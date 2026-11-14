<?php

use App\Support\CheckGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('judul', 200);
            $table->enum('kategori', ['surat', 'laporan', 'matriks', 'foto', 'risalah', 'lainnya']);
            $table->unsignedSmallInteger('versi')->default(1);
            $table->foreignId('induk_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('pemilik_id')->constrained('personel')->restrictOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('assignments')->nullOnDelete();
            $table->foreignId('report_id')->nullable()->constrained('reports')->nullOnDelete();
            $table->string('file_path', 500);
            $table->string('file_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->char('checksum', 64)->nullable();
            $table->enum('klasifikasi', ['umum', 'internal_be', 'rahasia_keuangan'])->default('internal_be');
            $table->enum('status', ['draft', 'untuk_review', 'final', 'digantikan', 'diarsipkan'])->default('draft');
            $table->foreignId('diunggah_oleh')->constrained('personel')->restrictOnDelete();
            $table->timestamps();

            $table->unique('file_path');
            $table->index(['kategori', 'status']);
            $table->index(['klasifikasi', 'pemilik_id']);
        });

        CheckGuard::add('documents', 'documents_file_size_positif', 'file_size > 0');
        CheckGuard::add('documents', 'documents_versi_positif', 'versi >= 1');
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
