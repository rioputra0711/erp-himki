@extends('layouts.app')

@section('judul', ($tugas->exists ? 'Ubah' : 'Buat').' Penugasan — ERP HIMKI')

@section('konten')
    <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Manajemen Penugasan</p>
    <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">
        {{ $tugas->exists ? 'Ubah Penugasan' : 'Formulir Penugasan Baru' }}
    </h1>
    <p class="mt-1 text-sm text-slate-600">
        Sumber perintah dan pemberi tugas diisi otomatis dari akun Anda agar riwayat instruksi dapat diaudit.
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

    <div class="mt-6 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 text-sm sm:grid-cols-3">
        <div>
            <span class="block text-xs font-medium uppercase tracking-wide text-slate-500">ID Penugasan</span>
            <span class="font-mono text-slate-900">{{ $tugas->kode ?? 'otomatis' }}</span>
        </div>
        <div>
            <span class="block text-xs font-medium uppercase tracking-wide text-slate-500">Sumber perintah</span>
            <span class="text-slate-900">{{ $sumberPerintah?->label() ?? '—' }}</span>
        </div>
        <div>
            <span class="block text-xs font-medium uppercase tracking-wide text-slate-500">Pemberi tugas</span>
            <span class="text-slate-900">{{ $pemberiNama ?? '—' }}</span>
        </div>
    </div>

    <form method="POST"
          action="{{ $tugas->exists ? route('tugas.update', $tugas) : route('tugas.store') }}"
          class="mt-4 space-y-5 rounded-xl border border-slate-200 bg-white p-5">
        @csrf
        @if ($tugas->exists)
            @method('PUT')
        @endif

        <label class="block text-sm">
            <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Judul tugas</span>
            <input type="text" name="judul" required maxlength="200"
                   value="{{ old('judul', $tugas->judul) }}" class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
        </label>

        <label class="block text-sm">
            <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Uraian tugas</span>
            <textarea name="deskripsi" rows="4" required maxlength="5000"
                      class="w-full rounded-lg border border-slate-300 px-3 py-1.5">{{ old('deskripsi', $tugas->deskripsi) }}</textarea>
        </label>

        <label class="block text-sm">
            <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Target keluaran</span>
            <textarea name="target_keluaran" rows="2" required maxlength="2000"
                      class="w-full rounded-lg border border-slate-300 px-3 py-1.5"
                      placeholder="Hasil kerja yang harus diserahkan, mis. berkas rekap triwulan.">{{ old('target_keluaran', $tugas->target_keluaran) }}</textarea>
        </label>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="text-sm">
                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Penanggung jawab (PIC)</span>
                <select name="pic_id" required class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                    <option value="">Pilih personel aktif</option>
                    @foreach ($pic as $satu)
                        <option value="{{ $satu->id }}" @selected((int) old('pic_id', $tugas->pic_id) === (int) $satu->id)>{{ $satu->nama_lengkap }}</option>
                    @endforeach
                </select>
                <span class="mt-1 block text-xs text-slate-500">Hanya personel berstatus aktif.</span>
            </label>

            <label class="text-sm">
                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Kategori</span>
                <select name="kategori" required class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                    @foreach ($kategori as $satu)
                        <option value="{{ $satu->value }}" @selected(old('kategori', $tugas->kategori?->value) === $satu->value)>{{ $satu->label() }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-sm">
                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Prioritas</span>
                <select name="prioritas" required class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                    @foreach ($prioritas as $satu)
                        <option value="{{ $satu->value }}" @selected(old('prioritas', $tugas->prioritas?->value) === $satu->value)>{{ $satu->label() }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-sm">
                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Job Description</span>
                <select name="job_description_id" class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                    <option value="">Tanpa rujukan JD</option>
                    @foreach ($jobDescription as $satu)
                        <option value="{{ $satu->id }}" @selected((int) old('job_description_id', $tugas->job_description_id) === (int) $satu->id)>{{ $satu->kode }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-sm">
                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Tanggal mulai</span>
                <input type="date" name="tanggal_mulai" required
                       value="{{ old('tanggal_mulai', $tugas->tanggal_mulai?->toDateString()) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
            </label>

            <label class="text-sm">
                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Tenggat</span>
                <input type="datetime-local" name="tenggat" required
                       value="{{ old('tenggat', $tugas->tenggat?->format('Y-m-d\TH:i')) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
            </label>

            <label class="text-sm">
                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Tugas dependensi</span>
                <select name="dependensi_id" class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                    <option value="">Tidak ada</option>
                    @foreach ($dependensi as $satu)
                        <option value="{{ $satu->id }}" @selected((int) old('dependensi_id', $tugas->dependensi_id) === (int) $satu->id)>
                            {{ $satu->kode }} — {{ \Illuminate\Support\Str::limit($satu->judul, 40) }}
                        </option>
                    @endforeach
                </select>
            </label>

            @if ($pilihanArahan->isNotEmpty())
                <label class="text-sm">
                    <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Teruskan arahan Pengurus</span>
                    <select name="sumber_arahan_id" class="w-full rounded-lg border border-slate-300 px-3 py-1.5">
                        <option value="">Bukan tindak lanjut arahan</option>
                        @foreach ($pilihanArahan as $satu)
                            <option value="{{ $satu->id }}" @selected((int) old('sumber_arahan_id') === (int) $satu->id)>
                                #{{ $satu->id }} — {{ \Illuminate\Support\Str::limit($satu->isi, 50) }}
                            </option>
                        @endforeach
                    </select>
                    <span class="mt-1 block text-xs text-slate-500">
                        Bila dipilih, pemberi tugas menjadi Pengurus pemberi arahan dan status arahan berubah menjadi ditindaklanjuti.
                    </span>
                </label>
            @endif
        </div>

        @if ($tugas->exists)
            <label class="block text-sm">
                <span class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Alasan perubahan</span>
                <input type="text" name="alasan" maxlength="500" value="{{ old('alasan') }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-1.5"
                       placeholder="Dicatat permanen pada jejak audit.">
            </label>
        @endif

        <div class="flex items-center gap-2">
            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                {{ $tugas->exists ? 'Simpan perubahan' : 'Buat penugasan' }}
            </button>
            <a href="{{ $tugas->exists ? route('tugas.show', $tugas) : route('tugas.index') }}"
               class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Batal</a>
        </div>
    </form>
@endsection
