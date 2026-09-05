<?php

namespace App\Http\Controllers\Konsinyasi;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Konsinyasi\PengirimanKonsinyasi;

class PengirimanKonsinyasiController extends ModuleCrudController
{
    protected string $model = PengirimanKonsinyasi::class;
    protected string $view = 'konsinyasi.pengiriman';
    protected string $title = 'Pengiriman Konsinyasi';
    protected string $routeBase = 'konsinyasi.pengiriman';
    protected array $withRelations = ['koperasiPemilik', 'koperasiPenerima'];
}
