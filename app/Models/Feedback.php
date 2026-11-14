<?php

namespace App\Models;

use App\Enums\AsalFeedback;
use App\Enums\JenisFeedback;
use App\Enums\PeranSistem;
use App\Enums\StatusActionItem;
use App\Enums\StatusFeedback;
use App\Models\Concerns\Auditable;
use App\Support\CakupanPengguna;
use App\Support\IdentitasPelapor;
use Database\Factories\FeedbackFactory;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

class Feedback extends Model
{
    /** @use HasFactory<FeedbackFactory> */
    use Auditable, HasFactory;

    protected $table = 'feedback';

    protected $fillable = [
        'pemberi_id',
        'penerima_id',
        'jenis',
        'asal',
        'subjek_type',
        'subjek_id',
        'isi',
        'lampiran_dokumen_id',
        'butuh_tindakan',
        'status',
        'tenggat',
    ];

    protected function casts(): array
    {
        return [
            'jenis' => JenisFeedback::class,
            'asal' => AsalFeedback::class,
            'status' => StatusFeedback::class,
            'butuh_tindakan' => 'boolean',
            'tenggat' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $feedback): void {
            $feedback->kunciIdentitasPemberi();

            if ($feedback->butuh_tindakan && $feedback->penerima_id === null) {
                throw new DomainException('Feedback yang butuh tindakan wajib memiliki penerima.');
            }

            // Status tindak lanjut tidak boleh lebih maju dari keberadaan penerima.
            if ($feedback->status === StatusFeedback::Ditindaklanjuti && $feedback->penerima_id === null) {
                throw new DomainException('Feedback tanpa penerima tidak dapat ditandai ditindaklanjuti.');
            }

            $feedback->jagaPersetujuanFormal();
        });
    }

    /**
     * Mengunci identitas pemberi arahan dari sesi (fitur.pdf Tahap 5).
     *
     * Nama, jabatan, dan asal arahan tidak boleh berasal dari isian formulir:
     * riwayat instruksi harus dapat diaudit ke akun yang benar-benar
     * menekan tombol kirim. Nilai yang diselipkan formulir ditimpa di sini.
     */
    private function kunciIdentitasPemberi(): void
    {
        $pengguna = Auth::user();

        if ($pengguna === null) {
            // Seeder, factory, dan tes tanpa sesi memakai kolom apa adanya.
            return;
        }

        if (! CakupanPengguna::bolehNamaIzin($pengguna, 'feedback.create')) {
            // Peran tanpa izin memberi arahan tidak pernah sampai ke sini lewat
            // HTTP (Policy menolak lebih dulu); baris dari seeder dibiarkan.
            return;
        }

        $personel = IdentitasPelapor::dariSesi($pengguna);

        if ($personel === null) {
            throw new DomainException('Akun ini belum tertaut ke Master Personel, sehingga tidak dapat memberi arahan.');
        }

        $this->forceFill([
            'pemberi_id' => $personel->getKey(),
            'pemberi_jabatan' => $personel->jabatan?->nama,
            'asal' => $pengguna->peranSistem() === PeranSistem::Pengurus
                ? AsalFeedback::Pengurus
                : AsalFeedback::De,
        ]);
    }

    /**
     * Persetujuan formal adalah keputusan, bukan komentar.
     *
     * Karena itu ia hanya boleh berupa baris baru dari Pengurus, dan tidak
     * boleh diboncengi permintaan tindakan. Mengubah `jenis` baris lama
     * menjadi persetujuan akan menciptakan keputusan yang tidak pernah
     * benar-benar diberikan, jadi ditolak.
     */
    private function jagaPersetujuanFormal(): void
    {
        if ($this->jenis !== JenisFeedback::Persetujuan) {
            return;
        }

        if ($this->exists && $this->isDirty('jenis')) {
            throw new DomainException('Persetujuan formal harus berupa baris baru, bukan perubahan jenis arahan yang sudah ada.');
        }

        if ($this->asal !== AsalFeedback::Pengurus) {
            throw new DomainException('Persetujuan formal hanya boleh diberikan Pengurus.');
        }

        if ($this->butuh_tindakan) {
            throw new DomainException('Persetujuan formal tidak dapat sekaligus meminta tindakan.');
        }
    }

    public function subjek(): MorphTo
    {
        return $this->morphTo();
    }

    public function pemberi(): BelongsTo
    {
        return $this->belongsTo(Personel::class, 'pemberi_id');
    }

    public function penerima(): BelongsTo
    {
        return $this->belongsTo(Personel::class, 'penerima_id');
    }

    public function lampiran(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'lampiran_dokumen_id');
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(ActionItem::class);
    }

    public function scopeTerbuka(Builder $query): Builder
    {
        return $query->where('status', StatusFeedback::Terbuka->value);
    }

    public function scopeButuhTindakan(Builder $query): Builder
    {
        return $query->where('butuh_tindakan', true);
    }

    /**
     * Batas baris arahan: Staf hanya melihat arahan yang ia beri atau terima.
     *
     * Arahan langsung Pengurus ke staf tetap terlihat DE karena DE tidak
     * dibatasi di sini (fitur.pdf Tahap 5 — "arahan langsung selalu terlihat DE").
     */
    public function scopeTerlihatOleh(Builder $query, ?User $pengguna): Builder
    {
        if ($pengguna === null || ! CakupanPengguna::adalahStaf($pengguna)) {
            return $query;
        }

        $personelId = CakupanPengguna::personelId($pengguna) ?? 0;

        return $query->where(function (Builder $q) use ($personelId): void {
            $q->where('pemberi_id', $personelId)->orWhere('penerima_id', $personelId);
        });
    }

    /**
     * Arahan hanya boleh ditutup setelah tindak lanjutnya tuntas.
     *
     * Item yang sudah terverifikasi selesai atau ditutup tidak lagi dihitung
     * sebagai tunggakan (lihat StatusActionItem::isTunggakan()).
     */
    public function bolehDitutup(): bool
    {
        return ! $this->actionItems()
            ->whereNotIn('status', [
                StatusActionItem::TerverifikasiSelesai->value,
                StatusActionItem::Ditutup->value,
            ])
            ->exists();
    }
}
