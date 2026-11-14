# Tahap 2 — Autentikasi Akun & Otorisasi Server-Side (RBAC)

Tanggal: 14 November 2026
Acuan: `Proposal_dan_PRD_ERP_HIMKI_Lengkap_Final.docx` (FR-01, E.2, AC-01/AC-10/AC-12), `fitur.pdf` Tahap 2, skill `laravel-owasp-security`.
Prasyarat: Tahap 1 selesai (14 entitas + relasi + 41 CHECK native + audit append-only + zona waktu server).

## 1. Ruang lingkup

| Butir Tahap 2 (fitur.pdf) | Wujud implementasi |
|---|---|
| Gerbang login akun individual (email/username + kata sandi), tanpa halaman promosi publik | Laravel Fortify (backend auth resmi) + view Blade kustom. Registrasi publik dimatikan. `/` langsung mengarah ke login atau ruang kerja. |
| Manajemen sesi aman | Sesi database, regenerasi ID saat login, invalidasi total saat logout, sesi lain dicabut saat login baru, `AuthenticateSession` memutus sesi bila kata sandi berubah, umur sesi 30 menit, cookie `HttpOnly` + `SameSite=Lax` + payload terenkripsi. |
| Pemulihan sandi | Fortify `resetPasswords` (token 60 menit, throttle 60 detik) + halaman lupa sandi/atur ulang sandi. |
| Auto-routing peran | `LoginResponse` kustom → `App\Support\RutePeran` memilih 4 ruang kerja: `/pengurus`, `/de`, `/staf`, `/admin`. |
| Middleware RBAC server-side untuk Create/Read/Update/Verify-Score/Export pada 12 menu | Alias middleware `peran:` + `izin:` (Spatie), 12 Policy Eloquent, gerbang ekspor `/ekspor/{modul}`, gerbang unduhan `/dokumen/{dokumen}/unduh`. |
| Larang Admin Teknis menilai pekerjaan | Admin tidak diberi izin penilaian di seeder **dan** `Gate::before` menolak seluruh kemampuan penilaian untuk peran `admin` (pertahanan berlapis). |

## 2. Keputusan teknis

| Keputusan | Pilihan | Alasan |
|---|---|---|
| Pustaka autentikasi | `laravel/fortify:^1.42` | Checklist OWASP melarang auth buatan sendiri; Fortify headless sehingga tidak memaksa halaman registrasi/profil bawaan seperti Breeze. |
| Field login | satu field `login` | Menerima email **atau** username; validasi Fortify tetap `required|string`. |
| Resolusi identitas | `App\Actions\Fortify\AttemptToAuthenticate` | Cari `email` atau `username`, verifikasi kata sandi lebih dulu, baru tolak akun nonaktif (tidak membocorkan status akun tanpa kata sandi benar). |
| Hash umpan | bcrypt dummy saat identitas tidak ditemukan | Menyamakan waktu respons agar tidak bisa dipakai menebak keberadaan akun (timing enumeration). |
| Rate limit login | `throttle:login` 5 percobaan/menit per `login|IP` | Batas brute force; kegagalan juga dicatat ke audit trail. |
| Otorisasi | Spatie `laravel-permission` + Policy Eloquent | Permission `modul.aksi` sudah di-seed sejak Tahap 1; Policy menambah cakupan baris (row-level). |
| Nama aksi | `view`, `create`, `update`, `delete`, `verify`, `export`, `submit`, `finalize`, `deactivate`, `configure` | Memetakan Create/Read/Update/Verify-Score/Export pada 12 menu. |
| Cakupan baris | Staf hanya baris miliknya; DE/Pengurus sesuai izin; Admin tanpa substansi operasional | AC-01: pengguna hanya melihat data sesuai peran dan cakupan. |
| 12 menu | `assignment`, `activity_log`, `report`, `document`, `review`, `feedback`, `action_item`, `kpi`, `meeting`, `personel`, `user`, `audit` | Sama dengan 12 modul izin yang sudah ada sejak Tahap 1. |
| Audit peristiwa auth | `AuditEvent` append-only | Login, login gagal, logout, reset sandi, ganti sandi, akses ditolak, unduhan dokumen (AC-10). |
| Header keamanan | Middleware `LindungiRespons` | CSP, `X-Frame-Options: DENY`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, HSTS saat HTTPS. |

## 3. Matriks izin final

Aksi per modul (12 menu):

| Modul | Aksi |
|---|---|
| assignment | view, create, update, delete, verify, export |
| activity_log | view, create, update, export |
| report | view, create, update, submit, verify, export |
| document | view, create, update, delete, export |
| review | view, create, update, verify, export |
| feedback | view, create, update |
| action_item | view, create, update, verify, export |
| kpi | view, create, update, finalize, configure, export |
| meeting | view, create, update, export |
| personel | view, create, update, deactivate, export |
| user | view, create, update, deactivate |
| audit | view, export |

Pemetaan peran:

- **Pengurus** — baca lintas personel + beri arahan; tidak menilai/mengubah skor teknis. Izin: `assignment.view/create/update/verify/export`, `activity_log.view/export`, `report.view/verify/export`, `document.view/create/export`, `review.view/create/verify`, `feedback.view/create`, `action_item.view`, `kpi.view/finalize/export`, `meeting.view/create/export`, `personel.view/export`, `audit.view`.
- **DE** — kendali operasional penuh termasuk penilaian. Izin: seluruh aksi operasional kecuali `kpi.configure`, `user.*`, `audit.export`.
- **Staf** — eksekusi milik sendiri. Izin: `assignment.view/update`, `activity_log.view/create/update/export`, `report.view/create/update/submit/export`, `document.view/create/update/export`, `review.view`, `feedback.view`, `action_item.view/update`, `kpi.view`, `meeting.view`.
- **Admin** — teknis, tanpa penilaian. Izin: `user.*`, `personel.view/create/update/deactivate`, `document.view/update`, `kpi.view/configure`, `audit.view/export`.

`Gate::before` menolak untuk peran `admin`: `review.create`, `review.update`, `review.verify`, `report.verify`, `kpi.create`, `kpi.update`, `kpi.finalize`, `assignment.verify`, `action_item.verify`, `feedback.create`, `feedback.update`.

## 4. Alur login

```
GET /login        → view auth.login (tanpa halaman promosi)
POST /login       → throttle:login
                    → AttemptToAuthenticate:
                        cari email/username → Hash::check
                        gagal  : increment limiter + ValidationException + audit auth.login_gagal
                        nonaktif: tolak (pesan khusus) + audit auth.login_gagal (alasan akun_nonaktif)
                        sukses : guard->login + catatLogin + audit auth.login
                    → PrepareAuthenticatedSession: session()->regenerate()
                    → LoginResponse: cabut sesi lain milik user → redirect intended / dashboard peran
POST /logout      → guard->logout + session invalidate + regenerateToken + audit auth.logout
```

Pengguna tanpa peran ditolak saat login (bukan diarahkan ke dashboard kosong).

## 5. Gerbang otorisasi

```
Route::middleware(['auth', 'aktif'])          // aktif = PastikanAkunAktif
  /pengurus   → peran:pengurus
  /de         → peran:de
  /staf       → peran:staf
  /admin      → peran:admin
  /modul/{m}  → izin:{m}.view
  /ekspor/{m} → izin:{m}.export
  /dokumen/{d}/unduh → izin:document.view + Policy@download
```

Setiap penolakan menghasilkan 403 dan dicatat sebagai `auth.akses_ditolak` pada audit trail.

## 6. Cakupan baris (Policy)

| Policy | Aturan tambahan |
|---|---|
| AssignmentPolicy | staf: hanya `pic_id` dirinya |
| ActivityLogPolicy | staf: hanya `personel_id` dirinya |
| ReportPolicy | staf: hanya laporan sendiri |
| DocumentPolicy | staf: pemilik/pengunggah; `rahasia_keuangan` hanya Pengurus/DE; admin tidak boleh unduh substansi |
| ReviewPolicy | staf: hanya review atas laporan sendiri |
| FeedbackPolicy | staf: hanya penerima/pemberi dirinya |
| ActionItemPolicy | staf: hanya `pic_id` dirinya |
| KpiResultPolicy | staf: hanya `personel_id` dirinya |
| MeetingPolicy | staf: peserta rapat atau pencatat |
| PersonelPolicy / UserPolicy | staf: hanya dirinya |
| AuditEventPolicy | hanya peran dengan `audit.view` |

## 7. Yang belum termasuk Tahap 2

- Isi data ekspor per modul (Tahap 11) — gerbang izin, route, dan header CSV sudah aktif.
- Layar modul (Tahap 3–11) — route `/modul/{m}` menampilkan status modul + kemampuan pengguna.
- 2FA/passkey (tidak diminta PRD; dapat ditambah tanpa mengubah matriks).
- Ubah nama/email sendiri (menu Profil Akun baru sebagian: baca akun + ganti sandi).

## 8. Kriteria penerimaan

1. Guest membuka rute terlindungi → 302 ke `/login`.
2. Login email dan login username sama-sama berhasil; salah sandi gagal dengan pesan generik.
3. Percobaan ke-6 dalam satu menit → HTTP 429.
4. Setelah login, ID sesi berubah dan sesi lama pengguna yang sama dicabut.
5. Akun `is_active = false` tidak bisa login; akun yang dinonaktifkan saat sesi berjalan langsung diputus.
6. Setiap peran hanya bisa membuka ruang kerjanya (403 untuk ruang kerja lain).
7. Admin menerima 403 pada seluruh aksi penilaian (`review.*`, `report.verify`, `kpi.finalize`).
8. Staf tidak bisa melihat/mengubah baris personel lain (Policy).
9. Unduhan dokumen `rahasia_keuangan` ditolak untuk staf; pemilik tetap boleh mengunduh miliknya.
10. Ekspor modul tanpa izin → 403; dengan izin → 200 `text/csv`.
11. Peristiwa login, login gagal, logout, dan akses ditolak tercatat di `audit_events`.
12. Registrasi publik tidak tersedia (404) dan tidak ada halaman promosi.
13. `php artisan test`, `pint --test`, `composer validate` hijau.
