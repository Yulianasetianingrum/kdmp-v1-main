<?php

namespace App\Exceptions\Finance;

use RuntimeException;

/**
 * Dilempar ketika JurnalService mencoba posting ke periode akuntansi
 * yang sudah ditutup (status = 'CLOSE').
 *
 * Cara penggunaan:
 *   throw new PeriodeTutupException(2026, 8);
 *   // → "Periode Agustus 2026 sudah ditutup. Posting tidak diizinkan."
 */
class PeriodeTutupException extends RuntimeException
{
    public function __construct(int $tahun, int $bulan)
    {
        $namaBulan = \DateTime::createFromFormat('!m', $bulan)->format('F');

        parent::__construct(
            "Periode {$namaBulan} {$tahun} sudah ditutup. Posting tidak diizinkan."
        );
    }
}
