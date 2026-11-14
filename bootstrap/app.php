<?php

use App\Http\Middleware\IzinMiddleware;
use App\Http\Middleware\IzinModulMiddleware;
use App\Http\Middleware\LindungiRespons;
use App\Http\Middleware\PastikanAkunAktif;
use App\Http\Middleware\PeranMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            AuthenticateSession::class,
            LindungiRespons::class,
        ]);

        $middleware->alias([
            'aktif' => PastikanAkunAktif::class,
            'peran' => PeranMiddleware::class,
            'izin' => IzinMiddleware::class,
            'izin_modul' => IzinModulMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
