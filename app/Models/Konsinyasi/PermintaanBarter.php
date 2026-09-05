<?php

namespace App\Models\Konsinyasi;

use App\Models\Master\Barang;
use App\Models\Tenant\KoperasiDesa;
use Illuminate\Database\Eloquent\Model;

class PermintaanBarter extends Model
{
    protected $table = 'permintaan_barter';

    protected $fillable = [
        'id_koperasi_pemohon', 'id_pemohon', 'id_barang', 'qty_diminta_dasar',
        'tgl_dibutuhkan', 'status', 'catatan',
    ];

    protected function casts(): array
    {
        return ['qty_diminta_dasar' => 'decimal:4', 'tgl_dibutuhkan' => 'date'];
    }

    public function koperasiPemohon()
    {
        return $this->belongsTo(KoperasiDesa::class, 'id_koperasi_pemohon', 'id_koperasi');
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'id_barang', 'id_barang');
    }

    public function penawaran()
    {
        return $this->hasMany(PenawaranBarter::class, 'id_permintaan_barter');
    }
}
