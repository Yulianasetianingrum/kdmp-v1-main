<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MODUL KONSINYASI ANTAR DESA
 *
 * Prinsip: barang titipan TETAP MILIK desa pengirim sampai terjual ke warga.
 * Baca docs/03-konsinyasi.md sebelum menyentuh modul ini.
 *
 * Barang titipan TIDAK BOLEH masuk tabel `stok` desa penerima. Kalau masuk,
 * Neraca desa penerima menggelembung dengan barang yang bukan miliknya dan
 * Neraca desa pemilik kehilangan aset yang sebenarnya masih miliknya.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Marketplace pencocokan surplus-defisit antar desa
        Schema::create('permintaan_barter', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_koperasi_pemohon')->constrained('koperasi_desa', 'id_koperasi');
            $t->foreignId('id_pemohon')->constrained('pengguna');
            $t->foreignId('id_barang')->constrained('master_barang', 'id_barang');
            $t->decimal('qty_diminta_dasar', 18, 4);
            $t->date('tgl_dibutuhkan')->nullable();
            $t->enum('status', ['terbuka', 'tercocok', 'tertutup', 'kadaluarsa'])->default('terbuka');
            $t->text('catatan')->nullable();
            $t->timestamps();
        });

        Schema::create('penawaran_barter', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_permintaan_barter')->constrained('permintaan_barter');
            $t->foreignId('id_koperasi_penawar')->constrained('koperasi_desa', 'id_koperasi');
            $t->foreignId('id_penawar')->constrained('pengguna');
            $t->decimal('qty_ditawarkan_dasar', 18, 4);
            $t->decimal('harga_titip_satuan', 18, 2);
            $t->enum('status', ['menunggu', 'diterima', 'ditolak'])->default('menunggu');
            $t->text('catatan')->nullable();
            $t->timestamps();
        });

        /**
         * Dokumen pengiriman titipan. Satu dokumen, jurnal HANYA di desa pengirim.
         * Desa penerima tidak menjurnal apa pun saat menerima.
         */
        Schema::create('pengiriman_konsinyasi', function (Blueprint $t) {
            $t->id('id_kiriman');
            $t->string('kode_kiriman', 30)->unique();
            $t->foreignId('id_penawaran_barter')->nullable()->constrained('penawaran_barter');
            $t->foreignId('id_koperasi_pemilik')->constrained('koperasi_desa', 'id_koperasi');
            $t->foreignId('id_koperasi_penerima')->constrained('koperasi_desa', 'id_koperasi');
            $t->foreignId('id_gudang_asal')->constrained('gudang', 'id_gudang');
            $t->foreignId('id_gudang_tujuan')->constrained('gudang', 'id_gudang');
            $t->date('tgl_kirim');
            $t->date('tgl_terima')->nullable();
            $t->date('tgl_batas_titip')->nullable()
              ->comment('tanpa batas waktu, barang mengendap di buku pemilik bertahun-tahun');

            // Dua model imbalan yang didukung. Tetapkan per pengiriman.
            $t->enum('model_imbalan', ['selisih_harga', 'komisi_persen'])->default('selisih_harga');
            $t->decimal('persen_komisi', 5, 2)->default(0)
              ->comment('hanya dipakai jika model_imbalan = komisi_persen');

            // Kebijakan susut HARUS ditetapkan di perjanjian antar desa,
            // bukan diputuskan operator per kasus.
            $t->enum('penanggung_susut', ['pemilik', 'penerima'])->default('pemilik');

            $t->decimal('total_nilai_titip', 18, 2)->default(0);
            $t->decimal('total_hpp_pemilik', 18, 2)->default(0);
            $t->enum('status', [
                'draft', 'dikirim', 'diterima', 'berjalan', 'selesai', 'ditolak',
            ])->default('draft');
            $t->enum('status_posting', ['F', 'T'])->default('F');
            $t->unsignedBigInteger('id_jurnal_kirim')->nullable();
            $t->text('catatan_pengiriman')->nullable();
            $t->text('catatan_penerimaan')->nullable();
            $t->timestamps();
        });

        Schema::create('pengiriman_konsinyasi_detail', function (Blueprint $t) {
            $t->id('id_detail');
            $t->foreignId('id_kiriman')->constrained('pengiriman_konsinyasi', 'id_kiriman');
            $t->foreignId('id_barang')->constrained('master_barang', 'id_barang');
            $t->decimal('qty_dasar', 18, 4);
            $t->decimal('harga_titip_satuan', 18, 2)->comment('hak desa pemilik per satuan');
            $t->decimal('harga_jual_saran', 18, 2)->default(0);
            $t->decimal('hpp_pemilik', 18, 4)->comment('snapshot hpp_rata2 desa pemilik saat kirim');
            $t->decimal('total_nilai_titip', 18, 2);
            $t->decimal('total_hpp', 18, 2);
        });

        /**
         * Stok titipan yang ada di gudang desa penerima.
         * TERPISAH dari tabel `stok` karena ini BUKAN aset desa penerima.
         *
         * Invariant yang harus selalu benar:
         *   qty_titip = qty_terjual + qty_retur + qty_susut + qty_sisa
         */
        Schema::create('stok_konsinyasi', function (Blueprint $t) {
            $t->id('id_stok_konsinyasi');
            $t->foreignId('id_kiriman')->constrained('pengiriman_konsinyasi', 'id_kiriman');
            $t->foreignId('id_koperasi_pemilik')->constrained('koperasi_desa', 'id_koperasi');
            $t->foreignId('id_koperasi_penerima')->constrained('koperasi_desa', 'id_koperasi');
            $t->foreignId('id_gudang_penerima')->constrained('gudang', 'id_gudang');
            $t->foreignId('id_barang')->constrained('master_barang', 'id_barang');
            $t->decimal('qty_titip', 18, 4);
            $t->decimal('qty_terjual', 18, 4)->default(0);
            $t->decimal('qty_retur', 18, 4)->default(0);
            $t->decimal('qty_susut', 18, 4)->default(0);
            $t->decimal('qty_sisa', 18, 4)->default(0);
            $t->decimal('harga_titip_satuan', 18, 2);
            $t->decimal('harga_jual_satuan', 18, 2)->default(0);
            $t->decimal('hpp_pemilik', 18, 4);
            $t->enum('status', ['aktif', 'habis', 'dikembalikan'])->default('aktif');
            $t->timestamps();
            $t->index(['id_koperasi_penerima', 'id_barang', 'status'], 'stokkon_pos_lookup');
            $t->index(['id_koperasi_pemilik', 'status'], 'stokkon_pemilik_lookup');
        });

        // Kartu mutasi titipan. Setara kartu_stok, tapi untuk barang orang lain.
        Schema::create('kartu_konsinyasi', function (Blueprint $t) {
            $t->id('id_kartu_kons');
            $t->foreignId('id_stok_konsinyasi')->constrained('stok_konsinyasi', 'id_stok_konsinyasi');
            $t->date('tanggal');
            $t->enum('jenis_mutasi', ['TITIP', 'JUAL', 'RETUR', 'SUSUT']);
            $t->string('ref_tipe', 30);
            $t->unsignedBigInteger('ref_id')->nullable();
            $t->decimal('qty', 18, 4);
            $t->decimal('harga_titip_satuan', 18, 2);
            $t->decimal('harga_jual_satuan', 18, 2)->default(0);
            $t->decimal('saldo_qty', 18, 4);
            $t->unsignedBigInteger('id_jurnal_penerima')->nullable();
            $t->unsignedBigInteger('id_jurnal_pemilik')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->unique(['ref_tipe', 'ref_id', 'id_stok_konsinyasi', 'jenis_mutasi'], 'kartukon_idempoten');
        });

        // Setoran hasil penjualan titipan dari desa penerima ke desa pemilik
        Schema::create('setoran_konsinyasi', function (Blueprint $t) {
            $t->id('id_setoran');
            $t->string('kode_setoran', 30)->unique();
            $t->foreignId('id_koperasi_penyetor')->constrained('koperasi_desa', 'id_koperasi');
            $t->foreignId('id_koperasi_penerima_dana')->constrained('koperasi_desa', 'id_koperasi');
            $t->date('tanggal');
            $t->decimal('total_nilai', 18, 2);
            $t->foreignId('id_kas_bank_penyetor')->constrained('master_kas_bank', 'id_kas_bank');
            $t->unsignedBigInteger('id_kas_bank_penerima');
            $t->enum('status_posting', ['F', 'T'])->default('F');
            $t->unsignedBigInteger('id_jurnal_penyetor')->nullable();
            $t->unsignedBigInteger('id_jurnal_penerima')->nullable();
            $t->text('catatan')->nullable();
            $t->timestamp('created_at')->nullable();
        });

        // FK yang menunggu tabel stok_konsinyasi terbentuk
        Schema::table('detail_penjualan', function (Blueprint $t) {
            $t->foreign('id_stok_konsinyasi')
              ->references('id_stok_konsinyasi')->on('stok_konsinyasi');
        });
    }

    public function down(): void
    {
        Schema::table('detail_penjualan', function (Blueprint $t) {
            $t->dropForeign(['id_stok_konsinyasi']);
        });
        foreach ([
            'setoran_konsinyasi', 'kartu_konsinyasi', 'stok_konsinyasi',
            'pengiriman_konsinyasi_detail', 'pengiriman_konsinyasi',
            'penawaran_barter', 'permintaan_barter',
        ] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
