<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_log';
    protected $primaryKey = 'id_log';
    const UPDATED_AT = null;

    protected $fillable = [
        'id_koperasi', 'id_pengguna', 'tabel', 'record_id', 'aksi',
        'data_lama', 'data_baru', 'ip_address',
    ];

    protected function casts(): array
    {
        return ['data_lama' => 'array', 'data_baru' => 'array'];
    }
}
