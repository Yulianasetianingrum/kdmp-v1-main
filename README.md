# KDMP — Kerangka (Rebuild)

Kerangka Laravel 12 dibangun ulang dari sistem KDMP asli. Skema database
**sama persis** dengan versi lama (12 migrasi disalin apa adanya, termasuk
trigger & view khusus MySQL di tabel jurnal). Model, controller, routing,
dan UI sudah lengkap untuk sembilan modul — tapi **logika bisnis belum
diisi**: mesin jurnal, HPP moving average, tutup buku, dsb. sengaja
dikosongkan dan menyusul saat pendalaman tiap modul, dimulai dari
**Keuangan & Akuntansi**.

## Yang sudah ada

- **Skema database identik** — 12 file migrasi di `database/migrations/`
- **66 model Eloquent**, terorganisir per modul di `app/Models/`
- **50 controller**, sebagian besar lewat satu base class
  `App\Http\Controllers\Concerns\ModuleCrudController` — `index()` sudah
  menampilkan data sungguhan dari database, `create/store/edit/update`
  masih placeholder
- **Routing lengkap** sembilan modul di `routes/web.php`
- **UI modern** — Tailwind 4, font Fraunces + IBM Plex, sidebar bermotif
  tab buku besar M0–M9, sudah diverifikasi tampil benar di browser
- Infrastruktur multi-tenant asli (`KoperasiScope`, `SetKoperasiAktif`,
  trait `BelongsToKoperasi`) disalin apa adanya — itu fondasi yang sudah
  teruji, bukan bagian yang perlu dibangun ulang
- Seeder: `CoaSeeder` & `TransaksiTemplateSeeder` (data referensi COA dan
  30 kode transaksi, disalin utuh dari sistem asli), `PeranSeeder`,
  `DataAwalSeeder` (2 koperasi contoh + akun login)

## Yang BELUM ada (sengaja, untuk pendalaman berikutnya)

- `JurnalService`, `TutupBukuService`, `PelunasanService`,
  `KasTransaksiService`, `SimpananService`, `AsetTetapService`,
  `PeriodeService`, `StokService`, `KonsinyasiService` — semua service
  bisnis dari dokumen pembelajaran modul Finance
- Form create/edit sungguhan di semua modul (saat ini placeholder)
- Fitur survei via suara (Speech-to-Text) — ada di sistem asli, belum
  dibawa ke kerangka ini

## Setup

Vendor PHP (`vendor/`) sudah disertakan sehingga **tidak perlu
`composer install`**. `node_modules` **sengaja tidak disertakan** (isinya
khusus platform tempat kerangka ini dibangun) — tapi CSS/JS hasil build
sudah ada di `public/build/`, jadi aplikasi tetap bisa langsung dicoba.

1. Sesuaikan `.env` — minimal `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
   untuk MySQL/MariaDB lokal Anda. `APP_KEY` sudah terisi, tapi boleh
   generate ulang dengan `php artisan key:generate` kalau mau.
2. Buat database kosong sesuai `DB_DATABASE` di `.env`.
3. Jalankan:
   ```bash
   php artisan migrate --seed
   ```
4. (Opsional, hanya kalau ingin mengubah CSS/JS)
   ```bash
   npm install
   npm run dev    # mode pengembangan
   npm run build  # build ulang ke public/build
   ```
5. Jalankan server:
   ```bash
   php artisan serve
   ```
6. Buka `http://127.0.0.1:8000`, klik **Masuk ke Sistem**, login dengan:
   - `manajer@kdmp-a.test` / `password` (Koperasi Desa Merah Putih Mekar Jaya)
   - `manajer@kdmp-b.test` / `password` (Koperasi Desa Merah Putih Sukamaju)

   Dua akun ini sengaja dibuat di dua koperasi (tenant) berbeda supaya
   Anda bisa mengetes isolasi data antar desa dan alur konsinyasi nanti.

## Struktur folder penting

```
app/Models/<Modul>/       Model per modul (Master, Akuntansi, Gudang, dst.)
app/Http/Controllers/<Modul>/   Controller per modul
app/Http/Controllers/Concerns/ModuleCrudController.php  Base CRUD generik
app/Scopes/KoperasiScope.php    Isolasi data antar tenant (dari sistem asli)
app/Models/Concerns/BelongsToKoperasi.php  Trait tenant (dari sistem asli)
database/migrations/            Skema — identik dengan sistem asli
database/seeders/               CoaSeeder & TransaksiTemplateSeeder dari sistem asli
resources/views/components/layouts/app.blade.php   Layout utama (sidebar+topbar)
resources/views/components/sidebar.blade.php        Sidebar modul M0-M9
resources/views/modules/index.blade.php              Tabel generik semua modul CRUD
routes/web.php                  Semua rute, dikelompokkan per modul
```

## Langkah berikutnya

Pendalaman modul **Keuangan & Akuntansi** — membangun `JurnalService`
(posting bertemplate, posting manual, jurnal pembalik), buku pembantu
piutang/hutang/kas/simpanan, delapan validasi tutup bulan, tutup tahun +
pembagian SHU, dan empat laporan keuangan. Rujukan detail lengkapnya ada
di dokumen pembelajaran yang sudah dibuat sebelumnya
(*Panduan-Sistem-KDMP-Modul-Finance.docx*).
