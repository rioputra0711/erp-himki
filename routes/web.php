<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\BerandaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EksporController;
use App\Http\Controllers\MasterBidangController;
use App\Http\Controllers\MasterJabatanController;
use App\Http\Controllers\MasterJobDescriptionController;
use App\Http\Controllers\MasterPersonelController;
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

    /*
    |----------------------------------------------------------------------
    | Layanan Audit Trail (Tahap 4)
    |----------------------------------------------------------------------
    |
    | Hanya baca. Rute ekspor statis didaftarkan sebelum rute berparameter agar
    | `/audit/ekspor` tidak tertangkap sebagai id peristiwa.
    |
    */
    Route::get('/audit/ekspor', [AuditController::class, 'ekspor'])
        ->middleware('izin:audit.export')
        ->name('audit.ekspor');

    Route::middleware('izin:audit.view')->group(function (): void {
        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
        Route::get('/audit/{auditEvent}', [AuditController::class, 'show'])
            ->whereNumber('auditEvent')
            ->name('audit.show');
    });

    /*
    |----------------------------------------------------------------------
    | Master Data Personel, Jabatan, Bidang & Job Description (Tahap 3)
    |----------------------------------------------------------------------
    |
    | Jabatan, bidang, dan Job Description bukan menu tersendiri; ketiganya
    | berada di bawah wewenang menu Master Personel (fitur.pdf Tahap 3).
    |
    */
    Route::prefix('master')->name('master.')->group(function (): void {
        // Rute statis (/create) harus didaftarkan sebelum rute berparameter
        // (/personel/{personel}) agar tidak tertangkap sebagai ID.
        Route::middleware('izin:personel.create')->group(function (): void {
            Route::get('personel/create', [MasterPersonelController::class, 'create'])->name('personel.create');
            Route::post('personel', [MasterPersonelController::class, 'store'])->name('personel.store');

            Route::post('jabatan', [MasterJabatanController::class, 'store'])->name('jabatan.store');
            Route::post('bidang', [MasterBidangController::class, 'store'])->name('bidang.store');

            Route::get('job-description/create', [MasterJobDescriptionController::class, 'create'])->name('jd.create');
            Route::post('job-description', [MasterJobDescriptionController::class, 'store'])->name('jd.store');
        });

        Route::middleware('izin:personel.view')->group(function (): void {
            Route::get('personel', [MasterPersonelController::class, 'index'])->name('personel.index');
            Route::get('personel/{personel}', [MasterPersonelController::class, 'show'])->name('personel.show');

            Route::get('jabatan', [MasterJabatanController::class, 'index'])->name('jabatan.index');
            Route::get('bidang', [MasterBidangController::class, 'index'])->name('bidang.index');

            Route::get('job-description', [MasterJobDescriptionController::class, 'index'])->name('jd.index');
            Route::get('job-description/{job_description}', [MasterJobDescriptionController::class, 'show'])->name('jd.show');
        });

        Route::middleware('izin:personel.update')->group(function (): void {
            Route::get('personel/{personel}/edit', [MasterPersonelController::class, 'edit'])->name('personel.edit');
            Route::put('personel/{personel}', [MasterPersonelController::class, 'update'])->name('personel.update');

            Route::put('jabatan/{jabatan}', [MasterJabatanController::class, 'update'])->name('jabatan.update');
            Route::put('bidang/{bidang}', [MasterBidangController::class, 'update'])->name('bidang.update');

            Route::get('job-description/{job_description}/edit', [MasterJobDescriptionController::class, 'edit'])->name('jd.edit');
            Route::put('job-description/{job_description}', [MasterJobDescriptionController::class, 'update'])->name('jd.update');
        });

        Route::middleware('izin:personel.deactivate')->group(function (): void {
            Route::patch('personel/{personel}/nonaktifkan', [MasterPersonelController::class, 'nonaktifkan'])->name('personel.nonaktifkan');
            Route::patch('jabatan/{jabatan}/nonaktifkan', [MasterJabatanController::class, 'nonaktifkan'])->name('jabatan.nonaktifkan');
            Route::patch('bidang/{bidang}/nonaktifkan', [MasterBidangController::class, 'nonaktifkan'])->name('bidang.nonaktifkan');
        });

        // Penetapan target & indikator = kewenangan Administrator saja.
        Route::middleware('izin:personel.configure')->group(function (): void {
            Route::patch('job-description/{job_description}/tetapkan', [MasterJobDescriptionController::class, 'tetapkan'])->name('jd.tetapkan');
            Route::patch('job-description/{job_description}/arsipkan', [MasterJobDescriptionController::class, 'arsipkan'])->name('jd.arsipkan');
            Route::post('job-description/{job_description}/versi-baru', [MasterJobDescriptionController::class, 'versiBaru'])->name('jd.versi');
        });
    });
});
