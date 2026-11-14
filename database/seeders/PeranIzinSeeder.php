<?php

namespace Database\Seeders;

use App\Enums\PeranSistem;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PeranIzinSeeder extends Seeder
{
    /**
     * Daftar izin per modul dengan pola `modul.aksi`.
     *
     * @var array<string, array<int, string>>
     */
    private const IZIN = [
        'assignment' => ['view', 'create', 'update', 'delete', 'verify'],
        'activity_log' => ['view', 'create', 'update'],
        'report' => ['view', 'create', 'update', 'submit', 'verify'],
        'document' => ['view', 'create', 'update', 'delete'],
        'review' => ['view', 'create', 'update'],
        'feedback' => ['view', 'create', 'update'],
        'action_item' => ['view', 'create', 'update', 'verify'],
        'kpi' => ['view', 'create', 'update', 'finalize'],
        'meeting' => ['view', 'create', 'update'],
        'personel' => ['view', 'create', 'update', 'deactivate'],
        'user' => ['view', 'create', 'update', 'deactivate'],
        'audit' => ['view'],
    ];

    /**
     * Peta peran ke izin. `admin` memakai seluruh izin yang terdaftar.
     *
     * @var array<string, array<int, string>>
     */
    private const PETA_PERAN = [
        PeranSistem::Pengurus->value => [
            'assignment.view', 'assignment.create', 'assignment.update', 'assignment.verify',
            'activity_log.view',
            'report.view', 'report.verify',
            'document.view', 'document.create',
            'review.view', 'review.create',
            'feedback.view', 'feedback.create',
            'action_item.view',
            'kpi.view', 'kpi.finalize',
            'meeting.view', 'meeting.create',
            'personel.view',
            'audit.view',
        ],
        PeranSistem::DirekturEksekutif->value => [
            'assignment.view', 'assignment.create', 'assignment.update', 'assignment.verify',
            'activity_log.view',
            'report.view', 'report.verify',
            'document.view', 'document.create', 'document.update',
            'review.view', 'review.create', 'review.update',
            'feedback.view', 'feedback.create', 'feedback.update',
            'action_item.view', 'action_item.create', 'action_item.update', 'action_item.verify',
            'kpi.view', 'kpi.create', 'kpi.update', 'kpi.finalize',
            'meeting.view', 'meeting.create', 'meeting.update',
            'personel.view',
            'audit.view',
        ],
        PeranSistem::Staf->value => [
            'assignment.view', 'assignment.update',
            'activity_log.view', 'activity_log.create', 'activity_log.update',
            'report.view', 'report.create', 'report.update', 'report.submit',
            'document.view', 'document.create', 'document.update',
            'feedback.view',
            'action_item.view', 'action_item.update',
            'kpi.view',
            'meeting.view',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $semuaIzin = [];

        foreach (self::IZIN as $modul => $aksi) {
            foreach ($aksi as $a) {
                $nama = $modul.'.'.$a;
                Permission::findOrCreate($nama, 'web');
                $semuaIzin[] = $nama;
            }
        }

        foreach (PeranSistem::cases() as $peran) {
            Role::findOrCreate($peran->value, 'web');
        }

        foreach (self::PETA_PERAN as $namaPeran => $izin) {
            Role::findByName($namaPeran, 'web')->syncPermissions($izin);
        }

        // Admin memegang seluruh izin, termasuk yang ditambahkan di masa depan.
        Role::findByName(PeranSistem::Admin->value, 'web')->syncPermissions($semuaIzin);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
