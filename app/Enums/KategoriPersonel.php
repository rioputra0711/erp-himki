<?php

namespace App\Enums;

enum KategoriPersonel: string
{
    case Pengurus = 'pengurus';
    case DirekturEksekutif = 'de';
    case Staf = 'staf';

    public function label(): string
    {
        return match ($this) {
            self::Pengurus => 'Pengurus Berwenang',
            self::DirekturEksekutif => 'Direktur Eksekutif',
            self::Staf => 'Personel / Staf BE',
        };
    }
}
