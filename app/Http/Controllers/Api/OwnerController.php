<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\BusinessActionOccurred;
use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\Courier;
use App\Models\CourierAssignment;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OwnerController extends Controller
{
    public function qris()
    {
        return ['data' => ['path' => BusinessSetting::where('key', 'qris_image_path')->value('value')], 'message' => 'Data berhasil dimuat.'];
    }

    public function updateQris(Request $request)
    {
        $validated = $request->validate(['qris' => 'required|file|mimes:jpg,jpeg,png|max:5120']);
        $path = $validated['qris']->store('qris', 'local');
        BusinessSetting::updateOrCreate(['key' => 'qris_image_path'], ['value' => $path]);

        return ['data' => ['path' => $path], 'message' => 'QRIS berhasil diperbarui.'];
    }

    public function orders()
    {
        return ['data' => Order::with('customer.user', 'payment', 'assignments.courier.user')->latest()->get(), 'message' => 'Data berhasil dimuat.'];
    }

    public function order(Order $order): array
    {
        return ['data' => $order->load('customer.user', 'items.product', 'payment.statusHistories.actor', 'assignments.courier.user', 'histories.actor'), 'message' => 'Data pesanan berhasil dimuat.'];
    }

    public function couriers(): array
    {
        return ['data' => CourierAssignment::query()->where('status', 'ACTIVE')->pluck('courier_id')->pipe(fn ($busyIds) => Courier::with('user')->whereNotIn('id', $busyIds)->get()), 'message' => 'Data kurir tersedia berhasil dimuat.'];
    }

    public function pending()
    {
        return ['data' => Payment::with('order.customer.user')->where('method', 'QRIS')->where('status', 'WAITING_VERIFICATION')->get(), 'message' => 'Data berhasil dimuat.'];
    }

    public function approve(Request $r, Payment $payment)
    {
        abort_if($payment->method !== 'QRIS' || $payment->status !== 'WAITING_VERIFICATION', 409);
        $payment->loadMissing('order.customer');
        DB::transaction(function () use ($r, $payment) {
            $payment->update(['verified_by' => $r->user()->id, 'verified_at' => now()]);
            $payment->transitionTo(PaymentStatus::PAID, $r->user()->id);
            $o = $payment->order;
            $o->update(['status' => OrderStatus::MENUNGGU_DIPROSES->value]);
            $o->histories()->create(['from_status' => OrderStatus::MENUNGGU_PEMBAYARAN->value, 'to_status' => $o->status, 'changed_by' => $r->user()->id]);
            event(new BusinessActionOccurred($o->customer->user_id, 'PAYMENT_VERIFIED', 'Pembayaran QRIS diverifikasi', 'Bukti pembayaran QRIS Anda telah disetujui.', ['order_id' => $o->id]));
        });

        return ['data' => $payment->fresh(), 'message' => 'Pembayaran berhasil diverifikasi.'];
    }

    public function reject(Request $r, Payment $payment)
    {
        abort_if($payment->method !== 'QRIS' || $payment->status !== 'WAITING_VERIFICATION', 409);
        $payment->loadMissing('order.customer');
        $proofPath = $payment->proof_path;
        DB::transaction(function () use ($payment, $r): void {
            $payment->update(['proof_path' => null, 'verified_by' => null, 'verified_at' => null]);
            $payment->transitionTo(PaymentStatus::PENDING, $r->user()->id);
            event(new BusinessActionOccurred($payment->order->customer->user_id, 'PAYMENT_REJECTED', 'Bukti pembayaran ditolak', 'Silakan unggah kembali bukti pembayaran QRIS Anda.', ['order_id' => $payment->order_id]));
        });
        if ($proofPath) {
            Storage::disk('local')->delete($proofPath);
        }

        return ['data' => $payment, 'message' => 'Pembayaran berhasil ditolak.'];
    }

    public function process(Request $r, Order $order)
    {
        abort_unless(in_array($order->status, [OrderStatus::MENUNGGU_DIPROSES->value], true), 409);
        DB::transaction(function () use ($order, $r): void {
            $order->update(['status' => OrderStatus::DIPROSES->value]);
            $order->histories()->create(['from_status' => OrderStatus::MENUNGGU_DIPROSES->value, 'to_status' => $order->status, 'changed_by' => $r->user()->id]);
            event(new BusinessActionOccurred($order->customer->user_id, 'ORDER_PROCESSING', 'Pesanan sedang diproses', 'Pesanan Anda sedang diproses oleh depot.', ['order_id' => $order->id]));
        });

        return ['data' => $order, 'message' => 'Pesanan berhasil diproses.'];
    }

    public function assign(Request $r, Order $order)
    {
        $v = $r->validate(['courier_id' => 'required|exists:couriers,id']);
        abort_unless(in_array($order->status, [OrderStatus::DIPROSES->value], true), 409);
        $a = DB::transaction(function () use ($order, $v, $r) {
            abort_if(CourierAssignment::where('courier_id', $v['courier_id'])->where('status', 'ACTIVE')->exists(), 409, 'Kurir sedang mengantarkan pesanan lain.');
            $assignment = CourierAssignment::create(['order_id' => $order->id, 'courier_id' => $v['courier_id'], 'status' => 'ASSIGNED', 'assigned_at' => now()]);
            $order->update(['status' => OrderStatus::DITUGASKAN->value]);
            $order->histories()->create(['from_status' => OrderStatus::DIPROSES->value, 'to_status' => $order->status, 'changed_by' => $r->user()->id]);
            event(new BusinessActionOccurred($assignment->courier->user_id, 'ORDER_ASSIGNED', 'Pesanan baru ditugaskan', 'Anda mendapat tugas pengantaran baru.', ['order_id' => $order->id, 'assignment_id' => $assignment->id]));

            return $assignment;
        });

        return ['data' => $a->load('courier.user'), 'message' => 'Kurir berhasil ditugaskan.'];
    }

    public function proof(Payment $payment)
    {
        abort_unless($payment->method === 'QRIS' && $payment->proof_path, 404, 'Bukti pembayaran tidak ditemukan.');
        abort_unless(Storage::disk('local')->exists($payment->proof_path), 404, 'Bukti pembayaran tidak ditemukan.');

        return Storage::disk('local')->download($payment->proof_path, 'payment-proof.'.$this->extension($payment->proof_path));
    }

    private function extension(string $path): string
    {
        return pathinfo($path, PATHINFO_EXTENSION) ?: 'bin';
    }
}
