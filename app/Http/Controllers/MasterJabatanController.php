<?php

namespace App\Http\Controllers;

use App\Enums\KategoriPersonel;
use App\Http\Requests\SimpanJabatanRequest;
use App\Models\Jabatan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Master jabatan (fitur.pdf Tahap 3).
 *
 * Nomenklatur jabatan dipakai bersama oleh Master Personel dan Job Description,
 * jadi hanya pemegang izin ubah Master Personel yang boleh menambah/mengubah.
 * Jabatan tidak pernah dihapus — dinonaktifkan agar riwayat personel tetap utuh.
 */
class MasterJabatanController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Jabatan::class);

        return view('master.jabatan.index', [
            'daftar' => Jabatan::query()
                ->withCount('personel')
                ->orderBy('kelompok')
                ->orderBy('nama')
                ->get(),
            'kategori' => KategoriPersonel::cases(),
            'bolehUbah' => $request->user()->can('create', Jabatan::class),
        ]);
    }

    public function store(SimpanJabatanRequest $request): RedirectResponse
    {
        Jabatan::query()->create([
            ...$request->validated(),
            'is_active' => true,
        ]);

        return redirect()
            ->route('master.jabatan.index')
            ->with('status', 'Jabatan ditambahkan.');
    }

    public function update(SimpanJabatanRequest $request, Jabatan $jabatan): RedirectResponse
    {
        $jabatan->update($request->validated());

        return redirect()
            ->route('master.jabatan.index')
            ->with('status', 'Jabatan diperbarui.');
    }

    public function nonaktifkan(Request $request, Jabatan $jabatan): RedirectResponse
    {
        $this->authorize('deactivate', $jabatan);

        if ($jabatan->personel()->where('status_aktif', true)->exists()) {
            return back()->withErrors([
                'jabatan' => 'Jabatan masih dipakai personel aktif. Pindahkan personel terlebih dahulu.',
            ]);
        }

        $jabatan->update(['is_active' => false]);

        return redirect()
            ->route('master.jabatan.index')
            ->with('status', 'Jabatan dinonaktifkan.');
    }
}
