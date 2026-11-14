<?php

namespace App\Enums;

enum KategoriTugas: string
{
    case Utama = 'utama';
    case Umum = 'umum';
    case LintasBidang = 'lintas_bidang';
    case Insidental = 'insidental';

    public function label(): string
    {
        return match ($this) {
            self::Utama => 'Tugas Utama',
            self::Umum => 'Tugas Umum',
            self::LintasBidang => 'Dukungan Lintas Bidang',
            self::Insidental => 'Tugas Insidental',
        };
    }
}
