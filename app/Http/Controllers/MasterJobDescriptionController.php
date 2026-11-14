<?php

namespace App\Http\Controllers;

use App\Enums\AksiIzin;
use App\Enums\FrekuensiTugas;
use App\Enums\KategoriTugas;
use App\Enums\ModulAplikasi;
use App\Http\Requests\SimpanJobDescriptionRequest;
use App\Http\Requests\TetapkanJobDescriptionRequest;
use App\Models\Jabatan;
use App\Models\JobDescription;
use App\Support\CakupanPengguna;
use App\Support\ServerClock;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Master Job Description (fitur.pdf Tahap 3).
 *
 * Target dan indikator harus ditetapkan sebelum periode penilaian dimulai.
 * Penetapan mengisi `ditetapkan_pada` dari jam server dan mengunci baris; revisi
 * dilakukan dengan membuat versi baru, bukan mengubah baris yang sedang berlaku.
 */
class MasterJobDescriptionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', JobDescription::class);

        $pengguna = $request->user();
        $personel = CakupanPengguna::personel($pengguna);

        $daftar = JobDescription::query()
            ->with(['jabatan', 'ditetapkanOleh'])
            ->when(
                CakupanPengguna::adalahStaf($pengguna),
                fn ($query) => $query->where('jabatan_id', $personel?->jabatan_id ?? 0)
            )
            ->when($request->integer('jabatan'), fn ($query, $id) => $query->where('jabatan_id', $id))
            ->orderBy('jabatan_id')
            ->orderBy('kode')
            ->orderBy('versi')
            ->get();

        return view('master.job-description.index', [
            'daftar' => $daftar,
            'jabatan' => Jabatan::query()->where('is_active', true)->orderBy('nama')->get(),
            'filterJabatan' => $request->integer('jabatan') ?: null,
            'bolehBuat' => $pengguna->can('create', JobDescription::class),
            'bolehTetapkan' => CakupanPengguna::boleh($pengguna, ModulAplikasi::Personel, AksiIzin::Konfigurasi),
        ]);
    }

    public function show(JobDescription $jobDescription): View
    {
        $this->authorize('view', $jobDescription);

        $jobDescription->load(['jabatan', 'ditetapkanOleh', 'induk', 'versiTurunan']);

        return view('master.job-description.show', [
            'jd' => $jobDescription,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', JobDescription::class);

        return view('master.job-description.form', [
            'jd' => new JobDescription([
                'jabatan_id' => $request->integer('jabatan') ?: null,
                'kategori' => KategoriTugas::Utama,
                'versi' => 1,
                'is_active' => true,
            ]),
            'jabatan' => Jabatan::query()->where('is_active', true)->orderBy('nama')->get(),
            'kategori' => KategoriTugas::cases(),
            'frekuensi' => FrekuensiTugas::cases(),
            'bolehTetapkan' => false,
        ]);
    }

    public function store(SimpanJobDescriptionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['alasan']);

        $jd = JobDescription::query()->create([
            ...$data,
            'versi' => 1,
            'is_active' => true,
        ]);

        return redirect()
            ->route('master.jd.show', $jd)
            ->with('status', 'Draf Job Description dibuat. Tetapkan target dan indikator sebelum periode penilaian.');
    }

    public function edit(JobDescription $jobDescription): View
    {
        $this->authorize('update', $jobDescription);

        return view('master.job-description.form', [
            'jd' => $jobDescription,
            'jabatan' => Jabatan::query()->where('is_active', true)->orderBy('nama')->get(),
            'kategori' => KategoriTugas::cases(),
            'frekuensi' => FrekuensiTugas::cases(),
            'bolehTetapkan' => false,
        ]);
    }

    public function update(SimpanJobDescriptionRequest $request, JobDescription $jobDescription): RedirectResponse
    {
        $data = $request->validated();
        $alasan = $data['alasan'] ?? null;
        unset($data['alasan']);

        $jobDescription->fill($data);

        if ($alasan !== null) {
            $jobDescription->denganAlasan($alasan);
        }

        $jobDescription->save();

        return redirect()
            ->route('master.jd.show', $jobDescription)
            ->with('status', 'Draf Job Description diperbarui.');
    }

    public function tetapkan(TetapkanJobDescriptionRequest $request, JobDescription $jobDescription): RedirectResponse
    {
        if ($jobDescription->sudahDitetapkan()) {
            return back()->withErrors([
                'jd' => 'Job Description ini sudah ditetapkan. Buat versi baru untuk mengubah target.',
            ]);
        }

        $jobDescription->fill([
            'target' => $request->string('target')->toString(),
            'indikator' => $request->string('indikator')->toString(),
            'frekuensi' => $request->string('frekuensi')->toString(),
            'berlaku_mulai' => $request->date('berlaku_mulai')?->toDateString(),
            'ditetapkan_pada' => ServerClock::now(),
            'ditetapkan_oleh_id' => $request->user()->getKey(),
        ]);

        $jobDescription->denganAlasan($request->string('alasan')->toString())->save();

        return redirect()
            ->route('master.jd.show', $jobDescription)
            ->with('status', 'Job Description ditetapkan dan dikunci. Standar evaluasi tidak dapat berubah sampai versi baru diterbitkan.');
    }

    public function arsipkan(Request $request, JobDescription $jobDescription): RedirectResponse
    {
        $this->authorize('configure', $jobDescription);

        $alasan = $request->validate([
            'alasan' => ['required', 'string', 'min:10', 'max:500'],
        ])['alasan'];

        $jobDescription->is_active = false;
        $jobDescription->denganAlasan($alasan)->save();

        return redirect()
            ->route('master.jd.show', $jobDescription)
            ->with('status', 'Versi Job Description diarsipkan. Riwayat tetap tersimpan.');
    }

    public function versiBaru(Request $request, JobDescription $jobDescription): RedirectResponse
    {
        $this->authorize('configure', $jobDescription);

        $data = $request->validate([
            'berlaku_mulai' => ['required', 'date', 'after:'.($jobDescription->berlaku_mulai?->toDateString() ?? 'today')],
            'alasan' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $versiBaru = JobDescription::query()->create([
            'jabatan_id' => $jobDescription->jabatan_id,
            'kode' => $jobDescription->kode,
            'versi' => ((int) $jobDescription->versi) + 1,
            'kategori' => $jobDescription->kategori,
            'uraian' => $jobDescription->uraian,
            'indikator' => $jobDescription->indikator,
            'target' => $jobDescription->target,
            'frekuensi' => $jobDescription->frekuensi,
            'berlaku_mulai' => $data['berlaku_mulai'],
            'ditetapkan_pada' => null,
            'ditetapkan_oleh_id' => null,
            'induk_id' => $jobDescription->getKey(),
            'is_active' => true,
        ]);

        return redirect()
            ->route('master.jd.edit', $versiBaru)
            ->with('status', 'Versi baru dibuat sebagai draf. Sesuaikan target, lalu tetapkan sebelum tanggal berlakunya.');
    }
}
