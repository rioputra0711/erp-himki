<?php

namespace App\Policies;

use App\Enums\AksiIzin;
use App\Enums\ModulAplikasi;
use App\Models\Meeting;
use App\Models\User;
use App\Policies\Concerns\IzinModul;
use App\Support\CakupanPengguna;

class MeetingPolicy
{
    use IzinModul;

    protected function modul(): ModulAplikasi
    {
        return ModulAplikasi::Rapat;
    }

    public function view(User $user, Meeting $meeting): bool
    {
        return $this->boleh($user, AksiIzin::Lihat) && $this->bolehBaris($user, $meeting);
    }

    public function update(User $user, Meeting $meeting): bool
    {
        return $this->boleh($user, AksiIzin::Ubah) && $this->bolehBaris($user, $meeting);
    }

    /**
     * Staf hanya melihat rapat yang ia catat atau yang ia hadiri.
     */
    private function bolehBaris(User $user, Meeting $meeting): bool
    {
        if (! CakupanPengguna::adalahStaf($user)) {
            return true;
        }

        $personelId = CakupanPengguna::personelId($user);

        if ($personelId === null) {
            return false;
        }

        if ((int) $meeting->pencatat_id === $personelId) {
            return true;
        }

        return $meeting->peserta()->whereKey($personelId)->exists();
    }
}
