<?php

namespace App\Models\Gudang;

use App\Models\Concerns\BelongsToKoperasi;
use Illuminate\Database\Eloquent\Model;

class OpnameHeader extends Model
{
    use BelongsToKoperasi;

    protected $table = 'opname_header';
    protected $primaryKey = 'id_opname';
    const UPDATED_AT = null;

    protected $fillable = [
        'id_koperasi', 'id_gudang', 'kode_opname', 'tanggal', 'status', 'approved_by',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    public function detail()
    {
        return $this->hasMany(OpnameDetail::class, 'id_opname', 'id_opname');
    }
}
