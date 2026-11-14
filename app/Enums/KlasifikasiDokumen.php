<?php

namespace App\Enums;

enum KlasifikasiDokumen: string
{
    case Umum = 'umum';
    case InternalBe = 'internal_be';
    case RahasiaKeuangan = 'rahasia_keuangan';

    public function label(): string
    {
        return match ($this) {
            self::Umum => 'Umum',
            self::InternalBe => 'Internal BE',
            self::RahasiaKeuangan => 'Rahasia — Keuangan',
        };
    }
}
