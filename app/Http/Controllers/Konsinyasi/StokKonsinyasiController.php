<?php

namespace App\Http\Controllers\Konsinyasi;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Konsinyasi\StokKonsinyasi;

/** Posisi stok titipan di gudang penerima — read-only. */
class StokKonsinyasiController extends ModuleCrudController
{
    protected string $model = StokKonsinyasi::class;
    protected string $view = 'konsinyasi.stok';
    protected string $title = 'Stok Konsinyasi';
    protected string $routeBase = 'konsinyasi.stok';
    protected array $withRelations = ['barang', 'kiriman'];
}
