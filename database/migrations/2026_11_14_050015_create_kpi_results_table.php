<?php

use App\Support\CheckGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personel_id')->constrained('personel')->restrictOnDelete();
            $table->char('periode', 7);
            $table->unsignedTinyInteger('skor_pencapaian');
            $table->unsignedTinyInteger('skor_kualitas');
            $table->unsignedTinyInteger('skor_ketepatan_waktu');
            $table->unsignedTinyInteger('skor_tindak_lanjut');
            $table->unsignedTinyInteger('skor_kolaborasi');
            $table->unsignedTinyInteger('bobot_pencapaian')->default(35);
            $table->unsignedTinyInteger('bobot_kualitas')->default(25);
            $table->unsignedTinyInteger('bobot_ketepatan_waktu')->default(15);
            $table->unsignedTinyInteger('bobot_tindak_lanjut')->default(15);
            $table->unsignedTinyInteger('bobot_kolaborasi')->default(10);
            $table->decimal('skor_total', 5, 2);
            $table->enum('kategori', [
                'sangat_baik', 'baik', 'perlu_peningkatan', 'kurang', 'sangat_kurang',
            ]);
            $table->enum('status', [
                'belum_dihitung', 'data_belum_lengkap', 'draft', 'menunggu_klarifikasi', 'final',
            ])->default('belum_dihitung');
            $table->string('rule_version', 30);
            $table->text('klarifikasi')->nullable();
            $table->foreignId('dihitung_oleh')->nullable()->constrained('personel')->nullOnDelete();
            $table->foreignId('dikunci_oleh')->nullable()->constrained('personel')->nullOnDelete();
            $table->dateTime('dikunci_pada')->nullable();
            $table->timestamps();

            $table->unique(['personel_id', 'periode']);
            $table->index(['periode', 'status']);
        });

        foreach (['pencapaian', 'kualitas', 'ketepatan_waktu', 'tindak_lanjut', 'kolaborasi'] as $komponen) {
            CheckGuard::add(
                'kpi_results',
                'kpi_skor_'.$komponen,
                "skor_{$komponen} BETWEEN 0 AND 100"
            );
            CheckGuard::add(
                'kpi_results',
                'kpi_bobot_'.$komponen,
                "bobot_{$komponen} BETWEEN 0 AND 100"
            );
        }

        CheckGuard::add('kpi_results', 'kpi_skor_total_range', 'skor_total BETWEEN 0 AND 100');
        CheckGuard::add(
            'kpi_results',
            'kpi_bobot_total',
            'bobot_pencapaian + bobot_kualitas + bobot_ketepatan_waktu + bobot_tindak_lanjut + bobot_kolaborasi = 100'
        );
        CheckGuard::add(
            'kpi_results',
            'kpi_periode_format',
            "length(periode) = 7 AND substr(periode, 5, 1) = '-'"
        );
        CheckGuard::add(
            'kpi_results',
            'kpi_final_terkunci',
            "status <> 'final' OR (dikunci_oleh IS NOT NULL AND dikunci_pada IS NOT NULL)"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_results');
    }
};
