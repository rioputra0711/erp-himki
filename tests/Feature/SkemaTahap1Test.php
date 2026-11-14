<?php

namespace Tests\Feature;

use App\Enums\AsalFeedback;
use App\Enums\JenisLaporan;
use App\Enums\KategoriKpi;
use App\Enums\StatusKpi;
use App\Enums\StatusLaporan;
use App\Enums\StatusPenugasan;
use App\Models\ActionItem;
use App\Models\ActivityLog;
use App\Models\Assignment;
use App\Models\AuditEvent;
use App\Models\Document;
use App\Models\Feedback;
use App\Models\KpiResult;
use App\Models\Meeting;
use App\Models\Personel;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use App\Support\ServerClock;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class SkemaTahap1Test extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function zona_waktu_server_adalah_asia_jakarta(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('Asia/Jakarta', ServerClock::now()->timezoneName);
    }

    #[Test]
    public function jam_database_mengikuti_zona_waktu_aplikasi(): void
    {
        $sesi = DB::selectOne('select @@session.time_zone as tz, now() as n');

        $this->assertSame('+07:00', $sesi->tz);

        $jamDatabase = CarbonImmutable::parse($sesi->n, config('app.timezone'));
        $selisih = abs($jamDatabase->getTimestamp() - ServerClock::now()->getTimestamp());

        $this->assertLessThanOrEqual(
            5,
            $selisih,
            'Jam database harus sejalan dengan ServerClock agar tenggat & SLA tidak melenceng.'
        );
    }

    #[Test]
    public function rantai_relasi_user_personel_penugasan_laporan_review_action_item(): void
    {
        $personel = Personel::factory()->create();
        $pemberi = Personel::factory()->create();

        $assignment = Assignment::factory()->create([
            'pemberi_tugas_id' => $pemberi->id,
            'pic_id' => $personel->id,
        ]);

        $log = ActivityLog::factory()->create([
            'personel_id' => $personel->id,
            'assignment_id' => $assignment->id,
        ]);

        $report = Report::factory()->create([
            'personel_id' => $personel->id,
            'assignment_id' => $assignment->id,
            'jenis' => JenisLaporan::Mingguan,
            'status' => StatusLaporan::Diajukan,
        ]);

        $review = Review::factory()->create([
            'report_id' => $report->id,
            'pemberi_review_id' => $pemberi->id,
        ]);

        $item = ActionItem::factory()->create([
            'review_id' => $review->id,
            'pic_id' => $personel->id,
        ]);

        $this->assertTrue($personel->user->is($personel->user()->first()));
        $this->assertSame($personel->id, $assignment->pic->id);
        $this->assertSame($assignment->id, $log->assignment->id);
        $this->assertSame($report->id, $review->report->id);
        $this->assertSame($review->id, $item->review->id);
        $this->assertSame($personel->id, $item->pic->id);
        $this->assertNotNull($report->diajukan_pada);
        $this->assertSame(1, $review->versi_review);
        $this->assertSame(
            Review::hitungSkorTotal($review->skorKomponen(), $review->bobotKomponen()),
            (float) $review->skor_total
        );
    }

    #[Test]
    public function foreign_key_menolak_pic_yang_tidak_ada(): void
    {
        $this->expectException(QueryException::class);

        Assignment::factory()->create(['pic_id' => 999_999]);
    }

    #[Test]
    public function unique_menolak_kpi_ganda_untuk_personel_dan_periode_sama(): void
    {
        $personel = Personel::factory()->create();

        KpiResult::factory()->create([
            'personel_id' => $personel->id,
            'periode' => '2026-10',
            'status' => StatusKpi::Draft,
        ]);

        $this->expectException(QueryException::class);

        KpiResult::factory()->create([
            'personel_id' => $personel->id,
            'periode' => '2026-10',
            'status' => StatusKpi::Draft,
        ]);
    }

    #[Test]
    public function check_menolak_progres_di_atas_100(): void
    {
        $this->expectException(QueryException::class);

        Assignment::factory()->create(['progres' => 101]);
    }

    #[Test]
    public function check_menolak_skor_total_di_atas_100_lewat_sql_langsung(): void
    {
        $report = Report::factory()->create();
        $pemberi = Personel::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('reviews')->insert([
            'report_id' => $report->id,
            'pemberi_review_id' => $pemberi->id,
            'skor_kelengkapan' => 90,
            'skor_kejelasan' => 90,
            'skor_akurasi' => 90,
            'skor_sistematika' => 90,
            'skor_tindak_lanjut' => 90,
            'bobot_kelengkapan' => 20,
            'bobot_kejelasan' => 25,
            'bobot_akurasi' => 25,
            'bobot_sistematika' => 15,
            'bobot_tindak_lanjut' => 15,
            'skor_total' => 120,
            'kategori' => 'sangat_baik',
            'versi_review' => 1,
            'adalah_final' => 0,
            'reviewed_at' => ServerClock::now(),
            'created_at' => ServerClock::now(),
            'updated_at' => ServerClock::now(),
        ]);
    }

    #[Test]
    public function check_menolak_periode_selesai_sebelum_periode_mulai(): void
    {
        $this->expectException(QueryException::class);

        Report::factory()->create([
            'periode_mulai' => '2026-10-10',
            'periode_selesai' => '2026-10-01',
        ]);
    }

    #[Test]
    public function audit_event_tidak_dapat_diubah_atau_dihapus(): void
    {
        $event = AuditEvent::factory()->create();

        try {
            $event->update(['event' => 'diubah']);
            $this->fail('Update audit event seharusnya ditolak model.');
        } catch (RuntimeException) {
            // sesuai harapan
        }

        try {
            $event->delete();
            $this->fail('Hapus audit event seharusnya ditolak model.');
        } catch (RuntimeException) {
            // sesuai harapan
        }

        $this->expectException(QueryException::class);
        DB::table('audit_events')->where('id', $event->id)->update(['event' => 'diubah']);
    }

    #[Test]
    public function audit_event_juga_ditolak_di_level_database(): void
    {
        $event = AuditEvent::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('audit_events')->where('id', $event->id)->delete();
    }

    #[Test]
    public function penugasan_batal_tanpa_alasan_ditolak(): void
    {
        $this->expectException(\DomainException::class);

        Assignment::factory()->create([
            'status' => StatusPenugasan::Dibatalkan,
            'alasan_pembatalan' => null,
        ]);
    }

    #[Test]
    public function penugasan_batal_tanpa_alasan_ditolak_di_level_database(): void
    {
        $pic = Personel::factory()->create();
        $pemberi = Personel::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('assignments')->insert([
            'judul' => 'Uji batal',
            'deskripsi' => 'Uji',
            'sumber_perintah' => 'de_langsung',
            'pemberi_tugas_id' => $pemberi->id,
            'pic_id' => $pic->id,
            'kategori' => 'utama',
            'target_keluaran' => 'Tidak ada',
            'prioritas' => 'sedang',
            'tanggal_mulai' => '2026-11-01',
            'tenggat' => '2026-11-10 17:00:00',
            'status' => 'dibatalkan',
            'progres' => 0,
            'created_at' => ServerClock::now(),
            'updated_at' => ServerClock::now(),
        ]);
    }

    #[Test]
    public function tenggat_risalah_dihitung_plus_1_jam_dan_plus_16_jam(): void
    {
        $selesai = ServerClock::now()->subDay()->setTime(15, 0);

        $meeting = Meeting::factory()->create([
            'waktu_mulai' => $selesai->subHours(2),
            'waktu_selesai' => $selesai,
        ]);

        $this->assertSame(
            $selesai->addHour()->toDateTimeString(),
            $meeting->tenggat_risalah_singkat->toDateTimeString()
        );
        $this->assertSame(
            $selesai->addHours(16)->toDateTimeString(),
            $meeting->tenggat_risalah_lengkap->toDateTimeString()
        );
    }

    #[Test]
    public function password_otomatis_ter_hash_saat_user_dibuat(): void
    {
        $user = User::query()->create([
            'name' => 'Uji Hash',
            'username' => 'ujihash',
            'email' => 'ujihash@himki.test',
            'password' => 'rahasia-sekali',
        ]);

        $this->assertNotSame('rahasia-sekali', $user->password);
        $this->assertTrue(Hash::check('rahasia-sekali', $user->password));
    }

    #[Test]
    public function kpi_menghitung_ulang_skor_total_dari_bobot_dan_kategori(): void
    {
        $kpi = KpiResult::factory()->create([
            'skor_pencapaian' => 100,
            'skor_kualitas' => 100,
            'skor_ketepatan_waktu' => 100,
            'skor_tindak_lanjut' => 100,
            'skor_kolaborasi' => 100,
        ]);

        $this->assertSame('100.00', (string) $kpi->skor_total);
        $this->assertSame(KategoriKpi::SangatBaik, $kpi->kategori);
    }

    #[Test]
    public function kpi_final_wajib_punya_pengunci(): void
    {
        $this->expectException(\DomainException::class);

        KpiResult::factory()->create(['status' => StatusKpi::Final]);
    }

    #[Test]
    public function feedback_butuh_tindakan_wajib_punya_penerima(): void
    {
        $this->expectException(\DomainException::class);

        Feedback::factory()->create([
            'butuh_tindakan' => true,
            'penerima_id' => null,
            'asal' => AsalFeedback::De,
        ]);
    }

    #[Test]
    public function trigger_dokumen_tetap_aktif_setelah_tabel_dokumen_dibangun_ulang(): void
    {
        $pemilik = Personel::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('documents')->insert([
            'judul' => 'Berkas uji',
            'kategori' => 'surat',
            'versi' => 1,
            'pemilik_id' => $pemilik->id,
            'file_path' => 'uji/berkas.pdf',
            'file_name' => 'berkas.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 0,
            'klasifikasi' => 'umum',
            'status' => 'draft',
            'diunggah_oleh' => $pemilik->id,
            'created_at' => ServerClock::now(),
            'updated_at' => ServerClock::now(),
        ]);
    }

    #[Test]
    public function dokumen_valid_dapat_disimpan(): void
    {
        $dokumen = Document::factory()->create();

        $this->assertDatabaseHas('documents', ['id' => $dokumen->id]);
    }
}
