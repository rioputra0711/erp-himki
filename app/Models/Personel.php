<?php

namespace App\Models;

use App\Enums\KategoriPersonel;
use App\Models\Concerns\Auditable;
use App\Support\CakupanPengguna;
use Database\Factories\PersonelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Personel extends Model
{
    /** @use HasFactory<PersonelFactory> */
    use Auditable, HasFactory;

    protected $table = 'personel';

    protected $fillable = [
        'user_id',
        'nip',
        'nama_lengkap',
        'jabatan_id',
        'bidang_id',
        'kategori',
        'status_aktif',
        'tanggal_masuk',
        'telepon',
    ];

    protected function casts(): array
    {
        return [
            'kategori' => KategoriPersonel::class,
            'status_aktif' => 'boolean',
            'tanggal_masuk' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class);
    }

    public function bidang(): BelongsTo
    {
        return $this->belongsTo(Bidang::class);
    }

    public function penugasanSebagaiPic(): HasMany
    {
        return $this->hasMany(Assignment::class, 'pic_id');
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
        return $this->hasMany(Document::class, 'pemilik_id');
    }

    public function kpiResults(): HasMany
    {
        return $this->hasMany(KpiResult::class);
    }

    public function feedbackDiterima(): HasMany
    {
        return $this->hasMany(Feedback::class, 'penerima_id');
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(ActionItem::class, 'pic_id');
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status_aktif', true);
    }

    public function scopeKategori(Builder $query, KategoriPersonel $kategori): Builder
    {
        return $query->where('kategori', $kategori->value);
    }

    /**
     * Batasi kueri pada baris yang boleh dibaca akun ini.
     *
     * Staf hanya melihat barisnya sendiri; peran lain mengikuti izin modul yang
     * sudah diperiksa middleware. Dipakai daftar master agar tautan langsung ke
     * baris orang lain tetap ditolak Policy, bukan hanya disembunyikan di UI.
     */
    public function scopeTerlihatOleh(Builder $query, ?User $user): Builder
    {
        if ($user === null || ! CakupanPengguna::adalahStaf($user)) {
            return $query;
        }

        return $query->whereKey(CakupanPengguna::personelId($user) ?? 0);
    }

    /**
     * Identitas resmi untuk ditampilkan pada laporan (fitur.pdf Tahap 3).
     *
     * @return array{nama: string, jabatan: ?string, bidang: ?string}
     */
    public function identitas(): array
    {
        return [
            'nama' => $this->nama_lengkap,
            'jabatan' => $this->jabatan?->nama,
            'bidang' => $this->bidang?->nama,
        ];
    }
}
