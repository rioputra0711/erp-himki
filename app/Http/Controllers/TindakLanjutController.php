<?php

namespace App\Http\Controllers;

use App\Enums\StatusActionItem;
use App\Models\ActionItem;
use App\Support\CakupanPengguna;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Aksi tindak lanjut / action item (fitur.pdf Tahap 5).
 *
 * Status kerja (terbuka, dalam proses, menunggu pihak lain, diajukan selesai)
 * boleh diubah PIC. Mengunci hasil (terverifikasi selesai) dan menutup item
 * hanya boleh pemegang `action_item.verify`, dan penutupan wajib bukti. Aturan
 * itu dijaga dua lapis: Policy di sini dan hook model `ActionItem`.
 */
class TindakLanjutController extends Controller
{
    public function update(Request $request, ActionItem $tindakLanjut): RedirectResponse
    {
        $this->authorize('update', $tindakLanjut);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in([
                StatusActionItem::Terbuka->value,
                StatusActionItem::DalamProses->value,
                StatusActionItem::MenungguPihakLain->value,
                StatusActionItem::DiajukanSelesai->value,
            ])],
            'alasan' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        try {
            $tindakLanjut->status = StatusActionItem::from($data['status']);
            $tindakLanjut->denganAlasan($data['alasan'])->save();
        } catch (DomainException $e) {
            return $this->gagal($tindakLanjut, $e->getMessage());
        }

        return $this->kembali($tindakLanjut, 'Status tindak lanjut diperbarui.');
    }

    public function verifikasi(Request $request, ActionItem $tindakLanjut): RedirectResponse
    {
        $this->authorize('verify', $tindakLanjut);

        $alasan = $request->validate([
            'alasan' => ['required', 'string', 'min:10', 'max:500'],
        ])['alasan'];

        try {
            $tindakLanjut->status = StatusActionItem::TerverifikasiSelesai;
            $tindakLanjut->diverifikasi_oleh = CakupanPengguna::personelId($request->user());
            $tindakLanjut->denganAlasan($alasan)->save();
        } catch (DomainException $e) {
            return $this->gagal($tindakLanjut, $e->getMessage());
        }

        return $this->kembali($tindakLanjut, 'Tindak lanjut diverifikasi selesai.');
    }

    public function tutup(Request $request, ActionItem $tindakLanjut): RedirectResponse
    {
        $this->authorize('tutup', $tindakLanjut);

        $data = $request->validate([
            'alasan' => ['required', 'string', 'min:10', 'max:500'],
            'bukti_penutupan' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        try {
            $tindakLanjut->status = StatusActionItem::Ditutup;
            $tindakLanjut->bukti_penutupan = $data['bukti_penutupan'];
            $tindakLanjut->diverifikasi_oleh = CakupanPengguna::personelId($request->user());
            $tindakLanjut->denganAlasan($data['alasan'])->save();
        } catch (DomainException $e) {
            return $this->gagal($tindakLanjut, $e->getMessage());
        }

        return $this->kembali($tindakLanjut, 'Tindak lanjut ditutup.');
    }

    private function kembali(ActionItem $item, string $pesan): RedirectResponse
    {
        $arahan = $item->feedback;

        return $arahan !== null
            ? redirect()->route('arahan.show', $arahan)->with('status', $pesan)
            : redirect()->route('arahan.index')->with('status', $pesan);
    }

    private function gagal(ActionItem $item, string $pesan): RedirectResponse
    {
        return $this->kembali($item, $pesan)->withErrors(['status' => $pesan]);
    }
}
