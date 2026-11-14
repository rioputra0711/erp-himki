<?php

namespace App\Enums;

enum StatusLaporan: string
{
    case Draft = 'draft';
    case Diajukan = 'diajukan';
    case DalamReview = 'dalam_review';
    case PerluRevisi = 'perlu_revisi';
    case Terverifikasi = 'terverifikasi';
    case Ditutup = 'ditutup';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Diajukan => 'Diajukan',
            self::DalamReview => 'Dalam Review',
            self::PerluRevisi => 'Perlu Revisi',
            self::Terverifikasi => 'Terverifikasi',
            self::Ditutup => 'Ditutup',
            self::Dibatalkan => 'Dibatalkan',
        };
    }
}
