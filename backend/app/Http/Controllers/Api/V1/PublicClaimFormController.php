<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Models\ClaimAccessLink;
use AlphaDirect\Services\Claims\ClaimFormDispatchService;
use AlphaDirect\Services\Claims\ClaimFormPrefill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The claimant's side: open the form with no password, fill it in, upload the
 * documents. Public and unauthenticated by design.
 *
 * THE CREDENTIAL IS THE TOKEN — 48 random bytes rendered as 64 URL-safe
 * characters, minted per claim, revocable, 90-day expiry. This is the ordinary
 * emailed-magic-link pattern, and it is what lets a claimant complete their own
 * claim form without being made to create an account. It is scoped to ONE claim
 * and fails closed on anything unusual.
 *
 * Guard rails, all deliberate:
 *   - only links with purpose 'fill_form' are accepted here, so the OTP-gated
 *     status link cannot be replayed against this surface;
 *   - responses never say whether a token merely expired or never existed —
 *     one message for both, so the surface cannot be enumerated;
 *   - throttled per IP in the route file;
 *   - uploads are extension- and size-checked before they touch the store.
 */
class PublicClaimFormController extends Controller
{
    /**
     * What a claimant may attach. Kept tight on purpose.
     *
     * doc/docx are deliberately EXCLUDED: this endpoint is open to the internet
     * (token only, no account) and claimant uploads land on the same Documents
     * tab a handler opens — a macro-bearing Word doc would be a live attack path
     * with no AV scan in between. A claim's supporting docs (police report,
     * quotations, photographs) are PDFs or images, which cover the real need.
     * If Word uploads are ever required, gate them behind AV scanning first.
     */
    private const ALLOWED = ['pdf', 'jpg', 'jpeg', 'png', 'heic', 'webp'];

    /** The same list checked by sniffed content, so a rename cannot get past it. */
    private const ALLOWED_MIME = [
        'application/pdf',
        'image/jpeg', 'image/png', 'image/heic', 'image/heif', 'image/webp',
    ];

    private const MAX_KB = 12288; // 12 MB — a phone photo of a police report

    public function __construct(private ClaimFormPrefill $prefill)
    {
    }

    /** GET — the pre-filled form, as data for the page to render. */
    public function show(string $token): JsonResponse
    {
        $link = $this->resolve($token);
        if (!$link) {
            return $this->dead();
        }

        $data = $this->prefill->build((int) $link->claim_id);
        if (!$data) {
            return $this->dead();
        }

        $claim = DB::table('claims')->where('id', $link->claim_id)->first(['claim_number', 'claim_type']);

        $link->last_viewed_at = now();
        $link->view_count     = (int) $link->view_count + 1;
        $link->save();

        return response()->json(['data' => [
            'claimNumber' => $claim->claim_number ?? null,
            'claimType'   => $claim->claim_type ?? null,
            'prefill'     => $data,
            'submitted'   => DB::table('claim_form_submissions')
                ->where('access_link_id', $link->id)->exists(),
        ]]);
    }

    /**
     * POST — the claimant's answers.
     *
     * The CFO's decision (11-Aug-2026) is that the claimant MAY change the
     * details we pre-filled. So we store both: what we filled in, and what came
     * back. The claim file therefore shows any alteration without the customer
     * having to fight a locked field.
     */
    public function submit(Request $request, string $token): JsonResponse
    {
        $link = $this->resolve($token);
        if (!$link) {
            return $this->dead();
        }

        // Cap the shape and size: this surface is reachable from the internet with
        // only a token, so bound the array sizes and per-value length rather than
        // letting a token-holder store arbitrary multi-MB JSON per submission.
        $validated = $request->validate([
            'answers'   => 'nullable|array|max:100',
            'answers.*' => 'nullable|string|max:4000',
            'prefill'   => 'nullable|array|max:100',
            'prefill.*' => 'nullable|string|max:500',
        ]);

        $original = $this->prefill->build((int) $link->claim_id);
        $returned = $validated['prefill'] ?? [];

        DB::table('claim_form_submissions')->insert([
            'claim_id'       => $link->claim_id,
            'access_link_id' => $link->id,
            'template_key'   => null,
            'payload'        => json_encode([
                'answers'       => $validated['answers'] ?? [],
                'prefill_sent'  => $this->flattenPrefill($original),
                'prefill_back'  => $returned,
                'changed_by_customer' => $this->diff($this->flattenPrefill($original), $returned),
            ]),
            'submitted_ip'   => $request->ip(),
            'submitted_at'   => now(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        return response()->json(['data' => ['ok' => true]]);
    }

    /** POST — a supporting document. One file per call, kept simple. */
    public function upload(Request $request, string $token): JsonResponse
    {
        $link = $this->resolve($token);
        if (!$link) {
            return $this->dead();
        }

        $request->validate([
            'file' => 'required|file|max:' . self::MAX_KB,
        ]);

        $file = $request->file('file');
        $ext  = strtolower((string) $file->getClientOriginalExtension());

        if (!in_array($ext, self::ALLOWED, true)) {
            return response()->json(['message' => 'Please send a PDF or a photograph.'], 422);
        }

        // Check the CONTENT, not just the name. This endpoint is open to the
        // internet with only a token, so a filename is a claim by a stranger,
        // not a fact — anything could be renamed to .pdf and a claims handler
        // would later open it. The sniffed type must also be on the list.
        $mime = (string) $file->getMimeType();
        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            return response()->json(['message' => 'That file does not look like a PDF or a photograph.'], 422);
        }

        try {
            // Same location claim attachments already use, so the document shows
            // up on the claim exactly like one a handler uploaded.
            $key = 'MIS/' . $link->claim_id . '/Documents/' . Str::random(28) . '.' . $ext;
            Storage::disk('s3')->put($key, file_get_contents($file->getRealPath()));

            // Match EVERY live reader of this shared legacy table, not just one.
            // The V2 claim screen — the page a handler actually opens — reads
            // `file_name` / `file_type` and builds the URL from `file_name`;
            // the legacy path reads the PHP-serialized `attachment` column. A
            // row that satisfies only one of them is invisible to the other, so
            // the claimant's document would silently never appear. Column-guarded
            // the same way ClaimsV2Controller::uploadDocument does, because this
            // table's shape differs between environments.
            $now  = now();
            $cols = \Schema::getColumnListing('claim_attachments');

            $row = [
                'claim_id'   => $link->claim_id,
                'name'       => Str::limit($file->getClientOriginalName(), 180, ''),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (in_array('attachment', $cols, true))         { $row['attachment'] = serialize([$key]); }
            if (in_array('file_name', $cols, true))          { $row['file_name'] = $key; }
            if (in_array('file_type', $cols, true))          { $row['file_type'] = $mime; }
            if (in_array('type', $cols, true))               { $row['type'] = $ext; }
            if (in_array('document_type_name', $cols, true)) { $row['document_type_name'] = 'Claimant upload'; }

            DB::table('claim_attachments')->insert(array_intersect_key($row, array_flip($cols)));

            return response()->json(['data' => ['ok' => true, 'name' => $file->getClientOriginalName()]]);
        } catch (\Throwable $e) {
            Log::error('[PublicClaimForm] upload failed', ['claim_id' => $link->claim_id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'We could not save that file. Please try again.'], 500);
        }
    }

    // ── internals ───────────────────────────────────────────────────────────

    /** Fail-closed token resolution. Only 'fill_form' links open this surface. */
    private function resolve(string $token): ?ClaimAccessLink
    {
        if (!ClaimFormDispatchService::enabled()) {
            return null;
        }
        if (strlen($token) < 32) {
            return null;
        }

        $link = ClaimAccessLink::where('token', $token)
            ->where('purpose', 'fill_form')
            ->first();

        if (!$link || !$link->isUsable()) {
            return null;
        }

        // Fail closed on requires_otp. A form link is minted with requires_otp
        // false; if a row ever carries true — a hand-edited record, a future
        // change of policy on this link type — this surface must NOT serve it
        // without the code, rather than silently ignoring the requirement.
        if ((bool) $link->requires_otp === true) {
            return null;
        }

        return $link;
    }

    /**
     * One message whether the token is wrong, revoked or expired — a different
     * message for each would let someone probe which tokens exist.
     */
    private function dead(): JsonResponse
    {
        return response()->json([
            'message' => 'This link is no longer active. Please contact us and we will send you a new one.',
        ], 404);
    }

    /**
     * Flatten the pre-fill into the SAME flat keys the form posts back, so the
     * two can be compared.
     *
     * The glass detail is nested under vehicle.glass and the form posts it as
     * `glass_*`. Without unwrapping it, every glass field would come back
     * looking like the customer had changed it — turning the alteration record
     * into noise on a quarter of all claims.
     */
    private function flattenPrefill(array $data): array
    {
        $out = [];
        foreach (['insured', 'policy', 'vehicle'] as $group) {
            foreach (($data[$group] ?? []) as $k => $v) {
                if (!is_array($v)) {
                    $out[$group . '_' . $k] = $v;
                }
            }
        }

        // vehicle.glass -> the glass_* keys the form actually posts.
        $glassKeyMap = [
            'type_of_glass'    => 'glass_type',
            'plate_size'       => 'glass_size',
            'damage_cause'     => 'glass_cause',
            'damage_extent'    => 'glass_extent',
            'vehicle_situated' => 'glass_where',
            'estimate'         => 'glass_estimate',
        ];
        foreach (($data['vehicle']['glass'] ?? []) as $k => $v) {
            if (!is_array($v) && isset($glassKeyMap[$k])) {
                $out[$glassKeyMap[$k]] = $v;
            }
        }

        return $out;
    }

    /**
     * What differs between what we hold NOW and what the claimant sent back.
     *
     * HONEST LIMITATION: this compares against the pre-fill rebuilt at submit
     * time, not a snapshot of what was on the form when it was emailed. If a
     * handler edits the claim after sending, an untouched field can appear here.
     * Both sides are stored (`prefill_sent` and `prefill_back`) so the raw
     * evidence survives either way — this list is a convenience for the handler,
     * not a control, and must not be treated as proof of what a customer did.
     */
    private function diff(array $sent, array $back): array
    {
        $changed = [];
        foreach ($back as $k => $v) {
            $was = $sent[$k] ?? null;
            if (trim((string) $was) !== trim((string) $v)) {
                $changed[$k] = ['was' => $was, 'now' => $v];
            }
        }

        return $changed;
    }
}
