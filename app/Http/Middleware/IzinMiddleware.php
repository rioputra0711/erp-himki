<?php

namespace App\Http\Middleware;

use App\Support\CakupanPengguna;
use App\Support\PencatatAudit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang izin server-side untuk aksi Create/Read/Update/Verify-Score/Export
 * pada dua belas menu (fitur.pdf Tahap 2).
 *
 * Pemakaian: `->middleware('izin:report.verify')`. Bila beberapa izin diberikan,
 * seluruhnya wajib dimiliki. Tombol yang disembunyikan di UI bukan pengaman;
 * pemeriksaan di sini berlaku untuk setiap permintaan, termasuk unduhan berkas.
 */
final class IzinMiddleware
{
    public function handle(Request $request, Closure $next, string ...$izin): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        foreach ($izin as $nama) {
            if (CakupanPengguna::bolehNamaIzin($user, $nama)) {
                continue;
            }

            PencatatAudit::catat(
                event: PencatatAudit::AKSES_DITOLAK,
                request: $request,
                actor: $user,
                after: [
                    'izin_diminta' => $nama,
                    'rute' => $request->path(),
                ],
                reason: 'izin_kurang',
            );

            abort(403, 'Akun Anda tidak memiliki izin untuk aksi ini.');
        }

        return $next($request);
    }
}
