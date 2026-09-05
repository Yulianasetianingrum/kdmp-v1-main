<?php

namespace App\Models\Gudang;

use App\Models\Master\Barang;
use Illuminate\Database\Eloquent\Model;

class OpnameDetail extends Model
{
    protected $table = 'opname_detail';
    protected $primaryKey = 'id_detail';
    public $timestamps = false;

    protected $fillable = [
        'id_opname', 'id_barang', 'qty_sistem', 'qty_fisik', 'selisih',
        'hpp_rata2', 'nilai_selisih', 'keterangan',
    ];

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'id_barang', 'id_barang');
    }
}
