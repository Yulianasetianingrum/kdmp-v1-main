<?php

namespace App\Models\Master;

use App\Models\Akuntansi\Coa;
use App\Models\Concerns\BelongsToKoperasi;
use Illuminate\Database\Eloquent\Model;

class KasBank extends Model
{
    use BelongsToKoperasi;

    protected $table = 'master_kas_bank';
    protected $primaryKey = 'id_kas_bank';
    public $timestamps = false;

    protected $fillable = [
        'id_koperasi', 'jenis', 'nama', 'no_rekening', 'kode_akun', 'is_default', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_active' => 'boolean'];
    }

    public function akun()
    {
        return $this->belongsTo(Coa::class, 'kode_akun', 'kode_anak');
    }
}
