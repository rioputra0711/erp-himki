<?php

namespace App\Models;

use App\Enums\FrekuensiTugas;
use App\Enums\KategoriTugas;
use Database\Factories\JobDescriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobDescription extends Model
{
    /** @use HasFactory<JobDescriptionFactory> */
    use HasFactory;

    protected $fillable = [
        'jabatan_id',
        'kode',
        'kategori',
        'uraian',
        'indikator',
        'target',
        'frekuensi',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'kategori' => KategoriTugas::class,
            'frekuensi' => FrekuensiTugas::class,
            'is_active' => 'boolean',
        ];
    }

    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }
}
