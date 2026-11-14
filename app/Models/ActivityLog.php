<?php

namespace App\Models;

use App\Models\Concerns\MilikSesiPelapor;
use App\Support\ServerClock;
use Database\Factories\ActivityLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    /** @use HasFactory<ActivityLogFactory> */
    use HasFactory, MilikSesiPelapor;

    protected $fillable = [
        'personel_id',
        'assignment_id',
        'job_description_id',
        'tanggal',
        'uraian',
        'progres',
        'durasi_menit',
        'kendala',
        'is_kendala_dini',
        'dicatat_pada',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'dicatat_pada' => 'datetime',
            'progres' => 'integer',
            'durasi_menit' => 'integer',
            'is_kendala_dini' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Waktu pencatatan selalu jam server, tidak pernah dari perangkat pengguna.
        static::creating(function (self $log): void {
            $log->dicatat_pada = ServerClock::now();
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

    public function jobDescription(): BelongsTo
    {
        return $this->belongsTo(JobDescription::class);
    }
}
