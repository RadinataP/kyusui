<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase B — Wave 2: finalisasi schema mengikuti specification.
 *
 * Rujukan:
 * - `docs/05_KYUSUI_DATABASE_SCHEMA_REBUILT.md` section 11 (orders),
 *   section 12 (order_items), section 13 (order_status_histories),
 *   section 17 (courier_locations), section 20 (index strategy).
 * - `docs/13_KYUSUI_DATABASE_FINALIZATION.md` section 21 (singleton
 *   `business_settings`), section 22 (`updated_by`), section 24 (FK matrix),
 *   section 25 (index strategy final).
 * - `KYUSUI_BACKEND_AUDIT.md` section 23 (database compliance matrix).
 *
 * Prinsip:
 * - Migration lama tidak pernah diedit; semua perubahan dibuat sebagai
 *   migration baru (`add` / `rename` / `drop`).
 * - `renameColumn` tidak diimplementasikan `SQLiteGrammar`, jadi rename memakai
 *   `ALTER TABLE ... RENAME COLUMN ... TO ...` yang tersedia pada MySQL 8 dan
 *   SQLite 3.25, dan index existing otomatis mengikuti kolom barunya.
 * - `ALTER TABLE ... DROP COLUMN` pada SQLite menolak kolom yang masih dipegang
 *   index, jadi unique index selalu dilepas lebih dulu.
 * - Data tidak pernah dihapus hanya agar migration berhasil; setiap backfill
 *   menyalin nilai lama ke kolom canonical.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->finalizeBusinessSettings();
        $this->finalizeOrders();
        $this->finalizeOrderItems();
        $this->finalizeOrderStatusHistories();
        $this->finalizePayments();
        $this->finalizeCourierAssignments();
        $this->finalizeCourierLocations();
        $this->finalizeIdentityTables();
    }

    public function down(): void
    {
        $this->finalizeIdentityTablesRollback();
        $this->finalizeCourierLocationsRollback();
        $this->finalizeCourierAssignmentsRollback();
        $this->finalizePaymentsRollback();
        $this->finalizeOrderStatusHistoriesRollback();
        $this->finalizeOrderItemsRollback();
        $this->finalizeOrdersRollback();
        $this->finalizeBusinessSettingsRollback();
    }

    // ---------------------------------------------------------------------
    // business_settings (13_DB section 21.2 dan section 22)
    // ---------------------------------------------------------------------

    private function finalizeBusinessSettings(): void
    {
        Schema::table('business_settings', function (Blueprint $table): void {
            $table->string('qris_image')->nullable()->after('id');
            $table->unsignedBigInteger('updated_by')->nullable()->after('qris_image');
        });

        $legacyQrisPath = DB::table('business_settings')
            ->whereIn('key', ['qris_image_path', 'qris_image'])
            ->orderBy('id')
            ->value('value');

        $unexpectedKeys = DB::table('business_settings')
            ->whereNotIn('key', ['qris_image_path', 'qris_image'])
            ->pluck('key');

        if ($unexpectedKeys->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'business_settings memuat key yang tidak dikenal [%s]. Konversi ke model singleton '
                .'13_DB section 21.2 membutuhkan keputusan manusia; data tidak dihapus otomatis.',
                $unexpectedKeys->implode(', '),
            ));
        }

        $this->collapseBusinessSettingsToSingleton($legacyQrisPath);

        DB::table('business_settings')->where('id', 1)->update([
            'qris_image' => $legacyQrisPath,
            'updated_at' => now(),
        ]);

        Schema::table('business_settings', function (Blueprint $table): void {
            $table->dropUnique(['key']);
            $table->dropColumn(['key', 'value']);
            $table->index('updated_by');
        });

        $this->addForeignKey('business_settings', 'updated_by', 'users', 'nullOnDelete');
    }

    /**
     * 13_DB section 21.2 menetapkan satu slot konfigurasi dengan `id = 1`.
     * Baris key-value lama hanya berisi path QRIS (sudah divalidasi di atas),
     * sehingga menggabungkannya ke satu baris tidak menghilangkan nilai bisnis.
     */
    private function collapseBusinessSettingsToSingleton(?string $legacyQrisPath): void
    {
        $qrisRow = DB::table('business_settings')
            ->whereIn('key', ['qris_image_path', 'qris_image'])
            ->orderBy('id')
            ->first();

        if ($qrisRow === null) {
            DB::table('business_settings')->insert([
                'id' => 1,
                'key' => 'qris_image_path',
                'value' => $legacyQrisPath,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('business_settings')->where('id', $qrisRow->id)->update([
                'key' => 'qris_image_path',
                'value' => $legacyQrisPath,
            ]);

            if ((int) $qrisRow->id !== 1) {
                DB::table('business_settings')->where('id', 1)->delete();
                DB::table('business_settings')->where('id', $qrisRow->id)->update(['id' => 1]);
            }
        }

        DB::table('business_settings')->where('id', '!=', 1)->delete();
    }

    private function finalizeBusinessSettingsRollback(): void
    {
        $qrisImage = DB::table('business_settings')->where('id', 1)->value('qris_image');

        $this->dropForeignKey('business_settings', 'updated_by');

        Schema::table('business_settings', function (Blueprint $table): void {
            $table->dropIndex(['updated_by']);
            $table->dropColumn(['qris_image', 'updated_by']);
            $table->string('key')->nullable()->unique()->after('id');
            $table->text('value')->nullable();
        });

        DB::table('business_settings')->where('id', 1)->update([
            'key' => 'qris_image_path',
            'value' => $qrisImage,
        ]);

        DB::table('business_settings')->where('id', '!=', 1)->delete();
    }

    // ---------------------------------------------------------------------
    // orders (05 section 11 dan section 20)
    // ---------------------------------------------------------------------

    private function finalizeOrders(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('order_number', 40)->nullable()->after('id');
            $table->timestamp('placed_at')->nullable()->after('delivery_longitude');
            $table->timestamp('processed_at')->nullable()->after('placed_at');
            $table->timestamp('completed_at')->nullable()->after('processed_at');
            $table->timestamp('deleted_at')->nullable()->after('updated_at');
        });

        // Backfill memakai `id`, sehingga nomor order pasti unik.
        DB::table('orders')->orderBy('id')->get(['id', 'created_at'])->each(function (object $row): void {
            $placedAt = $row->created_at ?? now();

            DB::table('orders')->where('id', $row->id)->update([
                'order_number' => sprintf('ORD-%s-%04d', now()->parse($placedAt)->format('Ymd'), $row->id),
                'placed_at' => $placedAt,
            ]);
        });

        if ($this->supportsStrictNullability()) {
            DB::statement('alter table `orders` modify `order_number` varchar(40) not null');
            DB::statement('alter table `orders` modify `placed_at` timestamp not null default CURRENT_TIMESTAMP');
        }

        $this->renameColumn('orders', 'status', 'order_status');
        $this->renameColumn('orders', 'subtotal', 'subtotal_amount');
        $this->renameColumn('orders', 'total', 'total_amount');

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
            $table->unique('order_number');
            $table->index('placed_at');
            $table->index('completed_at');
            $table->index('deleted_at');
        });

        // 05 section 11 dan 13_DB section 25 mewajibkan index pada
        // `orders.customer_id`. MySQL membuatnya otomatis sebagai index
        // pendukung foreign key, SQLite tidak. Tanpa index eksplisit,
        // list order customer melakukan full table scan pada driver test.
        $this->ensureIndex('orders', 'customer_id');
    }

    private function ensureIndex(string $table, string $column): void
    {
        foreach (Schema::getIndexes($table) as $index) {
            $columns = array_map(
                static fn (string $name): string => strtolower($name),
                $index['columns'] ?? [],
            );

            if ($columns !== [] && $columns[0] === strtolower($column)) {
                return;
            }
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            $blueprint->index($column);
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (in_array($indexName, array_column(Schema::getIndexes($table), 'name'), true)) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
                $blueprint->dropIndex($indexName);
            });
        }
    }

    /**
     * Membuat index satu kolom hanya bila belum ada index yang persis satu kolom
     * itu. Berbeda dengan `ensureIndex()`, index komposit dengan kolom yang
     * sama di posisi pertama tidak dianggap cukup karena MySQL tetap memakai
     * index komposit itu sebagai pendukung foreign key.
     */
    private function ensureExactIndex(string $table, string $column): void
    {
        foreach (Schema::getIndexes($table) as $index) {
            $columns = array_map(
                static fn (string $name): string => strtolower($name),
                $index['columns'] ?? [],
            );

            if ($columns === [strtolower($column)]) {
                return;
            }
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            $blueprint->index($column);
        });
    }

    private function finalizeOrdersRollback(): void
    {
        $this->dropIndexIfExists('orders', 'orders_customer_id_index');

        $this->renameColumn('orders', 'order_status', 'status');
        $this->renameColumn('orders', 'subtotal_amount', 'subtotal');
        $this->renameColumn('orders', 'total_amount', 'total');

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['placed_at']);
            $table->dropIndex(['completed_at']);
            $table->dropIndex(['deleted_at']);
            $table->dropUnique(['order_number']);
            $table->string('idempotency_key', 64)->nullable()->unique()->after('delivery_longitude');
            $table->dropColumn(['order_number', 'placed_at', 'processed_at', 'completed_at', 'deleted_at']);
        });
    }

    // ---------------------------------------------------------------------
    // order_items (05 section 12)
    // ---------------------------------------------------------------------

    private function finalizeOrderItems(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->string('product_name', 150)->nullable()->after('product_id');
        });

        DB::table('order_items')
            ->whereNull('product_name')
            ->update([
                'product_name' => DB::table('products')
                    ->select('name')
                    ->whereColumn('products.id', 'order_items.product_id'),
            ]);

        $unresolvedItems = DB::table('order_items')->whereNull('product_name')->count();

        if ($unresolvedItems > 0) {
            throw new RuntimeException(sprintf(
                '%d baris order_items tidak punya produk rujukan untuk snapshot product_name. '
                .'Backfill manual diperlukan dan data tidak dihapus otomatis.',
                $unresolvedItems,
            ));
        }

        if ($this->supportsStrictNullability()) {
            DB::statement('alter table `order_items` modify `product_name` varchar(150) not null');
        }
    }

    private function finalizeOrderItemsRollback(): void
    {
        $this->renameColumn('order_items', 'line_total', 'subtotal');

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('product_name');
        });
    }

    // ---------------------------------------------------------------------
    // order_status_histories (05 section 13)
    // ---------------------------------------------------------------------

    private function finalizeOrderStatusHistories(): void
    {
        Schema::table('order_status_histories', function (Blueprint $table): void {
            $table->string('note', 500)->nullable();
            $table->timestamp('changed_at')->nullable();
        });

        DB::table('order_status_histories')
            ->whereNull('changed_at')
            ->update(['changed_at' => DB::raw('created_at')]);

        $this->dropForeignKey('order_status_histories', 'changed_by');
        $this->renameColumn('order_status_histories', 'changed_by', 'changed_by_user_id');

        Schema::table('order_status_histories', function (Blueprint $table): void {
            $table->index('to_status');
            $table->index('changed_by_user_id');
            $table->index(['order_id', 'changed_at']);
        });

        $this->addForeignKey('order_status_histories', 'changed_by_user_id', 'users', 'nullOnDelete');
    }

    private function finalizeOrderStatusHistoriesRollback(): void
    {
        $this->dropForeignKey('order_status_histories', 'changed_by_user_id');

        // MySQL memakai index komposit `(order_id, changed_at)` sebagai index
        // pendukung foreign key `order_id` dan membuang index tunggal lama.
        // Index tunggal harus dibuat kembali sebelum index komposit dilepas,
        // kalau tidak MySQL menolak dengan errno 1553.
        $this->ensureExactIndex('order_status_histories', 'order_id');

        Schema::table('order_status_histories', function (Blueprint $table): void {
            $table->dropIndex(['order_id', 'changed_at']);
            $table->dropIndex(['changed_by_user_id']);
            $table->dropIndex(['to_status']);
            $table->dropColumn(['note', 'changed_at']);
        });

        $this->renameColumn('order_status_histories', 'changed_by_user_id', 'changed_by');
        $this->addForeignKey('order_status_histories', 'changed_by', 'users', 'nullOnDelete');
    }

    // ---------------------------------------------------------------------
    // payments (13_DB section 9.2, section 10, section 24, section 25)
    // ---------------------------------------------------------------------

    private function finalizePayments(): void
    {
        $this->renameColumn('payments', 'method', 'payment_method');
        $this->renameColumn('payments', 'status', 'payment_status');
        $this->renameColumn('payments', 'proof_path', 'proof_image');

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
            $table->index('payment_method');
            $table->index('payment_status');
            $table->index('verified_at');
        });

        $this->replaceForeignKey('payments', 'order_id', 'orders', 'restrict');
    }

    private function finalizePaymentsRollback(): void
    {
        $this->replaceForeignKey('payments', 'order_id', 'orders', 'cascade');

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['verified_at']);
            $table->dropIndex(['payment_status']);
            $table->dropIndex(['payment_method']);
            $table->string('idempotency_key', 64)->nullable()->unique();
        });

        $this->renameColumn('payments', 'proof_image', 'proof_path');
        $this->renameColumn('payments', 'payment_status', 'status');
        $this->renameColumn('payments', 'payment_method', 'method');
    }

    // ---------------------------------------------------------------------
    // courier_assignments (13_DB section 24)
    // ---------------------------------------------------------------------

    private function finalizeCourierAssignments(): void
    {
        $this->replaceForeignKey('courier_assignments', 'order_id', 'orders', 'restrict');
    }

    private function finalizeCourierAssignmentsRollback(): void
    {
        $this->replaceForeignKey('courier_assignments', 'order_id', 'orders', 'cascade');
    }

    // ---------------------------------------------------------------------
    // courier_locations (05 section 17 dan 13_DB section 24)
    // ---------------------------------------------------------------------

    private function finalizeCourierLocations(): void
    {
        $this->dropForeignKey('courier_locations', 'assignment_id');
        $this->renameColumn('courier_locations', 'assignment_id', 'courier_assignment_id');

        Schema::table('courier_locations', function (Blueprint $table): void {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });

        $this->addForeignKey('courier_locations', 'courier_assignment_id', 'courier_assignments', 'restrict');
    }

    private function finalizeCourierLocationsRollback(): void
    {
        $this->dropForeignKey('courier_locations', 'courier_assignment_id');

        Schema::table('courier_locations', function (Blueprint $table): void {
            $table->string('idempotency_key', 64)->nullable()->unique();
        });

        $this->renameColumn('courier_locations', 'courier_assignment_id', 'assignment_id');
        $this->addForeignKey('courier_locations', 'assignment_id', 'courier_assignments', 'cascade');
    }

    // ---------------------------------------------------------------------
    // tabel identitas (13_DB section 24, 05 section 9 dan section 20)
    // ---------------------------------------------------------------------

    private function finalizeIdentityTables(): void
    {
        foreach (['customers', 'owners', 'couriers'] as $table) {
            $this->replaceForeignKey($table, 'user_id', 'users', 'restrict');
        }

        // 05 section 9 dan audit P3-06: nomor kurir wajib unik.
        Schema::table('couriers', function (Blueprint $table): void {
            $table->unique('phone');
        });
    }

    private function finalizeIdentityTablesRollback(): void
    {
        Schema::table('couriers', function (Blueprint $table): void {
            $table->dropUnique(['phone']);
        });

        foreach (['customers', 'owners', 'couriers'] as $table) {
            $this->replaceForeignKey($table, 'user_id', 'users', 'cascade');
        }
    }

    // ---------------------------------------------------------------------
    // helper
    // ---------------------------------------------------------------------

    private function replaceForeignKey(string $table, string $column, string $parent, string $onDelete): void
    {
        if ($this->supportsForeignKeyAlteration() === false) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            $blueprint->dropForeign([$column]);
        });

        $this->addForeignKey($table, $column, $parent, $onDelete);
    }

    private function dropForeignKey(string $table, string $column): void
    {
        if ($this->supportsForeignKeyAlteration() === false) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            $blueprint->dropForeign([$column]);
        });
    }

    private function addForeignKey(string $table, string $column, string $parent, string $onDelete): void
    {
        if ($this->supportsForeignKeyAlteration() === false) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $parent, $onDelete): void {
            $foreign = $blueprint->foreign($column);
            $foreign->references('id')->on($parent)->cascadeOnUpdate();

            match ($onDelete) {
                'cascade' => $foreign->cascadeOnDelete(),
                'restrict' => $foreign->restrictOnDelete(),
                default => $foreign->nullOnDelete(),
            };
        });
    }

    /**
     * SQLite tidak dapat menukar delete rule foreign key tanpa rebuild tabel,
     * dan test suite berjalan pada SQLite in-memory. Delete rule final diverifikasi
     * terhadap MySQL, yang merupakan database runtime KYUSUI.
     */
    private function supportsForeignKeyAlteration(): bool
    {
        return DB::getDriverName() !== 'sqlite';
    }

    /**
     * SQLite tidak mendukung `ALTER TABLE ... MODIFY`. Pada database kosong milik
     * test suite kolom tetap nullable, dan kolom itu hanya diisi lewat model.
     */
    private function supportsStrictNullability(): bool
    {
        return DB::getDriverName() !== 'sqlite';
    }

    private function renameColumn(string $table, string $from, string $to): void
    {
        $grammar = DB::connection()->getQueryGrammar();

        DB::statement(sprintf(
            'alter table %s rename column %s to %s',
            $grammar->wrapTable($table),
            $grammar->wrap($from),
            $grammar->wrap($to),
        ));
    }
};
