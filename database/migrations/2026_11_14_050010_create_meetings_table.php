<?php

use App\Support\CheckGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->string('judul', 200);
            $table->text('agenda');
            $table->string('lokasi', 200)->nullable();
            $table->dateTime('waktu_mulai');
            $table->dateTime('waktu_selesai');
            $table->foreignId('pencatat_id')->constrained('personel')->restrictOnDelete();
            $table->dateTime('tenggat_risalah_singkat');
            $table->dateTime('tenggat_risalah_lengkap');
            $table->enum('status_risalah_singkat', ['belum', 'diajukan', 'terverifikasi', 'terlambat'])->default('belum');
            $table->enum('status_risalah_lengkap', ['belum', 'diajukan', 'terverifikasi', 'terlambat'])->default('belum');
            $table->foreignId('risalah_singkat_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('risalah_lengkap_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();

            $table->index(['waktu_selesai']);
            $table->index(['status_risalah_singkat', 'tenggat_risalah_singkat']);
            $table->index(['status_risalah_lengkap', 'tenggat_risalah_lengkap']);
        });

        CheckGuard::add('meetings', 'meetings_waktu_urut', 'waktu_selesai >= waktu_mulai');
        CheckGuard::add(
            'meetings',
            'meetings_tenggat_singkat_setelah_selesai',
            'tenggat_risalah_singkat > waktu_selesai'
        );
        CheckGuard::add(
            'meetings',
            'meetings_tenggat_lengkap_setelah_singkat',
            'tenggat_risalah_lengkap > tenggat_risalah_singkat'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
