<?php

namespace Database\Factories;

use App\Enums\AsalFeedback;
use App\Enums\JenisFeedback;
use App\Enums\StatusFeedback;
use App\Models\Assignment;
use App\Models\Feedback;
use App\Models\Personel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feedback>
 */
class FeedbackFactory extends Factory
{
    protected $model = Feedback::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pemberi_id' => Personel::factory(),
            'penerima_id' => Personel::factory(),
            'jenis' => fake()->randomElement(JenisFeedback::cases()),
            'asal' => fake()->randomElement(AsalFeedback::cases()),
            'subjek_type' => (new Assignment)->getMorphClass(),
            'subjek_id' => Assignment::factory(),
            'isi' => fake()->paragraph(),
            'butuh_tindakan' => false,
            'status' => StatusFeedback::Terbuka,
        ];
    }

    public function butuhTindakan(): static
    {
        return $this->state(fn (array $attributes): array => [
            'butuh_tindakan' => true,
            'tenggat' => now()->addDays(3),
        ]);
    }
}
