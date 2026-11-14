<?php

namespace App\Http\Controllers;

use App\Enums\ModulAplikasi;
use App\Enums\PeranSistem;
use App\Models\AuditEvent;
use App\Models\User;
use App\Support\CakupanPengguna;
use App\Support\ServerClock;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Empat ruang kerja sesuai peran (fitur.pdf Tahap 2 — Auto-Routing Peran).
 *
 * Isi dashboard masih berupa ringkasan status dan peta wewenang akun; modul
 * operasional dibangun pada tahap berikutnya.
 */
class DashboardController extends Controller
{
    public function pengurus(Request $request): View
    {
        return $this->tampilkan($request, PeranSistem::Pengurus);
    }

    public function direkturEksekutif(Request $request): View
    {
        return $this->tampilkan($request, PeranSistem::DirekturEksekutif);
    }

    public function staf(Request $request): View
    {
        return $this->tampilkan($request, PeranSistem::Staf);
    }

    public function admin(Request $request): View
    {
        return $this->tampilkan($request, PeranSistem::Admin);
    }

    private function tampilkan(Request $request, PeranSistem $peran): View
    {
        $pengguna = $request->user();

        return view('dashboard.ruang-kerja', [
            'peran' => $peran,
            'pengguna' => $pengguna,
            'personel' => $pengguna?->personel,
            'waktuServer' => ServerClock::now(),
            'modul' => $this->ringkasanModul($pengguna),
            'aktivitasTerakhir' => $this->aktivitasTerakhir($pengguna),
        ]);
    }

    /**
     * Peta wewenang akun atas 12 menu, dipakai untuk menyusun navigasi.
     *
     * @return list<array{modul: ModulAplikasi, aksi: list<string>, label: string}>
     */
    private function ringkasanModul(?User $pengguna): array
    {
        if ($pengguna === null) {
            return [];
        }

        $hasil = [];

        foreach (ModulAplikasi::cases() as $modul) {
            $aksi = [];

            foreach ($modul->aksi() as $satu) {
                if (CakupanPengguna::boleh($pengguna, $modul, $satu)) {
                    $aksi[] = $satu->label();
                }
            }

            if ($aksi === []) {
                continue;
            }

            $hasil[] = [
                'modul' => $modul,
                'aksi' => $aksi,
                'label' => $modul->label(),
            ];
        }

        return $hasil;
    }

    /**
     * @return list<AuditEvent>
     */
    private function aktivitasTerakhir(?User $pengguna): array
    {
        if ($pengguna === null) {
            return [];
        }

        return AuditEvent::query()
            ->where('actor_id', $pengguna->getKey())
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->all();
    }
}
