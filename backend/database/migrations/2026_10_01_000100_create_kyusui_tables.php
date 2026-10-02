<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', fn (Blueprint $t) => [$t->id(), $t->string('name')->unique()]);
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('role_id')->nullable()->after('id')->constrained('roles');
        });
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('phone')->nullable();
            $t->text('address')->nullable();
            $t->timestamps();
        });
        Schema::create('owners', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->timestamps();
        });
        Schema::create('couriers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('phone')->nullable();
            $t->string('vehicle')->nullable();
            $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->text('description')->nullable();
            $t->decimal('price', 15, 2);
            $t->boolean('availability')->default(true);
            $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->string('status')->index();
            $t->decimal('subtotal', 15, 2);
            $t->decimal('total', 15, 2);
            $t->text('delivery_address');
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('quantity');
            $t->decimal('unit_price', 15, 2);
            $t->decimal('line_total', 15, 2);
            $t->unique(['order_id', 'product_id']);
        });
        Schema::create('order_status_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('from_status')->nullable();
            $t->string('to_status');
            $t->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('method');
            $t->string('status');
            $t->string('proof_path')->nullable();
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
        });
        Schema::create('courier_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('courier_id')->constrained()->restrictOnDelete();
            $t->string('status')->default('ASSIGNED');
            $t->timestamp('assigned_at')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->index(['courier_id', 'status']);
        });
        Schema::create('courier_locations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assignment_id')->constrained('courier_assignments')->cascadeOnDelete();
            $t->foreignId('courier_id')->constrained()->restrictOnDelete();
            $t->decimal('latitude', 10, 7);
            $t->decimal('longitude', 10, 7);
            $t->decimal('accuracy_meters', 8, 2);
            $t->timestamp('recorded_at');
            $t->index(['assignment_id', 'recorded_at']);
        });
        Schema::create('business_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('type');
            $t->string('title');
            $t->text('body');
            $t->json('data')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        foreach (['notifications', 'business_settings', 'courier_locations', 'courier_assignments', 'payments', 'order_status_histories', 'order_items', 'orders', 'products', 'couriers', 'owners', 'customers'] as $t) {
            Schema::dropIfExists($t);
        } Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('role_id'));
        Schema::dropIfExists('roles');
    }
};
