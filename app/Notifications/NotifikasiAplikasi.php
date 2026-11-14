<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/**
 * Dasar notifikasi dalam aplikasi.
 *
 * Seluruh notifikasi disimpan ke tabel `notifications` bawaan Laravel dan
 * dibaca lewat `/notifikasi`. Tidak ada kanal luar (email/WhatsApp) pada tahap
 * ini, sehingga `via()` selalu `database`.
 *
 * Isi `data` sengaja terbatas pada judul, pesan, tautan internal, jenis, dan id
 * objek — tanpa NIP, isi dokumen, atau data sensitif lain. Tautan dibuat dari
 * nama rute, bukan dari masukan pengguna, sehingga tidak dapat dipakai untuk
 * mengarahkan korban ke domain luar.
 */
abstract class NotifikasiAplikasi extends Notification
{
    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->isi();
    }

    /**
     * @return array{judul: string, pesan: string, url: ?string, jenis: string, objek_id: ?int}
     */
    abstract protected function isi(): array;

    /**
     * Tautan internal, atau null bila rutenya belum terdaftar.
     *
     * Rute layar penugasan/arahan baru ada pada tahap implementasi berikutnya;
     * notifikasi tetap sah tanpa tautan, jadi ketiadaan rute tidak boleh
     * menggagalkan pengiriman.
     */
    protected function tautan(string $nama, mixed $parameter = null): ?string
    {
        if (! Route::has($nama)) {
            return null;
        }

        return $parameter === null ? route($nama) : route($nama, $parameter);
    }
}
