<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('idempotency_key', 64)->unique()->nullable()->after('status');
            $table->unsignedBigInteger('proof_size')->nullable()->after('proof_path');
            $table->string('proof_mime_type', 100)->nullable()->after('proof_size');
            $table->string('proof_checksum', 64)->nullable()->after('proof_mime_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn([
                'idempotency_key',
                'proof_size',
                'proof_mime_type',
                'proof_checksum',
            ]);
        });
    }
};
