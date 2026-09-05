<?php

namespace App\Http\Controllers\Keuangan;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Keuangan\Piutang;

/** Buku pembantu piutang — dibuat OTOMATIS oleh service transaksi, bukan CRUD manual. Read-only di sini. */
class PiutangController extends ModuleCrudController
{
    protected string $model = Piutang::class;
    protected string $view = 'keuangan.piutang';
    protected string $title = 'Piutang';
    protected string $routeBase = 'keuangan.piutang';
    protected array $withRelations = ['pihak'];
}
