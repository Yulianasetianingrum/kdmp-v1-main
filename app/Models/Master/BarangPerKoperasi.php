<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class BarangPerKoperasi extends Model
{
    protected $table = 'barang_per_koperasi';
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'id_koperasi', 'id_barang', 'stok_minimum', 'stok_maksimum',
        'harga_jual_standar', 'is_dijual',
    ];

    protected function casts(): array
    {
        return ['is_dijual' => 'boolean'];
    }
}
