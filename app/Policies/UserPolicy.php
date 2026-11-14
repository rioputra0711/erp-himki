<?php

namespace App\Policies;

use App\Enums\AksiIzin;
use App\Enums\ModulAplikasi;
use App\Models\User;
use App\Policies\Concerns\IzinModul;
use App\Support\CakupanPengguna;

class UserPolicy
{
    use IzinModul;

    protected function modul(): ModulAplikasi
    {
        return ModulAplikasi::Akun;
    }

    public function view(User $user, User $akun): bool
    {
        if (! $this->boleh($user, AksiIzin::Lihat)) {
            return false;
        }

        return CakupanPengguna::adalahAdmin($user) || (int) $akun->getKey() === (int) $user->getKey();
    }

    public function update(User $user, User $akun): bool
    {
        return $this->boleh($user, AksiIzin::Ubah) && CakupanPengguna::adalahAdmin($user);
    }

    public function deactivate(User $user, User $akun): bool
    {
        return $this->boleh($user, AksiIzin::Nonaktifkan)
            && CakupanPengguna::adalahAdmin($user)
            && (int) $akun->getKey() !== (int) $user->getKey();
    }
}
