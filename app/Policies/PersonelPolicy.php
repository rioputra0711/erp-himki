<?php

namespace App\Policies;

use App\Enums\AksiIzin;
use App\Enums\ModulAplikasi;
use App\Models\Personel;
use App\Models\User;
use App\Policies\Concerns\IzinModul;
use App\Support\CakupanPengguna;

class PersonelPolicy
{
    use IzinModul;

    protected function modul(): ModulAplikasi
    {
        return ModulAplikasi::Personel;
    }

    public function view(User $user, Personel $personel): bool
    {
        if (! $this->boleh($user, AksiIzin::Lihat)) {
            return false;
        }

        if (! CakupanPengguna::adalahStaf($user)) {
            return true;
        }

        return (int) $personel->user_id === (int) $user->getKey();
    }

    public function update(User $user, Personel $personel): bool
    {
        return $this->boleh($user, AksiIzin::Ubah) && ! CakupanPengguna::adalahStaf($user);
    }

    public function deactivate(User $user, Personel $personel): bool
    {
        return $this->boleh($user, AksiIzin::Nonaktifkan) && ! CakupanPengguna::adalahStaf($user);
    }
}
