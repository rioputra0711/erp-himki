<?php

namespace App\Http\Controllers;

use App\Http\Requests\SimpanBidangRequest;
use App\Models\Bidang;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Master bidang kerja (fitur.pdf Tahap 3).
 *
 * Daftar resmi: Keuangan, Administrasi, Publikasi, HI/Pameran, TI, Operasional.
 * Bidang tidak dihapus; yang tidak dipakai lagi ditandai `is_active = false`.
 */
class MasterBidangController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Bidang::class);

        return view('master.bidang.index', [
            'daftar' => Bidang::query()
                ->withCount('personel')
                ->orderByDesc('is_active')
                ->orderBy('nama')
                ->get(),
            'bolehUbah' => $request->user()->can('create', Bidang::class),
        ]);
    }

    public function store(SimpanBidangRequest $request): RedirectResponse
    {
        Bidang::query()->create([
            ...$request->validated(),
            'is_active' => true,
        ]);

        return redirect()
            ->route('master.bidang.index')
            ->with('status', 'Bidang kerja ditambahkan.');
    }

    public function update(SimpanBidangRequest $request, Bidang $bidang): RedirectResponse
    {
        $bidang->update($request->validated());

        return redirect()
            ->route('master.bidang.index')
            ->with('status', 'Bidang kerja diperbarui.');
    }

    public function nonaktifkan(Request $request, Bidang $bidang): RedirectResponse
    {
        $this->authorize('deactivate', $bidang);

        if ($bidang->personel()->where('status_aktif', true)->exists()) {
            return back()->withErrors([
                'bidang' => 'Bidang masih dipakai personel aktif. Pindahkan personel terlebih dahulu.',
            ]);
        }

        $bidang->update(['is_active' => false]);

        return redirect()
            ->route('master.bidang.index')
            ->with('status', 'Bidang kerja dinonaktifkan.');
    }
}
