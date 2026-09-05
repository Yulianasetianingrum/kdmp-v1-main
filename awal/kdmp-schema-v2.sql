-- ============================================================
-- STRUKTUR DATABASE KDMP — VERSI 2 (SELARAS)
-- Koperasi Desa Merah Putih
-- ============================================================
-- Basis keputusan:
--   * Metode persediaan   : Moving Average (perpetual)
--   * Status PKP          : TIDAK  -> seluruh akun & kolom PPN dihapus
--   * Gudang              : satu per desa
--   * Antar desa          : entitas terpisah -> penjualan/pembelian, bukan transfer
--   * Keanggotaan warga   : tidak wajib -> tetap dipisah anggota/non-anggota
--   * Alur beli           : 2 langkah (GRN -> Hutang Dagang)
--   * Penjualan kredit    : hanya antar desa; ke warga selalu tunai/transfer
--   * Tahun buku          : Jan-Des, tutup buku boleh sampai Maret tahun N+1
--   * Arsitektur          : SATU database, multi-tenant per koperasi desa
-- ============================================================
-- Target: MySQL 8.0+ / MariaDB 10.5+
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- BAGIAN 0 — TENANT & PERIODE          [BARU — WAJIB]
-- ============================================================

-- Entitas pembukuan. Setiap koperasi desa = satu tenant = satu buku besar.
CREATE TABLE `koperasi_desa` (
  `id_koperasi`     bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode_koperasi`   varchar(20)  NOT NULL,
  `nama_koperasi`   varchar(150) NOT NULL,
  `id_wilayah`      bigint(20) UNSIGNED NOT NULL,   -- tingkat = 'desa'
  `badan_hukum_no`  varchar(50)  DEFAULT NULL,
  `tgl_berdiri`     date         DEFAULT NULL,
  `tahun_buku_awal` year         NOT NULL,
  `is_active`       tinyint(1)   NOT NULL DEFAULT 1,
  `created_at`      timestamp NULL DEFAULT NULL,
  `updated_at`      timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_koperasi`),
  UNIQUE KEY `koperasi_kode_unique` (`kode_koperasi`),
  UNIQUE KEY `koperasi_wilayah_unique` (`id_wilayah`),
  CONSTRAINT `koperasi_wilayah_fk` FOREIGN KEY (`id_wilayah`) REFERENCES `wilayah`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kontrol buka/tutup periode. Tanpa ini tidak ada penguncian bulan.
-- Bulan 13 = periode penyesuaian (dipakai untuk tutup buku Jan-Mar tahun N+1).
CREATE TABLE `periode_akuntansi` (
  `id_periode`   bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_koperasi`  bigint(20) UNSIGNED NOT NULL,
  `tahun`        year        NOT NULL,
  `bulan`        tinyint(2) UNSIGNED NOT NULL COMMENT '1-12 operasional, 13 = periode penyesuaian/tutup buku',
  `status`       enum('OPEN','CLOSED','LOCKED') NOT NULL DEFAULT 'OPEN',
  `tgl_tutup`    datetime DEFAULT NULL,
  `ditutup_oleh` bigint(20) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id_periode`),
  UNIQUE KEY `periode_unik` (`id_koperasi`,`tahun`,`bulan`),
  CONSTRAINT `periode_koperasi_fk` FOREIGN KEY (`id_koperasi`) REFERENCES `koperasi_desa`(`id_koperasi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Konfigurasi per koperasi (menggantikan hardcode di aplikasi)
CREATE TABLE `konfigurasi` (
  `id_koperasi` bigint(20) UNSIGNED NOT NULL,
  `kunci`       varchar(50) NOT NULL,
  `nilai`       varchar(255) NOT NULL,
  `keterangan`  varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_koperasi`,`kunci`),
  CONSTRAINT `konfigurasi_koperasi_fk` FOREIGN KEY (`id_koperasi`) REFERENCES `koperasi_desa`(`id_koperasi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Persentase pembagian SHU. WAJIB DIISI sebelum tutup buku tahunan.
CREATE TABLE `config_shu` (
  `id_config`   bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_koperasi` bigint(20) UNSIGNED NOT NULL,
  `tahun`       year NOT NULL,
  `pos`         varchar(50) NOT NULL COMMENT 'Cadangan Umum, Jasa Anggota, Dana Pengurus, Dana Pendidikan, Dana Sosial',
  `persentase`  decimal(5,2) NOT NULL,
  `kode_akun`   varchar(10) NOT NULL COMMENT 'akun tujuan penampung',
  PRIMARY KEY (`id_config`),
  UNIQUE KEY `config_shu_unik` (`id_koperasi`,`tahun`,`pos`),
  CONSTRAINT `config_shu_koperasi_fk` FOREIGN KEY (`id_koperasi`) REFERENCES `koperasi_desa`(`id_koperasi`),
  CONSTRAINT `config_shu_akun_fk` FOREIGN KEY (`kode_akun`) REFERENCES `master_coa`(`kode_anak`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- CATATAN: total persentase seluruh pos untuk satu (id_koperasi, tahun)
-- harus = 100.00. Validasi ini tidak bisa dipaksakan di level kolom;
-- lakukan di sp_tutup_buku_tahunan sebelum jurnal penutup dibuat.


-- ============================================================
-- BAGIAN A — MASTER DATA
-- ============================================================

-- DIPERTAHANKAN dari rancangan Anda (sudah benar: adjacency list rekursif)
CREATE TABLE `wilayah` (
  `id`         bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id`  bigint(20) UNSIGNED DEFAULT NULL,
  `tingkat`    enum('prov','kab','kec','desa','dusun','rw','rt') NOT NULL,
  `nama`       varchar(255) NOT NULL,
  `kode_bps`   varchar(255) DEFAULT NULL,
  `lat`        decimal(10,7) DEFAULT NULL,
  `lng`        decimal(10,7) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wilayah_parent_idx` (`parent_id`),
  CONSTRAINT `wilayah_parent_fk` FOREIGN KEY (`parent_id`) REFERENCES `wilayah`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Konsep survei. Global, dipakai bersama semua desa.
CREATE TABLE `komoditas` (
  `id`         bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kategori`   varchar(30) NOT NULL COMMENT 'pertanian, perikanan, perkebunan, sembako, apotek',
  `nama`       varchar(255) NOT NULL,
  `alias_json` longtext CHECK (json_valid(`alias_json`)),
  `is_active`  tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `satuan` (
  `id`          bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode_satuan` varchar(20) NOT NULL,
  `alias_json`  longtext CHECK (json_valid(`alias_json`)),
  `is_active`   tinyint(1) NOT NULL DEFAULT 1,
  `created_at`  timestamp NULL DEFAULT NULL,
  `updated_at`  timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `satuan_kode_unique` (`kode_satuan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `master_unit_usaha` (
  `id_unit_usaha`   bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode_unit_usaha` varchar(20) NOT NULL,
  `nama_unit_usaha` varchar(100) NOT NULL,
  `kode_akun_persediaan` varchar(10) NOT NULL,
  `kode_akun_pendapatan_anggota`     varchar(10) NOT NULL,
  `kode_akun_pendapatan_non_anggota` varchar(10) NOT NULL,
  `kode_akun_hpp`   varchar(10) NOT NULL,
  `keterangan`      text DEFAULT NULL,
  `created_at`      timestamp NULL DEFAULT NULL,
  `updated_at`      timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_unit_usaha`),
  UNIQUE KEY `unit_usaha_kode_unique` (`kode_unit_usaha`),
  CONSTRAINT `uu_akun_persediaan_fk` FOREIGN KEY (`kode_akun_persediaan`) REFERENCES `master_coa`(`kode_anak`),
  CONSTRAINT `uu_akun_pend_agt_fk`   FOREIGN KEY (`kode_akun_pendapatan_anggota`) REFERENCES `master_coa`(`kode_anak`),
  CONSTRAINT `uu_akun_pend_nagt_fk`  FOREIGN KEY (`kode_akun_pendapatan_non_anggota`) REFERENCES `master_coa`(`kode_anak`),
  CONSTRAINT `uu_akun_hpp_fk`        FOREIGN KEY (`kode_akun_hpp`) REFERENCES `master_coa`(`kode_anak`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- PERUBAHAN: pemetaan akun dipindah ke master_unit_usaha.
-- Dulu Anda menaruh id_unit_usaha di master_detail_transaksi dan menduplikasi
-- baris mapping per unit. Setiap unit usaha baru berarti menambah 2 baris di
-- SETIAP kode transaksi. Sekarang cukup 1 baris master_unit_usaha.

CREATE TABLE `master_barang` (
  `id_barang`          bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode_barang`        varchar(30) NOT NULL,
  `id_komoditas`       bigint(20) UNSIGNED NOT NULL,
  `id_unit_usaha`      bigint(20) UNSIGNED NOT NULL,
  `nama_barang`        varchar(150) NOT NULL,
  `id_satuan_dasar`    bigint(20) UNSIGNED NOT NULL,
  `is_active`          tinyint(1) NOT NULL DEFAULT 1,
  `created_at`         timestamp NULL DEFAULT NULL,
  `updated_at`         timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_barang`),
  UNIQUE KEY `barang_kode_unique` (`kode_barang`),
  KEY `barang_komoditas_idx` (`id_komoditas`),
  CONSTRAINT `barang_komoditas_fk`  FOREIGN KEY (`id_komoditas`)    REFERENCES `komoditas`(`id`),
  CONSTRAINT `barang_unit_usaha_fk` FOREIGN KEY (`id_unit_usaha`)   REFERENCES `master_unit_usaha`(`id_unit_usaha`),
  CONSTRAINT `barang_satuan_fk`     FOREIGN KEY (`id_satuan_dasar`) REFERENCES `satuan`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- PERUBAHAN: UNIQUE(id_komoditas) DIHAPUS.
-- Kunci unik itu memaksa 1 komoditas = 1 barang selamanya. Beras tidak bisa
-- punya varian (Beras Premium 5kg, Beras Medium curah), dan komoditas yang
-- sama tidak bisa dijual di dua unit usaha. Relasi yang benar 1 komoditas : N barang.
-- PERUBAHAN: nilai_konversi & satuan_dasar (varchar) dipindah ke tabel konversi_satuan.
-- PERUBAHAN: stok_minimum, harga_beli_standar, harga_jual_standar dipindah ke
-- barang_per_koperasi, karena tiap desa punya limit stok & harga jual sendiri.

CREATE TABLE `konversi_satuan` (
  `id_konversi`  bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_barang`    bigint(20) UNSIGNED NOT NULL,
  `id_satuan`    bigint(20) UNSIGNED NOT NULL,
  `faktor_ke_dasar` decimal(18,6) NOT NULL COMMENT '1 satuan ini = berapa satuan dasar. Contoh karung=50 (kg)',
  `is_default_beli` tinyint(1) NOT NULL DEFAULT 0,
  `is_default_jual` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_konversi`),
  UNIQUE KEY `konversi_unik` (`id_barang`,`id_satuan`),
  CONSTRAINT `konversi_barang_fk` FOREIGN KEY (`id_barang`) REFERENCES `master_barang`(`id_barang`),
  CONSTRAINT `konversi_satuan_fk` FOREIGN KEY (`id_satuan`) REFERENCES `satuan`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Parameter barang yang berbeda-beda antar desa
CREATE TABLE `barang_per_koperasi` (
  `id_koperasi`        bigint(20) UNSIGNED NOT NULL,
  `id_barang`          bigint(20) UNSIGNED NOT NULL,
  `stok_minimum`       decimal(18,4) NOT NULL DEFAULT 0,
  `stok_maksimum`      decimal(18,4) NOT NULL DEFAULT 0,
  `harga_jual_standar` decimal(18,2) NOT NULL DEFAULT 0,
  `is_dijual`          tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_koperasi`,`id_barang`),
  CONSTRAINT `bpk_koperasi_fk` FOREIGN KEY (`id_koperasi`) REFERENCES `koperasi_desa`(`id_koperasi`),
  CONSTRAINT `bpk_barang_fk`   FOREIGN KEY (`id_barang`)   REFERENCES `master_barang`(`id_barang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Mitra dagang. Sekarang bertenant: supplier Desa A bukan supplier Desa B.
CREATE TABLE `master_pihak` (
  `id_pihak`      bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_koperasi`   bigint(20) UNSIGNED NOT NULL,
  `jenis_pihak`   enum('supplier','petani','warga','koperasi_desa_lain') NOT NULL,
  `id_koperasi_mitra` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'diisi jika jenis_pihak = koperasi_desa_lain',
  `nama`          varchar(255) NOT NULL,
  `nik`           varchar(20) DEFAULT NULL,
  `is_anggota`    tinyint(1) NOT NULL DEFAULT 0 COMMENT 'penentu akun pendapatan & hak SHU',
  `no_anggota`    varchar(30) DEFAULT NULL,
  `tgl_jadi_anggota` date DEFAULT NULL,
  `alamat`        text DEFAULT NULL,
  `no_hp`         varchar(30) DEFAULT NULL,
  `id_wilayah`    bigint(20) UNSIGNED DEFAULT NULL,
  `kualitas_rating`    decimal(3,2) DEFAULT NULL,
  `estimasi_pengiriman` varchar(50) DEFAULT NULL,
  `is_active`     tinyint(1) NOT NULL DEFAULT 1,
  `created_at`    timestamp NULL DEFAULT NULL,
  `updated_at`    timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_pihak`),
  UNIQUE KEY `pihak_no_anggota_unik` (`id_koperasi`,`no_anggota`),
  KEY `pihak_koperasi_idx` (`id_koperasi`,`jenis_pihak`),
  CONSTRAINT `pihak_koperasi_fk`  FOREIGN KEY (`id_koperasi`) REFERENCES `koperasi_desa`(`id_koperasi`),
  CONSTRAINT `pihak_mitra_fk`     FOREIGN KEY (`id_koperasi_mitra`) REFERENCES `koperasi_desa`(`id_koperasi`),
  CONSTRAINT `pihak_wilayah_fk`   FOREIGN KEY (`id_wilayah`) REFERENCES `wilayah`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- PERUBAHAN: enum jenis_pihak diubah. 'pelanggan_warga' & 'pelanggan_desa'
-- diganti 'warga' + 'koperasi_desa_lain', dan 'petani' dipisah dari 'supplier'
-- karena alur beli dari petani berbeda (tunai langsung, tanpa PO).
-- PERUBAHAN: is_anggota DITAMBAHKAN. Anda bilang keanggotaan tidak wajib —
-- justru karena itu flag ini wajib ada, untuk memisah pendapatan anggota
-- vs non-anggota yang jadi dasar pembagian jasa anggota di SHU.

-- Rekening kas & bank fisik milik tiap desa. Ini yang membuat JTFW/JTFD
-- bisa di-seed: kasir memilih id_kas_bank, sistem ambil kode akunnya.
CREATE TABLE `master_kas_bank` (
  `id_kas_bank`  bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_koperasi`  bigint(20) UNSIGNED NOT NULL,
  `jenis`        enum('kas','bank') NOT NULL,
  `nama`         varchar(100) NOT NULL,
  `no_rekening`  varchar(50) DEFAULT NULL,
  `kode_akun`    varchar(10) NOT NULL,
  `is_default`   tinyint(1) NOT NULL DEFAULT 0,
  `is_active`    tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_kas_bank`),
  KEY `kasbank_koperasi_idx` (`id_koperasi`),
  CONSTRAINT `kasbank_koperasi_fk` FOREIGN KEY (`id_koperasi`) REFERENCES `koperasi_desa`(`id_koperasi`),
  CONSTRAINT `kasbank_akun_fk`     FOREIGN KEY (`kode_akun`)   REFERENCES `master_coa`(`kode_anak`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Gudang. Satu per desa sesuai keputusan Anda, tapi tabel tetap dibuat
-- agar penambahan gudang kedua tidak menuntut migrasi.
CREATE TABLE `gudang` (
  `id_gudang`   bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_koperasi` bigint(20) UNSIGNED NOT NULL,
  `kode_gudang` varchar(20) NOT NULL,
  `nama_gudang` varchar(100) NOT NULL,
  `alamat`      text DEFAULT NULL,
  `is_active`   tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_gudang`),
  UNIQUE KEY `gudang_kode_unik` (`id_koperasi`,`kode_gudang`),
  CONSTRAINT `gudang_koperasi_fk` FOREIGN KEY (`id_koperasi`) REFERENCES `koperasi_desa`(`id_koperasi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- BAGIAN B — SURVEY
-- ============================================================
-- Struktur survei dinamis Anda (modul_survei / pertanyaan / jawaban /
-- rekaman_suara) DIPERTAHANKAN. Rancangannya sudah tepat untuk survei
-- berbasis suara via URL yang dibagikan, dan tidak butuh sinkronisasi offline.

CREATE TABLE `pengguna` (
  `id`            bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_koperasi`   bigint(20) UNSIGNED DEFAULT NULL COMMENT 'NULL = pengguna tingkat pusat/pengawas',
  `nama`          varchar(255) NOT NULL,
  `email`         varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password`      varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `is_active`     tinyint(1) NOT NULL DEFAULT 1,
  `created_at`    timestamp NULL DEFAULT NULL,
  `updated_at`    timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pengguna_email_unique` (`email`),
  KEY `pengguna_koperasi_idx` (`id_koperasi`),
  CONSTRAINT `pengguna_koperasi_fk` FOREIGN KEY (`id_koperasi`) REFERENCES `koperasi_desa`(`id_koperasi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `peran` (
  `id_peran` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode`     varchar(30) NOT NULL,
  `nama`     varchar(100) NOT NULL,
  PRIMARY KEY (`id_peran`),
  UNIQUE KEY `peran_kode_unique` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `pengguna_peran` (
  `id_pengguna` bigint(20) UNSIGNED NOT NULL,
  `id_peran`    bigint(20) UNSIGNED NOT NULL,
  PRIMARY KEY (`id_pengguna`,`id_peran`),
  CONSTRAINT `pp_pengguna_fk` FOREIGN KEY (`id_pengguna`) REFERENCES `pengguna`(`id`),
  CONSTRAINT `pp_peran_fk`    FOREIGN KEY (`id_peran`)    REFERENCES `peran`(`id_peran`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- PERUBAHAN: kolom `role` varchar diganti tabel peran + pivot.
-- Alasan: satu pengurus desa sering merangkap (kasir + admin gudang),
-- yang mustahil diwakili satu kolom varchar.

CREATE TABLE `modul_survei` (
  `id`         bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode`       varchar(255) NOT NULL,
  `nama`       varchar(255) NOT NULL,
  `versi`      varchar(255) NOT NULL DEFAULT 'v1',
  `is_active`  tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `pertanyaan` (
  `id`                   bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_modul`             bigint(20) UNSIGNED NOT NULL,
  `kode_pertanyaan`      varchar(255) NOT NULL,
  `teks_pertanyaan`      text NOT NULL,
  `tipe_jawaban`         enum('angka','teks','pilihan','pilihan_ganda','json','validasi_jumlah') NOT NULL,
  `satuan`               varchar(20) DEFAULT NULL,
  `wajib_diisi`          tinyint(1) NOT NULL DEFAULT 0,
  `aturan_validasi_json` longtext CHECK (json_valid(`aturan_validasi_json`)),
  `urutan`               smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `is_active`            tinyint(1) NOT NULL DEFAULT 1,
  `created_at`           timestamp NULL DEFAULT NULL,
  `updated_at`           timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `pertanyaan_modul_fk` FOREIGN KEY (`id_modul`) REFERENCES `modul_survei`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `sesi_survei` (
  `id`               bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_petugas`       bigint(20) UNSIGNED NOT NULL,
  `id_wilayah`       bigint(20) UNSIGNED NOT NULL,
  `tahun`            year NOT NULL,
  `bulan`            tinyint(2) UNSIGNED DEFAULT NULL,
  `tanggal_survei`   date NOT NULL,
  `status`           enum('draft','terkirim','disetujui','ditolak') NOT NULL DEFAULT 'draft',
  `catatan`          text DEFAULT NULL,
  `id_perangkat`     varchar(255) DEFAULT NULL,
  `uuid_sesi_klien`  varchar(255) DEFAULT NULL,
  `token_publik`     varchar(64) DEFAULT NULL COMMENT 'token URL yang dibagikan ke pengurus desa',
  `token_kadaluarsa` datetime DEFAULT NULL,
  `created_at`       timestamp NULL DEFAULT NULL,
  `updated_at`       timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sesi_token_unique` (`token_publik`),
  CONSTRAINT `sesi_petugas_fk` FOREIGN KEY (`id_petugas`) REFERENCES `pengguna`(`id`),
  CONSTRAINT `sesi_wilayah_fk` FOREIGN KEY (`id_wilayah`) REFERENCES `wilayah`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- PERUBAHAN: token_publik + token_kadaluarsa DITAMBAHKAN, sesuai keputusan
-- Anda bahwa URL survei dibagikan ke pengurus tiap desa. Tanpa token bertanggal
-- kedaluwarsa, satu URL yang bocor bisa dipakai mengisi survei kapan saja.

CREATE TABLE `jawaban` (
  `id`                bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_sesi`           bigint(20) UNSIGNED NOT NULL,
  `id_modul`          bigint(20) UNSIGNED NOT NULL,
  `id_pertanyaan`     bigint(20) UNSIGNED NOT NULL,
  `nilai_angka`       decimal(18,4) DEFAULT NULL,
  `nilai_teks`        longtext DEFAULT NULL,
  `nilai_json`        longtext CHECK (json_valid(`nilai_json`)),
  `satuan`            varchar(20) DEFAULT NULL,
  `sumber`            enum('suara','manual') NOT NULL DEFAULT 'suara',
  `tingkat_keyakinan` decimal(6,4) DEFAULT NULL,
  `created_at`        timestamp NULL DEFAULT NULL,
  `updated_at`        timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `jawaban_sesi_pertanyaan_unique` (`id_sesi`,`id_pertanyaan`),
  CONSTRAINT `jawaban_sesi_fk`       FOREIGN KEY (`id_sesi`)       REFERENCES `sesi_survei`(`id`),
  CONSTRAINT `jawaban_modul_fk`      FOREIGN KEY (`id_modul`)      REFERENCES `modul_survei`(`id`),
  CONSTRAINT `jawaban_pertanyaan_fk` FOREIGN KEY (`id_pertanyaan`) REFERENCES `pertanyaan`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `rekaman_suara` (
  `id`                 bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_sesi`            bigint(20) UNSIGNED NOT NULL,
  `id_modul`           bigint(20) UNSIGNED NOT NULL,
  `path_audio`         varchar(255) DEFAULT NULL,
  `teks_transkrip`     longtext DEFAULT NULL,
  `penyedia_stt`       varchar(50) DEFAULT NULL,
  `rata_keyakinan_stt` decimal(6,4) DEFAULT NULL,
  `durasi_detik`       int(10) UNSIGNED DEFAULT NULL,
  `created_at`         timestamp NULL DEFAULT NULL,
  `updated_at`         timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `rekaman_sesi_fk`  FOREIGN KEY (`id_sesi`)  REFERENCES `sesi_survei`(`id`),
  CONSTRAINT `rekaman_modul_fk` FOREIGN KEY (`id_modul`) REFERENCES `modul_survei`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- [BARU] Demografi dipisah dari kebutuhan komoditas.
CREATE TABLE `demografi_desa` (
  `id_demografi`    bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_wilayah`      bigint(20) UNSIGNED NOT NULL,
  `id_sesi_survei`  bigint(20) UNSIGNED DEFAULT NULL,
  `tahun`           year NOT NULL,
  `kelompok_umur`   enum('balita','anak','remaja','dewasa','lansia') NOT NULL,
  `jumlah_penduduk` int(11) NOT NULL DEFAULT 0,
  `created_at`      timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_demografi`),
  UNIQUE KEY `demografi_unik` (`id_wilayah`,`tahun`,`kelompok_umur`),
  CONSTRAINT `demografi_wilayah_fk` FOREIGN KEY (`id_wilayah`) REFERENCES `wilayah`(`id`),
  CONSTRAINT `demografi_sesi_fk`    FOREIGN KEY (`id_sesi_survei`) REFERENCES `sesi_survei`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- PERUBAHAN: di rancangan Anda, `jumlah_penduduk` disimpan berulang di setiap
-- baris kebutuhan_komoditas (per komoditas × per kelompok umur). Kalau jumlah
-- balita berubah, Anda harus meng-update puluhan baris sekaligus — dan kalau
-- satu baris terlewat, angkanya jadi tidak konsisten. Populasi disimpan satu kali di sini.

CREATE TABLE `standar_kebutuhan_komoditas` (
  `id`                bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sektor`            varchar(50) NOT NULL,
  `id_komoditas`      bigint(20) UNSIGNED NOT NULL,
  `kelompok_umur`     enum('balita','anak','remaja','dewasa','lansia') NOT NULL,
  `per_kapita_harian` decimal(12,6) NOT NULL DEFAULT 0,
  `satuan`            varchar(20) NOT NULL,
  `sumber`            varchar(150) DEFAULT NULL COMMENT 'AKG Kemenkes / survei lokal',
  `berlaku_mulai`     date NOT NULL,
  `berlaku_sampai`    date DEFAULT NULL,
  `created_at`        timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `standar_lookup_idx` (`id_komoditas`,`kelompok_umur`,`berlaku_mulai`),
  CONSTRAINT `standar_komoditas_fk` FOREIGN KEY (`id_komoditas`) REFERENCES `komoditas`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- PERUBAHAN: berlaku_mulai / berlaku_sampai / sumber DITAMBAHKAN.
-- Tanpa versi berlaku, revisi koefisien akan mengubah hasil kalkulasi bulan lalu
-- secara retroaktif dan menghapus jejak dasar keputusan pembelian.

CREATE TABLE `kebutuhan_komoditas` (
  `id`                bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_wilayah`        bigint(20) UNSIGNED NOT NULL,
  `id_komoditas`      bigint(20) UNSIGNED NOT NULL,
  `tahun`             year NOT NULL,
  `bulan`             tinyint(2) UNSIGNED NOT NULL,
  `kelompok_umur`     enum('balita','anak','remaja','dewasa','lansia') NOT NULL,
  `jumlah_penduduk`   int(11) NOT NULL DEFAULT 0,
  `per_kapita_harian` decimal(12,6) NOT NULL DEFAULT 0,
  `faktor_musiman`    decimal(6,4) NOT NULL DEFAULT 1.0000,
  `kebutuhan_bulanan` decimal(18,4) NOT NULL DEFAULT 0,
  `satuan`            varchar(20) NOT NULL,
  `id_standar`        bigint(20) UNSIGNED DEFAULT NULL COMMENT 'jejak koefisien versi mana yang dipakai',
  `created_at`        timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kebutuhan_unik` (`id_wilayah`,`id_komoditas`,`tahun`,`bulan`,`kelompok_umur`),
  CONSTRAINT `kebutuhan_wilayah_fk`   FOREIGN KEY (`id_wilayah`)   REFERENCES `wilayah`(`id`),
  CONSTRAINT `kebutuhan_komoditas_fk` FOREIGN KEY (`id_komoditas`) REFERENCES `komoditas`(`id`),
  CONSTRAINT `kebutuhan_standar_fk`   FOREIGN KEY (`id_standar`)   REFERENCES `standar_kebutuhan_komoditas`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- PERUBAHAN: dimensi BULAN ditambahkan, kolom harian/tahunan dihapus.
-- Rancangan Anda hanya punya kebutuhan_harian/bulanan/tahunan sebagai pengali
-- datar, sehingga tidak bisa membedakan kebutuhan beras bulan Ramadan dari
-- bulan biasa — padahal itu justru yang dibutuhkan untuk rencana pengadaan.

CREATE TABLE `ketersediaan_komoditas` (
  `id`             bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_wilayah`     bigint(20) UNSIGNED NOT NULL,
  `id_komoditas`   bigint(20) UNSIGNED NOT NULL,
  `id_sesi_survei` bigint(20) UNSIGNED DEFAULT NULL,
  `tahun`          year NOT NULL,
  `bulan`          tinyint(2) UNSIGNED NOT NULL COMMENT 'bulan panen/produksi',
  `jumlah_produksi` decimal(18,4) NOT NULL DEFAULT 0,
  `satuan`         varchar(20) NOT NULL,
  `created_at`     timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ketersediaan_unik` (`id_wilayah`,`id_komoditas`,`tahun`,`bulan`),
  CONSTRAINT `ketersediaan_wilayah_fk`   FOREIGN KEY (`id_wilayah`)   REFERENCES `wilayah`(`id`),
  CONSTRAINT `ketersediaan_komoditas_fk` FOREIGN KEY (`id_komoditas`) REFERENCES `komoditas`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- PERUBAHAN: ketersediaan_bulanan (angka datar) diganti produksi per bulan riil.
-- Padi tidak panen 12 kali setahun; membagi hasil tahunan dengan 12 menghasilkan
-- rencana pengadaan yang salah di bulan paceklik.


-- ============================================================
-- BAGIAN C — KALKULASI & PERENCANAAN
-- ============================================================

CREATE TABLE `hasil_kalkulasi` (
  `id_hasil`                   bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_wilayah`                 bigint(20) UNSIGNED NOT NULL,
  `id_komoditas`               bigint(20) UNSIGNED NOT NULL,
  `tahun`                      year NOT NULL,
  `bulan`                      tinyint(2) UNSIGNED NOT NULL,
  `total_kebutuhan`            decimal(18,4) NOT NULL DEFAULT 0,
  `total_ketersediaan`         decimal(18,4) NOT NULL DEFAULT 0,
  `selisih`                    decimal(18,4) NOT NULL DEFAULT 0,
  `status_surplus_defisit`     enum('surplus','defisit','seimbang') NOT NULL,
  `persentase_kecukupan`       decimal(8,2) NOT NULL DEFAULT 0,
  `id_unit_usaha_rekomendasi`  bigint(20) UNSIGNED DEFAULT NULL,
  `alasan_rekomendasi`         text DEFAULT NULL,
  `prioritas`                  tinyint(3) UNSIGNED DEFAULT NULL,
  `versi`                      int NOT NULL DEFAULT 1,
  `status`                     enum('draft','disetujui') NOT NULL DEFAULT 'draft',
  `created_at`                 timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_hasil`),
  UNIQUE KEY `hasil_unik` (`id_wilayah`,`id_komoditas`,`tahun`,`bulan`,`versi`),
  CONSTRAINT `hasil_wilayah_fk`   FOREIGN KEY (`id_wilayah`)   REFERENCES `wilayah`(`id`),
  CONSTRAINT `hasil_komoditas_fk` FOREIGN KEY (`id_komoditas`) REFERENCES `komoditas`(`id`),
  CONSTRAINT `hasil_unit_fk`      FOREIGN KEY (`id_unit_usaha_rekomendasi`) REFERENCES `master_unit_usaha`(`id_unit_usaha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `perbandingan_harga` (
  `id_perbandingan`  bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_koperasi`      bigint(20) UNSIGNED NOT NULL COMMENT 'desa yang melakukan perbandingan',
  `id_komoditas`     bigint(20) UNSIGNED NOT NULL,
  `id_wilayah_sumber` bigint(20) UNSIGNED NOT NULL COMMENT 'desa/lokasi asal penawaran',
  `bulan`            tinyint(2) UNSIGNED NOT NULL,
  `tahun`            year NOT NULL,
  `harga_ditawarkan` decimal(18,2) NOT NULL,
  `jumlah_tersedia`  decimal(18,4) NOT NULL DEFAULT 0,
  `jarak_ke_gudang`  decimal(10,2) DEFAULT NULL,
  `estimasi_ongkir`  decimal(18,2) DEFAULT NULL,
  `harga_efektif`    decimal(18,2) NOT NULL,
  `rank_harga`       tinyint(3) UNSIGNED DEFAULT NULL,
  `dipilih`          tinyint(1) NOT NULL DEFAULT 0,
  `created_at`       timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_perbandingan`),
  KEY `banding_lookup_idx` (`id_koperasi`,`id_komoditas`,`tahun`,`bulan`),
  CONSTRAINT `banding_koperasi_fk`  FOREIGN KEY (`id_koperasi`)  REFERENCES `koperasi_desa`(`id_koperasi`),
  CONSTRAINT `banding_komoditas_fk` FOREIGN KEY (`id_komoditas`) REFERENCES `komoditas`(`id`),
  CONSTRAINT `banding_wilayah_fk`   FOREIGN KEY (`id_wilayah_sumber`) REFERENCES `wilayah`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Permintaan pengadaan (PR). Sekarang punya header + detail multi-item.
CREATE TABLE `permintaan_pengadaan` (
  `id_permintaan`   bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_koperasi`     bigint(20) UNSIGNED NOT NULL,
  `kode_permintaan` varchar(30) NOT NULL,
  `id_pihak`        bigint(20) UNSIGNED DEFAULT NULL,
  `tgl_pengajuan`   date NOT NULL,
  `total_nilai`     decimal(18,2) NOT NULL DEFAULT 0,
  `status`          enum('draft','diajukan','disetujui','ditolak','jadi_pembelian','dibatalkan') NOT NULL DEFAULT 'draft',
  `catatan`         text DEFAULT NULL,
  `created_by`      bigint(20) UNSIGNED DEFAULT NULL,
  `approved_by`     bigint(20) UNSIGNED DEFAULT NULL,
  `approved_at`     datetime DEFAULT NULL,
  `created_at`      timestamp NULL DEFAULT NULL,
  `updated_at`      timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_permintaan`),
  UNIQUE KEY `pr_kode_unik` (`id_koperasi`,`kode_permintaan`),
  CONSTRAINT `pr_koperasi_fk` FOREIGN KEY (`id_koperasi`) REFERENCES `koperasi_desa`(`id_koperasi`),
  CONSTRAINT `pr_pihak_fk`    FOREIGN KEY (`id_pihak`)    REFERENCES `master_pihak`(`id_pihak`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `permintaan_pengadaan_detail` (
  `id_detail`       bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_permintaan`   bigint(20) UNSIGNED NOT NULL,
  `id_barang`       bigint(20) UNSIGNED NOT NULL,
  `id_hasil`        bigint(20) UNSIGNED DEFAULT NULL COMMENT 'jejak ke hasil_kalkulasi pemicu',
  `jumlah_diminta`  decimal(18,4) NOT NULL,
  `harga_perkiraan` decimal(18,2) NOT NULL DEFAULT 0,
  `subtotal`        decimal(18,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_detail`),
  CONSTRAINT `prd_permintaan_fk` FOREIGN KEY (`id_permintaan`) REFERENCES `permintaan_pengadaan`(`id_permintaan`),
  CONSTRAINT `prd_barang_fk`     FOREIGN KEY (`id_barang`)     REFERENCES `master_barang`(`id_barang`),
  CONSTRAINT `prd_hasil_fk`      FOREIGN KEY (`id_hasil`)      REFERENCES `hasil_kalkulasi`(`id_hasil`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- PERUBAHAN: permintaan_pengadaan Anda hanya menampung SATU komoditas per baris
-- dan langsung menyimpan jumlah_diterima + tanggal terima. Itu mencampur dokumen
-- permintaan dengan dokumen penerimaan. Sekarang dipisah: PR punya detail
-- multi-barang, penerimaan punya dokumennya sendiri.


-- ============================================================
-- BAGIAN D — GUDANG (Moving Average)
-- ============================================================

-- State stok berjalan. INILAH tabel yang membuat Moving Average bisa jalan.
CREATE TABLE `stok` (
  `id_gudang`         bigint(20) UNSIGNED NOT NULL,
  `id_barang`         bigint(20) UNSIGNED NOT NULL,
  `qty_on_hand`       decimal(18,4) NOT NULL DEFAULT 0,
  `qty_reserved`      decimal(18,4) NOT NULL DEFAULT 0,
  `hpp_rata2`         decimal(18,4) NOT NULL DEFAULT 0,
  `nilai_persediaan`  decimal(18,2) NOT NULL DEFAULT 0,
  `updated_at`        timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_gudang`,`id_barang`),
  CONSTRAINT `stok_gudang_fk` FOREIGN KEY (`id_gudang`) REFERENCES `gudang`(`id_gudang`),
  CONSTRAINT `stok_barang_fk` FOREIGN KEY (`id_barang`) REFERENCES `master_barang`(`id_barang`),
  CONSTRAINT `stok_qty_nonneg` CHECK (`qty_on_hand` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- qty_available = qty_on_hand - qty_reserved  (hitung di query, jangan disimpan)

-- Kartu stok. Append-only. Menggantikan stock_transactions.
CREATE TABLE `kartu_stok` (
  `id_kartu`          bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_koperasi`       bigint(20) UNSIGNED NOT NULL,
  `id_gudang`         bigint(20) UNSIGNED NOT NULL,
  `id_barang`         bigint(20) UNSIGNED NOT NULL,
  `tanggal`           date NOT NULL,
  `jenis_mutasi`      enum('IN','OUT','ADJ_IN','ADJ_OUT','TRF_IN','TRF_OUT') NOT NULL,
  `ref_tipe`          enum('PENERIMAAN','PENJUALAN','RETUR_BELI','RETUR_JUAL','OPNAME','KERUSAKAN','TRANSFER','SALDO_AWAL','KIRIM_ANTAR_DESA','TERIMA_ANTAR_DESA') NOT NULL,
  `ref_id`            bigint(20) UNSIGNED DEFAULT NULL,
  `qty_masuk`         decimal(18,4) NOT NULL DEFAULT 0,
  `qty_keluar`        decimal(18,4) NOT NULL DEFAULT 0,
  `harga_satuan`      decimal(18,4) NOT NULL DEFAULT 0 COMMENT 'harga beli utk IN, hpp_rata2 utk OUT',
  `nilai_mutasi`      decimal(18,2) NOT NULL DEFAULT 0,
  `saldo_qty`         decimal(18,4) NOT NULL DEFAULT 0,
  `saldo_nilai`       decimal(18,2) NOT NULL DEFAULT 0,
  `hpp_rata2_setelah` decimal(18,4) NOT NULL DEFAULT 0,
  `jenis_kejadian`    enum('rusak','susut','hilang') DEFAULT NULL,
  `id_jurnal`         bigint(20) UNSIGNED DEFAULT NULL COMMENT 'jurnal yang dihasilkan mutasi ini',
  `created_by`        bigint(20) UNSIGNED DEFAULT NULL,
  `created_at`        timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_kartu`),
  KEY `kartu_lookup_idx` (`id_koperasi`,`id_gudang`,`id_barang`,`tanggal`),
  UNIQUE KEY `kartu_idempoten` (`ref_tipe`,`ref_id`,`id_barang`,`jenis_mutasi`),
  CONSTRAINT `kartu_koperasi_fk` FOREIGN KEY (`id_koperasi`) REFERENCES `koperasi_desa`(`id_koperasi`),
  CONSTRAINT `kartu_gudang_fk`   FOREIGN KEY (`id_gudang`)   REFERENCES `gudang`(`id_gudang`),
  CONSTRAINT `kartu_barang_fk`   FOREIGN KEY (`id_barang`)   REFERENCES `master_barang`(`id_barang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- PERUBAHAN vs stock_transactions Anda:
--  + id_koperasi & id_gudang (dulu tidak ada sama sekali)
--  + saldo_nilai & hpp_rata2_setelah (tanpa ini Moving Average mustahil)
--  + UNIQUE(ref_tipe, ref_id, ...) mencegah satu dokumen memotong stok dua kali
--  + id_jurnal sebagai jejak balik ke akuntansi
--  - kolom kategori_asal/kategori_tujuan (varchar bebas) dihapus, diganti enum
--  ATURAN: dilarang input mutasi mundur tanggal. Koreksi = mutasi baru hari ini.

-- Penerimaan barang (GRN)
CREATE TABLE `penerimaan_barang` (
  `id_penerimaan`  bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_koperasi`    bigint(20) UNSIGNED NOT NULL,
  `id_gudang`      bigint(20) UNSIGNED NOT NULL,
  `kode_penerimaan` varchar(30) NOT NULL,
  `id_pembelian`   bigint(20) UNSIGNED DEFAULT NULL,
  `id_pihak`       bigint(20) UNSIGNED NOT NULL,
  `tanggal_terima` date NOT NULL,
  `status`         enum('draft','disortir','diposting','dibatalkan') NOT NULL DEFAULT 'draft',
  `catatan`        text DEFAULT NULL,
  `created_by`     bigint(20) UNSIGNED DEFAULT NULL,
  `created_at`     timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_penerimaan`),
  UNIQUE KEY `grn_kode_unik` (`id_koperasi`,`kode_penerimaan`),
  CONSTRAINT `grn_koperasi_fk`  FOREIGN KEY (`id_koperasi`)  REFERENCES `koperasi_desa`(`id_koperasi`),
  CONSTRAINT `grn_gudang_fk`    FOREIGN KEY (`id_gudang`)    REFERENCES `gudang`(`id_gudang`),
  CONSTRAINT `grn_pembelian_fk` FOREIGN KEY (`id_pembelian`) REFERENCES `pembelian`(`id_pembelian`),
  CONSTRAINT `grn_pihak_fk`     FOREIGN KEY (`id_pihak`)     REFERENCES `master_pihak`(`id_pihak`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `penerimaan_barang_detail` (
  `id_detail`          bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_penerimaan`      bigint(20) UNSIGNED NOT NULL,
  `id_barang`          bigint(20) UNSIGNED NOT NULL,
  `id_satuan_input`    bigint(20) UNSIGNED NOT NULL,
  `qty_input`          decimal(18,4) NOT NULL COMMENT 'dalam satuan input, mis. 10 karung',
  `faktor_konversi`    decimal(18,6) NOT NULL DEFAULT 1,
  `qty_dasar`          decimal(18,4) NOT NULL COMMENT 'hasil konversi ke satuan dasar',
  `qty_layak`          decimal(18,4) NOT NULL DEFAULT 0,
  `qty_tidak_layak`    decimal(18,4) NOT NULL DEFAULT 0,
  `harga_satuan_dasar` decimal(18,4) NOT NULL,
  `subtotal`           decimal(18,2) NOT NULL,
  `alasan_tidak_layak` text DEFAULT NULL,
  `foto_bukti`         varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_detail`),
  CONSTRAINT `grnd_penerimaan_fk` FOREIGN KEY (`id_penerimaan`) REFERENCES `penerimaan_barang`(`id_penerimaan`),
  CONSTRAINT `grnd_barang_fk`     FOREIGN KEY (`id_barang`)     REFERENCES `master_barang`(`id_barang`),
  CONSTRAINT `grnd_satuan_fk`     FOREIGN KEY (`id_satuan_input`) REFERENCES `satuan`(`id`),
  CONSTRAINT `grnd_qty_check`     CHECK (`qty_layak` + `qty_tidak_layak` <= `qty_dasar`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- PERUBAHAN: tabel `sortir_barang` terpisah DIHAPUS, hasil sortir dijadikan
-- kolom di detail penerimaan. Alasan: sortir_barang Anda tidak punya id_barang,
-- sehingga pada penerimaan multi-item tidak ketahuan barang mana yang tidak layak.

-- Stock opname
CREATE TABLE `opname_header` (
  `id_opname`   bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_koperasi` bigint(20) UNSIGNED NOT NULL,
  `id_gudang`   bigint(20) UNSIGNED NOT NULL,
  `kode_opname` varchar(30) NOT NULL,
  `tanggal`     date NOT NULL,
  `status`      enum('draft','dihitung','disetujui','diposting') NOT NULL DEFAULT 'draft',
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at`  timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_opname`),
  UNIQUE KEY `opname_kode_unik` (`id_koperasi`,`kode_opname`),
  CONSTRAINT `opname_koperasi_fk` FOREIGN KEY (`id_koperasi`) REFERENCES `koperasi_desa`(`id_koperasi`),
  CONSTRAINT `opname_gudang_fk`   FOREIGN KEY (`id_gudang`)   REFERENCES `gudang`(`id_gudang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `opname_detail` (
  `id_detail`    bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_opname`    bigint(20) UNSIGNED NOT NULL,
  `id_barang`    bigint(20) UNSIGNED NOT NULL,
  `qty_sistem`   decimal(18,4) NOT NULL,
  `qty_fisik`    decimal(18,4) NOT NULL,
  `selisih`      decimal(18,4) NOT NULL,
  `hpp_rata2`    decimal(18,4) NOT NULL,
  `nilai_selisih` decimal(18,2) NOT NULL,
  `keterangan`   text DEFAULT NULL,
  PRIMARY KEY (`id_detail`),
  CONSTRAINT `opd_opname_fk` FOREIGN KEY (`id_opname`) REFERENCES `opname_header`(`id_opname`),
  CONSTRAINT `opd_barang_fk` FOREIGN KEY (`id_barang`) REFERENCES `master_barang`(`id_barang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kerusakan / susut di gudang (bukan hasil opname)
CREATE TABLE `kerusakan_barang` (
  `id_kerusakan` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_koperasi`  bigint(20) UNSIGNED NOT NULL,
  `id_gudang`    bigint(20) UNSIGNED NOT NULL,
  `id_barang`    bigint(20) UNSIGNED NOT NULL,
  `tanggal`      date NOT NULL,
  `qty`          decimal(18,4) NOT NULL,
  `hpp_rata2`    decimal(18,4) NOT NULL,
  `nilai_kerugian` decimal(18,2) NOT NULL,
  `jenis_kejadian` enum('rusak','susut','hilang','kadaluarsa') NOT NULL,
  `foto_bukti`   varchar(255) DEFAULT NULL,
  `status`       enum('draft','disetujui','diposting') NOT NULL DEFAULT 'draft',
  `approved_by`  bigint(20) UNSIGNED DEFAULT NULL,
  `created_at`   timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_kerusakan`),
  CONSTRAINT `rusak_koperasi_fk` FOREIGN KEY (`id_koperasi`) REFERENCES `koperasi_desa`(`id_koperasi`),
  CONSTRAINT `rusak_gudang_fk`   FOREIGN KEY (`id_gudang`)   REFERENCES `gudang`(`id_gudang`),
  CONSTRAINT `rusak_barang_fk`   FOREIGN KEY (`id_barang`)   REFERENCES `master_barang`(`id_barang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- BAGIAN E — PEMBELIAN
-- ============================================================

CREATE TABLE `pembelian` (
  `id_pembelian`      bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_koperasi`       bigint(20) UNSIGNED NOT NULL,
  `kode_pembelian`    varchar(30) NOT NULL,
  `id_permintaan`     bigint(20) UNSIGNED DEFAULT NULL,
  `id_pihak`          bigint(20) UNSIGNED NOT NULL,
  `id_unit_usaha`     bigint(20) UNSIGNED NOT NULL,
  `id_gudang`         bigint(20) UNSIGNED NOT NULL,
  `tanggal_transaksi` date NOT NULL,
  `jenis_pembayaran`  enum('tunai','transfer','kredit') NOT NULL,
  `id_kas_bank`       bigint(20) UNSIGNED DEFAULT NULL COMMENT 'wajib jika tunai/transfer',
  `tgl_jatuh_tempo`   date DEFAULT NULL COMMENT 'wajib jika kredit',
  `total_pembelian`   decimal(18,2) NOT NULL DEFAULT 0,
  `status`            enum('draft','disetujui','diterima','selesai','dibatalkan') NOT NULL DEFAULT 'draft',
  `status_posting`    enum('F','T') NOT NULL DEFAULT 'F',
  `id_jurnal`         bigint(20) UNSIGNED DEFAULT NULL,
  `created_by`        bigint(20) UNSIGNED DEFAULT NULL,
  `created_at`        timestamp NULL DEFAULT NULL,
  `updated_at`        timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_pembelian`),
  UNIQUE KEY `beli_kode_unik` (`id_koperasi`,`kode_pembelian`),
  CONSTRAINT `beli_koperasi_fk`  FOREIGN KEY (`id_koperasi`)  REFERENCES `koperasi_desa`(`id_koperasi`),
  CONSTRAINT `beli_permintaan_fk` FOREIGN KEY (`id_permintaan`) REFERENCES `permintaan_pengadaan`(`id_permintaan`),
  CONSTRAINT `beli_pihak_fk`     FOREIGN KEY (`id_pihak`)     REFERENCES `master_pihak`(`id_pihak`),
  CONSTRAINT `beli_unit_fk`      FOREIGN KEY (`id_unit_usaha`) REFERENCES `master_unit_usaha`(`id_unit_usaha`),
  CONSTRAINT `beli_gudang_fk`    FOREIGN KEY (`id_gudang`)    REFERENCES `gudang`(`id_gudang`),
  CONSTRAINT `beli_kasbank_fk`   FOREIGN KEY (`id_kas_bank`)  REFERENCES `master_kas_bank`(`id_kas_bank`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- PERUBAHAN: total_ppn_masukan DIHAPUS (non-PKP).
-- PERUBAHAN: id_permintaan DITAMBAHKAN — rantai PR -> Pembelian dulu terputus.
-- PERUBAHAN: id_kas_bank DITAMBAHKAN — dulu hanya ada varchar metode_pembayaran,
-- sehingga sistem tidak tahu bank mana yang harus didebit/dikredit.

CREATE TABLE `detail_pembelian` (
  `id_detail`          bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_pembelian`       bigint(20) UNSIGNED NOT NULL,
  `id_barang`          bigint(20) UNSIGNED NOT NULL,
  `id_satuan_input`    bigint(20) UNSIGNED NOT NULL,
  `qty_input`          decimal(18,4) NOT NULL,
  `faktor_konversi`    decimal(18,6) NOT NULL DEFAULT 1,
  `qty_dasar`          decimal(18,4) NOT NULL,
  `harga_satuan_input` decimal(18,2) NOT NULL,
  `subtotal`           decimal(18,2) NOT NULL,
  PRIMARY KEY (`id_detail`),
  CONSTRAINT `dbeli_pembelian_fk` FOREIGN KEY (`id_pembelian`) REFERENCES `pembelian`(`id_pembelian`),
  CONSTRAINT `dbeli_barang_fk`    FOREIGN KEY (`id_barang`)    REFERENCES `master_barang`(`id_barang`),
  CONSTRAINT `dbeli_satuan_fk`    FOREIGN KEY (`id_satuan_input`) REFERENCES `satuan`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `retur_pembelian` (
  `id_retur`          bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_koperasi`       bigint(20) UNSIGNED NOT NULL,
  `kode_retur`        varchar(30) NOT NULL,
  `id_pembelian`      bigint(20) UNSIGNED NOT NULL,
  `id_penerimaan`     bigint(20) UNSIGNED DEFAULT NULL,
  `tgl_retur`         date NOT NULL,
  `jenis_penyelesaian` enum('uang','potong_hutang','ganti_barang') NOT NULL,
  `total_nilai`       decimal(18,2) NOT NULL DEFAULT 0,
  `alasan`            text DEFAULT NULL,
  `foto_bukti`        varchar(255) DEFAULT NULL,
  `status`            enum('diajukan','disetujui','selesai') NOT NULL DEFAULT 'diajukan',
  `status_posting`    enum('F','T') NOT NULL DEFAULT 'F',
  `id_jurnal`         bigint(20) UNSIGNED DEFAULT NULL,
  `created_at`        timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_retur`),
  UNIQUE KEY `rbeli_kode_unik` (`id_koperasi`,`kode_retur`),
  CONSTRAINT `rbeli_koperasi_fk`  FOREIGN KEY (`id_koperasi`)  REFERENCES `koperasi_desa`(`id_koperasi`),
  CONSTRAINT `rbeli_pembelian_fk` FOREIGN KEY (`id_pembelian`) REFERENCES `pembelian`(`id_pembelian`),
  CONSTRAINT `rbeli_penerimaan_fk` FOREIGN KEY (`id_penerimaan`) REFERENCES `penerimaan_barang`(`id_penerimaan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `retur_pembelian_detail` (
  `id_detail`    bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_retur`     bigint(20) UNSIGNED NOT NULL,
  `id_barang`    bigint(20) UNSIGNED NOT NULL,
  `qty_dasar`    decimal(18,4) NOT NULL,
  `hpp_rata2`    decimal(18,4) NOT NULL,
  `nilai`        decimal(18,2) NOT NULL,
  PRIMARY KEY (`id_detail`),
  CONSTRAINT `rbd_retur_fk`  FOREIGN KEY (`id_retur`)  REFERENCES `retur_pembelian`(`id_retur`),
  CONSTRAINT `rbd_barang_fk` FOREIGN KEY (`id_barang`) REFERENCES `master_barang`(`id_barang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- PERUBAHAN NAMA: retur_pengadaan -> retur_pembelian, dan sekarang punya detail.
-- Rancangan lama hanya punya satu `jumlah_retur` untuk satu pembelian
-- multi-item, jadi tidak ketahuan barang mana yang diretur.


-- ============================================================
-- BAGIAN F — PENJUALAN
-- ============================================================

CREATE TABLE `penjualan` (
  `id_penjualan`      bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_koperasi`       bigint(20) UNSIGNED NOT NULL,
  `kode_penjualan`    varchar(30) NOT NULL,
  `id_pihak`          bigint(20) UNSIGNED NOT NULL,
  `id_unit_usaha`     bigint(20) UNSIGNED NOT NULL,
  `id_gudang`         bigint(20) UNSIGNED NOT NULL,
  `tanggal_transaksi` date NOT NULL,
  `kode_transaksi`    varchar(10) NOT NULL COMMENT 'JTW / JTFW / JTD / JTFD / JKD',
  `is_pembeli_anggota` tinyint(1) NOT NULL DEFAULT 0,
  `id_kas_bank`       bigint(20) UNSIGNED DEFAULT NULL,
  `tgl_jatuh_tempo`   date DEFAULT NULL,
  `total_bruto`       decimal(18,2) NOT NULL DEFAULT 0,
  `diskon`            decimal(18,2) NOT NULL DEFAULT 0,
  `total_bayar`       decimal(18,2) NOT NULL DEFAULT 0,
  `total_hpp`         decimal(18,2) NOT NULL DEFAULT 0,
  `status_bayar`      enum('lunas','sebagian','belum_lunas') NOT NULL DEFAULT 'lunas',
  `status`            enum('draft','dikirim','selesai','dibatalkan') NOT NULL DEFAULT 'draft',
  `status_posting`    enum('F','T') NOT NULL DEFAULT 'F',
  `id_jurnal`         bigint(20) UNSIGNED DEFAULT NULL,
  `created_by`        bigint(20) UNSIGNED DEFAULT NULL,
  `created_at`        timestamp NULL DEFAULT NULL,
  `updated_at`        timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_penjualan`),
  UNIQUE KEY `jual_kode_unik` (`id_koperasi`,`kode_penjualan`),
  KEY `jual_lookup_idx` (`id_koperasi`,`tanggal_transaksi`),
  CONSTRAINT `jual_koperasi_fk` FOREIGN KEY (`id_koperasi`)  REFERENCES `koperasi_desa`(`id_koperasi`),
  CONSTRAINT `jual_pihak_fk`    FOREIGN KEY (`id_pihak`)     REFERENCES `master_pihak`(`id_pihak`),
  CONSTRAINT `jual_unit_fk`     FOREIGN KEY (`id_unit_usaha`) REFERENCES `master_unit_usaha`(`id_unit_usaha`),
  CONSTRAINT `jual_gudang_fk`   FOREIGN KEY (`id_gudang`)    REFERENCES `gudang`(`id_gudang`),
  CONSTRAINT `jual_kasbank_fk`  FOREIGN KEY (`id_kas_bank`)  REFERENCES `master_kas_bank`(`id_kas_bank`),
  CONSTRAINT `jual_transaksi_fk` FOREIGN KEY (`kode_transaksi`) REFERENCES `master_transaksi`(`kode_transaksi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- PERUBAHAN: total_ppn_keluaran DIHAPUS (non-PKP).
-- PERUBAHAN: kode_transaksi DITAMBAHKAN sebagai kolom eksplisit — inilah yang
-- dipakai mesin jurnal memilih template. Dulu tidak ada penghubungnya sama sekali
-- antara tabel penjualan dan master_transaksi.
-- PERUBAHAN: is_pembeli_anggota DITAMBAHKAN, disalin saat transaksi (bukan
-- di-join ke master_pihak saat pelaporan), agar status keanggotaan yang berubah
-- di kemudian hari tidak mengubah angka SHU tahun-tahun sebelumnya.

CREATE TABLE `detail_penjualan` (
  `id_detail`         bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_penjualan`      bigint(20) UNSIGNED NOT NULL,
  `id_barang`         bigint(20) UNSIGNED NOT NULL,
  `id_satuan_input`   bigint(20) UNSIGNED NOT NULL,
  `qty_input`         decimal(18,4) NOT NULL,
  `faktor_konversi`   decimal(18,6) NOT NULL DEFAULT 1,
  `qty_dasar`         decimal(18,4) NOT NULL,
  `harga_satuan`      decimal(18,2) NOT NULL,
  `subtotal`          decimal(18,2) NOT NULL,
  `hpp_satuan_dasar`  decimal(18,4) NOT NULL COMMENT 'diambil dari stok.hpp_rata2 SAAT transaksi',
  `total_hpp`         decimal(18,2) NOT NULL,
  PRIMARY KEY (`id_detail`),
  CONSTRAINT `djual_penjualan_fk` FOREIGN KEY (`id_penjualan`) REFERENCES `penjualan`(`id_penjualan`),
  CONSTRAINT `djual_barang_fk`    FOREIGN KEY (`id_barang`)    REFERENCES `master_barang`(`id_barang`),
  CONSTRAINT `djual_satuan_fk`    FOREIGN KEY (`id_satuan_input`) REFERENCES `satuan`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `retur_penjualan` (
  `id_retur`           bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_koperasi`        bigint(20) UNSIGNED NOT NULL,
  `kode_retur`         varchar(30) NOT NULL,
  `id_penjualan`       bigint(20) UNSIGNED NOT NULL,
  `tgl_retur`          date NOT NULL,
  `jenis_penyelesaian` enum('uang','potong_piutang','ganti_barang') NOT NULL,
  `total_nilai`        decimal(18,2) NOT NULL DEFAULT 0,
  `total_hpp`          decimal(18,2) NOT NULL DEFAULT 0,
  `alasan`             text DEFAULT NULL,
  `status`             enum('diajukan','disetujui','selesai') NOT NULL DEFAULT 'diajukan',
  `status_posting`     enum('F','T') NOT NULL DEFAULT 'F',
  `id_jurnal`          bigint(20) UNSIGNED DEFAULT NULL,
  `created_at`         timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_retur`),
  UNIQUE KEY `rjual_kode_unik` (`id_koperasi`,`kode_retur`),
  CONSTRAINT `rjual_koperasi_fk`  FOREIGN KEY (`id_koperasi`)  REFERENCES `koperasi_desa`(`id_koperasi`),
  CONSTRAINT `rjual_penjualan_fk` FOREIGN KEY (`id_penjualan`) REFERENCES `penjualan`(`id_penjualan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `retur_penjualan_detail` (
  `id_detail` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_retur`  bigint(20) UNSIGNED NOT NULL,
  `id_barang` bigint(20) UNSIGNED NOT NULL,
  `qty_dasar` decimal(18,4) NOT NULL,
  `harga_satuan` decimal(18,2) NOT NULL,
  `nilai`     decimal(18,2) NOT NULL,
  `hpp_satuan` decimal(18,4) NOT NULL,
  `total_hpp` decimal(18,2) NOT NULL,
  PRIMARY KEY (`id_detail`),
  CONSTRAINT `rjd_retur_fk`  FOREIGN KEY (`id_retur`)  REFERENCES `retur_penjualan`(`id_retur`),
  CONSTRAINT `rjd_barang_fk` FOREIGN KEY (`id_barang`) REFERENCES `master_barang`(`id_barang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- BAGIAN G — TRANSAKSI ANTAR DESA (menggantikan modul barter)
-- ============================================================
-- MODEL YANG DIASUMSIKAN: penjualan kredit antar entitas.
-- Desa A kirim barang -> Desa A akui Pendapatan + Piutang Antar Desa
--                      -> Desa B akui Persediaan + Hutang Antar Desa
-- Pelunasan bisa berupa uang, ATAU barang balasan (offset piutang vs hutang).
--
-- KALAU YANG ANDA MAKSUD KONSINYASI (Desa B baru bayar setelah barang laku),
-- model ini SALAH dan harus diganti: pendapatan belum boleh diakui saat kirim,
-- dan persediaan tetap milik Desa A. Konfirmasi dulu sebelum implementasi.

CREATE TABLE `permintaan_barter` (
  `id`                 bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_koperasi_pemohon` bigint(20) UNSIGNED NOT NULL,
  `id_pemohon`         bigint(20) UNSIGNED NOT NULL,
  `id_barang`          bigint(20) UNSIGNED NOT NULL,
  `qty_diminta_dasar`  decimal(18,4) NOT NULL,
  `tgl_dibutuhkan`     date DEFAULT NULL,
  `status`             enum('terbuka','tercocok','tertutup','kadaluarsa') NOT NULL DEFAULT 'terbuka',
  `catatan`            text DEFAULT NULL,
  `created_at`         timestamp NULL DEFAULT NULL,
  `updated_at`         timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `pb_koperasi_fk` FOREIGN KEY (`id_koperasi_pemohon`) REFERENCES `koperasi_desa`(`id_koperasi`),
  CONSTRAINT `pb_pemohon_fk`  FOREIGN KEY (`id_pemohon`) REFERENCES `pengguna`(`id`),
  CONSTRAINT `pb_barang_fk`   FOREIGN KEY (`id_barang`)  REFERENCES `master_barang`(`id_barang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `penawaran_barter` (
  `id`                    bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_permintaan_barter`  bigint(20) UNSIGNED NOT NULL,
  `id_koperasi_penawar`   bigint(20) UNSIGNED NOT NULL,
  `id_penawar`            bigint(20) UNSIGNED NOT NULL,
  `qty_ditawarkan_dasar`  decimal(18,4) NOT NULL,
  `harga_satuan`          decimal(18,2) NOT NULL,
  `status`                enum('menunggu','diterima','ditolak') NOT NULL DEFAULT 'menunggu',
  `catatan`               text DEFAULT NULL,
  `created_at`            timestamp NULL DEFAULT NULL,
  `updated_at`            timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `nb_permintaan_fk` FOREIGN KEY (`id_permintaan_barter`) REFERENCES `permintaan_barter`(`id`),
  CONSTRAINT `nb_koperasi_fk`   FOREIGN KEY (`id_koperasi_penawar`)  REFERENCES `koperasi_desa`(`id_koperasi`),
  CONSTRAINT `nb_penawar_fk`    FOREIGN KEY (`id_penawar`)           REFERENCES `pengguna`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dokumen pengiriman antar desa. SATU dokumen, DUA jurnal (di dua tenant).
CREATE TABLE `pengiriman_antar_desa` (
  `id_kiriman`         bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode_kiriman`       varchar(30) NOT NULL,
  `id_penawaran_barter` bigint(20) UNSIGNED DEFAULT NULL,
  `id_koperasi_pengirim` bigint(20) UNSIGNED NOT NULL,
  `id_koperasi_penerima` bigint(20) UNSIGNED NOT NULL,
  `id_gudang_asal`     bigint(20) UNSIGNED NOT NULL,
  `id_gudang_tujuan`   bigint(20) UNSIGNED NOT NULL,
  `tgl_kirim`          date NOT NULL,
  `tgl_terima`         date DEFAULT NULL,
  `tgl_jatuh_tempo`    date DEFAULT NULL,
  `total_nilai`        decimal(18,2) NOT NULL DEFAULT 0,
  `total_hpp_pengirim` decimal(18,2) NOT NULL DEFAULT 0,
  `status`             enum('draft','dikirim','diterima','ditolak','selesai') NOT NULL DEFAULT 'draft',
  `status_posting_pengirim` enum('F','T') NOT NULL DEFAULT 'F',
  `status_posting_penerima` enum('F','T') NOT NULL DEFAULT 'F',
  `id_jurnal_pengirim` bigint(20) UNSIGNED DEFAULT NULL,
  `id_jurnal_penerima` bigint(20) UNSIGNED DEFAULT NULL,
  `catatan_pengiriman` text DEFAULT NULL,
  `catatan_penerimaan` text DEFAULT NULL,
  `created_at`         timestamp NULL DEFAULT NULL,
  `updated_at`         timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_kiriman`),
  UNIQUE KEY `kirim_kode_unik` (`kode_kiriman`),
  CONSTRAINT `kirim_penawaran_fk` FOREIGN KEY (`id_penawaran_barter`) REFERENCES `penawaran_barter`(`id`),
  CONSTRAINT `kirim_pengirim_fk`  FOREIGN KEY (`id_koperasi_pengirim`) REFERENCES `koperasi_desa`(`id_koperasi`),
  CONSTRAINT `kirim_penerima_fk`  FOREIGN KEY (`id_koperasi_penerima`) REFERENCES `koperasi_desa`(`id_koperasi`),
  CONSTRAINT `kirim_gd_asal_fk`   FOREIGN KEY (`id_gudang_asal`)   REFERENCES `gudang`(`id_gudang`),
  CONSTRAINT `kirim_gd_tujuan_fk` FOREIGN KEY (`id_gudang_tujuan`) REFERENCES `gudang`(`id_gudang`),
  CONSTRAINT `kirim_beda_desa` CHECK (`id_koperasi_pengirim` <> `id_koperasi_penerima`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `pengiriman_antar_desa_detail` (
  `id_detail`     bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_kiriman`    bigint(20) UNSIGNED NOT NULL,
  `id_barang`     bigint(20) UNSIGNED NOT NULL,
  `qty_dasar`     decimal(18,4) NOT NULL,
  `harga_satuan`  decimal(18,2) NOT NULL,
  `subtotal`      decimal(18,2) NOT NULL,
  `hpp_pengirim`  decimal(18,4) NOT NULL,
  `total_hpp`     decimal(18,2) NOT NULL,
  PRIMARY KEY (`id_detail`),
  CONSTRAINT `kd_kiriman_fk` FOREIGN KEY (`id_kiriman`) REFERENCES `pengiriman_antar_desa`(`id_kiriman`),
  CONSTRAINT `kd_barang_fk`  FOREIGN KEY (`id_barang`)  REFERENCES `master_barang`(`id_barang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- PERUBAHAN vs transaksi_barter Anda:
--  - jumlah_ton / harga_per_ton_rp DIHAPUS. Mengunci satuan ke ton bertabrakan
--    dengan sistem konversi satuan Anda sendiri dan menghancurkan presisi
--    (ikan 40 kg = 0,04 ton -> pembulatan decimal(12,2) jadi 0,04, HPP melenceng).
--  - status_barang (menunggu_jual/terjual_habis) DIHAPUS. Kolom itu adalah sisa
--    model konsinyasi yang bercampur dengan model utang. Pilih satu.
--  + detail multi-barang: satu pengiriman jarang berisi satu komoditas saja.
--  + dua kolom status_posting & dua id_jurnal, karena satu dokumen ini
--    menghasilkan jurnal di DUA pembukuan yang berbeda.


-- ============================================================
-- BAGIAN H — PIUTANG, HUTANG, KAS  [BARU]
-- ============================================================

CREATE TABLE `piutang` (
  `id_piutang`      bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_koperasi`     bigint(20) UNSIGNED NOT NULL,
  `id_pihak`        bigint(20) UNSIGNED NOT NULL,
  `sumber_tipe`     enum('PENJUALAN','ANTAR_DESA','LAIN') NOT NULL,
  `sumber_id`       bigint(20) UNSIGNED NOT NULL,
  `kode_akun`       varchar(10) NOT NULL COMMENT '1132 Piutang Dagang / 1133 Piutang Antar Desa',
  `tanggal`         date NOT NULL,
  `tgl_jatuh_tempo` date NOT NULL,
  `nilai_awal`      decimal(18,2) NOT NULL,
  `nilai_terbayar`  decimal(18,2) NOT NULL DEFAULT 0,
  `sisa`            decimal(18,2) AS (`nilai_awal` - `nilai_terbayar`) STORED,
  `status`          enum('belum_lunas','sebagian','lunas','hapus_buku') NOT NULL DEFAULT 'belum_lunas',
  `created_at`      timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_piutang`),
  UNIQUE KEY `piutang_sumber_unik` (`sumber_tipe`,`sumber_id`),
  KEY `piutang_aging_idx` (`id_koperasi`,`status`,`tgl_jatuh_tempo`),
  CONSTRAINT `piutang_koperasi_fk` FOREIGN KEY (`id_koperasi`) REFERENCES `koperasi_desa`(`id_koperasi`),
  CONSTRAINT `piutang_pihak_fk`    FOREIGN KEY (`id_pihak`)    REFERENCES `master_pihak`(`id_pihak`),
  CONSTRAINT `piutang_akun_fk`     FOREIGN KEY (`kode_akun`)   REFERENCES `master_coa`(`kode_anak`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- PERUBAHAN: tabel piutang Anda memaksa FK ke id_penjualan NOT NULL, sehingga
-- piutang dari transaksi antar desa tidak punya tempat. Sekarang polimorfik
-- (sumber_tipe + sumber_id) dengan jatuh tempo dan aging.

CREATE TABLE `hutang` (
  `id_hutang`       bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_koperasi`     bigint(20) UNSIGNED NOT NULL,
  `id_pihak`        bigint(20) UNSIGNED NOT NULL,
  `sumber_tipe`     enum('PEMBELIAN','ANTAR_DESA','LAIN') NOT NULL,
  `sumber_id`       bigint(20) UNSIGNED NOT NULL,
  `kode_akun`       varchar(10) NOT NULL COMMENT '2111 Hutang Dagang / 2112 Hutang Antar Desa',
  `tanggal`         date NOT NULL,
  `tgl_jatuh_tempo` date NOT NULL,
  `nilai_awal`      decimal(18,2) NOT NULL,
  `nilai_terbayar`  decimal(18,2) NOT NULL DEFAULT 0,
  `sisa`            decimal(18,2) AS (`nilai_awal` - `nilai_terbayar`) STORED,
  `status`          enum('belum_lunas','sebagian','lunas') NOT NULL DEFAULT 'belum_lunas',
  `created_at`      timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_hutang`),
  UNIQUE KEY `hutang_sumber_unik` (`sumber_tipe`,`sumber_id`),
  KEY `hutang_aging_idx` (`id_koperasi`,`status`,`tgl_jatuh_tempo`),
  CONSTRAINT `hutang_koperasi_fk` FOREIGN KEY (`id_koperasi`) REFERENCES `koperasi_desa`(`id_koperasi`),
  CONSTRAINT `hutang_pihak_fk`    FOREIGN KEY (`id_pihak`)    REFERENCES `master_pihak`(`id_pihak`),
  CONSTRAINT `hutang_akun_fk`     FOREIGN KEY (`kode_akun`)   REFERENCES `master_coa`(`kode_anak`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- [BARU] Tidak ada padanannya di rancangan Anda, padahal pembelian kredit ada.

CREATE TABLE `pelunasan` (
  `id_pelunasan`   bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_koperasi`    bigint(20) UNSIGNED NOT NULL,
  `kode_pelunasan` varchar(30) NOT NULL,
  `jenis`          enum('terima_piutang','bayar_hutang','offset_barter') NOT NULL,
  `id_pihak`       bigint(20) UNSIGNED NOT NULL,
  `tanggal`        date NOT NULL,
  `id_kas_bank`    bigint(20) UNSIGNED DEFAULT NULL COMMENT 'NULL jika offset_barter',
  `total_nilai`    decimal(18,2) NOT NULL,
  `status_posting` enum('F','T') NOT NULL DEFAULT 'F',
  `id_jurnal`      bigint(20) UNSIGNED DEFAULT NULL,
  `catatan`        text DEFAULT NULL,
  `created_by`     bigint(20) UNSIGNED DEFAULT NULL,
  `created_at`     timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_pelunasan`),
  UNIQUE KEY `pelunasan_kode_unik` (`id_koperasi`,`kode_pelunasan`),
  CONSTRAINT `pelunasan_koperasi_fk` FOREIGN KEY (`id_koperasi`) REFERENCES `koperasi_desa`(`id_koperasi`),
  CONSTRAINT `pelunasan_pihak_fk`    FOREIGN KEY (`id_pihak`)    REFERENCES `master_pihak`(`id_pihak`),
  CONSTRAINT `pelunasan_kasbank_fk`  FOREIGN KEY (`id_kas_bank`) REFERENCES `master_kas_bank`(`id_kas_bank`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `pelunasan_detail` (
  `id_detail`    bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_pelunasan` bigint(20) UNSIGNED NOT NULL,
  `id_piutang`   bigint(20) UNSIGNED DEFAULT NULL,
  `id_hutang`    bigint(20) UNSIGNED DEFAULT NULL,
  `nilai_bayar`  decimal(18,2) NOT NULL,
  PRIMARY KEY (`id_detail`),
  CONSTRAINT `pld_pelunasan_fk` FOREIGN KEY (`id_pelunasan`) REFERENCES `pelunasan`(`id_pelunasan`),
  CONSTRAINT `pld_piutang_fk`   FOREIGN KEY (`id_piutang`)   REFERENCES `piutang`(`id_piutang`),
  CONSTRAINT `pld_hutang_fk`    FOREIGN KEY (`id_hutang`)    REFERENCES `hutang`(`id_hutang`),
  CONSTRAINT `pld_salah_satu`   CHECK ((`id_piutang` IS NULL) <> (`id_hutang` IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kas masuk/keluar non-dagang (bayar listrik, gaji, setor bank, dll)
CREATE TABLE `kas_transaksi` (
  `id_kas_trx`   bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_koperasi`  bigint(20) UNSIGNED NOT NULL,
  `kode_trx`     varchar(30) NOT NULL,
  `tanggal`      date NOT NULL,
  `jenis`        enum('masuk','keluar','mutasi_antar_kas') NOT NULL,
  `id_kas_bank`  bigint(20) UNSIGNED NOT NULL,
  `id_kas_bank_tujuan` bigint(20) UNSIGNED DEFAULT NULL,
  `kode_akun_lawan` varchar(10) DEFAULT NULL COMMENT 'akun biaya/pendapatan lawan',
  `nilai`        decimal(18,2) NOT NULL,
  `keterangan`   text DEFAULT NULL,
  `status_posting` enum('F','T') NOT NULL DEFAULT 'F',
  `id_jurnal`    bigint(20) UNSIGNED DEFAULT NULL,
  `created_by`   bigint(20) UNSIGNED DEFAULT NULL,
  `created_at`   timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_kas_trx`),
  UNIQUE KEY `kastrx_kode_unik` (`id_koperasi`,`kode_trx`),
  CONSTRAINT `kastrx_koperasi_fk` FOREIGN KEY (`id_koperasi`) REFERENCES `koperasi_desa`(`id_koperasi`),
  CONSTRAINT `kastrx_kasbank_fk`  FOREIGN KEY (`id_kas_bank`) REFERENCES `master_kas_bank`(`id_kas_bank`),
  CONSTRAINT `kastrx_tujuan_fk`   FOREIGN KEY (`id_kas_bank_tujuan`) REFERENCES `master_kas_bank`(`id_kas_bank`),
  CONSTRAINT `kastrx_akun_fk`     FOREIGN KEY (`kode_akun_lawan`) REFERENCES `master_coa`(`kode_anak`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Simpanan anggota (wajib untuk badan hukum koperasi)
CREATE TABLE `simpanan_anggota` (
  `id_simpanan`  bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_koperasi`  bigint(20) UNSIGNED NOT NULL,
  `id_pihak`     bigint(20) UNSIGNED NOT NULL COMMENT 'master_pihak dengan is_anggota = 1',
  `jenis`        enum('pokok','wajib','sukarela') NOT NULL,
  `tanggal`      date NOT NULL,
  `arah`         enum('setor','tarik') NOT NULL,
  `nilai`        decimal(18,2) NOT NULL,
  `id_kas_bank`  bigint(20) UNSIGNED NOT NULL,
  `status_posting` enum('F','T') NOT NULL DEFAULT 'F',
  `id_jurnal`    bigint(20) UNSIGNED DEFAULT NULL,
  `created_at`   timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_simpanan`),
  KEY `simpanan_lookup_idx` (`id_koperasi`,`id_pihak`,`jenis`),
  CONSTRAINT `simpanan_koperasi_fk` FOREIGN KEY (`id_koperasi`) REFERENCES `koperasi_desa`(`id_koperasi`),
  CONSTRAINT `simpanan_pihak_fk`    FOREIGN KEY (`id_pihak`)    REFERENCES `master_pihak`(`id_pihak`),
  CONSTRAINT `simpanan_kasbank_fk`  FOREIGN KEY (`id_kas_bank`) REFERENCES `master_kas_bank`(`id_kas_bank`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- CATATAN: simpanan pokok & wajib -> MODAL (akun 311/312).
--          simpanan sukarela      -> KEWAJIBAN (akun 2115), karena bisa ditarik.

-- Aset tetap (prasyarat jurnal penyusutan)
CREATE TABLE `aset_tetap` (
  `id_aset`          bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_koperasi`      bigint(20) UNSIGNED NOT NULL,
  `kode_aset`        varchar(30) NOT NULL,
  `nama_aset`        varchar(150) NOT NULL,
  `kategori`         enum('tanah','gedung','kendaraan','peralatan') NOT NULL,
  `tgl_perolehan`    date NOT NULL,
  `nilai_perolehan`  decimal(18,2) NOT NULL,
  `nilai_residu`     decimal(18,2) NOT NULL DEFAULT 0,
  `umur_bulan`       int NOT NULL DEFAULT 0 COMMENT '0 untuk tanah (tidak disusutkan)',
  `akum_penyusutan`  decimal(18,2) NOT NULL DEFAULT 0,
  `kode_akun_aset`   varchar(10) NOT NULL,
  `kode_akun_akum`   varchar(10) DEFAULT NULL,
  `kode_akun_biaya`  varchar(10) DEFAULT NULL,
  `status`           enum('aktif','habis_susut','dilepas') NOT NULL DEFAULT 'aktif',
  PRIMARY KEY (`id_aset`),
  UNIQUE KEY `aset_kode_unik` (`id_koperasi`,`kode_aset`),
  CONSTRAINT `aset_koperasi_fk` FOREIGN KEY (`id_koperasi`) REFERENCES `koperasi_desa`(`id_koperasi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- BAGIAN I — AKUNTANSI
-- ============================================================

CREATE TABLE `master_coa` (
  `kode_anak`      varchar(10) NOT NULL,
  `kode_induk`     varchar(10) DEFAULT NULL,
  `nama_rekening`  varchar(150) NOT NULL,
  `posisi_normal`  enum('D','K') NOT NULL,
  `is_transaction` enum('T','F') NOT NULL DEFAULT 'T',
  `kelompok`       enum('Aktiva','Kewajiban','Modal','Pendapatan','HPP','Biaya','Non-Operasional','Ikhtisar') NOT NULL,
  `is_kontra`      tinyint(1) NOT NULL DEFAULT 0,
  `level`          tinyint(2) UNSIGNED NOT NULL DEFAULT 1,
  `urutan_laporan` int NOT NULL DEFAULT 0,
  `is_active`      tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`kode_anak`),
  KEY `coa_induk_idx` (`kode_induk`),
  CONSTRAINT `coa_induk_fk` FOREIGN KEY (`kode_induk`) REFERENCES `master_coa`(`kode_anak`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- COA bersifat GLOBAL (dipakai semua desa). Yang bertenant adalah SALDO-nya.
-- Alasan: kalau tiap desa punya COA sendiri, laporan konsolidasi tingkat
-- kecamatan tidak bisa dibuat tanpa tabel pemetaan tambahan.
-- PERUBAHAN: kolom kelompok diubah dari varchar bebas ke enum; is_kontra dan
-- urutan_laporan ditambahkan agar akun kontra (Retur Penjualan, Akumulasi
-- Penyusutan) bisa dikurangkan otomatis, bukan lewat if-else di kode laporan.

CREATE TABLE `master_transaksi` (
  `kode_transaksi` varchar(10) NOT NULL,
  `nama_transaksi` varchar(100) NOT NULL,
  `modul`          varchar(30) NOT NULL,
  `is_active`      tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`kode_transaksi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `master_detail_transaksi` (
  `id_master`       bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode_transaksi`  varchar(10) NOT NULL,
  `urutan`          tinyint(3) UNSIGNED NOT NULL,
  `kode_anak`       varchar(10) DEFAULT NULL COMMENT 'NULL = akun dinamis, lihat akun_dinamis',
  `akun_dinamis`    enum('KAS_BANK','PERSEDIAAN_UNIT','PENDAPATAN_UNIT','HPP_UNIT') DEFAULT NULL,
  `posisi`          enum('D','K') NOT NULL,
  `sumber_variabel` varchar(50) NOT NULL COMMENT 'key di payload: total_bayar, total_hpp, dst',
  `is_optional`     tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_master`),
  UNIQUE KEY `mdt_urutan_unik` (`kode_transaksi`,`urutan`),
  CONSTRAINT `mdt_transaksi_fk` FOREIGN KEY (`kode_transaksi`) REFERENCES `master_transaksi`(`kode_transaksi`),
  CONSTRAINT `mdt_akun_fk`      FOREIGN KEY (`kode_anak`)      REFERENCES `master_coa`(`kode_anak`),
  CONSTRAINT `mdt_akun_terisi`  CHECK ((`kode_anak` IS NULL) <> (`akun_dinamis` IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- PERUBAHAN BESAR: kolom id_unit_usaha diganti kolom akun_dinamis.
-- Dulu Anda menduplikasi baris mapping per unit usaha (4111 Sembako, 4112 Apotek).
-- Setiap unit usaha baru berarti +2 baris di setiap kode transaksi, dan seed
-- Anda menghasilkan D=1x lawan K=2x kalau filter unit terlewat.
-- Sekarang cukup 1 baris; mesin jurnal menerjemahkan PENDAPATAN_UNIT menjadi
-- akun konkret lewat master_unit_usaha.

-- --- JURNAL: header + detail (menggantikan tabel `jurnals` flat) ---

CREATE TABLE `jurnal_header` (
  `id_jurnal`       bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_koperasi`     bigint(20) UNSIGNED NOT NULL,
  `no_jurnal`       varchar(30) NOT NULL,
  `nomor_nota`      varchar(30) DEFAULT NULL,
  `tanggal_jurnal`  date NOT NULL,
  `periode_tahun`   year NOT NULL,
  `periode_bulan`   tinyint(2) UNSIGNED NOT NULL,
  `kode_transaksi`  varchar(10) DEFAULT NULL,
  `jenis_jurnal`    enum('OTOMATIS','MANUAL','PENYESUAIAN','PEMBALIK','SALDO_AWAL','PENUTUP') NOT NULL DEFAULT 'OTOMATIS',
  `source_type`     varchar(40) DEFAULT NULL,
  `source_id`       bigint(20) UNSIGNED DEFAULT NULL,
  `keterangan`      text DEFAULT NULL,
  `total_debet`     decimal(18,2) NOT NULL DEFAULT 0,
  `total_kredit`    decimal(18,2) NOT NULL DEFAULT 0,
  `status`          enum('DRAFT','POSTED','REVERSED') NOT NULL DEFAULT 'DRAFT',
  `id_jurnal_asal`  bigint(20) UNSIGNED DEFAULT NULL,
  `created_by`      bigint(20) UNSIGNED DEFAULT NULL,
  `posted_by`       bigint(20) UNSIGNED DEFAULT NULL,
  `posted_at`       datetime DEFAULT NULL,
  `created_at`      timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_jurnal`),
  UNIQUE KEY `jurnal_no_unik` (`id_koperasi`,`no_jurnal`),
  UNIQUE KEY `jurnal_idempoten` (`id_koperasi`,`source_type`,`source_id`,`jenis_jurnal`),
  KEY `jurnal_periode_idx` (`id_koperasi`,`periode_tahun`,`periode_bulan`,`status`),
  CONSTRAINT `jh_koperasi_fk` FOREIGN KEY (`id_koperasi`) REFERENCES `koperasi_desa`(`id_koperasi`),
  CONSTRAINT `jh_asal_fk`     FOREIGN KEY (`id_jurnal_asal`) REFERENCES `jurnal_header`(`id_jurnal`),
  CONSTRAINT `jh_transaksi_fk` FOREIGN KEY (`kode_transaksi`) REFERENCES `master_transaksi`(`kode_transaksi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- UNIQUE jurnal_idempoten mencegah satu dokumen penjualan menghasilkan
-- dua jurnal kalau tombol posting ditekan dua kali.

CREATE TABLE `jurnal_detail` (
  `id_detail`   bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_jurnal`   bigint(20) UNSIGNED NOT NULL,
  `urutan`      tinyint(3) UNSIGNED NOT NULL,
  `kode_anak`   varchar(10) NOT NULL,
  `debet`       decimal(18,2) NOT NULL DEFAULT 0,
  `kredit`      decimal(18,2) NOT NULL DEFAULT 0,
  `keterangan`  varchar(255) DEFAULT NULL,
  `id_pihak`    bigint(20) UNSIGNED DEFAULT NULL COMMENT 'utk akun piutang/hutang, jejak buku pembantu',
  PRIMARY KEY (`id_detail`),
  KEY `jd_jurnal_idx` (`id_jurnal`),
  KEY `jd_akun_idx` (`kode_anak`),
  CONSTRAINT `jd_jurnal_fk` FOREIGN KEY (`id_jurnal`) REFERENCES `jurnal_header`(`id_jurnal`),
  CONSTRAINT `jd_akun_fk`   FOREIGN KEY (`kode_anak`) REFERENCES `master_coa`(`kode_anak`),
  CONSTRAINT `jd_satu_sisi` CHECK ((`debet` = 0 AND `kredit` > 0) OR (`debet` > 0 AND `kredit` = 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- PERUBAHAN: kolom `saldo` di tabel jurnals DIHAPUS.
-- Saldo berjalan adalah angka turunan. Satu jurnal yang disisipkan mundur
-- tanggal akan membuat seluruh kolom saldo di bawahnya salah, dan tidak ada
-- cara mendeteksinya. Saldo dihitung di buku_besar_periode / query laporan.

-- Ringkasan saldo per akun per bulan. Bisa dibangun ulang kapan saja dari jurnal.
CREATE TABLE `buku_besar_periode` (
  `id_koperasi`       bigint(20) UNSIGNED NOT NULL,
  `periode_tahun`     year NOT NULL,
  `periode_bulan`     tinyint(2) UNSIGNED NOT NULL,
  `kode_anak`         varchar(10) NOT NULL,
  `saldo_awal_debet`  decimal(18,2) NOT NULL DEFAULT 0,
  `saldo_awal_kredit` decimal(18,2) NOT NULL DEFAULT 0,
  `mutasi_debet`      decimal(18,2) NOT NULL DEFAULT 0,
  `mutasi_kredit`     decimal(18,2) NOT NULL DEFAULT 0,
  `saldo_akhir_debet` decimal(18,2) NOT NULL DEFAULT 0,
  `saldo_akhir_kredit` decimal(18,2) NOT NULL DEFAULT 0,
  `dihitung_pada`     datetime DEFAULT NULL,
  PRIMARY KEY (`id_koperasi`,`periode_tahun`,`periode_bulan`,`kode_anak`),
  CONSTRAINT `bbp_koperasi_fk` FOREIGN KEY (`id_koperasi`) REFERENCES `koperasi_desa`(`id_koperasi`),
  CONSTRAINT `bbp_akun_fk`     FOREIGN KEY (`kode_anak`)   REFERENCES `master_coa`(`kode_anak`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- PERUBAHAN: tabel `buku_besar` dan `neraca` Anda DIGABUNG jadi satu tabel ini.
-- Alasan:
--  1. `neraca` per-tahun tidak bisa memenuhi permintaan Anda "neraca & laba rugi
--     dipantau setiap saat, periode bulanan".
--  2. Keduanya tidak punya UNIQUE key, sehingga menjalankan ulang proses tutup
--     bulan akan menggandakan baris tanpa peringatan.
--  3. Keduanya tidak punya kolom saldo_awal, sehingga rumus
--     "saldo awal + mutasi = saldo akhir" tidak bisa diverifikasi.
-- Neraca dan Laba Rugi sekarang adalah HASIL FILTER dari tabel ini berdasarkan
-- master_coa.kelompok, bukan tabel terpisah.

CREATE TABLE `audit_log` (
  `id_log`      bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_koperasi` bigint(20) UNSIGNED DEFAULT NULL,
  `id_pengguna` bigint(20) UNSIGNED DEFAULT NULL,
  `tabel`       varchar(50) NOT NULL,
  `record_id`   bigint(20) UNSIGNED NOT NULL,
  `aksi`        enum('INSERT','UPDATE','DELETE','POST','REVERSE','APPROVE') NOT NULL,
  `data_lama`   longtext DEFAULT NULL,
  `data_baru`   longtext DEFAULT NULL,
  `ip_address`  varchar(45) DEFAULT NULL,
  `created_at`  timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_log`),
  KEY `audit_lookup_idx` (`tabel`,`record_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- SEED: master_coa  (SUDAH DIRAPIKAN, NON-PKP)
-- ============================================================
-- Perubahan penomoran dari COA lama Anda:
--  * HPP dipindah dari '43' (anak dari 4 PENDAPATAN) menjadi kelompok '5'.
--    Alasan: menjumlahkan anak-anak node '4' dulu menghasilkan
--    (Pendapatan - HPP), angka yang tidak berarti apa-apa di laporan mana pun.
--  * BIAYA digeser dari '5' ke '6'.
--  * Akun PPN Masukan/Keluaran DIHAPUS (non-PKP).
--  * 'PPN Keluaran' dan 'Hutang PPh' yang dulu ditaruh di bawah 114
--    (Aktiva - Pajak Dibayar Dimuka) padahal posisinya K: itu membuat
--    total Aktiva di Neraca salah. Sekarang dipindah ke kelompok 2 KEWAJIBAN.
--  * 'Retur Pembelian' & 'Potongan Pembelian' DIHAPUS dari kelompok Pendapatan.
--    Pada metode perpetual moving average, retur & potongan pembelian
--    mengurangi nilai Persediaan langsung, bukan menambah Pendapatan.
--  * 'Penyusutan Gedung/Komputer/Kendaraan' diganti nama jadi 'Akumulasi
--    Penyusutan ...' dan diberi PASANGAN akun biaya di 63x. Tanpa akun biaya,
--    jurnal penyusutan tidak punya sisi debit dan mustahil dibuat.
--  * Ditambah: simpanan pokok/wajib/sukarela, cadangan umum, dana-dana SHU,
--    biaya kerugian persediaan, penyisihan piutang, ikhtisar laba rugi.

INSERT INTO `master_coa`
(`kode_anak`,`kode_induk`,`nama_rekening`,`posisi_normal`,`is_transaction`,`kelompok`,`is_kontra`,`level`) VALUES
-- 1 AKTIVA
('1',    NULL,  'AKTIVA',                              'D','F','Aktiva',0,1),
('11',   '1',   'Aktiva Lancar',                       'D','F','Aktiva',0,2),
('111',  '11',  'Kas & Bank',                          'D','F','Aktiva',0,3),
('1111', '111', 'Kas',                                 'D','T','Aktiva',0,4),
('1112', '111', 'Bank',                                'D','F','Aktiva',0,4),
('11121','1112','Bank A',                              'D','T','Aktiva',0,5),
('11122','1112','Bank B',                              'D','T','Aktiva',0,5),
('11123','1112','Bank C',                              'D','T','Aktiva',0,5),
('112',  '11',  'Persediaan',                          'D','F','Aktiva',0,3),
('1121', '112', 'Persediaan Bahan Mentah',             'D','T','Aktiva',0,4),
('1122', '112', 'Persediaan Bahan Setengah Jadi',      'D','T','Aktiva',0,4),
('1123', '112', 'Persediaan Barang Jadi - Sembako',    'D','T','Aktiva',0,4),
('1124', '112', 'Persediaan Barang Jadi - Apotek',     'D','T','Aktiva',0,4),
('1125', '112', 'Persediaan Dalam Perjalanan',         'D','T','Aktiva',0,4),
('113',  '11',  'Piutang',                             'D','F','Aktiva',0,3),
('1131', '113', 'Piutang Karyawan',                    'D','T','Aktiva',0,4),
('1132', '113', 'Piutang Dagang',                      'D','T','Aktiva',0,4),
('1133', '113', 'Piutang Antar Desa',                  'D','T','Aktiva',0,4),
('1134', '113', 'Penyisihan Piutang Tak Tertagih',     'K','T','Aktiva',1,4),
('114',  '11',  'Uang Muka & Beban Dibayar Dimuka',    'D','F','Aktiva',0,3),
('1141', '114', 'Uang Muka Pembelian',                 'D','T','Aktiva',0,4),
('1142', '114', 'Beban Dibayar Dimuka',                'D','T','Aktiva',0,4),
('12',   '1',   'Aktiva Tetap',                        'D','F','Aktiva',0,2),
('121',  '12',  'Harga Perolehan',                     'D','F','Aktiva',0,3),
('1211', '121', 'Tanah',                               'D','T','Aktiva',0,4),
('1212', '121', 'Gedung & Bangunan',                   'D','T','Aktiva',0,4),
('1213', '121', 'Kendaraan',                           'D','T','Aktiva',0,4),
('1214', '121', 'Peralatan & Komputer',                'D','T','Aktiva',0,4),
('122',  '12',  'Akumulasi Penyusutan',                'K','F','Aktiva',1,3),
('1222', '122', 'Akumulasi Penyusutan Gedung',         'K','T','Aktiva',1,4),
('1223', '122', 'Akumulasi Penyusutan Kendaraan',      'K','T','Aktiva',1,4),
('1224', '122', 'Akumulasi Penyusutan Peralatan',      'K','T','Aktiva',1,4),
-- 2 KEWAJIBAN
('2',    NULL,  'KEWAJIBAN',                           'K','F','Kewajiban',0,1),
('21',   '2',   'Kewajiban Jangka Pendek',             'K','F','Kewajiban',0,2),
('2111', '21',  'Hutang Dagang',                       'K','T','Kewajiban',0,3),
('2112', '21',  'Hutang Antar Desa',                   'K','T','Kewajiban',0,3),
('2113', '21',  'Hutang Biaya (Akrual)',               'K','T','Kewajiban',0,3),
('2114', '21',  'Hutang Pajak (PPh)',                  'K','T','Kewajiban',0,3),
('2115', '21',  'Simpanan Sukarela Anggota',           'K','T','Kewajiban',0,3),
('2116', '21',  'Dana Pembagian SHU Belum Dibayar',    'K','T','Kewajiban',0,3),
('22',   '2',   'Kewajiban Jangka Panjang',            'K','F','Kewajiban',0,2),
('2211', '22',  'Hutang Bank',                         'K','T','Kewajiban',0,3),
-- 3 MODAL
('3',    NULL,  'MODAL',                               'K','F','Modal',0,1),
('31',   '3',   'Simpanan Anggota',                    'K','F','Modal',0,2),
('311',  '31',  'Simpanan Pokok',                      'K','T','Modal',0,3),
('312',  '31',  'Simpanan Wajib',                      'K','T','Modal',0,3),
('32',   '3',   'Modal Penyertaan & Hibah',            'K','F','Modal',0,2),
('321',  '32',  'Modal Penyertaan',                    'K','T','Modal',0,3),
('322',  '32',  'Modal Hibah / Bantuan Pemerintah',    'K','T','Modal',0,3),
('33',   '3',   'Cadangan',                            'K','F','Modal',0,2),
('331',  '33',  'Cadangan Umum',                       'K','T','Modal',0,3),
('332',  '33',  'Dana Pendidikan',                     'K','T','Modal',0,3),
('333',  '33',  'Dana Sosial',                         'K','T','Modal',0,3),
('334',  '33',  'Dana Pengurus & Pengawas',            'K','T','Modal',0,3),
('34',   '3',   'Sisa Hasil Usaha',                    'K','F','Modal',0,2),
('341',  '34',  'SHU Tahun Berjalan',                  'K','T','Modal',0,3),
('342',  '34',  'SHU Ditahan (Tahun Lalu)',            'K','T','Modal',0,3),
-- 4 PENDAPATAN
('4',    NULL,  'PENDAPATAN',                          'K','F','Pendapatan',0,1),
('41',   '4',   'Pendapatan Penjualan',                'K','F','Pendapatan',0,2),
('411',  '41',  'Penjualan Sembako - Anggota',         'K','T','Pendapatan',0,3),
('412',  '41',  'Penjualan Sembako - Non Anggota',     'K','T','Pendapatan',0,3),
('413',  '41',  'Penjualan Apotek - Anggota',          'K','T','Pendapatan',0,3),
('414',  '41',  'Penjualan Apotek - Non Anggota',      'K','T','Pendapatan',0,3),
('415',  '41',  'Penjualan Antar Desa',                'K','T','Pendapatan',0,3),
('42',   '4',   'Pengurang Pendapatan',                'D','F','Pendapatan',1,2),
('421',  '42',  'Retur Penjualan',                     'D','T','Pendapatan',1,3),
('422',  '42',  'Diskon Penjualan',                    'D','T','Pendapatan',1,3),
-- 5 HPP
('5',    NULL,  'HARGA POKOK PENJUALAN',               'D','F','HPP',0,1),
('51',   '5',   'Harga Pokok Penjualan',               'D','F','HPP',0,2),
('511',  '51',  'HPP Sembako',                         'D','T','HPP',0,3),
('512',  '51',  'HPP Apotek',                          'D','T','HPP',0,3),
('513',  '51',  'HPP Penjualan Antar Desa',            'D','T','HPP',0,3),
-- 6 BIAYA OPERASIONAL
('6',    NULL,  'BIAYA OPERASIONAL',                   'D','F','Biaya',0,1),
('61',   '6',   'Biaya Personalia',                    'D','F','Biaya',0,2),
('611',  '61',  'Gaji',                                'D','T','Biaya',0,3),
('612',  '61',  'Tunjangan',                           'D','T','Biaya',0,3),
('62',   '6',   'Biaya Kantor & Operasional',          'D','F','Biaya',0,2),
('621',  '62',  'Biaya Kantor',                        'D','T','Biaya',0,3),
('622',  '62',  'Telepon & Internet',                  'D','T','Biaya',0,3),
('623',  '62',  'Listrik',                             'D','T','Biaya',0,3),
('624',  '62',  'Air (PAM)',                           'D','T','Biaya',0,3),
('625',  '62',  'Bahan Bakar & Transport',             'D','T','Biaya',0,3),
('626',  '62',  'Konsumsi',                            'D','T','Biaya',0,3),
('63',   '6',   'Biaya Penyusutan',                    'D','F','Biaya',0,2),
('631',  '63',  'Biaya Penyusutan Gedung',             'D','T','Biaya',0,3),
('632',  '63',  'Biaya Penyusutan Kendaraan',          'D','T','Biaya',0,3),
('633',  '63',  'Biaya Penyusutan Peralatan',          'D','T','Biaya',0,3),
('64',   '6',   'Biaya Kerugian & Penyisihan',         'D','F','Biaya',0,2),
('641',  '64',  'Biaya Kerusakan & Susut Persediaan',  'D','T','Biaya',0,3),
('642',  '64',  'Biaya Penyisihan Piutang',            'D','T','Biaya',0,3),
('65',   '6',   'Biaya Lain-lain',                     'D','F','Biaya',0,2),
('651',  '65',  'Biaya Administrasi Bank',             'D','T','Biaya',0,3),
('652',  '65',  'Biaya Rapat & Entertain',             'D','T','Biaya',0,3),
-- 7 NON-OPERASIONAL
('7',    NULL,  'PENDAPATAN & BIAYA NON-OPERASIONAL',  'K','F','Non-Operasional',0,1),
('71',   '7',   'Pendapatan Non-Operasional',          'K','F','Non-Operasional',0,2),
('711',  '71',  'Pendapatan Lain-lain',                'K','T','Non-Operasional',0,3),
('712',  '71',  'Selisih Lebih Stok Opname',           'K','T','Non-Operasional',0,3),
('72',   '7',   'Biaya Non-Operasional',               'D','F','Non-Operasional',0,2),
('721',  '72',  'Beban Bunga',                         'D','T','Non-Operasional',0,3),
('722',  '72',  'Beban Pajak Penghasilan',             'D','T','Non-Operasional',0,3),
-- 8 IKHTISAR (hanya dipakai saat tutup buku)
('8',    NULL,  'IKHTISAR LABA RUGI',                  'K','F','Ikhtisar',0,1),
('811',  '8',   'Ikhtisar Laba Rugi',                  'K','T','Ikhtisar',0,2);


-- ============================================================
-- SEED: master_unit_usaha (dengan pemetaan akun)
-- ============================================================
INSERT INTO `master_unit_usaha`
(`kode_unit_usaha`,`nama_unit_usaha`,`kode_akun_persediaan`,`kode_akun_pendapatan_anggota`,`kode_akun_pendapatan_non_anggota`,`kode_akun_hpp`) VALUES
('SMB','Sembako','1123','411','412','511'),
('APT','Apotek', '1124','413','414','512');


-- ============================================================
-- SEED: master_transaksi
-- ============================================================
-- PERUBAHAN: JKW (Jual Kredit Warga) DIHAPUS.
-- Anda menyatakan penjualan kredit hanya diizinkan antar desa dan ke warga
-- selalu tunai. Membiarkan kode JKW tetap ada berarti membiarkan pintu
-- terbuka untuk transaksi yang menurut kebijakan Anda sendiri tidak boleh terjadi.
INSERT INTO `master_transaksi` (`kode_transaksi`,`nama_transaksi`,`modul`) VALUES
('JTW',  'Jual Tunai Warga',            'penjualan'),
('JTFW', 'Jual Transfer Warga',         'penjualan'),
('JTD',  'Jual Tunai Desa',             'penjualan'),
('JTFD', 'Jual Transfer Desa',          'penjualan'),
('JKD',  'Jual Kredit Antar Desa',      'antar_desa'),
('BTU',  'Beli Tunai',                  'pembelian'),
('BTF',  'Beli Transfer',               'pembelian'),
('BKR',  'Beli Kredit',                 'pembelian'),
('BPT',  'Beli Tunai dari Petani',      'pembelian'),
('TAD',  'Terima Barang Antar Desa',    'antar_desa'),
('RBU',  'Retur Beli - Diganti Uang',   'pembelian'),
('RBH',  'Retur Beli - Potong Hutang',  'pembelian'),
('RJU',  'Retur Jual - Diganti Uang',   'penjualan'),
('RJP',  'Retur Jual - Potong Piutang', 'penjualan'),
('TPI',  'Terima Pelunasan Piutang',    'keuangan'),
('BHU',  'Bayar Hutang',                'keuangan'),
('OFS',  'Offset Piutang vs Hutang',    'keuangan'),
('KSM',  'Kas Masuk Lain',              'keuangan'),
('KSK',  'Kas Keluar Lain',             'keuangan'),
('SPK',  'Setor Simpanan Pokok',        'keanggotaan'),
('SWJ',  'Setor Simpanan Wajib',        'keanggotaan'),
('SSK',  'Setor Simpanan Sukarela',     'keanggotaan'),
('TSK',  'Tarik Simpanan Sukarela',     'keanggotaan'),
('KRG',  'Kerugian Persediaan',         'gudang'),
('OPK',  'Opname - Selisih Kurang',     'gudang'),
('OPL',  'Opname - Selisih Lebih',      'gudang'),
('PNY',  'Penyusutan Aset Tetap',       'penyesuaian');


-- ============================================================
-- SEED: master_detail_transaksi  (INI YANG PALING BANYAK BERUBAH)
-- ============================================================
-- Setiap kode transaksi penjualan sekarang punya EMPAT baris:
-- sepasang untuk sisi uang, sepasang untuk sisi barang.
-- Tanpa pasangan HPP/Persediaan, laporan Laba Rugi menunjukkan margin 100%
-- dan nilai Persediaan di Neraca tidak pernah berkurang.

INSERT INTO `master_detail_transaksi`
(`kode_transaksi`,`urutan`,`kode_anak`,`akun_dinamis`,`posisi`,`sumber_variabel`) VALUES
-- JTW: Jual Tunai Warga
('JTW',1,'1111',NULL,             'D','total_bayar'),
('JTW',2, NULL,'PENDAPATAN_UNIT', 'K','total_bayar'),
('JTW',3, NULL,'HPP_UNIT',        'D','total_hpp'),
('JTW',4, NULL,'PERSEDIAAN_UNIT', 'K','total_hpp'),
-- JTFW: Jual Transfer Warga  (kini bisa di-seed karena ada master_kas_bank)
('JTFW',1,NULL,'KAS_BANK',        'D','total_bayar'),
('JTFW',2,NULL,'PENDAPATAN_UNIT', 'K','total_bayar'),
('JTFW',3,NULL,'HPP_UNIT',        'D','total_hpp'),
('JTFW',4,NULL,'PERSEDIAAN_UNIT', 'K','total_hpp'),
-- JTD: Jual Tunai Desa
('JTD',1,'1111',NULL,             'D','total_bayar'),
('JTD',2,'415', NULL,             'K','total_bayar'),
('JTD',3,'513', NULL,             'D','total_hpp'),
('JTD',4, NULL,'PERSEDIAAN_UNIT', 'K','total_hpp'),
-- JTFD: Jual Transfer Desa
('JTFD',1,NULL,'KAS_BANK',        'D','total_bayar'),
('JTFD',2,'415',NULL,             'K','total_bayar'),
('JTFD',3,'513',NULL,             'D','total_hpp'),
('JTFD',4,NULL,'PERSEDIAAN_UNIT', 'K','total_hpp'),
-- JKD: Jual Kredit Antar Desa (jurnal di pembukuan DESA PENGIRIM)
('JKD',1,'1133',NULL,             'D','total_bayar'),
('JKD',2,'415', NULL,             'K','total_bayar'),
('JKD',3,'513', NULL,             'D','total_hpp'),
('JKD',4, NULL,'PERSEDIAAN_UNIT', 'K','total_hpp'),
-- TAD: Terima Barang Antar Desa (jurnal di pembukuan DESA PENERIMA)
('TAD',1,NULL,'PERSEDIAAN_UNIT',  'D','total_nilai'),
('TAD',2,'2112',NULL,             'K','total_nilai'),
-- Pembelian
('BTU',1,NULL,'PERSEDIAAN_UNIT',  'D','total_pembelian'),
('BTU',2,'1111',NULL,             'K','total_pembelian'),
('BTF',1,NULL,'PERSEDIAAN_UNIT',  'D','total_pembelian'),
('BTF',2,NULL,'KAS_BANK',         'K','total_pembelian'),
('BKR',1,NULL,'PERSEDIAAN_UNIT',  'D','total_pembelian'),
('BKR',2,'2111',NULL,             'K','total_pembelian'),
('BPT',1,NULL,'PERSEDIAAN_UNIT',  'D','total_pembelian'),
('BPT',2,'1111',NULL,             'K','total_pembelian'),
-- Retur pembelian
('RBU',1,'1111',NULL,             'D','total_nilai'),
('RBU',2,NULL,'PERSEDIAAN_UNIT',  'K','total_nilai'),
('RBH',1,'2111',NULL,             'D','total_nilai'),
('RBH',2,NULL,'PERSEDIAAN_UNIT',  'K','total_nilai'),
-- Retur penjualan (pakai akun kontra 421, BUKAN mendebit Pendapatan langsung)
('RJU',1,'421', NULL,             'D','total_nilai'),
('RJU',2,'1111',NULL,             'K','total_nilai'),
('RJU',3,NULL,'PERSEDIAAN_UNIT',  'D','total_hpp'),
('RJU',4,NULL,'HPP_UNIT',         'K','total_hpp'),
('RJP',1,'421', NULL,             'D','total_nilai'),
('RJP',2,'1132',NULL,             'K','total_nilai'),
('RJP',3,NULL,'PERSEDIAAN_UNIT',  'D','total_hpp'),
('RJP',4,NULL,'HPP_UNIT',         'K','total_hpp'),
-- Pelunasan
('TPI',1,NULL,'KAS_BANK',         'D','total_nilai'),
('TPI',2,'1132',NULL,             'K','total_nilai'),
('BHU',1,'2111',NULL,             'D','total_nilai'),
('BHU',2,NULL,'KAS_BANK',         'K','total_nilai'),
('OFS',1,'2112',NULL,             'D','total_nilai'),
('OFS',2,'1133',NULL,             'K','total_nilai'),
-- Simpanan anggota
('SPK',1,NULL,'KAS_BANK',         'D','total_nilai'),
('SPK',2,'311', NULL,             'K','total_nilai'),
('SWJ',1,NULL,'KAS_BANK',         'D','total_nilai'),
('SWJ',2,'312', NULL,             'K','total_nilai'),
('SSK',1,NULL,'KAS_BANK',         'D','total_nilai'),
('SSK',2,'2115',NULL,             'K','total_nilai'),
('TSK',1,'2115',NULL,             'D','total_nilai'),
('TSK',2,NULL,'KAS_BANK',         'K','total_nilai'),
-- Gudang
('KRG',1,'641', NULL,             'D','nilai_kerugian'),
('KRG',2,NULL,'PERSEDIAAN_UNIT',  'K','nilai_kerugian'),
('OPK',1,'641', NULL,             'D','nilai_selisih'),
('OPK',2,NULL,'PERSEDIAAN_UNIT',  'K','nilai_selisih'),
('OPL',1,NULL,'PERSEDIAAN_UNIT',  'D','nilai_selisih'),
('OPL',2,'712', NULL,             'K','nilai_selisih');


-- ============================================================
-- CONTOH PROSEDUR: posting jurnal dengan validasi
-- ============================================================
DELIMITER $$

CREATE PROCEDURE `sp_post_jurnal`(IN p_id_jurnal BIGINT UNSIGNED, IN p_user BIGINT UNSIGNED)
BEGIN
  DECLARE v_debet  DECIMAL(18,2);
  DECLARE v_kredit DECIMAL(18,2);
  DECLARE v_status VARCHAR(10);
  DECLARE v_periode VARCHAR(10);
  DECLARE v_kop BIGINT UNSIGNED;
  DECLARE v_th YEAR; DECLARE v_bl TINYINT;

  SELECT `status`,`id_koperasi`,`periode_tahun`,`periode_bulan`
    INTO v_status, v_kop, v_th, v_bl
  FROM `jurnal_header` WHERE `id_jurnal` = p_id_jurnal FOR UPDATE;

  IF v_status <> 'DRAFT' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Jurnal bukan DRAFT, tidak bisa diposting.';
  END IF;

  SELECT `status` INTO v_periode FROM `periode_akuntansi`
   WHERE `id_koperasi` = v_kop AND `tahun` = v_th AND `bulan` = v_bl;

  IF v_periode IS NULL OR v_periode <> 'OPEN' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Periode tidak terbuka.';
  END IF;

  SELECT COALESCE(SUM(`debet`),0), COALESCE(SUM(`kredit`),0)
    INTO v_debet, v_kredit
  FROM `jurnal_detail` WHERE `id_jurnal` = p_id_jurnal;

  IF v_debet <> v_kredit OR v_debet = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Jurnal tidak seimbang (Debet <> Kredit).';
  END IF;

  UPDATE `jurnal_header`
     SET `total_debet` = v_debet, `total_kredit` = v_kredit,
         `status` = 'POSTED', `posted_by` = p_user, `posted_at` = NOW()
   WHERE `id_jurnal` = p_id_jurnal;
END$$

-- Larang UPDATE dan DELETE pada jurnal yang sudah POSTED
CREATE TRIGGER `trg_jurnal_detail_no_update`
BEFORE UPDATE ON `jurnal_detail`
FOR EACH ROW
BEGIN
  DECLARE v_status VARCHAR(10);
  SELECT `status` INTO v_status FROM `jurnal_header` WHERE `id_jurnal` = OLD.`id_jurnal`;
  IF v_status <> 'DRAFT' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Detail jurnal POSTED tidak boleh diubah. Gunakan jurnal pembalik.';
  END IF;
END$$

CREATE TRIGGER `trg_jurnal_detail_no_delete`
BEFORE DELETE ON `jurnal_detail`
FOR EACH ROW
BEGIN
  DECLARE v_status VARCHAR(10);
  SELECT `status` INTO v_status FROM `jurnal_header` WHERE `id_jurnal` = OLD.`id_jurnal`;
  IF v_status <> 'DRAFT' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Detail jurnal POSTED tidak boleh dihapus.';
  END IF;
END$$

DELIMITER ;


-- ============================================================
-- VIEW: Neraca & Laba Rugi real-time (memenuhi permintaan
--       "dipantau setiap saat, periode bulanan")
-- ============================================================
CREATE VIEW `v_saldo_berjalan` AS
SELECT
  jh.`id_koperasi`,
  jh.`periode_tahun`,
  jh.`periode_bulan`,
  jd.`kode_anak`,
  c.`nama_rekening`,
  c.`kelompok`,
  c.`posisi_normal`,
  c.`is_kontra`,
  SUM(jd.`debet`)  AS mutasi_debet,
  SUM(jd.`kredit`) AS mutasi_kredit,
  CASE WHEN c.`posisi_normal` = 'D'
       THEN SUM(jd.`debet`) - SUM(jd.`kredit`)
       ELSE SUM(jd.`kredit`) - SUM(jd.`debet`)
  END AS saldo_normal
FROM `jurnal_header` jh
JOIN `jurnal_detail` jd ON jd.`id_jurnal` = jh.`id_jurnal`
JOIN `master_coa`    c  ON c.`kode_anak`  = jd.`kode_anak`
WHERE jh.`status` = 'POSTED'
GROUP BY jh.`id_koperasi`, jh.`periode_tahun`, jh.`periode_bulan`,
         jd.`kode_anak`, c.`nama_rekening`, c.`kelompok`, c.`posisi_normal`, c.`is_kontra`;

-- Rekonsiliasi antar desa: piutang di A harus = hutang di B.
-- Jalankan tiap tutup bulan. Hasil tidak kosong = ada data yang tidak sinkron.
CREATE VIEW `v_rekonsiliasi_antar_desa` AS
SELECT
  k.`id_kiriman`,
  k.`kode_kiriman`,
  k.`id_koperasi_pengirim`,
  k.`id_koperasi_penerima`,
  k.`total_nilai`,
  p.`sisa` AS sisa_piutang_pengirim,
  h.`sisa` AS sisa_hutang_penerima,
  (COALESCE(p.`sisa`,0) - COALESCE(h.`sisa`,0)) AS selisih
FROM `pengiriman_antar_desa` k
LEFT JOIN `piutang` p ON p.`sumber_tipe` = 'ANTAR_DESA' AND p.`sumber_id` = k.`id_kiriman`
LEFT JOIN `hutang`  h ON h.`sumber_tipe` = 'ANTAR_DESA' AND h.`sumber_id` = k.`id_kiriman`
WHERE COALESCE(p.`sisa`,0) <> COALESCE(h.`sisa`,0);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- CATATAN URUTAN EKSEKUSI
-- Karena ada FK melingkar (master_unit_usaha -> master_coa, dan
-- master_coa di-seed setelah tabel dibuat), skrip ini mengandalkan
-- SET FOREIGN_KEY_CHECKS = 0 di awal. Jalankan skrip secara utuh,
-- jangan sepotong-sepotong.
-- ============================================================
