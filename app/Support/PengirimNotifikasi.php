<?php

namespace App\Support;

use App\Enums\AsalFeedback;
use App\Enums\PeranSistem;
use App\Enums\SumberPerintah;
use App\Models\ActionItem;
use App\Models\Assignment;
use App\Models\Feedback;
use App\Models\User;
use App\Notifications\ArahanBaru;
use App\Notifications\ArahanDiteruskan;
use App\Notifications\PenugasanBaru;
use App\Notifications\PenugasanDiverifikasi;
use App\Notifications\TindakLanjutBaru;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Satu pintu pengiriman notifikasi Tahap 5.
 *
 * Dua aturan keamanan dipegang di sini:
 *
 * 1. Penerima ditentukan dari relasi domain, bukan dari masukan pengguna.
 *    Arahan langsung Pengurus selalu menambah seluruh akun DE yang aktif,
 *    sehingga jalur instruksi tidak pernah tersembunyi dari DE.
 * 2. Gangguan pengiriman tidak boleh membatalkan data utama (PRD D.7).
 *    Setiap kegagalan dicatat sebagai `notifikasi.gagal` lalu ditelan; method
 *    publik di kelas ini tidak pernah melempar.
 */
class PengirimNotifikasi
{
    public function penugasanBaru(Assignment $tugas): void
    {
        $this->kirimKeBanyak($this->penerimaPenugasan($tugas), new PenugasanBaru($tugas));
    }

    public function penugasanDiverifikasi(Assignment $tugas): void
    {
        $this->kirimKeBanyak($this->penerimaPic($tugas), new PenugasanDiverifikasi($tugas));
    }

    public function arahanBaru(Feedback $arahan): void
    {
        $penerima = $this->penerimaArahan($arahan);

        $this->kirimKeBanyak($penerima, new ArahanBaru($arahan));
    }

    public function arahanDiteruskan(Assignment $tugas): void
    {
        $pemberi = $tugas->sumberArahan?->pemberi?->user;

        $this->kirimKeBanyak(collect([$pemberi]), new ArahanDiteruskan($tugas));
    }

    public function tindakLanjutBaru(ActionItem $item): void
    {
        $pic = $item->pic?->user;

        $this->kirimKeBanyak(collect([$pic]), new TindakLanjutBaru($item));
    }

    /**
     * Satu-satunya titik pengiriman, agar dapat diganti di tes.
     */
    protected function kirim(User $penerima, Notification $notifikasi): void
    {
        $penerima->notify($notifikasi);
    }

    /**
     * @param  Collection<int, User|null>  $penerima
     */
    private function kirimKeBanyak(Collection $penerima, Notification $notifikasi): void
    {
        foreach ($this->rapikan($penerima) as $satu) {
            try {
                $this->kirim($satu, $notifikasi);
            } catch (Throwable $e) {
                $this->catatGagal($satu, $notifikasi, $e);
            }
        }
    }

    /**
     * @param  Collection<int, User|null>  $penerima
     * @return Collection<int, User>
     */
    private function rapikan(Collection $penerima): Collection
    {
        return $penerima
            ->filter(static fn ($user): bool => $user instanceof User && $user->is_active)
            ->unique(static fn (User $user): int => (int) $user->getKey())
            ->values();
    }

    /**
     * @return Collection<int, User|null>
     */
    private function penerimaPenugasan(Assignment $tugas): Collection
    {
        $penerima = $this->penerimaPic($tugas);

        if ($tugas->sumber_perintah === SumberPerintah::PengurusLangsung) {
            $penerima = $penerima->merge($this->akunDe());
        }

        return $penerima;
    }

    /**
     * @return Collection<int, User|null>
     */
    private function penerimaPic(Assignment $tugas): Collection
    {
        return collect([$tugas->pic?->user]);
    }

    /**
     * @return Collection<int, User|null>
     */
    private function penerimaArahan(Feedback $arahan): Collection
    {
        $penerima = collect([$arahan->penerima?->user]);

        if ($arahan->asal === AsalFeedback::Pengurus) {
            $penerima = $penerima->merge($this->akunDe());
        }

        return $penerima;
    }

    /**
     * @return Collection<int, User>
     */
    private function akunDe(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->role(PeranSistem::DirekturEksekutif->value)
            ->get();
    }

    private function catatGagal(User $penerima, Notification $notifikasi, Throwable $e): void
    {
        PencatatAudit::catat(
            'notifikasi.gagal',
            app()->runningInConsole() ? null : request(),
            auth()->user(),
            after: [
                'kelas' => $notifikasi::class,
                'penerima_id' => $penerima->getKey(),
            ],
            reason: mb_substr($e->getMessage(), 0, 200),
        );

        Log::warning('Notifikasi gagal dikirim.', [
            'kelas' => $notifikasi::class,
            'penerima_id' => $penerima->getKey(),
            'pesan' => $e->getMessage(),
        ]);
    }
}
