<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Buku pembantu piutang. Polimorfik supaya bisa menampung piutang dari
         * penjualan biasa maupun dari konsinyasi (hak atas barang yang laku di
         * desa lain tapi uangnya belum disetor).
         */
        Schema::create('piutang', function (Blueprint $t) {
            $t->id('id_piutang');
            $t->foreignId('id_koperasi')->constrained('koperasi_desa', 'id_koperasi');
            $t->foreignId('id_pihak')->constrained('master_pihak', 'id_pihak');
            $t->enum('sumber_tipe', ['PENJUALAN', 'KONSINYASI', 'LAIN']);
            $t->unsignedBigInteger('sumber_id');
            $t->string('kode_akun', 10)->comment('1132 Piutang Dagang / 1135 Piutang Konsinyasi');
            $t->date('tanggal');
            $t->date('tgl_jatuh_tempo');
            $t->decimal('nilai_awal', 18, 2);
            $t->decimal('nilai_terbayar', 18, 2)->default(0);
            $t->enum('status', ['belum_lunas', 'sebagian', 'lunas', 'hapus_buku'])->default('belum_lunas');
            $t->timestamp('created_at')->nullable();
            $t->unique(['sumber_tipe', 'sumber_id']);
            $t->index(['id_koperasi', 'status', 'tgl_jatuh_tempo'], 'piutang_aging');
            $t->foreign('kode_akun')->references('kode_anak')->on('master_coa');
        });

        Schema::create('hutang', function (Blueprint $t) {
            $t->id('id_hutang');
            $t->foreignId('id_koperasi')->constrained('koperasi_desa', 'id_koperasi');
            $t->foreignId('id_pihak')->constrained('master_pihak', 'id_pihak');
            $t->enum('sumber_tipe', ['PEMBELIAN', 'KONSINYASI', 'LAIN']);
            $t->unsignedBigInteger('sumber_id');
            $t->string('kode_akun', 10)->comment('2111 Hutang Dagang / 2117 Hutang Konsinyasi');
            $t->date('tanggal');
            $t->date('tgl_jatuh_tempo');
            $t->decimal('nilai_awal', 18, 2);
            $t->decimal('nilai_terbayar', 18, 2)->default(0);
            $t->enum('status', ['belum_lunas', 'sebagian', 'lunas'])->default('belum_lunas');
            $t->timestamp('created_at')->nullable();
            $t->unique(['sumber_tipe', 'sumber_id']);
            $t->index(['id_koperasi', 'status', 'tgl_jatuh_tempo'], 'hutang_aging');
            $t->foreign('kode_akun')->references('kode_anak')->on('master_coa');
        });

        Schema::create('pelunasan', function (Blueprint $t) {
            $t->id('id_pelunasan');
            $t->foreignId('id_koperasi')->constrained('koperasi_desa', 'id_koperasi');
            $t->string('kode_pelunasan', 30);
            $t->enum('jenis', ['terima_piutang', 'bayar_hutang', 'offset']);
            $t->foreignId('id_pihak')->constrained('master_pihak', 'id_pihak');
            $t->date('tanggal');
            $t->foreignId('id_kas_bank')->nullable()->constrained('master_kas_bank', 'id_kas_bank');
            $t->decimal('total_nilai', 18, 2);
            $t->enum('status_posting', ['F', 'T'])->default('F');
            $t->unsignedBigInteger('id_jurnal')->nullable();
            $t->text('catatan')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->unique(['id_koperasi', 'kode_pelunasan']);
        });

        Schema::create('pelunasan_detail', function (Blueprint $t) {
            $t->id('id_detail');
            $t->foreignId('id_pelunasan')->constrained('pelunasan', 'id_pelunasan');
            $t->foreignId('id_piutang')->nullable()->constrained('piutang', 'id_piutang');
            $t->foreignId('id_hutang')->nullable()->constrained('hutang', 'id_hutang');
            $t->decimal('nilai_bayar', 18, 2);
        });

        Schema::create('kas_transaksi', function (Blueprint $t) {
            $t->id('id_kas_trx');
            $t->foreignId('id_koperasi')->constrained('koperasi_desa', 'id_koperasi');
            $t->string('kode_trx', 30);
            $t->date('tanggal');
            $t->enum('jenis', ['masuk', 'keluar', 'mutasi_antar_kas']);
            $t->foreignId('id_kas_bank')->constrained('master_kas_bank', 'id_kas_bank');
            $t->unsignedBigInteger('id_kas_bank_tujuan')->nullable();
            $t->string('kode_akun_lawan', 10)->nullable();
            $t->decimal('nilai', 18, 2);
            $t->text('keterangan')->nullable();
            $t->enum('status_posting', ['F', 'T'])->default('F');
            $t->unsignedBigInteger('id_jurnal')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->unique(['id_koperasi', 'kode_trx']);
            $t->foreign('kode_akun_lawan')->references('kode_anak')->on('master_coa');
        });

        /**
         * Simpanan pokok & wajib -> MODAL (akun 311/312)
         * Simpanan sukarela      -> KEWAJIBAN (akun 2115), karena bisa ditarik
         */
        Schema::create('simpanan_anggota', function (Blueprint $t) {
            $t->id('id_simpanan');
            $t->foreignId('id_koperasi')->constrained('koperasi_desa', 'id_koperasi');
            $t->foreignId('id_pihak')->constrained('master_pihak', 'id_pihak');
            $t->enum('jenis', ['pokok', 'wajib', 'sukarela']);
            $t->date('tanggal');
            $t->enum('arah', ['setor', 'tarik']);
            $t->decimal('nilai', 18, 2);
            $t->foreignId('id_kas_bank')->constrained('master_kas_bank', 'id_kas_bank');
            $t->enum('status_posting', ['F', 'T'])->default('F');
            $t->unsignedBigInteger('id_jurnal')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->index(['id_koperasi', 'id_pihak', 'jenis'], 'simpanan_lookup');
        });
    }

    public function down(): void
    {
        foreach ([
            'simpanan_anggota', 'kas_transaksi', 'pelunasan_detail',
            'pelunasan', 'hutang', 'piutang',
        ] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
