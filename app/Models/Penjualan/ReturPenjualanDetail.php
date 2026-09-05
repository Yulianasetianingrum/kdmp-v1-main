<?php

namespace App\Models\Penjualan;

use App\Models\Master\Barang;
use Illuminate\Database\Eloquent\Model;

class ReturPenjualanDetail extends Model
{
    protected $table = 'retur_penjualan_detail';
    protected $primaryKey = 'id_detail';
    public $timestamps = false;

    protected $fillable = [
        'id_retur', 'id_barang', 'qty_dasar', 'harga_satuan', 'nilai', 'hpp_satuan', 'total_hpp',
    ];

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'id_barang', 'id_barang');
    }
}
