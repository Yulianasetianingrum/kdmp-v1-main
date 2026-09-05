<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('komoditas', function (Blueprint $t) {
            $t->id();
            $t->string('kategori', 30);
            $t->string('nama');
            $t->json('alias_json')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('satuan', function (Blueprint $t) {
            $t->id();
            $t->string('kode_satuan', 20)->unique();
            $t->json('alias_json')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('master_unit_usaha', function (Blueprint $t) {
            $t->id('id_unit_usaha');
            $t->string('kode_unit_usaha', 20)->unique();
            $t->string('nama_unit_usaha', 100);
            $t->string('kode_akun_persediaan', 10);
            $t->string('kode_akun_pendapatan_anggota', 10);
            $t->string('kode_akun_pendapatan_non_anggota', 10);
            $t->string('kode_akun_hpp', 10);
            $t->text('keterangan')->nullable();
            $t->timestamps();

            foreach ([
                'kode_akun_persediaan',
                'kode_akun_pendapatan_anggota',
                'kode_akun_pendapatan_non_anggota',
                'kode_akun_hpp',
            ] as $col) {
                $t->foreign($col)->references('kode_anak')->on('master_coa');
            }
        });

        // 1 komoditas : N barang. Beras bisa punya varian premium & medium.
        Schema::create('master_barang', function (Blueprint $t) {
            $t->id('id_barang');
            $t->string('kode_barang', 30)->unique();
            $t->foreignId('id_komoditas')->constrained('komoditas');
            $t->foreignId('id_unit_usaha')->constrained('master_unit_usaha', 'id_unit_usaha');
            $t->string('nama_barang', 150);
            $t->foreignId('id_satuan_dasar')->constrained('satuan');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // Stok SELALU disimpan dalam satuan dasar. Konversi hanya di layer input.
        Schema::create('konversi_satuan', function (Blueprint $t) {
            $t->id('id_konversi');
            $t->foreignId('id_barang')->constrained('master_barang', 'id_barang');
            $t->foreignId('id_satuan')->constrained('satuan');
            $t->decimal('faktor_ke_dasar', 18, 6)->comment('1 satuan ini = berapa satuan dasar');
            $t->boolean('is_default_beli')->default(false);
            $t->boolean('is_default_jual')->default(false);
            $t->unique(['id_barang', 'id_satuan']);
        });

        // Parameter barang yang berbeda antar desa
        Schema::create('barang_per_koperasi', function (Blueprint $t) {
            $t->foreignId('id_koperasi')->constrained('koperasi_desa', 'id_koperasi');
            $t->foreignId('id_barang')->constrained('master_barang', 'id_barang');
            $t->decimal('stok_minimum', 18, 4)->default(0);
            $t->decimal('stok_maksimum', 18, 4)->default(0);
            $t->decimal('harga_jual_standar', 18, 2)->default(0);
            $t->boolean('is_dijual')->default(true);
            $t->primary(['id_koperasi', 'id_barang']);
        });

        Schema::create('master_pihak', function (Blueprint $t) {
            $t->id('id_pihak');
            $t->foreignId('id_koperasi')->constrained('koperasi_desa', 'id_koperasi');
            $t->enum('jenis_pihak', ['supplier', 'petani', 'warga', 'koperasi_desa_lain']);
            $t->unsignedBigInteger('id_koperasi_mitra')->nullable();
            $t->string('nama');
            $t->string('nik', 20)->nullable();
            $t->boolean('is_anggota')->default(false)
              ->comment('penentu akun pendapatan dan hak SHU');
            $t->string('no_anggota', 30)->nullable();
            $t->date('tgl_jadi_anggota')->nullable();
            $t->text('alamat')->nullable();
            $t->string('no_hp', 30)->nullable();
            $t->foreignId('id_wilayah')->nullable()->constrained('wilayah');
            $t->decimal('kualitas_rating', 3, 2)->nullable();
            $t->string('estimasi_pengiriman', 50)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();

            $t->unique(['id_koperasi', 'no_anggota']);
            $t->index(['id_koperasi', 'jenis_pihak']);
            $t->foreign('id_koperasi_mitra')->references('id_koperasi')->on('koperasi_desa');
        });

        Schema::create('master_kas_bank', function (Blueprint $t) {
            $t->id('id_kas_bank');
            $t->foreignId('id_koperasi')->constrained('koperasi_desa', 'id_koperasi');
            $t->enum('jenis', ['kas', 'bank']);
            $t->string('nama', 100);
            $t->string('no_rekening', 50)->nullable();
            $t->string('kode_akun', 10);
            $t->boolean('is_default')->default(false);
            $t->boolean('is_active')->default(true);
            $t->foreign('kode_akun')->references('kode_anak')->on('master_coa');
        });

        Schema::create('gudang', function (Blueprint $t) {
            $t->id('id_gudang');
            $t->foreignId('id_koperasi')->constrained('koperasi_desa', 'id_koperasi');
            $t->string('kode_gudang', 20);
            $t->string('nama_gudang', 100);
            $t->text('alamat')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unique(['id_koperasi', 'kode_gudang']);
        });

        Schema::create('aset_tetap', function (Blueprint $t) {
            $t->id('id_aset');
            $t->foreignId('id_koperasi')->constrained('koperasi_desa', 'id_koperasi');
            $t->string('kode_aset', 30);
            $t->string('nama_aset', 150);
            $t->enum('kategori', ['tanah', 'gedung', 'kendaraan', 'peralatan']);
            $t->date('tgl_perolehan');
            $t->decimal('nilai_perolehan', 18, 2);
            $t->decimal('nilai_residu', 18, 2)->default(0);
            $t->integer('umur_bulan')->default(0)->comment('0 untuk tanah, tidak disusutkan');
            $t->decimal('akum_penyusutan', 18, 2)->default(0);
            $t->string('kode_akun_aset', 10);
            $t->string('kode_akun_akum', 10)->nullable();
            $t->string('kode_akun_biaya', 10)->nullable();
            $t->enum('status', ['aktif', 'habis_susut', 'dilepas'])->default('aktif');
            $t->unique(['id_koperasi', 'kode_aset']);
        });
    }

    public function down(): void
    {
        foreach ([
            'aset_tetap', 'gudang', 'master_kas_bank', 'master_pihak',
            'barang_per_koperasi', 'konversi_satuan', 'master_barang',
            'master_unit_usaha', 'satuan', 'komoditas',
        ] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
