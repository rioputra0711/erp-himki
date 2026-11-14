<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_descriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jabatan_id')->constrained('jabatans')->restrictOnDelete();
            $table->string('kode', 30)->unique();
            $table->enum('kategori', ['utama', 'umum', 'lintas_bidang', 'insidental']);
            $table->text('uraian');
            $table->text('indikator')->nullable();
            $table->text('target')->nullable();
            $table->enum('frekuensi', ['harian', 'mingguan', 'bulanan', 'tahunan', 'insidental'])->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['jabatan_id', 'kategori']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_descriptions');
    }
};
