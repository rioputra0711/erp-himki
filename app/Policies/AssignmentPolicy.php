<?php

namespace App\Policies;

use App\Enums\AksiIzin;
use App\Enums\ModulAplikasi;
use App\Models\Assignment;
use App\Models\User;
use App\Policies\Concerns\IzinModul;
use App\Support\CakupanPengguna;

class AssignmentPolicy
{
    use IzinModul;

    protected function modul(): ModulAplikasi
    {
        return ModulAplikasi::Penugasan;
    }

    public function view(User $user, Assignment $assignment): bool
    {
        return $this->boleh($user, AksiIzin::Lihat)
            && CakupanPengguna::bolehBarisPersonel($user, $assignment->pic_id === null ? null : (int) $assignment->pic_id);
    }

    public function update(User $user, Assignment $assignment): bool
    {
        return $this->boleh($user, AksiIzin::Ubah)
            && CakupanPengguna::bolehBarisPersonel($user, $assignment->pic_id === null ? null : (int) $assignment->pic_id);
    }

    public function verify(User $user, Assignment $assignment): bool
    {
        return $this->boleh($user, AksiIzin::Verifikasi);
    }
}
