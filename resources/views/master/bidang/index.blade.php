@extends('layouts.app')

@section('judul', 'Master Bidang Kerja — ERP HIMKI')

@section('konten')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Master Data</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">Master Bidang Kerja</h1>
            <p class="mt-1 text-sm text-slate-600">
                Bidang resmi Badan Eksekutif: Keuangan, Administrasi, Publikasi, HI/Pameran, TI, Operasional.
            </p>
        </div>

        <a href="{{ route('master.personel.index') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-white">
            Kembali ke Personel
        </a>
    </div>

    @if (session('status'))
        <div class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="mt-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $pesan)
                    <li>{{ $pesan }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2">Kode</th>
                    <th class="px-4 py-2">Nama bidang</th>
                    <th class="px-4 py-2">Jumlah personel</th>
                    <th class="px-4 py-2">Status</th>
                    @if ($bolehUbah)
                        <th class="px-4 py-2">Aksi</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($daftar as $satu)
                    <tr class="align-top hover:bg-slate-50">
                        <td class="px-4 py-2 font-mono text-xs text-slate-500">{{ $satu->kode }}</td>
                        <td class="px-4 py-2 font-medium text-slate-900">{{ $satu->nama }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $satu->personel_count }}</td>
                        <td class="px-4 py-2">
                            @if ($satu->is_active)
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Aktif</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Nonaktif</span>
                            @endif
                        </td>
                        @if ($bolehUbah)
                            <td class="px-4 py-2">
                                <form method="POST" action="{{ route('master.bidang.update', $satu) }}" class="flex flex-wrap items-end gap-2">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="kode" value="{{ $satu->kode }}">
                                    <label class="text-xs">
                                        <span class="mb-1 block uppercase tracking-wide text-slate-500">Nama</span>
                                        <input type="text" name="nama" value="{{ $satu->nama }}" maxlength="100" required
                                               class="w-56 rounded-lg border border-slate-300 px-2 py-1">
                                    </label>
                                    <button type="submit" class="rounded-lg border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">Simpan</button>
                                </form>

                                @if ($satu->is_active)
                                    <form method="POST" action="{{ route('master.bidang.nonaktifkan', $satu) }}" class="mt-2">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="text-xs text-rose-600 hover:underline">Nonaktifkan</button>
                                    </form>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($bolehUbah)
        <form method="POST" action="{{ route('master.bidang.store') }}"
              class="mt-6 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-3">
            @csrf
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 sm:col-span-3">Tambah bidang kerja</h2>

            <label class="text-sm">
                <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Kode</span>
                <input type="text" name="kode" maxlength="20" required pattern="[A-Z0-9_]+"
                       value="{{ old('kode') }}" class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
            </label>
            <label class="text-sm sm:col-span-2">
                <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Nama bidang</span>
                <input type="text" name="nama" maxlength="100" required
                       value="{{ old('nama') }}" class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
            </label>
            <div class="sm:col-span-3">
                <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Tambah</button>
            </div>
        </form>
    @endif
@endsection
