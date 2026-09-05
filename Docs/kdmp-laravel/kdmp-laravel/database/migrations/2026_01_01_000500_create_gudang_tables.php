<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * State stok berjalan. INI yang membuat Moving Average bisa dijalankan.
         * hpp_rata2 diperbarui setiap penerimaan, dipakai (tidak diubah) setiap pengeluaran.
         *
         * Barang konsinyasi TIDAK BOLEH masuk tabel ini.
         */
        Schema::create('stok', function (Blueprint $t) {
            $t->foreignId('id_gudang')->constrained('gudang', 'id_gudang');
            $t->foreignId('id_barang')->constrained('master_barang', 'id_barang');
            $t->decimal('qty_on_hand', 18, 4)->default(0);
            $t->decimal('qty_reserved', 18, 4)->default(0);
            $t->decimal('hpp_rata2', 18, 4)->default(0);
            $t->decimal('nilai_persediaan', 18, 2)->default(0);
            $t->timestamp('updated_at')->nullable();
            $t->primary(['id_gudang', 'id_barang']);
        });

        /**
         * Kartu stok: append-only, kronologis.
         * DILARANG input mutasi mundur tanggal. Koreksi = mutasi baru hari ini.
         * Kalau backdate diizinkan, seluruh saldo_nilai di bawahnya jadi salah
         * tanpa mekanisme deteksi.
         */
        Schema::create('kartu_stok', function (Blueprint $t) {
            $t->id('id_kartu');
            $t->foreignId('id_koperasi')->constrained('koperasi_desa', 'id_koperasi');
            $t->foreignId('id_gudang')->constrained('gudang', 'id_gudang');
            $t->foreignId('id_barang')->constrained('master_barang', 'id_barang');
            $t->date('tanggal');
            $t->enum('jenis_mutasi', ['IN', 'OUT', 'ADJ_IN', 'ADJ_OUT']);
            $t->enum('ref_tipe', [
                'PENERIMAAN', 'PENJUALAN', 'RETUR_BELI', 'RETUR_JUAL',
                'OPNAME', 'KERUSAKAN', 'SALDO_AWAL',
                'KIRIM_KONSINYASI', 'RETUR_KONSINYASI',
            ]);
            $t->unsignedBigInteger('ref_id')->nullable();
            $t->decimal('qty_masuk', 18, 4)->default(0);
            $t->decimal('qty_keluar', 18, 4)->default(0);
            $t->decimal('harga_satuan', 18, 4)->default(0)
              ->comment('harga beli untuk IN, hpp_rata2 untuk OUT');
            $t->decimal('nilai_mutasi', 18, 2)->default(0);
            $t->decimal('saldo_qty', 18, 4)->default(0);
            $t->decimal('saldo_nilai', 18, 2)->default(0);
            $t->decimal('hpp_rata2_setelah', 18, 4)->default(0);
            $t->enum('jenis_kejadian', ['rusak', 'susut', 'hilang'])->nullable();
            $t->unsignedBigInteger('id_jurnal')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamp('created_at')->nullable();

            $t->index(['id_koperasi', 'id_gudang', 'id_barang', 'tanggal'], 'kartu_lookup');
            // Pengaman lapis database terhadap posting ganda
            $t->unique(['ref_tipe', 'ref_id', 'id_barang', 'jenis_mutasi'], 'kartu_idempoten');
        });

        Schema::create('penerimaan_barang', function (Blueprint $t) {
            $t->id('id_penerimaan');
            $t->foreignId('id_koperasi')->constrained('koperasi_desa', 'id_koperasi');
            $t->foreignId('id_gudang')->constrained('gudang', 'id_gudang');
            $t->string('kode_penerimaan', 30);
            $t->unsignedBigInteger('id_pembelian')->nullable();
            $t->foreignId('id_pihak')->constrained('master_pihak', 'id_pihak');
            $t->date('tanggal_terima');
            $t->enum('status', ['draft', 'disortir', 'diposting', 'dibatalkan'])->default('draft');
            $t->text('catatan')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->unique(['id_koperasi', 'kode_penerimaan']);
        });

        // Hasil sortir jadi kolom di sini, bukan tabel terpisah, supaya pada
        // penerimaan multi-item ketahuan barang MANA yang tidak layak.
        Schema::create('penerimaan_barang_detail', function (Blueprint $t) {
            $t->id('id_detail');
            $t->foreignId('id_penerimaan')->constrained('penerimaan_barang', 'id_penerimaan');
            $t->foreignId('id_barang')->constrained('master_barang', 'id_barang');
            $t->foreignId('id_satuan_input')->constrained('satuan');
            $t->decimal('qty_input', 18, 4)->comment('dalam satuan input, mis. 10 karung');
            $t->decimal('faktor_konversi', 18, 6)->default(1);
            $t->decimal('qty_dasar', 18, 4);
            $t->decimal('qty_layak', 18, 4)->default(0);
            $t->decimal('qty_tidak_layak', 18, 4)->default(0);
            $t->decimal('harga_satuan_dasar', 18, 4);
            $t->decimal('subtotal', 18, 2);
            $t->text('alasan_tidak_layak')->nullable();
            $t->string('foto_bukti')->nullable();
        });

        Schema::create('opname_header', function (Blueprint $t) {
            $t->id('id_opname');
            $t->foreignId('id_koperasi')->constrained('koperasi_desa', 'id_koperasi');
            $t->foreignId('id_gudang')->constrained('gudang', 'id_gudang');
            $t->string('kode_opname', 30);
            $t->date('tanggal');
            $t->enum('status', ['draft', 'dihitung', 'disetujui', 'diposting'])->default('draft');
            $t->unsignedBigInteger('approved_by')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->unique(['id_koperasi', 'kode_opname']);
        });

        Schema::create('opname_detail', function (Blueprint $t) {
            $t->id('id_detail');
            $t->foreignId('id_opname')->constrained('opname_header', 'id_opname');
            $t->foreignId('id_barang')->constrained('master_barang', 'id_barang');
            $t->decimal('qty_sistem', 18, 4);
            $t->decimal('qty_fisik', 18, 4);
            $t->decimal('selisih', 18, 4);
            $t->decimal('hpp_rata2', 18, 4);
            $t->decimal('nilai_selisih', 18, 2);
            $t->text('keterangan')->nullable();
        });

        Schema::create('kerusakan_barang', function (Blueprint $t) {
            $t->id('id_kerusakan');
            $t->foreignId('id_koperasi')->constrained('koperasi_desa', 'id_koperasi');
            $t->foreignId('id_gudang')->constrained('gudang', 'id_gudang');
            $t->foreignId('id_barang')->constrained('master_barang', 'id_barang');
            $t->date('tanggal');
            $t->decimal('qty', 18, 4);
            $t->decimal('hpp_rata2', 18, 4);
            $t->decimal('nilai_kerugian', 18, 2);
            $t->enum('jenis_kejadian', ['rusak', 'susut', 'hilang', 'kadaluarsa']);
            $t->string('foto_bukti')->nullable();
            $t->enum('status', ['draft', 'disetujui', 'diposting'])->default('draft');
            $t->unsignedBigInteger('approved_by')->nullable();
            $t->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach ([
            'kerusakan_barang', 'opname_detail', 'opname_header',
            'penerimaan_barang_detail', 'penerimaan_barang', 'kartu_stok', 'stok',
        ] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
