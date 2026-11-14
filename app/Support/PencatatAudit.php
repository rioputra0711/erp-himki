<?php

namespace App\Support;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Pencatat peristiwa keamanan ke audit trail append-only.
 *
 * Peristiwa autentikasi tidak melekat pada model domain mana pun, jadi ditulis
 * langsung ke `audit_events` dengan patron `auth.<peristiwa>`. Setiap kegagalan
 * menulis audit dimaafkan: keamanan tidak boleh berhenti hanya karena audit gagal,
 * tetapi kegagalannya tidak boleh pula membatalkan proses login yang sah.
 */
final class PencatatAudit
{
    public const LOGIN = 'auth.login';

    public const LOGIN_GAGAL = 'auth.login_gagal';

    public const LOGOUT = 'auth.logout';

    public const AKSES_DITOLAK = 'auth.akses_ditolak';

    public const KATA_SANDI_DIUBAH = 'auth.kata_sandi_diubah';

    public const KATA_SANDI_DIATUR_ULANG = 'auth.kata_sandi_diatur_ulang';

    public const LOGIN_DIBLOKIR = 'auth.login_diblokir';

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public static function catat(
        string $event,
        ?Request $request = null,
        ?User $actor = null,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
    ): void {
        try {
            AuditEvent::create([
                'actor_id' => $actor?->getKey(),
                'event' => $event,
                'before' => $before,
                'after' => $after,
                'reason' => $reason !== null ? mb_substr($reason, 0, 500) : null,
                'ip_address' => $request?->ip(),
                'user_agent' => $request !== null
                    ? mb_substr((string) $request->userAgent(), 0, 500)
                    : null,
            ]);
        } catch (\Throwable) {
            // Audit bersifat best-effort untuk peristiwa autentikasi; jangan pernah
            // menggagalkan permintaan pengguna hanya karena penulisan audit gagal.
        }
    }
}
