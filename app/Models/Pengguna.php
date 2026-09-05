<?php

namespace App\Models;

use App\Models\Tenant\KoperasiDesa;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Pengguna extends Authenticatable
{
    use Notifiable;

    protected $table = 'pengguna';

    protected $fillable = [
        'id_koperasi', 'nama', 'email', 'password', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /** NULL = pengguna tingkat pusat/pengawas, bukan milik satu desa. */
    public function koperasi()
    {
        return $this->belongsTo(KoperasiDesa::class, 'id_koperasi', 'id_koperasi');
    }

    public function peran()
    {
        return $this->belongsToMany(Peran::class, 'pengguna_peran', 'id_pengguna', 'id_peran');
    }

    public function hasPeran(string $kode): bool
    {
        return $this->peran->contains('kode', $kode);
    }
}
