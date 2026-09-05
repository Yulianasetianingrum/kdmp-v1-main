<?php

namespace App\Http\Controllers\Gudang;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Gudang\KerusakanBarang;

class KerusakanBarangController extends ModuleCrudController
{
    protected string $model = KerusakanBarang::class;
    protected string $view = 'gudang.kerusakan';
    protected string $title = 'Kerusakan / Susut Barang';
    protected string $routeBase = 'gudang.kerusakan';
    protected array $withRelations = ['barang'];
}
