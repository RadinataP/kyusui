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

/*
|--------------------------------------------------------------------------
| KYUSUI API v1
|--------------------------------------------------------------------------
|
| Path dan method mengikuti katalog final `docs/06_KYUSUI_API_SPECIFICATION_REBUILT.md`
| section 30. Base prefix `/api/v1` installation ditambahkan oleh `bootstrap/app.php`.
|
| Endpoint yang tidak ada di katalog dan sengaja dipertahankan diberi komentar
| `preserved`, karena menghapusnya akan memutus klien yang sedang berjalan dan
| tidak ada keputusan contract yang mengizinkan penghapusan tersebut.
|
*/
Route::prefix('v1')->group(function () {
    // --- AUTH (spec 06 section 5) ---
    Route::post('auth/register', [AuthController::class, 'register'])->name('api.v1.auth.register');
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('api.v1.auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
        // preserved: session management di luar katalog spec
        Route::post('auth/logout-all', [AuthController::class, 'logoutAllDevices'])->name('api.v1.auth.logout-all');
        // preserved: session management di luar katalog spec
        Route::post('auth/refresh-token', [AuthController::class, 'refreshToken'])->name('api.v1.auth.refresh-token');
        Route::get('auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        // Audit P3-01: alias lama `/me` dihapus. Katalog spec 06 section 30 hanya
        // mendefinisikan `GET /auth/me`, yang memang dipanggil `AuthApi.kt`.
        // `GET /api/v1/me` kini menghasilkan 405/404.

        // --- DASHBOARD (preserved: tidak ada di katalog spec, dipakai klien) ---
        Route::middleware(['role:CUSTOMER', 'throttle:10,1'])
            ->get('dashboard/customer', [DashboardController::class, 'customer'])
            ->name('api.v1.dashboard.customer');
        Route::middleware(['role:OWNER', 'throttle:30,1'])
            ->get('dashboard/owner', [DashboardController::class, 'owner'])
            ->name('api.v1.dashboard.owner');
        Route::middleware(['role:COURIER', 'throttle:20,1'])
            ->get('dashboard/courier', [DashboardController::class, 'courier'])
            ->name('api.v1.dashboard.courier');

        // --- CUSTOMER PROFILE (spec 06 section 9.1 dan 9.2) ---
        Route::middleware('role:CUSTOMER')->group(function () {
            Route::get('customer/profile', [ProfileController::class, 'customerProfile'])
                ->name('api.v1.customer.profile.show');
            Route::put('customer/profile', [ProfileController::class, 'updateCustomerProfile'])
                ->name('api.v1.customer.profile.update');
        });

        // preserved: profile management untuk OWNER dan COURIER, tidak ada di katalog spec
        Route::get('profile', [ProfileController::class, 'show'])->name('api.v1.profile.show');
        Route::put('profile', [ProfileController::class, 'update'])->name('api.v1.profile.update');

        // --- NOTIFICATION (spec 06 section 17) ---
        Route::get('notifications', [NotificationController::class, 'index'])->name('api.v1.notifications.index');
        Route::post('notifications/device-token', [NotificationController::class, 'registerDeviceToken'])
            ->name('api.v1.notifications.device-token');
        // preserved: bulk action di luar katalog spec
        Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('api.v1.notifications.read-all');
        Route::patch('notifications/{notification}/read', [NotificationController::class, 'read'])
            ->name('api.v1.notifications.read');

        // --- PRODUCT CATALOG (spec 06 section 9.3) ---
        Route::get('products', [ProductController::class, 'index'])->name('api.v1.products.index');
        // preserved: detail produk tidak ada di katalog spec
        Route::get('products/{product}', [ProductController::class, 'show'])->name('api.v1.products.show');

        // --- CUSTOMER ORDER (spec 06 section 9.4 - 9.7) ---
        Route::middleware(['role:CUSTOMER', 'throttle:5,1'])->prefix('customer/orders')->group(function () {
            Route::get('/', [OrderController::class, 'index'])->name('api.v1.customer.orders.index');
            Route::post('/', [OrderController::class, 'store'])->name('api.v1.customer.orders.store');
            Route::get('/{order}', [OrderController::class, 'show'])->name('api.v1.customer.orders.show');
            Route::get('/{order}/tracking', [OrderController::class, 'tracking'])->name('api.v1.customer.orders.tracking');
            // preserved: Keputusan bisnis (Pilihan A) - pembatalan hanya pada MENUNGGU_PEMBAYARAN
            Route::post('/{order}/cancel', [OrderController::class, 'cancel'])->name('api.v1.customer.orders.cancel');
        });

        // --- CUSTOMER PAYMENT (spec 06 section 11) ---
        Route::middleware('role:CUSTOMER')->prefix('customer')->group(function () {
            Route::get('payment/qris', [PaymentController::class, 'activeQris'])->name('api.v1.customer.payment.qris');
            Route::get('payments', [PaymentController::class, 'history'])->name('api.v1.customer.payments.index');
            Route::get('orders/{order}/payment', [PaymentController::class, 'show'])
                ->name('api.v1.customer.orders.payment.show');
            Route::post('orders/{order}/payment', [PaymentController::class, 'select'])
                ->name('api.v1.customer.orders.payment.select');
            Route::post('orders/{order}/payment/proof', [PaymentController::class, 'proof'])
                ->name('api.v1.customer.orders.payment.proof');
        });

        // preserved: private file delivery untuk QRIS image, tidak ada di katalog spec
        Route::middleware('role:CUSTOMER,OWNER')
            ->get('customer/payment/qris/image', [PaymentController::class, 'activeQrisImage'])
            ->name('customer.qris.image');

        // --- OWNER (spec 06 section 10, 12, dan 13) ---
        Route::prefix('owner')->middleware('role:OWNER')->group(function () {
            // preserved: katalog spec tidak mendefinisikan endpoint produk owner
            Route::get('products', [ProductController::class, 'ownerIndex'])->name('api.v1.owner.products.index');
            Route::post('products', [ProductController::class, 'store'])->name('api.v1.owner.products.store');
            Route::put('products/{product}', [ProductController::class, 'update'])->name('api.v1.owner.products.update');
            Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('api.v1.owner.products.destroy');

            Route::get('orders', [OwnerController::class, 'orders'])->name('api.v1.owner.orders.index');
            Route::get('orders/{order}', [OwnerController::class, 'order'])->name('api.v1.owner.orders.show');
            // preserved: katalog spec tidak mendefinisikan endpoint daftar kurir
            Route::get('couriers', [OwnerController::class, 'couriers'])->name('api.v1.owner.couriers.index');

            Route::patch('orders/{order}/status', [OwnerController::class, 'updateStatus'])
                ->name('api.v1.owner.orders.status');
            Route::post('orders/{order}/assignment', [OwnerController::class, 'assign'])
                ->name('api.v1.owner.orders.assignment');

            Route::get('payments/pending', [OwnerController::class, 'pending'])->name('api.v1.owner.payments.pending');
            Route::get('orders/{order}/payment/proof', [OwnerController::class, 'proof'])
                ->name('api.v1.owner.orders.payment.proof');
            Route::post('orders/{order}/payment-verification', [OwnerController::class, 'verifyOrderPayment'])
                ->name('api.v1.owner.orders.payment-verification');

            Route::get('payment/qris', [OwnerController::class, 'qris'])->name('api.v1.owner.payment.qris.show');
            Route::put('payment/qris', [OwnerController::class, 'updateQris'])->name('api.v1.owner.payment.qris.update');
        });

        // --- COURIER ---
        // Assignment-centric, bukan order-centric seperti spec 06 section 14-16.
        // Deviasi ini decided owner project dan saat ini dipakai penuh oleh klien
        // Android, jadi route tidak dihapus pada Phase B.
        Route::prefix('courier')->middleware('role:COURIER')->group(function () {
            Route::post('orders/{order}/payment-confirmation', [CourierController::class, 'confirmOrderPayment'])
                ->name('api.v1.courier.orders.payment-confirmation');
            Route::get('assignments', [CourierController::class, 'index'])->name('api.v1.courier.assignments.index');
            Route::get('assignments/{assignment}', [CourierController::class, 'show'])->name('api.v1.courier.assignments.show');
            Route::post('assignments/{assignment}/start', [CourierController::class, 'start'])->name('api.v1.courier.assignments.start');
            // Rate limit: 120 req/menit per courier (2 req/detik burst, rata-rata 30s interval = 2 req/menit normal)
            // Client-side interval = 30s (COURIER_SUBMISSION_INTERVAL_MS), jadi 120/menit memberi headroom 4x untuk retry/jitter.
            Route::middleware('throttle:120,1')
                ->post('assignments/{assignment}/location', [CourierController::class, 'location'])
                ->name('api.v1.courier.assignments.location');
            Route::post('assignments/{assignment}/cash/confirm', [CourierController::class, 'cash'])->name('api.v1.courier.assignments.cash.confirm');
            Route::post('assignments/{assignment}/complete', [CourierController::class, 'complete'])->name('api.v1.courier.assignments.complete');
        });
    });
});
