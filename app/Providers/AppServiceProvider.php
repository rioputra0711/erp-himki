<?php

namespace App\Providers;

use App\Enums\AksiIzin;
use App\Enums\ModulAplikasi;
use App\Listeners\CatatPerubahanAkses;
use App\Models\User;
use App\Support\CakupanPengguna;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Events\PermissionAttached;
use Spatie\Permission\Events\PermissionDetached;
use Spatie\Permission\Events\RoleAttached;
use Spatie\Permission\Events\RoleDetached;

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

        // Perubahan hak akses dicatat ke jejak audit (fitur.pdf Tahap 4).
        // Hanya terjadi bila ada sesi login; seeder/artisan tidak menghasilkan baris.
        Event::listen(
            [RoleAttached::class, RoleDetached::class, PermissionAttached::class, PermissionDetached::class],
            CatatPerubahanAkses::class,
        );

        // Navigasi hanya memuat menu yang benar-benar boleh dibuka akun ini.
        View::composer('layouts.app', function ($view): void {
            $pengguna = auth()->user();
            $navigasi = [];

            if ($pengguna instanceof User) {
                foreach (ModulAplikasi::cases() as $modul) {
                    // Audit punya layar penelusuran tersendiri; tautannya di bawah.
                    if ($modul === ModulAplikasi::Audit) {
                        continue;
                    }

                    if (CakupanPengguna::boleh($pengguna, $modul, AksiIzin::Lihat)) {
                        $navigasi[] = $modul;
                    }
                }
            }

            $view->with('navigasi', $navigasi);
        });
    }
}
