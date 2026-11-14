<?php

namespace App\Enums;

/**
 * Aksi otorisasi sesuai PRD Tahap 2: Create, Read, Update, Verify/Score, Export.
 */
enum AksiIzin: string
{
    case Lihat = 'view';
    case Buat = 'create';
    case Ubah = 'update';
    case Hapus = 'delete';
    case Verifikasi = 'verify';
    case Ekspor = 'export';
    case Ajukan = 'submit';
    case Kunci = 'finalize';
    case Nonaktifkan = 'deactivate';
    case Konfigurasi = 'configure';

    public function label(): string
    {
        return match ($this) {
            self::Lihat => 'Read',
            self::Buat => 'Create',
            self::Ubah => 'Update',
            self::Hapus => 'Delete',
            self::Verifikasi => 'Verify / Score',
            self::Ekspor => 'Export',
            self::Ajukan => 'Submit',
            self::Kunci => 'Finalize',
            self::Nonaktifkan => 'Deactivate',
            self::Konfigurasi => 'Configure',
        };
    }
}
