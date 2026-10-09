<?php

namespace App\Http\Controllers\Api;

use App\Enums\AssignmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\BusinessActionOccurred;
use App\Http\Controllers\Controller;
use App\Http\Resources\ActiveQrisResource;
use App\Http\Resources\OwnerOrderResource;
use App\Http\Resources\OwnerPaymentProofResource;
use App\Http\Resources\OwnerPendingPaymentResource;
use App\Http\Resources\PaymentResource;
use App\Models\BusinessSetting;
use App\Models\Courier;
use App\Models\CourierAssignment;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OwnerController extends Controller
{
    /**
     * `GET /owner/orders` — spec 06 section 10.1.
     *
     * Query filter tetap bernama `status`. Spec 06 section 10.1 hanya menyebut
     * "Filter status/payment dapat digunakan jika telah tersedia pada existing
     * implementation", dan klien aktif mengirim `?status=`. Nilai yang diterima
     * tetap vocabulary `OrderStatus`.
     */
    public function orders(Request $request): JsonResponse
    {
        $this->authorizeOwner($request);

        $validated = $request->validate([
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
            'status' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', array_column(OrderStatus::cases(), 'value'))],
        ]);

        $orders = Order::query()
            ->with(['customer.user:id,name', 'items', 'payment', 'assignments.courier.user:id,name'])
            ->when(! empty($validated['status']), fn ($query) => $query->where('order_status', $validated['status']))
            ->latest('id')
            ->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'data' => OwnerOrderResource::collection($orders->items()),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
            'message' => 'Data pesanan berhasil dimuat.',
        ], 200);
    }

    /**
     * `GET /owner/orders/{order}` — spec 06 section 10.2.
     */
    public function order(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOwner($request);

        return response()->json([
            'data' => new OwnerOrderResource($this->loadOwnerOrder($order)),
            'message' => 'Data pesanan berhasil dimuat.',
        ], 200);
    }

    /**
     * `PATCH /owner/orders/{order}/status` — spec 06 section 10.3.
     *
     * Hanya transition yang menjadi responsibility owner yang diterima, yaitu
     * `MENUNGGU_DIPROSES -> DIPROSES` (04 section 5, activity diagram Owner).
     * Transisi ke `DITUGASKAN` dilakukan oleh endpoint assignment, bukan di
     * sini. Status lain dijawab `409` supaya client tidak dapat mengirim
     * arbitrary transition (spec 06 section 6.1).
     *
     * Body tetap memakai key `status` sesuai spec 06 section 10.3 dan klien
     * aktif, sedangkan nilai disimpan pada kolom canonical `order_status`.
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOwner($request);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', array_column(OrderStatus::cases(), 'value'))],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        abort_unless(
            $validated['status'] === OrderStatus::DIPROSES->value,
            409,
            'Transisi status tersebut bukan tanggung jawab owner.',
        );

        DB::transaction(function () use ($order, $validated, $request): void {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                $lockedOrder->order_status === OrderStatus::MENUNGGU_DIPROSES->value,
                409,
                'Pesanan tidak dapat diproses pada status saat ini.',
            );

            $lockedOrder->update([
                'order_status' => OrderStatus::DIPROSES->value,
                'processed_at' => now(),
            ]);
            $lockedOrder->statusHistories()->create([
                'from_status' => OrderStatus::MENUNGGU_DIPROSES->value,
                'to_status' => OrderStatus::DIPROSES->value,
                'changed_by_user_id' => $request->user()->id,
                'note' => $validated['note'] ?? null,
                'changed_at' => now(),
            ]);
            event(new BusinessActionOccurred(
                $lockedOrder->customer->user_id,
                'ORDER_PROCESSED',
                'Pesanan Sedang Diproses',
                'Pesanan Anda sedang diproses oleh depot.',
                // `order_status` adalah event-specific field untuk
                // `ORDER_PROCESSED` pada spec 10 §14 dan tabel §19.
                ['order_id' => $lockedOrder->id, 'order_status' => $lockedOrder->order_status],
            ));
        });

        Log::info('Order processed by owner', [
            'order_id' => $order->id,
            'owner_id' => $request->user()->id,
        ]);

        return response()->json([
            'data' => new OwnerOrderResource($this->loadOwnerOrder($order->fresh())),
            'message' => 'Pesanan berhasil diproses.',
        ], 200);
    }

    /**
     * `POST /owner/orders/{order}/assignment` — spec 06 section 10.4.
     */
    public function assign(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOwner($request);

        $validated = $request->validate([
            'courier_id' => ['required', 'exists:couriers,id'],
        ]);

        DB::transaction(function () use ($order, $validated, $request): void {
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
                $lockedOrder->order_status === OrderStatus::DIPROSES->value,
                409,
                'Pesanan tidak dapat ditugaskan pada status saat ini.',
            );

            $assignment = CourierAssignment::create([
                'order_id' => $lockedOrder->id,
                'courier_id' => $courier->id,
                'status' => AssignmentStatus::ASSIGNED->value,
                'assigned_at' => now(),
            ]);
            $lockedOrder->update(['order_status' => OrderStatus::DITUGASKAN->value]);
            $lockedOrder->statusHistories()->create([
                'from_status' => OrderStatus::DIPROSES->value,
                'to_status' => OrderStatus::DITUGASKAN->value,
                'changed_by_user_id' => $request->user()->id,
                'changed_at' => now(),
            ]);
            event(new BusinessActionOccurred(
                $courier->user_id,
                'COURIER_ASSIGNED',
                'Tugas Pengantaran Baru',
                'Anda mendapat tugas pengantaran baru.',
                ['order_id' => $lockedOrder->id, 'assignment_id' => $assignment->id],
            ));
            event(new BusinessActionOccurred(
                $lockedOrder->customer->user_id,
                'COURIER_ASSIGNED',
                'Kurir Ditugaskan',
                'Kurir telah ditugaskan untuk mengantarkan pesanan Anda.',
                ['order_id' => $lockedOrder->id, 'assignment_id' => $assignment->id],
            ));
        });

        Log::info('Courier assigned to order', [
            'order_id' => $order->id,
            'courier_id' => $validated['courier_id'],
            'owner_id' => $request->user()->id,
        ]);

        return response()->json([
            'data' => new OwnerOrderResource($this->loadOwnerOrder($order->fresh())),
            'message' => 'Kurir berhasil ditugaskan.',
        ], 201);
    }

    /**
     * Preserved endpoint: daftar kurir tidak ada di katalog spec 06 section 30,
     * tetapi dipakai layar owner untuk memilih kurir pada section 10.4.
     */
    public function couriers(Request $request): JsonResponse
    {
        $this->authorizeOwner($request);

        $busyCourierIds = CourierAssignment::query()
            ->where('status', AssignmentStatus::ACTIVE->value)
            ->pluck('courier_id');

        $availableCouriers = Courier::with('user:id,name')
            ->whereNotIn('id', $busyCourierIds)
            ->get();

        return response()->json([
            'data' => $availableCouriers,
            'message' => 'Data kurir tersedia berhasil dimuat.',
        ], 200);
    }

    /**
     * `GET /owner/payments/pending` — spec 06 section 12.1.
     */
    public function pending(Request $request): JsonResponse
    {
        $this->authorizeOwner($request);

        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $payments = Payment::query()
            ->with('order.customer.user:id,name')
            ->where('payment_method', PaymentMethod::QRIS->value)
            ->where('payment_status', PaymentStatus::WAITING_VERIFICATION->value)
            ->latest('id')
            ->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'data' => OwnerPendingPaymentResource::collection($payments->items()),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
            'message' => 'Data pembayaran menunggu verifikasi berhasil dimuat.',
        ], 200);
    }

    /**
     * `GET /owner/orders/{order}/payment/proof` — spec 06 section 12.2.
     *
     * Endpoint ini memakai order sebagai key resource, bukan payment id, dan
     * hanya melaporkan URL private file delivery milik backend sendiri. Path
     * filesystem tidak pernah dikembalikan (spec 06 section 12.2).
     */
    public function proof(Request $request, Order $order): BinaryFileResponse|JsonResponse|Response
    {
        $this->authorizeOwner($request);

        $payment = $order->payment;
        abort_unless(
            $payment !== null
            && $payment->payment_method === PaymentMethod::QRIS->value
            && filled($payment->proof_image),
            404,
            'Bukti pembayaran tidak ditemukan.',
        );

        $disk = Storage::disk('local');
        abort_unless(
            $disk->exists($payment->proof_image),
            404,
            'File bukti pembayaran tidak ditemukan di server.',
        );

        // Spec 06 section 12.2: "Response dapat berupa payment proof resource
        // atau authorized file response sesuai implementation storage."
        //
        // Bentuk file hanya dipakai ketika klien memintanya secara eksplisit,
        // sehingga bentuk resource JSON yang dipakai klien existing tidak
        // berubah dan route tetap satu (tidak ada endpoint baru di katalog
        // spec 06 section 30).
        if ($request->boolean('download')) {
            return $this->privateProofFileResponse($request, $payment, $disk);
        }

        return response()->json([
            'data' => new OwnerPaymentProofResource($payment),
            'message' => 'Bukti pembayaran berhasil dimuat.',
        ], 200);
    }

    /**
     * Authorized file response untuk bukti QRIS.
     *
     * Path selalu berasal dari kolom `payments.proof_image` pada disk private
     * `local` (spec 08 section 11.1). Path tidak pernah berasal dari input
     * klien dan tidak pernah dikembalikan ke klien sebagai metadata
     * (spec 06 section 33 "QRIS proof stored privately").
     *
     * Seluruh route berada di balik `auth:sanctum` + `role:OWNER`, sehingga
     * file hanya dapat diambil oleh owner yang terautentikasi.
     */
    private function privateProofFileResponse(Request $request, Payment $payment, FilesystemAdapter $disk): BinaryFileResponse|Response
    {
        $path = $payment->proof_image;
        $lastModified = $disk->lastModified($path);
        $etag = '"'.md5($path.$lastModified).'"';
        $headers = [
            'ETag' => $etag,
            'Last-Modified' => gmdate('D, d M Y H:i:s', $lastModified).' GMT',
            'Content-Type' => $this->proofMimeType($payment, $disk, $path),
        ];

        if ($request->header('If-None-Match') === $etag) {
            return response('', 304, ['Cache-Control' => 'private, max-age=3600'] + $headers);
        }

        // `response()->file()` memaksa `Cache-Control: public` lewat argumen
        // `$public = true` milik BinaryFileResponse. Bukti QRIS bersifat
        // private, jadi cache directive itu harus dikoreksi eksplisit setelah
        // response dibuat, jika tidak shared cache boleh menyimpannya.
        $response = response()->file($disk->path($path), $headers);
        $response->setPrivate();
        $response->setMaxAge(3600);

        return $response;
    }

    /**
     * Content type dibatasi pada allowlist image yang diizinkan spec 08
     * section 11.1 (JPG/JPEG/PNG). Nilai dari request tidak pernah dipakai,
     * sehingga client tidak dapat memaksa content type.
     */
    private function proofMimeType(Payment $payment, FilesystemAdapter $disk, string $path): string
    {
        $allowed = ['image/jpeg', 'image/png'];

        $stored = $payment->proof_mime_type;
        if (is_string($stored) && in_array(strtolower($stored), $allowed, true)) {
            return strtolower($stored);
        }

        $detected = $disk->mimeType($path);
        if (is_string($detected) && in_array(strtolower($detected), $allowed, true)) {
            return strtolower($detected);
        }

        return 'application/octet-stream';
    }

    /**
     * `POST /owner/orders/{order}/payment-verification` — spec 06 section 12.3.
     *
     * Satu-satunya endpoint verifikasi QRIS. Route payment-id lama
     * (`POST /owner/payments/{payment}/approve` dan `/reject`) dihapus karena
     * membentuk privileged surface kedua yang tidak ada di active contract.
     */
    public function verifyOrderPayment(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOwner($request);

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:APPROVE,REJECT'],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $payment = $order->payment()->firstOrFail();
        $payment->loadMissing('order.customer.user');

        return $validated['action'] === 'APPROVE'
            ? $this->approve($request, $payment)
            : $this->reject($request, $payment);
    }

    /**
     * `GET /owner/payment/qris` — spec 06 section 13.1.
     *
     * `ActiveQrisResource` sudah mengembalikan `data: null` bila `qris_image`
     * kosong (13_DB section 21.5), sehingga endpoint ini tetap `200` saat QRIS
     * belum dikonfigurasi dan klien tidak perlu menangani `404` sebagai kondisi
     * normal.
     */
    public function qris(Request $request): JsonResponse
    {
        $this->authorizeOwner($request);

        $setting = BusinessSetting::active();

        return response()->json([
            'data' => $setting->hasActiveQris() ? new ActiveQrisResource($setting) : null,
            'message' => 'Data QRIS berhasil dimuat.',
        ], 200);
    }

    /**
     * `PUT /owner/payment/qris` — spec 06 section 13.2.
     *
     * `updated_by` dicatat sebagai audit actor penggantian QRIS
     * (13_DB section 22), terpisah dari `payments.verified_by`.
     */
    public function updateQris(Request $request): JsonResponse
    {
        $this->authorizeOwner($request);

        $validated = $request->validate([
            'qris_image' => ['required', 'file', 'mimes:'.config('kyusui.image_mimes'), 'max:'.config('kyusui.max_kilobytes')],
        ]);

        $previousPath = BusinessSetting::active()->qris_image;

        $path = $validated['qris_image']->store('qris', 'local');
        $setting = BusinessSetting::query()->findOrFail(BusinessSetting::SINGLETON_ID);
        $setting->update([
            'qris_image' => $path,
            'updated_by' => $request->user()->id,
        ]);

        if ($previousPath && $previousPath !== $path && Storage::disk('local')->exists($previousPath)) {
            Storage::disk('local')->delete($previousPath);
        }

        Log::info('QRIS image updated by owner', [
            'user_id' => $request->user()->id,
            'new_path' => $path,
        ]);

        return response()->json([
            'data' => new ActiveQrisResource($setting->fresh()),
            'message' => 'QRIS berhasil diperbarui.',
        ], 200);
    }

    /**
     * APPROVE: `WAITING_VERIFICATION -> PAID` (spec 06 section 12.3).
     *
     * State dibaca ulang di dalam `lockForUpdate()` sehingga dua request owner
     * paralel tidak dapat keduanya menulis transisi dan history yang sama.
     */
    private function approve(Request $request, Payment $payment): JsonResponse
    {
        DB::transaction(function () use ($request, $payment): void {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $lockedPayment->load('order.customer.user');
            abort_unless(
                $lockedPayment->payment_method === PaymentMethod::QRIS->value
                && $lockedPayment->payment_status === PaymentStatus::WAITING_VERIFICATION->value,
                409,
                'Pembayaran tidak dapat diverifikasi pada status saat ini.',
            );
            $order = $lockedPayment->order;
            abort_unless(
                $order !== null && $order->order_status === OrderStatus::MENUNGGU_PEMBAYARAN->value,
                409,
                'Status pesanan tidak valid untuk verifikasi pembayaran.',
            );
            abort_unless(
                filled($lockedPayment->proof_image)
                && Storage::disk('local')->exists($lockedPayment->proof_image),
                409,
                'File bukti pembayaran tidak ditemukan di server.',
            );

            $lockedPayment->transitionTo(PaymentStatus::PAID, $request->user()->id);
            $order->update(['order_status' => OrderStatus::MENUNGGU_DIPROSES->value]);
            $order->statusHistories()->create([
                'from_status' => OrderStatus::MENUNGGU_PEMBAYARAN->value,
                'to_status' => OrderStatus::MENUNGGU_DIPROSES->value,
                'changed_by_user_id' => $request->user()->id,
                'changed_at' => now(),
            ]);
            event(new BusinessActionOccurred(
                $order->customer->user_id,
                'PAYMENT_QRIS_APPROVED',
                'Pembayaran QRIS Disetujui',
                'Bukti pembayaran QRIS Anda telah disetujui.',
                // `payment_id` dipakai sebagai `resource_id`, dan `payment_method` serta
                // `payment_status` adalah event-specific field untuk
                // `PAYMENT_QRIS_APPROVED` pada spec 10 §9 dan tabel §19.
                [
                    'order_id' => $order->id,
                    'payment_id' => $lockedPayment->id,
                    'payment_method' => $lockedPayment->payment_method,
                    'payment_status' => $lockedPayment->payment_status,
                ],
            ));
        });

        Log::info('Payment approved by owner', [
            'payment_id' => $payment->id,
            'owner_id' => $request->user()->id,
        ]);

        return response()->json([
            'data' => ['payment' => new PaymentResource($payment->fresh())],
            'message' => 'Pembayaran berhasil diverifikasi.',
        ], 200);
    }

    /**
     * REJECT: `WAITING_VERIFICATION -> PENDING` (spec 06 section 12.3).
     *
     * Payment tidak pernah menjadi `FAILED` atau `REJECTED`, dan
     * `verified_by`/`verified_at` dikosongkan.
     */
    private function reject(Request $request, Payment $payment): JsonResponse
    {
        $proofPath = $payment->proof_image;

        DB::transaction(function () use ($payment, $request): void {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $lockedPayment->load('order.customer.user');
            abort_unless(
                $lockedPayment->payment_method === PaymentMethod::QRIS->value
                && $lockedPayment->payment_status === PaymentStatus::WAITING_VERIFICATION->value,
                409,
                'Pembayaran tidak dapat ditolak pada status saat ini.',
            );

            $lockedPayment->update([
                'proof_image' => null,
                'verified_by' => null,
                'verified_at' => null,
                'proof_size' => null,
                'proof_mime_type' => null,
                'proof_checksum' => null,
            ]);
            $lockedPayment->transitionTo(PaymentStatus::PENDING, $request->user()->id);
            event(new BusinessActionOccurred(
                $lockedPayment->order->customer->user_id,
                'PAYMENT_QRIS_REJECTED',
                'Bukti Pembayaran Ditolak',
                'Silakan unggah kembali bukti pembayaran QRIS Anda.',
                // `payment_id` dipakai sebagai `resource_id`, dan `payment_method` serta
                // `payment_status` adalah event-specific field untuk
                // `PAYMENT_QRIS_REJECTED` pada spec 10 §10 dan tabel §19.
                // `payment_status` dibaca setelah `transitionTo()` sehingga
                // nilainya mencerminkan `PENDING` hasil penolakan.
                [
                    'order_id' => $lockedPayment->order_id,
                    'payment_id' => $lockedPayment->id,
                    'payment_method' => $lockedPayment->payment_method,
                    'payment_status' => $lockedPayment->payment_status,
                ],
            ));
        });

        if ($proofPath) {
            try {
                if (Storage::disk('local')->exists($proofPath)) {
                    Storage::disk('local')->delete($proofPath);
                }
            } catch (\Exception $exception) {
                Log::warning('Failed to delete rejected QRIS proof.', [
                    'path' => $proofPath,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        Log::info('Payment rejected by owner', [
            'payment_id' => $payment->id,
            'owner_id' => $request->user()->id,
        ]);

        return response()->json([
            'data' => ['payment' => new PaymentResource($payment->fresh())],
            'message' => 'Pembayaran berhasil ditolak.',
        ], 200);
    }

    private function loadOwnerOrder(Order $order): Order
    {
        return $order->load([
            'customer.user:id,name',
            'items',
            'payment',
            'statusHistories',
            'assignments.courier.user:id,name',
        ]);
    }

    private function authorizeOwner(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user !== null && $user->role?->name === 'OWNER',
            403,
            'Akses ditolak. Hanya Owner yang dapat melakukan tindakan ini.',
        );
    }
}
