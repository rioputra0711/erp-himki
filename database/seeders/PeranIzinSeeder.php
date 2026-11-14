<?php

namespace Database\Seeders;

use App\Enums\AksiIzin;
use App\Enums\ModulAplikasi;
use App\Enums\PeranSistem;
use App\Support\CakupanPengguna;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Matriks izin `modul.aksi` untuk 12 menu dan 4 peran (PRD E.2, fitur.pdf Tahap 2).
 *
 * Sumber tunggal daftar aksi adalah enum ModulAplikasi::aksi(), sehingga izin yang
 * di-seed selalu sama dengan izin yang diperiksa middleware dan Policy.
 */
class PeranIzinSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $semuaIzin = $this->daftarkanIzin();

        foreach (PeranSistem::cases() as $peran) {
            Role::findOrCreate($peran->value, 'web');
        }

        foreach ($this->petaPeran() as $namaPeran => $izin) {
            Role::findByName($namaPeran, 'web')->syncPermissions($izin);
        }

        // Jaring pengaman: peran admin tidak boleh memegang izin penilaian meskipun
        // daftar di atas keliru diubah.
        Role::findByName(PeranSistem::Admin->value, 'web')
            ->revokePermissionTo(CakupanPengguna::PENILAIAN_DILARANG_ADMIN);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info(sprintf(
            'PeranIzinSeeder: %d izin untuk %d peran.',
            count($semuaIzin),
            count(PeranSistem::cases()),
        ));
    }

    /**
     * @return list<string>
     */
    private function daftarkanIzin(): array
    {
        $semua = [];

        foreach (ModulAplikasi::cases() as $modul) {
            foreach ($modul->aksi() as $aksi) {
                $nama = $modul->izin($aksi);
                Permission::findOrCreate($nama, 'web');
                $semua[] = $nama;
            }
        }

        return $semua;
    }

    /**
     * @return array<string, list<string>>
     */
    private function petaPeran(): array
    {
        return [
            PeranSistem::Pengurus->value => $this->izinPengurus(),
            PeranSistem::DirekturEksekutif->value => $this->izinDirekturEksekutif(),
            PeranSistem::Staf->value => $this->izinStaf(),
            PeranSistem::Admin->value => $this->izinAdmin(),
        ];
    }

    /**
     * Pengurus: membaca lintas personel, memberi arahan, memutuskan KPI.
     * Tidak menilai kualitas laporan (itu kewenangan DE), dan tidak
     * memverifikasi/menutup penugasan — kewenangan itu milik DE (desain Tahap 5 §10).
     *
     * @return list<string>
     */
    private function izinPengurus(): array
    {
        return $this->gabung(
            $this->izin(ModulAplikasi::Penugasan, AksiIzin::Lihat, AksiIzin::Buat, AksiIzin::Ubah, AksiIzin::Ekspor),
            $this->izin(ModulAplikasi::LogAktivitas, AksiIzin::Lihat, AksiIzin::Ekspor),
            $this->izin(ModulAplikasi::Laporan, AksiIzin::Lihat, AksiIzin::Verifikasi, AksiIzin::Ekspor),
            $this->izin(ModulAplikasi::Dokumen, AksiIzin::Lihat, AksiIzin::Buat, AksiIzin::Ekspor),
            $this->izin(ModulAplikasi::Review, AksiIzin::Lihat, AksiIzin::Buat, AksiIzin::Verifikasi),
            $this->izin(ModulAplikasi::UmpanBalik, AksiIzin::Lihat, AksiIzin::Buat),
            $this->izin(ModulAplikasi::TindakLanjut, AksiIzin::Lihat),
            $this->izin(ModulAplikasi::Kpi, AksiIzin::Lihat, AksiIzin::Kunci, AksiIzin::Ekspor),
            $this->izin(ModulAplikasi::Rapat, AksiIzin::Lihat, AksiIzin::Buat, AksiIzin::Ekspor),
            $this->izin(ModulAplikasi::Personel, AksiIzin::Lihat, AksiIzin::Ekspor),
            $this->izin(ModulAplikasi::Audit, AksiIzin::Lihat),
        );
    }

    /**
     * DE: kendali operasional penuh termasuk penilaian, kecuali konfigurasi KPI
     * dan administrasi akun. Audit hanya baca dan hanya peristiwa operasional
     * (lihat AuditEvent::scopeTerlihatOleh).
     *
     * @return list<string>
     */
    private function izinDirekturEksekutif(): array
    {
        $izin = [];

        foreach (ModulAplikasi::cases() as $modul) {
            if (in_array($modul, [ModulAplikasi::Akun, ModulAplikasi::Audit], true)) {
                continue;
            }

            foreach ($modul->aksi() as $aksi) {
                if ($modul === ModulAplikasi::Kpi && $aksi === AksiIzin::Konfigurasi) {
                    continue;
                }

                // Penetapan/freeze Job Description adalah wewenang Administrator
                // (PRD E.2 baris 9); DE hanya mengusulkan draf.
                if ($modul === ModulAplikasi::Personel && $aksi === AksiIzin::Konfigurasi) {
                    continue;
                }

                $izin[] = $modul->izin($aksi);
            }
        }

        // Audit: baca saja. Ekspor tetap wewenang Administrator.
        $izin[] = ModulAplikasi::Audit->izin(AksiIzin::Lihat);

        return $izin;
    }

    /**
     * Staf: eksekusi dan pelaporan milik sendiri.
     *
     * @return list<string>
     */
    private function izinStaf(): array
    {
        return $this->gabung(
            $this->izin(ModulAplikasi::Personel, AksiIzin::Lihat),
            $this->izin(ModulAplikasi::Penugasan, AksiIzin::Lihat, AksiIzin::Ubah),
            $this->izin(ModulAplikasi::LogAktivitas, AksiIzin::Lihat, AksiIzin::Buat, AksiIzin::Ubah, AksiIzin::Ekspor),
            $this->izin(ModulAplikasi::Laporan, AksiIzin::Lihat, AksiIzin::Buat, AksiIzin::Ubah, AksiIzin::Ajukan, AksiIzin::Ekspor),
            $this->izin(ModulAplikasi::Dokumen, AksiIzin::Lihat, AksiIzin::Buat, AksiIzin::Ubah, AksiIzin::Ekspor),
            $this->izin(ModulAplikasi::Review, AksiIzin::Lihat),
            $this->izin(ModulAplikasi::UmpanBalik, AksiIzin::Lihat),
            $this->izin(ModulAplikasi::TindakLanjut, AksiIzin::Lihat, AksiIzin::Ubah),
            $this->izin(ModulAplikasi::Kpi, AksiIzin::Lihat),
            $this->izin(ModulAplikasi::Rapat, AksiIzin::Lihat),
        );
    }

    /**
     * Administrator: akun, master personel, konfigurasi teknis, audit.
     * Tanpa satu pun izin penilaian.
     *
     * @return list<string>
     */
    private function izinAdmin(): array
    {
        return $this->gabung(
            $this->izin(ModulAplikasi::Akun, AksiIzin::Lihat, AksiIzin::Buat, AksiIzin::Ubah, AksiIzin::Nonaktifkan),
            $this->izin(ModulAplikasi::Personel, AksiIzin::Lihat, AksiIzin::Buat, AksiIzin::Ubah, AksiIzin::Nonaktifkan, AksiIzin::Konfigurasi),
            $this->izin(ModulAplikasi::Dokumen, AksiIzin::Lihat, AksiIzin::Ubah),
            $this->izin(ModulAplikasi::Kpi, AksiIzin::Lihat, AksiIzin::Konfigurasi),
            $this->izin(ModulAplikasi::Audit, AksiIzin::Lihat, AksiIzin::Ekspor),
        );
    }

    /**
     * @return list<string>
     */
    private function izin(ModulAplikasi $modul, AksiIzin ...$aksi): array
    {
        return array_map(
            static fn (AksiIzin $a): string => $modul->izin($a),
            $aksi,
        );
    }

    /**
     * @param  list<string>  ...$kelompok
     * @return list<string>
     */
    private function gabung(array ...$kelompok): array
    {
        return array_values(array_unique(array_merge(...$kelompok)));
    }
}
