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
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::get('me', [AuthController::class, 'me']);
        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/{notification}/read', [NotificationController::class, 'read']);
        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{product}', [ProductController::class, 'show']);
        Route::middleware('role:CUSTOMER')->get('customer/dashboard', [DashboardController::class, 'customer']);
        Route::middleware('role:CUSTOMER')->group(function () {
            Route::apiResource('orders', OrderController::class)->only(['index', 'store', 'show']);
            Route::get('orders/{order}/payment', [OrderController::class, 'payment']);
            Route::post('orders/{order}/payment/method', [OrderController::class, 'method']);
            Route::post('orders/{order}/payment/qris/proof', [PaymentController::class, 'proof']);
            Route::get('orders/{order}/tracking', [OrderController::class, 'tracking']);
            Route::get('qris/active', [PaymentController::class, 'activeQris']);
            Route::get('qris/active/image', [PaymentController::class, 'activeQrisImage'])->name('customer.qris.image');
        });
        Route::prefix('owner')->middleware('role:OWNER')->group(function () {
            Route::get('dashboard', [DashboardController::class, 'owner']);
            Route::get('products', [ProductController::class, 'ownerIndex']);
            Route::post('products', [ProductController::class, 'store']);
            Route::put('products/{product}', [ProductController::class, 'update']);
            Route::delete('products/{product}', [ProductController::class, 'destroy']);
            Route::get('orders', [OwnerController::class, 'orders']);
            Route::get('orders/{order}', [OwnerController::class, 'order']);
            Route::get('couriers', [OwnerController::class, 'couriers']);
            Route::get('payments/qris/pending', [OwnerController::class, 'pending']);
            Route::get('qris', [OwnerController::class, 'qris']);
            Route::put('qris', [OwnerController::class, 'updateQris']);
            Route::post('payments/{payment}/approve', [OwnerController::class, 'approve']);
            Route::post('payments/{payment}/reject', [OwnerController::class, 'reject']);
            Route::get('payments/{payment}/proof', [OwnerController::class, 'proof']);
            Route::post('orders/{order}/process', [OwnerController::class, 'process']);
            Route::post('orders/{order}/assign-courier', [OwnerController::class, 'assign']);
        });
        Route::prefix('courier')->middleware('role:COURIER')->group(function () {
            Route::get('dashboard', [DashboardController::class, 'courier']);
            Route::get('assignments', [CourierController::class, 'index']);
            Route::get('assignments/{assignment}', [CourierController::class, 'show']);
            Route::post('assignments/{assignment}/start', [CourierController::class, 'start']);
            Route::post('assignments/{assignment}/location', [CourierController::class, 'location']);
            Route::post('assignments/{assignment}/cash/confirm', [CourierController::class, 'cash']);
            Route::post('assignments/{assignment}/complete', [CourierController::class, 'complete']);
        });
    });
});
