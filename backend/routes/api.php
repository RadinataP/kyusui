<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourierController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OwnerController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/register', [AuthController::class, 'register'])->name('api.v1.auth.register');
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('api.v1.auth.login');
    Route::middleware('auth:sanctum')->group(function () {
        Route::middleware(['role:CUSTOMER', 'throttle:10,1'])
            ->get('dashboard/customer', [DashboardController::class, 'customer'])
            ->name('api.v1.dashboard.customer');
        Route::middleware(['role:OWNER', 'throttle:30,1'])
            ->get('dashboard/owner', [DashboardController::class, 'owner'])
            ->name('api.v1.dashboard.owner');
        Route::middleware(['role:COURIER', 'throttle:20,1'])
            ->get('dashboard/courier', [DashboardController::class, 'courier'])
            ->name('api.v1.dashboard.courier');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
        Route::post('auth/logout-all', [AuthController::class, 'logoutAllDevices'])->name('api.v1.auth.logout-all');
        Route::post('auth/refresh-token', [AuthController::class, 'refreshToken'])->name('api.v1.auth.refresh-token');
        Route::get('auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        Route::get('me', [AuthController::class, 'me'])->name('api.v1.me');
        Route::get('profile', [ProfileController::class, 'show'])->name('api.v1.profile.show');
        Route::put('profile', [ProfileController::class, 'update'])->name('api.v1.profile.update');
        Route::get('notifications', [NotificationController::class, 'index'])->name('api.v1.notifications.index');
        Route::post('notifications/device-token', [NotificationController::class, 'registerDeviceToken'])
            ->name('api.v1.notifications.device-token');
        Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('api.v1.notifications.read-all');
        Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('api.v1.notifications.read');
        Route::get('products', [ProductController::class, 'index'])->name('api.v1.products.index');
        Route::get('products/{product}', [ProductController::class, 'show'])->name('api.v1.products.show');
        Route::middleware(['role:CUSTOMER', 'throttle:5,1'])->prefix('orders')->group(function () {
            Route::get('/', [OrderController::class, 'index'])->name('api.v1.orders.index');
            Route::post('/', [OrderController::class, 'store'])->name('api.v1.orders.store');
            Route::get('/{order}', [OrderController::class, 'show'])->name('api.v1.orders.show');
            Route::get('/{order}/payment', [OrderController::class, 'payment'])->name('api.v1.orders.payment');
            Route::put('/{order}/method', [OrderController::class, 'updateMethod'])->name('api.v1.orders.method');
            Route::get('/{order}/tracking', [OrderController::class, 'tracking'])->name('api.v1.orders.tracking');
            Route::post('/{order}/qris-proof', [PaymentController::class, 'proof'])->name('api.v1.orders.qris-proof');
            Route::post('/{order}/cancel', [OrderController::class, 'cancel'])->name('api.v1.orders.cancel');
        });
        Route::middleware(['role:CUSTOMER'])->prefix('payment')->group(function () {
            Route::get('/qris', [PaymentController::class, 'activeQris'])->name('customer.qris');
            Route::get('/qris/image', [PaymentController::class, 'activeQrisImage'])->name('customer.qris.image');
        });
        Route::middleware('role:CUSTOMER')
            ->get('customer/payments', [PaymentController::class, 'history'])
            ->name('api.v1.customer.payments.index');
        Route::prefix('owner')->middleware('role:OWNER')->group(function () {
            Route::get('products', [ProductController::class, 'ownerIndex'])->name('api.v1.owner.products.index');
            Route::post('products', [ProductController::class, 'store'])->name('api.v1.owner.products.store');
            Route::put('products/{product}', [ProductController::class, 'update'])->name('api.v1.owner.products.update');
            Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('api.v1.owner.products.destroy');
            Route::get('orders', [OwnerController::class, 'orders'])->name('api.v1.owner.orders.index');
            Route::get('orders/{order}', [OwnerController::class, 'order'])->name('api.v1.owner.orders.show');
            Route::get('couriers', [OwnerController::class, 'couriers'])->name('api.v1.owner.couriers.index');
            Route::get('payments/qris/pending', [OwnerController::class, 'pending'])->name('api.v1.owner.payments.qris.pending');
            Route::get('qris', [OwnerController::class, 'qris'])->name('api.v1.owner.qris.show');
            Route::get('qris/image', [PaymentController::class, 'activeQrisImage'])->name('owner.qris.image');
            Route::put('qris', [OwnerController::class, 'updateQris'])->name('api.v1.owner.qris.update');
            Route::post('payments/{payment}/approve', [OwnerController::class, 'approve'])->name('api.v1.owner.payments.approve');
            Route::post('payments/{payment}/reject', [OwnerController::class, 'reject'])->name('api.v1.owner.payments.reject');
            Route::post('orders/{order}/payment-verification', [OwnerController::class, 'verifyOrderPayment'])
                ->name('api.v1.owner.orders.payment-verification');
            Route::get('payments/{payment}/proof', [OwnerController::class, 'proof'])->name('api.v1.owner.payments.proof');
            Route::post('orders/{order}/process', [OwnerController::class, 'process'])->name('api.v1.owner.orders.process');
            Route::post('orders/{order}/assign-courier', [OwnerController::class, 'assign'])->name('api.v1.owner.orders.assign-courier');
        });
        Route::prefix('courier')->middleware('ensure-role:COURIER')->group(function () {
            Route::post('orders/{order}/payment-confirmation', [CourierController::class, 'confirmOrderPayment'])
                ->name('api.v1.courier.orders.payment-confirmation');
            Route::get('assignments', [CourierController::class, 'index'])->name('api.v1.courier.assignments.index');
            Route::get('assignments/{assignment}', [CourierController::class, 'show'])->name('api.v1.courier.assignments.show');
            Route::post('assignments/{assignment}/start', [CourierController::class, 'start'])->name('api.v1.courier.assignments.start');
            Route::post('assignments/{assignment}/location', [CourierController::class, 'location'])->name('api.v1.courier.assignments.location');
            Route::post('assignments/{assignment}/cash/confirm', [CourierController::class, 'cash'])->name('api.v1.courier.assignments.cash.confirm');
            Route::post('assignments/{assignment}/complete', [CourierController::class, 'complete'])->name('api.v1.courier.assignments.complete');
        });
    });
});
