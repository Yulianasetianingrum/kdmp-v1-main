<?php

namespace App\Exceptions\Finance;

use RuntimeException;

/**
 * Dilempar ketika total Debit ≠ total Kredit setelah semua akun dinamis
 * berhasil diresolved oleh JurnalService.
 *
 * Cara penggunaan:
 *   throw new JurnalTidakBalanceException(170000, 100000);
 *   // → "Jurnal tidak balance: Debit Rp 170.000 ≠ Kredit Rp 100.000 (selisih Rp 70.000)."
 */
class JurnalTidakBalanceException extends RuntimeException
{
    public function __construct(
        private readonly float $totalDebit,
        private readonly float $totalKredit,
    ) {
        $selisih = abs($totalDebit - $totalKredit);

        parent::__construct(sprintf(
            'Jurnal tidak balance: Debit Rp %s ≠ Kredit Rp %s (selisih Rp %s).',
            number_format($totalDebit, 0, ',', '.'),
            number_format($totalKredit, 0, ',', '.'),
            number_format($selisih, 0, ',', '.'),
        ));
    }

    /** Nilai Debit total yang dihitung Service — berguna untuk logging. */
    public function getDebit(): float
    {
        return $this->totalDebit;
    }

    /** Nilai Kredit total yang dihitung Service — berguna untuk logging. */
    public function getKredit(): float
    {
        return $this->totalKredit;
    }
}
