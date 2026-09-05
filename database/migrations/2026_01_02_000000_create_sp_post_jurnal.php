<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Stored Procedure: sp_post_jurnal
 *
 * Tugas tunggal: terima satu JSON dari Laravel, lakukan INSERT + UPDATE
 * dalam satu transaksi database yang tidak bisa "setengah jadi".
 *
 * Parameter masuk:
 *   p_id_koperasi  — ID koperasi aktif (dari app('koperasi_aktif'))
 *   p_user_id      — ID pengguna yang memposting (untuk audit trail)
 *   p_json         — JSON lengkap berisi header + array baris jurnal
 *
 * Yang dilakukan di dalam (berurutan, dalam 1 TRANSACTION):
 *   1. Hitung total_debet dan total_kredit dari array baris
 *   2. INSERT satu baris ke jurnal_header (status langsung = POSTED)
 *   3. LOOP setiap baris di array → INSERT ke jurnal_detail
 *   4. LOOP setiap baris lagi   → UPSERT ke buku_besar_periode
 *   5. COMMIT — atau ROLLBACK semua jika ada error di langkah manapun
 *   6. SELECT id_jurnal yang baru dibuat sebagai return value
 *
 * Dipanggil dari PHP dengan:
 *   DB::statement('CALL sp_post_jurnal(?, ?, ?)', [$idKoperasi, $userId, $json]);
 */
return new class extends Migration
{
    public function up(): void
    {
        // Hapus dulu jika sudah ada (aman untuk migrate:fresh)
        DB::unprepared('DROP PROCEDURE IF EXISTS sp_post_jurnal');

        // DELIMITER tidak dipakai di PHP — Laravel pakai unprepared() langsung
        DB::unprepared("
CREATE PROCEDURE sp_post_jurnal(
    IN p_id_koperasi  BIGINT UNSIGNED,
    IN p_user_id      BIGINT UNSIGNED,
    IN p_json         JSON
)
BEGIN
    -- =========================================================
    -- DEKLARASI VARIABEL
    -- Semua DECLARE harus di atas, sebelum logika apapun.
    -- =========================================================

    -- Menyimpan ID jurnal yang baru di-INSERT
    DECLARE v_id_jurnal     BIGINT UNSIGNED DEFAULT 0;

    -- Jumlah baris di array JSON $.baris
    DECLARE v_n_baris       INT DEFAULT 0;

    -- Counter loop
    DECLARE v_i             INT DEFAULT 0;

    -- Variabel per-baris jurnal_detail
    DECLARE v_kode_anak     VARCHAR(10) COLLATE utf8mb4_unicode_ci;
    DECLARE v_debet         DECIMAL(18,2) DEFAULT 0;
    DECLARE v_kredit        DECIMAL(18,2) DEFAULT 0;
    DECLARE v_id_pihak      BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_ket_baris     VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL;

    -- Untuk kalkulasi total header
    DECLARE v_total_debet   DECIMAL(18,2) DEFAULT 0;
    DECLARE v_total_kredit  DECIMAL(18,2) DEFAULT 0;

    -- Untuk UPSERT buku_besar_periode
    DECLARE v_saldo_awal_d  DECIMAL(18,2) DEFAULT 0;
    DECLARE v_saldo_awal_k  DECIMAL(18,2) DEFAULT 0;

    -- Shortcut periode dari JSON
    DECLARE v_tahun         SMALLINT UNSIGNED;
    DECLARE v_bulan         TINYINT  UNSIGNED;

    -- =========================================================
    -- EXIT HANDLER: jika ada error SQL apapun → ROLLBACK
    -- RESIGNAL: lempar ulang error ke pemanggil (Laravel)
    -- sehingga Laravel bisa catch exception-nya
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

    -- Ambil periode dari JSON sekali saja (dipakai berulang)
    SET v_tahun = JSON_EXTRACT(p_json, '$.periode_tahun');
    SET v_bulan = JSON_EXTRACT(p_json, '$.periode_bulan');

    -- =========================================================
    -- LANGKAH 1: Hitung total_debet & total_kredit
    -- Loop JSON array $.baris untuk menjumlahkan semua nilai
    -- =========================================================
    SET v_n_baris = JSON_LENGTH(JSON_EXTRACT(p_json, '$.baris'));
    SET v_i       = 0;

    WHILE v_i < v_n_baris DO
        SET v_total_debet = v_total_debet +
            IFNULL(JSON_EXTRACT(p_json, CONCAT('$.baris[', v_i, '].debet')), 0);
        SET v_total_kredit = v_total_kredit +
            IFNULL(JSON_EXTRACT(p_json, CONCAT('$.baris[', v_i, '].kredit')), 0);
        SET v_i = v_i + 1;
    END WHILE;

    -- =========================================================
    -- LANGKAH 2: INSERT jurnal_header
    -- NULLIF(...,'null'): konversi string 'null' dari JSON_UNQUOTE
    --                      menjadi SQL NULL yang sesungguhnya
    -- =========================================================
    INSERT INTO jurnal_header (
        id_koperasi,
        no_jurnal,
        nomor_nota,
        tanggal_jurnal,
        periode_tahun,
        periode_bulan,
        kode_transaksi,
        jenis_jurnal,
        source_type,
        source_id,
        keterangan,
        total_debet,
        total_kredit,
        status,
        created_by,
        posted_by,
        posted_at,
        created_at
    ) VALUES (
        p_id_koperasi,
        JSON_UNQUOTE(JSON_EXTRACT(p_json, '$.no_jurnal')),
        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(p_json, '$.nomor_nota')),    'null'),
        JSON_UNQUOTE(JSON_EXTRACT(p_json, '$.tanggal_jurnal')),
        v_tahun,
        v_bulan,
        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(p_json, '$.kode_transaksi')), 'null'),
        JSON_UNQUOTE(JSON_EXTRACT(p_json, '$.jenis_jurnal')),
        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(p_json, '$.source_type')),    'null'),
        NULLIF(JSON_EXTRACT(p_json,        '$.source_id'),              'null'),
        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(p_json, '$.keterangan')),     'null'),
        v_total_debet,
        v_total_kredit,
        'POSTED',       -- langsung POSTED, bukan DRAFT
        p_user_id,      -- created_by
        p_user_id,      -- posted_by
        NOW(),          -- posted_at
        NOW()           -- created_at
    );

    -- Simpan ID baris yang baru saja diinsert
    SET v_id_jurnal = LAST_INSERT_ID();

    -- =========================================================
    -- LANGKAH 3 + 4: Loop baris jurnal
    -- Setiap baris: INSERT jurnal_detail + UPSERT buku_besar_periode
    -- =========================================================
    SET v_i = 0;

    WHILE v_i < v_n_baris DO

        -- Ekstrak nilai baris saat ini dari JSON
        SET v_kode_anak = JSON_UNQUOTE(JSON_EXTRACT(p_json,
                            CONCAT('$.baris[', v_i, '].kode_anak')));
        SET v_debet     = IFNULL(JSON_EXTRACT(p_json,
                            CONCAT('$.baris[', v_i, '].debet')), 0);
        SET v_kredit    = IFNULL(JSON_EXTRACT(p_json,
                            CONCAT('$.baris[', v_i, '].kredit')), 0);
        SET v_id_pihak  = NULLIF(JSON_EXTRACT(p_json,
                            CONCAT('$.baris[', v_i, '].id_pihak')), 'null');
        SET v_ket_baris = NULLIF(JSON_UNQUOTE(JSON_EXTRACT(p_json,
                            CONCAT('$.baris[', v_i, '].keterangan'))), 'null');

        -- --- INSERT jurnal_detail ---
        INSERT INTO jurnal_detail (
            id_jurnal, urutan, kode_anak, debet, kredit, id_pihak, keterangan
        ) VALUES (
            v_id_jurnal,
            v_i + 1,        -- urutan mulai dari 1
            v_kode_anak,
            v_debet,
            v_kredit,
            v_id_pihak,
            v_ket_baris
        );

        -- --- UPSERT buku_besar_periode ---
        -- Langkah 4a: ambil saldo_akhir bulan SEBELUMNYA sebagai saldo_awal
        -- Jika bulan pertama (1), ambil dari bulan 12 tahun sebelumnya.
        -- Jika tidak ada data bulan lalu → default 0 (akun baru).
        SET v_saldo_awal_d = 0;
        SET v_saldo_awal_k = 0;

        SELECT IFNULL(saldo_akhir_debet,  0),
               IFNULL(saldo_akhir_kredit, 0)
        INTO   v_saldo_awal_d, v_saldo_awal_k
        FROM   buku_besar_periode
        WHERE  id_koperasi   = p_id_koperasi
          AND  periode_tahun = IF(v_bulan = 1, v_tahun - 1, v_tahun)
          AND  periode_bulan = IF(v_bulan = 1, 12, v_bulan - 1)
          AND  kode_anak     = v_kode_anak
        LIMIT 1;

        -- Langkah 4b: UPSERT
        -- INSERT baru jika belum ada, UPDATE jika sudah ada.
        -- ON DUPLICATE KEY pakai composite PK (id_koperasi, tahun, bulan, kode_anak).
        --
        -- saldo_akhir_debet  = kumulatif sisi debet (saldo_awal_D + semua mutasi D)
        -- saldo_akhir_kredit = kumulatif sisi kredit (saldo_awal_K + semua mutasi K)
        -- Net saldo dihitung saat query laporan berdasarkan posisi_normal akun.
        INSERT INTO buku_besar_periode (
            id_koperasi,
            periode_tahun,
            periode_bulan,
            kode_anak,
            saldo_awal_debet,
            saldo_awal_kredit,
            mutasi_debet,
            mutasi_kredit,
            saldo_akhir_debet,
            saldo_akhir_kredit,
            dihitung_pada
        ) VALUES (
            p_id_koperasi,
            v_tahun,
            v_bulan,
            v_kode_anak,
            v_saldo_awal_d,
            v_saldo_awal_k,
            v_debet,
            v_kredit,
            v_saldo_awal_d + v_debet,
            v_saldo_awal_k + v_kredit,
            NOW()
        )
        ON DUPLICATE KEY UPDATE
            mutasi_debet       = mutasi_debet  + v_debet,
            mutasi_kredit      = mutasi_kredit + v_kredit,
            saldo_akhir_debet  = saldo_awal_debet  + mutasi_debet  + v_debet,
            saldo_akhir_kredit = saldo_awal_kredit + mutasi_kredit + v_kredit,
            dihitung_pada      = NOW();

        SET v_i = v_i + 1;
    END WHILE;

    -- =========================================================
    -- COMMIT — semua berhasil, simpan permanen
    -- =========================================================
    COMMIT;

    -- =========================================================
    -- RETURN: kembalikan id_jurnal ke pemanggil (JurnalService)
    -- Diakses di PHP dengan: DB::select('CALL sp_post_jurnal(...)')[0]->id_jurnal
    -- =========================================================
    SELECT v_id_jurnal AS id_jurnal;

END
        ");
    }

    public function down(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS sp_post_jurnal');
    }
};
