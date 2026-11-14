<?php

namespace App\Notifications;

use App\Models\Assignment;

/**
 * Hasil kerja telah diverifikasi atau ditutup; PIC perlu tahu bahwa tugasnya
 * sudah dikunci.
 */
class PenugasanDiverifikasi extends NotifikasiAplikasi
{
    public function __construct(private readonly Assignment $tugas) {}

    protected function isi(): array
    {
        return [
            'judul' => 'Penugasan diverifikasi: '.$this->tugas->kode,
            'pesan' => mb_substr((string) $this->tugas->judul, 0, 180),
            'url' => $this->tautan('tugas.show', $this->tugas->getKey()),
            'jenis' => 'penugasan',
            'objek_id' => (int) $this->tugas->getKey(),
        ];
    }
}
