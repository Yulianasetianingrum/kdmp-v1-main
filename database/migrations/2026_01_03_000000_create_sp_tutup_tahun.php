<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Stored Procedure: sp_tutup_tahun
 *
 * Tugas tunggal: membuat Jurnal Penutup otomatis untuk menutup tahun buku.
 *
 * Alur kerja (dalam 1 TRANSACTION):
 *   1. Hitung total saldo Pendapatan (kelompok Pendapatan, Non-Operasional) → saldo Kredit normal
 *   2. Hitung total saldo Biaya + HPP (kelompok Biaya, HPP) → saldo Debet normal
 *   3. INSERT jurnal penutup (PENUTUP) tanggal 31 Desember tahun tersebut:
 *        [D] Setiap akun Pendapatan senilai saldo bersihnya → meng-NOL-kan
 *        [K] Setiap akun Biaya+HPP senilai saldo bersihnya → meng-NOL-kan
 *        [K/D] Akun Ikhtisar Laba Rugi (811) → tampung selisih
 *   4. Pindahkan saldo akun 811 ke pos-pos Modal/SHU sesuai config_shu
 *        Setiap pos config_shu: [D] 811, [K] kode_akun SHU (persentase × laba bersih)
 *
 * Akun Ikhtisar Laba Rugi (811):
 *   Ini adalah clearing account yang mempertemukan semua akun nominal.
 *   Setelah jurnal penutup selesai, saldo 811 harus NOL (sudah dibagi ke Modal/SHU).
 *
 * Nomor Jurnal Penutup:
 *   Format: JTP-{tahun}-001 (Jurnal Tutup Penutup)
 *   Format SHU: JTP-{tahun}-002
 *
 * Keamanan:
 *   - EXIT HANDLER FOR SQLEXCEPTION: ROLLBACK + RESIGNAL ke Laravel
 *   - Menggunakan kelompok akun dari master_coa sebagai filter
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS sp_tutup_tahun');

        DB::unprepared("
CREATE PROCEDURE sp_tutup_tahun(
    IN p_id_koperasi  BIGINT UNSIGNED,
    IN p_tahun        SMALLINT UNSIGNED,
    IN p_user_id      BIGINT UNSIGNED
)
BEGIN
    -- =========================================================
    -- DEKLARASI VARIABEL
    -- =========================================================

    -- Loop untuk akun nominal
    DECLARE v_kode_anak     VARCHAR(10) COLLATE utf8mb4_unicode_ci;
    DECLARE v_saldo_bersih  DECIMAL(18,2);
    DECLARE v_posisi_normal ENUM('D','K');
    DECLARE v_done          INT DEFAULT 0;

    -- ID jurnal penutup yang akan dibuat
    DECLARE v_id_jurnal_penutup   BIGINT UNSIGNED DEFAULT 0;
    DECLARE v_id_jurnal_shu       BIGINT UNSIGNED DEFAULT 0;

    -- Nomor baris jurnal (urutan)
    DECLARE v_urutan        INT DEFAULT 1;

    -- Akumulasi untuk akun 811 (ikhtisar)
    DECLARE v_total_pendapatan    DECIMAL(18,2) DEFAULT 0;
    DECLARE v_total_biaya         DECIMAL(18,2) DEFAULT 0;
    DECLARE v_laba_bersih         DECIMAL(18,2) DEFAULT 0;

    -- Loop config_shu
    DECLARE v_persentase    DECIMAL(5,2);
    DECLARE v_kode_akun_shu VARCHAR(10) COLLATE utf8mb4_unicode_ci;
    DECLARE v_nilai_shu     DECIMAL(18,2);
    DECLARE v_total_shu_distribusi DECIMAL(18,2) DEFAULT 0;
    DECLARE v_shu_done      INT DEFAULT 0;

    -- Tanggal 31 Desember tahun yang ditutup
    DECLARE v_tanggal_tutup DATE;

    -- Cursor: Semua akun Pendapatan dengan saldo bersih > 0
    DECLARE cur_pendapatan CURSOR FOR
        SELECT
            bbp.kode_anak,
            (SUM(bbp.saldo_akhir_kredit) - SUM(bbp.saldo_akhir_debet)) AS saldo_bersih
        FROM buku_besar_periode bbp
        JOIN master_coa c ON c.kode_anak = bbp.kode_anak
        WHERE bbp.id_koperasi = p_id_koperasi
          AND bbp.periode_tahun = p_tahun
          AND c.kelompok IN ('Pendapatan', 'Non-Operasional')
          AND c.posisi_normal = 'K'
          AND c.is_transaction = 'T'
        GROUP BY bbp.kode_anak
        HAVING saldo_bersih > 0.009;

    -- Cursor: Semua akun Biaya+HPP dengan saldo bersih > 0
    DECLARE cur_biaya CURSOR FOR
        SELECT
            bbp.kode_anak,
            (SUM(bbp.saldo_akhir_debet) - SUM(bbp.saldo_akhir_kredit)) AS saldo_bersih
        FROM buku_besar_periode bbp
        JOIN master_coa c ON c.kode_anak = bbp.kode_anak
        WHERE bbp.id_koperasi = p_id_koperasi
          AND bbp.periode_tahun = p_tahun
          AND c.kelompok IN ('Biaya', 'HPP')
          AND c.posisi_normal = 'D'
          AND c.is_transaction = 'T'
        GROUP BY bbp.kode_anak
        HAVING saldo_bersih > 0.009;

    -- Cursor: distribusi SHU berdasarkan config
    DECLARE cur_shu CURSOR FOR
        SELECT persentase, kode_akun
        FROM config_shu
        WHERE id_koperasi = p_id_koperasi
          AND tahun = p_tahun
        ORDER BY id_config;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

    -- =========================================================
    -- EXIT HANDLER: Rollback + lempar ulang error ke Laravel
    -- =========================================================
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    -- =========================================================
    -- MULAI TRANSAKSI
    -- =========================================================
    START TRANSACTION;

    -- Tanggal penutup = 31 Desember tahun yang ditutup
    SET v_tanggal_tutup = MAKEDATE(p_tahun, 365);
    -- Jika tahun kabisat, MAKEDATE(tahun, 365) = 30 Des — kita tambah 1 hari lagi
    IF DAY(v_tanggal_tutup) = 30 AND MONTH(v_tanggal_tutup) = 12 THEN
        SET v_tanggal_tutup = DATE_ADD(v_tanggal_tutup, INTERVAL 1 DAY);
    END IF;

    -- =========================================================
    -- LANGKAH 1: Hitung total Pendapatan dan Biaya dari buku besar
    -- =========================================================
    SELECT
        COALESCE(SUM(CASE WHEN c.kelompok IN ('Pendapatan','Non-Operasional') AND c.posisi_normal='K'
                          THEN bbp.saldo_akhir_kredit - bbp.saldo_akhir_debet ELSE 0 END), 0),
        COALESCE(SUM(CASE WHEN c.kelompok IN ('Biaya','HPP') AND c.posisi_normal='D'
                          THEN bbp.saldo_akhir_debet - bbp.saldo_akhir_kredit ELSE 0 END), 0)
    INTO v_total_pendapatan, v_total_biaya
    FROM buku_besar_periode bbp
    JOIN master_coa c ON c.kode_anak = bbp.kode_anak
    WHERE bbp.id_koperasi = p_id_koperasi
      AND bbp.periode_tahun = p_tahun
      AND c.kelompok IN ('Pendapatan','Non-Operasional','Biaya','HPP')
      AND c.is_transaction = 'T';

    SET v_laba_bersih = v_total_pendapatan - v_total_biaya;

    -- =========================================================
    -- LANGKAH 2: INSERT jurnal_header untuk Jurnal Penutup
    -- =========================================================
    INSERT INTO jurnal_header (
        id_koperasi, no_jurnal, nomor_nota, tanggal_jurnal,
        periode_tahun, periode_bulan, kode_transaksi, jenis_jurnal,
        source_type, source_id, keterangan,
        total_debet, total_kredit, status,
        created_by, posted_by, posted_at, created_at
    ) VALUES (
        p_id_koperasi,
        CONCAT('JTP-', p_tahun, '-001'),   -- Nomor jurnal penutup
        NULL,
        v_tanggal_tutup,
        p_tahun,
        12,          -- Periode bulan 12 (Desember)
        NULL,
        'PENUTUP',
        NULL, NULL,
        CONCAT('Jurnal Penutup Tahun Buku ', p_tahun,
               ' | Pendapatan: ', FORMAT(v_total_pendapatan, 0),
               ' | Biaya: ', FORMAT(v_total_biaya, 0),
               ' | Laba Bersih: ', FORMAT(v_laba_bersih, 0)),
        -- total debet = total kredit = total pendapatan + total biaya
        -- karena setiap sisi akan di-mirror ke akun 811
        v_total_pendapatan + v_total_biaya,
        v_total_pendapatan + v_total_biaya,
        'POSTED',
        p_user_id, p_user_id, NOW(), NOW()
    );

    SET v_id_jurnal_penutup = LAST_INSERT_ID();
    SET v_urutan = 1;

    -- =========================================================
    -- LANGKAH 3A: Loop akun Pendapatan → Debet untuk meng-NOL-kan
    -- =========================================================
    SET v_done = 0;
    OPEN cur_pendapatan;

    loop_pendapatan: LOOP
        FETCH cur_pendapatan INTO v_kode_anak, v_saldo_bersih;
        IF v_done THEN LEAVE loop_pendapatan; END IF;

        -- [D] Akun Pendapatan (meng-NOL-kan saldo Kredit)
        INSERT INTO jurnal_detail (id_jurnal, urutan, kode_anak, debet, kredit)
        VALUES (v_id_jurnal_penutup, v_urutan, v_kode_anak, v_saldo_bersih, 0);

        -- Update buku_besar_periode: tambahkan mutasi balik
        INSERT INTO buku_besar_periode (
            id_koperasi, periode_tahun, periode_bulan, kode_anak,
            saldo_awal_debet, saldo_awal_kredit,
            mutasi_debet, mutasi_kredit,
            saldo_akhir_debet, saldo_akhir_kredit,
            dihitung_pada
        )
        SELECT
            p_id_koperasi, p_tahun, 12, v_kode_anak,
            saldo_awal_debet, saldo_awal_kredit,
            0, 0,
            saldo_akhir_debet, saldo_akhir_kredit,
            NOW()
        FROM buku_besar_periode
        WHERE id_koperasi = p_id_koperasi AND periode_tahun = p_tahun AND periode_bulan = 12 AND kode_anak = v_kode_anak
        ON DUPLICATE KEY UPDATE
            mutasi_debet       = mutasi_debet + v_saldo_bersih,
            saldo_akhir_debet  = saldo_awal_debet + mutasi_debet + v_saldo_bersih,
            dihitung_pada      = NOW();

        SET v_urutan = v_urutan + 1;
    END LOOP;

    CLOSE cur_pendapatan;

    -- [K] Akun Ikhtisar 811 untuk menampung total Pendapatan
    INSERT INTO jurnal_detail (id_jurnal, urutan, kode_anak, debet, kredit)
    VALUES (v_id_jurnal_penutup, v_urutan, '811', 0, v_total_pendapatan);
    SET v_urutan = v_urutan + 1;

    -- =========================================================
    -- LANGKAH 3B: Loop akun Biaya+HPP → Kredit untuk meng-NOL-kan
    -- =========================================================
    SET v_done = 0;
    OPEN cur_biaya;

    loop_biaya: LOOP
        FETCH cur_biaya INTO v_kode_anak, v_saldo_bersih;
        IF v_done THEN LEAVE loop_biaya; END IF;

        -- [K] Akun Biaya (meng-NOL-kan saldo Debet)
        INSERT INTO jurnal_detail (id_jurnal, urutan, kode_anak, debet, kredit)
        VALUES (v_id_jurnal_penutup, v_urutan, v_kode_anak, 0, v_saldo_bersih);

        -- Update buku_besar_periode: tambahkan mutasi balik
        INSERT INTO buku_besar_periode (
            id_koperasi, periode_tahun, periode_bulan, kode_anak,
            saldo_awal_debet, saldo_awal_kredit,
            mutasi_debet, mutasi_kredit,
            saldo_akhir_debet, saldo_akhir_kredit,
            dihitung_pada
        )
        SELECT
            p_id_koperasi, p_tahun, 12, v_kode_anak,
            saldo_awal_debet, saldo_awal_kredit,
            0, 0,
            saldo_akhir_debet, saldo_akhir_kredit,
            NOW()
        FROM buku_besar_periode
        WHERE id_koperasi = p_id_koperasi AND periode_tahun = p_tahun AND periode_bulan = 12 AND kode_anak = v_kode_anak
        ON DUPLICATE KEY UPDATE
            mutasi_kredit      = mutasi_kredit + v_saldo_bersih,
            saldo_akhir_kredit = saldo_awal_kredit + mutasi_kredit + v_saldo_bersih,
            dihitung_pada      = NOW();

        SET v_urutan = v_urutan + 1;
    END LOOP;

    CLOSE cur_biaya;

    -- [D] Akun Ikhtisar 811 untuk menampung total Biaya
    INSERT INTO jurnal_detail (id_jurnal, urutan, kode_anak, debet, kredit)
    VALUES (v_id_jurnal_penutup, v_urutan, '811', v_total_biaya, 0);

    -- =========================================================
    -- LANGKAH 4: INSERT jurnal_header untuk distribusi SHU
    -- Saldo 811 = v_laba_bersih → dibagi ke pos-pos Modal/SHU
    -- =========================================================
    INSERT INTO jurnal_header (
        id_koperasi, no_jurnal, nomor_nota, tanggal_jurnal,
        periode_tahun, periode_bulan, kode_transaksi, jenis_jurnal,
        source_type, source_id, keterangan,
        total_debet, total_kredit, status,
        created_by, posted_by, posted_at, created_at
    ) VALUES (
        p_id_koperasi,
        CONCAT('JTP-', p_tahun, '-002'),
        NULL,
        v_tanggal_tutup,
        p_tahun,
        12,
        NULL,
        'PENUTUP',
        NULL, NULL,
        CONCAT('Distribusi SHU Tahun Buku ', p_tahun,
               ' | Laba Bersih: ', FORMAT(v_laba_bersih, 0)),
        ABS(v_laba_bersih),
        ABS(v_laba_bersih),
        'POSTED',
        p_user_id, p_user_id, NOW(), NOW()
    );

    SET v_id_jurnal_shu = LAST_INSERT_ID();
    SET v_urutan = 1;
    SET v_done = 0;

    -- Jika laba bersih positif: [D] 811, [K] setiap akun SHU
    -- Jika laba bersih negatif (rugi): [K] 811, [D] setiap akun modal
    OPEN cur_shu;

    loop_shu: LOOP
        FETCH cur_shu INTO v_persentase, v_kode_akun_shu;
        IF v_shu_done THEN LEAVE loop_shu; END IF;

        SET v_nilai_shu = ROUND(ABS(v_laba_bersih) * v_persentase / 100, 2);
        SET v_total_shu_distribusi = v_total_shu_distribusi + v_nilai_shu;

        IF v_laba_bersih >= 0 THEN
            -- Laba: [D] 811 (tutup ikhtisar), [K] akun SHU/Modal
            INSERT INTO jurnal_detail (id_jurnal, urutan, kode_anak, debet, kredit)
            VALUES
                (v_id_jurnal_shu, v_urutan,     '811',         v_nilai_shu, 0),
                (v_id_jurnal_shu, v_urutan + 1, v_kode_akun_shu, 0, v_nilai_shu);
        ELSE
            -- Rugi: [K] 811 (tutup ikhtisar), [D] akun SHU/Modal (mengurangi modal)
            INSERT INTO jurnal_detail (id_jurnal, urutan, kode_anak, debet, kredit)
            VALUES
                (v_id_jurnal_shu, v_urutan,     '811',         0, v_nilai_shu),
                (v_id_jurnal_shu, v_urutan + 1, v_kode_akun_shu, v_nilai_shu, 0);
        END IF;

        SET v_urutan = v_urutan + 2;
        SET v_done = v_shu_done; -- reset dari CONTINUE HANDLER
    END LOOP;

    CLOSE cur_shu;

    -- =========================================================
    -- COMMIT
    -- =========================================================
    COMMIT;

    -- Return ringkasan ke pemanggil
    SELECT
        v_id_jurnal_penutup AS id_jurnal_penutup,
        v_id_jurnal_shu     AS id_jurnal_shu,
        v_total_pendapatan  AS total_pendapatan,
        v_total_biaya       AS total_biaya,
        v_laba_bersih       AS laba_bersih;

END
        ");
    }

    public function down(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS sp_tutup_tahun');
    }
};
