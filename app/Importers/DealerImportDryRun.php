<?php

declare(strict_types=1);

namespace App\Importers;

use App\Repositories\DealerRepository;

/**
 * DealerImportDryRun - compares CSV source rows against the live
 * `dealers` table and produces a structured diff.
 *
 * Read-only. Never writes to the database.
 *
 * Verdicts per source row:
 *   would_create  - dealer_code not present in DB
 *   would_update  - present, but name and/or area differ
 *   match         - present, name+area already canonical
 *   ambiguous     - duplicate dealer_code in CSV with conflicting fields
 *   unparseable   - missing dealer_code or branch name
 *
 * @phpstan-type Verdict array{
 *   verdict: string, code: string, name: string, area: string,
 *   row_no: int, current?: array<string, mixed>, diff?: array<string, array{from: ?string, to: ?string}>,
 *   reason?: string,
 * }
 */
final class DealerImportDryRun
{
    private DealerRepository $dealers;

    public function __construct(?DealerRepository $dealers = null)
    {
        $this->dealers = $dealers ?? new DealerRepository();
    }

    /**
     * @param array<int, array<string, mixed>> $sourceRows  output of CertificateCsvSource::load()['rows']
     * @return array{
     *   summary: array<string, int>,
     *   verdicts: array<int, array<string, mixed>>,
     *   ambiguous_groups: array<string, array<int, int>>,
     * }
     */
    public function plan(array $sourceRows): array
    {
        // Step 1: dedupe and detect intra-CSV ambiguity by dealer_code.
        $byCode = [];
        foreach ($sourceRows as $idx => $r) {
            $code = strtoupper(trim((string) $r['dealer_code']));
            if ($code === '' || $r['_dealer_name'] === '') {
                continue;
            }
            $byCode[$code][] = $idx;
        }

        $ambiguousGroups = [];
        foreach ($byCode as $code => $indices) {
            if (count($indices) < 2) { continue; }
            $signatures = [];
            foreach ($indices as $i) {
                $signatures[] = strtolower(trim((string) $sourceRows[$i]['_dealer_name']))
                              . '||'
                              . strtolower(trim((string) $sourceRows[$i]['area']));
            }
            if (count(array_unique($signatures)) > 1) {
                $ambiguousGroups[$code] = $indices;
            }
        }

        // Step 2: snapshot live dealers indexed by uppercase code.
        $live = [];
        foreach ($this->dealers->listAll() as $d) {
            $code = strtoupper(trim((string) ($d['code'] ?? '')));
            if ($code !== '') {
                $live[$code] = $d;
            }
        }

        // Step 3: classify each source row.
        $verdicts = [];
        $summary = [
            'total_rows'    => count($sourceRows),
            'would_create'  => 0,
            'would_update'  => 0,
            'match'         => 0,
            'ambiguous'     => 0,
            'unparseable'   => 0,
        ];
        $seenCreate = [];  // dedupe would_create within the same run
        $seenUpdate = [];

        foreach ($sourceRows as $idx => $r) {
            $rowNo = (int) $r['_row_no'];
            $code  = strtoupper(trim((string) $r['dealer_code']));
            $name  = trim((string) $r['_dealer_name']);
            $area  = trim((string) $r['area']);

            if ($code === '' || $name === '') {
                $verdicts[] = [
                    'verdict' => 'unparseable',
                    'row_no'  => $rowNo,
                    'code'    => (string) $r['dealer_code'],
                    'name'    => $name,
                    'area'    => $area,
                    'reason'  => $code === '' ? 'missing dealer_code' : 'missing branch name',
                ];
                $summary['unparseable']++;
                continue;
            }

            if (isset($ambiguousGroups[$code])) {
                $verdicts[] = [
                    'verdict' => 'ambiguous',
                    'row_no'  => $rowNo,
                    'code'    => $code,
                    'name'    => $name,
                    'area'    => $area,
                    'reason'  => 'CSV has ' . count($ambiguousGroups[$code]) . ' rows for this code with conflicting name/area',
                ];
                $summary['ambiguous']++;
                continue;
            }

            if (!isset($live[$code])) {
                if (isset($seenCreate[$code])) {
                    // Same code already covered by an earlier would_create.
                    continue;
                }
                $seenCreate[$code] = true;
                $verdicts[] = [
                    'verdict' => 'would_create',
                    'row_no'  => $rowNo,
                    'code'    => $code,
                    'name'    => $name,
                    'area'    => $area,
                ];
                $summary['would_create']++;
                continue;
            }

            $current = $live[$code];
            $currentName = trim((string) ($current['name'] ?? ''));
            $currentArea = trim((string) ($current['area'] ?? ''));

            $diff = [];
            if (strcasecmp($currentName, $name) !== 0) {
                $diff['name'] = ['from' => $currentName, 'to' => $name];
            }
            if (strcasecmp($currentArea, $area) !== 0) {
                $diff['area'] = ['from' => $currentArea, 'to' => $area];
            }

            if ($diff === []) {
                $verdicts[] = [
                    'verdict' => 'match',
                    'row_no'  => $rowNo,
                    'code'    => $code,
                    'name'    => $name,
                    'area'    => $area,
                ];
                $summary['match']++;
                continue;
            }

            if (isset($seenUpdate[$code])) {
                continue;
            }
            $seenUpdate[$code] = true;
            $verdicts[] = [
                'verdict' => 'would_update',
                'row_no'  => $rowNo,
                'code'    => $code,
                'name'    => $name,
                'area'    => $area,
                'current' => [
                    'id'     => (int) ($current['id'] ?? 0),
                    'name'   => $currentName,
                    'area'   => $currentArea,
                    'status' => (string) ($current['status'] ?? ''),
                ],
                'diff'    => $diff,
            ];
            $summary['would_update']++;
        }

        return [
            'summary'          => $summary,
            'verdicts'         => $verdicts,
            'ambiguous_groups' => $ambiguousGroups,
        ];
    }
}
