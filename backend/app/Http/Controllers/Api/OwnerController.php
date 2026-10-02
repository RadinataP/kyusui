<?php

namespace App\Http\Controllers\Api;

use App\Enums\AssignmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\BusinessActionOccurred;
use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\Courier;
use App\Models\CourierAssignment;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OwnerController extends Controller
{
    public function qris(Request $request): JsonResponse
    {
        $this->authorizeOwner($request);

        $path = BusinessSetting::where('key', 'qris_image_path')->value('value');

        return response()->json([
            'data' => [
                'path' => $path,
                'image_url' => $path ? route('owner.qris.image') : null,
            ],
            'message' => 'Data QRIS berhasil dimuat.',
        ], 200);
    }

    public function updateQris(Request $request): JsonResponse
    {
        $this->authorizeOwner($request);

        $validated = $request->validate([
            'qris' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);

        $oldPath = BusinessSetting::where('key', 'qris_image_path')->value('value');

        $path = $validated['qris']->store('qris', 'local');

        BusinessSetting::updateOrCreate(
            ['key' => 'qris_image_path'],
            ['value' => $path]
        );

        // Hapus file lama jika ada
        if ($oldPath && $oldPath !== $path && Storage::disk('local')->exists($oldPath)) {
            Storage::disk('local')->delete($oldPath);
        }

        Log::info('QRIS image updated by owner', [
            'user_id' => $request->user()->id,
            'new_path' => $path,
        ]);

        return response()->json([
            'data' => ['path' => $path],
            'message' => 'QRIS berhasil diperbarui.',
        ], 200);
    }

    public function orders(Request $request): JsonResponse
    {
        $this->authorizeOwner($request);

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'status' => ['nullable', 'string'],
        ]);

        $query = Order::with(['customer:id,user_id,phone,address', 'customer.user:id,name', 'payment', 'assignments.courier.user:id,name'])
            ->latest('id');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $orders = $query->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
            'message' => 'Data pesanan berhasil dimuat.',
        ], 200);
    }

    public function order(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOwner($request);

        $order->load([
            'customer:id,user_id,phone,address',
            'customer.user:id,name',
            'items.product:id,name,price',
            'payment',
            'statusHistories.changedBy:id,name',
            'assignments.courier.user:id,name',
        ]);

        return response()->json([
            'data' => $order,
            'message' => 'Data pesanan berhasil dimuat.',
        ], 200);
    }

    public function couriers(Request $request): JsonResponse
    {
        $this->authorizeOwner($request);

        $busyCourierIds = CourierAssignment::where('status', AssignmentStatus::ACTIVE->value)
            ->pluck('courier_id');

        $availableCouriers = Courier::with('user:id,name')
            ->whereNotIn('id', $busyCourierIds)
            ->get();

        return response()->json([
            'data' => $availableCouriers,
            'message' => 'Data kurir tersedia berhasil dimuat.',
        ], 200);
    }

    public function pending(Request $request): JsonResponse
    {
        $this->authorizeOwner($request);

        $payments = Payment::with('order.customer.user:id,name')
            ->where('method', PaymentMethod::QRIS->value)
            ->where('status', PaymentStatus::WAITING_VERIFICATION->value)
            ->latest('id')
            ->paginate(20);

        return response()->json([
            'data' => $payments->items(),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'total' => $payments->total(),
            ],
            'message' => 'Data pembayaran menunggu verifikasi berhasil dimuat.',
        ], 200);
    }

    public function approve(Request $request, Payment $payment): JsonResponse
    {
        $this->authorizeOwner($request);

        abort_unless(
            $payment->method === PaymentMethod::QRIS->value &&
            $payment->status === PaymentStatus::WAITING_VERIFICATION->value,
            409,
            'Pembayaran tidak dapat diverifikasi pada status saat ini.'
        );

        $payment->loadMissing('order.customer.user');

        DB::transaction(function () use ($request, $payment): void {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $payment->load('order.customer.user');
            abort_unless(
                $payment->method === PaymentMethod::QRIS->value
                && $payment->status === PaymentStatus::WAITING_VERIFICATION->value,
                409,
                'Pembayaran tidak dapat diverifikasi pada status saat ini.',
            );
            $order = $payment->order;
            abort_unless(
                $order !== null && $order->status === OrderStatus::MENUNGGU_PEMBAYARAN->value,
                409,
                'Status pesanan tidak valid untuk verifikasi pembayaran.',
            );
            $payment->transitionTo(PaymentStatus::PAID, $request->user()->id);
            $order->update(['status' => OrderStatus::MENUNGGU_DIPROSES->value]);
            $order->statusHistories()->create([
                'from_status' => OrderStatus::MENUNGGU_PEMBAYARAN->value,
                'to_status' => OrderStatus::MENUNGGU_DIPROSES->value,
                'changed_by' => $request->user()->id,
            ]);
            event(new BusinessActionOccurred(
                $order->customer->user_id,
                'PAYMENT_QRIS_APPROVED',
                'Pembayaran QRIS Disetujui',
                'Bukti pembayaran QRIS Anda telah disetujui.',
                ['order_id' => $order->id],
            ));
        });

        Log::info('Payment approved by owner', [
            'payment_id' => $payment->id,
            'owner_id' => $request->user()->id,
        ]);

        return response()->json([
            'data' => $payment->fresh(),
            'message' => 'Pembayaran berhasil diverifikasi.',
        ], 200);
    }

    public function reject(Request $request, Payment $payment): JsonResponse
    {
        $this->authorizeOwner($request);

        abort_unless(
            $payment->method === PaymentMethod::QRIS->value &&
            $payment->status === PaymentStatus::WAITING_VERIFICATION->value,
            409,
            'Pembayaran tidak dapat ditolak pada status saat ini.'
        );

        $payment->loadMissing('order.customer.user');
        $proofPath = $payment->proof_path;

        DB::transaction(function () use ($payment, $request): void {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $payment->load('order.customer.user');
            abort_unless(
                $payment->method === PaymentMethod::QRIS->value
                && $payment->status === PaymentStatus::WAITING_VERIFICATION->value,
                409,
                'Pembayaran tidak dapat ditolak pada status saat ini.',
            );

            $payment->update([
                'proof_path' => null,
                'verified_by' => null,
                'verified_at' => null,
                'idempotency_key' => null,
                'proof_size' => null,
                'proof_mime_type' => null,
                'proof_checksum' => null,
            ]);
            $payment->transitionTo(PaymentStatus::PENDING, $request->user()->id);
            event(new BusinessActionOccurred(
                $payment->order->customer->user_id,
                'PAYMENT_QRIS_REJECTED',
                'Bukti Pembayaran Ditolak',
                'Silakan unggah kembali bukti pembayaran QRIS Anda.',
                ['order_id' => $payment->order_id],
            ));
        });

        // Hapus file di luar transaction, tapi bungkus dengan try-catch agar tidak memicu 500 jika gagal
        if ($proofPath) {
            try {
                if (Storage::disk('local')->exists($proofPath)) {
                    Storage::disk('local')->delete($proofPath);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to delete rejected QRIS proof', [
                    'path' => $proofPath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Payment rejected by owner', [
            'payment_id' => $payment->id,
            'owner_id' => $request->user()->id,
        ]);

        return response()->json([
            'data' => $payment->fresh(),
            'message' => 'Pembayaran berhasil ditolak.',
        ], 200);
    }

    public function process(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOwner($request);

        abort_unless(
            $order->status === OrderStatus::MENUNGGU_DIPROSES->value,
            409,
            'Pesanan tidak dapat diproses pada status saat ini.'
        );

        $order->loadMissing('customer.user');

        DB::transaction(function () use ($order, $request): void {
            $order = Order::where('id', $order->id)->lockForUpdate()->first();

            $order->update(['status' => OrderStatus::DIPROSES->value]);

            $order->statusHistories()->create([
                'from_status' => OrderStatus::MENUNGGU_DIPROSES->value,
                'to_status' => OrderStatus::DIPROSES->value,
                'changed_by' => $request->user()->id,
            ]);

            event(new BusinessActionOccurred(
                $order->customer->user_id,
                'ORDER_PROCESSED',
                'Pesanan Sedang Diproses',
                'Pesanan Anda sedang diproses oleh depot.',
                ['order_id' => $order->id]
            ));
        });

        Log::info('Order processed by owner', [
            'order_id' => $order->id,
            'owner_id' => $request->user()->id,
        ]);

        return response()->json([
            'data' => $order->fresh(),
            'message' => 'Pesanan berhasil diproses.',
        ], 200);
    }

    public function assign(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOwner($request);

        $validated = $request->validate([
            'courier_id' => ['required', 'exists:couriers,id'],
        ]);

        abort_unless(
            $order->status === OrderStatus::DIPROSES->value,
            409,
            'Pesanan tidak dapat ditugaskan pada status saat ini.'
        );

        $order->loadMissing('customer.user');

        $assignment = DB::transaction(function () use ($order, $validated, $request): CourierAssignment {
            $courier = Courier::query()
                ->whereKey($validated['courier_id'])
                ->lockForUpdate()
                ->firstOrFail();
            abort_if(
                $courier->assignments()->where('status', AssignmentStatus::ACTIVE->value)->exists(),
                409,
                'Kurir sedang mengantarkan pesanan lain.',
            );
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                $lockedOrder->status === OrderStatus::DIPROSES->value,
                409,
                'Pesanan tidak dapat ditugaskan pada status saat ini.',
            );

            $assignment = CourierAssignment::create([
                'order_id' => $lockedOrder->id,
                'courier_id' => $courier->id,
                'status' => AssignmentStatus::ASSIGNED->value,
                'assigned_at' => now(),
            ]);
            $lockedOrder->update(['status' => OrderStatus::DITUGASKAN->value]);
            $lockedOrder->statusHistories()->create([
                'from_status' => OrderStatus::DIPROSES->value,
                'to_status' => OrderStatus::DITUGASKAN->value,
                'changed_by' => $request->user()->id,
            ]);
            event(new BusinessActionOccurred(
                $courier->user_id,
                'COURIER_ASSIGNED',
                'Tugas Pengantaran Baru',
                'Anda mendapat tugas pengantaran baru.',
                ['order_id' => $lockedOrder->id, 'assignment_id' => $assignment->id],
            ));

            return $assignment;
        });

        Log::info('Courier assigned to order', [
            'order_id' => $order->id,
            'courier_id' => $validated['courier_id'],
            'owner_id' => $request->user()->id,
        ]);

        return response()->json([
            'data' => $assignment->load('courier.user:id,name'),
            'message' => 'Kurir berhasil ditugaskan.',
        ], 200);
    }

    public function proof(Request $request, Payment $payment): BinaryFileResponse
    {
        $this->authorizeOwner($request);

        abort_unless(
            $payment->method === PaymentMethod::QRIS->value && $payment->proof_path,
            404,
            'Bukti pembayaran tidak ditemukan.'
        );

        abort_unless(
            Storage::disk('local')->exists($payment->proof_path),
            404,
            'File bukti pembayaran tidak ditemukan di server.'
        );

        // Gunakan response()->file agar bisa di-embed langsung di image view Android, bukan force download
        return response()->file(
            Storage::disk('local')->path($payment->proof_path),
            ['Content-Type' => Storage::disk('local')->mimeType($payment->proof_path) ?: 'image/jpeg']
        );
    }

    private function authorizeOwner(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user !== null && $user->role?->name === 'OWNER',
            403,
            'Akses ditolak. Hanya Owner yang dapat melakukan tindakan ini.'
        );
    }
}
