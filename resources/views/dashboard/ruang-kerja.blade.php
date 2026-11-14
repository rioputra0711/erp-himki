@extends('layouts.app')

@section('judul', $peran->dashboard().' — ERP HIMKI')

@section('konten')
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">{{ $peran->label() }}</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">{{ $peran->dashboard() }}</h1>
            <p class="mt-1 text-sm text-slate-600">
                Selamat datang, {{ $pengguna?->name }}@if ($personel) — {{ $personel->nama_lengkap }}@if ($personel->jabatan), {{ $personel->jabatan->nama }}@endif @endif.
            </p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-right">
            <p class="text-xs uppercase tracking-wide text-slate-500">Waktu server</p>
            <p class="text-sm font-semibold text-slate-900">{{ $waktuServer->translatedFormat('d F Y H:i') }} WIB</p>
        </div>
    </div>

    <section class="mt-8">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Wewenang akun Anda</h2>

        @if ($modul === [])
            <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Akun ini belum memiliki wewenang pada modul mana pun. Hubungi Administrator Aplikasi.
            </p>
        @else
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($modul as $item)
                    <a href="{{ route('modul.show', $item['modul']->value) }}"
                       class="rounded-xl border border-slate-200 bg-white p-4 transition hover:border-slate-300 hover:shadow-sm">
                        <p class="text-sm font-semibold text-slate-900">{{ $item['label'] }}</p>
                        <p class="mt-2 flex flex-wrap gap-1">
                            @foreach ($item['aksi'] as $aksi)
                                <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600">{{ $aksi }}</span>
                            @endforeach
                        </p>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    <section class="mt-8">
        <div class="flex flex-wrap items-end justify-between gap-2">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Tunggakan Anda</h2>
            <p class="text-xs text-slate-500">
                Pekerjaan yang belum tuntas tetap ditahan di sini sampai ditutup.
            </p>
        </div>

        @if ($tunggakan['jumlah'] === 0)
            <p class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                Tidak ada tunggakan.
            </p>
        @else
            <div class="mt-3 grid gap-3 lg:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tugas belum final</p>
                    <ul class="mt-2 space-y-2 text-sm">
                        @forelse ($tunggakan['tugas'] as $satu)
                            <li>
                                <a href="{{ route('tugas.show', $satu) }}" class="font-medium text-slate-900 hover:underline">
                                    {{ $satu->kode ?? 'Tanpa ID' }}
                                </a>
                                <span class="block text-xs text-slate-600">
                                    {{ \Illuminate\Support\Str::limit($satu->judul, 60) }}
                                </span>
                                <span class="block text-xs text-slate-500">
                                    Tenggat {{ $satu->tenggat?->translatedFormat('d M Y') ?? '—' }}
                                    @if ($satu->terlambat())
                                        &middot; <span class="font-medium text-rose-700">terlambat</span>
                                    @endif
                                </span>
                            </li>
                        @empty
                            <li class="text-slate-500">Tidak ada tugas tertunda.</li>
                        @endforelse
                    </ul>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tindak lanjut terbuka</p>
                    <ul class="mt-2 space-y-2 text-sm">
                        @forelse ($tunggakan['tindakLanjut'] as $satu)
                            <li>
                                @if ($satu->feedback)
                                    <a href="{{ route('arahan.show', $satu->feedback) }}" class="font-medium text-slate-900 hover:underline">
                                        {{ \Illuminate\Support\Str::limit($satu->deskripsi, 60) }}
                                    </a>
                                @else
                                    <span class="font-medium text-slate-900">{{ \Illuminate\Support\Str::limit($satu->deskripsi, 60) }}</span>
                                @endif
                                <span class="block text-xs text-slate-500">
                                    {{ $satu->status?->label() ?? '—' }}
                                    &middot; tenggat {{ $satu->tenggat?->translatedFormat('d M Y') ?? '—' }}
                                </span>
                            </li>
                        @empty
                            <li class="text-slate-500">Tidak ada tindak lanjut terbuka.</li>
                        @endforelse
                    </ul>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Arahan menunggu tindakan</p>
                    <ul class="mt-2 space-y-2 text-sm">
                        @forelse ($tunggakan['arahan'] as $satu)
                            <li>
                                <a href="{{ route('arahan.show', $satu) }}" class="font-medium text-slate-900 hover:underline">
                                    {{ \Illuminate\Support\Str::limit($satu->isi, 60) }}
                                </a>
                                <span class="block text-xs text-slate-500">
                                    {{ $satu->jenis?->label() }} &middot; {{ $satu->status?->label() }}
                                </span>
                            </li>
                        @empty
                            <li class="text-slate-500">Tidak ada arahan menunggu.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        @endif
    </section>

    <section class="mt-8">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Aktivitas terakhir</h2>

        <div class="mt-3 overflow-hidden rounded-xl border border-slate-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Waktu</th>
                        <th class="px-4 py-2">Peristiwa</th>
                        <th class="px-4 py-2">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($aktivitasTerakhir as $aktivitas)
                        <tr>
                            <td class="px-4 py-2 text-slate-600">{{ $aktivitas->created_at?->translatedFormat('d M Y H:i') }}</td>
                            <td class="px-4 py-2 font-medium text-slate-900">{{ $aktivitas->event }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ $aktivitas->reason ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-4 text-center text-slate-500">Belum ada aktivitas tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
