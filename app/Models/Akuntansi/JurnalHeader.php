<?php

namespace App\Models\Akuntansi;

use App\Models\Concerns\BelongsToKoperasi;
use Illuminate\Database\Eloquent\Model;

class JurnalHeader extends Model
{
    use BelongsToKoperasi;

    protected $table = 'jurnal_header';
    protected $primaryKey = 'id_jurnal';
    const UPDATED_AT = null;

    protected $fillable = [
        'id_koperasi', 'no_jurnal', 'nomor_nota', 'tanggal_jurnal', 'periode_tahun',
        'periode_bulan', 'kode_transaksi', 'jenis_jurnal', 'source_type', 'source_id',
        'keterangan', 'total_debet', 'total_kredit', 'status', 'id_jurnal_asal',
        'created_by', 'posted_by', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_jurnal' => 'date',
            'total_debet' => 'decimal:2',
            'total_kredit' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    public function detail()
    {
        return $this->hasMany(JurnalDetail::class, 'id_jurnal', 'id_jurnal')->orderBy('urutan');
    }

    public function jurnalAsal()
    {
        return $this->belongsTo(self::class, 'id_jurnal_asal', 'id_jurnal');
    }
}
