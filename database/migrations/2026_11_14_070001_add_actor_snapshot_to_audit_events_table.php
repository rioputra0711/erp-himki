<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot pelaku pada jejak audit (fitur.pdf Tahap 4).
 *
 * `actor_id` semula `nullOnDelete`, sehingga menghapus akun akan mengubah baris
 * audit lama — pelanggaran append-only lewat pintu belakang. Foreign key diganti
 * `restrictOnDelete` dan identitas pelaku disalin ke baris audit saat peristiwa
 * terjadi, agar jejak tetap utuh meski akun kelak dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_events', function (Blueprint $table) {
            $table->string('actor_nama', 150)->nullable()->after('actor_id');
            $table->string('actor_peran', 20)->nullable()->after('actor_nama');
        });

        Schema::table('audit_events', function (Blueprint $table) {
            $table->dropForeign(['actor_id']);
        });

        Schema::table('audit_events', function (Blueprint $table) {
            $table->foreign('actor_id')->references('id')->on('users')->restrictOnDelete();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('audit_events', function (Blueprint $table) {
            $table->dropForeign(['actor_id']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('audit_events', function (Blueprint $table) {
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('audit_events', function (Blueprint $table) {
            $table->dropColumn(['actor_nama', 'actor_peran']);
        });
    }
};
