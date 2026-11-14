<?php

namespace App\Models;

use App\Enums\StatusRisalah;
use App\Support\ServerClock;
use Database\Factories\MeetingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meeting extends Model
{
    /** @use HasFactory<MeetingFactory> */
    use HasFactory;

    protected $fillable = [
        'judul',
        'agenda',
        'lokasi',
        'waktu_mulai',
        'waktu_selesai',
        'pencatat_id',
        'tenggat_risalah_singkat',
        'tenggat_risalah_lengkap',
        'status_risalah_singkat',
        'status_risalah_lengkap',
        'risalah_singkat_id',
        'risalah_lengkap_id',
    ];

    protected function casts(): array
    {
        return [
            'waktu_mulai' => 'datetime',
            'waktu_selesai' => 'datetime',
            'tenggat_risalah_singkat' => 'datetime',
            'tenggat_risalah_lengkap' => 'datetime',
            'status_risalah_singkat' => StatusRisalah::class,
            'status_risalah_lengkap' => StatusRisalah::class,
        ];
    }

    protected static function booted(): void
    {
        // Tenggat risalah dihitung dari jam server: ringkas +1 jam, lengkap +16 jam.
        static::saving(function (self $meeting): void {
            if ($meeting->waktu_selesai !== null && $meeting->isDirty('waktu_selesai')) {
                $meeting->tenggat_risalah_singkat = ServerClock::tenggatRisalahSingkat($meeting->waktu_selesai);
                $meeting->tenggat_risalah_lengkap = ServerClock::tenggatRisalahLengkap($meeting->waktu_selesai);
            }
        });
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(Personel::class, 'pencatat_id');
    }

    public function risalahSingkat(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'risalah_singkat_id');
    }

    public function risalahLengkap(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'risalah_lengkap_id');
    }

    public function peserta(): BelongsToMany
    {
        return $this->belongsToMany(Personel::class, 'meeting_attendees')
            ->withPivot('hadir')
            ->withTimestamps();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
