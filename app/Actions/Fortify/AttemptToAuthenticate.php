<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Support\PencatatAudit;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;

/**
 * Verifikasi kredensial dengan satu kolom `login` yang menerima email **atau**
 * username (fitur.pdf Tahap 2 — Gerbang Login akun individual).
 *
 * Urutan pemeriksaan disengaja:
 *
 * 1. Identitas dicari dulu; bila tidak ada, kata sandi tetap diproses dengan
 *    biaya hash yang setara agar keberadaan akun tidak bisa ditebak dari durasi
 *    respons (timing enumeration).
 * 2. Kata sandi diverifikasi sebelum status akun diperiksa, sehingga pesan
 *    "akun nonaktif" hanya muncul bagi pemilik kata sandi yang benar.
 * 3. Akun tanpa peran ditolak; tidak ada ruang kerja yang bisa dituju.
 */
class AttemptToAuthenticate extends \Laravel\Fortify\Actions\AttemptToAuthenticate
{
    public function __construct(StatefulGuard $guard, LoginRateLimiter $limiter)
    {
        parent::__construct($guard, $limiter);
    }

    /**
     * @return mixed
     *
     * @throws ValidationException
     */
    public function handle($request, $next)
    {
        $identitas = trim((string) $request->input(Fortify::username()));
        $kataSandi = (string) $request->input('password');

        $pengguna = $this->cariPengguna($identitas);

        if ($pengguna === null) {
            // Biaya komputasi disamakan dengan jalur "kata sandi salah" memakai
            // jumlah putaran hash yang sedang dikonfigurasi aplikasi.
            Hash::make(Str::random(16));

            return $this->gagal($request, $identitas, 'identitas_tidak_ditemukan');
        }

        if (! Hash::check($kataSandi, $pengguna->getAuthPassword())) {
            return $this->gagal($request, $identitas, 'kata_sandi_salah', $pengguna);
        }

        if (! $pengguna->is_active) {
            return $this->gagal(
                $request,
                $identitas,
                'akun_nonaktif',
                $pengguna,
                'Akun Anda dinonaktifkan. Hubungi Administrator Aplikasi.',
            );
        }

        $peran = $pengguna->peranSistem();

        if ($peran === null) {
            return $this->gagal(
                $request,
                $identitas,
                'tanpa_peran',
                $pengguna,
                'Akun Anda belum memiliki peran. Hubungi Administrator Aplikasi.',
            );
        }

        $this->guard->login($pengguna, $request->boolean('remember'));
        $this->limiter->clear($request);
        $pengguna->catatLogin();

        PencatatAudit::catat(
            event: PencatatAudit::LOGIN,
            request: $request,
            actor: $pengguna,
            after: [
                'identitas' => $identitas,
                'peran' => $peran->value,
            ],
        );

        return $next($request);
    }

    private function cariPengguna(string $identitas): ?User
    {
        if ($identitas === '') {
            return null;
        }

        $dibersihkan = mb_strtolower($identitas);

        return User::query()
            ->whereRaw('LOWER(email) = ?', [$dibersihkan])
            ->orWhereRaw('LOWER(username) = ?', [$dibersihkan])
            ->first();
    }

    /**
     * @throws ValidationException
     */
    private function gagal(
        $request,
        string $identitas,
        string $alasan,
        ?User $pengguna = null,
        ?string $pesan = null,
    ): never {
        $this->limiter->increment($request);

        PencatatAudit::catat(
            event: PencatatAudit::LOGIN_GAGAL,
            request: $request,
            actor: $pengguna,
            after: ['identitas' => $identitas],
            reason: $alasan,
        );

        throw ValidationException::withMessages([
            Fortify::username() => [$pesan ?? trans('auth.failed')],
        ]);
    }
}
