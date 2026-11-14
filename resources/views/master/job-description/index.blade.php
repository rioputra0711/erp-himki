@extends('layouts.app')

@section('judul', 'Master Job Description — ERP HIMKI')

@section('konten')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Master Data</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">Master Job Description</h1>
            <p class="mt-1 text-sm text-slate-600">
                Tugas utama, umum, insidental, indikator, target dasar, dan frekuensi pelaporan per jabatan.
                Target dan indikator wajib ditetapkan sebelum periode penilaian dimulai.
            </p>
        </div>

        <div class="flex flex-wrap gap-2 text-sm">
            <a href="{{ route('master.personel.index') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-white">Personel</a>
            @if ($bolehBuat)
                <a href="{{ route('master.jd.create') }}" class="rounded-lg bg-slate-900 px-3 py-1.5 font-medium text-white hover:bg-slate-800">Buat Draf</a>
            @endif
        </div>
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

    <form method="GET" action="{{ route('master.jd.index') }}" class="mt-6 flex flex-wrap items-end gap-3">
        <label class="text-sm">
            <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Filter jabatan</span>
            <select name="jabatan" class="rounded-lg border border-slate-300 px-3 py-1.5">
                <option value="">Semua jabatan</option>
                @foreach ($jabatan as $satu)
                    <option value="{{ $satu->id }}" @selected($filterJabatan === $satu->id)>{{ $satu->nama }}</option>
                @endforeach
            </select>
        </label>
        <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-white">Terapkan</button>
    </form>

    <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2">Kode</th>
                    <th class="px-4 py-2">Jabatan</th>
                    <th class="px-4 py-2">Kategori</th>
                    <th class="px-4 py-2">Uraian</th>
                    <th class="px-4 py-2">Target dasar</th>
                    <th class="px-4 py-2">Frekuensi</th>
                    <th class="px-4 py-2">Berlaku mulai</th>
                    <th class="px-4 py-2">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($daftar as $jd)
                    <tr class="align-top hover:bg-slate-50">
                        <td class="px-4 py-2 font-mono text-xs">
                            <a href="{{ route('master.jd.show', $jd) }}" class="text-slate-900 hover:underline">{{ $jd->kode }}</a>
                            <span class="ml-1 rounded bg-slate-100 px-1 text-[10px] uppercase text-slate-600">v{{ $jd->versi }}</span>
                        </td>
                        <td class="px-4 py-2 text-slate-600">{{ $jd->jabatan?->nama ?? '—' }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $jd->kategori?->label() }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ \Illuminate\Support\Str::limit($jd->uraian, 60) }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ \Illuminate\Support\Str::limit($jd->target ?? '—', 40) }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $jd->frekuensi?->label() ?? '—' }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $jd->berlaku_mulai?->translatedFormat('d M Y') ?? '—' }}</td>
                        <td class="px-4 py-2">
                            @if (! $jd->is_active)
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Diarsipkan</span>
                            @elseif ($jd->sudahDitetapkan())
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Ditetapkan</span>
                            @else
                                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Draf</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-slate-500">
                            Belum ada Job Description yang dapat Anda lihat.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="mt-3 text-xs text-slate-500">
        Hanya Administrator Aplikasi yang dapat menetapkan versi.
        Anda {{ $bolehTetapkan ? 'memegang' : 'tidak memegang' }} izin penetapan.
    </p>
@endsection
