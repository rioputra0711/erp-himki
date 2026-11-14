<?php

namespace App\Models;

use App\Enums\JenisLaporan;
use App\Enums\StatusLaporan;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\MilikSesiPelapor;
use App\Support\ServerClock;
use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use Auditable, HasFactory, MilikSesiPelapor;

    protected $fillable = [
        'personel_id',
        'assignment_id',
        'jenis',
        'judul',
        'periode_mulai',
        'periode_selesai',
        'ringkasan',
        'narasi_evaluasi',
        'kendala',
        'rencana_tindak_lanjut',
        'status',
        'versi',
        'induk_id',
        'diajukan_pada',
        'diverifikasi_oleh',
        'diverifikasi_pada',
    ];

    protected function casts(): array
    {
        return [
            'jenis' => JenisLaporan::class,
            'status' => StatusLaporan::class,
            'periode_mulai' => 'date',
            'periode_selesai' => 'date',
            'diajukan_pada' => 'datetime',
            'diverifikasi_pada' => 'datetime',
            'versi' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $report): void {
            // Timestamp pengajuan hanya boleh berasal dari jam server saat status berubah.
            if ($report->status === StatusLaporan::Diajukan && $report->diajukan_pada === null) {
                $report->diajukan_pada = ServerClock::now();
            }

            if ($report->status === StatusLaporan::Terverifikasi && $report->diverifikasi_pada === null) {
                $report->diverifikasi_pada = ServerClock::now();
            }
        });
    }

    public function personel(): BelongsTo
    {
        return $this->belongsTo(Personel::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function induk(): BelongsTo
    {
        return $this->belongsTo(self::class, 'induk_id');
    }

    public function revisi(): HasMany
    {
        return $this->hasMany(self::class, 'induk_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function reviewFinal(): HasMany
    {
        return $this->hasMany(Review::class)->where('adalah_final', true);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(ActionItem::class);
    }
}
