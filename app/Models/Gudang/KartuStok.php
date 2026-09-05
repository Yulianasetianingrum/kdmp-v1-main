<?php

namespace App\Models\Gudang;

use App\Models\Concerns\BelongsToKoperasi;
use App\Models\Master\Barang;
use Illuminate\Database\Eloquent\Model;

/** Append-only, kronologis. Backdate DILARANG — koreksi = mutasi baru hari ini. */
class KartuStok extends Model
{
    use BelongsToKoperasi;

    protected $table = 'kartu_stok';
    protected $primaryKey = 'id_kartu';
    const UPDATED_AT = null;

    protected $fillable = [
        'id_koperasi', 'id_gudang', 'id_barang', 'tanggal', 'jenis_mutasi',
        'ref_tipe', 'ref_id', 'qty_masuk', 'qty_keluar', 'harga_satuan',
        'nilai_mutasi', 'saldo_qty', 'saldo_nilai', 'hpp_rata2_setelah',
        'jenis_kejadian', 'id_jurnal', 'created_by',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'id_barang', 'id_barang');
    }
}
