<?php

namespace App\Policies\Concerns;

use App\Enums\AksiIzin;
use App\Enums\ModulAplikasi;
use App\Models\User;
use App\Support\CakupanPengguna;

/**
 * Dasar Policy per modul: memetakan setiap kemampuan ke izin `modul.aksi`.
 *
 * Policy menambah cakupan baris di atas izin modul, sehingga akun yang berhak
 * membuka sebuah menu tetap hanya melihat baris yang menjadi wewenangnya.
 */
trait IzinModul
{
    abstract protected function modul(): ModulAplikasi;

    protected function boleh(User $user, AksiIzin $aksi): bool
    {
        return CakupanPengguna::boleh($user, $this->modul(), $aksi);
    }

    public function viewAny(User $user): bool
    {
        return $this->boleh($user, AksiIzin::Lihat);
    }

    public function create(User $user): bool
    {
        return $this->boleh($user, AksiIzin::Buat);
    }

    public function update(User $user): bool
    {
        return $this->boleh($user, AksiIzin::Ubah);
    }

    public function delete(User $user): bool
    {
        return $this->boleh($user, AksiIzin::Hapus);
    }

    public function verify(User $user): bool
    {
        return $this->boleh($user, AksiIzin::Verifikasi);
    }

    public function export(User $user): bool
    {
        return $this->boleh($user, AksiIzin::Ekspor);
    }

    public function submit(User $user): bool
    {
        return $this->boleh($user, AksiIzin::Ajukan);
    }

    public function finalize(User $user): bool
    {
        return $this->boleh($user, AksiIzin::Kunci);
    }

    public function deactivate(User $user): bool
    {
        return $this->boleh($user, AksiIzin::Nonaktifkan);
    }

    public function configure(User $user): bool
    {
        return $this->boleh($user, AksiIzin::Konfigurasi);
    }
}
