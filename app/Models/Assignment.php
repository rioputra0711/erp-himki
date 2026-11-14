<?php

namespace App\Models;

use App\Enums\KategoriTugas;
use App\Enums\PeranSistem;
use App\Enums\PrioritasTugas;
use App\Enums\StatusPenugasan;
use App\Enums\SumberPerintah;
use App\Models\Concerns\Auditable;
use App\Support\CakupanPengguna;
use App\Support\IdentitasPelapor;
use App\Support\ServerClock;
use Database\Factories\AssignmentFactory;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;

class Assignment extends Model
{
    /** @use HasFactory<AssignmentFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'judul',
        'deskripsi',
        'job_description_id',
        'sumber_arahan_id',
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
        'alasan_penutupan',
        'bukti_penutupan',
        'bukti_pembatalan',
        'bukti_dokumen_id',
        'dependensi_id',
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
            return $lamaFinal || in_array($baru, [
                StatusPenugasan::Terverifikasi->value,
                StatusPenugasan::Ditutup->value,
                StatusPenugasan::Dibatalkan->value,
            ], true);
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
            $assignment->terapkanSumberPerintahSesi();
        });

        static::saving(function (self $assignment): void {
            $assignment->jagaKolomTerlacak();
            $assignment->jagaTransisiStatus();

            if ($assignment->status === StatusPenugasan::Dibatalkan) {
                if (trim((string) $assignment->alasan_pembatalan) === '') {
                    throw new DomainException('Penugasan yang dibatalkan wajib menyertakan alasan pembatalan.');
                }

                if (trim((string) $assignment->bukti_pembatalan) === '') {
                    throw new DomainException('Penugasan yang dibatalkan wajib menyertakan bukti pembatalan.');
                }
            }

            if ($assignment->status === StatusPenugasan::Terhambat
                && trim((string) $assignment->kendala) === '') {
                throw new DomainException('Status terhambat wajib menyertakan kendala.');
            }

            if ($assignment->status === StatusPenugasan::DiajukanSelesai && (int) $assignment->progres !== 100) {
                throw new DomainException('Pengajuan selesai hanya sah bila progres sudah 100%.');
            }

            if ($assignment->status === StatusPenugasan::Terverifikasi) {
                $assignment->diverifikasi_oleh ??= IdentitasPelapor::dariSesi()?->getKey();
                $assignment->diverifikasi_pada ??= ServerClock::now();
            }

            if ($assignment->status === StatusPenugasan::Ditutup) {
                if ((int) $assignment->progres !== 100) {
                    throw new DomainException('Penugasan hanya dapat ditutup setelah progres 100%.');
                }

                if (trim((string) $assignment->alasan_penutupan) === ''
                    || trim((string) $assignment->bukti_penutupan) === '') {
                    throw new DomainException('Penutupan penugasan wajib menyertakan alasan dan bukti.');
                }

                $assignment->ditutup_pada ??= ServerClock::now();
            }
        });
    }

    /**
     * Menolak lompatan status dan memastikan hanya pemegang izin verifikasi
     * yang dapat mengunci hasil kerja.
     */
    private function jagaTransisiStatus(): void
    {
        if (! $this->status instanceof StatusPenugasan) {
            return;
        }

        $lama = $this->exists
            ? StatusPenugasan::tryFrom((string) $this->getRawOriginal('status'))
            : null;

        if ($lama !== null && $lama !== $this->status && ! $lama->bolehKe($this->status)) {
            throw new DomainException(sprintf(
                'Transisi status %s ke %s tidak sah.',
                $lama->label(),
                $this->status->label(),
            ));
        }

        if (! in_array($this->status, [StatusPenugasan::Terverifikasi, StatusPenugasan::Ditutup], true)) {
            return;
        }

        $pengguna = Auth::user();

        if ($pengguna !== null && ! CakupanPengguna::bolehNamaIzin($pengguna, 'assignment.verify')) {
            throw new DomainException('Hanya pemegang izin verifikasi yang dapat memverifikasi atau menutup penugasan.');
        }
    }

    /**
     * Kolom jejak instruksi hanya ditulis sekali, oleh `creating`.
     *
     * Perubahan setelah baris ada akan memutus riwayat instruksi dari
     * pelakunya, jadi ditolak untuk semua jalur penulisan.
     */
    private function jagaKolomTerlacak(): void
    {
        if (! $this->exists) {
            return;
        }

        $terkunci = [
            'kode' => 'ID penugasan',
            'sumber_perintah' => 'Sumber perintah',
            'pemberi_tugas_id' => 'Pemberi tugas',
            'diteruskan_oleh_id' => 'Penerus arahan',
            'sumber_arahan_id' => 'Arahan asal',
        ];

        foreach ($terkunci as $kolom => $label) {
            if ($this->isDirty($kolom)) {
                throw new DomainException($label.' tidak dapat diubah setelah penugasan dibuat.');
            }
        }
    }

    /**
     * Menetapkan asal instruksi dari sesi, bukan dari formulir.
     *
     * Tiga jalur yang diakui (fitur.pdf Tahap 5):
     * 1. DE memberi tugas langsung → `de_langsung`;
     * 2. Pengurus memberi arahan, DE meneruskan → `pengurus_melalui_de`;
     * 3. Pengurus memberi tugas langsung ke staf → `pengurus_langsung`.
     */
    private function terapkanSumberPerintahSesi(): void
    {
        $pengguna = Auth::user();

        if ($pengguna === null) {
            // Seeder dan tes tanpa sesi memakai kolom apa adanya.
            return;
        }

        $personel = IdentitasPelapor::dariSesi($pengguna);

        if ($personel === null) {
            // Akun yang memang berwenang menerbitkan instruksi wajib tertaut
            // Master Personel; selain itu baris dianggap data awal (seeder,
            // fixture) dan kolomnya dibiarkan seperti diisi.
            if (CakupanPengguna::bolehNamaIzin($pengguna, 'assignment.create')) {
                throw new DomainException('Akun ini belum tertaut ke Master Personel, sehingga tidak dapat menerbitkan instruksi.');
            }

            return;
        }

        if (! CakupanPengguna::bolehNamaIzin($pengguna, 'assignment.create')) {
            // Peran tanpa izin menerbitkan tugas tidak pernah sampai ke sini
            // lewat HTTP (Policy menolak lebih dulu). Baris yang lahir dari
            // seeder atau factory dibiarkan memakai kolomnya sendiri.
            return;
        }

        if ($this->sumber_arahan_id !== null) {
            $arahan = Feedback::find($this->sumber_arahan_id);

            if ($arahan === null) {
                throw new DomainException('Arahan asal tidak ditemukan.');
            }

            $this->tetapkanSumberPerintah(
                SumberPerintah::PengurusMelaluiDe,
                Personel::find($arahan->pemberi_id),
                $personel,
            );

            return;
        }

        if ($pengguna->peranSistem() === PeranSistem::Pengurus) {
            $this->tetapkanSumberPerintah(SumberPerintah::PengurusLangsung, $personel);

            return;
        }

        if ($pengguna->peranSistem() === PeranSistem::DirekturEksekutif) {
            $this->tetapkanSumberPerintah(SumberPerintah::DeLangsung, $personel);

            return;
        }

        throw new DomainException('Peran ini tidak berwenang menerbitkan penugasan.');
    }

    public function tetapkanSumberPerintah(SumberPerintah $sumber, ?Personel $pemberi, ?Personel $penerus = null): static
    {
        $this->setAttribute('sumber_perintah', $sumber);
        $this->setAttribute('pemberi_tugas_id', $pemberi?->getKey());
        $this->setAttribute('diteruskan_oleh_id', $penerus?->getKey());

        return $this;
    }

    /**
     * Menyimpan tugas dengan kode urut, mengulang bila dua pembuat bertabrakan
     * pada nomor yang sama.
     *
     * @param  callable(self): void  $isi
     */
    public static function buatDenganKodeUnik(callable $isi): self
    {
        $percobaan = 0;

        while (true) {
            $percobaan++;

            $tugas = new self;
            $isi($tugas);

            try {
                $tugas->save();

                return $tugas;
            } catch (UniqueConstraintViolationException $e) {
                if ($percobaan >= 3) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Apakah menautkan tugas ini ke `$id` akan membentuk lingkaran dependensi.
     */
    public function dependensiBerputar(?int $id): bool
    {
        if ($id === null) {
            return false;
        }

        $dikunjungi = [];
        $sekarang = $id;

        for ($langkah = 0; $langkah < 20 && $sekarang !== null; $langkah++) {
            if ($sekarang === (int) $this->getKey()) {
                return true;
            }

            if (isset($dikunjungi[$sekarang])) {
                return true;
            }

            $dikunjungi[$sekarang] = true;
            $sekarang = static::query()->whereKey($sekarang)->value('dependensi_id');
            $sekarang = $sekarang === null ? null : (int) $sekarang;
        }

        return false;
    }

    /**
     * Batas baris untuk akun Staf: hanya tugas yang menjadi tanggung jawabnya.
     */
    public function scopeTerlihatOleh(Builder $query, ?User $pengguna): Builder
    {
        if ($pengguna === null || ! CakupanPengguna::adalahStaf($pengguna)) {
            return $query;
        }

        return $query->where('pic_id', CakupanPengguna::personelId($pengguna) ?? 0);
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

    public function sumberArahan(): BelongsTo
    {
        return $this->belongsTo(Feedback::class, 'sumber_arahan_id');
    }

    public function buktiDokumen(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'bukti_dokumen_id');
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
