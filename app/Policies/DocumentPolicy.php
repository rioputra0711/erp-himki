<?php

namespace App\Policies;

use App\Enums\AksiIzin;
use App\Enums\KlasifikasiDokumen;
use App\Enums\ModulAplikasi;
use App\Enums\PeranSistem;
use App\Models\Document;
use App\Models\User;
use App\Policies\Concerns\IzinModul;
use App\Support\CakupanPengguna;

class DocumentPolicy
{
    use IzinModul;

    protected function modul(): ModulAplikasi
    {
        return ModulAplikasi::Dokumen;
    }

    public function view(User $user, Document $document): bool
    {
        if (! $this->boleh($user, AksiIzin::Lihat)) {
            return false;
        }

        if (CakupanPengguna::adalahStaf($user)) {
            return $this->milikSendiri($user, $document);
        }

        return true;
    }

    public function update(User $user, Document $document): bool
    {
        if (! $this->boleh($user, AksiIzin::Ubah)) {
            return false;
        }

        if (CakupanPengguna::adalahStaf($user)) {
            return $this->milikSendiri($user, $document);
        }

        return true;
    }

    /**
     * Gerbang unduhan berkas: cakupan baris + klasifikasi akses.
     *
     * Administrator tidak mengunduh substansi dokumen, dan dokumen berkategori
     * rahasia keuangan hanya untuk Pengurus dan DE.
     */
    public function download(User $user, Document $document): bool
    {
        if (! $this->view($user, $document)) {
            return false;
        }

        if (CakupanPengguna::adalahAdmin($user)) {
            return false;
        }

        if ($document->klasifikasi === KlasifikasiDokumen::RahasiaKeuangan) {
            return $user->hasAnyRole([
                PeranSistem::Pengurus->value,
                PeranSistem::DirekturEksekutif->value,
            ]);
        }

        return true;
    }

    private function milikSendiri(User $user, Document $document): bool
    {
        $personelId = CakupanPengguna::personelId($user);

        if ($personelId === null) {
            return false;
        }

        return in_array($personelId, [(int) $document->pemilik_id, (int) $document->diunggah_oleh], true);
    }
}
