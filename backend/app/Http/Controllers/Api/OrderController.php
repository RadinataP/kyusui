<?php

namespace App\Http\Controllers\Api;

use App\Enums\AssignmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\BusinessActionOccurred;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user()->customer;
        abort_unless($customer !== null, 403, 'Akses ditolak. Profil customer tidak ditemukan.');

        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'status' => ['sometimes', 'string', 'in:'.implode(',', array_column(OrderStatus::cases(), 'value'))],
        ]);
        $orders = $customer->orders()
            ->with(['items.product', 'payment', 'assignments.courier'])
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->latest('orders.created_at')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json([
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
            'message' => 'Data pesanan berhasil dimuat.',
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $customer = $request->user()->customer;
        abort_unless($customer !== null, 403, 'Akses ditolak. Profil customer tidak ditemukan.');
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'delivery_address' => ['required', 'string', 'max:500'],
            'delivery_latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:delivery_longitude'],
            'delivery_longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:delivery_latitude'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'payment_method' => ['required', 'string', 'in:'.PaymentMethod::QRIS->value.','.PaymentMethod::CASH->value],
        ]);

        try {
            [$order, $created] = DB::transaction(function () use ($validated, $customer, $request): array {
                if (isset($validated['idempotency_key'])) {
                    $existingOrder = Order::query()
                        ->where('customer_id', $customer->id)
                        ->where('idempotency_key', $validated['idempotency_key'])
                        ->with(['items.product', 'payment', 'statusHistories'])
                        ->first();

                    if ($existingOrder !== null) {
                        return [$existingOrder, false];
                    }
                }

                $deliveryLatitude = (float) ($validated['delivery_latitude'] ?? -6.2);
                $deliveryLongitude = (float) ($validated['delivery_longitude'] ?? 106.816666);
                $products = Product::query()
                    ->whereIn('id', array_column($validated['items'], 'product_id'))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                $subtotal = 0.0;
                $orderItems = [];

                foreach ($validated['items'] as $item) {
                    $product = $products->get($item['product_id']);
                    abort_unless($product !== null && $product->availability, 422, 'Produk tidak tersedia.');
                    $lineTotal = (float) $product->price * $item['quantity'];
                    $subtotal += $lineTotal;
                    $orderItems[] = [
                        'product_id' => $product->id,
                        'quantity' => $item['quantity'],
                        'unit_price' => $product->price,
                        'line_total' => $lineTotal,
                    ];
                }

                $deliveryFee = $this->calculateDeliveryFee($deliveryLatitude, $deliveryLongitude);
                $total = $subtotal + $deliveryFee;
                $initialStatus = $validated['payment_method'] === PaymentMethod::CASH->value
                    ? OrderStatus::MENUNGGU_DIPROSES
                    : OrderStatus::MENUNGGU_PEMBAYARAN;
                $order = Order::create([
                    'customer_id' => $customer->id,
                    'status' => $initialStatus->value,
                    'subtotal' => $subtotal,
                    'delivery_fee' => $deliveryFee,
                    'total' => $total,
                    'delivery_address' => $validated['delivery_address'],
                    'delivery_latitude' => $deliveryLatitude,
                    'delivery_longitude' => $deliveryLongitude,
                    'idempotency_key' => $validated['idempotency_key'] ?? null,
                ]);
                $order->items()->createMany($orderItems);
                $order->payment()->create([
                    'method' => $validated['payment_method'],
                    'status' => PaymentStatus::PENDING->value,
                    'amount' => $total,
                ]);
                $order->statusHistories()->create([
                    'from_status' => null,
                    'to_status' => $initialStatus->value,
                    'changed_by' => $request->user()->id,
                ]);
                $order->payment->statusHistories()->create([
                    'from_status' => null,
                    'to_status' => PaymentStatus::PENDING->value,
                    'changed_by' => $request->user()->id,
                ]);
                event(new BusinessActionOccurred(
                    userId: $request->user()->id,
                    type: 'ORDER_CREATED',
                    title: 'Order Dibuat',
                    body: 'Pesanan baru Anda telah berhasil dibuat.',
                    data: ['order_id' => $order->id],
                ));
                Log::info('Order created', [
                    'order_id' => $order->id,
                    'customer_id' => $customer->id,
                    'user_id' => $request->user()->id,
                    'total' => $total,
                ]);

                return [$order, true];
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
            'data' => $order->load(['items.product', 'payment']),
            'message' => $created ? 'Pesanan berhasil dibuat.' : 'Pesanan idempotensi sudah tersedia.',
        ], $created ? 201 : 200);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $customer = $request->user()->customer;
        abort_unless($customer !== null && $order->customer_id === $customer->id, 403, 'Anda tidak memiliki akses ke pesanan ini.');

        return response()->json([
            'data' => $order->load(['items.product', 'payment', 'statusHistories.changedBy']),
            'message' => 'Detail pesanan berhasil dimuat.',
        ], 200);
    }

    public function payment(Request $request, Order $order): JsonResponse
    {
        $customer = $request->user()->customer;
        abort_unless($customer !== null && $order->customer_id === $customer->id, 403, 'Anda tidak memiliki akses ke pesanan ini.');

        return response()->json([
            'data' => $order->payment,
            'message' => 'Data pembayaran berhasil dimuat.',
        ], 200);
    }

    public function updateMethod(Request $request, Order $order): JsonResponse
    {
        $customer = $request->user()->customer;
        abort_unless($customer !== null && $order->customer_id === $customer->id, 403, 'Anda tidak memiliki akses ke pesanan ini.');
        $validated = $request->validate([
            'method' => ['required', 'string', 'in:'.PaymentMethod::QRIS->value.','.PaymentMethod::CASH->value],
        ]);
        $order = DB::transaction(function () use ($order, $validated, $request): Order {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $payment = $lockedOrder->payment()->lockForUpdate()->first();
            abort_if($payment === null, 409, 'Pembayaran untuk pesanan ini tidak ditemukan.');
            abort_unless(
                $payment->status === PaymentStatus::PENDING->value
                && $lockedOrder->status === OrderStatus::MENUNGGU_PEMBAYARAN->value,
                409,
                'Metode pembayaran tidak dapat diubah pada status saat ini.',
            );
            $payment->update(['method' => $validated['method'], 'proof_path' => null]);
            if ($validated['method'] === PaymentMethod::CASH->value) {
                $lockedOrder->update(['status' => OrderStatus::MENUNGGU_DIPROSES->value]);
                $lockedOrder->statusHistories()->create([
                    'from_status' => OrderStatus::MENUNGGU_PEMBAYARAN->value,
                    'to_status' => OrderStatus::MENUNGGU_DIPROSES->value,
                    'changed_by' => $request->user()->id,
                ]);
            }

            return $lockedOrder->fresh('payment');
        });

        return response()->json([
            'data' => $order->payment,
            'message' => 'Metode pembayaran berhasil diperbarui.',
        ], 200);
    }

    public function uploadQrisProof(Request $request, Order $order): JsonResponse
    {
        $customer = $request->user()->customer;
        abort_unless($customer !== null && $order->customer_id === $customer->id, 403, 'Anda tidak memiliki akses ke pesanan ini.');
        $payment = $order->payment;
        abort_if($payment === null, 409, 'Pembayaran untuk pesanan ini tidak ditemukan.');
        abort_unless(
            $payment->method === PaymentMethod::QRIS->value
            && $payment->status === PaymentStatus::PENDING->value,
            409,
            'Bukti QRIS tidak dapat diunggah pada status saat ini.',
        );
        $validated = $request->validate([
            'proof_image' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);
        $oldProofPath = $payment->proof_path;
        $proofPath = DB::transaction(function () use ($validated, $payment, $request, $order): string {
            $proofPath = $validated['proof_image']->store('qris-proofs/'.$order->id, 'local');
            $payment->update(['proof_path' => $proofPath]);
            $payment->transitionTo(PaymentStatus::WAITING_VERIFICATION, $request->user()->id);

            return $proofPath;
        });
        if ($oldProofPath !== null && $oldProofPath !== $proofPath) {
            Storage::disk('local')->delete($oldProofPath);
        }
        event(new BusinessActionOccurred(
            userId: $request->user()->id,
            type: 'PAYMENT_QRIS_PROOF_UPLOADED',
            title: 'Bukti QRIS Diunggah',
            body: 'Bukti pembayaran QRIS Anda sedang menunggu verifikasi.',
            data: ['order_id' => $order->id, 'payment_id' => $payment->id],
        ));

        return response()->json([
            'data' => $payment->fresh(),
            'message' => 'Bukti pembayaran QRIS berhasil diunggah.',
        ], 200);
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        $customer = $request->user()->customer;
        abort_unless($customer !== null && $order->customer_id === $customer->id, 403, 'Anda tidak memiliki akses ke pesanan ini.');
        $order = DB::transaction(function () use ($order, $request): Order {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                $lockedOrder->status === OrderStatus::MENUNGGU_PEMBAYARAN->value,
                409,
                'Pesanan tidak dapat dibatalkan pada status saat ini.',
            );
            $lockedOrder->update(['status' => OrderStatus::DIBATALKAN->value]);
            $lockedOrder->statusHistories()->create([
                'from_status' => OrderStatus::MENUNGGU_PEMBAYARAN->value,
                'to_status' => OrderStatus::DIBATALKAN->value,
                'changed_by' => $request->user()->id,
            ]);
            Log::info('Order cancelled', [
                'order_id' => $lockedOrder->id,
                'customer_id' => $lockedOrder->customer_id,
                'user_id' => $request->user()->id,
            ]);

            return $lockedOrder->fresh();
        });

        return response()->json([
            'data' => $order,
            'message' => 'Pesanan berhasil dibatalkan.',
        ], 200);
    }

    public function tracking(Request $request, Order $order): JsonResponse
    {
        $customer = $request->user()->customer;
        abort_unless($customer !== null && $order->customer_id === $customer->id, 403, 'Anda tidak memiliki akses ke tracking ini.');
        $assignment = $order->assignments()
            ->where('status', AssignmentStatus::ACTIVE->value)
            ->with(['locations' => fn ($query) => $query->latest('recorded_at')->limit(10)])
            ->first();

        return response()->json([
            'data' => $assignment,
            'message' => 'Data tracking berhasil dimuat.',
        ], 200);
    }

    private function calculateDeliveryFee(float $latitude, float $longitude): int
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
