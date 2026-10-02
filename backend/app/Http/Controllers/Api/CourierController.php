<?php

namespace App\Http\Controllers\Api;

use App\Enums\AssignmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\CourierAssignment;
use App\Models\CourierLocation;
use App\Models\Order;
use App\Models\Payment;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class CourierController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService) {}

    private function courierId(Request $request): int
    {
        $courierId = $request->user()?->courier?->id;

        abort_unless($courierId !== null, 403, 'Anda tidak memiliki akses sebagai kurir.');

        return $courierId;
    }

    private function own(Request $request, CourierAssignment $assignment): CourierAssignment
    {
        abort_unless(
            $assignment->courier_id === $this->courierId($request),
            403,
            'Anda tidak memiliki akses ke pengantaran ini.'
        );

        return $assignment;
    }

    private function assignmentData(CourierAssignment $assignment): array
    {
        $assignment->load([
            'order.customer.user:id,name',
            'order.items.product',
            'order.payment',
            'locations',
        ]);

        return [
            'id' => $assignment->id,
            'status' => $assignment->status,
            'assigned_at' => $assignment->assigned_at,
            'started_at' => $assignment->started_at,
            'completed_at' => $assignment->completed_at,
            'order' => [
                'id' => $assignment->order->id,
                'status' => $assignment->order->status,
                'subtotal' => $assignment->order->subtotal,
                'total' => $assignment->order->total,
                'delivery_address' => $assignment->order->delivery_address,
                'customer' => [
                    'name' => $assignment->order->customer->user->name,
                ],
                'items' => $assignment->order->items->map(fn ($item) => [
                    'product_name' => $item->product->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,
                ]),
                'payment' => [
                    'method' => $assignment->order->payment->method,
                    'status' => $assignment->order->payment->status,
                ],
            ],
            'latest_location' => $assignment->locations->last(),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $courierId = $this->courierId($request);

        $assignments = CourierAssignment::where('courier_id', $courierId)
            ->with(['order.customer.user:id,name', 'order.items.product', 'order.payment'])
            ->latest('courier_assignments.created_at')
            ->get();

        return response()->json([
            'data' => $assignments->map(fn ($a) => $this->assignmentData($a)),
            'message' => 'Data pengantaran berhasil diambil.',
        ], 200);
    }

    public function show(Request $request, CourierAssignment $assignment): JsonResponse
    {
        return response()->json([
            'data' => $this->assignmentData($this->own($request, $assignment)),
            'message' => 'Data pengantaran berhasil diambil.',
        ], 200);
    }

    public function start(Request $request, CourierAssignment $assignment): JsonResponse
    {
        $this->own($request, $assignment);

        try {
            $assignment = DB::transaction(function () use ($assignment, $request): CourierAssignment {
                $lockedAssignment = CourierAssignment::query()
                    ->whereKey($assignment->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $order = Order::query()->whereKey($lockedAssignment->order_id)->lockForUpdate()->firstOrFail();

                abort_unless(
                    $lockedAssignment->status === AssignmentStatus::ASSIGNED
                    && $order->status === OrderStatus::DITUGASKAN->value,
                    409,
                    'Pengantaran tidak dapat dimulai karena status pengantaran tidak sesuai.'
                );

                abort_if(
                    CourierAssignment::query()
                        ->where('courier_id', $lockedAssignment->courier_id)
                        ->where('status', AssignmentStatus::ACTIVE->value)
                        ->where('id', '!=', $lockedAssignment->id)
                        ->lockForUpdate()
                        ->exists(),
                    409,
                    'Kurir sedang mengantarkan pesanan lain.'
                );

                $lockedAssignment->update([
                    'status' => AssignmentStatus::ACTIVE->value,
                    'started_at' => now(),
                ]);
                $order->update(['status' => OrderStatus::DALAM_PENGANTARAN->value]);
                $order->statusHistories()->create([
                    'from_status' => OrderStatus::DITUGASKAN->value,
                    'to_status' => OrderStatus::DALAM_PENGANTARAN->value,
                    'changed_by' => $request->user()->id,
                ]);

                Log::info('Courier delivery started.', $this->logContext($lockedAssignment, $request));

                return $lockedAssignment->fresh();
            });
        } catch (HttpExceptionInterface $exception) {
            throw $exception;
        } catch (QueryException $exception) {
            return $this->serverError($exception, 'start', $assignment);
        } catch (Exception $exception) {
            return $this->serverError($exception, 'start', $assignment);
        }

        $this->notificationService->notifyDeliveryStarted($assignment->order()->firstOrFail());

        return response()->json([
            'data' => $this->assignmentData($assignment),
            'message' => 'Pengantaran berhasil dimulai.',
        ], 200);
    }

    public function location(Request $request, CourierAssignment $assignment): JsonResponse
    {
        $this->own($request, $assignment);

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['required', 'numeric', 'min:0', 'max:10000'],
            'recorded_at' => ['nullable', 'date'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $location = DB::transaction(function () use ($assignment, $request, $validated): CourierLocation {
                $lockedAssignment = CourierAssignment::query()
                    ->whereKey($assignment->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $order = Order::query()->whereKey($lockedAssignment->order_id)->lockForUpdate()->firstOrFail();
                $existing = isset($validated['idempotency_key'])
                    ? CourierLocation::query()
                        ->where('assignment_id', $lockedAssignment->id)
                        ->where('idempotency_key', $validated['idempotency_key'])
                        ->first()
                    : null;

                if ($existing !== null) {
                    return $existing;
                }

                abort_unless(
                    $lockedAssignment->status === AssignmentStatus::ACTIVE
                    && $order->status === OrderStatus::DALAM_PENGANTARAN->value,
                    409,
                    'Lokasi belum dapat diperbarui karena pengantaran belum aktif.'
                );

                $recordedAt = isset($validated['recorded_at'])
                    ? CarbonImmutable::parse($validated['recorded_at'])
                    : CarbonImmutable::now();
                $previous = CourierLocation::query()
                    ->where('assignment_id', $lockedAssignment->id)
                    ->latest('recorded_at')
                    ->latest('id')
                    ->first();

                if ($previous !== null) {
                    $distanceKm = $this->haversineDistanceKm(
                        (float) $previous->latitude,
                        (float) $previous->longitude,
                        (float) $validated['latitude'],
                        (float) $validated['longitude'],
                    );
                    $elapsedSeconds = $recordedAt->getTimestamp() - $previous->recorded_at->getTimestamp();

                    abort_if(
                        $distanceKm > 0 && $elapsedSeconds <= 0,
                        422,
                        'Waktu lokasi harus lebih baru dari lokasi sebelumnya.'
                    );
                    $speedKmh = $elapsedSeconds > 0 ? $distanceKm / ($elapsedSeconds / 3600) : 0;
                    abort_if($speedKmh > 150, 422, 'Perubahan lokasi tidak valid karena kecepatannya terlalu tinggi.');
                }

                $location = $lockedAssignment->locations()->create([
                    'courier_id' => $lockedAssignment->courier_id,
                    'latitude' => $validated['latitude'],
                    'longitude' => $validated['longitude'],
                    'accuracy_meters' => $validated['accuracy_meters'],
                    'recorded_at' => $recordedAt,
                    'idempotency_key' => $validated['idempotency_key'] ?? null,
                ]);

                Log::info('Courier location updated.', $this->logContext($lockedAssignment, $request));

                return $location;
            });
        } catch (HttpExceptionInterface $exception) {
            throw $exception;
        } catch (QueryException $exception) {
            return $this->serverError($exception, 'location', $assignment);
        } catch (Exception $exception) {
            return $this->serverError($exception, 'location', $assignment);
        }

        return response()->json([
            'data' => $location,
            'message' => 'Lokasi berhasil diperbarui.',
        ], 200);
    }

    public function cash(Request $request, CourierAssignment $assignment): JsonResponse
    {
        $this->own($request, $assignment);

        try {
            $payment = DB::transaction(function () use ($assignment, $request): Payment {
                $lockedAssignment = CourierAssignment::query()
                    ->whereKey($assignment->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $order = Order::query()->whereKey($lockedAssignment->order_id)->lockForUpdate()->firstOrFail();

                abort_unless(
                    $lockedAssignment->status === AssignmentStatus::ACTIVE
                    && $order->status === OrderStatus::DALAM_PENGANTARAN->value,
                    409,
                    'Pembayaran tunai hanya dapat dikonfirmasi saat pengantaran aktif.'
                );

                $payment = Payment::query()->where('order_id', $order->id)->lockForUpdate()->first();
                abort_if($payment === null, 409, 'Pembayaran untuk pesanan ini tidak ditemukan.');
                abort_if($payment->method !== PaymentMethod::CASH->value, 409, 'Pembayaran ini bukan pembayaran tunai.');
                abort_if($payment->status === PaymentStatus::PAID->value, 409, 'Pembayaran sudah lunas.');
                abort_unless($payment->status === PaymentStatus::PENDING->value, 409, 'Pembayaran tidak dapat dikonfirmasi pada status saat ini.');

                $payment->transitionTo(PaymentStatus::PAID, $request->user()->id);
                Log::info('Courier cash payment confirmed.', $this->logContext($lockedAssignment, $request));

                return $payment->fresh();
            });
        } catch (HttpExceptionInterface $exception) {
            throw $exception;
        } catch (QueryException $exception) {
            return $this->serverError($exception, 'cash', $assignment);
        } catch (Exception $exception) {
            return $this->serverError($exception, 'cash', $assignment);
        }

        return response()->json([
            'data' => [
                'id' => $payment->id,
                'method' => $payment->method,
                'status' => $payment->status,
                'verified_at' => $payment->verified_at,
            ],
            'message' => 'Pembayaran tunai berhasil dikonfirmasi.',
        ], 200);
    }

    public function complete(Request $request, CourierAssignment $assignment): JsonResponse
    {
        $this->own($request, $assignment);

        try {
            $assignment = DB::transaction(function () use ($assignment, $request): CourierAssignment {
                $lockedAssignment = CourierAssignment::query()
                    ->whereKey($assignment->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $order = Order::query()->whereKey($lockedAssignment->order_id)->lockForUpdate()->firstOrFail();
                $payment = Payment::query()->where('order_id', $order->id)->lockForUpdate()->first();

                abort_unless(
                    $lockedAssignment->status === AssignmentStatus::ACTIVE
                    && $order->status === OrderStatus::DALAM_PENGANTARAN->value,
                    409,
                    'Pengantaran tidak dapat diselesaikan karena status saat ini tidak sesuai.'
                );
                abort_if($payment === null, 409, 'Pengantaran tidak dapat diselesaikan karena pembayaran tidak ditemukan.');
                abort_unless(
                    in_array($payment->method, [PaymentMethod::QRIS->value, PaymentMethod::CASH->value], true)
                    && $payment->status === PaymentStatus::PAID->value,
                    409,
                    'Pengantaran tidak dapat diselesaikan karena pembayaran belum lunas.'
                );

                $lockedAssignment->update([
                    'status' => AssignmentStatus::COMPLETED->value,
                    'completed_at' => now(),
                ]);
                $order->update(['status' => OrderStatus::SELESAI->value]);
                $order->statusHistories()->create([
                    'from_status' => OrderStatus::DALAM_PENGANTARAN->value,
                    'to_status' => OrderStatus::SELESAI->value,
                    'changed_by' => $request->user()->id,
                ]);

                Log::info('Courier delivery completed.', $this->logContext($lockedAssignment, $request));

                return $lockedAssignment->fresh();
            });
        } catch (HttpExceptionInterface $exception) {
            throw $exception;
        } catch (QueryException $exception) {
            return $this->serverError($exception, 'complete', $assignment);
        } catch (Exception $exception) {
            return $this->serverError($exception, 'complete', $assignment);
        }

        $this->notificationService->notifyOrderCompleted($assignment->order()->firstOrFail());

        return response()->json([
            'data' => $this->assignmentData($assignment),
            'message' => 'Pengantaran berhasil diselesaikan.',
        ], 200);
    }

    private function haversineDistanceKm(float $latitudeFrom, float $longitudeFrom, float $latitudeTo, float $longitudeTo): float
    {
        $earthRadiusKm = 6371.0;
        $latitudeDelta = deg2rad($latitudeTo - $latitudeFrom);
        $longitudeDelta = deg2rad($longitudeTo - $longitudeFrom);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitudeFrom)) * cos(deg2rad($latitudeTo)) * sin($longitudeDelta / 2) ** 2;

        return $earthRadiusKm * 2 * asin(min(1, sqrt($a)));
    }

    private function logContext(CourierAssignment $assignment, Request $request): array
    {
        return [
            'assignment_id' => $assignment->id,
            'courier_id' => $assignment->courier_id,
            'order_id' => $assignment->order_id,
            'user_id' => $request->user()->id,
        ];
    }

    private function serverError(Exception $exception, string $action, CourierAssignment $assignment): JsonResponse
    {
        Log::error('Courier action failed.', [
            'action' => $action,
            'assignment_id' => $assignment->id,
            'courier_id' => $assignment->courier_id,
            'order_id' => $assignment->order_id,
            'exception' => $exception,
        ]);

        return response()->json([
            'data' => null,
            'message' => 'Tindakan kurir tidak dapat diproses saat ini.',
        ], 500);
    }
}
