<?php

namespace Database\Factories;

use App\Models\Personel;
use App\Models\Report;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'pemberi_review_id' => Personel::factory(),
            'skor_kelengkapan' => fake()->numberBetween(60, 100),
            'skor_kejelasan' => fake()->numberBetween(60, 100),
            'skor_akurasi' => fake()->numberBetween(60, 100),
            'skor_sistematika' => fake()->numberBetween(60, 100),
            'skor_tindak_lanjut' => fake()->numberBetween(60, 100),
            'adalah_final' => false,
        ];
    }

    public function final(): static
    {
        return $this->state(fn (array $attributes): array => [
            'adalah_final' => true,
        ]);
    }
}
