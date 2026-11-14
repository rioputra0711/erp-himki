@extends('layouts.app')

@section('judul', 'Jejak Audit — ERP HIMKI')

@section('konten')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Keamanan</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">Jejak Audit Sistem</h1>
            <p class="mt-1 text-sm text-slate-600">
                Catatan permanen siapa mengubah apa dan kapan. Baris di sini tidak dapat diubah atau dihapus,
                termasuk oleh Administrator Aplikasi.
            </p>
        </div>

        @if ($bolehEkspor)
            <a href="{{ route('audit.ekspor', $filter) }}"
               class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-white">
                Ekspor CSV
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

    <form method="GET" action="{{ route('audit.index') }}"
          class="mt-6 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
        <label class="text-sm sm:col-span-2">
            <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Kata kunci</span>
            <input type="search" name="q" maxlength="100" value="{{ $filter['q'] ?? '' }}"
                   placeholder="Alasan, nama pelaku, atau nama kelas objek"
                   class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
        </label>

        <label class="text-sm">
            <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Tindakan</span>
            <select name="tindakan" class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                <option value="">Semua tindakan</option>
                @foreach ($pilihanTindakan as $tindakan)
                    <option value="{{ $tindakan }}" @selected(($filter['tindakan'] ?? null) === $tindakan)>
                        {{ \App\Support\LabelAudit::tindakan($tindakan) }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">
            <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Objek</span>
            <select name="objek" class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                <option value="">Semua objek</option>
                @foreach ($pilihanObjek as $objek)
                    <option value="{{ $objek }}" @selected(($filter['objek'] ?? null) === $objek)>
                        {{ \App\Support\LabelAudit::labelObjek($objek) }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">
            <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Pelaku</span>
            <select name="aktor" class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                <option value="">Semua pelaku</option>
                @foreach ($aktor as $orang)
                    <option value="{{ $orang->id }}" @selected((int) ($filter['aktor'] ?? 0) === $orang->id)>
                        {{ $orang->name }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">
            <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Dari tanggal</span>
            <input type="date" name="dari" value="{{ $filter['dari'] ?? '' }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
        </label>

        <label class="text-sm">
            <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Sampai tanggal</span>
            <input type="date" name="sampai" value="{{ $filter['sampai'] ?? '' }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
        </label>

        <label class="text-sm">
            <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Urutkan</span>
            <select name="urut" class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                <option value="waktu" @selected(($filter['urut'] ?? 'waktu') === 'waktu')>Waktu</option>
                <option value="tindakan" @selected(($filter['urut'] ?? null) === 'tindakan')>Tindakan</option>
            </select>
        </label>

        <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-4">
            <button type="submit" class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">
                Terapkan filter
            </button>
            <a href="{{ route('audit.index') }}"
               class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                Bersihkan
            </a>
        </div>
    </form>

    <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2">Waktu (WIB)</th>
                    <th class="px-4 py-2">Pelaku</th>
                    <th class="px-4 py-2">Tindakan</th>
                    <th class="px-4 py-2">Objek</th>
                    <th class="px-4 py-2">Alasan</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($events as $event)
                    <tr>
                        <td class="px-4 py-2 whitespace-nowrap text-slate-600">
                            {{ $event->created_at?->timezone(\App\Support\ServerClock::timezone())->format('d/m/Y H:i:s') }}
                        </td>
                        <td class="px-4 py-2 text-slate-700">
                            {{ $event->actor_nama ?? 'Sistem' }}
                            @if ($event->actor_peran)
                                <span class="ml-1 rounded bg-slate-100 px-1 text-[10px] uppercase text-slate-600">
                                    {{ $event->actor_peran }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-slate-700">{{ \App\Support\LabelAudit::tindakan($event->event) }}</td>
                        <td class="px-4 py-2 text-slate-600">
                            {{ \App\Support\LabelAudit::objek($event->auditable_type) }}
                            @isset($labelObjek[$event->getKey()])
                                <span class="block text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($labelObjek[$event->getKey()], 50) }}</span>
                            @endisset
                        </td>
                        <td class="px-4 py-2 text-slate-600">
                            {{ $event->reason === null ? '—' : \Illuminate\Support\Str::limit($event->reason, 80) }}
                        </td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('audit.show', $event) }}"
                               class="rounded-md border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">
                                Detail
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-500">
                            Tidak ada peristiwa yang cocok dengan filter ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $events->links() }}
    </div>
@endsection
