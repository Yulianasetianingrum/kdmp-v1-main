<?php

namespace App\Models\Penjualan;

use App\Models\Konsinyasi\StokKonsinyasi;
use App\Models\Master\Barang;
use Illuminate\Database\Eloquent\Model;

/** Kalau id_stok_konsinyasi terisi, baris ini penjualan titipan (KJL), bukan biasa. */
class DetailPenjualan extends Model
{
    protected $table = 'detail_penjualan';
    protected $primaryKey = 'id_detail';
    public $timestamps = false;

    protected $fillable = [
        'id_penjualan', 'id_barang', 'id_stok_konsinyasi', 'id_satuan_input',
        'qty_input', 'faktor_konversi', 'qty_dasar', 'harga_satuan', 'subtotal',
        'hpp_satuan_dasar', 'total_hpp', 'harga_titip_satuan',
    ];

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'id_barang', 'id_barang');
    }

    public function stokKonsinyasi()
    {
        return $this->belongsTo(StokKonsinyasi::class, 'id_stok_konsinyasi', 'id_stok_konsinyasi');
    }
}
