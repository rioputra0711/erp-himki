<?php

namespace App\Listeners;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Events\PermissionAttached;
use Spatie\Permission\Events\PermissionDetached;
use Spatie\Permission\Events\RoleAttached;
use Spatie\Permission\Events\RoleDetached;

/**
 * Mencatat perubahan hak akses (peran & izin) ke jejak audit.
 *
 * Hanya perubahan yang dilakukan manusia yang dicatat: tanpa sesi login
 * (seeder, artisan, tinker) listener langsung keluar. Jejak audit bersifat
 * append-only, sehingga baris hasil seeding tidak akan pernah bisa
 * dibersihkan — lebih baik tidak dibuat sejak awal.
 *
 * Berbeda dengan `PencatatAudit` yang bersifat best-effort, penulisan di sini
 * sengaja tanpa penangkap kesalahan: perubahan hak akses tanpa jejak tidak
 * boleh terjadi, jadi kegagalan menulis harus menggagalkan perubahan itu.
 */
final class CatatPerubahanAkses
{
    public function handle(RoleAttached|RoleDetached|PermissionAttached|PermissionDetached $event): void
    {
        $pelaku = Auth::user();

        if ($pelaku === null) {
            return;
        }

        [$nama, $kunci, $muatan] = match (true) {
            $event instanceof RoleAttached => ['akses.peran_diberikan', 'peran', $event->rolesOrIds],
            $event instanceof RoleDetached => ['akses.peran_dicabut', 'peran', $event->rolesOrIds],
            $event instanceof PermissionAttached => ['akses.izin_diberikan', 'izin', $event->permissionsOrIds],
            default => ['akses.izin_dicabut', 'izin', $event->permissionsOrIds],
        };

        $daftar = $this->namaDaftar($muatan);
        $subjek = $event->model;

        AuditEvent::create([
            'actor_id' => $pelaku->getKey(),
            'actor_nama' => $pelaku->name,
            'actor_peran' => $pelaku->peranSistem()?->value,
            'event' => $nama,
            'auditable_type' => $subjek instanceof Model ? $subjek->getMorphClass() : null,
            'auditable_id' => $subjek instanceof Model ? $subjek->getKey() : null,
            'before' => str_contains($nama, 'dicabut') ? [$kunci => $daftar] : null,
            'after' => str_contains($nama, 'diberikan') ? [$kunci => $daftar] : null,
            'reason' => null,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
        ]);
    }

    /**
     * Ubah muatan event Spatie (id, model, atau koleksi) menjadi daftar nama.
     *
     * @return list<string>
     */
    private function namaDaftar(mixed $muatan): array
    {
        $butir = $muatan instanceof Collection ? $muatan->all() : (array) $muatan;

        $nama = [];

        foreach ($butir as $item) {
            if ($item instanceof RoleContract || $item instanceof PermissionContract) {
                $nama[] = (string) $item->name;

                continue;
            }

            if (is_int($item) || (is_string($item) && ctype_digit($item))) {
                $nama[] = $this->namaDariId((int) $item);

                continue;
            }

            $nama[] = (string) $item;
        }

        $nama = array_values(array_filter($nama, static fn (string $n): bool => $n !== ''));
        sort($nama);

        return $nama;
    }

    private function namaDariId(int $id): string
    {
        $peran = app(RoleContract::class)->findById($id);
        $nama = $peran?->name ?? app(PermissionContract::class)->findById($id)?->name;

        return $nama === null ? '#'.$id : (string) $nama;
    }
}
