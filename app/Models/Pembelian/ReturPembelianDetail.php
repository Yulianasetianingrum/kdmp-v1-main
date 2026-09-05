<?php

namespace App\Models\Pembelian;

use App\Models\Master\Barang;
use Illuminate\Database\Eloquent\Model;

class ReturPembelianDetail extends Model
{
    protected $table = 'retur_pembelian_detail';
    protected $primaryKey = 'id_detail';
    public $timestamps = false;

    protected $fillable = ['id_retur', 'id_barang', 'qty_dasar', 'hpp_rata2', 'nilai'];

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'id_barang', 'id_barang');
    }
}
