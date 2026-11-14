<?php

namespace Database\Seeders;

use App\Models\Bidang;
use Illuminate\Database\Seeder;

/**
 * Bidang kerja Badan Eksekutif HIMKI (fitur.pdf Tahap 3).
 *
 * Daftar ini adalah acuan resmi modul Master Personel: Keuangan, Administrasi,
 * Publikasi, HI/Pameran, TI, Operasional. Bidang lama dari usulan Tahap 1 tidak
 * dihapus (personel historis masih merujuknya) melainkan dinonaktifkan agar
 * tidak lagi muncul pada pilihan baru.
 */
class BidangSeeder extends Seeder
{
    /**
     * @var array<int, array{kode: string, nama: string}>
     */
    private const DAFTAR = [
        ['kode' => 'KEUANGAN', 'nama' => 'Bidang Keuangan'],
        ['kode' => 'ADMINISTRASI', 'nama' => 'Bidang Administrasi'],
        ['kode' => 'PUBLIKASI', 'nama' => 'Bidang Publikasi'],
        ['kode' => 'HI_PAMERAN', 'nama' => 'Bidang Hubungan Internasional & Pameran'],
        ['kode' => 'TI', 'nama' => 'Bidang Teknologi Informasi'],
        ['kode' => 'OPERASIONAL', 'nama' => 'Bidang Operasional'],
    ];

    public function run(): void
    {
        foreach (self::DAFTAR as $bidang) {
            Bidang::query()->updateOrCreate(
                ['kode' => $bidang['kode']],
                ['nama' => $bidang['nama'], 'is_active' => true]
            );
        }

        Bidang::query()
            ->whereNotIn('kode', array_column(self::DAFTAR, 'kode'))
            ->update(['is_active' => false]);
    }
}
