<?php

namespace App\Models\Master;

use App\Models\Akuntansi\Coa;
use Illuminate\Database\Eloquent\Model;

class UnitUsaha extends Model
{
    protected $table = 'master_unit_usaha';
    protected $primaryKey = 'id_unit_usaha';

    protected $fillable = [
        'kode_unit_usaha', 'nama_unit_usaha', 'kode_akun_persediaan',
        'kode_akun_pendapatan_anggota', 'kode_akun_pendapatan_non_anggota',
        'kode_akun_hpp', 'keterangan',
    ];

    public function akunPersediaan()
    {
        return $this->belongsTo(Coa::class, 'kode_akun_persediaan', 'kode_anak');
    }

    public function akunHpp()
    {
        return $this->belongsTo(Coa::class, 'kode_akun_hpp', 'kode_anak');
    }
}
