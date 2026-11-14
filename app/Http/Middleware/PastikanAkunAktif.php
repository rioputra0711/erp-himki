<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memutus sesi milik akun yang dinonaktifkan.
 *
 * Pemeriksaan dilakukan pada setiap permintaan, bukan hanya saat login, sehingga
 * admin yang menonaktifkan akun langsung menghentikan akses yang sedang berjalan
 * (fitur.pdf Tahap 2 — manajemen sesi aman).
 */
final class PastikanAkunAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->is_active) {
            return $next($request);
        }

        $request->attributes->set('alasan_logout', 'akun_nonaktif');

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()
            ->route('login')
            ->withErrors(['login' => 'Akun Anda telah dinonaktifkan. Hubungi Administrator Aplikasi.']);
    }
}
