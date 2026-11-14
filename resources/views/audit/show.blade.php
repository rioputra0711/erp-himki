@extends('layouts.app')

@section('judul', 'Detail Jejak Audit — ERP HIMKI')

@section('konten')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Jejak Audit</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">
                {{ \App\Support\LabelAudit::tindakan($event->event) }}
                <span class="text-slate-400">·</span>
                {{ \App\Support\LabelAudit::objek($event->auditable_type) }}
            </h1>
            <p class="mt-1 text-sm text-slate-600">
                Peristiwa #{{ $event->getKey() }} — catatan permanen, tidak dapat diubah atau dihapus.
            </p>
        </div>

        <a href="{{ route('audit.index') }}"
           class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-white">
            Kembali ke daftar
        </a>
    </div>

    <dl class="mt-6 grid gap-4 rounded-xl border border-slate-200 bg-white p-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <dt class="text-xs uppercase tracking-wide text-slate-500">Waktu (WIB)</dt>
            <dd class="mt-1 text-slate-800">
                {{ $event->created_at?->timezone(\App\Support\ServerClock::timezone())->translatedFormat('d F Y H:i:s') }}
            </dd>
        </div>

        <div>
            <dt class="text-xs uppercase tracking-wide text-slate-500">Pelaku</dt>
            <dd class="mt-1 text-slate-800">
                {{ $event->actor_nama ?? 'Sistem' }}
                @if ($event->actor_peran)
                    <span class="ml-1 rounded bg-slate-100 px-1 text-[10px] uppercase text-slate-600">{{ $event->actor_peran }}</span>
                @endif
                @if ($event->actor_id !== null)
                    <span class="block text-xs text-slate-500">Akun #{{ $event->actor_id }}</span>
                @endif
            </dd>
        </div>

        <div>
            <dt class="text-xs uppercase tracking-wide text-slate-500">Objek</dt>
            <dd class="mt-1 text-slate-800">
                {{ \App\Support\LabelAudit::objek($event->auditable_type) }}
                @if ($labelObjek !== null)
                    <span class="block text-xs text-slate-500">{{ $labelObjek }}</span>
                @endif
                @if ($event->auditable_id !== null)
                    <span class="block text-xs text-slate-500">#{{ $event->auditable_id }}</span>
                @endif
                @if ($tautanObjek !== null)
                    <a href="{{ $tautanObjek }}" class="text-xs text-slate-700 underline">Buka objek</a>
                @endif
            </dd>
        </div>

        <div>
            <dt class="text-xs uppercase tracking-wide text-slate-500">Jaringan</dt>
            <dd class="mt-1 text-slate-800">
                {{ $event->ip_address ?? '—' }}
                @if ($event->user_agent)
                    <span class="block truncate text-xs text-slate-500" title="{{ $event->user_agent }}">{{ $event->user_agent }}</span>
                @endif
            </dd>
        </div>

        <div class="sm:col-span-2 lg:col-span-4">
            <dt class="text-xs uppercase tracking-wide text-slate-500">Alasan</dt>
            <dd class="mt-1 text-slate-800">{{ $event->reason ?? '—' }}</dd>
        </div>
    </dl>

    @if ($disamarkan)
        <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-xs text-amber-800">
            Kolom sensitif (ID personel, telepon, kata sandi, klarifikasi) disamarkan untuk peran Anda.
        </p>
    @endif

    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2 w-1/4">Kolom</th>
                    <th class="px-4 py-2">Sebelum</th>
                    <th class="px-4 py-2">Sesudah</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($perubahan as $baris)
                    <tr class="align-top">
                        <td class="px-4 py-2 font-medium text-slate-900">{{ $baris['kolom'] }}</td>
                        <td class="px-4 py-2 text-slate-600">
                            <pre class="whitespace-pre-wrap break-words font-sans">{{ $baris['sebelum'] }}</pre>
                        </td>
                        <td class="px-4 py-2 text-slate-600">
                            <pre class="whitespace-pre-wrap break-words font-sans">{{ $baris['sesudah'] }}</pre>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-6 text-center text-sm text-slate-500">
                            Tidak ada nilai yang dapat ditampilkan untuk peristiwa ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
