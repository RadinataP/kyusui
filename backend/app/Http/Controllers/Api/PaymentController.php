<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\BusinessActionOccurred;
use App\Http\Controllers\Controller;
use App\Http\Resources\ActiveQrisResource;
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
    /**
     * `GET /customer/payments` — spec 06 section 11.4.
     */
    public function history(Request $request): JsonResponse
    {
        $customer = $request->user()->customer;
        abort_unless($customer !== null, 403, 'Profil customer tidak ditemukan.');

        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'payment_method' => ['sometimes', 'string', 'in:'.PaymentMethod::QRIS->value.','.PaymentMethod::CASH->value],
            'payment_status' => ['sometimes', 'string', 'in:'.implode(',', array_column(PaymentStatus::cases(), 'value'))],
        ]);

        $payments = Payment::query()
            ->whereHas('order', fn ($query) => $query->where('customer_id', $customer->id))
            ->when(isset($validated['payment_method']), fn ($query) => $query->where('payment_method', $validated['payment_method']))
            ->when(isset($validated['payment_status']), fn ($query) => $query->where('payment_status', $validated['payment_status']))
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

    /**
     * `GET /customer/payment/qris` — spec 06 section 11.1 dan 7.6.
     */
    public function activeQris(Request $request): JsonResponse
    {
        $setting = BusinessSetting::active();
        abort_unless($setting->hasActiveQris(), 404, 'QRIS aktif belum tersedia.');

        return response()->json([
            'data' => new ActiveQrisResource($setting),
            'message' => 'Data QRIS berhasil dimuat.',
        ], 200);
    }

    /**
     * Private file delivery untuk QRIS image.
     *
     * Preserved endpoint: tidak ada di katalog spec, tetapi spec 06 section 7.6
     * mengizinkan "authorized temporary URL atau mekanisme private file
     * delivery yang ditentukan implementasi backend". Path filesystem tidak
     * pernah dikembalikan ke client.
     */
    public function activeQrisImage(Request $request): BinaryFileResponse|JsonResponse|Response
    {
        $path = BusinessSetting::active()->qris_image;
        abort_unless(is_string($path) && $path !== '' && Storage::disk('local')->exists($path), 404, 'QRIS aktif belum tersedia.');

        $disk = Storage::disk('local');
        $lastModified = $disk->lastModified($path);
        $etag = '"'.md5($path.$lastModified).'"';
        $headers = [
            'Cache-Control' => 'private, max-age=3600',
            'ETag' => $etag,
            'Last-Modified' => gmdate('D, d M Y H:i:s', $lastModified).' GMT',
            'Content-Type' => $disk->mimeType($path) ?: 'image/png',
        ];

        if ($request->header('If-None-Match') === $etag) {
            return response('', 304, $headers);
        }

        return response()->file($disk->path($path), $headers);
    }

    /**
     * `GET /customer/orders/{order}/payment` — spec 06 section 11.3.
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        $customer = $request->user()->customer;
        abort_unless($customer !== null && $order->customer_id === $customer->id, 404);

        $payment = $order->payment;
        abort_unless($payment !== null, 404, 'Data pembayaran belum tersedia.');

        return response()->json([
            'data' => new PaymentResource($payment),
            'message' => 'Data pembayaran berhasil dimuat.',
        ], 200);
    }

    /**
     * `POST /customer/orders/{order}/payment` — spec 06 section 11.2.
     *
     * Endpoint ini adalah satu-satunya jalan pemilihan metode pembayaran
     * (spec 06 section 30). Payment record dibuat di sini ketika order belum
     * memilikinya, dan tidak pernah dibuat record kedua karena
     * `payments.order_id` UNIQUE (spec 13_DB section 10.1).
     */
    public function select(Request $request, Order $order): JsonResponse
    {
        $customer = $request->user()->customer;
        abort_unless($customer !== null && $order->customer_id === $customer->id, 404);

        $validated = $request->validate([
            'payment_method' => ['required', 'string', 'in:'.PaymentMethod::QRIS->value.','.PaymentMethod::CASH->value],
        ]);
        $method = $validated['payment_method'];

        abort_unless(
            $method !== PaymentMethod::QRIS->value || $this->hasActiveQris(),
            409,
            'Pembayaran QRIS belum tersedia.',
        );

        $staleProofPath = filled($order->payment?->proof_image) ? $order->payment->proof_image : null;

        try {
            [$payment, $created] = DB::transaction(function () use ($order, $method, $request): array {
                $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                abort_unless(
                    $lockedOrder->order_status === OrderStatus::MENUNGGU_PEMBAYARAN->value,
                    409,
                    'Metode pembayaran tidak dapat dipilih pada status pesanan saat ini.',
                );

                $payment = $lockedOrder->payment()->lockForUpdate()->first();
                $created = false;

                if ($payment === null) {
                    $payment = $lockedOrder->payment()->create([
                        'payment_method' => $method,
                        'payment_status' => PaymentStatus::PENDING->value,
                        'amount' => $lockedOrder->total_amount,
                    ]);
                    $payment->statusHistories()->create([
                        'from_status' => null,
                        'to_status' => PaymentStatus::PENDING->value,
                        'changed_by' => $request->user()->id,
                    ]);
                    $created = true;
                } else {
                    abort_unless(
                        $payment->payment_status === PaymentStatus::PENDING->value,
                        409,
                        'Metode pembayaran tidak dapat diubah pada status pembayaran saat ini.',
                    );
                    $payment->update([
                        'payment_method' => $method,
                        'amount' => $lockedOrder->total_amount,
                        'proof_image' => null,
                        'proof_size' => null,
                        'proof_mime_type' => null,
                        'proof_checksum' => null,
                        'verified_by' => null,
                        'verified_at' => null,
                    ]);
                }

                if ($method === PaymentMethod::CASH->value) {
                    $lockedOrder->update(['order_status' => OrderStatus::MENUNGGU_DIPROSES->value]);
                    $lockedOrder->statusHistories()->create([
                        'from_status' => OrderStatus::MENUNGGU_PEMBAYARAN->value,
                        'to_status' => OrderStatus::MENUNGGU_DIPROSES->value,
                        'changed_by_user_id' => $request->user()->id,
                        'changed_at' => now(),
                    ]);
                }

                Log::info('Payment method selected.', [
                    'order_id' => $lockedOrder->id,
                    'payment_id' => $payment->id,
                    'payment_method' => $method,
                    'user_id' => $request->user()->id,
                    'created' => $created,
                ]);

                return [$payment->fresh(), $created];
            });
        } catch (HttpExceptionInterface $exception) {
            throw $exception;
        } catch (QueryException $exception) {
            Log::error('Database error while selecting payment method.', [
                'order_id' => $order->id,
                'user_id' => $request->user()->id,
                'exception' => $exception,
            ]);

            return response()->json([
                'data' => null,
                'message' => 'Gagal memproses metode pembayaran. Silakan coba lagi.',
            ], 500);
        }

        // Spec 06 section 19.1: file proof tidak boleh menggantung di storage
        // setelah tidak lagi dirujuk payment record (audit P2-08).
        if ($staleProofPath !== null) {
            try {
                Storage::disk('local')->delete($staleProofPath);
            } catch (\RuntimeException $exception) {
                Log::warning('Failed to delete stale QRIS proof after method change.', [
                    'path' => $staleProofPath,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return response()->json([
            'data' => ['payment' => new PaymentResource($payment)],
            'message' => 'Metode pembayaran berhasil dipilih.',
        ], $created ? 201 : 200);
    }

    /**
     * `POST /customer/orders/{order}/payment/proof` — spec 06 section 11.5.
     *
     * Re-upload menulis pada payment record yang sama
     * (spec 13_DB section 10.2) dan tidak pernah membuat record baru.
     */
    public function proof(Request $request, Order $order): JsonResponse
    {
        $customer = $request->user()->customer;
        abort_unless($customer !== null && $order->customer_id === $customer->id, 404);

        $payment = $order->payment;
        abort_unless($payment !== null, 404, 'Data pembayaran belum tersedia.');

        $validated = $request->validate([
            'proof' => $this->proofUploadRules('proof_image'),
            'proof_image' => $this->proofUploadRules('proof'),
        ]);
        $proofFile = $validated['proof'] ?? $validated['proof_image'];

        abort_unless($payment->payment_method === PaymentMethod::QRIS->value, 409, 'Upload bukti hanya untuk pembayaran QRIS.');
        abort_unless($order->order_status === OrderStatus::MENUNGGU_PEMBAYARAN->value, 409, 'Bukti pembayaran hanya dapat diunggah saat pesanan menunggu pembayaran.');
        abort_unless($payment->payment_status === PaymentStatus::PENDING->value, 409, 'Bukti pembayaran tidak dapat diunggah pada status saat ini.');

        $storedProofPath = null;
        $previousProofPath = filled($payment->proof_image) ? $payment->proof_image : null;

        try {
            $payment = DB::transaction(function () use ($order, $payment, $proofFile, $request, &$storedProofPath): Payment {
                $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
                abort_if(
                    $lockedPayment->payment_status === PaymentStatus::WAITING_VERIFICATION->value,
                    409,
                    'Bukti pembayaran sedang menunggu verifikasi.',
                );
                abort_unless($lockedPayment->payment_status === PaymentStatus::PENDING->value, 409, 'Status pembayaran telah berubah.');
                abort_unless($lockedPayment->payment_method === PaymentMethod::QRIS->value, 409, 'Upload bukti hanya untuk pembayaran QRIS.');

                $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                abort_unless(
                    $lockedOrder->order_status === OrderStatus::MENUNGGU_PEMBAYARAN->value,
                    409,
                    'Bukti pembayaran hanya dapat diunggah saat pesanan menunggu pembayaran.',
                );

                $proofPath = $proofFile->store('payment-proofs/order-'.$order->id, 'local');
                $storedProofPath = $proofPath;
                $proofChecksum = md5_file($proofFile->getRealPath());
                abort_unless($proofChecksum !== false, 422, 'Bukti pembayaran tidak dapat diproses.');

                $lockedPayment->update([
                    'proof_image' => $proofPath,
                    'proof_size' => $proofFile->getSize(),
                    'proof_mime_type' => $proofFile->getMimeType(),
                    'proof_checksum' => $proofChecksum,
                    'amount' => $lockedOrder->total_amount,
                    'verified_by' => null,
                    'verified_at' => null,
                ]);
                $lockedPayment->transitionTo(PaymentStatus::WAITING_VERIFICATION, $request->user()->id);

                return $lockedPayment->fresh();
            });
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

        if ($previousProofPath !== null && $previousProofPath !== $storedProofPath) {
            Storage::disk('local')->delete($previousProofPath);
        }

        // `payment_method` dan `payment_status` adalah event-specific field untuk
        // `PAYMENT_QRIS_PROOF_UPLOADED` pada spec 10 §8 dan tabel §19.
        $notificationData = [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'payment_method' => $payment->payment_method,
            'payment_status' => $payment->payment_status,
        ];
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
            'data' => ['payment' => new PaymentResource($payment)],
            'message' => 'Bukti pembayaran berhasil diunggah.',
        ], 200);
    }

    /**
     * 13_DB section 21.5: `qris_image` NULL berarti QRIS belum dikonfigurasi.
     */
    private function hasActiveQris(): bool
    {
        return BusinessSetting::active()->hasActiveQris();
    }

    /**
     * Aturan validasi upload bukti QRIS.
     *
     * Batas ukuran dan daftar format diambil dari configuration constant
     * `config/kyusui.php` karena spec 06 section 11.2 mewajibkan keduanya
     * bukan literal hard-coded. Nilainya dikunci spec 08 section 11.1 dan
     * spec 08 section 48 keputusan 7: maksimum 5 MB, format JPG/JPEG/PNG.
     *
     * @return array<int, callable|string>
     */
    private function proofUploadRules(string $counterpart): array
    {
        return [
            'required_without:'.$counterpart,
            'image',
            'mimes:'.config('kyusui.image_mimes'),
            'max:'.config('kyusui.max_kilobytes'),
            $this->imageDimensionsRule(),
        ];
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
