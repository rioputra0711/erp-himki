<?php

namespace App\Policies;

use App\Enums\AksiIzin;
use App\Enums\ModulAplikasi;
use App\Models\Review;
use App\Models\User;
use App\Policies\Concerns\IzinModul;
use App\Support\CakupanPengguna;

class ReviewPolicy
{
    use IzinModul;

    protected function modul(): ModulAplikasi
    {
        return ModulAplikasi::Review;
    }

    public function view(User $user, Review $review): bool
    {
        return $this->boleh($user, AksiIzin::Lihat) && $this->bolehBaris($user, $review);
    }

    public function update(User $user, Review $review): bool
    {
        return $this->boleh($user, AksiIzin::Ubah) && $this->bolehBaris($user, $review);
    }

    public function verify(User $user, Review $review): bool
    {
        return $this->boleh($user, AksiIzin::Verifikasi) && $this->bolehBaris($user, $review);
    }

    /**
     * Staf hanya boleh membaca review atas laporan miliknya.
     */
    private function bolehBaris(User $user, Review $review): bool
    {
        if (! CakupanPengguna::adalahStaf($user)) {
            return true;
        }

        $pemilikLaporan = $review->report?->personel_id;

        return CakupanPengguna::bolehBarisPersonel($user, $pemilikLaporan === null ? null : (int) $pemilikLaporan);
    }
}
