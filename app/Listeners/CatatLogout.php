<?php

namespace App\Listeners;

use App\Support\PencatatAudit;
use Illuminate\Auth\Events\Logout;

/**
 * Mencatat setiap pengakhiran sesi ke audit trail.
 *
 * Dipasang pada event `Logout` agar berlaku untuk semua jalur keluar: tombol
 * keluar biasa maupun pemutusan otomatis karena akun dinonaktifkan.
 */
final class CatatLogout
{
    public function handle(Logout $event): void
    {
        $request = app()->runningInConsole() ? null : request();

        PencatatAudit::catat(
            event: PencatatAudit::LOGOUT,
            request: $request,
            actor: $event->user,
            reason: $request?->attributes->get('alasan_logout'),
        );
    }
}
