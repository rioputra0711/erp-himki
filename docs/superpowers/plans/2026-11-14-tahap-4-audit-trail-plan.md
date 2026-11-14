# Rencana Implementasi Tahap 4 — Layanan Audit Trail

Acuan desain: `docs/superpowers/specs/2026-11-14-tahap-4-audit-trail-design.md`
Stack: Laravel 12, PHP 8.2, Blade, spatie/laravel-permission ^6.25, MariaDB 10.4 (dev & test).

Status tiap langkah ditandai `- [ ]` / `- [x]`. Satu langkah = satu commit-worth perubahan yang dapat diverifikasi sendiri.

## Langkah 1 — Skema: snapshot pelaku & FK restrict

- [ ] Migrasi `2026_11_14_070001_add_actor_snapshot_to_audit_events_table.php`
  - `actor_nama` string(150) nullable, `actor_peran` string(20) nullable, keduanya setelah `actor_id`
  - `dropForeign(['actor_id'])` lalu `foreign('actor_id')->references('id')->on('users')->restrictOnDelete()`
  - `index('created_at')`
  - `down()` membalik semuanya (drop index, drop FK, restore `nullOnDelete`, drop kolom)
- [ ] `AuditEvent`: tambah `actor_nama`, `actor_peran` ke `$fillable`
- Verifikasi: `php artisan migrate` lalu `php artisan migrate:rollback --step=1` tanpa error

## Langkah 2 — `LabelAudit` (label Indonesia + penyamaran)

- [ ] `app/Support/LabelAudit.php`
  - `tindakan(string $event): string` → `created`, `updated`, `deleted`, `auth.*`, `akses.*`, `ekspor.*`, `identitas.*`; tak dikenal dikembalikan apa adanya
  - `objek(?string $class): string` → nama pendek + label modul (`App\Models\Assignment` → `Penugasan`)
  - `kolom(string $kolom): string` → label Indonesia untuk kolom lazim, fallback `Str::headline`
  - `nilai(mixed $nilai): string` → enum → `label()`, bool → Ya/Tidak, tanggal → `d/m/Y H:i`, array → JSON
  - `SENSITIF` (list) + `samarkan(array $baris): array` → `nip`, `telepon`, `password`, `remember_token`, `klarifikasi` jadi `••••`
  - `KELAS` peta nama pendek ↔ kelas untuk allowlist filter
- Verifikasi: dipakai langkah 7; dicek lewat tes langkah 12

## Langkah 3 — Penjaga wajib alasan di trait `Auditable`

- [ ] `Auditable`:
  - `protected function kolomWajibAlasan(): array` (default `[]`)
  - `protected function wajibAlasanUntuk(string $kolom, mixed $nilaiBaru): bool` (default `true`)
  - `bootAuditable()` tambah `static::saving(...)` **sebelum** registrasi lain: bila `exists`, iris `array_keys(getDirty())` dengan `kolomWajibAlasan()`, untuk tiap kolom panggil `wajibAlasanUntuk()`, bila lolos dan `alasanAudit` kosong → `DomainException`
  - `catatAudit()` mengisi `actor_nama` (`Auth::user()?->name`) dan `actor_peran` (`?->peranSistem()?->value`)
  - `alasanAudit` dikosongkan setelah `AuditEvent::create` berhasil
- Verifikasi: `php artisan test --filter=Tahap3` tetap hijau (JD freeze tidak berubah)

## Langkah 4 — Kolom sensitif per model

- [ ] `Review`: `skor_*` (5), `bobot_*` (5), `adalah_final`
- [ ] `KpiResult`: `skor_*` (5), `bobot_*` (5), `status`, `rule_version`
- [ ] `JobDescription`: `target`, `indikator`, `frekuensi`, `kategori`, `uraian`, `berlaku_mulai` — hanya bila `ditetapkan_pada === null`
- [ ] `Assignment`: `status`, `progres`, `tenggat`, `prioritas` — `status` wajib bila lama final atau baru `terverifikasi`; tiga lainnya wajib bila status lama final
- [ ] `Report`: `status` — wajib bila baru `terverifikasi`/`ditutup`/`dibatalkan`
- [ ] `Document`: `status` (baru `final`/`diarsipkan`), `klasifikasi` (selalu)
- [ ] `Meeting`: pakai trait `Auditable` (tanpa kolom wajib alasan)
- Verifikasi: tes langkah 12 butir 4–8

## Langkah 5 — Seeder tetap berjalan

- [ ] `JobDescriptionSeeder`: `firstOrNew` + `denganAlasan('Data awal job description')`; lewati baris yang sudah `ditetapkan_pada` terisi
- [ ] `DemoSeeder`: seluruh `updateOrCreate` pada model terjaga diubah ke `firstOrNew` + `fill` + `denganAlasan('Data demo')` + `save()`
- Verifikasi: `php artisan migrate:fresh --seed` selesai tanpa `DomainException`

## Langkah 6 — Audit perubahan hak akses

- [ ] `config/permission.php` → `events_enabled => true`
- [ ] `app/Listeners/CatatPerubahanAkses.php` menangani `RoleAttached`, `RoleDetached`, `PermissionAttached`, `PermissionDetached`; keluar bila `Auth::guest()`; tulis `AuditEvent::create` tanpa penangkap
- [ ] Daftarkan listener di `AppServiceProvider::boot()` (`Event::listen`)
- Verifikasi: tes langkah 12 butir 9

## Langkah 7 — Cakupan & otorisasi audit

- [ ] `PeranIzinSeeder`: tambah `audit.view` ke peran DE
- [ ] `AuditEvent::scopeTerlihatOleh(User $user)`: DE → hanya kelas operasional; peran lain tanpa batas
- [ ] `AuditEventPolicy::viewAny(User): bool` (izin `audit.view`), `view()` ikut memanggil `scopeTerlihatOleh` agar DE tidak bisa membuka peristiwa di luar cakupan lewat URL langsung
- Verifikasi: tes langkah 12 butir 10–11

## Langkah 8 — Request filter

- [ ] `app/Http/Requests/FilterAuditRequest.php`: `q` (string max 100), `tindakan` (allowlist), `objek` (allowlist termasuk `akses`), `aktor` (exists users), `dari`/`sampai` (`date_format:Y-m-d`), `urut` (`in:waktu,tindakan`), `arah` (`in:asc,desc`); `authorize()` = `can('viewAny', AuditEvent::class)`
- Verifikasi: tes langkah 12 butir 12

## Langkah 9 — Controller

- [ ] `app/Http/Controllers/AuditController.php`
  - `index()`: kueri `terlihatOleh` + filter + `orderBy` dari `match`, 50/halaman, `withQueryString()`
  - `show(AuditEvent $auditEvent)`: `authorize('view', ...)`, susun daftar perubahan (`created` → `after`, `deleted` → `before`, `updated` → irisan kunci), samarkan kolom sensitif untuk non-Admin
  - `ekspor()`: streaming CSV, batas 5.000 baris, metadata konteks + header, `PencatatAudit::catat('ekspor.audit', ...)`
- Verifikasi: tes langkah 12 butir 12–13

## Langkah 10 — Rute & navigasi

- [ ] `routes/web.php`: `GET /audit` (`audit.index`), `GET /audit/ekspor` (`audit.ekspor`, statis lebih dulu), `GET /audit/{auditEvent}` (`audit.show`); middleware `izin:audit.view` / `izin:audit.export`
- [ ] `EksporController::unduh`: bila `modul === 'audit'` → `redirect()->route('audit.ekspor', $request->query())`
- [ ] `layouts/app.blade.php`: tautan "Audit Trail" dengan `@can('audit.view')`
- Verifikasi: `php artisan route:list --name=audit`

## Langkah 11 — Tampilan

- [ ] `resources/views/audit/index.blade.php`: form filter, tabel (waktu WIB, pelaku, peran, tindakan, objek, alasan), paginasi, tombol ekspor hanya bila `audit.export`
- [ ] `resources/views/audit/show.blade.php`: kepala peristiwa + tabel kolom/sebelum/sesudah, `<pre>` untuk JSON, tanpa tombol ubah/hapus
- Verifikasi: buka di browser (smoke test), cek tidak ada `{!! !!}`

## Langkah 12 — Tes

- [ ] `tests/Feature/Tahap4AuditTrailTest.php` menutup 15 kriteria penerimaan desain bagian 11
- Verifikasi: `php artisan test`

## Langkah 13 — Dokumentasi & ceklis

- [ ] `docs/operasional/keamanan-audit.md`: hak database (`SELECT, INSERT` pada `audit_events`), perintah `GRANT`/`REVOKE`, kewajiban produksi
- [ ] `docs/superpowers/specs/2026-11-14-ceklis-fitur-vs-implementasi.md`: Tahap 4 ✅, badge Pengurus #21, DE #27, Admin #10, catatan celah yang tertutup (Meeting, reason)
- [ ] Regenerasi `fitur-ceklis.pdf` (script `ceklis_pdf.py`), hitungan badge disamakan
- Verifikasi: hitungan badge = rekap doc

## Langkah 14 — Verifikasi akhir & commit

- [ ] `php artisan test`, `pint --test`, `composer validate --strict`, `composer audit`, `npm run build`, `npm audit --omit=dev`
- [ ] Smoke test live 4 peran pada `/audit`
- [ ] Commit `tahap 4: layanan audit trail & layar penelusuran`
