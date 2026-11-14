<?php

namespace App\Http\Controllers;

use App\Enums\AksiIzin;
use App\Enums\ModulAplikasi;
use App\Support\CakupanPengguna;
use App\Support\PencatatAudit;
use App\Support\ServerClock;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gerbang ekspor data per modul.
 *
 * Izin `export` diperiksa middleware sebelum controller dijalankan, sehingga
 * tautan ekspor yang disembunyikan di antarmuka tetap tidak bisa dipanggil
 * langsung. Isi rekapitulasi rinci menyusul pada tahap pelaporan; berkas yang
 * dihasilkan sekarang memuat keterangan sumber dan waktu server.
 */
class EksporController extends Controller
{
    public function unduh(Request $request, string $modul): StreamedResponse|RedirectResponse
    {
        $modulEnum = ModulAplikasi::from($modul);
        $pengguna = $request->user();

        // Audit punya permukaan ekspor sendiri yang berisi baris audit nyata;
        // rute generik hanya mengalihkan agar tidak ada dua sumber berbeda.
        if ($modulEnum === ModulAplikasi::Audit) {
            return redirect()->route('audit.ekspor', $request->query());
        }

        PencatatAudit::catat(
            event: 'ekspor.'.$modulEnum->value,
            request: $request,
            actor: $pengguna,
            after: [
                'izin' => $modulEnum->izin(AksiIzin::Ekspor),
                'rute' => $request->path(),
            ],
        );

        $namaBerkas = sprintf(
            'ekspor-%s-%s.csv',
            $modulEnum->value,
            ServerClock::now()->format('Ymd-His'),
        );

        $baris = [
            ['modul', 'nama_modul', 'peran', 'dibuat_pada', 'sumber_data'],
            [
                $modulEnum->value,
                $modulEnum->label(),
                CakupanPengguna::peran($pengguna)?->value ?? '-',
                ServerClock::now()->toIso8601String(),
                'erp_himki',
            ],
        ];

        return response()->streamDownload(function () use ($baris): void {
            $keluaran = fopen('php://output', 'wb');

            foreach ($baris as $satu) {
                fputcsv($keluaran, $satu);
            }

            fclose($keluaran);
        }, $namaBerkas, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
