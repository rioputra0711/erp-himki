<?php

use App\Support\CheckGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pemberi_id')->constrained('personel')->restrictOnDelete();
            $table->foreignId('penerima_id')->nullable()->constrained('personel')->nullOnDelete();
            $table->enum('jenis', ['arahan', 'komentar', 'masukan', 'koreksi']);
            $table->enum('asal', ['pengurus', 'de']);
            $table->nullableMorphs('subjek');
            $table->text('isi');
            $table->foreignId('lampiran_dokumen_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->boolean('butuh_tindakan')->default(false);
            $table->enum('status', ['terbuka', 'ditindaklanjuti', 'selesai'])->default('terbuka');
            $table->dateTime('tenggat')->nullable();
            $table->timestamps();

            $table->index(['penerima_id', 'status']);
            $table->index(['pemberi_id', 'created_at']);
        });

        CheckGuard::add(
            'feedback',
            'feedback_penerima_wajib',
            'butuh_tindakan = 0 OR penerima_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
