<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class Komoditas extends Model
{
    protected $table = 'komoditas';

    protected $fillable = ['kategori', 'nama', 'alias_json', 'is_active'];

    protected function casts(): array
    {
        return ['alias_json' => 'array', 'is_active' => 'boolean'];
    }

    public function barang()
    {
        return $this->hasMany(Barang::class, 'id_komoditas');
    }
}
