<?php

namespace App\Models\Gudang;

use App\Models\Master\Barang;
use App\Models\Master\Gudang as GudangModel;
use Illuminate\Database\Eloquent\Model;

/**
 * State stok berjalan. hpp_rata2 diperbarui setiap penerimaan (Moving Average),
 * dipakai (tidak diubah) setiap pengeluaran. Barang konsinyasi TIDAK masuk sini
 * — lihat App\Models\Konsinyasi\StokKonsinyasi.
 */
class Stok extends Model
{
    protected $table = 'stok';
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'id_gudang', 'id_barang', 'qty_on_hand', 'qty_reserved', 'hpp_rata2', 'nilai_persediaan',
    ];

    protected function casts(): array
    {
        return [
            'qty_on_hand' => 'decimal:4',
            'qty_reserved' => 'decimal:4',
            'hpp_rata2' => 'decimal:4',
            'nilai_persediaan' => 'decimal:2',
        ];
    }

    public function gudang()
    {
        return $this->belongsTo(GudangModel::class, 'id_gudang', 'id_gudang');
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'id_barang', 'id_barang');
    }
}
