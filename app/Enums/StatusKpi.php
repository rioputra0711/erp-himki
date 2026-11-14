<?php

namespace App\Enums;

enum StatusKpi: string
{
    case BelumDihitung = 'belum_dihitung';
    case DataBelumLengkap = 'data_belum_lengkap';
    case Draft = 'draft';
    case MenungguKlarifikasi = 'menunggu_klarifikasi';
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::BelumDihitung => 'Belum Dihitung',
            self::DataBelumLengkap => 'Data Belum Lengkap',
            self::Draft => 'Draft',
            self::MenungguKlarifikasi => 'Menunggu Klarifikasi',
            self::Final => 'Final',
        };
    }
}
