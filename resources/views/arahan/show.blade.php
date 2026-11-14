@extends('layouts.app')

@section('judul', 'Arahan #'.$arahan->id.' — ERP HIMKI')

@section('konten')
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">
                Arahan &middot; <span class="font-mono">#{{ $arahan->id }}</span>
            </p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">
                {{ $arahan->jenis?->label() ?? 'Arahan' }}
                @if ($arahan->asal)
                    <span class="text-base font-normal text-slate-500">dari {{ $arahan->asal->label() }}</span>
                @endif
            </h1>
            <p class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-600">
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                    {{ $arahan->status?->label() ?? '—' }}
                </span>
                @if ($arahan->butuh_tindakan)
                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Butuh tindakan</span>
                @endif
                <span>{{ $arahan->created_at?->translatedFormat('d M Y H:i') ?? '—' }} WIB</span>
            </p>
        </div>

        <div class="flex flex-wrap gap-2 text-sm">
            <a href="{{ route('arahan.index') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-white">Daftar arahan</a>
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
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Isi arahan</h2>
            <p class="mt-3 whitespace-pre-line text-sm text-slate-800">{{ $arahan->isi }}</p>

            @if ($arahan->tenggat)
                <p class="mt-4 text-sm text-slate-600">
                    Tenggat: <span class="font-medium text-slate-900">{{ $arahan->tenggat->translatedFormat('d F Y H:i') }} WIB</span>
                </p>
            @endif

            @if ($arahan->lampiran)
                <p class="mt-3 text-sm text-slate-600">
                    Lampiran:
                    @can('document.view')
                        <a href="{{ route('dokumen.unduh', $arahan->lampiran) }}" class="text-slate-900 underline">
                            {{ $arahan->lampiran->judul }}
                        </a>
                    @else
                        <span class="text-slate-900">{{ $arahan->lampiran->judul }}</span>
                    @endcan
                </p>
            @endif

            @if ($arahan->subjek)
                <p class="mt-3 text-sm text-slate-600">
                    Objek terkait:
                    <span class="text-slate-900">{{ class_basename($arahan->subjek_type) }} #{{ $arahan->subjek->getKey() }}</span>
                </p>
            @endif
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Identitas pemberi</h2>
            <dl class="mt-3 space-y-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Nama</dt>
                    <dd class="text-slate-900">{{ $arahan->pemberi?->nama_lengkap ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Jabatan</dt>
                    <dd class="text-slate-900">{{ $arahan->pemberi_jabatan ?? $arahan->pemberi?->jabatan?->nama ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Asal arahan</dt>
                    <dd class="text-slate-900">{{ $arahan->asal?->label() ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Penerima</dt>
                    <dd class="text-slate-900">
                        {{ $arahan->penerima?->nama_lengkap ?? '—' }}
                        @if ($arahan->penerima?->jabatan)
                            <span class="block text-xs text-slate-500">{{ $arahan->penerima->jabatan->nama }}</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </section>
    </div>

    <section class="mt-4 rounded-xl border border-slate-200 bg-white p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Action item</h2>
            <p class="text-xs text-slate-500">Item yang belum tuntas tetap dihitung sebagai tunggakan pada dashboard.</p>
        </div>

        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2 font-medium">Uraian</th>
                        <th class="px-3 py-2 font-medium">PIC</th>
                        <th class="px-3 py-2 font-medium">Tenggat</th>
                        <th class="px-3 py-2 font-medium">Status</th>
                        <th class="px-3 py-2 font-medium">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($arahan->actionItems as $item)
                        <tr class="align-top">
                            <td class="px-3 py-2 text-slate-800">{{ $item->deskripsi }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $item->pic?->nama_lengkap ?? '—' }}</td>
                            <td class="px-3 py-2 text-slate-600">
                                {{ $item->tenggat?->translatedFormat('d M Y') ?? '—' }}
                                @if ($item->status?->isTunggakan() && $item->tenggat && $item->tenggat->isPast())
                                    <span class="ml-1 rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700">Lewat tenggat</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-slate-600">{{ $item->status?->label() ?? '—' }}</td>
                            <td class="px-3 py-2">
                                <div class="space-y-2">
                                    @can('update', $item)
                                        <form method="POST" action="{{ route('tindak-lanjut.update', $item) }}"
                                              class="flex flex-wrap items-end gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <label class="text-xs">
                                                <span class="mb-1 block uppercase tracking-wide text-slate-500">Status</span>
                                                <select name="status" class="rounded-lg border border-slate-300 px-2 py-1 text-sm">
                                                    <option value="terbuka" @selected($item->status === \App\Enums\StatusActionItem::Terbuka)>Terbuka</option>
                                                    <option value="dalam_proses" @selected($item->status === \App\Enums\StatusActionItem::DalamProses)>Dalam proses</option>
                                                    <option value="menunggu_pihak_lain" @selected($item->status === \App\Enums\StatusActionItem::MenungguPihakLain)>Menunggu pihak lain</option>
                                                    <option value="diajukan_selesai" @selected($item->status === \App\Enums\StatusActionItem::DiajukanSelesai)>Diajukan selesai</option>
                                                </select>
                                            </label>
                                            <label class="text-xs">
                                                <span class="mb-1 block uppercase tracking-wide text-slate-500">Alasan</span>
                                                <input type="text" name="alasan" required minlength="10" maxlength="500"
                                                       class="w-56 rounded-lg border border-slate-300 px-2 py-1 text-sm">
                                            </label>
                                            <button type="submit"
                                                    class="rounded-lg border border-slate-300 px-2 py-1 text-xs font-medium text-slate-800 hover:bg-slate-50">
                                                Simpan
                                            </button>
                                        </form>
                                    @endcan

                                    @can('verify', $item)
                                        <form method="POST" action="{{ route('tindak-lanjut.verifikasi', $item) }}"
                                              class="flex flex-wrap items-end gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <label class="text-xs">
                                                <span class="mb-1 block uppercase tracking-wide text-slate-500">Alasan verifikasi</span>
                                                <input type="text" name="alasan" required minlength="10" maxlength="500"
                                                       class="w-56 rounded-lg border border-slate-300 px-2 py-1 text-sm">
                                            </label>
                                            <button type="submit"
                                                    class="rounded-lg bg-emerald-700 px-2 py-1 text-xs font-medium text-white hover:bg-emerald-800">
                                                Verifikasi selesai
                                            </button>
                                        </form>
                                    @endcan

                                    @can('tutup', $item)
                                        <form method="POST" action="{{ route('tindak-lanjut.tutup', $item) }}"
                                              class="flex flex-wrap items-end gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <label class="text-xs">
                                                <span class="mb-1 block uppercase tracking-wide text-slate-500">Alasan penutupan</span>
                                                <input type="text" name="alasan" required minlength="10" maxlength="500"
                                                       class="w-56 rounded-lg border border-slate-300 px-2 py-1 text-sm">
                                            </label>
                                            <label class="text-xs">
                                                <span class="mb-1 block uppercase tracking-wide text-slate-500">Bukti</span>
                                                <input type="text" name="bukti_penutupan" required minlength="5" maxlength="500"
                                                       class="w-40 rounded-lg border border-slate-300 px-2 py-1 text-sm"
                                                       placeholder="Nomor BA / tautan">
                                            </label>
                                            <button type="submit"
                                                    class="rounded-lg bg-slate-900 px-2 py-1 text-xs font-medium text-white hover:bg-slate-800">
                                                Tutup item
                                            </button>
                                        </form>
                                    @endcan

                                    @if ($item->bukti_penutupan)
                                        <p class="text-xs text-slate-500">Bukti: {{ $item->bukti_penutupan }}</p>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-4 text-center text-slate-500">
                                Belum ada action item. Arahan tanpa action item tidak dapat ditutup.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="mt-4 grid gap-4 lg:grid-cols-2">
        @can('setujui', $arahan)
            <form method="POST" action="{{ route('arahan.setujui', $arahan) }}"
                  class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
                @csrf
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Persetujuan formal</h2>
                <p class="mt-2 text-slate-600">
                    Persetujuan dicatat sebagai keputusan tersendiri dan tidak mengubah status arahan.
                </p>
                <label class="mt-3 block">
                    <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Pernyataan persetujuan</span>
                    <textarea name="isi" rows="2" required maxlength="2000"
                              class="w-full rounded-lg border border-slate-300 px-3 py-1.5">{{ old('isi') }}</textarea>
                </label>
                <button type="submit" class="mt-3 rounded-lg bg-emerald-700 px-3 py-1.5 font-medium text-white hover:bg-emerald-800">
                    Catat persetujuan
                </button>
            </form>
        @endcan

        @if ($bolehTindakLanjut)
            <form method="POST" action="{{ route('arahan.tindak-lanjut', $arahan) }}"
                  class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
                @csrf
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Tambah action item</h2>
                <label class="mt-3 block">
                    <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Uraian tindakan</span>
                    <textarea name="deskripsi" rows="2" required maxlength="2000"
                              class="w-full rounded-lg border border-slate-300 px-3 py-1.5">{{ old('deskripsi') }}</textarea>
                </label>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <label class="text-sm">
                        <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">PIC</span>
                        <select name="pic_id" required class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                            <option value="">Pilih personel aktif</option>
                            @foreach ($pilihanPic as $satu)
                                <option value="{{ $satu->id }}" @selected((int) old('pic_id') === (int) $satu->id)>{{ $satu->nama_lengkap }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Tenggat</span>
                        <input type="date" name="tenggat" value="{{ old('tenggat') }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                    </label>
                </div>
                <button type="submit" class="mt-3 rounded-lg bg-slate-900 px-3 py-1.5 font-medium text-white hover:bg-slate-800">
                    Tambah action item
                </button>
            </form>
        @endif
    </section>
@endsection
