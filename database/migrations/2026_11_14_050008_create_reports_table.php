<?php

use App\Support\CheckGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personel_id')->constrained('personel')->restrictOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('assignments')->nullOnDelete();
            $table->enum('jenis', ['harian', 'mingguan', 'bulanan', 'kegiatan']);
            $table->string('judul', 200);
            $table->date('periode_mulai');
            $table->date('periode_selesai');
            $table->text('ringkasan')->nullable();
            $table->text('narasi_evaluasi')->nullable();
            $table->text('kendala')->nullable();
            $table->text('rencana_tindak_lanjut')->nullable();
            $table->enum('status', [
                'draft', 'diajukan', 'dalam_review', 'perlu_revisi',
                'terverifikasi', 'ditutup', 'dibatalkan',
            ])->default('draft');
            $table->unsignedSmallInteger('versi')->default(1);
            $table->foreignId('induk_id')->nullable()->constrained('reports')->nullOnDelete();
            $table->dateTime('diajukan_pada')->nullable();
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('personel')->nullOnDelete();
            $table->dateTime('diverifikasi_pada')->nullable();
            $table->timestamps();

            $table->unique(
                ['personel_id', 'jenis', 'periode_mulai', 'periode_selesai', 'versi'],
                'reports_periode_versi_unique'
            );
            $table->index(['personel_id', 'jenis', 'periode_mulai']);
            $table->index(['status', 'periode_selesai']);
        });

        CheckGuard::add(
            'reports',
            'reports_periode_urut',
            'periode_selesai >= periode_mulai'
        );
        CheckGuard::add(
            'reports',
            'reports_versi_positif',
            'versi >= 1'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
