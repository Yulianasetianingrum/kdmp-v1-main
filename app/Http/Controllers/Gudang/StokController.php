<?php

namespace App\Http\Controllers\Gudang;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Gudang\Stok;

/** Laporan posisi stok on-hand + HPP moving average saat ini — read-only. */
class StokController extends ModuleCrudController
{
    protected string $model = Stok::class;
    protected string $view = 'gudang.stok';
    protected string $title = 'Posisi Stok';
    protected string $routeBase = 'gudang.stok';
    protected array $withRelations = ['gudang', 'barang'];
}
