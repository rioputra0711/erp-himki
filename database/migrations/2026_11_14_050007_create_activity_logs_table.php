<?php

use App\Support\CheckGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personel_id')->constrained('personel')->cascadeOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('assignments')->nullOnDelete();
            $table->foreignId('job_description_id')->nullable()->constrained('job_descriptions')->nullOnDelete();
            $table->date('tanggal');
            $table->text('uraian');
            $table->unsignedTinyInteger('progres')->nullable();
            $table->unsignedSmallInteger('durasi_menit')->nullable();
            $table->text('kendala')->nullable();
            $table->boolean('is_kendala_dini')->default(false);
            $table->dateTime('dicatat_pada');
            $table->timestamps();

            $table->index(['personel_id', 'tanggal']);
            $table->index(['assignment_id', 'tanggal']);
        });

        CheckGuard::add(
            'activity_logs',
            'activity_logs_progres_range',
            'progres IS NULL OR progres BETWEEN 0 AND 100'
        );
        CheckGuard::add(
            'activity_logs',
            'activity_logs_durasi_positif',
            'durasi_menit IS NULL OR durasi_menit > 0'
        );
        CheckGuard::add(
            'activity_logs',
            'activity_logs_sumber_terkait',
            'assignment_id IS NOT NULL OR job_description_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
