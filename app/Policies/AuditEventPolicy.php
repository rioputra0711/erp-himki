<?php

namespace App\Policies;

use App\Enums\AksiIzin;
use App\Enums\ModulAplikasi;
use App\Models\AuditEvent;
use App\Models\User;
use App\Policies\Concerns\IzinModul;

class AuditEventPolicy
{
    use IzinModul;

    protected function modul(): ModulAplikasi
    {
        return ModulAplikasi::Audit;
    }

    /**
     * Audit trail tidak dapat diubah atau dihapus siapa pun, termasuk admin.
     */
    public function view(User $user, AuditEvent $event): bool
    {
        return $this->boleh($user, AksiIzin::Lihat);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ?AuditEvent $event = null): bool
    {
        return false;
    }

    public function delete(User $user, ?AuditEvent $event = null): bool
    {
        return false;
    }
}
