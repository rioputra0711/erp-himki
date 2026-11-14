<?php

namespace App\Policies;

use App\Enums\AksiIzin;
use App\Enums\ModulAplikasi;
use App\Models\ActionItem;
use App\Models\User;
use App\Policies\Concerns\IzinModul;
use App\Support\CakupanPengguna;

class ActionItemPolicy
{
    use IzinModul;

    protected function modul(): ModulAplikasi
    {
        return ModulAplikasi::TindakLanjut;
    }

    public function view(User $user, ActionItem $actionItem): bool
    {
        return $this->boleh($user, AksiIzin::Lihat)
            && CakupanPengguna::bolehBarisPersonel($user, $actionItem->pic_id === null ? null : (int) $actionItem->pic_id);
    }

    public function update(User $user, ActionItem $actionItem): bool
    {
        return $this->boleh($user, AksiIzin::Ubah)
            && CakupanPengguna::bolehBarisPersonel($user, $actionItem->pic_id === null ? null : (int) $actionItem->pic_id);
    }

    public function verify(User $user, ActionItem $actionItem): bool
    {
        return $this->boleh($user, AksiIzin::Verifikasi);
    }
}
