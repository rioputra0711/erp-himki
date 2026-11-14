<?php

namespace Tests\Feature;

use App\Enums\AsalFeedback;
use App\Enums\JenisFeedback;
use App\Enums\KategoriTugas;
use App\Enums\PeranSistem;
use App\Enums\PrioritasTugas;
use App\Enums\StatusActionItem;
use App\Enums\StatusFeedback;
use App\Enums\StatusPenugasan;
use App\Enums\SumberPerintah;
use App\Models\ActionItem;
use App\Models\Assignment;
use App\Models\Feedback;
use App\Models\Personel;
use App\Models\User;
use App\Support\PengirimNotifikasi;
use App\Support\RingkasanTunggakan;
use App\Support\ServerClock;
use Database\Seeders\PeranIzinSeeder;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Kriteria penerimaan Tahap 5 — Manajemen Penugasan & 3 Jalur Arahan.
 *
 * Basis data MySQL sungguhan dengan matriks izin hasil seeder, sehingga yang
 * diuji adalah perilaku sistem: sumber perintah dari sesi, state machine,
 * kewajiban bukti, cakupan baris, dan notifikasi per akun.
 */
class Tahap5PenugasanTest extends TestCase
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

    /**
     * Menjalankan penulisan mentah yang seharusnya ditolak database.
     *
     * Mengembalikan pesan galat agar tes dapat memastikan yang gagal benar-benar
     * CHECK constraint yang dimaksud — bukan, misalnya, kolom yang belum ada.
     */
    private function tolak(callable $tambah, string $penanda): string
    {
        try {
            $tambah();
        } catch (QueryException $e) {
            return $e->getMessage();
        }

        $this->fail("Penulisan seharusnya ditolak oleh {$penanda}.");
    }

    // ------------------------------------------- 1. CHECK database (Tahap 5)

    #[Test]
    public function database_menolak_penutupan_tanpa_alasan_dan_bukti(): void
    {
        $tugas = Assignment::factory()->create([
            'status' => StatusPenugasan::Terverifikasi,
            'progres' => 100,
        ]);

        $pesan = $this->tolak(tambah: fn () => DB::table('assignments')->where('id', $tugas->id)->update([
            'status' => 'ditutup',
            'alasan_penutupan' => null,
            'bukti_penutupan' => null,
        ]), penanda: 'assignments_penutupan_lengkap');

        $this->assertStringContainsString('assignments_penutupan_lengkap', $pesan);
        $this->assertDatabaseHas('assignments', ['id' => $tugas->id, 'status' => 'terverifikasi']);
    }

    #[Test]
    public function database_menolak_persetujuan_dari_asal_de(): void
    {
        $pesan = $this->tolak(tambah: fn () => DB::table('feedback')->insert([
            'pemberi_id' => Personel::factory()->create()->id,
            'jenis' => 'persetujuan',
            'asal' => 'de',
            'isi' => 'Setuju',
            'created_at' => now(),
            'updated_at' => now(),
        ]), penanda: 'feedback_persetujuan_pengurus');

        $this->assertStringContainsString('feedback_persetujuan_pengurus', $pesan);
        $this->assertDatabaseCount('feedback', 0);
    }

    // --------------------------------------- 2. Matriks izin & label audit

    #[Test]
    public function pengurus_tidak_memegang_izin_verifikasi_penugasan(): void
    {
        $pengurus = $this->akun(PeranSistem::Pengurus);

        $this->assertFalse($pengurus->can('assignment.verify'));
        $this->assertTrue($pengurus->can('assignment.create'));
        $this->assertSame(56, Permission::query()->count());
    }

    // ---------------------------------- 3. State machine & kewajiban bukti

    #[Test]
    public function transisi_status_tidak_sah_ditolak_tanpa_mengubah_baris(): void
    {
        $tugas = Assignment::factory()->create(['status' => StatusPenugasan::Draft]);

        try {
            $tugas->denganAlasan('Coba lompat status')->update(['status' => StatusPenugasan::Terverifikasi]);
            $this->fail('Transisi draft ke terverifikasi seharusnya ditolak.');
        } catch (DomainException) {
            // diharapkan
        }

        $this->assertSame(StatusPenugasan::Draft, $tugas->fresh()->status);
    }

    #[Test]
    public function penutupan_wajib_alasan_dan_bukti_serta_mengisi_waktu_server(): void
    {
        $tugas = Assignment::factory()->create([
            'status' => StatusPenugasan::Terverifikasi,
            'progres' => 100,
        ]);

        try {
            $tugas->denganAlasan('Menutup tugas')->update(['status' => StatusPenugasan::Ditutup]);
            $this->fail('Penutupan tanpa alasan dan bukti seharusnya ditolak.');
        } catch (DomainException) {
            // diharapkan
        }

        $this->assertSame(StatusPenugasan::Terverifikasi, $tugas->fresh()->status);

        $tugas->forceFill([
            'alasan_penutupan' => 'Seluruh keluaran diterima',
            'bukti_penutupan' => 'Berita acara 12/HIMKI/XI/2026',
        ])->save();

        $tugas->denganAlasan('Seluruh keluaran diterima')->update(['status' => StatusPenugasan::Ditutup]);

        $segar = $tugas->fresh();
        $this->assertSame(StatusPenugasan::Ditutup, $segar->status);
        $this->assertNotNull($segar->ditutup_pada);
    }

    // ------------------- 4. Sumber perintah dari sesi & kode unik

    #[Test]
    public function sumber_perintah_dan_pemberi_tugas_diambil_dari_sesi_bukan_formulir(): void
    {
        $de = $this->akun(PeranSistem::DirekturEksekutif);
        $personelDe = Personel::factory()->create(['user_id' => $de->id]);
        $pic = Personel::factory()->create();
        $this->actingAs($de);

        $tugas = Assignment::buatDenganKodeUnik(function (Assignment $baru) use ($pic): void {
            $baru->fill([
                'judul' => 'Susun laporan',
                'deskripsi' => 'Rincian pekerjaan',
                'pic_id' => $pic->id,
                'kategori' => KategoriTugas::Umum,
                'target_keluaran' => 'Dokumen laporan',
                'prioritas' => PrioritasTugas::Sedang,
                'tanggal_mulai' => ServerClock::now()->toDateString(),
                'tenggat' => ServerClock::now()->addDays(7),
                'status' => StatusPenugasan::Aktif,
                // upaya pemalsuan yang harus diabaikan model:
                'sumber_perintah' => SumberPerintah::PengurusLangsung,
                'pemberi_tugas_id' => $pic->id,
            ]);
        });

        $this->assertSame(SumberPerintah::DeLangsung, $tugas->sumber_perintah);
        $this->assertSame($personelDe->id, (int) $tugas->pemberi_tugas_id);
        $this->assertMatchesRegularExpression('/^TGS-\d{4}-\d{4}$/', (string) $tugas->kode);
    }

    #[Test]
    public function sumber_perintah_tidak_dapat_diubah_setelah_dibuat(): void
    {
        $tugas = Assignment::factory()->create(['sumber_perintah' => SumberPerintah::DeLangsung]);

        $this->expectException(DomainException::class);

        $tugas->update(['sumber_perintah' => SumberPerintah::PengurusMelaluiDe]);
    }

    #[Test]
    public function dependensi_melingkar_ditolak(): void
    {
        $awal = Assignment::factory()->create();
        $tengah = Assignment::factory()->create(['dependensi_id' => $awal->id]);

        $this->assertTrue($awal->dependensiBerputar((int) $tengah->id));
        $this->assertTrue($awal->dependensiBerputar((int) $awal->id));
        $this->assertFalse($awal->dependensiBerputar(null));
    }

    // --------------- 5. Identitas arahan & pemisahan persetujuan formal

    #[Test]
    public function identitas_pemberi_arahan_dikunci_dari_sesi(): void
    {
        $pengurus = $this->akun(PeranSistem::Pengurus);
        $personel = Personel::factory()->create(['user_id' => $pengurus->id]);
        $personel->load('jabatan');
        $penerima = Personel::factory()->create();
        $this->actingAs($pengurus);

        $arahan = Feedback::query()->create([
            'penerima_id' => $penerima->id,
            'jenis' => JenisFeedback::Arahan,
            'asal' => AsalFeedback::De,
            'isi' => 'Tindak lanjuti hasil rapat',
            'pemberi_id' => $penerima->id,
        ]);

        $this->assertSame((int) $personel->id, (int) $arahan->pemberi_id);
        $this->assertSame(AsalFeedback::Pengurus, $arahan->asal);
        $this->assertSame($personel->jabatan?->nama, $arahan->pemberi_jabatan);
    }

    #[Test]
    public function komentar_tidak_dapat_dinaikkan_menjadi_persetujuan(): void
    {
        $arahan = Feedback::factory()->create([
            'jenis' => JenisFeedback::Komentar,
            'asal' => AsalFeedback::Pengurus,
        ]);

        $this->expectException(DomainException::class);

        $arahan->update(['jenis' => JenisFeedback::Persetujuan]);
    }

    #[Test]
    public function staf_hanya_melihat_arahan_yang_ia_beri_atau_terima(): void
    {
        $staf = $this->akun(PeranSistem::Staf);
        $personelStaf = Personel::factory()->create(['user_id' => $staf->id]);
        $orangLain = Personel::factory()->create();

        $sebagaiPenerima = Feedback::factory()->create(['penerima_id' => $personelStaf->id]);
        $sebagaiPemberi = Feedback::factory()->create([
            'pemberi_id' => $personelStaf->id,
            'penerima_id' => $orangLain->id,
        ]);
        $milikOrangLain = Feedback::factory()->create([
            'pemberi_id' => $orangLain->id,
            'penerima_id' => $orangLain->id,
        ]);

        $terlihat = Feedback::query()->terlihatOleh($staf)->pluck('id')->all();

        $this->assertEqualsCanonicalizing(
            [$sebagaiPenerima->id, $sebagaiPemberi->id],
            $terlihat,
        );
        $this->assertNotContains($milikOrangLain->id, $terlihat);
    }

    #[Test]
    public function persetujuan_formal_hanya_dari_pengurus_dan_tidak_meminta_tindakan(): void
    {
        $de = $this->akun(PeranSistem::DirekturEksekutif);
        Personel::factory()->create(['user_id' => $de->id]);
        $this->actingAs($de);

        try {
            Feedback::query()->create([
                'penerima_id' => Personel::factory()->create()->id,
                'jenis' => JenisFeedback::Persetujuan,
                'isi' => 'Setuju',
            ]);
            $this->fail('Persetujuan dari DE seharusnya ditolak.');
        } catch (DomainException) {
            // diharapkan: asal ditetapkan sesi menjadi `de`
        }

        $this->assertDatabaseCount('feedback', 0);

        $pengurus = $this->akun(PeranSistem::Pengurus);
        Personel::factory()->create(['user_id' => $pengurus->id]);
        $this->actingAs($pengurus);

        $this->expectException(DomainException::class);

        Feedback::query()->create([
            'penerima_id' => Personel::factory()->create()->id,
            'jenis' => JenisFeedback::Persetujuan,
            'isi' => 'Setuju',
            'butuh_tindakan' => true,
        ]);
    }

    // ---------------------------------------- 6. Penjaga action item

    #[Test]
    public function action_item_ditutup_wajib_bukti_dan_hanya_de(): void
    {
        $staf = $this->akun(PeranSistem::Staf);
        $de = $this->akun(PeranSistem::DirekturEksekutif);
        $personelStaf = Personel::factory()->create(['user_id' => $staf->id]);
        Personel::factory()->create(['user_id' => $de->id]);

        $item = ActionItem::factory()->create([
            'pic_id' => $personelStaf->id,
            'status' => StatusActionItem::DiajukanSelesai,
        ]);

        $this->actingAs($staf);

        try {
            $item->denganAlasan('Menutup item')->update(['status' => StatusActionItem::Ditutup]);
            $this->fail('Penutupan tanpa bukti seharusnya ditolak.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('bukti', $e->getMessage());
        }

        $item->refresh()->forceFill(['bukti_penutupan' => 'Dokumentasi kegiatan 07/XI/2026'])->save();

        try {
            $item->denganAlasan('Menutup item')->update(['status' => StatusActionItem::Ditutup]);
            $this->fail('Staf tidak boleh menutup action item.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('verifikasi', $e->getMessage());
        }

        $this->assertSame(StatusActionItem::DiajukanSelesai, $item->fresh()->status);

        $this->actingAs($de);
        $item->fresh()->denganAlasan('Bukti diterima')->update(['status' => StatusActionItem::Ditutup]);

        $this->assertSame(StatusActionItem::Ditutup, $item->fresh()->status);
    }

    #[Test]
    public function arahan_baru_boleh_ditutup_setelah_seluruh_item_tuntas(): void
    {
        $arahan = Feedback::factory()->create();
        $this->assertTrue($arahan->bolehDitutup(), 'Arahan tanpa action item boleh ditutup.');

        $item = ActionItem::factory()->create([
            'feedback_id' => $arahan->id,
            'status' => StatusActionItem::DalamProses,
        ]);

        $this->assertFalse($arahan->fresh()->bolehDitutup());

        $item->denganAlasan('Pekerjaan selesai')->update(['status' => StatusActionItem::TerverifikasiSelesai]);

        $this->assertTrue($arahan->fresh()->bolehDitutup());
    }

    // ------------------------------------------------------- 7. Notifikasi

    #[Test]
    public function arahan_pengurus_ke_staf_mengirim_notifikasi_ke_staf_dan_de(): void
    {
        $pengurus = $this->akun(PeranSistem::Pengurus);
        $de = $this->akun(PeranSistem::DirekturEksekutif);
        $staf = $this->akun(PeranSistem::Staf);
        Personel::factory()->create(['user_id' => $pengurus->id]);
        Personel::factory()->create(['user_id' => $de->id]);
        $personelStaf = Personel::factory()->create(['user_id' => $staf->id]);
        $this->actingAs($pengurus);

        $arahan = Feedback::query()->create([
            'penerima_id' => $personelStaf->id,
            'jenis' => JenisFeedback::Arahan,
            'isi' => 'Perbaiki rekap',
            'butuh_tindakan' => true,
        ]);

        app(PengirimNotifikasi::class)->arahanBaru($arahan);

        $this->assertCount(1, $staf->fresh()->notifications);
        $this->assertCount(1, $de->fresh()->notifications);
        $this->assertSame('arahan', $staf->fresh()->notifications->first()->data['jenis']);
    }

    #[Test]
    public function kegagalan_notifikasi_tidak_membatalkan_data_utama(): void
    {
        $de = $this->akun(PeranSistem::DirekturEksekutif);
        Personel::factory()->create(['user_id' => $de->id]);
        $pic = Personel::factory()->create();
        $this->actingAs($de);

        app()->instance(PengirimNotifikasi::class, new class extends PengirimNotifikasi
        {
            protected function kirim(User $penerima, Notification $notifikasi): void
            {
                throw new RuntimeException('kanal mati');
            }
        });

        $tugas = Assignment::buatDenganKodeUnik(fn (Assignment $t) => $t->fill([
            'judul' => 'Uji',
            'deskripsi' => 'Uji',
            'pic_id' => $pic->id,
            'kategori' => KategoriTugas::Umum,
            'target_keluaran' => 'X',
            'prioritas' => PrioritasTugas::Sedang,
            'tanggal_mulai' => ServerClock::now()->toDateString(),
            'tenggat' => ServerClock::now()->addDay(),
        ]));

        app(PengirimNotifikasi::class)->penugasanBaru($tugas);

        $this->assertDatabaseHas('assignments', ['id' => $tugas->id]);
        $this->assertDatabaseHas('audit_events', ['event' => 'notifikasi.gagal']);
        $this->assertDatabaseCount('notifications', 0);
    }

    // ---------------------------------------------- 8. Ringkasan tunggakan

    #[Test]
    public function tunggakan_staf_hanya_miliknya_sendiri(): void
    {
        $staf = $this->akun(PeranSistem::Staf);
        $personel = Personel::factory()->create(['user_id' => $staf->id]);
        $lain = Personel::factory()->create();

        Assignment::factory()->create(['pic_id' => $personel->id, 'status' => StatusPenugasan::Aktif]);
        Assignment::factory()->create(['pic_id' => $lain->id, 'status' => StatusPenugasan::Aktif]);

        $ringkas = RingkasanTunggakan::untuk($staf);

        $this->assertCount(1, $ringkas['tugas']);
        $this->assertSame((int) $personel->id, (int) $ringkas['tugas']->first()->pic_id);
    }

    #[Test]
    public function admin_tidak_memiliki_tunggakan_operasional(): void
    {
        $admin = $this->akun(PeranSistem::Admin);
        Assignment::factory()->create(['status' => StatusPenugasan::Aktif]);

        $ringkas = RingkasanTunggakan::untuk($admin);

        $this->assertCount(0, $ringkas['tugas']);
        $this->assertCount(0, $ringkas['tindakLanjut']);
        $this->assertCount(0, $ringkas['arahan']);
        $this->assertSame(0, $ringkas['jumlah']);
    }

    #[Test]
    public function tugas_final_dan_item_selesai_tidak_dihitung_sebagai_tunggakan(): void
    {
        $de = $this->akun(PeranSistem::DirekturEksekutif);
        Personel::factory()->create(['user_id' => $de->id]);

        $aktif = Assignment::factory()->create(['status' => StatusPenugasan::Aktif]);
        Assignment::factory()->create([
            'status' => StatusPenugasan::Terverifikasi,
            'progres' => 100,
        ]);

        ActionItem::factory()->create([
            'assignment_id' => $aktif->id,
            'status' => StatusActionItem::DalamProses,
        ]);
        ActionItem::factory()->create([
            'assignment_id' => $aktif->id,
            'status' => StatusActionItem::TerverifikasiSelesai,
            'diverifikasi_oleh' => Personel::factory(),
        ]);

        $ringkas = RingkasanTunggakan::untuk($de);

        $this->assertSame(1, $ringkas['tugas']->count());
        $this->assertSame(1, $ringkas['tindakLanjut']->count());
        $this->assertSame(2, $ringkas['jumlah']);
    }

    // ------------------------------------------------ 9. Policy penugasan

    #[Test]
    public function staf_tidak_dapat_menyunting_tugas_dan_hanya_boleh_progres_miliknya(): void
    {
        $staf = $this->akun(PeranSistem::Staf);
        $personel = Personel::factory()->create(['user_id' => $staf->id]);
        $milikSendiri = Assignment::factory()->create(['pic_id' => $personel->id]);
        $milikOrangLain = Assignment::factory()->create(['pic_id' => Personel::factory()]);

        $this->assertFalse($staf->can('sunting', $milikSendiri));
        $this->assertTrue($staf->can('progres', $milikSendiri));
        $this->assertTrue($staf->can('ajukan', $milikSendiri));
        $this->assertFalse($staf->can('progres', $milikOrangLain));
        $this->assertFalse($staf->can('batalkan', $milikSendiri));
    }

    #[Test]
    public function hanya_de_yang_dapat_memverifikasi_menutup_dan_mengaktifkan_tugas(): void
    {
        $tugas = Assignment::factory()->create([
            'status' => StatusPenugasan::DiajukanSelesai,
            'progres' => 100,
        ]);

        $de = $this->akun(PeranSistem::DirekturEksekutif);
        Personel::factory()->create(['user_id' => $de->id]);

        $this->assertTrue($de->can('verify', $tugas));
        $this->assertTrue($de->can('tutup', $tugas));
        $this->assertTrue($de->can('aktifkan', $tugas));

        foreach ([PeranSistem::Pengurus, PeranSistem::Staf] as $peran) {
            $user = $this->akun($peran);
            Personel::factory()->create(['user_id' => $user->id]);

            $this->assertFalse($user->can('verify', $tugas), "Peran {$peran->value} seharusnya tidak boleh verifikasi.");
            $this->assertFalse($user->can('tutup', $tugas));
        }
    }

    #[Test]
    public function pengurus_hanya_mengubah_dan_membatalkan_tugas_yang_ia_terbitkan(): void
    {
        $pengurus = $this->akun(PeranSistem::Pengurus);
        $personel = Personel::factory()->create(['user_id' => $pengurus->id]);

        $miliknya = Assignment::factory()->create(['pemberi_tugas_id' => $personel->id]);
        $milikDe = Assignment::factory()->create(['pemberi_tugas_id' => Personel::factory()]);

        $this->assertTrue($pengurus->can('sunting', $miliknya));
        $this->assertTrue($pengurus->can('batalkan', $miliknya));
        $this->assertFalse($pengurus->can('sunting', $milikDe));
        $this->assertFalse($pengurus->can('batalkan', $milikDe));
    }

    #[Test]
    public function persetujuan_formal_hanya_untuk_pengurus_pada_baris_non_persetujuan(): void
    {
        $pengurus = $this->akun(PeranSistem::Pengurus);
        Personel::factory()->create(['user_id' => $pengurus->id]);
        $de = $this->akun(PeranSistem::DirekturEksekutif);
        Personel::factory()->create(['user_id' => $de->id]);

        $komentar = Feedback::factory()->create([
            'jenis' => JenisFeedback::Komentar,
            'asal' => AsalFeedback::Pengurus,
        ]);
        $sudahPersetujuan = Feedback::factory()->create([
            'jenis' => JenisFeedback::Persetujuan,
            'asal' => AsalFeedback::Pengurus,
        ]);

        $this->assertTrue($pengurus->can('setujui', $komentar));
        $this->assertFalse($pengurus->can('setujui', $sudahPersetujuan));
        $this->assertFalse($de->can('setujui', $komentar));
    }

    #[Test]
    public function penutupan_action_item_hanya_untuk_pemegang_verifikasi(): void
    {
        $item = ActionItem::factory()->create(['status' => StatusActionItem::DiajukanSelesai]);

        $de = $this->akun(PeranSistem::DirekturEksekutif);
        Personel::factory()->create(['user_id' => $de->id]);
        $staf = $this->akun(PeranSistem::Staf);
        Personel::factory()->create(['user_id' => $staf->id]);

        $this->assertTrue($de->can('tutup', $item));
        $this->assertTrue($de->can('verifikasi', $item));
        $this->assertFalse($staf->can('tutup', $item));
        $this->assertFalse($staf->can('verifikasi', $item));
    }

    // ------------------------------------------- 10. Alur HTTP penugasan

    #[Test]
    public function de_membuat_tugas_lewat_formulir_dengan_sumber_perintah_otomatis(): void
    {
        $de = $this->akun(PeranSistem::DirekturEksekutif);
        $personelDe = Personel::factory()->create(['user_id' => $de->id]);
        $pic = Personel::factory()->create(['status_aktif' => true]);

        $this->actingAs($de)->post(route('tugas.store'), [
            'judul' => 'Rekap Q4',
            'deskripsi' => 'Menyusun rekap kegiatan kuartal keempat.',
            'pic_id' => $pic->id,
            'kategori' => 'umum',
            'target_keluaran' => 'Berkas rekap',
            'prioritas' => 'sedang',
            'tanggal_mulai' => ServerClock::now()->toDateString(),
            'tenggat' => ServerClock::now()->addDays(5)->toDateTimeString(),
            // Upaya mengubah jejak instruksi dari formulir; harus diabaikan.
            'sumber_perintah' => 'pengurus_langsung',
            'pemberi_tugas_id' => $pic->id,
        ])->assertRedirect();

        $tugas = Assignment::query()->latest('id')->firstOrFail();

        $this->assertSame(SumberPerintah::DeLangsung, $tugas->sumber_perintah);
        $this->assertSame((int) $personelDe->id, (int) $tugas->pemberi_tugas_id);
        $this->assertSame(StatusPenugasan::Aktif, $tugas->status);
        $this->assertSame(0, (int) $tugas->progres);
        $this->assertStringStartsWith('TGS-'.ServerClock::now()->year.'-', (string) $tugas->kode);
        $this->assertDatabaseCount('notifications', 1);
    }

    #[Test]
    public function staf_dan_admin_ditolak_saat_membuat_tugas(): void
    {
        foreach ([PeranSistem::Staf, PeranSistem::Admin] as $peran) {
            $this->actingAs($this->akun($peran))
                ->post(route('tugas.store'), [])
                ->assertForbidden();
        }
    }

    #[Test]
    public function de_meneruskan_arahan_pengurus_menjadi_tugas(): void
    {
        $de = $this->akun(PeranSistem::DirekturEksekutif);
        $personelDe = Personel::factory()->create(['user_id' => $de->id]);
        $pengurus = $this->akun(PeranSistem::Pengurus);
        $personelPengurus = Personel::factory()->create(['user_id' => $pengurus->id]);
        $pic = Personel::factory()->create(['status_aktif' => true]);

        $arahan = Feedback::factory()->create([
            'pemberi_id' => $personelPengurus->id,
            'penerima_id' => $personelDe->id,
            'asal' => AsalFeedback::Pengurus,
            'jenis' => JenisFeedback::Arahan,
            'status' => StatusFeedback::Terbuka,
            'butuh_tindakan' => true,
        ]);

        $this->actingAs($de)->post(route('tugas.store'), [
            'judul' => 'Tindak lanjut arahan',
            'deskripsi' => 'Melaksanakan arahan Pengurus.',
            'pic_id' => $pic->id,
            'kategori' => 'utama',
            'target_keluaran' => 'Laporan pelaksanaan',
            'prioritas' => 'tinggi',
            'tanggal_mulai' => ServerClock::now()->toDateString(),
            'tenggat' => ServerClock::now()->addDays(7)->toDateTimeString(),
            'sumber_arahan_id' => $arahan->id,
        ])->assertRedirect();

        $tugas = Assignment::query()->latest('id')->firstOrFail();

        $this->assertSame(SumberPerintah::PengurusMelaluiDe, $tugas->sumber_perintah);
        $this->assertSame((int) $personelPengurus->id, (int) $tugas->pemberi_tugas_id);
        $this->assertSame((int) $personelDe->id, (int) $tugas->diteruskan_oleh_id);
        $this->assertSame(StatusFeedback::Ditindaklanjuti, $arahan->fresh()->status);
        // PIC menerima notifikasi penugasan, Pengurus menerima kabar arahan
        // diteruskan.
        $this->assertDatabaseCount('notifications', 2);
    }

    #[Test]
    public function de_tidak_dapat_meneruskan_arahan_milik_orang_lain(): void
    {
        $de = $this->akun(PeranSistem::DirekturEksekutif);
        Personel::factory()->create(['user_id' => $de->id]);
        $pic = Personel::factory()->create(['status_aktif' => true]);

        $arahan = Feedback::factory()->create([
            'penerima_id' => Personel::factory()->create()->id,
            'asal' => AsalFeedback::Pengurus,
        ]);

        // Factory arahan ikut membuat satu tugas sebagai subjeknya; yang
        // diuji adalah tidak ada tugas *baru* dari permintaan ini.
        $sebelum = Assignment::query()->count();

        $this->actingAs($de)->post(route('tugas.store'), [
            'judul' => 'Tindak lanjut',
            'deskripsi' => 'Melaksanakan arahan.',
            'pic_id' => $pic->id,
            'kategori' => 'umum',
            'target_keluaran' => 'Laporan',
            'prioritas' => 'sedang',
            'tanggal_mulai' => ServerClock::now()->toDateString(),
            'tenggat' => ServerClock::now()->addDays(3)->toDateTimeString(),
            'sumber_arahan_id' => $arahan->id,
        ])->assertSessionHasErrors('sumber_arahan_id');

        $this->assertSame($sebelum, Assignment::query()->count());
    }

    #[Test]
    public function pengurus_membuat_tugas_langsung_mengirim_notifikasi_ke_de(): void
    {
        $pengurus = $this->akun(PeranSistem::Pengurus);
        Personel::factory()->create(['user_id' => $pengurus->id]);
        $de = $this->akun(PeranSistem::DirekturEksekutif);
        Personel::factory()->create(['user_id' => $de->id]);
        $pic = Personel::factory()->create(['status_aktif' => true]);

        $this->actingAs($pengurus)->post(route('tugas.store'), [
            'judul' => 'Instruksi langsung',
            'deskripsi' => 'Arahan langsung Pengurus ke staf.',
            'pic_id' => $pic->id,
            'kategori' => 'lintas_bidang',
            'target_keluaran' => 'Catatan pelaksanaan',
            'prioritas' => 'mendesak',
            'tanggal_mulai' => ServerClock::now()->toDateString(),
            'tenggat' => ServerClock::now()->addDays(2)->toDateTimeString(),
        ])->assertRedirect();

        $tugas = Assignment::query()->latest('id')->firstOrFail();

        $this->assertSame(SumberPerintah::PengurusLangsung, $tugas->sumber_perintah);
        // PIC dan DE: arahan langsung Pengurus tidak boleh tersembunyi dari DE.
        $this->assertDatabaseCount('notifications', 2);
    }

    #[Test]
    public function verifikasi_tugas_hanya_untuk_de_dan_wajib_alasan(): void
    {
        $tugas = Assignment::factory()->create([
            'status' => StatusPenugasan::DiajukanSelesai,
            'progres' => 100,
        ]);

        foreach ([PeranSistem::Staf, PeranSistem::Pengurus] as $peran) {
            $user = $this->akun($peran);
            Personel::factory()->create(['user_id' => $user->id]);

            $this->actingAs($user)
                ->patch(route('tugas.verifikasi', $tugas), ['alasan' => 'Sudah diperiksa'])
                ->assertForbidden();
        }

        $de = $this->akun(PeranSistem::DirekturEksekutif);
        Personel::factory()->create(['user_id' => $de->id]);

        $this->actingAs($de)
            ->patch(route('tugas.verifikasi', $tugas), ['alasan' => 'pendek'])
            ->assertSessionHasErrors('alasan');

        $this->actingAs($de)
            ->patch(route('tugas.verifikasi', $tugas), ['alasan' => 'Bukti kerja lengkap dan sesuai target.'])
            ->assertRedirect();

        $segar = $tugas->fresh();

        $this->assertSame(StatusPenugasan::Terverifikasi, $segar->status);
        $this->assertNotNull($segar->diverifikasi_pada);
    }

    #[Test]
    public function penutupan_lewat_http_wajib_alasan_dan_bukti(): void
    {
        $tugas = Assignment::factory()->create([
            'status' => StatusPenugasan::Terverifikasi,
            'progres' => 100,
        ]);

        $de = $this->akun(PeranSistem::DirekturEksekutif);
        Personel::factory()->create(['user_id' => $de->id]);

        $this->actingAs($de)
            ->patch(route('tugas.tutup', $tugas), [
                'alasan_penutupan' => 'Seluruh keluaran sudah diterima.',
                'bukti_penutupan' => '',
            ])
            ->assertSessionHasErrors('bukti_penutupan');

        $this->actingAs($de)
            ->patch(route('tugas.tutup', $tugas), [
                'alasan_penutupan' => 'Seluruh keluaran sudah diterima.',
                'bukti_penutupan' => 'Berita acara 12/XI/2026',
            ])
            ->assertRedirect();

        $this->assertSame(StatusPenugasan::Ditutup, $tugas->fresh()->status);
    }

    #[Test]
    public function pic_melaporkan_progres_dan_status_berpindah_ke_dalam_proses(): void
    {
        $staf = $this->akun(PeranSistem::Staf);
        $personel = Personel::factory()->create(['user_id' => $staf->id]);
        $tugas = Assignment::factory()->create([
            'pic_id' => $personel->id,
            'status' => StatusPenugasan::Aktif,
            'progres' => 0,
        ]);

        $this->actingAs($staf)
            ->patch(route('tugas.progres', $tugas), ['progres' => 40])
            ->assertRedirect();

        $segar = $tugas->fresh();

        $this->assertSame(40, (int) $segar->progres);
        $this->assertSame(StatusPenugasan::DalamProses, $segar->status);
    }

    #[Test]
    public function staf_tidak_dapat_membuka_tugas_personel_lain(): void
    {
        $staf = $this->akun(PeranSistem::Staf);
        Personel::factory()->create(['user_id' => $staf->id]);
        $tugasLain = Assignment::factory()->create(['pic_id' => Personel::factory()->create()->id]);

        $this->actingAs($staf)->get(route('tugas.show', $tugasLain))->assertForbidden();
    }

    // ------------------------------------------------ 11. Layar penugasan

    #[Test]
    public function layar_daftar_formulir_dan_detail_penugasan_dapat_dibuka(): void
    {
        $de = $this->akun(PeranSistem::DirekturEksekutif);
        $personel = Personel::factory()->create(['user_id' => $de->id]);
        $tugas = Assignment::factory()->create([
            'pemberi_tugas_id' => $personel->id,
            'pic_id' => Personel::factory()->create(['status_aktif' => true])->id,
        ]);

        $this->actingAs($de)->get(route('tugas.index'))->assertOk()->assertSee('Daftar Penugasan');
        $this->actingAs($de)->get(route('tugas.create'))->assertOk()->assertSee('Sumber perintah');
        $this->actingAs($de)->get(route('tugas.show', $tugas))->assertOk()->assertSee($tugas->judul);
        $this->actingAs($de)->get(route('tugas.edit', $tugas))->assertOk();
    }

    #[Test]
    public function staf_hanya_melihat_tugas_miliknya_pada_daftar(): void
    {
        $staf = $this->akun(PeranSistem::Staf);
        $personel = Personel::factory()->create(['user_id' => $staf->id]);
        $miliknya = Assignment::factory()->create(['pic_id' => $personel->id]);
        $milikLain = Assignment::factory()->create(['pic_id' => Personel::factory()->create()->id]);

        $this->actingAs($staf)
            ->get(route('tugas.index'))
            ->assertOk()
            ->assertSee($miliknya->judul)
            ->assertDontSee($milikLain->judul);

        // Tanpa izin buat, tombol Tugas Baru tidak dirender.
        $this->actingAs($staf)->get(route('tugas.index'))->assertDontSee('Tugas Baru');
    }

    #[Test]
    public function menu_modul_penugasan_dialihkan_ke_layar_tugas(): void
    {
        $de = $this->akun(PeranSistem::DirekturEksekutif);
        Personel::factory()->create(['user_id' => $de->id]);

        $this->actingAs($de)
            ->get(route('modul.show', 'assignment'))
            ->assertRedirect(route('tugas.index'));
    }

    // -------------------------------------- 12. Alur HTTP arahan & tindak lanjut

    #[Test]
    public function de_melihat_arahan_pengurus_kepada_staf_tetapi_staf_lain_tidak(): void
    {
        $pengurus = $this->akun(PeranSistem::Pengurus);
        $de = $this->akun(PeranSistem::DirekturEksekutif);
        $stafA = $this->akun(PeranSistem::Staf);
        $stafB = $this->akun(PeranSistem::Staf);
        Personel::factory()->create(['user_id' => $pengurus->id]);
        Personel::factory()->create(['user_id' => $de->id]);
        $personelA = Personel::factory()->create(['user_id' => $stafA->id]);
        Personel::factory()->create(['user_id' => $stafB->id]);

        $this->actingAs($pengurus);
        $arahan = Feedback::query()->create([
            'penerima_id' => $personelA->id,
            'jenis' => JenisFeedback::Arahan,
            'isi' => 'Koordinasi',
            'butuh_tindakan' => true,
        ]);

        $this->actingAs($de)->get(route('arahan.show', $arahan))->assertOk();
        $this->actingAs($stafA)->get(route('arahan.show', $arahan))->assertOk();
        $this->actingAs($stafB)->get(route('arahan.show', $arahan))->assertForbidden();
    }

    #[Test]
    public function persetujuan_formal_hanya_dibuat_pengurus_dan_tidak_mengubah_status_objek(): void
    {
        $pengurus = $this->akun(PeranSistem::Pengurus);
        $de = $this->akun(PeranSistem::DirekturEksekutif);
        Personel::factory()->create(['user_id' => $pengurus->id]);
        Personel::factory()->create(['user_id' => $de->id]);

        $arahan = Feedback::factory()->create(['asal' => AsalFeedback::Pengurus, 'jenis' => JenisFeedback::Arahan]);

        $this->actingAs($de)->post(route('arahan.setujui', $arahan), ['isi' => 'Setuju'])->assertForbidden();
        $this->actingAs($pengurus)->post(route('arahan.setujui', $arahan), ['isi' => 'Setuju'])->assertRedirect();

        $this->assertDatabaseHas('feedback', ['jenis' => 'persetujuan', 'asal' => 'pengurus', 'butuh_tindakan' => false]);
        $this->assertSame(StatusFeedback::Terbuka, $arahan->fresh()->status);
    }

    #[Test]
    public function pengurus_mengirim_arahan_lewat_http_dan_penerima_dinotifikasi(): void
    {
        $pengurus = $this->akun(PeranSistem::Pengurus);
        Personel::factory()->create(['user_id' => $pengurus->id]);
        $staf = $this->akun(PeranSistem::Staf);
        $penerima = Personel::factory()->create(['user_id' => $staf->id, 'status_aktif' => true]);

        $this->actingAs($pengurus)->post(route('arahan.store'), [
            'penerima_id' => $penerima->id,
            'jenis' => 'arahan',
            'isi' => 'Mohon siapkan bahan rapat koordinasi pekan depan.',
            'butuh_tindakan' => 1,
            'tenggat' => ServerClock::now()->addDays(3)->toDateString(),
            // Upaya memalsukan identitas; harus ditimpa dari sesi.
            'asal' => 'de',
        ])->assertRedirect();

        $arahan = Feedback::query()->latest('id')->firstOrFail();

        $this->assertSame(AsalFeedback::Pengurus, $arahan->asal);
        $this->assertSame((int) $pengurus->personel->id, (int) $arahan->pemberi_id);
        // Penerima + seluruh DE (arahan langsung selalu terlihat DE).
        $this->assertGreaterThanOrEqual(1, $staf->notifications()->count());
    }

    #[Test]
    public function staf_tidak_dapat_membuat_arahan(): void
    {
        $staf = $this->akun(PeranSistem::Staf);
        Personel::factory()->create(['user_id' => $staf->id]);

        $this->actingAs($staf)
            ->post(route('arahan.store'), [
                'penerima_id' => Personel::factory()->create()->id,
                'jenis' => 'arahan',
                'isi' => 'Coba-coba',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function de_menambah_tindak_lanjut_dari_arahan_dan_pic_dinotifikasi(): void
    {
        $de = $this->akun(PeranSistem::DirekturEksekutif);
        Personel::factory()->create(['user_id' => $de->id]);
        $staf = $this->akun(PeranSistem::Staf);
        $pic = Personel::factory()->create(['user_id' => $staf->id, 'status_aktif' => true]);

        $arahan = Feedback::factory()->create([
            'asal' => AsalFeedback::De,
            'jenis' => JenisFeedback::Arahan,
            'butuh_tindakan' => true,
            'penerima_id' => $pic->id,
        ]);

        $this->actingAs($de)->post(route('arahan.tindak-lanjut', $arahan), [
            'deskripsi' => 'Susun daftar hadir peserta.',
            'pic_id' => $pic->id,
            'tenggat' => ServerClock::now()->addDays(2)->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('action_items', [
            'feedback_id' => $arahan->id,
            'pic_id' => $pic->id,
            'status' => 'terbuka',
        ]);
        $this->assertGreaterThanOrEqual(1, $staf->notifications()->count());
    }

    #[Test]
    public function action_item_hanya_diverifikasi_dan_ditutup_de_dengan_bukti(): void
    {
        $staf = $this->akun(PeranSistem::Staf);
        $personel = Personel::factory()->create(['user_id' => $staf->id]);
        $item = ActionItem::factory()->create(['pic_id' => $personel->id]);

        $this->actingAs($staf)
            ->patch(route('tindak-lanjut.update', $item), [
                'status' => 'dalam_proses',
                'alasan' => 'Sedang dikerjakan bersama tim.',
            ])->assertRedirect();

        $this->assertSame(StatusActionItem::DalamProses, $item->fresh()->status);

        $this->actingAs($staf)
            ->patch(route('tindak-lanjut.verifikasi', $item), ['alasan' => 'Selesai semua'])
            ->assertForbidden();

        $de = $this->akun(PeranSistem::DirekturEksekutif);
        Personel::factory()->create(['user_id' => $de->id]);

        $this->actingAs($de)
            ->patch(route('tindak-lanjut.tutup', $item), [
                'alasan' => 'Bukti lengkap dan sesuai.',
                'bukti_penutupan' => '',
            ])
            ->assertSessionHasErrors('bukti_penutupan');

        $this->actingAs($de)
            ->patch(route('tindak-lanjut.tutup', $item), [
                'alasan' => 'Bukti lengkap dan sesuai.',
                'bukti_penutupan' => 'BA-07/XI/2026',
            ])
            ->assertRedirect();

        $this->assertSame(StatusActionItem::Ditutup, $item->fresh()->status);
        $this->assertNotNull($item->fresh()->selesai_pada);
    }

    // ------------------------------------------- 13. Pusat notifikasi

    #[Test]
    public function notifikasi_milik_akun_lain_menghasilkan_404(): void
    {
        $pemilik = $this->akun(PeranSistem::Staf);
        $lain = $this->akun(PeranSistem::Staf);
        $notifikasi = $pemilik->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => 'uji',
            'data' => ['judul' => 'X'],
        ]);

        $this->actingAs($lain)->post(route('notifikasi.baca', $notifikasi->id))->assertNotFound();
    }

    #[Test]
    public function baca_semua_menghapus_lencana_belum_dibaca(): void
    {
        $user = $this->akun(PeranSistem::Staf);
        $user->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => 'uji',
            'data' => ['judul' => 'X'],
        ]);

        $this->assertSame(1, $user->unreadNotifications()->count());
        $this->actingAs($user)->post(route('notifikasi.baca-semua'))->assertRedirect();
        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    #[Test]
    public function layar_notifikasi_menampilkan_isi_dan_menandai_satu_baris(): void
    {
        $user = $this->akun(PeranSistem::Staf);
        $notifikasi = $user->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => 'uji',
            'data' => [
                'judul' => 'Penugasan baru: TGS-01',
                'pesan' => 'Siapkan rekap.',
                'url' => null,
                'jenis' => 'penugasan',
                'objek_id' => 1,
            ],
        ]);

        $this->actingAs($user)
            ->get(route('notifikasi.index'))
            ->assertOk()
            ->assertSee('Penugasan baru: TGS-01')
            ->assertSee('Siapkan rekap.');

        $this->actingAs($user)->post(route('notifikasi.baca', $notifikasi->id))->assertRedirect();
        $this->assertNotNull($notifikasi->fresh()->read_at);
        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    // ---------------------------------------- 14. Panel tunggakan dashboard

    #[Test]
    public function dashboard_staf_menampilkan_tunggakan_miliknya(): void
    {
        $staf = $this->akun(PeranSistem::Staf);
        $personel = Personel::factory()->create(['user_id' => $staf->id]);
        Assignment::factory()->create([
            'pic_id' => $personel->id,
            'status' => StatusPenugasan::Aktif,
            'judul' => 'Rekap presensi bidang',
        ]);

        $this->actingAs($staf)
            ->get(route('staf.dashboard'))
            ->assertOk()
            ->assertSee('Tunggakan Anda')
            ->assertSee('Rekap presensi bidang');
    }

    #[Test]
    public function dashboard_admin_menyatakan_tidak_ada_tunggakan(): void
    {
        $admin = $this->akun(PeranSistem::Admin);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Tidak ada tunggakan.');
    }
}
