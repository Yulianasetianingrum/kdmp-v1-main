<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Chart of Account KDMP — non-PKP, dengan akun konsinyasi.
 *
 * Struktur kelompok:
 *   1 Aktiva | 2 Kewajiban | 3 Modal | 4 Pendapatan
 *   5 HPP    | 6 Biaya     | 7 Non-Operasional | 8 Ikhtisar
 *
 * Akun PPN tidak ada karena koperasi berstatus non-PKP.
 */
class CoaSeeder extends Seeder
{
    public function run(): void
    {
        // [kode_anak, kode_induk, nama, posisi, is_transaction, kelompok, is_kontra, level]
        $rows = [
            // ---------- 1 AKTIVA ----------
            ['1',     null,   'AKTIVA',                              'D', 'F', 'Aktiva', 0, 1],
            ['11',    '1',    'Aktiva Lancar',                       'D', 'F', 'Aktiva', 0, 2],
            ['111',   '11',   'Kas & Bank',                          'D', 'F', 'Aktiva', 0, 3],
            ['1111',  '111',  'Kas',                                 'D', 'T', 'Aktiva', 0, 4],
            ['1112',  '111',  'Bank',                                'D', 'F', 'Aktiva', 0, 4],
            ['11121', '1112', 'Bank A',                              'D', 'T', 'Aktiva', 0, 5],
            ['11122', '1112', 'Bank B',                              'D', 'T', 'Aktiva', 0, 5],
            ['11123', '1112', 'Bank C',                              'D', 'T', 'Aktiva', 0, 5],
            ['112',   '11',   'Persediaan',                          'D', 'F', 'Aktiva', 0, 3],
            ['1121',  '112',  'Persediaan Bahan Mentah',             'D', 'T', 'Aktiva', 0, 4],
            ['1122',  '112',  'Persediaan Bahan Setengah Jadi',      'D', 'T', 'Aktiva', 0, 4],
            ['1123',  '112',  'Persediaan Barang Jadi - Sembako',    'D', 'T', 'Aktiva', 0, 4],
            ['1124',  '112',  'Persediaan Barang Jadi - Apotek',     'D', 'T', 'Aktiva', 0, 4],
            ['1125',  '112',  'Persediaan Dalam Perjalanan',         'D', 'T', 'Aktiva', 0, 4],
            // Barang yang DITITIPKAN ke desa lain. Masih milik kita.
            ['1126',  '112',  'Persediaan Konsinyasi (Dititipkan)',  'D', 'T', 'Aktiva', 0, 4],
            ['113',   '11',   'Piutang',                             'D', 'F', 'Aktiva', 0, 3],
            ['1131',  '113',  'Piutang Karyawan',                    'D', 'T', 'Aktiva', 0, 4],
            ['1132',  '113',  'Piutang Dagang',                      'D', 'T', 'Aktiva', 0, 4],
            // Hak atas barang titipan yang sudah laku di desa lain, belum disetor.
            ['1135',  '113',  'Piutang Konsinyasi',                  'D', 'T', 'Aktiva', 0, 4],
            ['1134',  '113',  'Penyisihan Piutang Tak Tertagih',     'K', 'T', 'Aktiva', 1, 4],
            ['114',   '11',   'Uang Muka & Beban Dibayar Dimuka',    'D', 'F', 'Aktiva', 0, 3],
            ['1141',  '114',  'Uang Muka Pembelian',                 'D', 'T', 'Aktiva', 0, 4],
            ['1142',  '114',  'Beban Dibayar Dimuka',                'D', 'T', 'Aktiva', 0, 4],
            ['12',    '1',    'Aktiva Tetap',                        'D', 'F', 'Aktiva', 0, 2],
            ['121',   '12',   'Harga Perolehan',                     'D', 'F', 'Aktiva', 0, 3],
            ['1211',  '121',  'Tanah',                               'D', 'T', 'Aktiva', 0, 4],
            ['1212',  '121',  'Gedung & Bangunan',                   'D', 'T', 'Aktiva', 0, 4],
            ['1213',  '121',  'Kendaraan',                           'D', 'T', 'Aktiva', 0, 4],
            ['1214',  '121',  'Peralatan & Komputer',                'D', 'T', 'Aktiva', 0, 4],
            ['122',   '12',   'Akumulasi Penyusutan',                'K', 'F', 'Aktiva', 1, 3],
            ['1222',  '122',  'Akumulasi Penyusutan Gedung',         'K', 'T', 'Aktiva', 1, 4],
            ['1223',  '122',  'Akumulasi Penyusutan Kendaraan',      'K', 'T', 'Aktiva', 1, 4],
            ['1224',  '122',  'Akumulasi Penyusutan Peralatan',      'K', 'T', 'Aktiva', 1, 4],

            // ---------- 2 KEWAJIBAN ----------
            ['2',     null,   'KEWAJIBAN',                           'K', 'F', 'Kewajiban', 0, 1],
            ['21',    '2',    'Kewajiban Jangka Pendek',             'K', 'F', 'Kewajiban', 0, 2],
            ['2111',  '21',   'Hutang Dagang',                       'K', 'T', 'Kewajiban', 0, 3],
            ['2113',  '21',   'Hutang Biaya (Akrual)',               'K', 'T', 'Kewajiban', 0, 3],
            ['2114',  '21',   'Hutang Pajak (PPh)',                  'K', 'T', 'Kewajiban', 0, 3],
            ['2115',  '21',   'Simpanan Sukarela Anggota',           'K', 'T', 'Kewajiban', 0, 3],
            ['2116',  '21',   'Dana Pembagian SHU Belum Dibayar',    'K', 'T', 'Kewajiban', 0, 3],
            // Uang hasil menjual barang titipan desa lain. Bukan milik kita.
            ['2117',  '21',   'Hutang Konsinyasi',                   'K', 'T', 'Kewajiban', 0, 3],
            ['22',    '2',    'Kewajiban Jangka Panjang',            'K', 'F', 'Kewajiban', 0, 2],
            ['2211',  '22',   'Hutang Bank',                         'K', 'T', 'Kewajiban', 0, 3],

            // ---------- 3 MODAL ----------
            ['3',     null,   'MODAL',                               'K', 'F', 'Modal', 0, 1],
            ['31',    '3',    'Simpanan Anggota',                    'K', 'F', 'Modal', 0, 2],
            ['311',   '31',   'Simpanan Pokok',                      'K', 'T', 'Modal', 0, 3],
            ['312',   '31',   'Simpanan Wajib',                      'K', 'T', 'Modal', 0, 3],
            ['32',    '3',    'Modal Penyertaan & Hibah',            'K', 'F', 'Modal', 0, 2],
            ['321',   '32',   'Modal Penyertaan',                    'K', 'T', 'Modal', 0, 3],
            ['322',   '32',   'Modal Hibah / Bantuan Pemerintah',    'K', 'T', 'Modal', 0, 3],
            ['33',    '3',    'Cadangan',                            'K', 'F', 'Modal', 0, 2],
            ['331',   '33',   'Cadangan Umum',                       'K', 'T', 'Modal', 0, 3],
            ['332',   '33',   'Dana Pendidikan',                     'K', 'T', 'Modal', 0, 3],
            ['333',   '33',   'Dana Sosial',                         'K', 'T', 'Modal', 0, 3],
            ['334',   '33',   'Dana Pengurus & Pengawas',            'K', 'T', 'Modal', 0, 3],
            ['34',    '3',    'Sisa Hasil Usaha',                    'K', 'F', 'Modal', 0, 2],
            ['341',   '34',   'SHU Tahun Berjalan',                  'K', 'T', 'Modal', 0, 3],
            ['342',   '34',   'SHU Ditahan (Tahun Lalu)',            'K', 'T', 'Modal', 0, 3],

            // ---------- 4 PENDAPATAN ----------
            ['4',     null,   'PENDAPATAN',                          'K', 'F', 'Pendapatan', 0, 1],
            ['41',    '4',    'Pendapatan Penjualan',                'K', 'F', 'Pendapatan', 0, 2],
            ['411',   '41',   'Penjualan Sembako - Anggota',         'K', 'T', 'Pendapatan', 0, 3],
            ['412',   '41',   'Penjualan Sembako - Non Anggota',     'K', 'T', 'Pendapatan', 0, 3],
            ['413',   '41',   'Penjualan Apotek - Anggota',          'K', 'T', 'Pendapatan', 0, 3],
            ['414',   '41',   'Penjualan Apotek - Non Anggota',      'K', 'T', 'Pendapatan', 0, 3],
            // Omzet dari barang KITA yang laku di desa lain
            ['416',   '41',   'Penjualan Konsinyasi',                'K', 'T', 'Pendapatan', 0, 3],
            // Imbalan karena MENJUALKAN barang desa lain
            ['417',   '41',   'Pendapatan Imbalan Konsinyasi',       'K', 'T', 'Pendapatan', 0, 3],
            ['42',    '4',    'Pengurang Pendapatan',                'D', 'F', 'Pendapatan', 1, 2],
            ['421',   '42',   'Retur Penjualan',                     'D', 'T', 'Pendapatan', 1, 3],
            ['422',   '42',   'Diskon Penjualan',                    'D', 'T', 'Pendapatan', 1, 3],

            // ---------- 5 HPP ----------
            ['5',     null,   'HARGA POKOK PENJUALAN',               'D', 'F', 'HPP', 0, 1],
            ['51',    '5',    'Harga Pokok Penjualan',               'D', 'F', 'HPP', 0, 2],
            ['511',   '51',   'HPP Sembako',                         'D', 'T', 'HPP', 0, 3],
            ['512',   '51',   'HPP Apotek',                          'D', 'T', 'HPP', 0, 3],
            ['514',   '51',   'HPP Penjualan Konsinyasi',            'D', 'T', 'HPP', 0, 3],

            // ---------- 6 BIAYA ----------
            ['6',     null,   'BIAYA OPERASIONAL',                   'D', 'F', 'Biaya', 0, 1],
            ['61',    '6',    'Biaya Personalia',                    'D', 'F', 'Biaya', 0, 2],
            ['611',   '61',   'Gaji',                                'D', 'T', 'Biaya', 0, 3],
            ['612',   '61',   'Tunjangan',                           'D', 'T', 'Biaya', 0, 3],
            ['62',    '6',    'Biaya Kantor & Operasional',          'D', 'F', 'Biaya', 0, 2],
            ['621',   '62',   'Biaya Kantor',                        'D', 'T', 'Biaya', 0, 3],
            ['622',   '62',   'Telepon & Internet',                  'D', 'T', 'Biaya', 0, 3],
            ['623',   '62',   'Listrik',                             'D', 'T', 'Biaya', 0, 3],
            ['624',   '62',   'Air (PAM)',                           'D', 'T', 'Biaya', 0, 3],
            ['625',   '62',   'Bahan Bakar & Transport',             'D', 'T', 'Biaya', 0, 3],
            ['626',   '62',   'Konsumsi',                            'D', 'T', 'Biaya', 0, 3],
            ['63',    '6',    'Biaya Penyusutan',                    'D', 'F', 'Biaya', 0, 2],
            ['631',   '63',   'Biaya Penyusutan Gedung',             'D', 'T', 'Biaya', 0, 3],
            ['632',   '63',   'Biaya Penyusutan Kendaraan',          'D', 'T', 'Biaya', 0, 3],
            ['633',   '63',   'Biaya Penyusutan Peralatan',          'D', 'T', 'Biaya', 0, 3],
            ['64',    '6',    'Biaya Kerugian & Penyisihan',         'D', 'F', 'Biaya', 0, 2],
            ['641',   '64',   'Biaya Kerusakan & Susut Persediaan',  'D', 'T', 'Biaya', 0, 3],
            ['642',   '64',   'Biaya Penyisihan Piutang',            'D', 'T', 'Biaya', 0, 3],
            ['65',    '6',    'Biaya Lain-lain',                     'D', 'F', 'Biaya', 0, 2],
            ['651',   '65',   'Biaya Administrasi Bank',             'D', 'T', 'Biaya', 0, 3],
            ['652',   '65',   'Biaya Rapat & Entertain',             'D', 'T', 'Biaya', 0, 3],
            // Dipakai hanya pada model imbalan komisi_persen
            ['653',   '65',   'Biaya Imbalan Konsinyasi',            'D', 'T', 'Biaya', 0, 3],

            // ---------- 7 NON-OPERASIONAL ----------
            ['7',     null,   'PENDAPATAN & BIAYA NON-OPERASIONAL',  'K', 'F', 'Non-Operasional', 0, 1],
            ['71',    '7',    'Pendapatan Non-Operasional',          'K', 'F', 'Non-Operasional', 0, 2],
            ['711',   '71',   'Pendapatan Lain-lain',                'K', 'T', 'Non-Operasional', 0, 3],
            ['712',   '71',   'Selisih Lebih Stok Opname',           'K', 'T', 'Non-Operasional', 0, 3],
            ['72',    '7',    'Biaya Non-Operasional',               'D', 'F', 'Non-Operasional', 0, 2],
            ['721',   '72',   'Beban Bunga',                         'D', 'T', 'Non-Operasional', 0, 3],
            ['722',   '72',   'Beban Pajak Penghasilan',             'D', 'T', 'Non-Operasional', 0, 3],

            // ---------- 8 IKHTISAR (hanya saat tutup buku) ----------
            ['8',     null,   'IKHTISAR LABA RUGI',                  'K', 'F', 'Ikhtisar', 0, 1],
            ['811',   '8',    'Ikhtisar Laba Rugi',                  'K', 'T', 'Ikhtisar', 0, 2],
        ];

        $urutan = 0;
        foreach ($rows as $r) {
            DB::table('master_coa')->updateOrInsert(
                ['kode_anak' => $r[0]],
                [
                    'kode_induk'     => $r[1],
                    'nama_rekening'  => $r[2],
                    'posisi_normal'  => $r[3],
                    'is_transaction' => $r[4],
                    'kelompok'       => $r[5],
                    'is_kontra'      => $r[6],
                    'level'          => $r[7],
                    'urutan_laporan' => ++$urutan * 10,
                    'is_active'      => true,
                ]
            );
        }
    }
}
