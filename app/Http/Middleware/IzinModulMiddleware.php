<?php

namespace App\Http\Middleware;

use App\Enums\AksiIzin;
use App\Enums\ModulAplikasi;
use App\Support\CakupanPengguna;
use App\Support\PencatatAudit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang izin untuk rute berparameter modul, misalnya `/modul/{modul}` dan
 * `/ekspor/{modul}`.
 *
 * Aksi ditentukan oleh argumen middleware (`izin_modul:export`), modul diambil
 * dari parameter rute. Nama modul yang tidak dikenal menghasilkan 404 agar
 * daftar menu tidak bisa ditebak dari percobaan URL.
 */
final class IzinModulMiddleware
{
    public function handle(Request $request, Closure $next, string $aksi = 'view'): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $modul = ModulAplikasi::tryFrom((string) $request->route('modul'));

        if ($modul === null) {
            abort(404);
        }

        $aksiIzin = AksiIzin::tryFrom($aksi) ?? AksiIzin::Lihat;

        if (CakupanPengguna::boleh($user, $modul, $aksiIzin)) {
            return $next($request);
        }

        PencatatAudit::catat(
            event: PencatatAudit::AKSES_DITOLAK,
            request: $request,
            actor: $user,
            after: [
                'izin_diminta' => $modul->izin($aksiIzin),
                'rute' => $request->path(),
            ],
            reason: 'izin_kurang',
        );

        abort(403, 'Akun Anda tidak memiliki izin untuk aksi ini.');
    }
}
