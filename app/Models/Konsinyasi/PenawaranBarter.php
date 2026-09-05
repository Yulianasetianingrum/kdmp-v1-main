<?php

namespace App\Models\Konsinyasi;

use App\Models\Tenant\KoperasiDesa;
use Illuminate\Database\Eloquent\Model;

class PenawaranBarter extends Model
{
    protected $table = 'penawaran_barter';

    protected $fillable = [
        'id_permintaan_barter', 'id_koperasi_penawar', 'id_penawar',
        'qty_ditawarkan_dasar', 'harga_titip_satuan', 'status', 'catatan',
    ];

    protected function casts(): array
    {
        return ['qty_ditawarkan_dasar' => 'decimal:4', 'harga_titip_satuan' => 'decimal:2'];
    }

    public function permintaan()
    {
        return $this->belongsTo(PermintaanBarter::class, 'id_permintaan_barter');
    }

    public function koperasiPenawar()
    {
        return $this->belongsTo(KoperasiDesa::class, 'id_koperasi_penawar', 'id_koperasi');
    }
}
