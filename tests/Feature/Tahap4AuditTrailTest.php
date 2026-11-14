<?php

namespace Tests\Feature;

use App\Enums\KlasifikasiDokumen;
use App\Enums\PeranSistem;
use App\Enums\StatusDokumen;
use App\Enums\StatusLaporan;
use App\Enums\StatusPenugasan;
use App\Models\Assignment;
use App\Models\AuditEvent;
use App\Models\Document;
use App\Models\JobDescription;
use App\Models\KpiResult;
use App\Models\Meeting;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use App\Support\ServerClock;
use Database\Seeders\PeranIzinSeeder;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Kriteria penerimaan Tahap 4 — Layanan Audit Trail.
 *
 * Basis data MySQL sungguhan dengan matriks izin hasil seeder, sehingga yang
 * diuji adalah perilaku sistem: penjaga wajib alasan, append-only, cakupan
 * Direktur Eksekutif, dan ekspor yang berisi baris audit nyata.
 */
class Tahap4AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PeranIzinSeeder::class);
    }

    // ---------------------------------------------------------------- bantuan

    /**
     * @param  array<string, mixed>  $atribut
     */
    private function akun(PeranSistem $peran, array $atribut = []): User
    {
        $user = User::factory()->create(array_merge([
            'username' => $peran->value.'-'.Str::lower(Str::random(6)),
            'email' => Str::lower(Str::random(8)).'@himki.test',
            'is_active' => true,
        ], $atribut));

        $user->syncRoles([$peran->value]);

        return $user->fresh();
    }

    // ------------------------------------------- 1. append-only di dua lapis

    #[Test]
    public function audit_menolak_ubah_dan_hapus_lewat_model_maupun_sql_langsung(): void
    {
        $event = AuditEvent::factory()->create();

        try {
            $event->update(['event' => 'diubah']);
            $this->fail('Perubahan baris audit seharusnya ditolak.');
        } catch (RuntimeException) {
            // sesuai harapan
        }

        try {
            $event->delete();
            $this->fail('Penghapusan baris audit seharusnya ditolak.');
        } catch (RuntimeException) {
            // sesuai harapan
        }

        $this->expectException(QueryException::class);
        DB::table('audit_events')->where('id', $event->id)->update(['event' => 'diubah']);
    }

    #[Test]
    public function trigger_database_juga_menolak_penghapusan_langsung(): void
    {
        $event = AuditEvent::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('audit_events')->where('id', $event->id)->delete();
    }

    // ------------------------------------- 2. Policy menolak admin sekalipun

    #[Test]
    public function administrator_pun_tidak_boleh_mengubah_atau_menghapus_jejak_audit(): void
    {
        $admin = $this->akun(PeranSistem::Admin);
        $event = AuditEvent::factory()->create();

        $this->actingAs($admin);

        $this->assertTrue($admin->can('view', $event));
        $this->assertFalse($admin->can('create', AuditEvent::class));
        $this->assertFalse($admin->can('update', $event));
        $this->assertFalse($admin->can('delete', $event));
    }

    // ------------------------------- 3. akun berjejak tidak bisa dihapus

    #[Test]
    public function menghapus_akun_berjejak_ditolak_dan_snapshot_pelaku_tetap_ada(): void
    {
        $admin = $this->akun(PeranSistem::Admin);
        $pelaku = $this->akun(PeranSistem::Pengurus);

        $this->actingAs($pelaku);
        $assignment = Assignment::factory()->create();

        $event = AuditEvent::query()
            ->where('auditable_type', Assignment::class)
            ->where('auditable_id', $assignment->getKey())
            ->firstOrFail();

        $this->assertSame($pelaku->name, $event->actor_nama);
        $this->assertSame(PeranSistem::Pengurus->value, $event->actor_peran);

        $this->actingAs($admin);

        $this->expectException(QueryException::class);
        $pelaku->delete();
    }

    // ------------------------------- 4. wajib alasan pada review & skor

    #[Test]
    public function perubahan_skor_review_wajib_alasan_dan_alasan_tercatat(): void
    {
        $admin = $this->akun(PeranSistem::Admin);
        $review = Review::factory()->create();
        $this->actingAs($admin);

        $review->skor_kelengkapan = 55;

        try {
            $review->save();
            $this->fail('Perubahan skor tanpa alasan seharusnya ditolak.');
        } catch (DomainException) {
            // sesuai harapan
        }

        $review->denganAlasan('Koreksi penilaian setelah verifikasi ulang bukti kerja.');
        $review->save();

        $event = AuditEvent::query()
            ->where('auditable_type', Review::class)
            ->where('auditable_id', $review->getKey())
            ->where('event', 'updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('Koreksi penilaian setelah verifikasi ulang bukti kerja.', $event->reason);
        $this->assertSame(55, $event->after['skor_kelengkapan']);
    }

    #[Test]
    public function alasan_hangus_setelah_satu_perubahan(): void
    {
        $admin = $this->akun(PeranSistem::Admin);
        $review = Review::factory()->create(['skor_kejelasan' => 60]);
        $this->actingAs($admin);
        $review->skor_kejelasan = 70;
        $review->denganAlasan('Penyesuaian pertama atas temuan review.');
        $review->save();

        $review->skor_kejelasan = 71;

        $this->expectException(DomainException::class);
        $review->save();
    }

    #[Test]
    public function perubahan_bobot_kpi_dan_target_job_description_wajib_alasan(): void
    {
        $admin = $this->akun(PeranSistem::Admin);
        $this->actingAs($admin);

        $kpi = KpiResult::factory()->create();
        $kpi->bobot_pencapaian = 40;

        try {
            $kpi->save();
            $this->fail('Perubahan bobot KPI tanpa alasan seharusnya ditolak.');
        } catch (DomainException) {
            // sesuai harapan
        }

        $jd = JobDescription::factory()->create();
        $jd->target = 'Target baru yang belum disepakati.';

        $this->expectException(DomainException::class);
        $jd->save();
    }

    #[Test]
    public function job_description_yang_sudah_ditetapkan_tetap_terkunci_bukan_hanya_wajib_alasan(): void
    {
        $admin = $this->akun(PeranSistem::Admin);
        $this->actingAs($admin);

        $jd = JobDescription::factory()->ditetapkan('-2 months')->create();
        $jd->denganAlasan('Mencoba mengubah target yang sudah dibekukan.');
        $jd->target = 'Target yang tidak boleh berlaku.';

        $this->expectException(DomainException::class);
        $jd->save();
    }

    // ------------------------------- 5. status penugasan & verifikasi

    #[Test]
    public function status_tugas_final_wajib_alasan_sedangkan_progres_biasa_tidak(): void
    {
        $admin = $this->akun(PeranSistem::Admin);
        $this->actingAs($admin);

        $biasa = Assignment::factory()->create(['status' => StatusPenugasan::Aktif]);
        $biasa->status = StatusPenugasan::DalamProses;
        $biasa->progres = 20;
        $biasa->save();

        $this->assertSame(StatusPenugasan::DalamProses, $biasa->fresh()->status);

        $final = Assignment::factory()->create(['status' => StatusPenugasan::Terverifikasi]);
        $final->status = StatusPenugasan::Ditutup;

        try {
            $final->save();
            $this->fail('Perubahan status final tanpa alasan seharusnya ditolak.');
        } catch (DomainException) {
            // sesuai harapan
        }

        $final->progres = 100;

        $this->expectException(DomainException::class);
        $final->save();
    }

    #[Test]
    public function verifikasi_laporan_dan_finalisasi_dokumen_wajib_alasan(): void
    {
        $admin = $this->akun(PeranSistem::Admin);
        $this->actingAs($admin);

        $laporan = Report::factory()->create(['status' => StatusLaporan::Diajukan]);
        $laporan->status = StatusLaporan::Terverifikasi;

        try {
            $laporan->save();
            $this->fail('Verifikasi laporan tanpa alasan seharusnya ditolak.');
        } catch (DomainException) {
            // sesuai harapan
        }

        $dokumen = Document::factory()->create(['status' => StatusDokumen::Draft]);
        $dokumen->status = StatusDokumen::Final;

        $this->expectException(DomainException::class);
        $dokumen->save();
    }

    #[Test]
    public function mengubah_klasifikasi_dokumen_selalu_wajib_alasan(): void
    {
        $admin = $this->akun(PeranSistem::Admin);
        $this->actingAs($admin);

        $dokumen = Document::factory()->create(['klasifikasi' => KlasifikasiDokumen::InternalBe]);
        $dokumen->klasifikasi = KlasifikasiDokumen::RahasiaKeuangan;

        $this->expectException(DomainException::class);
        $dokumen->save();
    }

    // ------------------------------------------- 6. rapat ikut terekam

    #[Test]
    public function rapat_menghasilkan_baris_audit_saat_dibuat_dan_diubah(): void
    {
        $admin = $this->akun(PeranSistem::Admin);
        $this->actingAs($admin);

        $meeting = Meeting::factory()->create(['judul' => 'Rapat Evaluasi Triwulan']);

        $this->assertDatabaseHas('audit_events', [
            'event' => 'created',
            'auditable_type' => Meeting::class,
            'auditable_id' => $meeting->getKey(),
            'actor_id' => $admin->getKey(),
        ]);

        $meeting->lokasi = 'Ruang Rapat Lantai 2';
        $meeting->save();

        $this->assertDatabaseHas('audit_events', [
            'event' => 'updated',
            'auditable_type' => Meeting::class,
            'auditable_id' => $meeting->getKey(),
        ]);
    }

    // -------------------------------------- 7. audit perubahan hak akses

    #[Test]
    public function pemberian_peran_oleh_pengguna_login_tercatat_tetapi_seeder_tidak(): void
    {
        $admin = $this->akun(PeranSistem::Admin);
        $calon = $this->akun(PeranSistem::Staf);

        $this->assertDatabaseMissing('audit_events', ['event' => 'akses.peran_diberikan']);

        $this->actingAs($admin);
        $calon->assignRole(PeranSistem::Pengurus->value);

        $event = AuditEvent::query()->where('event', 'akses.peran_diberikan')->firstOrFail();

        $this->assertSame($admin->getKey(), $event->actor_id);
        $this->assertSame(User::class, $event->auditable_type);
        $this->assertSame($calon->getKey(), $event->auditable_id);
        $this->assertSame([PeranSistem::Pengurus->value], $event->after['peran']);
    }

    // ------------------------------------- 8. cakupan Direktur Eksekutif

    #[Test]
    public function de_hanya_melihat_peristiwa_operasional_dan_ditolak_di_luar_cakupan(): void
    {
        $de = $this->akun(PeranSistem::DirekturEksekutif);
        $lain = $this->akun(PeranSistem::Staf);

        $keamanan = AuditEvent::factory()->create([
            'actor_id' => $lain->getKey(),
            'event' => 'auth.login',
        ]);

        $this->actingAs($de);
        $assignment = Assignment::factory()->create();

        $operasional = AuditEvent::query()
            ->where('auditable_type', Assignment::class)
            ->where('auditable_id', $assignment->getKey())
            ->firstOrFail();

        $response = $this->get(route('audit.index'))->assertOk();

        $ids = $response->viewData('events')->pluck('id')->all();

        $this->assertContains($operasional->getKey(), $ids);
        $this->assertNotContains($keamanan->getKey(), $ids);

        $this->get(route('audit.show', $operasional))->assertOk();
        $this->get(route('audit.show', $keamanan))->assertForbidden();
    }

    #[Test]
    public function staf_tidak_punya_akses_ke_layar_audit(): void
    {
        $staf = $this->akun(PeranSistem::Staf);
        $event = AuditEvent::factory()->create();

        $this->actingAs($staf);

        $this->get(route('audit.index'))->assertForbidden();
        $this->get(route('audit.show', $event))->assertForbidden();
        $this->get(route('audit.ekspor'))->assertForbidden();
    }

    // ------------------------------------------ 9. filter dan detail

    #[Test]
    public function admin_dapat_memfilter_daftar_dan_melihat_detail_perubahan(): void
    {
        $admin = $this->akun(PeranSistem::Admin);
        $this->actingAs($admin);

        $assignment = Assignment::factory()->create(['status' => StatusPenugasan::Aktif]);
        $assignment->progres = 55;
        $assignment->save();

        $event = AuditEvent::query()
            ->where('auditable_type', Assignment::class)
            ->where('event', 'updated')
            ->latest('id')
            ->firstOrFail();

        $daftar = $this->get(route('audit.index', ['tindakan' => 'updated', 'objek' => 'assignment']))
            ->assertOk();

        $this->assertContains($event->getKey(), $daftar->viewData('events')->pluck('id')->all());

        $detail = $this->get(route('audit.show', $event))->assertOk();
        $detail->assertSee('Progres');
        $detail->assertSee('55');
    }

    #[Test]
    public function filter_objek_memisahkan_hak_akses_dari_akun(): void
    {
        $admin = $this->akun(PeranSistem::Admin);
        $this->actingAs($admin);

        $akses = AuditEvent::factory()->create([
            'event' => 'akses.peran_diberikan',
            'auditable_type' => User::class,
            'auditable_id' => $admin->getKey(),
            'after' => ['peran' => ['pengurus']],
        ]);

        $akun = AuditEvent::factory()->create([
            'event' => 'created',
            'auditable_type' => User::class,
            'auditable_id' => $admin->getKey(),
        ]);

        $hanyaAkses = $this->get(route('audit.index', ['objek' => 'akses']))->assertOk();
        $hanyaAkun = $this->get(route('audit.index', ['objek' => 'user']))->assertOk();

        $this->assertContains($akses->getKey(), $hanyaAkses->viewData('events')->pluck('id')->all());
        $this->assertNotContains($akun->getKey(), $hanyaAkses->viewData('events')->pluck('id')->all());

        $this->assertContains($akun->getKey(), $hanyaAkun->viewData('events')->pluck('id')->all());
        $this->assertNotContains($akses->getKey(), $hanyaAkun->viewData('events')->pluck('id')->all());
    }

    #[Test]
    public function nilai_filter_di_luar_daftar_ditolak(): void
    {
        $admin = $this->akun(PeranSistem::Admin);

        $this->actingAs($admin)
            ->get(route('audit.index', ['tindakan' => 'drop table']))
            ->assertSessionHasErrors('tindakan');

        $this->actingAs($admin)
            ->get(route('audit.index', ['urut' => 'id desc, (select 1)']))
            ->assertSessionHasErrors('urut');
    }

    // -------------------------------------------------- 10. ekspor CSV

    #[Test]
    public function ekspor_audit_berisi_baris_nyata_dan_mencatat_peristiwanya(): void
    {
        $admin = $this->akun(PeranSistem::Admin);
        $this->actingAs($admin);

        Assignment::factory()->create(['judul' => 'Tugas untuk ekspor audit']);

        $response = $this->get(route('audit.ekspor'))->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Jejak Audit ERP HIMKI', $csv);
        $this->assertStringContainsString('waktu_wib', $csv);
        $this->assertStringContainsString('Tugas untuk ekspor audit', $csv);
        $this->assertStringContainsString('Dibuat', $csv);

        $this->assertDatabaseHas('audit_events', [
            'event' => 'ekspor.audit',
            'actor_id' => $admin->getKey(),
        ]);
    }

    #[Test]
    public function de_dan_staf_tidak_boleh_mengekspor_audit_dan_rute_lama_mengalihkan(): void
    {
        $de = $this->akun(PeranSistem::DirekturEksekutif);
        $staf = $this->akun(PeranSistem::Staf);
        $admin = $this->akun(PeranSistem::Admin);

        $this->actingAs($de)->get(route('audit.ekspor'))->assertForbidden();
        $this->actingAs($staf)->get(route('audit.ekspor'))->assertForbidden();

        $this->actingAs($admin)
            ->get(route('modul.ekspor', 'audit'))
            ->assertRedirect(route('audit.ekspor'));
    }

    // ------------------------------------------- 11. snapshot pelaku lama

    #[Test]
    public function baris_audit_lama_tanpa_snapshot_tetap_dapat_dibaca(): void
    {
        $admin = $this->akun(PeranSistem::Admin);

        $lama = AuditEvent::factory()->create([
            'actor_id' => $admin->getKey(),
            'actor_nama' => null,
            'actor_peran' => null,
            'created_at' => ServerClock::now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->get(route('audit.index'))
            ->assertOk()
            ->assertSee($admin->name);

        $this->get(route('audit.show', $lama))->assertOk();
    }
}
