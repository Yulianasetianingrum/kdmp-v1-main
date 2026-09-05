# 🗺️ ROADMAP IMPLEMENTASI — Modul Finance KDMP

> **Dokumen Hidup — Update setiap kali satu step selesai.**
> Dibuat berdasarkan arsitektur & kemajuan pekerjaan per 31 Agustus 2026.
> Penanggung jawab: Ketua Tim (feature/finance)

---

## Status Legenda
- `✅ SELESAI` — Kode sudah ada, sudah ditest, berjalan normal.
- `🔄 SEDANG` — Sedang dalam pengerjaan.
- `⏳ BERIKUTNYA` — Target step selanjutnya.
- `🔲 BELUM` — Belum dikerjakan.

---

## ✅ STEP 1 — Discovery & Pemahaman Sistem (SELESAI)

**Tujuan:** Memahami apa yang sudah ada sebelum menulis kode baru apapun.

**Yang dilakukan:**
- [x] Membaca & memahami 12 file migration (skema database).
- [x] Membaca semua model Eloquent yang relevan.
- [x] Memahami arsitektur multi-tenant (`KoperasiScope`, `BelongsToKoperasi`, `SetKoperasiAktif` middleware).
- [x] Memahami konsep `master_transaksi` (template jurnal) dan `master_detail_transaksi` (baris template).
- [x] Memahami alur status jurnal: `DRAFT` → `POSTED` → `REVERSED`.
- [x] Memahami peran MySQL Trigger dalam melindungi data jurnal yang sudah `POSTED`.
- [x] Memetakan akun dinamis: `KAS_BANK`, `PERSEDIAAN_UNIT`, `PENDAPATAN_UNIT`, `HPP_UNIT`.

**Hasil nyata:**
- File [`KONSEP-TUTUP-BUKU.md`](KONSEP-TUTUP-BUKU.md) — Dokumentasi alur tutup tahun.
- File [`TEAM-CONTRACT.md`](TEAM-CONTRACT.md) — Kontrak API antar anggota tim.
- File [`SECURITY-TENANT.md`](SECURITY-TENANT.md) — Checklist keamanan isolasi data antar desa.

---

## ✅ STEP 2 — Audit Keamanan Multi-Tenant (SELESAI)

**Tujuan:** Memastikan tidak ada kebocoran data antar koperasi desa.

**Yang dilakukan:**
- [x] Audit semua lapisan isolasi tenant (Middleware → Scope → Trait).
- [x] Menemukan model yang belum dilindungi trait `BelongsToKoperasi` (Konsinyasi, BukuBesarPeriode).
- [x] Memverifikasi `withoutGlobalScope` belum disalahgunakan di luar modul pelaporan.
- [x] Mendokumentasikan risiko dan checklist wajib ke `SECURITY-TENANT.md`.

**Pekerjaan Tertunda (dari checklist SECURITY-TENANT.md):**
- [ ] Tambah `use BelongsToKoperasi` ke semua model Konsinyasi yang punya `id_koperasi`.
- [ ] Tambah `use BelongsToKoperasi` ke `BukuBesarPeriode`.
- [ ] Buat `TenantIsolationTest.php` (direncanakan di Step 10).

---

## ✅ STEP 3 — Perencanaan Arsitektur JurnalService (SELESAI)

**Tujuan:** Merancang kontrak kode sebelum implementasi.

**Yang diputuskan:**
- [x] Laravel menangani: validasi, resolve akun dinamis, generate nomor jurnal, panggil SP.
- [x] MySQL SP menangani: INSERT jurnal (header+detail), UPDATE buku besar, dalam 1 TRANSAKSI ATOMIK.
- [x] Format komunikasi: JSON payload dikirim dari Laravel ke MySQL.
- [x] Kontrak method Service: `posting()`, `postingManual()`, `balik()`, `bangunBukuBesar()`.
- [x] Idempotency guard via `jurnal_idempoten` unique constraint di database.

---

## ✅ STEP 4 — Implementasi Mesin Jurnal (SELESAI)

### Step 4a — Exception Classes ✅
**Lokasi:** `app/Exceptions/Finance/`
- [x] `PeriodeTutupException.php` — Dilempar jika periode sudah CLOSED.
- [x] `JurnalTidakBalanceException.php` — Dilempar jika Debit ≠ Kredit.
- [x] `JurnalSudahDibalikException.php` — Dilempar jika jurnal sudah pernah dibalik.
- [x] `AkunTidakDitemukanException.php` — Dilempar jika akun dinamis gagal di-resolve.

### Step 4b — Stored Procedure MySQL ✅
**Lokasi:** `database/migrations/2026_01_02_000000_create_sp_post_jurnal.php`
- [x] `sp_post_jurnal(p_id_koperasi, p_user_id, p_json)` — sudah terdaftar di MySQL.
- [x] Fitur: Loop JSON array → INSERT header → INSERT detail → UPSERT buku besar.
- [x] Fitur: `EXIT HANDLER FOR SQLEXCEPTION` → ROLLBACK otomatis jika ada error.
- [x] Fix: Collation `utf8mb4_unicode_ci` pada variabel VARCHAR.

### Step 4c — JurnalService ✅
**Lokasi:** `app/Services/Finance/JurnalService.php`
- [x] Method `posting()` — Jurnal dari template (JTW, BLY, KST, dll).
- [x] Method `postingManual()` — Jurnal bebas tanpa template (beban listrik, kasbon).
- [x] Method `balik()` — Membuat jurnal pembalik, menandai asal sebagai REVERSED.
- [x] Method `bangunBukuBesar()` — Rebuild ringkasan saldo (idempoten, untuk maintenance).
- [x] Private helpers: `resolveBarisJurnal()`, `generateNomorJurnal()` (dengan `lockForUpdate`).

---

## ✅ STEP 5 — Testing JurnalService via Tinker (SELESAI)

**Yang ditest:**
- [x] Posting jurnal `JTW` (Jual Tunai Warga) dengan payload mock data.
- [x] Verifikasi 4 baris `jurnal_detail` terbuat dengan akun yang benar.
- [x] Verifikasi `buku_besar_periode` ter-update otomatis setelah posting.

**Hasil test:**
```
✅ Jurnal No: JRN-2026-08-0001
   1111 | D: 150000.00 | K: 0.00       ← Kas Masuk
   412  | D: 0.00      | K: 150000.00  ← Pendapatan Non-Anggota
   511  | D: 100000.00 | K: 0.00       ← HPP Keluar
   1123 | D: 0.00      | K: 100000.00  ← Persediaan Berkurang
```

---

## ✅ STEP 6 — Controller & Form Kas Transaksi (SELESAI)

**Tujuan:** Menyambungkan `JurnalService` ke UI nyata.

**Yang dilakukan:**
- [x] `KasTransaksiController` — untuk transaksi Kas Masuk & Kas Keluar harian.
  - `index()` — riwayat transaksi kas + widget saldo realtime per Kas/Bank.
  - `create()` — form input Kas Masuk / Kas Keluar / Mutasi Antar Kas.
  - `store()` — validasi via `StoreKasTransaksiRequest`, panggil `JurnalService->postingManual()`.
- [x] `StoreKasTransaksiRequest` — Form Request Laravel dengan rules lengkap.
- [x] Tampilan saldo Kas Tunai realtime dari `v_saldo_berjalan`.
- [x] Views: `index.blade.php`, `create.blade.php`.

**Kode template jurnal yang dipakai:**
- `KSM` (Kas Masuk Lainnya) — beban masuk tanpa faktur.
- `KSK` (Kas Keluar Lainnya) — pengeluaran bebas.
- `MTK` (Mutasi Kas/Bank) — pindah uang antar kas.

---

## ✅ STEP 7 — Controller Pelunasan Hutang & Piutang (SELESAI)

**Tujuan:** Kasir bisa mencatat saat anggota membayar cicilan / pelunasan.

**Yang dilakukan:**
- [x] `StorePelunasanRequest` — Form Request dengan validasi lengkap (detail array, nilai_bayar > 0).
- [x] `PelunasanController` penuh:
  - `index()` — riwayat pelunasan dengan filter jenis/status/pihak.
  - `create()` — form input dengan AJAX load piutang/hutang terbuka.
  - `terbuka()` — AJAX endpoint: return JSON piutang/hutang terbuka milik pihak.
  - `store()` — resolve `kode_akun` dari record piutang/hutang (Opsi A: handle 1132/1135/2111/2117 otomatis), group-by akun, `postingManual()`, UPDATE status piutang/hutang.
  - `show()` — detail alokasi + link ke jurnal.
- [x] Views: `index.blade.php`, `create.blade.php`, `show.blade.php`.
- [x] Route AJAX `keuangan.pelunasan.terbuka` ditambah sebelum resource route.
- [x] Security: tenant guard di setiap akses piutang/hutang (cek `id_koperasi`).

**Keputusan Arsitektur:**
- Akun di-resolve dari field `kode_akun` pada record piutang/hutang itu sendiri — otomatis handle Piutang Dagang (1132), Piutang Konsinyasi (1135), Hutang Dagang (2111), Hutang Konsinyasi (2117).
- Pelunasan tidak bisa di-edit/hapus setelah posted. Koreksi via `JurnalService::balik()` (Step 10).

---

## ✅ STEP 8 — Laporan Keuangan (Buku Besar, Neraca, L/R)

**Tujuan:** Kepala Koperasi bisa melihat kondisi keuangan kapan saja.

**Rencana:**
- [x] **Buku Besar Periode** — Tabel mutasi per akun per bulan.
- [x] **Neraca Saldo (Trial Balance)** — Daftar semua akun dengan saldo D/K.
- [x] **Laporan Laba/Rugi** — Pendapatan vs HPP vs Beban → Laba Bersih.
- [x] **Neraca (Balance Sheet)** — Aset = Kewajiban + Modal.
- [x] Semua laporan bisa difilter per bulan/tahun.
- [ ] Export ke PDF / Excel. (Ditunda ke rilis berikutnya berdasar kesepakatan)

---

## ✅ STEP 9 — TutupBulanService & TutupTahunService (SELESAI)

**Tujuan:** Mengunci periode agar tidak ada perubahan data yang tidak sah.

**Yang dilakukan (sesuai `KONSEP-TUTUP-BUKU.md`):**

### Step 9a — Tutup Bulan
- [x] `TutupBulanService.php` — 8 validasi + eksekusi tutup bulan.
- [x] Validasi 1: Tidak ada jurnal DRAFT di periode ini.
- [x] Validasi 2: Total Debet = Total Kredit (balance check seluruh periode).
- [x] Validasi 3: Saldo Kas/Bank tidak negatif.
- [x] Validasi 4: Saldo Persediaan (BB) ≈ nilai fisik stok gudang.
- [x] Validasi 5: Saldo Piutang (BB) = buku pembantu piutang.
- [x] Validasi 6: Saldo Hutang (BB) = buku pembantu hutang.
- [x] Validasi 7: Saldo Persediaan Konsinyasi (BB) = nilai stok titipan.
- [x] Validasi 8: Rekonsiliasi konsinyasi via `v_rekonsiliasi_konsinyasi` harus kosong (selisih = 0).
- [x] Eksekusi: Panggil `JurnalService::bangunBukuBesar()` (snapshot final) → ubah `periode_akuntansi` ke `CLOSED`.
- [x] `TutupBulanController` — `index()` (tampil status 8 validasi) + `store()` (eksekusi).
- [x] View `tutup-bulan.blade.php` — tabel validasi live, tombol tutup hanya muncul jika semua lulus.
- [x] Route POST `akuntansi.tutup-bulan.store` ditambahkan.

### Step 9b — Tutup Tahun (Hard-Close)
- [x] Migration `sp_tutup_tahun(p_id_koperasi, p_tahun, p_user_id)` dibuat.
  - Cursor loop akun Pendapatan → [D] untuk meng-NOL-kan saldo Kredit.
  - Cursor loop akun Biaya+HPP → [K] untuk meng-NOL-kan saldo Debet.
  - Akun 811 (Ikhtisar Laba Rugi) sebagai clearing account.
  - Distribusi SHU ke Modal sesuai `config_shu` (jurnal kedua JTP-{tahun}-002).
  - `EXIT HANDLER FOR SQLEXCEPTION` → ROLLBACK otomatis.
- [x] `TutupTahunService.php` — 3 pra-kondisi + `ringkasanLabaRugi()` + eksekusi.
  - Pra-kondisi 1: Total persentase `config_shu` = 100%.
  - Pra-kondisi 2: Bulan 1–11 semua sudah CLOSED.
  - Pra-kondisi 3: Tahun belum pernah di-LOCKED.
  - Setelah SP berhasil: semua periode tahun tersebut diubah ke `LOCKED`.
- [x] `TutupTahunController` — `index()` (pra-kondisi + preview L/R) + `store()` (finalisasi).
- [x] View `tutup-tahun.blade.php` — preview laba/rugi bersih, checkbox konfirmasi, tombol finalisasi dinonaktifkan sampai semua lulus.
- [x] Route POST `akuntansi.tutup-tahun.store` ditambahkan.

**Keputusan Arsitektur:**
- Rekonsiliasi "nota cicilan" diimplementasikan via `v_rekonsiliasi_konsinyasi` (sudah ada di migrasi) — semua kiriman konsinyasi yang masih berselisih antara piutang pemilik dan hutang penerima wajib di-settle terlebih dahulu.
- Akun Ikhtisar Laba Rugi menggunakan kode `811` (sudah didokumentasikan di view lama).
- Distribusi SHU menggunakan persentase dari `config_shu` — fleksibel tanpa hardcode.

---

## ✅ STEP 10 — Keamanan & Testing Lanjutan (SELESAI)

**Tujuan:** Memastikan sistem aman dan dapat dipertahankan secara teknis.

**Yang dilakukan:**
- [x] `BukuBesarPeriode.php` — Tambah `use BelongsToKoperasi` (backlog Step 2 selesai).
- [x] `PengirimanKonsinyasi.php` — Tambah 3 local scope: `scopeTerlibat()`, `scopeMilikSaya()`, `scopeTitipanMasuk()`.
- [x] `StokKonsinyasi.php` — Tambah 3 local scope: `scopeMilikKoperasi()`, `scopeDiKoperasi()`, `scopeTerlibat()`.
- [x] `TenantOwnershipRule.php` — Custom validation rule IDOR protection: cek bahwa ID yang dikirim form adalah milik koperasi aktif.
- [x] `StoreKasTransaksiRequest.php` — Ganti `exists:master_kas_bank` dengan `TenantOwnershipRule`.
- [x] Pastikan test `TenantIsolationTest` hijau.
- [x] Pastikan `JurnalServiceTest` idempoten dan saldo balance hijau.
- [x] `JurnalServiceTest.php` — 5 Unit test: posting berhasil, tidak balance, periode tutup, balik dua kali, balik berhasil.

**Keputusan Arsitektur:**
- Konsinyasi tidak pakai Global Scope karena dirancang lintas koperasi — menggunakan Local Scope eksplisit lebih aman dan eksplisit.
- Mode Admin Pusat (tanpa koperasi_aktif) diizinkan bypass TenantOwnershipRule secara otomatis.

---

## 📊 Progress Summary

```
Step  1  Discovery & Pemahaman          ████████████ 100% ✅
Step  2  Audit Keamanan Tenant          ████████████ 100% ✅ (backlog selesai di Step 10)
Step  3  Perencanaan Arsitektur          ████████████ 100% ✅
Step  4  Implementasi Mesin Jurnal      ████████████ 100% ✅
Step  5  Testing JurnalService          ████████████ 100% ✅
Step  6  Controller Kas Transaksi       ████████████ 100% ✅
Step  7  Controller Hutang/Piutang      ████████████ 100% ✅
Step  8  Laporan Keuangan               ████████████ 100% ✅
Step  9  Tutup Bulan & Tutup Tahun      ████████████ 100% ✅
Step 10  Keamanan & Testing Lanjutan    ████████████ 100% ✅
```

---

*Terakhir diperbarui: 2026-09-03 | Versi Roadmap: 1.4 | **🎉 SEMUA STEP SELESAI***
