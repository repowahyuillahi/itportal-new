<?php

/**
 * Phase 0 inspection script (pure PHP, no Composer required).
 *
 * Reads headers + a few sample rows from each Excel/CSV source file in the
 * project root so we can sketch import mapping. Does NOT write to MySQL.
 *
 * Run: php scripts/inspect_sources.php
 */

declare(strict_types=1);

const SAMPLE_ROWS = 3;
const MAX_COLS = 25;
const ROOT = __DIR__ . DIRECTORY_SEPARATOR . '..';

$csvFiles = [
    'data-lengkap-sertifikat.csv',
];

$xlsxFiles = [
    'Weekly Report.xlsx',
    '2026-IT-Monitoring.xlsx',
    '10-Fauzan-DailyReport.xlsx',
    '25 Maret 2026.xlsx',
    'CCTV Access Control Maret 2026.xlsx',
    'Improvement_Report_Monitoring_Radio_Server_CCTV.xlsx',
];

function trim_cell(?string $v, int $n = 80): string
{
    if ($v === null) {
        return '';
    }
    $s = trim(str_replace(["\r", "\n", "\t"], ' ', $v));
    return mb_strlen($s) <= $n ? $s : mb_substr($s, 0, $n - 1) . '...';
}

/** Convert Excel column letter (e.g. "AB") to zero-based index. */
function col_to_index(string $col): int
{
    $col = strtoupper($col);
    $n = 0;
    for ($i = 0, $len = strlen($col); $i < $len; $i++) {
        $n = $n * 26 + (ord($col[$i]) - 64);
    }
    return $n - 1;
}

/** Parse shared strings part of an .xlsx zip. */
function parse_shared_strings(ZipArchive $zip): array
{
    $xml = $zip->getFromName('xl/sharedStrings.xml');
    if ($xml === false) {
        return [];
    }
    $sst = @simplexml_load_string($xml);
    if ($sst === false) {
        return [];
    }
    $out = [];
    foreach ($sst->si as $si) {
        // Strip namespaces, get inner text
        $out[] = trim((string) strip_tags($si->asXML()));
    }
    return $out;
}

/** Read first N rows from one sheet XML using shared strings table. */
function read_sheet_rows(string $sheetXml, array $sharedStrings, int $maxRows, int $maxCols): array
{
    $doc = new XMLReader();
    $doc->XML($sheetXml);
    $rows = [];
    $currentRow = null;
    $rowCount = 0;

    while ($doc->read()) {
        if ($doc->nodeType === XMLReader::ELEMENT && $doc->name === 'row') {
            $currentRow = array_fill(0, $maxCols, null);
        } elseif ($doc->nodeType === XMLReader::ELEMENT && $doc->name === 'c') {
            $ref = $doc->getAttribute('r') ?? '';
            $type = $doc->getAttribute('t') ?? '';
            $colLetters = preg_replace('/\d+/', '', $ref);
            $colIdx = $colLetters !== '' ? col_to_index($colLetters) : 0;

            $value = null;
            $cellXml = $doc->readOuterXml();
            if (preg_match('#<v>(.*?)</v>#s', $cellXml, $m)) {
                $raw = $m[1];
                if ($type === 's') {
                    $idx = (int) $raw;
                    $value = $sharedStrings[$idx] ?? '';
                } else {
                    $value = $raw;
                }
            } elseif (preg_match('#<t[^>]*>(.*?)</t>#s', $cellXml, $m)) {
                $value = $m[1];
            }

            if ($colIdx >= 0 && $colIdx < $maxCols && $currentRow !== null) {
                $currentRow[$colIdx] = $value;
            }
        } elseif ($doc->nodeType === XMLReader::END_ELEMENT && $doc->name === 'row') {
            $rows[] = $currentRow;
            $rowCount++;
            $currentRow = null;
            if ($rowCount >= $maxRows) {
                break;
            }
        }
    }
    $doc->close();
    return $rows;
}

function inspect_xlsx(string $path): void
{
    echo "\n=== " . basename($path) . " ===\n";
    if (!is_file($path)) {
        echo "  (missing)\n";
        return;
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        echo "  ERROR open zip\n";
        return;
    }

    $shared = parse_shared_strings($zip);

    // Find sheet list via workbook.xml + workbook.xml.rels
    $wb = $zip->getFromName('xl/workbook.xml');
    $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($wb === false || $rels === false) {
        echo "  ERROR: missing workbook parts\n";
        $zip->close();
        return;
    }
    $wbXml = simplexml_load_string($wb);
    $relsXml = simplexml_load_string($rels);

    $relMap = [];
    foreach ($relsXml->Relationship as $r) {
        $relMap[(string) $r['Id']] = (string) $r['Target'];
    }

    $sheets = [];
    $ns = $wbXml->getDocNamespaces(true);
    foreach ($wbXml->sheets->sheet as $s) {
        $attrs = $s->attributes();
        $name = (string) $attrs['name'];
        $rid = '';
        foreach ($s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships') as $k => $v) {
            if ($k === 'id') {
                $rid = (string) $v;
            }
        }
        if ($rid === '') {
            // fallback
            foreach ($s->attributes() as $k => $v) {
                if (stripos($k, 'id') !== false) {
                    $rid = (string) $v;
                }
            }
        }
        $target = $relMap[$rid] ?? '';
        if ($target !== '') {
            $sheets[] = ['name' => $name, 'path' => 'xl/' . ltrim($target, '/')];
        }
    }

    foreach ($sheets as $sheet) {
        $sxml = $zip->getFromName($sheet['path']);
        if ($sxml === false) {
            echo "\n  Sheet: {$sheet['name']}  (not found at {$sheet['path']})\n";
            continue;
        }
        $rows = read_sheet_rows($sxml, $shared, SAMPLE_ROWS + 6, MAX_COLS);
        echo "\n  Sheet: {$sheet['name']}  (rows_read=" . count($rows) . ")\n";

        // pick first non-empty row as header
        $headerIdx = 0;
        foreach ($rows as $i => $r) {
            if ($r && array_filter($r, fn($c) => $c !== null && $c !== '')) {
                $headerIdx = $i;
                break;
            }
        }
        $header = $rows[$headerIdx] ?? [];
        echo "    Header row #" . ($headerIdx + 1) . ":\n";
        foreach ($header as $j => $h) {
            if ($h !== null && $h !== '') {
                echo "      col[$j] = " . trim_cell((string) $h, 60) . "\n";
            }
        }
        echo "    Sample rows:\n";
        $shown = 0;
        for ($i = $headerIdx + 1; $i < count($rows) && $shown < SAMPLE_ROWS; $i++) {
            $r = $rows[$i];
            if (!$r) {
                continue;
            }
            $cells = array_map(fn($c) => trim_cell((string) ($c ?? ''), 30), $r);
            // trim trailing empties for readability
            while (count($cells) > 0 && end($cells) === '') {
                array_pop($cells);
            }
            echo "      | " . implode(' | ', $cells) . "\n";
            $shown++;
        }
    }

    $zip->close();
}

function inspect_csv(string $path): void
{
    echo "\n=== " . basename($path) . " ===\n";
    if (!is_file($path)) {
        echo "  (missing)\n";
        return;
    }
    $fh = fopen($path, 'r');
    if (!$fh) {
        echo "  ERROR open\n";
        return;
    }
    $header = fgetcsv($fh);
    if (!$header) {
        echo "  (empty)\n";
        fclose($fh);
        return;
    }
    echo "  Columns (" . count($header) . "):\n";
    foreach ($header as $j => $h) {
        echo "    col[$j] = $h\n";
    }
    echo "  Sample rows:\n";
    $i = 0;
    while (($row = fgetcsv($fh)) !== false && $i < SAMPLE_ROWS) {
        $cells = array_map(fn($c) => trim_cell((string) $c, 30), $row);
        echo "    | " . implode(' | ', $cells) . "\n";
        $i++;
    }
    fclose($fh);
}

echo "Project root: " . realpath(ROOT) . "\n";
echo "\n--- Files in root ---\n";
foreach (scandir(ROOT) as $name) {
    $p = ROOT . DIRECTORY_SEPARATOR . $name;
    if (is_file($p)) {
        echo "  $name  (" . filesize($p) . " bytes)\n";
    }
}

echo "\n--- CSV ---\n";
foreach ($csvFiles as $name) {
    inspect_csv(ROOT . DIRECTORY_SEPARATOR . $name);
}

echo "\n--- Excel ---\n";
foreach ($xlsxFiles as $name) {
    inspect_xlsx(ROOT . DIRECTORY_SEPARATOR . $name);
}

echo "\nDone.\n";
