<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class KonversiSatuan extends Model
{
    protected $table = 'konversi_satuan';
    protected $primaryKey = 'id_konversi';
    public $timestamps = false;

    protected $fillable = [
        'id_barang', 'id_satuan', 'faktor_ke_dasar', 'is_default_beli', 'is_default_jual',
    ];

    protected function casts(): array
    {
        return [
            'faktor_ke_dasar' => 'decimal:6',
            'is_default_beli' => 'boolean',
            'is_default_jual' => 'boolean',
        ];
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'id_barang', 'id_barang');
    }

    public function satuan()
    {
        return $this->belongsTo(Satuan::class, 'id_satuan');
    }
}
