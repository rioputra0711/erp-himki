<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('judul', 'Masuk — ERP HIMKI')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full items-center justify-center bg-slate-900 px-4 py-12">
    <div class="w-full max-w-md">
        <div class="mb-6 text-center">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">DPP HIMKI</p>
            <h1 class="mt-1 text-xl font-semibold text-white">ERP HIMKI</h1>
            <p class="mt-1 text-sm text-slate-400">Modul HR, Manajemen Kerja, dan Evaluasi Kinerja</p>
        </div>

        <div class="rounded-2xl bg-white p-6 shadow-xl">
            @yield('konten')
        </div>

        <p class="mt-6 text-center text-xs text-slate-500">
            Akses internal. Akun dibuat oleh Administrator Aplikasi.
        </p>
    </div>
</body>
</html>
