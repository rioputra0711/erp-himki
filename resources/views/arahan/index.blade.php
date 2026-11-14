@extends('layouts.app')

@section('judul', 'Arahan & Tindak Lanjut — ERP HIMKI')

@section('konten')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Arahan &amp; Umpan Balik</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">Arahan &amp; Tindak Lanjut</h1>
            <p class="mt-1 text-sm text-slate-600">
                Instruksi pimpinan beserta action item-nya. Komentar biasa bukan persetujuan formal.
            </p>
        </div>

        @if ($bolehBuat)
            <a href="{{ route('arahan.create') }}"
               class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">
                Arahan Baru
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

    <form method="GET" action="{{ route('arahan.index') }}"
          class="mt-6 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
        <label class="text-sm">
            <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Cari isi</span>
            <input type="search" name="q" value="{{ $filter['q'] ?? '' }}" maxlength="100"
                   class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
        </label>

        <label class="text-sm">
            <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Jenis</span>
            <select name="jenis" class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                <option value="">Semua jenis</option>
                @foreach ($jenis as $satu)
                    <option value="{{ $satu->value }}" @selected(($filter['jenis'] ?? '') === $satu->value)>{{ $satu->label() }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-sm">
            <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Asal</span>
            <select name="asal" class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                <option value="">Semua asal</option>
                @foreach ($asal as $satu)
                    <option value="{{ $satu->value }}" @selected(($filter['asal'] ?? '') === $satu->value)>{{ $satu->label() }}</option>
                @endforeach
            </select>
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
            <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Tindak lanjut</span>
            <select name="tindakan" class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                <option value="">Semua</option>
                <option value="butuh" @selected(($filter['tindakan'] ?? '') === 'butuh')>Butuh tindakan</option>
                <option value="selesai" @selected(($filter['tindakan'] ?? '') === 'selesai')>Selesai</option>
            </select>
        </label>

        <label class="text-sm">
            <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Urut</span>
            <select name="urut" class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                <option value="dibuat" @selected(($filter['urut'] ?? 'dibuat') === 'dibuat')>Terbaru</option>
                <option value="tenggat" @selected(($filter['urut'] ?? '') === 'tenggat')>Tenggat</option>
            </select>
        </label>

        <label class="text-sm">
            <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Arah</span>
            <select name="arah" class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                <option value="desc" @selected(($filter['arah'] ?? 'desc') === 'desc')>Turun</option>
                <option value="asc" @selected(($filter['arah'] ?? '') === 'asc')>Naik</option>
            </select>
        </label>

        <div class="flex items-end gap-2">
            <button type="submit" class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">Terapkan</button>
            <a href="{{ route('arahan.index') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Reset</a>
        </div>
    </form>

    <div class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2 font-medium">Waktu</th>
                    <th class="px-4 py-2 font-medium">Pemberi</th>
                    <th class="px-4 py-2 font-medium">Penerima</th>
                    <th class="px-4 py-2 font-medium">Jenis</th>
                    <th class="px-4 py-2 font-medium">Asal</th>
                    <th class="px-4 py-2 font-medium">Tenggat</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="px-4 py-2 font-medium">Tindak lanjut</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($daftar as $satu)
                    @php($terbuka = $satu->actionItems->filter(fn ($item) => $item->status?->isTunggakan())->count())
                    <tr class="align-top hover:bg-slate-50">
                        <td class="px-4 py-2 text-slate-600">
                            {{ $satu->created_at?->translatedFormat('d M Y H:i') ?? '—' }}
                        </td>
                        <td class="px-4 py-2">
                            <span class="font-medium text-slate-900">{{ $satu->pemberi?->nama_lengkap ?? '—' }}</span>
                            <span class="block text-xs text-slate-500">{{ $satu->pemberi?->jabatan?->nama ?? '—' }}</span>
                        </td>
                        <td class="px-4 py-2 text-slate-600">{{ $satu->penerima?->nama_lengkap ?? '—' }}</td>
                        <td class="px-4 py-2">
                            @if ($satu->jenis === \App\Enums\JenisFeedback::Persetujuan)
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Persetujuan</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">{{ $satu->jenis?->label() }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-slate-600">{{ $satu->asal?->label() }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $satu->tenggat?->translatedFormat('d M Y') ?? '—' }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $satu->status?->label() }}</td>
                        <td class="px-4 py-2 text-slate-600">
                            @if ($terbuka > 0)
                                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">{{ $terbuka }} item terbuka</span>
                            @else
                                <span class="text-xs text-slate-500">Tidak ada tunggakan</span>
                            @endif
                            <a href="{{ route('arahan.show', $satu) }}" class="mt-1 block text-xs text-slate-700 underline">
                                {{ \Illuminate\Support\Str::limit($satu->isi, 60) }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-slate-500">
                            Belum ada arahan yang cocok dengan filter Anda.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $daftar->links() }}</div>
@endsection
