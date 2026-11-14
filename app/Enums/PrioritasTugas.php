<?php

namespace App\Enums;

enum PrioritasTugas: string
{
    case Rendah = 'rendah';
    case Sedang = 'sedang';
    case Tinggi = 'tinggi';
    case Mendesak = 'mendesak';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
