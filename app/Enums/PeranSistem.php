<?php

namespace App\Enums;

/**
 * Kelompok akun sesuai PRD E.1 (matriks hak akses 4 peran).
 */
enum PeranSistem: string
{
    case Pengurus = 'pengurus';
    case DirekturEksekutif = 'de';
    case Staf = 'staf';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Pengurus => 'Pengurus Berwenang',
            self::DirekturEksekutif => 'Direktur Eksekutif',
            self::Staf => 'Personel / Staf BE',
            self::Admin => 'Administrator Aplikasi',
        };
    }

    public function dashboard(): string
    {
        return match ($this) {
            self::Pengurus => 'Dashboard Pengurus',
            self::DirekturEksekutif => 'Dashboard Direktur Eksekutif',
            self::Staf => 'Dashboard Personel BE',
            self::Admin => 'Panel Administrasi',
        };
    }

    /** @return list<self> */
    public static function semua(): array
    {
        return self::cases();
    }
}
