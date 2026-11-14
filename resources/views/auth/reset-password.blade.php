@extends('layouts.tamu')

@section('judul', 'Atur Ulang Kata Sandi — ERP HIMKI')

@section('konten')
    <h2 class="text-lg font-semibold text-slate-900">Atur ulang kata sandi</h2>
    <p class="mt-1 text-sm text-slate-500">Buat kata sandi baru minimal 12 karakter.</p>

    @if ($errors->any())
        <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
            <ul class="list-disc space-y-1 pl-4">
                @foreach ($errors->all() as $pesan)
                    <li>{{ $pesan }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('password.update') }}" class="mt-5 space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
            <input id="email" name="email" type="email" required autocomplete="email"
                   value="{{ old('email', $request->email) }}"
                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-300">
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-slate-700">Kata Sandi Baru</label>
            <input id="password" name="password" type="password" required autocomplete="new-password"
                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-300">
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-slate-700">Ulangi Kata Sandi Baru</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-300">
        </div>

        <button type="submit"
                class="w-full rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
            Simpan kata sandi baru
        </button>
    </form>
@endsection
