<?php

namespace App\Support;

use App\Enums\AksiIzin;
use App\Enums\ModulAplikasi;
use App\Enums\PeranSistem;
use App\Models\Personel;
use App\Models\User;

/**
 * Satu pintu pemeriksaan hak akses tingkat modul dan tingkat baris.
 *
 * Middleware, Policy, dan tampilan semuanya memakai kelas ini, sehingga
 * keputusan "boleh atau tidak" tidak pernah berbeda antar lapisan dan tidak
 * bergantung pada tombol yang tampak di antarmuka.
 */
final class CakupanPengguna
{
    /**
     * Aksi yang dilarang untuk Administrator Aplikasi, apa pun isi tabel izin.
     *
     * Administrator mengurus akun, master personel, dan konfigurasi teknis;
     * penilaian pekerjaan personel tetap milik Pengurus dan DE (fitur.pdf
     * Tahap 2 — "Larang admin menilai pekerjaan").
     *
     * @var list<string>
     */
    public const PENILAIAN_DILARANG_ADMIN = [
        'assignment.verify',
        'report.verify',
        'review.create',
        'review.update',
        'review.verify',
        'feedback.create',
        'feedback.update',
        'action_item.verify',
        'kpi.create',
        'kpi.update',
        'kpi.finalize',
    ];

    public static function peran(User $user): ?PeranSistem
    {
        return $user->peranSistem();
    }

    public static function adalahAdmin(User $user): bool
    {
        return $user->hasRole(PeranSistem::Admin->value);
    }

    public static function adalahStaf(User $user): bool
    {
        return $user->hasRole(PeranSistem::Staf->value);
    }

    public static function adalahDirekturEksekutif(User $user): bool
    {
        return $user->hasRole(PeranSistem::DirekturEksekutif->value);
    }

    /**
     * Id baris `personel` milik akun ini (null bila akun belum tertaut personel).
     */
    public static function personelId(User $user): ?int
    {
        $id = $user->personel?->getKey();

        return $id === null ? null : (int) $id;
    }

    /**
     * Apakah akun boleh melakukan aksi pada modul tertentu.
     */
    public static function boleh(User $user, ModulAplikasi $modul, AksiIzin $aksi): bool
    {
        return self::bolehNamaIzin($user, $modul->izin($aksi));
    }

    /**
     * Pemeriksaan berbasis nama izin `modul.aksi`.
     *
     * Lapisan penolakan admin dijalankan lebih dulu agar tidak bergantung pada
     * urutan pendaftaran Gate::before milik paket permission.
     */
    public static function bolehNamaIzin(User $user, string $izin): bool
    {
        if (self::adalahAdmin($user) && in_array($izin, self::PENILAIAN_DILARANG_ADMIN, true)) {
            return false;
        }

        return $user->can($izin);
    }

    /**
     * Apakah akun boleh menyentuh baris milik personel tertentu.
     *
     * Staf hanya boleh menyentuh barisnya sendiri; peran lain mengikuti izin
     * modul yang sudah lolos di middleware.
     */
    public static function bolehBarisPersonel(User $user, ?int $personelId): bool
    {
        if (! self::adalahStaf($user)) {
            return true;
        }

        return $personelId !== null && $personelId === self::personelId($user);
    }

    /**
     * Personel milik akun, dipakai Policy untuk mencocokkan baris.
     */
    public static function personel(User $user): ?Personel
    {
        return $user->personel;
    }
}
