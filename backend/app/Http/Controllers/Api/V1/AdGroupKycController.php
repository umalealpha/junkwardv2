<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\AdGroupKycActivity;
use AlphaDirect\Models\AdGroupKycCampaign;
use AlphaDirect\Models\AdGroupKycLink;
use AlphaDirect\Services\AdGroupKycNotificationService;
use AlphaDirect\Services\AdGroupKycService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * AD Group KYC campaign management — V2 API port of the V8 Blade admin
 * (Admin\AdGroupKycAdminController + Admin\AdGroupKycLinkController), which
 * is unreachable in V2 behind BlockV1AdminPanel. Thin HTTP layer: mutations
 * delegate to AdGroupKycService / AdGroupKycNotificationService.
 *
 * Deliberate departures from V8 (documented defects there):
 *  - export uses AdGroupKycService::getCampaignStats (V8 called a method
 *    that didn't exist and 500'd every time);
 *  - links table returns raw JSON fields, no HTML badges, and selects the
 *    opened_at / otp_verified_at / completed_at columns it renders;
 *  - resend passes the optional custom message through (V8 dropped it);
 *  - campaign create honors status=active (V8's service forced draft).
 */
class AdGroupKycController extends Controller
{
    public function __construct(
        private AdGroupKycService $kycService,
        private AdGroupKycNotificationService $notificationService,
    ) {}

    // ──────────────────────────────────────────────────────────────
    // Reads
    // ──────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/kyc/ad-group/dashboard
     * Global stat tiles + recent activities for the campaigns page.
     */
    public function dashboard(): JsonResponse
    {
        $stats = [
            'total_campaigns'  => AdGroupKycCampaign::count(),
            'active_campaigns' => AdGroupKycCampaign::where('status', 'active')->count(),
            'total_links'      => AdGroupKycLink::count(),
            'completed_links'  => AdGroupKycLink::where('status', 'completed')->count(),
            'pending_links'    => AdGroupKycLink::where('status', 'pending')->count(),
            'expired_links'    => AdGroupKycLink::where('status', 'expired')->count(),
            'sent_links'       => AdGroupKycLink::whereNotNull('sent_at')->count(),
            'opened_links'     => AdGroupKycLink::whereIn('status', ['opened', 'otp_verified', 'completed'])->count(),
        ];
        $stats['completion_rate'] = $stats['total_links'] > 0
            ? round(($stats['completed_links'] / $stats['total_links']) * 100, 1)
            : 0;

        // Average sent -> opened minutes. Same definition as the campaign
        // stats; computed in SQL so the dashboard doesn't hydrate every link.
        $avgRow = AdGroupKycLink::whereNotNull('sent_at')
            ->whereNotNull('opened_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, sent_at, opened_at)) as avg_minutes')
            ->first();
        $stats['response_time'] = (int) round((float) ($avgRow->avg_minutes ?? 0));

        $activities = AdGroupKycActivity::with(['customer', 'link'])
            ->orderBy('occurred_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn ($a) => [
                'id'           => $a->id,
                'linkId'       => $a->link_id,
                'activityType' => $a->activity_type,
                'description'  => $a->description,
                'customerName' => $a->customer
                    ? trim(($a->customer->firstName ?? '') . ' ' . ($a->customer->lastName ?? ''))
                    : null,
                'occurredAt'   => $a->occurred_at,
            ]);

        return response()->json(['stats' => $stats, 'recent_activities' => $activities]);
    }

    /**
     * GET /api/v1/kyc/ad-group/campaigns
     */
    public function campaigns(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search'   => 'nullable|string|max:100',
            'status'   => 'nullable|string|in:draft,active,paused,completed,cancelled',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $results = AdGroupKycCampaign::withCount(['links', 'activities'])
            ->with('employerGroup:employer_group_id,name')
            ->when($validated['search'] ?? null, function ($q, $search) {
                $like = "%{$search}%";
                $q->where(fn ($i) => $i->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('employer_group_id', 'like', $like));
            })
            ->when($validated['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'data' => collect($results->items())->map(fn ($c) => [
                'id'                => $c->id,
                'name'              => $c->name,
                'description'       => $c->description,
                'status'            => $c->status,
                'employerGroupId'   => $c->employer_group_id,
                'employerGroupName' => $c->employerGroup->name ?? null,
                'linksCount'        => $c->links_count,
                'activitiesCount'   => $c->activities_count,
                'completionRate'    => $c->completion_rate,
                'createdAt'         => $c->created_at,
            ]),
            'meta' => $this->meta($results),
        ]);
    }

    /**
     * GET /api/v1/kyc/ad-group/campaigns/{id}
     */
    public function showCampaign(int $id): JsonResponse
    {
        $campaign = AdGroupKycCampaign::with('employerGroup:employer_group_id,name')->find($id);
        if (!$campaign) {
            return response()->json(['message' => 'Campaign not found'], 404);
        }

        return response()->json(['data' => [
            'id'                => $campaign->id,
            'name'              => $campaign->name,
            'description'       => $campaign->description,
            'status'            => $campaign->status,
            'employerGroupId'   => $campaign->employer_group_id,
            'employerGroupName' => $campaign->employerGroup->name ?? null,
            'linkExpiryHours'   => $campaign->link_expiry_hours,
            'otpExpiryMinutes'  => $campaign->otp_expiry_minutes,
            'maxAttempts'       => $campaign->max_attempts,
            'escalationDays'    => $campaign->escalation_days,
            'reminderDays'      => $campaign->reminder_days ?? [],
            'createdAt'         => $campaign->created_at,
            'stats'             => $this->kycService->getCampaignStats($campaign),
        ]]);
    }

    /**
     * GET /api/v1/kyc/ad-group/campaigns/{id}/links
     *
     * One row per employee policy in the campaign's employer group
     * (product_id 12), LEFT JOINed to the campaign's links — employees
     * without a generated link appear with linkId=null so the UI can offer
     * "Generate". JSON rewrite of the V8 DataTables endpoint.
     */
    public function campaignLinks(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'search'   => 'nullable|string|max:100',
            'status'   => 'nullable|string|max:30',
            'sort'     => 'nullable|string|in:id,customer,status,sent_at,expires_at',
            'dir'      => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $campaign = AdGroupKycCampaign::find($id);
        if (!$campaign) {
            return response()->json(['message' => 'Campaign not found'], 404);
        }

        $query = DB::table('employer_group_policy as egp')
            ->join('policies as p', 'egp.policy_id', '=', 'p.id')
            ->join('customer as c', 'p.customer_id', '=', 'c.id')
            ->leftJoin('ad_group_kyc_links as kyc', function ($join) use ($id) {
                $join->on('p.id', '=', 'kyc.policy_id')
                     ->where('kyc.campaign_id', '=', $id);
            })
            ->where('egp.employer_group_id', $campaign->employer_group_id)
            ->where('p.product_id', 12)
            ->when($validated['status'] ?? null, function ($q, $status) {
                $status === 'not_generated'
                    ? $q->whereNull('kyc.id')
                    : $q->where('kyc.status', $status);
            })
            ->when($validated['search'] ?? null, function ($q, $search) {
                $like = "%{$search}%";
                $q->where(fn ($i) => $i->where('c.firstName', 'like', $like)
                    ->orWhere('c.lastName', 'like', $like)
                    ->orWhere('c.email', 'like', $like)
                    ->orWhere('c.cellphone', 'like', $like)
                    ->orWhere('p.policyNumber', 'like', $like)
                    ->orWhere('egp.employee_id', 'like', $like));
            })
            ->select([
                'egp.employee_id', 'egp.policy_id',
                'p.policyNumber as policy_number',
                'c.id as customer_id', 'c.firstName', 'c.lastName', 'c.email', 'c.cellphone',
                'kyc.id as link_id', 'kyc.status as link_status',
                'kyc.sent_at', 'kyc.opened_at', 'kyc.otp_verified_at',
                'kyc.completed_at', 'kyc.expires_at',
            ]);

        // Whitelisted sort map (V8 ordered by the raw client column name).
        $sortMap = [
            'id'         => 'egp.policy_id',
            'customer'   => 'c.firstName',
            'status'     => 'kyc.status',
            'sent_at'    => 'kyc.sent_at',
            'expires_at' => 'kyc.expires_at',
        ];
        $query->orderBy(
            $sortMap[$validated['sort'] ?? 'id'],
            ($validated['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc'
        );

        $results = $query->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'data' => collect($results->items())->map(fn ($r) => [
                'policyId'      => $r->policy_id,
                'employeeId'    => $r->employee_id,
                'policyNumber'  => $r->policy_number,
                'customerId'    => $r->customer_id,
                'customerName'  => trim(($r->firstName ?? '') . ' ' . ($r->lastName ?? '')),
                'email'         => $r->email,
                'cellphone'     => $r->cellphone,
                'linkId'        => $r->link_id,
                'status'        => $r->link_id ? $r->link_status : 'not_generated',
                'sentAt'        => $r->sent_at,
                'openedAt'      => $r->opened_at,
                'otpVerifiedAt' => $r->otp_verified_at,
                'completedAt'   => $r->completed_at,
                'expiresAt'     => $r->expires_at,
            ]),
            'meta' => $this->meta($results),
        ]);
    }

    /**
     * GET /api/v1/kyc/ad-group/links/{id}
     * Link detail: timeline, delivery info, customer, activities (with
     * metadata — the V8 viewer was a stub).
     */
    public function showLink(int $id): JsonResponse
    {
        $link = AdGroupKycLink::with(['customer', 'policy:id,policyNumber', 'campaign:id,name,status'])->find($id);
        if (!$link) {
            return response()->json(['message' => 'Link not found'], 404);
        }

        $activities = AdGroupKycActivity::where('link_id', $link->id)
            ->orderByDesc('occurred_at')
            ->get()
            ->map(fn ($a) => [
                'id'           => $a->id,
                'activityType' => $a->activity_type,
                'description'  => $a->description,
                'metadata'     => $a->metadata,
                'ipAddress'    => $a->ip_address,
                'occurredAt'   => $a->occurred_at,
            ]);

        return response()->json(['data' => [
            'id'                => $link->id,
            'status'            => $link->status,
            'token'             => $link->unique_token,
            // Single source of truth for the customer URL (V8 built two
            // conflicting formats; the START_URL one is the real flow).
            'kycUrl'            => $link->kyc_url,
            'deliveryMethod'    => $link->delivery_method,
            'deliveryReference' => $link->delivery_reference,
            'ipAddress'         => $link->ip_address,
            'userAgent'         => $link->user_agent,
            'otpAttempts'       => $link->otp_attempts,
            'createdAt'         => $link->created_at,
            'sentAt'            => $link->sent_at,
            'openedAt'          => $link->opened_at,
            'otpVerifiedAt'     => $link->otp_verified_at,
            'completedAt'       => $link->completed_at,
            'expiresAt'         => $link->expires_at,
            'campaign'          => $link->campaign ? [
                'id'     => $link->campaign->id,
                'name'   => $link->campaign->name,
                'status' => $link->campaign->status,
            ] : null,
            'customer'          => $link->customer ? [
                'id'        => $link->customer->id,
                'name'      => trim(($link->customer->firstName ?? '') . ' ' . ($link->customer->lastName ?? '')),
                'email'     => $link->customer->email,
                'cellphone' => $link->customer->cellphone,
            ] : null,
            'policyNumber'      => $link->policy->policyNumber ?? null,
            'activities'        => $activities,
        ]]);
    }

    /**
     * GET /api/v1/kyc/ad-group/campaigns/{id}/export
     * Streamed CSV of the campaign's links. V8's export fataled 100% of the
     * time (called a stats method that didn't exist on the controller).
     */
    public function exportCampaign(int $id): StreamedResponse|JsonResponse
    {
        $campaign = AdGroupKycCampaign::with(['links.customer', 'links.policy:id,policyNumber'])->find($id);
        if (!$campaign) {
            return response()->json(['message' => 'Campaign not found'], 404);
        }
        $stats = $this->kycService->getCampaignStats($campaign);

        $filename = 'ad_group_kyc_campaign_' . $campaign->id . '_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($campaign, $stats) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Campaign', $campaign->name]);
            fputcsv($out, ['Status', $campaign->status]);
            fputcsv($out, ['Employer Group', $campaign->employer_group_id]);
            fputcsv($out, ['Total Links', $stats['total_links']]);
            fputcsv($out, ['Completed', $stats['completed_links']]);
            fputcsv($out, ['Completion Rate %', $stats['completion_rate']]);
            fputcsv($out, []);
            fputcsv($out, ['Link ID', 'Customer', 'Email', 'Phone', 'Policy Number',
                           'Status', 'Sent At', 'Opened At', 'Completed At', 'Expires At']);
            foreach ($campaign->links as $link) {
                fputcsv($out, [
                    $link->id,
                    trim(($link->customer->firstName ?? '') . ' ' . ($link->customer->lastName ?? '')),
                    $link->customer->email ?? '',
                    $link->customer->cellphone ?? '',
                    $link->policy->policyNumber ?? '',
                    $link->status,
                    $link->sent_at,
                    $link->opened_at,
                    $link->completed_at,
                    $link->expires_at,
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    // ──────────────────────────────────────────────────────────────
    // Writes
    // ──────────────────────────────────────────────────────────────

    /**
     * POST /api/v1/kyc/ad-group/campaigns
     */
    public function storeCampaign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                    => 'required|string|max:255',
            'description'             => 'nullable|string|max:1000',
            'employer_group_id'       => 'required|string|exists:employer_groups,employer_group_id',
            'link_expiry_hours'       => 'required|integer|min:1|max:720',
            'otp_expiry_minutes'      => 'required|integer|min:1|max:60',
            'max_attempts'            => 'required|integer|min:1|max:10',
            'escalation_days'         => 'required|integer|min:1|max:30',
            'reminder_days'           => 'required|array|min:1',
            'reminder_days.*'         => 'integer|min:1|max:30',
            'notification_channels'   => 'required|array|min:1',
            'notification_channels.*' => 'in:email,sms,whatsapp',
        ]);

        $validated['status'] = 'active';
        $validated['product_id'] = 12;
        $validated['notification_settings'] = ['channels' => $validated['notification_channels']];
        unset($validated['notification_channels']);

        $campaign = $this->kycService->createCampaign($validated);

        return response()->json([
            'message' => 'Campaign created',
            'data'    => ['id' => $campaign->id],
        ], 201);
    }

    /**
     * PUT /api/v1/kyc/ad-group/campaigns/{id}
     */
    public function updateCampaign(Request $request, int $id): JsonResponse
    {
        $campaign = AdGroupKycCampaign::find($id);
        if (!$campaign) {
            return response()->json(['message' => 'Campaign not found'], 404);
        }

        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'description'     => 'nullable|string|max:1000',
            'status'          => 'required|in:active,completed,paused',
            'escalation_days' => 'nullable|integer|min:1|max:365',
            'reminder_days'   => 'nullable|array',
            'reminder_days.*' => 'integer|min:1|max:30',
        ]);
        $validated['updated_by'] = auth()->id();

        $campaign->update($validated);

        return response()->json(['message' => 'Campaign updated']);
    }

    /**
     * POST /api/v1/kyc/ad-group/campaigns/{id}/generate-links
     * Idempotent per campaign+policy (service returns the existing link).
     */
    public function generateLinks(Request $request, int $id): JsonResponse
    {
        $campaign = AdGroupKycCampaign::find($id);
        if (!$campaign) {
            return response()->json(['message' => 'Campaign not found'], 404);
        }

        $validated = $request->validate([
            'policy_ids'   => 'required|array|min:1',
            'policy_ids.*' => 'integer|exists:policies,id',
        ]);

        $links = $this->kycService->generateLinksForPolicies($campaign, $validated['policy_ids']);

        return response()->json([
            'message' => 'Links generated',
            'data'    => ['links_count' => is_countable($links) ? count($links) : 0],
        ]);
    }

    /**
     * POST /api/v1/kyc/ad-group/campaigns/{id}/send-links
     */
    public function sendLinks(Request $request, int $id): JsonResponse
    {
        $campaign = AdGroupKycCampaign::find($id);
        if (!$campaign) {
            return response()->json(['message' => 'Campaign not found'], 404);
        }

        // No 'all' pseudo-channel: the service returns a nested per-channel
        // map for it that the counting below can't see (and the FE never
        // sends it) — callers list channels explicitly instead.
        $validated = $request->validate([
            'link_ids'   => 'required|array|min:1',
            'link_ids.*' => 'integer|exists:ad_group_kyc_links,id',
            'channels'   => 'required|array|min:1',
            'channels.*' => 'in:email,sms,whatsapp',
        ]);

        // Don't notify customers whose link is already terminal.
        $eligibleIds = AdGroupKycLink::whereIn('id', $validated['link_ids'])
            ->whereNotIn('status', ['completed', 'expired'])
            ->pluck('id')
            ->all();
        $skipped = array_values(array_diff($validated['link_ids'], $eligibleIds));

        // Per-link map: either ['success' => false, 'error' => ...] (link not
        // found) or a per-channel results map from the notification service.
        $raw = $eligibleIds
            ? $this->kycService->sendKycLinks($eligibleIds, $validated['channels'], true)
            : [];

        $successCount = 0;
        $failureCount = count($skipped);
        foreach ($raw as $linkResult) {
            $ok = array_key_exists('success', $linkResult)
                ? (bool) $linkResult['success']
                : collect($linkResult)->contains(fn ($r) => !empty($r['success']));
            $ok ? $successCount++ : $failureCount++;
        }

        return $this->notifyResponse([
            'success_count'    => $successCount,
            'failure_count'    => $failureCount,
            'total'            => count($raw) + count($skipped),
            'skipped_terminal' => $skipped,
            'details'          => $raw,
        ]);
    }

    /**
     * POST /api/v1/kyc/ad-group/links/{id}/resend
     */
    public function resendLink(Request $request, int $id): JsonResponse
    {
        $link = AdGroupKycLink::with(['customer', 'campaign'])->find($id);
        if (!$link) {
            return response()->json(['message' => 'Link not found'], 404);
        }
        if (in_array($link->status, ['completed', 'expired'], true)) {
            return response()->json(['message' => "Link is already {$link->status} — nothing to resend."], 409);
        }

        $validated = $request->validate([
            'channels'   => 'required|array|min:1',
            'channels.*' => 'in:email,sms,whatsapp',
            'message'    => 'nullable|string|max:500',
        ]);

        $results = $this->notificationService->sendKycLink(
            $link, $validated['channels'], true, $validated['message'] ?? null
        );
        $success = collect($results)->contains(fn ($r) => !empty($r['success']));

        AdGroupKycActivity::create([
            'link_id'       => $link->id,
            'customer_id'   => $link->customer_id,
            'policy_id'     => $link->policy_id,
            'activity_type' => 'notification_resent',
            'description'   => 'Notification re-sent by ' . (auth()->user()->firstName ?? 'staff')
                . ' via ' . implode(', ', $validated['channels'])
                . (!empty($validated['message']) ? ' — note: ' . $validated['message'] : ''),
            'metadata'      => ['channels' => $validated['channels'], 'results' => $results],
            'occurred_at'   => now(),
        ]);

        return response()->json([
            'message' => $success ? 'Notification re-sent' : 'All channels failed',
            'data'    => $results,
        ], $success ? 200 : 502);
    }

    /**
     * POST /api/v1/kyc/ad-group/campaigns/{id}/notifications  (bulk)
     */
    public function bulkNotify(Request $request, int $id): JsonResponse
    {
        $campaign = AdGroupKycCampaign::find($id);
        if (!$campaign) {
            return response()->json(['message' => 'Campaign not found'], 404);
        }

        $validated = $request->validate([
            'link_ids'   => 'required|array|min:1',
            'link_ids.*' => 'integer|exists:ad_group_kyc_links,id',
            'channels'   => 'required|array|min:1',
            'channels.*' => 'in:email,sms,whatsapp',
            'message'    => 'nullable|string|max:500',
        ]);

        $results = $this->notificationService->sendBulkNotifications(
            $campaign, $validated['link_ids'], $validated['channels'], $validated['message'] ?? null
        );

        return $this->notifyResponse($results);
    }

    /**
     * POST /api/v1/kyc/ad-group/campaigns/{id}/reminders
     */
    public function sendReminders(Request $request, int $id): JsonResponse
    {
        $campaign = AdGroupKycCampaign::find($id);
        if (!$campaign) {
            return response()->json(['message' => 'Campaign not found'], 404);
        }

        $validated = $request->validate([
            'days_since_sent' => 'nullable|integer|min:1|max:30',
            'channels'        => 'required|array|min:1',
            'channels.*'      => 'in:email,sms,whatsapp',
        ]);

        $results = $this->notificationService->sendReminderNotifications(
            $campaign, $validated['days_since_sent'] ?? 3, $validated['channels']
        );

        return $this->notifyResponse($results);
    }

    /**
     * POST /api/v1/kyc/ad-group/campaigns/{id}/escalations
     */
    public function sendEscalations(Request $request, int $id): JsonResponse
    {
        $campaign = AdGroupKycCampaign::find($id);
        if (!$campaign) {
            return response()->json(['message' => 'Campaign not found'], 404);
        }

        $validated = $request->validate([
            'channels'   => 'required|array|min:1',
            'channels.*' => 'in:email,sms,whatsapp',
        ]);

        $results = $this->notificationService->sendEscalationNotifications($campaign, $validated['channels']);

        return $this->notifyResponse($results);
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    private function notifyResponse(array $results): JsonResponse
    {
        $success = $results['success_count'] ?? 0;
        $failure = $results['failure_count'] ?? 0;

        return response()->json([
            'message' => "Success: {$success}, Failed: {$failure}",
            'data'    => $results,
        ]);
    }

    private function meta($paginator): array
    {
        return [
            'total'        => $paginator->total(),
            'per_page'     => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'from'         => $paginator->firstItem(),
            'to'           => $paginator->lastItem(),
        ];
    }
}
