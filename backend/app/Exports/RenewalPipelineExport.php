<?php

namespace AlphaDirect\Exports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

/**
 * Export the renewals pipeline (policy + premium + customer + agent) so
 * call agents can work the list offline. Mirrors
 * RenewalDashboardController::pipeline() filtering but returns every row
 * rather than paginating.
 *
 * The SQL shape is kept in lockstep with pipeline() so the on-screen
 * grid and the downloaded sheet stay in sync.
 */
class RenewalPipelineExport implements FromArray, WithHeadings, ShouldAutoSize
{
    use Exportable;

    protected string $filter;
    protected ?string $search;
    protected $productId;
    protected string $section;

    public function __construct(string $filter = 'all', ?string $search = null, $productId = null, string $section = 'all')
    {
        $this->filter    = $filter;
        $this->search    = $search;
        $this->productId = $productId;
        $this->section   = $section;
    }

    public function headings(): array
    {
        return [
            'Policy Number', 'Section', 'Product', 'Anniversary',
            'Customer Name', 'Customer Phone', 'Customer Email',
            'Agent Name', 'Agent Email', 'Expiry Date', 'Days To Expiry',
            'Current Premium', 'Old Premium', 'New Premium', 'Rate Change %',
            'Is Rated', 'Is Renewed', 'Needs Consent',
        ];
    }

    public function array(): array
    {
        $hasRenewals = Schema::hasTable('policy_renewals');

        // Derived table: latest ISSUED action per policy.
        $latestIssued = DB::table('policy_actions')
            ->select('policy_id', DB::raw('MAX(id) as id'))
            ->where('status', 'ISSUED')
            ->whereNull('deleted_at')
            ->groupBy('policy_id');

        $query = DB::table('policies as p')
            ->leftJoinSub($latestIssued, 'li', 'li.policy_id', '=', 'p.id')
            ->leftJoin('policy_actions as pa', 'pa.id', '=', 'li.id')
            ->leftJoin('customer as c', 'c.id', '=', 'p.customer_id')
            ->leftJoin('users as ag', 'ag.id', '=', 'p.agent_id')
            ->leftJoin('products as prod', 'prod.id', '=', 'p.product_id')
            ->when($hasRenewals, function ($q) {
                $q->leftJoin('policy_renewals as pr', function ($j) {
                    $j->on('pr.policy_id', '=', 'p.id')->where('pr.is_renewed', 0);
                });
            })
            ->where('p.status', 1)
            ->where(function ($q) {
                $q->whereNotNull('pa.effective_to')->orWhereNotNull('p.expiry_date');
            });

        $expiryExpr = 'COALESCE(pa.effective_to, p.expiry_date)';
        // The DomCom family, matching Support\KycDomComProducts::IDS and the
        // specialist product set used by the renew crons. Commercial
        // Liabilities (20), Marine (22), Guarantee (23) and Miscellaneous (24)
        // were missing, and because the 'mis' section is the NOT-IN complement
        // of this list, their renewals were reported in the MIS retail
        // pipeline instead of DomCom. None of them is an MIS product
        // (KycComplianceCheck::MIS_PRODUCT_IDS = 1-6, 9, 10).
        $domcomIds  = [7, 8, 16, 17, 18, 19, 20, 22, 23, 24];

        if ($this->section === 'domcom') {
            $query->whereIn('p.product_id', $domcomIds);
        } elseif ($this->section === 'mis') {
            $query->where(function ($q) use ($domcomIds) {
                $q->whereNotIn('p.product_id', $domcomIds)
                  ->orWhere('p.policyNumber', 'like', 'MIS%');
            });
        }

        match ($this->filter) {
            'due_7'    => $query->whereRaw("{$expiryExpr} BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"),
            'due_15'   => $query->whereRaw("{$expiryExpr} BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 15 DAY)"),
            'due_30'   => $query->whereRaw("{$expiryExpr} BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"),
            'overdue'  => $query->whereRaw("{$expiryExpr} < CURDATE()"),
            'pending_consent' => $hasRenewals
                ? $query->where('pr.is_rated', 1)->where('pr.is_renewed', 0)->whereColumn('pr.new_premium', '>', 'pr.old_premium')
                : $query->whereRaw('1=0'),
            'renewed'  => (function () use ($query, $hasRenewals) {
                // Mirror RenewalDashboardController::pipeline() 'renewed'
                // branch — MIS via policy_renewals + DOM/COM via
                // policy_actions, both windowed to the last 30 days,
                // deduped by policy_id.
                $cutoff = now()->subDays(30)->toDateTimeString();
                $misIds = $hasRenewals
                    ? \DB::table('policy_renewals')
                        ->where('is_renewed', 1)
                        ->where('renew_completed', 1)
                        ->where('updated_at', '>=', $cutoff)
                        ->pluck('policy_id')->toArray()
                    : [];
                $actionIds = \DB::table('policy_actions')
                    ->whereIn('transaction_type', ['RENEW', 'ANNIVERSARY-RENEW'])
                    ->where('status', 'ISSUED')
                    ->where('updated_at', '>=', $cutoff)
                    ->whereNull('deleted_at')
                    ->distinct()
                    ->pluck('policy_id')->toArray();
                $allIds = array_values(array_unique(array_filter(array_merge($misIds, $actionIds))));
                return empty($allIds)
                    ? $query->whereRaw('1=0')
                    : $query->whereIn('p.id', $allIds);
            })(),
            default    => $query->whereRaw("{$expiryExpr} BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)"),
        };

        if ($this->productId) $query->where('p.product_id', $this->productId);
        if ($this->search) {
            $s = $this->search;
            $query->where(function ($q) use ($s) {
                $q->where('p.policyNumber', 'like', "%{$s}%")
                  ->orWhereRaw("CONCAT(c.firstName, ' ', c.lastName) LIKE ?", ["%{$s}%"]);
            });
        }

        $selectCols = [
            'p.policyNumber', 'p.product_id', 'p.premium_freq',
            'prod.name as product_name',
            DB::raw("CONCAT(COALESCE(c.firstName, ''), ' ', COALESCE(c.lastName, '')) as customer_name"),
            'c.cellphone as customer_phone', 'c.email as customer_email',
            DB::raw("CONCAT(COALESCE(ag.firstName, ''), ' ', COALESCE(ag.lastName, '')) as agent_name"),
            'ag.email as agent_email',
            DB::raw("{$expiryExpr} as expiry_date"),
            DB::raw("DATEDIFF({$expiryExpr}, CURDATE()) as days_to_expiry"),
            'p.premium as current_premium',
        ];
        if ($hasRenewals) {
            $selectCols = array_merge($selectCols, [
                'pr.old_premium', 'pr.new_premium', 'pr.is_rated', 'pr.is_renewed',
            ]);
        }

        $rows = $query
            ->select($selectCols)
            ->orderBy(DB::raw("DATEDIFF({$expiryExpr}, CURDATE())"))
            ->get();

        $fmt = fn($v) => $v !== null ? number_format((float) $v, 2, '.', ',') : '';
        $freqLabel = fn($f) => match ((int) ($f ?? 0)) {
            1 => 'Monthly', 2 => 'Three Instalments', 3 => 'Annual',
            4 => 'Semiannual', 5 => 'Quarterly', 6 => 'Manual Input',
            default => 'Unknown',
        };
        $sectionOf = fn($pid) => in_array((int) $pid, $domcomIds, true) ? 'DomCom' : 'MIS';

        return $rows->map(fn($r) => [
            $r->policyNumber,
            $sectionOf($r->product_id ?? 0),
            $r->product_name ?? '',
            $freqLabel($r->premium_freq ?? 0),
            trim($r->customer_name ?? ''),
            $r->customer_phone ?? '',
            $r->customer_email ?? '',
            trim($r->agent_name ?? ''),
            $r->agent_email ?? '',
            $r->expiry_date ? substr($r->expiry_date, 0, 10) : '',
            (int) $r->days_to_expiry,
            $fmt($r->current_premium),
            $fmt($r->old_premium ?? null),
            $fmt($r->new_premium ?? null),
            (($r->old_premium ?? null) && ($r->new_premium ?? null))
                ? round((($r->new_premium - $r->old_premium) / $r->old_premium) * 100, 1)
                : '',
            ($r->is_rated ?? 0) ? 'Yes' : 'No',
            ($r->is_renewed ?? 0) ? 'Yes' : 'No',
            (($r->is_rated ?? 0) && !($r->is_renewed ?? 0) && ($r->new_premium ?? 0) > ($r->old_premium ?? 0)) ? 'Yes' : 'No',
        ])->toArray();
    }
}
