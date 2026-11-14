<?php

namespace Database\Factories;

use App\Enums\StatusActionItem;
use App\Models\ActionItem;
use App\Models\Assignment;
use App\Models\Personel;
use App\Support\ServerClock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActionItem>
 */
class ActionItemFactory extends Factory
{
    protected $model = ActionItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'deskripsi' => fake()->sentence(12),
            'pic_id' => Personel::factory(),
            'tenggat' => ServerClock::now()->addDays(7),
            'status' => StatusActionItem::Terbuka,
        ];
    }

    public function selesai(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusActionItem::TerverifikasiSelesai,
            'diverifikasi_oleh' => Personel::factory(),
        ]);
    }
}
