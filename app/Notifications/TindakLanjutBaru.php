<?php

namespace App\Notifications;

use App\Models\ActionItem;

/**
 * Action item baru untuk PIC-nya, termasuk item otomatis dari rekomendasi
 * perbaikan review.
 */
class TindakLanjutBaru extends NotifikasiAplikasi
{
    public function __construct(private readonly ActionItem $item) {}

    protected function isi(): array
    {
        return [
            'judul' => 'Tindak lanjut baru',
            'pesan' => mb_substr((string) $this->item->deskripsi, 0, 180),
            'url' => $this->tautan('tindak-lanjut.show', $this->item->getKey()),
            'jenis' => 'tindak_lanjut',
            'objek_id' => (int) $this->item->getKey(),
        ];
    }
}
