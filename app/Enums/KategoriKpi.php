<?php

namespace App\Enums;

enum KategoriKpi: string
{
    case SangatBaik = 'sangat_baik';
    case Baik = 'baik';
    case PerluPeningkatan = 'perlu_peningkatan';
    case Kurang = 'kurang';
    case SangatKurang = 'sangat_kurang';

    public static function dariSkor(float $skor): self
    {
        return match (true) {
            $skor >= 90 => self::SangatBaik,
            $skor >= 80 => self::Baik,
            $skor >= 70 => self::PerluPeningkatan,
            $skor >= 60 => self::Kurang,
            default => self::SangatKurang,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::SangatBaik => 'Sangat Baik',
            self::Baik => 'Baik',
            self::PerluPeningkatan => 'Perlu Peningkatan',
            self::Kurang => 'Kurang',
            self::SangatKurang => 'Sangat Kurang',
        };
    }
}
