<?php

namespace App\Http\Controllers;

use App\Models\Keuangan\Hutang;
use App\Models\Keuangan\Piutang;
use App\Models\Master\Barang;
use App\Models\Penjualan\Penjualan;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $ringkasan = [
            'jumlah_barang' => Barang::count(),
            'jumlah_penjualan' => Penjualan::count(),
            'piutang_terbuka' => Piutang::whereIn('status', ['belum_lunas', 'sebagian'])->count(),
            'hutang_terbuka' => Hutang::whereIn('status', ['belum_lunas', 'sebagian'])->count(),
        ];

        return view('dashboard', compact('ringkasan'));
    }
}
