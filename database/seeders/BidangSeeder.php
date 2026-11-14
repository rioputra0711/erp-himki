<?php

namespace Database\Seeders;

use App\Models\Bidang;
use Illuminate\Database\Seeder;

class BidangSeeder extends Seeder
{
    /**
     * Bidang kerja di lingkungan Badan Eksekutif HIMKI.
     *
     * @var array<int, array{kode: string, nama: string}>
     */
    private const DAFTAR = [
        ['kode' => 'PROGRAM', 'nama' => 'Bidang Program dan Kegiatan'],
        ['kode' => 'KEUANGAN', 'nama' => 'Bidang Keuangan'],
        ['kode' => 'SDM', 'nama' => 'Bidang Sumber Daya Manusia'],
        ['kode' => 'HUMAS', 'nama' => 'Bidang Hubungan Masyarakat'],
        ['kode' => 'UMUM', 'nama' => 'Bidang Umum dan Kesekretariatan'],
    ];

    public function run(): void
    {
        foreach (self::DAFTAR as $bidang) {
            Bidang::query()->updateOrCreate(
                ['kode' => $bidang['kode']],
                ['nama' => $bidang['nama'], 'is_active' => true]
            );
        }
    }
}
