<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Menjaga tabel jejak audit tetap append-only.
 *
 * Guard Eloquent saja tidak cukup: perubahan bisa datang dari SQL langsung,
 * seeder, atau alat administrasi database. Karena itu trigger dipasang di
 * level database untuk menolak UPDATE dan DELETE.
 */
final class AppendOnlyGuard
{
    public static function aktifkan(string $table): void
    {
        $driver = DB::connection()->getDriverName();
        $pesan = $table.' bersifat append-only: UPDATE dan DELETE ditolak';

        if ($driver === 'sqlite') {
            DB::unprepared(sprintf(
                "CREATE TRIGGER %s_no_update BEFORE UPDATE ON %s BEGIN SELECT RAISE(ABORT, '%s'); END;",
                $table,
                $table,
                $pesan
            ));
            DB::unprepared(sprintf(
                "CREATE TRIGGER %s_no_delete BEFORE DELETE ON %s BEGIN SELECT RAISE(ABORT, '%s'); END;",
                $table,
                $table,
                $pesan
            ));

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::unprepared(sprintf(
                "CREATE TRIGGER %s_no_update BEFORE UPDATE ON %s FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '%s'",
                $table,
                $table,
                $pesan
            ));
            DB::unprepared(sprintf(
                "CREATE TRIGGER %s_no_delete BEFORE DELETE ON %s FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '%s'",
                $table,
                $table,
                $pesan
            ));

            return;
        }

        if ($driver === 'pgsql') {
            // PostgreSQL: rule INSTEAD NOTHING menolak perubahan tanpa error.
            DB::unprepared(sprintf('CREATE RULE %s_no_update AS ON UPDATE TO %s DO INSTEAD NOTHING', $table, $table));
            DB::unprepared(sprintf('CREATE RULE %s_no_delete AS ON DELETE TO %s DO INSTEAD NOTHING', $table, $table));
        }
    }

    public static function matikan(string $table): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_update");
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_delete");

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_update");
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_delete");

            return;
        }

        if ($driver === 'pgsql') {
            DB::unprepared("DROP RULE IF EXISTS {$table}_no_update ON {$table}");
            DB::unprepared("DROP RULE IF EXISTS {$table}_no_delete ON {$table}");
        }
    }
}
