<?php

namespace App\Http\Controllers\Penjualan;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Penjualan\ReturPenjualan;

class ReturPenjualanController extends ModuleCrudController
{
    protected string $model = ReturPenjualan::class;
    protected string $view = 'penjualan.retur';
    protected string $title = 'Retur Penjualan';
    protected string $routeBase = 'penjualan.retur';
    protected array $withRelations = ['penjualan'];
}
