<?php

namespace Database\Factories;

use App\Enums\KategoriPersonel;
use App\Models\Jabatan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Jabatan>
 */
class JabatanFactory extends Factory
{
    protected $model = Jabatan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kode' => 'JBT-'.fake()->unique()->numerify('####'),
            'nama' => fake()->unique()->jobTitle(),
            'kelompok' => fake()->randomElement(KategoriPersonel::cases()),
            'is_active' => true,
        ];
    }
}
