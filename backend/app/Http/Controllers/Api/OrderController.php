<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\BusinessActionOccurred;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $r)
    {
        $c = $r->user()->customer;

        return ['data' => $c->orders()->with('items.product', 'payment')->latest()->get(), 'meta' => [], 'message' => 'Data berhasil dimuat.'];
    }

    public function store(Request $r)
    {
        $v = $r->validate(['items' => 'required|array|min:1', 'items.*.product_id' => 'required|integer|exists:products,id', 'items.*.quantity' => 'required|integer|min:1|max:100', 'delivery_address' => 'required|string|max:500', 'payment_method' => 'required|in:QRIS,CASH']);
        $o = DB::transaction(function () use ($v, $r) {
            $c = $r->user()->customer;
            $initialStatus = $v['payment_method'] === 'CASH'
                ? OrderStatus::MENUNGGU_DIPROSES->value
                : OrderStatus::MENUNGGU_PEMBAYARAN->value;
            $o = Order::create(['customer_id' => $c->id, 'status' => $initialStatus, 'subtotal' => 0, 'total' => 0, 'delivery_address' => $v['delivery_address']]);
            $sub = 0;
            foreach ($v['items'] as $i) {
                $p = Product::whereKey($i['product_id'])->where('availability', true)->firstOrFail();
                $line = $p->price * $i['quantity'];
                $sub += $line;
                $o->items()->create(['product_id' => $p->id, 'quantity' => $i['quantity'], 'unit_price' => $p->price, 'line_total' => $line]);
            }$o->update(['subtotal' => $sub, 'total' => $sub]);
            $payment = $o->payment()->create(['method' => $v['payment_method'], 'status' => PaymentStatus::PENDING->value]);
            $payment->statusHistories()->create(['to_status' => PaymentStatus::PENDING->value, 'changed_by' => $r->user()->id]);
            $o->histories()->create(['to_status' => $o->status, 'changed_by' => $r->user()->id]);

            event(new BusinessActionOccurred($r->user()->id, 'ORDER_CREATED', 'Order dibuat', 'Order baru berhasil dibuat.', ['order_id' => $o->id]));

            return $o;
        });

        return ['data' => $o->load('items.product', 'payment'), 'message' => 'Pesanan berhasil dibuat.'];
    }

    public function show(Request $r, Order $order)
    {
        abort_unless($order->customer_id === $r->user()->customer->id, 403);

        return ['data' => $order->load('items.product', 'payment', 'histories'), 'message' => 'Data berhasil dimuat.'];
    }

    public function payment(Request $r, Order $order)
    {
        abort_unless($order->customer_id === $r->user()->customer->id, 403);

        return ['data' => $order->payment, 'message' => 'Data berhasil dimuat.'];
    }

    public function method(Request $r, Order $order)
    {
        abort_unless($order->customer_id === $r->user()->customer->id, 403);
        $v = $r->validate(['method' => 'required|in:QRIS,CASH']);
        abort_if($order->payment->status !== PaymentStatus::PENDING->value || $order->status !== OrderStatus::MENUNGGU_PEMBAYARAN->value, 409);
        $order->payment->update(['method' => $v['method']]);

        return ['data' => $order->payment, 'message' => 'Metode pembayaran berhasil dipilih.'];
    }

    public function tracking(Request $r, Order $order)
    {
        abort_unless($order->customer_id === $r->user()->customer->id, 403);
        $a = $order->assignments()->where('status', 'ACTIVE')->latest()->first();

        return ['data' => $a?->load('locations'), 'message' => 'Data berhasil dimuat.'];
    }
}
