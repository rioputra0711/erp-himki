<?php

namespace Tests\Feature;

use App\Enums\KlasifikasiDokumen;
use App\Enums\PeranSistem;
use App\Models\AuditEvent;
use App\Models\Document;
use App\Models\Personel;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use App\Support\CakupanPengguna;
use Database\Seeders\PeranIzinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Kriteria penerimaan Tahap 2 — Autentikasi & Otorisasi Server-Side (RBAC).
 *
 * Setiap pengujian memakai basis data MySQL sungguhan dan matriks izin hasil
 * seeder, bukan izin yang disuntikkan di dalam tes, supaya yang diuji adalah
 * perilaku sistem yang sebenarnya.
 */
class Tahap2AutentikasiRbacTest extends TestCase
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

    private function personel(User $user): Personel
    {
        return Personel::factory()->create(['user_id' => $user->id]);
    }

    // ------------------------------------------------------- 1. gerbang login

    #[Test]
    public function tamu_diarahkan_ke_gerbang_login_tanpa_halaman_promosi(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/staf')->assertRedirect('/login');
        $this->get('/modul/report')->assertRedirect('/login');

        $this->get('/login')
            ->assertOk()
            ->assertSee('Masuk ke akun Anda');
    }

    #[Test]
    public function registrasi_publik_tidak_tersedia(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Penyusup',
            'email' => 'penyusup@himki.test',
            'password' => 'RahasiaSekali123!',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'penyusup@himki.test']);
    }

    // --------------------------------------------------- 2. login email/username

    #[Test]
    public function login_dengan_email_maupun_username_sama_sama_berhasil(): void
    {
        $user = $this->akun(PeranSistem::Staf);

        $this->post('/login', ['login' => $user->email, 'password' => 'password'])
            ->assertRedirect('/staf');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout');

        $this->post('/login', ['login' => $user->username, 'password' => 'password'])
            ->assertRedirect('/staf');
        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function login_mengarahkan_setiap_peran_ke_ruang_kerjanya(): void
    {
        $harapan = [
            PeranSistem::Pengurus->value => '/pengurus',
            PeranSistem::DirekturEksekutif->value => '/de',
            PeranSistem::Staf->value => '/staf',
            PeranSistem::Admin->value => '/admin',
        ];

        foreach ($harapan as $peran => $tujuan) {
            $user = $this->akun(PeranSistem::from($peran));

            $this->post('/login', ['login' => $user->username, 'password' => 'password'])
                ->assertRedirect($tujuan);

            $this->post('/logout');
        }
    }

    #[Test]
    public function kata_sandi_salah_ditolak_dengan_pesan_generik(): void
    {
        $user = $this->akun(PeranSistem::Staf);

        $this->post('/login', ['login' => $user->username, 'password' => 'salah-sekali'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
        $this->assertSame(
            trans('auth.failed'),
            session('errors')->first('login'),
        );
    }

    #[Test]
    public function akun_tanpa_peran_tidak_bisa_login(): void
    {
        $user = User::factory()->create([
            'username' => 'tanpa-peran',
            'is_active' => true,
        ]);

        $this->post('/login', ['login' => $user->username, 'password' => 'password'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    // ------------------------------------------------------ 3. batas percobaan

    #[Test]
    public function percobaan_login_keenam_dalam_semenit_ditolak(): void
    {
        $user = $this->akun(PeranSistem::Staf);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['login' => $user->username, 'password' => 'salah-'.$i])
                ->assertStatus(302);
        }

        $this->post('/login', ['login' => $user->username, 'password' => 'salah-lagi'])
            ->assertStatus(429);

        $this->assertGuest();
    }

    // ------------------------------------------------------ 4. manajemen sesi

    #[Test]
    public function sesi_diperbarui_dan_sesi_lain_milik_akun_yang_sama_dicabut(): void
    {
        $user = $this->akun(PeranSistem::Staf);

        $this->get('/login');
        $idSebelum = session()->getId();

        DB::table('sessions')->insert([
            'id' => 'sesi-perangkat-lain',
            'user_id' => $user->id,
            'ip_address' => '10.0.0.9',
            'user_agent' => 'perangkat lain',
            'payload' => 'x',
            'last_activity' => time(),
        ]);

        $this->post('/login', ['login' => $user->username, 'password' => 'password'])
            ->assertRedirect('/staf');

        $this->assertNotSame($idSebelum, session()->getId());
        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-perangkat-lain']);
    }

    #[Test]
    public function logout_mengakhiri_sesi_dan_menghapus_jejak_otentikasi(): void
    {
        $user = $this->akun(PeranSistem::Staf);

        $this->actingAs($user);
        $this->post('/logout')->assertRedirect('/');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_events', ['event' => 'auth.logout']);
    }

    // ------------------------------------------------------- 5. akun nonaktif

    #[Test]
    public function akun_nonaktif_tidak_bisa_login(): void
    {
        $user = $this->akun(PeranSistem::Staf, ['is_active' => false]);

        $this->post('/login', ['login' => $user->username, 'password' => 'password'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_events', [
            'event' => 'auth.login_gagal',
            'reason' => 'akun_nonaktif',
        ]);
    }

    #[Test]
    public function akun_yang_dinonaktifkan_saat_sesi_berjalan_langsung_diputus(): void
    {
        $user = $this->akun(PeranSistem::Staf);

        $this->actingAs($user);
        $this->get('/staf')->assertOk();

        $user->forceFill(['is_active' => false])->saveQuietly();

        $this->get('/staf')->assertRedirect('/login');
        $this->assertGuest();
    }

    // -------------------------------------------------- 6. ruang kerja per peran

    #[Test]
    public function setiap_peran_hanya_membuka_ruang_kerjanya_sendiri(): void
    {
        $ruang = [
            PeranSistem::Pengurus->value => '/pengurus',
            PeranSistem::DirekturEksekutif->value => '/de',
            PeranSistem::Staf->value => '/staf',
            PeranSistem::Admin->value => '/admin',
        ];

        foreach ($ruang as $namaPeran => $path) {
            $user = $this->akun(PeranSistem::from($namaPeran));

            $this->actingAs($user)->get($path)->assertOk();

            foreach ($ruang as $lainPeran => $lainPath) {
                if ($lainPath === $path) {
                    continue;
                }

                $this->actingAs($user)->get($lainPath)->assertForbidden();
            }

            $this->post('/logout');
        }
    }

    // ------------------------------------------------ 7. admin tidak menilai

    #[Test]
    public function administrator_ditolak_pada_seluruh_aksi_penilaian(): void
    {
        $admin = $this->akun(PeranSistem::Admin);
        $de = $this->akun(PeranSistem::DirekturEksekutif);
        $review = Review::factory()->create();

        foreach (CakupanPengguna::PENILAIAN_DILARANG_ADMIN as $izin) {
            $this->assertFalse(
                Role::findByName(PeranSistem::Admin->value)->hasPermissionTo($izin),
                "Peran admin seharusnya tidak memegang izin {$izin}.",
            );
            $this->assertFalse(
                Gate::forUser($admin)->allows($izin),
                "Admin seharusnya ditolak pada {$izin}.",
            );
        }

        $this->assertFalse(Gate::forUser($admin)->allows('verify', $review));
        $this->assertTrue(Gate::forUser($de)->allows('verify', $review));
    }

    // ------------------------------------------------- 8. cakupan baris (staf)

    #[Test]
    public function staf_tidak_bisa_melihat_atau_mengubah_baris_personel_lain(): void
    {
        $stafA = $this->akun(PeranSistem::Staf);
        $stafB = $this->akun(PeranSistem::Staf);
        $personelA = $this->personel($stafA);
        $personelB = $this->personel($stafB);

        $milikSendiri = Report::factory()->create(['personel_id' => $personelA->id]);
        $milikOrangLain = Report::factory()->create(['personel_id' => $personelB->id]);

        $this->assertTrue(Gate::forUser($stafA)->allows('view', $milikSendiri));
        $this->assertTrue(Gate::forUser($stafA)->allows('update', $milikSendiri));

        $this->assertFalse(Gate::forUser($stafA)->allows('view', $milikOrangLain));
        $this->assertFalse(Gate::forUser($stafA)->allows('update', $milikOrangLain));
    }

    #[Test]
    public function de_dan_pengurus_membaca_lintas_personel_selama_memegang_izin(): void
    {
        $de = $this->akun(PeranSistem::DirekturEksekutif);
        $staf = $this->akun(PeranSistem::Staf);
        $laporanStaf = Report::factory()->create(['personel_id' => $this->personel($staf)->id]);

        $this->assertTrue(Gate::forUser($de)->allows('view', $laporanStaf));
        $this->assertTrue(Gate::forUser($de)->allows('verify', $laporanStaf));
    }

    // ------------------------------------------------------ 9. unduhan berkas

    #[Test]
    public function unduhan_dokumen_rahasia_keuangan_hanya_untuk_pengurus_dan_de(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('dokumen/rahasia.pdf', 'isi rahasia');

        $staf = $this->akun(PeranSistem::Staf);
        $de = $this->akun(PeranSistem::DirekturEksekutif);
        $pengurus = $this->akun(PeranSistem::Pengurus);
        $admin = $this->akun(PeranSistem::Admin);

        $pemilik = $this->personel($staf);

        $dokumen = Document::factory()->create([
            'pemilik_id' => $pemilik->id,
            'diunggah_oleh' => $pemilik->id,
            'file_path' => 'dokumen/rahasia.pdf',
            'file_name' => 'rahasia.pdf',
            'klasifikasi' => KlasifikasiDokumen::RahasiaKeuangan,
        ]);

        $this->actingAs($staf)->get(route('dokumen.unduh', $dokumen))->assertForbidden();
        $this->actingAs($admin)->get(route('dokumen.unduh', $dokumen))->assertForbidden();
        $this->actingAs($de)->get(route('dokumen.unduh', $dokumen))->assertOk();
        $this->actingAs($pengurus)->get(route('dokumen.unduh', $dokumen))->assertOk();
    }

    #[Test]
    public function pemilik_dokumen_internal_tetap_bisa_mengunduh_miliknya(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('dokumen/milik.pdf', 'isi');

        $staf = $this->akun(PeranSistem::Staf);
        $lain = $this->akun(PeranSistem::Staf);
        $pemilik = $this->personel($staf);
        $orangLain = $this->personel($lain);

        $dokumen = Document::factory()->create([
            'pemilik_id' => $pemilik->id,
            'diunggah_oleh' => $pemilik->id,
            'file_path' => 'dokumen/milik.pdf',
            'file_name' => 'milik.pdf',
            'klasifikasi' => KlasifikasiDokumen::InternalBe,
        ]);

        $this->actingAs($staf)->get(route('dokumen.unduh', $dokumen))->assertOk();
        $this->actingAs($lain)->get(route('dokumen.unduh', $dokumen))->assertForbidden();

        $this->assertDatabaseHas('audit_events', ['event' => 'dokumen.diunduh']);
    }

    // ------------------------------------------------------------- 10. ekspor

    #[Test]
    public function ekspor_modul_mengikuti_izin_ekspor_yang_dimiliki(): void
    {
        $staf = $this->akun(PeranSistem::Staf);
        $admin = $this->akun(PeranSistem::Admin);

        $this->actingAs($staf)
            ->get(route('modul.ekspor', 'report'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($staf)
            ->get(route('modul.ekspor', 'user'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('modul.ekspor', 'kpi'))
            ->assertForbidden();
    }

    #[Test]
    public function modul_tidak_dikenal_menghasilkan_404(): void
    {
        $staf = $this->akun(PeranSistem::Staf);

        $this->actingAs($staf)->get('/modul/modul-karangan')->assertNotFound();
    }

    // -------------------------------------------------------------- 11. audit

    #[Test]
    public function peristiwa_keamanan_tercatat_pada_audit_trail(): void
    {
        $user = $this->akun(PeranSistem::Staf);

        $this->post('/login', ['login' => $user->username, 'password' => 'password']);
        $this->post('/logout');
        $this->post('/login', ['login' => $user->username, 'password' => 'salah']);

        $this->assertDatabaseHas('audit_events', ['event' => 'auth.login']);
        $this->assertDatabaseHas('audit_events', ['event' => 'auth.logout']);
        $this->assertDatabaseHas('audit_events', [
            'event' => 'auth.login_gagal',
            'reason' => 'kata_sandi_salah',
        ]);

        $this->actingAs($user)->get('/admin')->assertForbidden();

        $this->assertDatabaseHas('audit_events', [
            'event' => 'auth.akses_ditolak',
            'reason' => 'peran_tidak_cocok',
        ]);

        // Audit trail tetap append-only.
        $event = AuditEvent::query()->firstOrFail();
        $this->expectException(\RuntimeException::class);
        $event->update(['event' => 'diubah']);
    }

    // ----------------------------------------------- 12. matriks izin seeder

    #[Test]
    public function matriks_izin_seeder_sesuai_prd(): void
    {
        $this->assertTrue(Role::findByName('pengurus')->hasPermissionTo('kpi.finalize'));
        $this->assertTrue(Role::findByName('pengurus')->hasPermissionTo('report.verify'));
        $this->assertFalse(Role::findByName('pengurus')->hasPermissionTo('review.update'));

        $this->assertTrue(Role::findByName('de')->hasPermissionTo('review.verify'));
        $this->assertTrue(Role::findByName('de')->hasPermissionTo('kpi.finalize'));
        $this->assertFalse(Role::findByName('de')->hasPermissionTo('kpi.configure'));
        $this->assertFalse(Role::findByName('de')->hasPermissionTo('user.view'));

        $this->assertTrue(Role::findByName('staf')->hasPermissionTo('report.submit'));
        $this->assertFalse(Role::findByName('staf')->hasPermissionTo('report.verify'));
        $this->assertFalse(Role::findByName('staf')->hasPermissionTo('audit.view'));

        $this->assertTrue(Role::findByName('admin')->hasPermissionTo('user.deactivate'));
        $this->assertTrue(Role::findByName('admin')->hasPermissionTo('kpi.configure'));
        $this->assertFalse(Role::findByName('admin')->hasPermissionTo('review.view'));
    }
}
