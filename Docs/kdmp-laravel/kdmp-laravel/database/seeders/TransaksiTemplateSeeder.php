<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Template jurnal otomatis.
 *
 * Akun dinamis diterjemahkan saat runtime oleh JurnalService:
 *   KAS_BANK        -> master_kas_bank.kode_akun (pilihan kasir)
 *   PERSEDIAAN_UNIT -> master_unit_usaha.kode_akun_persediaan
 *   PENDAPATAN_UNIT -> tergantung unit usaha DAN is_anggota
 *   HPP_UNIT        -> master_unit_usaha.kode_akun_hpp
 *
 * CATATAN PENTING:
 * Setiap transaksi penjualan punya EMPAT baris — sepasang sisi uang,
 * sepasang sisi barang. Tanpa pasangan HPP/Persediaan, Laba Rugi akan
 * menunjukkan margin 100% dan nilai Persediaan tidak pernah berkurang.
 *
 * Kode JKW (Jual Kredit Warga) SENGAJA TIDAK ADA: penjualan ke warga
 * selalu tunai atau transfer.
 */
class TransaksiTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $transaksi = [
            // kode, nama, modul
            ['JTW',  'Jual Tunai Warga',                'penjualan'],
            ['JTFW', 'Jual Transfer Warga',             'penjualan'],
            ['BTU',  'Beli Tunai',                      'pembelian'],
            ['BTF',  'Beli Transfer',                   'pembelian'],
            ['BKR',  'Beli Kredit',                     'pembelian'],
            ['BPT',  'Beli Tunai dari Petani',          'pembelian'],
            ['RBU',  'Retur Beli - Diganti Uang',       'pembelian'],
            ['RBH',  'Retur Beli - Potong Hutang',      'pembelian'],
            ['RJU',  'Retur Jual - Diganti Uang',       'penjualan'],
            // --- konsinyasi ---
            ['KTK',  'Kirim Titipan Konsinyasi',        'konsinyasi'],
            ['KJL',  'Jual Barang Titipan (penerima)',  'konsinyasi'],
            ['KAP',  'Akui Penjualan Titipan (pemilik)', 'konsinyasi'],
            ['KKM',  'Akui Imbalan Komisi (pemilik)',   'konsinyasi'],
            ['KST',  'Setor Hasil Titipan (penerima)',  'konsinyasi'],
            ['KTR',  'Terima Setoran Titipan (pemilik)', 'konsinyasi'],
            ['KRT',  'Retur Titipan ke Pemilik',        'konsinyasi'],
            ['KSP',  'Susut Titipan - Beban Pemilik',   'konsinyasi'],
            ['KSN',  'Susut Titipan - Beban Penerima',  'konsinyasi'],
            // --- keuangan ---
            ['TPI',  'Terima Pelunasan Piutang',        'keuangan'],
            ['BHU',  'Bayar Hutang',                    'keuangan'],
            ['KSM',  'Kas Masuk Lain',                  'keuangan'],
            ['KSK',  'Kas Keluar Lain',                 'keuangan'],
            ['SPK',  'Setor Simpanan Pokok',            'keanggotaan'],
            ['SWJ',  'Setor Simpanan Wajib',            'keanggotaan'],
            ['SSK',  'Setor Simpanan Sukarela',         'keanggotaan'],
            ['TSK',  'Tarik Simpanan Sukarela',         'keanggotaan'],
            // --- gudang & penyesuaian ---
            ['KRG',  'Kerugian Persediaan',             'gudang'],
            ['OPK',  'Opname - Selisih Kurang',         'gudang'],
            ['OPL',  'Opname - Selisih Lebih',          'gudang'],
            ['PNY',  'Penyusutan Aset Tetap',           'penyesuaian'],
        ];

        foreach ($transaksi as $t) {
            DB::table('master_transaksi')->updateOrInsert(
                ['kode_transaksi' => $t[0]],
                ['nama_transaksi' => $t[1], 'modul' => $t[2], 'is_active' => true]
            );
        }

        // kode, urutan, kode_anak, akun_dinamis, posisi, sumber_variabel
        $detail = [
            // ===== PENJUALAN REGULER =====
            ['JTW', 1, '1111', null,              'D', 'total_bayar'],
            ['JTW', 2, null,   'PENDAPATAN_UNIT', 'K', 'total_bayar'],
            ['JTW', 3, null,   'HPP_UNIT',        'D', 'total_hpp'],
            ['JTW', 4, null,   'PERSEDIAAN_UNIT', 'K', 'total_hpp'],

            ['JTFW', 1, null, 'KAS_BANK',        'D', 'total_bayar'],
            ['JTFW', 2, null, 'PENDAPATAN_UNIT', 'K', 'total_bayar'],
            ['JTFW', 3, null, 'HPP_UNIT',        'D', 'total_hpp'],
            ['JTFW', 4, null, 'PERSEDIAAN_UNIT', 'K', 'total_hpp'],

            // ===== PEMBELIAN =====
            ['BTU', 1, null,   'PERSEDIAAN_UNIT', 'D', 'total_pembelian'],
            ['BTU', 2, '1111', null,              'K', 'total_pembelian'],
            ['BTF', 1, null,   'PERSEDIAAN_UNIT', 'D', 'total_pembelian'],
            ['BTF', 2, null,   'KAS_BANK',        'K', 'total_pembelian'],
            ['BKR', 1, null,   'PERSEDIAAN_UNIT', 'D', 'total_pembelian'],
            ['BKR', 2, '2111', null,              'K', 'total_pembelian'],
            ['BPT', 1, null,   'PERSEDIAAN_UNIT', 'D', 'total_pembelian'],
            ['BPT', 2, '1111', null,              'K', 'total_pembelian'],

            // ===== RETUR =====
            ['RBU', 1, '1111', null,              'D', 'total_nilai'],
            ['RBU', 2, null,   'PERSEDIAAN_UNIT', 'K', 'total_nilai'],
            ['RBH', 1, '2111', null,              'D', 'total_nilai'],
            ['RBH', 2, null,   'PERSEDIAAN_UNIT', 'K', 'total_nilai'],
            // Pakai akun kontra 421, jangan mendebit akun Pendapatan langsung
            ['RJU', 1, '421',  null,              'D', 'total_nilai'],
            ['RJU', 2, '1111', null,              'K', 'total_nilai'],
            ['RJU', 3, null,   'PERSEDIAAN_UNIT', 'D', 'total_hpp'],
            ['RJU', 4, null,   'HPP_UNIT',        'K', 'total_hpp'],

            // ===== KONSINYASI =====
            // 1. Kirim titipan — di buku PEMILIK. Reklasifikasi aset, bukan penjualan.
            ['KTK', 1, '1126', null,              'D', 'total_hpp'],
            ['KTK', 2, null,   'PERSEDIAAN_UNIT', 'K', 'total_hpp'],

            // 2. Penerima menjual ke warga — di buku PENERIMA.
            //    Penerima TIDAK mencatat Pendapatan Penjualan dan TIDAK mencatat HPP.
            ['KJL', 1, null,   'KAS_BANK', 'D', 'total_bayar'],
            ['KJL', 2, '2117', null,       'K', 'nilai_hak_pemilik'],
            ['KJL', 3, '417',  null,       'K', 'nilai_imbalan'],

            // 3. Pemilik mengakui penjualan — di buku PEMILIK, otomatis serentak.
            ['KAP', 1, '1135', null, 'D', 'nilai_hak_pemilik'],
            ['KAP', 2, '416',  null, 'K', 'nilai_hak_pemilik'],
            ['KAP', 3, '514',  null, 'D', 'total_hpp'],
            ['KAP', 4, '1126', null, 'K', 'total_hpp'],

            // 3b. Hanya pada model komisi_persen: pemilik menanggung biaya imbalan
            ['KKM', 1, '653',  null, 'D', 'nilai_imbalan'],
            ['KKM', 2, '1135', null, 'K', 'nilai_imbalan'],

            // 4. Setoran hasil
            ['KST', 1, '2117', null,       'D', 'total_nilai'],
            ['KST', 2, null,   'KAS_BANK', 'K', 'total_nilai'],
            ['KTR', 1, null,   'KAS_BANK', 'D', 'total_nilai'],
            ['KTR', 2, '1135', null,       'K', 'total_nilai'],

            // 5. Retur sisa titipan — di buku PEMILIK
            ['KRT', 1, null,   'PERSEDIAAN_UNIT', 'D', 'total_hpp'],
            ['KRT', 2, '1126', null,              'K', 'total_hpp'],

            // 6. Susut titipan
            ['KSP', 1, '641',  null, 'D', 'nilai_susut'],   // beban pemilik
            ['KSP', 2, '1126', null, 'K', 'nilai_susut'],
            ['KSN', 1, '641',  null, 'D', 'nilai_susut'],   // beban penerima
            ['KSN', 2, '2117', null, 'K', 'nilai_susut'],

            // ===== KEUANGAN =====
            ['TPI', 1, null,   'KAS_BANK', 'D', 'total_nilai'],
            ['TPI', 2, '1132', null,       'K', 'total_nilai'],
            ['BHU', 1, '2111', null,       'D', 'total_nilai'],
            ['BHU', 2, null,   'KAS_BANK', 'K', 'total_nilai'],

            ['SPK', 1, null,   'KAS_BANK', 'D', 'total_nilai'],
            ['SPK', 2, '311',  null,       'K', 'total_nilai'],
            ['SWJ', 1, null,   'KAS_BANK', 'D', 'total_nilai'],
            ['SWJ', 2, '312',  null,       'K', 'total_nilai'],
            ['SSK', 1, null,   'KAS_BANK', 'D', 'total_nilai'],
            ['SSK', 2, '2115', null,       'K', 'total_nilai'],
            ['TSK', 1, '2115', null,       'D', 'total_nilai'],
            ['TSK', 2, null,   'KAS_BANK', 'K', 'total_nilai'],

            // ===== GUDANG =====
            ['KRG', 1, '641', null,              'D', 'nilai_kerugian'],
            ['KRG', 2, null,  'PERSEDIAAN_UNIT', 'K', 'nilai_kerugian'],
            ['OPK', 1, '641', null,              'D', 'nilai_selisih'],
            ['OPK', 2, null,  'PERSEDIAAN_UNIT', 'K', 'nilai_selisih'],
            ['OPL', 1, null,  'PERSEDIAAN_UNIT', 'D', 'nilai_selisih'],
            ['OPL', 2, '712', null,              'K', 'nilai_selisih'],
        ];

        foreach ($detail as $d) {
            DB::table('master_detail_transaksi')->updateOrInsert(
                ['kode_transaksi' => $d[0], 'urutan' => $d[1]],
                [
                    'kode_anak'       => $d[2],
                    'akun_dinamis'    => $d[3],
                    'posisi'          => $d[4],
                    'sumber_variabel' => $d[5],
                    'is_optional'     => false,
                ]
            );
        }
    }
}
