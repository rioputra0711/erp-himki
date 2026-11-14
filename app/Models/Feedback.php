<?php

namespace App\Models;

use App\Enums\AsalFeedback;
use App\Enums\JenisFeedback;
use App\Enums\StatusFeedback;
use App\Models\Concerns\Auditable;
use Database\Factories\FeedbackFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Feedback extends Model
{
    /** @use HasFactory<FeedbackFactory> */
    use Auditable, HasFactory;

    protected $table = 'feedback';

    protected $fillable = [
        'pemberi_id',
        'penerima_id',
        'jenis',
        'asal',
        'subjek_type',
        'subjek_id',
        'isi',
        'lampiran_dokumen_id',
        'butuh_tindakan',
        'status',
        'tenggat',
    ];

    protected function casts(): array
    {
        return [
            'jenis' => JenisFeedback::class,
            'asal' => AsalFeedback::class,
            'status' => StatusFeedback::class,
            'butuh_tindakan' => 'boolean',
            'tenggat' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $feedback): void {
            if ($feedback->butuh_tindakan && $feedback->penerima_id === null) {
                throw new \DomainException('Feedback yang butuh tindakan wajib memiliki penerima.');
            }

            // Status tindak lanjut tidak boleh lebih maju dari keberadaan penerima.
            if ($feedback->status === StatusFeedback::Ditindaklanjuti && $feedback->penerima_id === null) {
                throw new \DomainException('Feedback tanpa penerima tidak dapat ditandai ditindaklanjuti.');
            }
        });
    }

    public function subjek(): MorphTo
    {
        return $this->morphTo();
    }

    public function pemberi(): BelongsTo
    {
        return $this->belongsTo(Personel::class, 'pemberi_id');
    }

    public function penerima(): BelongsTo
    {
        return $this->belongsTo(Personel::class, 'penerima_id');
    }

    public function lampiran(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'lampiran_dokumen_id');
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(ActionItem::class);
    }

    public function scopeTerbuka(Builder $query): Builder
    {
        return $query->where('status', StatusFeedback::Terbuka->value);
    }

    public function scopeButuhTindakan(Builder $query): Builder
    {
        return $query->where('butuh_tindakan', true);
    }
}
