<?php

namespace App\Http\Controllers\Api;

use App\Enums\AssignmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardAssignmentResource;
use App\Http\Resources\DashboardOrderResource;
use App\Http\Resources\DashboardPaymentResource;
use App\Http\Resources\UserResource;
use App\Models\Courier;
use App\Models\CourierAssignment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class DashboardController extends Controller
{
    public function customer(Request $request): JsonResponse
    {
        $user = $request->user();
        $customer = $user?->customer;
        abort_unless($customer !== null, 403, 'Profil customer tidak tersedia.');

        try {
            $activeStatuses = [
                OrderStatus::MENUNGGU_PEMBAYARAN->value,
                OrderStatus::MENUNGGU_DIPROSES->value,
                OrderStatus::DIPROSES->value,
                OrderStatus::DITUGASKAN->value,
                OrderStatus::DALAM_PENGANTARAN->value,
            ];
            $ordersQuery = $customer->orders();
            $completedOrders = (clone $ordersQuery)->where('order_status', OrderStatus::SELESAI->value);
            $favoriteProduct = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->join('products', 'products.id', '=', 'order_items.product_id')
                ->where('orders.customer_id', $customer->id)
                ->select('products.id', 'products.name')
                ->selectRaw('COUNT(order_items.id) as order_count')
                ->groupBy('products.id', 'products.name')
                ->orderByDesc('order_count')
                ->first();
            $activeOrders = (clone $ordersQuery)
                ->whereIn('order_status', $activeStatuses)
                ->with(['payment', 'items', 'assignments.courier.user:id,name'])
                ->latest('id')
                ->limit(5)
                ->get();
            $data = [
                'user' => new UserResource($user->load('role')),
                'summary' => [
                    'total_orders' => (clone $ordersQuery)->count(),
                    'active_orders' => (clone $ordersQuery)->whereIn('order_status', $activeStatuses)->count(),
                    'total_spent' => (float) ($completedOrders->sum('total_amount') ?? 0),
                    'total_completed_orders' => $completedOrders->count(),
                    'favorite_product' => $favoriteProduct ? [
                        'id' => $favoriteProduct->id,
                        'name' => $favoriteProduct->name,
                        'order_count' => (int) $favoriteProduct->order_count,
                    ] : null,
                ],
                'active_orders' => DashboardOrderResource::collection($activeOrders),
                'unread_notifications' => $user->appNotifications()->whereNull('read_at')->count(),
            ];
            Log::info('Customer dashboard accessed.', $this->requestContext($request));

            return response()->json(['data' => $data, 'message' => 'Data dashboard customer berhasil diambil.'], 200);
        } catch (HttpExceptionInterface $exception) {
            throw $exception;
        } catch (QueryException|Throwable $exception) {
            return $this->serverError('customer', $request, $exception);
        }
    }

    public function owner(Request $request): JsonResponse
    {
        $this->authorizeOwner($request);

        try {
            $today = today();
            $monthStart = now()->startOfMonth();
            $orderMetrics = DB::table('orders')
                ->selectRaw('COUNT(*) as total_orders')
                ->selectRaw('SUM(CASE WHEN order_status = ? THEN 1 ELSE 0 END) as waiting_process', [OrderStatus::MENUNGGU_DIPROSES->value])
                ->selectRaw('SUM(CASE WHEN order_status = ? THEN 1 ELSE 0 END) as completed_orders', [OrderStatus::SELESAI->value])
                ->selectRaw('COALESCE(SUM(CASE WHEN order_status = ? AND completed_at >= ? THEN total_amount ELSE 0 END), 0) as today_revenue', [OrderStatus::SELESAI->value, $today])
                ->selectRaw('COALESCE(SUM(CASE WHEN order_status = ? AND completed_at >= ? THEN total_amount ELSE 0 END), 0) as month_revenue', [OrderStatus::SELESAI->value, $monthStart])
                ->selectRaw('COALESCE(AVG(CASE WHEN order_status = ? THEN total_amount END), 0) as avg_order_value', [OrderStatus::SELESAI->value])
                ->selectRaw('COALESCE(100.0 * SUM(CASE WHEN order_status = ? THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0), 0) as completion_rate', [OrderStatus::SELESAI->value])
                ->first();
            $paymentMetrics = DB::table('payments')
                ->selectRaw('SUM(CASE WHEN payment_method = ? AND payment_status = ? THEN 1 ELSE 0 END) as waiting_qris_verification', [PaymentMethod::QRIS->value, PaymentStatus::WAITING_VERIFICATION->value])
                ->first();
            $assignmentMetrics = DB::table('courier_assignments')
                ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active_deliveries', [AssignmentStatus::ACTIVE->value])
                ->first();
            $waitingStatuses = [OrderStatus::MENUNGGU_DIPROSES->value, OrderStatus::DIPROSES->value];
            $data = [
                'summary' => [
                    'total_orders' => (int) $orderMetrics->total_orders,
                    'waiting_process' => (int) $orderMetrics->waiting_process,
                    'completed_orders' => (int) $orderMetrics->completed_orders,
                    'waiting_qris_verification' => (int) $paymentMetrics->waiting_qris_verification,
                    'active_deliveries' => (int) $assignmentMetrics->active_deliveries,
                    'available_products' => Product::where('availability', true)->count(),
                    'today_revenue' => (float) $orderMetrics->today_revenue,
                    'month_revenue' => (float) $orderMetrics->month_revenue,
                    'avg_order_value' => (float) $orderMetrics->avg_order_value,
                    'completion_rate' => (float) $orderMetrics->completion_rate,
                ],
                'recent_orders' => DashboardOrderResource::collection(
                    Order::with(['customer.user:id,name', 'payment', 'items', 'assignments'])
                        ->latest('id')->limit(5)->get()
                ),
                'waiting_orders' => DashboardOrderResource::collection(
                    Order::whereIn('order_status', $waitingStatuses)
                        ->with(['customer.user:id,name', 'payment', 'items', 'assignments'])
                        ->latest('id')->limit(5)->get()
                ),
                'pending_qris_payments' => DashboardPaymentResource::collection(
                    Payment::where('payment_method', PaymentMethod::QRIS->value)
                        ->where('payment_status', PaymentStatus::WAITING_VERIFICATION->value)
                        ->with('order.customer.user:id,name')
                        ->latest('id')->limit(5)->get()
                ),
                'active_deliveries_list' => DashboardAssignmentResource::collection(
                    CourierAssignment::where('status', AssignmentStatus::ACTIVE->value)
                        ->with(['order.customer.user:id,name', 'courier.user:id,name'])
                        ->latest('id')->limit(5)->get()
                ),
                'active_couriers' => Courier::whereHas('assignments', fn ($query) => $query->where('status', AssignmentStatus::ACTIVE->value))
                    ->with('user:id,name,email')->get()->map(fn (Courier $courier): array => [
                        'id' => $courier->id,
                        'name' => $courier->user->name,
                        'phone' => $courier->phone,
                        'vehicle' => $courier->vehicle,
                    ])->values(),
            ];
            Log::info('Owner dashboard accessed.', $this->requestContext($request));

            return response()->json(['data' => $data, 'message' => 'Data dashboard owner berhasil diambil.'], 200);
        } catch (HttpExceptionInterface $exception) {
            throw $exception;
        } catch (QueryException|Throwable $exception) {
            return $this->serverError('owner', $request, $exception);
        }
    }

    public function courier(Request $request): JsonResponse
    {
        $user = $request->user();
        $courier = $user?->courier;
        abort_unless($courier !== null, 403, 'Profil courier tidak tersedia.');

        try {
            $todayAssignments = $courier->assignments()->whereDate('assigned_at', today());
            $monthAssignments = $courier->assignments()->where('assigned_at', '>=', now()->startOfMonth());
            $completedDeliveries = (clone $monthAssignments)->where('status', AssignmentStatus::COMPLETED->value)->count();
            $monthDeliveries = (clone $monthAssignments)->count();
            $activeAssignment = $courier->assignments()
                ->where('status', AssignmentStatus::ACTIVE->value)
                ->with(['order.customer.user:id,name', 'order.payment', 'order.items', 'order.assignments'])
                ->latest('id')->first();
            $todayAssignmentRecords = $todayAssignments
                ->with(['order.customer.user:id,name', 'order.payment', 'order.items', 'order.assignments'])
                ->latest('assigned_at')->limit(20)->get();
            $data = [
                'active_assignment' => $activeAssignment ? new DashboardOrderResource($activeAssignment->order) : null,
                'today_assignments' => DashboardOrderResource::collection($todayAssignmentRecords->map->order),
                'summary' => [
                    'today_deliveries' => (clone $todayAssignments)->count(),
                    'month_deliveries' => $monthDeliveries,
                    'completed_deliveries' => $completedDeliveries,
                    'completion_rate' => $monthDeliveries > 0 ? round(($completedDeliveries / $monthDeliveries) * 100, 2) : 0.0,
                ],
                'unread_notifications' => $user->appNotifications()->whereNull('read_at')->count(),
            ];
            Log::info('Courier dashboard accessed.', $this->requestContext($request));

            return response()->json(['data' => $data, 'message' => 'Data dashboard courier berhasil diambil.'], 200);
        } catch (HttpExceptionInterface $exception) {
            throw $exception;
        } catch (QueryException|Throwable $exception) {
            return $this->serverError('courier', $request, $exception);
        }
    }

    private function authorizeOwner(Request $request): void
    {
        $user = $request->user();
        abort_unless($user !== null && $user->role?->name === 'OWNER', 403, 'Akses ditolak. Hanya Owner yang dapat melihat dashboard ini.');
    }

    private function requestContext(Request $request): array
    {
        return ['user_id' => $request->user()?->id, 'ip' => $request->ip()];
    }

    private function serverError(string $dashboard, Request $request, Throwable $exception): JsonResponse
    {
        Log::error('Dashboard request failed.', array_merge($this->requestContext($request), [
            'dashboard' => $dashboard,
            'exception' => $exception,
        ]));

        return response()->json([
            'data' => null,
            'message' => 'Dashboard tidak dapat dimuat saat ini.',
        ], 500);
    }
}
