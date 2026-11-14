<?php

namespace App\Listeners;

use App\Support\PencatatAudit;
use Illuminate\Auth\Events\PasswordReset;

/**
 * Mencatat pengaturan ulang kata sandi lewat tautan pemulihan.
 *
 * Peristiwa ini memicu pemutusan sesi lain karena AuthenticateSession
 * membandingkan hash kata sandi yang tersimpan di sesi.
 */
final class CatatResetKataSandi
{
    public function handle(PasswordReset $event): void
    {
        PencatatAudit::catat(
            event: PencatatAudit::KATA_SANDI_DIATUR_ULANG,
            request: app()->runningInConsole() ? null : request(),
            actor: $event->user,
            after: ['metode' => 'tautan_pemulihan'],
        );
    }
}
