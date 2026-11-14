<?php

namespace Database\Seeders;

use App\Enums\FrekuensiTugas;
use App\Enums\KategoriTugas;
use App\Models\Jabatan;
use App\Models\JobDescription;
use App\Support\ServerClock;
use Illuminate\Database\Seeder;

/**
 * Job Description awal per jabatan (fitur.pdf Tahap 3).
 *
 * Setiap baris sudah ditetapkan dan berlaku sejak awal tahun berjalan, sehingga
 * standar evaluasi tidak berubah di tengah periode penilaian. Perubahan target
 * atau indikator harus dibuat sebagai versi baru.
 */
class JobDescriptionSeeder extends Seeder
{
    /**
     * @var array<int, array{kode: string, jabatan: string, kategori: KategoriTugas, uraian: string, indikator: string, target: string, frekuensi: FrekuensiTugas}>
     */
    private const DAFTAR = [
        [
            'kode' => 'JD-DE-01',
            'jabatan' => 'DE',
            'kategori' => KategoriTugas::Utama,
            'uraian' => 'Mengoordinasikan seluruh operasional Sekretariat/Badan Eksekutif dan meneruskan arahan pengurus menjadi penugasan.',
            'indikator' => 'Seluruh arahan pengurus memiliki penugasan turunan dengan PIC dan tenggat yang jelas.',
            'target' => '100% arahan pengurus diteruskan dalam 3 hari kerja.',
            'frekuensi' => FrekuensiTugas::Bulanan,
        ],
        [
            'kode' => 'JD-DE-02',
            'jabatan' => 'DE',
            'kategori' => KategoriTugas::Umum,
            'uraian' => 'Memeriksa laporan dan bukti kerja personel, memberi skor kualitas, serta menetapkan tindak lanjut.',
            'indikator' => 'Setiap laporan yang diajukan memperoleh review berskor dan rekomendasi.',
            'target' => '100% laporan direview maksimal 3 hari setelah diajukan.',
            'frekuensi' => FrekuensiTugas::Mingguan,
        ],
        [
            'kode' => 'JD-DE-03',
            'jabatan' => 'DE',
            'kategori' => KategoriTugas::Insidental,
            'uraian' => 'Menangani kendala dini yang dieskalasi personel dan menetapkan keputusan operasional.',
            'indikator' => 'Kendala dini memperoleh keputusan dan tercatat pada tindak lanjut.',
            'target' => 'Keputusan diberikan maksimal 2 hari kerja sejak eskalasi.',
            'frekuensi' => FrekuensiTugas::Insidental,
        ],
        [
            'kode' => 'JD-KEUANGAN-01',
            'jabatan' => 'STAF_KEUANGAN',
            'kategori' => KategoriTugas::Utama,
            'uraian' => 'Menyusun rencana anggaran dan laporan realisasi keuangan bidang.',
            'indikator' => 'Laporan realisasi lengkap, akurat, dan disertai bukti.',
            'target' => 'Laporan realisasi bulanan selesai sebelum tanggal 10.',
            'frekuensi' => FrekuensiTugas::Bulanan,
        ],
        [
            'kode' => 'JD-KEUANGAN-02',
            'jabatan' => 'STAF_KEUANGAN',
            'kategori' => KategoriTugas::Umum,
            'uraian' => 'Mengarsipkan bukti pengeluaran dan memutakhirkan buku kas.',
            'indikator' => 'Seluruh transaksi memiliki bukti yang tertaut pada arsip digital.',
            'target' => 'Tidak ada transaksi tanpa bukti pada akhir bulan.',
            'frekuensi' => FrekuensiTugas::Harian,
        ],
        [
            'kode' => 'JD-ADMINISTRASI-01',
            'jabatan' => 'STAF_ADMINISTRASI',
            'kategori' => KategoriTugas::Utama,
            'uraian' => 'Mengelola surat masuk/keluar, arsip digital, dan administrasi kesekretariatan.',
            'indikator' => 'Surat tercatat dengan nomor, tanggal, dan tautan arsip.',
            'target' => '100% surat terarsip pada hari yang sama.',
            'frekuensi' => FrekuensiTugas::Harian,
        ],
        [
            'kode' => 'JD-ADMINISTRASI-02',
            'jabatan' => 'STAF_ADMINISTRASI',
            'kategori' => KategoriTugas::Insidental,
            'uraian' => 'Menyiapkan administrasi rapat dan risalah sesuai SLA.',
            'indikator' => 'Risalah singkat dan lengkap terbit tepat waktu.',
            'target' => 'Risalah singkat 1 jam dan lengkap 16 jam setelah rapat.',
            'frekuensi' => FrekuensiTugas::Insidental,
        ],
        [
            'kode' => 'JD-PUBLIKASI-01',
            'jabatan' => 'STAF_PUBLIKASI',
            'kategori' => KategoriTugas::Utama,
            'uraian' => 'Menyusun rencana publikasi dan mengelola kanal komunikasi organisasi.',
            'indikator' => 'Konten terbit sesuai kalender publikasi dan brief.',
            'target' => 'Minimal 8 publikasi terjadwal per bulan.',
            'frekuensi' => FrekuensiTugas::Bulanan,
        ],
        [
            'kode' => 'JD-PUBLIKASI-02',
            'jabatan' => 'STAF_PUBLIKASI',
            'kategori' => KategoriTugas::Umum,
            'uraian' => 'Mendokumentasikan kegiatan dan menyiapkan bahan publikasi.',
            'indikator' => 'Dokumentasi kegiatan tersedia lengkap dengan metadata.',
            'target' => 'Dokumentasi diunggah maksimal 2 hari setelah kegiatan.',
            'frekuensi' => FrekuensiTugas::Mingguan,
        ],
        [
            'kode' => 'JD-HI-01',
            'jabatan' => 'STAF_HI',
            'kategori' => KategoriTugas::Utama,
            'uraian' => 'Menyiapkan bahan hubungan internasional dan koordinasi pameran.',
            'indikator' => 'Bahan dan rundown pameran tersedia serta disetujui DE.',
            'target' => 'Bahan final H-7 sebelum kegiatan.',
            'frekuensi' => FrekuensiTugas::Bulanan,
        ],
        [
            'kode' => 'JD-HI-02',
            'jabatan' => 'STAF_HI',
            'kategori' => KategoriTugas::Insidental,
            'uraian' => 'Menyusun laporan pelaksanaan pameran dan evaluasi mitra.',
            'indikator' => 'Laporan pameran memuat capaian, kendala, dan rekomendasi.',
            'target' => 'Laporan terbit maksimal 5 hari setelah pameran.',
            'frekuensi' => FrekuensiTugas::Insidental,
        ],
        [
            'kode' => 'JD-TI-01',
            'jabatan' => 'STAF_TI',
            'kategori' => KategoriTugas::Utama,
            'uraian' => 'Mengelola akun, perangkat, dan dukungan teknis aplikasi ERP.',
            'indikator' => 'Permintaan dukungan tercatat dan diselesaikan sesuai prioritas.',
            'target' => '90% tiket dukungan selesai maksimal 2 hari kerja.',
            'frekuensi' => FrekuensiTugas::Harian,
        ],
        [
            'kode' => 'JD-TI-02',
            'jabatan' => 'STAF_TI',
            'kategori' => KategoriTugas::Umum,
            'uraian' => 'Memelihara cadangan data dan memantau keamanan akun.',
            'indikator' => 'Cadangan data tersedia dan akses akun sesuai matriks peran.',
            'target' => 'Cadangan mingguan tanpa kegagalan.',
            'frekuensi' => FrekuensiTugas::Mingguan,
        ],
        [
            'kode' => 'JD-OPERASIONAL-01',
            'jabatan' => 'STAF_OPERASIONAL',
            'kategori' => KategoriTugas::Utama,
            'uraian' => 'Menyiapkan kebutuhan logistik dan operasional kegiatan.',
            'indikator' => 'Kebutuhan kegiatan tersedia sesuai jadwal dan anggaran.',
            'target' => 'Tidak ada kekurangan logistik pada hari pelaksanaan.',
            'frekuensi' => FrekuensiTugas::Mingguan,
        ],
        [
            'kode' => 'JD-OPERASIONAL-02',
            'jabatan' => 'STAF_OPERASIONAL',
            'kategori' => KategoriTugas::Umum,
            'uraian' => 'Merawat inventaris sekretariat dan mencatat mutasi barang.',
            'indikator' => 'Catatan inventaris mutakhir dan sesuai fisik.',
            'target' => 'Selisih inventaris 0% pada pemeriksaan bulanan.',
            'frekuensi' => FrekuensiTugas::Bulanan,
        ],
    ];

    public function run(): void
    {
        $mulai = ServerClock::now()->startOfYear();

        foreach (self::DAFTAR as $jd) {
            $jabatanId = Jabatan::query()->where('kode', $jd['jabatan'])->value('id');

            if ($jabatanId === null) {
                continue;
            }

            $jdModel = JobDescription::query()->firstOrNew(
                ['jabatan_id' => $jabatanId, 'kode' => $jd['kode'], 'versi' => 1],
            );

            // Job Description yang sudah ditetapkan bersifat beku; seeder tidak
            // boleh menimpanya (perubahan hanya lewat versi baru).
            if ($jdModel->exists && $jdModel->ditetapkan_pada !== null) {
                continue;
            }

            $jdModel->fill([
                'kategori' => $jd['kategori'],
                'uraian' => $jd['uraian'],
                'indikator' => $jd['indikator'],
                'target' => $jd['target'],
                'frekuensi' => $jd['frekuensi'],
                'berlaku_mulai' => $mulai->toDateString(),
                'ditetapkan_pada' => $mulai,
                'is_active' => true,
            ])->denganAlasan('Data awal job description')->save();
        }
    }
}
