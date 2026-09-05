<?php

namespace Tests\Unit;

use App\Exceptions\Finance\JurnalSudahDibalikException;
use App\Exceptions\Finance\JurnalTidakBalanceException;
use App\Exceptions\Finance\PeriodeTutupException;
use App\Models\Akuntansi\JurnalHeader;
use App\Models\Tenant\KoperasiDesa;
use App\Models\Tenant\PeriodeAkuntansi;
use App\Services\Finance\JurnalService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\TestCase;

/**
 * JurnalServiceTest — Unit test setiap skenario JurnalService.
 *
 * Fokus: postingManual() dan balik() karena tidak membutuhkan setup
 * master_detail_transaksi / master_transaksi (yang kompleks untuk disiapkan di test).
 * posting() dengan template diuji via integration test terpisah.
 *
 * CARA JALANKAN:
 *   php artisan test --filter JurnalServiceTest
 */
class JurnalServiceTest extends TestCase
{
    use DatabaseTruncation;

    private JurnalService $service;
    private KoperasiDesa $koperasi;
    private int $idKoperasi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(JurnalService::class);

        // Buat wilayah dulu (required FK dari koperasi_desa)
        $sfx = uniqid();
        $idWilayah = \DB::table('wilayah')->insertGetId([
            'tingkat' => 'desa', 'nama' => 'Desa Test ' . $sfx, 'created_at' => now(),
        ]);

        // Buat koperasi dengan semua field wajib
        $this->idKoperasi = \DB::table('koperasi_desa')->insertGetId([
            'kode_koperasi'   => 'KT-' . $sfx,
            'nama_koperasi'   => 'Koperasi Test',
            'id_wilayah'      => $idWilayah,
            'tahun_buku_awal' => 2026,
            'is_active'       => 1,
            'created_at'      => now(),
        ]);
        app()->instance('koperasi_aktif', $this->idKoperasi);

        // Insert COA minimal — kolom sesuai skema master_coa
        \DB::table('master_coa')->insertOrIgnore([
            [
                'kode_anak'      => '111',
                'nama_rekening'  => 'Kas',
                'kelompok'       => 'Aktiva',
                'posisi_normal'  => 'D',
                'is_transaction' => 'T',
                'level'          => 3,
                'is_kontra'      => 0,
                'urutan_laporan' => 1,
                'is_active'      => 1,
            ],
            [
                'kode_anak'      => '411',
                'nama_rekening'  => 'Pendapatan Penjualan',
                'kelompok'       => 'Pendapatan',
                'posisi_normal'  => 'K',
                'is_transaction' => 'T',
                'level'          => 3,
                'is_kontra'      => 0,
                'urutan_laporan' => 10,
                'is_active'      => 1,
            ],
        ]);

        // Buka periode Agustus 2026
        \DB::table('periode_akuntansi')->insert([
            'id_koperasi' => $this->idKoperasi,
            'tahun'       => 2026,
            'bulan'       => 8,
            'status'      => 'OPEN',
        ]);
    }

    // =========================================================================
    // TEST: postingManual() — skenario BERHASIL
    // =========================================================================

    /**
     * @test
     * postingManual() dengan Debet = Kredit → jurnal berhasil dibuat.
     */
    public function posting_manual_berhasil_jika_debet_sama_dengan_kredit(): void
    {
        $jurnal = $this->service->postingManual(
            header: [
                'tanggal_jurnal' => '2026-08-15',
                'jenis_jurnal'   => 'MANUAL',
                'keterangan'     => 'Test jurnal balance',
            ],
            baris: [
                ['kode_anak' => '111', 'posisi' => 'D', 'nilai' => 500000],
                ['kode_anak' => '411', 'posisi' => 'K', 'nilai' => 500000],
            ]
        );

        $this->assertInstanceOf(JurnalHeader::class, $jurnal);
        $this->assertEquals('POSTED', $jurnal->status);
        $this->assertEquals(500000, $jurnal->total_debet);
        $this->assertEquals(500000, $jurnal->total_kredit);
        $this->assertEquals($this->idKoperasi, $jurnal->id_koperasi);
    }

    // =========================================================================
    // TEST: postingManual() — Debet ≠ Kredit → Exception
    // =========================================================================

    /**
     * @test
     * postingManual() dengan Debet ≠ Kredit → JurnalTidakBalanceException dilempar.
     */
    public function posting_manual_gagal_jika_debet_tidak_sama_kredit(): void
    {
        $this->expectException(JurnalTidakBalanceException::class);

        $this->service->postingManual(
            header: [
                'tanggal_jurnal' => '2026-08-15',
                'jenis_jurnal'   => 'MANUAL',
                'keterangan'     => 'Test tidak balance',
            ],
            baris: [
                ['kode_anak' => '111', 'posisi' => 'D', 'nilai' => 500000],
                ['kode_anak' => '411', 'posisi' => 'K', 'nilai' => 300000], // ← beda 200rb
            ]
        );
    }

    // =========================================================================
    // TEST: postingManual() → PeriodeTutupException jika periode CLOSED
    // =========================================================================

    /**
     * @test
     * postingManual() ke periode yang sudah CLOSED → PeriodeTutupException.
     */
    public function posting_manual_gagal_jika_periode_sudah_closed(): void
    {
        // Tutup periode Agustus 2026
        PeriodeAkuntansi::where('id_koperasi', $this->idKoperasi)
            ->where('tahun', 2026)
            ->where('bulan', 8)
            ->update(['status' => 'CLOSED']);

        $this->expectException(PeriodeTutupException::class);

        $this->service->postingManual(
            header: [
                'tanggal_jurnal' => '2026-08-15', // ← tanggal di periode CLOSED
                'jenis_jurnal'   => 'MANUAL',
                'keterangan'     => 'Test periode tutup',
            ],
            baris: [
                ['kode_anak' => '111', 'posisi' => 'D', 'nilai' => 100000],
                ['kode_anak' => '411', 'posisi' => 'K', 'nilai' => 100000],
            ]
        );
    }

    // =========================================================================
    // TEST: balik() — jurnal yang sudah REVERSED tidak bisa dibalik lagi
    // =========================================================================

    /**
     * @test
     * balik() jurnal yang sudah berstatus REVERSED → JurnalSudahDibalikException.
     */
    public function balik_gagal_jika_jurnal_sudah_pernah_dibalik(): void
    {
        // Buat jurnal POSTED dulu
        $jurnal = $this->service->postingManual(
            header: [
                'tanggal_jurnal' => '2026-08-15',
                'jenis_jurnal'   => 'MANUAL',
                'keterangan'     => 'Jurnal yang akan dibalik',
            ],
            baris: [
                ['kode_anak' => '111', 'posisi' => 'D', 'nilai' => 200000],
                ['kode_anak' => '411', 'posisi' => 'K', 'nilai' => 200000],
            ]
        );

        // Balik pertama kali → berhasil
        $this->service->balik($jurnal->id_jurnal, 'Balik pertama');

        // Balik kedua kali → Exception
        $this->expectException(JurnalSudahDibalikException::class);

        $this->service->balik($jurnal->id_jurnal, 'Coba balik lagi — harus ditolak');
    }

    // =========================================================================
    // TEST: balik() — jurnal valid bisa dibalik
    // =========================================================================

    /**
     * @test
     * balik() pada jurnal POSTED → membuat jurnal pembalik baru.
     */
    public function balik_berhasil_membuat_jurnal_pembalik(): void
    {
        $jurnal = $this->service->postingManual(
            header: [
                'tanggal_jurnal' => '2026-08-10',
                'jenis_jurnal'   => 'MANUAL',
                'keterangan'     => 'Jurnal untuk dibalik',
            ],
            baris: [
                ['kode_anak' => '111', 'posisi' => 'D', 'nilai' => 150000],
                ['kode_anak' => '411', 'posisi' => 'K', 'nilai' => 150000],
            ]
        );

        $jurnal->fresh(); // reload dari DB

        $jurnalBalik = $this->service->balik($jurnal->id_jurnal, 'Koreksi data');

        $this->assertInstanceOf(JurnalHeader::class, $jurnalBalik);
        $this->assertEquals('PEMBALIK', $jurnalBalik->jenis_jurnal);
        $this->assertEquals($jurnal->id_jurnal, $jurnalBalik->id_jurnal_asal);

        // Jurnal asal harus berubah status ke REVERSED
        $jurnalAsal = JurnalHeader::withoutGlobalScopes()->find($jurnal->id_jurnal);
        $this->assertEquals('REVERSED', $jurnalAsal->status);
    }

    // =========================================================================
    // TEST: Idempoten — posting dua kali dengan source_id sama → Exception unik
    // =========================================================================

    /**
     * @test
     * Posting dengan source_type + source_id yang sama dua kali → UniqueConstraintViolationException
     * (diproteksi oleh unique constraint 'jurnal_idempoten' di tabel jurnal_header).
     */
    public function posting_dua_kali_dengan_source_sama_ditolak(): void
    {
        $params = [
            'tanggal_jurnal' => '2026-08-20',
            'jenis_jurnal'   => 'MANUAL',
            'keterangan'     => 'Idempoten test',
            'source_type'    => 'App\\Models\\Penjualan',
            'source_id'      => 999,
        ];
        $baris = [
            ['kode_anak' => '111', 'posisi' => 'D', 'nilai' => 75000],
            ['kode_anak' => '411', 'posisi' => 'K', 'nilai' => 75000],
        ];

        // Posting pertama → berhasil
        $this->service->postingManual($params, $baris);

        // Posting kedua dengan source_id sama → harus ditolak
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $this->service->postingManual($params, $baris);
    }
}
