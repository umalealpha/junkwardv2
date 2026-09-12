<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommunicationController extends Controller
{
    // =========================================================================
    //  Notification Templates CRUD
    // =========================================================================

    public function templates(Request $request): JsonResponse
    {
        $query = DB::table('notification_templates')->orderBy('type');

        if ($request->has('search')) {
            $s = $request->input('search');
            $query->where(fn($q) => $q->where('name', 'like', "%{$s}%")->orWhere('type', 'like', "%{$s}%"));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'           => 'required|string|max:100|unique:notification_templates,type',
            'name'           => 'required|string|max:200',
            'channels'       => 'nullable|array',
            'email_subject'  => 'nullable|string|max:300',
            'email_body'     => 'nullable|string',
            'sms_body'       => 'nullable|string',
            'whatsapp_body'  => 'nullable|string',
            'variables'      => 'nullable|array',
            'active'         => 'nullable|boolean',
        ]);

        $data['channels']  = isset($data['channels']) ? json_encode($data['channels']) : null;
        $data['variables'] = isset($data['variables']) ? json_encode($data['variables']) : null;
        $data['active']    = $data['active'] ?? true;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('notification_templates')->insertGetId($data);
        return response()->json(['message' => 'Template created.', 'data' => ['id' => $id]], 201);
    }

    public function updateTemplate(Request $request, int $id): JsonResponse
    {
        $template = DB::table('notification_templates')->where('id', $id)->first();
        if (!$template) return response()->json(['message' => 'Not found.'], 404);

        $data = $request->validate([
            'name'           => 'required|string|max:200',
            'channels'       => 'nullable|array',
            'email_subject'  => 'nullable|string|max:300',
            'email_body'     => 'nullable|string',
            'sms_body'       => 'nullable|string',
            'whatsapp_body'  => 'nullable|string',
            'variables'      => 'nullable|array',
            'active'         => 'nullable|boolean',
        ]);

        $data['channels']  = isset($data['channels']) ? json_encode($data['channels']) : null;
        $data['variables'] = isset($data['variables']) ? json_encode($data['variables']) : null;
        $data['updated_at'] = now();

        DB::table('notification_templates')->where('id', $id)->update($data);
        return response()->json(['message' => 'Template updated.']);
    }

    public function destroyTemplate(int $id): JsonResponse
    {
        DB::table('notification_templates')->where('id', $id)->delete();
        return response()->json(['message' => 'Template deleted.']);
    }

    // =========================================================================
    //  Delivery Logs
    // =========================================================================

    public function logs(Request $request): JsonResponse
    {
        $query = DB::table('notification_logs')
            ->orderByDesc('created_at');

        if ($request->has('type'))    $query->where('type', $request->input('type'));
        if ($request->has('channel')) $query->where('channel', $request->input('channel'));
        if ($request->has('status'))  $query->where('status', $request->input('status'));
        if ($request->has('user_id')) $query->where('user_id', $request->input('user_id'));
        if ($request->has('date_from')) $query->whereDate('created_at', '>=', $request->input('date_from'));
        if ($request->has('date_to'))   $query->whereDate('created_at', '<=', $request->input('date_to'));

        $results = $query->simplePaginate($request->input('per_page', 50));

        // Batch-load user names
        $items = collect($results->items());
        $userIds = $items->pluck('user_id')->filter()->unique()->values()->toArray();
        $userMap = !empty($userIds)
            ? DB::table('users')->whereIn('id', $userIds)->pluck('name', 'id')->toArray()
            : [];

        return response()->json([
            'data' => $items->map(fn($l) => [
                'id'        => $l->id,
                'userId'    => $l->user_id,
                'userName'  => $userMap[$l->user_id] ?? null,
                'type'      => $l->type,
                'channel'   => $l->channel,
                'status'    => $l->status,
                'reason'    => $l->reason,
                'data'      => json_decode($l->data ?? '{}', true),
                'createdAt' => $l->created_at,
            ]),
            'meta' => [
                'current_page' => $results->currentPage(),
                'per_page'     => $results->perPage(),
                'has_more'     => $results->hasMorePages(),
            ],
        ]);
    }

    // =========================================================================
    //  SMS Templates (legacy graphiteBWV8 sms_templates table)
    // =========================================================================

    public function smsTemplates(): JsonResponse
    {
        $templates = DB::table('sms_templates')->orderBy('name')->get();
        return response()->json(['data' => $templates]);
    }

    public function storeSmsTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'    => 'required|string|max:100',
            'content' => 'required|string',
            'status'  => 'nullable|integer|in:0,1',
        ]);
        $data['status']     = $data['status'] ?? 1;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('sms_templates')->insertGetId($data);
        return response()->json(['message' => 'SMS template created.', 'data' => ['id' => $id]], 201);
    }

    public function updateSmsTemplate(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name'    => 'required|string|max:100',
            'content' => 'required|string',
            'status'  => 'nullable|integer|in:0,1',
        ]);
        $data['updated_at'] = now();

        DB::table('sms_templates')->where('id', $id)->update($data);
        return response()->json(['message' => 'SMS template updated.']);
    }

    public function destroySmsTemplate(int $id): JsonResponse
    {
        DB::table('sms_templates')->where('id', $id)->delete();
        return response()->json(['message' => 'SMS template deleted.']);
    }

    // =========================================================================
    //  SMS/Email Delivery Logs (legacy sms_email_log table)
    // =========================================================================

    public function smsEmailLogs(Request $request): JsonResponse
    {
        $query = DB::table('sms_email_log as l')
            ->orderByDesc('l.id');

        if ($request->has('log_type'))     $query->where('l.log_type', $request->input('log_type'));
        if ($request->has('content_type')) $query->where('l.content_type', $request->input('content_type'));
        if ($request->has('customer_id'))  $query->where('l.customer_id', $request->input('customer_id'));

        $results = $query->simplePaginate($request->input('per_page', 50));

        return response()->json([
            'data' => collect($results->items()),
            'meta' => [
                'current_page' => $results->currentPage(),
                'per_page'     => $results->perPage(),
                'has_more'     => $results->hasMorePages(),
            ],
        ]);
    }
}
