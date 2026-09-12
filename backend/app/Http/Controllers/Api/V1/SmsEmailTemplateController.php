<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SMS Templates + Email Templates management.
 * Tables already exist: sms_templates, email_templates (graphiteBWV8 tables).
 */
class SmsEmailTemplateController extends Controller
{
    // ── SMS Templates ───────────────────────────────────────────────────
    public function smsTemplates(Request $request): JsonResponse
    {
        $query = DB::table('sms_templates')->orderBy('name');
        if ($request->has('search')) $query->where('name', 'like', '%' . $request->input('search') . '%');
        return response()->json(['data' => $query->get()]);
    }

    public function storeSmsTemplate(Request $request): JsonResponse
    {
        $d = $request->validate(['name' => 'required|string|max:100', 'content' => 'required|string', 'status' => 'nullable|integer|in:0,1']);
        $d['status'] = $d['status'] ?? 1;
        $d['created_at'] = now(); $d['updated_at'] = now();
        $id = DB::table('sms_templates')->insertGetId($d);
        return response()->json(['message' => 'SMS template created.', 'data' => ['id' => $id]], 201);
    }

    public function updateSmsTemplate(Request $request, int $id): JsonResponse
    {
        $d = $request->validate(['name' => 'required|string|max:100', 'content' => 'required|string', 'status' => 'nullable|integer|in:0,1']);
        $d['updated_at'] = now();
        DB::table('sms_templates')->where('id', $id)->update($d);
        return response()->json(['message' => 'SMS template updated.']);
    }

    public function destroySmsTemplate(int $id): JsonResponse
    {
        DB::table('sms_templates')->where('id', $id)->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    // ── Email Templates ─────────────────────────────────────────────────
    public function emailTemplates(Request $request): JsonResponse
    {
        $query = DB::table('email_templates')->orderByDesc('id');
        if ($request->has('search')) $query->where('name', 'like', '%' . $request->input('search') . '%');
        return response()->json(['data' => $query->get()]);
    }

    public function storeEmailTemplate(Request $request): JsonResponse
    {
        $d = $request->validate([
            'name'    => 'required|string|max:200',
            'subject' => 'nullable|string|max:300',
            'body'    => 'required|string',
            'status'  => 'nullable|integer|in:0,1',
        ]);
        $d['status'] = $d['status'] ?? 1;
        $d['created_at'] = now(); $d['updated_at'] = now();
        $id = DB::table('email_templates')->insertGetId($d);
        return response()->json(['message' => 'Email template created.', 'data' => ['id' => $id]], 201);
    }

    /**
     * Templates that carry credential / set-password links. Editing them can
     * redirect a partner or HR user to an attacker-controlled page, so only
     * Super Admin may change or delete them (security audit I2, 2026-09-07).
     */
    private const PROTECTED_HOOKS = ['partner_login_new', 'hr_login_new', 'hr_password_reset'];

    private function guardProtectedTemplate(Request $request, int $id): ?JsonResponse
    {
        $hook = DB::table('email_templates')->where('id', $id)->value('hook_slug');
        if ($hook && in_array($hook, self::PROTECTED_HOOKS, true) && !optional($request->user())->hasRole('Super Admin')) {
            return response()->json(['message' => "Template '{$hook}' carries credential links and can only be changed by a Super Admin."], 403);
        }
        return null;
    }

    public function updateEmailTemplate(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->guardProtectedTemplate($request, $id)) {
            return $deny;
        }
        $d = $request->validate([
            'name'    => 'required|string|max:200',
            'subject' => 'nullable|string|max:300',
            'body'    => 'required|string',
            'status'  => 'nullable|integer|in:0,1',
        ]);
        $d['updated_at'] = now();
        DB::table('email_templates')->where('id', $id)->update($d);
        return response()->json(['message' => 'Email template updated.']);
    }

    public function destroyEmailTemplate(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->guardProtectedTemplate($request, $id)) {
            return $deny;
        }
        DB::table('email_templates')->where('id', $id)->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    // ── Communication Timeline (per customer) ───────────────────────────
    public function customerTimeline(Request $request, int $customerId): JsonResponse
    {
        // Merge sms_email_log + notification_logs for unified timeline
        $smsEmails = DB::table('sms_email_log')
            ->where('customer_id', $customerId)
            ->select([
                'id', DB::raw("'sms_email' as source"),
                'type as channel', 'policyNumber as policy_number',
                'message', 'status', 'to_email', 'to_cellphone',
                'delivery_at', 'created_at',
            ])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $notifications = DB::table('notification_logs')
            ->where('user_id', function ($q) use ($customerId) {
                // Find user_id linked to this customer (agent who owns policies)
                $q->select('added_by')->from('policies')->where('customer_id', $customerId)->limit(1);
            })
            ->whereRaw("JSON_EXTRACT(data, '$.policy_id') IN (SELECT id FROM policies WHERE customer_id = ?)", [$customerId])
            ->select([
                'id', DB::raw("'notification' as source"),
                'channel', DB::raw("JSON_EXTRACT(data, '$.policy_number') as policy_number"),
                DB::raw("JSON_EXTRACT(data, '$.message') as message"),
                'status', DB::raw("NULL as to_email"), DB::raw("NULL as to_cellphone"),
                DB::raw("NULL as delivery_at"), 'created_at',
            ])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $timeline = $smsEmails->concat($notifications)->sortByDesc('created_at')->values()->take(100);

        return response()->json(['data' => $timeline]);
    }
}
