<?php

namespace App\Http\Middleware;

use App\Support\PencatatAudit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi ruang kerja berdasarkan peran (fitur.pdf Tahap 2 — Auto-Routing Peran).
 *
 * Pemakaian: `->middleware('peran:pengurus,de')` membolehkan salah satu peran.
 * Penolakan selalu 403 dan tercatat pada audit trail.
 */
final class PeranMiddleware
{
    public function handle(Request $request, Closure $next, string ...$peran): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $dimiliki = $user->getRoleNames()->all();

        if (array_intersect($peran, $dimiliki) !== []) {
            return $next($request);
        }

        PencatatAudit::catat(
            event: PencatatAudit::AKSES_DITOLAK,
            request: $request,
            actor: $user,
            after: [
                'peran_diminta' => array_values($peran),
                'peran_dimiliki' => $dimiliki,
                'rute' => $request->path(),
            ],
            reason: 'peran_tidak_cocok',
        );

        abort(403, 'Peran akun Anda tidak berhak membuka ruang kerja ini.');
    }
}
