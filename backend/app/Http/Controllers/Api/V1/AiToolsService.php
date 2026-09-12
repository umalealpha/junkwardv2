<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use Illuminate\Support\Facades\DB;
use AlphaDirect\Helpers\PiiMask;

class AiToolsService
{
    // Actual product_id → name from the products table
    private array $productNames = [];

    public function __construct()
    {
        // Cache product names for this request
        try {
            $products = DB::table('products')->pluck('name', 'id')->toArray();
            $this->productNames = $products;
        } catch (\Exception $e) {
            $this->productNames = [];
        }
    }

    private function productName(int $id): string
    {
        return $this->productNames[$id] ?? "Product #{$id}";
    }

    public function execute(string $tool, array $input): array
    {
        return match ($tool) {
            'search_policies'     => $this->searchPolicies($input),
            'get_payment_summary' => $this->getPaymentSummary($input),
            'get_customer_info'   => $this->getCustomerInfo($input),
            'get_claims'          => $this->getClaims($input),
            'get_anomalies'       => $this->getAnomalies($input),
            'get_policy_stats'    => $this->getPolicyStats($input),
            'execute_query'       => $this->executeQuery($input),
            default               => ['error' => "Unknown tool: {$tool}"],
        };
    }

    private function searchPolicies(array $input): array
    {
        $statusMap = [
            'active'    => 1,
            'cancelled' => 2,
            'pending'   => 0,
            'expired'   => 3,
        ];

        $query = DB::table('policies as p')
            ->leftJoin('customer as c', 'c.id', '=', 'p.customer_id')
            ->select([
                'p.id',
                'p.policyNumber',
                DB::raw("CONCAT(COALESCE(c.firstName,''), ' ', COALESCE(c.lastName,'')) as customer_name"),
                'p.status',
                'p.premium',
                'p.product_id',
                'p.billingStartDate',
                'p.is_draft',
            ])
            ->limit($input['limit'] ?? 50);

        if (!empty($input['status'])) {
            if ($input['status'] === 'draft') {
                $query->where('p.is_draft', 1);
            } else {
                $s = $statusMap[strtolower($input['status'])] ?? null;
                if ($s !== null) $query->where('p.status', $s);
            }
        }

        if (!empty($input['policy_number'])) {
            $query->where('p.policyNumber', 'like', '%' . $input['policy_number'] . '%');
        }

        if (!empty($input['customer_name'])) {
            $name = trim(preg_replace('/\s+/', ' ', $input['customer_name']));
            $like = "%{$name}%";
            $words = count(explode(' ', $name)) >= 2
                ? array_values(array_filter(explode(' ', $name)))
                : [];
            $query->where(function ($q) use ($like, $words) {
                $q->where('c.firstName', 'like', $like)
                  ->orWhere('c.lastName', 'like', $like)
                  ->orWhereRaw("CONCAT_WS(' ', TRIM(c.firstName), TRIM(c.lastName)) LIKE ?", [$like]);
                if (count($words) >= 2) {
                    $q->orWhere(fn($i) =>
                        $i->where('c.firstName', 'like', "%{$words[0]}%")
                          ->where('c.lastName', 'like', "%{$words[1]}%")
                    )->orWhere(fn($i) =>
                        $i->where('c.firstName', 'like', "%{$words[1]}%")
                          ->where('c.lastName', 'like', "%{$words[0]}%")
                    );
                }
            });
        }

        if (!empty($input['product'])) {
            // Match product by keyword against actual product names in DB
            $keyword = strtolower($input['product']);
            $matchedIds = collect($this->productNames)
                ->filter(fn($name) => str_contains(strtolower($name), $keyword))
                ->keys()->toArray();
            if (!empty($matchedIds)) {
                $query->whereIn('p.product_id', $matchedIds);
            }
        }

        if (!empty($input['date_from'])) {
            $query->where('p.created_at', '>=', $input['date_from']);
        }
        if (!empty($input['date_to'])) {
            $query->where('p.created_at', '<=', $input['date_to'] . ' 23:59:59');
        }

        $rows = $query->get();

        return [
            'tool'    => 'search_policies',
            'count'   => $rows->count(),
            'columns' => ['Policy Number', 'Customer', 'Product', 'Status', 'Premium (P)', 'Start Date'],
            'rows'    => $rows->map(fn($r) => [
                $r->policyNumber,
                trim($r->customer_name),
                $this->productName((int) $r->product_id),
                $this->policyStatusLabel($r->status, $r->is_draft),
                number_format((float) $r->premium, 2),
                $r->billingStartDate,
            ])->toArray(),
        ];
    }

    private function getPaymentSummary(array $input): array
    {
        $groupBy  = $input['group_by'] ?? 'product';
        $dateFrom = $input['date_from'] ?? now()->startOfMonth()->toDateString();
        $dateTo   = $input['date_to']   ?? now()->toDateString();

        if ($groupBy === 'product') {
            $rows = DB::select("
                SELECT
                    COALESCE(pr.name, CONCAT('Product #', p.product_id)) as product,
                    COUNT(DISTINCT pt.policy_id) as policies,
                    SUM(CASE WHEN pt.status IN ('SUCCESS','Paid') AND pt.is_reverse = 0 THEN CAST(pt.amount AS DECIMAL(10,2)) ELSE 0 END) as collected,
                    COUNT(CASE WHEN pt.status IN ('SUCCESS','Paid') AND pt.is_reverse = 0 THEN 1 END) as success_count,
                    COUNT(CASE WHEN pt.status NOT IN ('SUCCESS','Paid') THEN 1 END) as failed_count
                FROM payment_transactions pt
                JOIN policies p ON p.id = pt.policy_id
                LEFT JOIN products pr ON pr.id = p.product_id
                WHERE DATE(pt.created_at) BETWEEN ? AND ?
                GROUP BY p.product_id, pr.name
                ORDER BY collected DESC
            ", [$dateFrom, $dateTo]);
        } elseif ($groupBy === 'day') {
            $rows = DB::select("
                SELECT
                    DATE(pt.created_at) as date,
                    SUM(CASE WHEN pt.status IN ('SUCCESS','Paid') AND pt.is_reverse = 0 THEN CAST(pt.amount AS DECIMAL(10,2)) ELSE 0 END) as collected,
                    COUNT(CASE WHEN pt.status IN ('SUCCESS','Paid') THEN 1 END) as success,
                    COUNT(CASE WHEN pt.status NOT IN ('SUCCESS','Paid') THEN 1 END) as failed
                FROM payment_transactions pt
                WHERE DATE(pt.created_at) BETWEEN ? AND ?
                GROUP BY DATE(pt.created_at)
                ORDER BY date DESC
            ", [$dateFrom, $dateTo]);
        } elseif ($groupBy === 'month') {
            $rows = DB::select("
                SELECT
                    DATE_FORMAT(pt.created_at, '%Y-%m') as month,
                    SUM(CASE WHEN pt.status IN ('SUCCESS','Paid') AND pt.is_reverse = 0 THEN CAST(pt.amount AS DECIMAL(10,2)) ELSE 0 END) as collected,
                    COUNT(CASE WHEN pt.status IN ('SUCCESS','Paid') THEN 1 END) as success,
                    COUNT(CASE WHEN pt.status NOT IN ('SUCCESS','Paid') THEN 1 END) as failed
                FROM payment_transactions pt
                WHERE DATE(pt.created_at) BETWEEN ? AND ?
                GROUP BY DATE_FORMAT(pt.created_at, '%Y-%m')
                ORDER BY month DESC
            ", [$dateFrom, $dateTo]);
        } else {
            // Default: overall summary
            $rows = DB::select("
                SELECT
                    SUM(CASE WHEN status IN ('SUCCESS','Paid') AND is_reverse = 0 THEN CAST(amount AS DECIMAL(10,2)) ELSE 0 END) as total_collected,
                    COUNT(CASE WHEN status IN ('SUCCESS','Paid') THEN 1 END) as total_success,
                    COUNT(CASE WHEN status NOT IN ('SUCCESS','Paid') THEN 1 END) as total_failed,
                    COUNT(*) as total_transactions
                FROM payment_transactions
                WHERE DATE(created_at) BETWEEN ? AND ?
            ", [$dateFrom, $dateTo]);
        }

        return [
            'tool'       => 'get_payment_summary',
            'period'     => "{$dateFrom} to {$dateTo}",
            'grouped_by' => $groupBy,
            'count'      => count($rows),
            'columns'    => array_keys((array) ($rows[0] ?? [])),
            'rows'       => array_map(fn($r) => array_values((array) $r), $rows),
        ];
    }

    private function getCustomerInfo(array $input): array
    {
        $search = trim(preg_replace('/\s+/', ' ', $input['search']));
        $like = "%{$search}%";
        $words = count(explode(' ', $search)) >= 2
            ? array_values(array_filter(explode(' ', $search)))
            : [];

        $customer = DB::table('customer as c')
            ->where(function ($q) use ($like, $words) {
                $q->where('c.firstName', 'like', $like)
                  ->orWhere('c.lastName', 'like', $like)
                  ->orWhereRaw("CONCAT_WS(' ', TRIM(c.firstName), TRIM(c.lastName)) LIKE ?", [$like])
                  ->orWhere('c.idNumber', $search)
                  ->orWhere('c.phone', 'like', $like)
                  ->orWhere('c.email', 'like', $like);
                if (count($words) >= 2) {
                    $q->orWhere(fn($i) =>
                        $i->where('c.firstName', 'like', "%{$words[0]}%")
                          ->where('c.lastName', 'like', "%{$words[1]}%")
                    )->orWhere(fn($i) =>
                        $i->where('c.firstName', 'like', "%{$words[1]}%")
                          ->where('c.lastName', 'like', "%{$words[0]}%")
                    );
                }
            })
            ->first();

        if (!$customer) {
            return ['tool' => 'get_customer_info', 'found' => false, 'message' => "No customer found matching '{$search}'"];
        }

        $policies = DB::table('policies')
            ->where('customer_id', $customer->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'policyNumber', 'status', 'premium', 'product_id', 'billingStartDate']);

        $recentPayments = DB::table('payment_transactions')
            ->where('policy_id', function ($q) use ($customer) {
                $q->select('id')->from('policies')->where('customer_id', $customer->id);
            })
            ->orderByDesc('id')
            ->limit(10)
            ->get(['policy_id', 'amount', 'status', 'new_payment_date']);

        return [
            'tool'     => 'get_customer_info',
            'found'    => true,
            'customer' => [
                'name'      => trim(($customer->firstName ?? '') . ' ' . ($customer->lastName ?? '')),
                // PII is masked UNCONDITIONALLY here: this payload is sent to an
                // external LLM (Groq/Anthropic), so raw values must never leave the
                // server regardless of the staff user's role. Omang + email masked
                // per the DPO field rules (name + phone are shown to all staff);
                // full reveal is via the audited DataAccessRequest / OTP flow, not
                // the assistant.
                'id_number' => PiiMask::idnum($customer->idNumber ?? ''),
                'phone'     => $customer->phone ?? '',
                'email'     => PiiMask::email($customer->email ?? ''),
            ],
            'policies_count' => $policies->count(),
            'columns' => ['Policy Number', 'Status', 'Premium (P)', 'Start Date'],
            'rows'    => $policies->map(fn($p) => [
                $p->policyNumber,
                $this->policyStatusLabel($p->status, 0),
                number_format((float) $p->premium, 2),
                $p->billingStartDate,
            ])->toArray(),
            'recent_payments' => $recentPayments->map(fn($pt) => [
                'amount' => number_format((float) $pt->amount, 2),
                'status' => $pt->status,
                'date'   => $pt->new_payment_date,
            ])->toArray(),
        ];
    }

    private function getClaims(array $input): array
    {
        // Claims status enum: Pending, Approved, Rejected, Closed, Reopen
        $statusMap = [
            'open'     => ['Pending', 'Reopen'],
            'pending'  => ['Pending'],
            'approved' => ['Approved'],
            'rejected' => ['Rejected'],
            'closed'   => ['Closed'],
            'reopen'   => ['Reopen'],
        ];

        try {
            $query = DB::table('claims as cl')
                ->leftJoin('policies as p', 'p.id', '=', 'cl.policy_id')
                ->leftJoin('customer as c', 'c.id', '=', 'p.customer_id')
                ->select([
                    'cl.id',
                    'cl.claim_number',
                    DB::raw("COALESCE(p.policyNumber, '') as policy_number"),
                    DB::raw("CONCAT(COALESCE(c.firstName,''), ' ', COALESCE(c.lastName,'')) as customer"),
                    'cl.claim_type',
                    'cl.status',
                    'cl.category',
                    'cl.registered_claim',
                    'cl.created_at',
                ])
                ->limit($input['limit'] ?? 50)
                ->orderByDesc('cl.id');

            if (!empty($input['status'])) {
                $key = strtolower(trim($input['status']));
                $mapped = $statusMap[$key] ?? null;
                if ($mapped) {
                    $query->whereIn('cl.status', $mapped);
                } else {
                    // Try direct match
                    $query->where('cl.status', $input['status']);
                }
            }
            if (!empty($input['date_from'])) {
                $query->where('cl.created_at', '>=', $input['date_from']);
            }
            if (!empty($input['date_to'])) {
                $query->where('cl.created_at', '<=', $input['date_to'] . ' 23:59:59');
            }
            if (!empty($input['policy_number'])) {
                $query->where('p.policyNumber', 'like', '%' . $input['policy_number'] . '%');
            }

            $rows = $query->get();

            return [
                'tool'    => 'get_claims',
                'count'   => $rows->count(),
                'columns' => ['Claim #', 'Policy Number', 'Customer', 'Claim Type', 'Status', 'Category', 'Registered', 'Created'],
                'rows'    => $rows->map(fn($r) => [
                    $r->claim_number,
                    $r->policy_number,
                    trim($r->customer),
                    $r->claim_type,
                    $r->status,
                    $r->category ?? '',
                    $r->registered_claim ?? '',
                    substr($r->created_at ?? '', 0, 10),
                ])->toArray(),
            ];
        } catch (\Exception $e) {
            return ['tool' => 'get_claims', 'error' => 'Claims query error: ' . $e->getMessage()];
        }
    }

    private function getAnomalies(array $input): array
    {
        $query = DB::table('reconciliation_anomalies')
            ->where('status', 'open')
            ->orderByDesc('id')
            ->limit($input['limit'] ?? 50);

        if (!empty($input['type'])) {
            $query->where('anomaly_type', $input['type']);
        }
        if (!empty($input['severity'])) {
            $query->where('severity', $input['severity']);
        }

        $rows = $query->get();

        // Also get summary
        $summary = DB::table('reconciliation_anomalies')
            ->where('status', 'open')
            ->select('anomaly_type', 'severity', DB::raw('COUNT(*) as cnt'))
            ->groupBy('anomaly_type', 'severity')
            ->get();

        return [
            'tool'    => 'get_anomalies',
            'summary' => $summary,
            'count'   => $rows->count(),
            'columns' => ['Policy', 'Customer', 'Type', 'Severity', 'Description', 'Amount'],
            'rows'    => $rows->map(fn($r) => [
                $r->policy_number  ?? '',
                $r->customer_name  ?? '',
                $r->anomaly_type   ?? '',
                $r->severity       ?? '',
                $r->description    ?? '',
                $r->amount_at_risk ?? '',
            ])->toArray(),
        ];
    }

    private function getPolicyStats(array $input): array
    {
        $period = $input['period'] ?? 'this_month';

        [$dateFrom, $dateTo] = match ($period) {
            'today'      => [now()->toDateString(), now()->toDateString()],
            'this_week'  => [now()->startOfWeek()->toDateString(), now()->toDateString()],
            'this_month' => [now()->startOfMonth()->toDateString(), now()->toDateString()],
            'last_month' => [now()->subMonth()->startOfMonth()->toDateString(), now()->subMonth()->endOfMonth()->toDateString()],
            'this_year'  => [now()->startOfYear()->toDateString(), now()->toDateString()],
            default      => ["{$period}-01", "{$period}-31"],
        };

        $active = DB::table('policies')
            ->where('status', 1)
            ->select('product_id', DB::raw('COUNT(*) as count'), DB::raw('SUM(CAST(premium AS DECIMAL(10,2))) as total_premium'))
            ->groupBy('product_id')
            ->get();

        $newPolicies = DB::table('policies')
            ->whereBetween(DB::raw('DATE(created_at)'), [$dateFrom, $dateTo])
            ->count();

        $cancelledPolicies = DB::table('policies')
            ->where('status', 2)
            ->whereBetween(DB::raw('DATE(updated_at)'), [$dateFrom, $dateTo])
            ->count();

        $byProduct = $active->map(fn($r) => [
            'product_name'    => $this->productName((int) $r->product_id),
            'active_count'    => $r->count,
            'monthly_premium' => 'P ' . number_format((float) $r->total_premium, 0),
        ])->toArray();

        return [
            'tool'                => 'get_policy_stats',
            'period'              => "{$dateFrom} to {$dateTo}",
            'new_policies'        => $newPolicies,
            'cancelled_in_period' => $cancelledPolicies,
            'active_by_product'   => $byProduct,
            'total_active'        => $active->sum('count'),
            'count'   => count($byProduct),
            'columns' => ['Product', 'Active Policies', 'Monthly Premium (P)'],
            'rows'    => array_map(fn($r) => [
                $r['product_name'],
                number_format($r['active_count']),
                $r['monthly_premium'],
            ], $byProduct),
            'label'   => 'Active Policies by Product',
        ];
    }

    private function executeQuery(array $input): array
    {
        // C2 (pentest) — HARD DISABLED. This tool let the AI model run arbitrary
        // model-chosen SELECTs against any table (incl. sensitive PII), bypassing
        // field-level masking, gated only by a leaky blocklist. It is removed from
        // the tool schema; this server-side guard neutralises ANY caller (incl.
        // the WhatsApp path) regardless of schema. Do NOT remove without replacing
        // free-form SQL with fixed, parameterised, column-allow-listed queries.
        return ['error' => 'The custom query tool is disabled. Please ask for a specific report instead.'];

        // --- retained below (unreachable) for a future allow-listed rebuild ---
        $sql   = trim($input['sql'] ?? '');
        $label = $input['label'] ?? 'Custom Query';

        // Strip a single trailing semicolon, then reject anything that still
        // contains one — blocks stacked/multi statements (e.g. a smuggled
        // "SELECT 1; DROP ...").
        $sql = rtrim($sql, "; \t\n\r");
        if (str_contains($sql, ';')) {
            return ['error' => 'Only a single statement is allowed.'];
        }

        // Security: only allow SELECT
        if (!preg_match('/^SELECT\s/i', $sql)) {
            return ['error' => 'Only SELECT statements are allowed.'];
        }

        // Block SQL comments — they can smuggle blocked keywords past the
        // word-boundary checks below.
        if (preg_match('/--|#|\/\*/', $sql)) {
            return ['error' => 'SQL comments are not allowed.'];
        }

        // Block dangerous keywords + file read/write exfiltration.
        $blocked = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'TRUNCATE', 'ALTER', 'CREATE', 'REPLACE', 'CALL', 'EXEC', 'GRANT', 'LOAD_FILE'];
        foreach ($blocked as $kw) {
            if (preg_match('/\b' . $kw . '\b/i', $sql)) {
                return ['error' => "Statement contains blocked keyword: {$kw}"];
            }
        }
        if (preg_match('/\bINTO\s+(OUTFILE|DUMPFILE)\b/i', $sql)) {
            return ['error' => 'File-writing queries are not allowed.'];
        }

        // Add LIMIT if not present
        if (!preg_match('/LIMIT\s+\d+/i', $sql)) {
            $sql .= ' LIMIT 500';
        }

        try {
            $rows = DB::select($sql);

            if (empty($rows)) {
                return ['tool' => 'execute_query', 'label' => $label, 'count' => 0, 'columns' => [], 'rows' => []];
            }

            $columns = array_keys((array) $rows[0]);

            // DPA: mask PII columns for non-privileged users. PiiMask::if*
            // returns raw for privileged staff (Admin roles / DPO reveal
            // permissions) and masked otherwise, so raw Omang/banking/email/
            // DOB never leaves the server for an unprivileged AI session —
            // mirroring the masking the rest of the API layer already applies.
            $maskers = array_map(fn ($c) => $this->piiMaskerFor((string) $c), $columns);

            $rows = array_map(function ($r) use ($maskers) {
                $vals = array_values((array) $r);
                foreach ($vals as $i => $v) {
                    if (($maskers[$i] ?? null) !== null && $v !== null && $v !== '') {
                        $vals[$i] = ($maskers[$i])((string) $v);
                    }
                }
                return $vals;
            }, $rows);

            return [
                'tool'    => 'execute_query',
                'label'   => $label,
                'sql'     => $sql,
                'count'   => count($rows),
                'columns' => $columns,
                'rows'    => $rows,
            ];
        } catch (\Exception $e) {
            return ['error' => 'Query failed: ' . $e->getMessage()];
        }
    }

    /**
     * Return a privileged-aware PiiMask callable for a result column matched
     * by name, or null if the column is not PII. Name + phone are deliberately
     * left raw (DPO policy: shown in full to all staff — see PiiMask). Matchers
     * are intentionally specific so foreign keys / analytics columns
     * (customer_id, product_name, accounting_date, ...) are never masked.
     */
    private function piiMaskerFor(string $column): ?callable
    {
        $c = strtolower($column);

        // Date of birth / address / medical -> fully hidden.
        if (preg_match('/(^|_)(dob|date_of_birth|birth_?date)($|_)/', $c)
            || str_contains($c, 'address')
            || str_contains($c, 'residential')
            || str_contains($c, 'postal')
            || str_contains($c, 'physical')
            || str_contains($c, 'medical')
            || str_contains($c, 'diagnos')) {
            return fn ($v) => PiiMask::ifHidden($v);
        }
        // Bank account number (not accounting_* / *_id).
        if (preg_match('/(account_?number|account_?no|bank_?account|acc_no)/', $c)) {
            return fn ($v) => PiiMask::ifBankAccount($v);
        }
        if (preg_match('/(branch_?code|sort_?code)/', $c)) {
            return fn ($v) => PiiMask::ifBranchCode($v);
        }
        if (preg_match('/bank_?name/', $c)) {
            return fn ($v) => PiiMask::ifBankName($v);
        }
        // Omang / national id / passport (NOT bare *_id foreign keys).
        if (str_contains($c, 'omang')
            || preg_match('/(^|_)(id_?number|id_?no|national_?id|passport)($|_)?/', $c)) {
            return fn ($v) => PiiMask::ifId($v);
        }
        // Email.
        if (str_contains($c, 'email')) {
            return fn ($v) => PiiMask::ifEmail($v);
        }

        return null;
    }

    private function policyStatusLabel(int $status, $isDraft): string
    {
        if ($isDraft) return 'Draft';
        return match ($status) {
            1       => 'Active',
            2       => 'Cancelled',
            0       => 'Pending',
            3       => 'Expired',
            default => 'Unknown',
        };
    }
}
