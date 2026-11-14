<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versi & penguncian Job Description (fitur.pdf Tahap 3).
 *
 * Target dan indikator harus ditetapkan sebelum periode penilaian dimulai dan
 * tidak boleh berubah saat pekerjaan berjalan. Karena itu satu baris JD tidak
 * diubah setelah ditetapkan; perubahan dibuat sebagai versi baru dengan
 * `berlaku_mulai` yang lebih baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_descriptions', function (Blueprint $table) {
            $table->dropUnique(['kode']);

            $table->unsignedSmallInteger('versi')->default(1)->after('kode');
            $table->date('berlaku_mulai')->nullable()->after('frekuensi');
            $table->timestamp('ditetapkan_pada')->nullable()->after('berlaku_mulai');
            $table->foreignId('ditetapkan_oleh_id')->nullable()->after('ditetapkan_pada')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('induk_id')->nullable()->after('ditetapkan_oleh_id')
                ->constrained('job_descriptions')->nullOnDelete();

            $table->unique(['jabatan_id', 'kode', 'versi']);
            $table->index(['jabatan_id', 'berlaku_mulai']);
        });
    }

    public function down(): void
    {
        Schema::table('job_descriptions', function (Blueprint $table) {
            $table->dropIndex(['jabatan_id', 'berlaku_mulai']);
            $table->dropUnique(['jabatan_id', 'kode', 'versi']);
            $table->dropConstrainedForeignId('induk_id');
            $table->dropConstrainedForeignId('ditetapkan_oleh_id');
            $table->dropColumn(['versi', 'berlaku_mulai', 'ditetapkan_pada']);

            $table->unique('kode');
        });
    }
};
