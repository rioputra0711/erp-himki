<?php

namespace App\Http\Controllers;

use App\Enums\KategoriPersonel;
use App\Http\Requests\SimpanPersonelRequest;
use App\Models\Bidang;
use App\Models\Jabatan;
use App\Models\Personel;
use App\Models\User;
use App\Support\CakupanPengguna;
use App\Support\IdentitasPelapor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Master Personel & Jabatan (fitur.pdf Tahap 3).
 *
 * Cakupan baris ditegakkan dua lapis: kueri daftar memakai `scopeTerlihatOleh`
 * dan setiap aksi baris memakai PersonelPolicy. Staf hanya melihat identitasnya
 * sendiri; Administrator mengelola seluruh master; Pengurus dan DE membaca
 * lintas personel untuk keperluan pemantauan tanpa hak ubah.
 */
class MasterPersonelController extends Controller
{
    public function index(Request $request): View
    {
        $pengguna = $request->user();

        $filter = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'bidang' => ['nullable', 'integer'],
            'kategori' => ['nullable', 'string'],
            'status' => ['nullable', 'in:aktif,nonaktif'],
            'urut' => ['nullable', 'in:nama,jabatan,bidang,tanggal_masuk'],
            'arah' => ['nullable', 'in:asc,desc'],
        ]);

        $urut = $filter['urut'] ?? 'nama';
        $arah = $filter['arah'] ?? 'asc';

        // Kolom pengurutan dipetakan dari nama pendek ke kolom/relasi nyata,
        // sehingga nilai `urut` tidak pernah menjadi SQL mentah.
        $pengurutan = match ($urut) {
            'jabatan' => Jabatan::query()->select('nama')
                ->whereColumn('jabatans.id', 'personel.jabatan_id'),
            'bidang' => Bidang::query()->select('nama')
                ->whereColumn('bidangs.id', 'personel.bidang_id'),
            'tanggal_masuk' => 'tanggal_masuk',
            default => 'nama_lengkap',
        };

        $daftar = Personel::query()
            ->terlihatOleh($pengguna)
            ->with(['jabatan', 'bidang', 'user'])
            ->when($filter['q'] ?? null, function ($query, string $q): void {
                $query->where(function ($sub) use ($q): void {
                    $sub->where('nama_lengkap', 'like', '%'.$q.'%')
                        ->orWhere('nip', 'like', '%'.$q.'%');
                });
            })
            ->when($filter['bidang'] ?? null, fn ($query, $id) => $query->where('bidang_id', $id))
            ->when($filter['kategori'] ?? null, fn ($query, $k) => $query->where('kategori', $k))
            ->when($filter['status'] ?? null, fn ($query, $s) => $query->where('status_aktif', $s === 'aktif'))
            ->orderBy($pengurutan, $arah)
            ->paginate(20)
            ->withQueryString();

        return view('master.personel.index', [
            'daftar' => $daftar,
            'filter' => $filter,
            'bidang' => Bidang::query()->orderBy('nama')->get(),
            'kategori' => KategoriPersonel::cases(),
            'bolehKelola' => $pengguna->can('create', Personel::class),
            'identitasSesi' => IdentitasPelapor::ringkas($pengguna),
        ]);
    }

    public function show(Request $request, Personel $personel): View
    {
        $this->authorize('view', $personel);

        $personel->load(['jabatan', 'bidang', 'user']);

        return view('master.personel.show', [
            'personel' => $personel,
            'jobDescription' => $personel->jabatan
                ? $personel->jabatan->jobDescriptions()->aktif()->orderBy('kode')->get()
                : collect(),
            'adalahSesi' => (int) CakupanPengguna::personelId($request->user()) === (int) $personel->getKey(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Personel::class);

        return view('master.personel.form', [
            'personel' => new Personel(['status_aktif' => true, 'kategori' => KategoriPersonel::Staf]),
            'akun' => $this->akunTanpaPersonel(),
            'jabatan' => Jabatan::query()->where('is_active', true)->orderBy('nama')->get(),
            'bidang' => Bidang::query()->where('is_active', true)->orderBy('nama')->get(),
            'kategori' => KategoriPersonel::cases(),
        ]);
    }

    public function store(SimpanPersonelRequest $request): RedirectResponse
    {
        $personel = Personel::query()->create([
            ...$request->validated(),
            'status_aktif' => true,
        ]);

        return redirect()
            ->route('master.personel.show', $personel)
            ->with('status', 'Personel berhasil ditambahkan ke Master Personel.');
    }

    public function edit(Personel $personel): View
    {
        $this->authorize('update', $personel);

        return view('master.personel.form', [
            'personel' => $personel,
            'akun' => $this->akunTanpaPersonel($personel),
            'jabatan' => Jabatan::query()->where('is_active', true)->orderBy('nama')->get(),
            'bidang' => Bidang::query()->where('is_active', true)->orderBy('nama')->get(),
            'kategori' => KategoriPersonel::cases(),
        ]);
    }

    public function update(SimpanPersonelRequest $request, Personel $personel): RedirectResponse
    {
        $personel->fill($request->validated());
        $personel->denganAlasan('Perubahan master personel')->save();

        return redirect()
            ->route('master.personel.show', $personel)
            ->with('status', 'Data personel diperbarui.');
    }

    public function nonaktifkan(Request $request, Personel $personel): RedirectResponse
    {
        $this->authorize('deactivate', $personel);

        $alasan = $request->validate([
            'alasan' => ['required', 'string', 'min:10', 'max:500'],
        ])['alasan'];

        $personel->status_aktif = false;
        $personel->denganAlasan($alasan)->save();

        return redirect()
            ->route('master.personel.show', $personel)
            ->with('status', 'Personel dinonaktifkan. Riwayat tetap tersimpan.');
    }

    /**
     * Akun aktif yang belum tertaut ke personel mana pun (atau milik baris ini).
     *
     * @return Collection<int, User>
     */
    private function akunTanpaPersonel(?Personel $personel = null)
    {
        return User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($personel): void {
                $query->whereDoesntHave('personel');

                if ($personel !== null) {
                    $query->orWhereKey($personel->user_id);
                }
            })
            ->orderBy('name')
            ->get();
    }
}
