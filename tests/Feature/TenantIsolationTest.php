<?php

namespace Tests\Feature;

use App\Models\Akuntansi\BukuBesarPeriode;
use App\Models\Akuntansi\JurnalHeader;
use App\Models\Pengguna;
use App\Models\Tenant\KoperasiDesa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TenantIsolationTest — Membuktikan sistem tidak bocor data antar koperasi.
 *
 * PRASYARAT:
 *   Database `kdmp_test` sudah dibuat dan sudah dijalankan migration.
 *   Seeder minimal dibutuhkan: 2 baris koperasi_desa, 2 baris pengguna.
 *
 * CARA JALANKAN:
 *   php artisan test --filter TenantIsolationTest
 *
 * FILOSOFI TEST INI:
 *   Test ini bukan unit test — test ini adalah "trust boundary" test.
 *   Jika ada satu test di sini yang FAIL, berarti ada kebocoran data antar tenant
 *   yang wajib diperbaiki sebelum deploy ke production.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private KoperasiDesa $desaA;
    private KoperasiDesa $desaB;
    private Pengguna $userA;
    private Pengguna $userB;

    protected function setUp(): void
    {
        parent::setUp();

        // Buat baris wilayah (required FK dari koperasi_desa)
        $sfx = uniqid(); // suffix unik per test agar tidak duplicate
        $idWilayahA = \DB::table('wilayah')->insertGetId([
            'tingkat' => 'desa', 'nama' => 'Desa Alpha ' . $sfx, 'created_at' => now(),
        ]);
        $idWilayahB = \DB::table('wilayah')->insertGetId([
            'tingkat' => 'desa', 'nama' => 'Desa Beta ' . $sfx, 'created_at' => now(),
        ]);

        // Buat 2 desa dengan semua field wajib
        // Tambahkan COA minimal di database jika belum ada (wajib untuk master_kas_bank)
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
            ]
        ]);

        $idDesaA = \DB::table('koperasi_desa')->insertGetId([
            'kode_koperasi'  => 'DA-' . $sfx,
            'nama_koperasi'  => 'Koperasi Desa A Test',
            'id_wilayah'     => $idWilayahA,
            'tahun_buku_awal'=> 2026,
            'is_active'      => 1,
            'created_at'     => now(),
        ]);
        $idDesaB = \DB::table('koperasi_desa')->insertGetId([
            'kode_koperasi'  => 'DB-' . $sfx,
            'nama_koperasi'  => 'Koperasi Desa B Test',
            'id_wilayah'     => $idWilayahB,
            'tahun_buku_awal'=> 2026,
            'is_active'      => 1,
            'created_at'     => now(),
        ]);

        $this->desaA = KoperasiDesa::withoutGlobalScopes()->find($idDesaA);
        $this->desaB = KoperasiDesa::withoutGlobalScopes()->find($idDesaB);

        // Buat pengguna masing-masing desa
        $idUserA = \DB::table('pengguna')->insertGetId([
            'nama'        => 'User Desa A',
            'email'       => 'usera-' . $sfx . '@test.local',
            'password'    => bcrypt('password'),
            'id_koperasi' => $idDesaA,
            'created_at'  => now(),
        ]);
        $idUserB = \DB::table('pengguna')->insertGetId([
            'nama'        => 'User Desa B',
            'email'       => 'userb-' . $sfx . '@test.local',
            'password'    => bcrypt('password'),
            'id_koperasi' => $idDesaB,
            'created_at'  => now(),
        ]);

        $this->userA = Pengguna::withoutGlobalScopes()->find($idUserA);
        $this->userB = Pengguna::withoutGlobalScopes()->find($idUserB);
    }

    // =========================================================================
    // TEST 1: Global Scope BelongsToKoperasi memfilter query Eloquent
    // =========================================================================

    /**
     * @test
     * User Desa A tidak bisa membaca BukuBesarPeriode milik Desa B melalui Eloquent.
     */
    public function user_desa_a_tidak_bisa_baca_buku_besar_desa_b(): void
    {
        // Buat data buku besar untuk Desa B
        BukuBesarPeriode::withoutGlobalScopes()->create([
            'id_koperasi'      => $this->desaB->id_koperasi,
            'periode_tahun'    => 2026,
            'periode_bulan'    => 1,
            'kode_anak'        => '111',
            'saldo_awal_debet' => 0,
            'saldo_awal_kredit'=> 0,
            'mutasi_debet'     => 1000000,
            'mutasi_kredit'    => 0,
            'saldo_akhir_debet'=> 1000000,
            'saldo_akhir_kredit'=> 0,
        ]);

        // Simulasi: User A login → koperasi_aktif = desaA
        app()->instance('koperasi_aktif', $this->desaA->id_koperasi);

        // Query via Eloquent — seharusnya kosong karena BelongsToKoperasi scope
        $hasil = BukuBesarPeriode::where('periode_tahun', 2026)->get();

        $this->assertCount(0, $hasil,
            'BukuBesarPeriode milik Desa B seharusnya tidak terlihat oleh User Desa A'
        );
    }

    /**
     * @test
     * User Desa A hanya melihat JurnalHeader miliknya sendiri.
     */
    public function user_desa_a_hanya_lihat_jurnal_miliknya(): void
    {
        // Buat jurnal untuk kedua desa
        JurnalHeader::withoutGlobalScopes()->insert([
            [
                'id_koperasi'    => $this->desaA->id_koperasi,
                'no_jurnal'      => 'JU-A-001',
                'tanggal_jurnal' => '2026-01-15',
                'periode_tahun'  => 2026,
                'periode_bulan'  => 1,
                'total_debet'    => 500000,
                'total_kredit'   => 500000,
                'status'         => 'POSTED',
                'created_at'     => now(),
            ],
            [
                'id_koperasi'    => $this->desaB->id_koperasi,
                'no_jurnal'      => 'JU-B-001',
                'tanggal_jurnal' => '2026-01-15',
                'periode_tahun'  => 2026,
                'periode_bulan'  => 1,
                'total_debet'    => 999999,
                'total_kredit'   => 999999,
                'status'         => 'POSTED',
                'created_at'     => now(),
            ],
        ]);

        // Login sebagai User A
        app()->instance('koperasi_aktif', $this->desaA->id_koperasi);

        $jurnal = JurnalHeader::all();

        $this->assertCount(1, $jurnal, 'User Desa A seharusnya hanya melihat 1 jurnal miliknya');
        $this->assertEquals('JU-A-001', $jurnal->first()->no_jurnal);
    }

    // =========================================================================
    // TEST 2: TenantOwnershipRule memblokir ID milik desa lain
    // =========================================================================

    /**
     * @test
     * POST kas transaksi dengan id_kas_bank milik Desa B → validation error.
     */
    public function post_kas_transaksi_dengan_id_kas_bank_desa_lain_ditolak(): void
    {
        // Buat kas bank untuk Desa B
        $idKasB = \DB::table('master_kas_bank')->insertGetId([
            'id_koperasi' => $this->desaB->id_koperasi,
            'nama'        => 'Kas Utama Desa B',
            'jenis'       => 'kas',
            'kode_akun'   => '111',
            'is_default'  => 1,
            'is_active'   => 1,
        ]);

        // Login sebagai User A
        $response = $this->actingAs($this->userA)
            ->post(route('keuangan.kas-transaksi.store'), [
                'tanggal'    => '2026-01-20',
                'jenis'      => 'masuk',
                'id_kas_bank'=> $idKasB, // ← ID milik Desa B!
                'kode_akun_lawan' => '411',
                'nilai'      => 100000,
                'keterangan' => 'Test isolasi',
            ]);

        $response->assertSessionHasErrors('id_kas_bank');
    }

    // =========================================================================
    // TEST 3: withoutGlobalScopes bisa membaca data lintas koperasi (untuk admin)
    // =========================================================================

    /**
     * @test
     * withoutGlobalScopes() tetap bisa membaca data lintas koperasi (untuk konsolidasi pusat).
     */
    public function admin_pusat_bisa_baca_data_semua_koperasi(): void
    {
        BukuBesarPeriode::withoutGlobalScopes()->create([
            'id_koperasi'       => $this->desaA->id_koperasi,
            'periode_tahun'     => 2026,
            'periode_bulan'     => 2,
            'kode_anak'         => '111',
            'saldo_awal_debet'  => 0,
            'saldo_awal_kredit' => 0,
            'mutasi_debet'      => 100,
            'mutasi_kredit'     => 0,
            'saldo_akhir_debet' => 100,
            'saldo_akhir_kredit'=> 0,
        ]);

        BukuBesarPeriode::withoutGlobalScopes()->create([
            'id_koperasi'       => $this->desaB->id_koperasi,
            'periode_tahun'     => 2026,
            'periode_bulan'     => 2,
            'kode_anak'         => '111',
            'saldo_awal_debet'  => 0,
            'saldo_awal_kredit' => 0,
            'mutasi_debet'      => 200,
            'mutasi_kredit'     => 0,
            'saldo_akhir_debet' => 200,
            'saldo_akhir_kredit'=> 0,
        ]);

        // Admin pusat: tidak ada koperasi_aktif di container
        app()->forgetInstance('koperasi_aktif');

        $semua = BukuBesarPeriode::withoutGlobalScopes()
            ->where('periode_bulan', 2)
            ->get();

        $this->assertCount(2, $semua, 'Admin pusat seharusnya bisa melihat data kedua desa');
    }
}
