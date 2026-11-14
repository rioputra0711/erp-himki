@extends('layouts.app')

@section('judul', ($tugas->kode ?? 'Penugasan').' — ERP HIMKI')

@section('konten')
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">
                Penugasan &middot; <span class="font-mono">{{ $tugas->kode ?? 'tanpa ID' }}</span>
            </p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">{{ $tugas->judul }}</h1>
            <p class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-600">
                <x-lencana-status :status="$tugas->status" />
                <span>{{ $tugas->sumber_perintah?->label() }}</span>
                <span>&middot;</span>
                <span>{{ $tugas->kategori?->label() }}</span>
                <span>&middot;</span>
                <span>Prioritas {{ $tugas->prioritas?->label() }}</span>
                @if ($tugas->terlambat())
                    <span class="rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700">Terlambat</span>
                @endif
            </p>
        </div>

        <div class="flex flex-wrap gap-2 text-sm">
            <a href="{{ route('tugas.index') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-white">Daftar</a>
            @can('sunting', $tugas)
                <a href="{{ route('tugas.edit', $tugas) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-white">Ubah</a>
            @endcan
        </div>
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

    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <section class="rounded-xl border border-slate-200 bg-white p-4 lg:col-span-2">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Rincian tugas</h2>

            <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Pemberi tugas</dt>
                    <dd class="text-slate-900">
                        {{ $tugas->pemberiTugas?->nama_lengkap ?? '—' }}
                        @if ($tugas->diteruskanOleh)
                            <span class="block text-xs text-slate-500">Diteruskan {{ $tugas->diteruskanOleh->nama_lengkap }}</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Penanggung jawab</dt>
                    <dd class="text-slate-900">
                        {{ $tugas->pic?->nama_lengkap ?? '—' }}
                        @if ($tugas->pic?->jabatan)
                            <span class="block text-xs text-slate-500">{{ $tugas->pic->jabatan->nama }}</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Tanggal mulai</dt>
                    <dd class="text-slate-900">{{ $tugas->tanggal_mulai?->translatedFormat('d F Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Tenggat</dt>
                    <dd class="text-slate-900">{{ $tugas->tenggat?->translatedFormat('d F Y H:i') ?? '—' }} WIB</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Progres</dt>
                    <dd class="text-slate-900 tabular-nums">{{ (int) $tugas->progres }}%</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Job Description</dt>
                    <dd class="text-slate-900">{{ $tugas->jobDescription?->kode ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Dependensi</dt>
                    <dd class="text-slate-900">{{ $tugas->dependensi?->kode ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Arahan asal</dt>
                    <dd class="text-slate-900">
                        @if ($tugas->sumberArahan)
                            #{{ $tugas->sumberArahan->id }} — {{ $tugas->sumberArahan->pemberi?->nama_lengkap ?? 'Pengurus' }}
                        @else
                            —
                        @endif
                    </dd>
                </div>
            </dl>

            <div class="mt-4 space-y-3 text-sm">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Uraian</p>
                    <p class="mt-1 whitespace-pre-line text-slate-800">{{ $tugas->deskripsi }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Target keluaran</p>
                    <p class="mt-1 whitespace-pre-line text-slate-800">{{ $tugas->target_keluaran ?? '—' }}</p>
                </div>
                @if ($tugas->kendala)
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-500">Kendala terakhir</p>
                        <p class="mt-1 whitespace-pre-line text-slate-800">{{ $tugas->kendala }}</p>
                    </div>
                @endif
            </div>
        </section>

        <section class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Jejak penyelesaian</h2>
                <dl class="mt-3 space-y-2">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Diverifikasi</dt>
                        <dd class="text-slate-900">
                            {{ $tugas->verifikator?->nama_lengkap ?? '—' }}
                            @if ($tugas->diverifikasi_pada)
                                <span class="block text-xs text-slate-500">{{ $tugas->diverifikasi_pada->translatedFormat('d M Y H:i') }} WIB</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Ditutup pada</dt>
                        <dd class="text-slate-900">{{ $tugas->ditutup_pada?->translatedFormat('d M Y H:i') ?? '—' }}</dd>
                    </div>
                    @if ($tugas->alasan_penutupan)
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">Alasan penutupan</dt>
                            <dd class="text-slate-800">{{ $tugas->alasan_penutupan }}</dd>
                        </div>
                    @endif
                    @if ($tugas->bukti_penutupan)
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">Bukti penutupan</dt>
                            <dd class="text-slate-800">{{ $tugas->bukti_penutupan }}</dd>
                        </div>
                    @endif
                    @if ($tugas->alasan_pembatalan)
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">Alasan pembatalan</dt>
                            <dd class="text-slate-800">{{ $tugas->alasan_pembatalan }}</dd>
                        </div>
                    @endif
                    @if ($tugas->bukti_pembatalan)
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">Bukti pembatalan</dt>
                            <dd class="text-slate-800">{{ $tugas->bukti_pembatalan }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Riwayat audit terakhir</h2>
                <ul class="mt-3 space-y-2">
                    @forelse ($riwayat as $satu)
                        <li class="border-l-2 border-slate-200 pl-3">
                            <p class="text-xs text-slate-500">
                                {{ $satu->created_at?->translatedFormat('d M Y H:i') }} WIB &middot; {{ $satu->event }}
                            </p>
                            <p class="text-slate-800">{{ $satu->actor_nama ?? 'Sistem' }}</p>
                            @if ($satu->reason)
                                <p class="text-xs text-slate-600">{{ $satu->reason }}</p>
                            @endif
                        </li>
                    @empty
                        <li class="text-slate-500">Belum ada jejak audit.</li>
                    @endforelse
                </ul>
            </div>

            @if ($dokumen->isNotEmpty())
                <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Dokumen terkait</h2>
                    <ul class="mt-3 space-y-1">
                        @foreach ($dokumen as $satu)
                            <li class="text-slate-800">{{ $satu->judul }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>
    </div>

    @if ($tugas->actionItems->isNotEmpty())
        <section class="mt-4 rounded-xl border border-slate-200 bg-white p-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Action item</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-2 font-medium">Uraian</th>
                            <th class="px-3 py-2 font-medium">PIC</th>
                            <th class="px-3 py-2 font-medium">Tenggat</th>
                            <th class="px-3 py-2 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($tugas->actionItems as $satu)
                            <tr>
                                <td class="px-3 py-2 text-slate-800">{{ \Illuminate\Support\Str::limit($satu->deskripsi, 80) }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $satu->pic?->nama_lengkap ?? '—' }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $satu->tenggat?->translatedFormat('d M Y') ?? '—' }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $satu->status?->label() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-slate-500">
                Pengelolaan action item dilakukan di layar <a href="{{ route('arahan.index') }}" class="underline">Arahan &amp; Tindak Lanjut</a>.
            </p>
        </section>
    @endif

    <section class="mt-4 grid gap-4 lg:grid-cols-2">
        @can('progres', $tugas)
            <form method="POST" action="{{ route('tugas.progres', $tugas) }}"
                  class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
                @csrf
                @method('PATCH')
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Laporkan progres</h2>
                <label class="mt-3 block">
                    <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Progres (%)</span>
                    <input type="number" name="progres" min="0" max="100" required
                           value="{{ old('progres', (int) $tugas->progres) }}"
                           class="w-32 rounded-lg border border-slate-300 px-3 py-1.5">
                </label>
                <label class="mt-3 block">
                    <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Kendala (wajib bila terhambat)</span>
                    <textarea name="kendala" rows="2" maxlength="2000"
                              class="w-full rounded-lg border border-slate-300 px-3 py-1.5">{{ old('kendala', $tugas->kendala) }}</textarea>
                </label>
                <button type="submit" class="mt-3 rounded-lg bg-slate-900 px-3 py-1.5 font-medium text-white hover:bg-slate-800">
                    Simpan progres
                </button>
            </form>
        @endcan

        @can('ajukan', $tugas)
            <form method="POST" action="{{ route('tugas.ajukan', $tugas) }}"
                  class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
                @csrf
                @method('PATCH')
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Ajukan selesai</h2>
                <p class="mt-2 text-slate-600">
                    Hanya sah bila progres sudah 100%. Verifikasi dilakukan Direktur Eksekutif.
                </p>
                <button type="submit" class="mt-3 rounded-lg border border-slate-300 px-3 py-1.5 font-medium text-slate-800 hover:bg-slate-50">
                    Ajukan verifikasi
                </button>
            </form>
        @endcan

        @can('verify', $tugas)
            <form method="POST" action="{{ route('tugas.verifikasi', $tugas) }}"
                  class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
                @csrf
                @method('PATCH')
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Verifikasi</h2>
                <p class="mt-2 text-slate-600">Mengunci hasil kerja. Wajib alasan tertulis yang tercatat pada audit.</p>
                <textarea name="alasan" rows="2" required minlength="10" maxlength="500"
                          class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-1.5"
                          placeholder="Dasar verifikasi, mis. seluruh keluaran sesuai target.">{{ old('alasan') }}</textarea>
                <button type="submit" class="mt-3 rounded-lg bg-emerald-700 px-3 py-1.5 font-medium text-white hover:bg-emerald-800">
                    Verifikasi tugas
                </button>
            </form>
        @endcan

        @can('tutup', $tugas)
            <form method="POST" action="{{ route('tugas.tutup', $tugas) }}"
                  class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
                @csrf
                @method('PATCH')
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Tutup tugas</h2>
                <p class="mt-2 text-slate-600">Hanya setelah terverifikasi dan progres 100%. Alasan dan bukti wajib.</p>
                <label class="mt-2 block">
                    <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Alasan penutupan</span>
                    <textarea name="alasan_penutupan" rows="2" required minlength="10" maxlength="500"
                              class="w-full rounded-lg border border-slate-300 px-3 py-1.5">{{ old('alasan_penutupan') }}</textarea>
                </label>
                <label class="mt-2 block">
                    <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Bukti penutupan</span>
                    <input type="text" name="bukti_penutupan" required minlength="5" maxlength="500"
                           value="{{ old('bukti_penutupan') }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-1.5"
                           placeholder="Nomor berita acara / tautan bukti">
                </label>
                @if ($dokumen->isNotEmpty())
                    <label class="mt-2 block">
                        <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Dokumen bukti (opsional)</span>
                        <select name="bukti_dokumen_id" class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                            <option value="">Tanpa dokumen</option>
                            @foreach ($dokumen as $satu)
                                <option value="{{ $satu->id }}" @selected((int) old('bukti_dokumen_id') === (int) $satu->id)>{{ $satu->judul }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <button type="submit" class="mt-3 rounded-lg bg-slate-900 px-3 py-1.5 font-medium text-white hover:bg-slate-800">
                    Tutup tugas
                </button>
            </form>
        @endcan

        @can('batalkan', $tugas)
            <form method="POST" action="{{ route('tugas.batalkan', $tugas) }}"
                  class="rounded-xl border border-rose-200 bg-white p-4 text-sm">
                @csrf
                @method('PATCH')
                <h2 class="text-sm font-semibold uppercase tracking-wide text-rose-700">Batalkan tugas</h2>
                <p class="mt-2 text-slate-600">Pembatalan bersifat final. Alasan dan bukti wajib dicatat.</p>
                <label class="mt-2 block">
                    <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Alasan pembatalan</span>
                    <textarea name="alasan_pembatalan" rows="2" required minlength="10" maxlength="500"
                              class="w-full rounded-lg border border-slate-300 px-3 py-1.5">{{ old('alasan_pembatalan') }}</textarea>
                </label>
                <label class="mt-2 block">
                    <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Bukti pembatalan</span>
                    <input type="text" name="bukti_pembatalan" required minlength="5" maxlength="500"
                           value="{{ old('bukti_pembatalan') }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                </label>
                <button type="submit" class="mt-3 rounded-lg bg-rose-700 px-3 py-1.5 font-medium text-white hover:bg-rose-800">
                    Batalkan tugas
                </button>
            </form>
        @endcan

        @can('aktifkan', $tugas)
            @if ($tugas->status === \App\Enums\StatusPenugasan::Draft)
                <form method="POST" action="{{ route('tugas.aktifkan', $tugas) }}"
                      class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
                    @csrf
                    @method('PATCH')
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Aktifkan draf</h2>
                    <label class="mt-2 block">
                        <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Alasan (opsional)</span>
                        <input type="text" name="alasan" minlength="10" maxlength="500" value="{{ old('alasan') }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                    </label>
                    <button type="submit" class="mt-3 rounded-lg border border-slate-300 px-3 py-1.5 font-medium text-slate-800 hover:bg-slate-50">
                        Aktifkan
                    </button>
                </form>
            @endif
        @endcan
    </section>
@endsection
