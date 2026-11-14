@extends('layouts.app')

@section('judul', 'Notifikasi — ERP HIMKI')

@section('konten')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Kotak Masuk</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">Notifikasi</h1>
            <p class="mt-1 text-sm text-slate-600">
                Pemberitahuan penugasan, arahan, dan tindak lanjut yang ditujukan kepada akun Anda.
            </p>
        </div>

        @if ($jumlahBelumDibaca > 0)
            <form method="POST" action="{{ route('notifikasi.baca-semua') }}">
                @csrf
                <button type="submit"
                        class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">
                    Baca semua ({{ $jumlahBelumDibaca }})
                </button>
            </form>
        @endif
    </div>

    @if (session('status'))
        <div class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    <ul class="mt-6 space-y-3">
        @forelse ($daftar as $baris)
            @php
                $data = is_array($baris->data) ? $baris->data : [];
                $belumDibaca = $baris->read_at === null;
            @endphp
            <li class="rounded-xl border bg-white p-4 {{ $belumDibaca ? 'border-slate-300' : 'border-slate-200' }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">
                            {{ $data['judul'] ?? 'Notifikasi' }}
                            @if ($belumDibaca)
                                <span class="ml-1 rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700">Baru</span>
                            @endif
                        </p>
                        <p class="mt-1 text-sm text-slate-700">{{ $data['pesan'] ?? '' }}</p>
                        <p class="mt-2 text-xs text-slate-500">
                            {{ $baris->created_at?->translatedFormat('d M Y H:i') ?? '—' }} WIB
                            @if (! empty($data['jenis']))
                                &middot; {{ $data['jenis'] }}
                            @endif
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        @if (! empty($data['url']))
                            <a href="{{ $data['url'] }}"
                               class="rounded-lg border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-slate-50">
                                Buka
                            </a>
                        @endif

                        @if ($belumDibaca)
                            <form method="POST" action="{{ route('notifikasi.baca', $baris->id) }}">
                                @csrf
                                <button type="submit"
                                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-slate-50">
                                    Tandai dibaca
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </li>
        @empty
            <li class="rounded-xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-500">
                Belum ada notifikasi.
            </li>
        @endforelse
    </ul>

    <div class="mt-4">{{ $daftar->links() }}</div>
@endsection
