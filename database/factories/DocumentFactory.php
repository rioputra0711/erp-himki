<?php

namespace Database\Factories;

use App\Enums\KategoriDokumen;
use App\Enums\KlasifikasiDokumen;
use App\Enums\StatusDokumen;
use App\Models\Document;
use App\Models\Personel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nama = Str::slug(fake()->sentence(3)).'.pdf';

        return [
            'judul' => fake()->sentence(4),
            'kategori' => fake()->randomElement(KategoriDokumen::cases()),
            'versi' => 1,
            'pemilik_id' => Personel::factory(),
            'file_path' => 'dokumen/'.fake()->unique()->uuid().'/'.$nama,
            'file_name' => $nama,
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(1024, 5_000_000),
            'checksum' => hash('sha256', fake()->uuid()),
            'klasifikasi' => KlasifikasiDokumen::InternalBe,
            'status' => StatusDokumen::Draft,
            'diunggah_oleh' => Personel::factory(),
        ];
    }
}
