-- ============================================================
-- STRUKTUR DATABASE LENGKAP — SISTEM KDMP
-- (Koperasi Desa Merah Putih)
-- ============================================================
-- Bagian A : Master Data Terpusat (dari app survey + master baru)
-- Bagian B : Sub-Tema 1 — Survey (diterjemahkan dari v2t_survey_desa)
-- Bagian C : Sub-Tema 2 — Hitung Kalkulasi & Barter
-- Bagian D : Sub-Tema 3 — Gudang
-- Bagian E : Sub-Tema 4 — Penjualan & Pembelian
-- Bagian F : Modul Keuangan Akuntansi (BARU — sesuai spesifikasi terbaru)
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- BAGIAN A — MASTER DATA TERPUSAT
-- ============================================================

CREATE TABLE `wilayah` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `tingkat` enum('prov','kab','kec','desa','dusun','rw','rt') NOT NULL,
  `nama` varchar(255) NOT NULL,
  `kode_bps` varchar(255) DEFAULT NULL,
  `lat` decimal(10,7) DEFAULT NULL,
  `lng` decimal(10,7) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wilayah_id_induk_foreign` (`parent_id`),
  CONSTRAINT `wilayah_id_induk_foreign` FOREIGN KEY (`parent_id`) REFERENCES `wilayah` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `komoditas` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kategori` varchar(30) NOT NULL,
  `nama` varchar(255) NOT NULL,
  `alias_json` longtext CHECK (json_valid(`alias_json`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `satuan` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode_satuan` varchar(20) NOT NULL,
  `alias_json` longtext CHECK (json_valid(`alias_json`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `master_unit_usaha` (
  `id_unit_usaha` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode_unit_usaha` varchar(20) NOT NULL,
  `nama_unit_usaha` varchar(100) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_unit_usaha`),
  UNIQUE KEY `master_unit_usaha_kode_unique` (`kode_unit_usaha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `master_barang` (
  `id_barang` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_komoditas` bigint(20) UNSIGNED NOT NULL,
  `id_unit_usaha` bigint(20) UNSIGNED NOT NULL,
  `id_satuan` bigint(20) UNSIGNED NOT NULL,
  `nilai_konversi` decimal(12,4) NOT NULL DEFAULT 1.0000,
  `satuan_dasar` varchar(20) NOT NULL,
  `stok_minimum` decimal(15,2) NOT NULL DEFAULT 0.00,
  `harga_beli_standar` decimal(15,2) NOT NULL DEFAULT 0.00,
  `harga_jual_standar` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_barang`),
  UNIQUE KEY `master_barang_id_komoditas_unique` (`id_komoditas`),
  KEY `master_barang_id_unit_usaha_foreign` (`id_unit_usaha`),
  KEY `master_barang_id_satuan_foreign` (`id_satuan`),
  CONSTRAINT `master_barang_id_komoditas_foreign` FOREIGN KEY (`id_komoditas`) REFERENCES `komoditas` (`id`),
  CONSTRAINT `master_barang_id_unit_usaha_foreign` FOREIGN KEY (`id_unit_usaha`) REFERENCES `master_unit_usaha` (`id_unit_usaha`),
  CONSTRAINT `master_barang_id_satuan_foreign` FOREIGN KEY (`id_satuan`) REFERENCES `satuan` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `master_pihak` (
  `id_pihak` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `jenis_pihak` enum('supplier','pelanggan_warga','pelanggan_desa') NOT NULL,
  `nama` varchar(255) NOT NULL,
  `alamat` text DEFAULT NULL,
  `no_hp` varchar(30) DEFAULT NULL,
  `id_wilayah` bigint(20) UNSIGNED DEFAULT NULL,
  `kualitas_rating` decimal(3,2) DEFAULT NULL,
  `estimasi_pengiriman` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_pihak`),
  KEY `master_pihak_id_wilayah_foreign` (`id_wilayah`),
  CONSTRAINT `master_pihak_id_wilayah_foreign` FOREIGN KEY (`id_wilayah`) REFERENCES `wilayah` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel: master_transaksi — daftar kode transaksi (JTW, JTFW, dst)
CREATE TABLE `master_transaksi` (
  `kode_transaksi` varchar(10) NOT NULL,
  `nama_transaksi` varchar(100) NOT NULL,
  PRIMARY KEY (`kode_transaksi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- ============================================================
-- BAGIAN B — SUB-TEMA 1: SURVEY
-- ============================================================

CREATE TABLE `pengguna` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'surveyor',
  `id_kecamatan` bigint(20) UNSIGNED DEFAULT NULL,
  `id_desa` bigint(20) UNSIGNED DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pengguna_email_unique` (`email`),
  KEY `pengguna_id_kecamatan_foreign` (`id_kecamatan`),
  KEY `pengguna_id_desa_foreign` (`id_desa`),
  CONSTRAINT `pengguna_id_kecamatan_foreign` FOREIGN KEY (`id_kecamatan`) REFERENCES `wilayah` (`id`),
  CONSTRAINT `pengguna_id_desa_foreign` FOREIGN KEY (`id_desa`) REFERENCES `wilayah` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `modul_survei` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode` varchar(255) NOT NULL,
  `nama` varchar(255) NOT NULL,
  `versi` varchar(255) NOT NULL DEFAULT 'v1',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `pertanyaan` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_modul` bigint(20) UNSIGNED NOT NULL,
  `kode_pertanyaan` varchar(255) NOT NULL,
  `teks_pertanyaan` text NOT NULL,
  `tipe_jawaban` enum('angka','teks','pilihan','pilihan_ganda','json','validasi_jumlah') NOT NULL,
  `satuan` varchar(20) DEFAULT NULL,
  `wajib_diisi` tinyint(1) NOT NULL DEFAULT 0,
  `aturan_validasi_json` longtext CHECK (json_valid(`aturan_validasi_json`)),
  `urutan` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pertanyaan_id_modul_foreign` (`id_modul`),
  CONSTRAINT `pertanyaan_id_modul_foreign` FOREIGN KEY (`id_modul`) REFERENCES `modul_survei` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `sesi_survei` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_petugas` bigint(20) UNSIGNED NOT NULL,
  `id_wilayah` bigint(20) UNSIGNED NOT NULL,
  `tanggal_survei` date NOT NULL,
  `status` enum('draft','terkirim','disetujui','ditolak') NOT NULL DEFAULT 'draft',
  `catatan` text DEFAULT NULL,
  `id_perangkat` varchar(255) DEFAULT NULL,
  `uuid_sesi_klien` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sesi_survei_id_petugas_foreign` (`id_petugas`),
  KEY `sesi_survei_id_wilayah_foreign` (`id_wilayah`),
  CONSTRAINT `sesi_survei_id_petugas_foreign` FOREIGN KEY (`id_petugas`) REFERENCES `pengguna` (`id`),
  CONSTRAINT `sesi_survei_id_wilayah_foreign` FOREIGN KEY (`id_wilayah`) REFERENCES `wilayah` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `jawaban` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_sesi` bigint(20) UNSIGNED NOT NULL,
  `id_modul` bigint(20) UNSIGNED NOT NULL,
  `id_pertanyaan` bigint(20) UNSIGNED NOT NULL,
  `nilai_angka` decimal(18,4) DEFAULT NULL,
  `nilai_teks` longtext DEFAULT NULL,
  `nilai_json` longtext CHECK (json_valid(`nilai_json`)),
  `satuan` varchar(20) DEFAULT NULL,
  `sumber` enum('suara','manual') NOT NULL DEFAULT 'suara',
  `tingkat_keyakinan` decimal(6,4) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `jawaban_sesi_pertanyaan_unique` (`id_sesi`,`id_pertanyaan`),
  KEY `jawaban_id_modul_foreign` (`id_modul`),
  KEY `jawaban_id_pertanyaan_foreign` (`id_pertanyaan`),
  CONSTRAINT `jawaban_id_sesi_foreign` FOREIGN KEY (`id_sesi`) REFERENCES `sesi_survei` (`id`),
  CONSTRAINT `jawaban_id_modul_foreign` FOREIGN KEY (`id_modul`) REFERENCES `modul_survei` (`id`),
  CONSTRAINT `jawaban_id_pertanyaan_foreign` FOREIGN KEY (`id_pertanyaan`) REFERENCES `pertanyaan` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `rekaman_suara` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_sesi` bigint(20) UNSIGNED NOT NULL,
  `id_modul` bigint(20) UNSIGNED NOT NULL,
  `path_audio` varchar(255) DEFAULT NULL,
  `teks_transkrip` longtext DEFAULT NULL,
  `penyedia_stt` varchar(50) DEFAULT NULL,
  `rata_keyakinan_stt` decimal(6,4) DEFAULT NULL,
  `durasi_detik` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rekaman_suara_id_sesi_foreign` (`id_sesi`),
  KEY `rekaman_suara_id_modul_foreign` (`id_modul`),
  CONSTRAINT `rekaman_suara_id_sesi_foreign` FOREIGN KEY (`id_sesi`) REFERENCES `sesi_survei` (`id`),
  CONSTRAINT `rekaman_suara_id_modul_foreign` FOREIGN KEY (`id_modul`) REFERENCES `modul_survei` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `kebutuhan_komoditas` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_wilayah` bigint(20) UNSIGNED NOT NULL,
  `id_sesi_survei` bigint(20) UNSIGNED DEFAULT NULL,
  `sektor` varchar(50) NOT NULL,
  `id_komoditas` bigint(20) UNSIGNED NOT NULL,
  `kelompok_umur` varchar(50) NOT NULL,
  `jumlah_penduduk` int(11) NOT NULL DEFAULT 0,
  `per_kapita_harian` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `kebutuhan_harian` decimal(15,2) NOT NULL DEFAULT 0.00,
  `kebutuhan_bulanan` decimal(15,2) NOT NULL DEFAULT 0.00,
  `kebutuhan_tahunan` decimal(15,2) NOT NULL DEFAULT 0.00,
  `satuan` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kebutuhan_komoditas_id_wilayah_foreign` (`id_wilayah`),
  KEY `kebutuhan_komoditas_id_komoditas_foreign` (`id_komoditas`),
  CONSTRAINT `kebutuhan_komoditas_id_wilayah_foreign` FOREIGN KEY (`id_wilayah`) REFERENCES `wilayah` (`id`),
  CONSTRAINT `kebutuhan_komoditas_id_komoditas_foreign` FOREIGN KEY (`id_komoditas`) REFERENCES `komoditas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `standar_kebutuhan_komoditas` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sektor` varchar(50) NOT NULL,
  `id_komoditas` bigint(20) UNSIGNED NOT NULL,
  `kelompok_umur` varchar(50) NOT NULL,
  `per_kapita_harian` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `satuan` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `standar_kebutuhan_id_komoditas_foreign` (`id_komoditas`),
  CONSTRAINT `standar_kebutuhan_id_komoditas_foreign` FOREIGN KEY (`id_komoditas`) REFERENCES `komoditas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `ketersediaan_komoditas` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_wilayah` bigint(20) UNSIGNED NOT NULL,
  `id_komoditas` bigint(20) UNSIGNED NOT NULL,
  `id_sesi_survei` bigint(20) UNSIGNED DEFAULT NULL,
  `ketersediaan_bulanan` decimal(15,2) NOT NULL DEFAULT 0.00,
  `ketersediaan_tahunan` decimal(15,2) NOT NULL DEFAULT 0.00,
  `satuan` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ketersediaan_id_wilayah_foreign` (`id_wilayah`),
  KEY `ketersediaan_id_komoditas_foreign` (`id_komoditas`),
  CONSTRAINT `ketersediaan_id_wilayah_foreign` FOREIGN KEY (`id_wilayah`) REFERENCES `wilayah` (`id`),
  CONSTRAINT `ketersediaan_id_komoditas_foreign` FOREIGN KEY (`id_komoditas`) REFERENCES `komoditas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- BAGIAN C — SUB-TEMA 2: HITUNG KALKULASI & BARTER
-- ============================================================

CREATE TABLE `hasil_kalkulasi` (
  `id_hasil` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_wilayah` bigint(20) UNSIGNED NOT NULL,
  `id_komoditas` bigint(20) UNSIGNED NOT NULL,
  `total_kebutuhan_tahunan` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_ketersediaan_tahunan` decimal(15,2) NOT NULL DEFAULT 0.00,
  `selisih` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status_surplus_defisit` enum('surplus','defisit','seimbang') NOT NULL,
  `persentase_kecukupan` decimal(6,2) NOT NULL DEFAULT 0.00,
  `id_unit_usaha_rekomendasi` bigint(20) UNSIGNED DEFAULT NULL,
  `alasan_rekomendasi` text DEFAULT NULL,
  `prioritas` tinyint(3) UNSIGNED DEFAULT NULL,
  `tahun_survei` year(4) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_hasil`),
  KEY `hasil_kalkulasi_id_wilayah_foreign` (`id_wilayah`),
  KEY `hasil_kalkulasi_id_komoditas_foreign` (`id_komoditas`),
  KEY `hasil_kalkulasi_id_unit_usaha_foreign` (`id_unit_usaha_rekomendasi`),
  CONSTRAINT `hasil_kalkulasi_id_wilayah_foreign` FOREIGN KEY (`id_wilayah`) REFERENCES `wilayah` (`id`),
  CONSTRAINT `hasil_kalkulasi_id_komoditas_foreign` FOREIGN KEY (`id_komoditas`) REFERENCES `komoditas` (`id`),
  CONSTRAINT `hasil_kalkulasi_id_unit_usaha_foreign` FOREIGN KEY (`id_unit_usaha_rekomendasi`) REFERENCES `master_unit_usaha` (`id_unit_usaha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `perbandingan_harga` (
  `id_perbandingan` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_komoditas` bigint(20) UNSIGNED NOT NULL,
  `id_wilayah` bigint(20) UNSIGNED NOT NULL,
  `bulan` tinyint(2) UNSIGNED NOT NULL,
  `tahun` year(4) NOT NULL,
  `harga_ditawarkan` decimal(15,2) NOT NULL,
  `jumlah_tersedia` decimal(15,2) NOT NULL DEFAULT 0.00,
  `jarak_kegudang` decimal(10,2) DEFAULT NULL,
  `estimasi_ongkir` decimal(15,2) DEFAULT NULL,
  `harga_efektif` decimal(15,2) NOT NULL,
  `rank_harga` tinyint(3) UNSIGNED DEFAULT NULL,
  `dipilih` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_perbandingan`),
  KEY `perbandingan_harga_id_komoditas_foreign` (`id_komoditas`),
  KEY `perbandingan_harga_id_wilayah_foreign` (`id_wilayah`),
  CONSTRAINT `perbandingan_harga_id_komoditas_foreign` FOREIGN KEY (`id_komoditas`) REFERENCES `komoditas` (`id`),
  CONSTRAINT `perbandingan_harga_id_wilayah_foreign` FOREIGN KEY (`id_wilayah`) REFERENCES `wilayah` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `permintaan_pengadaan` (
  `id_permintaan` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode_permintaan` varchar(30) NOT NULL,
  `id_hasil` bigint(20) UNSIGNED DEFAULT NULL,
  `id_wilayah_asal` bigint(20) UNSIGNED NOT NULL,
  `id_komoditas` bigint(20) UNSIGNED NOT NULL,
  `id_pihak` bigint(20) UNSIGNED NOT NULL,
  `jumlah_diminta` decimal(15,2) NOT NULL,
  `jumlah_diterima` decimal(15,2) DEFAULT NULL,
  `harga_satuan` decimal(15,2) NOT NULL,
  `total_nilai` decimal(15,2) NOT NULL,
  `status` enum('diajukan','disetujui','dikirim','diterima','selesai','dibatalkan') NOT NULL DEFAULT 'diajukan',
  `tgl_pengajuan` date NOT NULL,
  `tgl_kirim` date DEFAULT NULL,
  `tgl_terima` date DEFAULT NULL,
  `tgl_selesai` date DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_permintaan`),
  UNIQUE KEY `permintaan_pengadaan_kode_unique` (`kode_permintaan`),
  KEY `permintaan_pengadaan_id_hasil_foreign` (`id_hasil`),
  KEY `permintaan_pengadaan_id_wilayah_asal_foreign` (`id_wilayah_asal`),
  KEY `permintaan_pengadaan_id_komoditas_foreign` (`id_komoditas`),
  KEY `permintaan_pengadaan_id_pihak_foreign` (`id_pihak`),
  CONSTRAINT `permintaan_pengadaan_id_hasil_foreign` FOREIGN KEY (`id_hasil`) REFERENCES `hasil_kalkulasi` (`id_hasil`),
  CONSTRAINT `permintaan_pengadaan_id_wilayah_asal_foreign` FOREIGN KEY (`id_wilayah_asal`) REFERENCES `wilayah` (`id`),
  CONSTRAINT `permintaan_pengadaan_id_komoditas_foreign` FOREIGN KEY (`id_komoditas`) REFERENCES `komoditas` (`id`),
  CONSTRAINT `permintaan_pengadaan_id_pihak_foreign` FOREIGN KEY (`id_pihak`) REFERENCES `master_pihak` (`id_pihak`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `permintaan_barter` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_pemohon` bigint(20) UNSIGNED NOT NULL,
  `id_wilayah_pemohon` bigint(20) UNSIGNED NOT NULL,
  `id_wilayah_tujuan` bigint(20) UNSIGNED DEFAULT NULL,
  `id_komoditas` bigint(20) UNSIGNED NOT NULL,
  `jumlah_diminta_ton` decimal(12,2) NOT NULL,
  `status` enum('terbuka','tercocok','tertutup') NOT NULL DEFAULT 'terbuka',
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `permintaan_barter_id_pemohon_foreign` (`id_pemohon`),
  KEY `permintaan_barter_id_wilayah_pemohon_foreign` (`id_wilayah_pemohon`),
  KEY `permintaan_barter_id_wilayah_tujuan_foreign` (`id_wilayah_tujuan`),
  KEY `permintaan_barter_id_komoditas_foreign` (`id_komoditas`),
  CONSTRAINT `permintaan_barter_id_pemohon_foreign` FOREIGN KEY (`id_pemohon`) REFERENCES `pengguna` (`id`),
  CONSTRAINT `permintaan_barter_id_wilayah_pemohon_foreign` FOREIGN KEY (`id_wilayah_pemohon`) REFERENCES `wilayah` (`id`),
  CONSTRAINT `permintaan_barter_id_wilayah_tujuan_foreign` FOREIGN KEY (`id_wilayah_tujuan`) REFERENCES `wilayah` (`id`),
  CONSTRAINT `permintaan_barter_id_komoditas_foreign` FOREIGN KEY (`id_komoditas`) REFERENCES `komoditas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `penawaran_barter` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_permintaan_barter` bigint(20) UNSIGNED NOT NULL,
  `id_penawar` bigint(20) UNSIGNED NOT NULL,
  `id_wilayah_penawar` bigint(20) UNSIGNED NOT NULL,
  `jumlah_ditawarkan_ton` decimal(12,2) NOT NULL,
  `status` enum('menunggu','diterima','ditolak') NOT NULL DEFAULT 'menunggu',
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `penawaran_barter_id_permintaan_foreign` (`id_permintaan_barter`),
  KEY `penawaran_barter_id_penawar_foreign` (`id_penawar`),
  KEY `penawaran_barter_id_wilayah_penawar_foreign` (`id_wilayah_penawar`),
  CONSTRAINT `penawaran_barter_id_permintaan_foreign` FOREIGN KEY (`id_permintaan_barter`) REFERENCES `permintaan_barter` (`id`),
  CONSTRAINT `penawaran_barter_id_penawar_foreign` FOREIGN KEY (`id_penawar`) REFERENCES `pengguna` (`id`),
  CONSTRAINT `penawaran_barter_id_wilayah_penawar_foreign` FOREIGN KEY (`id_wilayah_penawar`) REFERENCES `wilayah` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `transaksi_barter` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_permintaan_barter` bigint(20) UNSIGNED NOT NULL,
  `id_penawaran_barter` bigint(20) UNSIGNED NOT NULL,
  `id_pengguna_pengutang` bigint(20) UNSIGNED NOT NULL,
  `id_wilayah_pengutang` bigint(20) UNSIGNED NOT NULL,
  `id_pengguna_pemberi` bigint(20) UNSIGNED NOT NULL,
  `id_wilayah_pemberi` bigint(20) UNSIGNED NOT NULL,
  `id_komoditas` bigint(20) UNSIGNED NOT NULL,
  `jumlah_ton` decimal(12,2) NOT NULL,
  `harga_per_ton_rp` decimal(15,2) NOT NULL,
  `total_nilai_rp` decimal(15,2) NOT NULL,
  `nilai_barter_rp` decimal(15,2) NOT NULL DEFAULT 0.00,
  `jumlah_utang_rp` decimal(15,2) NOT NULL DEFAULT 0.00,
  `jumlah_dibayar_rp` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status_barang` enum('menunggu_jual','terjual_habis') NOT NULL DEFAULT 'menunggu_jual',
  `status_pengiriman` enum('menunggu_kirim','dikirim','diterima') NOT NULL DEFAULT 'menunggu_kirim',
  `dikirim_pada` timestamp NULL DEFAULT NULL,
  `estimasi_tiba_pada` timestamp NULL DEFAULT NULL,
  `diterima_pada` timestamp NULL DEFAULT NULL,
  `received_by` bigint(20) UNSIGNED DEFAULT NULL,
  `pembayaran_diminta_pada` timestamp NULL DEFAULT NULL,
  `requested_by` bigint(20) UNSIGNED DEFAULT NULL,
  `pengiriman_disetujui_pada` timestamp NULL DEFAULT NULL,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `catatan_penerimaan` text DEFAULT NULL,
  `catatan_pengiriman` text DEFAULT NULL,
  `catatan_tanda_terima` text DEFAULT NULL,
  `catatan_permintaan_bayar` text DEFAULT NULL,
  `pembayaran_terakhir_pada` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transaksi_barter_id_permintaan_foreign` (`id_permintaan_barter`),
  KEY `transaksi_barter_id_penawaran_foreign` (`id_penawaran_barter`),
  KEY `transaksi_barter_id_komoditas_foreign` (`id_komoditas`),
  CONSTRAINT `transaksi_barter_id_permintaan_foreign` FOREIGN KEY (`id_permintaan_barter`) REFERENCES `permintaan_barter` (`id`),
  CONSTRAINT `transaksi_barter_id_penawaran_foreign` FOREIGN KEY (`id_penawaran_barter`) REFERENCES `penawaran_barter` (`id`),
  CONSTRAINT `transaksi_barter_id_komoditas_foreign` FOREIGN KEY (`id_komoditas`) REFERENCES `komoditas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `pembayaran_barter` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_transaksi_barter` bigint(20) UNSIGNED NOT NULL,
  `id_pembayar` bigint(20) UNSIGNED NOT NULL,
  `jumlah_rp` decimal(15,2) NOT NULL,
  `tanggal_bayar` date NOT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pembayaran_barter_id_transaksi_foreign` (`id_transaksi_barter`),
  KEY `pembayaran_barter_id_pembayar_foreign` (`id_pembayar`),
  CONSTRAINT `pembayaran_barter_id_transaksi_foreign` FOREIGN KEY (`id_transaksi_barter`) REFERENCES `transaksi_barter` (`id`),
  CONSTRAINT `pembayaran_barter_id_pembayar_foreign` FOREIGN KEY (`id_pembayar`) REFERENCES `pengguna` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- View payload barter ke Modul Keuangan (bukan tabel, tidak duplikasi data)
CREATE VIEW `payload_barter_keuangan` AS
SELECT
  tb.id                       AS id_refrensi,
  DATE(tb.created_at)        AS tanggal_transaksi,
  tb.id_wilayah_pemberi       AS desa_mitra,
  tb.id_wilayah_pengutang     AS desa_mitra_penerima,
  tb.total_nilai_rp           AS total_nilai_keluar,
  tb.jumlah_dibayar_rp        AS total_nilai_masuk
FROM `transaksi_barter` tb
WHERE tb.status_barang = 'terjual_habis' AND tb.status_pengiriman = 'diterima';

-- ============================================================
-- BAGIAN D — SUB-TEMA 3: GUDANG
-- ============================================================

CREATE TABLE `stock_transactions` (
  `id_stock` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tanggal_transaksi` date NOT NULL,
  `id_barang` bigint(20) UNSIGNED NOT NULL,
  `id_unit_usaha` bigint(20) UNSIGNED NOT NULL,
  `jenis_transaksi` enum('masuk','keluar','mutasi','retur','kerugian') NOT NULL,
  `jumlah_masuk` decimal(15,2) NOT NULL DEFAULT 0.00,
  `jumlah_keluar` decimal(15,2) NOT NULL DEFAULT 0.00,
  `saldo_stok` decimal(15,2) NOT NULL DEFAULT 0.00,
  `harga` decimal(15,2) NOT NULL DEFAULT 0.00,
  `kategori_asal` varchar(50) DEFAULT NULL,
  `kategori_tujuan` varchar(50) DEFAULT NULL,
  `jenis_kejadian` enum('rusak','susut','hilang') DEFAULT NULL,
  `total_nilai_barang` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_nilai_kerugian` decimal(15,2) NOT NULL DEFAULT 0.00,
  `reference_id` varchar(50) DEFAULT NULL,
  `status_posting` char(1) NOT NULL DEFAULT 'F',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_stock`),
  KEY `stock_transactions_id_barang_foreign` (`id_barang`),
  KEY `stock_transactions_id_unit_usaha_foreign` (`id_unit_usaha`),
  CONSTRAINT `stock_transactions_id_barang_foreign` FOREIGN KEY (`id_barang`) REFERENCES `master_barang` (`id_barang`),
  CONSTRAINT `stock_transactions_id_unit_usaha_foreign` FOREIGN KEY (`id_unit_usaha`) REFERENCES `master_unit_usaha` (`id_unit_usaha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `penerimaan_barang` (
  `id_penerimaan` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_permintaan` bigint(20) UNSIGNED NOT NULL,
  `tanggal_terima` date NOT NULL,
  `jumlah_diterima` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_penerimaan`),
  KEY `penerimaan_barang_id_permintaan_foreign` (`id_permintaan`),
  CONSTRAINT `penerimaan_barang_id_permintaan_foreign` FOREIGN KEY (`id_permintaan`) REFERENCES `permintaan_pengadaan` (`id_permintaan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `sortir_barang` (
  `id_sortir` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_penerimaan` bigint(20) UNSIGNED NOT NULL,
  `jumlah_layak` decimal(15,2) NOT NULL DEFAULT 0.00,
  `jumlah_tidak_layak` decimal(15,2) NOT NULL DEFAULT 0.00,
  `alasan_tidak_layak` text DEFAULT NULL,
  `foto_bukti` varchar(255) DEFAULT NULL,
  `petugas_sortir` bigint(20) UNSIGNED DEFAULT NULL,
  `tgl_sortir` date NOT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_sortir`),
  KEY `sortir_barang_id_penerimaan_foreign` (`id_penerimaan`),
  CONSTRAINT `sortir_barang_id_penerimaan_foreign` FOREIGN KEY (`id_penerimaan`) REFERENCES `penerimaan_barang` (`id_penerimaan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- BAGIAN E — SUB-TEMA 4: PENJUALAN & PEMBELIAN
-- ============================================================

CREATE TABLE `pembelian` (
  `id_pembelian` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_pihak` bigint(20) UNSIGNED NOT NULL,
  `id_unit_usaha` bigint(20) UNSIGNED NOT NULL,
  `tanggal_transaksi` date NOT NULL,
  `jenis_pembelian` enum('tunai','bank_langsung','kredit') NOT NULL,
  `metode_pembayaran` varchar(30) NOT NULL,
  `total_pembelian` decimal(15,2) NOT NULL,
  `total_ppn_masukan` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','disetujui','selesai','dibatalkan') NOT NULL DEFAULT 'draft',
  `status_posting` char(1) NOT NULL DEFAULT 'F',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_pembelian`),
  KEY `pembelian_id_pihak_foreign` (`id_pihak`),
  KEY `pembelian_id_unit_usaha_foreign` (`id_unit_usaha`),
  CONSTRAINT `pembelian_id_pihak_foreign` FOREIGN KEY (`id_pihak`) REFERENCES `master_pihak` (`id_pihak`),
  CONSTRAINT `pembelian_id_unit_usaha_foreign` FOREIGN KEY (`id_unit_usaha`) REFERENCES `master_unit_usaha` (`id_unit_usaha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `detail_pembelian` (
  `id_detail` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_pembelian` bigint(20) UNSIGNED NOT NULL,
  `id_barang` bigint(20) UNSIGNED NOT NULL,
  `jumlah` decimal(15,2) NOT NULL,
  `harga` decimal(15,2) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  PRIMARY KEY (`id_detail`),
  KEY `detail_pembelian_id_pembelian_foreign` (`id_pembelian`),
  KEY `detail_pembelian_id_barang_foreign` (`id_barang`),
  CONSTRAINT `detail_pembelian_id_pembelian_foreign` FOREIGN KEY (`id_pembelian`) REFERENCES `pembelian` (`id_pembelian`),
  CONSTRAINT `detail_pembelian_id_barang_foreign` FOREIGN KEY (`id_barang`) REFERENCES `master_barang` (`id_barang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `retur_pengadaan` (
  `id_retur` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_pembelian` bigint(20) UNSIGNED NOT NULL,
  `id_sortir` bigint(20) UNSIGNED DEFAULT NULL,
  `jumlah_retur` decimal(15,2) NOT NULL,
  `alasan` text DEFAULT NULL,
  `foto_bukti` varchar(255) DEFAULT NULL,
  `jenis_penyelesaian` enum('uang','potong_hutang') NOT NULL,
  `status` enum('diajukan','selesai') NOT NULL DEFAULT 'diajukan',
  `tgl_retur` date NOT NULL,
  `tgl_selesai` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_retur`),
  KEY `retur_pengadaan_id_pembelian_foreign` (`id_pembelian`),
  KEY `retur_pengadaan_id_sortir_foreign` (`id_sortir`),
  CONSTRAINT `retur_pengadaan_id_pembelian_foreign` FOREIGN KEY (`id_pembelian`) REFERENCES `pembelian` (`id_pembelian`),
  CONSTRAINT `retur_pengadaan_id_sortir_foreign` FOREIGN KEY (`id_sortir`) REFERENCES `sortir_barang` (`id_sortir`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `penjualan` (
  `id_penjualan` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_pihak` bigint(20) UNSIGNED NOT NULL,
  `id_unit_usaha` bigint(20) UNSIGNED NOT NULL,
  `tanggal_transaksi` date NOT NULL,
  `metode_pembayaran` enum('tunai','transfer','kredit') NOT NULL,
  `total_penjualan` decimal(15,2) NOT NULL,
  `total_ppn_keluaran` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_hpp` decimal(15,2) NOT NULL DEFAULT 0.00,
  `diskon` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status_bayar` enum('lunas','belum_lunas') NOT NULL DEFAULT 'lunas',
  `status_posting` char(1) NOT NULL DEFAULT 'F',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_penjualan`),
  KEY `penjualan_id_pihak_foreign` (`id_pihak`),
  KEY `penjualan_id_unit_usaha_foreign` (`id_unit_usaha`),
  CONSTRAINT `penjualan_id_pihak_foreign` FOREIGN KEY (`id_pihak`) REFERENCES `master_pihak` (`id_pihak`),
  CONSTRAINT `penjualan_id_unit_usaha_foreign` FOREIGN KEY (`id_unit_usaha`) REFERENCES `master_unit_usaha` (`id_unit_usaha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `detail_penjualan` (
  `id_detail` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_penjualan` bigint(20) UNSIGNED NOT NULL,
  `id_barang` bigint(20) UNSIGNED NOT NULL,
  `jumlah` decimal(15,2) NOT NULL,
  `harga` decimal(15,2) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  `nilai_hpp_satuan` decimal(15,2) NOT NULL,
  PRIMARY KEY (`id_detail`),
  KEY `detail_penjualan_id_penjualan_foreign` (`id_penjualan`),
  KEY `detail_penjualan_id_barang_foreign` (`id_barang`),
  CONSTRAINT `detail_penjualan_id_penjualan_foreign` FOREIGN KEY (`id_penjualan`) REFERENCES `penjualan` (`id_penjualan`),
  CONSTRAINT `detail_penjualan_id_barang_foreign` FOREIGN KEY (`id_barang`) REFERENCES `master_barang` (`id_barang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `piutang` (
  `id_piutang` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_penjualan` bigint(20) UNSIGNED NOT NULL,
  `id_pihak` bigint(20) UNSIGNED NOT NULL,
  `masuk` decimal(15,2) NOT NULL DEFAULT 0.00,
  `keluar` decimal(15,2) NOT NULL DEFAULT 0.00,
  `saldo` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_piutang`),
  KEY `piutang_id_penjualan_foreign` (`id_penjualan`),
  KEY `piutang_id_pihak_foreign` (`id_pihak`),
  CONSTRAINT `piutang_id_penjualan_foreign` FOREIGN KEY (`id_penjualan`) REFERENCES `penjualan` (`id_penjualan`),
  CONSTRAINT `piutang_id_pihak_foreign` FOREIGN KEY (`id_pihak`) REFERENCES `master_pihak` (`id_pihak`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `retur_penjualan` (
  `id_retur` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_penjualan` bigint(20) UNSIGNED NOT NULL,
  `jumlah_retur` decimal(15,2) NOT NULL,
  `alasan` text DEFAULT NULL,
  `jenis_penyelesaian` enum('uang','potong_piutang') NOT NULL,
  `status` enum('diajukan','selesai') NOT NULL DEFAULT 'diajukan',
  `tgl_retur` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_retur`),
  KEY `retur_penjualan_id_penjualan_foreign` (`id_penjualan`),
  CONSTRAINT `retur_penjualan_id_penjualan_foreign` FOREIGN KEY (`id_penjualan`) REFERENCES `penjualan` (`id_penjualan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- BAGIAN F — MODUL KEUANGAN AKUNTANSI (BARU)
-- Sesuai spesifikasi terbaru dari pengguna.
-- Tambahan dari saya (ditandai [Menebak]): kolom source_id/source_type
-- dan is_pembalik/id_jurnal_asal di tabel `jurnals`, sesuai requirement
-- audit trail & jurnal pembalik yang eksplisit diminta di dokumen asli.
-- ============================================================

-- Tabel 1: master_coa — daftar akun (Chart of Accounts)
CREATE TABLE `master_coa` (
  `kode_anak` varchar(10) NOT NULL,
  `kode_induk` varchar(10) DEFAULT NULL,
  `nama_rekening` varchar(150) NOT NULL,
  `posisi_normal` enum('D','K') NOT NULL,
  `is_transaction` char(1) NOT NULL DEFAULT 'T' COMMENT 'T = akun leaf/bisa diposting, F = akun kelompok/header',
  `kelompok` varchar(30) NOT NULL COMMENT 'Aktiva, Kewajiban, Modal, Pendapatan, HPP, Beban',
  PRIMARY KEY (`kode_anak`),
  KEY `master_coa_kode_induk_foreign` (`kode_induk`),
  CONSTRAINT `master_coa_kode_induk_foreign` FOREIGN KEY (`kode_induk`) REFERENCES `master_coa` (`kode_anak`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



-- Tabel 2: master_detail_transaksi — mapping kode_transaksi ke akun COA
CREATE TABLE `master_detail_transaksi` (
  `id_master` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode_transaksi` varchar(10) NOT NULL,
  `kode_anak` varchar(10) NOT NULL,
  `kode_induk` varchar(10) DEFAULT NULL,
  `posisi` enum('D','K') NOT NULL,
  `sumber_variabel` varchar(50) NOT NULL COMMENT 'nama key di JSON payload API, mis. total_bayar, nilai_hpp',
  `id_unit_usaha` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'NULL = berlaku semua unit (mis. Kas/Piutang); diisi kalau akun spesifik per unit (mis. Pendapatan, HPP)',
  PRIMARY KEY (`id_master`),
  KEY `master_detail_transaksi_kode_transaksi_foreign` (`kode_transaksi`),
  KEY `master_detail_transaksi_kode_anak_foreign` (`kode_anak`),
  KEY `master_detail_transaksi_id_unit_usaha_foreign` (`id_unit_usaha`),
  CONSTRAINT `master_detail_transaksi_kode_transaksi_foreign` FOREIGN KEY (`kode_transaksi`) REFERENCES `master_transaksi` (`kode_transaksi`),
  CONSTRAINT `master_detail_transaksi_kode_anak_foreign` FOREIGN KEY (`kode_anak`) REFERENCES `master_coa` (`kode_anak`),
  CONSTRAINT `master_detail_transaksi_id_unit_usaha_foreign` FOREIGN KEY (`id_unit_usaha`) REFERENCES `master_unit_usaha` (`id_unit_usaha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel 3: jurnals
CREATE TABLE `jurnals` (
  `id_jurnal` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomor_nota` varchar(30) NOT NULL,
  `tanggal_jurnal` date NOT NULL,
  `kode_induk` varchar(10) DEFAULT NULL,
  `kode_anak` varchar(10) NOT NULL,
  `debet` decimal(15,2) NOT NULL DEFAULT 0.00,
  `kredit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `saldo` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status_posting` char(1) NOT NULL DEFAULT 'F',
  -- kolom tambahan wajib sesuai requirement audit trail & jurnal pembalik di dokumen asli:
  `source_type` varchar(30) DEFAULT NULL COMMENT 'nama modul/tabel sumber, mis. penjualan, pembelian, transaksi_barter',
  `source_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'PK baris sumber di modul asal',
  `is_pembalik` tinyint(1) NOT NULL DEFAULT 0,
  `id_jurnal_asal` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'diisi kalau baris ini pembalik dari baris jurnal lain',
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_jurnal`),
  KEY `jurnals_kode_anak_foreign` (`kode_anak`),
  KEY `jurnals_id_jurnal_asal_foreign` (`id_jurnal_asal`),
  CONSTRAINT `jurnals_kode_anak_foreign` FOREIGN KEY (`kode_anak`) REFERENCES `master_coa` (`kode_anak`),
  CONSTRAINT `jurnals_id_jurnal_asal_foreign` FOREIGN KEY (`id_jurnal_asal`) REFERENCES `jurnals` (`id_jurnal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel: buku_besar
CREATE TABLE `buku_besar` (
  `id_buku_besar` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `bulan_jurnal` tinyint(2) UNSIGNED NOT NULL,
  `tahun_jurnal` year(4) NOT NULL,
  `kode_induk` varchar(10) DEFAULT NULL,
  `kode_anak` varchar(10) NOT NULL,
  `debet` decimal(15,2) NOT NULL DEFAULT 0.00,
  `kredit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `saldo` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id_buku_besar`),
  KEY `buku_besar_kode_anak_foreign` (`kode_anak`),
  CONSTRAINT `buku_besar_kode_anak_foreign` FOREIGN KEY (`kode_anak`) REFERENCES `master_coa` (`kode_anak`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel: neraca
CREATE TABLE `neraca` (
  `id_neraca` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tahun_jurnal` year(4) NOT NULL,
  `kode_induk` varchar(10) DEFAULT NULL,
  `kode_anak` varchar(10) NOT NULL,
  `debet` decimal(15,2) NOT NULL DEFAULT 0.00,
  `kredit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `saldo` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id_neraca`),
  KEY `neraca_kode_anak_foreign` (`kode_anak`),
  CONSTRAINT `neraca_kode_anak_foreign` FOREIGN KEY (`kode_anak`) REFERENCES `master_coa` (`kode_anak`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Seed data: master_coa (COA riil KDMP)
-- Catatan perbaikan struktural (lihat pembahasan sebelum bagian ini):
--  1) kode_induk '0' (root) diganti NULL, karena '0' bukan baris nyata.
--  2) kode_anak '4' (PENDAPATAN) dan '5' (BIAYA) yang tadinya tertulis
--     beranak dari '33' (SHU Ditahan) diperbaiki jadi top-level (NULL),
--     karena kode 33 adalah akun leaf, tidak logis jadi induk kelompok besar.
-- ------------------------------------------------------------
INSERT INTO `master_coa` (`kode_induk`, `kode_anak`, `nama_rekening`, `posisi_normal`, `is_transaction`, `kelompok`) VALUES
(NULL, '1', 'AKTIVA', 'D', 'F', 'Aktiva'),
('1', '11', 'Aktiva Lancar', 'D', 'F', 'Aktiva'),
('11', '111', 'Kas & Bank', 'D', 'F', 'Aktiva'),
('111', '1111', 'Kas', 'D', 'T', 'Aktiva'),
('111', '1112', 'Bank', 'D', 'F', 'Aktiva'),
('1112', '11121', 'Bank A', 'D', 'T', 'Aktiva'),
('1112', '11122', 'Bank B', 'D', 'T', 'Aktiva'),
('1112', '11123', 'Bank C', 'D', 'T', 'Aktiva'),
('11', '112', 'Persediaan', 'D', 'F', 'Aktiva'),
('112', '1121', 'Bahan Mentah', 'D', 'F', 'Aktiva'),
('1121', '11211', 'Bahan Mentah - Sembako / Tani', 'D', 'T', 'Aktiva'),
('112', '1122', 'Bahan 1/2 Jadi', 'D', 'F', 'Aktiva'),
('1122', '11221', 'Bahan 1/2 Jadi - Proses Kemas', 'D', 'T', 'Aktiva'),
('112', '1123', 'Bahan Jadi', 'D', 'F', 'Aktiva'),
('1123', '11231', 'Bahan Jadi - Sembako', 'D', 'T', 'Aktiva'),
('1123', '11232', 'Bahan Jadi - Apotek (Obat)', 'D', 'T', 'Aktiva'),
('11', '113', 'Piutang', 'D', 'F', 'Aktiva'),
('113', '1131', 'Piutang Karyawan', 'D', 'T', 'Aktiva'),
('113', '1132', 'Piutang Dagang', 'D', 'T', 'Aktiva'),
('113', '1133', 'Piutang Antar Desa (Barter)', 'D', 'T', 'Aktiva'),
('11', '114', 'Pajak Dibayar Dimuka', 'D', 'F', 'Aktiva'),
('114', '1141', 'PPN Masukan (pembelian)', 'D', 'T', 'Aktiva'),
('114', '1142', 'PPN Keluaran (penjualan)', 'K', 'T', 'Aktiva'),
('114', '1143', 'Hutang PPH (pajak Penghasilan)', 'K', 'T', 'Aktiva'),
('1', '12', 'Aktiva Tetap', 'D', 'F', 'Aktiva'),
('12', '1211', 'Gedung', 'D', 'T', 'Aktiva'),
('12', '1212', 'Penyusutan Gedung', 'K', 'T', 'Aktiva'),
('12', '1213', 'Tanah', 'D', 'T', 'Aktiva'),
('12', '1214', 'Komputer', 'D', 'T', 'Aktiva'),
('12', '1215', 'Penyusutan Komputer', 'K', 'T', 'Aktiva'),
('12', '1216', 'Kendaraan', 'D', 'T', 'Aktiva'),
('12', '1217', 'Penyusutan Kendaraan', 'K', 'T', 'Aktiva'),
(NULL, '2', 'KEWAJIBAN', 'K', 'F', 'Kewajiban'),
('2', '21', 'Hutang Jk Pendek', 'K', 'F', 'Kewajiban'),
('21', '2111', 'Hutang Dagang', 'K', 'T', 'Kewajiban'),
('21', '2112', 'Hutang Antar Desa (Barter)', 'K', 'T', 'Kewajiban'),
('21', '2113', 'Hutang Pajak', 'K', 'F', 'Kewajiban'),
('2', '22', 'Hutang JK Panjang', 'K', 'F', 'Kewajiban'),
('22', '2211', 'Hutang Bank (Modal KDMP)', 'K', 'T', 'Kewajiban'),
(NULL, '3', 'MODAL', 'K', 'F', 'Modal'),
('3', '31', 'Modal Awal (Penyertaan)', 'K', 'T', 'Modal'),
('3', '32', 'R/L Tahun Berjalan (SHU)', 'K', 'T', 'Modal'),
('3', '33', 'R/L Tahun Lalu (SHU Ditahan)', 'K', 'T', 'Modal'),
(NULL, '4', 'PENDAPATAN', 'K', 'F', 'Pendapatan'),
('4', '41', 'Pendapatan Penjualan', 'K', 'F', 'Pendapatan'),
('41', '4111', 'Pendapatan Sembako', 'K', 'T', 'Pendapatan'),
('41', '4112', 'Pendapatan Apotek', 'K', 'T', 'Pendapatan'),
('4', '42', 'Potongan & Retur', 'D', 'F', 'Pendapatan'),
('42', '4211', 'Potongan Pembelian', 'K', 'T', 'Pendapatan'),
('42', '4212', 'Potongan Disc (Diskon Jual)', 'D', 'T', 'Pendapatan'),
('42', '4213', 'Retur Penjualan (Barang Rusak)', 'D', 'T', 'Pendapatan'),
('42', '4214', 'Retur Pembelian (Ke Supplier)', 'K', 'T', 'Pendapatan'),
('4', '43', 'HARGA POKOK PENJUALAN', 'D', 'F', 'HPP'),
('43', '431', 'HPP Sembako', 'D', 'T', 'HPP'),
('43', '432', 'HPP Apotek', 'D', 'T', 'HPP'),
('4', '44', 'PENDAPATAN LAIN', 'K', 'F', 'Pendapatan'),
('44', '441', 'Pendapatan Lain-lain', 'K', 'T', 'Pendapatan'),
(NULL, '5', 'BIAYA', 'D', 'F', 'Biaya'),
('5', '51', 'Biaya Tetap', 'D', 'F', 'Biaya'),
('51', '511', 'Gaji', 'D', 'T', 'Biaya'),
('51', '512', 'Tunjangan', 'D', 'T', 'Biaya'),
('5', '52', 'Biaya Operasional & Kantor', 'D', 'F', 'Biaya'),
('52', '521', 'Biaya Kantor', 'D', 'T', 'Biaya'),
('52', '522', 'Telp', 'D', 'T', 'Biaya'),
('52', '523', 'Listrik', 'D', 'T', 'Biaya'),
('52', '524', 'PAM', 'D', 'T', 'Biaya'),
('52', '525', 'Bensin', 'D', 'T', 'Biaya'),
('52', '526', 'Konsumsi', 'D', 'T', 'Biaya'),
('5', '53', 'Biaya Lain-Lain', 'D', 'F', 'Biaya'),
('53', '531', 'Entertain', 'D', 'T', 'Biaya'),
('5', '54', 'Pajak', 'D', 'T', 'Biaya');

-- ------------------------------------------------------------
-- Seed data: master_unit_usaha
-- Baru Sembako & Apotek yang diisi — mengikuti unit yang sudah
-- punya breakdown HPP (431/432). Pupuk, Cold Storage, Simpan Pinjam
-- disebut di dokumen awal tapi belum ada breakdown COA-nya —
-- tambahkan baris + akun HPP/Pendapatan-nya kalau unit itu mulai aktif.
-- ------------------------------------------------------------
INSERT INTO `master_unit_usaha` (`kode_unit_usaha`, `nama_unit_usaha`, `keterangan`) VALUES
('SMB', 'Sembako', NULL),
('APT', 'Apotek', NULL);

-- ------------------------------------------------------------
-- Seed data: master_transaksi (6 kode sesuai spesifikasi)
-- ------------------------------------------------------------
INSERT INTO `master_transaksi` (`kode_transaksi`, `nama_transaksi`) VALUES
('JTW',  'Jual Tunai Warga'),
('JTFW', 'Jual Transfer Warga'),
('JKW',  'Jual Kredit Warga'),
('JTD',  'Jual Tunai Desa'),
('JTFD', 'Jual Transfer Desa'),
('JKD',  'Jual Kredit Desa');

-- ------------------------------------------------------------
-- Seed data: master_detail_transaksi (mapping D/K per kode transaksi)
-- Kas (1111) & Piutang Dagang (1132): id_unit_usaha NULL, berlaku semua unit.
-- Pendapatan Sembako (4111) / Apotek (4112): id_unit_usaha diisi,
-- karena sekarang Pendapatan dipecah per unit usaha (bukan 1 akun gabungan).
-- Stored Procedure nanti pilih baris K yang id_unit_usaha-nya cocok
-- dengan field unit_usaha di payload JSON dari Modul Penjualan.
-- JTFW, JTFD (jual transfer) SENGAJA belum di-seed — sisi Bank masih
-- "belum tahu" (bank tertentu fix, atau kasir pilih per transaksi).
-- ------------------------------------------------------------
INSERT INTO `master_detail_transaksi` (`kode_transaksi`, `kode_anak`, `kode_induk`, `posisi`, `sumber_variabel`, `id_unit_usaha`) VALUES
('JTW',  '1111', '111', 'D', 'total_bayar', NULL),
('JTW',  '4111', '41',  'K', 'total_bayar',   1),   -- Sembako
('JTW',  '4112', '41',  'K', 'total_bayar',   2),   -- Apotek
('JKW',  '1132', '113', 'D', 'total_bayar', NULL),
('JKW',  '4111', '41',  'K', 'total_bayar',   1),
('JKW',  '4112', '41',  'K', 'total_bayar',   2),
('JTD',  '1111', '111', 'D', 'total_bayar', NULL),
('JTD',  '4111', '41',  'K', 'total_bayar',   1),
('JTD',  '4112', '41',  'K', 'total_bayar',   2),
('JKD',  '1132', '113', 'D', 'total_bayar', NULL),
('JKD',  '4111', '41',  'K', 'total_bayar',   1),
('JKD',  '4112', '41',  'K', 'total_bayar',   2);
-- JTFW, JTFD: BELUM di-seed. Tambahkan begitu keputusan Bank sudah ada.

SET FOREIGN_KEY_CHECKS = 1;
