<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Perencanaan\PermintaanPengadaan;

class PermintaanPengadaanController extends ModuleCrudController
{
    protected string $model = PermintaanPengadaan::class;
    protected string $view = 'perencanaan.permintaan-pengadaan';
    protected string $title = 'Permintaan Pengadaan (PR)';
    protected string $routeBase = 'perencanaan.permintaan-pengadaan';
    protected array $withRelations = ['pihak'];
}
