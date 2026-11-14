# Tahap 3 — Modul Master Data Personel, Jabatan, & Job Description

Tanggal: 14 November 2026
Acuan: `Proposal_dan_PRD_ERP_HIMKI_Lengkap_Final.docx` (D.1 Master Personel, D.2 Master Job Description, E.2 matriks izin baris 9 & 11, D.3 prinsip akses), `fitur.pdf` Tahap 3, skill `laravel-owasp-security`.
Prasyarat: Tahap 1 (skema) + Tahap 2 (autentikasi & RBAC) selesai dan teruji.

## 1. Ruang lingkup

| Butir Tahap 3 (`fitur.pdf`) | Wujud implementasi |
|---|---|
| Master Personel & Jabatan: kelola ID personel, nama resmi, jabatan, bidang kerja, status aktif | Layar `/master/personel` (daftar + filter + cari), `/master/personel/{id}`, tambah, ubah, nonaktifkan. Master pendukung `/master/jabatan` dan `/master/bidang`. |
| Bidang kerja: Keuangan, Administrasi, Publikasi, HI/Pameran, TI, Operasional | `BidangSeeder` diganti menjadi 6 bidang tersebut. Bidang lama tidak dihapus (FK `restrictOnDelete`) tetapi ditandai `is_active = false`. |
| Keamanan: data personal hanya untuk pengguna berwenang sesuai kebutuhan kerja | Policy per baris: Staf hanya barisnya sendiri; DE & Pengurus baca lintas personel; Admin kelola penuh. Kolom kontak (`telepon`, `nip`) hanya tampil untuk pemilik baris dan Admin. |
| Kunci Identitas Otomatis: akun → Master Personel, nama & jabatan laporan terisi otomatis | `App\Support\IdentitasPelapor` menyelesaikan identitas dari sesi. Trait `MilikSesiPelapor` mengisi `personel_id` dan menolak nilai yang berbeda dari sesi. Kolom nama/jabatan tidak pernah berasal dari isian klien. |
| Keamanan: kunci kolom nama & jabatan dari sesi aktif untuk mencegah pemalsuan identitas | Trait di atas + partial Blade `identitas-terkunci` yang menampilkan identitas sebagai teks terkunci (tanpa input). Staf tidak memegang izin ubah Master Personel. |
| Master Job Description: tugas utama, umum, insidental, indikator, target dasar, frekuensi per jabatan | Layar `/master/job-description` (per jabatan), tambah, ubah, tetapkan, arsipkan, dan riwayat versi. |
| Keamanan: target & indikator ditetapkan sebelum periode penilaian, tidak berubah saat kerja berjalan | JD berversi dengan `berlaku_mulai`, `ditetapkan_pada`, `ditetapkan_oleh_id`, `induk_id`. Setelah ditetapkan, `uraian`/`indikator`/`target`/`frekuensi`/`kategori`/`berlaku_mulai` terkunci di level model. Perubahan wajib lewat versi baru dengan `berlaku_mulai` di masa depan. Hanya Admin (`personel.configure`) yang boleh menetapkan. |

## 2. Keputusan teknis

| Keputusan | Pilihan | Alasan |
|---|---|---|
| Daftar bidang kerja | 6 bidang dari `fitur.pdf` menggantikan 5 bidang lama | `fitur.pdf` Tahap 3 menyebut daftar eksplisit; daftar lama adalah usulan internal Tahap 1. Baris lama dinonaktifkan, bukan dihapus, agar personel historis tetap valid. |
| Otoritas menetapkan JD | Izin baru `personel.configure` (AksiIzin::Konfigurasi) hanya untuk Admin | PRD E.2 baris 9: Admin = C,R,U konfigurasi master; DE = R,U usulan target/indikator. DE tetap boleh membuat/mengubah **draf**, tidak boleh menetapkan. |
| Freeze JD | Penanda `ditetapkan_pada` (bukan flag terpisah) | Satu kolom menentukan status: `null` = draf (bebas diubah), terisi = ditetapkan (terkunci). Tidak ada dua sumber kebenaran. |
| Versi JD | `versi` + `induk_id` (self FK) + `berlaku_mulai` | Riwayat tidak dihapus (PRD: versi lama tetap tersimpan); evaluasi memakai versi yang berlaku pada periode tersebut. |
| Kunci identitas | Trait model + service, bukan validasi form saja | Validasi form bisa dilewati dengan request langsung. Pengisian dan penolakan terjadi di event `saving` model, sehingga berlaku untuk semua jalur tulis. |
| Audit master data | `Personel`, `Jabatan`, `Bidang`, `JobDescription` memakai trait `Auditable` | Perubahan master memengaruhi evaluasi; wajib tertelusur (PRD D.4, Tahap 4). |
| Hapus data | Tidak ada hapus; `status_aktif` / `is_active` | Konsisten dengan Tahap 1. FK `restrictOnDelete` menjaga riwayat. |

## 3. Delta skema

### 3.1 `job_descriptions` (kolom baru)

| Kolom | Tipe | Aturan |
|---|---|---|
| versi | unsignedSmallInteger | default 1, NN |
| berlaku_mulai | date | nullable (wajib saat ditetapkan) |
| ditetapkan_pada | timestamp | nullable |
| ditetapkan_oleh_id | FK `users` | nullable, `nullOnDelete` |
| induk_id | FK `job_descriptions` | nullable, `nullOnDelete` |

Indeks: `unique(jabatan_id, kode, versi)`, `index(jabatan_id, berlaku_mulai)`.
`kode` tidak lagi unik global (satu kode boleh punya banyak versi); keunikan pindah ke triple di atas.

### 3.2 `personel`

Tidak ada kolom baru. `nip` tetap `unique` nullable, `user_id` tetap `unique` (satu akun = satu personel).
Aturan tambahan di level aplikasi: `nama_lengkap` dan `jabatan_id` tidak dapat diubah oleh Staf (Policy) dan setiap perubahan tercatat di audit.

### 3.3 Tabel pendukung

`jabatans` dan `bidangs` tidak berubah struktur. Keduanya memperoleh audit trail.

## 4. Matriks otorisasi

| Aksi | Pengurus | DE | Staf | Admin |
|---|---|---|---|---|
| Lihat daftar personel | semua baris | semua baris | baris sendiri | semua baris |
| Tambah / ubah personel | — | — | — | ya |
| Nonaktifkan personel | — | — | — | ya |
| Ekspor personel | ya | ya | — | ya |
| Lihat jabatan & bidang | ya | ya | ya | ya |
| Tambah / ubah jabatan & bidang | — | — | — | ya |
| Lihat Job Description | semua jabatan | semua jabatan | jabatan sendiri | semua jabatan |
| Buat / ubah draf JD | — | ya | — | ya |
| Tetapkan / arsipkan JD | — | — | — | ya |

Izin yang dipakai (semua di bawah modul `personel`):

- `personel.view` — Pengurus, DE, Staf (baris sendiri), Admin
- `personel.create` — DE, Admin
- `personel.update` — DE, Admin
- `personel.deactivate` — DE, Admin
- `personel.configure` — Admin (**baru**)
- `personel.export` — Pengurus, DE, Admin

`PersonelPolicy`, `JabatanPolicy`, `BidangPolicy`, `JobDescriptionPolicy` menegakkan aturan baris dan aksi di atas. Middleware `izin:personel.*` menegakkan tingkat modul.

## 5. Alur kunci identitas

```
Formulir laporan (Tahap 8) / log aktivitas (Tahap 6)
  └─ partial identitas-terkunci
       ├─ nama       = personel.nama_lengkap   (teks, bukan input)
       ├─ jabatan    = personel.jabatan.nama   (teks, bukan input)
       └─ bidang     = personel.bidang.nama    (teks, bukan input)
  └─ POST menyertakan personel_id (hidden, dari sesi)
       └─ MilikSesiPelapor::saving
            ├─ tanpa sesi (console/seeder)  → biarkan nilai eksplisit
            ├─ personel_id kosong           → isi dari sesi
            └─ personel_id ≠ sesi           → AuthorizationException (403 + audit)
```

Konsekuensi: nama dan jabatan pada laporan selalu berasal dari Master Personel, bukan dari isian pengirim. Memalsukan `personel_id` tidak mengubah identitas yang tampil karena tampilan membaca relasi `personel`.

## 6. Daur hidup Job Description

```
draf  ──tetapkan──►  ditetapkan (terkunci)  ──arsipkan──►  diarsipkan
  │                         │
  │                         └── versi baru (induk_id = versi lama, berlaku_mulai > hari ini)
  └── ubah bebas (selama ditetapkan_pada masih null)
```

Aturan yang ditegakkan model (`JobDescription::booted`):

1. `ditetapkan_pada` hanya boleh diisi sekali; tidak boleh dikosongkan atau diubah.
2. Saat menetapkan: `indikator`, `target`, `frekuensi`, dan `berlaku_mulai` wajib terisi.
3. Setelah `ditetapkan_pada` terisi, perubahan pada `jabatan_id`, `kode`, `kategori`, `uraian`, `indikator`, `target`, `frekuensi`, `berlaku_mulai`, `versi` ditolak (`RuntimeException`).
4. `is_active` tetap boleh diubah (arsip) meskipun sudah ditetapkan.
5. `berlaku_mulai` versi baru harus setelah `berlaku_mulai` versi induk.

`JobDescription::scopeBerlakuPada($tanggal)` dipakai modul KPI (Tahap 10) untuk memilih versi yang berlaku pada periode penilaian.

## 7. Rute & layar

| Metode | Rute | Nama | Middleware |
|---|---|---|---|
| GET | `/master/personel` | `master.personel.index` | `izin:personel.view` |
| GET | `/master/personel/{personel}` | `master.personel.show` | `izin:personel.view` |
| GET | `/master/personel/create` | `master.personel.create` | `izin:personel.create` |
| POST | `/master/personel` | `master.personel.store` | `izin:personel.create` |
| GET | `/master/personel/{personel}/edit` | `master.personel.edit` | `izin:personel.update` |
| PUT | `/master/personel/{personel}` | `master.personel.update` | `izin:personel.update` |
| PATCH | `/master/personel/{personel}/nonaktifkan` | `master.personel.nonaktifkan` | `izin:personel.deactivate` |
| GET | `/master/jabatan` | `master.jabatan.index` | `izin:personel.view` |
| POST/PUT | `/master/jabatan[/{jabatan}]` | `master.jabatan.*` | `izin:personel.update` |
| GET | `/master/bidang` | `master.bidang.index` | `izin:personel.view` |
| POST/PUT | `/master/bidang[/{bidang}]` | `master.bidang.*` | `izin:personel.update` |
| GET | `/master/job-description` | `master.jd.index` | `izin:personel.view` |
| GET | `/master/job-description/{jd}` | `master.jd.show` | `izin:personel.view` |
| GET/POST | `/master/job-description/create` | `master.jd.create/store` | `izin:personel.create` |
| GET/PUT | `/master/job-description/{jd}/edit` | `master.jd.edit/update` | `izin:personel.update` |
| PATCH | `/master/job-description/{jd}/tetapkan` | `master.jd.tetapkan` | `izin:personel.configure` |
| PATCH | `/master/job-description/{jd}/arsipkan` | `master.jd.arsipkan` | `izin:personel.configure` |
| POST | `/master/job-description/{jd}/versi-baru` | `master.jd.versi` | `izin:personel.configure` |

Semua rute berada di grup `['auth', 'aktif']`. Navigasi menambahkan tautan Master Data hanya bila akun memegang `personel.view`.

## 8. Validasi & keamanan (OWASP)

| Butir OWASP | Penerapan |
|---|---|
| A01 Broken Access Control | Middleware `izin:personel.*` + 4 Policy + scoping query (`Personel::visibleTo`) + penolakan `personel_id` palsu di trait. |
| A02 Cryptographic Failures | Tidak ada rahasia baru; kata sandi tetap ditangani Tahap 2. |
| A03 Injection | `$fillable` eksplisit; FormRequest `validated()`; tidak ada `whereRaw`/`orderByRaw` dari input. Urutan kolom daftar memakai whitelist. |
| A04 Insecure Design | Freeze JD ditegakkan di model, bukan hanya tombol UI; penetapan butuh izin terpisah. |
| A05 Security Misconfiguration | Tidak berubah; header keamanan tetap dari Tahap 2. |
| A06 Vulnerable Components | `composer audit` + `npm audit` dijalankan pada verifikasi. |
| A07 Auth Failures | Tidak berubah. |
| A08 Integrity Failures | `@csrf` pada seluruh formulir; `Auditable` mencatat before/after. |
| A09 Logging | Pembuatan/penetapan/arsip/penonaktifan personel dan JD tercatat di `audit_events`. |
| A10 SSRF | Tidak ada permintaan keluar. |
| XSS | Blade `{{ }}`; tidak ada `{!! !!}` dengan data pengguna. |
| Mass assignment | FormRequest membatasi field; `status_aktif` tidak bisa diubah lewat form ubah (hanya lewat aksi nonaktifkan). |
| IDOR | Rute model-binding + Policy; Staf mendapat 403 pada baris lain. |

## 9. Kriteria penerimaan

1. Admin dapat menambah, mengubah, dan menonaktifkan personel; setiap perubahan muncul di `audit_events`.
2. Personel baru wajib tertaut ke tepat satu akun; `user_id` dan `nip` duplikat ditolak dengan pesan validasi.
3. Staf hanya melihat baris personelnya sendiri; membuka baris lain menghasilkan 403.
4. Staf tidak dapat menambah, mengubah, atau menonaktifkan personel (403 walau memanggil rute langsung).
5. Pengurus dan DE melihat seluruh baris personel tetapi tidak dapat mengubahnya.
6. Daftar bidang aktif tepat: Keuangan, Administrasi, Publikasi, HI/Pameran, TI, Operasional.
7. Laporan baru tanpa `personel_id` otomatis terisi dari sesi; `personel_id` yang berbeda dari sesi ditolak dan dicatat.
8. Nama dan jabatan pada partial identitas berasal dari Master Personel, bukan input pengguna.
9. DE dapat membuat draf JD, tetapi 403 saat menetapkan; Admin dapat menetapkan.
10. Penetapan JD tanpa indikator/target/berlaku_mulai ditolak validasi.
11. Setelah ditetapkan, perubahan `uraian`/`indikator`/`target` ditolak di level model; versi baru dengan `berlaku_mulai` lebih baru berhasil dibuat.
12. Staf hanya melihat JD untuk jabatannya sendiri.
13. `php artisan test`, `pint --test`, `composer validate`, dan `npm run build` hijau.

## 10. Non-tujuan

- Formulir penugasan, log aktivitas, laporan, dan KPI (Tahap 5–10). Tahap 3 hanya menyediakan mekanisme kunci identitas dan master datanya.
- Notifikasi, impor massal CSV, dan unggah foto personel.
- Penghapusan permanen data master.
- Perhitungan bobot/indikator KPI (Tahap 10) — Tahap 3 hanya menyimpan definisinya.
