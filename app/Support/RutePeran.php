<?php

namespace App\Support;

use App\Enums\PeranSistem;
use App\Models\User;

/**
 * Menentukan ruang kerja tujuan setelah login (fitur.pdf Tahap 2 — Auto-Routing Peran).
 *
 * Satu tempat untuk seluruh logika pengarahan, supaya middleware, controller, dan
 * LoginResponse tidak pernah berbeda pendapat soal tujuan per peran.
 */
final class RutePeran
{
    /**
     * Nama route ruang kerja untuk sebuah peran.
     */
    public static function namaRoute(PeranSistem $peran): string
    {
        return $peran->value.'.dashboard';
    }

    /**
     * Path ruang kerja untuk sebuah peran.
     */
    public static function path(PeranSistem $peran): string
    {
        return '/'.$peran->value;
    }

    /**
     * URL ruang kerja pengguna. Null bila akun belum punya peran yang dikenali,
     * sehingga pemanggil dapat menolak login alih-alih mengirim ke dashboard kosong.
     */
    public static function untuk(User $user): ?string
    {
        $peran = $user->peranSistem();

        if ($peran === null) {
            return null;
        }

        return route(self::namaRoute($peran), absolute: false);
    }

    /**
     * Daftar nama route ruang kerja seluruh peran.
     *
     * @return list<string>
     */
    public static function semuaRoute(): array
    {
        return array_map(
            static fn (PeranSistem $peran): string => self::namaRoute($peran),
            PeranSistem::cases(),
        );
    }
}
