@extends('layouts.tamu')

@section('judul', 'Lupa Kata Sandi — ERP HIMKI')

@section('konten')
    <h2 class="text-lg font-semibold text-slate-900">Pemulihan kata sandi</h2>
    <p class="mt-1 text-sm text-slate-500">
        Masukkan email akun Anda. Tautan pengaturan ulang berlaku 60 menit.
    </p>

    @if ($errors->any())
        <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
            {{ $errors->first() }}
        </div>
    @endif

    @if (session('status'))
        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-5 space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
            <input id="email" name="email" type="email" required autofocus autocomplete="email"
                   value="{{ old('email') }}"
                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-300">
        </div>

        <button type="submit"
                class="w-full rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
            Kirim tautan pemulihan
        </button>
    </form>

    <p class="mt-4 text-center text-sm">
        <a href="{{ route('login') }}" class="text-slate-600 underline hover:text-slate-900">Kembali ke halaman masuk</a>
    </p>
@endsection
