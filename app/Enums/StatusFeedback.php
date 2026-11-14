<?php

namespace App\Enums;

enum StatusFeedback: string
{
    case Terbuka = 'terbuka';
    case Ditindaklanjuti = 'ditindaklanjuti';
    case Selesai = 'selesai';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
