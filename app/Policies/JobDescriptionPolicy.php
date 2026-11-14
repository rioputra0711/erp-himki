<?php

namespace App\Policies;

use App\Enums\AksiIzin;
use App\Enums\ModulAplikasi;
use App\Models\JobDescription;
use App\Models\User;
use App\Policies\Concerns\IzinModul;
use App\Support\CakupanPengguna;

/**
 * Master Job Description (fitur.pdf Tahap 3).
 *
 * - Staf hanya membaca JD jabatannya sendiri.
 * - DE dan Admin boleh menyusun draf (create/update).
 * - Hanya pemegang `personel.configure` (Administrator) yang boleh menetapkan
 *   atau mengarsipkan versi, supaya target & indikator tidak berubah saat
 *   periode penilaian berjalan.
 */
class JobDescriptionPolicy
{
    use IzinModul;

    protected function modul(): ModulAplikasi
    {
        return ModulAplikasi::Personel;
    }

    public function view(User $user, JobDescription $jobDescription): bool
    {
        if (! $this->boleh($user, AksiIzin::Lihat)) {
            return false;
        }

        if (! CakupanPengguna::adalahStaf($user)) {
            return true;
        }

        $personel = CakupanPengguna::personel($user);

        return $personel !== null
            && (int) $personel->jabatan_id === (int) $jobDescription->jabatan_id;
    }

    public function create(User $user): bool
    {
        return $this->boleh($user, AksiIzin::Buat);
    }

    public function update(User $user, JobDescription $jobDescription): bool
    {
        // Baris yang sudah ditetapkan terkunci; perubahan harus lewat versi baru.
        return $this->boleh($user, AksiIzin::Ubah) && ! $jobDescription->sudahDitetapkan();
    }

    public function delete(User $user): bool
    {
        return false;
    }

    public function configure(User $user, JobDescription $jobDescription): bool
    {
        return $this->boleh($user, AksiIzin::Konfigurasi);
    }
}
