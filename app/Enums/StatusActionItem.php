<?php

namespace App\Enums;

enum StatusActionItem: string
{
    case Terbuka = 'terbuka';
    case DalamProses = 'dalam_proses';
    case MenungguPihakLain = 'menunggu_pihak_lain';
    case DiajukanSelesai = 'diajukan_selesai';
    case TerverifikasiSelesai = 'terverifikasi_selesai';
    case Ditutup = 'ditutup';

    public function label(): string
    {
        return match ($this) {
            self::Terbuka => 'Terbuka',
            self::DalamProses => 'Dalam Proses',
            self::MenungguPihakLain => 'Menunggu Pihak Lain',
            self::DiajukanSelesai => 'Diajukan Selesai',
            self::TerverifikasiSelesai => 'Terverifikasi Selesai',
            self::Ditutup => 'Ditutup',
        };
    }

    /** Item di luar dua status ini masih dihitung sebagai tunggakan aktif. */
    public function isTunggakan(): bool
    {
        return ! in_array($this, [self::TerverifikasiSelesai, self::Ditutup], true);
    }
}
