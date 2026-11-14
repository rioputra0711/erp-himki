<?php

namespace App\Enums;

enum JenisLaporan: string
{
    case Harian = 'harian';
    case Mingguan = 'mingguan';
    case Bulanan = 'bulanan';
    case Kegiatan = 'kegiatan';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
