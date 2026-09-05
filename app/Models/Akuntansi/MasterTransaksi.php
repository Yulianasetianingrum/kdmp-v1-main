<?php

namespace App\Models\Akuntansi;

use Illuminate\Database\Eloquent\Model;

class MasterTransaksi extends Model
{
    protected $table = 'master_transaksi';
    protected $primaryKey = 'kode_transaksi';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = ['kode_transaksi', 'nama_transaksi', 'modul', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function detail()
    {
        return $this->hasMany(MasterDetailTransaksi::class, 'kode_transaksi', 'kode_transaksi')
            ->orderBy('urutan');
    }
}
