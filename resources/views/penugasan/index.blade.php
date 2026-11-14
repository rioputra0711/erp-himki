@extends('layouts.app')

@section('judul', 'Penugasan — ERP HIMKI')

@section('konten')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Manajemen Penugasan</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">Daftar Penugasan</h1>
            <p class="mt-1 text-sm text-slate-600">
                Instruksi kerja beserta sumber perintah, PIC, tenggat, dan progresnya.
            </p>
        </div>

        @if ($bolehBuat)
            <a href="{{ route('tugas.create') }}"
               class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">
                Tugas Baru
            </a>
        @endif
    </div>

    @if ($errors->any())
        <div class="mt-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $pesan)
                    <li>{{ $pesan }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="GET" action="{{ route('tugas.index') }}"
          class="mt-6 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
        <label class="text-sm">
            <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Cari judul / ID</span>
            <input type="search" name="q" value="{{ $filter['q'] ?? '' }}" maxlength="100"
                   class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
        </label>

        <label class="text-sm">
            <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Status</span>
            <select name="status" class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                <option value="">Semua status</option>
                @foreach ($status as $satu)
                    <option value="{{ $satu->value }}" @selected(($filter['status'] ?? '') === $satu->value)>{{ $satu->label() }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">
            <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Kategori</span>
            <select name="kategori" class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                <option value="">Semua kategori</option>
                @foreach ($kategori as $satu)
                    <option value="{{ $satu->value }}" @selected(($filter['kategori'] ?? '') === $satu->value)>{{ $satu->label() }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">
            <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Prioritas</span>
            <select name="prioritas" class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                <option value="">Semua prioritas</option>
                @foreach ($prioritas as $satu)
                    <option value="{{ $satu->value }}" @selected(($filter['prioritas'] ?? '') === $satu->value)>{{ $satu->label() }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">
            <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Sumber perintah</span>
            <select name="sumber" class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                <option value="">Semua sumber</option>
                @foreach ($sumber as $satu)
                    <option value="{{ $satu->value }}" @selected(($filter['sumber'] ?? '') === $satu->value)>{{ $satu->label() }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">
            <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">PIC</span>
            <select name="pic" class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                <option value="">Semua PIC</option>
                @foreach ($pic as $satu)
                    <option value="{{ $satu->id }}" @selected((int) ($filter['pic'] ?? 0) === (int) $satu->id)>{{ $satu->nama_lengkap }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">
            <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Urut</span>
            <select name="urut" class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                <option value="tenggat" @selected(($filter['urut'] ?? 'tenggat') === 'tenggat')>Tenggat</option>
                <option value="dibuat" @selected(($filter['urut'] ?? '') === 'dibuat')>Terbaru dibuat</option>
                <option value="prioritas" @selected(($filter['urut'] ?? '') === 'prioritas')>Prioritas</option>
            </select>
        </label>

        <label class="text-sm">
            <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Arah</span>
            <select name="arah" class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                <option value="asc" @selected(($filter['arah'] ?? 'asc') === 'asc')>Naik</option>
                <option value="desc" @selected(($filter['arah'] ?? '') === 'desc')>Turun</option>
            </select>
        </label>

        <label class="flex items-end gap-2 text-sm">
            <input type="checkbox" name="terlambat" value="1" @checked(! empty($filter['terlambat']))
                   class="rounded border-slate-300">
            <span class="pb-1.5 text-slate-700">Hanya terlambat</span>
        </label>

        <div class="flex items-end gap-2 pb-0.5">
            <button type="submit" class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">
                Terapkan
            </button>
            <a href="{{ route('tugas.index') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                Reset
            </a>
        </div>
    </form>

    <div class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2 font-medium">ID</th>
                    <th class="px-4 py-2 font-medium">Judul</th>
                    <th class="px-4 py-2 font-medium">PIC</th>
                    <th class="px-4 py-2 font-medium">Sumber</th>
                    <th class="px-4 py-2 font-medium">Kategori</th>
                    <th class="px-4 py-2 font-medium">Prioritas</th>
                    <th class="px-4 py-2 font-medium">Tenggat</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="px-4 py-2 font-medium">Progres</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($daftar as $satu)
                    <tr class="align-top hover:bg-slate-50">
                        <td class="px-4 py-2 font-mono text-xs text-slate-500">
                            <a href="{{ route('tugas.show', $satu) }}" class="hover:text-slate-900">{{ $satu->kode ?? '—' }}</a>
                        </td>
                        <td class="px-4 py-2">
                            <a href="{{ route('tugas.show', $satu) }}" class="font-medium text-slate-900 hover:underline">
                                {{ \Illuminate\Support\Str::limit($satu->judul, 60) }}
                            </a>
                            @if ($satu->terlambat())
                                <span class="ml-1 rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700">Terlambat</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-slate-600">{{ $satu->pic?->nama_lengkap ?? '—' }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $satu->sumber_perintah?->label() }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $satu->kategori?->label() }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $satu->prioritas?->label() }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $satu->tenggat?->translatedFormat('d M Y H:i') ?? '—' }}</td>
                        <td class="px-4 py-2">
                            <x-lencana-status :status="$satu->status" />
                        </td>
                        <td class="px-4 py-2 text-slate-600 tabular-nums">{{ (int) $satu->progres }}%</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-6 text-center text-slate-500">
                            Belum ada penugasan yang cocok dengan filter Anda.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $daftar->links() }}</div>
@endsection
