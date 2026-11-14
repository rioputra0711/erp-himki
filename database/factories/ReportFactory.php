<?php

namespace Database\Factories;

use App\Enums\JenisLaporan;
use App\Enums\StatusLaporan;
use App\Models\Assignment;
use App\Models\Personel;
use App\Models\Report;
use App\Support\ServerClock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $mulai = ServerClock::now()->subDays(7)->startOfDay();

        return [
            'personel_id' => Personel::factory(),
            'assignment_id' => Assignment::factory(),
            'jenis' => fake()->randomElement(JenisLaporan::cases()),
            'judul' => fake()->sentence(5),
            'periode_mulai' => $mulai->toDateString(),
            'periode_selesai' => $mulai->addDays(6)->toDateString(),
            'ringkasan' => fake()->paragraph(),
            'narasi_evaluasi' => fake()->paragraph(),
            'status' => StatusLaporan::Draft,
            'versi' => 1,
        ];
    }

    public function diajukan(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusLaporan::Diajukan,
        ]);
    }
}
