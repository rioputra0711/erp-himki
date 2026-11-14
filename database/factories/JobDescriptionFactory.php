<?php

namespace Database\Factories;

use App\Enums\FrekuensiTugas;
use App\Enums\KategoriTugas;
use App\Models\Jabatan;
use App\Models\JobDescription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobDescription>
 */
class JobDescriptionFactory extends Factory
{
    protected $model = JobDescription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'jabatan_id' => Jabatan::factory(),
            'kode' => 'JD-'.fake()->unique()->numerify('####'),
            'kategori' => fake()->randomElement(KategoriTugas::cases()),
            'uraian' => fake()->sentence(10),
            'indikator' => fake()->sentence(8),
            'target' => fake()->sentence(6),
            'frekuensi' => fake()->randomElement(FrekuensiTugas::cases()),
            'is_active' => true,
        ];
    }
}
