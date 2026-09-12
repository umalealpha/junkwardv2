<?php

namespace AlphaDirect\Services\Claims;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * MotoLink (motolink.app) INBOUND assessment bridge.
 * ---------------------------------------------------------------------------
 * Port of the Claims Tracker's js/motolink.js. Pulls vehicle-damage assessments
 * from motolink.app and mirrors them onto the matching Graphite claim.
 *
 * MATCH KEY:  motolink "Claim Number" (G2026…) -> claims.claim_number.
 *             (In the standalone tracker this was claim_number OR
 *              graphite_claim_number; in Graphite the claim's own claim_number
 *              IS the Graphite number, so a single column match is exact.)
 *
 * WRITE RULE: the assessment is ALWAYS mirrored into the dedicated motolink_*
 *             columns (a machine mirror — never human-typed data). It only
 *             fills a MANUAL stage field (claim_tracker_workflow.assessment_
 *             report_date) when that field is still empty — the sync never
 *             overwrites a value the claims team entered by hand.
 *
 * SAFE / DARK: isEnabled() is false unless a key is configured (or mock is on),
 *              so with no MOTOLINK_API_KEY the whole path is a complete no-op —
 *              no outbound call, no DB write, no error.
 *
 * To adapt to the real motolink response shape, edit normalizeAssessment()
 * ONLY — nothing else. (Same contract as the tracker.)
 */
class MotolinkAssessmentBridge
{
    /** True only when the bridge is configured (key set) or explicitly mocked. */
    public function isEnabled(): bool
    {
        if ((bool) config('motolink.mock', false)) {
            return true;
        }
        return $this->base() !== '' && (string) config('motolink.key', '') !== '';
    }

    private function base(): string
    {
        return rtrim((string) config('motolink.base', ''), '/');
    }

    /**
     * Run one sync pass. Returns a stats summary. A complete no-op (and never
     * throws) while the bridge is unconfigured.
     *
     * @param bool $dryRun assemble + match only; write nothing to the DB.
     */
    public function syncOnce(bool $dryRun = false): array
    {
        if (!$this->isEnabled()) {
            return ['skipped' => true, 'reason' => 'motolink bridge not configured'];
        }

        $t0  = microtime(true);
        $raw = $this->fetchAssessments();

        $result = [
            'skipped'   => false,
            'scanned'   => 0,
            'matched'   => 0,
            'updated'   => 0,
            'writeOffs' => 0,
            'unmatched' => [],
            'errors'    => 0,
        ];

        $hasWorkflow = Schema::hasTable('claim_tracker_workflow');

        foreach ($raw as $item) {
            $result['scanned']++;

            try {
                $n = $this->normalizeAssessment((array) $item);
            } catch (\Throwable $e) {
                $result['errors']++;
                continue;
            }

            if ($n['claimNumber'] === '') {
                $result['unmatched'][] = $n['assessmentId'] !== '' ? $n['assessmentId'] : '(no claim no.)';
                continue;
            }

            $claim = DB::table('claims')->where('claim_number', $n['claimNumber'])->first();
            if (!$claim) {
                $result['unmatched'][] = $n['claimNumber'];
                continue;
            }
            $result['matched']++;

            if ($dryRun) {
                if ($n['totalLoss'] === 1) {
                    $result['writeOffs']++;
                }
                continue;
            }

            try {
                DB::table('claims')->where('id', $claim->id)->update([
                    'motolink_assessment_id'   => $n['assessmentId'],
                    'motolink_status'          => $n['status'],
                    'motolink_final_cost'      => $n['finalCost'],
                    'motolink_total_loss'      => $n['totalLoss'],
                    'motolink_write_off_alert' => $n['writeOffAlert'],
                    'motolink_vin'             => $n['vin'],
                    'motolink_registration'    => $n['registration'],
                    'motolink_make'            => $n['make'],
                    'motolink_model'           => $n['model'],
                    'motolink_updated_at'      => $n['updatedAt'],
                    'motolink_synced_at'       => now(),
                    'motolink_sync_error'      => '',
                    'updated_at'               => now(),
                ]);

                // fill-if-empty for a MANUAL stage field — never overwrite human
                // entry. assessment_report_date lives on claim_tracker_workflow
                // in Graphite (1:1 with claims). Only fill an EXISTING empty row.
                if ($hasWorkflow && $n['reportDate'] !== '') {
                    $wf = DB::table('claim_tracker_workflow')->where('claim_id', $claim->id)->first();
                    if ($wf && empty($wf->assessment_report_date)) {
                        DB::table('claim_tracker_workflow')
                            ->where('id', $wf->id)
                            ->update([
                                'assessment_report_date' => $n['reportDate'],
                                'updated_at'             => now(),
                            ]);
                    }
                }

                $result['updated']++;
                if ($n['totalLoss'] === 1) {
                    $result['writeOffs']++;
                }
            } catch (\Throwable $e) {
                $result['errors']++;
                Log::warning('[motolink] row update failed', [
                    'claim_number' => $n['claimNumber'],
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        $result['ms'] = (int) round((microtime(true) - $t0) * 1000);
        Log::info('[motolink] sync', [
            'scanned'   => $result['scanned'],
            'matched'   => $result['matched'],
            'updated'   => $result['updated'],
            'writeOffs' => $result['writeOffs'],
            'unmatched' => count($result['unmatched']),
            'errors'    => $result['errors'],
            'ms'        => $result['ms'],
            'dryRun'    => $dryRun,
        ]);

        return $result;
    }

    /**
     * Apply ONE already-normalized assessment onto its matching claim.
     *
     * The single-record counterpart to syncOnce()'s loop body, so the inbound
     * PUSH endpoint (ClaimsTrackerController::assessment) and the scheduled PULL
     * share identical write behaviour: mirror into the dedicated motolink_*
     * machine columns, and fill claim_tracker_workflow.assessment_report_date
     * ONLY when it is still empty — never overwrite a value the claims team
     * typed by hand.
     *
     * Idempotent by construction: a re-send writes the same machine columns to
     * the same claim (matched on claim_number) and never creates a row, so a
     * retry of the same event is safe.
     *
     * Returns a status the caller maps to its own outcome:
     *   ['status' => 'no_claim_number']                       — nothing to match on
     *   ['status' => 'unmatched']                             — no claim for that number
     *   ['status' => 'updated', 'claim_id' => int, 'writeOff' => bool]
     *   ['status' => 'error',   'error' => string]
     */
    public function applyNormalized(array $n): array
    {
        $claimNumber = trim((string) ($n['claimNumber'] ?? ''));
        if ($claimNumber === '') {
            return ['status' => 'no_claim_number'];
        }

        $claim = DB::table('claims')->where('claim_number', $claimNumber)->first();
        if (! $claim) {
            return ['status' => 'unmatched'];
        }

        try {
            DB::table('claims')->where('id', $claim->id)->update([
                'motolink_assessment_id'   => $n['assessmentId'],
                'motolink_status'          => $n['status'],
                'motolink_final_cost'      => $n['finalCost'],
                'motolink_total_loss'      => $n['totalLoss'],
                'motolink_write_off_alert' => $n['writeOffAlert'],
                'motolink_vin'             => $n['vin'],
                'motolink_registration'    => $n['registration'],
                'motolink_make'            => $n['make'],
                'motolink_model'           => $n['model'],
                'motolink_updated_at'      => $n['updatedAt'],
                'motolink_synced_at'       => now(),
                'motolink_sync_error'      => '',
                'updated_at'               => now(),
            ]);

            if (Schema::hasTable('claim_tracker_workflow') && ($n['reportDate'] ?? '') !== '') {
                // Fill-if-empty in ONE atomic UPDATE: the emptiness guard lives in
                // the WHERE clause, so a human entering the date in the millisecond
                // between a separate read and write can no longer be clobbered.
                DB::table('claim_tracker_workflow')
                    ->where('claim_id', $claim->id)
                    ->where(function ($q) {
                        $q->whereNull('assessment_report_date')
                          ->orWhere('assessment_report_date', '');
                    })
                    ->update([
                        'assessment_report_date' => $n['reportDate'],
                        'updated_at'             => now(),
                    ]);
            }

            return [
                'status'   => 'updated',
                'claim_id' => (int) $claim->id,
                'writeOff' => ($n['totalLoss'] ?? 0) === 1,
            ];
        } catch (\Throwable $e) {
            Log::warning('[motolink] applyNormalized update failed', [
                'claim_number' => $claimNumber,
                'error'        => $e->getMessage(),
            ]);
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * Map a raw motolink assessment -> normalized record.
     * THE ONLY place to edit when the real motolink response shape is confirmed.
     * Defensive reads (several candidate keys) keep it working across shapes.
     */
    public function normalizeAssessment(array $a): array
    {
        $pick = function (array $keys) use ($a) {
            foreach ($keys as $k) {
                $v = $a;
                foreach (explode('.', $k) as $p) {
                    if (is_array($v) && array_key_exists($p, $v)) {
                        $v = $v[$p];
                    } else {
                        $v = null;
                        break;
                    }
                }
                if ($v !== null && $v !== '') {
                    return $v;
                }
            }
            return null;
        };

        $statusRaw = trim((string) ($pick(['status', 'state', 'stage']) ?? ''));
        $isTotalLoss = preg_match('/total\s*loss|write[-\s]?off/i', $statusRaw) === 1
            || $pick(['totalLoss', 'is_total_loss', 'writeOff']) === true;
        $alert = $pick(['writeOffAlert', 'alert', 'writeoff_alert', 'alerts.0.message']);

        return [
            'assessmentId'  => (string) ($pick(['assessmentId', 'assessment_id', 'reference', 'ref', 'id']) ?? ''),
            'claimNumber'   => trim((string) ($pick(['claimNumber', 'claim_number', 'claimNo', 'claim']) ?? '')),
            'vin'           => (string) ($pick(['vin', 'vinCode', 'vin_code']) ?? ''),
            'registration'  => (string) ($pick(['registration', 'reg', 'plate', 'plateNumber']) ?? ''),
            'make'          => (string) ($pick(['make', 'vehicleMake']) ?? ''),
            'model'         => (string) ($pick(['model', 'vehicleModel']) ?? ''),
            'status'        => $statusRaw,
            'finalCost'     => (float) ($pick(['finalCost', 'final_cost', 'authorisedAmount', 'authorised_amount', 'totalCost']) ?? 0),
            'totalLoss'     => $isTotalLoss ? 1 : 0,
            'writeOffAlert' => $alert ? (string) $alert : ($isTotalLoss ? 'Possible write-off' : ''),
            'updatedAt'     => (string) ($pick(['updatedAt', 'updated_at', 'modified']) ?? ''),
            'reportDate'    => (string) ($pick(['assessmentReportDate', 'report_date', 'reportDate', 'completedAt']) ?? ''),
        ];
    }

    /**
     * Fetch assessments — via HTTP (paginated) or from built-in fixtures when
     * mock is on. Defensive paginated pull; stops when a page is empty or short.
     */
    private function fetchAssessments(): array
    {
        if ((bool) config('motolink.mock', false)) {
            return $this->mockAssessments();
        }

        $base     = $this->base();
        $key      = (string) config('motolink.key', '');
        $pageSize = max(1, (int) config('motolink.page_size', 100));
        $timeout  = max(1, (int) config('motolink.timeout', 20));

        $out  = [];
        $page = 1;
        while (true) {
            $resp = Http::withHeaders([
                    'api-key' => $key,
                    'Accept'  => 'application/json',
                ])
                ->timeout($timeout)
                ->get($base . '/assessments', ['page' => $page, 'pageSize' => $pageSize]);

            if (!$resp->successful()) {
                throw new \RuntimeException("motolink {$resp->status()} on page {$page}");
            }

            $body = $resp->json();
            if (is_array($body) && array_keys($body) === range(0, count($body) - 1)) {
                $rows = $body; // top-level list
            } else {
                $rows = $body['data'] ?? $body['assessments'] ?? $body['results'] ?? [];
            }
            if (!is_array($rows)) {
                $rows = [];
            }

            foreach ($rows as $r) {
                $out[] = $r;
            }

            if (count($rows) < $pageSize) {
                break;
            }
            $page++;
            if ($page > 1000) {
                break; // hard stop
            }
        }

        return $out;
    }

    /** Screenshot-derived fixtures for MOTOLINK_MOCK=true testing. */
    private function mockAssessments(): array
    {
        return [
            ['assessmentId' => 'ALPHA-TEST-0001', 'claimNumber' => 'TESTCLM-0001', 'registration' => 'BTEST001', 'status' => 'Authorised', 'finalCost' => 18450.00, 'updatedAt' => '2026-06-24'],
            ['assessmentId' => 'ALPHA-TEST-0002', 'claimNumber' => 'TESTCLM-0002', 'registration' => 'BTEST002', 'status' => 'Request Auth', 'finalCost' => 0, 'updatedAt' => '2026-06-24'],
            ['assessmentId' => 'ALPHA-TEST-0003', 'claimNumber' => 'TESTCLM-0003', 'registration' => 'BTEST003', 'status' => 'Total Loss', 'finalCost' => 0, 'writeOffAlert' => 'Possible write-off detected', 'updatedAt' => '2026-06-19'],
            ['assessmentId' => 'ALPHA-TEST-0004', 'claimNumber' => 'TESTCLM-0002', 'vin' => 'TESTVIN0000000001', 'registration' => 'BTEST004', 'make' => 'TOYOTA', 'model' => 'FORTUNER (G) 5D', 'status' => 'Completed', 'finalCost' => 42310.50, 'assessmentReportDate' => '2026-06-24', 'updatedAt' => '2026-06-24'],
        ];
    }
}
