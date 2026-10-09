<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Wave 2 - Database Finalization.
 *
 * Memverifikasi schema hasil migrasi, bukan hanya isi file migrasi.
 * Assertion memakai `Schema` facade sehingga otomatis berlaku pada SQLite
 * (driver test) maupun MySQL (driver development).
 *
 * Rujukan:
 * - `docs/13_KYUSUI_DATABASE_FINALIZATION.md` section 9.2, 9.3, 24, 25
 * - `docs/05_KYUSUI_DATABASE_SCHEMA_REBUILT.md` section 5, 6, 11, 12, 13, 17
 */
class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_orders_table_matches_the_canonical_column_names(): void
    {
        // 05 section 11 dan 13_DB section 9.3.
        $this->assertCanonicalColumns('orders', [
            'id', 'order_number', 'customer_id', 'order_status', 'subtotal_amount',
            'delivery_fee', 'total_amount', 'delivery_address', 'delivery_latitude',
            'delivery_longitude', 'placed_at', 'processed_at', 'completed_at',
            'created_at', 'updated_at', 'deleted_at',
        ]);

        foreach (['status', 'subtotal', 'total'] as $legacyColumn) {
            $this->assertFalse(
                Schema::hasColumn('orders', $legacyColumn),
                "Kolom lama `orders.{$legacyColumn}` masih ada."
            );
        }
    }

    public function test_payments_table_matches_the_canonical_column_names(): void
    {
        // 13_DB section 9.2: satu order satu payment, tanpa provider field.
        $this->assertCanonicalColumns('payments', [
            'id', 'order_id', 'payment_method', 'payment_status', 'amount',
            'proof_image', 'proof_size', 'proof_mime_type', 'proof_checksum',
            'verified_by', 'verified_at', 'created_at', 'updated_at',
        ]);

        foreach (['method', 'status', 'proof_path', 'idempotency_key'] as $legacyColumn) {
            $this->assertFalse(
                Schema::hasColumn('payments', $legacyColumn),
                "Kolom terlarang `payments.{$legacyColumn}` masih ada."
            );
        }
    }

    public function test_payments_order_id_is_unique(): void
    {
        // 13_DB section 10.1 - harus-not-regress.
        $this->assertTrue(Schema::hasIndex('payments', ['order_id'], 'unique'));
    }

    public function test_order_number_is_unique_and_indexed(): void
    {
        // 05 section 11 dan 13_DB section 25.
        $this->assertTrue(Schema::hasIndex('orders', ['order_number'], 'unique'));
        $this->assertTrue(Schema::hasIndex('orders', ['customer_id']));
        $this->assertTrue(Schema::hasIndex('orders', ['order_status']));
        $this->assertTrue(Schema::hasIndex('orders', ['placed_at']));
        $this->assertTrue(Schema::hasIndex('orders', ['deleted_at']));
    }

    public function test_order_items_snapshot_product_name_and_use_line_total(): void
    {
        // 05 section 12 dan 13_DB section 25.
        $this->assertCanonicalColumns('order_items', [
            'id', 'order_id', 'product_id', 'product_name', 'quantity', 'unit_price', 'line_total',
        ]);

        $this->assertFalse(Schema::hasColumn('order_items', 'line_total_amount'));
    }

    public function test_order_status_histories_uses_changed_by_user_id(): void
    {
        // 13_DB section 24 mewajibkan `changed_by_user_id`, bukan `changed_by`.
        $this->assertCanonicalColumns('order_status_histories', [
            'id', 'order_id', 'from_status', 'to_status', 'changed_by_user_id',
            'note', 'changed_at', 'created_at', 'updated_at',
        ]);

        $this->assertFalse(Schema::hasColumn('order_status_histories', 'changed_by'));
        $this->assertTrue(Schema::hasIndex('order_status_histories', ['order_id', 'changed_at']));
        $this->assertTrue(Schema::hasForeignKey('order_status_histories', ['changed_by_user_id']));
    }

    public function test_courier_locations_uses_courier_assignment_id_without_idempotency_key(): void
    {
        // 05 section 17 dan 13_DB section 9.3.
        $this->assertCanonicalColumns('courier_locations', [
            'id', 'courier_assignment_id', 'courier_id',
            'latitude', 'longitude', 'accuracy_meters', 'recorded_at',
        ]);

        $this->assertFalse(Schema::hasColumn('courier_locations', 'assignment_id'));
        $this->assertFalse(Schema::hasColumn('courier_locations', 'idempotency_key'));
        $this->assertTrue(Schema::hasIndex('courier_locations', ['courier_assignment_id', 'recorded_at']));
    }

    public function test_orders_does_not_carry_an_idempotency_key(): void
    {
        // 13_DB section 9.3.
        $this->assertFalse(Schema::hasColumn('orders', 'idempotency_key'));
    }

    public function test_business_settings_is_a_singleton_with_updated_by(): void
    {
        // 13_DB section 21.2 dan 22.
        $this->assertCanonicalColumns('business_settings', [
            'id', 'qris_image', 'updated_by', 'created_at', 'updated_at',
        ]);

        // Foreign key ini dibuat lewat `ALTER TABLE` sehingga tidak ada pada
        // SQLite, yang tidak bisa menambahkan FK tanpa rebuild tabel. Delete
        // rule final diverifikasi terhadap MySQL (lihat dump schema di
        // KYUSUI_BACKEND_REPAIR_REPORT.md bagian Database).
        if (DB::getDriverName() !== 'sqlite') {
            $this->assertTrue(Schema::hasForeignKey('business_settings', ['updated_by']));
        }
    }

    public function test_roles_expose_display_name_and_users_expose_status(): void
    {
        // 05 section 5 dan section 6.
        $this->assertCanonicalColumns('roles', ['id', 'name', 'display_name']);
        $this->assertTrue(Schema::hasColumn('users', 'status'));
    }

    public function test_couriers_phone_is_unique(): void
    {
        // 05 section 19 dan 13_DB section 25.
        $this->assertTrue(Schema::hasColumn('couriers', 'phone'));
        $this->assertTrue(Schema::hasIndex('couriers', ['phone'], 'unique'));
    }

    public function test_legacy_payment_provider_artifacts_do_not_exist(): void
    {
        // 06 section 28 dan 13_DB section 9.3: Midtrans/gateway/webhook dan
        // `payment_transactions` dilarang keras.
        $this->assertFalse(Schema::hasTable('payment_transactions'));
        $this->assertFalse(Schema::hasTable('payment_webhooks'));

        foreach (['orders', 'payments', 'courier_locations', 'customers', 'couriers', 'users'] as $table) {
            $this->assertFalse(
                Schema::hasColumn($table, 'idempotency_key'),
                "`{$table}.idempotency_key` masih ada."
            );
        }
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function assertCanonicalColumns(string $table, array $columns): void
    {
        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn($table, $column),
                "Kolom wajib `{$table}.{$column}` tidak ada."
            );
        }
    }
}
