<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterAuditRequest;
use App\Models\AuditEvent;
use App\Models\JobDescription;
use App\Models\Personel;
use App\Models\User;
use App\Support\CakupanPengguna;
use App\Support\LabelAudit;
use App\Support\PencatatAudit;
use App\Support\ServerClock;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Layar penelusuran jejak audit (fitur.pdf Tahap 4).
 *
 * Hanya membaca. Tidak ada rute, tombol, maupun kemampuan Policy untuk
 * mengubah atau menghapus baris audit — tabelnya append-only sejak Tahap 1.
 */
class AuditController extends Controller
{
    /** Batas baris pada satu halaman daftar. */
    private const PER_HALAMAN = 50;

    /** Batas baris ekspor agar berkas tetap wajar. */
    private const BATAS_EKSPOR = 5_000;

    public function index(FilterAuditRequest $request): View
    {
        $pengguna = $request->user();

        $events = $this->kueri($request, $pengguna)
            ->paginate(self::PER_HALAMAN)
            ->withQueryString();

        return view('audit.index', [
            'events' => $events,
            'labelObjek' => $this->labelObjek($events->getCollection()),
            'filter' => $request->validated(),
            'pilihanTindakan' => LabelAudit::pilihanTindakan(),
            'pilihanObjek' => [...array_keys(LabelAudit::KELAS), 'akses'],
            'aktor' => $this->pilihanAktor(),
            'bolehEkspor' => $pengguna->can('export', AuditEvent::class),
        ]);
    }

    public function show(Request $request, AuditEvent $auditEvent): View
    {
        $this->authorize('view', $auditEvent);

        $pengguna = $request->user();
        $sembunyikan = ! CakupanPengguna::adalahAdmin($pengguna);

        return view('audit.show', [
            'event' => $auditEvent,
            'perubahan' => $this->susunPerubahan($auditEvent, $sembunyikan),
            'labelObjek' => $this->labelObjek(new Collection([$auditEvent]))[$auditEvent->getKey()] ?? null,
            'tautanObjek' => $this->tautanObjek($auditEvent),
            'disamarkan' => $sembunyikan,
        ]);
    }

    public function ekspor(FilterAuditRequest $request): StreamedResponse
    {
        $this->authorize('export', AuditEvent::class);

        $pengguna = $request->user();
        $filter = $request->validated();

        PencatatAudit::catat(
            event: 'ekspor.audit',
            request: $request,
            actor: $pengguna,
            after: [
                'filter' => $filter,
                'batas_baris' => self::BATAS_EKSPOR,
                'rute' => $request->path(),
            ],
        );

        $kueri = $this->kueri($request, $pengguna)->limit(self::BATAS_EKSPOR);
        $namaBerkas = sprintf('audit-trail-%s.csv', ServerClock::now()->format('Ymd-His'));

        return response()->streamDownload(function () use ($kueri, $filter): void {
            $keluaran = fopen('php://output', 'wb');

            fputcsv($keluaran, ['laporan', 'Jejak Audit ERP HIMKI']);
            fputcsv($keluaran, ['dibuat_pada', ServerClock::now()->toIso8601String()]);
            fputcsv($keluaran, ['sumber_data', 'erp_himki.audit_events']);
            fputcsv($keluaran, ['filter', $this->uraianFilter($filter)]);
            fputcsv($keluaran, ['batas_baris', (string) self::BATAS_EKSPOR]);
            fputcsv($keluaran, []);
            fputcsv($keluaran, ['waktu_wib', 'pelaku', 'peran', 'tindakan', 'objek', 'objek_ringkas', 'objek_id', 'alasan', 'ip']);

            $kueri->chunk(500, function (Collection $baris) use ($keluaran): void {
                $ringkas = $this->labelObjek($baris);

                foreach ($baris as $event) {
                    fputcsv($keluaran, [
                        $event->created_at?->timezone(ServerClock::timezone())->format('Y-m-d H:i:s'),
                        $event->actor_nama ?? ($event->actor_id === null ? '' : '#'.$event->actor_id),
                        $event->actor_peran ?? '',
                        LabelAudit::tindakan($event->event),
                        LabelAudit::objek($event->auditable_type),
                        $ringkas[$event->getKey()] ?? '',
                        $event->auditable_id ?? '',
                        $event->reason ?? '',
                        $event->ip_address ?? '',
                    ]);
                }
            });

            fclose($keluaran);
        }, $namaBerkas, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * Kueri dasar yang sama dipakai daftar dan ekspor, sehingga isi berkas
     * selalu sepadan dengan yang tampak di layar.
     */
    private function kueri(FilterAuditRequest $request, User $pengguna): Builder
    {
        $filter = $request->validated();

        $kueri = AuditEvent::query()
            ->terlihatOleh($pengguna)
            ->with('actor');

        if (($filter['tindakan'] ?? null) !== null) {
            $kueri->where('event', $filter['tindakan']);
        }

        if (($filter['objek'] ?? null) !== null) {
            $this->saringObjek($kueri, $filter['objek']);
        }

        if (($filter['aktor'] ?? null) !== null) {
            $kueri->where('actor_id', (int) $filter['aktor']);
        }

        if (($filter['dari'] ?? null) !== null) {
            $kueri->where('created_at', '>=', $filter['dari'].' 00:00:00');
        }

        if (($filter['sampai'] ?? null) !== null) {
            $kueri->where('created_at', '<=', $filter['sampai'].' 23:59:59');
        }

        if (($filter['q'] ?? null) !== null) {
            $cari = '%'.$filter['q'].'%';

            $kueri->where(function (Builder $sub) use ($cari): void {
                $sub->where('reason', 'like', $cari)
                    ->orWhere('actor_nama', 'like', $cari)
                    ->orWhere('auditable_type', 'like', $cari);
            });
        }

        $urut = $filter['urut'] ?? 'waktu';
        $arah = $filter['arah'] ?? 'desc';

        if ($urut === 'tindakan') {
            $kueri->orderBy('event', $arah)->orderByDesc('id');
        } else {
            $kueri->orderBy('created_at', $arah)->orderBy('id', $arah);
        }

        return $kueri;
    }

    private function saringObjek(Builder $kueri, string $objek): void
    {
        if ($objek === 'akses') {
            $kueri->where('event', 'like', 'akses.%');

            return;
        }

        $kelas = LabelAudit::KELAS[$objek] ?? null;

        if ($kelas === null) {
            return;
        }

        $kueri->where('auditable_type', $kelas);

        // Perubahan peran/izin juga menunjuk ke model User; keduanya dipisah
        // agar `objek=user` tidak memuat baris `akses.*`.
        if ($objek === 'user') {
            $kueri->where('event', 'not like', 'akses.%');
        }
    }

    /**
     * Daftar perubahan yang ditampilkan pada halaman detail.
     *
     * @return list<array{kolom: string, sebelum: string, sesudah: string}>
     */
    private function susunPerubahan(AuditEvent $event, bool $samarkan): array
    {
        $before = $event->before ?? [];
        $after = $event->after ?? [];

        if ($samarkan) {
            $before = LabelAudit::samarkan($before);
            $after = LabelAudit::samarkan($after);
        }

        $kelas = $event->auditable_type;
        $kolom = match ($event->event) {
            'created' => array_keys($after),
            'deleted' => array_keys($before),
            default => array_values(array_intersect(array_keys($before), array_keys($after))),
        };

        $baris = [];

        foreach ($kolom as $nama) {
            $sebelum = $before[$nama] ?? null;
            $sesudah = $after[$nama] ?? null;

            if ($event->event === 'updated' && $sebelum == $sesudah) {
                continue;
            }

            $baris[] = [
                'kolom' => LabelAudit::kolom((string) $nama),
                'sebelum' => LabelAudit::nilai($sebelum, $kelas, (string) $nama),
                'sesudah' => LabelAudit::nilai($sesudah, $kelas, (string) $nama),
            ];
        }

        return $baris;
    }

    /**
     * Label ringkas objek untuk daftar, dikumpulkan per jenis agar tidak
     * menimbulkan satu kueri per baris.
     *
     * @param  Collection<int, AuditEvent>  $events
     * @return array<int, string> id peristiwa => label objek
     */
    private function labelObjek(Collection $events): array
    {
        $kelompok = [];

        foreach ($events as $event) {
            if ($event->auditable_type === null || $event->auditable_id === null) {
                continue;
            }

            $kelompok[$event->auditable_type][] = $event->auditable_id;
        }

        $label = [];

        foreach ($kelompok as $kelas => $ids) {
            $kolom = LabelAudit::KOLOM_RINGKAS[$kelas] ?? null;

            if ($kolom === null || ! class_exists($kelas)) {
                continue;
            }

            $baris = $kelas::query()
                ->whereKey(array_values(array_unique($ids)))
                ->pluck($kolom, 'id');

            foreach ($events as $event) {
                if ($event->auditable_type !== $kelas || $event->auditable_id === null) {
                    continue;
                }

                $nilai = $baris[$event->auditable_id] ?? null;

                if ($nilai !== null && $nilai !== '') {
                    $label[$event->getKey()] = (string) $nilai;
                }
            }
        }

        return $label;
    }

    private function tautanObjek(AuditEvent $event): ?string
    {
        if ($event->auditable_id === null) {
            return null;
        }

        return match ($event->auditable_type) {
            Personel::class => Route::has('master.personel.show')
                ? route('master.personel.show', $event->auditable_id)
                : null,
            JobDescription::class => Route::has('master.jd.show')
                ? route('master.jd.show', $event->auditable_id)
                : null,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $filter
     */
    private function uraianFilter(array $filter): string
    {
        $bagian = [];

        foreach ($filter as $kunci => $nilai) {
            if ($nilai === null || $nilai === '') {
                continue;
            }

            $bagian[] = $kunci.'='.$nilai;
        }

        return $bagian === [] ? 'tanpa filter' : implode('; ', $bagian);
    }

    /**
     * @return Collection<int, User>
     */
    private function pilihanAktor(): Collection
    {
        return User::query()->orderBy('name')->get(['id', 'name']);
    }
}
