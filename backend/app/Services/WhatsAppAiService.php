<?php

namespace AlphaDirect\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use AlphaDirect\Http\Controllers\Api\V1\AiAssistantService;

/**
 * WhatsApp AI Bot — multi-persona routing service.
 *
 * Phone → persona detection (Exco / Staff / Agent / Customer)
 * → persona-specific system prompt + tools → AI → WhatsApp reply.
 *
 * Staff / Exco get execute_query so they can answer ANY business question
 * by writing SQL.  Agents and customers are scoped to their own data only.
 */
class WhatsAppAiService
{
    private const CONVERSATION_TTL_MINUTES = 30;

    // =========================================================================
    //  Entry point
    // =========================================================================

    public static function handleIncoming(string $phone, string $message, ?string $mediaUrl = null): string
    {
        try {
            $persona = self::getPersona($phone);
            if (!$persona) {
                return "Your number ({$phone}) is not registered with Alpha Direct Insurance. "
                    . "Please contact us at +267 3702740.";
            }

            // Customer claim shortcut
            if ($persona['type'] === 'customer' && self::isClaimTrigger($message)) {
                return "To file a claim please visit https://graphite.alphadirect.co.bw "
                    . "or call our claims department. A reference number will be issued on submission.";
            }

            // Dynamic tool selection for staff/exco — keeps payload small for BI queries,
            // expands to DevOps tools only when the message needs them.
            if (in_array($persona['type'], ['exco', 'staff'])) {
                $persona['tools'] = WhatsAppAiTools::selectStaffTools($message);
            }

            $conv     = self::getOrCreateConversation($phone, $persona);
            $response = self::callAi($persona, $conv, $message);

            // Update TTL (safe to skip if table missing)
            try {
                if (!empty($conv['id'])) {
                    DB::table('whatsapp_conversations')
                        ->where('id', $conv['id'])
                        ->update(['expires_at' => now()->addMinutes(self::CONVERSATION_TTL_MINUTES)]);
                }
            } catch (\Throwable $e) { /* non-fatal */ }

            return $response;

        } catch (\Throwable $e) {
            // Log full error — visible in CloudWatch under /ecs/graphite-backend
            $errClass = get_class($e);
            $errMsg   = $e->getMessage();
            Log::error("WhatsAppAiService [{$errClass}]: {$errMsg}", [
                'phone' => $phone,
                'trace' => substr($e->getTraceAsString(), 0, 1200),
            ]);

            // Write to whats_app_log so error is visible in the DB without CloudWatch
            try {
                DB::table('whats_app_log')->insert([
                    'WA_cellphone'  => $phone,
                    'method'        => 'ai_error',
                    'template_type' => 'error',
                    'input'         => json_encode(['message' => substr($message, 0, 200)]),
                    'output'        => json_encode([
                        'error_class'   => $errClass,
                        'error_message' => $errMsg,
                        'file'          => basename($e->getFile()) . ':' . $e->getLine(),
                    ]),
                    'status'        => 'error',
                    'start_time'    => now(),
                    'end_time'      => now(),
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            } catch (\Throwable $ignored) {}

            return "Sorry, I hit an error processing your request. Please try again.";
        }
    }

    // =========================================================================
    //  Persona detection
    //
    //  Priority: Exco → Staff (any other system user with a role) → Agent → Customer
    // =========================================================================

    public static function getPersona(string $phone): ?array
    {
        // Match on last 9 digits — handles any country code (+267 BW, +91 IN, etc.)
        $last9     = substr(preg_replace('/\D/', '', $phone), -9);
        $phoneLike = "%{$last9}";

        // ── 1. Exco / Senior management ──────────────────────────────────────
        $excoRoles = [
            'admin', 'Super Admin', 'CEO', 'CFO', 'COO', 'Managing Director',
            'exco', 'Sales Head', 'Finance Manager', 'Operations Manager',
        ];

        $excoUser = DB::table('users as u')
            ->join('model_has_roles as mr', fn($j) => $j->on('mr.model_id', '=', 'u.id')
                ->where('mr.model_type', 'AlphaDirect\\User'))
            ->join('roles as r', 'r.id', '=', 'mr.role_id')
            ->join('user_profile as up', 'up.user_id', '=', 'u.id')
            ->whereIn('r.name', $excoRoles)
            ->where('up.cellphone', 'like', $phoneLike)
            ->select('u.id', 'u.firstName', 'u.lastName', 'u.email', 'r.name as role_name',
                     'up.whatsapp_access', 'up.whatsapp_visibility')
            ->first();

        if ($excoUser) {
            // Explicit block: whatsapp_access = 'NO' prevents any WhatsApp AI access.
            // NULL or 'YES' means allowed (NULL = legacy / not yet set).
            $waAccess = $excoUser->whatsapp_access ?? null;
            if ($waAccess === 'NO') return null;

            return [
                'type'       => 'exco',
                'user_id'    => $excoUser->id,
                'name'       => trim("{$excoUser->firstName} {$excoUser->lastName}"),
                'role'       => $excoUser->role_name,
                'phone'      => $phone,
                'visibility' => $excoUser->whatsapp_visibility ?? 'Admin',
                'prompt'     => self::staffPrompt($excoUser),
                'tools'      => WhatsAppAiTools::coreStaffTools(), // overridden per-message in handleIncoming
            ];
        }

        // ── 2. Agent (has agency_id, not in exco list) ───────────────────────
        // NOTE: Full BI access is granted only via the exco roles list above.
        // To give a staff member (marketing, UW, finance) full access,
        // assign them one of the exco roles in Graphite Admin → Users.
        $agent = DB::table('users as u')
            ->join('user_profile as up', 'up.user_id', '=', 'u.id')
            ->whereNotNull('u.agency_id')
            ->where('up.cellphone', 'like', $phoneLike)
            ->select('u.id', 'u.firstName', 'u.lastName', 'u.agency_id', 'u.email',
                     'up.whatsapp_access', 'up.whatsapp_visibility')
            ->first();

        if ($agent) {
            $waAccess = $agent->whatsapp_access ?? null;
            if ($waAccess === 'NO') return null;

            $agencyName = DB::table('agencies')->where('id', $agent->agency_id)->value('name');
            return [
                'type'       => 'agent',
                'user_id'    => $agent->id,
                'name'       => trim("{$agent->firstName} {$agent->lastName}"),
                'agency'     => $agencyName,
                'phone'      => $phone,
                'visibility' => $agent->whatsapp_visibility ?? 'Self',
                'prompt'     => self::agentPrompt($agent, $agencyName),
                'tools'      => WhatsAppAiTools::agentTools(),
            ];
        }

        // ── 3. Registered system user with whatsapp_access = YES but no exco role / agency
        //     (e.g. back-office, underwriting, finance staff)
        //     They get a scoped BI view based on their whatsapp_visibility setting.
        $staffUser = DB::table('users as u')
            ->join('user_profile as up', 'up.user_id', '=', 'u.id')
            ->whereNull('u.agency_id')                      // not an agent
            ->where('up.whatsapp_access', 'YES')            // must explicitly opt-in
            ->where('up.cellphone', 'like', $phoneLike)
            ->select('u.id', 'u.firstName', 'u.lastName', 'u.email',
                     'up.whatsapp_access', 'up.whatsapp_visibility')
            ->first();

        if ($staffUser) {
            $visibility = $staffUser->whatsapp_visibility ?? 'Full';

            // Visibility = 'Admin' → full BI + DevOps (handled dynamically in handleIncoming)
            // Visibility = 'Full'  → full BI (same as exco minus DevOps)
            // Visibility = 'Self'  → agent-like view of their own data
            $fakeExcoObj = (object) [
                'id'         => $staffUser->id,
                'firstName'  => $staffUser->firstName,
                'lastName'   => $staffUser->lastName,
                'role_name'  => 'Staff (' . $visibility . ')',
            ];

            return [
                'type'       => in_array($visibility, ['Admin', 'Full']) ? 'exco' : 'agent',
                'user_id'    => $staffUser->id,
                'name'       => trim("{$staffUser->firstName} {$staffUser->lastName}"),
                'role'       => 'Staff',
                'phone'      => $phone,
                'visibility' => $visibility,
                'prompt'     => self::staffPrompt($fakeExcoObj),
                'tools'      => WhatsAppAiTools::coreStaffTools(),
            ];
        }

        // ── 4. Customer ───────────────────────────────────────────────────────
        $customer = DB::table('customer')
            ->where('cellphone', 'like', $phoneLike)
            ->first(['id', 'firstName', 'lastName', 'email', 'cellphone']);

        if ($customer) {
            return [
                'type'        => 'customer',
                'user_id'     => 0,
                'customer_id' => $customer->id,
                'name'        => trim("{$customer->firstName} {$customer->lastName}"),
                'phone'       => $phone,
                'prompt'      => self::customerPrompt($customer),
                'tools'       => WhatsAppAiTools::customerTools(),
            ];
        }

        return null;
    }

    // =========================================================================
    //  Conversation state
    // =========================================================================

    private static function getOrCreateConversation(string $phone, array $persona): array
    {
        // Create the ai_conversation first — this table always exists
        $aiConvId = DB::table('ai_conversations')->insertGetId([
            'user_id'    => $persona['user_id'] ?: 0,
            'title'      => "WhatsApp: {$persona['name']} ({$persona['type']})",
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Try to resume an existing active session from whatsapp_conversations
        // (table may not yet exist on a fresh deploy — fall back gracefully)
        $convTableId = null;
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('whatsapp_conversations')) {
                $existing = DB::table('whatsapp_conversations')
                    ->where('phone', $phone)
                    ->where('flow', 'ai_' . $persona['type'])
                    ->where('expires_at', '>', now())
                    ->orderByDesc('id')
                    ->first();

                if ($existing) {
                    // Resume: reuse the existing ai_conversation instead of the new one
                    $existingAiConvId = json_decode($existing->data ?? '{}', true)['ai_conversation_id'] ?? null;
                    if ($existingAiConvId) {
                        // Delete the just-created placeholder conversation
                        DB::table('ai_conversations')->where('id', $aiConvId)->delete();
                        return [
                            'id'                 => $existing->id,
                            'ai_conversation_id' => $existingAiConvId,
                        ];
                    }
                }

                $convTableId = DB::table('whatsapp_conversations')->insertGetId([
                    'phone'       => $phone,
                    'flow'        => 'ai_' . $persona['type'],
                    'step'        => 'active',
                    'data'        => json_encode([
                        'persona_type'       => $persona['type'],
                        'user_id'            => $persona['user_id'],
                        'customer_id'        => $persona['customer_id'] ?? null,
                        'ai_conversation_id' => $aiConvId,
                        'name'               => $persona['name'],
                    ]),
                    'customer_id' => $persona['customer_id'] ?? null,
                    'expires_at'  => now()->addMinutes(self::CONVERSATION_TTL_MINUTES),
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
        } catch (\Throwable $e) {
            // whatsapp_conversations table missing or schema mismatch — non-fatal,
            // continue without session persistence (stateless single-turn mode)
            Log::warning('getOrCreateConversation table error: ' . $e->getMessage());
        }

        return ['id' => $convTableId, 'ai_conversation_id' => $aiConvId];
    }

    // =========================================================================
    //  AI call
    // =========================================================================

    private static function callAi(array $persona, array $conv, string $userMessage): string
    {
        $aiConvId = $conv['ai_conversation_id'];

        $toolContext = [
            'user_id'     => $persona['user_id'],
            'customer_id' => $persona['customer_id'] ?? null,
            'phone'       => $persona['phone'],
        ];

        // Persist user message only.
        // ai_messages.role is ENUM('user','assistant') — 'system' is never stored.
        DB::table('ai_messages')->insert([
            'conversation_id' => $aiConvId,
            'role'            => 'user',
            'content'         => $userMessage,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // System prompt prepended in memory; DB history contains only user/assistant turns.
        $dbHistory = DB::table('ai_messages')
            ->where('conversation_id', $aiConvId)
            ->orderBy('id')
            ->limit(20)
            ->get(['role', 'content'])
            ->map(fn($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        $history = array_merge(
            [['role' => 'system', 'content' => $persona['prompt']]],
            $dbHistory
        );

        // Wrap tool executor to capture any chart URLs returned by generate_chart.
        // This makes chart delivery robust even if the AI forgets to include the URL.
        $capturedChartUrls = [];
        $toolExecutor = function (string $toolName, array $input) use ($toolContext, &$capturedChartUrls) {
            $result = WhatsAppAiTools::execute($toolName, $input, $toolContext);
            if (!empty($result['chart_url'])) {
                $capturedChartUrls[] = $result['chart_url'];
            }
            return $result;
        };

        $ai     = new AiAssistantService();
        $result = $ai->chatWithCustomTools($history, $persona['tools'], $toolExecutor);

        $reply = $result['content'] ?? 'I could not process your request. Please try again.';

        // Append any chart URLs that the AI didn't include in its response text.
        // The WhatsAppAiController::sendReply() will extract and deliver them as images.
        foreach ($capturedChartUrls as $chartUrl) {
            if (!str_contains($reply, $chartUrl)) {
                $reply .= "\n" . $chartUrl;
            }
        }

        DB::table('ai_messages')->insert([
            'conversation_id' => $aiConvId,
            'role'            => 'assistant',
            'content'         => $reply,
            'tokens_used'     => $result['tokens_used'] ?? 0,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return $reply;
    }

    // =========================================================================
    //  System prompts
    // =========================================================================

    /**
     * Staff / Exco prompt — full DB schema reference so the AI can write SQL
     * for any question without saying "I don't have that data".
     */
    private static function staffPrompt(object $user): string
    {
        $today = now()->toDateString();
        $month = now()->format('F Y');

        return <<<PROMPT
You are the Alpha Direct Insurance internal AI assistant on WhatsApp.
You are talking to: {$user->firstName} {$user->lastName} (Role: {$user->role_name})
Today: {$today}  |  Current month: {$month}

## Your job
You are a full AI agent. You can answer business questions, debug bugs, fix code, deploy to production,
and monitor the live server — all via WhatsApp.
NEVER say "I don't have access to that" or "I can't do that".

## Capabilities
1. **Business Intelligence** — execute_query for any data question, generate_chart for visuals
2. **Bug Diagnosis** — check_error_logs → search_code → read_code_file → explain the bug
3. **Code Fixes** — after diagnosing, use create_code_fix to open a GitHub PR automatically
4. **Deployments** — trigger_deploy starts a build; ecs_service_health shows server status
5. **Server Ops** — run_artisan to clear cache/restart queues; check_failed_jobs for stuck jobs
6. **Log Analysis** — check_error_logs for app errors; read_cloudwatch_logs for full output

## Rules
- Always use a tool to fetch real data. Never estimate or make up figures.
- Currency is Botswana Pula (P). Format: P X,XXX.XX
- This is WhatsApp — be concise. Use bullet points.
- For charts: call execute_query first, then generate_chart. Include the chart_url in your response.
- For bugs: always check_error_logs first, then search_code + read_code_file before suggesting a fix.
- For deployments: always confirm "Shall I trigger a deploy?" before calling trigger_deploy.
- For code fixes: show the user what you intend to change before calling create_code_fix.
- Compare vs previous period when it adds insight.

## Database schema reference

### policies
- id, policyNumber, customer_id, agent_id, product_id, premium (monthly)
- status: 0=Pending, 1=Active, 2=Cancelled, 3=Expired
- is_draft: 1=Draft (not yet submitted)
- policyActivatedDate, billingStartDate, cover_start, cover_end, created_at, updated_at

### products
- id, name
- 1=Accidental Death, 2=Third Party Car, 3=Motor Comprehensive, 4=Legal
- 5=Mobile Device, 6=Tyre and Rim, 7=Commercial, 8=Domestic
- 9=Hospital Cashback, 10=Health In A Box, 12=Grouped AD
- 16=Commercial Engineering, 17=Commercial Specialist
- 18=Domestic Engineering, 19=Domestic Specialist

### customer
- id, firstName, lastName, email, cellphone, omang, dob, gender, created_at

### users  (staff + agents)
- id, firstName, lastName, email, agency_id (NULL for staff, set for agents), created_at

### agencies
- id, name

### user_profile
- id, user_id, cellphone, address, city, country

### roles / model_has_roles / model_has_permissions
- roles: id, name
- model_has_roles: model_id (=user id), model_type='AlphaDirect\User', role_id

### payment_transactions
- id, policy_id, amount, payment_method, status, created_at
- payment_method: realpay, dpo, orange_money, vcs, ngenius, flutterwave
- status: 'Success', 'Failed', 'Pending'
- is_reverse: 1 = refund/reversal

### new_claims  (claims table)
- id, policy_id, claim_number, claim_type, status, claim_date, created_at
- status: Pending, Approved, Rejected, Closed, Reopen
- "open claims" = Pending + Reopen

### policy_actions
- id, policy_id, transaction_type, status, effective_from, effective_to, created_at
- transaction_type: 'New Business', 'Renewal', 'Endorsement', 'Cancellation'
- status: QUOTE, IN_APPROVAL, APPROVED, ISSUED

### policy_ledger
- id, policy_id, debit, credit, balance, description, created_at

### premium_register
- id, policy_id, product_id, posting_date, written_premium, earned_premium, unearned_premium

### commission_ledger
- id, agent_id, policy_id, commission_amount, entry_type, status, created_at
- entry_type: 'earned', 'clawback'
- status: 'pending', 'paid'

### policy_coverages + policy_coverage_detail
- policy_coverages: id, policy_id, coverage_id
- policy_coverage_detail: id, policy_coverage_id, sum_insured, rate, premium

### risk_address  (DOM/COM properties)
- id, policy_id, address, city, property_type, construction, ...

### customer_kyc
- id, customer_id, compliance, status, omangFrontStatus, omangBackStatus, ...

## Common query patterns
```sql
-- New business today
SELECT COUNT(*) as count, SUM(premium) as gwp FROM policies WHERE DATE(created_at) = CURDATE();

-- Loss ratio by product (YTD)
SELECT pr.name, SUM(p.premium)*12 as annualised_premium,
       SUM(nc.claimed_amount) as claims_paid,
       ROUND(SUM(nc.claimed_amount)/NULLIF(SUM(p.premium)*12,0)*100,1) as loss_ratio_pct
FROM policies p
JOIN products pr ON pr.id = p.product_id
LEFT JOIN new_claims nc ON nc.policy_id = p.id AND nc.status = 'Approved'
  AND YEAR(nc.claim_date) = YEAR(CURDATE())
WHERE p.status = 1
GROUP BY pr.id ORDER BY loss_ratio_pct DESC;

-- Collections this month by method
SELECT payment_method, COUNT(*) as txn, SUM(amount) as total
FROM payment_transactions
WHERE status='Success' AND DATE(created_at) >= DATE_FORMAT(CURDATE(),'%Y-%m-01')
GROUP BY payment_method;

-- Policies expiring in 30 days
SELECT p.policyNumber, CONCAT(c.firstName,' ',c.lastName) as customer,
       pa.effective_to, DATEDIFF(pa.effective_to,CURDATE()) as days_left
FROM policies p
JOIN policy_actions pa ON pa.policy_id=p.id AND pa.status='ISSUED'
LEFT JOIN customer c ON c.id=p.customer_id
WHERE p.status=1 AND pa.effective_to BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)
ORDER BY pa.effective_to LIMIT 20;
```
PROMPT;
    }

    private static function agentPrompt(object $agent, ?string $agencyName): string
    {
        return "You are Alpha Direct Insurance's WhatsApp agent assistant.\n\n"
            . "Agent: {$agent->firstName} {$agent->lastName} (ID: {$agent->id})\n"
            . "Agency: {$agencyName}\n\n"
            . "Help this agent with their portfolio: policies sold, commission, renewals, customers.\n"
            . "RULES:\n"
            . "- Only show data for THIS agent's policies (agent_id={$agent->id}). Never show other agents' data.\n"
            . "- Currency: Pula (P X,XXX.XX)\n"
            . "- WhatsApp — be concise, use bullet points\n"
            . "- Always use tools. Never make up numbers.\n"
            . "- Flag urgent items: renewals within 7 days, pending commission.";
    }

    private static function customerPrompt(object $customer): string
    {
        return "You are Alpha Direct Insurance's WhatsApp customer assistant.\n\n"
            . "Customer: {$customer->firstName} {$customer->lastName} (ID: {$customer->id})\n"
            . "Phone: {$customer->cellphone}\n\n"
            . "Help this customer with their policies, claims, payments, and KYC.\n"
            . "RULES:\n"
            . "- Only show data for THIS customer (customer_id={$customer->id}). Never show other customers' data.\n"
            . "- Currency: Pula (P X,XXX.XX)\n"
            . "- WhatsApp — keep replies under 500 words, use bullet points\n"
            . "- Always use tools. Never make up information.\n"
            . "- For claim filing, direct to: https://graphite.alphadirect.co.bw";
    }

    private static function isClaimTrigger(string $message): bool
    {
        $triggers = ['claim', 'file claim', 'file a claim', 'new claim', 'report claim', 'accident'];
        return in_array(strtolower(trim($message)), $triggers);
    }
}
