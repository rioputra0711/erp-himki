<?php

namespace App\Enums;

/**
 * Dua belas menu aplikasi yang dijaga middleware RBAC (fitur.pdf Tahap 2).
 *
 * Nilai enum sama dengan prefiks izin `modul.aksi` pada PeranIzinSeeder.
 */
enum ModulAplikasi: string
{
    case Penugasan = 'assignment';
    case LogAktivitas = 'activity_log';
    case Laporan = 'report';
    case Dokumen = 'document';
    case Review = 'review';
    case UmpanBalik = 'feedback';
    case TindakLanjut = 'action_item';
    case Kpi = 'kpi';
    case Rapat = 'meeting';
    case Personel = 'personel';
    case Akun = 'user';
    case Audit = 'audit';

    public function label(): string
    {
        return match ($this) {
            self::Penugasan => 'Manajemen Penugasan',
            self::LogAktivitas => 'Log Aktivitas Harian',
            self::Laporan => 'Laporan Kerja',
            self::Dokumen => 'Dokumen & Bukti Kerja',
            self::Review => 'Review & Skor Kualitas',
            self::UmpanBalik => 'Arahan & Umpan Balik',
            self::TindakLanjut => 'Tindak Lanjut',
            self::Kpi => 'KPI & Evaluasi Kinerja',
            self::Rapat => 'Register Rapat',
            self::Personel => 'Master Personel & Jabatan',
            self::Akun => 'Manajemen Akun',
            self::Audit => 'Audit Trail Sistem',
        };
    }

    /**
     * Aksi yang tersedia per menu. `view` = Read, `create`, `update`,
     * `verify` = Verify/Score, `export` = Export (PRD Tahap 2).
     *
     * @return list<AksiIzin>
     */
    public function aksi(): array
    {
        return match ($this) {
            self::Penugasan => [AksiIzin::Lihat, AksiIzin::Buat, AksiIzin::Ubah, AksiIzin::Hapus, AksiIzin::Verifikasi, AksiIzin::Ekspor],
            self::LogAktivitas => [AksiIzin::Lihat, AksiIzin::Buat, AksiIzin::Ubah, AksiIzin::Ekspor],
            self::Laporan => [AksiIzin::Lihat, AksiIzin::Buat, AksiIzin::Ubah, AksiIzin::Ajukan, AksiIzin::Verifikasi, AksiIzin::Ekspor],
            self::Dokumen => [AksiIzin::Lihat, AksiIzin::Buat, AksiIzin::Ubah, AksiIzin::Hapus, AksiIzin::Ekspor],
            self::Review => [AksiIzin::Lihat, AksiIzin::Buat, AksiIzin::Ubah, AksiIzin::Verifikasi, AksiIzin::Ekspor],
            self::UmpanBalik => [AksiIzin::Lihat, AksiIzin::Buat, AksiIzin::Ubah],
            self::TindakLanjut => [AksiIzin::Lihat, AksiIzin::Buat, AksiIzin::Ubah, AksiIzin::Verifikasi, AksiIzin::Ekspor],
            self::Kpi => [AksiIzin::Lihat, AksiIzin::Buat, AksiIzin::Ubah, AksiIzin::Kunci, AksiIzin::Konfigurasi, AksiIzin::Ekspor],
            self::Rapat => [AksiIzin::Lihat, AksiIzin::Buat, AksiIzin::Ubah, AksiIzin::Ekspor],
            self::Personel => [AksiIzin::Lihat, AksiIzin::Buat, AksiIzin::Ubah, AksiIzin::Nonaktifkan, AksiIzin::Konfigurasi, AksiIzin::Ekspor],
            self::Akun => [AksiIzin::Lihat, AksiIzin::Buat, AksiIzin::Ubah, AksiIzin::Nonaktifkan],
            self::Audit => [AksiIzin::Lihat, AksiIzin::Ekspor],
        };
    }

    public function izin(AksiIzin $aksi): string
    {
        return $this->value.'.'.$aksi->value;
    }

    /** @return list<string> */
    public static function semuaNilai(): array
    {
        return array_map(fn (self $modul): string => $modul->value, self::cases());
    }
}
