<?php

namespace Database\Factories;

use App\Enums\KategoriTugas;
use App\Enums\PrioritasTugas;
use App\Enums\StatusPenugasan;
use App\Enums\SumberPerintah;
use App\Models\Assignment;
use App\Models\JobDescription;
use App\Models\Personel;
use App\Support\ServerClock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    protected $model = Assignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $mulai = ServerClock::now()->subDays(fake()->numberBetween(1, 20));

        return [
            'judul' => fake()->sentence(5),
            'deskripsi' => fake()->paragraph(),
            'job_description_id' => JobDescription::factory(),
            'sumber_perintah' => fake()->randomElement(SumberPerintah::cases()),
            'pemberi_tugas_id' => Personel::factory(),
            'pic_id' => Personel::factory(),
            'kategori' => fake()->randomElement(KategoriTugas::cases()),
            'target_keluaran' => fake()->sentence(6),
            'prioritas' => fake()->randomElement(PrioritasTugas::cases()),
            'tanggal_mulai' => $mulai->toDateString(),
            'tenggat' => $mulai->copy()->addDays(fake()->numberBetween(3, 30)),
            'status' => StatusPenugasan::Aktif,
            'progres' => fake()->numberBetween(0, 100),
        ];
    }

    public function dibatalkan(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusPenugasan::Dibatalkan,
            'alasan_pembatalan' => fake()->sentence(8),
        ]);
    }
}
