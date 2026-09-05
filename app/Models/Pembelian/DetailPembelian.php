<?php

namespace App\Models\Pembelian;

use App\Models\Master\Barang;
use Illuminate\Database\Eloquent\Model;

class DetailPembelian extends Model
{
    protected $table = 'detail_pembelian';
    protected $primaryKey = 'id_detail';
    public $timestamps = false;

    protected $fillable = [
        'id_pembelian', 'id_barang', 'id_satuan_input', 'qty_input',
        'faktor_konversi', 'qty_dasar', 'harga_satuan_input', 'subtotal',
    ];

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'id_barang', 'id_barang');
    }
}
