<?php

namespace App\Http\Controllers\Api;

use App\Enums\AssignmentStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardOrderResource;
use App\Http\Resources\UserResource;
use App\Models\Courier;
use App\Models\CourierAssignment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function customer(Request $request): array
    {
        $customer = $request->user()->customer;
        abort_unless($customer !== null, 403, 'Profil customer tidak tersedia.');

        $activeStatuses = [
            OrderStatus::MENUNGGU_PEMBAYARAN->value,
            OrderStatus::MENUNGGU_DIPROSES->value,
            OrderStatus::DIPROSES->value,
            OrderStatus::DITUGASKAN->value,
            OrderStatus::DALAM_PENGANTARAN->value,
        ];
        $orders = $customer->orders();
        $activeOrders = (clone $orders)
            ->whereIn('status', $activeStatuses)
            ->with(['payment', 'items.product', 'assignments'])
            ->latest('id')
            ->limit(5)
            ->get();

        return [
            'data' => [
                'user' => new UserResource($request->user()->load('role')),
                'summary' => [
                    'total_orders' => (clone $orders)->count(),
                    'active_orders' => (clone $orders)->whereIn('status', $activeStatuses)->count(),
                ],
                'active_orders' => DashboardOrderResource::collection($activeOrders),
                'unread_notifications' => $request->user()->appNotifications()->whereNull('read_at')->count(),
            ],
            'message' => 'Data dashboard customer berhasil diambil.',
        ];
    }

    public function owner(): array
    {
        $waitingStatuses = [OrderStatus::MENUNGGU_DIPROSES->value, OrderStatus::DIPROSES->value];

        return [
            'data' => [
                'summary' => [
                    'total_orders' => Order::count(),
                    'waiting_process' => Order::where('status', OrderStatus::MENUNGGU_DIPROSES->value)->count(),
                    'waiting_qris_verification' => Payment::where('method', 'QRIS')->where('status', 'WAITING_VERIFICATION')->count(),
                    'active_deliveries' => CourierAssignment::where('status', AssignmentStatus::ACTIVE->value)->count(),
                    'available_products' => Product::where('availability', true)->count(),
                ],
                'recent_orders' => Order::with('customer.user')->latest('id')->limit(5)->get(),
                'waiting_orders' => Order::whereIn('status', $waitingStatuses)->with('customer.user')->latest('id')->limit(5)->get(),
                'pending_qris_payments' => Payment::where('method', 'QRIS')->where('status', 'WAITING_VERIFICATION')->with('order.customer.user')->latest('id')->limit(5)->get(),
                'active_deliveries' => CourierAssignment::where('status', AssignmentStatus::ACTIVE->value)->with('order.customer.user', 'courier.user')->latest('id')->limit(5)->get(),
                'active_couriers' => Courier::whereHas('assignments', fn ($query) => $query->where('status', AssignmentStatus::ACTIVE->value))->with('user')->get(),
            ],
            'message' => 'Data dashboard owner berhasil diambil.',
        ];
    }

    public function courier(Request $request): array
    {
        $courier = $request->user()->courier;
        abort_unless($courier !== null, 403, 'Profil courier tidak tersedia.');

        return [
            'data' => [
                'active_assignment' => $courier->assignments()
                    ->where('status', AssignmentStatus::ACTIVE->value)
                    ->with('order.customer.user', 'order.payment', 'order.items.product')
                    ->latest('id')
                    ->first(),
                'today_assignments' => $courier->assignments()
                    ->whereDate('assigned_at', today())
                    ->with('order.customer.user', 'order.payment')
                    ->latest('id')
                    ->get(),
                'unread_notifications' => $request->user()->appNotifications()->whereNull('read_at')->count(),
            ],
            'message' => 'Data dashboard courier berhasil diambil.',
        ];
    }
}
