<?php

namespace App\Models\Survei;

use Illuminate\Database\Eloquent\Model;

class ModulSurvei extends Model
{
    protected $table = 'modul_survei';

    protected $fillable = ['kode', 'nama', 'versi', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function pertanyaan()
    {
        return $this->hasMany(Pertanyaan::class, 'id_modul');
    }
}
