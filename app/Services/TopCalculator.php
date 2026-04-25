<?php

namespace App\Services;

/**
 * Calculates the Term of Payment (TOP) result for transporter invoices.
 *
 * Formula:
 *   podDelay  = min(max(podLateDays, 0), MAX_DELAY_POD)
 *   epodDelay = min(max(epodLateDays, 0), MAX_DELAY_EPOD)
 *   penalty   = podDelay + epodDelay
 *   total     = baselineTop + penalty
 *   result    = min(total, MAX_TOP)
 */
class TopCalculator
{
    // Maximum penalty days for physical POD (per document type)
    public const MAX_DELAY_POD  = 30;

    // Maximum penalty days for electronic POD (per document type)
    public const MAX_DELAY_EPOD = 30;

    // Absolute ceiling on the final TOP result
    public const MAX_TOP        = 45;

    /**
     * @param int $baselineTop   Baseline TOP in days (from transporter contract)
     * @param int $podLateDays   Physical POD late days (negative treated as 0)
     * @param int $epodLateDays  Electronic POD late days (negative treated as 0)
     * @return int               Final TOP result in days
     */
    public function calculate(int $baselineTop, int $podLateDays, int $epodLateDays): int
    {
        // Cap each delay individually — ensures neither alone can exceed its own limit,
        // even if the other is zero.
        $podDelay  = min(max($podLateDays, 0), self::MAX_DELAY_POD);
        $epodDelay = min(max($epodLateDays, 0), self::MAX_DELAY_EPOD);

        $penalty = $podDelay + $epodDelay;
        $total   = $baselineTop + $penalty;

        return min($total, self::MAX_TOP);
    }
}
