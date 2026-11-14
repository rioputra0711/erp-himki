<?php

namespace App\Models\Concerns;

use App\Models\AuditEvent;
use Illuminate\Support\Facades\Auth;

/**
 * Mencatat perubahan model penting ke tabel audit_events.
 *
 * Dipakai pada objek yang perubahannya wajib dapat ditelusuri sesuai PRD:
 * penugasan, laporan, review/skor, dokumen, arahan, action item, KPI, rapat.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (self $model): void {
            $model->catatAudit('created', null, $model->getAttributes());
        });

        static::updated(function (self $model): void {
            $model->catatAudit('updated', $model->getOriginal(), $model->getChanges());
        });

        static::deleted(function (self $model): void {
            $model->catatAudit('deleted', $model->getOriginal(), null);
        });
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    protected function catatAudit(string $event, ?array $before, ?array $after): void
    {
        $reason = null;

        if (isset($after['alasan_pembatalan'])) {
            $reason = $after['alasan_pembatalan'];
        } elseif (isset($after['reason'])) {
            $reason = $after['reason'];
        }

        AuditEvent::create([
            'actor_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'before' => $this->rapikan($before),
            'after' => $this->rapikan($after),
            'reason' => $reason,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'user_agent' => app()->runningInConsole() ? null : substr((string) request()->userAgent(), 0, 500),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function rapikan(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        unset($values['updated_at']);

        return $values;
    }
}
