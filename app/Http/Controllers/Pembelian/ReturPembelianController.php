<?php

namespace App\Http\Controllers\Pembelian;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Pembelian\ReturPembelian;

class ReturPembelianController extends ModuleCrudController
{
    protected string $model = ReturPembelian::class;
    protected string $view = 'pembelian.retur';
    protected string $title = 'Retur Pembelian';
    protected string $routeBase = 'pembelian.retur';
    protected array $withRelations = ['pembelian'];
}
