# Checklist Urutan Build

Urutan ini berbeda dari urutan modul di konsep awal Anda. Alasannya ada di catatan tiap fase.

---

## Fase 0 — Fondasi (1–2 minggu)

- [ ] `composer create-project laravel/laravel kdmp`
- [ ] Salin `app/`, `database/`, `docs/` dari kerangka ini
- [ ] `php artisan migrate` — pastikan 9 migrasi jalan tanpa error
- [ ] Jalankan `CoaSeeder` dan `TransaksiTemplateSeeder`
- [ ] Middleware `SetKoperasiAktif` yang mengisi `app()->instance('koperasi_aktif', ...)`
- [ ] Auth + peran (Surveyor, Admin Gudang, Kasir, Pembelian, Akuntan, Manajer)
- [ ] Seeder data awal: 2–3 koperasi desa contoh, gudang, kas/bank, unit usaha
- [ ] `PeriodeService::bukaTahun()` untuk tiap koperasi

**Definition of done:** login berhasil, dan `Penjualan::all()` hanya mengembalikan data desa yang sedang login.

---

## Fase 1 — Mesin jurnal (1 minggu)

- [ ] `JurnalService::posting()` jalan untuk template sederhana
- [ ] `JurnalService::balik()` jalan
- [ ] Test: posting `JTW` menghasilkan 4 baris dengan D = K
- [ ] Test: posting dua kali dengan `source_id` sama menghasilkan exception
- [ ] Test: posting ke periode CLOSED ditolak
- [ ] Test: UPDATE pada `jurnal_detail` yang POSTED ditolak oleh trigger

**Kenapa sebelum modul transaksi:** kalau mesin jurnal dibangun belakangan, Anda akan menemukan bahwa tabel transaksi yang sudah terlanjur jadi tidak menyimpan informasi yang dibutuhkan untuk menjurnalnya. Itu persis yang terjadi pada rancangan database Anda yang pertama — tabel `penjualan` tidak punya satu pun kolom yang menghubungkannya ke `master_transaksi`.

---

## Fase 2 — Gudang & moving average (1–2 minggu)

- [ ] `StokService::masuk()` dan `keluar()`
- [ ] Saldo awal: stock opname pertama per desa, `refTipe = 'SALDO_AWAL'`
- [ ] Penerimaan barang (GRN) multi-item + sortir
- [ ] Stock opname + jurnal selisih
- [ ] Kerusakan barang
- [ ] Laporan kartu stok

**Test moving average yang wajib lulus:**

```
Masuk  100 kg @ 8.000  -> hpp 8.000, nilai 800.000
Masuk  100 kg @ 10.000 -> hpp 9.000, nilai 1.800.000
Keluar  50 kg          -> hpp TETAP 9.000, nilai 1.350.000, HPP keluar 450.000
Masuk   50 kg @ 12.000 -> hpp 9.600, nilai 1.950.000
```

Kalau angka `hpp` berubah saat pengeluaran, implementasi Anda salah.

---

## Fase 3 — Pembelian & Penjualan (2 minggu)

- [ ] Pembelian tunai / transfer / kredit
- [ ] Jalur cepat pembelian dari petani (tanpa PR/PO) — jangan dilewati, kalau petugas lapangan dipaksa alur panjang mereka akan bypass sistem
- [ ] POS penjualan ke warga
- [ ] Retur pembelian & retur penjualan
- [ ] Konversi satuan di layer input

**Test yang wajib lulus:** setelah 10 transaksi campuran, `SUM(stok.nilai_persediaan)` harus sama persis dengan saldo akun Persediaan di jurnal.

---

## Fase 4 — Piutang, Hutang, Kas (1 minggu)

- [ ] Buku pembantu piutang & hutang dengan aging
- [ ] Pelunasan (parsial dan penuh)
- [ ] Kas masuk/keluar, mutasi antar kas
- [ ] Simpanan anggota (pokok, wajib, sukarela)

---

## Fase 5 — Konsinyasi (2 minggu)

**Baca `docs/03-konsinyasi.md` sampai habis sebelum menulis kode.**

- [ ] Marketplace permintaan/penawaran antar desa
- [ ] `KonsinyasiService::kirim()`
- [ ] `KonsinyasiService::jualTitipan()` — dua jurnal, dua tenant, satu transaksi
- [ ] POS mendukung baris campuran (barang sendiri + titipan dalam satu nota)
- [ ] Setoran, retur sisa, susut
- [ ] View `v_rekonsiliasi_konsinyasi` harus selalu kosong

**Test yang wajib lulus:**

```
Desa A kirim 100 kg (HPP 8.000, titip 12.000) ke Desa B
  -> akun 1126 di A = 800.000
  -> tabel `stok` Desa B TIDAK berubah sama sekali
  -> tidak ada piutang/hutang yang terbentuk

Desa B jual 60 kg @ 13.000
  -> Kas B naik 780.000
  -> akun 2117 di B = 720.000
  -> akun 417 di B = 60.000   (BUKAN 780.000)
  -> akun 416 di A = 720.000
  -> akun 1135 di A = 720.000
  -> akun 1126 di A turun jadi 320.000
  -> Pendapatan Penjualan (411-414) di B TIDAK berubah
```

Baris terakhir adalah yang paling sering salah. Kalau omzet Desa B naik 780.000, seluruh model konsinyasi Anda salah dan SHU Desa B akan menggelembung.

---

## Fase 6 — Tutup buku & laporan (1–2 minggu)

- [ ] `TutupBukuService::validasiTutupBulan()` — 8 pemeriksaan
- [ ] `bangunBukuBesar()` idempoten
- [ ] Neraca, Laba Rugi, Neraca Saldo, Arus Kas
- [ ] Aging piutang & hutang
- [ ] Penyusutan aset tetap bulanan
- [ ] Jurnal penutup tahunan + pembagian SHU

**Blokir:** `tutupTahun()` sengaja melempar exception sampai `config_shu` terisi dan berjumlah 100%. Ambil angkanya dari AD/ART koperasi sekarang, jangan tunggu Desember.

---

## Fase 7 — Survei & perencanaan (2 minggu)

- [ ] Survei dinamis via URL bertoken
- [ ] Integrasi speech-to-text
- [ ] Kalkulasi kebutuhan per bulan
- [ ] Neraca komoditas surplus/defisit
- [ ] Perbandingan harga antar desa
- [ ] Permintaan pengadaan otomatis

**Kenapa terakhir:** modul ini tidak menghasilkan uang dan tidak memblokir operasi koperasi. Gudang dan akuntansi memblokir segalanya. Kalau Anda membangun survei duluan lalu kehabisan waktu atau anggaran, Anda punya aplikasi survei yang bagus dan koperasi yang tidak bisa berjualan.

---

## Yang masih menunggu keputusan Anda

| Item | Dampak kalau ditunda |
|---|---|
| Persentase pembagian SHU (AD/ART) | Tutup tahun tidak bisa diselesaikan |
| Model imbalan: selisih harga atau komisi persen | Salah pilih = jurnal konsinyasi salah di semua transaksi |
| Penanggung susut titipan: pemilik atau penerima | Sengketa antar desa yang sistem tidak bisa selesaikan |
| Batas waktu titipan | Barang mengendap bertahun-tahun di neraca pemilik |
| Nilai persediaan awal per desa | Moving average mulai dari nol, HPP penjualan pertama salah |
