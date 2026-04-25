<?php

namespace App\Services\WhatsApp;

/**
 * Parses raw WhatsApp logistics messages into structured arrays.
 *
 * Expected message shape:
 *   Header lines → Date → Origin → Cargo lines → Safety note → Footer
 */
class WhatsAppMessageParser
{
    public function __construct(private readonly DateParser $dateParser) {}

    public function parse(string $rawMessage): array
    {
        $lines = explode("\n", $rawMessage);
        $lines = array_map(fn($l) => $this->stripFormatting($l), $lines);
        $lines = array_values(array_filter($lines, fn($l) => trim($l) !== ''));

        $date        = null;
        $origin      = null;
        $items       = [];
        $safetyNote  = null;
        $originFound = false;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($this->isHeaderLine($line)) {
                continue;
            }

            if (preg_match('/^Terima\s+[Kk]asih/i', $line)) {
                continue;
            }

            // Safety note (starts with "Pastikan")
            if (preg_match('/^Pastikan\s+/i', $line)) {
                $safetyNote = $line;
                continue;
            }

            // Origin line
            if (!$originFound && preg_match('/^Origin\s+(.+)$/i', $line, $m)) {
                $origin      = trim($m[1]);
                $originFound = true;
                continue;
            }

            // Date line (look for it before origin is established)
            if ($date === null && !$originFound) {
                $parsed = $this->dateParser->parse($line);
                if ($parsed !== null) {
                    $date = $parsed;
                    continue;
                }
            }

            // Cargo lines — only after origin is established
            if ($originFound) {
                $item = $this->parseCargoLine($line);
                if ($item !== null) {
                    $items[] = $item;
                }
            }
        }

        return [
            'date'       => $date,
            'origin'     => $origin,
            'items'      => $items,
            'safetyNote' => $safetyNote,
        ];
    }

    /**
     * Strip WhatsApp bold/italic markers (* and _) and collapse extra whitespace.
     */
    private function stripFormatting(string $line): string
    {
        $line = str_replace(['*', '_'], '', $line);

        return preg_replace('/\s+/', ' ', trim($line));
    }

    /**
     * Returns true for known fixed header lines.
     */
    private function isHeaderLine(string $line): bool
    {
        return (bool) preg_match(
            '/^(Dear\s+Team\s+Transporter|Remind\s+Order|Order\s+Baru|Planning\s+Loading)$/i',
            $line
        );
    }

    /**
     * Parse a single cargo line into a structured array.
     *
     * Expected (after formatting is stripped):
     *   <destinations> <volume> Cbm <units> Unit[s] [(notes)] [PO [Tgl] <date>]
     *
     * Returns null when the line does not look like a cargo entry.
     */
    private function parseCargoLine(string $line): ?array
    {
        // Remove trailing punctuation (e.g. a period)
        $line = rtrim($line, '. ');

        // --- 1. Extract PO date ---
        $poDate     = null;
        $poPattern  = '/\bPO\s+(?:Tgl\s+)?(\d{1,2}\s+[A-Za-z]+\s+\d{2,4}|\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}|\d{1,2}\s+\d{1,2}\s+\d{2,4})/i';
        if (preg_match($poPattern, $line, $poMatch)) {
            $poDate = $this->dateParser->parse($poMatch[1]);
            $line   = trim(preg_replace($poPattern, '', $line));
        }

        // --- 2. Extract parenthetical notes ---
        $notes = null;
        if (preg_match('/\(([^)]+)\)/', $line, $noteMatch)) {
            $notes = '(' . trim($noteMatch[1]) . ')';
            $line  = trim(str_replace($noteMatch[0], '', $line));
        }

        // --- 3. Match core cargo pattern: <destinations> <volume> Cbm <units> Unit[s] ---
        // Everything before the first "NN Cbm" sequence is treated as destinations.
        // Trailing text after unit count (e.g. "Urgent") is intentionally ignored.
        $corePattern = '/^(.+?)\s+(\d+(?:\.\d+)?)\s+[Cc]bm\s+(\d+)\s+[Uu]nits?(?:\s+.*)?$/i';
        if (!preg_match($corePattern, trim($line), $m)) {
            return null;
        }

        $volumeCbm = (float) $m[2];

        // Split destinations by '+', normalize whitespace, drop empties
        $destinations = array_values(array_filter(
            array_map(
                fn($d) => preg_replace('/\s+/', ' ', trim($d)),
                explode('+', $m[1])
            ),
            fn($d) => $d !== ''
        ));

        if (empty($destinations)) {
            return null;
        }

        return [
            'destinations' => $destinations,
            'volumeCbm'    => $volumeCbm == (int) $volumeCbm ? (int) $volumeCbm : $volumeCbm,
            'unitCount'    => (int) $m[3],
            'poDate'       => $poDate,
            'notes'        => $notes,
        ];
    }
}
