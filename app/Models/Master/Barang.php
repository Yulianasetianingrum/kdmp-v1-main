<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class Barang extends Model
{
    protected $table = 'master_barang';
    protected $primaryKey = 'id_barang';

    protected $fillable = [
        'kode_barang', 'id_komoditas', 'id_unit_usaha', 'nama_barang',
        'id_satuan_dasar', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function komoditas()
    {
        return $this->belongsTo(Komoditas::class, 'id_komoditas');
    }

    public function unitUsaha()
    {
        return $this->belongsTo(UnitUsaha::class, 'id_unit_usaha', 'id_unit_usaha');
    }

    public function satuanDasar()
    {
        return $this->belongsTo(Satuan::class, 'id_satuan_dasar');
    }

    public function konversiSatuan()
    {
        return $this->hasMany(KonversiSatuan::class, 'id_barang', 'id_barang');
    }
}
