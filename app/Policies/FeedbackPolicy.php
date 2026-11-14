<?php

namespace App\Policies;

use App\Enums\AksiIzin;
use App\Enums\JenisFeedback;
use App\Enums\ModulAplikasi;
use App\Enums\PeranSistem;
use App\Models\Feedback;
use App\Models\User;
use App\Policies\Concerns\IzinModul;
use App\Support\CakupanPengguna;

class FeedbackPolicy
{
    use IzinModul;

    protected function modul(): ModulAplikasi
    {
        return ModulAplikasi::UmpanBalik;
    }

    public function view(User $user, Feedback $feedback): bool
    {
        return $this->boleh($user, AksiIzin::Lihat) && $this->bolehBaris($user, $feedback);
    }

    public function update(User $user, Feedback $feedback): bool
    {
        return $this->boleh($user, AksiIzin::Ubah) && $this->bolehBaris($user, $feedback);
    }

    /**
     * Persetujuan formal hanya milik Pengurus dan selalu berupa baris baru;
     * komentar biasa tidak dapat diubah menjadi persetujuan.
     */
    public function setujui(User $user, Feedback $feedback): bool
    {
        return $this->boleh($user, AksiIzin::Buat)
            && CakupanPengguna::peran($user) === PeranSistem::Pengurus
            && $feedback->jenis !== JenisFeedback::Persetujuan;
    }

    private function bolehBaris(User $user, Feedback $feedback): bool
    {
        if (! CakupanPengguna::adalahStaf($user)) {
            return true;
        }

        $personelId = CakupanPengguna::personelId($user);

        if ($personelId === null) {
            return false;
        }

        return in_array($personelId, [(int) $feedback->pemberi_id, (int) $feedback->penerima_id], true);
    }
}
