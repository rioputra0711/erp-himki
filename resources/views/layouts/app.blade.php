<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('judul', 'ERP HIMKI')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-100 text-slate-900 antialiased">
<div class="min-h-full">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-4 px-4 py-3">
            <a href="{{ route('beranda') }}" class="text-sm font-semibold tracking-tight text-slate-900">
                ERP HIMKI
            </a>

            <nav class="flex flex-wrap items-center gap-1 text-sm">
                @foreach ($navigasi as $item)
                    <a href="{{ route('modul.show', $item->value) }}"
                       class="rounded-md px-2 py-1 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                        {{ $item->label() }}
                    </a>
                @endforeach

                @can('personel.view')
                    <a href="{{ route('master.personel.index') }}"
                       class="rounded-md px-2 py-1 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                        Master Data
                    </a>
                @endcan

                @can('audit.view')
                    <a href="{{ route('audit.index') }}"
                       class="rounded-md px-2 py-1 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                        Audit Trail
                    </a>
                @endcan
            </nav>

            <div class="ml-auto flex items-center gap-3 text-sm">
                <span class="text-slate-500">{{ auth()->user()?->name }}</span>
                <span class="rounded-full bg-slate-900 px-2 py-0.5 text-xs font-medium text-white">
                    {{ auth()->user()?->peranSistem()?->label() }}
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-md border border-slate-300 px-2 py-1 text-slate-700 hover:bg-slate-50">
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8">
        @if (session('status'))
            <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        @yield('konten')
    </main>

    <footer class="mx-auto max-w-7xl px-4 pb-8 text-xs text-slate-500">
        Waktu server: {{ \App\Support\ServerClock::now()->translatedFormat('d F Y H:i') }} WIB
    </footer>
</div>
</body>
</html>
