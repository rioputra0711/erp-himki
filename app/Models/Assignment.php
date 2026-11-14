<?php

namespace App\Models;

use App\Enums\KategoriTugas;
use App\Enums\PrioritasTugas;
use App\Enums\StatusPenugasan;
use App\Enums\SumberPerintah;
use App\Models\Concerns\Auditable;
use App\Support\ServerClock;
use Database\Factories\AssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    /** @use HasFactory<AssignmentFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'kode',
        'judul',
        'deskripsi',
        'job_description_id',
        'sumber_perintah',
        'pemberi_tugas_id',
        'diteruskan_oleh_id',
        'pic_id',
        'kategori',
        'target_keluaran',
        'prioritas',
        'tanggal_mulai',
        'tenggat',
        'status',
        'progres',
        'kendala',
        'alasan_pembatalan',
        'dependensi_id',
        'diverifikasi_oleh',
        'diverifikasi_pada',
        'ditutup_pada',
    ];

    protected function casts(): array
    {
        return [
            'sumber_perintah' => SumberPerintah::class,
            'kategori' => KategoriTugas::class,
            'prioritas' => PrioritasTugas::class,
            'status' => StatusPenugasan::class,
            'tanggal_mulai' => 'date',
            'tenggat' => 'datetime',
            'diverifikasi_pada' => 'datetime',
            'ditutup_pada' => 'datetime',
            'progres' => 'integer',
        ];
    }

    /**
     * Status yang sudah diverifikasi tidak boleh berubah tanpa jejak alasan.
     *
     * @return list<string>
     */
    protected function kolomWajibAlasan(): array
    {
        return ['status', 'progres', 'tenggat', 'prioritas'];
    }

    protected function wajibAlasanUntuk(string $kolom, mixed $nilaiBaru): bool
    {
        $lama = $this->nilaiStatus($this->getOriginal('status'));
        $baru = $this->nilaiStatus($nilaiBaru);

        $lamaFinal = in_array($lama, [
            StatusPenugasan::Terverifikasi->value,
            StatusPenugasan::Ditutup->value,
            StatusPenugasan::Dibatalkan->value,
        ], true);

        if ($kolom === 'status') {
            return $lamaFinal || $baru === StatusPenugasan::Terverifikasi->value;
        }

        // Progres, tenggat, dan prioritas hanya dikunci setelah tugas final.
        return $lamaFinal;
    }

    private function nilaiStatus(mixed $status): ?string
    {
        if ($status instanceof StatusPenugasan) {
            return $status->value;
        }

        return $status === null ? null : (string) $status;
    }

    protected static function booted(): void
    {
        static::creating(function (self $assignment): void {
            $assignment->kode ??= self::nomorBerikutnya();
        });

        static::saving(function (self $assignment): void {
            if ($assignment->status === StatusPenugasan::Dibatalkan
                && trim((string) $assignment->alasan_pembatalan) === '') {
                throw new \DomainException('Penugasan yang dibatalkan wajib menyertakan alasan pembatalan.');
            }

            if ($assignment->status === StatusPenugasan::Terverifikasi && $assignment->diverifikasi_pada === null) {
                $assignment->diverifikasi_pada = ServerClock::now();
            }
        });
    }

    public static function nomorBerikutnya(): string
    {
        $tahun = ServerClock::now()->year;
        $awalan = sprintf('TGS-%d-', $tahun);

        $terakhir = static::query()
            ->where('kode', 'like', $awalan.'%')
            ->orderByDesc('id')
            ->value('kode');

        $urut = $terakhir === null ? 1 : ((int) substr((string) $terakhir, -4)) + 1;

        return $awalan.str_pad((string) $urut, 4, '0', STR_PAD_LEFT);
    }

    public function jobDescription(): BelongsTo
    {
        return $this->belongsTo(JobDescription::class);
    }

    public function pemberiTugas(): BelongsTo
    {
        return $this->belongsTo(Personel::class, 'pemberi_tugas_id');
    }

    public function diteruskanOleh(): BelongsTo
    {
        return $this->belongsTo(Personel::class, 'diteruskan_oleh_id');
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(Personel::class, 'pic_id');
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(Personel::class, 'diverifikasi_oleh');
    }

    public function dependensi(): BelongsTo
    {
        return $this->belongsTo(self::class, 'dependensi_id');
    }

    public function turunan(): HasMany
    {
        return $this->hasMany(self::class, 'dependensi_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function scopeTerlambat(Builder $query): Builder
    {
        return $query
            ->whereNotIn('status', [
                StatusPenugasan::Terverifikasi->value,
                StatusPenugasan::Ditutup->value,
                StatusPenugasan::Dibatalkan->value,
            ])
            ->where('tenggat', '<', ServerClock::now());
    }
}
