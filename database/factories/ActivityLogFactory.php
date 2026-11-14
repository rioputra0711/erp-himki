<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\Assignment;
use App\Models\Personel;
use App\Support\ServerClock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'personel_id' => Personel::factory(),
            'assignment_id' => Assignment::factory(),
            'tanggal' => ServerClock::now()->toDateString(),
            'uraian' => fake()->sentence(12),
            'progres' => fake()->numberBetween(0, 100),
            'durasi_menit' => fake()->numberBetween(15, 480),
            'is_kendala_dini' => false,
        ];
    }
}
