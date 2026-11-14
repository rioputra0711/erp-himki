# Desain Tahap 1 — Fondasi Database Relasional & Konfigurasi Dasar

Tanggal: 2026-11-14
Proyek: ERP HIMKI — Modul HR, Manajemen Kerja, Pelaporan, Evaluasi Kinerja Personel BE
Status: menunggu persetujuan

## 1. Maksud

Menyiapkan lapisan data yang menjadi dasar seluruh modul ERP HIMKI: 14 entitas inti,
relasi kunci asing yang utuh, validasi isian di level database, audit trail append-only,
dan zona waktu resmi HIMKI sebagai acuan tunggal seluruh timestamp, pengingat tenggat,
serta perhitungan SLA risalah.

Tahap ini **tidak** membangun UI, autentikasi login, controller, atau mesin kalkulasi.
Yang dihasilkan: migration, model Eloquent, enum domain, seeder peran/izin, factory,
konfigurasi waktu, dan test yang membuktikan integritas skema.

## 2. Tujuan

- 14 entitas inti + tabel penghubung tersedia dan saling terhubung lewat FK.
- Setiap baris punya ID unik dan timestamp; tidak ada relasi yatim.
- Status/tipe memakai kolom enum (ditegakkan database), bukan string bebas.
- Nilai numerik kritis (skor 0–100, progres 0–100, urutan waktu) ditolak database bila di luar batas.
- Semua waktu diambil dari jam server, bukan jam perangkat pengguna.
- Audit trail tidak dapat diubah atau dihapus.
- Rekap apa pun dapat ditelusuri ke baris sumbernya lewat relasi.

## 3. Non-tujuan

- Halaman login, Breeze/Fortify, controller, route, dashboard.
- Perhitungan skor laporan/KPI (Tahap 2) — Tahap 1 hanya menyimpan komponen dan hasil.
- Notifikasi, ekspor PDF/Excel, integrasi email/kalender.
- Modul ERP lain (pengadaan, inventaris, akuntansi, keanggotaan).
- Program/Kegiatan sebagai tabel tersendiri (belum masuk daftar 14 entitas; dicatat sebagai item Tahap 2).

## 4. Keputusan pendekatan

| Keputusan | Pilihan | Alasan |
|---|---|---|
| RBAC | **spatie/laravel-permission v6** (`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`) | PRD (E.1) hanya menetapkan "Server-Side RBAC", tidak menetapkan pustaka. Pustaka teruji mengurangi risiko pada komponen keamanan. Versi 6 dipilih karena mendukung PHP ^8.0 + Laravel 12 (v7/v8 menuntut PHP 8.3). Peran menempel di `users`; row-level scope tetap Policy kustom |
| Master jabatan/bidang | Tabel lookup `jabatans`, `bidangs` | Personel & Job Description butuh referensi yang konsisten; menghindari salah ketik nomenklatur |
| Nama kolom | Bahasa Indonesia untuk domain, Inggris untuk kolom teknis (`id`, `created_at`) | Mengikuti istilah PRD agar tidak ada terjemahan ganda saat audit |
| Timestamp | `app.timezone = Asia/Jakarta`, disimpan sebagai waktu lokal HIMKI | Satu zona operasional; SLA 1 jam/16 jam dihitung tanpa konversi |
| Jam server | `App\Support\ServerClock` + kolom "waktu kejadian" diisi model event | Pengguna tidak bisa memalsukan `diajukan_pada`, `reviewed_at`, `dicatat_pada` |
| Validasi numerik | Helper `CheckGuard`: `CHECK` di MySQL/PostgreSQL, trigger `RAISE(ABORT)` di SQLite | Portabel, tetap berlaku walau data diubah lewat SQL langsung |
| Basis data | **MySQL/MariaDB**, database `erp_himki_baru` (+ `erp_himki_baru_test` untuk test), `utf8mb4`/`utf8mb4_unicode_ci` | Dipilih pengguna; CHECK native sejak MySQL 8.0.16 / MariaDB 10.2, jadi aturan integritas tetap berlaku di level database |
| Hapus data | Tanpa soft delete; pakai kolom status/`status_aktif` | PRD minta arsip & retensi, bukan hapus tersembunyi |
| Audit | Tabel `audit_events` append-only + guard model + trigger tolak UPDATE/DELETE | Memenuhi NFR "tidak dapat diubah atau dihapus" |

## 5. Skema

Legenda: `PK` primary key, `FK` foreign key, `UQ` unique, `NN` not null.
Semua tabel punya `id` bigint unsigned PK dan `created_at`/`updated_at` kecuali `audit_events`.

### 5.1 `users`
| Kolom | Tipe | Aturan |
|---|---|---|
| name | string(150) | NN |
| username | string(50) | UQ, nullable |
| email | string(190) | UQ, NN |
| email_verified_at | timestamp | nullable |
| password | string | NN, cast `hashed` |
| is_active | boolean | default true |
| last_login_at | timestamp | nullable |
| remember_token | string(100) | nullable |

### 5.2 `roles` / `permissions` (+ 3 tabel pivot)
Dikelola `spatie/laravel-permission` v6 sesuai migration bawaan paket
(`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`).
Nama peran: `pengurus`, `de`, `staf`, `admin`. Izin memakai pola `modul.aksi`
(`assignment.create`, `report.verify`, `kpi.finalize`, …) dan dikelompokkan per modul.

Konsekuensi operasional: setiap deploy/seeder wajib `php artisan permission:cache-reset`,
jika tidak, perubahan izin tidak terbaca (cache basi).

### 5.6 `jabatans`
`nama` UQ, `kode` UQ, `kelompok` enum(`pengurus`,`de`,`staf`), `is_active`.

### 5.7 `bidangs`
`nama` UQ, `kode` UQ, `is_active`.

### 5.8 `personel`
| Kolom | Tipe | Aturan |
|---|---|---|
| user_id | FK users | UQ, NN, cascade |
| nip | string(30) | UQ, nullable |
| nama_lengkap | string(150) | NN |
| jabatan_id | FK jabatans | NN, restrict |
| bidang_id | FK bidangs | nullable, restrict |
| kategori | enum(pengurus, de, staf) | NN |
| status_aktif | boolean | default true |
| tanggal_masuk | date | nullable |
| telepon | string(30) | nullable |

### 5.9 `job_descriptions`
`jabatan_id` FK NN, `kode` UQ, `kategori` enum(utama, umum, insidental, lintas_bidang),
`uraian` text NN, `indikator` text, `target` text, `frekuensi` enum(harian, mingguan, bulanan, tahunan, insidental) nullable, `is_active`.

### 5.10 `assignments`
`kode` UQ (format `TGS-YYYY-NNNN`), `judul`, `deskripsi`,
`job_description_id` FK nullable, `sumber_perintah` enum(de_langsung, pengurus_melalui_de, pengurus_langsung),
`pemberi_tugas_id` FK personel NN, `diteruskan_oleh_id` FK personel nullable,
`pic_id` FK personel NN, `kategori` enum(utama, umum, lintas_bidang, insidental),
`target_keluaran` text, `prioritas` enum(rendah, sedang, tinggi, mendesak),
`tanggal_mulai` date NN, `tenggat` datetime NN,
`status` enum(draft, aktif, dalam_proses, terhambat, diajukan_selesai, terverifikasi, ditutup, dibatalkan),
`progres` tinyint unsigned default 0, `kendala` text, `alasan_pembatalan` text,
`dependensi_id` FK assignments nullable (self), `diverifikasi_oleh` FK personel nullable, `diverifikasi_pada` datetime nullable, `ditutup_pada` datetime nullable.

Aturan: `status = dibatalkan` wajib `alasan_pembatalan` (ditegakkan CHECK/trigger).

### 5.11 `activity_logs`
`personel_id` FK NN cascade, `assignment_id` FK nullable, `job_description_id` FK nullable,
`tanggal` date NN, `uraian` text NN, `progres` tinyint unsigned nullable,
`durasi_menit` smallint unsigned nullable, `kendala` text nullable,
`is_kendala_dini` boolean default false, `dicatat_pada` datetime NN (jam server).

### 5.12 `reports`
`personel_id` FK NN, `assignment_id` FK nullable, `jenis` enum(harian, mingguan, bulanan, kegiatan),
`judul`, `periode_mulai` date NN, `periode_selesai` date NN,
`ringkasan` text, `narasi_evaluasi` text, `kendala` text, `rencana_tindak_lanjut` text,
`status` enum(draft, diajukan, dalam_review, perlu_revisi, terverifikasi, ditutup, dibatalkan),
`versi` smallint unsigned default 1, `induk_id` FK reports nullable (versi sebelumnya),
`diajukan_pada` datetime nullable, `diverifikasi_oleh` FK personel nullable, `diverifikasi_pada` datetime nullable.

UQ: (`personel_id`, `jenis`, `periode_mulai`, `periode_selesai`, `versi`).
CHECK: `periode_selesai >= periode_mulai`.

### 5.13 `documents`
`judul`, `kategori` enum(surat, laporan, matriks, foto, risalah, lainnya),
`versi` smallint unsigned default 1, `induk_id` FK documents nullable,
`pemilik_id` FK personel NN, `assignment_id`/`report_id`/`meeting_id` FK nullable,
`file_path` string NN, `file_name`, `mime_type` string(100), `file_size` bigint unsigned,
`checksum` char(64) nullable, `klasifikasi` enum(umum, internal_be, rahasia_keuangan),
`status` enum(draft, untuk_review, final, digantikan, diarsipkan),
`diunggah_oleh` FK personel NN.

UQ: (`file_path`). CHECK: `file_size > 0`.

### 5.14 `reviews` (Review & Skor)
`report_id` FK NN cascade, `dokumen_id` FK documents nullable,
`pemberi_review_id` FK personel NN,
`skor_kelengkapan`, `skor_kejelasan`, `skor_akurasi`, `skor_sistematika`, `skor_tindak_lanjut` tinyint unsigned NN,
`bobot_kelengkapan`=20, `bobot_kejelasan`=25, `bobot_akurasi`=25, `bobot_sistematika`=15, `bobot_tindak_lanjut`=15 (tinyint, disimpan agar skor historis tetap sah saat bobot berubah),
`skor_total` decimal(5,2) NN, `kategori` enum(sangat_baik, baik, cukup, perlu_perbaikan, belum_memenuhi_standar),
`catatan_kekuatan` text, `kekurangan_spesifik` text, `rekomendasi_perbaikan` text,
`versi_review` smallint unsigned default 1, `adalah_final` boolean default false, `reviewed_at` datetime NN.

UQ: (`report_id`, `versi_review`). CHECK: tiap skor 0–100, `skor_total` 0–100, bobot 0–100.

### 5.15 `feedback` (Arahan/Komentar Pimpinan)
`pemberi_id` FK personel NN, `penerima_id` FK personel nullable,
`jenis` enum(arahan, komentar, masukan, koreksi),
`asal` enum(pengurus, de), `subjek_type`/`subjek_id` (morph: assignment, report, document),
`isi` text NN, `lampiran_dokumen_id` FK documents nullable,
`butuh_tindakan` boolean default false,
`status` enum(terbuka, ditindaklanjuti, selesai), `tenggat` datetime nullable.

CHECK: `butuh_tindakan = false` OR `penerima_id` tidak null.

### 5.16 `action_items`
`feedback_id` FK nullable, `review_id` FK nullable, `assignment_id` FK nullable, `report_id` FK nullable,
`deskripsi` text NN, `pic_id` FK personel NN, `tenggat` datetime nullable,
`status` enum(terbuka, dalam_proses, menunggu_pihak_lain, diajukan_selesai, terverifikasi_selesai, ditutup),
`selesai_pada` datetime nullable, `diverifikasi_oleh` FK personel nullable.

CHECK: minimal satu sumber terisi (`feedback_id`, `review_id`, `assignment_id`, atau `report_id`).

### 5.17 `kpi_results`
`personel_id` FK NN, `periode` char(7) format `YYYY-MM`,
`skor_pencapaian`, `skor_kualitas`, `skor_ketepatan_waktu`, `skor_tindak_lanjut`, `skor_kolaborasi` tinyint unsigned NN,
`bobot_pencapaian`=35, `bobot_kualitas`=25, `bobot_ketepatan_waktu`=15, `bobot_tindak_lanjut`=15, `bobot_kolaborasi`=10,
`skor_total` decimal(5,2), `kategori` enum(sangat_baik, baik, perlu_peningkatan, kurang, sangat_kurang),
`status` enum(belum_dihitung, data_belum_lengkap, draft, menunggu_klarifikasi, final),
`rule_version` string(30) NN, `klarifikasi` text,
`dihitung_oleh` FK personel nullable, `dikunci_oleh` FK personel nullable, `dikunci_pada` datetime nullable.

UQ: (`personel_id`, `periode`). CHECK: skor 0–100, bobot 0–100, jumlah bobot = 100.
Aturan: `status = final` wajib `dikunci_oleh` + `dikunci_pada`.

### 5.18 `meetings` + 5.19 `meeting_attendees`
`meetings`: `judul`, `agenda` text, `lokasi` nullable, `waktu_mulai` datetime NN, `waktu_selesai` datetime NN,
`pencatat_id` FK personel NN,
`tenggat_risalah_singkat` datetime NN, `tenggat_risalah_lengkap` datetime NN,
`status_risalah_singkat`/`status_risalah_lengkap` enum(belum, diajukan, terverifikasi, terlambat),
`risalah_singkat_id`/`risalah_lengkap_id` FK documents nullable.

CHECK: `waktu_selesai >= waktu_mulai`; tenggat = selesai + 1 jam / + 16 jam (dihitung model, diverifikasi test).
`meeting_attendees`: PK gabungan (`meeting_id`, `personel_id`), `hadir` boolean default true.

### 5.20 `audit_events`
`actor_id` FK users nullable (nullOnDelete agar jejak bertahan),
`event` string(50) NN, `auditable_type` string, `auditable_id` bigint nullable,
`before` json nullable, `after` json nullable, `reason` text nullable,
`ip_address` string(45), `user_agent` text, `created_at` timestamp NN.
Tanpa `updated_at`. Index (`auditable_type`,`auditable_id`), (`actor_id`), (`created_at`).
Trigger menolak UPDATE dan DELETE.

## 6. Integritas & keamanan

- Semua FK `NN` memakai `restrict`/`cascade` eksplisit; tidak ada `set null` pada kolom wajib.
- Enum status mencegah nilai liar (contoh: `status` assignment hanya 8 nilai).
- CHECK/trigger menolak: progres & skor di luar 0–100, periode terbalik, rapat selesai sebelum mulai, pembatalan tanpa alasan, KPI final tanpa pengunci, action item tanpa sumber, bobot KPI ≠ 100.
- `audit_events` append-only di level model (exception saat update/delete) dan level database (trigger).
- `users.password` cast `hashed`; `password` & `remember_token` hidden dari serialisasi.
- Tidak ada `$guarded = []`; setiap model memakai `$fillable` eksplisit.
- Semua timestamp kejadian diisi `ServerClock::now()` lewat model event, bukan dari request.

### 6.1 Catatan implementasi SQLite (jalur cadangan)

Database utama adalah MySQL/MariaDB: `CheckGuard` memasang `CHECK` constraint native
(41 aturan, terverifikasi lewat `information_schema.TABLE_CONSTRAINTS`) dan tabel
`check_guard_rules` **tidak** dibuat. Catatan di bawah hanya berlaku bila proyek
dijalankan di SQLite.

SQLite tidak punya CHECK constraint yang dapat ditambah setelah tabel dibuat, sehingga
`CheckGuard` memasang trigger `BEFORE INSERT`/`BEFORE UPDATE` dengan `RAISE(ABORT)`.
Dua perilaku SQLite yang harus dijaga:

1. **Kolom harus berkualifikasi.** Saat tabel lain di-`ALTER`, SQLite mem-parse ulang
   seluruh trigger. Referensi kolom tanpa awalan gagal dengan `no such column`, jadi
   `CheckGuard` menulis ekspresi sebagai `NEW.<kolom>`.
2. **Trigger hilang saat tabel dibangun ulang.** Menambah foreign key ke tabel yang
   sudah ada memaksa SQLite membuat tabel sementara, menyalin data, lalu `DROP` tabel
   lama — seluruh trigger tabel itu ikut hilang. Karena itu setiap aturan dicatat di
   tabel pendukung `check_guard_rules` dan migrasi terakhir
   (`2026_11_14_050018_pulihkan_check_guard_triggers`) memanggil
   `CheckGuard::pulihkanSemua()`. Migrasi baru yang mengubah tabel wajib dibuat
   **sebelum** migrasi pemulihan tersebut, atau memanggil `pulihkanSemua()` sendiri.
3. Test `trigger_dokumen_tetap_aktif_setelah_tabel_dokumen_dibangun_ulang` mengunci
   perilaku ini agar regresi tidak lolos.

## 7. Zona waktu

- `.env`, `.env.example`: `APP_TIMEZONE=Asia/Jakarta`, `APP_LOCALE=id`, `DB_TIMEZONE=+07:00`.
- `config/app.php`: `'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta')`.
- `config/database.php` (koneksi `mysql` & `mariadb`): `'timezone' => env('DB_TIMEZONE', '+07:00')`,
  sehingga `now()` MySQL/MariaDB berada di WIB apa pun zona OS server.
- `App\Support\ServerClock::now()` → `CarbonImmutable::now(config('app.timezone'))`.
- Test: `config('app.timezone') === 'Asia/Jakarta'`; `ServerClock::now()->timezoneName === 'Asia/Jakarta'`;
  `@@session.time_zone === '+07:00'` dan `now()` database cocok dengan `ServerClock` (menit yang sama).
- SLA risalah: `tenggat_risalah_singkat = waktu_selesai + 1 jam`, `tenggat_risalah_lengkap = waktu_selesai + 16 jam`, dihitung dari jam server saat rapat dicatat.

## 8. Bukti penerimaan (test)

1. `migrate:fresh --seed` bersih di MySQL `erp_himki_baru` (29 tabel, 41 CHECK native, 2 trigger audit).
2. Rantai relasi: user → personel → assignment → activity_log → report → review → action_item dapat dibuat dan dibaca balik.
3. FK menolak PIC yang tidak ada.
4. Unique menolak KPI ganda untuk personel+periode yang sama.
5. CHECK menolak `progres = 101`, `skor_total = 120`, `periode_selesai < periode_mulai`.
6. Audit event tidak bisa di-update/di-delete.
7. Assignment batal tanpa alasan ditolak.
8. Tenggat risalah = +1 jam / +16 jam dari `waktu_selesai`.
9. Zona waktu aplikasi `Asia/Jakarta`; sesi database `+07:00` dan `now()` DB = `ServerClock` (selisih < 1 menit).
10. `password` otomatis ter-hash saat user dibuat.
11. Skor total review/KPI dihitung ulang model dari komponen & bobot tersimpan.
12. KPI final tanpa `dikunci_oleh` ditolak.
13. Feedback `butuh_tindakan` tanpa penerima ditolak.
14. Trigger CHECK `documents` tetap aktif setelah tabel dibangun ulang (lihat §6.1).

## 9. Yang diserahkan ke Tahap 2

- FormRequest & validasi input HTTP.
- Policy/Gate per peran memakai `permissions`.
- Mesin skor laporan & KPI (memakai bobot tersimpan).
- Generator rekap mingguan/bulanan.
- Notifikasi tenggat.
- Tabel `programs`/kegiatan bila disepakati.
