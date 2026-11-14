<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan CHECK constraint portabel.
 *
 * MySQL/MariaDB dan PostgreSQL mendukung CHECK sejak lama; SQLite baru
 * menegakkannya bila tabel dibuat dengan constraint tersebut, sehingga untuk
 * SQLite dipakai trigger RAISE(ABORT) agar aturan tetap berlaku walau data
 * diubah lewat SQL langsung (bukan hanya lewat Eloquent).
 *
 * Dua jebakan SQLite ditangani di sini:
 *
 * 1. Saat tabel lain di-ALTER, SQLite mem-parse ulang seluruh trigger.
 *    Referensi kolom tanpa kualifikasi membuat proses itu gagal
 *    ("no such column"), jadi setiap kolom diberi awalan `NEW.`.
 * 2. Saat tabel yang di-ALTER dibangun ulang (misalnya untuk menambah foreign
 *    key), SQLite menjatuhkan tabel lama beserta seluruh trigger-nya. Karena
 *    itu setiap aturan dicatat di `check_guard_rules` agar dapat dipasang
 *    kembali lewat `pulihkanSemua()` pada akhir rangkaian migrasi.
 */
final class CheckGuard
{
    private const TABEL_ATURAN = 'check_guard_rules';

    /**
     * @param  string  $table  nama tabel tanpa prefix koneksi
     * @param  string  $name  nama constraint unik
     * @param  string  $expression  ekspresi SQL, contoh: "progres BETWEEN 0 AND 100"
     */
    public static function add(string $table, string $name, string $expression): void
    {
        if (! self::memakaiTrigger()) {
            DB::statement(sprintf('ALTER TABLE %s ADD CONSTRAINT %s CHECK (%s)', $table, $name, $expression));

            return;
        }

        self::catat($table, $name, $expression);
        self::pasang($table, $name, $expression);
    }

    /**
     * Melepas satu aturan. Dipakai `down()` migrasi agar rollback tidak
     * meninggalkan CHECK yang menunjuk kolom yang sudah dihapus.
     */
    public static function drop(string $table, string $name): void
    {
        if (self::memakaiTrigger()) {
            self::lepas($table, $name);

            if (Schema::hasTable(self::TABEL_ATURAN)) {
                DB::table(self::TABEL_ATURAN)
                    ->where('table_name', $table)
                    ->where('name', $name)
                    ->delete();
            }

            return;
        }

        DB::statement(sprintf('ALTER TABLE %s DROP CONSTRAINT %s', $table, $name));
    }

    /**
     * Memasang ulang seluruh aturan yang tercatat. Dipanggil migrasi terakhir
     * agar trigger yang hilang akibat pembangunan ulang tabel kembali aktif.
     */
    public static function pulihkanSemua(): void
    {
        if (! self::memakaiTrigger() || ! Schema::hasTable(self::TABEL_ATURAN)) {
            return;
        }

        $aturan = DB::table(self::TABEL_ATURAN)->orderBy('id')->get();

        foreach ($aturan as $satu) {
            self::lepas((string) $satu->table_name, (string) $satu->name);
            self::pasang((string) $satu->table_name, (string) $satu->name, (string) $satu->expression);
        }
    }

    private static function memakaiTrigger(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }

    private static function catat(string $table, string $name, string $expression): void
    {
        if (! Schema::hasTable(self::TABEL_ATURAN)) {
            Schema::create(self::TABEL_ATURAN, function ($blueprint): void {
                $blueprint->id();
                $blueprint->string('table_name', 100);
                $blueprint->string('name', 100);
                $blueprint->text('expression');
                $blueprint->timestamps();

                $blueprint->unique(['table_name', 'name']);
            });
        }

        DB::table(self::TABEL_ATURAN)->updateOrInsert(
            ['table_name' => $table, 'name' => $name],
            [
                'expression' => $expression,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private static function pasang(string $table, string $name, string $expression): void
    {
        $prefix = 'trg_'.$table.'_'.$name;
        $ekspresi = self::kualifikasiKolom($table, $expression);

        DB::unprepared(sprintf(
            'CREATE TRIGGER %s_insert BEFORE INSERT ON %s FOR EACH ROW WHEN NOT (%s) BEGIN SELECT RAISE(ABORT, \'%s\'); END;',
            $prefix,
            $table,
            $ekspresi,
            self::pesan($name)
        ));

        DB::unprepared(sprintf(
            'CREATE TRIGGER %s_update BEFORE UPDATE ON %s FOR EACH ROW WHEN NOT (%s) BEGIN SELECT RAISE(ABORT, \'%s\'); END;',
            $prefix,
            $table,
            $ekspresi,
            self::pesan($name)
        ));
    }

    private static function lepas(string $table, string $name): void
    {
        $prefix = 'trg_'.$table.'_'.$name;

        DB::unprepared('DROP TRIGGER IF EXISTS '.$prefix.'_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS '.$prefix.'_update');
    }

    /**
     * Memberi awalan `NEW.` pada setiap nama kolom tabel agar ekspresi tetap
     * valid saat SQLite mem-parse ulang trigger.
     */
    private static function kualifikasiKolom(string $table, string $expression): string
    {
        $kolom = array_map(
            static fn (string $nama): string => strtolower($nama),
            Schema::getColumnListing($table)
        );

        if ($kolom === []) {
            return $expression;
        }

        // Pisahkan bagian di dalam tanda kutip agar literal string tidak diubah.
        $bagian = preg_split("/(\'[^\']*\')/", $expression, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$expression];

        foreach ($bagian as $i => $potongan) {
            if ($potongan === '' || $potongan[0] === "'") {
                continue;
            }

            $bagian[$i] = preg_replace_callback(
                '/(?<![\w.])([A-Za-z_][A-Za-z0-9_]*)\b(?!\s*\()/',
                static function (array $cocok) use ($kolom): string {
                    return in_array(strtolower($cocok[1]), $kolom, true)
                        ? 'NEW.'.$cocok[1]
                        : $cocok[1];
                },
                $potongan
            ) ?? $potongan;
        }

        return implode('', $bagian);
    }

    private static function pesan(string $name): string
    {
        return 'Ditolak oleh aturan '.$name;
    }
}
