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
     * Satu baris jejak hanya boleh dibuka bila berada dalam cakupan akun.
     *
     * Penyaringan daftar saja tidak cukup: tanpa pemeriksaan baris, Direktur
     * Eksekutif masih bisa membuka peristiwa keamanan lewat URL langsung.
     */
    public function view(User $user, AuditEvent $event): bool
    {
        return $this->boleh($user, AksiIzin::Lihat) && $event->terlihatOleh($user);
    }

    /**
     * Audit trail tidak dapat diubah atau dihapus siapa pun, termasuk admin.
     */
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
