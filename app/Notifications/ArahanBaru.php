<?php

namespace App\Notifications;

use App\Models\Feedback;

/**
 * Arahan baru untuk penerimanya; DE juga menerima bila arahan berasal dari
 * Pengurus, sehingga arahan langsung Pengurus ke staf tidak pernah tersembunyi
 * dari DE.
 */
class ArahanBaru extends NotifikasiAplikasi
{
    public function __construct(private readonly Feedback $arahan) {}

    protected function isi(): array
    {
        return [
            'judul' => 'Arahan baru dari '.($this->arahan->pemberi_jabatan ?: 'pimpinan'),
            'pesan' => mb_substr((string) $this->arahan->isi, 0, 180),
            'url' => $this->tautan('arahan.show', $this->arahan->getKey()),
            'jenis' => 'arahan',
            'objek_id' => (int) $this->arahan->getKey(),
        ];
    }
}
