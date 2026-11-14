<?php

namespace App\Models;

use App\Enums\KategoriDokumen;
use App\Enums\KlasifikasiDokumen;
use App\Enums\StatusDokumen;
use App\Models\Concerns\Auditable;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'judul',
        'kategori',
        'versi',
        'induk_id',
        'pemilik_id',
        'assignment_id',
        'report_id',
        'meeting_id',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'checksum',
        'klasifikasi',
        'status',
        'diunggah_oleh',
    ];

    protected function casts(): array
    {
        return [
            'kategori' => KategoriDokumen::class,
            'klasifikasi' => KlasifikasiDokumen::class,
            'status' => StatusDokumen::class,
            'versi' => 'integer',
            'file_size' => 'integer',
        ];
    }

    public function pemilik(): BelongsTo
    {
        return $this->belongsTo(Personel::class, 'pemilik_id');
    }

    public function pengunggah(): BelongsTo
    {
        return $this->belongsTo(Personel::class, 'diunggah_oleh');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function induk(): BelongsTo
    {
        return $this->belongsTo(self::class, 'induk_id');
    }

    public function versiLain(): HasMany
    {
        return $this->hasMany(self::class, 'induk_id');
    }
}
