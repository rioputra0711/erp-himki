@extends('layouts.app')

@section('judul', 'Master Personel — ERP HIMKI')

@section('konten')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Master Data</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">Master Personel &amp; Jabatan</h1>
            <p class="mt-1 text-sm text-slate-600">
                Identitas resmi personel Badan Eksekutif. Nama dan jabatan di sini dipakai otomatis pada setiap laporan.
            </p>
        </div>

        <nav class="flex flex-wrap gap-2 text-sm">
            <a href="{{ route('master.jabatan.index') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-white">Jabatan</a>
            <a href="{{ route('master.bidang.index') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-white">Bidang Kerja</a>
            <a href="{{ route('master.jd.index') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-white">Job Description</a>
            @if ($bolehKelola)
                <a href="{{ route('master.personel.create') }}" class="rounded-lg bg-slate-900 px-3 py-1.5 font-medium text-white hover:bg-slate-800">Tambah Personel</a>
            @endif
        </nav>
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

    <form method="GET" action="{{ route('master.personel.index') }}"
          class="mt-6 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-5">
        <label class="text-sm">
            <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Cari nama / ID</span>
            <input type="search" name="q" value="{{ $filter['q'] ?? '' }}" maxlength="100"
                   class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
        </label>

        <label class="text-sm">
            <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Bidang</span>
            <select name="bidang" class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                <option value="">Semua bidang</option>
                @foreach ($bidang as $satu)
                    <option value="{{ $satu->id }}" @selected((int) ($filter['bidang'] ?? 0) === $satu->id)>
                        {{ $satu->nama }}{{ $satu->is_active ? '' : ' (nonaktif)' }}
                    </option>
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
            <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Status</span>
            <select name="status" class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                <option value="">Semua status</option>
                <option value="aktif" @selected(($filter['status'] ?? '') === 'aktif')>Aktif</option>
                <option value="nonaktif" @selected(($filter['status'] ?? '') === 'nonaktif')>Nonaktif</option>
            </select>
        </label>

        <div class="flex items-end gap-2">
            <input type="hidden" name="urut" value="{{ $filter['urut'] ?? 'nama' }}">
            <input type="hidden" name="arah" value="{{ $filter['arah'] ?? 'asc' }}">
            <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Terapkan</button>
            <a href="{{ route('master.personel.index') }}" class="rounded-lg px-3 py-1.5 text-sm text-slate-500 hover:text-slate-900">Reset</a>
        </div>
    </form>

    <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2">ID Personel</th>
                    <th class="px-4 py-2">Nama resmi</th>
                    <th class="px-4 py-2">Jabatan</th>
                    <th class="px-4 py-2">Bidang kerja</th>
                    <th class="px-4 py-2">Kategori</th>
                    <th class="px-4 py-2">Status aktif</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($daftar as $satu)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2 font-mono text-xs text-slate-500">{{ $satu->nip ?? '—' }}</td>
                        <td class="px-4 py-2">
                            <a href="{{ route('master.personel.show', $satu) }}" class="font-medium text-slate-900 hover:underline">
                                {{ $satu->nama_lengkap }}
                            </a>
                        </td>
                        <td class="px-4 py-2 text-slate-600">{{ $satu->jabatan?->nama ?? '—' }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $satu->bidang?->nama ?? '—' }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $satu->kategori?->label() }}</td>
                        <td class="px-4 py-2">
                            @if ($satu->status_aktif)
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Aktif</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Nonaktif</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-slate-500">
                            Tidak ada personel yang dapat Anda lihat.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $daftar->links() }}</div>
@endsection
