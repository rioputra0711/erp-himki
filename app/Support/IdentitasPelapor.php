<?php

namespace App\Support;

use App\Models\Personel;
use App\Models\User;

/**
 * Kunci Identitas Otomatis (fitur.pdf Tahap 3).
 *
 * Nama dan jabatan pada setiap laporan berasal dari Master Personel yang
 * tertaut ke akun sesi, bukan dari isian pengirim. Kelas ini menjadi satu-
 * satunya sumber identitas pelapor sehingga formulir tidak perlu meminta
 * pengguna mengetik ulang nama/jabatan yang bisa dipalsukan.
 */
final class IdentitasPelapor
{
    /**
     * Personel milik akun yang sedang login, apa pun perannya.
     */
    public static function dariSesi(?User $user = null): ?Personel
    {
        $user ??= auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        return $user->personel?->loadMissing(['jabatan', 'bidang']);
    }

    /**
     * Identitas siap tampil untuk formulir dan laporan.
     *
     * @return array{personel_id: ?int, nama: ?string, jabatan: ?string, bidang: ?string}|null
     */
    public static function ringkas(?User $user = null): ?array
    {
        $personel = self::dariSesi($user);

        if ($personel === null) {
            return null;
        }

        return [
            'personel_id' => (int) $personel->getKey(),
            'nama' => $personel->nama_lengkap,
            'jabatan' => $personel->jabatan?->nama,
            'bidang' => $personel->bidang?->nama,
        ];
    }

    /**
     * Apakah akun boleh menulis atas nama personel tertentu.
     *
     * Hanya identitas milik sesi sendiri yang boleh dipakai untuk menandatangani
     * laporan; peran lain yang perlu mencatat atas nama orang lain (mis. verifikator)
     * memakai kolom teknis terpisah (`diverifikasi_oleh`), bukan identitas pelapor.
     */
    public static function cocokDenganSesi(?int $personelId, ?User $user = null): bool
    {
        $id = self::dariSesi($user)?->getKey();

        return $id !== null && $personelId !== null && (int) $id === (int) $personelId;
    }
}
