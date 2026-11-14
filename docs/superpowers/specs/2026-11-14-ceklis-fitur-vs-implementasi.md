# Ceklis Fitur — `fitur.pdf` vs Implementasi

Diperiksa: 14 Nov 2026, database `erp_himki_baru` (MariaDB 10.4.32), 41 test lulus (171 asersi).
Sumber acuan: `C:\Users\rio\Downloads\fitur.pdf` (matriks 4 peran + 12 tahap pembangunan).

## Legenda

| Tanda | Arti |
|---|---|
| ✅ | Selesai dan terverifikasi (kode + test) |
| 🟡 | Fondasi database siap; alur/UI/controller belum ada |
| ⬜ | Belum ada sama sekali |

Cakupan yang dibangun saat ini adalah **Tahap 1 dan Tahap 2**. Butir Tahap 2 kini ✅ karena
gerbang login, auto-routing peran, dan middleware RBAC server-side sudah berjalan dan diuji.
Fitur layar tetap 🟡: gerbang otorisasi untuk setiap aksi (create/read/update/verify/export)
sudah aktif dan diuji, tetapi layar operasionalnya dibangun pada tahap berikutnya.
Kolom 🟡 berarti tabel, kolom, enum, relasi, atau aturan model sudah ada sehingga fitur
tinggal dibangun di atasnya tanpa migrasi baru.

---

## A. 12 Tahap Pembangunan

| Tahap | Isi | Status | Bukti / kekurangan |
|---|---|---|---|
| 1 | Skema 14 entitas, relasi & atribut kunci, standarisasi zona waktu | ✅ | 29 tabel, 48 FK, 41 CHECK native, 2 trigger audit, `ServerClock`, test `SkemaTahap1Test` |
| 2 | Gerbang login, auto-routing peran, middleware RBAC | ✅ | Fortify headless (login email/username, tanpa registrasi publik, reset sandi), 4 ruang kerja per peran, 55 izin × 4 peran, middleware `peran`/`izin`/`izin_modul`/`aktif`, 12 Policy, sesi DB + pencabutan sesi lain, lockout 429, audit `auth.*`, test `Tahap2AutentikasiRbacTest` (21 asersi) |
| 3 | Master personel & jabatan, kunci identitas otomatis, master job description | 🟡 | `personel` (nip, user_id unik, jabatan, bidang, status_aktif), `jabatans`, `bidangs`, `job_descriptions` (kategori, indikator, target, frekuensi) |
| 4 | Audit trail append-only, rekam jejak perubahan | 🟡 | Tabel + trigger tolak UPDATE/DELETE + trait `Auditable` pada 7 model. **Celah:** `Meeting`, `ActivityLog`, `Personel`, `User`, `Jabatan`, `Bidang`, `JobDescription` belum diaudit; `reason` belum wajib saat ubah skor/bobot |
| 5 | Formulir penugasan, 3 jalur arahan, arahan & action items, state machine tugas | 🟡 | Kolom lengkap: `sumber_perintah` (3 nilai), `pemberi_tugas_id`, `diteruskan_oleh_id`, `pic_id`, `kategori` (4), `prioritas` (4), `target_keluaran`, `tenggat`, `dependensi_id`. **Celah:** transisi status belum dikunci di DB; hanya enum + guard batal/verifikasi |
| 6 | Log aktivitas harian, atribut log, early warning | 🟡 | `activity_logs` wajib tertaut ke `assignment_id` atau `job_description_id` (CHECK `sumber_terkait`), `progres`, `durasi_menit`, `kendala`, `is_kendala_dini` |
| 7 | Repositori dokumen & versi, proteksi & klasifikasi, SLA register rapat | 🟡 | `documents` (versi, induk_id, 4 status, 3 klasifikasi, checksum), `meetings` (SLA +1j/+16j otomatis, 2 status risalah). **Celah:** belum ada guard larangan hapus dokumen Final/Diarsipkan. Otorisasi unduh per klasifikasi sudah aktif (`DocumentPolicy::download` + rute `dokumen.unduh`) |
| 8 | Pelaporan berjenjang, generator matriks bulanan, siklus versi laporan | 🟡 | `reports` (4 jenis, versi, induk_id, narasi_evaluasi, kendala, rencana_tindak_lanjut, 7 status, unique per periode+versi). Generator otomatis belum ada |
| 9 | Kalkulator 5 komponen laporan, bobot & kategori, action item revisi, pemisahan skor versi | 🟡 | `Review` menghitung `skor_total` dari 5 komponen bobot 20/25/25/15/15, kategori otomatis, `versi_review`, satu final per laporan. **Celah:** action item otomatis untuk skor < 70 belum dibuat |
| 10 | Rumus KPI berversi, kalkulator 5 komponen, jendela evaluasi & klarifikasi, rekap tahunan | 🟡 | `KpiResult` hitung ulang total, bobot wajib 100, `rule_version`, status klarifikasi, kunci Final wajib `dikunci_oleh`, kolom skor terkunci setelah Final. **Celah:** rekap tahunan & jendela tanggal 20–24 belum ada |
| 11 | 4 dashboard responsif, pemantauan real-time, filter & ekspor | 🟡 | 4 ruang kerja (`/pengurus`, `/de`, `/staf`, `/admin`) + `DashboardController`, peta wewenang per akun, jam server, 5 aktivitas terakhir, ekspor CSV per modul. **Celah:** kartu/grafik agregat, filter, dan pemantauan real-time belum ada |
| 12 | Backup & DR, UAT AC-01..AC-15, serah terima | ⬜ | Belum ada skrip backup, dokumen UAT, panduan pengguna |

---

## B. Dashboard Pengurus Berwenang (21 fitur)

| # | Fitur | Status | Catatan data |
|---|---|---|---|
| 1 | Kartu Ringkasan Kinerja | 🟡 | agregasi `assignments.status` belum dibuat |
| 2 | Indikator Last Updated | ⬜ | tidak ada mekanisme penanda pembaruan data |
| 3 | Grafik Capaian Tugas | 🟡 | `target_keluaran` + `progres` tersedia |
| 4 | Daftar Tugas Terlambat | 🟡 | `Assignment::scopeTerlambat()` sudah ada |
| 5 | Daftar Kendala Dini | 🟡 | `is_kendala_dini` + status `terhambat` |
| 6 | Kartu Skor Laporan | 🟡 | `reviews.skor_total` |
| 7 | Indikator Evaluasi Bulanan (20–24) | ⬜ | logika jendela tanggal belum ada |
| 8 | Daftar Tunggakan Arahan | 🟡 | `action_items.status` + `tenggat` |
| 9 | Filter Pencarian Data | ⬜ | belum ada query/filter |
| 10 | Tombol Ekspor Data | 🟡 | rute `/ekspor/{modul}` + izin `*.export` aktif dan teruji; isi rekap masih CSV keterangan sumber, rekap rinci di Tahap 11 |
| 11 | Menu Profil Akun | 🟡 | `users` (name, username, email, password) + rute `user-password.update` (Fortify) dengan kebijakan sandi 12 karakter |
| 12 | Tabel Manajemen Penugasan | 🟡 | `assignments` lengkap |
| 13 | Tabel Log Aktivitas | 🟡 | `activity_logs` |
| 14 | Penelusuran Laporan Kerja | 🟡 | `reports` (4 jenis) |
| 15 | Repositori Dokumen & Bukti | 🟡 | versi + klasifikasi ada; proteksi hapus belum |
| 16 | Tabel Register Rapat | 🟡 | SLA + status risalah ada |
| 17 | Riwayat Review & Skor | 🟡 | `versi_review`, `adalah_final` |
| 18 | Tombol Buat Arahan | 🟡 | `feedback` + `action_items`; notifikasi belum |
| 19 | Tabel Job Description | 🟡 | `job_descriptions` |
| 20 | Tabel Evaluasi KPI | 🟡 | `kpi_results` |
| 21 | Tabel Audit Trail | 🟡 | `audit_events` append-only; kini juga memuat peristiwa `auth.*` (login, login gagal, logout, akses ditolak, blokir). `Meeting` belum diaudit |

## C. Dashboard Direktur Eksekutif (27 fitur)

| # | Fitur | Status | Catatan data |
|---|---|---|---|
| 1 | Kartu Akumulasi Tugas | 🟡 | enum status 8 nilai |
| 2 | Grafik Beban Kerja | 🟡 | `pic_id` + `prioritas` |
| 3 | Indikator Last Updated | ⬜ | belum ada |
| 4 | Daftar Tugas Prioritas | 🟡 | `prioritas` + `tenggat` |
| 5 | Panel Kendala Dini | 🟡 | `kendala` + `is_kendala_dini` |
| 6 | Monitor SLA Risalah | 🟡 | kolom tenggat & status; countdown belum |
| 7 | Status Pengumpulan Laporan | 🟡 | `reports.status` + periode |
| 8 | Antrean Verifikasi Laporan | 🟡 | status `diajukan` / `dalam_review` |
| 9 | Grafik Skor & KPI | 🟡 | `reviews`, `kpi_results` |
| 10 | Indikator Evaluasi Bulanan | ⬜ | belum ada |
| 11 | Notifikasi Instruksi Pengurus | ⬜ | belum ada |
| 12 | Daftar Tunggakan Arahan | 🟡 | `action_items` |
| 13 | Daftar Dokumen Belum Lengkap | 🟡 | `documents.status` draft |
| 14 | Tombol Pintas Buat Tugas | 🟡 | `assignments` |
| 15 | Filter & Ekspor Operasional | 🟡 | ekspor CSV per modul aktif (`/ekspor/{modul}` + izin `export`); filter belum ada |
| 16 | Menu Profil Akun | 🟡 | `users` + rute `user-password.update` (Fortify) |
| 17 | Form Manajemen Penugasan | 🟡 | kolom + `diverifikasi_oleh`/`_pada` |
| 18 | Form Log Aktivitas Harian | 🟡 | `activity_logs` |
| 19 | Modul Laporan Kerja | 🟡 | `reports` + `diverifikasi_oleh` |
| 20 | Modul Manajemen Dokumen | 🟡 | `documents` |
| 21 | Modul Register Rapat | 🟡 | `meetings` + `meeting_attendees` |
| 22 | Form Penilaian Laporan | 🟡 | 5 komponen + bobot + `catatan_kekuatan`, `kekurangan_spesifik`, `rekomendasi_perbaikan` |
| 23 | Modul Koordinasi Arahan | 🟡 | `action_items.pic_id`/`tenggat`/`diverifikasi_oleh` |
| 24 | Menu Job Description | 🟡 | `job_descriptions` |
| 25 | Modul Evaluasi KPI | 🟡 | `kpi_results` + `klarifikasi` + kunci Final |
| 26 | Tabel Struktur Personel | 🟡 | `personel` + `jabatans` + `bidangs` |
| 27 | Tabel Audit Trail Operasional | 🟡 | `audit_events` |

## D. Dashboard Personel BE (17 fitur)

| # | Fitur | Status | Catatan data |
|---|---|---|---|
| 1 | Daftar Tugas Pribadi | 🟡 | `pic_id` + `sumber_perintah` |
| 2 | Pengingat Tenggat & Prioritas | 🟡 | `tenggat` + `prioritas` |
| 3 | Tombol Pintas Log Harian | 🟡 | `activity_logs` |
| 4 | Daftar Revisi & Tindak Lanjut | 🟡 | `action_items` + `reviews` |
| 5 | Countdown SLA Risalah | 🟡 | kolom tenggat tersimpan |
| 6 | Kartu Skor Evaluasi Pribadi | 🟡 | `reviews` + `kpi_results` |
| 7 | Menu Profil Akun | 🟡 | `users` + rute `user-password.update` (Fortify) |
| 8 | Form Update Progres Tugas | 🟡 | `progres` + status `dalam_proses`/`diajukan_selesai` |
| 9 | Tombol Lapor Kendala Dini | 🟡 | status `terhambat` + `kendala` + `is_kendala_dini` |
| 10 | Form Log Aktivitas Harian | 🟡 | tautan wajib ke tugas/JD (CHECK) |
| 11 | Generator Laporan Pribadi | 🟡 | `reports` 4 jenis + `narasi_evaluasi` |
| 12 | Form Unggah Bukti Kerja | 🟡 | `versi` + `induk_id`; otorisasi unduh per klasifikasi aktif, guard hapus Final/Diarsipkan belum |
| 13 | Form Register Rapat | 🟡 | `meetings` + `waktu_selesai` |
| 14 | Panel Review & Revisi | 🟡 | `reviews` + `reports.induk_id` |
| 15 | Panel Tindak Lanjut Arahan | 🟡 | `action_items.status` 6 nilai |
| 16 | Menu Job Description Sendiri | 🟡 | `job_descriptions` |
| 17 | Panel Klarifikasi KPI | 🟡 | status `menunggu_klarifikasi` + `klarifikasi` |

## E. Panel Administrator (10 fitur)

| # | Fitur | Status | Catatan data |
|---|---|---|---|
| 1 | Panel Status Teknis | ⬜ | belum ada |
| 2 | Modul Manajemen Akun | 🟡 | `users.is_active`, `last_login_at`, tabel `password_reset_tokens`; izin `user.view/create/update/deactivate` sudah ditegakkan server-side (`UserPolicy`), layar CRUD belum ada |
| 3 | Master Personel & Jabatan | 🟡 | `personel`, `jabatans`, `bidangs`; izin `personel.*` sudah ditegakkan (`PersonelPolicy`) |
| 4 | Konfigurasi Matriks RBAC | 🟡 | Spatie v6 + 4 peran + 55 izin ter-seed (`PeranIzinSeeder`), 12 Policy, middleware `peran`/`izin`/`izin_modul`; UI pengaturan izin belum |
| 5 | Pengaturan Klasifikasi Dokumen | 🟡 | enum 3 tingkat + penegakan akses `rahasia_keuangan` di `DocumentPolicy::download`; kebijakan retensi belum |
| 6 | Panel Metadata & Storage | 🟡 | `mime_type`, `file_size`, `checksum`, `file_path` |
| 7 | Master Job Description | 🟡 | `job_descriptions` |
| 8 | Konfigurasi Rumus Berversi | 🟡 | `rule_version` + kolom bobot per baris; versi kebijakan per jabatan belum |
| 9 | Panel Backup & Recovery | ⬜ | belum ada |
| 10 | Modul Audit Trail Sistem | 🟡 | `audit_events` append-only, izin `audit.view/export` (Pengurus & Admin), peristiwa `auth.*` tercatat |

---

## F. Rekap angka

| Kategori | Jumlah |
|---|---|
| Fitur layar di `fitur.pdf` | 75 (Pengurus 21, DE 27, Staf 17, Admin 10) |
| ✅ selesai penuh | 0 |
| 🟡 fondasi database + gerbang RBAC siap | 67 (Pengurus 18, DE 24, Staf 17, Admin 8) |
| ⬜ belum ada | 8 (Pengurus 3, DE 3, Staf 0, Admin 2) |
| Butir Tahap 1 (skema/relasi/zona waktu) | 3 dari 3 ✅ |
| Butir Tahap 2 (login/routing/RBAC) | 1 dari 1 ✅ |

8 fitur ⬜: Indikator Last Updated (Pengurus, DE), Indikator Evaluasi Bulanan (Pengurus,
DE), Filter Pencarian Data, Notifikasi Instruksi Pengurus, Panel Status Teknis, Panel Backup
& Recovery.

## G. Celah yang masih terbuka

Tahap 2 sudah menutup celah "belum ada pengecekan server-side". Yang tersisa:

1. **`reason` wajib** saat mengubah skor/bobot/status terverifikasi (Tahap 4).
2. **`Meeting` belum memakai trait `Auditable`** padahal risalah wajib diaudit.
3. **Guard hapus dokumen Final/Diarsipkan** belum ada di model maupun DB.
4. **Transisi status** masih bebas; state machine belum ditegakkan.
5. **Action item otomatis** untuk laporan skor < 70 belum dibuat.
6. **Isi ekspor masih placeholder** — rute dan izin `export` sudah benar, rekapitulasi rinci
   menyusul di Tahap 11.
7. **Layar operasional 12 modul** belum ada; baru gerbang otorisasi + tabel wewenang.
