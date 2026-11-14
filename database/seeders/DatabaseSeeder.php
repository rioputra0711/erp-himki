<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Urutan seeding mengikuti dependensi foreign key:
     * referensi (jabatan, bidang) → peran & izin → data demo.
     */
    public function run(): void
    {
        $this->call([
            JabatanSeeder::class,
            BidangSeeder::class,
            PeranIzinSeeder::class,
            DemoSeeder::class,
        ]);
    }
}
