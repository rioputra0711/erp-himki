<?php

namespace App\Policies;

use App\Enums\AksiIzin;
use App\Enums\ModulAplikasi;
use App\Models\ActivityLog;
use App\Models\User;
use App\Policies\Concerns\IzinModul;
use App\Support\CakupanPengguna;

class ActivityLogPolicy
{
    use IzinModul;

    protected function modul(): ModulAplikasi
    {
        return ModulAplikasi::LogAktivitas;
    }

    public function view(User $user, ActivityLog $log): bool
    {
        return $this->boleh($user, AksiIzin::Lihat)
            && CakupanPengguna::bolehBarisPersonel($user, (int) $log->personel_id);
    }

    public function update(User $user, ActivityLog $log): bool
    {
        return $this->boleh($user, AksiIzin::Ubah)
            && CakupanPengguna::bolehBarisPersonel($user, (int) $log->personel_id);
    }
}
