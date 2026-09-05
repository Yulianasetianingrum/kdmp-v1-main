<?php

namespace App\Models\Master;

use App\Models\Concerns\BelongsToKoperasi;
use Illuminate\Database\Eloquent\Model;

class AsetTetap extends Model
{
    use BelongsToKoperasi;

    protected $table = 'aset_tetap';
    protected $primaryKey = 'id_aset';
    public $timestamps = false;

    protected $fillable = [
        'id_koperasi', 'kode_aset', 'nama_aset', 'kategori', 'tgl_perolehan',
        'nilai_perolehan', 'nilai_residu', 'umur_bulan', 'akum_penyusutan',
        'kode_akun_aset', 'kode_akun_akum', 'kode_akun_biaya', 'status',
    ];

    protected function casts(): array
    {
        return [
            'tgl_perolehan' => 'date',
            'nilai_perolehan' => 'decimal:2',
            'nilai_residu' => 'decimal:2',
            'akum_penyusutan' => 'decimal:2',
        ];
    }
}
