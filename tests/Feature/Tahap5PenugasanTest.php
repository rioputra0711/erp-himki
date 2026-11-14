<?php

namespace Tests\Feature;

use App\Enums\AsalFeedback;
use App\Enums\JenisFeedback;
use App\Enums\KategoriTugas;
use App\Enums\PeranSistem;
use App\Enums\PrioritasTugas;
use App\Enums\StatusActionItem;
use App\Enums\StatusPenugasan;
use App\Enums\SumberPerintah;
use App\Models\ActionItem;
use App\Models\Assignment;
use App\Models\Feedback;
use App\Models\Personel;
use App\Models\User;
use App\Support\PengirimNotifikasi;
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
}
