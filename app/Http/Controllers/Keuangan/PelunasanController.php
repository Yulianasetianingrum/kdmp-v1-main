<?php

namespace App\Http\Controllers\Keuangan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Keuangan\StorePelunasanRequest;
use App\Models\Keuangan\Hutang;
use App\Models\Keuangan\Pelunasan;
use App\Models\Keuangan\PelunasanDetail;
use App\Models\Keuangan\Piutang;
use App\Models\Master\KasBank;
use App\Models\Master\Pihak;
use App\Services\Finance\JurnalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * PelunasanController — Mencatat:
 *   1. Penerimaan pembayaran piutang dari anggota/pihak eksternal  (jenis: terima_piutang)
 *   2. Pembayaran hutang koperasi ke supplier/pihak ketiga         (jenis: bayar_hutang)
 *
 * Satu transaksi pelunasan bisa mengalokasikan ke BANYAK baris piutang/hutang.
 *
 * Akun yang didebet/dikredit diambil dari record piutang/hutang itu sendiri
 * (bukan hardcode), sehingga otomatis handle:
 *   - Piutang Dagang (1132)
 *   - Piutang Konsinyasi (1135)
 *   - Hutang Dagang (2111)
 *   - Hutang Konsinyasi (2117)
 */
class PelunasanController extends Controller
{
    // =========================================================================
    // index() — Riwayat pelunasan
    // =========================================================================

    public function index(Request $request): View
    {
        $idKoperasi = app('koperasi_aktif');

        $query = Pelunasan::with(['pihak', 'kasBank'])
            ->where('id_koperasi', $idKoperasi);

        // Filter jenis
        if ($jenis = $request->query('jenis')) {
            $query->where('jenis', $jenis);
        }

        // Filter status posting
        if ($status = $request->query('status')) {
            $query->where('status_posting', $status);
        }

        // Cari by pihak / kode
        if ($q = $request->query('q')) {
            $query->where(function ($sub) use ($q) {
                $sub->where('kode_pelunasan', 'like', "%{$q}%")
                    ->orWhereHas('pihak', fn ($p) => $p->where('nama', 'like', "%{$q}%"));
            });
        }

        $items = $query->latest('id_pelunasan')->paginate(15)->withQueryString();

        return view('keuangan.pelunasan.index', compact('items'));
    }

    // =========================================================================
    // create() — Form input pelunasan baru
    // =========================================================================

    public function create(Request $request): View
    {
        $idKoperasi = app('koperasi_aktif');

        $kasBanks = KasBank::where('id_koperasi', $idKoperasi)
            ->where('is_active', 1)
            ->get();

        // Semua pihak: anggota + supplier (form akan filter via JS sesuai jenis)
        $pihaks = Pihak::where('id_koperasi', $idKoperasi)
            ->orderBy('nama')
            ->get(['id_pihak', 'nama', 'tipe']);

        return view('keuangan.pelunasan.create', compact('kasBanks', 'pihaks'));
    }

    // =========================================================================
    // terbuka() — AJAX: ambil piutang/hutang terbuka milik pihak
    // =========================================================================

    /**
     * Dipanggil via AJAX saat kasir memilih pihak di form create.
     * Return JSON list piutang/hutang yang belum lunas milik pihak.
     */
    public function terbuka(Request $request, Pihak $pihak): JsonResponse
    {
        $idKoperasi = app('koperasi_aktif');
        $jenis      = $request->query('jenis'); // 'terima_piutang' | 'bayar_hutang'

        // Guard: pastikan pihak milik koperasi aktif
        if ($pihak->id_koperasi !== $idKoperasi) {
            return response()->json(['error' => 'Pihak tidak ditemukan.'], 403);
        }

        if ($jenis === 'terima_piutang') {
            $rows = Piutang::where('id_koperasi', $idKoperasi)
                ->where('id_pihak', $pihak->id_pihak)
                ->whereIn('status', ['belum_lunas', 'sebagian'])
                ->orderBy('tgl_jatuh_tempo')
                ->get(['id_piutang', 'kode_akun', 'sumber_tipe', 'tanggal', 'tgl_jatuh_tempo', 'nilai_awal', 'nilai_terbayar', 'status'])
                ->map(fn ($p) => [
                    'id'             => $p->id_piutang,
                    'type'           => 'piutang',
                    'kode_akun'      => $p->kode_akun,
                    'sumber_tipe'    => $p->sumber_tipe,
                    'tanggal'        => $p->tanggal->format('d M Y'),
                    'jatuh_tempo'    => $p->tgl_jatuh_tempo->format('d M Y'),
                    'nilai_awal'     => (float) $p->nilai_awal,
                    'nilai_terbayar' => (float) $p->nilai_terbayar,
                    'sisa'           => (float) $p->sisa(),
                    'status'         => $p->status,
                ]);

            return response()->json($rows);
        }

        if ($jenis === 'bayar_hutang') {
            $rows = Hutang::where('id_koperasi', $idKoperasi)
                ->where('id_pihak', $pihak->id_pihak)
                ->whereIn('status', ['belum_lunas', 'sebagian'])
                ->orderBy('tgl_jatuh_tempo')
                ->get(['id_hutang', 'kode_akun', 'sumber_tipe', 'tanggal', 'tgl_jatuh_tempo', 'nilai_awal', 'nilai_terbayar', 'status'])
                ->map(fn ($h) => [
                    'id'             => $h->id_hutang,
                    'type'           => 'hutang',
                    'kode_akun'      => $h->kode_akun,
                    'sumber_tipe'    => $h->sumber_tipe,
                    'tanggal'        => $h->tanggal->format('d M Y'),
                    'jatuh_tempo'    => $h->tgl_jatuh_tempo->format('d M Y'),
                    'nilai_awal'     => (float) $h->nilai_awal,
                    'nilai_terbayar' => (float) $h->nilai_terbayar,
                    'sisa'           => (float) $h->sisa(),
                    'status'         => $h->status,
                ]);

            return response()->json($rows);
        }

        return response()->json([]);
    }

    // =========================================================================
    // store() — Simpan & posting jurnal
    // =========================================================================

    public function store(StorePelunasanRequest $request, JurnalService $jurnalService): RedirectResponse
    {
        $idKoperasi = app('koperasi_aktif');
        $validated  = $request->validated();
        $jenis      = $validated['jenis'];
        $isPiutang  = ($jenis === 'terima_piutang');

        // --- LANGKAH 1: Resolve & validasi setiap baris detail ---
        // (Security: pastikan setiap record milik koperasi aktif, bukan inject ID asing)
        $detailRows    = $validated['detail'];
        $barisJurnal   = [];
        $totalNilai    = '0';
        $resolvedItems = []; // [{model, nilai_bayar, kode_akun}]

        $kasBank = KasBank::where('id_koperasi', $idKoperasi)
            ->findOrFail($validated['id_kas_bank']);

        foreach ($detailRows as $idx => $baris) {
            $nilaiBayar = (string) $baris['nilai_bayar'];

            if ($isPiutang) {
                if (empty($baris['id_piutang'])) {
                    return back()->withInput()->with('error', "Baris #".($idx+1).": id_piutang wajib diisi untuk jenis terima_piutang.");
                }

                /** @var Piutang $piutang */
                $piutang = Piutang::where('id_koperasi', $idKoperasi)
                    ->whereIn('status', ['belum_lunas', 'sebagian'])
                    ->find($baris['id_piutang']);

                if (!$piutang) {
                    return back()->withInput()->with('error', "Piutang #".($baris['id_piutang'])." tidak ditemukan atau sudah lunas.");
                }

                // Nilai bayar tidak boleh melebihi sisa piutang
                $sisa = (float) $piutang->sisa();
                if ((float) $nilaiBayar > $sisa + 0.001) {
                    return back()->withInput()->with('error',
                        "Piutang #{$piutang->id_piutang}: nilai bayar Rp ".number_format($nilaiBayar, 2, ',', '.')
                        ." melebihi sisa Rp ".number_format($sisa, 2, ',', '.').".`"
                    );
                }

                $resolvedItems[] = ['model' => $piutang, 'nilai_bayar' => $nilaiBayar, 'id_piutang' => $piutang->id_piutang, 'id_hutang' => null];

            } else {
                // bayar_hutang
                if (empty($baris['id_hutang'])) {
                    return back()->withInput()->with('error', "Baris #".($idx+1).": id_hutang wajib diisi untuk jenis bayar_hutang.");
                }

                /** @var Hutang $hutang */
                $hutang = Hutang::where('id_koperasi', $idKoperasi)
                    ->whereIn('status', ['belum_lunas', 'sebagian'])
                    ->find($baris['id_hutang']);

                if (!$hutang) {
                    return back()->withInput()->with('error', "Hutang #".($baris['id_hutang'])." tidak ditemukan atau sudah lunas.");
                }

                $sisa = (float) $hutang->sisa();
                if ((float) $nilaiBayar > $sisa + 0.001) {
                    return back()->withInput()->with('error',
                        "Hutang #{$hutang->id_hutang}: nilai bayar Rp ".number_format($nilaiBayar, 2, ',', '.')
                        ." melebihi sisa Rp ".number_format($sisa, 2, ',', '.').".`"
                    );
                }

                $resolvedItems[] = ['model' => $hutang, 'nilai_bayar' => $nilaiBayar, 'id_piutang' => null, 'id_hutang' => $hutang->id_hutang];
            }

            $totalNilai = bcadd($totalNilai, $nilaiBayar, 2);
        }

        // --- LANGKAH 2: Generate kode pelunasan ---
        $prefix   = $isPiutang ? 'TPI' : 'BHU';
        $tanggal  = $validated['tanggal'];
        $yyyymm   = date('Ym', strtotime($tanggal));

        $counter = Pelunasan::where('id_koperasi', $idKoperasi)
            ->where('kode_pelunasan', 'like', "{$prefix}-{$yyyymm}-%")
            ->count();

        $kodePelunasan = sprintf('%s-%s-%04d', $prefix, $yyyymm, $counter + 1);

        // --- LANGKAH 3: Susun baris jurnal ---
        // Opsi A: resolve kode_akun dari setiap record piutang/hutang
        // Ini menghandle 1132, 1135, 2111, 2117 secara otomatis.
        if ($isPiutang) {
            // TERIMA PIUTANG: Kas masuk (D), Piutang berkurang (K) per baris
            // Karena jurnal harus balance per posting, kita aggregate per kode_akun
            $barisJurnal[] = [
                'kode_anak' => $kasBank->kode_akun,
                'posisi'    => 'D',
                'nilai'     => (float) $totalNilai,
                'keterangan'=> "Terima pelunasan piutang — {$kodePelunasan}",
            ];

            // Group by kode_akun karena bisa ada piutang dagang + konsinyasi sekaligus
            $grupAkun = [];
            foreach ($resolvedItems as $item) {
                $kodeAkun = $item['model']->kode_akun;
                $grupAkun[$kodeAkun] = bcadd($grupAkun[$kodeAkun] ?? '0', $item['nilai_bayar'], 2);
            }

            foreach ($grupAkun as $kodeAkun => $nilaiGrup) {
                $barisJurnal[] = [
                    'kode_anak' => $kodeAkun,
                    'posisi'    => 'K',
                    'nilai'     => (float) $nilaiGrup,
                    'keterangan'=> "Pelunasan piutang — {$kodePelunasan}",
                ];
            }
        } else {
            // BAYAR HUTANG: Hutang berkurang (D) per baris akun, Kas keluar (K)
            $grupAkun = [];
            foreach ($resolvedItems as $item) {
                $kodeAkun = $item['model']->kode_akun;
                $grupAkun[$kodeAkun] = bcadd($grupAkun[$kodeAkun] ?? '0', $item['nilai_bayar'], 2);
            }

            foreach ($grupAkun as $kodeAkun => $nilaiGrup) {
                $barisJurnal[] = [
                    'kode_anak' => $kodeAkun,
                    'posisi'    => 'D',
                    'nilai'     => (float) $nilaiGrup,
                    'keterangan'=> "Bayar hutang — {$kodePelunasan}",
                ];
            }

            $barisJurnal[] = [
                'kode_anak' => $kasBank->kode_akun,
                'posisi'    => 'K',
                'nilai'     => (float) $totalNilai,
                'keterangan'=> "Bayar hutang — {$kodePelunasan}",
            ];
        }

        // --- LANGKAH 4: Eksekusi dalam satu transaksi DB ---
        try {
            DB::beginTransaction();

            // 4a. INSERT header pelunasan
            $pelunasan = Pelunasan::create([
                'id_koperasi'    => $idKoperasi,
                'kode_pelunasan' => $kodePelunasan,
                'jenis'          => $jenis,
                'id_pihak'       => $validated['id_pihak'],
                'tanggal'        => $tanggal,
                'id_kas_bank'    => $kasBank->id_kas_bank,
                'total_nilai'    => $totalNilai,
                'catatan'        => $validated['catatan'] ?? null,
                'created_by'     => auth()->id(),
                'status_posting' => 'F',
            ]);

            // 4b. INSERT detail + UPDATE piutang/hutang
            foreach ($resolvedItems as $item) {
                PelunasanDetail::create([
                    'id_pelunasan' => $pelunasan->id_pelunasan,
                    'id_piutang'   => $item['id_piutang'],
                    'id_hutang'    => $item['id_hutang'],
                    'nilai_bayar'  => $item['nilai_bayar'],
                ]);

                /** @var Piutang|Hutang $model */
                $model = $item['model'];
                $model->nilai_terbayar = bcadd((string) $model->nilai_terbayar, $item['nilai_bayar'], 2);

                $sisa = (float) $model->sisa();
                if ($sisa <= 0.001) {
                    $model->status = 'lunas';
                } elseif ((float) $model->nilai_terbayar > 0) {
                    $model->status = 'sebagian';
                }
                $model->save();
            }

            // 4c. Posting jurnal via JurnalService
            $headerJurnal = [
                'tanggal_jurnal' => $tanggal,
                'jenis_jurnal'   => 'MANUAL',
                'kode_transaksi' => $prefix,
                'nomor_nota'     => $kodePelunasan,
                'keterangan'     => ($isPiutang ? 'Terima pelunasan piutang' : 'Bayar hutang')
                    . ' — ' . $kodePelunasan
                    . ($validated['catatan'] ? '. ' . $validated['catatan'] : ''),
            ];

            $jurnal = $jurnalService->postingManual($headerJurnal, $barisJurnal);

            // 4d. Update status posting pelunasan
            $pelunasan->update([
                'id_jurnal'      => $jurnal->id_jurnal,
                'status_posting' => 'T',
            ]);

            DB::commit();

            return redirect()
                ->route('keuangan.pelunasan.show', $pelunasan)
                ->with('success', "Pelunasan {$kodePelunasan} berhasil disimpan dan dijurnal.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Gagal memposting pelunasan: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // show() — Detail satu pelunasan
    // =========================================================================

    public function show(Pelunasan $pelunasan): View
    {
        $idKoperasi = app('koperasi_aktif');

        // Guard tenant
        abort_if($pelunasan->id_koperasi !== $idKoperasi, 403);

        $pelunasan->load([
            'pihak',
            'kasBank',
            'detail.piutang',
            'detail.hutang',
        ]);

        return view('keuangan.pelunasan.show', compact('pelunasan'));
    }
}
