<?php

use App\Support\CheckGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Skema Tahap 5 — penugasan, arahan, tindak lanjut, dan notifikasi.
 *
 * Tiga kelompok perubahan:
 *
 * 1. `assignments`: jejak arahan asal, alasan + bukti penutupan, dan bukti
 *    pembatalan. Bukti berupa rujukan teks wajib (berkas fisik/berita acara)
 *    dengan tautan dokumen opsional, karena layar unggah dokumen baru ada
 *    pada tahap berikutnya.
 * 2. `feedback`: jabatan pemberi direkam saat arahan dibuat, dan `jenis`
 *    diperluas dengan `persetujuan` — baris persetujuan formal selalu baru
 *    dan hanya boleh berasal dari Pengurus.
 * 3. `action_items` + `notifications`: bukti penutupan item, dan tabel
 *    notifikasi bawaan Laravel agar status baca per akun dapat disimpan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table): void {
            $table->foreignId('sumber_arahan_id')->nullable()->after('job_description_id')
                ->constrained('feedback')->nullOnDelete();
            $table->text('alasan_penutupan')->nullable()->after('alasan_pembatalan');
            $table->text('bukti_penutupan')->nullable()->after('alasan_penutupan');
            $table->text('bukti_pembatalan')->nullable()->after('bukti_penutupan');
            $table->foreignId('bukti_dokumen_id')->nullable()->after('bukti_pembatalan')
                ->constrained('documents')->restrictOnDelete();
        });

        CheckGuard::add(
            'assignments',
            'assignments_penutupan_lengkap',
            "status <> 'ditutup' OR (alasan_penutupan IS NOT NULL AND trim(alasan_penutupan) <> ''"
            ." AND bukti_penutupan IS NOT NULL AND trim(bukti_penutupan) <> '')"
        );

        CheckGuard::add(
            'assignments',
            'assignments_pembatalan_bukti',
            "status <> 'dibatalkan' OR (bukti_pembatalan IS NOT NULL AND trim(bukti_pembatalan) <> '')"
        );

        Schema::table('feedback', function (Blueprint $table): void {
            $table->string('pemberi_jabatan', 100)->nullable()->after('pemberi_id');
        });

        $this->ubahJenisFeedback([
            'arahan', 'komentar', 'masukan', 'koreksi', 'persetujuan',
        ]);

        CheckGuard::add(
            'feedback',
            'feedback_persetujuan_pengurus',
            "jenis <> 'persetujuan' OR asal = 'pengurus'"
        );

        Schema::table('action_items', function (Blueprint $table): void {
            $table->text('bukti_penutupan')->nullable()->after('selesai_pada');
        });

        CheckGuard::add(
            'action_items',
            'action_items_penutupan_bukti',
            "status <> 'ditutup' OR (bukti_penutupan IS NOT NULL AND trim(bukti_penutupan) <> '')"
        );

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });

        // Migrasi ini berjalan setelah 050018; ALTER pada SQLite dapat
        // menjatuhkan trigger, jadi seluruh aturan dipasang ulang di sini.
        CheckGuard::pulihkanSemua();
    }

    public function down(): void
    {
        CheckGuard::drop('action_items', 'action_items_penutupan_bukti');
        CheckGuard::drop('feedback', 'feedback_persetujuan_pengurus');
        CheckGuard::drop('assignments', 'assignments_pembatalan_bukti');
        CheckGuard::drop('assignments', 'assignments_penutupan_lengkap');

        Schema::dropIfExists('notifications');

        Schema::table('action_items', function (Blueprint $table): void {
            $table->dropColumn('bukti_penutupan');
        });

        // Baris `persetujuan` yang sudah ada akan menggagalkan penyempitan
        // enum; itu disengaja — rollback skema tidak boleh diam-diam
        // menghapus keputusan formal yang sudah tercatat.
        $this->ubahJenisFeedback(['arahan', 'komentar', 'masukan', 'koreksi']);

        Schema::table('feedback', function (Blueprint $table): void {
            $table->dropColumn('pemberi_jabatan');
        });

        Schema::table('assignments', function (Blueprint $table): void {
            // MySQL menolak DROP COLUMN selama foreign key-nya masih ada,
            // jadi setiap kolom ber-FK dilepas lewat dropConstrainedForeignId.
            $table->dropConstrainedForeignId('bukti_dokumen_id');
            $table->dropConstrainedForeignId('sumber_arahan_id');
        });

        Schema::table('assignments', function (Blueprint $table): void {
            $table->dropColumn(['alasan_penutupan', 'bukti_penutupan', 'bukti_pembatalan']);
        });
    }

    /**
     * @param  list<string>  $nilai
     */
    private function ubahJenisFeedback(array $nilai): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $daftar = implode(',', array_map(static fn (string $satu): string => "'{$satu}'", $nilai));

            DB::statement("ALTER TABLE feedback MODIFY jenis ENUM({$daftar}) NOT NULL");

            return;
        }

        Schema::table('feedback', function (Blueprint $table) use ($nilai): void {
            $table->enum('jenis', $nilai)->change();
        });
    }
};
