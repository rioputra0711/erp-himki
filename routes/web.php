<?php

use App\Http\Controllers\BerandaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EksporController;
use App\Http\Controllers\ModulController;
use App\Http\Controllers\UnduhDokumenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rute ERP HIMKI
|--------------------------------------------------------------------------
|
| Halaman terdepan adalah gerbang login; tidak ada halaman promosi publik
| (PRD E.3). Rute login, logout, dan pemulihan sandi disediakan Fortify.
| Seluruh rute kerja dijaga tiga lapis: `auth`, `aktif`, lalu `peran`/`izin`.
|
*/

Route::get('/', BerandaController::class)->name('beranda');

Route::middleware(['auth', 'aktif'])->group(function (): void {
    Route::get('/pengurus', [DashboardController::class, 'pengurus'])
        ->middleware('peran:pengurus')
        ->name('pengurus.dashboard');

    Route::get('/de', [DashboardController::class, 'direkturEksekutif'])
        ->middleware('peran:de')
        ->name('de.dashboard');

    Route::get('/staf', [DashboardController::class, 'staf'])
        ->middleware('peran:staf')
        ->name('staf.dashboard');

    Route::get('/admin', [DashboardController::class, 'admin'])
        ->middleware('peran:admin')
        ->name('admin.dashboard');

    Route::get('/modul/{modul}', [ModulController::class, 'show'])
        ->middleware('izin_modul:view')
        ->name('modul.show');

    Route::get('/ekspor/{modul}', [EksporController::class, 'unduh'])
        ->middleware('izin_modul:export')
        ->name('modul.ekspor');

    Route::get('/dokumen/{dokumen}/unduh', UnduhDokumenController::class)
        ->middleware('izin:document.view')
        ->name('dokumen.unduh');
});
