<?php

namespace App\Providers;

use App\Enums\AksiIzin;
use App\Enums\ModulAplikasi;
use App\Models\User;
use App\Support\CakupanPengguna;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Kebijakan kata sandi minimum untuk seluruh jalur (reset, ganti sandi, buat akun).
        Password::defaults(fn (): Password => Password::min(12)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols());

        // Pertahanan berlapis: Administrator Aplikasi tidak pernah boleh menilai
        // pekerjaan personel, walaupun baris izin di basis data keliru memberikannya.
        // Pemeriksaan utama tetap di CakupanPengguna (dipakai middleware + Policy).
        Gate::before(function (User $user, string $ability) {
            if (CakupanPengguna::adalahAdmin($user)
                && in_array($ability, CakupanPengguna::PENILAIAN_DILARANG_ADMIN, true)) {
                return false;
            }

            return null;
        });

        // Navigasi hanya memuat menu yang benar-benar boleh dibuka akun ini.
        View::composer('layouts.app', function ($view): void {
            $pengguna = auth()->user();
            $navigasi = [];

            if ($pengguna instanceof User) {
                foreach (ModulAplikasi::cases() as $modul) {
                    if (CakupanPengguna::boleh($pengguna, $modul, AksiIzin::Lihat)) {
                        $navigasi[] = $modul;
                    }
                }
            }

            $view->with('navigasi', $navigasi);
        });
    }
}
