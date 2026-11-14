<?php

namespace App\Http\Controllers;

use App\Enums\AsalFeedback;
use App\Enums\JenisFeedback;
use App\Enums\KategoriTugas;
use App\Enums\PeranSistem;
use App\Enums\PrioritasTugas;
use App\Enums\StatusFeedback;
use App\Enums\StatusPenugasan;
use App\Enums\SumberPerintah;
use App\Http\Requests\FilterPenugasanRequest;
use App\Http\Requests\SimpanPenugasanRequest;
use App\Models\Assignment;
use App\Models\AuditEvent;
use App\Models\Document;
use App\Models\Feedback;
use App\Models\JobDescription;
use App\Models\Personel;
use App\Models\User;
use App\Support\CakupanPengguna;
use App\Support\IdentitasPelapor;
use App\Support\PengirimNotifikasi;
use App\Support\ServerClock;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Layar penugasan & aksi transisi status (fitur.pdf Tahap 5).
 *
 * Tiga hal tidak pernah ditentukan formulir dan selalu berasal dari sesi atau
 * state machine di model: sumber perintah, pemberi tugas, dan status. Controller
 * hanya memvalidasi data pekerjaan, memanggil Policy, lalu menyerahkan transisi
 * ke `Assignment` agar aturan yang sama berlaku untuk semua jalur penulisan.
 */
class AssignmentController extends Controller
{
    /** Batas baris pada satu halaman daftar. */
    private const PER_HALAMAN = 25;

    /** Jumlah baris jejak audit yang ditampilkan pada detail. */
    private const RIWAYAT = 5;

    public function index(FilterPenugasanRequest $request): View
    {
        $pengguna = $request->user();
        $filter = $request->validated();

        $daftar = Assignment::query()
            ->terlihatOleh($pengguna)
            ->with(['pic', 'pemberiTugas', 'diteruskanOleh'])
            ->when($filter['q'] ?? null, function (Builder $kueri, string $cari): void {
                $pola = '%'.$cari.'%';

                $kueri->where(function (Builder $sub) use ($pola): void {
                    $sub->where('judul', 'like', $pola)->orWhere('kode', 'like', $pola);
                });
            })
            ->when($filter['status'] ?? null, fn (Builder $kueri, $status) => $kueri->where('status', $status))
            ->when($filter['kategori'] ?? null, fn (Builder $kueri, $kategori) => $kueri->where('kategori', $kategori))
            ->when($filter['prioritas'] ?? null, fn (Builder $kueri, $prioritas) => $kueri->where('prioritas', $prioritas))
            ->when($filter['sumber'] ?? null, fn (Builder $kueri, $sumber) => $kueri->where('sumber_perintah', $sumber))
            ->when($filter['pic'] ?? null, fn (Builder $kueri, $pic) => $kueri->where('pic_id', (int) $pic))
            ->when(
                ($filter['terlambat'] ?? null) !== null,
                fn (Builder $kueri) => filter_var($filter['terlambat'], FILTER_VALIDATE_BOOLEAN)
                    ? $kueri->terlambat()
                    : $kueri,
            );

        $kolomUrut = match ($filter['urut'] ?? 'tenggat') {
            'dibuat' => 'created_at',
            'prioritas' => 'prioritas',
            default => 'tenggat',
        };

        $daftar = $daftar
            ->orderBy($kolomUrut, $filter['arah'] ?? 'asc')
            ->orderByDesc('id')
            ->paginate(self::PER_HALAMAN)
            ->withQueryString();

        return view('penugasan.index', [
            'daftar' => $daftar,
            'filter' => $filter,
            'status' => StatusPenugasan::cases(),
            'kategori' => KategoriTugas::cases(),
            'prioritas' => PrioritasTugas::cases(),
            'sumber' => SumberPerintah::cases(),
            'pic' => $this->pilihanPic(),
            'bolehBuat' => $pengguna->can('create', Assignment::class),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Assignment::class);

        $pengguna = $request->user();

        return view('penugasan.form', [
            'tugas' => new Assignment([
                'tanggal_mulai' => ServerClock::now()->toDateString(),
                'tenggat' => ServerClock::now()->addDays(7),
                'kategori' => KategoriTugas::Umum,
                'prioritas' => PrioritasTugas::Sedang,
            ]),
            'sumberPerintah' => $this->sumberPerintahSesi($pengguna),
            'pemberiNama' => IdentitasPelapor::dariSesi($pengguna)?->nama_lengkap,
            'pilihanArahan' => $this->pilihanArahan($pengguna),
            'pic' => $this->pilihanPic(),
            'jobDescription' => $this->pilihanJobDescription(),
            'dependensi' => $this->pilihanDependensi($pengguna),
            'kategori' => KategoriTugas::cases(),
            'prioritas' => PrioritasTugas::cases(),
        ]);
    }

    public function store(SimpanPenugasanRequest $request, PengirimNotifikasi $notifikasi): RedirectResponse
    {
        $this->authorize('create', Assignment::class);

        $data = $request->validated();
        unset($data['alasan']);

        $arahan = $this->arahanUntukDiteruskan($request->user(), $data['sumber_arahan_id'] ?? null);
        unset($data['sumber_arahan_id']);

        $dependensiId = isset($data['dependensi_id']) ? (int) $data['dependensi_id'] : null;

        if ((new Assignment)->dependensiBerputar($dependensiId)) {
            throw ValidationException::withMessages([
                'dependensi_id' => 'Tugas dependensi membentuk lingkaran.',
            ]);
        }

        $tugas = Assignment::buatDenganKodeUnik(function (Assignment $tugas) use ($data, $arahan): void {
            $tugas->fill([
                ...$data,
                'sumber_arahan_id' => $arahan?->getKey(),
                'status' => StatusPenugasan::Aktif,
                'progres' => 0,
            ]);
        });

        if ($arahan !== null) {
            // Arahan Pengurus resmi berubah status begitu DE meneruskannya,
            // sehingga daftar arahan tidak lagi menampilkannya sebagai tunggakan.
            $arahan->update(['status' => StatusFeedback::Ditindaklanjuti]);
            $notifikasi->arahanDiteruskan($tugas);
        }

        $notifikasi->penugasanBaru($tugas);

        return redirect()
            ->route('tugas.show', $tugas)
            ->with('status', 'Penugasan '.$tugas->kode.' dibuat.');
    }

    public function show(Request $request, Assignment $tugas): View
    {
        $this->authorize('view', $tugas);

        $tugas->load([
            'pic.jabatan',
            'pemberiTugas.jabatan',
            'diteruskanOleh.jabatan',
            'jobDescription',
            'dependensi',
            'sumberArahan.pemberi',
            'verifikator',
            'buktiDokumen',
            'actionItems.pic',
        ]);

        $riwayat = AuditEvent::query()
            ->where('auditable_type', $tugas->getMorphClass())
            ->where('auditable_id', $tugas->getKey())
            ->orderByDesc('id')
            ->limit(self::RIWAYAT)
            ->get();

        return view('penugasan.show', [
            'tugas' => $tugas,
            'riwayat' => $riwayat,
            'dokumen' => Document::query()
                ->where('assignment_id', $tugas->getKey())
                ->orderByDesc('id')
                ->get(['id', 'judul']),
        ]);
    }

    public function edit(Request $request, Assignment $tugas): View|RedirectResponse
    {
        $this->authorize('sunting', $tugas);

        if (! in_array($tugas->status, [StatusPenugasan::Draft, StatusPenugasan::Aktif], true)) {
            return redirect()
                ->route('tugas.show', $tugas)
                ->withErrors(['status' => 'Tugas yang sudah berjalan tidak dapat diubah; gunakan aksi progres atau pembatalan.']);
        }

        return view('penugasan.form', [
            'tugas' => $tugas,
            'sumberPerintah' => $tugas->sumber_perintah,
            'pemberiNama' => $tugas->pemberiTugas?->nama_lengkap,
            'pilihanArahan' => collect(),
            'pic' => $this->pilihanPic(),
            'jobDescription' => $this->pilihanJobDescription(),
            'dependensi' => $this->pilihanDependensi($request->user(), $tugas),
            'kategori' => KategoriTugas::cases(),
            'prioritas' => PrioritasTugas::cases(),
        ]);
    }

    public function update(SimpanPenugasanRequest $request, Assignment $tugas): RedirectResponse
    {
        $this->authorize('sunting', $tugas);

        $data = $request->validated();
        $alasan = $data['alasan'] ?? 'Perubahan rincian penugasan';
        // Jejak instruksi terkunci: sumber arahan tidak dapat diganti lewat ubah.
        unset($data['alasan'], $data['sumber_arahan_id']);

        $dependensiId = isset($data['dependensi_id']) ? (int) $data['dependensi_id'] : null;

        if ($tugas->dependensiBerputar($dependensiId)) {
            throw ValidationException::withMessages([
                'dependensi_id' => 'Tugas dependensi membentuk lingkaran.',
            ]);
        }

        $tugas->fill($data);
        $tugas->denganAlasan($alasan)->save();

        return redirect()
            ->route('tugas.show', $tugas)
            ->with('status', 'Penugasan diperbarui.');
    }

    public function progres(Request $request, Assignment $tugas): RedirectResponse
    {
        $this->authorize('progres', $tugas);

        $data = $request->validate([
            'progres' => ['required', 'integer', 'min:0', 'max:100'],
            'kendala' => ['nullable', 'string', 'max:2000'],
        ]);

        $tugas->progres = (int) $data['progres'];
        $tugas->kendala = $data['kendala'] ?? null;

        if ($tugas->status === StatusPenugasan::Aktif && $tugas->progres > 0) {
            $tugas->status = StatusPenugasan::DalamProses;
        }

        if ($tugas->status === StatusPenugasan::DiajukanSelesai && $tugas->progres < 100) {
            // Progres turun setelah diajukan berarti pekerjaan dibuka kembali.
            $tugas->status = StatusPenugasan::DalamProses;
        }

        if ($tugas->status === StatusPenugasan::Terhambat && trim((string) $tugas->kendala) === '') {
            return $this->gagal($tugas, 'Status terhambat wajib menyertakan kendala.');
        }

        try {
            $tugas->denganAlasan('Pelaporan progres oleh PIC')->save();
        } catch (DomainException $e) {
            return $this->gagal($tugas, $e->getMessage());
        }

        return redirect()
            ->route('tugas.show', $tugas)
            ->with('status', 'Progres penugasan diperbarui.');
    }

    public function ajukan(Assignment $tugas): RedirectResponse
    {
        $this->authorize('ajukan', $tugas);

        if ((int) $tugas->progres !== 100) {
            return $this->gagal($tugas, 'Pengajuan selesai hanya sah bila progres sudah 100%.');
        }

        try {
            $tugas->status = StatusPenugasan::DiajukanSelesai;
            $tugas->denganAlasan('Diajukan selesai oleh PIC')->save();
        } catch (DomainException $e) {
            return $this->gagal($tugas, $e->getMessage());
        }

        return redirect()
            ->route('tugas.show', $tugas)
            ->with('status', 'Tugas diajukan untuk verifikasi.');
    }

    public function verifikasi(Request $request, Assignment $tugas, PengirimNotifikasi $notifikasi): RedirectResponse
    {
        $this->authorize('verify', $tugas);

        $alasan = $request->validate([
            'alasan' => ['required', 'string', 'min:10', 'max:500'],
        ])['alasan'];

        try {
            $tugas->status = StatusPenugasan::Terverifikasi;
            $tugas->denganAlasan($alasan)->save();
        } catch (DomainException $e) {
            return $this->gagal($tugas, $e->getMessage());
        }

        $notifikasi->penugasanDiverifikasi($tugas);

        return redirect()
            ->route('tugas.show', $tugas)
            ->with('status', 'Tugas diverifikasi.');
    }

    public function tutup(Request $request, Assignment $tugas): RedirectResponse
    {
        $this->authorize('tutup', $tugas);

        $data = $request->validate([
            'alasan_penutupan' => ['required', 'string', 'min:10', 'max:500'],
            'bukti_penutupan' => ['required', 'string', 'min:5', 'max:500'],
            'bukti_dokumen_id' => ['nullable', 'integer', Rule::exists('documents', 'id')],
        ]);

        try {
            $tugas->status = StatusPenugasan::Ditutup;
            $tugas->alasan_penutupan = $data['alasan_penutupan'];
            $tugas->bukti_penutupan = $data['bukti_penutupan'];
            $tugas->bukti_dokumen_id = $data['bukti_dokumen_id'] ?? null;
            $tugas->denganAlasan($data['alasan_penutupan'])->save();
        } catch (DomainException $e) {
            return $this->gagal($tugas, $e->getMessage());
        }

        return redirect()
            ->route('tugas.show', $tugas)
            ->with('status', 'Tugas ditutup.');
    }

    public function batalkan(Request $request, Assignment $tugas): RedirectResponse
    {
        $this->authorize('batalkan', $tugas);

        $data = $request->validate([
            'alasan_pembatalan' => ['required', 'string', 'min:10', 'max:500'],
            'bukti_pembatalan' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        try {
            $tugas->status = StatusPenugasan::Dibatalkan;
            $tugas->alasan_pembatalan = $data['alasan_pembatalan'];
            $tugas->bukti_pembatalan = $data['bukti_pembatalan'];
            $tugas->denganAlasan($data['alasan_pembatalan'])->save();
        } catch (DomainException $e) {
            return $this->gagal($tugas, $e->getMessage());
        }

        return redirect()
            ->route('tugas.show', $tugas)
            ->with('status', 'Tugas dibatalkan.');
    }

    public function aktifkan(Request $request, Assignment $tugas): RedirectResponse
    {
        $this->authorize('aktifkan', $tugas);

        $alasan = $request->validate([
            'alasan' => ['nullable', 'string', 'min:10', 'max:500'],
        ])['alasan'] ?? 'Aktivasi tugas draf';

        try {
            $tugas->status = StatusPenugasan::Aktif;
            $tugas->denganAlasan($alasan)->save();
        } catch (DomainException $e) {
            return $this->gagal($tugas, $e->getMessage());
        }

        return redirect()
            ->route('tugas.show', $tugas)
            ->with('status', 'Tugas diaktifkan.');
    }

    /**
     * Arahan Pengurus yang ditujukan kepada DE dan belum selesai.
     *
     * Rujukan ini menentukan jalur instruksi, jadi diperiksa di server: DE tidak
     * dapat meneruskan arahan milik orang lain, arahan DE sendiri, atau arahan
     * yang sudah selesai. Pelanggaran menjadi galat validasi 422, bukan diam-diam
     * diabaikan.
     */
    private function arahanUntukDiteruskan(User $pengguna, mixed $id): ?Feedback
    {
        if ($id === null || $id === '') {
            return null;
        }

        $personelId = CakupanPengguna::personelId($pengguna);
        $arahan = Feedback::query()->find($id);

        $sah = $arahan !== null
            && $arahan->asal === AsalFeedback::Pengurus
            && $personelId !== null
            && (int) $arahan->penerima_id === $personelId
            && $arahan->status !== StatusFeedback::Selesai;

        if (! $sah) {
            throw ValidationException::withMessages([
                'sumber_arahan_id' => 'Arahan asal harus arahan Pengurus yang ditujukan kepada Anda dan belum selesai.',
            ]);
        }

        return $arahan;
    }

    private function gagal(Assignment $tugas, string $pesan): RedirectResponse
    {
        return redirect()
            ->route('tugas.show', $tugas)
            ->withErrors(['status' => $pesan]);
    }

    private function sumberPerintahSesi(User $pengguna): SumberPerintah
    {
        return $pengguna->peranSistem() === PeranSistem::Pengurus
            ? SumberPerintah::PengurusLangsung
            : SumberPerintah::DeLangsung;
    }

    /**
     * @return Collection<int, Feedback>
     */
    private function pilihanArahan(User $pengguna): Collection
    {
        $personelId = CakupanPengguna::personelId($pengguna);

        if (! CakupanPengguna::adalahDirekturEksekutif($pengguna) || $personelId === null) {
            return collect();
        }

        return Feedback::query()
            ->where('asal', AsalFeedback::Pengurus->value)
            ->where('penerima_id', $personelId)
            ->whereIn('jenis', [JenisFeedback::Arahan->value, JenisFeedback::Koreksi->value])
            ->where('status', '!=', StatusFeedback::Selesai->value)
            ->orderByDesc('id')
            ->limit(50)
            ->get();
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
     * @return Collection<int, JobDescription>
     */
    private function pilihanJobDescription(): Collection
    {
        return JobDescription::query()
            ->aktif()
            ->orderBy('kode')
            ->get(['id', 'kode', 'uraian']);
    }

    /**
     * @return Collection<int, Assignment>
     */
    private function pilihanDependensi(User $pengguna, ?Assignment $kecuali = null): Collection
    {
        return Assignment::query()
            ->terlihatOleh($pengguna)
            ->when($kecuali?->exists, fn (Builder $kueri) => $kueri->whereKeyNot($kecuali->getKey()))
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'kode', 'judul']);
    }
}
