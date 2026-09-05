<?php

namespace App\Models\Konsinyasi;

use App\Models\Master\Barang;
use Illuminate\Database\Eloquent\Model;

class PengirimanKonsinyasiDetail extends Model
{
    protected $table = 'pengiriman_konsinyasi_detail';
    protected $primaryKey = 'id_detail';
    public $timestamps = false;

    protected $fillable = [
        'id_kiriman', 'id_barang', 'qty_dasar', 'harga_titip_satuan',
        'harga_jual_saran', 'hpp_pemilik', 'total_nilai_titip', 'total_hpp',
    ];

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'id_barang', 'id_barang');
    }
}
