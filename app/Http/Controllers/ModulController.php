<?php

namespace App\Http\Controllers;

use App\Enums\ModulAplikasi;
use App\Support\CakupanPengguna;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Halaman modul: menampilkan wewenang akun atas modul yang sedang dibuka.
 *
 * Izin `view` sudah diperiksa middleware `izin_modul`; di sini hanya disusun
 * daftar aksi yang boleh dilakukan agar antarmuka tidak menampilkan tombol
 * yang pasti ditolak server.
 *
 * Tiga modul sudah punya layar kerja sendiri sejak Tahap 5. Rujukan dialihkan
 * ke layar itu supaya menu lama tidak menampilkan halaman wewenang yang sudah
 * tidak dipakai. `Route::has()` dipakai karena layar arahan baru terdaftar pada
 * tugas berikutnya di tahap yang sama.
 */
class ModulController extends Controller
{
    public function show(Request $request, string $modul): View|RedirectResponse
    {
        $modulEnum = ModulAplikasi::from($modul);

        $tujuan = match ($modulEnum) {
            ModulAplikasi::Penugasan => 'tugas.index',
            ModulAplikasi::UmpanBalik, ModulAplikasi::TindakLanjut => 'arahan.index',
            default => null,
        };

        if ($tujuan !== null && Route::has($tujuan)) {
            return redirect()->route($tujuan);
        }

        $pengguna = $request->user();

        $aksi = [];

        foreach ($modulEnum->aksi() as $satu) {
            $aksi[] = [
                'aksi' => $satu,
                'boleh' => CakupanPengguna::boleh($pengguna, $modulEnum, $satu),
            ];
        }

        return view('modul.show', [
            'modul' => $modulEnum,
            'aksi' => $aksi,
        ]);
    }
}
