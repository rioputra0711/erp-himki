<?php

namespace App\Enums;

enum KategoriSkor: string
{
    case SangatBaik = 'sangat_baik';
    case Baik = 'baik';
    case Cukup = 'cukup';
    case PerluPerbaikan = 'perlu_perbaikan';
    case BelumMemenuhiStandar = 'belum_memenuhi_standar';

    public static function dariSkor(float $skor): self
    {
        return match (true) {
            $skor >= 90 => self::SangatBaik,
            $skor >= 80 => self::Baik,
            $skor >= 70 => self::Cukup,
            $skor >= 60 => self::PerluPerbaikan,
            default => self::BelumMemenuhiStandar,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::SangatBaik => 'Sangat Baik',
            self::Baik => 'Baik',
            self::Cukup => 'Cukup',
            self::PerluPerbaikan => 'Perlu Perbaikan',
            self::BelumMemenuhiStandar => 'Belum Memenuhi Standar',
        };
    }
}
