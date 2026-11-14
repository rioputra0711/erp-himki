<?php

namespace App\Enums;

enum FrekuensiTugas: string
{
    case Harian = 'harian';
    case Mingguan = 'mingguan';
    case Bulanan = 'bulanan';
    case Tahunan = 'tahunan';
    case Insidental = 'insidental';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
