@extends('layouts.tamu')

@section('judul', 'Masuk — ERP HIMKI')

@section('konten')
    <h2 class="text-lg font-semibold text-slate-900">Masuk ke akun Anda</h2>
    <p class="mt-1 text-sm text-slate-500">Gunakan email atau username yang terdaftar.</p>

    @if ($errors->any())
        <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
            <ul class="list-disc space-y-1 pl-4">
                @foreach ($errors->all() as $pesan)
                    <li>{{ $pesan }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('status'))
        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="mt-5 space-y-4">
        @csrf

        <div>
            <label for="login" class="block text-sm font-medium text-slate-700">Email atau Username</label>
            <input id="login" name="login" type="text" required autofocus autocomplete="username"
                   value="{{ old('login') }}"
                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-300">
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-slate-700">Kata Sandi</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-300">
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" value="1" class="rounded border-slate-300">
            Ingat perangkat ini
        </label>

        <button type="submit"
                class="w-full rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
            Masuk
        </button>
    </form>

    <p class="mt-4 text-center text-sm">
        <a href="{{ route('password.request') }}" class="text-slate-600 underline hover:text-slate-900">
            Lupa kata sandi?
        </a>
    </p>
@endsection
