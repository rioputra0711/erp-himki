{{--
    Identitas pelapor terkunci (fitur.pdf Tahap 3).

    Nama dan jabatan tidak pernah berupa input: nilainya berasal dari Master
    Personel yang tertaut ke akun sesi. Formulir hanya mengirim `personel_id`
    sebagai penanda; server tetap memverifikasi ulang lewat trait MilikSesiPelapor.
--}}
@props(['identitas' => null])

<div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
        Identitas pelapor (terkunci dari akun aktif)
    </p>

    @if ($identitas === null)
        <p class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            Akun ini belum tertaut ke Master Personel. Hubungi Administrator Aplikasi sebelum mengirim laporan.
        </p>
    @else
        <input type="hidden" name="personel_id" value="{{ $identitas['personel_id'] }}">

        <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">Nama</dt>
                <dd class="font-semibold text-slate-900">{{ $identitas['nama'] }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">Jabatan</dt>
                <dd class="font-semibold text-slate-900">{{ $identitas['jabatan'] ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">Bidang</dt>
                <dd class="font-semibold text-slate-900">{{ $identitas['bidang'] ?? '—' }}</dd>
            </div>
        </dl>

        <p class="mt-2 text-xs text-slate-500">
            Kolom ini tidak dapat diketik ulang. Perubahan identitas resmi hanya melalui Master Personel.
        </p>
    @endif
</div>
