<?php

namespace App\Models;

use App\Enums\StatusActionItem;
use App\Models\Concerns\Auditable;
use App\Support\ServerClock;
use Database\Factories\ActionItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
                throw new \DomainException('Action item wajib terhubung ke minimal satu sumber (feedback, review, penugasan, atau laporan).');
            }

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
}
