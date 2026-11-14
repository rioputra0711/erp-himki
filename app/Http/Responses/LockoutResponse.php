<?php

namespace App\Http\Responses;

use App\Support\PencatatAudit;
use Laravel\Fortify\Contracts\LockoutResponse as LockoutResponseContract;
use Laravel\Fortify\LoginRateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Respons pembatasan percobaan login.
 *
 * Bawaan Fortify melempar ValidationException yang oleh Laravel diubah menjadi
 * redirect 302. PRD meminta penolakan yang tegas, jadi respons di sini memakai
 * status HTTP 429 beserta sisa waktu tunggu.
 */
class LockoutResponse implements LockoutResponseContract
{
    public function __construct(private readonly LoginRateLimiter $limiter) {}

    /**
     * @return Response
     */
    public function toResponse($request)
    {
        $detik = $this->limiter->availableIn($request);
        $pesan = trans('auth.throttle', [
            'seconds' => $detik,
            'minutes' => (int) ceil($detik / 60),
        ]);

        PencatatAudit::catat(
            event: PencatatAudit::LOGIN_DIBLOKIR,
            request: $request,
            reason: 'batas_percobaan',
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $pesan,
                'errors' => ['login' => [$pesan]],
            ], 429);
        }

        return response()->view('auth.lockout', [
            'pesan' => $pesan,
            'detik' => $detik,
        ], 429);
    }
}
