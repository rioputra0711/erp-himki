<?php

namespace App\Enums;

enum StatusPenugasan: string
{
    case Draft = 'draft';
    case Aktif = 'aktif';
    case DalamProses = 'dalam_proses';
    case Terhambat = 'terhambat';
    case DiajukanSelesai = 'diajukan_selesai';
    case Terverifikasi = 'terverifikasi';
    case Ditutup = 'ditutup';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Aktif => 'Aktif',
            self::DalamProses => 'Dalam Proses',
            self::Terhambat => 'Terhambat',
            self::DiajukanSelesai => 'Diajukan Selesai',
            self::Terverifikasi => 'Terverifikasi',
            self::Ditutup => 'Ditutup',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Terverifikasi, self::Ditutup, self::Dibatalkan], true);
    }

    /**
     * Peta transisi yang sah. `Terverifikasi` dan `Ditutup` sengaja hanya
     * dapat dicapai lewat jalur pemeriksaan, bukan langsung dari status kerja.
     *
     * @return list<self>
     */
    public function bolehKe(self $tujuan): bool
    {
        return in_array($tujuan, match ($this) {
            self::Draft => [self::Aktif, self::Dibatalkan],
            self::Aktif => [self::DalamProses, self::Terhambat, self::Dibatalkan],
            self::DalamProses => [self::Terhambat, self::DiajukanSelesai, self::Dibatalkan],
            self::Terhambat => [self::DalamProses, self::DiajukanSelesai, self::Dibatalkan],
            self::DiajukanSelesai => [self::DalamProses, self::Terverifikasi, self::Dibatalkan],
            self::Terverifikasi => [self::Ditutup],
            self::Ditutup, self::Dibatalkan => [],
        }, true);
    }
}
