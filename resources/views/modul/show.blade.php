@extends('layouts.app')

@section('judul', $modul->label().' — ERP HIMKI')

@section('konten')
    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Modul</p>
    <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">{{ $modul->label() }}</h1>

    <p class="mt-2 text-sm text-slate-600">
        Layar operasional modul ini dibangun pada tahap implementasi berikutnya. Yang sudah aktif
        sekarang adalah gerbang otorisasi server-side untuk setiap aksi.
    </p>

    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2">Aksi</th>
                    <th class="px-4 py-2">Izin</th>
                    <th class="px-4 py-2">Status akun</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($aksi as $item)
                    <tr>
                        <td class="px-4 py-2 font-medium text-slate-900">{{ $item['aksi']->label() }}</td>
                        <td class="px-4 py-2 font-mono text-xs text-slate-500">{{ $modul->izin($item['aksi']) }}</td>
                        <td class="px-4 py-2">
                            @if ($item['boleh'])
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Diizinkan</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Tidak berhak</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if (collect($aksi)->firstWhere('boleh', true))
        <p class="mt-4 text-sm">
            <a href="{{ route('modul.ekspor', $modul->value) }}"
               class="rounded-lg border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-slate-50">
                Unduh rekapitulasi (CSV)
            </a>
        </p>
    @endif
@endsection
