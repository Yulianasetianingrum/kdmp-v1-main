<?php

namespace App\Models\Akuntansi;

use Illuminate\Database\Eloquent\Model;

class MasterDetailTransaksi extends Model
{
    protected $table = 'master_detail_transaksi';
    protected $primaryKey = 'id_master';
    public $timestamps = false;

    protected $fillable = [
        'kode_transaksi', 'urutan', 'kode_anak', 'akun_dinamis',
        'posisi', 'sumber_variabel', 'is_optional',
    ];

    protected function casts(): array
    {
        return ['is_optional' => 'boolean'];
    }
}
