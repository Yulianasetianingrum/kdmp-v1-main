<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class Satuan extends Model
{
    protected $table = 'satuan';

    protected $fillable = ['kode_satuan', 'alias_json', 'is_active'];

    protected function casts(): array
    {
        return ['alias_json' => 'array', 'is_active' => 'boolean'];
    }
}
