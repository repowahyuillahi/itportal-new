<?php

declare(strict_types=1);

namespace App\Importers;

use App\Repositories\DealerRepository;

/**
 * CertificateImportDryRun - reports the canonical certificate set from
 * the CSV and flags structural gaps, without writing to any database.
 *
 * There is no live `dealer_certificates` table yet; the dry-run output
 * also includes a *proposed* schema so the owner can review the column
 * list before any migration is committed.
 */
final class CertificateImportDryRun
{
    private DealerRepository $dealers;

    public function __construct(?DealerRepository $dealers = null)
    {
        $this->dealers = $dealers ?? new DealerRepository();
    }

    /** @return array<int, array{name: string, type: string, note: string}> */
    public function proposedSchema(): array
    {
        return [
            ['name' => 'id',               'type' => 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'note' => ''],
            ['name' => 'dealer_id',        'type' => 'BIGINT UNSIGNED NOT NULL',                   'note' => 'FK -> dealers.id, ON DELETE RESTRICT'],
            ['name' => 'cert_code',        'type' => 'VARCHAR(50) NOT NULL',                       'note' => 'UNIQUE; e.g. "AP9FP002"'],
            ['name' => 'subject',          'type' => 'VARCHAR(500) NULL',                          'note' => 'X.509 subject DN'],
            ['name' => 'issuer',           'type' => 'VARCHAR(500) NULL',                          'note' => 'X.509 issuer DN'],
            ['name' => 'valid_from',       'type' => 'DATETIME NULL',                              'note' => 'Asia/Jakarta'],
            ['name' => 'valid_to',         'type' => 'DATETIME NULL',                              'note' => 'Used to compute expiring_soon'],
            ['name' => 'thumbprint_sha1',  'type' => 'CHAR(40) NULL',                              'note' => 'Hex, INDEX'],
            ['name' => 'serial_number',    'type' => 'VARCHAR(40) NULL',                           'note' => 'Hex'],
            ['name' => 'pin_encrypted',    'type' => 'VARBINARY(255) NULL',                        'note' => 'Sensitive; encrypted at rest (key managed outside this app)'],
            ['name' => 'p12_filename',     'type' => 'VARCHAR(190) NULL',                          'note' => 'Filename only; operator local paths are not stored'],
            ['name' => 'notes',            'type' => 'TEXT NULL',                                  'note' => ''],
            ['name' => 'status',           'type' => 'ENUM("active","inactive","revoked") NOT NULL DEFAULT "active"', 'note' => ''],
            ['name' => 'created_at',       'type' => 'DATETIME NOT NULL',                          'note' => ''],
            ['name' => 'updated_at',       'type' => 'DATETIME NOT NULL',                          'note' => ''],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $sourceRows
     * @return array{
     *   summary: array<string, int>,
     *   certificates: array<int, array<string, mixed>>,
     *   proposed_schema: array<int, array<string, string>>,
     * }
     */
    public function plan(array $sourceRows, ?int $nowTs = null): array
    {
        $now = $nowTs ?? time();
        $soonCutoff = $now + (60 * 86400);

        // Index live dealers for orphan detection.
        $liveByCode = [];
        foreach ($this->dealers->listAll() as $d) {
            $code = strtoupper(trim((string) ($d['code'] ?? '')));
            if ($code !== '') {
                $liveByCode[$code] = $d;
            }
        }

        // Count certificates per dealer (after dedupe by cert_code+dealer).
        $perDealer = [];
        $byCertCode = [];

        $certs = [];
        $summary = [
            'total_rows'             => count($sourceRows),
            'distinct_certificates'  => 0,
            'orphan_certificate'     => 0,
            'duplicate_cert_code'    => 0,
            'multiple_certs_per_dealer' => 0,
            'expired'                => 0,
            'expiring_soon'          => 0,
            'pin_present'            => 0,
            'pin_blank'              => 0,
        ];

        foreach ($sourceRows as $r) {
            $certCode   = strtoupper(trim((string) $r['cert_code']));
            $dealerCode = strtoupper(trim((string) $r['dealer_code']));
            if ($certCode === '' || $dealerCode === '') {
                continue;
            }

            $flags = [];
            if (!isset($liveByCode[$dealerCode])) {
                $flags[] = 'orphan_certificate';
                $summary['orphan_certificate']++;
            }

            if (isset($byCertCode[$certCode])) {
                $flags[] = 'duplicate_cert_code';
                $summary['duplicate_cert_code']++;
            }
            $byCertCode[$certCode] = true;

            $expired = false;
            $expSoon = false;
            $vto = $r['_valid_to_ts'];
            if (is_int($vto)) {
                if ($vto < $now) {
                    $expired = true;
                    $flags[] = 'expired';
                    $summary['expired']++;
                } elseif ($vto < $soonCutoff) {
                    $expSoon = true;
                    $flags[] = 'expiring_soon';
                    $summary['expiring_soon']++;
                }
            }

            $pin = (string) $r['pin'];
            if ($pin === '') {
                $summary['pin_blank']++;
            } else {
                $summary['pin_present']++;
            }

            $perDealer[$dealerCode] = ($perDealer[$dealerCode] ?? 0) + 1;

            $certs[] = [
                'row_no'         => (int) $r['_row_no'],
                'cert_code'      => $certCode,
                'dealer_code'    => $dealerCode,
                'dealer_name'    => (string) $r['_dealer_name'],
                'area'           => (string) $r['area'],
                'subject'        => (string) $r['subject'],
                'issuer'         => (string) $r['issuer'],
                'valid_from'     => $this->fmt($r['_valid_from_ts']),
                'valid_to'       => $this->fmt($r['_valid_to_ts']),
                'thumbprint_sha1' => strtoupper((string) $r['thumbprint_sha1']),
                'serial_number'  => strtoupper((string) $r['serial_number']),
                'pin_masked'     => $this->maskPin($pin),
                'p12_filename'   => $this->basenameOnly((string) $r['file_p12']),
                'flags'          => $flags,
                'expired'        => $expired,
                'expiring_soon'  => $expSoon,
            ];
        }

        foreach ($perDealer as $dealerCode => $count) {
            if ($count > 1) {
                $summary['multiple_certs_per_dealer']++;
            }
        }

        $summary['distinct_certificates'] = count($byCertCode);

        return [
            'summary'         => $summary,
            'certificates'    => $certs,
            'proposed_schema' => $this->proposedSchema(),
        ];
    }

    private function fmt(?int $ts): string
    {
        if ($ts === null) { return ''; }
        return (new \DateTimeImmutable('@' . $ts))
            ->setTimezone(new \DateTimeZone('Asia/Jakarta'))
            ->format('d/m/Y H:i');
    }

    private function maskPin(string $pin): string
    {
        $len = strlen($pin);
        if ($len === 0) { return ''; }
        if ($len <= 4)  { return str_repeat('*', $len); }
        return substr($pin, 0, 2) . str_repeat('*', max(3, $len - 4)) . substr($pin, -2);
    }

    private function basenameOnly(string $path): string
    {
        if ($path === '') { return ''; }
        $parts = preg_split('#[\\\\/]+#', $path) ?: [];
        return (string) end($parts);
    }
}
