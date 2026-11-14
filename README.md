# ERP HIMKI

Aplikasi ERP HIMKI untuk manajemen kerja, pelaporan, dan evaluasi kinerja personel
Badan Eksekutif: penugasan, log aktivitas, laporan, review & skor, feedback, action item,
KPI, rapat, dokumen, dan jejak audit.

Fondasi: Laravel 12, PHP 8.2, MySQL/MariaDB, Blade + Tailwind 4 + Vite,
`spatie/laravel-permission` v6.

## Status: Tahap 1 — Fondasi Database

Yang sudah ada:

- 14 entitas inti + tabel penghubung, seluruhnya dengan FK eksplisit.
- Enum domain (`app/Enums`) untuk setiap status/tipe yang ditegakkan database.
- Validasi level database lewat `App\Support\CheckGuard` — CHECK constraint native di
  MySQL/MariaDB (41 aturan terpasang) dan PostgreSQL; trigger `RAISE(ABORT)` hanya
  sebagai jalur cadangan bila memakai SQLite.
- Audit trail append-only (`audit_events`) dijaga model + trigger database.
- Satu sumber waktu server: `App\Support\ServerClock` (zona `Asia/Jakarta`), termasuk
  SLA risalah rapat +1 jam (ringkas) dan +16 jam (lengkap).
- Peran & izin awal (`pengurus`, `de`, `staf`, `admin`) dengan pola izin `modul.aksi`.
- Factory, seeder referensi, dan data demo.
- 21 test yang membuktikan integritas skema.

Belum ada (Tahap 2): UI, login, controller/route, dashboard, mesin kalkulasi skor,
notifikasi, ekspor, Policy per peran.

## Menjalankan

Prasyarat: PHP 8.2+, Composer, MySQL/MariaDB (XAMPP: `C:\xampp\mysql\bin\mysql.exe`).
Buat dua database dulu — satu untuk aplikasi, satu untuk test:

```sql
CREATE DATABASE erp_himki_baru CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE erp_himki_baru_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Lalu:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan permission:cache-reset
php artisan test
```

`.env` default sudah `DB_CONNECTION=mysql`, `DB_DATABASE=erp_himki_baru`,
`DB_USERNAME=root`, `DB_PASSWORD=` (kosong, khas XAMPP), `DB_TIMEZONE=+07:00`.
Sesuaikan bila kredensial beda. `phpunit.xml` menunjuk ke `erp_himki_baru_test`, jadi
`php artisan test` tidak menyentuh data kerja.

Data demo (kata sandi `password`):

| Peran | Email | Username |
|---|---|---|
| Admin | admin@himki.test | admin |
| Pengurus | ketua@himki.test | ketua |
| Direktur Eksekutif | direktur@himki.test | direktur |
| Staf | staf@himki.test | staf |

## Catatan operasional

- **Selalu jalankan `php artisan permission:cache-reset` setelah seeder/deploy.** Spatie
  menyimpan cache izin; tanpa reset, perubahan peran/izin tidak terbaca.
- **Zona waktu** diatur `APP_TIMEZONE=Asia/Jakarta`. Jangan memakai `now()` dari sisi
  klien untuk timestamp kejadian; gunakan `ServerClock`. Sesi database dipatok
  `DB_TIMEZONE=+07:00` sehingga `now()` MySQL sama dengan jam server, apa pun zona OS-nya.
- **Database utama MySQL/MariaDB.** CHECK constraint terpasang native, jadi aturan
  numerik tetap berlaku walau data diubah lewat SQL langsung. Verifikasi cepat:
  `SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='erp_himki_baru' AND CONSTRAINT_TYPE='CHECK';`
  → harus 41.
- **Jalur cadangan SQLite** (tidak dipakai di produksi) memerlukan dua hal: migrasi yang
  mengubah tabel yang sudah ada harus berada sebelum
  `2026_11_14_050018_pulihkan_check_guard_triggers`, dan tabel `check_guard_rules` harus
  ada agar trigger CHECK dapat dipasang ulang setelah tabel dibangun ulang. Detail:
  `docs/superpowers/specs/2026-11-14-tahap-1-skema-database-design.md` §6.1.
  Di MySQL tabel `check_guard_rules` memang tidak dibuat — itu normal, bukan cacat.
- **Audit tidak bisa dihapus** — baik lewat Eloquent maupun SQL langsung. Untuk
  retensi/arsip, gunakan kolom status pada entitas terkait, bukan hapus baris audit.

## Struktur penting

```
app/Enums/                     enum domain
app/Models/                    model Eloquent (+ Concerns/Auditable)
app/Support/ServerClock.php    satu sumber waktu & SLA risalah
app/Support/CheckGuard.php     CHECK native (MySQL/Postgres) + jalur cadangan SQLite
app/Support/AppendOnlyGuard.php trigger append-only audit_events
database/migrations/           skema lengkap
database/seeders/              referensi + peran/izin + demo
docs/superpowers/specs/        desain Tahap 1
tests/Feature/SkemaTahap1Test.php bukti penerimaan
```
