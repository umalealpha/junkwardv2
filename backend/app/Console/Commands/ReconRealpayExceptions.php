<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Routine task: reconcile DOMG (Domestic) + COMG (Commercial) policies against
 * RealPay debit-order mandates and write the exceptions into the Exceptions
 * module (recon_exception_* tables) for Finance to review.
 *
 * READ-ONLY against the operational tables (policies, realpay_client_contracts,
 * realpay_contract_installments, claims, cancel_policies). It only writes to the
 * recon_exception_* tables. It never changes a mandate, premium or policy.
 *
 * Flags (mutually exclusive per policy so nothing is double-listed):
 *   A  active monthly policy (premium_freq=1) with NO active RealPay mandate
 *   B  active RealPay mandate on a non-active policy, NO registered claim (orphan)
 *   C  active monthly policy + active mandate, RealPay debit != Graphite premium (>P1)
 *   D  active RealPay mandate on a non-active policy WITH a registered claim
 *      (claim-driven — "agreement of loss / cancellation, mandate must stop")
 *
 * Idempotent per (source, run_date): re-running the same day regenerates the
 * run's exceptions UNLESS the run has already been closed by Finance.
 *
 * Scheduled weekly (Mondays 04:00) in Console/Kernel.php.
 */
class ReconRealpayExceptions extends Command
{
    protected $signature = 'recon:realpay-exceptions {--dry : Compute and print a summary without writing}';
    protected $description = 'Reconcile DOMG/COMG policies vs RealPay mandates; write exceptions for Finance review';

    private const TOL = 1.0;            // BWP tolerance for premium match
    private const PRODUCTS = ['DOMG', 'COMG'];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $source = 'realpay';
        $runDate = now()->toDateString();

        try {
            // Compute from the operational tables only — no recon_exception_* access
            // here, so --dry works even before this module's tables are migrated.
            $exceptions = [];
            $totals = $this->emptyTotals();

            foreach (self::PRODUCTS as $product) {
                $this->line("Computing {$product} …");
                $this->flagA($product, $exceptions, $totals);
                $this->flagC($product, $exceptions, $totals);
                $this->flagsBD($product, $exceptions, $totals);
            }

            $openCount = count($exceptions);
            $byProduct = [];
            foreach ($exceptions as $e) {
                $byProduct[$e['product']][$e['flag_code']] = ($byProduct[$e['product']][$e['flag_code']] ?? 0) + 1;
            }
            $totals['by_product_flag'] = $byProduct;

            if ($dry) {
                $this->info("DRY RUN — {$openCount} exceptions computed (nothing written):");
                $this->line(json_encode($totals, JSON_PRETTY_PRINT));
                return self::SUCCESS;
            }

            // Idempotency: one run per (source, run_date); never overwrite a closed review.
            $existing = DB::table('recon_exception_runs')
                ->where('source', $source)->where('run_date', $runDate)->first();
            if ($existing && $existing->status === 'closed') {
                $this->warn("Run for {$runDate} is already closed by Finance — not overwriting.");
                return self::SUCCESS;
            }

            // Create or reuse the run row
            $now = now();
            if ($existing) {
                $runId = $existing->id;
                DB::table('recon_exceptions')->where('run_id', $runId)->delete();
                DB::table('recon_exception_runs')->where('id', $runId)->update([
                    'status' => 'generating', 'error' => null, 'updated_at' => $now,
                ]);
            } else {
                $runId = DB::table('recon_exception_runs')->insertGetId([
                    'source'      => $source,
                    'period_label'=> 'Week of ' . $now->format('d M Y'),
                    'run_date'    => $runDate,
                    'status'      => 'generating',
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }

            // Insert exceptions in chunks
            foreach (array_chunk($exceptions, 500) as $chunk) {
                $rows = array_map(function ($e) use ($runId, $now) {
                    return [
                        'run_id'         => $runId,
                        'product'        => $e['product'],
                        'flag_code'      => $e['flag_code'],
                        'flag_label'     => $e['flag_label'],
                        'policy_number'  => $e['policy_number'] ?? null,
                        'customer_id'    => $e['customer_id'] ?? null,
                        'contract_number'=> $e['contract_number'] ?? null,
                        'severity'       => $e['severity'],
                        'graphite_value' => $e['graphite_value'] ?? null,
                        'realpay_value'  => $e['realpay_value'] ?? null,
                        'variance'       => $e['variance'] ?? null,
                        'detail'         => json_encode($e['detail'] ?? []),
                        'status'         => 'open',
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ];
                }, $chunk);
                DB::table('recon_exceptions')->insert($rows);
            }

            DB::table('recon_exception_runs')->where('id', $runId)->update([
                'status'          => 'ready',
                'exception_count' => $openCount,
                'open_count'      => $openCount,
                'totals'          => json_encode($totals),
                'updated_at'      => now(),
            ]);

            $this->info("Run #{$runId} ready — {$openCount} exceptions written for {$runDate}.");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('recon:realpay-exceptions failed', ['err' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            if (!empty($runId)) {
                DB::table('recon_exception_runs')->where('id', $runId)->update([
                    'status' => 'failed', 'error' => $e->getMessage(), 'updated_at' => now(),
                ]);
            }
            $this->error('Failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function emptyTotals(): array
    {
        return ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'c_cannot_verify' => 0, 'c_agree' => 0];
    }

    /** A — active monthly policy with no active RealPay mandate. */
    private function flagA(string $product, array &$out, array &$totals): void
    {
        $rows = DB::select("
            SELECT p.policyNumber, p.customer_id, p.annual_premium, DATE(p.created_at) created
            FROM policies p
            LEFT JOIN (SELECT DISTINCT policy_id FROM realpay_client_contracts WHERE status=1) ra ON ra.policy_id=p.id
            WHERE p.policyNumber LIKE ? AND p.status=1 AND p.premium_freq=1 AND ra.policy_id IS NULL
            ORDER BY p.created_at", [$product . '%']);
        foreach ($rows as $r) {
            $out[] = [
                'product' => $product, 'flag_code' => 'A',
                'flag_label' => 'Active monthly policy with no RealPay mandate',
                'policy_number' => $r->policyNumber, 'customer_id' => $r->customer_id,
                'severity' => 'medium',
                'detail' => ['annual_premium' => $r->annual_premium, 'policy_created' => $r->created],
            ];
            $totals['A']++;
        }
    }

    /** C — matched monthly policy whose RealPay debit != Graphite premium (>P1). */
    private function flagC(string $product, array &$out, array &$totals): void
    {
        $matched = DB::select("
            SELECT p.id, p.policyNumber, p.customer_id, p.premium g_monthly
            FROM policies p
            JOIN (SELECT DISTINCT policy_id FROM realpay_client_contracts WHERE status=1) ra ON ra.policy_id=p.id
            WHERE p.policyNumber LIKE ? AND p.status=1 AND p.premium_freq=1", [$product . '%']);
        if (!$matched) return;

        $ids = array_map(fn ($r) => $r->id, $matched);
        $ctr = DB::select("SELECT policy_id, contract_number FROM realpay_client_contracts
                           WHERE status=1 AND policy_id IN (" . implode(',', $ids) . ")");
        $byPol = []; $allCn = [];
        foreach ($ctr as $c) { $byPol[$c->policy_id][] = $c->contract_number; $allCn[$c->contract_number] = 1; }

        $cmap = [];
        if ($allCn) {
            $in = implode(',', array_map(fn ($s) => "'" . addslashes($s) . "'", array_keys($allCn)));
            // realpay_contract_installments.policy_id is unreliable — join by contractNumber.
            $inst = DB::select("SELECT contractNumber,
                SUBSTRING_INDEX(GROUP_CONCAT(InstalmentAmount ORDER BY InstalmentActionDate DESC),',',1) amt,
                MAX(InstalmentActionDate) d
                FROM realpay_contract_installments WHERE InstalmentAmount>0 AND contractNumber IN ($in)
                GROUP BY contractNumber");
            foreach ($inst as $x) $cmap[$x->contractNumber] = ['amt' => $x->amt, 'd' => $x->d];
        }

        foreach ($matched as $r) {
            $g = is_numeric($r->g_monthly) ? round((float) $r->g_monthly, 2) : null;
            $best = null; $bd = null; $bestCn = null;
            foreach (($byPol[$r->id] ?? []) as $cn) {
                if (isset($cmap[$cn])) {
                    $d = $cmap[$cn]['d'];
                    if ($bd === null || $d > $bd) { $bd = $d; $best = $cmap[$cn]['amt']; $bestCn = $cn; }
                }
            }
            $rp = is_numeric($best) ? round((float) $best, 2) : null;

            if ($g === null) { $totals['c_cannot_verify']++; continue; }   // not an actionable exception
            if ($rp === null) { $totals['c_cannot_verify']++; continue; }
            $diff = round($rp - $g, 2);
            if (abs($diff) <= self::TOL) { $totals['c_agree']++; continue; }

            $out[] = [
                'product' => $product, 'flag_code' => 'C',
                'flag_label' => 'RealPay debit differs from Graphite premium',
                'policy_number' => $r->policyNumber, 'customer_id' => $r->customer_id,
                'contract_number' => $bestCn,
                'graphite_value' => $g, 'realpay_value' => $rp, 'variance' => $diff,
                'severity' => abs($diff) > 1000 ? 'high' : 'medium',
                'detail' => ['note' => 'RealPay figure is latest installment; may include arrears/catch-up'],
            ];
            $totals['C']++;
        }
    }

    /**
     * B + D — active RealPay mandate on a non-active policy.
     *   D = the policy has a registered claim (claim-driven: agreement of loss / cancellation)
     *   B = no registered claim (plain orphan mandate)
     * Split so a policy appears under exactly one flag.
     */
    private function flagsBD(string $product, array &$out, array &$totals): void
    {
        $rp = DB::select("
            SELECT p.id, p.policyNumber, p.customer_id, p.status pol_status, r.contract_number
            FROM policies p JOIN realpay_client_contracts r ON r.policy_id=p.id AND r.status=1
            WHERE p.policyNumber LIKE ? AND p.status<>1", [$product . '%']);
        if (!$rp) return;

        // dedup to one row per policy
        $seen = []; $uniq = [];
        foreach ($rp as $r) { if (isset($seen[$r->policyNumber])) continue; $seen[$r->policyNumber] = 1; $uniq[] = $r; }

        $ids = array_values(array_unique(array_map(fn ($r) => $r->id, $uniq)));
        $clmap = [];
        if ($ids) {
            $cl = DB::select("SELECT policy_id, COUNT(*) cc,
                GROUP_CONCAT(DISTINCT claim_type SEPARATOR '; ') types, MAX(created_at) last
                FROM claims WHERE policy_id IN (" . implode(',', $ids) . ") GROUP BY policy_id");
            foreach ($cl as $x) $clmap[$x->policy_id] = $x;
        }

        $statusLabel = [0 => 'Deactivated', 2 => 'Cancelled', 3 => 'Expired'];
        foreach ($uniq as $r) {
            $cm = $clmap[$r->id] ?? null; $cc = $cm ? (int) $cm->cc : 0;
            $st = $statusLabel[(int) $r->pol_status] ?? ('Status ' . $r->pol_status);
            if ($cc > 0) {
                $out[] = [
                    'product' => $product, 'flag_code' => 'D',
                    'flag_label' => 'Cancelled/claimed policy still has an active RealPay mandate',
                    'policy_number' => $r->policyNumber, 'customer_id' => $r->customer_id,
                    'contract_number' => $r->contract_number,
                    'severity' => 'critical',
                    'detail' => ['policy_status' => $st, 'claim_count' => $cc,
                                 'claim_types' => $cm->types ?? null, 'last_claim' => $cm->last ?? null,
                                 'note' => "Agreement of Loss is not a stored field; cancellation + claim used as proxy"],
                ];
                $totals['D']++;
            } else {
                $out[] = [
                    'product' => $product, 'flag_code' => 'B',
                    'flag_label' => 'Active RealPay mandate on a non-active policy',
                    'policy_number' => $r->policyNumber, 'customer_id' => $r->customer_id,
                    'contract_number' => $r->contract_number,
                    'severity' => 'high',
                    'detail' => ['policy_status' => $st],
                ];
                $totals['B']++;
            }
        }
    }
}
