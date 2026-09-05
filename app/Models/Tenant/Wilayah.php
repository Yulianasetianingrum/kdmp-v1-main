<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class Wilayah extends Model
{
    protected $table = 'wilayah';

    protected $fillable = ['parent_id', 'tingkat', 'nama', 'kode_bps', 'lat', 'lng'];

    protected function casts(): array
    {
        return ['lat' => 'decimal:7', 'lng' => 'decimal:7'];
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function anak()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function koperasi()
    {
        return $this->hasOne(KoperasiDesa::class, 'id_wilayah');
    }
}
