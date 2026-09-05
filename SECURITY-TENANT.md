# ⚠️ PENGINGAT KEAMANAN — Isolasi Tenant (Multi-Koperasi)

> File ini dibuat berdasarkan audit kode tanggal 2026-08-26.
> **JANGAN HAPUS.** Baca sebelum menambah model, controller, atau query baru.

---

## Arsitektur Isolasi yang Ada

Sistem ini menggunakan **satu database untuk semua koperasi desa** (multi-tenant
single-database). Isolasi data antar desa dijaga oleh tiga lapisan:

```
Request HTTP
    └─ [1] Middleware: SetKoperasiAktif
           → Membaca auth()->user()->id_koperasi
           → Mendaftarkan app('koperasi_aktif') di container
               └─ [2] Trait: BelongsToKoperasi (dipasang di setiap Model bertenant)
                      → Memasang KoperasiScope otomatis
                          └─ [3] KoperasiScope
                                 → Menambahkan WHERE id_koperasi = ? ke setiap query
```

---

## 🔴 Temuan Audit — Belum Diperbaiki

### 1. Model Konsinyasi TIDAK memakai `BelongsToKoperasi`
Tabel konsinyasi memiliki kolom `id_koperasi_pemilik` / `id_koperasi_penerima`,
namun model-model berikut **belum memasang trait** sehingga query-nya tidak
difilter otomatis:

- [ ] `app/Models/Konsinyasi/PengirimanKonsinyasi.php`
- [ ] `app/Models/Konsinyasi/KartuKonsinyasi.php`
- [ ] `app/Models/Konsinyasi/SetoranKonsinyasi.php`
- [ ] `app/Models/Konsinyasi/StokKonsinyasi.php`
- [ ] `app/Models/Konsinyasi/PenawaranBarter.php`
- [ ] `app/Models/Konsinyasi/PermintaanBarter.php`

> **CATATAN KHUSUS `PengirimanKonsinyasi`:** tabel ini bersifat lintas-tenant
> (pemilik ≠ penerima). Tidak bisa langsung pakai `BelongsToKoperasi` biasa —
> perlu scope khusus yang filter berdasarkan
> `id_koperasi_pemilik = ? OR id_koperasi_penerima = ?`. Diskusikan dulu
> sebelum mengubah.

### 2. `BukuBesarPeriode` tidak memakai trait
- [ ] `app/Models/Akuntansi/BukuBesarPeriode.php` — punya `id_koperasi`
  tapi tidak pakai `BelongsToKoperasi`. Berisiko jika query tanpa filter manual.

### 3. Tidak ada Feature Test isolasi tenant
- [ ] `tests/Feature/TenantIsolationTest.php` — **BELUM DIBUAT**

Tanpa test ini, tidak ada jaminan otomatis bahwa isolasi berjalan ketika
model baru ditambahkan. Template test yang harus dibuat:

```php
// Skenario yang HARUS ditest:
// 1. Pengguna desa A tidak bisa melihat data desa B
// 2. Pengguna desa A tidak bisa menyimpan data dengan id_koperasi desa B
// 3. Pengguna desa A tidak bisa akses resource desa B lewat URL langsung
//    (mis: GET /keuangan/piutang/999 di mana id 999 milik desa B)
```

---

## 🟡 Risiko Menengah — Perlu Ditambahkan

### 4. Tidak ada guard jika `id_koperasi` pengguna = null secara tidak sengaja
`SetKoperasiAktif` tidak memvalidasi bahwa pengguna normal (non-pusat) pasti
punya `id_koperasi`. Jika ada bug yang mengakibatkan `id_koperasi = null`,
pengguna tersebut akan melihat data **semua** desa tanpa error.

**Mitigasi yang perlu ditambah di `SetKoperasiAktif`:**
```php
// Jika pengguna bukan level pusat tapi id_koperasi-nya null → abort
if ($pengguna !== null && $pengguna->id_koperasi === null
    && $pengguna->level !== 'pusat') {
    abort(403, 'Akun tidak terikat ke koperasi manapun.');
}
```

### 5. Belum ada validasi bahwa ID dari form milik koperasi aktif
Contoh celah: form pelunasan mengirim `id_pihak=999`. Jika `id_pihak` 999
milik desa B, tapi tidak ada validasi silang, desa A bisa merekam transaksi
dengan pihak milik desa lain.

**Solusi:** Buat `TenantOwnershipRule` (custom Laravel validation rule) yang
memverifikasi bahwa setiap foreign key yang masuk dari form memang milik
`koperasi_aktif`.

---

## ✅ Aturan Wajib — JANGAN DILANGGAR

```
1. SETIAP model yang tabelnya punya kolom id_koperasi HARUS memakai
   trait BelongsToKoperasi — tidak ada pengecualian.

2. withoutGlobalScope(KoperasiScope::class) HANYA BOLEH dipakai di
   modul laporan konsolidasi (akses level pusat). Dilarang di tempat lain.

3. JurnalService dan semua Service lain yang menulis ke database WAJIB
   menerima id_koperasi dari app('koperasi_aktif'), bukan dari input user.

4. Stored Procedure yang akan dibuat (sp_post_jurnal) HARUS menerima
   p_id_koperasi sebagai parameter eksplisit dan TIDAK boleh mengambil
   id_koperasi dari data lain di dalam procedure.

5. Setiap kali menambah tabel bertenant baru (ada kolom id_koperasi),
   WAJIB menambah test case di TenantIsolationTest.php.
```

---

## Referensi File Kunci

| File | Fungsi |
|---|---|
| [`app/Scopes/KoperasiScope.php`](app/Scopes/KoperasiScope.php) | Global scope yang menyuntik WHERE |
| [`app/Models/Concerns/BelongsToKoperasi.php`](app/Models/Concerns/BelongsToKoperasi.php) | Trait yang memasang scope + auto-fill |
| [`app/Http/Middleware/SetKoperasiAktif.php`](app/Http/Middleware/SetKoperasiAktif.php) | Middleware yang mendaftarkan tenant aktif |
| [`bootstrap/app.php`](bootstrap/app.php) | Tempat middleware didaftarkan ke grup `web` |
| `tests/Feature/TenantIsolationTest.php` | ❌ BELUM ADA — harus dibuat |

---

*Terakhir diperbarui: 2026-08-26 oleh audit otomatis Antigravity.*
