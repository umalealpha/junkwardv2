<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Services\WhatsAppTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin-facing CRUD over Meta WhatsApp Business message templates.
 * Mounted under /api/v1/whatsapp-templates and surfaced in the
 * Notification Management UI under "WhatsApp Templates".
 *
 * Each method is a thin pass-through to WhatsAppTemplateService —
 * Meta is the source of truth for template state, including the
 * PENDING -> APPROVED/REJECTED lifecycle. We don't mirror to a
 * local DB because that would just drift; the UI re-fetches from
 * Meta each time.
 */
class WhatsAppTemplateController extends Controller
{
    public function __construct(private WhatsAppTemplateService $svc)
    {
    }

    public function index(): JsonResponse
    {
        if (!$this->svc->isConfigured()) {
            return response()->json([
                'configured' => false,
                'message'    => 'WhatsApp template service not configured. Set WHATSAPP_BUSINESS_ACCOUNT_ID env on the backend task definition.',
                'data'       => [],
            ]);
        }
        try {
            $items = $this->svc->list();
            return response()->json(['configured' => true, 'data' => $items]);
        } catch (\Throwable $e) {
            return response()->json([
                'configured' => true,
                'message'    => $e->getMessage(),
                'data'       => [],
            ], 502);
        }
    }

    public function store(Request $req): JsonResponse
    {
        $data = $req->validate([
            'name'       => 'required|string|min:1|max:512|regex:/^[a-z0-9_]+$/',
            'category'   => 'required|in:UTILITY,MARKETING,AUTHENTICATION',
            'language'   => 'required|string|min:2|max:10',
            'components' => 'required|array|min:1',
            'components.*.type' => 'required|string',
            'components.*.text' => 'nullable|string',
        ]);
        try {
            $result = $this->svc->create($data['name'], $data['category'], $data['language'], $data['components']);
            return response()->json(['success' => true, 'data' => $result], 201);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * DELETE /whatsapp-templates/{name} — destroys ALL languages by
     * default; pass ?hsm_id=... in the query to target one variant.
     */
    public function destroy(Request $req, string $name): JsonResponse
    {
        $hsmId = $req->query('hsm_id');
        try {
            $this->svc->delete($name, $hsmId ? (string) $hsmId : null);
            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /whatsapp-templates/test — send an approved template to a
     * recipient. Used by the admin UI to smoke-test a freshly-approved
     * template before relying on it in cron alerts.
     */
    public function test(Request $req): JsonResponse
    {
        $data = $req->validate([
            'to'           => 'required|string',
            'name'         => 'required|string',
            'language'     => 'required|string',
            'body_params'  => 'array',
        ]);
        try {
            $result = $this->svc->send($data['to'], $data['name'], $data['language'], $data['body_params'] ?? []);
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
