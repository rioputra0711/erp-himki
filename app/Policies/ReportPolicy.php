<?php

namespace App\Policies;

use App\Enums\AksiIzin;
use App\Enums\ModulAplikasi;
use App\Models\Report;
use App\Models\User;
use App\Policies\Concerns\IzinModul;
use App\Support\CakupanPengguna;

class ReportPolicy
{
    use IzinModul;

    protected function modul(): ModulAplikasi
    {
        return ModulAplikasi::Laporan;
    }

    public function view(User $user, Report $report): bool
    {
        return $this->boleh($user, AksiIzin::Lihat)
            && CakupanPengguna::bolehBarisPersonel($user, (int) $report->personel_id);
    }

    public function update(User $user, Report $report): bool
    {
        return $this->boleh($user, AksiIzin::Ubah)
            && CakupanPengguna::bolehBarisPersonel($user, (int) $report->personel_id);
    }

    public function submit(User $user, Report $report): bool
    {
        return $this->boleh($user, AksiIzin::Ajukan)
            && CakupanPengguna::bolehBarisPersonel($user, (int) $report->personel_id);
    }
}
