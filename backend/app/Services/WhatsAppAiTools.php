<?php

namespace AlphaDirect\Services;

use Illuminate\Support\Facades\DB;

/**
 * WhatsApp AI tools — persona-scoped data access.
 *
 * Staff / Exco get execute_query so they can answer ANY business question
 * by writing SQL directly against the live schema.  Narrow helper tools
 * exist for the most common lookups, but execute_query is the fallback
 * for anything not covered.
 *
 * Agents see only their own data.  Customers see only their own data.
 */
class WhatsAppAiTools
{
    // =========================================================================
    //  Tool dispatcher
    // =========================================================================

    public static function execute(string $tool, array $input, array $context): array
    {
        return match ($tool) {
            // ── Customer tools ───────────────────────────────────────────────
            'get_my_policies'   => self::getMyPolicies($context),
            'get_my_claims'     => self::getMyClaims($context),
            'get_my_payments'   => self::getMyPayments($context),
            'get_my_kyc_status' => self::getMyKycStatus($context),
            'get_my_balance'    => self::getMyBalance($context),
            // ── Agent tools ──────────────────────────────────────────────────
            'get_agent_policies'   => self::getAgentPolicies($context, $input),
            'get_agent_commission' => self::getAgentCommission($context),
            'get_agent_renewals'   => self::getAgentRenewals($context),
            'get_agent_customers'  => self::getAgentCustomers($context),
            // ── Staff / Exco tools ───────────────────────────────────────────
            'execute_query'         => self::executeQuery($input),
            'generate_chart'        => self::generateChart($input),
            'get_policy_stats'      => self::getPolicyStats($input),
            'get_collection_today'  => self::getCollectionToday($input),
            'get_agent_leaderboard' => self::getAgentLeaderboard($input),
            'get_premium_register'  => self::getPremiumRegister($input),
            // ── DevOps / Agent tools ─────────────────────────────────────────
            'check_error_logs'    => self::checkErrorLogs($input),
            'check_failed_jobs'   => self::checkFailedJobs($input),
            'run_artisan'         => self::runArtisan($input),
            'ecs_service_health'  => self::ecsServiceHealth($input),
            'trigger_deploy'      => self::triggerDeploy($input),
            'read_cloudwatch_logs'=> self::readCloudwatchLogs($input),
            'search_code'         => self::searchCode($input),
            'read_code_file'      => self::readCodeFile($input),
            'create_code_fix'     => self::createCodeFix($input),
            default => ['error' => "Unknown tool: {$tool}"],
        };
    }

    // =========================================================================
    //  Tool definitions — returned to AI as callable schema
    // =========================================================================

    public static function customerTools(): array
    {
        return [
            self::tool('get_my_policies',   'Get all policies for this customer (active, cancelled, pending)',                                      []),
            self::tool('get_my_claims',      'Get all claims filed by this customer',                                                               []),
            self::tool('get_my_payments',    'Get recent payment history for this customer (last 10 transactions)',                                  []),
            self::tool('get_my_kyc_status',  'Check KYC compliance status and list missing documents',                                              []),
            self::tool('get_my_balance',     'Get outstanding balance, next payment due date and amount',                                            []),
        ];
    }

    public static function agentTools(): array
    {
        return [
            self::tool('get_agent_policies',   'Get policies sold by this agent. Filter by status (active/cancelled/all) and period (today/this_week/this_month/this_year).', ['status' => 'string', 'period' => 'string']),
            self::tool('get_agent_commission', 'Get commission breakdown: earned, pending, paid, clawback',                                                                    []),
            self::tool('get_agent_renewals',   'Get policies expiring in the next 30 days sold by this agent',                                                                 []),
            self::tool('get_agent_customers',  'Get customer list with policy count for this agent',                                                                           []),
        ];
    }

    /**
     * Dynamically select the right tool set based on the incoming message.
     *
     * - BI / business questions  → 6 core tools (safe for any LLM context size)
     * - DevOps / code questions  → all 15 tools (extended set)
     *
     * Keeping the set small dramatically reduces the chance of Groq rejecting
     * the request due to schema complexity or token-limit issues.
     */
    public static function selectStaffTools(string $message): array
    {
        $devopsKeywords = [
            // Errors & bugs
            'error', 'bug', 'crash', 'exception', 'traceback',
            // Deployments
            'deploy', 'build', 'release', 'push to prod',
            // Infrastructure
            'server', 'ecs', 'fargate', 'container', 'task',
            // Logs
            'log', 'cloudwatch', 'failed job', 'queue',
            // Code / GitHub
            'code', 'fix', 'github', 'pr ', 'pull request',
            // Laravel ops
            'artisan', 'cache', 'health', 'down', 'broken',
            'migration', 'schedule',
            // Feature / implementation questions → needs search_code
            'implement', 'feature', 'function', 'where is', 'how does',
            'suspend', 'module', 'class ', 'method', 'api endpoint',
            'has been', 'have we', 'did we', 'is there', 'do we have',
            'check if', 'look at', 'find the', 'search for',
            'show me the code', 'what does',
        ];

        $lower = strtolower($message);
        foreach ($devopsKeywords as $kw) {
            if (str_contains($lower, $kw)) {
                return self::staffTools();   // full 15-tool set
            }
        }

        return self::coreStaffTools();       // lean 6-tool BI set
    }

    /**
     * Core BI tools — sent for all standard business questions.
     * Keeping this to 6 tools reduces Groq schema-validation failures.
     */
    public static function coreStaffTools(): array
    {
        return [
            [
                'name'         => 'execute_query',
                'description'  => 'Run a read-only SQL SELECT against the live database to answer ANY business question. '
                    . 'Use this when the other tools cannot answer the question. '
                    . 'Examples: loss ratio by product, claims pending for a broker, revenue by month, '
                    . 'customer acquisition trend, policy lapse rate, unpaid premiums, agent performance, '
                    . 'UW exposure by occupation, marketing channel conversion. '
                    . 'Always SELECT only what you need. Max 200 rows returned.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'sql'   => ['type' => 'string', 'description' => 'A valid MySQL SELECT statement'],
                        'label' => ['type' => 'string', 'description' => 'Short description of what this query returns'],
                    ],
                    'required' => ['sql'],
                ],
            ],
            [
                'name'        => 'generate_chart',
                'description' => 'Generate a chart image (bar, line, pie, doughnut) and return a public PNG URL '
                    . 'that will be sent as a WhatsApp image. '
                    . 'First call execute_query to get the data, then pass it here. '
                    . 'Keep labels short (≤15 chars). Max 12 data points per dataset.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'chart_type' => ['type' => 'string',  'description' => 'Chart type: bar, horizontalBar, line, pie, doughnut'],
                        'title'      => ['type' => 'string',  'description' => 'Chart title shown above the chart'],
                        'labels'     => ['type' => 'array',   'description' => 'X-axis labels or pie slice names', 'items' => ['type' => 'string']],
                        'datasets'   => [
                            'type'        => 'array',
                            'description' => 'Array of dataset objects. Each has label (string) and data (array of numbers).',
                            'items'       => [
                                'type'       => 'object',
                                'properties' => [
                                    'label' => ['type' => 'string'],
                                    'data'  => ['type' => 'array', 'items' => ['type' => 'number']],
                                ],
                            ],
                        ],
                        'caption'    => ['type' => 'string',  'description' => 'Short caption to send alongside the image'],
                    ],
                    'required' => ['chart_type', 'title', 'labels', 'datasets'],
                ],
            ],
            self::tool('get_policy_stats',
                'Policy counts and GWP for a period — new business, active, cancelled, by product. '
                . 'Use for: policies sold today/this week/this month, new business count, GWP by product.',
                ['period' => 'string — today / this_week / this_month / last_month / this_year / YYYY-MM-DD']
            ),
            self::tool('get_collection_today',
                'Total payment collections for a date broken down by product and payment method.',
                ['date' => 'string — YYYY-MM-DD, defaults to today']
            ),
            self::tool('get_agent_leaderboard',
                'Top agents by policies sold and premium for a period.',
                ['period' => 'string — today/this_week/this_month/this_year', 'limit' => 'integer — default 10']
            ),
            self::tool('get_premium_register',
                'Written, earned and unearned premium totals from the premium register.',
                ['product_id' => 'integer — optional filter']
            ),
        ];
    }

    /**
     * DevOps / code tools — added on top of coreStaffTools() when the message
     * contains keywords like error, deploy, bug, server, code, github, etc.
     */
    public static function devopsStaffTools(): array
    {
        return [
            self::tool('check_error_logs',
                'Read recent error or warning entries from the Laravel application log. '
                . 'Use when the user reports a bug, crash, or unexpected behaviour. '
                . 'Returns the most recent errors with file name and line number.',
                ['level' => 'string — ERROR (default) / WARNING / CRITICAL', 'limit' => 'integer — max entries to return, default 15']
            ),
            self::tool('check_failed_jobs',
                'List recent failed background queue jobs with their exception messages. '
                . 'Use when emails/SMS are not sending, payments are stuck, or reports are not generating.',
                ['limit' => 'integer — default 10']
            ),
            self::tool('run_artisan',
                'Run a safe Laravel Artisan command on the live server. '
                . 'Allowed: cache:clear, config:clear, route:clear, view:clear, queue:restart, '
                . 'migrate:status, schedule:list, queue:monitor. '
                . 'Use for: clearing stuck cache, restarting queue workers, checking migration status.',
                ['command' => 'string — artisan command name (without "php artisan")']
            ),
            self::tool('ecs_service_health',
                'Check ECS service health: running tasks, desired count, last deployment time and status.',
                ['service' => 'string — default graphite-backend']
            ),
            self::tool('trigger_deploy',
                'Trigger a new deployment via CodeBuild. Builds the Docker image and deploys to ECS. '
                . 'Always confirm with the user before calling this.',
                ['project' => 'string — CodeBuild project name, default graphite-backend-full-build']
            ),
            self::tool('read_cloudwatch_logs',
                'Fetch recent log lines from CloudWatch for the backend service. '
                . 'More complete than check_error_logs — shows all output including PHP notices.',
                ['lines' => 'integer — number of recent lines to fetch, default 50', 'filter' => 'string — optional keyword filter']
            ),
            self::tool('search_code',
                'Search the Graphite codebase on GitHub for a class, method, error string, or pattern. '
                . 'Returns file path, line snippet, and GitHub URL.',
                ['query' => 'string — search term, e.g. "PolicyCreateController" or "cancelledButCollecting"']
            ),
            self::tool('read_code_file',
                'Read the contents of a specific file from the GitHub repository. '
                . 'Returns up to 300 lines of the file.',
                ['path' => 'string — file path e.g. "backend/app/Http/Controllers/Api/V1/PolicyController.php"', 'start_line' => 'integer — optional, start reading from this line']
            ),
            self::tool('create_code_fix',
                'Create a GitHub Pull Request with an AI-generated code fix. '
                . 'Use only after confirming the fix with the user.',
                ['file_path' => 'string — path of file to fix', 'fixed_content' => 'string — complete new file content', 'description' => 'string — what was fixed and why', 'pr_title' => 'string — PR title']
            ),
        ];
    }

    /**
     * Staff / Exco tools — full set (core BI + DevOps).
     * execute_query is the primary power tool — it can answer ANY question.
     * The other tools are fast-path helpers for the most common lookups.
     */
    public static function staffTools(): array
    {
        return array_merge(self::coreStaffTools(), self::devopsStaffTools());
    }

    // Alias kept for any legacy callers
    public static function allStaffTools(): array { return self::staffTools(); }

    // Alias so existing callers still work
    public static function excoTools(): array { return self::staffTools(); }

    // =========================================================================
    //  generate_chart — QuickChart.io PNG, sent as WhatsApp image
    // =========================================================================

    private static function generateChart(array $input): array
    {
        // 'chart_type' is the schema field name (avoids collision with JSON Schema 'type' keyword)
        $type     = $input['chart_type'] ?? $input['type'] ?? 'bar';
        $title    = $input['title']      ?? '';
        $labels   = $input['labels']     ?? [];
        $datasets = $input['datasets']   ?? [];
        $caption  = $input['caption']    ?? $title;

        // Alpha Direct brand palette
        $palette = ['#F4A623', '#0D1B2A', '#5B9BD5', '#70AD47', '#FF5252', '#AB47BC', '#26C6DA', '#FF7043', '#66BB6A', '#42A5F5', '#EC407A', '#8D6E63'];

        // Colour each dataset and ensure data values are numeric (LLM sometimes passes strings)
        foreach ($datasets as $i => &$ds) {
            // Cast every data point to float — Chart.js silently ignores string values
            if (isset($ds['data']) && is_array($ds['data'])) {
                $ds['data'] = array_map('floatval', $ds['data']);
            }

            $colour = $palette[$i % count($palette)];
            if ($type === 'pie' || $type === 'doughnut') {
                $ds['backgroundColor'] = array_map(
                    fn($j) => $palette[$j % count($palette)],
                    array_keys($labels)
                );
            } else {
                $ds['backgroundColor'] = $colour;
                $ds['borderColor']     = $colour;
                $ds['borderWidth']     = 1;
            }
            if ($type === 'line') {
                $ds['fill']        = false;
                $ds['pointRadius'] = 3;
            }
        }
        unset($ds);

        $config = [
            'type' => $type,
            'data' => ['labels' => $labels, 'datasets' => $datasets],
            'options' => [
                'title'  => ['display' => true, 'text' => $title, 'fontSize' => 16],
                'legend' => ['display' => count($datasets) > 1],
                'scales' => in_array($type, ['pie', 'doughnut']) ? new \stdClass() : [
                    'yAxes' => [['ticks' => ['beginAtZero' => true]]],
                ],
                'plugins' => [
                    'datalabels' => [
                        'display' => count($labels) <= 8,
                        'anchor'  => 'end',
                        'align'   => 'top',
                    ],
                ],
            ],
        ];

        $url = 'https://quickchart.io/chart?'
            . 'c=' . urlencode(json_encode($config))
            . '&w=700&h=380&bkg=%23ffffff&devicePixelRatio=2';

        // QuickChart URLs can be long — use their short-URL API for anything >1800 chars
        if (strlen($url) > 1800) {
            try {
                $resp = \Illuminate\Support\Facades\Http::timeout(10)
                    ->post('https://quickchart.io/chart/create', ['chart' => $config, 'width' => 700, 'height' => 380, 'backgroundColor' => 'white']);
                if ($resp->successful() && !empty($resp->json('url'))) {
                    $url = $resp->json('url');
                }
            } catch (\Throwable $e) {
                // Fallback to long URL — fine for most charts
            }
        }

        return [
            'chart_url' => $url,
            'caption'   => $caption,
            'type'      => 'image',
        ];
    }

    // =========================================================================
    //  execute_query — the universal power tool
    // =========================================================================

    private static function executeQuery(array $input): array
    {
        $sql   = trim($input['sql']   ?? '');
        $label = trim($input['label'] ?? 'Query result');

        if (empty($sql)) {
            return ['error' => 'No SQL provided.'];
        }

        // Only SELECT is permitted
        if (!preg_match('/^\s*SELECT\b/i', $sql)) {
            return ['error' => 'Only SELECT statements are permitted.'];
        }

        // Block any write/DDL keywords even if buried inside the query
        $blocked = '/\b(INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|TRUNCATE|REPLACE|EXEC|EXECUTE|CALL|GRANT|REVOKE|LOAD\s+DATA)\b/i';
        if (preg_match($blocked, $sql)) {
            return ['error' => 'Query contains a disallowed keyword. Only read-only SELECT is permitted.'];
        }

        try {
            $rows = DB::select($sql);

            if (empty($rows)) {
                return ['label' => $label, 'count' => 0, 'rows' => [], 'note' => 'No rows matched.'];
            }

            // Cap at 200 rows to keep the AI context manageable
            $capped  = count($rows) > 200;
            $rows    = array_slice($rows, 0, 200);

            return [
                'label'   => $label,
                'count'   => count($rows),
                'capped'  => $capped,
                'rows'    => array_map(fn($r) => (array) $r, $rows),
            ];
        } catch (\Throwable $e) {
            return ['error' => 'Query failed: ' . $e->getMessage()];
        }
    }

    // =========================================================================
    //  Customer tool implementations
    // =========================================================================

    private static function getMyPolicies(array $ctx): array
    {
        $rows = DB::table('policies as p')
            ->leftJoin('products as pr', 'pr.id', '=', 'p.product_id')
            ->where('p.customer_id', $ctx['customer_id'] ?? 0)
            ->select('p.policyNumber', 'pr.name as product', 'p.premium', 'p.status', 'p.policyActivatedDate')
            ->orderByDesc('p.id')->limit(20)->get();

        $map = [1 => 'Active', 2 => 'Cancelled', 0 => 'Pending', 3 => 'Expired'];
        return ['policies' => $rows->map(fn($p) => [
            'policy_number' => $p->policyNumber,
            'product'       => $p->product,
            'premium'       => 'P ' . number_format((float) $p->premium, 2),
            'status'        => $map[$p->status] ?? $p->status,
            'activated'     => $p->policyActivatedDate,
        ])->toArray()];
    }

    private static function getMyClaims(array $ctx): array
    {
        $rows = DB::table('new_claims as nc')
            ->join('policies as p', 'p.id', '=', 'nc.policy_id')
            ->where('p.customer_id', $ctx['customer_id'] ?? 0)
            ->select('nc.claim_number', 'nc.claim_type', 'nc.status', 'nc.claim_date', 'p.policyNumber')
            ->orderByDesc('nc.id')->limit(10)->get();
        return ['claims' => $rows->toArray()];
    }

    private static function getMyPayments(array $ctx): array
    {
        $rows = DB::table('payment_transactions as pt')
            ->join('policies as p', 'p.id', '=', 'pt.policy_id')
            ->where('p.customer_id', $ctx['customer_id'] ?? 0)
            ->select('pt.amount', 'pt.payment_method', 'pt.status', 'pt.created_at', 'p.policyNumber')
            ->orderByDesc('pt.id')->limit(10)->get();
        return ['payments' => $rows->map(fn($p) => [
            'amount'  => 'P ' . number_format((float) $p->amount, 2),
            'method'  => $p->payment_method,
            'status'  => $p->status,
            'date'    => $p->created_at,
            'policy'  => $p->policyNumber,
        ])->toArray()];
    }

    private static function getMyKycStatus(array $ctx): array
    {
        $kyc = DB::table('customer_kyc')->where('customer_id', $ctx['customer_id'] ?? 0)->first();
        if (!$kyc) return ['kyc' => 'No KYC record found. Please upload your documents.'];

        $map  = [0 => 'Not Uploaded', 1 => 'Approved', 2 => 'Rejected'];
        $docs = [];
        foreach ([
            'omangFrontStatus'    => 'Omang Front',
            'omangBackStatus'     => 'Omang Back',
            'driving_licenseStatus' => 'Driving License',
            'passportStatus'      => 'Passport',
            'proof_residenceStatus' => 'Proof of Residence',
            'proof_incomeStatus'  => 'Proof of Income',
        ] as $col => $name) {
            $docs[] = ['document' => $name, 'status' => $map[$kyc->$col ?? 0] ?? 'Pending'];
        }

        return [
            'compliance' => $kyc->compliance == 1 ? 'Compliant' : ($kyc->compliance == 2 ? 'Non-Compliant' : 'Pending'),
            'documents'  => $docs,
        ];
    }

    private static function getMyBalance(array $ctx): array
    {
        $policy = DB::table('policies')
            ->where('customer_id', $ctx['customer_id'] ?? 0)
            ->where('status', 1)->orderByDesc('id')
            ->first(['id', 'policyNumber', 'premium', 'billingStartDate', 'product_id']);

        if (!$policy) return ['balance' => 'No active policy found.'];

        $last = DB::table('payment_transactions')
            ->where('policy_id', $policy->id)->where('status', 'Success')
            ->orderByDesc('id')->first(['amount', 'created_at']);

        // The outstanding balance quoted to a customer over WhatsApp must be the
        // same figure as the Ledger tab, the Balance Owing widget and the
        // Statement of Account PDF. This read the last ledger row's `balance`
        // column, which is stamped from debit/credit at write time and is not
        // reliable (see AccountStatementService::rowAmount()); it also ignored
        // soft-deleted rows entirely, so a discarded invoice or a reversed
        // receipt still shaped what the bot told the customer.
        $isDomCom = in_array(
            (int) ($policy->product_id ?? 0),
            \AlphaDirect\Services\AccountStatementService::DOMCOM_PRODUCT_IDS,
            true
        );
        $bal = \AlphaDirect\Services\AccountStatementService::closingBalance(
            (int) $policy->id,
            null,
            !$isDomCom
        );

        return [
            'policy_number'       => $policy->policyNumber,
            'monthly_premium'     => 'P ' . number_format((float) $policy->premium, 2),
            'outstanding_balance' => 'P ' . number_format((float) ($bal ?? 0), 2),
            'last_payment'        => $last
                ? 'P ' . number_format((float) $last->amount, 2) . ' on ' . $last->created_at
                : 'No payments found',
        ];
    }

    // =========================================================================
    //  Agent tool implementations
    // =========================================================================

    private static function getAgentPolicies(array $ctx, array $input): array
    {
        $q = DB::table('policies as p')
            ->leftJoin('customer as c', 'c.id', '=', 'p.customer_id')
            ->leftJoin('products as pr', 'pr.id', '=', 'p.product_id')
            ->where('p.agent_id', $ctx['user_id'] ?? 0);

        $status = $input['status'] ?? 'active';
        if ($status === 'active')    $q->where('p.status', 1);
        elseif ($status === 'cancelled') $q->where('p.status', 2);

        $period = $input['period'] ?? null;
        if ($period === 'today')      $q->whereDate('p.created_at', now()->toDateString());
        elseif ($period === 'this_week')  $q->where('p.created_at', '>=', now()->startOfWeek());
        elseif ($period === 'this_month') $q->where('p.created_at', '>=', now()->startOfMonth());
        elseif ($period === 'this_year')  $q->where('p.created_at', '>=', now()->startOfYear());

        return [
            'total_count'   => (clone $q)->count(),
            'total_premium' => 'P ' . number_format((float) (clone $q)->sum('p.premium'), 2),
            'policies'      => $q->select('p.policyNumber',
                                    DB::raw("CONCAT(c.firstName,' ',c.lastName) as customer"),
                                    'pr.name as product', 'p.premium', 'p.created_at')
                                 ->orderByDesc('p.id')->limit(20)->get()->toArray(),
        ];
    }

    private static function getAgentCommission(array $ctx): array
    {
        $id  = $ctx['user_id'] ?? 0;
        $fmt = fn($v) => 'P ' . number_format((float) $v, 2);
        return [
            'earned'   => $fmt(DB::table('commission_ledger')->where('agent_id', $id)->where('entry_type', 'earned')->sum('commission_amount')),
            'pending'  => $fmt(DB::table('commission_ledger')->where('agent_id', $id)->where('status', 'pending')->sum('commission_amount')),
            'paid'     => $fmt(DB::table('commission_ledger')->where('agent_id', $id)->where('status', 'paid')->sum('commission_amount')),
            'clawback' => $fmt(DB::table('commission_ledger')->where('agent_id', $id)->where('entry_type', 'clawback')->sum('commission_amount')),
        ];
    }

    private static function getAgentRenewals(array $ctx): array
    {
        $rows = DB::select("
            SELECT p.policyNumber, CONCAT(c.firstName,' ',c.lastName) as customer,
                   pa.effective_to as expiry_date, DATEDIFF(pa.effective_to, CURDATE()) as days_left
            FROM policies p
            JOIN policy_actions pa ON pa.id = (
                SELECT pa2.id FROM policy_actions pa2
                WHERE pa2.policy_id = p.id AND pa2.status = 'ISSUED'
                  AND pa2.deleted_at IS NULL ORDER BY pa2.id DESC LIMIT 1
            )
            LEFT JOIN customer c ON c.id = p.customer_id
            WHERE p.agent_id = ? AND p.status = 1
              AND pa.effective_to BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            ORDER BY pa.effective_to LIMIT 20
        ", [$ctx['user_id'] ?? 0]);

        return ['renewals_due' => count($rows), 'policies' => $rows];
    }

    private static function getAgentCustomers(array $ctx): array
    {
        $rows = DB::select("
            SELECT c.id, CONCAT(c.firstName,' ',c.lastName) as name, c.cellphone, c.email,
                   COUNT(p.id) as policy_count, SUM(p.premium) as total_premium
            FROM customer c
            JOIN policies p ON p.customer_id = c.id AND p.agent_id = ?
            GROUP BY c.id ORDER BY policy_count DESC LIMIT 20
        ", [$ctx['user_id'] ?? 0]);

        return ['customer_count' => count($rows), 'customers' => array_map(fn($c) => [
            'name'    => $c->name,
            'phone'   => $c->cellphone,
            'policies'=> $c->policy_count,
            'premium' => 'P ' . number_format((float) $c->total_premium, 2),
        ], $rows)];
    }

    // =========================================================================
    //  Staff / Exco helper tool implementations
    // =========================================================================

    private static function getPolicyStats(array $input): array
    {
        $period   = $input['period'] ?? 'today';
        $dateFrom = match (true) {
            $period === 'today'      => now()->toDateString(),
            $period === 'this_week'  => now()->startOfWeek()->toDateString(),
            $period === 'this_month' => now()->startOfMonth()->toDateString(),
            $period === 'last_month' => now()->subMonth()->startOfMonth()->toDateString(),
            $period === 'this_year'  => now()->startOfYear()->toDateString(),
            default                  => $period,
        };
        $dateTo = $period === 'last_month'
            ? now()->subMonth()->endOfMonth()->toDateString()
            : now()->toDateString();

        $rows  = DB::select("
            SELECT pr.name as product,
                   COUNT(p.id)                                      as policies_sold,
                   SUM(p.premium)                                   as total_premium,
                   SUM(CASE WHEN p.status = 1 THEN 1 ELSE 0 END)  as active,
                   SUM(CASE WHEN p.status = 2 THEN 1 ELSE 0 END)  as cancelled
            FROM policies p
            JOIN products pr ON pr.id = p.product_id
            WHERE DATE(p.created_at) BETWEEN ? AND ?
            GROUP BY pr.id, pr.name ORDER BY policies_sold DESC
        ", [$dateFrom, $dateTo]);

        return [
            'period'         => $period,
            'date_from'      => $dateFrom,
            'date_to'        => $dateTo,
            'total_policies' => array_sum(array_column($rows, 'policies_sold')),
            'total_premium'  => 'P ' . number_format(array_sum(array_column($rows, 'total_premium')), 2),
            'by_product'     => array_map(fn($r) => [
                'product'   => $r->product,
                'sold'      => $r->policies_sold,
                'active'    => $r->active,
                'cancelled' => $r->cancelled,
                'premium'   => 'P ' . number_format((float) $r->total_premium, 2),
            ], $rows),
        ];
    }

    private static function getCollectionToday(array $input): array
    {
        $date = $input['date'] ?? now()->toDateString();
        $rows = DB::select("
            SELECT pr.name as product, pt.payment_method, COUNT(*) as count, SUM(pt.amount) as total
            FROM payment_transactions pt
            JOIN policies p ON p.id = pt.policy_id
            JOIN products pr ON pr.id = p.product_id
            WHERE DATE(pt.created_at) = ? AND pt.status = 'Success'
            GROUP BY pr.name, pt.payment_method ORDER BY total DESC
        ", [$date]);

        return [
            'date'        => $date,
            'grand_total' => 'P ' . number_format(array_sum(array_column($rows, 'total')), 2),
            'breakdown'   => array_map(fn($r) => [
                'product' => $r->product, 'method' => $r->payment_method,
                'count'   => $r->count,   'total'  => 'P ' . number_format((float) $r->total, 2),
            ], $rows),
        ];
    }

    private static function getAgentLeaderboard(array $input): array
    {
        $limit  = (int) ($input['limit'] ?? 10);
        $period = $input['period'] ?? 'this_month';
        $from   = match ($period) {
            'today'      => now()->toDateString(),
            'this_week'  => now()->startOfWeek()->toDateString(),
            'this_month' => now()->startOfMonth()->toDateString(),
            'this_year'  => now()->startOfYear()->toDateString(),
            default      => now()->startOfMonth()->toDateString(),
        };

        $rows = DB::select("
            SELECT CONCAT(u.firstName,' ',u.lastName) as name, a.name as agency,
                   COUNT(p.id) as policies_sold, SUM(p.premium) as total_premium
            FROM users u
            LEFT JOIN agencies a ON a.id = u.agency_id
            JOIN policies p ON p.agent_id = u.id AND DATE(p.created_at) >= ? AND p.status = 1
            WHERE u.agency_id IS NOT NULL
            GROUP BY u.id ORDER BY total_premium DESC LIMIT ?
        ", [$from, $limit]);

        return ['period' => $period, 'agents' => array_map(fn($a) => [
            'name'     => $a->name,
            'agency'   => $a->agency,
            'policies' => $a->policies_sold,
            'premium'  => 'P ' . number_format((float) $a->total_premium, 2),
        ], $rows)];
    }

    private static function getPremiumRegister(array $input): array
    {
        $q = DB::table('premium_register')->where('posting_date', now()->toDateString());
        if (!empty($input['product_id'])) $q->where('product_id', $input['product_id']);

        $t   = $q->selectRaw("COUNT(DISTINCT policy_id) as policies, COALESCE(SUM(written_premium),0) as written, COALESCE(SUM(earned_premium),0) as earned, COALESCE(SUM(unearned_premium),0) as unearned")->first();
        $fmt = fn($v) => 'P ' . number_format((float) $v, 2);

        return [
            'date'              => now()->toDateString(),
            'policies'          => $t->policies ?? 0,
            'written_premium'   => $fmt($t->written   ?? 0),
            'earned_premium'    => $fmt($t->earned    ?? 0),
            'unearned_premium'  => $fmt($t->unearned  ?? 0),
        ];
    }

    // =========================================================================
    //  DevOps / Agent tool implementations
    // =========================================================================

    /** Read recent error entries from storage/logs/laravel.log */
    private static function checkErrorLogs(array $input): array
    {
        $limit   = (int) ($input['limit'] ?? 15);
        $level   = strtoupper($input['level'] ?? 'ERROR');
        $logPath = storage_path('logs/laravel.log');

        if (!file_exists($logPath)) {
            return ['entries' => [], 'note' => 'Log file not found'];
        }

        // Read last 300 KB to avoid loading huge log into memory
        $fileSize    = filesize($logPath);
        $bytesToRead = min($fileSize, 300000);
        $fh          = fopen($logPath, 'r');
        fseek($fh, max(0, $fileSize - $bytesToRead));
        $content = fread($fh, $bytesToRead);
        fclose($fh);

        // Each log entry starts with [YYYY-MM-DD
        $raw     = preg_split('/(?=\[\d{4}-\d{2}-\d{2})/', $content);
        $raw     = array_filter($raw, fn($e) => stripos($e, ".{$level}:") !== false || stripos($e, " {$level}:") !== false);
        $entries = array_slice(array_values(array_reverse($raw)), 0, $limit);

        $parsed = array_map(function ($e) {
            $e = trim($e);
            // Extract timestamp, channel.LEVEL, message
            preg_match('/^\[([^\]]+)\]\s+(\S+)\s+(.+?)(?:\s*\{|$)/s', $e, $m);
            return [
                'time'    => $m[1] ?? '',
                'level'   => $m[2] ?? '',
                'message' => trim(substr($m[3] ?? $e, 0, 400)),
                'raw'     => substr($e, 0, 600),
            ];
        }, $entries);

        return ['count' => count($parsed), 'level' => $level, 'entries' => $parsed];
    }

    /** List recent failed queue jobs */
    private static function checkFailedJobs(array $input): array
    {
        $limit = (int) ($input['limit'] ?? 10);

        if (!\Illuminate\Support\Facades\Schema::hasTable('failed_jobs')) {
            return ['total' => 0, 'jobs' => [], 'note' => 'failed_jobs table not found'];
        }

        $total = DB::table('failed_jobs')->count();
        $jobs  = DB::table('failed_jobs')->orderByDesc('failed_at')->limit($limit)->get();

        return [
            'total_failed' => $total,
            'jobs' => $jobs->map(function ($j) {
                $payload = json_decode($j->payload, true);
                return [
                    'id'         => $j->id,
                    'job'        => $payload['displayName'] ?? 'Unknown',
                    'queue'      => $j->queue,
                    'failed_at'  => $j->failed_at,
                    'error'      => substr($j->exception, 0, 500),
                ];
            })->toArray(),
        ];
    }

    /** Run a whitelisted Artisan command using Artisan::call() — no exec() needed */
    private static function runArtisan(array $input): array
    {
        $command = trim($input['command'] ?? '');

        $whitelist = [
            'cache:clear', 'config:clear', 'route:clear', 'view:clear',
            'queue:restart', 'schedule:list', 'migrate:status',
            'storage:link', 'about', 'queue:monitor',
        ];

        if (!in_array($command, $whitelist)) {
            return [
                'error'   => "Command not in whitelist.",
                'allowed' => $whitelist,
            ];
        }

        try {
            \Illuminate\Support\Facades\Artisan::call($command, ['--no-interaction' => true]);
            $output = \Illuminate\Support\Facades\Artisan::output();
            return ['command' => $command, 'success' => true, 'output' => trim($output)];
        } catch (\Throwable $e) {
            return ['command' => $command, 'success' => false, 'error' => $e->getMessage()];
        }
    }

    /** Check ECS service health via AWS SDK */
    private static function ecsServiceHealth(array $input): array
    {
        $service = $input['service'] ?? 'graphite-backend';
        $cluster = 'graphite-cluster';
        $region  = env('AWS_DEFAULT_REGION', 'af-south-1');

        try {
            $ecs = new \Aws\Ecs\EcsClient([
                'region'  => $region,
                'version' => 'latest',
            ]);

            $result = $ecs->describeServices([
                'cluster'  => $cluster,
                'services' => [$service],
            ]);

            $svc    = $result['services'][0] ?? [];
            $deploy = $svc['deployments'][0] ?? [];

            return [
                'service'       => $service,
                'status'        => $svc['status'] ?? 'UNKNOWN',
                'running_tasks' => $svc['runningCount'] ?? 0,
                'desired_tasks' => $svc['desiredCount'] ?? 0,
                'pending_tasks' => $svc['pendingCount'] ?? 0,
                'deployment'    => [
                    'status'     => $deploy['status'] ?? 'UNKNOWN',
                    'image'      => substr($deploy['taskDefinition'] ?? '', -20),
                    'updated_at' => isset($deploy['updatedAt']) ? (string) $deploy['updatedAt'] : null,
                ],
                'healthy'       => ($svc['runningCount'] ?? 0) === ($svc['desiredCount'] ?? 1),
            ];
        } catch (\Throwable $e) {
            return ['error' => 'ECS describe failed: ' . $e->getMessage()];
        }
    }

    /** Trigger a CodeBuild deployment */
    private static function triggerDeploy(array $input): array
    {
        $project = $input['project'] ?? 'graphite-backend-full-build';
        $region  = env('AWS_DEFAULT_REGION', 'af-south-1');

        try {
            $cb     = new \Aws\CodeBuild\CodeBuildClient(['region' => $region, 'version' => 'latest']);
            $result = $cb->startBuild(['projectName' => $project]);
            $build  = $result['build'];

            return [
                'success'    => true,
                'build_id'   => $build['id'],
                'status'     => $build['buildStatus'],
                'started_at' => (string) $build['startTime'],
                'note'       => 'Build started. ECS will update in ~5-8 minutes.',
            ];
        } catch (\Throwable $e) {
            return ['error' => 'CodeBuild trigger failed: ' . $e->getMessage()];
        }
    }

    /** Fetch recent CloudWatch log lines for the backend service */
    private static function readCloudwatchLogs(array $input): array
    {
        $lines  = (int) ($input['lines'] ?? 50);
        $filter = $input['filter'] ?? null;
        $region = env('AWS_DEFAULT_REGION', 'af-south-1');

        try {
            $cw     = new \Aws\CloudWatchLogs\CloudWatchLogsClient(['region' => $region, 'version' => 'latest']);
            $params = [
                'logGroupName'  => '/ecs/graphite-backend',
                'startTime'     => (int) ((time() - 3600) * 1000), // last hour
                'endTime'       => (int) (time() * 1000),
                'limit'         => min($lines, 100),
                'interleaved'   => true,
            ];

            if ($filter) $params['filterPattern'] = $filter;

            $result = $cw->filterLogEvents($params);
            $events = $result['events'] ?? [];

            return [
                'count'   => count($events),
                'filter'  => $filter,
                'entries' => array_map(fn($e) => [
                    'time'    => date('Y-m-d H:i:s', intdiv($e['timestamp'], 1000)),
                    'message' => substr($e['message'], 0, 400),
                ], $events),
            ];
        } catch (\Throwable $e) {
            return ['error' => 'CloudWatch read failed: ' . $e->getMessage()];
        }
    }

    /** Search the GitHub codebase */
    private static function searchCode(array $input): array
    {
        $query = trim($input['query'] ?? '');
        $token = env('GITHUB_TOKEN', '');
        $repo  = env('GITHUB_REPO', 'alphadirectinsurance/Graphitev2');

        if (empty($token)) return ['error' => 'GITHUB_TOKEN not configured in environment.'];
        if (empty($query)) return ['error' => 'query is required'];

        $url = 'https://api.github.com/search/code?q=' . urlencode("{$query} repo:{$repo}") . '&per_page=8';
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$token}", 'Accept: application/vnd.github.v3+json', 'User-Agent: GraphiteAI/1.0'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $resp = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (isset($resp['message'])) return ['error' => $resp['message']];

        return [
            'total'   => $resp['total_count'] ?? 0,
            'results' => array_map(fn($i) => [
                'file'    => $i['path'],
                'url'     => $i['html_url'],
                'matches' => array_map(fn($m) => trim($m['fragment'] ?? ''), array_slice($i['text_matches'] ?? [], 0, 2)),
            ], $resp['items'] ?? []),
        ];
    }

    /** Read a file from GitHub */
    private static function readCodeFile(array $input): array
    {
        $path      = trim($input['path'] ?? '');
        $startLine = (int) ($input['start_line'] ?? 1);
        $token     = env('GITHUB_TOKEN', '');
        $repo      = env('GITHUB_REPO', 'alphadirectinsurance/Graphitev2');

        if (empty($token)) return ['error' => 'GITHUB_TOKEN not configured.'];
        if (empty($path))  return ['error' => 'path is required'];

        $url = "https://api.github.com/repos/{$repo}/contents/{$path}?ref=main";
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$token}", 'Accept: application/vnd.github.v3+json', 'User-Agent: GraphiteAI/1.0'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $resp = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (isset($resp['message'])) return ['error' => $resp['message']];
        if (!isset($resp['content'])) return ['error' => 'File not found or is a directory'];

        $fullContent = base64_decode($resp['content']);
        $allLines    = explode("\n", $fullContent);
        $totalLines  = count($allLines);

        // Return up to 300 lines from start_line
        $slice = array_slice($allLines, max(0, $startLine - 1), 300);
        $numbered = [];
        foreach ($slice as $i => $line) {
            $numbered[] = ($startLine + $i) . ': ' . $line;
        }

        return [
            'path'        => $path,
            'total_lines' => $totalLines,
            'showing'     => "lines {$startLine}–" . min($totalLines, $startLine + 299),
            'sha'         => $resp['sha'],
            'content'     => implode("\n", $numbered),
        ];
    }

    /** Create a GitHub PR with a code fix */
    private static function createCodeFix(array $input): array
    {
        $filePath     = trim($input['file_path']     ?? '');
        $fixedContent = $input['fixed_content']      ?? '';
        $description  = $input['description']        ?? 'AI-generated fix via WhatsApp';
        $prTitle      = $input['pr_title']           ?? $description;
        $token        = env('GITHUB_TOKEN',           '');
        $repo         = env('GITHUB_REPO',            'alphadirectinsurance/Graphitev2');

        if (empty($token))       return ['error' => 'GITHUB_TOKEN not configured.'];
        if (empty($filePath))    return ['error' => 'file_path is required'];
        if (empty($fixedContent))return ['error' => 'fixed_content is required'];

        $headers = [
            "Authorization: Bearer {$token}",
            'Accept: application/vnd.github.v3+json',
            'Content-Type: application/json',
            'User-Agent: GraphiteAI/1.0',
        ];

        $branch = 'ai-fix-' . date('YmdHis');

        // 1. Get current main SHA
        $ch = curl_init("https://api.github.com/repos/{$repo}/git/ref/heads/main");
        curl_setopt_array($ch, [CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true]);
        $mainRef = json_decode(curl_exec($ch), true);
        curl_close($ch);
        $mainSha = $mainRef['object']['sha'] ?? null;
        if (!$mainSha) return ['error' => 'Could not get main branch SHA'];

        // 2. Create branch
        $ch = curl_init("https://api.github.com/repos/{$repo}/git/refs");
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['ref' => "refs/heads/{$branch}", 'sha' => $mainSha]),
            CURLOPT_RETURNTRANSFER => true,
        ]);
        curl_exec($ch);
        curl_close($ch);

        // 3. Get current file SHA
        $ch = curl_init("https://api.github.com/repos/{$repo}/contents/{$filePath}?ref=main");
        curl_setopt_array($ch, [CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true]);
        $existing = json_decode(curl_exec($ch), true);
        curl_close($ch);
        $fileSha = $existing['sha'] ?? null;

        // 4. Commit the fix
        $ch = curl_init("https://api.github.com/repos/{$repo}/contents/{$filePath}");
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => json_encode([
                'message' => "[AI Fix] {$description}",
                'content' => base64_encode($fixedContent),
                'branch'  => $branch,
                'sha'     => $fileSha,
            ]),
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $commitResult = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (isset($commitResult['message']) && !isset($commitResult['content'])) {
            return ['error' => 'Commit failed: ' . $commitResult['message']];
        }

        // 5. Create PR
        $ch = curl_init("https://api.github.com/repos/{$repo}/pulls");
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'title' => "[AI Fix] {$prTitle}",
                'head'  => $branch,
                'base'  => 'main',
                'body'  => "## AI-Generated Fix\n\n{$description}\n\n_Generated via WhatsApp AI Agent_",
            ]),
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $pr = json_decode(curl_exec($ch), true);
        curl_close($ch);

        return [
            'success'    => isset($pr['html_url']),
            'pr_url'     => $pr['html_url']     ?? null,
            'pr_number'  => $pr['number']       ?? null,
            'branch'     => $branch,
            'note'       => 'PR created. Merge it on GitHub to auto-deploy to production.',
        ];
    }

    // =========================================================================
    //  Helpers
    // =========================================================================

    /** Build a simple tool definition with optional string/int properties. */
    private static function tool(string $name, string $description, array $props): array
    {
        $properties = [];
        foreach ($props as $pname => $pdesc) {
            [$type, $desc] = str_contains($pdesc, ' — ') ? explode(' — ', $pdesc, 2) : ['string', $pdesc];
            $properties[$pname] = ['type' => trim($type), 'description' => trim($desc)];
        }

        return [
            'name'         => $name,
            'description'  => $description,
            'input_schema' => [
                'type'       => 'object',
                'properties' => empty($properties) ? new \stdClass() : $properties,
                'required'   => [],
            ],
        ];
    }
}
