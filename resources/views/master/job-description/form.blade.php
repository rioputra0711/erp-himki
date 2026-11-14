@extends('layouts.app')

@section('judul', ($jd->exists ? 'Ubah' : 'Buat').' Job Description — ERP HIMKI')

@section('konten')
    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Master Job Description</p>
    <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">
        {{ $jd->exists ? 'Ubah draf Job Description' : 'Buat draf Job Description' }}
    </h1>
    <p class="mt-1 text-sm text-slate-600">
        Draf boleh diubah bebas. Setelah ditetapkan Administrator, target dan indikator terkunci sampai versi baru terbit.
    </p>

    @if ($errors->any())
        <div class="mt-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $pesan)
                    <li>{{ $pesan }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST"
          action="{{ $jd->exists ? route('master.jd.update', $jd) : route('master.jd.store') }}"
          class="mt-6 space-y-5 rounded-xl border border-slate-200 bg-white p-5">
        @csrf
        @if ($jd->exists)
            @method('PUT')
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="text-sm">
                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Jabatan</span>
                <select name="jabatan_id" required class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                    <option value="">Pilih jabatan</option>
                    @foreach ($jabatan as $satu)
                        <option value="{{ $satu->id }}" @selected((int) old('jabatan_id', $jd->jabatan_id) === $satu->id)>{{ $satu->nama }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-sm">
                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Kode</span>
                <input type="text" name="kode" required maxlength="30" pattern="[A-Z0-9\-]+"
                       value="{{ old('kode', $jd->kode) }}" class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
            </label>

            <label class="text-sm">
                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Kategori tugas</span>
                <select name="kategori" required class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                    @foreach ($kategori as $k)
                        <option value="{{ $k->value }}" @selected(old('kategori', $jd->kategori?->value) === $k->value)>{{ $k->label() }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-sm">
                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Frekuensi pelaporan</span>
                <select name="frekuensi" class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                    <option value="">Belum ditentukan</option>
                    @foreach ($frekuensi as $f)
                        <option value="{{ $f->value }}" @selected(old('frekuensi', $jd->frekuensi?->value) === $f->value)>{{ $f->label() }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-sm sm:col-span-2">
                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Uraian tugas</span>
                <textarea name="uraian" rows="3" required maxlength="2000"
                          class="w-full rounded-lg border border-slate-300 px-3 py-1.5">{{ old('uraian', $jd->uraian) }}</textarea>
            </label>

            <label class="text-sm">
                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Indikator hasil kerja</span>
                <textarea name="indikator" rows="3" maxlength="2000"
                          class="w-full rounded-lg border border-slate-300 px-3 py-1.5">{{ old('indikator', $jd->indikator) }}</textarea>
            </label>

            <label class="text-sm">
                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Target dasar</span>
                <textarea name="target" rows="3" maxlength="2000"
                          class="w-full rounded-lg border border-slate-300 px-3 py-1.5">{{ old('target', $jd->target) }}</textarea>
            </label>

            <label class="text-sm sm:col-span-2">
                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Berlaku mulai (rencana)</span>
                <input type="date" name="berlaku_mulai"
                       value="{{ old('berlaku_mulai', $jd->berlaku_mulai?->toDateString()) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-1.5 sm:w-64">
                <span class="mt-1 block text-xs text-slate-500">
                    Penetapan resmi mengisi tanggal ini dari jam server; versi baru wajib berlaku setelah versi sebelumnya.
                </span>
            </label>
        </div>

        <div class="flex items-center gap-2">
            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                Simpan draf
            </button>
            <a href="{{ $jd->exists ? route('master.jd.show', $jd) : route('master.jd.index') }}"
               class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Batal</a>
        </div>
    </form>
@endsection
