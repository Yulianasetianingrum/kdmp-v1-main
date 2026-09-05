# KDMP — Sistem ERP Koperasi Desa Merah Putih

Kerangka proyek Laravel siap-bangun. Buka folder ini di VS Code, baca `docs/`, lalu ikuti urutan kerja di `docs/04-checklist-build.md`.

## Keputusan arsitektur yang sudah final

| Aspek | Keputusan |
|---|---|
| Framework | Laravel 11/12, PHP 8.2+ |
| Database | MySQL 8 / MariaDB 10.6+, **satu database multi-tenant** |
| Isolasi tenant | Kolom `id_koperasi` + global scope, bukan database terpisah |
| Metode persediaan | Moving Average (perpetual) |
| Status PKP | Tidak — tidak ada modul PPN |
| Gudang | Satu per desa |
| Antar desa | **Konsinyasi** — barang titipan, bukan jual-beli putus |
| Penjualan kredit | Hanya antar desa; ke warga selalu tunai/transfer |
| Tahun buku | Jan–Des, periode 13 untuk penyesuaian sampai Maret |
| Mode operasi | Online penuh, tanpa sinkronisasi offline |

## Cara memulai

```bash
composer create-project laravel/laravel kdmp
cd kdmp

# salin folder app/, database/, docs/ dari kerangka ini ke dalam proyek

composer require spatie/laravel-permission
php artisan migrate
php artisan db:seed --class=Database\\Seeders\\CoaSeeder
php artisan db:seed --class=Database\\Seeders\\TransaksiTemplateSeeder
```

Konfigurasi `.env` yang wajib diubah:

```
DB_CONNECTION=mysql
DB_DATABASE=kdmp
APP_TIMEZONE=Asia/Jakarta
```

Tambahkan di `config/app.php`: `'timezone' => 'Asia/Jakarta'`.

## Struktur folder

```
app/
  Enums/            Enum PHP untuk status, jenis mutasi, posisi D/K
  Models/
    Concerns/       trait BelongsToKoperasi
  Scopes/           KoperasiScope — global scope tenant
  Services/         SELURUH logika bisnis ada di sini
    PeriodeService.php       buka/tutup periode, validasi posting
    JurnalService.php        mesin jurnal otomatis dari template
    StokService.php          moving average, kartu stok
    PenjualanService.php     penjualan reguler + penjualan titipan
    KonsinyasiService.php    kirim titip, jual titipan, setor, retur
    TutupBukuService.php     tutup bulan & tutup tahun
  Support/          NomorDokumen helper
database/
  migrations/       9 file, dikelompokkan per domain
  seeders/          CoaSeeder, TransaksiTemplateSeeder
docs/
  01-arsitektur.md          multi-tenant, aturan lintas modul
  02-alur-modul.md          alur tiap modul, state machine dokumen
  03-konsinyasi.md          model akuntansi konsinyasi (BACA DULU)
  04-checklist-build.md     urutan kerja + definition of done
```

## Lima aturan yang tidak boleh dilanggar

1. **Tidak ada controller yang menulis ke `jurnal_header` / `jurnal_detail` langsung.** Semua lewat `JurnalService::posting()`.
2. **Hanya `StokService` yang boleh menyentuh tabel `stok` dan `kartu_stok`.**
3. **Setiap transaksi bisnis dibungkus satu `DB::transaction()`.** Kalau jurnal gagal, mutasi stok ikut rollback. Jangan pernah commit stok lebih dulu lalu menjurnal di job terpisah.
4. **Uang selalu `decimal(18,2)`, kuantitas selalu `decimal(18,4)`.** Jangan pernah float atau double.
5. **Barang konsinyasi tidak pernah masuk tabel `stok`.** Hanya `stok_konsinyasi`. Melanggar ini membuat Neraca dua desa salah sekaligus.
