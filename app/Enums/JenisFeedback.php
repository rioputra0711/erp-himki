<?php

namespace App\Enums;

enum JenisFeedback: string
{
    case Arahan = 'arahan';
    case Komentar = 'komentar';
    case Masukan = 'masukan';
    case Koreksi = 'koreksi';
    case Persetujuan = 'persetujuan';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
