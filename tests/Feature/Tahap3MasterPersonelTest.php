<?php

namespace Tests\Feature;

use App\Enums\FrekuensiTugas;
use App\Enums\KategoriPersonel;
use App\Enums\KategoriTugas;
use App\Enums\PeranSistem;
use App\Models\AuditEvent;
use App\Models\Bidang;
use App\Models\Jabatan;
use App\Models\JobDescription;
use App\Models\Personel;
use App\Models\Report;
use App\Models\User;
use App\Support\IdentitasPelapor;
use App\Support\ServerClock;
use Database\Seeders\BidangSeeder;
use Database\Seeders\PeranIzinSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Kriteria penerimaan Tahap 3 — Master Personel, Jabatan, & Job Description.
 *
 * Setiap pengujian memakai basis data MySQL sungguhan dan matriks izin hasil
 * seeder, sehingga yang diuji adalah perilaku sistem yang sebenarnya, bukan
 * izin yang disuntikkan di dalam tes.
 */
class Tahap3MasterPersonelTest extends TestCase
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
     * @param  array<string, mixed>  $atribut
     */
    private function personel(User $user, array $atribut = []): Personel
    {
        return Personel::factory()->create(array_merge([
            'user_id' => $user->id,
            'nip' => 'NIP-'.Str::upper(Str::random(8)),
            'nama_lengkap' => 'Personel '.Str::random(6),
        ], $atribut));
    }

    /**
     * @param  array<string, mixed>  $tambahan
     * @return array<string, mixed>
     */
    private function payloadPersonel(User $akun, Jabatan $jabatan, ?Bidang $bidang = null, array $tambahan = []): array
    {
        return array_merge([
            'user_id' => $akun->getKey(),
            'nip' => 'NIP-'.Str::upper(Str::random(8)),
            'nama_lengkap' => 'Nama Resmi '.Str::random(5),
            'jabatan_id' => $jabatan->getKey(),
            'bidang_id' => $bidang?->getKey(),
            'kategori' => KategoriPersonel::Staf->value,
            'tanggal_masuk' => ServerClock::now()->subYear()->toDateString(),
            'telepon' => '0812-3456-7890',
        ], $tambahan);
    }

    /**
     * @param  array<string, mixed>  $tambahan
     * @return array<string, mixed>
     */
    private function payloadJobDescription(Jabatan $jabatan, array $tambahan = []): array
    {
        return array_merge([
            'jabatan_id' => $jabatan->getKey(),
            'kode' => 'JD-'.Str::upper(Str::random(6)),
            'kategori' => KategoriTugas::Utama->value,
            'uraian' => 'Menyusun dan melaksanakan program kerja bidang.',
            'indikator' => 'Program terlaksana sesuai target keluaran.',
            'target' => 'Seluruh program bidang terlaksana dalam satu periode.',
            'frekuensi' => FrekuensiTugas::Bulanan->value,
        ], $tambahan);
    }

    // ------------------------------------------- 1. CRUD personel + audit

    #[Test]
    public function admin_dapat_menambah_mengubah_dan_menonaktifkan_personel_dengan_jejak_audit(): void
    {
        $admin = $this->akun(PeranSistem::Admin);
        $calon = $this->akun(PeranSistem::Staf);
        $jabatan = Jabatan::factory()->create();
        $bidang = Bidang::factory()->create();

        $payload = $this->payloadPersonel($calon, $jabatan, $bidang, ['nama_lengkap' => 'Rani Puspita']);

        $this->actingAs($admin)
            ->post(route('master.personel.store'), $payload)
            ->assertRedirect();

        $personel = Personel::query()->where('user_id', $calon->getKey())->firstOrFail();

        $this->assertSame('Rani Puspita', $personel->nama_lengkap);
        $this->assertTrue($personel->status_aktif);

        $this->assertDatabaseHas('audit_events', [
            'event' => 'created',
            'auditable_type' => Personel::class,
            'auditable_id' => $personel->getKey(),
            'actor_id' => $admin->getKey(),
        ]);

        $this->actingAs($admin)
            ->put(route('master.personel.update', $personel), $this->payloadPersonel($calon, $jabatan, $bidang, [
                'nama_lengkap' => 'Rani Puspita Sari',
            ]))
            ->assertRedirect(route('master.personel.show', $personel));

        $this->assertSame('Rani Puspita Sari', $personel->fresh()->nama_lengkap);

        $this->assertDatabaseHas('audit_events', [
            'event' => 'updated',
            'auditable_type' => Personel::class,
            'auditable_id' => $personel->getKey(),
        ]);

        $this->actingAs($admin)
            ->patch(route('master.personel.nonaktifkan', $personel), [
                'alasan' => 'Mengakhiri masa kerja kepengurusan periode ini.',
            ])
            ->assertRedirect(route('master.personel.show', $personel));

        $this->assertFalse($personel->fresh()->status_aktif);

        $auditNonaktif = AuditEvent::query()
            ->where('auditable_type', Personel::class)
            ->where('auditable_id', $personel->getKey())
            ->where('reason', 'Mengakhiri masa kerja kepengurusan periode ini.')
            ->exists();

        $this->assertTrue($auditNonaktif, 'Alasan penonaktifan harus tercatat pada audit trail.');
    }

    // ---------------------------------------- 2. satu akun = satu personel

    #[Test]
    public function personel_wajib_tertaut_ke_satu_akun_dan_id_personel_unik(): void
    {
        $admin = $this->akun(PeranSistem::Admin);
        $jabatan = Jabatan::factory()->create();

        $pemilik = $this->akun(PeranSistem::Staf);
        $this->personel($pemilik, ['nip' => 'NIP-DIPAKAI']);

        $this->actingAs($admin)
            ->post(route('master.personel.store'), $this->payloadPersonel($pemilik, $jabatan))
            ->assertSessionHasErrors('user_id');

        $calonBaru = $this->akun(PeranSistem::Staf);

        $this->actingAs($admin)
            ->post(route('master.personel.store'), $this->payloadPersonel($calonBaru, $jabatan, null, [
                'nip' => 'NIP-DIPAKAI',
            ]))
            ->assertSessionHasErrors('nip');

        $this->assertSame(1, Personel::query()->where('user_id', $pemilik->getKey())->count());
        $this->assertSame(1, Personel::query()->where('nip', 'NIP-DIPAKAI')->count());
    }

    // ------------------------------------------ 3. staf hanya melihat barisnya

    #[Test]
    public function staf_hanya_melihat_baris_personelnya_sendiri(): void
    {
        $staf = $this->akun(PeranSistem::Staf);
        $milikSendiri = $this->personel($staf, ['nama_lengkap' => 'Staf Pemilik Baris']);
        $orangLain = $this->personel(
            $this->akun(PeranSistem::Staf),
            ['nama_lengkap' => 'Staf Baris Lain']
        );

        $this->actingAs($staf)
            ->get(route('master.personel.index'))
            ->assertOk()
            ->assertSee('Staf Pemilik Baris')
            ->assertDontSee('Staf Baris Lain');

        $this->actingAs($staf)
            ->get(route('master.personel.show', $milikSendiri))
            ->assertOk()
            ->assertSee('Staf Pemilik Baris');

        $this->actingAs($staf)
            ->get(route('master.personel.show', $orangLain))
            ->assertForbidden();
    }

    // ------------------------------------------- 4. staf tidak dapat menulis

    #[Test]
    public function staf_tidak_dapat_menambah_mengubah_atau_menonaktifkan_personel(): void
    {
        $staf = $this->akun(PeranSistem::Staf);
        $milikSendiri = $this->personel($staf);
        $jabatan = Jabatan::factory()->create();

        $this->actingAs($staf)->get(route('master.personel.create'))->assertForbidden();

        $this->actingAs($staf)
            ->post(route('master.personel.store'), $this->payloadPersonel($this->akun(PeranSistem::Staf), $jabatan))
            ->assertForbidden();

        $this->actingAs($staf)
            ->get(route('master.personel.edit', $milikSendiri))
            ->assertForbidden();

        $this->actingAs($staf)
            ->put(route('master.personel.update', $milikSendiri), $this->payloadPersonel($staf, $jabatan, null, [
                'nama_lengkap' => 'Nama Diubah Sendiri',
            ]))
            ->assertForbidden();

        $this->actingAs($staf)
            ->patch(route('master.personel.nonaktifkan', $milikSendiri), [
                'alasan' => 'Mencoba menonaktifkan diri sendiri.',
            ])
            ->assertForbidden();

        $this->assertSame($milikSendiri->nama_lengkap, $milikSendiri->fresh()->nama_lengkap);
        $this->assertTrue($milikSendiri->fresh()->status_aktif);
    }

    // ------------------------------ 5. pengurus & DE membaca tanpa hak ubah

    #[Test]
    public function pengurus_dan_de_membaca_seluruh_personel_tanpa_hak_ubah(): void
    {
        $jabatan = Jabatan::factory()->create();

        $pemilikSatu = $this->akun(PeranSistem::Staf);
        $pemilikDua = $this->akun(PeranSistem::Staf);
        $satu = $this->personel($pemilikSatu, ['nama_lengkap' => 'Personel Satu']);
        $dua = $this->personel($pemilikDua, ['nama_lengkap' => 'Personel Dua']);

        foreach ([PeranSistem::Pengurus, PeranSistem::DirekturEksekutif] as $peran) {
            $pembaca = $this->akun($peran);

            $this->actingAs($pembaca)
                ->get(route('master.personel.index'))
                ->assertOk()
                ->assertSee('Personel Satu')
                ->assertSee('Personel Dua');

            $this->actingAs($pembaca)
                ->get(route('master.personel.show', $dua))
                ->assertOk();

            $this->actingAs($pembaca)
                ->get(route('master.personel.create'))
                ->assertForbidden();

            $this->actingAs($pembaca)
                ->get(route('master.personel.edit', $satu))
                ->assertForbidden();

            $this->actingAs($pembaca)
                ->put(route('master.personel.update', $satu), $this->payloadPersonel($pemilikSatu, $jabatan))
                ->assertForbidden();

            $this->actingAs($pembaca)
                ->patch(route('master.personel.nonaktifkan', $satu), [
                    'alasan' => 'Percobaan penonaktifan oleh pembaca.',
                ])
                ->assertForbidden();
        }

        $this->assertSame('Personel Satu', $satu->fresh()->nama_lengkap);
        $this->assertTrue($satu->fresh()->status_aktif);
    }

    // -------------------------------------------- 6. enam bidang kerja resmi

    #[Test]
    public function bidang_kerja_aktif_tepat_enam_sesuai_fitur_pdf(): void
    {
        Bidang::query()->create([
            'kode' => 'PROGRAM_LAMA',
            'nama' => 'Bidang Program Lama',
            'is_active' => true,
        ]);

        $this->seed(BidangSeeder::class);

        $aktif = Bidang::query()
            ->where('is_active', true)
            ->orderBy('kode')
            ->pluck('kode')
            ->all();

        $this->assertSame([
            'ADMINISTRASI',
            'HI_PAMERAN',
            'KEUANGAN',
            'OPERASIONAL',
            'PUBLIKASI',
            'TI',
        ], $aktif);

        $this->assertFalse(
            Bidang::query()->where('kode', 'PROGRAM_LAMA')->value('is_active'),
            'Bidang lama harus dinonaktifkan, bukan dihapus.'
        );
    }

    // ---------------------------- 7. personel_id terkunci pada sesi pelapor

    #[Test]
    public function personel_id_terisi_dari_sesi_dan_pemalsuan_ditolak_serta_dicatat(): void
    {
        $staf = $this->akun(PeranSistem::Staf);
        $milikSendiri = $this->personel($staf);
        $milikOrangLain = $this->personel($this->akun(PeranSistem::Staf));

        $this->actingAs($staf);

        $laporan = Report::factory()->create(['personel_id' => null]);

        $this->assertSame(
            $milikSendiri->getKey(),
            (int) $laporan->personel_id,
            'personel_id kosong harus diisi dari Master Personel milik sesi.'
        );

        $this->assertTrue(IdentitasPelapor::cocokDenganSesi($milikSendiri->getKey()));
        $this->assertFalse(IdentitasPelapor::cocokDenganSesi($milikOrangLain->getKey()));

        try {
            Report::factory()->create(['personel_id' => $milikOrangLain->getKey()]);
            $this->fail('Pemalsuan personel_id seharusnya ditolak.');
        } catch (AuthorizationException) {
            // Diharapkan: identitas pelapor terkunci pada akun aktif.
        }

        $this->assertDatabaseMissing('reports', [
            'personel_id' => $milikOrangLain->getKey(),
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event' => 'identitas.ditolak',
            'actor_id' => $staf->getKey(),
        ]);
    }

    // ----------------------- 8. identitas tampil dari master, bukan input

    #[Test]
    public function nama_dan_jabatan_pada_identitas_berasal_dari_master_personel(): void
    {
        $jabatan = Jabatan::factory()->create(['nama' => 'Staf Publikasi Resmi']);
        $bidang = Bidang::factory()->create(['nama' => 'Bidang Publikasi Resmi']);

        $staf = $this->akun(PeranSistem::Staf);
        $personel = $this->personel($staf, [
            'nama_lengkap' => 'Nama Resmi Dari Master',
            'jabatan_id' => $jabatan->getKey(),
            'bidang_id' => $bidang->getKey(),
        ]);

        $ringkas = IdentitasPelapor::ringkas($staf);

        $this->assertSame($personel->getKey(), $ringkas['personel_id']);
        $this->assertSame('Nama Resmi Dari Master', $ringkas['nama']);
        $this->assertSame('Staf Publikasi Resmi', $ringkas['jabatan']);
        $this->assertSame('Bidang Publikasi Resmi', $ringkas['bidang']);

        $html = Blade::render(
            '<x-identitas-terkunci :identitas="$identitas" />',
            ['identitas' => $ringkas]
        );

        $this->assertStringContainsString('Nama Resmi Dari Master', $html);
        $this->assertStringContainsString('Staf Publikasi Resmi', $html);
        $this->assertStringContainsString('name="personel_id"', $html);

        $this->assertStringNotContainsString('name="nama_lengkap"', $html);
        $this->assertStringNotContainsString('name="jabatan_id"', $html);
    }

    // --------------------------- 9. DE menyusun draf, admin yang menetapkan

    #[Test]
    public function de_dapat_membuat_draf_jd_tetapi_hanya_admin_yang_dapat_menetapkan(): void
    {
        $de = $this->akun(PeranSistem::DirekturEksekutif);
        $admin = $this->akun(PeranSistem::Admin);
        $jabatan = Jabatan::factory()->create();

        $this->assertFalse($de->can('personel.configure'));
        $this->assertTrue($admin->can('personel.configure'));

        $this->actingAs($de)
            ->post(route('master.jd.store'), $this->payloadJobDescription($jabatan))
            ->assertRedirect();

        $jd = JobDescription::query()->firstOrFail();
        $this->assertNull($jd->ditetapkan_pada, 'Draf dari DE tidak boleh langsung terkunci.');

        $this->actingAs($de)
            ->patch(route('master.jd.tetapkan', $jd), [
                'target' => 'Target versi DE',
                'indikator' => 'Indikator versi DE',
                'frekuensi' => FrekuensiTugas::Bulanan->value,
                'berlaku_mulai' => ServerClock::now()->addWeek()->toDateString(),
                'alasan' => 'Percobaan penetapan oleh Direktur Eksekutif.',
            ])
            ->assertForbidden();

        $this->assertNull($jd->fresh()->ditetapkan_pada);

        $this->actingAs($admin)
            ->patch(route('master.jd.tetapkan', $jd), [
                'target' => 'Target final periode berjalan',
                'indikator' => 'Indikator final periode berjalan',
                'frekuensi' => FrekuensiTugas::Bulanan->value,
                'berlaku_mulai' => ServerClock::now()->addWeek()->toDateString(),
                'alasan' => 'Menetapkan standar evaluasi sebelum periode dimulai.',
            ])
            ->assertRedirect(route('master.jd.show', $jd));

        $jd->refresh();

        $this->assertNotNull($jd->ditetapkan_pada);
        $this->assertSame($admin->getKey(), (int) $jd->ditetapkan_oleh_id);
        $this->assertSame('Target final periode berjalan', $jd->target);
    }

    // ------------------------ 10. penetapan wajib lengkap sebelum dikunci

    #[Test]
    public function penetapan_jd_tanpa_target_indikator_dan_berlaku_mulai_ditolak(): void
    {
        $admin = $this->akun(PeranSistem::Admin);
        $jabatan = Jabatan::factory()->create();

        $jd = JobDescription::factory()->create([
            'jabatan_id' => $jabatan->getKey(),
            'target' => null,
            'indikator' => null,
            'frekuensi' => null,
            'berlaku_mulai' => null,
            'ditetapkan_pada' => null,
        ]);

        $this->actingAs($admin)
            ->patch(route('master.jd.tetapkan', $jd), ['alasan' => 'Tanpa data wajib sama sekali.'])
            ->assertSessionHasErrors(['target', 'indikator', 'frekuensi', 'berlaku_mulai']);

        $this->assertNull($jd->fresh()->ditetapkan_pada);
    }

    // ----------------------- 11. freeze di level model + versi baru

    #[Test]
    public function jd_yang_sudah_ditetapkan_terkunci_dan_perubahan_wajib_lewat_versi_baru(): void
    {
        $admin = $this->akun(PeranSistem::Admin);
        $jabatan = Jabatan::factory()->create();

        $jd = JobDescription::factory()->ditetapkan('-2 months')->create([
            'jabatan_id' => $jabatan->getKey(),
            'kode' => 'JD-KUNCI-01',
            'uraian' => 'Uraian asli yang sudah disepakati.',
            'target' => 'Target asli.',
            'indikator' => 'Indikator asli.',
        ]);

        $this->expectException(\DomainException::class);
        $jd->update(['target' => 'Target diubah di tengah periode.']);
    }

    #[Test]
    public function versi_baru_dengan_berlaku_mulai_lebih_baru_berhasil_dibuat(): void
    {
        $admin = $this->akun(PeranSistem::Admin);
        $jabatan = Jabatan::factory()->create();

        $jd = JobDescription::factory()->ditetapkan('-2 months')->create([
            'jabatan_id' => $jabatan->getKey(),
            'kode' => 'JD-VERSI-01',
        ]);

        $this->actingAs($admin)
            ->get(route('master.jd.edit', $jd))
            ->assertForbidden();

        $berlakuBaru = ServerClock::now()->addMonth()->toDateString();

        $this->actingAs($admin)
            ->post(route('master.jd.versi', $jd), [
                'berlaku_mulai' => $berlakuBaru,
                'alasan' => 'Menyesuaikan target untuk periode evaluasi berikutnya.',
            ])
            ->assertRedirect();

        $versiBaru = JobDescription::query()
            ->where('kode', 'JD-VERSI-01')
            ->where('versi', 2)
            ->firstOrFail();

        $this->assertSame($jd->getKey(), (int) $versiBaru->induk_id);
        $this->assertNull($versiBaru->ditetapkan_pada, 'Versi baru harus mulai sebagai draf.');
        $this->assertSame($berlakuBaru, $versiBaru->berlaku_mulai?->toDateString());

        // Tanggal berlaku versi baru harus setelah versi induk.
        $this->actingAs($admin)
            ->post(route('master.jd.versi', $jd), [
                'berlaku_mulai' => $jd->berlaku_mulai?->toDateString(),
                'alasan' => 'Tanggal berlaku mundur seharusnya ditolak.',
            ])
            ->assertSessionHasErrors('berlaku_mulai');
    }

    // ---------------------------- 12. staf hanya melihat JD jabatannya

    #[Test]
    public function staf_hanya_melihat_job_description_untuk_jabatannya_sendiri(): void
    {
        $jabatanSendiri = Jabatan::factory()->create(['nama' => 'Jabatan Sendiri']);
        $jabatanLain = Jabatan::factory()->create(['nama' => 'Jabatan Lain']);

        $staf = $this->akun(PeranSistem::Staf);
        $this->personel($staf, ['jabatan_id' => $jabatanSendiri->getKey()]);

        $jdSendiri = JobDescription::factory()->ditetapkan()->create([
            'jabatan_id' => $jabatanSendiri->getKey(),
            'kode' => 'JD-MILIK-SENDIRI',
        ]);

        $jdLain = JobDescription::factory()->ditetapkan()->create([
            'jabatan_id' => $jabatanLain->getKey(),
            'kode' => 'JD-JABATAN-LAIN',
        ]);

        $this->actingAs($staf)
            ->get(route('master.jd.index'))
            ->assertOk()
            ->assertSee('JD-MILIK-SENDIRI')
            ->assertDontSee('JD-JABATAN-LAIN');

        $this->actingAs($staf)
            ->get(route('master.jd.show', $jdSendiri))
            ->assertOk();

        $this->actingAs($staf)
            ->get(route('master.jd.show', $jdLain))
            ->assertForbidden();
    }

    // --------------------------------- 13. matriks izin personel.configure

    #[Test]
    public function izin_konfigurasi_personel_hanya_dipegang_administrator(): void
    {
        $this->assertSame(56, Permission::query()->count());
        $this->assertTrue(Permission::query()->where('name', 'personel.configure')->exists());

        $admin = $this->akun(PeranSistem::Admin);
        $pengurus = $this->akun(PeranSistem::Pengurus);
        $de = $this->akun(PeranSistem::DirekturEksekutif);
        $staf = $this->akun(PeranSistem::Staf);

        $this->assertTrue($admin->can('personel.configure'));
        $this->assertFalse($pengurus->can('personel.configure'));
        $this->assertFalse($de->can('personel.configure'));
        $this->assertFalse($staf->can('personel.configure'));

        $this->assertFalse($pengurus->can('personel.update'));
        $this->assertFalse($staf->can('personel.update'));

        // DE memegang izin menyusun draf Job Description, tetapi bukan penetapan.
        $this->assertTrue($de->can('personel.create'));
        $this->assertFalse($de->can('personel.configure'));
    }
}
