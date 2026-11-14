<?php

namespace Database\Factories;

use App\Enums\StatusRisalah;
use App\Models\Meeting;
use App\Models\Personel;
use App\Support\ServerClock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    protected $model = Meeting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $mulai = ServerClock::now()->subDays(fake()->numberBetween(1, 10));

        return [
            'judul' => fake()->sentence(5),
            'agenda' => fake()->paragraph(),
            'lokasi' => fake()->randomElement(['Ruang Rapat HIMKI', 'Daring (Zoom)', null]),
            'waktu_mulai' => $mulai,
            'waktu_selesai' => $mulai->addHours(2),
            'pencatat_id' => Personel::factory(),
            'status_risalah_singkat' => StatusRisalah::Belum,
            'status_risalah_lengkap' => StatusRisalah::Belum,
        ];
    }
}
