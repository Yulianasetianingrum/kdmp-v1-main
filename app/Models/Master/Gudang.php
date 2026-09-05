<?php

namespace App\Models\Master;

use App\Models\Concerns\BelongsToKoperasi;
use Illuminate\Database\Eloquent\Model;

class Gudang extends Model
{
    use BelongsToKoperasi;

    protected $table = 'gudang';
    protected $primaryKey = 'id_gudang';
    public $timestamps = false;

    protected $fillable = ['id_koperasi', 'kode_gudang', 'nama_gudang', 'alamat', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
