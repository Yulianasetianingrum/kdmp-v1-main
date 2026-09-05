<?php

use App\Http\Controllers\Akuntansi\AsetTetapController;
use App\Http\Controllers\Akuntansi\ConfigShuController;
use App\Http\Controllers\Akuntansi\JurnalController;
use App\Http\Controllers\Akuntansi\LaporanController;
use App\Http\Controllers\Akuntansi\TutupBulanController;
use App\Http\Controllers\Akuntansi\TutupTahunController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Gudang\KartuStokController;
use App\Http\Controllers\Gudang\KerusakanBarangController;
use App\Http\Controllers\Gudang\OpnameController;
use App\Http\Controllers\Gudang\PenerimaanBarangController;
use App\Http\Controllers\Gudang\StokController;
use App\Http\Controllers\Keuangan\HutangController;
use App\Http\Controllers\Keuangan\KasTransaksiController;
use App\Http\Controllers\Keuangan\PelunasanController;
use App\Http\Controllers\Keuangan\PiutangController;
use App\Http\Controllers\Keuangan\SimpananController;
use App\Http\Controllers\Konsinyasi\MarketplaceController;
use App\Http\Controllers\Konsinyasi\PengirimanKonsinyasiController;
use App\Http\Controllers\Konsinyasi\RekonsiliasiController;
use App\Http\Controllers\Konsinyasi\SetoranKonsinyasiController;
use App\Http\Controllers\Konsinyasi\StokKonsinyasiController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\Master\BarangController;
use App\Http\Controllers\Master\CoaController;
use App\Http\Controllers\Master\GudangController as MasterGudangController;
use App\Http\Controllers\Master\KasBankController;
use App\Http\Controllers\Master\KomoditasController;
use App\Http\Controllers\Master\KoperasiController;
use App\Http\Controllers\Master\PeriodeController;
use App\Http\Controllers\Master\PihakController;
use App\Http\Controllers\Master\SatuanController;
use App\Http\Controllers\Pembelian\PembelianController;
use App\Http\Controllers\Pembelian\ReturPembelianController;
use App\Http\Controllers\Penjualan\PenjualanController;
use App\Http\Controllers\Penjualan\ReturPenjualanController;
use App\Http\Controllers\Perencanaan\DemografiController;
use App\Http\Controllers\Perencanaan\KebutuhanKomoditasController;
use App\Http\Controllers\Perencanaan\NeracaKomoditasController;
use App\Http\Controllers\Perencanaan\PerbandinganHargaController;
use App\Http\Controllers\Perencanaan\PermintaanPengadaanController;
use App\Http\Controllers\Perencanaan\PotensiProduksiController;
use App\Http\Controllers\Perencanaan\StandarKebutuhanController;
use App\Http\Controllers\Survei\PertanyaanController;
use App\Http\Controllers\Survei\SesiSurveiController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('landing');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ===== M0 — Master & Periode =====
    Route::prefix('master')->name('master.')->group(function () {
        Route::resource('koperasi', KoperasiController::class);
        Route::resource('komoditas', KomoditasController::class);
        Route::resource('satuan', SatuanController::class);
        Route::resource('barang', BarangController::class);
        Route::resource('pihak', PihakController::class);
        Route::resource('kas-bank', KasBankController::class);
        Route::resource('gudang', MasterGudangController::class);
        Route::resource('periode', PeriodeController::class)->only(['index', 'edit', 'update']);
        Route::resource('coa', CoaController::class);
    });

    // ===== M1 — Survey =====
    Route::prefix('survei')->name('survei.')->group(function () {
        Route::resource('sesi', SesiSurveiController::class);
        Route::resource('pertanyaan', PertanyaanController::class);
    });

    // ===== M2/M3 — Kalkulasi Kebutuhan & Perencanaan =====
    Route::prefix('perencanaan')->name('perencanaan.')->group(function () {
        Route::resource('demografi', DemografiController::class);
        Route::resource('potensi-produksi', PotensiProduksiController::class);
        Route::resource('standar-kebutuhan', StandarKebutuhanController::class);
        Route::resource('kebutuhan-komoditas', KebutuhanKomoditasController::class)
            ->only(['index', 'show']);
        Route::resource('neraca-komoditas', NeracaKomoditasController::class)
            ->only(['index', 'show']);
        Route::resource('perbandingan-harga', PerbandinganHargaController::class);
        Route::resource('permintaan-pengadaan', PermintaanPengadaanController::class);
    });

    // ===== M4 — Gudang =====
    Route::prefix('gudang')->name('gudang.')->group(function () {
        Route::resource('penerimaan', PenerimaanBarangController::class);
        Route::resource('opname', OpnameController::class);
        Route::resource('kerusakan', KerusakanBarangController::class);
        Route::resource('kartu-stok', KartuStokController::class)->only(['index', 'show']);
        Route::resource('stok', StokController::class)->only(['index', 'show']);
    });

    // ===== M5 — Pembelian =====
    Route::prefix('pembelian')->name('pembelian.')->group(function () {
        Route::resource('pembelian', PembelianController::class);
        Route::resource('retur', ReturPembelianController::class);
    });

    // ===== M6 — Penjualan =====
    Route::prefix('penjualan')->name('penjualan.')->group(function () {
        Route::resource('penjualan', PenjualanController::class);
        Route::resource('retur', ReturPenjualanController::class);
    });

    // ===== M7 — Konsinyasi Antar Desa =====
    Route::prefix('konsinyasi')->name('konsinyasi.')->group(function () {
        Route::resource('marketplace', MarketplaceController::class);
        Route::resource('pengiriman', PengirimanKonsinyasiController::class);
        Route::resource('stok', StokKonsinyasiController::class)->only(['index', 'show']);
        Route::resource('setoran', SetoranKonsinyasiController::class);
        Route::get('rekonsiliasi', [RekonsiliasiController::class, 'index'])->name('rekonsiliasi.index');
    });

    // ===== M8/M9 — Keuangan & Akuntansi (fokus pendalaman besok) =====
    Route::prefix('keuangan')->name('keuangan.')->group(function () {
        Route::resource('piutang', PiutangController::class)->only(['index', 'show']);
        Route::resource('hutang', HutangController::class)->only(['index', 'show']);
        // AJAX: ambil piutang/hutang terbuka milik pihak tertentu (harus SEBELUM resource)
        Route::get('pelunasan/pihak/{pihak}/terbuka', [PelunasanController::class, 'terbuka'])
            ->name('pelunasan.terbuka');
        // Pelunasan tidak bisa di-edit/hapus setelah posted — pembatalan via jurnal balik
        Route::resource('pelunasan', PelunasanController::class)->except(['edit', 'update', 'destroy']);
        Route::resource('kas-transaksi', KasTransaksiController::class);
        Route::resource('simpanan', SimpananController::class);
    });

    Route::prefix('akuntansi')->name('akuntansi.')->group(function () {
        Route::resource('jurnal', JurnalController::class)->only(['index', 'show']);
        Route::resource('aset-tetap', AsetTetapController::class);
        Route::resource('config-shu', ConfigShuController::class);
        Route::get('tutup-bulan', [TutupBulanController::class, 'index'])->name('tutup-bulan.index');
        Route::post('tutup-bulan', [TutupBulanController::class, 'store'])->name('tutup-bulan.store');
        Route::get('tutup-tahun', [TutupTahunController::class, 'index'])->name('tutup-tahun.index');
        Route::post('tutup-tahun', [TutupTahunController::class, 'store'])->name('tutup-tahun.store');

        Route::prefix('laporan')->name('laporan.')->group(function () {
            Route::get('neraca-saldo', [LaporanController::class, 'neracaSaldo'])->name('neraca-saldo');
            Route::get('buku-besar', [LaporanController::class, 'bukuBesar'])->name('buku-besar');
            Route::get('neraca', [LaporanController::class, 'neraca'])->name('neraca');
            Route::get('laba-rugi', [LaporanController::class, 'labaRugi'])->name('laba-rugi');
            Route::get('arus-kas', [LaporanController::class, 'arusKas'])->name('arus-kas');
        });
    });
});
