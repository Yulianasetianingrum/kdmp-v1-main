<?php

namespace App\Models\Akuntansi;

use Illuminate\Database\Eloquent\Model;

class Coa extends Model
{
    protected $table = 'master_coa';
    protected $primaryKey = 'kode_anak';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'kode_anak', 'kode_induk', 'nama_rekening', 'posisi_normal',
        'is_transaction', 'kelompok', 'is_kontra', 'level', 'urutan_laporan', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_kontra' => 'boolean', 'is_active' => 'boolean'];
    }

    public function induk()
    {
        return $this->belongsTo(self::class, 'kode_induk', 'kode_anak');
    }

    public function anak()
    {
        return $this->hasMany(self::class, 'kode_induk', 'kode_anak');
    }
}
