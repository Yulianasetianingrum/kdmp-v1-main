<?php

namespace App\Http\Controllers\Konsinyasi;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Konsinyasi\SetoranKonsinyasi;

class SetoranKonsinyasiController extends ModuleCrudController
{
    protected string $model = SetoranKonsinyasi::class;
    protected string $view = 'konsinyasi.setoran';
    protected string $title = 'Setoran Hasil Konsinyasi';
    protected string $routeBase = 'konsinyasi.setoran';
    protected array $withRelations = ['koperasiPenyetor', 'koperasiPenerimaDana'];
}
