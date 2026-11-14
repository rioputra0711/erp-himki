<?php

use App\Support\CheckGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('action_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_id')->nullable()->constrained('feedback')->nullOnDelete();
            $table->foreignId('review_id')->nullable()->constrained('reviews')->nullOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('assignments')->nullOnDelete();
            $table->foreignId('report_id')->nullable()->constrained('reports')->nullOnDelete();
            $table->text('deskripsi');
            $table->foreignId('pic_id')->constrained('personel')->restrictOnDelete();
            $table->dateTime('tenggat')->nullable();
            $table->enum('status', [
                'terbuka', 'dalam_proses', 'menunggu_pihak_lain',
                'diajukan_selesai', 'terverifikasi_selesai', 'ditutup',
            ])->default('terbuka');
            $table->dateTime('selesai_pada')->nullable();
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('personel')->nullOnDelete();
            $table->timestamps();

            $table->index(['pic_id', 'status']);
            $table->index(['status', 'tenggat']);
        });

        CheckGuard::add(
            'action_items',
            'action_items_sumber_wajib',
            'feedback_id IS NOT NULL OR review_id IS NOT NULL OR assignment_id IS NOT NULL OR report_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('action_items');
    }
};
