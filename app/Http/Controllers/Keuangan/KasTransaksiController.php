<?php

namespace App\Http\Controllers\Keuangan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Keuangan\StoreKasTransaksiRequest;
use App\Models\Keuangan\KasTransaksi;
use App\Models\Master\KasBank;
use App\Models\Akuntansi\Coa;
use App\Services\Finance\JurnalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class KasTransaksiController extends Controller
{
    public function index(Request $request): View
    {
        $idKoperasi = app('koperasi_aktif');

        // Ambil list semua Kas/Bank yang aktif
        $kasBanks = KasBank::where('id_koperasi', $idKoperasi)
            ->where('is_active', 1)
            ->get();

        // Hitung saldo realtime dari v_saldo_berjalan (akumulasi semua periode)
        $saldoKas = DB::table('v_saldo_berjalan')
            ->where('id_koperasi', $idKoperasi)
            ->whereIn('kode_anak', $kasBanks->pluck('kode_akun'))
            ->selectRaw('kode_anak, SUM(saldo_normal) as total_saldo')
            ->groupBy('kode_anak')
            ->get()
            ->keyBy('kode_anak');

        // Siapkan data items untuk tabel riwayat
        $query = KasTransaksi::with(['kasBank'])->where('id_koperasi', $idKoperasi);

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('kode_trx', 'like', "%{$search}%")
                  ->orWhere('keterangan', 'like', "%{$search}%");
            });
        }

        $items = $query->latest('id_kas_trx')->paginate(15)->withQueryString();

        return view('keuangan.kas-transaksi.index', compact('items', 'kasBanks', 'saldoKas'));
    }

    public function create(): View
    {
        $idKoperasi = app('koperasi_aktif');
        $kasBanks = KasBank::where('id_koperasi', $idKoperasi)->where('is_active', 1)->get();
        // Ambil akun untuk lawan (selain header dan grup kas)
        $akunLawan = Coa::where('is_transaction', 1)
            ->where('is_active', 1)
            ->orderBy('kode_anak')
            ->get();

        return view('keuangan.kas-transaksi.create', compact('kasBanks', 'akunLawan'));
    }

    public function store(StoreKasTransaksiRequest $request, JurnalService $jurnalService): RedirectResponse
    {
        $idKoperasi = app('koperasi_aktif');
        $validated = $request->validated();
        
        $jenis = $validated['jenis'];
        $nilai = (float) $validated['nilai'];
        
        // Dapatkan objek kas bank
        $kasBank = KasBank::findOrFail($validated['id_kas_bank']);
        
        // Buat record KasTransaksi
        $kasTrx = new KasTransaksi();
        $kasTrx->id_koperasi = $idKoperasi;
        $kasTrx->tanggal = $validated['tanggal'];
        $kasTrx->jenis = $jenis;
        $kasTrx->id_kas_bank = $kasBank->id_kas_bank;
        $kasTrx->nilai = $nilai;
        $kasTrx->keterangan = $validated['keterangan'];
        $kasTrx->created_by = auth()->id();
        
        // Siapkan baris jurnal
        $baris = [];
        $kodeTrxPrefix = '';

        if ($jenis === 'masuk') {
            $kodeTrxPrefix = 'KSM';
            $kasTrx->kode_akun_lawan = $validated['kode_akun_lawan'];
            
            // Kas bertambah (D), Akun lawan bertambah (K)
            $baris[] = ['kode_anak' => $kasBank->kode_akun, 'posisi' => 'D', 'nilai' => $nilai];
            $baris[] = ['kode_anak' => $validated['kode_akun_lawan'], 'posisi' => 'K', 'nilai' => $nilai];
            
        } elseif ($jenis === 'keluar') {
            $kodeTrxPrefix = 'KSK';
            $kasTrx->kode_akun_lawan = $validated['kode_akun_lawan'];
            
            // Kas berkurang (K), Akun lawan bertambah (D - contoh: biaya)
            $baris[] = ['kode_anak' => $kasBank->kode_akun, 'posisi' => 'K', 'nilai' => $nilai];
            $baris[] = ['kode_anak' => $validated['kode_akun_lawan'], 'posisi' => 'D', 'nilai' => $nilai];
            
        } elseif ($jenis === 'mutasi_antar_kas') {
            $kodeTrxPrefix = 'MTK';
            $kasBankTujuan = KasBank::findOrFail($validated['id_kas_bank_tujuan']);
            $kasTrx->id_kas_bank_tujuan = $kasBankTujuan->id_kas_bank;
            
            // Kas Asal berkurang (K), Kas Tujuan bertambah (D)
            $baris[] = ['kode_anak' => $kasBank->kode_akun, 'posisi' => 'K', 'nilai' => $nilai];
            $baris[] = ['kode_anak' => $kasBankTujuan->kode_akun, 'posisi' => 'D', 'nilai' => $nilai];
        }

        // Generate kode_trx
        $countTrx = KasTransaksi::where('id_koperasi', $idKoperasi)
            ->whereMonth('tanggal', date('m', strtotime($validated['tanggal'])))
            ->whereYear('tanggal', date('Y', strtotime($validated['tanggal'])))
            ->where('kode_trx', 'like', $kodeTrxPrefix . '%')
            ->count();
            
        $kasTrx->kode_trx = sprintf('%s-%s-%04d', $kodeTrxPrefix, date('Ym', strtotime($validated['tanggal'])), $countTrx + 1);

        try {
            DB::beginTransaction();
            
            // Simpan transaksi
            $kasTrx->save();
            
            // Posting Jurnal
            $headerJurnal = [
                'tanggal_jurnal' => $kasTrx->tanggal->toDateString(),
                'jenis_jurnal' => 'MANUAL',
                'kode_transaksi' => $kodeTrxPrefix,
                'nomor_nota' => $kasTrx->kode_trx,
                'keterangan' => $kasTrx->keterangan,
            ];
            
            $jurnal = $jurnalService->postingManual($headerJurnal, $baris);
            
            // Update status posting
            $kasTrx->id_jurnal = $jurnal->id_jurnal;
            $kasTrx->status_posting = 'T';
            $kasTrx->save();
            
            DB::commit();
            return redirect()->route('keuangan.kas-transaksi.index')->with('success', 'Transaksi kas berhasil disimpan.');
            
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Gagal memposting transaksi: ' . $e->getMessage());
        }
    }
}
