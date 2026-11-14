<?php

namespace App\Models;

use App\Enums\StatusActionItem;
use App\Models\Concerns\Auditable;
use App\Support\CakupanPengguna;
use App\Support\ServerClock;
use Database\Factories\ActionItemFactory;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class ActionItem extends Model
{
    /** @use HasFactory<ActionItemFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'feedback_id',
        'review_id',
        'assignment_id',
        'report_id',
        'deskripsi',
        'pic_id',
        'tenggat',
        'status',
        'selesai_pada',
        'bukti_penutupan',
        'diverifikasi_oleh',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusActionItem::class,
            'tenggat' => 'datetime',
            'selesai_pada' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            $punyaSumber = $item->feedback_id !== null
                || $item->review_id !== null
                || $item->assignment_id !== null
                || $item->report_id !== null;

            if (! $punyaSumber) {
                throw new DomainException('Action item wajib terhubung ke minimal satu sumber (feedback, review, penugasan, atau laporan).');
            }

            if ($item->status === StatusActionItem::Ditutup
                && trim((string) $item->bukti_penutupan) === '') {
                throw new DomainException('Penutupan action item wajib menyertakan bukti penutupan.');
            }

            $item->jagaKewenanganPenutupan();

            // Waktu penyelesaian selalu dari jam server, bukan input pengguna.
            $selesai = in_array($item->status, [
                StatusActionItem::TerverifikasiSelesai,
                StatusActionItem::Ditutup,
            ], true);

            if ($selesai && $item->selesai_pada === null) {
                $item->selesai_pada = ServerClock::now();
            }

            if (! $selesai && $item->status !== StatusActionItem::DiajukanSelesai) {
                $item->selesai_pada = null;
            }
        });
    }

    /**
     * Hanya pemegang `action_item.verify` yang boleh mengunci item sebagai
     * selesai atau menutupnya.
     */
    private function jagaKewenanganPenutupan(): void
    {
        if (! in_array($this->status, [StatusActionItem::TerverifikasiSelesai, StatusActionItem::Ditutup], true)) {
            return;
        }

        $pengguna = Auth::user();

        if ($pengguna !== null && ! CakupanPengguna::bolehNamaIzin($pengguna, 'action_item.verify')) {
            throw new DomainException('Hanya pemegang izin verifikasi yang dapat memverifikasi atau menutup action item.');
        }
    }

    /**
     * Perubahan status item selalu mengubah tunggakan, jadi wajib beralasan.
     *
     * @return list<string>
     */
    protected function kolomWajibAlasan(): array
    {
        return ['status'];
    }

    protected function wajibAlasanUntuk(string $kolom, mixed $nilaiBaru): bool
    {
        if ($kolom !== 'status') {
            return true;
        }

        $lama = StatusActionItem::tryFrom((string) $this->getRawOriginal('status'));
        $baru = $nilaiBaru instanceof StatusActionItem
            ? $nilaiBaru
            : StatusActionItem::tryFrom((string) $nilaiBaru);

        $final = [StatusActionItem::TerverifikasiSelesai, StatusActionItem::Ditutup];

        return in_array($baru, $final, true) || in_array($lama, $final, true);
    }

    public function feedback(): BelongsTo
    {
        return $this->belongsTo(Feedback::class);
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(Personel::class, 'pic_id');
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(Personel::class, 'diverifikasi_oleh');
    }

    public function scopeTunggakan(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            StatusActionItem::TerverifikasiSelesai->value,
            StatusActionItem::Ditutup->value,
        ]);
    }

    public function scopeLewatTenggat(Builder $query): Builder
    {
        return $this->scopeTunggakan($query)
            ->whereNotNull('tenggat')
            ->where('tenggat', '<', ServerClock::now());
    }

    /**
     * Batas baris untuk akun Staf: hanya item yang menjadi tanggung jawabnya.
     */
    public function scopeTerlihatOleh(Builder $query, ?User $pengguna): Builder
    {
        if ($pengguna === null || ! CakupanPengguna::adalahStaf($pengguna)) {
            return $query;
        }

        return $query->where('pic_id', CakupanPengguna::personelId($pengguna) ?? 0);
    }
}
