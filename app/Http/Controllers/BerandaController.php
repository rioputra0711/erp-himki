<?php

namespace App\Http\Controllers;

use App\Support\RutePeran;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Titik masuk aplikasi: tanpa halaman promosi publik (PRD E.3).
 *
 * Tamu dikirim ke gerbang login; pengguna yang sudah masuk langsung diarahkan
 * ke ruang kerja sesuai perannya.
 */
class BerandaController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $pengguna = $request->user();

        if ($pengguna === null) {
            return redirect()->route('login');
        }

        $tujuan = RutePeran::untuk($pengguna);

        if ($tujuan === null) {
            return redirect()
                ->route('login')
                ->withErrors(['login' => 'Akun Anda belum memiliki peran. Hubungi Administrator Aplikasi.']);
        }

        return redirect($tujuan);
    }
}
