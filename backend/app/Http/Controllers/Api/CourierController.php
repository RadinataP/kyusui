<?php

namespace App\Http\Controllers\Api;

use App\Enums\AssignmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\CourierAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CourierController extends Controller
{
    private function courierId(Request $request): int
    {
        $courierId = $request->user()?->courier?->id;

        abort_unless($courierId !== null, 403, 'Anda tidak memiliki akses sebagai kurir.');

        return $courierId;
    }

    private function own(Request $request, CourierAssignment $assignment): CourierAssignment
    {
        abort_unless($assignment->courier_id === $this->courierId($request), 403, 'Anda tidak memiliki akses ke pengantaran ini.');

        return $assignment;
    }

    private function assignmentData(CourierAssignment $assignment): CourierAssignment
    {
        return $assignment->load([
            'order.customer.user:id,name',
            'order.items.product',
            'order.payment',
            'locations',
        ]);
    }

    public function index(Request $request): array
    {
        $assignments = $request->user()->courier
            ->assignments()
            ->with(['order.customer.user:id,name', 'order.items.product', 'order.payment'])
            ->latest('courier_assignments.created_at')
            ->get();

        return [
            'data' => $assignments,
            'meta' => [],
            'message' => 'Data pengantaran berhasil diambil.',
        ];
    }

    public function show(Request $request, CourierAssignment $assignment): array
    {
        return [
            'data' => $this->assignmentData($this->own($request, $assignment)),
            'message' => 'Data pengantaran berhasil diambil.',
        ];
    }

    public function start(Request $request, CourierAssignment $assignment): array
    {
        $assignment = $this->own($request, $assignment)->load('order');

        abort_unless(
            $assignment->status === AssignmentStatus::ASSIGNED
            && $assignment->order?->status === OrderStatus::DITUGASKAN->value,
            409,
            'Pengantaran tidak dapat dimulai karena status pengantaran tidak sesuai.'
        );

        DB::transaction(function () use ($assignment): void {
            $assignment->update([
                'status' => AssignmentStatus::ACTIVE,
                'started_at' => now(),
            ]);
            $assignment->order->update(['status' => OrderStatus::DALAM_PENGANTARAN->value]);
            $assignment->order->histories()->create([
                'from_status' => OrderStatus::DITUGASKAN->value,
                'to_status' => OrderStatus::DALAM_PENGANTARAN->value,
            ]);
        });

        return [
            'data' => $this->assignmentData($assignment->fresh()),
            'message' => 'Pengantaran berhasil dimulai.',
        ];
    }

    public function location(Request $request, CourierAssignment $assignment): array
    {
        $assignment = $this->own($request, $assignment);

        abort_unless(
            $assignment->status === AssignmentStatus::ACTIVE,
            409,
            'Lokasi belum dapat diperbarui karena pengantaran belum aktif.'
        );

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['required', 'numeric', 'min:0', 'max:10000'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        $location = $assignment->locations()->create([
            'courier_id' => $assignment->courier_id,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'accuracy_meters' => $validated['accuracy_meters'],
            'recorded_at' => $validated['recorded_at'] ?? now(),
        ]);

        return ['data' => $location, 'message' => 'Lokasi berhasil diperbarui.'];
    }

    public function cash(Request $request, CourierAssignment $assignment): array
    {
        $assignment = $this->own($request, $assignment)->load('order.payment');

        abort_unless(
            $assignment->status === AssignmentStatus::ACTIVE
            && $assignment->order?->status === OrderStatus::DALAM_PENGANTARAN->value,
            409,
            'Pembayaran tunai hanya dapat dikonfirmasi saat pengantaran aktif.'
        );

        $payment = $assignment->order?->payment;
        abort_if($payment === null, 409, 'Pembayaran untuk pesanan ini tidak ditemukan.');
        abort_if($payment->method !== PaymentMethod::CASH->value, 409, 'Pembayaran ini bukan pembayaran tunai.');
        abort_if($payment->status === PaymentStatus::PAID->value, 409, 'Pembayaran sudah lunas.');
        abort_unless($payment->status === PaymentStatus::PENDING->value, 409, 'Pembayaran tidak dapat dikonfirmasi pada status saat ini.');

        DB::transaction(function () use ($payment, $request): void {
            $payment->transitionTo(PaymentStatus::PAID, $request->user()->id);
        });

        return ['data' => $payment->fresh(), 'message' => 'Pembayaran tunai berhasil dikonfirmasi.'];
    }

    public function complete(Request $request, CourierAssignment $assignment): array
    {
        $assignment = $this->own($request, $assignment)->load('order.payment');

        abort_unless(
            $assignment->status === AssignmentStatus::ACTIVE
            && $assignment->order?->status === OrderStatus::DALAM_PENGANTARAN->value,
            409,
            'Pengantaran tidak dapat diselesaikan karena status saat ini tidak sesuai.'
        );

        $payment = $assignment->order?->payment;
        abort_if($payment === null, 409, 'Pengantaran tidak dapat diselesaikan karena pembayaran tidak ditemukan.');
        abort_unless(
            in_array($payment->method, [PaymentMethod::QRIS->value, PaymentMethod::CASH->value], true)
            && $payment->status === PaymentStatus::PAID->value,
            409,
            'Pengantaran tidak dapat diselesaikan karena pembayaran belum lunas.'
        );

        DB::transaction(function () use ($assignment): void {
            $assignment->update([
                'status' => AssignmentStatus::COMPLETED,
                'completed_at' => now(),
            ]);
            $assignment->order->update(['status' => OrderStatus::SELESAI->value]);
            $assignment->order->histories()->create([
                'from_status' => OrderStatus::DALAM_PENGANTARAN->value,
                'to_status' => OrderStatus::SELESAI->value,
            ]);
        });

        return [
            'data' => $this->assignmentData($assignment->fresh()),
            'message' => 'Pengantaran berhasil diselesaikan.',
        ];
    }
}
