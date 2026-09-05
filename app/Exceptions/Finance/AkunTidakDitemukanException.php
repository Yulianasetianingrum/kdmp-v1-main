<?php

namespace App\Exceptions\Finance;

use RuntimeException;

/**
 * Dilempar ketika JurnalService gagal meresolve akun dinamis karena
 * data master yang dibutuhkan tidak ada atau tidak lengkap.
 *
 * Cara penggunaan:
 *   throw new AkunTidakDitemukanException('KAS_BANK', 'id_kas_bank = 999 tidak ditemukan');
 *   // → "Akun dinamis [KAS_BANK] tidak bisa diresolved: id_kas_bank = 999 tidak ditemukan."
 *
 *   throw new AkunTidakDitemukanException('PERSEDIAAN_UNIT', 'Unit usaha APOTEK tidak aktif');
 *   // → "Akun dinamis [PERSEDIAAN_UNIT] tidak bisa diresolved: Unit usaha APOTEK tidak aktif."
 */
class AkunTidakDitemukanException extends RuntimeException
{
    public function __construct(string $jenisAkunDinamis, string $alasan)
    {
        parent::__construct(
            "Akun dinamis [{$jenisAkunDinamis}] tidak bisa diresolved: {$alasan}."
        );
    }
}
