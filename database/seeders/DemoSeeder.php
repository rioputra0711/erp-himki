<?php

namespace Database\Seeders;

use App\Enums\AsalFeedback;
use App\Enums\JenisFeedback;
use App\Enums\JenisLaporan;
use App\Enums\KategoriDokumen;
use App\Enums\KategoriPersonel;
use App\Enums\KategoriTugas;
use App\Enums\KlasifikasiDokumen;
use App\Enums\PeranSistem;
use App\Enums\PrioritasTugas;
use App\Enums\StatusDokumen;
use App\Enums\StatusKpi;
use App\Enums\StatusLaporan;
use App\Enums\StatusPenugasan;
use App\Enums\SumberPerintah;
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
use App\Support\ServerClock;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = $this->buatPersonel('admin', 'Administrator Aplikasi', 'STAF_UMUM', 'UMUM', PeranSistem::Admin);
        $pengurus = $this->buatPersonel('ketua', 'Ketua Umum HIMKI', 'KETUM', null, PeranSistem::Pengurus);
        $de = $this->buatPersonel('direktur', 'Direktur Eksekutif', 'DE', 'PROGRAM', PeranSistem::DirekturEksekutif);
        $staf = $this->buatPersonel('staf', 'Staf Program', 'STAF_PROGRAM', 'PROGRAM', PeranSistem::Staf);

        $jd = JobDescription::query()->updateOrCreate(
            ['kode' => 'JD-PROGRAM-01'],
            [
                'jabatan_id' => Jabatan::query()->where('kode', 'STAF_PROGRAM')->value('id'),
                'kategori' => KategoriTugas::Utama,
                'uraian' => 'Menyusun dan melaksanakan program kerja bidang sesuai arahan DE.',
                'indikator' => 'Program terlaksana sesuai target keluaran dan tenggat.',
                'target' => 'Seluruh program bidang terlaksana dalam satu periode kepengurusan.',
                'frekuensi' => 'bulanan',
                'is_active' => true,
            ]
        );

        $assignment = Assignment::query()->updateOrCreate([
            'judul' => 'Penyusunan Matriks Program Kerja Bidang Program',
            'pic_id' => $staf->id,
        ], [
            'judul' => 'Penyusunan Matriks Program Kerja Bidang Program',
            'deskripsi' => 'Menyusun matriks program kerja beserta target keluaran dan jadwal pelaksanaan.',
            'job_description_id' => $jd->id,
            'sumber_perintah' => SumberPerintah::DeLangsung,
            'pemberi_tugas_id' => $de->id,
            'pic_id' => $staf->id,
            'kategori' => KategoriTugas::Utama,
            'target_keluaran' => 'Matriks program kerja final yang disetujui DE.',
            'prioritas' => PrioritasTugas::Tinggi,
            'tanggal_mulai' => ServerClock::now()->subDays(5)->toDateString(),
            'tenggat' => ServerClock::now()->addDays(10),
            'status' => StatusPenugasan::DalamProses,
            'progres' => 40,
        ]);

        ActivityLog::query()->updateOrCreate(
            [
                'personel_id' => $staf->id,
                'assignment_id' => $assignment->id,
                'uraian' => 'Mengumpulkan usulan program dari tiap bidang.',
            ],
            [
                'tanggal' => ServerClock::now()->toDateString(),
                'progres' => 40,
                'durasi_menit' => 120,
            ]
        );

        $document = Document::query()->updateOrCreate(
            ['file_path' => 'dokumen/demo/matriks-program-kerja-v1.pdf'],
            [
                'judul' => 'Draft Matriks Program Kerja',
                'kategori' => KategoriDokumen::Matriks,
                'pemilik_id' => $staf->id,
                'assignment_id' => $assignment->id,
                'file_name' => 'matriks-program-kerja-v1.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => 245_760,
                'checksum' => hash('sha256', 'demo-matriks-v1'),
                'klasifikasi' => KlasifikasiDokumen::InternalBe,
                'status' => StatusDokumen::UntukReview,
                'diunggah_oleh' => $staf->id,
            ]
        );

        $report = Report::query()->updateOrCreate(
            [
                'personel_id' => $staf->id,
                'judul' => 'Laporan Mingguan Penyusunan Matriks Program',
            ],
            [
                'assignment_id' => $assignment->id,
                'jenis' => JenisLaporan::Mingguan,
                'periode_mulai' => ServerClock::now()->subDays(7)->toDateString(),
                'periode_selesai' => ServerClock::now()->subDay()->toDateString(),
                'ringkasan' => 'Pengumpulan usulan program dari lima bidang telah selesai.',
                'status' => StatusLaporan::Diajukan,
            ]
        );

        $review = Review::query()->updateOrCreate(
            [
                'report_id' => $report->id,
                'versi_review' => 1,
            ],
            [
                'dokumen_id' => $document->id,
                'pemberi_review_id' => $de->id,
                'skor_kelengkapan' => 90,
                'skor_kejelasan' => 85,
                'skor_akurasi' => 88,
                'skor_sistematika' => 80,
                'skor_tindak_lanjut' => 75,
                'catatan_kekuatan' => 'Data usulan program lengkap dan terstruktur.',
                'kekurangan_spesifik' => 'Rencana tindak lanjut belum memuat penanggung jawab per program.',
                'rekomendasi_perbaikan' => 'Tambahkan kolom PIC dan tenggat pada setiap program.',
                'adalah_final' => true,
            ]
        );

        ActionItem::query()->updateOrCreate(
            [
                'review_id' => $review->id,
                'deskripsi' => 'Menambahkan kolom PIC dan tenggat pada matriks program kerja.',
            ],
            [
                'pic_id' => $staf->id,
                'tenggat' => ServerClock::now()->addDays(3),
            ]
        );

        Feedback::query()->updateOrCreate(
            [
                'pemberi_id' => $de->id,
                'penerima_id' => $staf->id,
                'isi' => 'Pastikan setiap program memiliki indikator keberhasilan yang terukur.',
            ],
            [
                'jenis' => JenisFeedback::Masukan,
                'asal' => AsalFeedback::De,
                'subjek_type' => $assignment->getMorphClass(),
                'subjek_id' => $assignment->id,
                'butuh_tindakan' => true,
                'tenggat' => ServerClock::now()->addDays(5),
            ]
        );

        KpiResult::query()->updateOrCreate(
            [
                'personel_id' => $staf->id,
                'periode' => ServerClock::now()->subMonthNoOverflow()->format('Y-m'),
            ],
            [
                'skor_pencapaian' => 82,
                'skor_kualitas' => 85,
                'skor_ketepatan_waktu' => 78,
                'skor_tindak_lanjut' => 80,
                'skor_kolaborasi' => 88,
                'status' => StatusKpi::Draft,
                'rule_version' => 'kpi-v1',
                'dihitung_oleh' => $de->id,
            ]
        );

        $meeting = Meeting::query()->updateOrCreate(
            ['judul' => 'Rapat Koordinasi Program Kerja'],
            [
                'agenda' => 'Pembahasan matriks program kerja dan pembagian penanggung jawab.',
                'lokasi' => 'Ruang Rapat HIMKI',
                'waktu_mulai' => ServerClock::now()->subDays(2),
                'waktu_selesai' => ServerClock::now()->subDays(2)->addHours(2),
                'pencatat_id' => $staf->id,
            ]
        );

        $meeting->peserta()->sync([
            $pengurus->id => ['hadir' => true],
            $de->id => ['hadir' => true],
            $staf->id => ['hadir' => true],
        ]);

        $this->command?->info(sprintf(
            'Demo siap: admin=%s, pengurus=%s, de=%s, staf=%s (kata sandi: password).',
            $admin->user->email,
            $pengurus->user->email,
            $de->user->email,
            $staf->user->email,
        ));
    }

    private function buatPersonel(
        string $username,
        string $nama,
        string $kodeJabatan,
        ?string $kodeBidang,
        PeranSistem $peran,
    ): Personel {
        $user = User::query()->updateOrCreate(
            ['username' => $username],
            [
                'name' => $nama,
                'email' => $username.'@himki.test',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => ServerClock::now()])->saveQuietly();
        }

        $user->syncRoles([$peran->value]);

        $personel = Personel::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'nip' => 'HIMKI-'.strtoupper($username),
                'nama_lengkap' => $nama,
                'jabatan_id' => Jabatan::query()->where('kode', $kodeJabatan)->value('id'),
                'bidang_id' => $kodeBidang === null
                    ? null
                    : Bidang::query()->where('kode', $kodeBidang)->value('id'),
                'kategori' => match ($peran) {
                    PeranSistem::Pengurus => KategoriPersonel::Pengurus,
                    PeranSistem::DirekturEksekutif => KategoriPersonel::DirekturEksekutif,
                    default => KategoriPersonel::Staf,
                },
                'status_aktif' => true,
                'tanggal_masuk' => ServerClock::now()->subYear()->toDateString(),
            ]
        );

        return $personel->load('user');
    }
}
