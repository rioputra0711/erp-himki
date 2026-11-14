<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('nip', 30)->nullable()->unique();
            $table->string('nama_lengkap', 150);
            $table->foreignId('jabatan_id')->constrained('jabatans')->restrictOnDelete();
            $table->foreignId('bidang_id')->nullable()->constrained('bidangs')->restrictOnDelete();
            $table->enum('kategori', ['pengurus', 'de', 'staf']);
            $table->boolean('status_aktif')->default(true);
            $table->date('tanggal_masuk')->nullable();
            $table->string('telepon', 30)->nullable();
            $table->timestamps();

            $table->index(['kategori', 'status_aktif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personel');
    }
};
