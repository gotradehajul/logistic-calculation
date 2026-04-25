<?php

namespace App\Services\WhatsApp;

/**
 * Parses Indonesian date strings into ISO 8601 format (YYYY-MM-DD).
 *
 * Handles full/abbreviated/numeric months, optional day-of-week prefix,
 * and 2- or 4-digit years.
 */
class DateParser
{
    private const MONTH_MAP = [
        // Full names
        'januari'   => 1,  'februari'  => 2,  'maret'     => 3,
        'april'     => 4,  'mei'       => 5,  'juni'      => 6,
        'juli'      => 7,  'agustus'   => 8,  'september' => 9,
        'oktober'   => 10, 'november'  => 11, 'desember'  => 12,
        // Abbreviations
        'jan' => 1,  'feb' => 2,  'mar' => 3,  'apr' => 4,
        'jun' => 6,  'jul' => 7,
        'agu' => 8,  'agt' => 8,  'agust' => 8,
        'sep' => 9,  'sept' => 9,
        'okt' => 10, 'nov' => 11, 'des' => 12,
    ];

    private const DAY_NAMES = [
        'senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu',
    ];

    /**
     * Parse a date string and return YYYY-MM-DD, or null if unparseable.
     */
    public function parse(string $text): ?string
    {
        $normalized = trim(strtolower($text));

        // Strip optional leading day-of-week (e.g. "Rabu," or "Rabu ")
        foreach (self::DAY_NAMES as $day) {
            if (str_starts_with($normalized, $day)) {
                $normalized = ltrim(substr($normalized, strlen($day)), " \t,");
                break;
            }
        }

        $normalized = preg_replace('/\s+/', ' ', trim($normalized));

        // Format: DD MonthName YY[YY]  — e.g. "23 Oktober 2024" or "20 Feb 25"
        if (preg_match('/^(\d{1,2})\s+([a-z]+)\s+(\d{2,4})$/', $normalized, $m)) {
            $month = self::MONTH_MAP[$m[2]] ?? null;
            if ($month !== null) {
                return $this->format((int) $m[1], $month, $this->expandYear((int) $m[3]));
            }
        }

        // Format: DD/MM/YY[YY] or DD-MM-YY[YY]
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/', $normalized, $m)) {
            return $this->format((int) $m[1], (int) $m[2], $this->expandYear((int) $m[3]));
        }

        // Format: DD MM YY[YY] (space-separated numeric month)
        if (preg_match('/^(\d{1,2})\s+(\d{1,2})\s+(\d{2,4})$/', $normalized, $m)) {
            $month = (int) $m[2];
            if ($month >= 1 && $month <= 12) {
                return $this->format((int) $m[1], $month, $this->expandYear((int) $m[3]));
            }
        }

        return null;
    }

    private function expandYear(int $year): int
    {
        return $year < 100 ? 2000 + $year : $year;
    }

    private function format(int $day, int $month, int $year): ?string
    {
        if ($day < 1 || $day > 31 || $month < 1 || $month > 12) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
}
