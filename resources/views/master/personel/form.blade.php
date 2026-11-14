@extends('layouts.app')

@section('judul', ($personel->exists ? 'Ubah' : 'Tambah').' Personel — ERP HIMKI')

@section('konten')
    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Master Personel</p>
    <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">
        {{ $personel->exists ? 'Ubah data personel' : 'Tambah personel' }}
    </h1>
    <p class="mt-1 text-sm text-slate-600">
        Nama resmi dan jabatan yang dicatat di sini otomatis dipakai pada setiap laporan akun terkait.
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

    @if ($akun->isEmpty() && ! $personel->exists)
        <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Tidak ada akun aktif yang belum tertaut personel. Buat akun terlebih dahulu di menu Manajemen Akun.
        </div>
    @endif

    <form method="POST"
          action="{{ $personel->exists ? route('master.personel.update', $personel) : route('master.personel.store') }}"
          class="mt-6 space-y-5 rounded-xl border border-slate-200 bg-white p-5">
        @csrf
        @if ($personel->exists)
            @method('PUT')
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="text-sm">
                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Akun pengguna</span>
                <select name="user_id" required class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                    <option value="">Pilih akun</option>
                    @foreach ($akun as $satu)
                        <option value="{{ $satu->id }}" @selected((int) old('user_id', $personel->user_id) === $satu->id)>
                            {{ $satu->name }} ({{ $satu->email }})
                        </option>
                    @endforeach
                </select>
                <span class="mt-1 block text-xs text-slate-500">Satu akun hanya boleh tertaut ke satu personel.</span>
            </label>

            <label class="text-sm">
                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">ID personel / NIP</span>
                <input type="text" name="nip" maxlength="30" value="{{ old('nip', $personel->nip) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
            </label>

            <label class="text-sm sm:col-span-2">
                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Nama resmi</span>
                <input type="text" name="nama_lengkap" required maxlength="150"
                       value="{{ old('nama_lengkap', $personel->nama_lengkap) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
            </label>

            <label class="text-sm">
                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Jabatan</span>
                <select name="jabatan_id" required class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                    <option value="">Pilih jabatan</option>
                    @foreach ($jabatan as $satu)
                        <option value="{{ $satu->id }}" @selected((int) old('jabatan_id', $personel->jabatan_id) === $satu->id)>
                            {{ $satu->nama }} — {{ $satu->kelompok?->label() }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="text-sm">
                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Bidang kerja</span>
                <select name="bidang_id" class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                    <option value="">Tanpa bidang</option>
                    @foreach ($bidang as $satu)
                        <option value="{{ $satu->id }}" @selected((int) old('bidang_id', $personel->bidang_id) === $satu->id)>
                            {{ $satu->nama }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="text-sm">
                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Kategori personel</span>
                <select name="kategori" required class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                    @foreach ($kategori as $satu)
                        <option value="{{ $satu->value }}" @selected(old('kategori', $personel->kategori?->value) === $satu->value)>
                            {{ $satu->label() }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="text-sm">
                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Tanggal masuk</span>
                <input type="date" name="tanggal_masuk"
                       value="{{ old('tanggal_masuk', $personel->tanggal_masuk?->toDateString()) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
            </label>

            <label class="text-sm sm:col-span-2">
                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Telepon</span>
                <input type="text" name="telepon" maxlength="30" value="{{ old('telepon', $personel->telepon) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                <span class="mt-1 block text-xs text-slate-500">
                    Hanya terlihat oleh pemilik akun dan Administrator. Status aktif diubah lewat aksi nonaktifkan.
                </span>
            </label>
        </div>

        <div class="flex items-center gap-2">
            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                Simpan
            </button>
            <a href="{{ $personel->exists ? route('master.personel.show', $personel) : route('master.personel.index') }}"
               class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Batal</a>
        </div>
    </form>
@endsection
