<?php

namespace App\Models\Akuntansi;

use Illuminate\Database\Eloquent\Model;

class JurnalDetail extends Model
{
    protected $table = 'jurnal_detail';
    protected $primaryKey = 'id_detail';
    public $timestamps = false;

    protected $fillable = [
        'id_jurnal', 'urutan', 'kode_anak', 'debet', 'kredit', 'keterangan', 'id_pihak',
    ];

    protected function casts(): array
    {
        return ['debet' => 'decimal:2', 'kredit' => 'decimal:2'];
    }

    public function jurnal()
    {
        return $this->belongsTo(JurnalHeader::class, 'id_jurnal', 'id_jurnal');
    }

    public function akun()
    {
        return $this->belongsTo(Coa::class, 'kode_anak', 'kode_anak');
    }
}
