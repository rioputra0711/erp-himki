@extends('layouts.app')

@section('judul', $jd->kode.' — Job Description')

@section('konten')
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">
                Job Description &middot; versi {{ $jd->versi }}
            </p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">{{ $jd->kode }}</h1>
            <p class="mt-1 text-sm text-slate-600">
                {{ $jd->jabatan?->nama ?? '—' }} &middot; {{ $jd->kategori?->label() }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2 text-sm">
            <a href="{{ route('master.jd.index') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-white">Kembali</a>

            @can('update', $jd)
                <a href="{{ route('master.jd.edit', $jd) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-white">Ubah draf</a>
            @endcan
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

    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <section class="rounded-xl border border-slate-200 bg-white p-4 lg:col-span-2">
            <div class="flex items-center gap-2">
                @if (! $jd->is_active)
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Diarsipkan</span>
                @elseif ($jd->sudahDitetapkan())
                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Ditetapkan &amp; terkunci</span>
                @else
                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Draf</span>
                @endif
            </div>

            <dl class="mt-4 space-y-3 text-sm">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Uraian tugas</dt>
                    <dd class="mt-1 whitespace-pre-line text-slate-900">{{ $jd->uraian }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Indikator hasil kerja</dt>
                    <dd class="mt-1 whitespace-pre-line text-slate-900">{{ $jd->indikator ?? 'Belum ditetapkan' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Target dasar</dt>
                    <dd class="mt-1 whitespace-pre-line text-slate-900">{{ $jd->target ?? 'Belum ditetapkan' }}</dd>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Frekuensi pelaporan</dt>
                        <dd class="mt-1 text-slate-900">{{ $jd->frekuensi?->label() ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Berlaku mulai</dt>
                        <dd class="mt-1 text-slate-900">{{ $jd->berlaku_mulai?->translatedFormat('d F Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Ditetapkan pada</dt>
                        <dd class="mt-1 text-slate-900">
                            {{ $jd->ditetapkan_pada?->translatedFormat('d F Y H:i') ?? 'Belum ditetapkan' }}
                            @if ($jd->ditetapkanOleh)
                                <span class="block text-xs text-slate-500">oleh {{ $jd->ditetapkanOleh->name }}</span>
                            @endif
                        </dd>
                    </div>
                </div>
            </dl>

            @if ($jd->sudahDitetapkan())
                <p class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
                    Versi ini terkunci. Target dan indikator tidak dapat diubah selama periode penilaian berjalan;
                    perubahan harus dibuat sebagai versi baru dengan tanggal berlaku berikutnya.
                </p>
            @endif
        </section>

        <aside class="space-y-4">
            @can('configure', $jd)
                @unless ($jd->sudahDitetapkan())
                    <form method="POST" action="{{ route('master.jd.tetapkan', $jd) }}"
                          class="rounded-xl border border-slate-200 bg-white p-4 space-y-3"
                          onsubmit="return confirm('Tetapkan dan kunci versi ini? Target dan indikator tidak dapat diubah setelah ditetapkan.');">
                        @csrf
                        @method('PATCH')
                        <h2 class="text-sm font-semibold text-slate-900">Tetapkan &amp; kunci versi</h2>
                        <p class="text-xs text-slate-500">
                            Standar evaluasi harus final sebelum periode penilaian dimulai.
                        </p>

                        <label class="block text-sm">
                            <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Target dasar</span>
                            <textarea name="target" rows="2" required maxlength="2000"
                                      class="w-full rounded-lg border border-slate-300 px-3 py-1.5">{{ old('target', $jd->target) }}</textarea>
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Indikator hasil kerja</span>
                            <textarea name="indikator" rows="2" required maxlength="2000"
                                      class="w-full rounded-lg border border-slate-300 px-3 py-1.5">{{ old('indikator', $jd->indikator) }}</textarea>
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Frekuensi pelaporan</span>
                            <select name="frekuensi" required class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                                @foreach (App\Enums\FrekuensiTugas::cases() as $f)
                                    <option value="{{ $f->value }}" @selected($jd->frekuensi === $f)>{{ $f->label() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Berlaku mulai</span>
                            <input type="date" name="berlaku_mulai" required
                                   value="{{ old('berlaku_mulai', $jd->berlaku_mulai?->toDateString()) }}"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Alasan penetapan (wajib)</span>
                            <textarea name="alasan" rows="2" required minlength="10" maxlength="500"
                                      class="w-full rounded-lg border border-slate-300 px-3 py-1.5">{{ old('alasan') }}</textarea>
                        </label>

                        <button type="submit" class="w-full rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">
                            Tetapkan &amp; kunci
                        </button>
                    </form>
                @endunless

                <form method="POST" action="{{ route('master.jd.versi', $jd) }}"
                      class="rounded-xl border border-slate-200 bg-white p-4 space-y-3">
                    @csrf
                    <h2 class="text-sm font-semibold text-slate-900">Buat versi baru</h2>
                    <p class="text-xs text-slate-500">
                        Versi baru dibuat sebagai draf dan harus ditetapkan sebelum tanggal berlakunya.
                    </p>
                    <label class="block text-sm">
                        <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Berlaku mulai</span>
                        <input type="date" name="berlaku_mulai" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Alasan (wajib)</span>
                        <textarea name="alasan" rows="2" required minlength="10" maxlength="500"
                                  class="w-full rounded-lg border border-slate-300 px-3 py-1.5"></textarea>
                    </label>
                    <button type="submit" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                        Buat versi baru
                    </button>
                </form>

                @if ($jd->is_active)
                    <form method="POST" action="{{ route('master.jd.arsipkan', $jd) }}"
                          class="rounded-xl border border-rose-200 bg-white p-4 space-y-3">
                        @csrf
                        @method('PATCH')
                        <h2 class="text-sm font-semibold text-rose-700">Arsipkan versi</h2>
                        <label class="block text-sm">
                            <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Alasan (wajib)</span>
                            <textarea name="alasan" rows="2" required minlength="10" maxlength="500"
                                      class="w-full rounded-lg border border-slate-300 px-3 py-1.5"></textarea>
                        </label>
                        <button type="submit" class="w-full rounded-lg bg-rose-600 px-3 py-2 text-sm font-medium text-white hover:bg-rose-700">
                            Arsipkan
                        </button>
                    </form>
                @endif
            @endcan

            @if ($jd->induk)
                <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Versi sebelumnya</h2>
                    <a href="{{ route('master.jd.show', $jd->induk) }}" class="mt-1 block text-slate-900 hover:underline">
                        {{ $jd->induk->kode }} v{{ $jd->induk->versi }}
                    </a>
                </div>
            @endif

            @if ($jd->versiTurunan->isNotEmpty())
                <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Versi berikutnya</h2>
                    <ul class="mt-1 space-y-1">
                        @foreach ($jd->versiTurunan as $turunan)
                            <li>
                                <a href="{{ route('master.jd.show', $turunan) }}" class="text-slate-900 hover:underline">
                                    {{ $turunan->kode }} v{{ $turunan->versi }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </aside>
    </div>
@endsection
