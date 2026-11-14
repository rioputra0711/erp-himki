<?php

namespace Tests\Feature;

use App\Enums\PeranSistem;
use App\Enums\StatusPenugasan;
use App\Models\Assignment;
use App\Models\Personel;
use App\Models\User;
use Database\Seeders\PeranIzinSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
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
}
