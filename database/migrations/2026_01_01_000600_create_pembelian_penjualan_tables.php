<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembelian', function (Blueprint $t) {
            $t->id('id_pembelian');
            $t->foreignId('id_koperasi')->constrained('koperasi_desa', 'id_koperasi');
            $t->string('kode_pembelian', 30);
            $t->foreignId('id_permintaan')->nullable()
              ->constrained('permintaan_pengadaan', 'id_permintaan');
            $t->foreignId('id_pihak')->constrained('master_pihak', 'id_pihak');
            $t->foreignId('id_unit_usaha')->constrained('master_unit_usaha', 'id_unit_usaha');
            $t->foreignId('id_gudang')->constrained('gudang', 'id_gudang');
            $t->date('tanggal_transaksi');
            $t->enum('jenis_pembayaran', ['tunai', 'transfer', 'kredit']);
            $t->foreignId('id_kas_bank')->nullable()->constrained('master_kas_bank', 'id_kas_bank')
              ->comment('wajib jika tunai/transfer');
            $t->date('tgl_jatuh_tempo')->nullable()->comment('wajib jika kredit');
            $t->decimal('total_pembelian', 18, 2)->default(0);
            $t->enum('status', ['draft', 'disetujui', 'diterima', 'selesai', 'dibatalkan'])->default('draft');
            $t->enum('status_posting', ['F', 'T'])->default('F');
            $t->unsignedBigInteger('id_jurnal')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->unique(['id_koperasi', 'kode_pembelian']);
        });

        Schema::create('detail_pembelian', function (Blueprint $t) {
            $t->id('id_detail');
            $t->foreignId('id_pembelian')->constrained('pembelian', 'id_pembelian');
            $t->foreignId('id_barang')->constrained('master_barang', 'id_barang');
            $t->foreignId('id_satuan_input')->constrained('satuan');
            $t->decimal('qty_input', 18, 4);
            $t->decimal('faktor_konversi', 18, 6)->default(1);
            $t->decimal('qty_dasar', 18, 4);
            $t->decimal('harga_satuan_input', 18, 2);
            $t->decimal('subtotal', 18, 2);
        });

        Schema::create('retur_pembelian', function (Blueprint $t) {
            $t->id('id_retur');
            $t->foreignId('id_koperasi')->constrained('koperasi_desa', 'id_koperasi');
            $t->string('kode_retur', 30);
            $t->foreignId('id_pembelian')->constrained('pembelian', 'id_pembelian');
            $t->foreignId('id_penerimaan')->nullable()->constrained('penerimaan_barang', 'id_penerimaan');
            $t->date('tgl_retur');
            $t->enum('jenis_penyelesaian', ['uang', 'potong_hutang', 'ganti_barang']);
            $t->decimal('total_nilai', 18, 2)->default(0);
            $t->text('alasan')->nullable();
            $t->string('foto_bukti')->nullable();
            $t->enum('status', ['diajukan', 'disetujui', 'selesai'])->default('diajukan');
            $t->enum('status_posting', ['F', 'T'])->default('F');
            $t->unsignedBigInteger('id_jurnal')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->unique(['id_koperasi', 'kode_retur']);
        });

        Schema::create('retur_pembelian_detail', function (Blueprint $t) {
            $t->id('id_detail');
            $t->foreignId('id_retur')->constrained('retur_pembelian', 'id_retur');
            $t->foreignId('id_barang')->constrained('master_barang', 'id_barang');
            $t->decimal('qty_dasar', 18, 4);
            $t->decimal('hpp_rata2', 18, 4);
            $t->decimal('nilai', 18, 2);
        });

        /**
         * Penjualan ke warga SELALU tunai atau transfer.
         * Kode JKW (jual kredit warga) sengaja tidak ada di master_transaksi.
         *
         * is_pembeli_anggota DISALIN saat transaksi, tidak di-join saat pelaporan.
         * Kalau di-join, warga yang keluar keanggotaan tahun depan akan mengubah
         * angka SHU tahun-tahun sebelumnya.
         */
        Schema::create('penjualan', function (Blueprint $t) {
            $t->id('id_penjualan');
            $t->foreignId('id_koperasi')->constrained('koperasi_desa', 'id_koperasi');
            $t->string('kode_penjualan', 30);
            $t->foreignId('id_pihak')->constrained('master_pihak', 'id_pihak');
            $t->foreignId('id_unit_usaha')->constrained('master_unit_usaha', 'id_unit_usaha');
            $t->foreignId('id_gudang')->constrained('gudang', 'id_gudang');
            $t->date('tanggal_transaksi');
            $t->boolean('is_pembeli_anggota')->default(false);
            $t->boolean('ada_baris_konsinyasi')->default(false)
              ->comment('true jika nota ini memuat barang titipan desa lain');
            $t->foreignId('id_kas_bank')->nullable()->constrained('master_kas_bank', 'id_kas_bank');
            $t->enum('metode_bayar', ['tunai', 'transfer']);
            $t->decimal('total_bruto', 18, 2)->default(0);
            $t->decimal('diskon', 18, 2)->default(0);
            $t->decimal('total_bayar', 18, 2)->default(0);
            $t->decimal('total_hpp', 18, 2)->default(0)->comment('hanya barang milik sendiri');
            $t->enum('status', ['draft', 'selesai', 'dibatalkan'])->default('draft');
            $t->enum('status_posting', ['F', 'T'])->default('F');
            $t->unsignedBigInteger('id_jurnal')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->unique(['id_koperasi', 'kode_penjualan']);
            $t->index(['id_koperasi', 'tanggal_transaksi']);
        });

        Schema::create('detail_penjualan', function (Blueprint $t) {
            $t->id('id_detail');
            $t->foreignId('id_penjualan')->constrained('penjualan', 'id_penjualan');
            $t->foreignId('id_barang')->constrained('master_barang', 'id_barang');
            // Kalau kolom ini terisi, baris diproses sebagai penjualan titipan (KJL),
            // bukan penjualan biasa. Satu nota boleh campur.
            $t->unsignedBigInteger('id_stok_konsinyasi')->nullable();
            $t->foreignId('id_satuan_input')->constrained('satuan');
            $t->decimal('qty_input', 18, 4);
            $t->decimal('faktor_konversi', 18, 6)->default(1);
            $t->decimal('qty_dasar', 18, 4);
            $t->decimal('harga_satuan', 18, 2);
            $t->decimal('subtotal', 18, 2);
            $t->decimal('hpp_satuan_dasar', 18, 4)->default(0)
              ->comment('dari stok.hpp_rata2 SAAT transaksi; 0 untuk baris konsinyasi');
            $t->decimal('total_hpp', 18, 2)->default(0);
            $t->decimal('harga_titip_satuan', 18, 2)->default(0)
              ->comment('hak desa pemilik, hanya untuk baris konsinyasi');
        });

        Schema::create('retur_penjualan', function (Blueprint $t) {
            $t->id('id_retur');
            $t->foreignId('id_koperasi')->constrained('koperasi_desa', 'id_koperasi');
            $t->string('kode_retur', 30);
            $t->foreignId('id_penjualan')->constrained('penjualan', 'id_penjualan');
            $t->date('tgl_retur');
            $t->enum('jenis_penyelesaian', ['uang', 'potong_piutang', 'ganti_barang']);
            $t->decimal('total_nilai', 18, 2)->default(0);
            $t->decimal('total_hpp', 18, 2)->default(0);
            $t->text('alasan')->nullable();
            $t->enum('status', ['diajukan', 'disetujui', 'selesai'])->default('diajukan');
            $t->enum('status_posting', ['F', 'T'])->default('F');
            $t->unsignedBigInteger('id_jurnal')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->unique(['id_koperasi', 'kode_retur']);
        });

        Schema::create('retur_penjualan_detail', function (Blueprint $t) {
            $t->id('id_detail');
            $t->foreignId('id_retur')->constrained('retur_penjualan', 'id_retur');
            $t->foreignId('id_barang')->constrained('master_barang', 'id_barang');
            $t->decimal('qty_dasar', 18, 4);
            $t->decimal('harga_satuan', 18, 2);
            $t->decimal('nilai', 18, 2);
            $t->decimal('hpp_satuan', 18, 4);
            $t->decimal('total_hpp', 18, 2);
        });
    }

    public function down(): void
    {
        foreach ([
            'retur_penjualan_detail', 'retur_penjualan', 'detail_penjualan', 'penjualan',
            'retur_pembelian_detail', 'retur_pembelian', 'detail_pembelian', 'pembelian',
        ] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
