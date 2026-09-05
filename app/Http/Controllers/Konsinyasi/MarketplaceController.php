<?php

namespace App\Http\Controllers\Konsinyasi;

use App\Http\Controllers\Concerns\ModuleCrudController;
use App\Models\Konsinyasi\PermintaanBarter;

class MarketplaceController extends ModuleCrudController
{
    protected string $model = PermintaanBarter::class;
    protected string $view = 'konsinyasi.marketplace';
    protected string $title = 'Marketplace Barter Antar Desa';
    protected string $routeBase = 'konsinyasi.marketplace';
    protected array $withRelations = ['koperasiPemohon', 'barang'];
}
