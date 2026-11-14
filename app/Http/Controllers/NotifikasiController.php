<?php

namespace App\Http\Controllers;

use App\Support\ServerClock;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Pusat notifikasi dalam aplikasi (fitur.pdf Tahap 5).
 *
 * Notifikasi selalu dibaca lewat relasi milik akun yang sedang login, sehingga
 * id notifikasi milik akun lain berakhir sebagai 404 — bukan pesan "ditolak"
 * yang membocorkan keberadaan baris tersebut.
 */
class NotifikasiController extends Controller
{
    private const PER_HALAMAN = 50;

    public function index(Request $request): View
    {
        $pengguna = $request->user();

        return view('notifikasi.index', [
            'daftar' => $pengguna->notifications()->paginate(self::PER_HALAMAN),
            'jumlahBelumDibaca' => $pengguna->unreadNotifications()->count(),
        ]);
    }

    public function baca(Request $request, string $notifikasi): RedirectResponse
    {
        $baris = $request->user()->notifications()->find($notifikasi);

        abort_if($baris === null, 404);

        $baris->markAsRead();

        return redirect()
            ->route('notifikasi.index')
            ->with('status', 'Notifikasi ditandai sudah dibaca.');
    }

    public function bacaSemua(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => ServerClock::now()]);

        return redirect()
            ->route('notifikasi.index')
            ->with('status', 'Semua notifikasi ditandai sudah dibaca.');
    }
}
