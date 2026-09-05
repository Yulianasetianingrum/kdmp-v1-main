<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * TenantOwnershipRule — Memastikan ID yang dikirim via form adalah milik koperasi aktif.
 *
 * MASALAH YANG DIPECAHKAN:
 *   Jika user Desa A mengganti id_kas_bank di form dengan ID milik Desa B,
 *   validasi `exists:master_kas_bank` akan lolos karena ID itu memang ada di database.
 *   Rule ini menambahkan filter `id_koperasi = ?` ke query `exists`.
 *
 * PENGGUNAAN di Form Request:
 *   use App\Rules\TenantOwnershipRule;
 *
 *   'id_kas_bank' => ['required', new TenantOwnershipRule('master_kas_bank', 'id_kas_bank')],
 *   'id_pihak'    => ['required', new TenantOwnershipRule('master_pihak', 'id_pihak')],
 *   'id_gudang'   => ['required', new TenantOwnershipRule('gudang', 'id_gudang')],
 *
 * KOLOM TENANT:
 *   Default: `id_koperasi`. Tabel yang menggunakan nama kolom berbeda bisa
 *   di-override via parameter $kolom_tenant.
 *
 * EXCEPTION AMAN:
 *   Jika 'koperasi_aktif' tidak terikat di container (mode konsolidasi pusat),
 *   rule ini selalu lolos agar tidak memblokir operasi level pusat.
 */
class TenantOwnershipRule implements ValidationRule
{
    /**
     * @param string $tabel        Nama tabel yang dicek (contoh: 'master_kas_bank')
     * @param string $kolom_id     Nama kolom primary key (contoh: 'id_kas_bank')
     * @param string $kolom_tenant Nama kolom tenant di tabel tersebut (default: 'id_koperasi')
     */
    public function __construct(
        private readonly string $tabel,
        private readonly string $kolom_id,
        private readonly string $kolom_tenant = 'id_koperasi',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Mode konsolidasi pusat: tidak ada koperasi aktif → lewati validasi
        if (!app()->bound('koperasi_aktif')) {
            return;
        }

        $idKoperasi = app('koperasi_aktif');

        $ada = DB::table($this->tabel)
            ->where($this->kolom_id, $value)
            ->where($this->kolom_tenant, $idKoperasi)
            ->exists();

        if (!$ada) {
            $fail("Data :attribute tidak ditemukan atau bukan milik koperasi Anda.");
        }
    }
}
