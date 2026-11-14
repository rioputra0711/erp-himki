<?php

namespace App\Models;

use App\Enums\KategoriSkor;
use App\Models\Concerns\Auditable;
use App\Support\ServerClock;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use Auditable, HasFactory;

    /**
     * Komponen penilaian beserta bobot bawaannya (total 100).
     *
     * @var array<string, int>
     */
    public const KOMPONEN = [
        'kelengkapan' => 20,
        'kejelasan' => 25,
        'akurasi' => 25,
        'sistematika' => 15,
        'tindak_lanjut' => 15,
    ];

    protected $fillable = [
        'report_id',
        'dokumen_id',
        'pemberi_review_id',
        'skor_kelengkapan',
        'skor_kejelasan',
        'skor_akurasi',
        'skor_sistematika',
        'skor_tindak_lanjut',
        'bobot_kelengkapan',
        'bobot_kejelasan',
        'bobot_akurasi',
        'bobot_sistematika',
        'bobot_tindak_lanjut',
        'skor_total',
        'kategori',
        'catatan_kekuatan',
        'kekurangan_spesifik',
        'rekomendasi_perbaikan',
        'versi_review',
        'adalah_final',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'kategori' => KategoriSkor::class,
            'skor_kelengkapan' => 'integer',
            'skor_kejelasan' => 'integer',
            'skor_akurasi' => 'integer',
            'skor_sistematika' => 'integer',
            'skor_tindak_lanjut' => 'integer',
            'bobot_kelengkapan' => 'integer',
            'bobot_kejelasan' => 'integer',
            'bobot_akurasi' => 'integer',
            'bobot_sistematika' => 'integer',
            'bobot_tindak_lanjut' => 'integer',
            'skor_total' => 'decimal:2',
            'versi_review' => 'integer',
            'adalah_final' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * Skor dan bobot menentukan nilai akhir personel, sehingga setiap
     * perubahan wajib beralasan (fitur.pdf Tahap 4).
     *
     * @return list<string>
     */
    protected function kolomWajibAlasan(): array
    {
        return [
            'skor_kelengkapan',
            'skor_kejelasan',
            'skor_akurasi',
            'skor_sistematika',
            'skor_tindak_lanjut',
            'bobot_kelengkapan',
            'bobot_kejelasan',
            'bobot_akurasi',
            'bobot_sistematika',
            'bobot_tindak_lanjut',
            'adalah_final',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $review): void {
            // Angka rekapitulasi selalu dihitung ulang dari skor dan bobot komponen,
            // supaya tidak ada skor_total yang tidak dapat ditelusuri ke sumbernya.
            $review->skor_total = self::hitungSkorTotal($review->skorKomponen(), $review->bobotKomponen());
            $review->kategori = KategoriSkor::dariSkor((float) $review->skor_total);

            $review->reviewed_at ??= ServerClock::now();
            $review->versi_review ??= self::versiBerikutnya((int) $review->report_id);
        });

        static::saved(function (self $review): void {
            if (! $review->adalah_final) {
                return;
            }

            // Hanya satu review final per laporan; versi final lama diturunkan tanpa memicu event model.
            static::query()
                ->where('report_id', $review->report_id)
                ->whereKeyNot($review->getKey())
                ->where('adalah_final', true)
                ->toBase()
                ->update(['adalah_final' => false, 'updated_at' => ServerClock::now()]);
        });
    }

    /**
     * @param  array<string, int|float>  $skor
     * @param  array<string, int|float>  $bobot
     */
    public static function hitungSkorTotal(array $skor, array $bobot): float
    {
        $totalBobot = array_sum($bobot);

        if ((int) round($totalBobot) !== 100) {
            throw new \DomainException('Total bobot komponen review harus 100.');
        }

        $jumlah = 0.0;

        foreach (self::KOMPONEN as $komponen => $_) {
            $jumlah += ((float) ($skor[$komponen] ?? 0)) * ((float) ($bobot[$komponen] ?? 0));
        }

        return round($jumlah / 100, 2);
    }

    public static function versiBerikutnya(int $reportId): int
    {
        return (int) static::query()->where('report_id', $reportId)->max('versi_review') + 1;
    }

    /** @return array<string, int|float> */
    public function skorKomponen(): array
    {
        return [
            'kelengkapan' => $this->skor_kelengkapan ?? 0,
            'kejelasan' => $this->skor_kejelasan ?? 0,
            'akurasi' => $this->skor_akurasi ?? 0,
            'sistematika' => $this->skor_sistematika ?? 0,
            'tindak_lanjut' => $this->skor_tindak_lanjut ?? 0,
        ];
    }

    /** @return array<string, int|float> */
    public function bobotKomponen(): array
    {
        return [
            'kelengkapan' => $this->bobot_kelengkapan ?? self::KOMPONEN['kelengkapan'],
            'kejelasan' => $this->bobot_kejelasan ?? self::KOMPONEN['kejelasan'],
            'akurasi' => $this->bobot_akurasi ?? self::KOMPONEN['akurasi'],
            'sistematika' => $this->bobot_sistematika ?? self::KOMPONEN['sistematika'],
            'tindak_lanjut' => $this->bobot_tindak_lanjut ?? self::KOMPONEN['tindak_lanjut'],
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function dokumen(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'dokumen_id');
    }

    public function pemberiReview(): BelongsTo
    {
        return $this->belongsTo(Personel::class, 'pemberi_review_id');
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(ActionItem::class);
    }

    public function scopeFinal(Builder $query): Builder
    {
        return $query->where('adalah_final', true);
    }
}
