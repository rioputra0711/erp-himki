<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\BidangFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bidang extends Model
{
    /** @use HasFactory<BidangFactory> */
    use Auditable, HasFactory;

    protected $table = 'bidangs';

    protected $fillable = [
        'kode',
        'nama',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function personel(): HasMany
    {
        return $this->hasMany(Personel::class);
    }
}
