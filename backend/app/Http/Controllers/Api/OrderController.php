<?php

namespace App\Http\Controllers\Api;

use App\Enums\AssignmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\BusinessActionOccurred;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Resources\TrackingResource;
use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class OrderController extends Controller
{
    /**
     * `GET /customer/orders` — spec 06 section 9.5.
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user()->customer;
        abort_unless($customer !== null, 403, 'Akses ditolak. Profil customer tidak ditemukan.');

        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'order_status' => ['sometimes', 'string', 'in:'.implode(',', array_column(OrderStatus::cases(), 'value'))],
        ]);

        $orders = $customer->orders()
            ->with(['items', 'payment', 'assignments.courier.user'])
            ->when(isset($validated['order_status']), fn ($query) => $query->where('order_status', $validated['order_status']))
            ->latestPlaced()
            ->paginate($validated['per_page'] ?? 15);

        return response()->json([
            'data' => OrderResource::collection($orders->items()),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
            'message' => 'Data pesanan berhasil dimuat.',
        ], 200);
    }

    /**
     * `POST /customer/orders` — spec 06 section 9.4 dan 13_IC section 12.1.
     *
     * `payment_method` opsional. Bila dikirim, satu payment record dibuat di dalam
     * transaksi order dengan `payment_status = PENDING` dan nominal sama dengan
     * `orders.total_amount` (13_DB section 11). Bila tidak dikirim, payment
     * record dibuat saat customer memanggil `POST /customer/orders/{order}/payment`
     * (spec 06 section 11.2 dan section 30). Keduanya dijaga oleh
     * `payments.order_id` UNIQUE sehingga tidak pernah ada dua payment record
     * untuk satu order (13_DB section 10.1).
     *
     * Nominal selalu dihitung server dari `products.price` saat transaksi;
     * client tidak pernah menjadi source of truth (13_DB section 11).
     */
    public function store(Request $request): JsonResponse
    {
        $customer = $request->user()->customer;
        abort_unless($customer !== null, 403, 'Akses ditolak. Profil customer tidak ditemukan.');

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'delivery_location' => ['required', 'array'],
            'delivery_location.latitude' => ['required', 'numeric', 'between:-90,90'],
            'delivery_location.longitude' => ['required', 'numeric', 'between:-180,180'],
            'delivery_location.address' => ['required', 'string', 'max:500'],
            'payment_method' => ['sometimes', 'nullable', 'string', 'in:'.PaymentMethod::QRIS->value.','.PaymentMethod::CASH->value],
        ]);

        $paymentMethod = $validated['payment_method'] ?? null;

        abort_unless(
            $paymentMethod !== PaymentMethod::QRIS->value || $this->hasActiveQris(),
            409,
            'Pembayaran QRIS belum tersedia.',
        );

        try {
            $order = DB::transaction(function () use ($validated, $paymentMethod, $customer, $request): Order {
                $deliveryLatitude = (float) $validated['delivery_location']['latitude'];
                $deliveryLongitude = (float) $validated['delivery_location']['longitude'];

                $products = Product::query()
                    ->whereIn('id', array_column($validated['items'], 'product_id'))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $subtotal = '0.00';
                $orderItems = [];

                foreach ($validated['items'] as $item) {
                    $product = $products->get($item['product_id']);
                    abort_unless($product !== null && $product->availability, 422, 'Produk tidak tersedia.');

                    $lineTotal = bcmul((string) $product->price, (string) $item['quantity'], 2);
                    $subtotal = bcadd($subtotal, $lineTotal, 2);
                    $orderItems[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'quantity' => $item['quantity'],
                        'unit_price' => $product->price,
                        'line_total' => $lineTotal,
                    ];
                }

                $deliveryFee = $this->calculateDeliveryFee($deliveryLatitude, $deliveryLongitude);
                $totalAmount = bcadd($subtotal, number_format($deliveryFee, 2, '.', ''), 2);

                $order = Order::create([
                    'customer_id' => $customer->id,
                    'order_status' => OrderStatus::MENUNGGU_PEMBAYARAN->value,
                    'subtotal_amount' => $subtotal,
                    'delivery_fee' => $deliveryFee,
                    'total_amount' => $totalAmount,
                    'delivery_address' => $validated['delivery_location']['address'],
                    'delivery_latitude' => $deliveryLatitude,
                    'delivery_longitude' => $deliveryLongitude,
                    'placed_at' => now(),
                ]);

                $order->items()->createMany($orderItems);
                $order->statusHistories()->create([
                    'from_status' => null,
                    'to_status' => OrderStatus::MENUNGGU_PEMBAYARAN->value,
                    'changed_by_user_id' => $request->user()->id,
                    'changed_at' => now(),
                ]);

                if ($paymentMethod !== null) {
                    $payment = $order->payment()->create([
                        'payment_method' => $paymentMethod,
                        'payment_status' => PaymentStatus::PENDING->value,
                        'amount' => $totalAmount,
                    ]);
                    $payment->statusHistories()->create([
                        'from_status' => null,
                        'to_status' => PaymentStatus::PENDING->value,
                        'changed_by' => $request->user()->id,
                    ]);
                }

                // `order_status` adalah event-specific field untuk
                // `ORDER_CREATED` pada spec 10 §13 dan tabel §19.
                $notificationData = [
                    'order_id' => $order->id,
                    'order_status' => $order->order_status,
                ];

                event(new BusinessActionOccurred(
                    userId: $request->user()->id,
                    type: 'ORDER_CREATED',
                    title: 'Order Dibuat',
                    body: 'Pesanan Anda telah berhasil dibuat.',
                    data: $notificationData,
                ));

                User::query()
                    ->whereHas('role', fn ($query) => $query->where('name', 'OWNER'))
                    ->pluck('id')
                    ->each(fn (int $ownerUserId) => event(new BusinessActionOccurred(
                        userId: $ownerUserId,
                        type: 'ORDER_CREATED',
                        title: 'Order Baru',
                        body: 'Pesanan baru menunggu pembayaran.',
                        data: $notificationData,
                    )));

                Log::info('Order created', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_id' => $customer->id,
                    'user_id' => $request->user()->id,
                    'total_amount' => $totalAmount,
                ]);

                return $order->load(['items', 'payment']);
            });
        } catch (HttpExceptionInterface $exception) {
            throw $exception;
        } catch (QueryException|Throwable $exception) {
            Log::error('Database error while creating order.', [
                'user_id' => $request->user()->id,
                'exception' => $exception,
            ]);

            return response()->json([
                'data' => null,
                'message' => 'Gagal memproses pesanan. Silakan coba lagi.',
            ], 500);
        }

        return response()->json([
            'data' => new OrderResource($order),
            'message' => 'Pesanan berhasil dibuat.',
        ], 201);
    }

    /**
     * `GET /customer/orders/{order}` — spec 06 section 9.6.
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorizeCustomer($request, $order);

        return response()->json([
            'data' => new OrderResource($order->load(['items', 'payment', 'assignments.courier.user'])),
            'message' => 'Detail pesanan berhasil dimuat.',
        ], 200);
    }

    /**
     * `GET /customer/orders/{order}/tracking` — spec 06 section 9.7 dan 7.7.
     *
     * Mengembalikan assignment aktif beserta riwayat lokasi (maksimal 10 titik,
     * terbaru dulu) sesuai kontrak Android tracking.
     */
    public function tracking(Request $request, Order $order): JsonResponse
    {
        $this->authorizeCustomer($request, $order);

        $assignment = $order->assignments()
            ->where('status', AssignmentStatus::ACTIVE->value)
            ->with(['courier.user', 'locations' => function ($query) {
                $query->latest('recorded_at')->limit(TrackingResource::MAX_LOCATIONS);
            }])
            ->first();

        if ($assignment === null) {
            return response()->json([
                'data' => null,
                'message' => 'Tracking location is not available yet.',
            ], 200);
        }

        return response()->json([
            'data' => new TrackingResource($assignment),
            'message' => 'Data tracking berhasil dimuat.',
        ], 200);
    }

    /**
     * `POST /customer/orders/{order}/cancel`.
     *
     * Keputusan bisnis (Pilihan A): pembatalan hanya diperbolehkan pada status
     * `MENUNGGU_PEMBAYARAN`. Status lain ditolak dengan HTTP 409.
     * Endpoint ini tidak ada di katalog spec 06 section 30, tetapi dipertahankan
     * untuk kompatibilitas mundur (preserved).
     */
    public function cancel(Request $request, Order $order): JsonResponse
    {
        $this->authorizeCustomer($request, $order);

        $order = DB::transaction(function () use ($order, $request): Order {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                $lockedOrder->order_status === OrderStatus::MENUNGGU_PEMBAYARAN->value,
                409,
                'Pesanan tidak dapat dibatalkan pada status saat ini.',
            );

            $lockedOrder->update(['order_status' => OrderStatus::DIBATALKAN->value]);
            $lockedOrder->statusHistories()->create([
                'from_status' => OrderStatus::MENUNGGU_PEMBAYARAN->value,
                'to_status' => OrderStatus::DIBATALKAN->value,
                'changed_by_user_id' => $request->user()->id,
                'changed_at' => now(),
            ]);

            Log::info('Order cancelled', [
                'order_id' => $lockedOrder->id,
                'customer_id' => $lockedOrder->customer_id,
                'user_id' => $request->user()->id,
            ]);

            return $lockedOrder->fresh();
        });

        return response()->json([
            'data' => new OrderResource($order->load(['items', 'payment'])),
            'message' => 'Pesanan berhasil dibatalkan.',
        ], 200);
    }

    /**
     * Spec 06 section 5.6 dan section 23.3: order milik customer lain harus
     * `404`, bukan `403`, supaya keberadaan order tidak bisa ditebak.
     */
    private function authorizeCustomer(Request $request, Order $order): void
    {
        $customer = $request->user()->customer;
        abort_unless($customer !== null && $order->customer_id === $customer->id, 404);
    }

    /**
     * 13_DB section 21.5: `qris_image` yang NULL berarti QRIS belum dikonfigurasi.
     */
    private function hasActiveQris(): bool
    {
        return BusinessSetting::active()->hasActiveQris();
    }

    private function calculateDeliveryFee(float $latitude, float $longitude): float
    {
        $distance = $this->calculateDistance($latitude, $longitude, -6.2, 106.816666);
        abort_if($distance > 10, 422, 'Alamat di luar area layanan kami (max 10km).');

        return 5000 + (int) ceil($distance * 2000);
    }

    private function calculateDistance(float $latitudeFrom, float $longitudeFrom, float $latitudeTo, float $longitudeTo): float
    {
        $earthRadiusKm = 6371.0;
        $latitudeDelta = deg2rad($latitudeTo - $latitudeFrom);
        $longitudeDelta = deg2rad($longitudeTo - $longitudeFrom);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitudeFrom)) * cos(deg2rad($latitudeTo)) * sin($longitudeDelta / 2) ** 2;

        return $earthRadiusKm * 2 * asin(min(1, sqrt($a)));
    }
}
