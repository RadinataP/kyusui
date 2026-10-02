<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\BusinessActionOccurred;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class PaymentController extends Controller
{
    public function history(Request $request): JsonResponse
    {
        $customer = $request->user()->customer;
        abort_unless($customer !== null, 403, 'Profil customer tidak ditemukan.');

        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'payment_method' => ['sometimes', 'string', 'in:QRIS,CASH'],
            'payment_status' => ['sometimes', 'string', 'in:PENDING,WAITING_VERIFICATION,PAID'],
        ]);

        $payments = Payment::query()
            ->whereHas('order', fn ($query) => $query->where('customer_id', $customer->id))
            ->when(isset($validated['payment_method']), fn ($query) => $query->where('method', $validated['payment_method']))
            ->when(isset($validated['payment_status']), fn ($query) => $query->where('status', $validated['payment_status']))
            ->latest('id')
            ->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'data' => PaymentResource::collection($payments->items()),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
            'message' => 'Riwayat pembayaran berhasil dimuat.',
        ], 200);
    }

    public function activeQris(Request $request): JsonResponse
    {
        abort_unless(
            in_array($request->user()->role?->name, ['CUSTOMER', 'OWNER'], true),
            403,
            'Anda tidak memiliki akses ke QRIS aktif.',
        );
        abort_unless(BusinessSetting::query()->where('key', 'qris_image_path')->exists(), 404, 'QRIS aktif belum tersedia.');

        return response()->json([
            'data' => ['image_url' => route('customer.qris.image')],
            'message' => 'Data QRIS berhasil dimuat.',
        ], 200);
    }

    public function activeQrisImage(Request $request): BinaryFileResponse|JsonResponse|Response
    {
        abort_unless(
            in_array($request->user()->role?->name, ['CUSTOMER', 'OWNER'], true),
            403,
            'Anda tidak memiliki akses ke QRIS aktif.',
        );
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
            $storedProofPath = null;
            $previousProofPath = $payment->proof_path;
            $payment = DB::transaction(function () use ($request, $order, $payment, $proofFile, $validated, &$storedProofPath): Payment {
                $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
                abort_if(
                    $lockedPayment->proof_path !== null && $lockedPayment->status === PaymentStatus::WAITING_VERIFICATION->value,
                    409,
                    'Bukti pembayaran sedang menunggu verifikasi.',
                );
                abort_unless($lockedPayment->status === PaymentStatus::PENDING->value, 409, 'Status pembayaran telah berubah.');

                $proofPath = $proofFile->store('payment-proofs/order-'.$order->id, 'local');
                $storedProofPath = $proofPath;
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
            if ($previousProofPath !== null && $previousProofPath !== $storedProofPath) {
                Storage::disk('local')->delete($previousProofPath);
            }
        } catch (HttpExceptionInterface $exception) {
            if ($storedProofPath !== null) {
                Storage::disk('local')->delete($storedProofPath);
            }
            throw $exception;
        } catch (QueryException $exception) {
            if ($storedProofPath !== null) {
                Storage::disk('local')->delete($storedProofPath);
            }
            Log::error('Database error while uploading QRIS proof.', ['order_id' => $order->id, 'exception' => $exception]);

            return response()->json([
                'data' => null,
                'message' => 'Gagal mengunggah bukti pembayaran. Silakan coba lagi.',
            ], 500);
        }

        $notificationData = ['order_id' => $order->id, 'payment_id' => $payment->id];
        User::query()
            ->whereHas('role', fn ($query) => $query->where('name', 'OWNER'))
            ->pluck('id')
            ->each(fn (int $ownerUserId) => event(new BusinessActionOccurred(
                userId: $ownerUserId,
                type: 'PAYMENT_QRIS_PROOF_UPLOADED',
                title: 'Bukti Pembayaran Baru',
                body: 'Bukti pembayaran QRIS baru menunggu verifikasi.',
                data: $notificationData,
            )));
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
