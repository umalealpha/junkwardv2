<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Mail\CareerApplicationReceived;
use AlphaDirect\Models\WebsiteLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WebsiteLeadController extends Controller
{
    // HR inbox notified on every career application from the website.
    private const CAREER_NOTIFY_EMAIL = 'hr@alphadirect.co.bw';

    /**
     * POST /api/v1/public/website-leads
     *
     * Public, throttled store for every marketing-website form submission
     * (quotation enquiries, contact messages, claim notifications, career
     * applications, newsletter sign-ups). CAPTCHA is verified by the
     * website's own server before this endpoint is called.
     *
     * Body: { type, fullName?, email?, phone?, product?, message?, details?, source? }
     * Returns: { ok: true, id } 201 | { ok: false, errors } 422
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:' . implode(',', WebsiteLead::TYPES),
            'fullName' => 'nullable|string|max:160',
            'email' => 'nullable|email|max:160',
            'phone' => 'nullable|string|max:50',
            'product' => 'nullable|string|max:160',
            'message' => 'nullable|string|max:5000',
            'details' => 'nullable|array',
            'source' => 'nullable|string|max:64',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $lead = WebsiteLead::create([
            'type' => $request->input('type'),
            'full_name' => $request->input('fullName'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'product' => $request->input('product'),
            'message' => $request->input('message'),
            'details' => $request->input('details'),
            'status' => WebsiteLead::STATUS_NEW,
            'source' => $request->input('source', 'website'),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'submitted_at' => now(),
        ]);

        Log::info('website_lead.created', ['id' => $lead->id, 'type' => $lead->type]);

        // Notify HR about career applications — a mail failure must never
        // fail the submission itself.
        if ($lead->type === WebsiteLead::TYPE_CAREER) {
            try {
                Mail::to(self::CAREER_NOTIFY_EMAIL)->send(new CareerApplicationReceived($lead));
            } catch (\Throwable $e) {
                Log::warning('website_lead.career_mail_failed', [
                    'id' => $lead->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['ok' => true, 'id' => $lead->id], 201);
    }

    /**
     * GET /api/v1/website-leads
     *
     * Authenticated list for the V2 admin SPA's Leads page.
     * Filters: type, status, search (name/email/phone/product), per_page.
     * Returns: { data: [...], meta: { current_page, last_page, per_page, total } }
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'nullable|in:' . implode(',', WebsiteLead::TYPES),
            'status' => 'nullable|in:' . implode(',', WebsiteLead::STATUSES),
            'search' => 'nullable|string|max:160',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 25);

        $leads = WebsiteLead::query()
            ->when($validated['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($validated['search'] ?? null, function ($q, $term) {
                $term = trim($term);
                $q->where(function ($sub) use ($term) {
                    $sub->where('full_name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhere('product', 'like', "%{$term}%");
                });
            })
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => collect($leads->items())->map(fn (WebsiteLead $lead) => [
                'id' => $lead->id,
                'type' => $lead->type,
                'full_name' => $lead->full_name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'product' => $lead->product,
                'message' => $lead->message,
                'details' => $lead->details,
                'status' => $lead->status,
                'source' => $lead->source,
                'submitted_at' => optional($lead->submitted_at)->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $leads->currentPage(),
                'last_page' => $leads->lastPage(),
                'per_page' => $leads->perPage(),
                'total' => $leads->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/public/website-leads/files
     *
     * Public, throttled multipart upload for career-application files
     * (CV + credentials). One file per request. Returns an opaque stored
     * name the website embeds in the lead's details; admins retrieve it
     * through the authenticated download route below.
     *
     * Body: multipart { file }
     * Returns: { ok: true, file, original } 201 | { ok: false, errors } 422
     */
    public function uploadFile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240|mimes:pdf,doc,docx,jpg,jpeg,png',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $file = $request->file('file');
        $name = (string) Str::uuid() . '.' . strtolower($file->getClientOriginalExtension());
        $file->storeAs('website-leads', $name);

        Log::info('website_lead.file_uploaded', ['file' => $name, 'size' => $file->getSize()]);

        return response()->json([
            'ok' => true,
            'file' => $name,
            'original' => $file->getClientOriginalName(),
        ], 201);
    }

    /**
     * GET /api/v1/website-leads/files/{name}
     *
     * Authenticated download of a career-application file for the admin
     * Leads page. {name} is the opaque uuid name from uploadFile — the
     * strict pattern also blocks path traversal.
     */
    public function downloadFile(string $name): StreamedResponse
    {
        abort_unless(
            preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\.(pdf|doc|docx|jpg|jpeg|png)$/', $name) === 1,
            404,
        );

        $path = 'website-leads/' . $name;
        abort_unless(Storage::exists($path), 404);

        return Storage::download($path, $name);
    }

    /**
     * PATCH /api/v1/website-leads/{id}/status
     *
     * Body: { status: new|in_progress|resolved|spam }
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', WebsiteLead::STATUSES),
        ]);

        $lead = WebsiteLead::findOrFail($id);
        $lead->update(['status' => $validated['status']]);

        Log::info('website_lead.status_updated', ['id' => $lead->id, 'status' => $lead->status]);

        return response()->json(['ok' => true]);
    }
}
