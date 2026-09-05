<?php

namespace App\Models\Konsinyasi;

use Illuminate\Database\Eloquent\Model;

class KartuKonsinyasi extends Model
{
    protected $table = 'kartu_konsinyasi';
    protected $primaryKey = 'id_kartu_kons';
    const UPDATED_AT = null;

    protected $fillable = [
        'id_stok_konsinyasi', 'tanggal', 'jenis_mutasi', 'ref_tipe', 'ref_id',
        'qty', 'harga_titip_satuan', 'harga_jual_satuan', 'saldo_qty',
        'id_jurnal_penerima', 'id_jurnal_pemilik',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    public function stokKonsinyasi()
    {
        return $this->belongsTo(StokKonsinyasi::class, 'id_stok_konsinyasi', 'id_stok_konsinyasi');
    }
}
