# Ceklis Fitur — `fitur.pdf` vs Implementasi

Diperiksa: 14 Nov 2026, database `erp_himki_baru` (MariaDB 10.4.32), 130 test lulus (517 asersi).
Sumber acuan: `C:\Users\rio\Downloads\fitur.pdf` (matriks 4 peran + 12 tahap pembangunan).

## Legenda

| Tanda | Arti |
|---|---|
| ✅ | Selesai dan terverifikasi (kode + test) |
| 🟡 | Fondasi database siap; alur/UI/controller belum ada |
| ⬜ | Belum ada sama sekali |

Cakupan yang dibangun saat ini adalah **Tahap 1, Tahap 2, Tahap 3, Tahap 4, dan Tahap 5**. Butir Tahap 2 kini ✅ karena
gerbang login, auto-routing peran, dan middleware RBAC server-side sudah berjalan dan diuji.
Butir Tahap 3 kini ✅ karena Master Personel, Jabatan, Bidang, dan Job Description sudah punya
layar, Policy, FormRequest, dan penguncian identitas otomatis.
Butir Tahap 4 kini ✅ karena jejak audit sudah append-only di tiga lapis (model, trigger DB, Policy),
alasan wajib ditegakkan di level model, dan layar penelusuran `/audit` sudah ada.
Butir Tahap 5 kini ✅ karena formulir penugasan, tiga jalur instruksi, modul arahan & action item,
state machine status, pusat notifikasi, dan panel tunggakan dashboard sudah berjalan dan diuji.
Fitur layar lain tetap 🟡: gerbang otorisasi untuk setiap aksi (create/read/update/verify/export)
sudah aktif dan diuji, tetapi layar operasionalnya dibangun pada tahap berikutnya.
Kolom 🟡 berarti tabel, kolom, enum, relasi, atau aturan model sudah ada sehingga fitur
tinggal dibangun di atasnya tanpa migrasi baru.

---

## A. 12 Tahap Pembangunan

| Tahap | Isi | Status | Bukti / kekurangan |
|---|---|---|---|
| 1 | Skema 14 entitas, relasi & atribut kunci, standarisasi zona waktu | ✅ | 29 tabel, 48 FK, 41 CHECK native, 2 trigger audit, `ServerClock`, test `SkemaTahap1Test` |
| 2 | Gerbang login, auto-routing peran, middleware RBAC | ✅ | Fortify headless (login email/username, tanpa registrasi publik, reset sandi), 4 ruang kerja per peran, 56 izin × 4 peran, middleware `peran`/`izin`/`izin_modul`/`aktif`, 12 Policy inti, sesi DB + pencabutan sesi lain, lockout 429, audit `auth.*`, test `Tahap2AutentikasiRbacTest` (21 asersi) |
| 3 | Master personel & jabatan, kunci identitas otomatis, master job description | ✅ | Layar `/master/personel` (daftar/filter/cari/urut, detail, tambah, ubah, nonaktifkan + alasan wajib), `/master/jabatan`, `/master/bidang`, `/master/job-description` (draf → tetapkan → arsip → versi baru). 6 bidang resmi (Keuangan, Administrasi, Publikasi, HI/Pameran, TI, Operasional). Kunci identitas: trait `MilikSesiPelapor` pada `Report`/`ActivityLog`/`Document` + `IdentitasPelapor`. Izin ke-56 `personel.configure` (khusus Admin) memisahkan draf DE dari penetapan. Audit `identitas.ditolak` untuk percobaan pemalsuan. Test `Tahap3MasterPersonelTest` (14 tes, 105 asersi) |
| 4 | Audit trail append-only, rekam jejak perubahan | ✅ | Append-only di tiga lapis: `AuditEvent::booted()` menolak `updating`/`deleting`, trigger DB menolak `UPDATE`/`DELETE` termasuk SQL langsung, `AuditEventPolicy::create/update/delete` mengembalikan `false` untuk **semua** peran termasuk Administrator. Trait `Auditable` pada 12 model (termasuk `Meeting`). Alasan wajib ditegakkan di level model (`kolomWajibAlasan()`/`wajibAlasanUntuk()`) untuk skor & bobot `Review`, skor/bobot/status `KpiResult`, target/indikator JD, status final `Assignment`, verifikasi `Report`, finalisasi/klasifikasi `Document`. Snapshot pelaku `actor_nama`/`actor_peran`; `actor_id` `restrictOnDelete`. Perubahan peran/izin dicatat lewat listener `CatatPerubahanAkses` (`akses.*`, hanya bila ada sesi login). Layar `/audit` (filter 8 kolom, detail before/after, ekspor CSV 5.000 baris + `ekspor.audit`); DE hanya peristiwa operasional, ekspor khusus Admin. Test `Tahap4AuditTrailTest` (21 tes, 61 asersi) |
| 5 | Formulir penugasan, 3 jalur arahan, arahan & action items, state machine tugas | ✅ | Migrasi `2026_11_14_080001_add_tahap5_columns.php` (kolom bukti/alasan penutupan & pembatalan, CHECK `assignments_penutupan_*`/`pembatalan_*`). `Assignment`: kode otomatis `TGS-<tahun>-NNNN` unik, `sumber_perintah`/`pemberi_tugas_id`/`diteruskan_oleh_id` **diisi dari sesi** dan terkunci setelah dibuat, deteksi dependensi melingkar, `scopeTerlihatOleh`, `scopeTerlambat`, `terlambat()`. `StatusPenugasan::bolehKe()` menegakkan peta transisi (Draft → Aktif → DalamProses/Terhambat → DiajukanSelesai → Terverifikasi → Ditutup, plus Dibatalkan); Terverifikasi/Ditutup/Dibatalkan wajib alasan tertulis dan penutupan/pembatalan wajib bukti di level model **dan** DB. `Feedback` mengunci identitas pemberi dari sesi dan menolak komentar dinaikkan menjadi persetujuan formal. `ActionItem` menolak penutupan tanpa bukti dan hanya pemegang `action_item.verify`. Rute `/tugas` (13 aksi), `/arahan`, `/tindak-lanjut`, `/notifikasi`; layar daftar/formulir/detail untuk keduanya; 5 kelas notifikasi database + panel tunggakan dashboard. Pengurus kehilangan `assignment.verify` (total 56 izin). Test `Tahap5PenugasanTest` (54 tes, 178 asersi) + smoke live 4 peran 50 pemeriksaan |
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
| 4 | Daftar Tugas Terlambat | ✅ | `/tugas?terlambat=1` (filter tenggat lewat + status belum final) dan penanda "terlambat" pada panel tunggakan dashboard |
| 5 | Daftar Kendala Dini | 🟡 | `is_kendala_dini` + status `terhambat` |
| 6 | Kartu Skor Laporan | 🟡 | `reviews.skor_total` |
| 7 | Indikator Evaluasi Bulanan (20–24) | ⬜ | logika jendela tanggal belum ada |
| 8 | Daftar Tunggakan Arahan | ✅ | Panel "Tunggakan Anda" di `/pengurus` (arahan menunggu tindakan + tindak lanjut terbuka) dan daftar `/arahan` dengan penanda status |
| 9 | Filter Pencarian Data | ⬜ | belum ada query/filter |
| 10 | Tombol Ekspor Data | 🟡 | rute `/ekspor/{modul}` + izin `*.export` aktif dan teruji; isi rekap masih CSV keterangan sumber, rekap rinci di Tahap 11 |
| 11 | Menu Profil Akun | 🟡 | `users` (name, username, email, password) + rute `user-password.update` (Fortify) dengan kebijakan sandi 12 karakter |
| 12 | Tabel Manajemen Penugasan | ✅ | `/tugas` — daftar dengan filter PIC/terlambat, urut tenggat, lencana status; detail memuat sumber perintah, PIC, tenggat, dependensi, riwayat audit, dan form aksi yang dijaga `@can` |
| 13 | Tabel Log Aktivitas | 🟡 | `activity_logs` |
| 14 | Penelusuran Laporan Kerja | 🟡 | `reports` (4 jenis) |
| 15 | Repositori Dokumen & Bukti | 🟡 | versi + klasifikasi ada; proteksi hapus belum |
| 16 | Tabel Register Rapat | 🟡 | SLA + status risalah ada |
| 17 | Riwayat Review & Skor | 🟡 | `versi_review`, `adalah_final` |
| 18 | Tombol Buat Arahan | ✅ | `/arahan/buat` + `POST /arahan`; identitas pemberi, jabatan, dan asal arahan diisi otomatis dari akun pembuat, penerima menerima notifikasi `ArahanBaru`. Persetujuan formal dicatat sebagai baris baru, bukan komentar |
| 19 | Tabel Job Description | ✅ | `/master/job-description` — daftar per jabatan dengan target, indikator, frekuensi, versi, dan status penetapan (dibuka dari menu Master Data) |
| 20 | Tabel Evaluasi KPI | 🟡 | `kpi_results` |
| 21 | Tabel Audit Trail | ✅ | `/audit` — daftar + filter (tindakan, objek, pelaku, rentang tanggal, urutan), detail before/after, ekspor CSV. `audit_events` append-only di tiga lapis; `Meeting` ikut diaudit; alasan wajib pada perubahan sensitif |

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
| 11 | Notifikasi Instruksi Pengurus | ✅ | Pusat notifikasi `/notifikasi` + lencana belum dibaca di header; notifikasi `database` per akun. Instruksi Pengurus ke staf selalu memicu notifikasi ke DE (`PenugasanBaru`), dan arahan Pengurus yang diteruskan DE memberi kabar balik ke Pengurus (`ArahanDiteruskan`) |
| 12 | Daftar Tunggakan Arahan | ✅ | Panel "Tunggakan Anda" di `/de` (arahan menunggu tindakan + tindak lanjut terbuka) dan daftar `/arahan` |
| 13 | Daftar Dokumen Belum Lengkap | 🟡 | `documents.status` draft |
| 14 | Tombol Pintas Buat Tugas | ✅ | Tombol "Tugas Baru" pada `/tugas` (dan menu Penugasan) membuka `/tugas/buat`; sumber perintah terisi otomatis sesuai peran pembuat |
| 15 | Filter & Ekspor Operasional | 🟡 | ekspor CSV per modul aktif (`/ekspor/{modul}` + izin `export`); filter belum ada |
| 16 | Menu Profil Akun | 🟡 | `users` + rute `user-password.update` (Fortify) |
| 17 | Form Manajemen Penugasan | ✅ | `/tugas/buat` + `/tugas/{tugas}/ubah`; sumber perintah/pemberi terkunci dari sesi, dependensi melingkar ditolak, verifikasi & penutupan dijaga izin `assignment.verify` dengan alasan dan bukti |
| 18 | Form Log Aktivitas Harian | 🟡 | `activity_logs` |
| 19 | Modul Laporan Kerja | 🟡 | `reports` + `diverifikasi_oleh` |
| 20 | Modul Manajemen Dokumen | 🟡 | `documents` |
| 21 | Modul Register Rapat | 🟡 | `meetings` + `meeting_attendees` |
| 22 | Form Penilaian Laporan | 🟡 | 5 komponen + bobot + `catatan_kekuatan`, `kekurangan_spesifik`, `rekomendasi_perbaikan` |
| 23 | Modul Koordinasi Arahan | ✅ | `/arahan` (daftar, formulir, detail) + `/tindak-lanjut` (ubah status, verifikasi, tutup). DE dapat meneruskan arahan Pengurus menjadi tugas (jalur 2) dan menambah action item; PIC menerima notifikasi `TindakLanjutBaru` |
| 24 | Menu Job Description | ✅ | `/master/job-description` — DE membaca seluruh JD dan boleh menyusun draf, tetapi penetapan tetap milik Admin (`personel.configure`) |
| 25 | Modul Evaluasi KPI | 🟡 | `kpi_results` + `klarifikasi` + kunci Final |
| 26 | Tabel Struktur Personel | ✅ | `/master/personel` + `/master/jabatan` + `/master/bidang`: daftar, filter bidang/kategori/status, urut, detail, jejak audit |
| 27 | Tabel Audit Trail Operasional | ✅ | `/audit` — DE memegang `audit.view` dan hanya melihat peristiwa operasional (tugas, log, laporan, dokumen, review, feedback, action item, KPI, rapat). Jejak keamanan (`auth.*`), perubahan hak akses (`akses.*`), master personel, dan akun tidak terlihat; membuka URL langsung ditolak `AuditEventPolicy::view`. Ekspor tetap milik Admin |

## D. Dashboard Personel BE (17 fitur)

| # | Fitur | Status | Catatan data |
|---|---|---|---|
| 1 | Daftar Tugas Pribadi | ✅ | `/tugas` — Staf hanya melihat tugas dengan `pic_id` miliknya (`scopeTerlihatOleh`), lengkap dengan tenggat, prioritas, lencana status, dan penanda terlambat |
| 2 | Pengingat Tenggat & Prioritas | 🟡 | `tenggat` + `prioritas` |
| 3 | Tombol Pintas Log Harian | 🟡 | `activity_logs` |
| 4 | Daftar Revisi & Tindak Lanjut | 🟡 | `action_items` + `reviews` |
| 5 | Countdown SLA Risalah | 🟡 | kolom tenggat tersimpan |
| 6 | Kartu Skor Evaluasi Pribadi | 🟡 | `reviews` + `kpi_results` |
| 7 | Menu Profil Akun | 🟡 | `users` + rute `user-password.update` (Fortify) |
| 8 | Form Update Progres Tugas | ✅ | Form progres pada `/tugas/{tugas}` (0–100 + kendala) — status berpindah otomatis ke Dalam Proses, dan turun di bawah 100 mengembalikan pengajuan ke Dalam Proses |
| 9 | Tombol Lapor Kendala Dini | 🟡 | status `terhambat` + `kendala` + `is_kendala_dini` |
| 10 | Form Log Aktivitas Harian | 🟡 | tautan wajib ke tugas/JD (CHECK) |
| 11 | Generator Laporan Pribadi | 🟡 | `reports` 4 jenis + `narasi_evaluasi` |
| 12 | Form Unggah Bukti Kerja | 🟡 | `versi` + `induk_id`; otorisasi unduh per klasifikasi aktif, guard hapus Final/Diarsipkan belum |
| 13 | Form Register Rapat | 🟡 | `meetings` + `waktu_selesai` |
| 14 | Panel Review & Revisi | 🟡 | `reviews` + `reports.induk_id` |
| 15 | Panel Tindak Lanjut Arahan | ✅ | `/arahan/{arahan}` — action item dengan PIC, tenggat, form ubah status (wajib alasan), verifikasi dan penutupan oleh pemegang `action_item.verify` (wajib alasan + bukti). Item belum tuntas tetap dihitung tunggakan |
| 16 | Menu Job Description Sendiri | ✅ | `/master/job-description` — Staf hanya melihat JD untuk jabatannya sendiri (Policy `JobDescriptionPolicy::view`), versi berlaku & status penetapan tampil |
| 17 | Panel Klarifikasi KPI | 🟡 | status `menunggu_klarifikasi` + `klarifikasi` |

## E. Panel Administrator (10 fitur)

| # | Fitur | Status | Catatan data |
|---|---|---|---|
| 1 | Panel Status Teknis | ⬜ | belum ada |
| 2 | Modul Manajemen Akun | 🟡 | `users.is_active`, `last_login_at`, tabel `password_reset_tokens`; izin `user.view/create/update/deactivate` sudah ditegakkan server-side (`UserPolicy`), layar CRUD belum ada |
| 3 | Master Personel & Jabatan | ✅ | `/master/personel` (CRUD + nonaktifkan beralasan), `/master/jabatan`, `/master/bidang`; penulisan khusus Admin (`PersonelPolicy`/`JabatanPolicy`/`BidangPolicy`), Staf hanya barisnya sendiri, kolom kontak (`telepon`, email akun) hanya untuk pemilik baris dan Admin |
| 4 | Konfigurasi Matriks RBAC | 🟡 | Spatie v6 + 4 peran + 56 izin ter-seed (`PeranIzinSeeder`), 15 Policy, middleware `peran`/`izin`/`izin_modul`; UI pengaturan izin belum |
| 5 | Pengaturan Klasifikasi Dokumen | 🟡 | enum 3 tingkat + penegakan akses `rahasia_keuangan` di `DocumentPolicy::download`; kebijakan retensi belum |
| 6 | Panel Metadata & Storage | 🟡 | `mime_type`, `file_size`, `checksum`, `file_path` |
| 7 | Master Job Description | ✅ | `/master/job-description`: daftar per jabatan, detail, draf (DE/Admin), penetapan target+indikator+frekuensi+`berlaku_mulai` oleh Admin (alasan wajib), arsip, versi baru. Baris yang ditetapkan terkunci di level model; revisi = versi baru |
| 8 | Konfigurasi Rumus Berversi | 🟡 | `rule_version` + kolom bobot per baris; versi kebijakan per jabatan belum |
| 9 | Panel Backup & Recovery | ⬜ | belum ada |
| 10 | Modul Audit Trail Sistem | ✅ | `/audit` (daftar, filter, detail before/after) + `/audit/ekspor` (CSV 5.000 baris, mencatat `ekspor.audit`). Append-only di tiga lapis — Administrator pun tidak bisa mengubah/menghapus baris. Kewajiban hak DB produksi (tanpa `DELETE`/`TRUNCATE` pada `audit_events`) tercatat di `docs/operasional/keamanan-audit.md` |

---

## F. Rekap angka

| Kategori | Jumlah |
|---|---|
| Fitur layar di `fitur.pdf` | 75 (Pengurus 21, DE 27, Staf 17, Admin 10) |
| ✅ selesai penuh | 21 (Pengurus 6, DE 8, Staf 4, Admin 3) |
| 🟡 fondasi database + gerbang RBAC siap | 47 (Pengurus 12, DE 17, Staf 13, Admin 5) |
| ⬜ belum ada | 7 (Pengurus 3, DE 2, Staf 0, Admin 2) |
| Butir Tahap 1 (skema/relasi/zona waktu) | 3 dari 3 ✅ |
| Butir Tahap 2 (login/routing/RBAC) | 1 dari 1 ✅ |
| Butir Tahap 3 (master personel/jabatan/JD) | 1 dari 1 ✅ |
| Butir Tahap 4 (audit trail append-only) | 1 dari 1 ✅ |
| Butir Tahap 5 (penugasan/arahan/state machine) | 1 dari 1 ✅ |

7 fitur ⬜: Indikator Last Updated (Pengurus, DE), Indikator Evaluasi Bulanan (Pengurus,
DE), Filter Pencarian Data, Panel Status Teknis, Panel Backup & Recovery.

## G. Celah yang masih terbuka

Tahap 2 menutup celah "belum ada pengecekan server-side". Tahap 3 menutup celah "identitas
pelapor bisa dipalsukan" dan "standar evaluasi bisa berubah di tengah periode". Tahap 4
menutup celah "perubahan skor/bobot/status terverifikasi tanpa alasan" dan "rapat tidak
teraudit". Tahap 5 menutup celah "transisi status masih bebas" (state machine tugas) dan
"arahan langsung Pengurus tidak terlihat DE" (notifikasi wajib ke DE). Yang tersisa:

1. **Guard hapus dokumen Final/Diarsipkan** belum ada di model maupun DB.
2. **Action item otomatis** untuk laporan skor < 70 belum dibuat.
3. **Isi ekspor modul lain masih placeholder** — rute dan izin `export` sudah benar, dan
   ekspor audit (`/audit/ekspor`) sudah berisi baris nyata; rekapitulasi rinci 11 modul
   lain menyusul di Tahap 11.
4. **Layar operasional modul lain** (log aktivitas, laporan, dokumen, rapat, review, KPI)
   belum ada; baru penugasan dan arahan yang punya layar penuh.
5. **Akun tanpa baris Master Personel** ditolak saat mengirim laporan (trait `MilikSesiPelapor`),
   tetapi belum ada layar penautan massal akun → personel; saat ini lewat `/master/personel` satu per satu.
6. **UI konfigurasi RBAC** belum ada; matriks izin masih diatur lewat `PeranIzinSeeder`.
7. **`TRUNCATE` pada `audit_events` tidak dapat dicegah trigger** MySQL/MariaDB. Selama
   pengembangan aplikasi memakai `root`; di produksi wajib memakai akun aplikasi tanpa hak
   `DELETE`/`TRUNCATE` (lihat `docs/operasional/keamanan-audit.md`).
