<?php

namespace App\Services;

use App\Support\NomorDokumen;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Mesin jurnal. SATU-SATUNYA jalan menulis ke jurnal_header / jurnal_detail.
 *
 * Tidak ada controller, model, atau service lain yang boleh menyentuh dua
 * tabel itu langsung.
 */
class JurnalService
{
    public function __construct(
        private PeriodeService $periode,
    ) {}

    /**
     * Membuat jurnal dari template master_detail_transaksi.
     *
     * @param  array<string,mixed>  $payload  nilai untuk sumber_variabel + konteks:
     *         id_kas_bank, id_unit_usaha, is_anggota, id_pihak
     * @return int id_jurnal
     */
    public function posting(
        string $kodeTransaksi,
        int $koperasiId,
        string $tanggal,
        array $payload,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $keterangan = null,
        string $jenisJurnal = 'OTOMATIS',
    ): int {
        $this->periode->pastikanTerbuka($koperasiId, $tanggal);

        $template = DB::table('master_detail_transaksi')
            ->where('kode_transaksi', $kodeTransaksi)
            ->orderBy('urutan')
            ->get();

        if ($template->isEmpty()) {
            throw new RuntimeException("Template jurnal '{$kodeTransaksi}' tidak ditemukan.");
        }

        $baris = [];
        $totalD = '0';
        $totalK = '0';

        foreach ($template as $tpl) {
            $nilai = $payload[$tpl->sumber_variabel] ?? null;

            if ($nilai === null) {
                if ($tpl->is_optional) {
                    continue;
                }
                throw new RuntimeException(
                    "Variabel '{$tpl->sumber_variabel}' tidak ada di payload transaksi {$kodeTransaksi}."
                );
            }

            $nilai = $this->bulat($nilai);
            if (bccomp($nilai, '0', 2) === 0) {
                continue; // baris bernilai nol tidak perlu dijurnal
            }

            $akun = $tpl->kode_anak ?? $this->resolveAkunDinamis($tpl->akun_dinamis, $payload);

            $baris[] = [
                'urutan'     => count($baris) + 1,
                'kode_anak'  => $akun,
                'debet'      => $tpl->posisi === 'D' ? $nilai : '0.00',
                'kredit'     => $tpl->posisi === 'K' ? $nilai : '0.00',
                'keterangan' => $keterangan,
                'id_pihak'   => $payload['id_pihak'] ?? null,
            ];

            $tpl->posisi === 'D'
                ? $totalD = bcadd($totalD, $nilai, 2)
                : $totalK = bcadd($totalK, $nilai, 2);
        }

        if (bccomp($totalD, $totalK, 2) !== 0) {
            throw new RuntimeException(
                "Jurnal {$kodeTransaksi} tidak seimbang: D={$totalD} K={$totalK}."
            );
        }

        if (bccomp($totalD, '0', 2) === 0) {
            throw new RuntimeException("Jurnal {$kodeTransaksi} bernilai nol.");
        }

        $c = Carbon::parse($tanggal);

        return DB::transaction(function () use (
            $koperasiId, $c, $tanggal, $kodeTransaksi, $jenisJurnal,
            $sourceType, $sourceId, $keterangan, $totalD, $totalK, $baris
        ) {
            $idJurnal = DB::table('jurnal_header')->insertGetId([
                'id_koperasi'    => $koperasiId,
                'no_jurnal'      => app(NomorDokumen::class)->berikutnya($koperasiId, 'JU', $c),
                'tanggal_jurnal' => $tanggal,
                'periode_tahun'  => $c->year,
                'periode_bulan'  => $c->month,
                'kode_transaksi' => $kodeTransaksi,
                'jenis_jurnal'   => $jenisJurnal,
                'source_type'    => $sourceType,
                'source_id'      => $sourceId,
                'keterangan'     => $keterangan,
                'total_debet'    => $totalD,
                'total_kredit'   => $totalK,
                'status'         => 'POSTED',
                'created_by'     => auth()->id(),
                'posted_by'      => auth()->id(),
                'posted_at'      => now(),
                'created_at'     => now(),
            ]);

            foreach ($baris as &$b) {
                $b['id_jurnal'] = $idJurnal;
            }
            DB::table('jurnal_detail')->insert($baris);

            return $idJurnal;
        });
    }

    /**
     * Jurnal pembalik. Jurnal asal TIDAK dihapus — itu jejak audit.
     *
     * PERINGATAN: kalau jurnal asal berdampak stok, panggil juga
     * StokService::balik(). Membalik jurnal tanpa membalik kartu stok
     * membuat nilai Persediaan di Neraca berbeda dari nilai di Gudang.
     */
    public function balik(int $idJurnalAsal, string $tanggalBalik): int
    {
        return DB::transaction(function () use ($idJurnalAsal, $tanggalBalik) {
            $asal = DB::table('jurnal_header')->where('id_jurnal', $idJurnalAsal)->lockForUpdate()->first();

            if (! $asal) {
                throw new RuntimeException("Jurnal {$idJurnalAsal} tidak ditemukan.");
            }
            if ($asal->status !== 'POSTED') {
                throw new RuntimeException("Hanya jurnal POSTED yang bisa dibalik. Status saat ini: {$asal->status}.");
            }

            $this->periode->pastikanTerbuka($asal->id_koperasi, $tanggalBalik);

            $c = Carbon::parse($tanggalBalik);

            $idBaru = DB::table('jurnal_header')->insertGetId([
                'id_koperasi'    => $asal->id_koperasi,
                'no_jurnal'      => app(NomorDokumen::class)->berikutnya($asal->id_koperasi, 'JB', $c),
                'tanggal_jurnal' => $tanggalBalik,
                'periode_tahun'  => $c->year,
                'periode_bulan'  => $c->month,
                'kode_transaksi' => $asal->kode_transaksi,
                'jenis_jurnal'   => 'PEMBALIK',
                'source_type'    => $asal->source_type,
                'source_id'      => $asal->source_id,
                'keterangan'     => "Koreksi atas jurnal {$asal->no_jurnal}",
                'total_debet'    => $asal->total_kredit,
                'total_kredit'   => $asal->total_debet,
                'status'         => 'POSTED',
                'id_jurnal_asal' => $idJurnalAsal,
                'created_by'     => auth()->id(),
                'posted_by'      => auth()->id(),
                'posted_at'      => now(),
                'created_at'     => now(),
            ]);

            $detail = DB::table('jurnal_detail')->where('id_jurnal', $idJurnalAsal)->orderBy('urutan')->get();

            $baris = $detail->map(fn ($d, $i) => [
                'id_jurnal'  => $idBaru,
                'urutan'     => $i + 1,
                'kode_anak'  => $d->kode_anak,
                'debet'      => $d->kredit,   // D dan K ditukar
                'kredit'     => $d->debet,
                'keterangan' => 'pembalik',
                'id_pihak'   => $d->id_pihak,
            ])->all();

            DB::table('jurnal_detail')->insert($baris);
            DB::table('jurnal_header')->where('id_jurnal', $idJurnalAsal)->update(['status' => 'REVERSED']);

            return $idBaru;
        });
    }

    /**
     * Menerjemahkan akun dinamis menjadi kode akun konkret.
     */
    private function resolveAkunDinamis(?string $jenis, array $payload): string
    {
        return match ($jenis) {
            'KAS_BANK' => DB::table('master_kas_bank')
                ->where('id_kas_bank', $payload['id_kas_bank']
                    ?? throw new RuntimeException('payload id_kas_bank wajib untuk akun KAS_BANK'))
                ->value('kode_akun'),

            'PERSEDIAAN_UNIT' => $this->akunUnit($payload, 'kode_akun_persediaan'),
            'HPP_UNIT'        => $this->akunUnit($payload, 'kode_akun_hpp'),

            'PENDAPATAN_UNIT' => $this->akunUnit(
                $payload,
                ($payload['is_anggota'] ?? false)
                    ? 'kode_akun_pendapatan_anggota'
                    : 'kode_akun_pendapatan_non_anggota'
            ),

            default => throw new RuntimeException("Akun dinamis '{$jenis}' tidak dikenal."),
        };
    }

    private function akunUnit(array $payload, string $kolom): string
    {
        $id = $payload['id_unit_usaha']
            ?? throw new RuntimeException('payload id_unit_usaha wajib untuk akun per unit usaha');

        return DB::table('master_unit_usaha')->where('id_unit_usaha', $id)->value($kolom)
            ?? throw new RuntimeException("Unit usaha {$id} belum punya pemetaan {$kolom}.");
    }

    /** Selalu bcmath untuk uang, jangan aritmetika float. */
    private function bulat(mixed $n): string
    {
        return bcadd((string) $n, '0', 2);
    }
}
