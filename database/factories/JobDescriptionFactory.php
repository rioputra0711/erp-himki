<?php

namespace Database\Factories;

use App\Enums\FrekuensiTugas;
use App\Enums\KategoriTugas;
use App\Models\Jabatan;
use App\Models\JobDescription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

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
            'versi' => 1,
            'kategori' => fake()->randomElement(KategoriTugas::cases()),
            'uraian' => fake()->sentence(10),
            'indikator' => fake()->sentence(8),
            'target' => fake()->sentence(6),
            'frekuensi' => fake()->randomElement(FrekuensiTugas::cases()),
            'berlaku_mulai' => null,
            'ditetapkan_pada' => null,
            'ditetapkan_oleh_id' => null,
            'induk_id' => null,
            'is_active' => true,
        ];
    }

    /**
     * Job Description yang sudah ditetapkan (terkunci) dan berlaku sejak tanggal tertentu.
     */
    public function ditetapkan(string $berlakuMulai = '-1 month'): static
    {
        return $this->state(function (array $attributes) use ($berlakuMulai): array {
            $tanggal = Carbon::parse($berlakuMulai);

            return [
                'berlaku_mulai' => $tanggal->toDateString(),
                'ditetapkan_pada' => $tanggal,
                'frekuensi' => $attributes['frekuensi'] ?? FrekuensiTugas::Bulanan,
            ];
        });
    }
}
