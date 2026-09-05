<?php

namespace App\Http\Controllers\Gudang;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Gudang\KartuStok;

/** Laporan mutasi stok, append-only — hanya index (read-only). */
class KartuStokController extends ModuleCrudController
{
    protected string $model = KartuStok::class;
    protected string $view = 'gudang.kartu-stok';
    protected string $title = 'Kartu Stok';
    protected string $routeBase = 'gudang.kartu-stok';
    protected array $withRelations = ['barang'];
}
