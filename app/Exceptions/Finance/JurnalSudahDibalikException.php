<?php

namespace App\Exceptions\Finance;

use RuntimeException;

/**
 * Dilempar ketika JurnalService::balik() dipanggil pada jurnal yang
 * sudah berstatus REVERSED (sudah pernah dibalik sebelumnya).
 *
 * Cara penggunaan:
 *   throw new JurnalSudahDibalikException(101, 202);
 *   // → "Jurnal #101 sudah pernah dibalik oleh jurnal pembalik #202."
 */
class JurnalSudahDibalikException extends RuntimeException
{
    public function __construct(int $idJurnal, int $idJurnalPembalik)
    {
        parent::__construct(
            "Jurnal #{$idJurnal} sudah pernah dibalik oleh jurnal pembalik #{$idJurnalPembalik}."
        );
    }
}
