<?php

namespace App\Models\Concerns;

use App\Support\IdentitasPelapor;
use App\Support\PencatatAudit;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Mengunci kolom `personel_id` pada model yang ditandatangani pelapor.
 *
 * Dipakai laporan, log aktivitas, dan dokumen (fitur.pdf Tahap 3 — "Kunci
 * kolom nama dan jabatan dari sesi akun aktif untuk mencegah pemalsuan
 * identitas pengirim laporan").
 *
 * Aturan:
 * - tanpa sesi (seeder, perintah konsol) nilai eksplisit dibiarkan apa adanya;
 * - sesi ada dan `personel_id` kosong → diisi dari Master Personel milik sesi;
 * - sesi ada dan `personel_id` berbeda → ditolak, bukan ditimpa diam-diam,
 *   supaya percobaan pemalsuan terlihat dan tercatat.
 */
trait MilikSesiPelapor
{
    public static function bootMilikSesiPelapor(): void
    {
        static::saving(function (self $model): void {
            $sesi = auth()->user();

            if ($sesi === null) {
                return;
            }

            $kolom = $model->kolomPelapor();
            $idSesi = IdentitasPelapor::dariSesi($sesi)?->getKey();
            $idModel = $model->getAttribute($kolom);

            if ($idSesi === null) {
                return;
            }

            if ($idModel === null) {
                $model->setAttribute($kolom, (int) $idSesi);

                return;
            }

            if ((int) $idModel !== (int) $idSesi) {
                // Percobaan pemalsuan identitas pelapor dicatat sebagai peristiwa
                // keamanan sebelum ditolak, supaya tidak hilang tanpa jejak.
                PencatatAudit::catat(
                    event: 'identitas.ditolak',
                    request: request(),
                    actor: $sesi,
                    after: [
                        'model' => $model::class,
                        'personel_id_diminta' => (int) $idModel,
                        'personel_id_sesi' => (int) $idSesi,
                    ],
                );

                throw new AuthorizationException(
                    'Identitas pelapor terkunci pada akun yang sedang aktif.'
                );
            }
        });
    }

    /**
     * Nama kolom identitas pelapor pada model ini.
     */
    protected function kolomPelapor(): string
    {
        return 'personel_id';
    }
}
