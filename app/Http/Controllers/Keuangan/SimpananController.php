<?php

namespace App\Http\Controllers\Keuangan;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Keuangan\SimpananAnggota;

class SimpananController extends ModuleCrudController
{
    protected string $model = SimpananAnggota::class;
    protected string $view = 'keuangan.simpanan';
    protected string $title = 'Simpanan Anggota (Pokok / Wajib / Sukarela)';
    protected string $routeBase = 'keuangan.simpanan';
    protected array $withRelations = ['pihak'];
}
