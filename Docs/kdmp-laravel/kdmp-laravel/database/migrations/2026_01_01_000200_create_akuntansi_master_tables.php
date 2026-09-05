<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COA dibuat lebih dulu karena master_unit_usaha dan master_kas_bank
 * punya foreign key ke sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_coa', function (Blueprint $t) {
            $t->string('kode_anak', 10)->primary();
            $t->string('kode_induk', 10)->nullable();
            $t->string('nama_rekening', 150);
            $t->enum('posisi_normal', ['D', 'K']);
            $t->enum('is_transaction', ['T', 'F'])->default('T')
              ->comment('T = leaf, bisa diposting. F = header, hanya agregasi');
            $t->enum('kelompok', [
                'Aktiva', 'Kewajiban', 'Modal', 'Pendapatan',
                'HPP', 'Biaya', 'Non-Operasional', 'Ikhtisar',
            ]);
            $t->boolean('is_kontra')->default(false);
            $t->unsignedTinyInteger('level')->default(1);
            $t->integer('urutan_laporan')->default(0);
            $t->boolean('is_active')->default(true);

            $t->foreign('kode_induk')->references('kode_anak')->on('master_coa');
        });

        Schema::create('master_transaksi', function (Blueprint $t) {
            $t->string('kode_transaksi', 10)->primary();
            $t->string('nama_transaksi', 100);
            $t->string('modul', 30);
            $t->boolean('is_active')->default(true);
        });

        /**
         * akun_dinamis: akun yang baru diketahui saat runtime.
         *   KAS_BANK        -> dari master_kas_bank pilihan kasir
         *   PERSEDIAAN_UNIT -> dari master_unit_usaha.kode_akun_persediaan
         *   PENDAPATAN_UNIT -> tergantung unit usaha DAN status keanggotaan
         *   HPP_UNIT        -> dari master_unit_usaha.kode_akun_hpp
         */
        Schema::create('master_detail_transaksi', function (Blueprint $t) {
            $t->id('id_master');
            $t->string('kode_transaksi', 10);
            $t->unsignedTinyInteger('urutan');
            $t->string('kode_anak', 10)->nullable();
            $t->enum('akun_dinamis', [
                'KAS_BANK', 'PERSEDIAAN_UNIT', 'PENDAPATAN_UNIT', 'HPP_UNIT',
            ])->nullable();
            $t->enum('posisi', ['D', 'K']);
            $t->string('sumber_variabel', 50)->comment('key di payload, mis. total_bayar');
            $t->boolean('is_optional')->default(false);

            $t->unique(['kode_transaksi', 'urutan']);
            $t->foreign('kode_transaksi')->references('kode_transaksi')->on('master_transaksi');
            $t->foreign('kode_anak')->references('kode_anak')->on('master_coa');
        });

        Schema::create('config_shu', function (Blueprint $t) {
            $t->id('id_config');
            $t->foreignId('id_koperasi')->constrained('koperasi_desa', 'id_koperasi');
            $t->year('tahun');
            $t->string('pos', 50);
            $t->decimal('persentase', 5, 2);
            $t->string('kode_akun', 10);
            $t->unique(['id_koperasi', 'tahun', 'pos']);
            $t->foreign('kode_akun')->references('kode_anak')->on('master_coa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('config_shu');
        Schema::dropIfExists('master_detail_transaksi');
        Schema::dropIfExists('master_transaksi');
        Schema::dropIfExists('master_coa');
    }
};
