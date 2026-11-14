# Tahap 4 — Layanan Audit Trail (Jejak Audit Sistem & Operasional)

Tanggal: 14 November 2026
Acuan: `Proposal_dan_PRD_ERP_HIMKI_Lengkap_Final.docx` (bagian 12 Keamanan/Audit Trail, D.4 butir perubahan skor/target/bobot/status, E.2 matriks izin baris audit), `fitur.pdf` Tahap 4, skill `laravel-owasp-security`.
Prasyarat: Tahap 1 (skema + `audit_events` append-only), Tahap 2 (RBAC), Tahap 3 (master personel/JD) selesai dan teruji.

## 1. Ruang lingkup

| Butir Tahap 4 (`fitur.pdf`) | Wujud implementasi |
|---|---|
| Tabel Append-Only: layanan pencatatan log otomatis yang permanen, tidak dapat diubah atau dihapus pengguna mana pun | Tiga lapis: model `AuditEvent` menolak `updating`/`deleting`; trigger DB `no_update`/`no_delete`; `actor_id` diubah ke `restrictOnDelete` + snapshot `actor_nama`/`actor_peran` sehingga jejak tidak berubah walau akun dihapus. TRUNCATE ditutup lewat hak database (bagian 9). |
| Keamanan: blokir ubah/hapus di level aplikasi **dan** bagi Administrator Aplikasi | `AuditEventPolicy::create/update/delete` mengembalikan `false` untuk semua peran termasuk `admin`; tidak ada rute ubah/hapus; trigger DB tetap berlaku walau lewat SQL langsung. |
| Rekam jejak perubahan: pelaku, waktu, objek, tindakan, nilai sebelum/sesudah, alasan | Kolom `actor_id`, `actor_nama`, `actor_peran`, `created_at` (WIB), `event`, `auditable_type`/`auditable_id`, `before`, `after`, `reason` — sudah ada sejak Tahap 1, dilengkapi snapshot pelaku pada Tahap 4. |
| Perubahan pada tugas, status verifikasi, skor, KPI, dokumen, serta hak akses | Trait `Auditable` (12 model setelah `Meeting` ditambahkan) + listener event Spatie untuk pemberian/pencabutan peran & izin. |
| Keamanan: wajib alasan saat pejabat berwenang mengubah skor, target, bobot, atau status yang sudah terverifikasi | Penjaga deklaratif di trait `Auditable`: model menyebut kolom sensitif; `save()` tanpa `denganAlasan()` ditolak `DomainException` di lapisan model, bukan hanya di formulir. |
| (tambahan, diminta pengguna) layar penelusuran audit | `/audit` (daftar + filter + paginasi), `/audit/{event}` (detail sebelum/sesudah), `/audit/ekspor` (CSV nyata berisi baris audit). |

## 2. Pendekatan yang dipertimbangkan

| Pendekatan | Isi | Trade-off | Keputusan |
|---|---|---|---|
| **A. Aturan di lapisan model + satu layar audit generik** | Trait `Auditable` menolak perubahan kolom sensitif tanpa alasan; diff dihitung saat render dari JSON `before`/`after`; satu tabel `audit_events` untuk semua objek | Query per kolom memakai `JSON_EXTRACT` (tidak seindeks); diff dihitung saat tampil | **Dipilih** |
| B. Tabel anak per field `audit_changes` | Satu baris audit punya banyak baris `kolom`/`sebelum`/`sesudah`; query per field jadi SQL biasa | Tabel baru + trigger append-only kedua, tulis 1+N baris, duplikasi JSON yang sudah ada, butuh migrasi data 11 model | Ditolak: keuntungannya belum ada yang meminta |
| C. `spatie/laravel-activitylog` | Paket pihak ketiga menggantikan trait | Menulis ke tabelnya sendiri tanpa trigger append-only; kolom `reason` wajib harus ditambal; membuang trait + 11 model + tes yang sudah hijau | Ditolak |

Keputusan pengguna: **A**.

## 3. Perubahan skema

### 3.1 `audit_events` (migrasi baru, tanpa mengubah baris lama)

| Kolom | Tipe | Aturan |
|---|---|---|
| actor_nama | string(150) | nullable, snapshot nama pelaku saat peristiwa terjadi |
| actor_peran | string(20) | nullable, snapshot peran sistem (`pengurus`/`de`/`staf`/`admin`) |

Perubahan constraint:

- `actor_id` → `restrictOnDelete` (sebelumnya `nullOnDelete`). Menghapus akun yang punya jejak audit ditolak database.
- Indeks tambahan: `index(created_at)` untuk filter periode, `index(actor_nama)` tidak perlu (kardinalitas rendah); pencarian teks memakai `LIKE` pada `reason`/`actor_nama` dengan batas 100 karakter.

Baris lama: `actor_nama`/`actor_peran` dibiarkan `null`; layar menampilkan `actor_id` sebagai gantinya. Tidak ada penulisan ulang baris audit (append-only).

Sumber snapshot: `actor_nama` = `users.name` akun yang login (identitas login, bukan `personel.nama_lengkap`), `actor_peran` = `User::peranSistem()->value`. Akun saat ini memegang tepat satu peran; bila kelak multi-peran, yang tercatat hanya peran pertama dan hal ini dicatat di batasan (bagian 13).

### 3.2 Tidak ada tabel baru

Diff, filter, dan ekspor membaca `audit_events` apa adanya.

## 4. Penjaga wajib alasan

### 4.1 Mekanisme

Trait `Auditable` memperoleh:

```php
/** @return list<string> kolom yang perubahannya wajib alasan */
protected function kolomWajibAlasan(): array { return []; }

/** Kondisi tambahan; default: setiap perubahan kolom di atas wajib alasan. */
protected function wajibAlasanUntuk(string $kolom, mixed $nilaiBaru): bool { return true; }
```

Pada event `saving` (didaftarkan trait, sehingga berjalan **sebelum** hook `saving` milik model):

1. Hanya untuk `exists === true` (pembuatan baris tidak butuh alasan).
2. Ambil `array_keys($this->getDirty())`; iriskan dengan `kolomWajibAlasan()`.
3. Untuk setiap kolom yang beririsan, panggil `wajibAlasanUntuk($kolom, $this->getAttribute($kolom))`.
4. Bila ada yang `true` dan `alasanAudit` kosong → `DomainException('Perubahan <kolom> wajib menyertakan alasan.')`.

Karena trait mendaftar lebih dulu, kolom turunan yang dihitung di hook model (`skor_total`, `kategori`, `diverifikasi_pada`) belum berubah saat pemeriksaan, sehingga tidak menimbulkan false positive.

### 4.2 Daur hidup `alasanAudit`

Alasan berlaku untuk **satu** perubahan. Trait mengosongkan `alasanAudit` setelah baris audit tertulis (`created`/`updated`/`deleted`), sehingga `save()` kedua pada instance yang sama kembali ditolak bila tidak memanggil `denganAlasan()` lagi. Tanpa ini, alasan lama akan menempel dan masuk ke baris audit berikutnya sebagai alasan palsu pada tabel yang tidak bisa dikoreksi.

### 4.3 Matriks kolom sensitif

| Model | Kolom wajib alasan | Kondisi |
|---|---|---|
| `Review` | `skor_kelengkapan`, `skor_kejelasan`, `skor_akurasi`, `skor_sistematika`, `skor_tindak_lanjut`, `bobot_*` (5 kolom), `adalah_final` | selalu |
| `KpiResult` | `skor_*` (5), `bobot_*` (5), `status`, `rule_version` | selalu (perubahan setelah Final sudah ditolak terpisah) |
| `JobDescription` | `target`, `indikator`, `frekuensi`, `kategori`, `uraian`, `berlaku_mulai` | hanya saat `ditetapkan_pada` masih `null`; setelah ditetapkan perubahan sudah ditolak `RuntimeException` |
| `Assignment` | `status`, `progres`, `tenggat`, `prioritas` | `status` wajib alasan bila status lama final (`terverifikasi`/`ditutup`/`dibatalkan`) atau status baru `terverifikasi`; `progres`/`tenggat`/`prioritas` wajib alasan **hanya bila status lama sudah final**. Di luar itu bebas (alasan tidak diminta). `alasan_pembatalan` tetap jalur lama |
| `Report` | `status` | hanya bila status baru `terverifikasi`, `ditutup`, atau `dibatalkan` |
| `Document` | `status`, `klasifikasi` | `status` wajib alasan hanya bila status baru `final` atau `diarsipkan`; `klasifikasi` selalu (mengubah tingkat kerahasiaan) |
| `Personel`, `Jabatan`, `Bidang`, `ActivityLog`, `Feedback`, `ActionItem`, `Meeting` | — | tetap diaudit, alasan tidak diwajibkan (alasan sudah jadi kolom domain) |

`Meeting` ditambahkan ke daftar model `Auditable` (menutup celah yang tercatat di ceklis Tahap 3).

### 4.4 Formulir

Setiap formulir yang menyentuh kolom di atas wajib menyertakan input `alasan` (`required|string|min:10|max:500`) dan meneruskannya lewat `denganAlasan()`. Validasi form adalah lapisan pesan ramah; penolakan sebenarnya tetap di model. Pada Tahap 4 hanya dua formulir yang sudah ada yang terkena (`/master/job-description` ubah & tetapkan); formulir lain menyusul bersama modulnya di Tahap 5–10.

### 4.5 Seeder

`DemoSeeder` dan `JobDescriptionSeeder` memakai `updateOrCreate` pada model yang kini dijaga. Keduanya diubah ke `firstOrNew` + `fill` + `denganAlasan('Data awal/demo')` + `save()`, dan `JobDescriptionSeeder` tidak lagi menimpa JD yang sudah `ditetapkan_pada` terisi. Ini menjaga `php artisan migrate:fresh --seed` tetap jalan.

## 5. Cakupan audit per peran

| Peran | Cakupan |
|---|---|
| Administrator | seluruh peristiwa, termasuk `auth.*`, `akses.*`, dan master data |
| Pengurus | seluruh peristiwa (Read; tanpa ekspor) |
| Direktur Eksekutif | **operasional saja**: `Assignment`, `ActivityLog`, `Report`, `Document`, `Review`, `Feedback`, `ActionItem`, `KpiResult`, `Meeting`; tanpa `auth.*`, `akses.*`, master personel/JD, dan akun |
| Staf | tidak punya `audit.view` → 403 pada semua rute audit |

Izin: `audit.view` ditambahkan ke peran **DE** di `PeranIzinSeeder` (Pengurus & Admin sudah punya). `audit.export` tetap hanya Admin. Jumlah izin total tidak berubah (56); yang berubah hanya pemetaan peran.

Penegakan: middleware `izin:audit.view` / `izin:audit.export` pada rute, ditambah `AuditEvent::scopeTerlihatOleh(User)` yang mempersempit kueri untuk DE. Policy `AuditEventPolicy::viewAny` baru (saat ini hanya `view`).

## 6. Rute & layar

| Metode | Rute | Nama | Middleware |
|---|---|---|---|
| GET | `/audit` | `audit.index` | `izin:audit.view` |
| GET | `/audit/ekspor` | `audit.ekspor` | `izin:audit.export` |
| GET | `/audit/{auditEvent}` | `audit.show` | `izin:audit.view` |

Rute statis `/audit/ekspor` didaftarkan sebelum `/audit/{auditEvent}`.

### 6.1 Daftar (`/audit`)

Kolom tabel: waktu (WIB), pelaku (`actor_nama` + peran), tindakan (`event` berlabel), objek (label kelas + kode/nama bila dapat diselesaikan), alasan (dipotong 80 karakter), tautan detail.

Filter (semua divalidasi `FormRequest`, nilai tak dikenal ditolak):

- `q` — cari pada `reason`, `actor_nama`, `auditable_type` (maks 100 karakter)
- `tindakan` — allowlist dari daftar event yang dikenal + `auth.*`/`akses.*`
- `objek` — allowlist nama pendek model (`assignment`, `report`, `document`, `review`, `kpi`, `meeting`, `personel`, `jabatan`, `bidang`, `jd`, `activity_log`, `feedback`, `action_item`, `user`) ditambah nilai khusus `akses` yang berarti `event LIKE 'akses.%'`. `objek=user` **mengecualikan** baris `akses.*`; keduanya tidak tumpang tindih.
- `aktor` — id pengguna (integer, harus ada di tabel `users`)
- `dari` / `sampai` — tanggal `Y-m-d`, diubah ke rentang WIB
- `urut` — `waktu` (default) atau `tindakan`, dipetakan lewat `match`; `arah` — `asc`/`desc`
- 50 baris per halaman, `withQueryString()`

### 6.2 Detail (`/audit/{event}`)

- Kepala: waktu WIB, pelaku (nama + peran + id), tindakan, objek (tautan ke halaman objek bila rutenya ada), IP, user agent, alasan.
- Tabel perubahan: `kolom` (label Indonesia), `sebelum`, `sesudah`. Nilai enum dipetakan ke label; nilai `null` ditampilkan `—`; JSON/array ditampilkan sebagai JSON terformat dalam `<pre>`.
- Untuk `created`: seluruh kolom `after`. Untuk `deleted`: seluruh kolom `before`. Untuk `updated`: hanya kolom yang berubah.
- Kolom tersamar untuk peran selain Admin: `nip`, `telepon`, `password`, `remember_token`, `klarifikasi` → ditampilkan sebagai `••••` dengan catatan "disamarkan".
- Tanpa tombol ubah/hapus di mana pun.

### 6.3 Ekspor (`/audit/ekspor`)

Streaming CSV (`no-store`), memakai filter yang sama dengan daftar, dibatasi 5.000 baris terbaru.

`/ekspor/audit` yang lama **diganti**: rute `modul.ekspor` untuk `modul=audit` diarahkan ulang (`redirect()->route('audit.ekspor', request()->query())`, 302) sehingga hanya ada satu permukaan ekspor audit. Rute generik `/ekspor/{modul}` tetap untuk 11 modul lain; tautan "Unduh rekapitulasi" pada `modul/show.blade.php` tetap, dan untuk modul audit akan mengalihkan ke ekspor audit yang sebenarnya.

Baris pertama: metadata konteks (filter yang dipakai, periode, waktu ekspor WIB, sumber data) sesuai PRD D.6. Baris berikutnya: `waktu, pelaku, peran, tindakan, objek, objek_id, alasan, ip`.

Setiap ekspor dicatat sebagai `ekspor.audit` (sudah otomatis lewat `PencatatAudit` di `EksporController` bila memakai rute `/ekspor/{modul}`; rute `/audit/ekspor` memanggil `PencatatAudit::catat` sendiri).

## 7. Audit perubahan hak akses

- `config/permission.php` → `events_enabled = true`.
- Listener di `AppServiceProvider` (atau `EventServiceProvider`) untuk empat event Spatie:

| Event Spatie | Nama audit |
|---|---|
| `RoleAttached` | `akses.peran_diberikan` |
| `RoleDetached` | `akses.peran_dicabut` |
| `PermissionAttached` | `akses.izin_diberikan` |
| `PermissionDetached` | `akses.izin_dicabut` |

- Isi audit: `actor_id` = pengguna yang sedang login (bukan subjek), `auditable_type`/`auditable_id` = model yang menerima perubahan (biasanya `User`), `after`/`before` memuat daftar nama peran/izin, `reason` = null (alasan belum diwajibkan untuk hak akses).
- **Hanya perubahan yang dilakukan manusia yang dicatat.** Listener langsung keluar bila `Auth::check()` bernilai `false`. Akibatnya `php artisan migrate:fresh --seed` dan sinkronisasi `Role::syncPermissions()` di `PeranIzinSeeder` **tidak** menghasilkan baris audit, sehingga jejak tidak dipenuhi derau yang tidak bisa dihapus. Ini juga mencegah kegagalan tulis audit membatalkan seeding.
- Kegagalan menulis audit tidak boleh menggagalkan perubahan hak akses? **Tidak** — di sini audit bersifat wajib: listener memakai `AuditEvent::create` langsung tanpa penangkap, sehingga kegagalan menulis menggagalkan transaksi. Perubahan hak akses tanpa jejak tidak boleh terjadi.
- Belum ada layar manajemen akun (Admin #2 masih fondasi), jadi pada Tahap 4 listener hanya teruji lewat tes, bukan lewat UI.

## 8. Label & penyamaran

`App\Support\LabelAudit`:

- `tindakan(string $event): string` — label Indonesia untuk `created`/`updated`/`deleted`, `auth.*`, `akses.*`, `ekspor.*`, `identitas.*`; nilai tak dikenal dikembalikan apa adanya.
- `objek(string $class): string` — nama pendek + label modul.
- `kolom(string $kolom): string` — label Indonesia untuk kolom yang lazim (`status`, `skor_total`, `target`, `bobot_kelengkapan`, …); fallback `Str::headline`.
- `nilai(mixed $nilai, ?string $cast): string` — enum → `label()`, boolean → Ya/Tidak, tanggal → `d/m/Y H:i` WIB, array → JSON.

## 9. Penutupan celah append-only

| Celah | Penutupan |
|---|---|
| `TRUNCATE` tidak memicu trigger `BEFORE DELETE` di MySQL/MariaDB | Tidak bisa ditutup trigger. Ditutup lewat hak database: user aplikasi hanya `SELECT, INSERT` pada `audit_events`; `DROP`/`ALTER`/`TRUNCATE` hanya milik DBA. Didokumentasikan di `docs/operasional/keamanan-audit.md` beserta perintah `GRANT`/`REVOKE` contoh. |
| `actor_id` `nullOnDelete` mengubah baris audit | `restrictOnDelete` + snapshot `actor_nama`/`actor_peran`. |
| Kode aplikasi bisa memanggil `DB::table('audit_events')->delete()` | Trigger DB menolak. Diuji. |
| `AuditEvent::create` dipakai bebas | Tetap perlu (layanan audit harus menulis). Tidak ada API publik untuk mengubah. |

## 10. Pemetaan OWASP (`laravel-owasp-security`)

| Butir | Penerapan |
|---|---|
| A01 Broken Access Control | Middleware `izin:audit.*` + `AuditEventPolicy::viewAny/view` + `scopeTerlihatOleh` untuk DE; tidak ada rute ubah/hapus. |
| A02 Cryptographic Failures | Tidak ada rahasia baru; kolom sensitif disamarkan di tampilan. |
| A03 Injection | Filter lewat `FormRequest` + allowlist; `orderBy` dari `match`; pencarian memakai binding `LIKE`. |
| A04 Insecure Design | Wajib alasan ditegakkan di model; audit hak akses gagal = transaksi gagal. |
| A05 Security Misconfiguration | Tidak berubah. |
| A06 Vulnerable Components | `composer audit` + `npm audit` pada verifikasi. |
| A07 Auth Failures | Tidak berubah; peristiwa `auth.*` tetap tercatat. |
| A08 Integrity Failures | `@csrf` pada formulir filter; trigger DB append-only. |
| A09 Logging | Tahap ini adalah A09 itu sendiri; kegagalan tulis audit pada jalur hak akses tidak dimaafkan. |
| A10 SSRF | Tidak ada permintaan keluar. |
| XSS | Blade `{{ }}`; JSON diff dirender lewat `{{ }}` di dalam `<pre>`, tanpa `{!! !!}`. |
| IDOR | `Route::model` + Policy; DE dibatasi kueri, bukan hanya tampilan. |

## 11. Kriteria penerimaan

1. `audit_events` menolak `UPDATE`/`DELETE` lewat model (RuntimeException) dan lewat `DB::table()` (QueryException dari trigger).
2. `AuditEventPolicy` menolak `update`/`delete` bahkan untuk Administrator.
3. Menghapus akun yang punya jejak audit ditolak (FK `restrictOnDelete`); `actor_nama`/`actor_peran` tetap terisi pada baris lama.
4. `Review::save()` yang mengubah salah satu skor tanpa `denganAlasan()` melempar `DomainException`; dengan alasan, tersimpan dan `audit_events.reason` terisi.
5. `KpiResult` yang mengubah bobot tanpa alasan ditolak; `JobDescription` draf yang mengubah `target` tanpa alasan ditolak.
6. `Assignment` yang mengubah status dari `terverifikasi` tanpa alasan ditolak; perubahan status dari `aktif` ke `dalam_proses` tanpa alasan **diterima**; mengubah `progres` pada tugas `terverifikasi` tanpa alasan ditolak, sedangkan pada tugas `dalam_proses` diterima.
7. `Report` yang mengubah status ke `terverifikasi` tanpa alasan ditolak; `Document` ke `final` tanpa alasan ditolak.
8. `Meeting` kini menghasilkan baris audit saat dibuat/diubah.
9. Pemberian peran lewat `assignRole()` saat ada sesi login menghasilkan baris `akses.peran_diberikan` dengan pelaku = pengguna yang login; `assignRole()` tanpa sesi (console/seeder) tidak menghasilkan baris.
10. DE dapat membuka `/audit` dan hanya melihat peristiwa operasional; peristiwa `auth.login` milik orang lain tidak tampil dan `/audit/{id}` untuk peristiwa di luar cakupannya menghasilkan 403.
11. Staf mendapat 403 pada `/audit`.
12. Admin dapat memfilter daftar (tindakan, objek, aktor, rentang tanggal), membuka detail, dan melihat tabel sebelum/sesudah dengan label Indonesia. `objek=akses` hanya mengembalikan `akses.*`; `objek=user` tidak memuatnya.
13. `/audit/ekspor` menghasilkan CSV berisi baris audit (bukan placeholder) dan mencatat `ekspor.audit`; Staf 403, DE 403 (tanpa `audit.export`). `/ekspor/audit` mengalihkan (302) ke `/audit/ekspor`.
14. Alasan tidak menempel: `denganAlasan()` lalu `save()`, kemudian `save()` lagi tanpa alasan pada kolom sensitif → ditolak.
15. `php artisan test`, `pint --test`, `composer validate`, `npm run build` hijau.

## 12. Non-tujuan

- Manajemen akun & layar konfigurasi RBAC (Admin #2 dan #4) — Tahap 4 hanya mencatat perubahannya, tidak menyediakan UI-nya.
- Retensi/penghapusan audit otomatis, partisi tabel, pengarsipan dingin.
- Anomali detection, alert real-time, integrasi SIEM.
- Tanda tangan kriptografis per baris audit (hash chain) — dicatat sebagai usulan lanjutan.
- Layar audit untuk Staf.

## 13. Batasan yang diketahui

1. Diff dihitung saat render; halaman detail peristiwa `created`/`deleted` pada model berkolom banyak menjadi tabel panjang (dipotong 200 baris dengan catatan).
2. `Review` menurunkan status `adalah_final` review lain lewat `toBase()->update()` yang melewati event model; perubahan turunan itu hanya tercatat pada baris audit review baru. Alternatif (mengaudit tiap baris turunan) menambah derau tanpa nilai telusur.
3. Filter per kolom tertentu (mis. "semua perubahan `skor_total`") tidak tersedia; pencarian terbatas pada `reason`, `actor_nama`, dan jenis objek. Bila dibutuhkan, jalur B (tabel anak) bisa ditambahkan tanpa mengubah baris lama.
4. User database aplikasi saat ini `root` (XAMPP). Pencabutan hak `DROP`/`TRUNCATE` wajib dilakukan saat deployment produksi.
5. `actor_peran` menyimpan peran pertama saja; akun multi-peran belum ada di matriks peran saat ini.
6. Perubahan hak akses yang dilakukan tanpa sesi login (seeder, artisan) tidak menghasilkan baris audit, sesuai keputusan bagian 7.
