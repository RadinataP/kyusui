<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase B — Wave 2 (lanjutan): kolom identitas yang diperlukan oleh
 * `App\Http\Resources\UserResource` (audit P3-12).
 *
 * - `roles.display_name` VARCHAR(100) NOT NULL
 *   (`docs/05_KYUSUI_DATABASE_SCHEMA_REBUILT.md` section 5).
 * - `users.status` VARCHAR(30) NOT NULL DEFAULT 'ACTIVE' dengan INDEX
 *   (`docs/05_KYUSUI_DATABASE_SCHEMA_REBUILT.md` section 6 dan section 20).
 *
 * `users.phone` dan `users.last_login_at` dari spec section 6 TIDAK ditambahkan
 * pada Phase B. `phone` masih hidup pada `customers.phone` dan `couriers.phone`,
 * dan memindahkannya ke `users` akan memaksa registrasi owner/kurir meminta
 * nomor telepon yang tidak diwajibkan `docs/06_KYUSUI_API_SPECIFICATION_REBUILT.md`
 * section 5.1. `last_login_at` belum dipakai kode mana pun. Keduanya dicatat
 * sebagai temuan terbuka pada laporan Phase B.
 */
return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private array $displayNames = [
        'CUSTOMER' => 'Customer',
        'OWNER' => 'Owner',
        'COURIER' => 'Courier',
    ];

    public function up(): void
    {
        if (Schema::hasColumn('roles', 'display_name') === false) {
            Schema::table('roles', function (Blueprint $table): void {
                $table->string('display_name', 100)->nullable()->after('name');
                $table->timestamps();
            });

            foreach ($this->displayNames as $name => $displayName) {
                DB::table('roles')->where('name', $name)->update(['display_name' => $displayName]);
            }

            DB::table('roles')->whereNull('display_name')->update(['display_name' => DB::raw('name')]);

            if (DB::getDriverName() !== 'sqlite') {
                DB::statement('alter table `roles` modify `display_name` varchar(100) not null');
            }
        }

        if (Schema::hasColumn('users', 'status') === false) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('status', 30)->default('ACTIVE')->index()->after('email');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn(['display_name', 'created_at', 'updated_at']);
        });
    }
};
