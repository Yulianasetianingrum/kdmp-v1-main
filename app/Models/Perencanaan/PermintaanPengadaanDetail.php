<?php

namespace App\Models\Perencanaan;

use App\Models\Master\Barang;
use Illuminate\Database\Eloquent\Model;

class PermintaanPengadaanDetail extends Model
{
    protected $table = 'permintaan_pengadaan_detail';
    protected $primaryKey = 'id_detail';
    public $timestamps = false;

    protected $fillable = [
        'id_permintaan', 'id_barang', 'id_hasil', 'jumlah_diminta',
        'harga_perkiraan', 'subtotal',
    ];

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'id_barang', 'id_barang');
    }
}
