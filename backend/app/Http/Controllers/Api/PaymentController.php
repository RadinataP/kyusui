<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\BusinessActionOccurred;
use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PaymentController extends Controller
{
    public function activeQris(Request $request): JsonResponse
    {
        $customer = $request->user()->customer;
        abort_unless($customer !== null, 403, 'Akses ditolak. Profil customer tidak ditemukan.');
        abort_unless(BusinessSetting::query()->where('key', 'qris_image_path')->exists(), 404, 'QRIS aktif belum tersedia.');

        return response()->json([
            'data' => ['image_url' => route('customer.qris.image')],
            'message' => 'Data QRIS berhasil dimuat.',
        ], 200);
    }

    public function activeQrisImage(Request $request): BinaryFileResponse|JsonResponse|Response
    {
        $customer = $request->user()->customer;
        abort_unless($customer !== null, 403, 'Akses ditolak. Profil customer tidak ditemukan.');
        $path = BusinessSetting::query()->where('key', 'qris_image_path')->value('value');
        abort_unless($path && Storage::disk('local')->exists($path), 404, 'QRIS aktif belum tersedia.');

        $disk = Storage::disk('local');
        $lastModified = $disk->lastModified($path);
        $etag = '"'.md5($path.$lastModified).'"';
        $headers = [
            'Cache-Control' => 'public, max-age=3600',
            'ETag' => $etag,
            'Last-Modified' => gmdate('D, d M Y H:i:s', $lastModified).' GMT',
            'Content-Type' => $disk->mimeType($path) ?: 'image/png',
        ];

        if ($request->header('If-None-Match') === $etag) {
            return response('', 304, $headers);
        }

        return response()->file($disk->path($path), $headers);
    }

    public function proof(Request $request, Order $order): JsonResponse
    {
        $customer = $request->user()->customer;
        abort_unless($customer !== null && $order->customer_id === $customer->id, 403, 'Anda tidak memiliki akses ke pesanan ini.');
        $payment = $order->payment;
        abort_unless($payment !== null, 404, 'Data pembayaran tidak ditemukan.');

        $validatedKey = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:64'],
        ]);

        if ($payment->idempotency_key === $validatedKey['idempotency_key'] && $payment->proof_path !== null) {
            return response()->json([
                'data' => $payment,
                'message' => 'Bukti pembayaran dengan idempotency key tersebut sudah tersedia.',
            ], 200);
        }

        $validated = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:64'],
            'proof' => ['required_without:proof_image', 'image', 'mimes:jpg,jpeg,png', 'max:2048', $this->imageDimensionsRule()],
            'proof_image' => ['required_without:proof', 'image', 'mimes:jpg,jpeg,png', 'max:2048', $this->imageDimensionsRule()],
        ]);
        $proofFile = $validated['proof'] ?? $validated['proof_image'];

        abort_unless($payment->method === PaymentMethod::QRIS->value, 409, 'Upload bukti hanya untuk pembayaran QRIS.');
        abort_unless($order->status === OrderStatus::MENUNGGU_PEMBAYARAN->value, 409, 'Bukti pembayaran hanya dapat diunggah saat pesanan menunggu pembayaran.');
        abort_unless($payment->status === PaymentStatus::PENDING->value, 409, 'Bukti pembayaran tidak dapat diunggah pada status saat ini.');

        try {
            $payment = DB::transaction(function () use ($request, $order, $payment, $proofFile, $validated): Payment {
                $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
                abort_if(
                    $lockedPayment->proof_path !== null && $lockedPayment->status === PaymentStatus::WAITING_VERIFICATION->value,
                    409,
                    'Bukti pembayaran sedang menunggu verifikasi.',
                );
                abort_unless($lockedPayment->status === PaymentStatus::PENDING->value, 409, 'Status pembayaran telah berubah.');

                if ($lockedPayment->proof_path !== null) {
                    Storage::disk('local')->delete($lockedPayment->proof_path);
                }
                $proofPath = $proofFile->store('payment-proofs/order-'.$order->id, 'local');
                $proofChecksum = md5_file($proofFile->getRealPath());
                abort_unless($proofChecksum !== false, 422, 'Bukti pembayaran tidak dapat diproses.');
                $lockedPayment->update([
                    'proof_path' => $proofPath,
                    'idempotency_key' => $validated['idempotency_key'],
                    'proof_size' => $proofFile->getSize(),
                    'proof_mime_type' => $proofFile->getMimeType(),
                    'proof_checksum' => $proofChecksum,
                    'verified_by' => null,
                    'verified_at' => null,
                ]);
                $lockedPayment->transitionTo(PaymentStatus::WAITING_VERIFICATION, $request->user()->id);

                return $lockedPayment->fresh();
            });
        } catch (QueryException $exception) {
            Log::error('Database error while uploading QRIS proof.', ['order_id' => $order->id, 'exception' => $exception]);

            return response()->json([
                'data' => null,
                'message' => 'Gagal mengunggah bukti pembayaran. Silakan coba lagi.',
            ], 500);
        }

        event(new BusinessActionOccurred(
            userId: $request->user()->id,
            type: 'PAYMENT_QRIS_PROOF_UPLOADED',
            title: 'Bukti Pembayaran Diunggah',
            body: 'Bukti pembayaran Anda sedang menunggu verifikasi.',
            data: ['order_id' => $order->id, 'payment_id' => $payment->id],
        ));
        Log::info('QRIS proof uploaded.', [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'customer_id' => $customer->id,
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'data' => $payment,
            'message' => 'Bukti pembayaran berhasil diunggah.',
        ], 200);
    }

    public function verify(Request $request, Order $order): JsonResponse
    {
        abort_unless($request->user()->role?->name === 'OWNER', 403, 'Akses hanya untuk owner.');
        $validated = $request->validate(['action' => ['required', 'string', 'in:approve,reject']]);
        $proofPath = null;

        try {
            [$payment, $customerUserId] = DB::transaction(function () use ($request, $order, $validated, &$proofPath): array {
                $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                $payment = Payment::query()->where('order_id', $lockedOrder->id)->lockForUpdate()->firstOrFail();
                abort_unless($payment->method === PaymentMethod::QRIS->value, 409, 'Verifikasi ini hanya berlaku untuk QRIS.');
                abort_unless($payment->status === PaymentStatus::WAITING_VERIFICATION->value, 409, 'Pembayaran tidak sedang menunggu verifikasi.');
                abort_unless($payment->proof_path !== null, 409, 'Bukti pembayaran tidak ditemukan.');
                $proofPath = $payment->proof_path;

                if ($validated['action'] === 'approve') {
                    abort_unless($lockedOrder->status === OrderStatus::MENUNGGU_PEMBAYARAN->value, 409, 'Order tidak dapat disetujui pada status saat ini.');
                    $payment->transitionTo(PaymentStatus::PAID, $request->user()->id);
                    $lockedOrder->update(['status' => OrderStatus::MENUNGGU_DIPROSES->value]);
                    $lockedOrder->statusHistories()->create([
                        'from_status' => OrderStatus::MENUNGGU_PEMBAYARAN->value,
                        'to_status' => OrderStatus::MENUNGGU_DIPROSES->value,
                        'changed_by' => $request->user()->id,
                    ]);
                } else {
                    $payment->transitionTo(PaymentStatus::PENDING, $request->user()->id);
                    $payment->update([
                        'proof_path' => null,
                        'idempotency_key' => null,
                        'proof_size' => null,
                        'proof_mime_type' => null,
                        'proof_checksum' => null,
                    ]);
                }

                return [$payment->fresh(), $lockedOrder->customer()->with('user')->firstOrFail()->user_id];
            });
        } catch (QueryException $exception) {
            Log::error('Database error while verifying QRIS payment.', ['order_id' => $order->id, 'exception' => $exception]);

            return response()->json([
                'data' => null,
                'message' => 'Gagal memverifikasi pembayaran. Silakan coba lagi.',
            ], 500);
        }

        if ($validated['action'] === 'reject' && $proofPath !== null) {
            Storage::disk('local')->delete($proofPath);
        }
        $eventType = $validated['action'] === 'approve' ? 'PAYMENT_QRIS_APPROVED' : 'PAYMENT_QRIS_REJECTED';
        event(new BusinessActionOccurred(
            userId: $customerUserId,
            type: $eventType,
            title: $validated['action'] === 'approve' ? 'Pembayaran QRIS Disetujui' : 'Pembayaran QRIS Ditolak',
            body: $validated['action'] === 'approve'
                ? 'Pembayaran QRIS Anda telah disetujui.'
                : 'Bukti QRIS Anda ditolak. Silakan unggah bukti baru.',
            data: ['order_id' => $order->id, 'payment_id' => $payment->id],
        ));
        Log::info('QRIS payment verified.', [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'action' => $validated['action'],
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'data' => $payment,
            'message' => $validated['action'] === 'approve'
                ? 'Pembayaran QRIS berhasil disetujui.'
                : 'Pembayaran QRIS berhasil ditolak.',
        ], 200);
    }

    private function imageDimensionsRule(): callable
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $dimensions = @getimagesize($value->getRealPath());
            if ($dimensions === false || $dimensions[0] > 4000 || $dimensions[1] > 4000 || $dimensions[0] < 100 || $dimensions[1] < 100) {
                $fail('Ukuran gambar harus berada di antara 100x100 dan 4000x4000 piksel.');
            }
        };
    }
}
