<?php

namespace App\Http\Controllers;

use App\Enums\AsalFeedback;
use App\Enums\JenisFeedback;
use App\Enums\StatusActionItem;
use App\Enums\StatusFeedback;
use App\Http\Requests\FilterArahanRequest;
use App\Http\Requests\SimpanArahanRequest;
use App\Models\ActionItem;
use App\Models\Document;
use App\Models\Feedback;
use App\Models\Personel;
use App\Support\CakupanPengguna;
use App\Support\PengirimNotifikasi;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Modul arahan & action item (fitur.pdf Tahap 5).
 *
 * Dua hal yang dijaga di sini:
 *
 * 1. Identitas pemberi arahan tidak berasal dari formulir; model mengisinya
 *    dari sesi (`Feedback::kunciIdentitasPemberi`).
 * 2. Persetujuan formal selalu baris baru dari Pengurus. Komentar biasa tidak
 *    dapat dinaikkan menjadi persetujuan, dan persetujuan tidak mengubah status
 *    objek yang disetujui.
 */
class ArahanController extends Controller
{
    /** Batas baris pada satu halaman daftar. */
    private const PER_HALAMAN = 25;

    public function index(FilterArahanRequest $request): View
    {
        $pengguna = $request->user();
        $filter = $request->validated();

        $daftar = Feedback::query()
            ->terlihatOleh($pengguna)
            ->with(['pemberi.jabatan', 'penerima', 'actionItems'])
            ->when($filter['q'] ?? null, function (Builder $kueri, string $cari): void {
                $kueri->where('isi', 'like', '%'.$cari.'%');
            })
            ->when($filter['jenis'] ?? null, fn (Builder $kueri, $jenis) => $kueri->where('jenis', $jenis))
            ->when($filter['asal'] ?? null, fn (Builder $kueri, $asal) => $kueri->where('asal', $asal))
            ->when($filter['status'] ?? null, fn (Builder $kueri, $status) => $kueri->where('status', $status))
            ->when(($filter['tindakan'] ?? null) === 'butuh', fn (Builder $kueri) => $kueri->where('butuh_tindakan', true))
            ->when(($filter['tindakan'] ?? null) === 'selesai', fn (Builder $kueri) => $kueri->where('status', StatusFeedback::Selesai->value));

        $kolomUrut = ($filter['urut'] ?? 'dibuat') === 'tenggat' ? 'tenggat' : 'created_at';
        $daftar = $daftar
            ->orderBy($kolomUrut, $filter['arah'] ?? 'desc')
            ->orderByDesc('id')
            ->paginate(self::PER_HALAMAN)
            ->withQueryString();

        return view('arahan.index', [
            'daftar' => $daftar,
            'filter' => $filter,
            'jenis' => JenisFeedback::cases(),
            'asal' => AsalFeedback::cases(),
            'status' => StatusFeedback::cases(),
            'bolehBuat' => $pengguna->can('create', Feedback::class),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Feedback::class);

        return view('arahan.form', [
            'penerima' => $this->pilihanPenerima($request),
            'dokumen' => $this->pilihanDokumen(),
        ]);
    }

    public function store(SimpanArahanRequest $request, PengirimNotifikasi $notifikasi): RedirectResponse
    {
        $this->authorize('create', Feedback::class);

        $data = $request->validated();
        unset($data['alasan'], $data['subjek_type'], $data['subjek_id']);

        // Identitas pemberi, jabatan, dan asal diisi model dari sesi.
        $arahan = Feedback::query()->create([
            ...$data,
            'butuh_tindakan' => (bool) ($data['butuh_tindakan'] ?? false),
            'status' => StatusFeedback::Terbuka,
        ]);

        $notifikasi->arahanBaru($arahan);

        return redirect()
            ->route('arahan.show', $arahan)
            ->with('status', 'Arahan tercatat.');
    }

    public function show(Feedback $arahan): View
    {
        $this->authorize('view', $arahan);

        $arahan->load([
            'pemberi.jabatan',
            'penerima.jabatan',
            'lampiran',
            'actionItems.pic',
            'subjek',
        ]);

        return view('arahan.show', [
            'arahan' => $arahan,
            'bolehTindakLanjut' => auth()->user()?->can('create', ActionItem::class) ?? false,
            'pilihanPic' => $this->pilihanPic(),
        ]);
    }

    /**
     * Persetujuan formal: baris baru berjenis `persetujuan` dari Pengurus.
     *
     * Status arahan asal sengaja tidak diubah — persetujuan adalah keputusan
     * tersendiri, bukan perubahan status pekerjaan.
     */
    public function setujui(Request $request, Feedback $arahan): RedirectResponse
    {
        $this->authorize('setujui', $arahan);

        $data = $request->validate([
            'isi' => ['required', 'string', 'max:2000'],
        ]);

        try {
            Feedback::query()->create([
                'pemberi_id' => CakupanPengguna::personelId($request->user()),
                'penerima_id' => $arahan->pemberi_id,
                'jenis' => JenisFeedback::Persetujuan,
                'asal' => $arahan->asal,
                'subjek_type' => $arahan->subjek_type,
                'subjek_id' => $arahan->subjek_id,
                'isi' => $data['isi'],
                'butuh_tindakan' => false,
                'status' => StatusFeedback::Selesai,
            ]);
        } catch (DomainException $e) {
            return redirect()
                ->route('arahan.show', $arahan)
                ->withErrors(['isi' => $e->getMessage()]);
        }

        return redirect()
            ->route('arahan.show', $arahan)
            ->with('status', 'Persetujuan formal tercatat.');
    }

    public function tindakLanjut(Request $request, Feedback $arahan, PengirimNotifikasi $notifikasi): RedirectResponse
    {
        $this->authorize('create', ActionItem::class);
        $this->authorize('view', $arahan);

        $data = $request->validate([
            'deskripsi' => ['required', 'string', 'max:2000'],
            'pic_id' => [
                'required',
                'integer',
                Rule::exists('personel', 'id')->where('status_aktif', true),
            ],
            'tenggat' => ['nullable', 'date'],
        ]);

        $item = ActionItem::query()->create([
            'feedback_id' => $arahan->getKey(),
            'deskripsi' => $data['deskripsi'],
            'pic_id' => (int) $data['pic_id'],
            'tenggat' => $data['tenggat'] ?? null,
            'status' => StatusActionItem::Terbuka,
        ]);

        if ($arahan->status === StatusFeedback::Terbuka) {
            $arahan->update(['status' => StatusFeedback::Ditindaklanjuti]);
        }

        $notifikasi->tindakLanjutBaru($item);

        return redirect()
            ->route('arahan.show', $arahan)
            ->with('status', 'Action item ditambahkan.');
    }

    /**
     * @return Collection<int, Personel>
     */
    private function pilihanPenerima(Request $request): Collection
    {
        $pengguna = $request->user();

        return Personel::query()
            ->where('status_aktif', true)
            // DE memberi arahan kepada Pengurus/DE lain; Pengurus ke staf.
            ->when(
                CakupanPengguna::adalahDirekturEksekutif($pengguna),
                fn (Builder $kueri) => $kueri->whereKeyNot(CakupanPengguna::personelId($pengguna) ?? 0),
            )
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap']);
    }

    /**
     * @return Collection<int, Personel>
     */
    private function pilihanPic(): Collection
    {
        return Personel::query()
            ->where('status_aktif', true)
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap']);
    }

    /**
     * @return Collection<int, Document>
     */
    private function pilihanDokumen(): Collection
    {
        return Document::query()
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'judul']);
    }
}
