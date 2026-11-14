<?php

namespace App\Models;

use App\Enums\KategoriPersonel;
use App\Models\Concerns\Auditable;
use Database\Factories\JabatanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Jabatan extends Model
{
    /** @use HasFactory<JabatanFactory> */
    use Auditable, HasFactory;

    protected $table = 'jabatans';

    protected $fillable = [
        'kode',
        'nama',
        'kelompok',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'kelompok' => KategoriPersonel::class,
            'is_active' => 'boolean',
        ];
    }

    public function personel(): HasMany
    {
        return $this->hasMany(Personel::class);
    }

    public function jobDescriptions(): HasMany
    {
        return $this->hasMany(JobDescription::class);
    }
}
