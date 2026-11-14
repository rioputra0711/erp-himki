<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Support\PencatatAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unduhan dokumen dengan pemeriksaan izin dan cakupan baris.
 *
 * Unduhan adalah jalur paling sering dipakai untuk membocorkan berkas, jadi
 * setiap permintaan melewati Policy `download` (klasifikasi akses + kepemilikan)
 * dan tercatat pada audit trail.
 */
class UnduhDokumenController extends Controller
{
    public function __invoke(Request $request, Document $dokumen): StreamedResponse
    {
        $pengguna = $request->user();

        if (Gate::forUser($pengguna)->denies('download', $dokumen)) {
            PencatatAudit::catat(
                event: PencatatAudit::AKSES_DITOLAK,
                request: $request,
                actor: $pengguna,
                after: [
                    'dokumen_id' => $dokumen->getKey(),
                    'klasifikasi' => $dokumen->klasifikasi?->value,
                ],
                reason: 'unduhan_ditolak',
            );

            abort(403, 'Anda tidak berhak mengunduh dokumen ini.');
        }

        $disk = Storage::disk('local');

        abort_unless($disk->exists($dokumen->file_path), 404, 'Berkas dokumen tidak ditemukan.');

        PencatatAudit::catat(
            event: 'dokumen.diunduh',
            request: $request,
            actor: $pengguna,
            after: [
                'dokumen_id' => $dokumen->getKey(),
                'versi' => $dokumen->versi,
            ],
        );

        return $disk->download($dokumen->file_path, $dokumen->file_name);
    }
}
