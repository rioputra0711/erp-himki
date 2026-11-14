<?php

use App\Support\CheckGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->foreignId('dokumen_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('pemberi_review_id')->constrained('personel')->restrictOnDelete();
            $table->unsignedTinyInteger('skor_kelengkapan');
            $table->unsignedTinyInteger('skor_kejelasan');
            $table->unsignedTinyInteger('skor_akurasi');
            $table->unsignedTinyInteger('skor_sistematika');
            $table->unsignedTinyInteger('skor_tindak_lanjut');
            $table->unsignedTinyInteger('bobot_kelengkapan')->default(20);
            $table->unsignedTinyInteger('bobot_kejelasan')->default(25);
            $table->unsignedTinyInteger('bobot_akurasi')->default(25);
            $table->unsignedTinyInteger('bobot_sistematika')->default(15);
            $table->unsignedTinyInteger('bobot_tindak_lanjut')->default(15);
            $table->decimal('skor_total', 5, 2);
            $table->enum('kategori', [
                'sangat_baik', 'baik', 'cukup', 'perlu_perbaikan', 'belum_memenuhi_standar',
            ]);
            $table->text('catatan_kekuatan')->nullable();
            $table->text('kekurangan_spesifik')->nullable();
            $table->text('rekomendasi_perbaikan')->nullable();
            $table->unsignedSmallInteger('versi_review')->default(1);
            $table->boolean('adalah_final')->default(false);
            $table->dateTime('reviewed_at');
            $table->timestamps();

            $table->unique(['report_id', 'versi_review']);
            $table->index(['pemberi_review_id', 'reviewed_at']);
        });

        foreach (['kelengkapan', 'kejelasan', 'akurasi', 'sistematika', 'tindak_lanjut'] as $komponen) {
            CheckGuard::add(
                'reviews',
                'reviews_skor_'.$komponen,
                "skor_{$komponen} BETWEEN 0 AND 100"
            );
            CheckGuard::add(
                'reviews',
                'reviews_bobot_'.$komponen,
                "bobot_{$komponen} BETWEEN 0 AND 100"
            );
        }

        CheckGuard::add('reviews', 'reviews_skor_total_range', 'skor_total BETWEEN 0 AND 100');
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
