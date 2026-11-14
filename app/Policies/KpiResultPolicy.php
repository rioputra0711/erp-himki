<?php

namespace App\Policies;

use App\Enums\AksiIzin;
use App\Enums\ModulAplikasi;
use App\Models\KpiResult;
use App\Models\User;
use App\Policies\Concerns\IzinModul;
use App\Support\CakupanPengguna;

class KpiResultPolicy
{
    use IzinModul;

    protected function modul(): ModulAplikasi
    {
        return ModulAplikasi::Kpi;
    }

    public function view(User $user, KpiResult $kpi): bool
    {
        return $this->boleh($user, AksiIzin::Lihat)
            && CakupanPengguna::bolehBarisPersonel($user, (int) $kpi->personel_id);
    }

    public function update(User $user, KpiResult $kpi): bool
    {
        return $this->boleh($user, AksiIzin::Ubah)
            && CakupanPengguna::bolehBarisPersonel($user, (int) $kpi->personel_id);
    }

    public function finalize(User $user, KpiResult $kpi): bool
    {
        return $this->boleh($user, AksiIzin::Kunci);
    }
}
