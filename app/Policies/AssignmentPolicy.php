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

    /**
     * Menyunting isi tugas. Staf tidak menyunting tugas, hanya melaporkan
     * progres; DE bebas, Pengurus hanya untuk tugas yang ia terbitkan sendiri.
     */
    public function sunting(User $user, Assignment $assignment): bool
    {
        if (! $this->boleh($user, AksiIzin::Ubah) || CakupanPengguna::adalahStaf($user)) {
            return false;
        }

        if (CakupanPengguna::adalahDirekturEksekutif($user)) {
            return true;
        }

        return $this->pemberiSendiri($user, $assignment);
    }

    /**
     * Melaporkan progres dan mengajukan selesai: hanya PIC (atau DE).
     */
    public function progres(User $user, Assignment $assignment): bool
    {
        return $this->boleh($user, AksiIzin::Ubah)
            && CakupanPengguna::bolehBarisPersonel($user, $assignment->pic_id === null ? null : (int) $assignment->pic_id);
    }

    public function ajukan(User $user, Assignment $assignment): bool
    {
        return $this->progres($user, $assignment);
    }

    public function tutup(User $user, Assignment $assignment): bool
    {
        return $this->verify($user, $assignment);
    }

    public function aktifkan(User $user, Assignment $assignment): bool
    {
        return $this->verify($user, $assignment);
    }

    /**
     * Pembatalan oleh DE atau oleh pemberi tugas itu sendiri.
     */
    public function batalkan(User $user, Assignment $assignment): bool
    {
        if (! $this->boleh($user, AksiIzin::Ubah)) {
            return false;
        }

        return CakupanPengguna::adalahDirekturEksekutif($user)
            || $this->pemberiSendiri($user, $assignment);
    }

    private function pemberiSendiri(User $user, Assignment $assignment): bool
    {
        $personelId = CakupanPengguna::personelId($user);

        return $personelId !== null && $personelId === (int) $assignment->pemberi_tugas_id;
    }
}
