<?php

namespace App\Http\Controllers\Penjualan;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Penjualan\Penjualan;

class PenjualanController extends ModuleCrudController
{
    protected string $model = Penjualan::class;
    protected string $view = 'penjualan.index';
    protected string $title = 'Penjualan';
    protected string $routeBase = 'penjualan.penjualan';
    protected array $withRelations = ['pihak', 'unitUsaha', 'gudang'];
}
