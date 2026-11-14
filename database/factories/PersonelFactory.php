<?php

namespace Database\Factories;

use App\Enums\KategoriPersonel;
use App\Models\Bidang;
use App\Models\Jabatan;
use App\Models\Personel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Personel>
 */
class PersonelFactory extends Factory
{
    protected $model = Personel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $kategori = fake()->randomElement(KategoriPersonel::cases());

        return [
            'user_id' => User::factory(),
            'nip' => fake()->unique()->numerify('NIP#######'),
            'nama_lengkap' => fake()->name(),
            'jabatan_id' => Jabatan::factory()->state(['kelompok' => $kategori]),
            'bidang_id' => Bidang::factory(),
            'kategori' => $kategori,
            'status_aktif' => true,
            'tanggal_masuk' => fake()->dateTimeBetween('-3 years', 'now'),
            'telepon' => fake()->numerify('08##########'),
        ];
    }

    public function nonaktif(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status_aktif' => false,
        ]);
    }
}
