<?php

namespace App\Models;

use App\Enums\KategoriKpi;
use App\Enums\StatusKpi;
use App\Models\Concerns\Auditable;
use App\Support\ServerClock;
use Database\Factories\KpiResultFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiResult extends Model
{
    /** @use HasFactory<KpiResultFactory> */
    use Auditable, HasFactory;

    /**
     * Komponen KPI beserta bobot bawaannya (total 100).
     *
     * @var array<string, int>
     */
    public const KOMPONEN = [
        'pencapaian' => 35,
        'kualitas' => 25,
        'ketepatan_waktu' => 15,
        'tindak_lanjut' => 15,
        'kolaborasi' => 10,
    ];

    /** Kolom yang terkunci setelah status final. */
    private const KOLOM_TERKUNCI = [
        'skor_pencapaian',
        'skor_kualitas',
        'skor_ketepatan_waktu',
        'skor_tindak_lanjut',
        'skor_kolaborasi',
        'bobot_pencapaian',
        'bobot_kualitas',
        'bobot_ketepatan_waktu',
        'bobot_tindak_lanjut',
        'bobot_kolaborasi',
        'skor_total',
        'kategori',
        'periode',
        'personel_id',
        'rule_version',
    ];

    protected $fillable = [
        'personel_id',
        'periode',
        'skor_pencapaian',
        'skor_kualitas',
        'skor_ketepatan_waktu',
        'skor_tindak_lanjut',
        'skor_kolaborasi',
        'bobot_pencapaian',
        'bobot_kualitas',
        'bobot_ketepatan_waktu',
        'bobot_tindak_lanjut',
        'bobot_kolaborasi',
        'skor_total',
        'kategori',
        'status',
        'rule_version',
        'klarifikasi',
        'dihitung_oleh',
        'dikunci_oleh',
        'dikunci_pada',
    ];

    protected function casts(): array
    {
        return [
            'kategori' => KategoriKpi::class,
            'status' => StatusKpi::class,
            'skor_pencapaian' => 'integer',
            'skor_kualitas' => 'integer',
            'skor_ketepatan_waktu' => 'integer',
            'skor_tindak_lanjut' => 'integer',
            'skor_kolaborasi' => 'integer',
            'bobot_pencapaian' => 'integer',
            'bobot_kualitas' => 'integer',
            'bobot_ketepatan_waktu' => 'integer',
            'bobot_tindak_lanjut' => 'integer',
            'bobot_kolaborasi' => 'integer',
            'skor_total' => 'decimal:2',
            'dikunci_pada' => 'datetime',
        ];
    }

    /**
     * Skor, bobot, status, dan versi aturan menentukan nilai KPI resmi,
     * sehingga setiap perubahan wajib beralasan (fitur.pdf Tahap 4).
     *
     * @return list<string>
     */
    protected function kolomWajibAlasan(): array
    {
        return [
            'skor_pencapaian',
            'skor_kualitas',
            'skor_ketepatan_waktu',
            'skor_tindak_lanjut',
            'skor_kolaborasi',
            'bobot_pencapaian',
            'bobot_kualitas',
            'bobot_ketepatan_waktu',
            'bobot_tindak_lanjut',
            'bobot_kolaborasi',
            'status',
            'rule_version',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $kpi): void {
            if ($kpi->exists && $kpi->getOriginal('status') === StatusKpi::Final->value) {
                $berubah = array_keys($kpi->getDirty());

                if (array_intersect($berubah, self::KOLOM_TERKUNCI) !== []) {
                    throw new \DomainException('KPI yang sudah final terkunci dan tidak dapat diubah.');
                }
            }

            // Rekap selalu dihitung ulang dari skor & bobot komponen yang tersimpan.
            $kpi->skor_total = self::hitungSkorTotal($kpi->skorKomponen(), $kpi->bobotKomponen());
            $kpi->kategori = KategoriKpi::dariSkor((float) $kpi->skor_total);

            if ($kpi->status === StatusKpi::Final) {
                if ($kpi->dikunci_oleh === null) {
                    throw new \DomainException('KPI final wajib menyertakan pihak yang mengunci (dikunci_oleh).');
                }

                $kpi->dikunci_pada ??= ServerClock::now();
            }
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
            throw new \DomainException('Total bobot komponen KPI harus 100.');
        }

        $jumlah = 0.0;

        foreach (self::KOMPONEN as $komponen => $_) {
            $jumlah += ((float) ($skor[$komponen] ?? 0)) * ((float) ($bobot[$komponen] ?? 0));
        }

        return round($jumlah / 100, 2);
    }

    public static function periodeSekarang(): string
    {
        return ServerClock::now()->format('Y-m');
    }

    /** @return array<string, int|float> */
    public function skorKomponen(): array
    {
        return [
            'pencapaian' => $this->skor_pencapaian ?? 0,
            'kualitas' => $this->skor_kualitas ?? 0,
            'ketepatan_waktu' => $this->skor_ketepatan_waktu ?? 0,
            'tindak_lanjut' => $this->skor_tindak_lanjut ?? 0,
            'kolaborasi' => $this->skor_kolaborasi ?? 0,
        ];
    }

    /** @return array<string, int|float> */
    public function bobotKomponen(): array
    {
        return [
            'pencapaian' => $this->bobot_pencapaian ?? self::KOMPONEN['pencapaian'],
            'kualitas' => $this->bobot_kualitas ?? self::KOMPONEN['kualitas'],
            'ketepatan_waktu' => $this->bobot_ketepatan_waktu ?? self::KOMPONEN['ketepatan_waktu'],
            'tindak_lanjut' => $this->bobot_tindak_lanjut ?? self::KOMPONEN['tindak_lanjut'],
            'kolaborasi' => $this->bobot_kolaborasi ?? self::KOMPONEN['kolaborasi'],
        ];
    }

    public function personel(): BelongsTo
    {
        return $this->belongsTo(Personel::class);
    }

    public function penghitung(): BelongsTo
    {
        return $this->belongsTo(Personel::class, 'dihitung_oleh');
    }

    public function pengunci(): BelongsTo
    {
        return $this->belongsTo(Personel::class, 'dikunci_oleh');
    }

    public function scopePeriode(Builder $query, string $periode): Builder
    {
        return $query->where('periode', $periode);
    }

    public function scopeFinal(Builder $query): Builder
    {
        return $query->where('status', StatusKpi::Final->value);
    }
}
