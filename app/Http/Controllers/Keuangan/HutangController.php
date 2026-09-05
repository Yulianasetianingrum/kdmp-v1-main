<?php

namespace App\Http\Controllers\Keuangan;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Keuangan\Hutang;

/** Buku pembantu hutang — dibuat OTOMATIS oleh service transaksi. Read-only di sini. */
class HutangController extends ModuleCrudController
{
    protected string $model = Hutang::class;
    protected string $view = 'keuangan.hutang';
    protected string $title = 'Hutang';
    protected string $routeBase = 'keuangan.hutang';
    protected array $withRelations = ['pihak'];
}
