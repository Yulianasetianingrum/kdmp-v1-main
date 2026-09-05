<?php

namespace App\Http\Controllers\Pembelian;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Pembelian\Pembelian;

class PembelianController extends ModuleCrudController
{
    protected string $model = Pembelian::class;
    protected string $view = 'pembelian.index';
    protected string $title = 'Pembelian';
    protected string $routeBase = 'pembelian.pembelian';
    protected array $withRelations = ['pihak', 'unitUsaha', 'gudang'];
}
