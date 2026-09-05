<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Peran extends Model
{
    protected $table = 'peran';
    protected $primaryKey = 'id_peran';
    public $timestamps = false;

    protected $fillable = ['kode', 'nama'];

    public function pengguna()
    {
        return $this->belongsToMany(Pengguna::class, 'pengguna_peran', 'id_peran', 'id_pengguna');
    }
}
