@extends('layouts.app')

@section('judul', 'Arahan Baru — ERP HIMKI')

@section('konten')
    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Arahan &amp; Umpan Balik</p>
    <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">Arahan Baru</h1>
    <p class="mt-1 text-sm text-slate-600">
        Nama, jabatan, dan asal arahan diambil otomatis dari akun Anda dan tidak dapat diubah dari formulir.
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

    <form method="POST" action="{{ route('arahan.store') }}"
          class="mt-6 space-y-5 rounded-xl border border-slate-200 bg-white p-5">
        @csrf

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="text-sm">
                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Penerima</span>
                <select name="penerima_id" required class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                    <option value="">Pilih personel aktif</option>
                    @foreach ($penerima as $satu)
                        <option value="{{ $satu->id }}" @selected((int) old('penerima_id') === (int) $satu->id)>{{ $satu->nama_lengkap }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-sm">
                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Jenis</span>
                <select name="jenis" required class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                    <option value="arahan" @selected(old('jenis', 'arahan') === 'arahan')>Arahan</option>
                    <option value="koreksi" @selected(old('jenis') === 'koreksi')>Koreksi</option>
                    <option value="masukan" @selected(old('jenis') === 'masukan')>Masukan</option>
                    <option value="komentar" @selected(old('jenis') === 'komentar')>Komentar</option>
                </select>
                <span class="mt-1 block text-xs text-slate-500">
                    Komentar biasa bukan persetujuan formal. Persetujuan dibuat lewat tombol di detail arahan.
                </span>
            </label>
        </div>

        <label class="block text-sm">
            <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Isi arahan</span>
            <textarea name="isi" rows="5" required maxlength="5000"
                      class="w-full rounded-lg border border-slate-300 px-3 py-1.5">{{ old('isi') }}</textarea>
        </label>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" name="butuh_tindakan" value="1" @checked(old('butuh_tindakan'))
                       class="mt-1 rounded border-slate-300">
                <span>
                    <span class="block text-slate-800">Butuh tindakan</span>
                    <span class="block text-xs text-slate-500">Wajib punya penerima; item yang belum tuntas ditahan sebagai tunggakan.</span>
                </span>
            </label>

            <label class="text-sm">
                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Tenggat</span>
                <input type="date" name="tenggat" value="{{ old('tenggat') }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
            </label>

            <label class="text-sm sm:col-span-2">
                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Lampiran (opsional)</span>
                <select name="lampiran_dokumen_id" class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                    <option value="">Tanpa lampiran</option>
                    @foreach ($dokumen as $satu)
                        <option value="{{ $satu->id }}" @selected((int) old('lampiran_dokumen_id') === (int) $satu->id)>{{ $satu->judul }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="flex items-center gap-2">
            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                Simpan arahan
            </button>
            <a href="{{ route('arahan.index') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Batal</a>
        </div>
    </form>
@endsection
