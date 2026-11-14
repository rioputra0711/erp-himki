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

        return CakupanPengguna::bolehBarisPersonel($user, (int) $personel->getKey());
    }

    /**
     * Penulisan Master Personel adalah wewenang Administrator saja
     * (PRD E.2 baris 11). DE memegang `personel.create`/`personel.update`
     * untuk menyusun draf Job Description, bukan untuk mengubah identitas
     * personel; Pengurus hanya membaca.
     */
    public function create(User $user): bool
    {
        return CakupanPengguna::adalahAdmin($user) && $this->boleh($user, AksiIzin::Buat);
    }

    public function update(User $user, Personel $personel): bool
    {
        return CakupanPengguna::adalahAdmin($user) && $this->boleh($user, AksiIzin::Ubah);
    }

    public function deactivate(User $user, Personel $personel): bool
    {
        return CakupanPengguna::adalahAdmin($user) && $this->boleh($user, AksiIzin::Nonaktifkan);
    }
}
