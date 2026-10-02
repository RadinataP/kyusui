<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    public function activeQris(): array
    {
        $path = BusinessSetting::where('key', 'qris_image_path')->value('value');

        abort_unless($path, 404, 'QRIS aktif belum tersedia.');

        return [
            'data' => ['image_url' => route('customer.qris.image'), 'path' => $path],
            'message' => 'Data QRIS berhasil dimuat.',
        ];
    }

    public function activeQrisImage()
    {
        $path = BusinessSetting::where('key', 'qris_image_path')->value('value');

        abort_unless($path && Storage::disk('local')->exists($path), 404, 'QRIS aktif belum tersedia.');

        return Storage::disk('local')->download($path, 'qris.png', ['Content-Type' => Storage::disk('local')->mimeType($path)]);
    }

    public function proof(Request $r, Order $order): array
    {
        abort_unless($order->customer_id === $r->user()->customer->id, 403);
        abort_if($order->payment->method !== PaymentMethod::QRIS->value || $order->payment->status !== PaymentStatus::PENDING->value || ! in_array($order->status, [OrderStatus::MENUNGGU_PEMBAYARAN->value, OrderStatus::MENUNGGU_DIPROSES->value], true), 409);
        $v = $r->validate(['proof' => 'required|file|mimes:jpg,jpeg,png|max:5120']);
        $path = DB::transaction(function () use ($r, $order) {
            $p = $r->file('proof')->store('payment-proofs', 'local');
            $order->payment->update(['proof_path' => $p]);
            $order->payment->transitionTo(PaymentStatus::WAITING_VERIFICATION, $r->user()->id);

            return $p;
        });

        return ['data' => $order->fresh('payment')->payment, 'message' => 'Bukti pembayaran berhasil diunggah.'];
    }
}
