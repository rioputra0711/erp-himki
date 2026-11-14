<?php

namespace App\Policies;

use App\Enums\AksiIzin;
use App\Enums\ModulAplikasi;
use App\Models\User;
use App\Policies\Concerns\IzinModul;
use App\Support\CakupanPengguna;

/**
 * Master bidang kerja berada di bawah menu Master Personel (fitur.pdf Tahap 3).
 *
 * Semua peran boleh membaca daftar bidang; hanya Administrator yang boleh
 * menambah, mengubah, atau menonaktifkannya (PRD E.2 baris 11).
 */
class BidangPolicy
{
    use IzinModul;

    protected function modul(): ModulAplikasi
    {
        return ModulAplikasi::Personel;
    }

    public function create(User $user): bool
    {
        return CakupanPengguna::adalahAdmin($user) && $this->boleh($user, AksiIzin::Buat);
    }

    public function update(User $user): bool
    {
        return CakupanPengguna::adalahAdmin($user) && $this->boleh($user, AksiIzin::Ubah);
    }

    public function delete(User $user): bool
    {
        return false;
    }

    public function deactivate(User $user): bool
    {
        return CakupanPengguna::adalahAdmin($user) && $this->boleh($user, AksiIzin::Nonaktifkan);
    }
}
