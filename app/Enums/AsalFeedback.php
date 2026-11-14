<?php

namespace App\Enums;

enum AsalFeedback: string
{
    case Pengurus = 'pengurus';
    case De = 'de';

    public function label(): string
    {
        return match ($this) {
            self::Pengurus => 'Pengurus',
            self::De => 'Direktur Eksekutif',
        };
    }
}
