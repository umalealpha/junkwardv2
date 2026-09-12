<?php
/**
 * Smart Underwriting Upload — accepts a broker schedule (xlsx/xls/xlsb/pdf),
 * stores it, queues extraction via SmartUnderwritingExtractJob (smart-uw-engine),
 * returns a job id the frontend polls. The extracted Graphite-ready JSON is
 * replayed by the review screen through the EXISTING create-policy endpoints —
 * no new write paths.
 *
 * Mirrors conventions from ExcelImportController::upload + OcrController.
 */

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use AlphaDirect\Http\Controllers\Admin\AiConfigController;
use AlphaDirect\Http\Controllers\Admin\VaultController;
use AlphaDirect\Jobs\SmartUnderwritingExtractJob;
use AlphaDirect\Services\SmartUw\PhpScheduleExtractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SmartUploadController extends Controller
{
    // Whatever the broker actually sent. A schedule arrives as a workbook, a
    // PDF, a CSV export, a Word document or a phone photograph of a printout,
    // and rejecting the last three just moved the typing back to the
    // underwriter. PhpScheduleExtractor has a reader for each (see its FORMATS
    // block); .xlsb still needs the Python sidecar, which is why it stays.
    //
    // Excluded on purpose: legacy binary .doc (no PHP reader) and .tif/.tiff
    // (PHP's GD cannot decode TIFF, so it would fail after the upload rather
    // than at it).
    private const ACCEPT = [
        'xlsx', 'xls', 'xlsb',
        'pdf',
        'csv', 'txt', 'tsv', 'md',
        'docx',
        'png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp',
    ];

    // A 'queued' row older than this has almost certainly not been picked up
    // by anything. Extraction is started by a detached CLI process
    // (SmartUnderwritingExtractJob::start) and re-kicked from this poll up to
    // REVIVE_MAX_ATTEMPTS times, so by 90s a row has had all 3 spawns and one
    // of them should have flipped it to 'processing'. Still 'queued' here
    // means the spawns themselves are failing (no CLI php, disable_functions)
    // and the queue fallback has no consumer either.
    private const PICKUP_WARN_SECONDS = 90;

    // Re-kick pacing for a row whose extractor never started. 40s > the time a
    // healthy spawn needs to write 'processing', so a live run is never
    // disturbed; 3 attempts land at ~0s / 40s / 80s, all inside the warn
    // window, so the operator usually never sees a stall message at all.
    private const REVIVE_STALE_SECONDS = 40;
    private const REVIVE_MAX_ATTEMPTS  = 3;

    // Hard ceiling before we declare the run dead. Must exceed the job's own
    // $timeout (1200s) plus the worker's, so a healthy long extraction is
    // never false-failed — the job writes no heartbeat while the engine runs.
    private const STALE_SECONDS = 1500;

    /** POST /api/v1/underwriting/smart-upload  (multipart: file) */
    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file'             => 'required|file|max:25600', // 25 MB
            // Where the extracted schedule is headed. Chosen by the operator
            // BEFORE upload — broker schedules rarely carry a Graphite policy
            // number, so this is never inferred from the file. Absent = new
            // business, which preserves the pre-picker behaviour.
            'target_mode'      => 'nullable|in:new_business,existing',
            'target_policy_id' => 'nullable|integer',
            'target_action_id' => 'nullable|integer',
        ]);

        $targetMode     = $validated['target_mode'] ?? 'new_business';
        $targetPolicyId = null;
        $targetActionId = null;

        if ($targetMode === 'existing') {
            // Both ids are mandatory in this mode, and the action must actually
            // belong to the policy — otherwise the review screen would load a
            // schedule into someone else's transaction.
            $targetPolicyId = $validated['target_policy_id'] ?? null;
            $targetActionId = $validated['target_action_id'] ?? null;
            if (!$targetPolicyId || !$targetActionId) {
                return response()->json([
                    'error' => 'Select a policy and an action before uploading.',
                ], 422);
            }
            $owns = DB::table('policy_actions')
                ->where('id', $targetActionId)
                ->where('policy_id', $targetPolicyId)
                ->whereNull('deleted_at')
                ->exists();
            if (!$owns) {
                return response()->json([
                    'error' => 'That action does not belong to the selected policy.',
                ], 422);
            }
        }

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, self::ACCEPT, true)) {
            return response()->json([
                'error' => 'Cannot read a .' . $ext . ' file. Accepted: '
                    . implode(', ', array_map(fn ($e) => '.' . $e, self::ACCEPT))
                    . '. A .doc must be saved as .docx or PDF first; a .tif as PNG or JPEG.',
            ], 422);
        }

        // Store under a per-upload prefix (mirrors ExcelImportController path shape).
        $stamp = now()->format('YmdHis');
        $key   = "smart_uw_uploads/{$stamp}_" . Str::random(6) . "/" . $file->getClientOriginalName();
        // Explicit disk. This is a transient working file that the extraction
        // job reads back once — NOT the record copy, which the underwriter
        // already uploads under the policy attachments section. 'local' is
        // storage/app, the EFS mount shared with the smartuw-worker container.
        // Never the DEFAULT disk: on deployed envs that resolves to the legacy
        // ap-south-1 bucket and died with InvalidAccessKeyId (2026-08-21).
        Storage::disk('local')->put($key, file_get_contents($file->getRealPath()));

        $id = DB::table('smart_uw_uploads')->insertGetId([
            'uploaded_file' => $key,
            'original_name' => $file->getClientOriginalName(),
            'file_ext'      => $ext,
            'target_mode'      => $targetMode,
            'target_policy_id' => $targetPolicyId,
            'target_action_id' => $targetActionId,
            'status'        => 'queued',
            'added_by'      => auth()->id(),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Detached CLI kick, NOT a bare dispatch(): the smartuw queue has
        // repeatedly had no consumer on the deployed envs (supervisord pins
        // `queue:work redis` while QUEUE_CONNECTION is `database`), which is
        // what left this screen spinning "Waiting for an extraction worker".
        // start() falls back to the queue when shell spawning is unavailable.
        SmartUnderwritingExtractJob::start($id);

        return response()->json([
            'job_id'  => $id,
            'status'  => 'queued',
            'message' => 'Schedule received. Extraction started.',
        ]);
    }

    /**
     * GET /api/v1/underwriting/smart-upload/policy-lookup?policy_number=...
     *
     * Feeds the "existing policy" branch of the target picker: the operator
     * types a policy number, we return the matching policies plus each one's
     * actions so the date dropdown can be populated.
     *
     * Deliberately slim — PolicyController::actions() scans 14 specialist
     * coverage tables and the PDF-job table per call, which this picker does
     * not need. Partial match so a half-typed number still finds the policy.
     */
    public function policyLookup(Request $request): JsonResponse
    {
        $request->validate([
            'policy_number' => 'required|string|max:64',
        ]);
        $needle = trim($request->query('policy_number', ''));
        if ($needle === '') {
            return response()->json(['policies' => []]);
        }

        $policies = DB::table('policies')
            ->where('policyNumber', 'like', '%' . $needle . '%')
            ->orderByRaw('CASE WHEN policyNumber = ? THEN 0 ELSE 1 END', [$needle])
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get(['id', 'policyNumber', 'customer_id', 'product_id', 'status',
                   'term_start_date', 'term_end_date', 'expiry_date']);

        if ($policies->isEmpty()) {
            return response()->json(['policies' => []]);
        }

        $policyIds = $policies->pluck('id')->all();

        // Actions for every hit in one query — no N+1 across the 10 rows.
        // Chronological order is (effective_from, id): same-date transactions
        // tie-break on id, which is the ordering the rest of the app uses.
        $actions = DB::table('policy_actions')
            ->whereIn('policy_id', $policyIds)
            ->whereNull('deleted_at')
            ->orderBy('effective_from', 'asc')
            ->orderBy('id', 'asc')
            ->get(['id', 'policy_id', 'transaction_type', 'status',
                   'effective_from', 'effective_to', 'transaction_reason'])
            ->groupBy('policy_id');

        // Customer / product labels so the operator can confirm they picked the
        // right policy before uploading a schedule into it. Table is 'customer'
        // (singular) — see AlphaDirect\Customer::$table.
        $customerIds = $policies->pluck('customer_id')->filter()->all();
        $customers = DB::table('customer')
            ->whereIn('id', $customerIds)
            ->pluck(DB::raw("TRIM(CONCAT(COALESCE(firstName,''),' ',COALESCE(lastName,'')))"), 'id');

        // Organisations carry no first/last name — the trading name lives on
        // companies.name via customer_profile.company_id. A commercial schedule
        // (e.g. DIESEL HEADS MECHANICS) would otherwise show a blank label.
        $companyNames = DB::table('customer_profile')
            ->join('companies', 'companies.id', '=', 'customer_profile.company_id')
            ->whereIn('customer_profile.customer_id', $customerIds)
            ->pluck('companies.name', 'customer_profile.customer_id');

        $products = DB::table('products')
            ->whereIn('id', $policies->pluck('product_id')->filter()->all())
            ->pluck('name', 'id');

        return response()->json([
            'policies' => $policies->map(fn($p) => [
                'id'            => $p->id,
                'policy_number' => $p->policyNumber,
                // Company name wins for organisations; fall back to the
                // person name, then to null rather than an empty string.
                'customer_name' => ($companyNames[$p->customer_id] ?? null)
                    ?: (($customers[$p->customer_id] ?? null) ?: null),
                'product_id'    => $p->product_id,
                'product_name'  => $products[$p->product_id] ?? null,
                'status'        => $p->status,
                'term_start_date' => $p->term_start_date,
                'term_end_date'   => $p->term_end_date ?? $p->expiry_date,
                'actions'       => array_values(($actions[$p->id] ?? collect())->map(fn($a) => [
                    'id'                 => $a->id,
                    'transaction_type'   => $a->transaction_type,
                    'status'             => $a->status,
                    'effective_from'     => $a->effective_from,
                    'effective_to'       => $a->effective_to,
                    'transaction_reason' => $a->transaction_reason,
                ])->all()),
            ])->values(),
        ]);
    }

    /** GET /api/v1/underwriting/smart-upload/{id} — poll status + result */
    public function status(int $id): JsonResponse
    {
        $row = DB::table('smart_uw_uploads')->where('id', $id)->first();
        if (!$row) {
            return response()->json(['error' => 'not found'], 404);
        }
        $extractions = DB::table('smart_uw_extractions')
            ->where('upload_id', $id)
            ->get(['id', 'segment_name', 'extracted_json', 'confidence', 'provider', 'discrepancies', 'human_verified']);

        // ── Liveness ──────────────────────────────────────────────────────
        // Poll-side watchdog. The job's failed() hook covers a job that dies
        // with Laravel watching; it cannot cover a job that was never picked
        // up (no smartuw worker) or one whose worker vanished with the whole
        // container. Both leave the row queued/processing forever, which the
        // frontend reads as "still working" and polls until the tab is closed.
        $status  = $row->status;
        $stalled = false;
        $note    = null;
        if (in_array($status, ['queued', 'processing'], true)) {
            $since = strtotime((string) ($row->updated_at ?? $row->created_at));
            $waited = $since ? max(0, time() - $since) : 0;

            // Self-healing, driven by this poll and nothing else. A row still
            // 'queued' means its detached extractor never started, so start
            // another one — no cron entry and no queue worker involved (see
            // SmartUnderwritingExtractJob::reviveIfStalled). Only 'queued' rows
            // are touched, so an extraction already in flight is never
            // disturbed, and the attempt counter caps the retries.
            if ($row->status === 'queued') {
                SmartUnderwritingExtractJob::reviveIfStalled(
                    $id, self::REVIVE_STALE_SECONDS, self::REVIVE_MAX_ATTEMPTS
                );
            }

            if ($waited > self::STALE_SECONDS) {
                // Give up and persist it, so every other poller / the uploads
                // list agrees and nobody waits on this row again.
                $status = 'failed';
                $note = $row->status === 'queued'
                    ? 'Extraction never started — the extractor process could not be '
                        . 'launched (' . self::REVIVE_MAX_ATTEMPTS . ' attempts) and no '
                        . 'worker consumed the "smartuw" queue either. Recover with '
                        . '`php artisan smartuw:run ' . $id . '`.'
                    : 'Extraction stopped responding and exceeded the '
                        . self::STALE_SECONDS . 's limit. Check the smartuw-worker '
                        . 'and smartuw-engine logs.';
                DB::table('smart_uw_uploads')->where('id', $id)
                    ->whereIn('status', ['queued', 'processing'])
                    ->update(['status' => 'failed', 'message' => $note, 'updated_at' => now()]);
            } elseif ($row->status === 'queued' && $waited > self::PICKUP_WARN_SECONDS) {
                // Not fatal yet — but say so instead of animating a fake
                // "AI is reading the workbook" while nothing has started.
                $stalled = true;
                $note = 'Still waiting for the extraction engine to start ('
                    . $waited . 's). Restart attempts are running automatically; if this '
                    . 'does not clear, the extractor process cannot be launched on the '
                    . 'backend and the smartuw queue has no worker.';
            }
        }

        return response()->json([
            'job_id'  => $id,
            'status'  => $status,                // queued|processing|completed|failed
            'risks'   => $extractions,           // one per sheet/segment
            'message' => $note ?? ($row->message ?? null),
            // Lets the UI say "waiting for worker" vs "AI is reading" instead
            // of showing one indistinguishable spinner for both.
            'stalled' => $stalled,
            // Echoes the operator's pre-upload choice so "Review & Issue" knows
            // whether to open the create wizard or an existing policy's action.
            'target'  => [
                'mode'      => $row->target_mode ?? 'new_business',
                'policy_id' => $row->target_policy_id ?? null,
                'action_id' => $row->target_action_id ?? null,
                // Set when the schedule's INSURED is not the target policy's
                // holder. The wizard blocks on this too, but saying it here
                // stops the operator opening the wrong policy at all.
                'insured_mismatch' => $this->insuredMismatch($row, $extractions),
            ],
        ]);
    }

    /**
     * Is this schedule's insured someone other than the target policy's holder?
     *
     * The INSURED on a broker schedule is the policy name — the company on a
     * commercial policy. Nothing downstream can catch a mix-up: the sections,
     * sums insured and premiums are all perfectly valid, just filed against
     * another client. So the two names are compared here, and the review screen
     * refuses to hand the schedule to a policy that does not match.
     *
     * Only for target_mode=existing — new business has no holder to compare
     * against yet, and the operator types the customer themselves.
     *
     * @param  \Illuminate\Support\Collection  $extractions
     * @return array{insured: string, policy: string}|null
     */
    private function insuredMismatch(object $row, $extractions): ?array
    {
        if (($row->target_mode ?? 'new_business') !== 'existing' || empty($row->target_policy_id)) {
            return null;
        }

        // The first segment that names an insured at all. Every segment of one
        // schedule carries the same insured; a later branch sheet may omit it.
        $insured = '';
        foreach ($extractions as $ex) {
            $json = json_decode((string) ($ex->extracted_json ?? ''), true);
            $name = trim((string) (($json['customer']['name'] ?? '')));
            if ($name !== '') {
                $insured = $name;
                break;
            }
        }
        if ($insured === '') {
            return null;
        }

        $holder = $this->policyHolderName((int) $row->target_policy_id);
        if ($holder === '' || $this->sameEntity($insured, $holder)) {
            return null;
        }

        return ['insured' => $insured, 'policy' => $holder];
    }

    /**
     * The policy's own name: the company for an organisation, else the person.
     *
     * Same resolution policyLookup() uses two methods up — organisations carry
     * no first/last name, their trading name lives on companies.name via
     * customer_profile.company_id, and the person table is 'customer'
     * (singular, camelCase columns).
     */
    private function policyHolderName(int $policyId): string
    {
        $customerId = DB::table('policies')->where('id', $policyId)->value('customer_id');
        if (!$customerId) {
            return '';
        }

        $company = DB::table('customer_profile')
            ->join('companies', 'companies.id', '=', 'customer_profile.company_id')
            ->where('customer_profile.customer_id', $customerId)
            ->value('companies.name');
        if (!empty($company)) {
            return trim((string) $company);
        }

        $person = DB::table('customer')->where('id', $customerId)->first(['firstName', 'lastName']);

        return trim(implode(' ', array_filter([
            (string) ($person->firstName ?? ''),
            (string) ($person->lastName ?? ''),
        ])));
    }

    /**
     * Same party? Forgiving about legal form, strict about identity.
     *
     * "Diesel Heads Mechanics (Pty) Ltd" and "DIESEL HEADS MECHANICS PTY LTD"
     * are one insured; the suffixes say nothing about who they are. So the
     * comparison runs on the IDENTIFYING words only — every legal-form word is
     * removed first, on both sides, before anything is counted.
     *
     * That last part is the whole trick. An earlier version stripped the noise
     * for the equality check but then fell back to a word overlap computed on
     * the RAW names, where "PTY" and "LTD" counted as two words of agreement:
     * "Alpha (Pty) Ltd" and "Beta (Pty) Ltd" scored 2/3 and were accepted as
     * the same insured. Two unrelated companies, one policy.
     *
     * Mirrors insuredMatchesPolicy() in CreateWizard/smartUwPrefill.ts — keep
     * the two in step.
     */
    private function sameEntity(string $a, string $b): bool
    {
        // Only a genuinely empty name means "nothing to compare". A name made
        // ENTIRELY of legal-form words ("The Group Holdings") is still a name
        // and must not match everything.
        if (trim($a) === '' || trim($b) === '') {
            return true;
        }

        $wa = $this->identifyingWords($a);
        $wb = $this->identifyingWords($b);

        // Nothing but noise on either side: compare what was actually written.
        if (!$wa || !$wb) {
            $compact = static fn (string $v): string
                => (string) preg_replace('/[^A-Z0-9]/', '', strtoupper($v));

            return $compact($a) === $compact($b);
        }

        // Whole-word containment only. Substrings accepted "Smith" as
        // "Smithson Enterprises".
        sort($wa);
        sort($wb);
        if ($wa === $wb || !array_diff($wa, $wb) || !array_diff($wb, $wa)) {
            return true;
        }

        // Overlap on the UNIQUE identifying words — counting duplicates let
        // "Alpha Alpha Trading" score 2/3 against "Alpha Beta Gamma".
        $hits = count(array_intersect($wa, $wb));

        return ($hits / max(count($wa), count($wb))) >= 0.6;
    }

    /** An entity name reduced to the words that identify it, de-duplicated. */
    private function identifyingWords(string $value): array
    {
        // Legal form and grammar only. // NOT noise: HOLDINGS and GROUP. They identify a company —
                  // "Kalahari Holdings" is not "Kalahari Transport" — and
                  // stripping them collapsed the first into a subset of the
                  // second, matching two unrelated firms.
        $noise = ['PTY', 'PROPRIETARY', 'LTD', 'LIMITED', 'INC', 'INCORPORATED',
                  'CC', 'CLOSECORPORATION', 'TA', 'TRADINGAS', 'THE', 'AND'];

        // Fold accents first. Without it "Sefalana Café" stripped to "CAF" and
        // no longer matched "Sefalana Cafe" — a spelling difference reading as
        // a different company.
        // iconv's TRANSLIT spells "Café" as "Caf'e" on some builds, which then
        // splits into two words — worse than the accent it replaced. Strip the
        // quote artefacts it introduces before splitting.
        $folded = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $folded = str_replace(["'", '`', '^', '"', '~'], '', $folded !== false ? $folded : $value);
        $value = strtoupper(str_replace('&', ' AND ', $folded));
        $value = (string) preg_replace('/[^A-Z0-9]+/', ' ', $value);
        $words = array_filter(explode(' ', trim($value)), static fn ($w) => $w !== '');

        return array_values(array_unique(array_diff($words, $noise)));
    }


    /**
     * PATCH /api/v1/underwriting/smart-upload/extraction/{id}
     *
     * Save the underwriter's own classification of a segment.
     *
     * The reader never guesses a bucket it is unsure of: a line it cannot
     * place is left in unclassified[], and a line it placed without confidence
     * carries needs_check. Both are decided by a person on the review screen —
     * this is where that decision is kept, so it survives a reload, is visible
     * to the next reviewer, and is what the wizard applies.
     *
     * It writes ONLY the extraction row. Nothing here touches a policy: the
     * amended schedule still goes to the policy through the wizard's own
     * endpoints (smartUwApply), so every backend validator applies exactly as
     * if the underwriter had typed it.
     *
     * discrepancies and confidence are recomputed from the saved JSON rather
     * than trusted from the client — a placement that clears the last
     * exception should clear the amber panel with it, and a client-supplied
     * confidence figure would be unauditable.
     */
    public function saveExtraction(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            // The whole risk object as the review screen now holds it.
            'extracted_json'  => 'required|array',
            'human_verified'  => 'nullable|boolean',
        ]);

        $row = DB::table('smart_uw_extractions')->where('id', $id)->first();
        if (!$row) {
            return response()->json(['error' => 'Extraction not found.'], 404);
        }

        $risk = $validated['extracted_json'];

        // A payload with none of the keys a risk carries is a client bug, and
        // storing it would replace a good extraction with rubbish.
        if (!array_intersect(['coverages', 'customer', 'motor', 'policy', 'unclassified'], array_keys($risk))) {
            return response()->json([
                'error' => 'That does not look like an extracted risk — nothing was saved.',
            ], 422);
        }

        // Keep the reader's own answer. This endpoint REPLACES extracted_json,
        // so without it the AI's original placement is gone the first time
        // anyone edits — and "what did the AI actually say before the
        // underwriter moved it?" is the question an audit asks. Stored once,
        // inside the row's own JSON, so no schema change: set on the FIRST
        // hand-edit and never touched again. A client-supplied copy is always
        // discarded — the original has to come from what is stored.
        unset($risk['_ai_original']);
        $stored = json_decode((string) $row->extracted_json, true);
        if (is_array($stored)) {
            $risk['_ai_original'] = $stored['_ai_original'] ?? $stored;

            // What the reader withheld from the model is the reader's record,
            // not the caller's. It survives a save today only because the
            // client happens to send back a deep clone — the same accident
            // that was losing `_from` — so take it from storage instead. The
            // underwriter must never stop being told which columns went.
            if (!empty($stored['_redacted_lines']) && is_array($stored['_redacted_lines'])) {
                $risk['_redacted_lines'] = array_values(array_map(
                    'strval', $stored['_redacted_lines']
                ));
            }
        }

        // Re-read, never trusted from the client: a hand edit that supplied a
        // missing insured name should CLEAR that error, and an _errors list
        // typed by the caller would be unauditable. The reader's own
        // non-structural notes (a truncated sheet, redacted PII lines) are not
        // reproducible by validate(), so they are carried across.
        $risk = PhpScheduleExtractor::normaliseClassification($risk);
        $carried = array_values(array_filter(
            is_array($risk['_errors'] ?? null) ? array_map('strval', $risk['_errors']) : [],
            static fn ($e) => str_contains($e, 'were read') || str_contains($e, 'PII')
        ));
        $errors = array_values(array_unique(array_merge(
            PhpScheduleExtractor::validate($risk), $carried
        )));
        $risk['_errors']  = $errors;
        $exceptions = PhpScheduleExtractor::classificationExceptions($risk);
        // Recorded on the row so the review screen and any later reader see
        // the same counts the extractor would have written.
        $risk['_exceptions'] = $exceptions;
        $discrepancies = array_values(array_merge($errors, $exceptions));

        DB::table('smart_uw_extractions')->where('id', $id)->update([
            'extracted_json' => json_encode($risk, JSON_UNESCAPED_UNICODE),
            'discrepancies'  => $discrepancies ? json_encode($discrepancies) : null,
            // Same scale the job writes: broken read 0.5, exceptions
            // outstanding 0.75, everything placed and checked 1.0.
            'confidence'     => !empty($errors) ? 0.5 : (!empty($exceptions) ? 0.75 : 1.0),
            'human_verified' => array_key_exists('human_verified', $validated)
                ? (bool) $validated['human_verified']
                : (bool) $row->human_verified,
            'updated_at'     => now(),
        ]);

        Log::info('Smart UW extraction reclassified by hand', [
            'extraction_id' => $id,
            'upload_id'     => $row->upload_id,
            'by'            => auth()->id(),
            'outstanding'   => count($exceptions),
        ]);

        return response()->json([
            'success'       => true,
            'outstanding'   => count($exceptions),
            'discrepancies' => $discrepancies,
            'message'       => count($exceptions) === 0
                ? 'Saved. Every line is classified.'
                : 'Saved. ' . count($exceptions) . ' line(s) still need your attention.',
        ]);
    }

    /**
     * GET /api/v1/underwriting/smart-upload/ai-config
     *
     * What the extractor will do on the next upload, without revealing a
     * single credential. Exists because the Credentials Vault UI lives in the
     * V1 admin panel, which BlockV1AdminPanel 404s unless
     * V1_ADMIN_PANEL_ENABLED is on — so on a deployed env there was no way to
     * see or set the AI provider key at all, and extraction failed with
     * "No Gemini API key" that nobody with app-only access could fix.
     *
     * Never returns a key value. Only whether one is present, and where it
     * came from — an env var beats the vault in VaultController::get(), so a
     * stale env value silently wins over anything saved here and the operator
     * has to be told that.
     */
    public function aiConfig(): JsonResponse
    {
        // Must match SmartUnderwritingExtractJob's own default — the card
        // showing one provider while the job used another is exactly the class
        // of confusion the prefer_php mismatch caused. DeepSeek is the mapping
        // engine (CFO, 2026-09-09).
        $provider = strtolower((string) VaultController::get(
            'smartuw_commercial_provider', env('SMARTUW_COMMERCIAL_PROVIDER', 'deepseek')));
        if (!in_array($provider, ['gemini', 'deepseek'], true)) {
            $provider = 'deepseek';
        }

        // Unset defaults to ON, matching SmartUnderwritingExtractJob::
        // preferInProcess(). These two read the same setting and MUST agree —
        // when they did not, the card showed the reader as in-process while
        // the job used the sidecar.
        $preferPhpRaw = (string) VaultController::get('smartuw_prefer_php', '');
        $preferPhp = $preferPhpRaw !== ''
            ? in_array(strtolower($preferPhpRaw), ['1', 'true', 'yes', 'on'], true)
            : (bool) env('SMARTUW_PREFER_PHP', true);

        $shared = AiConfigController::getSettings();

        return response()->json([
            'provider'            => $provider,
            // "Configured" = a key is available from either source. The job
            // reads the vault FIRST and falls back to env, so both count.
            'gemini_configured'   => trim((string) VaultController::get('gemini_api_key', '')) !== '',
            'deepseek_configured' => trim((string) VaultController::get('deepseek_api_key', '')) !== '',
            // Whether the value saved in the app exists, separately from the
            // env fallback, so the card can say which one is actually in use.
            'gemini_in_vault'     => trim((string) VaultController::getVaultOnly('gemini_api_key', '')) !== '',
            'deepseek_in_vault'   => trim((string) VaultController::getVaultOnly('deepseek_api_key', '')) !== '',
            'gemini_from_env'     => trim((string) env('GEMINI_API_KEY', '')) !== '',
            'deepseek_from_env'   => trim((string) env('DEEPSEEK_API_KEY', '')) !== '',
            // The KYC document reader's own credentials (AiConfigController::
            // getSettings — vault, then .env). PhpScheduleExtractor falls back
            // to these, so a container that reads KYC PDFs can read a schedule
            // with no Smart-UW-specific key at all. Reported separately so the
            // card can say "no key of its own, but it will use the KYC one"
            // instead of the flat red "extraction will fail".
            'shared_gemini'       => trim((string) ($shared['gemini_api_key'] ?? '')) !== '',
            'shared_anthropic'    => trim((string) ($shared['anthropic_api_key'] ?? '')) !== '',
            // Groq is the DEFAULT provider for KYC document reading
            // (AI_PROVIDER=groq), so on a container where only KYC has been
            // configured this is the key that exists. Text segments fall back
            // to it; a scanned PDF cannot (Groq vision takes no PDF).
            'shared_groq'         => trim((string) ($shared['groq_api_key'] ?? '')) !== '',
            'prefer_php'          => $preferPhp,
            // Whether anyone has actually CHOSEN this, as opposed to it being
            // the env default of false. The recommended state is ON, so a
            // never-configured install must not render the box unticked and
            // invite an operator to save a key alongside the reader that
            // cannot read their file.
            'prefer_php_set'      => $preferPhpRaw !== '',
            // Not a secret, and the single most useful diagnostic: with a
            // sidecar URL set, the Python engine handles the extraction and
            // hard-routes any segment carrying a "Contact Person" block to a
            // LOCAL model that is not deployed in the ECS task. The in-process
            // reader anonymises those lines and carries on, so an ordinary
            // broker schedule needs prefer_php ON.
            'engine_url'          => (string) env('SMARTUW_ENGINE_URL', ''),
        ]);
    }

    /**
     * POST /api/v1/underwriting/smart-upload/ai-config
     *
     * Store the commercial provider key (and the routing switches) in the
     * Credentials Vault, encrypted exactly as VaultController::save does, then
     * bust the vault cache so the next upload picks it up.
     *
     * The key travels in the request BODY over TLS and is never echoed back,
     * logged, or written to a task definition. That last point is the reason
     * this endpoint exists rather than an artisan command: the deploy
     * workflow's run-artisan job prints the command it runs into the GitHub
     * Actions log, so a key passed that way would be published to anyone who
     * can read the run.
     */
    public function saveAiConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Which provider the key belongs to, and which one extraction uses.
            'provider'   => 'required|in:gemini,deepseek',
            // Optional so the switches can be changed without re-entering the
            // key. min:20 rejects an obviously truncated paste.
            'api_key'    => 'nullable|string|min:20|max:512',
            'prefer_php' => 'nullable|boolean',
        ]);

        $provider = $validated['provider'];
        $saved = [];

        $this->putVault('smartuw_commercial_provider', $provider);
        $saved[] = 'provider';

        $key = trim((string) ($validated['api_key'] ?? ''));
        if ($key !== '') {
            // A key with whitespace or quotes pasted around it fails at the
            // provider with an opaque 400 — trim here rather than debug there.
            $this->putVault($provider . '_api_key', trim($key, " \t\n\r\0\x0B\"'"));
            $saved[] = $provider . ' key';
        }

        if (array_key_exists('prefer_php', $validated) && $validated['prefer_php'] !== null) {
            $this->putVault('smartuw_prefer_php', $validated['prefer_php'] ? '1' : '0');
            $saved[] = 'reader preference';
        }

        // Same cache key VaultController::save busts — without this the next
        // upload reads a stale decrypted snapshot for up to 10 minutes.
        Cache::forget('credential_vault_cache');

        Log::info('Smart UW AI config updated', [
            // Deliberately no value, not even a masked one.
            'by'       => auth()->id(),
            'provider' => $provider,
            'fields'   => $saved,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Saved: ' . implode(', ', $saved) . '. Re-upload the schedule to use it.',
        ]);
    }

    /**
     * Encrypt-and-upsert one vault row.
     *
     * category is only stamped when the row is created: the column is part of
     * how the vault UI groups its screen, and overwriting an existing row's
     * category would silently move a credential to another tab.
     */
    private function putVault(string $key, string $value): void
    {
        $exists = DB::table('credential_vault')->where('setting_key', $key)->exists();
        $row = [
            'setting_value' => Crypt::encryptString($value),
            'updated_at'    => now(),
        ];
        if (!$exists) {
            $row['setting_key'] = $key;
            $row['category']    = 'AI';
            $row['created_at']  = now();
            DB::table('credential_vault')->insert($row);

            return;
        }
        DB::table('credential_vault')->where('setting_key', $key)->update($row);
    }
}
