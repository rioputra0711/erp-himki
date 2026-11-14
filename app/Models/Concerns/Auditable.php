<?php

namespace App\Models\Concerns;

use App\Models\AuditEvent;
use DomainException;
use Illuminate\Support\Facades\Auth;

/**
 * Mencatat perubahan model penting ke tabel audit_events.
 *
 * Dipakai pada objek yang perubahannya wajib dapat ditelusuri sesuai PRD:
 * penugasan, laporan, review/skor, dokumen, arahan, action item, KPI, rapat.
 *
 * Dua kewajiban dijaga di sini, bukan di formulir, supaya berlaku untuk semua
 * jalur penulisan (controller, console, seeder, tinker):
 *
 * 1. Perubahan kolom tertentu wajib menyertakan alasan (`kolomWajibAlasan()`).
 * 2. Setiap perubahan meninggalkan satu baris audit berisi pelaku, waktu,
 *    nilai sebelum/sesudah, dan alasan.
 */
trait Auditable
{
    /**
     * Alasan perubahan yang dicatat pada audit berikutnya.
     *
     * Dipakai aksi yang mewajibkan alasan (mis. penetapan target/indikator)
     * tanpa menambahkan kolom alasan ke tabel domain. Dikosongkan setelah
     * baris audit tertulis: alasan berlaku untuk satu perubahan saja, agar
     * alasan lama tidak menempel pada perubahan berikutnya.
     */
    protected ?string $alasanAudit = null;

    public function denganAlasan(string $alasan): static
    {
        $this->alasanAudit = $alasan;

        return $this;
    }

    /**
     * Kolom yang perubahannya wajib menyertakan alasan.
     *
     * @return list<string>
     */
    protected function kolomWajibAlasan(): array
    {
        return [];
    }

    /**
     * Apakah perubahan satu kolom pada kondisi saat ini mewajibkan alasan.
     *
     * Model dapat mempersempit: mis. status tugas hanya wajib alasan bila
     * status lama sudah final.
     */
    protected function wajibAlasanUntuk(string $kolom, mixed $nilaiBaru): bool
    {
        return true;
    }

    public static function bootAuditable(): void
    {
        // Didaftarkan lebih dulu agar berjalan sebelum hook `saving` milik model.
        // Dengan begitu kolom turunan (skor_total, kategori, diverifikasi_pada)
        // belum berubah saat pemeriksaan, sehingga tidak menimbulkan false positive.
        static::saving(function (self $model): void {
            $model->periksaWajibAlasan();
        });

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

    protected function periksaWajibAlasan(): void
    {
        if (! $this->exists) {
            return;
        }

        $dijaga = array_intersect(array_keys($this->getDirty()), $this->kolomWajibAlasan());

        if ($dijaga === []) {
            return;
        }

        $wajib = false;

        foreach ($dijaga as $kolom) {
            if ($this->wajibAlasanUntuk($kolom, $this->getAttribute($kolom))) {
                $wajib = true;

                break;
            }
        }

        if (! $wajib) {
            return;
        }

        if (trim((string) $this->alasanAudit) === '') {
            throw new DomainException(sprintf(
                'Perubahan %s wajib menyertakan alasan.',
                implode(', ', array_map(
                    static fn (string $kolom): string => str_replace('_', ' ', $kolom),
                    $dijaga,
                )),
            ));
        }
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    protected function catatAudit(string $event, ?array $before, ?array $after): void
    {
        $reason = $this->alasanAudit ?? null;

        if ($reason === null && isset($after['alasan_pembatalan'])) {
            $reason = $after['alasan_pembatalan'];
        } elseif ($reason === null && isset($after['reason'])) {
            $reason = $after['reason'];
        }

        $pelaku = Auth::user();

        AuditEvent::create([
            'actor_id' => $pelaku?->getKey(),
            'actor_nama' => $pelaku?->name,
            'actor_peran' => $pelaku?->peranSistem()?->value,
            'event' => $event,
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'before' => $this->rapikan($before),
            'after' => $this->rapikan($after),
            'reason' => $reason,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'user_agent' => app()->runningInConsole() ? null : substr((string) request()->userAgent(), 0, 500),
        ]);

        $this->alasanAudit = null;
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
