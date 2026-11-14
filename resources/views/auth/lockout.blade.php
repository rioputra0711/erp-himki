@extends('layouts.tamu')

@section('judul', 'Terlalu Banyak Percobaan — ERP HIMKI')

@section('konten')
    <h2 class="text-lg font-semibold text-slate-900">Percobaan masuk dibatasi</h2>
    <p class="mt-2 text-sm text-slate-600">{{ $pesan }}</p>
    <p class="mt-1 text-sm text-slate-500">
        Coba lagi dalam {{ $detik }} detik. Bila Anda lupa kata sandi, gunakan tautan pemulihan.
    </p>

    <a href="{{ route('login') }}"
       class="mt-5 inline-block rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
        Kembali ke halaman masuk
    </a>
@endsection
