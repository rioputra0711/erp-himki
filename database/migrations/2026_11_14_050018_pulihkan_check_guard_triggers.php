<?php

use App\Support\CheckGuard;
use Illuminate\Database\Migrations\Migration;

/**
 * Memasang ulang seluruh CHECK trigger SQLite.
 *
 * Migrasi 050017 membangun ulang tabel `documents` untuk menambah foreign key
 * `meeting_id`; SQLite menjatuhkan tabel lama beserta trigger-nya. Migrasi ini
 * harus tetap berada di urutan terakhir agar setiap aturan yang tercatat di
 * `check_guard_rules` kembali aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        CheckGuard::pulihkanSemua();
    }

    public function down(): void
    {
        // Tidak ada yang perlu dibatalkan: trigger dikelola CheckGuard.
    }
};
