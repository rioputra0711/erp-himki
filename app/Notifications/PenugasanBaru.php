<?php

namespace App\Notifications;

use App\Models\Assignment;

/**
 * Penugasan baru untuk PIC; DE juga menerima bila arahan datang langsung dari
 * Pengurus (fitur.pdf Tahap 5 — jalur 3 wajib terlihat DE).
 */
class PenugasanBaru extends NotifikasiAplikasi
{
    public function __construct(private readonly Assignment $tugas) {}

    protected function isi(): array
    {
        return [
            'judul' => 'Penugasan baru: '.$this->tugas->kode,
            'pesan' => mb_substr((string) $this->tugas->judul, 0, 180),
            'url' => $this->tautan('tugas.show', $this->tugas->getKey()),
            'jenis' => 'penugasan',
            'objek_id' => (int) $this->tugas->getKey(),
        ];
    }
}
