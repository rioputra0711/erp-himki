<?php

namespace App\Enums;

enum StatusRisalah: string
{
    case Belum = 'belum';
    case Diajukan = 'diajukan';
    case Terverifikasi = 'terverifikasi';
    case Terlambat = 'terlambat';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
