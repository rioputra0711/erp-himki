<?php

namespace Database\Seeders;

use App\Enums\KategoriPersonel;
use App\Models\Jabatan;
use Illuminate\Database\Seeder;

class JabatanSeeder extends Seeder
{
    /**
     * Jabatan baku HIMKI. Kode dipakai sebagai rujukan stabil di kode program.
     *
     * @var array<int, array{kode: string, nama: string, kelompok: KategoriPersonel}>
     */
    private const DAFTAR = [
        ['kode' => 'KETUM', 'nama' => 'Ketua Umum', 'kelompok' => KategoriPersonel::Pengurus],
        ['kode' => 'WAKETUM', 'nama' => 'Wakil Ketua Umum', 'kelompok' => KategoriPersonel::Pengurus],
        ['kode' => 'SEKRETARIS', 'nama' => 'Sekretaris Umum', 'kelompok' => KategoriPersonel::Pengurus],
        ['kode' => 'BENDAHARA', 'nama' => 'Bendahara Umum', 'kelompok' => KategoriPersonel::Pengurus],
        ['kode' => 'DE', 'nama' => 'Direktur Eksekutif', 'kelompok' => KategoriPersonel::DirekturEksekutif],
        ['kode' => 'MANAGER_PROGRAM', 'nama' => 'Manajer Program', 'kelompok' => KategoriPersonel::DirekturEksekutif],
        ['kode' => 'MANAGER_KEUANGAN', 'nama' => 'Manajer Keuangan', 'kelompok' => KategoriPersonel::DirekturEksekutif],
        ['kode' => 'STAF_PROGRAM', 'nama' => 'Staf Program', 'kelompok' => KategoriPersonel::Staf],
        ['kode' => 'STAF_KEUANGAN', 'nama' => 'Staf Keuangan', 'kelompok' => KategoriPersonel::Staf],
        ['kode' => 'STAF_UMUM', 'nama' => 'Staf Umum', 'kelompok' => KategoriPersonel::Staf],
    ];

    public function run(): void
    {
        foreach (self::DAFTAR as $jabatan) {
            Jabatan::query()->updateOrCreate(
                ['kode' => $jabatan['kode']],
                [
                    'nama' => $jabatan['nama'],
                    'kelompok' => $jabatan['kelompok'],
                    'is_active' => true,
                ]
            );
        }
    }
}
