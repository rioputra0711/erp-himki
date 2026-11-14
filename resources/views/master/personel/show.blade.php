@extends('layouts.app')

@section('judul', $personel->nama_lengkap.' — Master Personel')

@section('konten')
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Master Personel</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">{{ $personel->nama_lengkap }}</h1>
            <p class="mt-1 text-sm text-slate-600">
                {{ $personel->jabatan?->nama ?? 'Jabatan belum ditetapkan' }}
                @if ($personel->bidang)
                    &middot; {{ $personel->bidang->nama }}
                @endif
            </p>
        </div>

        <div class="flex flex-wrap gap-2 text-sm">
            <a href="{{ route('master.personel.index') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-white">Kembali</a>

            @can('update', $personel)
                <a href="{{ route('master.personel.edit', $personel) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-white">Ubah</a>
            @endcan
        </div>
    </div>

    @if (session('status'))
        <div class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
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
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Data resmi</h2>

            <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">ID Personel</dt>
                    <dd class="font-mono text-slate-900">{{ $personel->nip ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Kategori</dt>
                    <dd class="text-slate-900">{{ $personel->kategori?->label() }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Jabatan</dt>
                    <dd class="text-slate-900">{{ $personel->jabatan?->nama ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Bidang kerja</dt>
                    <dd class="text-slate-900">{{ $personel->bidang?->nama ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Tanggal masuk</dt>
                    <dd class="text-slate-900">{{ $personel->tanggal_masuk?->translatedFormat('d F Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Status aktif</dt>
                    <dd>
                        @if ($personel->status_aktif)
                            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Aktif</span>
                        @else
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Nonaktif</span>
                        @endif
                    </dd>
                </div>
            </dl>

            <div class="mt-5 border-t border-slate-100 pt-4">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Data pribadi (terbatas)</h3>

                @if ($adalahSesi || auth()->user()->can('create', \App\Models\Personel::class))
                    <dl class="mt-2 grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">Akun</dt>
                            <dd class="text-slate-900">{{ $personel->user?->email ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">Telepon</dt>
                            <dd class="text-slate-900">{{ $personel->telepon ?? '—' }}</dd>
                        </div>
                    </dl>
                @else
                    <p class="mt-2 text-sm text-slate-500">
                        Data kontak hanya dapat dilihat oleh pemilik akun dan Administrator Aplikasi.
                    </p>
                @endif
            </div>
        </section>

        <aside class="space-y-4">
            <x-identitas-terkunci :identitas="$adalahSesi ? [
                'personel_id' => $personel->id,
                'nama' => $personel->nama_lengkap,
                'jabatan' => $personel->jabatan?->nama,
                'bidang' => $personel->bidang?->nama,
            ] : null" />

            @can('deactivate', $personel)
                @if ($personel->status_aktif)
                    <form method="POST" action="{{ route('master.personel.nonaktifkan', $personel) }}"
                          class="rounded-xl border border-rose-200 bg-white p-4"
                          onsubmit="return confirm('Nonaktifkan personel ini? Riwayat tetap tersimpan.');">
                        @csrf
                        @method('PATCH')
                        <h2 class="text-sm font-semibold text-rose-700">Nonaktifkan personel</h2>
                        <p class="mt-1 text-xs text-slate-500">
                            Personel nonaktif tidak dapat menerima penugasan baru. Data historis tetap utuh.
                        </p>
                        <label class="mt-3 block text-sm">
                            <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Alasan (wajib)</span>
                            <textarea name="alasan" rows="2" required minlength="10" maxlength="500"
                                      class="w-full rounded-lg border border-slate-300 px-3 py-1.5"></textarea>
                        </label>
                        <button type="submit" class="mt-3 rounded-lg bg-rose-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-rose-700">
                            Nonaktifkan
                        </button>
                    </form>
                @endif
            @endcan
        </aside>
    </div>

    <section class="mt-6">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">
                Job Description jabatan ini
            </h2>
            <a href="{{ route('master.jd.index', ['jabatan' => $personel->jabatan_id]) }}"
               class="text-sm text-slate-600 hover:text-slate-900">Lihat semua</a>
        </div>

        <div class="mt-3 overflow-hidden rounded-xl border border-slate-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Kode</th>
                        <th class="px-4 py-2">Kategori</th>
                        <th class="px-4 py-2">Uraian</th>
                        <th class="px-4 py-2">Berlaku</th>
                        <th class="px-4 py-2">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($jobDescription as $jd)
                        <tr>
                            <td class="px-4 py-2 font-mono text-xs">
                                <a href="{{ route('master.jd.show', $jd) }}" class="text-slate-900 hover:underline">{{ $jd->kode }}</a>
                            </td>
                            <td class="px-4 py-2 text-slate-600">{{ $jd->kategori?->label() }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ \Illuminate\Support\Str::limit($jd->uraian, 80) }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ $jd->berlaku_mulai?->translatedFormat('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-2">
                                @if ($jd->sudahDitetapkan())
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Ditetapkan</span>
                                @else
                                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Draf</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-slate-500">Belum ada Job Description untuk jabatan ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
