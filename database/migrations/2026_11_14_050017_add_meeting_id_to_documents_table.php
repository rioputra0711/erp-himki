<?php

use App\Support\CheckGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('meeting_id')
                ->nullable()
                ->after('report_id')
                ->constrained('meetings')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('meeting_id');
        });

        // Pembangunan ulang tabel menjatuhkan trigger CHECK; pasang kembali.
        CheckGuard::pulihkanSemua();
    }
};
