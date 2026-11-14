<?php

namespace App\Support;

use App\Models\ActionItem;
use App\Models\ActivityLog;
use App\Models\Assignment;
use App\Models\Bidang;
use App\Models\Document;
use App\Models\Feedback;
use App\Models\Jabatan;
use App\Models\JobDescription;
use App\Models\KpiResult;
use App\Models\Meeting;
use App\Models\Personel;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Support\Str;

/**
 * Penerjemah jejak audit ke bahasa manusia.
 *
 * Jejak audit disimpan apa adanya (nama kelas, nama kolom, nilai mentah) agar
 * tetap sahih walau label berubah. Penerjemahan hanya terjadi saat ditampilkan,
 * sehingga memperbaiki label tidak pernah menyentuh baris audit.
 */
final class LabelAudit
{
    /** Kolom yang isinya tidak boleh tampil utuh di layar audit. */
    public const SENSITIF = [
        'nip',
        'telepon',
        'password',
        'remember_token',
        'klarifikasi',
    ];

    /**
     * Nama pendek untuk filter `objek` => kelas model.
     *
     * @var array<string, class-string>
     */
    public const KELAS = [
        'assignment' => Assignment::class,
        'activity_log' => ActivityLog::class,
        'report' => Report::class,
        'document' => Document::class,
        'review' => Review::class,
        'feedback' => Feedback::class,
        'action_item' => ActionItem::class,
        'kpi' => KpiResult::class,
        'meeting' => Meeting::class,
        'personel' => Personel::class,
        'jabatan' => Jabatan::class,
        'bidang' => Bidang::class,
        'jd' => JobDescription::class,
        'user' => User::class,
    ];

    /**
     * Kolom yang dipakai untuk menyebut satu baris objek pada daftar audit.
     *
     * @var array<class-string, string>
     */
    public const KOLOM_RINGKAS = [
        Assignment::class => 'judul',
        ActivityLog::class => 'uraian',
        Report::class => 'judul',
        Document::class => 'judul',
        Review::class => 'versi_review',
        Feedback::class => 'isi',
        ActionItem::class => 'deskripsi',
        KpiResult::class => 'periode',
        Meeting::class => 'judul',
        Personel::class => 'nama_lengkap',
        Jabatan::class => 'nama',
        Bidang::class => 'nama',
        JobDescription::class => 'kode',
        User::class => 'name',
    ];

    /**
     * Tindakan yang dapat dipilih pada filter.
     *
     * @return list<string>
     */
    public static function pilihanTindakan(): array
    {
        return [
            'created',
            'updated',
            'deleted',
            'auth.login',
            'auth.login_gagal',
            'auth.logout',
            'auth.akses_ditolak',
            'auth.kata_sandi_diubah',
            'auth.kata_sandi_diatur_ulang',
            'auth.login_diblokir',
            'akses.peran_diberikan',
            'akses.peran_dicabut',
            'akses.izin_diberikan',
            'akses.izin_dicabut',
            'ekspor.audit',
            'identitas.ditolak',
        ];
    }

    public static function tindakan(string $event): string
    {
        return match ($event) {
            'created' => 'Dibuat',
            'updated' => 'Diubah',
            'deleted' => 'Dihapus',
            'auth.login' => 'Login berhasil',
            'auth.login_gagal' => 'Login gagal',
            'auth.logout' => 'Logout',
            'auth.akses_ditolak' => 'Akses ditolak',
            'auth.kata_sandi_diubah' => 'Kata sandi diubah',
            'auth.kata_sandi_diatur_ulang' => 'Kata sandi diatur ulang',
            'auth.login_diblokir' => 'Login diblokir',
            'akses.peran_diberikan' => 'Peran diberikan',
            'akses.peran_dicabut' => 'Peran dicabut',
            'akses.izin_diberikan' => 'Izin diberikan',
            'akses.izin_dicabut' => 'Izin dicabut',
            'ekspor.audit' => 'Ekspor audit',
            'identitas.ditolak' => 'Pemalsuan identitas ditolak',
            default => Str::startsWith($event, 'ekspor.')
                ? 'Ekspor '.$event
                : $event,
        };
    }

    public static function objek(?string $kelas): string
    {
        if ($kelas === null) {
            return '—';
        }

        $pendek = array_search($kelas, self::KELAS, true);

        if ($pendek === false) {
            return Str::afterLast($kelas, '\\');
        }

        return self::labelObjek($pendek);
    }

    public static function labelObjek(string $pendek): string
    {
        return match ($pendek) {
            'assignment' => 'Penugasan',
            'activity_log' => 'Log Aktivitas',
            'report' => 'Laporan',
            'document' => 'Dokumen',
            'review' => 'Review & Skor',
            'feedback' => 'Arahan',
            'action_item' => 'Tindak Lanjut',
            'kpi' => 'KPI',
            'meeting' => 'Rapat',
            'personel' => 'Personel',
            'jabatan' => 'Jabatan',
            'bidang' => 'Bidang Kerja',
            'jd' => 'Job Description',
            'user' => 'Akun',
            'akses' => 'Hak Akses',
            default => Str::headline($pendek),
        };
    }

    public static function kolom(string $kolom): string
    {
        return match ($kolom) {
            'status' => 'Status',
            'status_aktif' => 'Status aktif',
            'skor_total' => 'Skor total',
            'kategori' => 'Kategori',
            'progres' => 'Progres',
            'tenggat' => 'Tenggat',
            'prioritas' => 'Prioritas',
            'target' => 'Target',
            'indikator' => 'Indikator',
            'uraian' => 'Uraian',
            'frekuensi' => 'Frekuensi',
            'berlaku_mulai' => 'Berlaku mulai',
            'ditetapkan_pada' => 'Ditetapkan pada',
            'klasifikasi' => 'Klasifikasi',
            'reason' => 'Alasan',
            'alasan_pembatalan' => 'Alasan pembatalan',
            'nip' => 'ID personel',
            'telepon' => 'Telepon',
            'nama_lengkap' => 'Nama resmi',
            'is_active' => 'Aktif',
            'adalah_final' => 'Review final',
            'rule_version' => 'Versi aturan',
            default => Str::headline($kolom),
        };
    }

    public static function nilai(mixed $nilai, ?string $kelas = null, ?string $kolom = null): string
    {
        if ($nilai === null) {
            return '—';
        }

        if ($nilai instanceof BackedEnum) {
            return method_exists($nilai, 'label') ? $nilai->label() : (string) $nilai->value;
        }

        if (is_string($nilai) && $kelas !== null && $kolom !== null) {
            $enum = self::enumUntuk($kelas, $kolom);

            if ($enum !== null) {
                $kasus = $enum::tryFrom($nilai);

                if ($kasus !== null) {
                    return method_exists($kasus, 'label') ? $kasus->label() : (string) $kasus->value;
                }
            }
        }

        if (is_bool($nilai)) {
            return $nilai ? 'Ya' : 'Tidak';
        }

        if ($nilai instanceof DateTimeInterface) {
            return $nilai->format('d/m/Y H:i');
        }

        if (is_array($nilai)) {
            return json_encode($nilai, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '—';
        }

        return (string) $nilai;
    }

    /**
     * Kelas enum dari cast kolom model, bila ada.
     *
     * Nilai audit disimpan sebagai JSON mentah; label enum direkonstruksi dari
     * deklarasi cast model agar penambahan kolom baru tidak perlu didaftarkan
     * ulang di sini.
     *
     * @return class-string<BackedEnum>|null
     */
    private static function enumUntuk(string $kelas, string $kolom): ?string
    {
        /** @var array<string, array<string, string>> $cache */
        static $cache = [];

        if (! isset($cache[$kelas])) {
            $cache[$kelas] = class_exists($kelas) ? (new $kelas)->getCasts() : [];
        }

        $cast = $cache[$kelas][$kolom] ?? null;

        return is_string($cast) && enum_exists($cast) ? $cast : null;
    }

    /**
     * Samarkan kolom sensitif pada potongan data audit.
     *
     * @param  array<string, mixed>  $baris
     * @return array<string, mixed>
     */
    public static function samarkan(array $baris): array
    {
        foreach (self::SENSITIF as $kolom) {
            if (array_key_exists($kolom, $baris)) {
                $baris[$kolom] = '••••';
            }
        }

        return $baris;
    }
}
