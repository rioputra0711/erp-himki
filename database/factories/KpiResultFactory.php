<?php

namespace Database\Factories;

use App\Enums\StatusKpi;
use App\Models\KpiResult;
use App\Models\Personel;
use App\Support\ServerClock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KpiResult>
 */
class KpiResultFactory extends Factory
{
    protected $model = KpiResult::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'personel_id' => Personel::factory(),
            'periode' => ServerClock::now()->subMonthNoOverflow()->format('Y-m'),
            'skor_pencapaian' => fake()->numberBetween(50, 100),
            'skor_kualitas' => fake()->numberBetween(50, 100),
            'skor_ketepatan_waktu' => fake()->numberBetween(50, 100),
            'skor_tindak_lanjut' => fake()->numberBetween(50, 100),
            'skor_kolaborasi' => fake()->numberBetween(50, 100),
            'status' => StatusKpi::Draft,
            'rule_version' => 'kpi-v1',
        ];
    }

    public function final(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusKpi::Final,
            'dikunci_oleh' => Personel::factory(),
        ]);
    }
}
