# Rencana Implementasi Tahap 5 — Manajemen Penugasan & 3 Jalur Arahan Pimpinan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menghadirkan formulir penugasan, tiga jalur instruksi, modul arahan + action item, state machine tugas, notifikasi per akun, dan panel tunggakan — dengan sumber perintah serta identitas pemberi yang selalu berasal dari sesi, bukan dari formulir.

**Architecture:** Aturan domain ditegakkan di lapisan model (penjaga `saving`/`creating` + CHECK database), bukan di controller. Formulir hanya mengirim field yang punya aturan validasi; kolom sensitif diisi model dari sesi. Notifikasi dikirim setelah data utama tersimpan, dibungkus penangkap galat agar kegagalan kanal tidak membatalkan transaksi (PRD D.7).

**Tech Stack:** Laravel 12, PHP 8.2, Blade + Tailwind (Vite), spatie/laravel-permission ^6.25, MariaDB 10.4 (dev & test), PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-14-tahap-5-penugasan-design.md`

## Global Constraints

- Total izin tetap **56**; tidak ada izin baru. Hanya pemetaan peran yang berubah (Pengurus kehilangan `assignment.verify`).
- Tanpa paket baru. Notifikasi memakai tabel `notifications` bawaan Laravel + `Notifiable` yang sudah ada di `App\Models\User`.
- Semua waktu dari `App\Support\ServerClock::now()`, tidak pernah dari input.
- Semua formulir memakai `@csrf`; tampilan hanya `{{ }}`, tanpa `{!! !!}`.
- Setiap rute baru berada di dalam grup `['auth', 'aktif']` dan memakai `izin:*`.
- CHECK baru memakai `App\Support\CheckGuard::add()`; jangan menulis SQL DDL mentah untuk CHECK.
- `php artisan test` berjalan pada MySQL `erp_himki_baru_test`; suite awal **76 lulus / 337 asersi** harus tetap hijau.
- Perubahan `PeranIzinSeeder` wajib diikuti `php artisan db:seed --class=PeranIzinSeeder` + `php artisan permission:cache-reset` pada DB dev.

## Review Focus

1. **Menutup tugas saat `progres` < 100** — alasan + bukti terisi tetapi pekerjaan belum selesai. Harapan: ditolak `DomainException`, bukan tersimpan.
2. **`sumber_arahan_id` tidak sah** (milik DE lain, arahan bukan `asal = pengurus`, arahan sudah `selesai`) — harapan: 422 dengan pesan lapangan, bukan 500 dan bukan tugas tercipta.
3. **Dua DE membuat tugas pada detik yang sama** — harapan: kode `TGS-YYYY-NNNN` unik, percobaan kedua memakai nomor berikutnya, tidak ada galat 500.
4. **`dependensi_id` melingkar** (A→B→A) — harapan: ditolak 422, bukan rekursi tak berujung.
5. **Membuka notifikasi milik akun lain lewat URL** — harapan: 404 (bukan 403, agar keberadaan baris tidak bocor).

---

### Task 1: Migrasi skema Tahap 5

**Files:**
- Create: `database/migrations/2026_11_14_080001_add_tahap5_columns.php`
- Modify: `app/Support/CheckGuard.php` (tambah `drop()`)
- Test: `tests/Feature/Tahap5PenugasanTest.php` (kerangka + tes CHECK)

**Interfaces:**
- Produces: kolom `assignments.sumber_arahan_id|alasan_penutupan|bukti_penutupan|bukti_pembatalan|bukti_dokumen_id`; `feedback.pemberi_jabatan` + nilai enum `persetujuan`; `action_items.bukti_penutupan`; tabel `notifications`; `CheckGuard::drop(string $table, string $name): void`.

- [ ] **Step 1: Tulis tes CHECK yang gagal**

```php
#[Test]
public function database_menolak_penutupan_tanpa_alasan_dan_bukti(): void
{
    $tugas = Assignment::factory()->create(['status' => StatusPenugasan::Terverifikasi]);
    $this->expectException(QueryException::class);
    DB::table('assignments')->where('id', $tugas->id)->update([
        'status' => 'ditutup',
        'alasan_penutupan' => null,
        'bukti_penutupan' => null,
    ]);
}

#[Test]
public function database_menolak_persetujuan_dari_asal_de(): void
{
    $this->expectException(QueryException::class);
    DB::table('feedback')->insert([
        'pemberi_id' => Personel::factory()->create()->id,
        'jenis' => 'persetujuan', 'asal' => 'de', 'isi' => 'x',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter=database_menolak_penutupan_tanpa_alasan_dan_bukti`
Expected: FAIL — kolom `alasan_penutupan` belum ada.

- [ ] **Step 3: Tambah `CheckGuard::drop()`**

```php
public static function drop(string $table, string $name): void
{
    if (self::memakaiTrigger()) {
        self::lepas($table, $name);
        if (Schema::hasTable(self::TABEL_ATURAN)) {
            DB::table(self::TABEL_ATURAN)->where(['table_name' => $table, 'name' => $name])->delete();
        }
        return;
    }

    DB::statement(sprintf('ALTER TABLE %s DROP CONSTRAINT %s', $table, $name));
}
```

- [ ] **Step 4: Tulis migrasi**

`up()`:
- `Schema::table('assignments')`: `sumber_arahan_id` FK `feedback` `nullOnDelete`; `alasan_penutupan`, `bukti_penutupan`, `bukti_pembatalan` (text nullable); `bukti_dokumen_id` FK `documents` `restrictOnDelete`.
- `CheckGuard::add('assignments', 'assignments_penutupan_lengkap', "status <> 'ditutup' OR (alasan_penutupan IS NOT NULL AND trim(alasan_penutupan) <> '' AND bukti_penutupan IS NOT NULL AND trim(bukti_penutupan) <> '')")`
- `CheckGuard::add('assignments', 'assignments_pembatalan_bukti', "status <> 'dibatalkan' OR (bukti_pembatalan IS NOT NULL AND trim(bukti_pembatalan) <> '')")`
- `Schema::table('feedback')`: `pemberi_jabatan` string(100) nullable; `$table->enum('jenis', ['arahan','komentar','masukan','koreksi','persetujuan'])->change()`.
- `CheckGuard::add('feedback', 'feedback_persetujuan_pengurus', "jenis <> 'persetujuan' OR asal = 'pengurus'")`
- `Schema::table('action_items')`: `bukti_penutupan` text nullable.
- `CheckGuard::add('action_items', 'action_items_penutupan_bukti', "status <> 'ditutup' OR (bukti_penutupan IS NOT NULL AND trim(bukti_penutupan) <> '')")`
- `Schema::create('notifications')`: uuid PK, `type`, `morphs('notifiable')`, `text data`, `timestamp read_at` nullable, timestamps, `index(['notifiable_type','notifiable_id','read_at'])`.
- Baris terakhir `up()`: `CheckGuard::pulihkanSemua();` — migrasi ini berada setelah `050018_pulihkan_check_guard_triggers`, jadi trigger SQLite yang hilang akibat ALTER harus dipasang ulang di sini.

`down()`: panggil `CheckGuard::drop()` untuk kelima constraint, lalu `dropConstrainedForeignId`/`dropColumn` dan `Schema::dropIfExists('notifications')`.

Catatan: `->change()` enum aman di MariaDB (menghasilkan `MODIFY ... ENUM(...) NOT NULL`). Bila gagal, ganti dengan `DB::statement("ALTER TABLE feedback MODIFY jenis ENUM('arahan','komentar','masukan','koreksi','persetujuan') NOT NULL")` khusus driver `mysql`.

- [ ] **Step 5: Jalankan migrasi dua arah**

Run: `php artisan migrate && php artisan migrate:rollback --step=1 && php artisan migrate`
Expected: ketiganya sukses tanpa galat.

- [ ] **Step 6: Jalankan tes**

Run: `php artisan test --filter=Tahap5`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_11_14_080001_add_tahap5_columns.php app/Support/CheckGuard.php tests/Feature/Tahap5PenugasanTest.php
git commit -m "tahap 5: skema penugasan, arahan, tindak lanjut & notifikasi"
```

---

### Task 2: Matriks izin & label audit

**Files:**
- Modify: `database/seeders/PeranIzinSeeder.php`
- Modify: `app/Support/LabelAudit.php`
- Test: `tests/Feature/Tahap5PenugasanTest.php`

**Interfaces:**
- Produces: Pengurus tidak lagi memegang `assignment.verify`; label untuk `notifikasi.gagal` dan kolom baru.

- [ ] **Step 1: Tulis tes yang gagal**

```php
#[Test]
public function pengurus_tidak_memegang_izin_verifikasi_penugasan(): void
{
    $pengurus = $this->akun(PeranSistem::Pengurus);
    $this->assertFalse($pengurus->can('assignment.verify'));
    $this->assertTrue($pengurus->can('assignment.create'));
    $this->assertSame(56, Permission::query()->count());
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter=pengurus_tidak_memegang_izin_verifikasi_penugasan`
Expected: FAIL — Pengurus masih memegang `assignment.verify`.

- [ ] **Step 3: Ubah seeder**

Pada `izinPengurus()`, `ModulAplikasi::Penugasan` menjadi `AksiIzin::Lihat, AksiIzin::Buat, AksiIzin::Ubah, AksiIzin::Ekspor` (hapus `Verifikasi`). Tambah komentar: verifikasi/penutupan penugasan adalah kewenangan DE (desain §10).

- [ ] **Step 4: Lengkapi `LabelAudit`**

- `pilihanTindakan()` + `tindakan()`: `'notifikasi.gagal' => 'Notifikasi gagal'`.
- `kolom()`: `alasan_penutupan` → "Alasan penutupan", `bukti_penutupan` → "Bukti penutupan", `bukti_pembatalan` → "Bukti pembatalan", `pemberi_jabatan` → "Jabatan pemberi", `sumber_perintah` → "Sumber perintah", `sumber_arahan_id` → "Arahan asal", `pemberi_tugas_id` → "Pemberi tugas", `diteruskan_oleh_id` → "Diteruskan oleh".

- [ ] **Step 5: Tes + seeder dev**

Run: `php artisan test --filter=Tahap5`
Run: `php artisan db:seed --class=PeranIzinSeeder && php artisan permission:cache-reset`
Expected: PASS; seeder melaporkan 56 izin.

- [ ] **Step 6: Commit**

```bash
git add database/seeders/PeranIzinSeeder.php app/Support/LabelAudit.php tests/Feature/Tahap5PenugasanTest.php
git commit -m "tahap 5: matriks izin penugasan & label audit baru"
```

---

### Task 3: Peta transisi `StatusPenugasan` + penjaga state machine

**Files:**
- Modify: `app/Enums/StatusPenugasan.php`
- Modify: `app/Models/Assignment.php`
- Test: `tests/Feature/Tahap5PenugasanTest.php`

**Interfaces:**
- Produces: `StatusPenugasan::bolehKe(self $tujuan): bool`.
- Produces: `Assignment` menolak transisi tak sah, mewajibkan `alasan_penutupan` + `bukti_penutupan` saat `ditutup`, `alasan_pembatalan` + `bukti_pembatalan` saat `dibatalkan`, `kendala` saat `terhambat`, `progres = 100` saat `diajukan_selesai`, serta mengisi `ditutup_pada`/`diverifikasi_pada` dari `ServerClock`.

- [ ] **Step 1: Tulis tes transisi**

```php
#[Test]
public function transisi_status_tidak_sah_ditolak_tanpa_mengubah_baris(): void
{
    $tugas = Assignment::factory()->create(['status' => StatusPenugasan::Draft]);

    try {
        $tugas->update(['status' => StatusPenugasan::Terverifikasi]);
        $this->fail('Transisi draft -> terverifikasi seharusnya ditolak.');
    } catch (DomainException) {
    }

    $this->assertSame(StatusPenugasan::Draft, $tugas->fresh()->status);
}

#[Test]
public function penutupan_wajib_alasan_dan_bukti_serta_mengisi_waktu_server(): void
{
    $tugas = Assignment::factory()->create([
        'status' => StatusPenugasan::Terverifikasi,
        'progres' => 100,
    ]);

    try {
        $tugas->update(['status' => StatusPenugasan::Ditutup]);
        $this->fail('Penutupan tanpa alasan/bukti seharusnya ditolak.');
    } catch (DomainException) {
    }

    $tugas->forceFill([
        'alasan_penutupan' => 'Seluruh keluaran diterima',
        'bukti_penutupan' => 'Berita acara 12/HIMKI/XI/2026',
    ])->denganAlasan('Bukti penutupan dilengkapi')->save();
    $tugas->denganAlasan('Seluruh keluaran diterima')->update(['status' => StatusPenugasan::Ditutup]);

    $this->assertNotNull($tugas->fresh()->ditutup_pada);
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter=transisi_status_tidak_sah_ditolak_tanpa_mengubah_baris`
Expected: FAIL — transisi saat ini bebas.

- [ ] **Step 3: Tambah `bolehKe()`**

```php
public function bolehKe(self $tujuan): bool
{
    return in_array($tujuan, match ($this) {
        self::Draft => [self::Aktif, self::Dibatalkan],
        self::Aktif => [self::DalamProses, self::Terhambat, self::Dibatalkan],
        self::DalamProses => [self::Terhambat, self::DiajukanSelesai, self::Dibatalkan],
        self::Terhambat => [self::DalamProses, self::DiajukanSelesai, self::Dibatalkan],
        self::DiajukanSelesai => [self::DalamProses, self::Terverifikasi, self::Dibatalkan],
        self::Terverifikasi => [self::Ditutup],
        self::Ditutup, self::Dibatalkan => [],
    }, true);
}
```

- [ ] **Step 4: Perluas `Assignment::booted()`**

Di dalam `saving`, sebelum aturan lama:
- `$lama = $this->exists ? StatusPenugasan::tryFrom((string) $this->getOriginal('status')) : null;`
- bila `$lama !== null && $this->status !== $lama && ! $lama->bolehKe($this->status)` → `DomainException('Transisi status ... tidak sah.')`.
- bila ada sesi dan status baru `Terverifikasi`/`Ditutup` dan akun tidak memegang `assignment.verify` → `DomainException`.
- `Ditutup`: `alasan_penutupan` dan `bukti_penutupan` wajib non-kosong; `progres` wajib 100; isi `ditutup_pada ??= ServerClock::now()`.
- `Dibatalkan`: `alasan_pembatalan` dan `bukti_pembatalan` wajib non-kosong.
- `Terhambat`: `kendala` wajib non-kosong.
- `DiajukanSelesai`: `progres === 100`.
- `Terverifikasi`: `diverifikasi_oleh ??= personel sesi`; `diverifikasi_pada ??= ServerClock::now()`.
- Perluas `wajibAlasanUntuk('status', $baru)`: kembalikan `true` bila `$baru` salah satu dari `Terverifikasi`, `Ditutup`, `Dibatalkan` (desain §7.2 menuntut alasan audit pada ketiganya), selain aturan lama (`$lamaFinal`).

Urutan hook penting: `Auditable` mendaftar `saving` lebih dulu, jadi `denganAlasan()` wajib dipanggil sebelum `save()` pada transisi `terverifikasi`/`ditutup`/`dibatalkan`.

- [ ] **Step 5: Jalankan tes**

Run: `php artisan test --filter=Tahap5`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Enums/StatusPenugasan.php app/Models/Assignment.php tests/Feature/Tahap5PenugasanTest.php
git commit -m "tahap 5: state machine penugasan & kewajiban bukti penutupan"
```

---

### Task 4: Sumber perintah otomatis & kode unik

**Files:**
- Modify: `app/Models/Assignment.php`
- Test: `tests/Feature/Tahap5PenugasanTest.php`

**Interfaces:**
- Produces: `Assignment::tetapkanSumberPerintah(SumberPerintah $sumber, ?Personel $pemberi, ?Personel $penerus = null): static`
- Produces: `Assignment::buatDenganKodeUnik(callable $isi): self`
- Produces: `Assignment::sumberArahan(): BelongsTo`, `Assignment::scopeTerlihatOleh(Builder $q, ?User $user): Builder`, `Assignment::dependensiBerputar(?int $id): bool`

- [ ] **Step 1: Tulis tes**

```php
#[Test]
public function sumber_perintah_dan_pemberi_tugas_diambil_dari_sesi_bukan_formulir(): void
{
    $de = $this->akun(PeranSistem::DirekturEksekutif);
    $personelDe = Personel::factory()->create(['user_id' => $de->id]);
    $pic = Personel::factory()->create();
    $this->actingAs($de);

    $tugas = Assignment::buatDenganKodeUnik(function (Assignment $t) use ($pic): void {
        $t->fill([
            'judul' => 'Susun laporan', 'deskripsi' => 'Rincian', 'pic_id' => $pic->id,
            'kategori' => KategoriTugas::Umum, 'target_keluaran' => 'Dokumen',
            'prioritas' => PrioritasTugas::Sedang,
            'tanggal_mulai' => ServerClock::now()->toDateString(),
            'tenggat' => ServerClock::now()->addDays(7),
            // upaya pemalsuan:
            'sumber_perintah' => SumberPerintah::PengurusLangsung,
            'pemberi_tugas_id' => $pic->id,
        ]);
    });

    $this->assertSame(SumberPerintah::DeLangsung, $tugas->sumber_perintah);
    $this->assertSame($personelDe->id, $tugas->pemberi_tugas_id);
    $this->assertMatchesRegularExpression('/^TGS-\d{4}-\d{4}$/', $tugas->kode);
}

#[Test]
public function sumber_perintah_tidak_dapat_diubah_setelah_dibuat(): void
{
    $tugas = Assignment::factory()->create();
    $this->expectException(DomainException::class);
    $tugas->update(['sumber_perintah' => SumberPerintah::PengurusMelaluiDe]);
}

#[Test]
public function dependensi_melingkar_ditolak(): void
{
    $a = Assignment::factory()->create();
    $b = Assignment::factory()->create(['dependensi_id' => $a->id]);
    $this->assertTrue($a->dependensiBerputar($b->id));
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter=sumber_perintah_dan_pemberi_tugas_diambil_dari_sesi_bukan_formulir`
Expected: FAIL — `buatDenganKodeUnik` belum ada.

- [ ] **Step 3: Implementasi**

- `tetapkanSumberPerintah()`: `setAttribute()` untuk ketiga kolom, `return $this`.
- `terapkanSumberPerintahSesi(): void` (protected, dipanggil dari `creating`) — satu-satunya pemanggil `tetapkanSumberPerintah()`, sehingga hanya ada satu tempat yang menulis ketiga kolom:
  - tanpa sesi → `return`.
  - `$personel = IdentitasPelapor::dariSesi()`; null → `DomainException('Akun belum tertaut Master Personel.')`.
  - `$arahan = $this->sumber_arahan_id ? Feedback::find($this->sumber_arahan_id) : null;`
  - bila `$arahan !== null` → `PengurusMelaluiDe`, pemberi = `$arahan->pemberi`, penerus = `$personel`.
  - selain itu, peran `pengurus` → `PengurusLangsung`, pemberi = `$personel`, penerus `null`.
  - selain itu (DE) → `DeLangsung`, pemberi = `$personel`, penerus `null`.
  - selain DE/Pengurus (Staf/Admin) → `DomainException('Peran ini tidak dapat membuat penugasan.')`.
- `saving` (existing rows): tolak perubahan `kode`, `sumber_perintah`, `pemberi_tugas_id`, `diteruskan_oleh_id`.
- `buatDenganKodeUnik(callable $isi): self`: maksimal 3 percobaan; `new self()`, `$isi($model)`, `$model->save()`, `return`; bila `QueryException` pada percobaan < 3 → ulangi; selain itu lempar ulang.
- `scopeTerlihatOleh()`: Staf → `where('pic_id', personelId ?? 0)`; peran lain tanpa batas.
- `dependensiBerputar(?int $id): bool`: telusuri rantai `dependensi_id` maksimal 20 langkah; `true` bila bertemu `$this->getKey()` atau `$id`.
- `sumberArahan(): BelongsTo` → `Feedback::class, 'sumber_arahan_id'`.
- `$fillable`: hapus `kode`, `diverifikasi_oleh`, `diverifikasi_pada`, `ditutup_pada`; tambah `sumber_arahan_id`, `alasan_penutupan`, `bukti_penutupan`, `bukti_pembatalan`, `bukti_dokumen_id`. **`status` tetap `$fillable`** karena `DemoSeeder` dan `AssignmentFactory` mengisinya lewat `fill()`; keamanannya dijaga penjaga transisi + `Auditable`, dan `status` tidak pernah ada di `$rules` FormRequest.
- `AssignmentFactory`: state `dibatalkan()` wajib menambah `bukti_pembatalan` (CHECK baru menolak pembatalan tanpa bukti).

- [ ] **Step 4: Jalankan tes**

Run: `php artisan test --filter=Tahap5`
Expected: PASS. Bila factory gagal karena `status` tidak fillable, perbaiki factory lebih dulu (bukan dengan mengembalikan `status` ke `$fillable`).

- [ ] **Step 5: Commit**

```bash
git add app/Models/Assignment.php database/factories/AssignmentFactory.php tests/Feature/Tahap5PenugasanTest.php
git commit -m "tahap 5: sumber perintah dari sesi & kode tugas unik"
```

---

### Task 5: Identitas arahan & pemisahan persetujuan formal

**Files:**
- Modify: `app/Enums/JenisFeedback.php`
- Modify: `app/Models/Feedback.php`
- Modify: `database/factories/FeedbackFactory.php`
- Test: `tests/Feature/Tahap5PenugasanTest.php`

**Interfaces:**
- Produces: `JenisFeedback::Persetujuan` (`'persetujuan'`).
- Produces: `Feedback` mengisi `pemberi_id`, `pemberi_jabatan`, `asal` dari sesi; menolak `persetujuan` dari non-Pengurus; menolak perubahan `jenis` menjadi `persetujuan`; `Feedback::scopeTerlihatOleh()`; `Feedback::bolehDitutup(): bool`.

- [ ] **Step 1: Tulis tes**

```php
#[Test]
public function identitas_pemberi_arahan_dikunci_dari_sesi(): void
{
    $pengurus = $this->akun(PeranSistem::Pengurus);
    $personel = Personel::factory()->create(['user_id' => $pengurus->id]);
    $personel->load('jabatan');
    $penerima = Personel::factory()->create();
    $this->actingAs($pengurus);

    $arahan = Feedback::query()->create([
        'penerima_id' => $penerima->id, 'jenis' => JenisFeedback::Arahan,
        'asal' => AsalFeedback::De, 'isi' => 'Tindak lanjuti rapat',
        'pemberi_id' => $penerima->id, // upaya pemalsuan
    ]);

    $this->assertSame($personel->id, $arahan->pemberi_id);
    $this->assertSame(AsalFeedback::Pengurus, $arahan->asal);
    $this->assertSame($personel->jabatan?->nama, $arahan->pemberi_jabatan);
}

#[Test]
public function komentar_tidak_dapat_dinaikkan_menjadi_persetujuan(): void
{
    $arahan = Feedback::factory()->create(['jenis' => JenisFeedback::Komentar, 'asal' => AsalFeedback::Pengurus]);
    $this->expectException(DomainException::class);
    $arahan->update(['jenis' => JenisFeedback::Persetujuan]);
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter=komentar_tidak_dapat_dinaikkan_menjadi_persetujuan`
Expected: FAIL — enum `Persetujuan` belum ada.

- [ ] **Step 3: Implementasi**

- `JenisFeedback::Persetujuan = 'persetujuan'` + label "Persetujuan".
- `Feedback::booted()` tambahan:
  - bila ada sesi: `$personel = IdentitasPelapor::dariSesi()`; null → `DomainException`; `forceFill(['pemberi_id' => ..., 'pemberi_jabatan' => $personel->jabatan?->nama, 'asal' => peran === pengurus ? Pengurus : De])`.
  - `jenis === Persetujuan` dan `asal !== Pengurus` → `DomainException`.
  - `exists` dan `isDirty('jenis')` dan `jenis === Persetujuan` → `DomainException('Persetujuan formal harus berupa baris baru.')`.
  - `jenis === Persetujuan` dan `butuh_tindakan` → `DomainException`.
- `$fillable`: tambah `pemberi_jabatan` tidak perlu (diisi `forceFill`); biarkan `pemberi_id`/`asal` tetap fillable agar factory & seeder berjalan.
- `scopeTerlihatOleh()`: Staf → `where(fn($q) => $q->where('pemberi_id', $id)->orWhere('penerima_id', $id))`.
- `bolehDitutup(): bool` → `$this->actionItems()->whereNotIn('status', ['terverifikasi_selesai','ditutup'])->doesntExist()`.
- `FeedbackFactory`: tambah `pemberi_jabatan => null` tidak perlu; pastikan `jenis` acak tidak memakai `Persetujuan` (gunakan allowlist `[Arahan, Komentar, Masukan, Koreksi]`) agar CHECK `feedback_persetujuan_pengurus` tidak pecah saat `asal = de`.

- [ ] **Step 4: Jalankan tes**

Run: `php artisan test --filter=Tahap5`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Enums/JenisFeedback.php app/Models/Feedback.php database/factories/FeedbackFactory.php tests/Feature/Tahap5PenugasanTest.php
git commit -m "tahap 5: identitas arahan dari sesi & persetujuan formal terpisah"
```

---

### Task 6: Penjaga penutupan action item

**Files:**
- Modify: `app/Models/ActionItem.php`
- Test: `tests/Feature/Tahap5PenugasanTest.php`

**Interfaces:**
- Produces: `ActionItem::scopeTerlihatOleh(Builder $q, ?User $user): Builder`; `kolomWajibAlasan()` = `['status']`; guard `ditutup` wajib `bukti_penutupan`.

- [ ] **Step 1: Tulis tes**

```php
#[Test]
public function action_item_ditutup_wajib_bukti_dan_hanya_de(): void
{
    $staf = $this->akun(PeranSistem::Staf);
    $de = $this->akun(PeranSistem::DirekturEksekutif);
    $personelStaf = Personel::factory()->create(['user_id' => $staf->id]);
    Personel::factory()->create(['user_id' => $de->id]);

    $item = ActionItem::factory()->create(['pic_id' => $personelStaf->id, 'status' => StatusActionItem::DiajukanSelesai]);

    $this->actingAs($staf);
    $this->expectException(DomainException::class);
    $item->update(['status' => StatusActionItem::Ditutup]);
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter=action_item_ditutup_wajib_bukti_dan_hanya_de`
Expected: FAIL — penutupan saat ini bebas.

- [ ] **Step 3: Implementasi**

- `$fillable` tambah `bukti_penutupan`.
- `kolomWajibAlasan(): ['status']`; `wajibAlasanUntuk('status', $baru)` → `true` bila status baru `TerverifikasiSelesai`/`Ditutup`, atau status lama sudah salah satu dari itu.
- `booted()` tambahan di `saving`:
  - `Ditutup` dan `trim((string) $this->bukti_penutupan) === ''` → `DomainException`.
  - bila ada sesi dan status baru `TerverifikasiSelesai`/`Ditutup` dan akun tidak memegang `action_item.verify` → `DomainException`.
- `scopeTerlihatOleh()`: Staf → `where('pic_id', personelId ?? 0)`.

- [ ] **Step 4: Jalankan tes**

Run: `php artisan test --filter=Tahap5`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models/ActionItem.php tests/Feature/Tahap5PenugasanTest.php
git commit -m "tahap 5: penjaga penutupan action item"
```

---

### Task 7: Notifikasi

**Files:**
- Create: `app/Notifications/PenugasanBaru.php`, `PenugasanDiverifikasi.php`, `ArahanBaru.php`, `ArahanDiteruskan.php`, `TindakLanjutBaru.php`
- Create: `app/Support/PengirimNotifikasi.php`
- Test: `tests/Feature/Tahap5PenugasanTest.php`

**Interfaces:**
- Produces: `PengirimNotifikasi::penugasanBaru(Assignment $tugas): void`, `penugasanDiverifikasi(Assignment $tugas): void`, `arahanBaru(Feedback $arahan): void`, `arahanDiteruskan(Assignment $tugas): void`, `tindakLanjutBaru(ActionItem $item): void`.
- Produces: `protected function kirim(User $penerima, Notification $notifikasi): void` — satu-satunya titik pengiriman, agar dapat diganti di tes.

- [ ] **Step 1: Tulis tes**

```php
#[Test]
public function arahan_pengurus_ke_staf_mengirim_notifikasi_ke_staf_dan_de(): void
{
    $pengurus = $this->akun(PeranSistem::Pengurus);
    $de = $this->akun(PeranSistem::DirekturEksekutif);
    $staf = $this->akun(PeranSistem::Staf);
    Personel::factory()->create(['user_id' => $pengurus->id]);
    Personel::factory()->create(['user_id' => $de->id]);
    $personelStaf = Personel::factory()->create(['user_id' => $staf->id]);
    $this->actingAs($pengurus);

    $arahan = Feedback::query()->create([
        'penerima_id' => $personelStaf->id, 'jenis' => JenisFeedback::Arahan,
        'isi' => 'Perbaiki rekap', 'butuh_tindakan' => true,
    ]);
    app(PengirimNotifikasi::class)->arahanBaru($arahan);

    $this->assertCount(1, $staf->fresh()->notifications);
    $this->assertCount(1, $de->fresh()->notifications);
}

#[Test]
public function kegagalan_notifikasi_tidak_membatalkan_data_utama(): void
{
    $de = $this->akun(PeranSistem::DirekturEksekutif);
    $personelDe = Personel::factory()->create(['user_id' => $de->id]);
    $pic = Personel::factory()->create();
    $this->actingAs($de);

    app()->instance(PengirimNotifikasi::class, new class extends PengirimNotifikasi {
        protected function kirim(User $penerima, \Illuminate\Notifications\Notification $notifikasi): void
        {
            throw new RuntimeException('kanal mati');
        }
    });

    $tugas = Assignment::buatDenganKodeUnik(fn (Assignment $t) => $t->fill([
        'judul' => 'Uji', 'deskripsi' => 'Uji', 'pic_id' => $pic->id,
        'kategori' => KategoriTugas::Umum, 'target_keluaran' => 'X',
        'prioritas' => PrioritasTugas::Sedang,
        'tanggal_mulai' => ServerClock::now()->toDateString(),
        'tenggat' => ServerClock::now()->addDay(),
    ]));
    app(PengirimNotifikasi::class)->penugasanBaru($tugas);

    $this->assertDatabaseHas('assignments', ['id' => $tugas->id]);
    $this->assertDatabaseHas('audit_events', ['event' => 'notifikasi.gagal']);
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter=arahan_pengurus_ke_staf_mengirim_notifikasi_ke_staf_dan_de`
Expected: FAIL — kelas belum ada.

- [ ] **Step 3: Implementasi kelas notifikasi**

Setiap kelas `extends Notification`, `via()` → `['database']`, `toDatabase($notifiable): array` mengembalikan `['judul', 'pesan', 'url', 'jenis', 'objek_id']`. `url` dari `route('tugas.show', $id)` / `route('arahan.show', $id)` / `route('tugas.show', $item->assignment_id)` (null → `route('notifikasi.index')`). Tidak ada NIP atau isi dokumen di `data`.

- [ ] **Step 4: Implementasi `PengirimNotifikasi`**

- `penerima(Assignment $tugas)`: `$tugas->pic?->user`; bila `sumber_perintah === PengurusLangsung` tambah seluruh akun peran `de` yang `is_active`.
- `penerima(Feedback $arahan)`: `$arahan->penerima?->user`; bila `asal === Pengurus` tambah seluruh akun `de`.
- `arahanDiteruskan`: `$tugas->sumberArahan?->pemberi?->user`.
- `tindakLanjutBaru`: `$item->pic?->user`.
- Semua penerima: `unique('id')`, buang `null`, buang `is_active === false`.
- Setiap method publik membungkus loop `kirim()` dengan `try/catch (Throwable $e)`; pada galat tulis `PencatatAudit::catat('notifikasi.gagal', request(), auth()->user(), after: ['kelas' => $notifikasi::class, 'penerima_id' => $penerima->getKey()], reason: mb_substr($e->getMessage(), 0, 200))` dan `Log::warning(...)`. Jangan pernah melempar ulang.

- [ ] **Step 5: Jalankan tes**

Run: `php artisan test --filter=Tahap5`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Notifications app/Support/PengirimNotifikasi.php tests/Feature/Tahap5PenugasanTest.php
git commit -m "tahap 5: notifikasi penugasan, arahan & tindak lanjut"
```

---

### Task 8: `RingkasanTunggakan`

**Files:**
- Create: `app/Support/RingkasanTunggakan.php`
- Test: `tests/Feature/Tahap5PenugasanTest.php`

**Interfaces:**
- Produces: `RingkasanTunggakan::untuk(User $user): array{tugas: Collection, tindakLanjut: Collection, arahan: Collection, jumlah: int}` — maksimal 5 baris per kategori.

- [ ] **Step 1: Tulis tes**

```php
#[Test]
public function tunggakan_staf_hanya_miliknya_sendiri(): void
{
    $staf = $this->akun(PeranSistem::Staf);
    $personel = Personel::factory()->create(['user_id' => $staf->id]);
    $lain = Personel::factory()->create();

    Assignment::factory()->create(['pic_id' => $personel->id, 'status' => StatusPenugasan::Aktif]);
    Assignment::factory()->create(['pic_id' => $lain->id, 'status' => StatusPenugasan::Aktif]);

    $ringkas = RingkasanTunggakan::untuk($staf);

    $this->assertCount(1, $ringkas['tugas']);
    $this->assertSame($personel->id, $ringkas['tugas']->first()->pic_id);
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter=tunggakan_staf_hanya_miliknya_sendiri`
Expected: FAIL — kelas belum ada.

- [ ] **Step 3: Implementasi**

- `tugas`: `Assignment::query()->terlihatOleh($user)->whereNotIn('status', final)->orderBy('tenggat')->limit(5)->get()`.
- `tindakLanjut`: `ActionItem::query()->terlihatOleh($user)->tunggakan()->orderBy('tenggat')->limit(5)->get()`.
- `arahan`: `Feedback::query()->terlihatOleh($user)->butuhTindakan()->where('status', '!=', 'selesai')->orderBy('tenggat')->limit(5)->get()`.
- `jumlah`: jumlah ketiga koleksi.
- Admin: kembalikan semua koleksi kosong (`CakupanPengguna::adalahAdmin($user)`), karena Admin tidak memegang modul operasional.

- [ ] **Step 4: Jalankan tes**

Run: `php artisan test --filter=Tahap5`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Support/RingkasanTunggakan.php tests/Feature/Tahap5PenugasanTest.php
git commit -m "tahap 5: ringkasan tunggakan dashboard"
```

---

### Task 9: Policy & FormRequest

**Files:**
- Modify: `app/Policies/AssignmentPolicy.php`, `app/Policies/FeedbackPolicy.php`, `app/Policies/ActionItemPolicy.php`
- Create: `app/Http/Requests/SimpanPenugasanRequest.php`, `SimpanArahanRequest.php`, `FilterPenugasanRequest.php`, `FilterArahanRequest.php`
- Test: `tests/Feature/Tahap5PenugasanTest.php`

**Interfaces:**
- Produces: `AssignmentPolicy::sunting|progres|ajukan|verify|tutup|batalkan|aktifkan`
- Produces: `FeedbackPolicy::setujui`
- Produces: `ActionItemPolicy::verify|tutup`

- [ ] **Step 1: Tulis tes otorisasi**

```php
#[Test]
public function staf_tidak_dapat_membuka_tugas_personel_lain(): void
{
    $staf = $this->akun(PeranSistem::Staf);
    $personel = Personel::factory()->create(['user_id' => $staf->id]);
    $tugasLain = Assignment::factory()->create(['pic_id' => Personel::factory()->create()->id]);

    $this->actingAs($staf)->get(route('tugas.show', $tugasLain))->assertForbidden();
}

#[Test]
public function pengurus_dan_staf_tidak_dapat_memverifikasi(): void
{
    $tugas = Assignment::factory()->create(['status' => StatusPenugasan::DiajukanSelesai, 'progres' => 100]);

    foreach ([PeranSistem::Pengurus, PeranSistem::Staf] as $peran) {
        $user = $this->akun($peran);
        Personel::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user)->patch(route('tugas.verifikasi', $tugas), ['alasan' => 'Sudah sesuai'])->assertForbidden();
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter=staf_tidak_dapat_membuka_tugas_personel_lain`
Expected: FAIL — rute `tugas.show` belum ada.

- [ ] **Step 3: Implementasi Policy**

- `AssignmentPolicy::sunting`: `boleh(Ubah)` && bukan Staf && (DE || `pemberi_tugas_id === personelId`).
- `progres`: `boleh(Ubah)` && `bolehBarisPersonel(pic_id)`.
- `ajukan`: sama dengan `progres`.
- `verify` (sudah ada): `boleh(Verifikasi)` — setelah Task 2 hanya DE.
- `tutup`: `verify`.
- `aktifkan`: `verify`.
- `batalkan`: `boleh(Ubah)` && (DE || `pemberi_tugas_id === personelId`).
- `FeedbackPolicy::setujui`: `boleh(Buat)` && `peran === Pengurus` && `jenis !== Persetujuan`.
- `ActionItemPolicy::tutup`: `verify`; tambah alias `verifikasi` → `verify`.

- [ ] **Step 4: Implementasi FormRequest**

`SimpanPenugasanRequest` (`authorize()` → `$this->user()->can('create', Assignment::class)`):
```
judul required string max:200
deskripsi required string max:5000
job_description_id nullable integer exists:job_descriptions,id
pic_id required integer exists:personel,id + Rule::exists('personel','id')->where('status_aktif', true)
kategori required Rule::enum(KategoriTugas::class)
target_keluaran required string max:2000
prioritas required Rule::enum(PrioritasTugas::class)
tanggal_mulai required date after_or_equal:-30 days
tenggat required date after_or_equal:tanggal_mulai
dependensi_id nullable integer exists:assignments,id
sumber_arahan_id nullable integer exists:feedback,id
alasan nullable string max:500
```
`SimpanArahanRequest`: `penerima_id` required exists personel aktif; `jenis` `Rule::in(['arahan','komentar','masukan','koreksi'])`; `isi` required max:5000; `butuh_tindakan` boolean; `tenggat` nullable date; `lampiran_dokumen_id` nullable exists documents.
`FilterPenugasanRequest` / `FilterArahanRequest`: allowlist enum + `q` max:100 + `urut`/`arah` allowlist, `authorize()` → `viewAny`.

- [ ] **Step 5: Jalankan tes**

Run: `php artisan test --filter=Tahap5`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Policies app/Http/Requests tests/Feature/Tahap5PenugasanTest.php
git commit -m "tahap 5: policy & validasi penugasan dan arahan"
```

---

### Task 10: `AssignmentController` & rute

**Files:**
- Create: `app/Http/Controllers/AssignmentController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Tahap5PenugasanTest.php`

**Interfaces:**
- Consumes: `Assignment::buatDenganKodeUnik`, `PengirimNotifikasi`, `RingkasanTunggakan` tidak dipakai di sini.
- Produces: rute `tugas.index|create|store|show|edit|update|progres|ajukan|verifikasi|tutup|batalkan`.

- [ ] **Step 1: Tulis tes alur**

```php
#[Test]
public function de_membuat_tugas_lewat_formulir_dengan_sumber_perintah_otomatis(): void
{
    $de = $this->akun(PeranSistem::DirekturEksekutif);
    $personelDe = Personel::factory()->create(['user_id' => $de->id]);
    $pic = Personel::factory()->create(['status_aktif' => true]);

    $this->actingAs($de)->post(route('tugas.store'), [
        'judul' => 'Rekap Q4', 'deskripsi' => 'Rekap', 'pic_id' => $pic->id,
        'kategori' => 'umum', 'target_keluaran' => 'Berkas rekap',
        'prioritas' => 'sedang',
        'tanggal_mulai' => ServerClock::now()->toDateString(),
        'tenggat' => ServerClock::now()->addDays(5)->toDateTimeString(),
        'sumber_perintah' => 'pengurus_langsung',
        'pemberi_tugas_id' => $pic->id,
    ])->assertRedirect();

    $tugas = Assignment::query()->latest('id')->first();
    $this->assertSame(SumberPerintah::DeLangsung, $tugas->sumber_perintah);
    $this->assertSame($personelDe->id, $tugas->pemberi_tugas_id);
    $this->assertSame(StatusPenugasan::Aktif, $tugas->status);
    $this->assertDatabaseCount('notifications', 1);
}

#[Test]
public function staf_dan_admin_ditolak_saat_membuat_tugas(): void
{
    foreach ([PeranSistem::Staf, PeranSistem::Admin] as $peran) {
        $this->actingAs($this->akun($peran))->post(route('tugas.store'), [])->assertForbidden();
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter=de_membuat_tugas_lewat_formulir_dengan_sumber_perintah_otomatis`
Expected: FAIL — rute belum terdaftar.

- [ ] **Step 3: Implementasi controller**

- `index(FilterPenugasanRequest)`: `authorize('viewAny', Assignment::class)`; `Assignment::query()->terlihatOleh($user)->with(['pic','pemberiTugas','diteruskanOleh'])`; filter `status|kategori|prioritas|sumber|pic|terlambat` dari allowlist; `q` → `where(fn($s) => $s->where('judul','like',"%{$q}%")->orWhere('kode','like',...))`; `paginate(25)->withQueryString()`.
- `create()`: `authorize('create', ...)`; kirim `sumberPerintah` hasil turunan peran, `pilihanArahan` (DE saja: `Feedback::query()->where('asal','pengurus')->where('penerima_id', personelId)->whereIn('jenis',['arahan','koreksi'])->where('status','!=','selesai')`).
- `store(SimpanPenugasanRequest)`: `authorize('create')`; validasi `sumber_arahan_id` lewat `arahanUntukDiteruskan()` (lempar `ValidationException`); `Assignment::buatDenganKodeUnik()`; `status = Aktif`, `progres = 0`; bila ada arahan → `status = Ditindaklanjuti` + `PengirimNotifikasi::arahanDiteruskan()`; `PengirimNotifikasi::penugasanBaru()`; redirect `tugas.show` + `status`.
- `show()`: `authorize('view')`; `load(['pic','pemberiTugas','diteruskanOleh','jobDescription','dependensi','sumberArahan','actionItems.pic'])`; 5 baris `AuditEvent` terakhir untuk objek ini.
- `edit()`: `authorize('sunting')`; hanya status `draft`/`aktif`, selain itu `back()->withErrors`.
- `update(SimpanPenugasanRequest)`: `authorize('sunting')`; `fill(validated)`; `denganAlasan($alasan)->save()`.
- `progres(Request)`: `authorize('progres')`; validate `progres` required integer 0..100, `kendala` nullable max:2000; simpan; bila status `Aktif` dan progres > 0 → `DalamProses`.
- `ajukan()`: `authorize('ajukan')`; `status = DiajukanSelesai`; `denganAlasan('Diajukan selesai oleh PIC')`.
- `verifikasi(Request)`: `authorize('verify')`; validate `alasan` required min:10 max:500; `status = Terverifikasi`; `denganAlasan($alasan)`; notifikasi `penugasanDiverifikasi`.
- `tutup(Request)`: `authorize('tutup')`; validate `alasan_penutupan` required min:10, `bukti_penutupan` required min:5, `bukti_dokumen_id` nullable exists; set + `denganAlasan($alasan_penutupan)`.
- `batalkan(Request)`: `authorize('batalkan')`; validate `alasan_pembatalan` required min:10, `bukti_pembatalan` required min:5; set + `denganAlasan`.
- `aktifkan()`: `authorize('aktifkan')`; `status = Aktif` + alasan.

- [ ] **Step 4: Daftarkan rute**

Di `routes/web.php` dalam grup `['auth','aktif']`, sebelum grup master:
```php
Route::prefix('tugas')->name('tugas.')->group(function (): void {
    Route::middleware('izin:assignment.create')->group(function (): void {
        Route::get('buat', [AssignmentController::class, 'create'])->name('create');
        Route::post('/', [AssignmentController::class, 'store'])->name('store');
    });

    Route::middleware('izin:assignment.update')->group(function (): void {
        Route::get('{tugas}/ubah', [AssignmentController::class, 'edit'])->whereNumber('tugas')->name('edit');
        Route::put('{tugas}', [AssignmentController::class, 'update'])->whereNumber('tugas')->name('update');
        Route::patch('{tugas}/progres', [AssignmentController::class, 'progres'])->whereNumber('tugas')->name('progres');
        Route::patch('{tugas}/ajukan-selesai', [AssignmentController::class, 'ajukan'])->whereNumber('tugas')->name('ajukan');
        Route::patch('{tugas}/batalkan', [AssignmentController::class, 'batalkan'])->whereNumber('tugas')->name('batalkan');
    });

    Route::middleware('izin:assignment.verify')->group(function (): void {
        Route::patch('{tugas}/verifikasi', [AssignmentController::class, 'verifikasi'])->whereNumber('tugas')->name('verifikasi');
        Route::patch('{tugas}/tutup', [AssignmentController::class, 'tutup'])->whereNumber('tugas')->name('tutup');
        Route::patch('{tugas}/aktifkan', [AssignmentController::class, 'aktifkan'])->whereNumber('tugas')->name('aktifkan');
    });

    Route::middleware('izin:assignment.view')->group(function (): void {
        Route::get('/', [AssignmentController::class, 'index'])->name('index');
        Route::get('{tugas}', [AssignmentController::class, 'show'])->whereNumber('tugas')->name('show');
    });
});
```

- [ ] **Step 5: Jalankan tes & cek rute**

Run: `php artisan route:list --name=tugas`
Run: `php artisan test --filter=Tahap5`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/AssignmentController.php routes/web.php tests/Feature/Tahap5PenugasanTest.php
git commit -m "tahap 5: layar penugasan & aksi transisi status"
```

---

### Task 11: Tampilan penugasan

**Files:**
- Create: `resources/views/penugasan/index.blade.php`, `form.blade.php`, `show.blade.php`
- Modify: `app/Http/Controllers/ModulController.php` (redirect `assignment` → `tugas.index`)
- Modify: `resources/views/layouts/app.blade.php` (tautan "Penugasan")

- [ ] **Step 1: Tulis `index.blade.php`**

Tabel: ID, Judul, PIC, Sumber, Kategori, Prioritas, Tenggat, Status (lencana), Progres, penanda "Terlambat". Form filter GET dengan allowlist (select dari enum), tombol "Tugas Baru" hanya `@can('create', App\Models\Assignment::class)`. Paginasi `{{ $daftar->links() }}`.

- [ ] **Step 2: Tulis `form.blade.php`**

Field sesuai `SimpanPenugasanRequest`. Tiga baris baca-saja di atas: ID (`$tugas->kode ?? 'otomatis'`), Sumber perintah (`$sumberPerintah->label()`), Pemberi tugas (`$identitas['nama']`). Select `sumber_arahan_id` hanya bila `$pilihanArahan` tidak kosong. Tidak ada input untuk `sumber_perintah`, `pemberi_tugas_id`, `status`.

- [ ] **Step 3: Tulis `show.blade.php`**

Kepala + tombol aksi ber-`@can`: Progres (form inline), Ajukan Selesai, Verifikasi (textarea alasan), Tutup (alasan + bukti + pilih dokumen opsional), Batalkan (alasan + bukti), Aktifkan. Riwayat 5 audit + daftar action item.

- [ ] **Step 4: Navigasi**

- `ModulController::show`: bila `$modul === 'assignment'` → `redirect()->route('tugas.index')`; bila `'feedback'` → `redirect()->route('arahan.index')`; bila `'action_item'` → `redirect()->route('arahan.index')`. Periksa `Route::has()` agar tidak pecah sebelum Task 12.
- `layouts/app.blade.php`: tautan "Penugasan" `@can('assignment.view')` dan "Arahan" `@can('feedback.view')`.

- [ ] **Step 5: Verifikasi**

Run: `php artisan test --filter=Tahap5`
Run: `Select-String -Path resources/views/penugasan/*.blade.php -Pattern '\{!!'`
Expected: tes PASS; tidak ada `{!!`.

- [ ] **Step 6: Commit**

```bash
git add resources/views/penugasan app/Http/Controllers/ModulController.php resources/views/layouts/app.blade.php
git commit -m "tahap 5: tampilan daftar, formulir & detail penugasan"
```

---

### Task 12: Arahan & action item

**Files:**
- Create: `app/Http/Controllers/ArahanController.php`, `app/Http/Controllers/TindakLanjutController.php`
- Create: `resources/views/arahan/index.blade.php`, `form.blade.php`, `show.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Tahap5PenugasanTest.php`

**Interfaces:**
- Produces: rute `arahan.index|create|store|show|setujui|tindak-lanjut`, `tindak-lanjut.update|verifikasi|tutup`.

- [ ] **Step 1: Tulis tes**

```php
#[Test]
public function de_melihat_arahan_pengurus_kepada_staf_tetapi_staf_lain_tidak(): void
{
    $pengurus = $this->akun(PeranSistem::Pengurus);
    $de = $this->akun(PeranSistem::DirekturEksekutif);
    $stafA = $this->akun(PeranSistem::Staf);
    $stafB = $this->akun(PeranSistem::Staf);
    Personel::factory()->create(['user_id' => $pengurus->id]);
    Personel::factory()->create(['user_id' => $de->id]);
    $personelA = Personel::factory()->create(['user_id' => $stafA->id]);
    Personel::factory()->create(['user_id' => $stafB->id]);

    $this->actingAs($pengurus);
    $arahan = Feedback::query()->create([
        'penerima_id' => $personelA->id, 'jenis' => JenisFeedback::Arahan, 'isi' => 'Koordinasi',
    ]);

    $this->actingAs($de)->get(route('arahan.show', $arahan))->assertOk();
    $this->actingAs($stafA)->get(route('arahan.show', $arahan))->assertOk();
    $this->actingAs($stafB)->get(route('arahan.show', $arahan))->assertForbidden();
}

#[Test]
public function persetujuan_formal_hanya_dibuat_pengurus_dan_tidak_mengubah_status_objek(): void
{
    $pengurus = $this->akun(PeranSistem::Pengurus);
    $de = $this->akun(PeranSistem::DirekturEksekutif);
    Personel::factory()->create(['user_id' => $pengurus->id]);
    Personel::factory()->create(['user_id' => $de->id]);

    $arahan = Feedback::factory()->create(['asal' => AsalFeedback::Pengurus, 'jenis' => JenisFeedback::Arahan]);

    $this->actingAs($de)->post(route('arahan.setujui', $arahan), ['isi' => 'Setuju'])->assertForbidden();
    $this->actingAs($pengurus)->post(route('arahan.setujui', $arahan), ['isi' => 'Setuju'])->assertRedirect();

    $this->assertDatabaseHas('feedback', ['jenis' => 'persetujuan', 'asal' => 'pengurus', 'butuh_tindakan' => false]);
    $this->assertSame(StatusFeedback::Terbuka, $arahan->fresh()->status);
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter=de_melihat_arahan_pengurus_kepada_staf_tetapi_staf_lain_tidak`
Expected: FAIL — rute belum ada.

- [ ] **Step 3: Implementasi `ArahanController`**

- `index(FilterArahanRequest)`: `authorize('viewAny', Feedback::class)`; `Feedback::query()->terlihatOleh($user)->with(['pemberi.jabatan','penerima','actionItems'])->latest('created_at')->paginate(25)`.
- `create()`: `authorize('create', Feedback::class)`; kirim daftar personel aktif.
- `store(SimpanArahanRequest)`: `authorize('create')`; `Feedback::create(validated)` (identitas diisi model); `PengirimNotifikasi::arahanBaru()`.
- `show(Feedback $arahan)`: `authorize('view', $arahan)`; `load(['pemberi.jabatan','penerima','lampiran','actionItems.pic','subjek'])`.
- `setujui(Request, Feedback $arahan)`: `authorize('setujui', $arahan)`; validate `isi` required max:2000; buat baris baru `jenis = Persetujuan`, `asal = Pengurus`, `penerima_id = $arahan->pemberi_id`, `subjek_type/subjek_id` sama, `status = Selesai`, `butuh_tindakan = false`.
- `tindakLanjut(Request, Feedback $arahan)`: `authorize('create', ActionItem::class)`; validate `deskripsi` required, `pic_id` exists aktif, `tenggat` nullable date; `ActionItem::create(['feedback_id' => $arahan->id, ...])`; `PengirimNotifikasi::tindakLanjutBaru()`.

- [ ] **Step 4: Implementasi `TindakLanjutController`**

- `update(Request, ActionItem $tindakLanjut)`: `authorize('update', ...)`; validate `status` `Rule::in(['terbuka','dalam_proses','menunggu_pihak_lain','diajukan_selesai'])`; `denganAlasan($alasan)->save()`.
- `verifikasi(Request, ActionItem $tindakLanjut)`: `authorize('verify', ...)`; validate `alasan` min:10; `status = TerverifikasiSelesai`; `diverifikasi_oleh = personel sesi`.
- `tutup(Request, ActionItem $tindakLanjut)`: `authorize('tutup', ...)`; validate `alasan` min:10 + `bukti_penutupan` min:5; set status + bukti; `denganAlasan($alasan)`.

- [ ] **Step 5: Daftarkan rute**

```php
Route::prefix('arahan')->name('arahan.')->group(function (): void {
    Route::middleware('izin:feedback.create')->group(function (): void {
        Route::get('buat', [ArahanController::class, 'create'])->name('create');
        Route::post('/', [ArahanController::class, 'store'])->name('store');
        Route::post('{arahan}/setujui', [ArahanController::class, 'setujui'])->whereNumber('arahan')->name('setujui');
    });

    Route::middleware('izin:action_item.create')->group(function (): void {
        Route::post('{arahan}/tindak-lanjut', [ArahanController::class, 'tindakLanjut'])->whereNumber('arahan')->name('tindak-lanjut');
    });

    Route::middleware('izin:feedback.view')->group(function (): void {
        Route::get('/', [ArahanController::class, 'index'])->name('index');
        Route::get('{arahan}', [ArahanController::class, 'show'])->whereNumber('arahan')->name('show');
    });
});

Route::prefix('tindak-lanjut')->name('tindak-lanjut.')->group(function (): void {
    Route::middleware('izin:action_item.update')->group(function (): void {
        Route::patch('{tindakLanjut}', [TindakLanjutController::class, 'update'])->whereNumber('tindakLanjut')->name('update');
    });

    Route::middleware('izin:action_item.verify')->group(function (): void {
        Route::patch('{tindakLanjut}/verifikasi', [TindakLanjutController::class, 'verifikasi'])->whereNumber('tindakLanjut')->name('verifikasi');
        Route::patch('{tindakLanjut}/tutup', [TindakLanjutController::class, 'tutup'])->whereNumber('tindakLanjut')->name('tutup');
    });
});
```

- [ ] **Step 6: Tulis tiga view arahan**

`index`: waktu, pemberi + jabatan, penerima, jenis (lencana; `persetujuan` beda warna), asal, tenggat, status, jumlah action item terbuka. `form`: penerima, jenis, isi, butuh tindakan, tenggat, lampiran. `show`: isi lengkap, lampiran, action item + tombol ubah status, form "Setujui" hanya `@can('setujui', $arahan)`, form "Tindak lanjut" hanya `@can('create', App\Models\ActionItem::class)`.

- [ ] **Step 7: Jalankan tes**

Run: `php artisan test --filter=Tahap5`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/ArahanController.php app/Http/Controllers/TindakLanjutController.php resources/views/arahan routes/web.php tests/Feature/Tahap5PenugasanTest.php
git commit -m "tahap 5: modul arahan & tindak lanjut"
```

---

### Task 13: Layar notifikasi & lencana header

**Files:**
- Create: `app/Http/Controllers/NotifikasiController.php`, `resources/views/notifikasi/index.blade.php`
- Modify: `routes/web.php`, `resources/views/layouts/app.blade.php`
- Test: `tests/Feature/Tahap5PenugasanTest.php`

**Interfaces:**
- Produces: rute `notifikasi.index|baca|baca-semua`.

- [ ] **Step 1: Tulis tes**

```php
#[Test]
public function notifikasi_milik_akun_lain_menghasilkan_404(): void
{
    $pemilik = $this->akun(PeranSistem::Staf);
    $lain = $this->akun(PeranSistem::Staf);
    $notifikasi = $pemilik->notifications()->create([
        'id' => Str::uuid()->toString(), 'type' => 'uji', 'data' => ['judul' => 'X'],
    ]);

    $this->actingAs($lain)->post(route('notifikasi.baca', $notifikasi->id))->assertNotFound();
}

#[Test]
public function baca_semua_menghapus_lencana_belum_dibaca(): void
{
    $user = $this->akun(PeranSistem::Staf);
    $user->notifications()->create(['id' => Str::uuid()->toString(), 'type' => 'uji', 'data' => ['judul' => 'X']]);

    $this->assertSame(1, $user->unreadNotifications()->count());
    $this->actingAs($user)->post(route('notifikasi.baca-semua'))->assertRedirect();
    $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test --filter=notifikasi_milik_akun_lain_menghasilkan_404`
Expected: FAIL — rute belum ada.

- [ ] **Step 3: Implementasi controller**

- `index(Request)`: `$request->user()->notifications()->paginate(50)`; kirim juga `jumlahBelumDibaca`.
- `baca(Request, string $id)`: `$n = $request->user()->notifications()->find($id); abort_if($n === null, 404); $n->markAsRead();` redirect `notifikasi.index`.
- `bacaSemua(Request)`: `$request->user()->unreadNotifications()->update(['read_at' => ServerClock::now()]);` redirect.

- [ ] **Step 4: Rute + view + lencana**

```php
Route::get('/notifikasi', [NotifikasiController::class, 'index'])->name('notifikasi.index');
Route::post('/notifikasi/baca-semua', [NotifikasiController::class, 'bacaSemua'])->name('notifikasi.baca-semua');
Route::post('/notifikasi/{notifikasi}/baca', [NotifikasiController::class, 'baca'])
    ->whereUuid('notifikasi')->name('notifikasi.baca');
```
Didaftarkan **sebelum** rute berparameter lain yang mungkin bentrok; keduanya cukup `auth`+`aktif`.

`layouts/app.blade.php`, di blok `ml-auto` sebelum nama pengguna:
```blade
<a href="{{ route('notifikasi.index') }}" class="rounded-md px-2 py-1 text-slate-600 hover:bg-slate-100">
    Notifikasi
    @php($belumDibaca = auth()->user()->unreadNotifications()->count())
    @if ($belumDibaca > 0)
        <span class="ml-1 rounded-full bg-rose-600 px-1.5 py-0.5 text-xs font-semibold text-white">{{ $belumDibaca }}</span>
    @endif
</a>
```
View `notifikasi/index.blade.php`: daftar judul, pesan, waktu WIB, penanda belum dibaca, tautan `data.url`, tombol "Tandai dibaca" per baris + "Baca semua".

- [ ] **Step 5: Jalankan tes**

Run: `php artisan test --filter=Tahap5`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/NotifikasiController.php resources/views/notifikasi resources/views/layouts/app.blade.php routes/web.php tests/Feature/Tahap5PenugasanTest.php
git commit -m "tahap 5: pusat notifikasi & lencana belum dibaca"
```

---

### Task 14: Panel tunggakan dashboard

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`, `resources/views/dashboard/ruang-kerja.blade.php`

- [ ] **Step 1: Tambah data**

`DashboardController::tampilkan()` menambah `'tunggakan' => RingkasanTunggakan::untuk($pengguna)`.

- [ ] **Step 2: Tambah bagian view**

Sisipkan sebelum "Aktivitas terakhir": tiga kolom (Tugas terlambat, Tindak lanjut terbuka, Arahan menunggu). Bila `$tunggakan['jumlah'] === 0` tampilkan "Tidak ada tunggakan." Setiap baris menautkan `tugas.show` / `arahan.show`.

- [ ] **Step 3: Verifikasi**

Run: `php artisan test --filter=Tahap5`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/DashboardController.php resources/views/dashboard/ruang-kerja.blade.php
git commit -m "tahap 5: panel tunggakan pada dashboard"
```

---

### Task 15: Tutup kriteria penerimaan yang tersisa

**Files:**
- Modify: `tests/Feature/Tahap5PenugasanTest.php`

- [ ] **Step 1: Tambah tes untuk sisa kriteria desain §12**

Tes yang belum tercakup tugas sebelumnya:
1. `de_tidak_dapat_memakai_arahan_yang_bukan_miliknya` → 422 + pesan `sumber_arahan_id`.
2. `de_meneruskan_arahan_menjadi_tugas_jalur_dua` → `pengurus_melalui_de`, `pemberi_tugas_id` = Pengurus, `diteruskan_oleh_id` = DE, arahan `ditindaklanjuti`, notifikasi ke Pengurus.
3. `pengurus_membuat_tugas_langsung_memicu_notifikasi_ke_de` → `pengurus_langsung` + 1 notifikasi DE.
4. `menutup_arahan_menunggu_semua_action_item_selesai` → `bolehDitutup()` false selama ada item terbuka.
5. `terverifikasi_mengisi_diverifikasi_oleh_dan_pada_dari_jam_server`.
6. `pembatalan_tanpa_bukti_ditolak_model_dan_check`.
7. `terhambat_tanpa_kendala_ditolak` dan `diajukan_selesai_dengan_progres_kurang_100_ditolak`.
8. `action_item_tidak_dapat_ditutup_staf` (403) dan `ditutup_dengan_bukti_tersimpan`.
9. `notifikasi_terkirim_untuk_kelima_kelas` (PenugasanBaru, PenugasanDiverifikasi, ArahanBaru, ArahanDiteruskan, TindakLanjutBaru).
10. `alasan_tidak_menempel_pada_perubahan_berikutnya` → `denganAlasan()` sekali pakai.
11. `dependensi_melingkar_ditolak_lewat_formulir` → 422.
12. `kode_tugas_berurutan_dan_unik` → dua tugas berurutan `TGS-<tahun>-0001`, `-0002`.

- [ ] **Step 2: Jalankan seluruh tes Tahap 5**

Run: `php artisan test --filter=Tahap5`
Expected: semua PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Tahap5PenugasanTest.php
git commit -m "tahap 5: tes kriteria penerimaan penugasan & arahan"
```

---

### Task 16: Verifikasi akhir, ceklis & PDF

**Files:**
- Modify: `docs/superpowers/specs/2026-11-14-ceklis-fitur-vs-implementasi.md`
- Regenerate: `C:\Users\rio\Downloads\fitur-ceklis.pdf`

- [ ] **Step 1: Jalankan seluruh pemeriksaan**

```bash
php artisan test
vendor/bin/pint --test
composer validate --strict
composer audit
npm run build
npm audit --omit=dev
```
Expected: semua hijau; tes ≥ 76 + tes Tahap 5 baru.

- [ ] **Step 2: Smoke test live 4 peran**

Login `admin@himki.test`, `ketua@himki.test`, `direktur@himki.test`, `staf@himki.test` (kata sandi `password`, field `login`). Periksa:
- DE: buat tugas, verifikasi, tutup, teruskan arahan Pengurus.
- Pengurus: buat arahan ke staf, setujui, buat tugas langsung, tidak melihat tombol verifikasi.
- Staf: lihat tugas sendiri, ubah progres, ajukan selesai, tidak dapat membuka tugas orang lain.
- Admin: tidak melihat menu Penugasan/Arahan; panel tunggakan kosong.
- Lencana notifikasi muncul dan `Baca semua` menghapusnya.

- [ ] **Step 3: Perbarui ceklis**

Buka `docs/superpowers/specs/2026-11-14-ceklis-fitur-vs-implementasi.md`, lalu:
- Baris Tahap 5 → ✅ dengan ringkasan bukti (migrasi, model, policy, rute, tes).
- Pengurus #18 "Tombol Buat Arahan" → ✅ (notifikasi sudah ada).
- DE #11 "Notifikasi Instruksi Pengurus" → ✅.
- Setiap baris lain yang menyebut penugasan/arahan/tindak lanjut: periksa dan ubah hanya bila benar-benar tertutup; jangan menaikkan status yang belum dikerjakan.
- Perbarui bagian rekap (jumlah ✅/🟡/⬜) dan bagian celah.

- [ ] **Step 4: Regenerasi PDF**

```bash
python C:\Users\rio\AppData\Local\Temp\opencode\ceklis_pdf.py
```
Pastikan jumlah badge di PDF sama dengan rekap doc (hitung ulang dengan Python, bukan PowerShell).

- [ ] **Step 5: Commit**

```bash
git add docs/superpowers/specs/2026-11-14-ceklis-fitur-vs-implementasi.md docs/superpowers/plans/2026-11-14-tahap-5-penugasan-plan.md docs/superpowers/specs/2026-11-14-tahap-5-penugasan-design.md
git commit -m "tahap 5: ceklis fitur & rencana implementasi"
```

---

## Catatan izin yang sudah ada (jangan diubah tanpa alasan)

| Peran | `assignment` | `feedback` | `action_item` |
|---|---|---|---|
| Pengurus | lihat, buat, ubah, ekspor | lihat, buat | lihat |
| DE | semua kecuali konfigurasi | lihat, buat, ubah | semua |
| Staf | lihat, ubah | lihat | lihat, ubah |
| Admin | — | — | — |

Konsekuensi yang disengaja: Staf tidak dapat membuat arahan (`feedback.create` tidak dimiliki), dan Pengurus tidak dapat membuat action item (`action_item.create` hanya DE) — sesuai pembagian "DE melakukan penyelarasan operasional" pada PRD E.3.1 jalur 2. Jangan menambah izin; bila sebuah layar membutuhkan akses yang tidak ada, itu tanda layarnya salah tempat, bukan tanda matriks izin kurang.

## Catatan penyimpangan dari desain (disengaja, dicatat di sini)

1. **`sumber_perintah`/`pemberi_tugas_id`/`diteruskan_oleh_id` tetap `$fillable`.** Alasan: `AssignmentFactory` dan seeder membuat baris lewat konstruktor Eloquent; mengeluarkannya memaksa `forceFill` di banyak tempat tanpa menambah keamanan nyata. Pengaman sebenarnya: (a) ketiga kolom tidak pernah ada di `$rules` FormRequest sehingga tidak pernah masuk `validated()`; (b) hook `creating` **menimpa** nilainya dari sesi; (c) hook `saving` menolak perubahannya pada baris yang sudah ada.
2. **Tugas baru langsung berstatus `Aktif`, bukan `Draft`.** Alasannya: desain §9 tidak memuat rute aktivasi, sehingga `draft → aktif` tidak dapat dicapai lewat UI; membuat tugas = menerbitkan instruksi. Transisi `draft → aktif` tetap sah di peta agar baris draf lama/ impor dapat diaktifkan.
3. **Pengurus kehilangan `assignment.verify`.** Desain §10 menaruh verifikasi/penutupan pada DE; membiarkan izin di Pengurus akan membuat matriks izin berbohong.
