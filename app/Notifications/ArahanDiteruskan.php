<?php

namespace App\Notifications;

use App\Models\Assignment;

/**
 * Arahan Pengurus sudah diteruskan DE menjadi penugasan; pemberi arahan perlu
 * tahu bahwa instruksinya benar-benar sampai ke pelaksana.
 */
class ArahanDiteruskan extends NotifikasiAplikasi
{
    public function __construct(private readonly Assignment $tugas) {}

    protected function isi(): array
    {
        return [
            'judul' => 'Arahan diteruskan: '.$this->tugas->kode,
            'pesan' => mb_substr((string) $this->tugas->judul, 0, 180),
            'url' => $this->tautan('tugas.show', $this->tugas->getKey()),
            'jenis' => 'penugasan',
            'objek_id' => (int) $this->tugas->getKey(),
        ];
    }
}
