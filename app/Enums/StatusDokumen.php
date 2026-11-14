<?php

namespace App\Enums;

enum StatusDokumen: string
{
    case Draft = 'draft';
    case UntukReview = 'untuk_review';
    case Final = 'final';
    case Digantikan = 'digantikan';
    case Diarsipkan = 'diarsipkan';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::UntukReview => 'Untuk Review',
            self::Final => 'Final',
            self::Digantikan => 'Digantikan Versi Baru',
            self::Diarsipkan => 'Diarsipkan',
        };
    }
}
