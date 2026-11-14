<?php

namespace App\Support;

use App\Enums\StatusFeedback;
use App\Enums\StatusPenugasan;
use App\Models\ActionItem;
use App\Models\Assignment;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Ringkasan tunggakan untuk panel dashboard (fitur.pdf Tahap 5).
 *
 * "Tunggakan" adalah pekerjaan yang belum tuntas: tugas yang belum final,
 * action item di luar status terverifikasi/ditutup, dan arahan yang meminta
 * tindakan tetapi belum selesai. Item yang belum selesai sengaja ditahan di
 * sini agar tidak hilang dari pandangan pemiliknya.
 *
 * Batas baris mengikuti cakupan pengguna yang sama dengan layar daftar, jadi
 * panel dashboard tidak pernah membocorkan pekerjaan orang lain ke Staf.
 */
final class RingkasanTunggakan
{
    private const BATAS = 5;

    /**
     * @return array{tugas: Collection<int, Assignment>, tindakLanjut: Collection<int, ActionItem>, arahan: Collection<int, Feedback>, jumlah: int}
     */
    public static function untuk(User $user): array
    {
        if (CakupanPengguna::adalahAdmin($user)) {
            // Admin mengurus akun dan konfigurasi, bukan pekerjaan operasional.
            return [
                'tugas' => collect(),
                'tindakLanjut' => collect(),
                'arahan' => collect(),
                'jumlah' => 0,
            ];
        }

        $tugas = Assignment::query()
            ->terlihatOleh($user)
            ->whereNotIn('status', [
                StatusPenugasan::Terverifikasi->value,
                StatusPenugasan::Ditutup->value,
                StatusPenugasan::Dibatalkan->value,
            ])
            ->orderBy('tenggat')
            ->limit(self::BATAS)
            ->get();

        $tindakLanjut = ActionItem::query()
            ->terlihatOleh($user)
            ->tunggakan()
            ->orderBy('tenggat')
            ->limit(self::BATAS)
            ->get();

        $arahan = Feedback::query()
            ->terlihatOleh($user)
            ->butuhTindakan()
            ->where('status', '!=', StatusFeedback::Selesai->value)
            ->orderBy('tenggat')
            ->limit(self::BATAS)
            ->get();

        return [
            'tugas' => $tugas,
            'tindakLanjut' => $tindakLanjut,
            'arahan' => $arahan,
            'jumlah' => $tugas->count() + $tindakLanjut->count() + $arahan->count(),
        ];
    }
}
