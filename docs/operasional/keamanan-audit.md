# Keamanan Jejak Audit — Kewajiban Deployment

Tanggal: 14 November 2026
Berlaku untuk: `audit_events` (Tahap 1–4).

Jejak audit bersifat **append-only**: satu baris yang sudah tertulis tidak boleh berubah
dan tidak boleh hilang. Dokumen ini mencatat apa yang sudah ditegakkan aplikasi, apa yang
masih terbuka, dan apa yang wajib dilakukan saat ERP HIMKI dipasang di server produksi.

## 1. Lapisan yang sudah aktif

| Lapisan | Penegakan | Bukti |
|---|---|---|
| Model | `AuditEvent::booted()` melempar `RuntimeException` pada `updating` dan `deleting` | `SkemaTahap1Test`, `Tahap4AuditTrailTest` |
| Basis data | Trigger `no_update` dan `no_delete` pada `audit_events` menolak `UPDATE`/`DELETE` termasuk lewat SQL langsung | `Tahap4AuditTrailTest` |
| Otorisasi | `AuditEventPolicy::create/update/delete` mengembalikan `false` untuk **semua** peran, termasuk Administrator | `Tahap4AuditTrailTest` |
| Rute | Tidak ada rute ubah/hapus. Hanya `GET /audit`, `GET /audit/ekspor`, `GET /audit/{id}` | `php artisan route:list --name=audit` |
| Identitas pelaku | `actor_id` memakai `restrictOnDelete`; `actor_nama`/`actor_peran` disalin saat peristiwa terjadi | `Tahap4AuditTrailTest` |
| Audit hak akses | Perubahan peran/izin oleh pengguna login ditulis tanpa penangkap kesalahan: gagal tulis = gagal perubahan | `Tahap4AuditTrailTest` |

## 2. Celah yang tidak bisa ditutup dari sisi aplikasi

**`TRUNCATE TABLE` tidak memicu trigger `BEFORE DELETE` di MySQL/MariaDB.** Siapa pun yang
memegang hak `DROP` atau `TRUNCATE` pada basis data dapat menghapus seluruh jejak tanpa
meninggalkan bukti. Ini tidak dapat ditutup oleh trigger, Policy, maupun kode aplikasi.

Penutupnya adalah **hak database**. Selama pengembangan di XAMPP, aplikasi memakai `root`
sehingga celah ini terbuka. Di produksi, langkah bagian 3 wajib dijalankan.

## 3. Kewajiban saat deployment produksi

### 3.1 Pisahkan akun database aplikasi dari akun DBA

Buat akun khusus aplikasi (contoh `erp_himki_app`) yang **tidak** memiliki hak `DROP`,
`ALTER`, `TRUNCATE`, maupun `DELETE` pada `audit_events`.

```sql
-- Dijalankan oleh DBA, bukan oleh aplikasi.
CREATE USER 'erp_himki_app'@'%' IDENTIFIED BY '<kata-sandi-kuat>';

GRANT SELECT, INSERT, UPDATE, DELETE ON erp_himki.* TO 'erp_himki_app'@'%';

-- Jejak audit: hanya baca dan tambah.
REVOKE ALL PRIVILEGES ON erp_himki.audit_events FROM 'erp_himki_app'@'%';
GRANT SELECT, INSERT ON erp_himki.audit_events TO 'erp_himki_app'@'%';

FLUSH PRIVILEGES;
```

Migrasi (`php artisan migrate`) dijalankan dengan akun DBA terpisah, bukan dengan akun
aplikasi, karena migrasi memerlukan `ALTER`/`CREATE`.

### 3.2 Verifikasi setelah pencabutan hak

```sql
-- Harus gagal dengan ERROR 1142 (command denied).
DELETE FROM erp_himki.audit_events WHERE id = 1;

-- Harus gagal dengan ERROR 1142 (command denied).
TRUNCATE TABLE erp_himki.audit_events;
```

Catat hasil kedua perintah di berita acara serah terima.

### 3.3 Jangan pernah memberi akun aplikasi hak `FILE`

`SELECT ... INTO OUTFILE` dan `LOAD DATA` memakai hak `FILE`. Akun aplikasi tidak
membutuhkannya; ekspor audit dilakukan aplikasi lewat CSV, bukan lewat MySQL.

## 4. Retensi dan pencadangan

- Baris audit **tidak dihapus** oleh aplikasi. Tidak ada perintah retensi otomatis.
- Pencadangan basis data wajib menyertakan tabel `audit_events`. Cadangan yang dapat
  dipulihkan tetapi tidak memuat jejak audit tidak memenuhi syarat PRD bagian 12.
- Pemulihan (restore) dari cadangan adalah satu-satunya jalur yang sah untuk "menghapus"
  baris audit, dan itu berarti kehilangan seluruh peristiwa setelah titik cadangan.
- Bila kelak dibutuhkan pengarsipan dingin, gunakan tabel arsip terpisah yang juga
  append-only; jangan memindahkan baris keluar dari `audit_events` secara destruktif.

## 5. Yang tercatat dan yang tidak

| Tercatat | Tidak tercatat |
|---|---|
| Perubahan 12 model domain (tugas, log aktivitas, laporan, dokumen, review, umpan balik, tindak lanjut, KPI, rapat, personel, jabatan, bidang, job description) | Pembacaan data (read) — hanya perubahan yang dicatat |
| Peristiwa autentikasi: login, login gagal, logout, akses ditolak, blokir, ubah/atur ulang sandi | Perubahan hak akses tanpa sesi login (seeder, artisan, tinker) |
| Perubahan peran dan izin oleh pengguna login | Percobaan `TRUNCATE`/`DROP` (tidak terlihat oleh aplikasi) |
| Ekspor per modul (`ekspor.*`) | Isi berkas yang diekspor |

## 6. Pemeriksaan berkala

Jalankan minimal bulanan, atau sebelum rapat pleno:

```sql
-- Baris tanpa pelaku: hanya sah bila berasal dari proses sistem (seeder/artisan).
SELECT COUNT(*) FROM erp_himki.audit_events WHERE actor_id IS NULL;

-- Peristiwa keamanan yang perlu ditinjau.
SELECT created_at, actor_nama, event, reason, ip_address
FROM erp_himki.audit_events
WHERE event IN ('auth.login_gagal', 'auth.login_diblokir', 'auth.akses_ditolak', 'identitas.ditolak')
ORDER BY created_at DESC
LIMIT 100;
```

Layar `/audit` menyediakan filter yang sama tanpa akses SQL. Ekspor CSV (`/audit/ekspor`)
dibatasi 5.000 baris terbaru per unduhan dan mencatat dirinya sebagai `ekspor.audit`.
