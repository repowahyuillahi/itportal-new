<?php

declare(strict_types=1);

namespace App\Importers;

/**
 * CertificateCsvSource - reads and normalizes rows from
 * `data-lengkap-sertifikat.csv` (see docs/import-mapping.md).
 *
 * Read-only. Never writes to the source file or to any database.
 *
 * Each returned row is an associative array with keys:
 *   area, folder_area, branch, file_p12, file_pin, cert_code,
 *   dealer_code, pin, subject, issuer, valid_from, valid_to,
 *   thumbprint_sha1, serial_number,
 *   _row_no   (1-indexed, header=1)
 *   _parsed   (true if dealer_code + branch_name resolvable, else false)
 *   _dealer_name (branch with trailing " (CODE)" stripped, trimmed)
 *   _valid_from_ts (?int unix seconds)
 *   _valid_to_ts   (?int unix seconds)
 */
final class CertificateCsvSource
{
    private const REQUIRED_HEADERS = [
        'area', 'folder_area', 'branch', 'file_p12', 'file_pin',
        'cert_code', 'dealer_code', 'pin', 'subject', 'issuer',
        'valid_from', 'valid_to', 'thumbprint_sha1', 'serial_number',
    ];

    /**
     * @return array{rows: array<int, array<string, mixed>>, header_issues: array<int, string>}
     */
    public function load(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException("Source CSV not found or unreadable: $path");
        }
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            throw new \RuntimeException("Cannot open source CSV: $path");
        }

        $headerRaw = fgetcsv($fh);
        if ($headerRaw === false || $headerRaw === null) {
            fclose($fh);
            throw new \RuntimeException("Source CSV is empty: $path");
        }

        // Strip BOM from first header.
        if (isset($headerRaw[0])) {
            $headerRaw[0] = preg_replace('/^\xEF\xBB\xBF/u', '', $headerRaw[0]) ?? $headerRaw[0];
        }
        $header = array_map(static fn($h) => strtolower(trim((string) $h)), $headerRaw);

        $issues = [];
        foreach (self::REQUIRED_HEADERS as $i => $expected) {
            if (!isset($header[$i]) || $header[$i] !== $expected) {
                $issues[] = sprintf(
                    'header[%d] expected "%s", got "%s"',
                    $i, $expected, $header[$i] ?? '(missing)'
                );
            }
        }

        $rows = [];
        $rowNo = 1;
        while (($row = fgetcsv($fh)) !== false) {
            $rowNo++;
            // Skip fully blank rows.
            if (count(array_filter($row, static fn($c) => $c !== null && trim((string) $c) !== '')) === 0) {
                continue;
            }
            $assoc = [];
            foreach (self::REQUIRED_HEADERS as $i => $key) {
                $assoc[$key] = isset($row[$i]) ? trim((string) $row[$i]) : '';
            }
            $assoc['_row_no']      = $rowNo;
            $assoc['_dealer_name'] = $this->stripCodeSuffix($assoc['branch']);
            $assoc['_parsed']      = $assoc['dealer_code'] !== '' && $assoc['_dealer_name'] !== '';
            $assoc['_valid_from_ts'] = $this->parseUsDateTime($assoc['valid_from']);
            $assoc['_valid_to_ts']   = $this->parseUsDateTime($assoc['valid_to']);
            $rows[] = $assoc;
        }
        fclose($fh);

        return ['rows' => $rows, 'header_issues' => $issues];
    }

    /**
     * "SENTRAL YAMAHA (9FP002)" -> "SENTRAL YAMAHA"
     * "0. MAIN DEALER (GA0701)" -> "0. MAIN DEALER"
     * "Name with no code"       -> "Name with no code"
     */
    private function stripCodeSuffix(string $branch): string
    {
        $b = trim($branch);
        if (preg_match('/^(.*)\s*\(([^()]+)\)\s*$/u', $b, $m)) {
            return trim($m[1]);
        }
        return $b;
    }

    private function parseUsDateTime(string $s): ?int
    {
        if ($s === '') { return null; }
        // Accept "M/D/YYYY h:mm AM/PM" (US locale).
        $dt = \DateTimeImmutable::createFromFormat('!n/j/Y g:i A', $s, new \DateTimeZone('Asia/Jakarta'));
        if ($dt === false) {
            // Fallback: try generic strtotime as last resort.
            $ts = strtotime($s);
            return $ts === false ? null : $ts;
        }
        return $dt->getTimestamp();
    }
}
