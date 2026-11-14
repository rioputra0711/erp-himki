<?php

namespace App\Http\Controllers;

use App\Enums\ModulAplikasi;
use App\Support\CakupanPengguna;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Halaman modul: menampilkan wewenang akun atas modul yang sedang dibuka.
 *
 * Izin `view` sudah diperiksa middleware `izin_modul`; di sini hanya disusun
 * daftar aksi yang boleh dilakukan agar antarmuka tidak menampilkan tombol
 * yang pasti ditolak server.
 */
class ModulController extends Controller
{
    public function show(Request $request, string $modul): View
    {
        $modulEnum = ModulAplikasi::from($modul);
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
