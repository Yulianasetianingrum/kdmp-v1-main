<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurnal_header', function (Blueprint $t) {
            $t->id('id_jurnal');
            $t->foreignId('id_koperasi')->constrained('koperasi_desa', 'id_koperasi');
            $t->string('no_jurnal', 30);
            $t->string('nomor_nota', 30)->nullable();
            $t->date('tanggal_jurnal');
            $t->year('periode_tahun');
            $t->unsignedTinyInteger('periode_bulan');
            $t->string('kode_transaksi', 10)->nullable();
            $t->enum('jenis_jurnal', [
                'OTOMATIS', 'MANUAL', 'PENYESUAIAN', 'PEMBALIK', 'SALDO_AWAL', 'PENUTUP',
            ])->default('OTOMATIS');
            $t->string('source_type', 40)->nullable();
            $t->unsignedBigInteger('source_id')->nullable();
            $t->text('keterangan')->nullable();
            $t->decimal('total_debet', 18, 2)->default(0);
            $t->decimal('total_kredit', 18, 2)->default(0);
            $t->enum('status', ['DRAFT', 'POSTED', 'REVERSED'])->default('DRAFT');
            $t->unsignedBigInteger('id_jurnal_asal')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->unsignedBigInteger('posted_by')->nullable();
            $t->dateTime('posted_at')->nullable();
            $t->timestamp('created_at')->nullable();

            $t->unique(['id_koperasi', 'no_jurnal']);
            // Pengaman lapis database terhadap posting ganda
            $t->unique(['id_koperasi', 'source_type', 'source_id', 'jenis_jurnal'], 'jurnal_idempoten');
            $t->index(['id_koperasi', 'periode_tahun', 'periode_bulan', 'status'], 'jurnal_periode');
            $t->foreign('id_jurnal_asal')->references('id_jurnal')->on('jurnal_header');
            $t->foreign('kode_transaksi')->references('kode_transaksi')->on('master_transaksi');
        });

        Schema::create('jurnal_detail', function (Blueprint $t) {
            $t->id('id_detail');
            $t->foreignId('id_jurnal')->constrained('jurnal_header', 'id_jurnal');
            $t->unsignedTinyInteger('urutan');
            $t->string('kode_anak', 10);
            $t->decimal('debet', 18, 2)->default(0);
            $t->decimal('kredit', 18, 2)->default(0);
            $t->string('keterangan')->nullable();
            $t->unsignedBigInteger('id_pihak')->nullable()
              ->comment('untuk akun piutang/hutang, jejak ke buku pembantu');
            $t->index('kode_anak');
            $t->foreign('kode_anak')->references('kode_anak')->on('master_coa');
        });

        // Satu baris tidak boleh punya debet DAN kredit sekaligus
        DB::statement('ALTER TABLE jurnal_detail ADD CONSTRAINT jd_satu_sisi
            CHECK ((debet = 0 AND kredit > 0) OR (debet > 0 AND kredit = 0))');

        /**
         * Ringkasan saldo per akun per bulan. Bisa DIBANGUN ULANG kapan saja
         * dari jurnal, sehingga satu jurnal backdate tidak merusak data permanen.
         *
         * Neraca dan Laba Rugi adalah HASIL FILTER tabel ini berdasarkan
         * master_coa.kelompok, bukan tabel terpisah.
         */
        Schema::create('buku_besar_periode', function (Blueprint $t) {
            $t->foreignId('id_koperasi')->constrained('koperasi_desa', 'id_koperasi');
            $t->year('periode_tahun');
            $t->unsignedTinyInteger('periode_bulan');
            $t->string('kode_anak', 10);
            $t->decimal('saldo_awal_debet', 18, 2)->default(0);
            $t->decimal('saldo_awal_kredit', 18, 2)->default(0);
            $t->decimal('mutasi_debet', 18, 2)->default(0);
            $t->decimal('mutasi_kredit', 18, 2)->default(0);
            $t->decimal('saldo_akhir_debet', 18, 2)->default(0);
            $t->decimal('saldo_akhir_kredit', 18, 2)->default(0);
            $t->dateTime('dihitung_pada')->nullable();
            $t->primary(['id_koperasi', 'periode_tahun', 'periode_bulan', 'kode_anak'], 'bbp_pk');
            $t->foreign('kode_anak')->references('kode_anak')->on('master_coa');
        });

        // Saldo real-time bulan berjalan, memenuhi kebutuhan "dipantau setiap saat"
        // DROP dulu karena migrate:fresh menghapus tabel tapi tidak menghapus view.
        DB::statement('DROP VIEW IF EXISTS v_saldo_berjalan');
        DB::statement("
            CREATE VIEW v_saldo_berjalan AS
            SELECT
                jh.id_koperasi,
                jh.periode_tahun,
                jh.periode_bulan,
                jd.kode_anak,
                c.nama_rekening,
                c.kelompok,
                c.posisi_normal,
                c.is_kontra,
                SUM(jd.debet)  AS mutasi_debet,
                SUM(jd.kredit) AS mutasi_kredit,
                CASE WHEN c.posisi_normal = 'D'
                     THEN SUM(jd.debet) - SUM(jd.kredit)
                     ELSE SUM(jd.kredit) - SUM(jd.debet)
                END AS saldo_normal
            FROM jurnal_header jh
            JOIN jurnal_detail jd ON jd.id_jurnal = jh.id_jurnal
            JOIN master_coa    c  ON c.kode_anak  = jd.kode_anak
            WHERE jh.status = 'POSTED'
            GROUP BY jh.id_koperasi, jh.periode_tahun, jh.periode_bulan,
                     jd.kode_anak, c.nama_rekening, c.kelompok,
                     c.posisi_normal, c.is_kontra
        ");

        // Rekonsiliasi konsinyasi: piutang di pemilik HARUS = hutang di penerima
        DB::statement('DROP VIEW IF EXISTS v_rekonsiliasi_konsinyasi');
        DB::statement("
            CREATE VIEW v_rekonsiliasi_konsinyasi AS
            SELECT
                k.id_kiriman,
                k.kode_kiriman,
                k.id_koperasi_pemilik,
                k.id_koperasi_penerima,
                COALESCE(p.nilai_awal - p.nilai_terbayar, 0) AS sisa_piutang_pemilik,
                COALESCE(h.nilai_awal - h.nilai_terbayar, 0) AS sisa_hutang_penerima,
                COALESCE(p.nilai_awal - p.nilai_terbayar, 0)
              - COALESCE(h.nilai_awal - h.nilai_terbayar, 0) AS selisih
            FROM pengiriman_konsinyasi k
            LEFT JOIN piutang p ON p.sumber_tipe = 'KONSINYASI' AND p.sumber_id = k.id_kiriman
            LEFT JOIN hutang  h ON h.sumber_tipe = 'KONSINYASI' AND h.sumber_id = k.id_kiriman
            WHERE COALESCE(p.nilai_awal - p.nilai_terbayar, 0)
               <> COALESCE(h.nilai_awal - h.nilai_terbayar, 0)
        ");

        // Jurnal POSTED tidak boleh diubah atau dihapus. Koreksi = jurnal pembalik.
        DB::unprepared("
            CREATE TRIGGER trg_jurnal_detail_no_update
            BEFORE UPDATE ON jurnal_detail
            FOR EACH ROW
            BEGIN
                DECLARE v_status VARCHAR(10);
                SELECT status INTO v_status FROM jurnal_header WHERE id_jurnal = OLD.id_jurnal;
                IF v_status <> 'DRAFT' THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Detail jurnal POSTED tidak boleh diubah. Gunakan jurnal pembalik.';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER trg_jurnal_detail_no_delete
            BEFORE DELETE ON jurnal_detail
            FOR EACH ROW
            BEGIN
                DECLARE v_status VARCHAR(10);
                SELECT status INTO v_status FROM jurnal_header WHERE id_jurnal = OLD.id_jurnal;
                IF v_status <> 'DRAFT' THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Detail jurnal POSTED tidak boleh dihapus.';
                END IF;
            END
        ");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_jurnal_detail_no_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_jurnal_detail_no_update');
        DB::statement('DROP VIEW IF EXISTS v_rekonsiliasi_konsinyasi');
        DB::statement('DROP VIEW IF EXISTS v_saldo_berjalan');
        Schema::dropIfExists('buku_besar_periode');
        Schema::dropIfExists('jurnal_detail');
        Schema::dropIfExists('jurnal_header');
    }
};
