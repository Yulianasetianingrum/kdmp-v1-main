<?php

namespace App\Http\Controllers\Gudang;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Gudang\PenerimaanBarang;

class PenerimaanBarangController extends ModuleCrudController
{
    protected string $model = PenerimaanBarang::class;
    protected string $view = 'gudang.penerimaan';
    protected string $title = 'Penerimaan Barang (GRN)';
    protected string $routeBase = 'gudang.penerimaan';
    protected array $withRelations = ['gudang', 'pihak'];
}
