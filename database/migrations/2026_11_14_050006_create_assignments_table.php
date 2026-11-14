<?php

use App\Support\CheckGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->nullable()->unique();
            $table->string('judul', 200);
            $table->text('deskripsi');
            $table->foreignId('job_description_id')->nullable()->constrained('job_descriptions')->nullOnDelete();
            $table->enum('sumber_perintah', ['de_langsung', 'pengurus_melalui_de', 'pengurus_langsung']);
            $table->foreignId('pemberi_tugas_id')->constrained('personel')->restrictOnDelete();
            $table->foreignId('diteruskan_oleh_id')->nullable()->constrained('personel')->nullOnDelete();
            $table->foreignId('pic_id')->constrained('personel')->restrictOnDelete();
            $table->enum('kategori', ['utama', 'umum', 'lintas_bidang', 'insidental']);
            $table->text('target_keluaran');
            $table->enum('prioritas', ['rendah', 'sedang', 'tinggi', 'mendesak'])->default('sedang');
            $table->date('tanggal_mulai');
            $table->dateTime('tenggat');
            $table->enum('status', [
                'draft', 'aktif', 'dalam_proses', 'terhambat',
                'diajukan_selesai', 'terverifikasi', 'ditutup', 'dibatalkan',
            ])->default('draft');
            $table->unsignedTinyInteger('progres')->default(0);
            $table->text('kendala')->nullable();
            $table->text('alasan_pembatalan')->nullable();
            $table->foreignId('dependensi_id')->nullable()->constrained('assignments')->nullOnDelete();
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('personel')->nullOnDelete();
            $table->dateTime('diverifikasi_pada')->nullable();
            $table->dateTime('ditutup_pada')->nullable();
            $table->timestamps();

            $table->index(['pic_id', 'status']);
            $table->index(['status', 'tenggat']);
        });

        CheckGuard::add('assignments', 'assignments_progres_range', 'progres BETWEEN 0 AND 100');
        CheckGuard::add(
            'assignments',
            'assignments_pembatalan_alasan',
            "status <> 'dibatalkan' OR (alasan_pembatalan IS NOT NULL AND trim(alasan_pembatalan) <> '')"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
