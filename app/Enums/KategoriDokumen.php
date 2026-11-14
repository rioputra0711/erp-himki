<?php

namespace App\Enums;

enum KategoriDokumen: string
{
    case Surat = 'surat';
    case Laporan = 'laporan';
    case Matriks = 'matriks';
    case Foto = 'foto';
    case Risalah = 'risalah';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
