<?php

namespace App\Models;

use App\Enums\FrekuensiTugas;
use App\Enums\KategoriTugas;
use App\Models\Concerns\Auditable;
use Database\Factories\JobDescriptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Master Job Description per jabatan, berversi dan terkunci setelah ditetapkan.
 *
 * Aturan penguncian (fitur.pdf Tahap 3): target dan indikator harus ditetapkan
 * sebelum periode penilaian dimulai dan tidak boleh berubah saat pekerjaan
 * berjalan. Karena itu baris yang sudah ditetapkan tidak dapat diubah; perubahan
 * dibuat sebagai versi baru dengan `berlaku_mulai` yang lebih baru.
 */
class JobDescription extends Model
{
    /** @use HasFactory<JobDescriptionFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'jabatan_id',
        'kode',
        'versi',
        'kategori',
        'uraian',
        'indikator',
        'target',
        'frekuensi',
        'berlaku_mulai',
        'ditetapkan_pada',
        'ditetapkan_oleh_id',
        'induk_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'kategori' => KategoriTugas::class,
            'frekuensi' => FrekuensiTugas::class,
            'berlaku_mulai' => 'date',
            'ditetapkan_pada' => 'datetime',
            'versi' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Kolom yang terkunci setelah JD ditetapkan.
     *
     * @var list<string>
     */
    public const KOLOM_TERKUNCI = [
        'jabatan_id',
        'kode',
        'versi',
        'kategori',
        'uraian',
        'indikator',
        'target',
        'frekuensi',
        'berlaku_mulai',
        'ditetapkan_pada',
        'ditetapkan_oleh_id',
        'induk_id',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $jd): void {
            $asli = $jd->exists ? $jd->getOriginal() : [];

            if (($asli['ditetapkan_pada'] ?? null) !== null) {
                foreach (self::KOLOM_TERKUNCI as $kolom) {
                    if ($jd->isDirty($kolom)) {
                        throw new \DomainException(
                            "Job Description yang sudah ditetapkan tidak boleh diubah pada kolom {$kolom}. "
                            .'Buat versi baru dengan periode berlaku berikutnya.'
                        );
                    }
                }
            }

            if ($jd->isDirty('ditetapkan_pada') && $jd->ditetapkan_pada !== null) {
                $kurang = [];

                foreach (['indikator', 'target', 'frekuensi', 'berlaku_mulai'] as $wajib) {
                    $nilai = $jd->getAttribute($wajib);

                    if ($nilai instanceof \BackedEnum) {
                        $nilai = $nilai->value;
                    }

                    if ($nilai instanceof \DateTimeInterface) {
                        $nilai = $nilai->format('Y-m-d');
                    }

                    if (trim((string) $nilai) === '') {
                        $kurang[] = $wajib;
                    }
                }

                if ($kurang !== []) {
                    throw new \DomainException(
                        'Job Description belum lengkap untuk ditetapkan: '.implode(', ', $kurang).'.'
                    );
                }
            }
        });
    }

    public function sudahDitetapkan(): bool
    {
        return $this->ditetapkan_pada !== null;
    }

    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class);
    }

    public function ditetapkanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditetapkan_oleh_id');
    }

    public function induk(): BelongsTo
    {
        return $this->belongsTo(self::class, 'induk_id');
    }

    public function versiTurunan(): HasMany
    {
        return $this->hasMany(self::class, 'induk_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Versi yang berlaku pada tanggal tertentu: sudah ditetapkan, sudah mulai
     * berlaku, dan belum digantikan versi yang lebih baru.
     */
    public function scopeBerlakuPada(Builder $query, Carbon|string $tanggal): Builder
    {
        $tanggal = $tanggal instanceof Carbon ? $tanggal->toDateString() : $tanggal;

        return $query
            ->where('is_active', true)
            ->whereNotNull('ditetapkan_pada')
            ->whereDate('berlaku_mulai', '<=', $tanggal)
            ->whereNotExists(function ($sub) use ($tanggal): void {
                $sub->selectRaw('1')
                    ->from('job_descriptions as pengganti')
                    ->whereColumn('pengganti.induk_id', 'job_descriptions.id')
                    ->where('pengganti.is_active', true)
                    ->whereDate('pengganti.berlaku_mulai', '<=', $tanggal);
            });
    }
}
