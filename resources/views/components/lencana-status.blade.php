@props(['status'])

@php
    use App\Enums\StatusPenugasan;

    $warna = match ($status) {
        StatusPenugasan::Draft => 'bg-slate-100 text-slate-600',
        StatusPenugasan::Aktif => 'bg-sky-50 text-sky-700',
        StatusPenugasan::DalamProses => 'bg-indigo-50 text-indigo-700',
        StatusPenugasan::Terhambat => 'bg-amber-50 text-amber-700',
        StatusPenugasan::DiajukanSelesai => 'bg-violet-50 text-violet-700',
        StatusPenugasan::Terverifikasi => 'bg-emerald-50 text-emerald-700',
        StatusPenugasan::Ditutup => 'bg-emerald-100 text-emerald-800',
        StatusPenugasan::Dibatalkan => 'bg-rose-50 text-rose-700',
        default => 'bg-slate-100 text-slate-600',
    };
@endphp

<span {{ $attributes->merge(['class' => 'rounded-full px-2 py-0.5 text-xs font-medium '.$warna]) }}>
    {{ $status?->label() ?? '—' }}
</span>
