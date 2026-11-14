<?php

namespace App\Http\Responses;

use App\Support\RutePeran;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pengarahan pasca-login ke ruang kerja sesuai peran (fitur.pdf Tahap 2).
 *
 * Setelah sesi diperbarui, seluruh sesi lain milik akun yang sama dicabut agar
 * satu akun tidak dipakai bersama di banyak perangkat (larangan akun bersama).
 */
class LoginResponse implements LoginResponseContract
{
    /**
     * @return Response
     */
    public function toResponse($request)
    {
        $pengguna = $request->user();
        $tujuan = $pengguna === null ? '/' : (RutePeran::untuk($pengguna) ?? '/');

        $this->cabutSesiLain($request, $pengguna);

        if ($request->wantsJson()) {
            return response()->json(['tujuan' => $tujuan], 200);
        }

        return redirect()->intended($tujuan);
    }

    private function cabutSesiLain($request, $pengguna): void
    {
        if ($pengguna === null || ! $request->hasSession()) {
            return;
        }

        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $pengguna->getAuthIdentifier())
            ->where('id', '!=', $request->session()->getId())
            ->delete();
    }
}
