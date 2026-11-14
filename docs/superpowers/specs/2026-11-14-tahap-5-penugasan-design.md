# Tahap 5 — Modul Manajemen Penugasan & 3 Jalur Arahan Pimpinan

Tanggal: 14 November 2026
Acuan: `fitur.pdf` Tahap 5 (Formulir Penugasan, Tiga Jalur Instruksi, Modul Arahan & Action Items, State Machine Tugas), PRD `Proposal_dan_PRD_ERP_HIMKI_Lengkap_Final.docx` (E.3.1 tiga jalur, D.5 status standar, D.6 aturan bisnis, D.7 layanan notifikasi, FR-05/FR-10, AC-06/AC-14), skill `laravel-owasp-security`.
Prasyarat: Tahap 1 (skema), Tahap 2 (RBAC 56 izin), Tahap 3 (master personel/JD), Tahap 4 (audit trail + wajib alasan) selesai dan teruji (76 tes / 337 asersi).

## 1. Ruang lingkup

| Butir `fitur.pdf` Tahap 5 | Wujud implementasi |
|---|---|
| **Formulir Penugasan**: ID otomatis, sumber perintah, pemberi, PIC, kategori (Utama/Umum/Lintas Bidang/Insidental), target, prioritas, tenggat, dependensi | Layar `/tugas/buat` + `/tugas/{tugas}/ubah`. `kode` `TGS-YYYY-NNNN` dibuat model. `kategori`, `target_keluaran`, `prioritas`, `tanggal_mulai`, `tenggat`, `dependensi_id`, `pic_id`, `job_description_id` diisi pengguna. |
| Keamanan: sumber perintah & pemberi tugas tercatat otomatis sesuai akun pembuat | `sumber_perintah`, `pemberi_tugas_id`, `diteruskan_oleh_id` **dikeluarkan dari `$fillable`** dan diisi satu-satunya lewat `Assignment::tetapkanSumberPerintah()` yang membaca sesi. Formulir tidak punya input untuk ketiga kolom itu. |
| **Tiga Jalur Instruksi**: DE→staf, Pengurus→DE, Pengurus→staf + notifikasi otomatis ke DE | Jalur 1 & 3 = `sumber_perintah` hasil turunan peran pembuat (`de_langsung` / `pengurus_langsung`). Jalur 2 = DE meneruskan arahan: `sumber_perintah = pengurus_melalui_de`, `pemberi_tugas_id` = pemberi arahan, `diteruskan_oleh_id` = DE, ditautkan lewat kolom baru `assignments.sumber_arahan_id` → `feedback`. |
| Keamanan: arahan langsung Pengurus ke staf selalu terlihat DE | `FeedbackPolicy::view` sudah mengizinkan non-staf melihat semua baris, jadi DE melihat seluruh arahan Pengurus→staf. Ditambah notifikasi ke DE pada setiap arahan `asal = pengurus`, dan panel "Arahan Pengurus" di dashboard DE. Diuji lewat tes, bukan hanya tampilan. |
| **Modul Arahan & Action Items**: identitas pemberi, jabatan, timestamp, isi, lampiran, PIC, tenggat; pisahkan komentar biasa dari persetujuan formal | Layar `/arahan`. Snapshot `pemberi_jabatan` baru di `feedback`. `jenis` diperluas dengan nilai `persetujuan`; `jenis` lama (`komentar`) **tidak boleh** diubah menjadi `persetujuan` — persetujuan formal selalu baris baru yang tercatat terpisah. Action item dibuat dari arahan lewat `/arahan/{arahan}/tindak-lanjut` (PIC + tenggat). |
| Keamanan: komentar bukan persetujuan formal otomatis; item belum selesai ditahan sebagai tunggakan | Guard model `Feedback` menolak `persetujuan` dari peran selain Pengurus dan menolak konversi jenis. Tunggakan = panel dashboard 3 peran + `ActionItem::scopeTunggakan()`/`scopeLewatTenggat()` + `Assignment::scopeTerlambat()` yang sudah ada. |
| **State Machine Tugas**: Draft → Ditutup/Dibatalkan; Terverifikasi hanya DE/pemeriksa | Peta transisi eksplisit di `StatusPenugasan::bolehKe()` + penjaga `saving` di model (berlaku untuk controller, console, tinker). Peran diperiksa di Policy **dan** di model saat ada sesi. |
| Keamanan: Terverifikasi/Ditutup hanya peran berwenang; wajib alasan + bukti saat penutupan/pembatalan | `assignment.verify` (DE) untuk Terverifikasi & Ditutup; pembatalan oleh DE atau pemberi tugas. Alasan + bukti wajib di tiga lapis: FormRequest, model (`DomainException`), CHECK database. |

## 2. Pendekatan yang dipertimbangkan

### 2.1 Kanal notifikasi (dipilih pengguna)

| Pendekatan | Isi | Trade-off | Keputusan |
|---|---|---|---|
| A. Panel turunan | Dashboard DE menampilkan query `sumber_perintah = pengurus_langsung`; tanpa tabel baru | Tidak ada status baca, jadi DE #11 ("info arahan **baru**") tetap ⬜; AC-14 "DE mendapat notifikasi" tidak terpenuhi | Ditolak |
| **B. Tabel `notifications` bawaan Laravel + panel & lencana** | `Notifiable` (sudah ada di `User`) + migrasi `notifications`; notifikasi per akun dengan `read_at`; panel `/notifikasi` dan lencana jumlah belum dibaca di header | +1 migrasi, +2 kelas Notification, +1 layar, +tes | **Dipilih** |
| C. Audit + panel | Hanya jejak `audit_events` + daftar | Audit tidak punya penerima maupun status baca; bukan kanal pemberitahuan | Ditolak |

Alasan B sesuai dokumen: PRD E.3.1 menulis notifikasi ke Dashboard Personel (jalur 1), ke DE (jalur 2), dan real-time ke Dashboard DE (jalur 3); FR-10 dan AC-14 mengulanginya; `fitur.pdf` DE #11 memakai kata "arahan **baru**" yang menuntut status baca. Audit tetap dipakai **sebagai pelengkap** (bukti), bukan pengganti.

Catatan jujur: PRD D.12 menaruh "notifikasi" pada kolom *Tahap berikutnya*, bukan MVP. `fitur.pdf` Tahap 5 memintanya eksplisit, jadi dikerjakan sekarang.

### 2.2 Penegakan state machine

| Pendekatan | Isi | Trade-off | Keputusan |
|---|---|---|---|
| **A. Peta transisi di enum + penjaga `saving` di model** | `StatusPenugasan::bolehKe(self $tujuan): bool`; model menolak transisi tak sah, menuntut alasan/bukti, dan memeriksa peran bila ada sesi | Aturan peran ikut di model sehingga konsol tanpa sesi melewati pemeriksaan peran (legalitas transisi tetap ditegakkan) | **Dipilih** |
| B. Trigger database | `BEFORE UPDATE` membandingkan `OLD.status`/`NEW.status` | Logika transisi berlipat di SQL, sulit diuji, pesan galat tidak ramah; MySQL/MariaDB tidak punya CHECK lintas-baris | Ditolak |
| C. Kelas layanan `PenugasanWorkflow` | Semua transisi lewat satu service | Jalur tulis lain (seeder, tinker, `update()` massal) melewati aturan; bertentangan dengan preseden Tahap 4 yang menaruh penjaga di model | Ditolak |

Keputusan: **A**, dengan CHECK database sebagai jaring terakhir untuk kewajiban alasan/bukti (bukan untuk legalitas transisi).

### 2.3 Penyimpanan bukti penutupan/pembatalan

| Pendekatan | Isi | Trade-off | Keputusan |
|---|---|---|---|
| **A. Kolom teks rujukan bukti + FK dokumen opsional** | `bukti_penutupan`/`bukti_pembatalan` (teks, wajib), `bukti_dokumen_id` (FK `documents`, opsional, `restrictOnDelete`) | Bukti bisa berupa rujukan arsip saat modul unggah dokumen (Tahap 7) belum ada | **Dipilih** |
| B. Wajib FK dokumen | `bukti_dokumen_id` wajib saat menutup | Belum ada layar unggah dokumen; penutupan tugas mustahil dilakukan sampai Tahap 7 | Ditolak |
| C. Tabel `assignment_bukti` (banyak dokumen) | Pivot banyak-ke-banyak | Menambah tabel + layar untuk kebutuhan yang belum ada | Ditolak |

## 3. Perubahan skema

Satu migrasi: `2026_11_14_080001_add_tahap5_columns.php`. Tidak ada tabel domain baru; satu tabel infrastruktur (`notifications`).

### 3.1 `assignments`

| Kolom | Tipe | Aturan |
|---|---|---|
| `sumber_arahan_id` | FK → `feedback`, nullable, `nullOnDelete` | Jejak Jalur 2: arahan Pengurus yang diteruskan DE menjadi tugas |
| `alasan_penutupan` | text nullable | Wajib saat status → `ditutup` |
| `bukti_penutupan` | text nullable | Wajib saat status → `ditutup` |
| `bukti_pembatalan` | text nullable | Wajib saat status → `dibatalkan` (`alasan_pembatalan` sudah ada) |
| `bukti_dokumen_id` | FK → `documents`, nullable, `restrictOnDelete` | Rujukan berkas bukti bila sudah ada |

CHECK baru (lewat `CheckGuard`):

- `assignments_penutupan_lengkap`: `status <> 'ditutup' OR (alasan_penutupan IS NOT NULL AND trim(alasan_penutupan) <> '' AND bukti_penutupan IS NOT NULL AND trim(bukti_penutupan) <> '')`
- `assignments_pembatalan_bukti`: `status <> 'dibatalkan' OR (bukti_pembatalan IS NOT NULL AND trim(bukti_pembatalan) <> '')`

Baris lama tidak berubah: saat migrasi dijalankan tidak ada baris `ditutup`/`dibatalkan` (diperiksa sebelum menulis migrasi; bila ada, migrasi mengisi teks penanda `Data lama` lebih dulu).

### 3.2 `feedback`

| Kolom | Tipe | Aturan |
|---|---|---|
| `pemberi_jabatan` | string(100) nullable | Snapshot jabatan pemberi saat arahan dibuat (PRD E.3.1: "identitas pemberi, jabatan, timestamp") |

`jenis` diperluas: `arahan`, `komentar`, `masukan`, `koreksi`, **`persetujuan`**. Alter enum lewat `DB::statement` (MariaDB tidak punya `ALTER TYPE`).

CHECK baru:

- `feedback_persetujuan_pengurus`: `jenis <> 'persetujuan' OR asal = 'pengurus'`

### 3.3 `action_items`

| Kolom | Tipe | Aturan |
|---|---|---|
| `bukti_penutupan` | text nullable | Wajib saat status → `ditutup` (PRD D.5: "penutupan mencatat bukti/alasan") |

CHECK baru: `action_items_penutupan_bukti`: `status <> 'ditutup' OR (bukti_penutupan IS NOT NULL AND trim(bukti_penutupan) <> '')`.

### 3.4 `notifications` (bawaan Laravel)

`uuid` PK, `type`, `morphs('notifiable')`, `text data`, `timestamp read_at`, `timestamps`, indeks `(notifiable_type, notifiable_id, read_at)`.

## 4. Formulir penugasan & pencatatan sumber perintah otomatis

### 4.1 Kolom yang tidak boleh datang dari klien

`sumber_perintah`, `pemberi_tugas_id`, `diteruskan_oleh_id`, `diverifikasi_oleh`, `kode` dikeluarkan dari `$fillable`. Pengisian hanya lewat metode domain:

```php
public function tetapkanSumberPerintah(
    SumberPerintah $sumber,
    ?Personel $pemberi,
    ?Personel $penerus = null,
): static
```

`AssignmentPolicy::create` + `AssignmentController::store` memanggil ini **setelah** `validated()`, dari identitas sesi (`IdentitasPelapor::dariSesi()`), sehingga `$request->validated()` tidak pernah bisa menyentuhnya. Upaya mengirim field itu lewat formulir diabaikan (tidak ada di `$fillable`), dan bila nilai di DB berbeda dari turunan sesi pada `save()` berikutnya, model melempar `DomainException` — bukan menimpa diam-diam.

### 4.2 Turunan per peran pembuat

| Pembuat | `sumber_perintah` | `pemberi_tugas_id` | `diteruskan_oleh_id` | Syarat |
|---|---|---|---|---|
| DE, tanpa `sumber_arahan_id` | `de_langsung` | personel DE | null | Jalur 1 |
| DE, dengan `sumber_arahan_id` | `pengurus_melalui_de` | pemberi arahan (Pengurus) | personel DE | Jalur 2; arahan harus `asal = pengurus`, `penerima_id` = DE, `jenis ∈ {arahan, koreksi}`, belum ditutup |
| Pengurus | `pengurus_langsung` | personel Pengurus | null | Jalur 3; notifikasi ke DE |
| Staf | — | — | — | Ditolak (`assignment.create` tidak dimiliki Staf) |
| Administrator | — | — | — | Ditolak (tidak punya izin modul penugasan; `PENILAIAN_DILARANG_ADMIN` tetap berlaku) |

Akun tanpa baris Master Personel tidak dapat membuat penugasan/arahan (`IdentitasPelapor::dariSesi()` null → `ValidationException` dengan pesan jelas), karena pemberi tugas wajib punya identitas resmi.

### 4.3 Isi formulir

`judul`, `deskripsi`, `job_description_id` (opsional, dibatasi JD yang berlaku), `pic_id`, `kategori`, `target_keluaran`, `prioritas`, `tanggal_mulai`, `tenggat`, `dependensi_id` (opsional; hanya tugas yang bukan final), `sumber_arahan_id` (khusus DE; pilihan = arahan Pengurus yang ditujukan kepadanya dan belum diteruskan).

Validasi (`SimpanPenugasanRequest`):

- `tenggat` ≥ `tanggal_mulai`, dan `tanggal_mulai` ≥ hari ini − 30 hari (toleransi input mundur).
- `dependensi_id` ≠ id tugas itu sendiri; tidak boleh membentuk siklus (`Assignment::dependensiBerputar()`).
- `pic_id` harus personel aktif.
- `kategori`/`prioritas` dari `Rule::enum`.

Kode `TGS-YYYY-NNNN`: `nomorBerikutnya()` dibungkus transaksi + maksimal 3 percobaan ulang bila unique index menolak (menutup balapan dua pembuat bersamaan).

### 4.4 Tampilan formulir (teks)

```
┌ Penugasan Baru ─────────────────────────────────────────────┐
│ ID Tugas: TGS-2026-0007 (otomatis)                          │
│ Sumber perintah: DE Langsung          ← dari akun, read-only│
│ Pemberi tugas:  Direktur Eksekutif    ← dari akun, read-only│
│                                                             │
│ Judul ............ PIC ............ Kategori ▾              │
│ Target keluaran ..........................                  │
│ Prioritas ▾   Mulai [ ]   Tenggat [ ]   Dependensi ▾        │
│ Diteruskan dari arahan ▾ (hanya DE; kosong = Jalur 1)        │
└─────────────────────────────────────────────────────────────┘
```

## 5. Tiga jalur instruksi

| Jalur | Alur | Bukti tersimpan |
|---|---|---|
| 1. DE → staf | DE membuat penugasan | `sumber_perintah = de_langsung`, `pemberi_tugas_id` = DE, notifikasi ke PIC |
| 2. Pengurus → DE → staf | Pengurus membuat arahan (`/arahan/buat`) → DE menerima notifikasi → DE meneruskan lewat `/tugas/buat` dengan `sumber_arahan_id` | `sumber_perintah = pengurus_melalui_de`, `pemberi_tugas_id` = Pengurus, `diteruskan_oleh_id` = DE, `sumber_arahan_id` terisi; arahan berubah status `ditindaklanjuti` |
| 3. Pengurus → staf | Pengurus membuat arahan langsung ke staf, atau penugasan langsung ke staf | `asal = pengurus`, `penerima_id` = staf, `pemberi_jabatan` snapshot; **notifikasi ke DE** dan ke penerima; penugasan langsung → `sumber_perintah = pengurus_langsung` + notifikasi DE |

Aturan tambahan:

- Arahan Pengurus yang `penerima_id` = DE wajib dapat diteruskan; DE boleh meneruskan ke lebih dari satu PIC dengan membuat beberapa penugasan yang menunjuk `sumber_arahan_id` yang sama.
- Menutup arahan (`status = selesai`) hanya bila seluruh action item-nya selesai/tertutup, dan hanya oleh DE atau pemberi arahan.
- DE tidak boleh membuat arahan `asal = pengurus` (tidak bisa atas nama Pengurus); arahan DE memakai `asal = de`.

## 6. Modul arahan & action items

### 6.1 Data yang dicatat

`pemberi_id` + snapshot `pemberi_jabatan`, `penerima_id`, `asal`, `jenis`, `isi`, `lampiran_dokumen_id`, `butuh_tindakan`, `tenggat`, `created_at` (WIB, dari server). Timestamp tidak pernah dari input.

### 6.2 Komentar ≠ persetujuan formal

- `jenis = persetujuan` hanya boleh dibuat oleh peran **Pengurus** (`asal = pengurus`), lewat aksi terpisah `/arahan/{arahan}/setujui` yang membuat **baris baru** bertipe `persetujuan` dengan `subjek_type`/`subjek_id` yang sama.
- Guard model menolak perubahan `jenis` dari nilai lain menjadi `persetujuan` pada baris yang sudah ada (`DomainException`), sehingga komentar lama tidak bisa "dinaikkan" menjadi persetujuan tanpa jejak.
- Persetujuan formal **tidak** mengubah `status` objek yang disetujui. Persetujuan tugas tetap lewat transisi `terverifikasi` (izin `assignment.verify`). Ini memenuhi "jangan anggap komentar pimpinan sebagai persetujuan formal otomatis".
- `persetujuan` tidak boleh `butuh_tindakan = true` (persetujuan bukan pekerjaan).

### 6.3 Action items

Dibuat dari arahan (`feedback_id`), review, atau penugasan — minimal satu sumber (CHECK sudah ada). Formulir: `deskripsi`, `pic_id`, `tenggat`. Status mengikuti enum 6 nilai; `terverifikasi_selesai` dan `ditutup` hanya oleh DE (`action_item.verify`); `ditutup` wajib `bukti_penutupan` + alasan audit.

### 6.4 Tunggakan

Definisi tunggakan yang ditampilkan di dashboard (satu kelas `App\Support\RingkasanTunggakan` agar tiga dashboard memakai definisi sama):

1. `action_items` berstatus bukan `terverifikasi_selesai`/`ditutup`, diurut tenggat.
2. `assignments` yang `Assignment::scopeTerlambat()` (belum final dan lewat tenggat).
3. `feedback` `butuh_tindakan` dengan `status ≠ selesai`.

Cakupan baris: Staf hanya miliknya (`pic_id`/`penerima_id`), Pengurus & DE seluruhnya, Admin tidak melihat panel ini.

## 7. State machine tugas

### 7.1 Peta transisi

| Dari | Boleh ke | Peran |
|---|---|---|
| `draft` | `aktif`, `dibatalkan` | DE (verifikasi) / pemberi tugas |
| `aktif` | `dalam_proses`, `terhambat`, `dibatalkan` | PIC untuk dua pertama; DE/pemberi untuk pembatalan |
| `dalam_proses` | `terhambat`, `diajukan_selesai`, `dibatalkan` | PIC; pembatalan DE/pemberi |
| `terhambat` | `dalam_proses`, `diajukan_selesai`, `dibatalkan` | PIC; pembatalan DE/pemberi |
| `diajukan_selesai` | `dalam_proses`, `terverifikasi`, `dibatalkan` | DE mengembalikan atau memverifikasi |
| `terverifikasi` | `ditutup` | DE |
| `ditutup`, `dibatalkan` | — (final) | — |

Transisi tak sah → `DomainException` di model; Policy menolak lebih dulu dengan 403 sehingga pengguna melihat pesan wewenang, bukan galat 500.

### 7.2 Kewajiban per transisi

| Transisi | Wajib | Ditegakkan di |
|---|---|---|
| → `terverifikasi` | `diverifikasi_oleh` = personel sesi, `diverifikasi_pada` dari jam server, alasan audit | Policy (`assignment.verify`) + model (`Auditable::kolomWajibAlasan` sudah mencakup) |
| → `ditutup` | `alasan_penutupan`, `bukti_penutupan`, `ditutup_pada`, alasan audit | FormRequest + model + CHECK |
| → `dibatalkan` | `alasan_pembatalan`, `bukti_pembatalan`, alasan audit | FormRequest + model + CHECK |
| → `terhambat` | `kendala` tidak kosong | model |
| → `diajukan_selesai` | `progres = 100` | model |

`diverifikasi_pada`/`ditutup_pada` diisi `ServerClock::now()`, tidak pernah dari input.

### 7.3 Pemeriksaan peran di model

Model memeriksa `Auth::user()` **hanya bila ada sesi** (pola sama dengan `MilikSesiPelapor`): transisi `terverifikasi`/`ditutup` menolak akun yang tidak memegang `assignment.verify`; transisi PIC menolak akun yang bukan PIC dan bukan pemegang `assignment.update`. Tanpa sesi (seeder, konsol) pemeriksaan peran dilewati, legalitas transisi dan kewajiban alasan/bukti tetap berlaku. Batasan ini dicatat di bagian 12.

## 8. Notifikasi

### 8.1 Jenis

| Kelas | Pemicu | Penerima |
|---|---|---|
| `PenugasanBaru` | penugasan dibuat | PIC; **plus DE** bila `sumber_perintah = pengurus_langsung` |
| `PenugasanDiverifikasi` | → `terverifikasi`/`ditutup` | PIC |
| `ArahanBaru` | arahan dibuat | Penerima arahan; **plus DE** bila `asal = pengurus` |
| `ArahanDiteruskan` | penugasan dibuat dengan `sumber_arahan_id` | Pemberi arahan (Pengurus) |
| `TindakLanjutBaru` | action item dibuat | PIC |

Isi `data`: `judul`, `pesan`, `url` (rute internal hasil `route()`), `jenis`, `objek_id`. Tidak ada data sensitif (tidak ada NIP, tidak ada isi dokumen rahasia).

### 8.2 Ketahanan

PRD D.7: "Gangguan notifikasi tidak boleh menghilangkan data utama."

- Penugasan/arahan disimpan lebih dulu dalam transaksi database; notifikasi dikirim **setelah** commit.
- Pengiriman dibungkus `try/catch`; kegagalan dicatat ke `audit_events` sebagai `notifikasi.gagal` (dengan kelas notifikasi dan id objek) dan dilaporkan ke `Log::warning`, tetapi respons pengguna tetap sukses.
- Tidak memakai queue (tidak ada worker di lingkungan ini); pengiriman sinkron setelah commit.

### 8.3 Layar

- Lencana jumlah belum dibaca di header (`layouts/app.blade.php`), hanya bila pengguna login.
- `/notifikasi`: daftar 50 terbaru, judul, waktu WIB, penanda belum dibaca, tautan objek.
- `POST /notifikasi/{id}/baca` dan `POST /notifikasi/baca-semua`; hanya notifikasi milik akun sendiri (404 untuk id milik orang lain — bukan 403, agar keberadaan baris tidak bocor).
- Semua rute notifikasi cukup dijaga `auth` + `aktif`; tidak perlu izin modul karena isinya selalu milik akun sendiri dan dibatasi `notifiable_id`.

## 9. Rute & layar

| Metode | Rute | Nama | Middleware |
|---|---|---|---|
| GET | `/tugas` | `tugas.index` | `izin:assignment.view` |
| GET | `/tugas/buat` | `tugas.create` | `izin:assignment.create` |
| POST | `/tugas` | `tugas.store` | `izin:assignment.create` |
| GET | `/tugas/{tugas}` | `tugas.show` | `izin:assignment.view` |
| GET | `/tugas/{tugas}/ubah` | `tugas.edit` | `izin:assignment.update` |
| PUT | `/tugas/{tugas}` | `tugas.update` | `izin:assignment.update` |
| PATCH | `/tugas/{tugas}/progres` | `tugas.progres` | `izin:assignment.update` |
| PATCH | `/tugas/{tugas}/ajukan-selesai` | `tugas.ajukan` | `izin:assignment.update` |
| PATCH | `/tugas/{tugas}/verifikasi` | `tugas.verifikasi` | `izin:assignment.verify` |
| PATCH | `/tugas/{tugas}/tutup` | `tugas.tutup` | `izin:assignment.verify` |
| PATCH | `/tugas/{tugas}/batalkan` | `tugas.batalkan` | `izin:assignment.update` |
| GET | `/arahan` | `arahan.index` | `izin:feedback.view` |
| GET | `/arahan/buat` | `arahan.create` | `izin:feedback.create` |
| POST | `/arahan` | `arahan.store` | `izin:feedback.create` |
| GET | `/arahan/{arahan}` | `arahan.show` | `izin:feedback.view` |
| POST | `/arahan/{arahan}/setujui` | `arahan.setujui` | `izin:feedback.create` + peran Pengurus |
| POST | `/arahan/{arahan}/tindak-lanjut` | `arahan.tindak-lanjut` | `izin:action_item.create` |
| PATCH | `/tindak-lanjut/{tindakLanjut}` | `tindak-lanjut.update` | `izin:action_item.update` |
| PATCH | `/tindak-lanjut/{tindakLanjut}/verifikasi` | `tindak-lanjut.verifikasi` | `izin:action_item.verify` |
| PATCH | `/tindak-lanjut/{tindakLanjut}/tutup` | `tindak-lanjut.tutup` | `izin:action_item.verify` |
| GET | `/notifikasi` | `notifikasi.index` | `auth`,`aktif` |
| POST | `/notifikasi/{id}/baca` | `notifikasi.baca` | `auth`,`aktif` |
| POST | `/notifikasi/baca-semua` | `notifikasi.baca-semua` | `auth`,`aktif` |

Rute statis (`/tugas/buat`, `/arahan/buat`, `/notifikasi/baca-semua`) didaftarkan sebelum rute berparameter; parameter memakai `whereNumber` seperti `/audit/{auditEvent}`.

### 9.1 Daftar tugas (`/tugas`)

Kolom: ID, judul, PIC, sumber perintah, kategori, prioritas, tenggat, status (lencana), progres, penanda terlambat. Filter: `q` (judul/kode, maks 100), `status` (allowlist enum), `kategori`, `prioritas`, `pic` (id personel aktif), `sumber` (allowlist enum), `terlambat` (boolean), `urut`/`arah` (allowlist). 25 baris/halaman.

### 9.2 Detail tugas (`/tugas/{tugas}`)

Kepala: ID, judul, status, sumber perintah + pemberi + penerus, PIC, kategori, prioritas, mulai, tenggat, progres, target keluaran, kendala, dependensi (tautan), arahan asal (tautan, bila ada). Tombol aksi hanya muncul bila Policy mengizinkan. Riwayat: jejak audit objek ini (5 baris terakhir, tautan ke `/audit?objek=assignment`) + daftar action item terkait.

### 9.3 Daftar & detail arahan

Daftar: waktu, pemberi + jabatan, penerima, jenis (lencana; `persetujuan` dibedakan warna), asal, tenggat, status, jumlah action item terbuka. Detail: isi lengkap, lampiran (tautan unduh bila ada), action items, tombol "Setujui" hanya untuk Pengurus pada arahan yang bukan persetujuan.

## 10. Otorisasi & matriks peran

Tidak ada izin baru — total tetap **56**. Yang berubah hanya pemetaan yang sudah ada plus method Policy baru.

| Kemampuan | Policy | Izin | Cakupan baris |
|---|---|---|---|
| Lihat tugas | `AssignmentPolicy::view` | `assignment.view` | Staf: hanya `pic_id` sendiri |
| Buat tugas | `create` | `assignment.create` | DE & Pengurus |
| Ubah tugas | `update` | `assignment.update` | Staf: hanya tugas sendiri, dan hanya field progres/kendala |
| Verifikasi/tutup | `verifikasi`, `tutup` | `assignment.verify` | DE (Admin diblokir `PENILAIAN_DILARANG_ADMIN`) |
| Batalkan | `batalkan` | `assignment.update` | DE atau pemberi tugas |
| Lihat arahan | `FeedbackPolicy::view` | `feedback.view` | Staf: pemberi atau penerima |
| Buat arahan | `FeedbackPolicy::create` | `feedback.create` | Pengurus & DE; `asal` dari peran sesi |
| Setujui formal | `FeedbackPolicy::setujui` | `feedback.create` + peran `pengurus` | Pengurus |
| Lihat action item | `ActionItemPolicy::view` | `action_item.view` | Staf: `pic_id` sendiri |
| Verifikasi/tutup action item | `verify` | `action_item.verify` | DE |

Formulir tidak pernah menjadi penentu: setiap aksi memanggil `$this->authorize(...)` sebelum menyentuh model.

## 11. Pemetaan OWASP (`laravel-owasp-security`)

Tidak ada React/Inertia di stack ini (Blade + Laravel 12), jadi hanya checklist Laravel yang diterapkan.

| Butir | Penerapan Tahap 5 |
|---|---|
| A01 Broken Access Control | Middleware `izin:*` per rute; `authorize()` di setiap aksi; `AssignmentPolicy`/`FeedbackPolicy`/`ActionItemPolicy` mencakup baris; notifikasi dibatasi `notifiable_id`; IDOR diuji dengan URL langsung antar peran. |
| A02 Cryptographic Failures | Tidak ada rahasia baru. Bukti disimpan sebagai rujukan teks + FK, bukan berkas mentah. |
| A03 Injection | `$request->validated()` → `fill()`; `sumber_perintah`/`pemberi_tugas_id`/`diverifikasi_oleh`/`kode` tidak `fillable`; filter memakai allowlist `Rule::enum` dan `match` untuk `orderBy`; pencarian `LIKE` dengan binding. |
| A04 Insecure Design | State machine di server; alasan + bukti wajib di model **dan** CHECK DB; persetujuan formal tidak pernah diturunkan dari komentar; peran pemberi diverifikasi dari sesi. |
| A05 Security Misconfiguration | Tidak berubah; tidak ada `.env` baru. |
| A06 Vulnerable Components | `composer audit`, `npm audit` pada verifikasi akhir. |
| A07 Auth Failures | Tidak berubah; rute baru tetap di bawah `auth` + `aktif`. |
| A08 Integrity Failures | `@csrf` di setiap formulir POST/PUT/PATCH; tidak ada rute baru yang dikecualikan dari CSRF; CHECK DB menjaga integritas penutupan. |
| A09 Logging | Setiap transisi status, pembuatan arahan, persetujuan, dan penutupan menghasilkan baris `audit_events` (trait `Auditable`); kegagalan notifikasi dicatat `notifikasi.gagal`. |
| A10 SSRF | Tidak ada permintaan keluar; `url` notifikasi dibuat dari `route()`, bukan input pengguna. |
| XSS | Blade `{{ }}` di seluruh tampilan baru; isi arahan dan alasan dirender sebagai teks, bukan HTML; tidak ada `{!! !!}`. |
| CSRF/IDOR | Seperti di atas; ditambah tes akses lintas peran untuk setiap rute baru. |

## 12. Kriteria penerimaan

1. Membuat tugas sebagai DE menghasilkan `sumber_perintah = de_langsung`, `pemberi_tugas_id` = personel DE, `kode` `TGS-<tahun>-0001`; mengirim `sumber_perintah`/`pemberi_tugas_id` lewat POST **tidak** mengubah hasil.
2. DE yang membuat tugas dengan `sumber_arahan_id` miliknya menghasilkan `pengurus_melalui_de`, `pemberi_tugas_id` = Pengurus, `diteruskan_oleh_id` = DE, dan arahan berubah `ditindaklanjuti`.
3. DE tidak dapat memakai `sumber_arahan_id` yang bukan miliknya, bukan `asal = pengurus`, atau sudah ditutup (422).
4. Pengurus membuat tugas langsung menghasilkan `pengurus_langsung` dan notifikasi ke DE; Staf dan Admin mendapat 403 pada `POST /tugas`.
5. Arahan Pengurus ke staf: DE dapat membuka `/arahan/{id}` dan menerima notifikasi; Staf lain tidak dapat membuka (403).
6. `jenis = persetujuan` hanya dibuat Pengurus; DE yang mencoba mendapat 403; mengubah baris `komentar` menjadi `persetujuan` lewat `save()` melempar `DomainException`.
7. Menutup arahan hanya boleh setelah semua action item selesai/tertutup.
8. Transisi sah berhasil, transisi tak sah (`draft` → `terverifikasi`, `ditutup` → `dalam_proses`) melempar `DomainException` dan tidak mengubah baris.
9. `terverifikasi` oleh Staf/Pengurus ditolak 403; oleh DE berhasil dan mengisi `diverifikasi_oleh` + `diverifikasi_pada` dari jam server.
10. `ditutup` tanpa `alasan_penutupan`/`bukti_penutupan` ditolak model; dengan `DB::table('assignments')->update(['status' => 'ditutup'])` ditolak CHECK.
11. `dibatalkan` tanpa `bukti_pembatalan` ditolak; dengan bukti tersimpan dan tercatat di audit.
12. `terhambat` tanpa `kendala` ditolak; `diajukan_selesai` dengan progres < 100 ditolak.
13. Action item `ditutup` tanpa `bukti_penutupan` ditolak; dengan bukti tersimpan; Staf tidak dapat menutup (403).
14. Notifikasi terkirim ke penerima yang benar untuk kelima kelas; kegagalan notifikasi (kelas melempar) tidak membatalkan penyimpanan tugas dan tercatat `notifikasi.gagal`.
15. `/notifikasi` hanya menampilkan milik sendiri; `POST /notifikasi/{id}/baca` untuk notifikasi akun lain menghasilkan 404; lencana menghitung yang belum dibaca.
16. Tunggakan dashboard: Staf melihat hanya miliknya; Pengurus & DE melihat seluruhnya; item yang sudah final tidak muncul.
17. Alasan/bukti tidak menempel: `denganAlasan()` lalu `save()`, kemudian `save()` lagi pada kolom terjaga tanpa alasan → ditolak.
18. `php artisan test`, `pint --test`, `composer validate --strict`, `composer audit`, `npm run build`, `npm audit --omit=dev` hijau.

## 13. Non-tujuan

- Unggah berkas bukti (Tahap 7). Tahap 5 hanya menerima rujukan teks + FK dokumen yang sudah ada.
- Notifikasi email/WhatsApp/websocket (Fase 3 PRD); push real-time di luar kemampuan stack ini.
- Log aktivitas harian (Tahap 6), laporan (Tahap 8), KPI (Tahap 10), dashboard agregat/grafik (Tahap 11).
- Ekspor rinci per modul; `/ekspor/{modul}` tetap placeholder untuk 11 modul.
- Pengaturan bobot/ambang skor dan konfigurasi RBAC lewat UI.

## 14. Batasan yang diketahui

1. **Pemeriksaan peran di model bergantung sesi.** Tanpa sesi (seeder, `artisan tinker`), legalitas transisi dan kewajiban alasan/bukti tetap ditegakkan, tetapi peran tidak diperiksa — konsekuensi wajar dari penjaga di lapisan model. Jalur tulis resmi (controller) selalu memeriksa peran lewat Policy.
2. **`sumber_perintah` tidak dapat diubah setelah dibuat.** Bila DE keliru menautkan `sumber_arahan_id`, jalan keluarnya membatalkan tugas (beralasan + bukti) lalu membuat ulang. Mengubah sumber perintah di tengah jalan akan memalsukan riwayat instruksi.
3. **Bukti penutupan berupa teks.** Sampai modul dokumen (Tahap 7) punya layar unggah, bukti adalah rujukan arsip (mis. "Surat No. 123/HIMKI/XI/2026"). FK `bukti_dokumen_id` sudah tersedia agar Tahap 7 tinggal mewajibkannya.
4. **Notifikasi tanpa queue.** Pengiriman sinkron setelah commit; pada beban besar ini menambah latensi respons. Bila kelak ada worker, pindah ke `ShouldQueue` tanpa mengubah skema.
5. **Lencana belum diperbarui tanpa muat ulang halaman.** "Real-time" di sini berarti muncul pada pemuatan/refresh berikutnya, sesuai PRD "real-time/mendekati real-time".
6. **Persetujuan formal hanya oleh Pengurus.** DE tidak dapat mencatat `persetujuan` atas nama Pengurus; verifikasi DE tetap lewat transisi `terverifikasi`.
7. **Satu `pic_id` per tugas.** Penerusan satu arahan ke beberapa staf dilakukan dengan membuat beberapa tugas yang menunjuk `sumber_arahan_id` yang sama.
