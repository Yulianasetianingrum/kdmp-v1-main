<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Master\Barang;

class BarangController extends ModuleCrudController
{
    protected string $model = Barang::class;
    protected string $view = 'master.barang';
    protected string $title = 'Barang';
    protected string $routeBase = 'master.barang';
    protected array $withRelations = ['komoditas', 'satuanDasar'];
}
