<?php
namespace AlphaDirect\Jobs;

use AlphaDirect\Models\V2PdfJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateQuotationPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
       public $timeout = 3000;
        public $tries   = 3;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    //public $tries = 2;

    protected $policyId, $termId, $actionId, $pdfJobId;

    /**
     * Heartbeat closure attached in handle() so generateWithDomPdf() can
     * write heartbeat_at between long PDF steps. Plain property (no type
     * hint) because Closure isn't serialisable and InteractsWithQueue
     * serialises the job — kept transient by living on a vanilla prop.
     */
    protected $beatFn = null;

    public function __construct($policyId, $termId, $actionId, $pdfJobId)
    {
       \Log::info('GenerateQuotationPdfJob __construct called', [
        'policyId' => $policyId,
        'termId'   => $termId,
        'actionId' => $actionId,
        'pdfJobId' => $pdfJobId,
    ]);

        $this->policyId = $policyId;
        $this->termId = $termId;
        $this->actionId = $actionId;
        $this->pdfJobId = $pdfJobId;
    }

    public function handle()
    {
        \Log::info("🔥 GenerateQuotationPdfJob: handle() STARTED");
        // Increase memory and time limits for large PDF generation
        ini_set('memory_limit', '2048M');
        ini_set('max_execution_time', 600); // 10 minutes
        set_time_limit(600); // 10 minutes

        $startTime = microtime(true);
        \Log::info("🔥 GenerateQuotationPdfJob: memory/time limits set, proceeding to load data");

        \Log::info('GenerateQuotationPdfJob handle started', [
            'pdfJobId'  => $this->pdfJobId,
            'policyId'  => $this->policyId,
            'termId'    => $this->termId,
            'actionId'  => $this->actionId,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
        ]);

        $job = V2PdfJob::find($this->pdfJobId);
        if ($job) {
            $job->status = 'processing';
            // Heartbeat at start so stale-reset never sees this row as
            // already-dead during the first minute of rendering. Schema
            // migration adds heartbeat_at; guard so older deploys (no
            // column yet) don't blow up.
            if (\Schema::connection('mysql_system')->hasColumn('v2_pdf_jobs', 'heartbeat_at')) {
                $job->heartbeat_at = now();
            }
            $job->save();
            // Refresh to ensure we have the latest data
            $job->refresh();
            \Log::info('GenerateQuotationPdfJob: Status updated to processing', [
                'pdfJobId' => $this->pdfJobId,
                'status' => $job->status,
            ]);
        }

        // ── Heartbeat at known checkpoints ──
        // The renderer is mostly synchronous (buildBladeData → PdfGenerator
        // → PDFMerger → StorageService::put). On big policies a single step
        // can run several minutes with zero DB writes — exactly the window
        // where the old `updated_at < NOW() - 10 min` stale-reset would
        // re-queue the row mid-render and spawn a duplicate worker.
        //
        // The $beat closure writes heartbeat_at = NOW() and is called at
        // every meaningful pipeline boundary (data load done, render done,
        // merge done, store done). A truly-dead worker won't reach the
        // next checkpoint, so the stale-reset sweep finds heartbeat older
        // than 2 minutes and recycles the row. A slow-but-alive worker
        // keeps beating between steps and is never killed.
        $hasHeartbeatCol = \Schema::connection('mysql_system')->hasColumn('v2_pdf_jobs', 'heartbeat_at');
        $jobId = $this->pdfJobId;
        $beat = function (string $checkpoint = '') use ($hasHeartbeatCol, $jobId) {
            if (!$hasHeartbeatCol) return;
            try {
                \DB::connection('mysql_system')->table('v2_pdf_jobs')
                    ->where('id', $jobId)
                    ->update(['heartbeat_at' => now()]);
            } catch (\Throwable $e) {
                // Heartbeat write failure must NOT abort the render.
                \Log::warning("GenerateQuotationPdfJob heartbeat failed at {$checkpoint}: ".$e->getMessage());
            }
        };
        // Store on instance so generateWithDomPdf() can also beat between
        // its long-running PDF steps without re-resolving the closure.
        $this->beatFn = $beat;

        try {
            // Include the unique pdfJobId in the filename. time() is only
            // second-resolution, and the date-range Policy Doc feature kicks
            // off one worker per action simultaneously — without the job id,
            // concurrent renders compute the SAME filename and overwrite each
            // other on storage, so every action's Documents-tab row ended up
            // pointing at a single surviving PDF (all actions showed the same
            // document). pdfJobId is unique per job, guaranteeing distinct keys.
            $fileName = 'COMG' . $this->policyId . '_' . $this->pdfJobId . '_' . time() . '_quote_sheet.pdf';

            \Log::info('GenerateQuotationPdfJob: Starting PDF generation', [
                'pdfJobId' => $this->pdfJobId,
                'fileName' => $fileName,
                'memory_usage_before' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            ]);

            // V2-native render via DomPDF + Blade templates — NO legacy merge.
            // The prior path called Admin\PolicyController::v2_quotationPdf* which
            // merges static PDFs from storage/app/CoverageWiseMultimark/. Those
            // assets are not deployed to the V2 container, so generation failed
            // silently for Engineering/Specialist/PI products with the error
            // "Legacy quote generator likely failed on a static asset". The
            // generateWithDomPdf() helper below picks the right Blade template
            // per product_id and writes the result directly — no merge step.
            $productId = (int) (\AlphaDirect\Policy::where('id', $this->policyId)->value('product_id') ?? 0);
            \Log::info("GenerateQuotationPdfJob: rendering via V2 DomPDF (product {$productId})");

            $genStart = microtime(true);
            if ($this->beatFn) ($this->beatFn)('before-render');
            $this->generateWithDomPdf(
                $this->policyId, $this->termId, $this->actionId, $fileName
            );
            if ($this->beatFn) ($this->beatFn)('after-render');
            $genTime = round((microtime(true) - $genStart) * 1000, 2);

            \Log::info('GenerateQuotationPdfJob: PDF generation call returned', [
                'pdfJobId' => $this->pdfJobId,
                'fileName' => $fileName,
                'generation_time_ms' => $genTime,
                'memory_usage_after' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            ]);

            // Post-run guard: the legacy v2_quotationPdf* methods can throw mid-
            // flight (e.g. a PDFMerger::merge() on a missing static asset) and
            // still return without re-raising — leaving us about to mark the
            // job "completed" when nothing was actually written to disk.
            // Check the expected path + 'public' disk + S3 before claiming success.
            $expectedPath = 'quote_sheet/' . $fileName;
            $expectedAbs  = storage_path('app/public/' . $expectedPath);
            $fileExists   = file_exists($expectedAbs);
            if (!$fileExists) {
                try {
                    if (\Illuminate\Support\Facades\Storage::disk('public')->exists($expectedPath)) $fileExists = true;
                } catch (\Throwable $e) { /* ignore */ }
            }
            if (!$fileExists) {
                try {
                    if (\Illuminate\Support\Facades\Storage::disk('s3')->exists($expectedPath)) $fileExists = true;
                } catch (\Throwable $e) { /* ignore */ }
            }

            if (!$fileExists) {
                $msg = "Generation finished but no PDF was written at {$expectedAbs}. "
                     . 'Legacy quote generator likely failed on a static asset (e.g. storage/app/CoverageWiseMultimark/*.pdf not deployed to this container) '
                     . 'or encountered a silent PDFMerger error. Check Laravel logs around this timestamp.';
                \Log::error('GenerateQuotationPdfJob: ' . $msg, ['pdfJobId' => $this->pdfJobId]);
                if ($job) {
                    $job->status  = 'failed';
                    $job->message = substr($msg, 0, 500);
                    $job->save();
                }
                return; // do not notify; do not mark completed
            }

            // ── Binary verification ──
            // PdfGeneratorService falls back Puppeteer → Snappy → DomPDF and
            // PDFMerger silently skips files it can't parse. A "completed"
            // status could mean: zero-byte file, HTML error page mislabelled
            // as .pdf, missing wordings, or a single-page render where pages
            // 2-N were dropped. Verify the bytes before telling the user
            // "Ready":
            //   1. starts with %PDF-1.x signature  (rules out HTML/JSON garbage)
            //   2. size >= 5 KB                    (a real quote sheet is >100 KB)
            //   3. last 1 KB contains %%EOF        (truncated writes lack it)
            //
            // Verification walks local → public disk → S3 — same order as the
            // file-exists check above. On normal PROD the file is on S3 (S3
            // is the StorageService's preferred disk), so a local-only check
            // would falsely flag every successful render as "failed" — which
            // is exactly the regression Phase 2 caused before this edit.
            $verifyReason = $this->verifyPdfBinaryAcrossDisks($expectedPath);
            if ($verifyReason !== null) {
                \Log::error('GenerateQuotationPdfJob: binary verification failed', [
                    'pdfJobId' => $this->pdfJobId,
                    'reason'   => $verifyReason,
                    'path'     => $expectedPath,
                ]);
                if ($job) {
                    $job->status  = 'failed';
                    $job->message = 'PDF verification failed: ' . $verifyReason;
                    $job->save();
                }
                return; // do not notify; do not mark completed
            }

            $totalTime = round((microtime(true) - $startTime) * 1000, 2);
            \Log::info("PDF PROFILE: TOTAL TIME", ['ms' => $totalTime, 'pdfJobId' => $this->pdfJobId]);

            if ($job) {
                $job->status    = 'completed';
                $job->file_name = $fileName;
                $job->message   = 'PDF ready for download.';
                $job->save();

                // Send notification to the user who requested it
                $policy = \AlphaDirect\Policy::find($this->policyId);
                $requestedBy = auth()->id() ?? $job->requested_by ?? 1;
                \AlphaDirect\Http\Controllers\Api\V1\NotificationController::notify(
                    $requestedBy,
                    'pdf_ready',
                    [
                        'title'       => 'V2 Quote Sheet Ready',
                        'message'     => 'Quote sheet for ' . ($policy->policyNumber ?? $this->policyId) . ' is ready for download.',
                        'policy_id'   => $this->policyId,
                        'policy_number' => $policy->policyNumber ?? '',
                        'job_id'      => $this->pdfJobId,
                        'file_name'   => $fileName,
                    ],
                    '/policies/' . $this->policyId
                );
            }
        } catch (\Throwable $e) {
            \Log::error('GenerateQuotationPdfJob failed', [
                'pdfJobId' => $this->pdfJobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            if ($job) {
                $job->status = 'failed';
                $job->message = $e->getMessage() . ' | Line: ' . $e->getLine() . ' | File: ' . basename($e->getFile());
                $job->save();
                $job->refresh();
                \Log::info('GenerateQuotationPdfJob: Status updated to failed', [
                    'pdfJobId' => $this->pdfJobId,
                    'status' => $job->status,
                ]);
            }

            throw $e;
        }
    }

    /**
     * Called by Laravel when the job permanently fails (all retries exhausted
     * OR the worker's --timeout kills it). Keep V2PdfJob status in sync so
     * the UI's Document Jobs page shows 'failed' instead of stuck at
     * 'processing' / 'queued'.
     */
    public function failed(\Throwable $exception): void
    {
        try {
            $job = V2PdfJob::find($this->pdfJobId);
            if ($job && !in_array($job->status, ['completed', 'failed'])) {
                $msg = $exception->getMessage() . ' | Line: ' . $exception->getLine()
                     . ' | File: ' . basename($exception->getFile());
                $job->status  = 'failed';
                $job->message = substr($msg, 0, 500);
                $job->save();
            }
            \Log::error('GenerateQuotationPdfJob permanently failed', [
                'pdfJobId' => $this->pdfJobId,
                'policyId' => $this->policyId,
                'error'    => $exception->getMessage(),
            ]);
        } catch (\Throwable $e) {
            \Log::error('failed() handler itself threw: ' . $e->getMessage());
        }
    }

    /**
     * Build the full $array passed to v2-quote-sheet.blade.php (and its
     * engineering / specialist variants). Extracted so synchronous callers
     * — PolicyCreateController::streamPolicyPdf for DomCom Policy Doc —
     * can render the same blade with the same data without duplicating
     * 250 lines of relation-eager-loading / pro-rata calc.
     *
     * Returns ['policy' => Policy, 'action' => PolicyAction|null,
     * 'term' => PolicyTerm|null, 'policy_coverages' => Collection,
     * 'array' => array (the blade payload, with QuoteSheetDefaults merged)].
     */
    public static function buildBladeData(int $policyId, ?int $termId, ?int $actionId): array
    {
        $policy = \AlphaDirect\Policy::with(['customer', 'profile', 'profile.states', 'profile.cities', 'profile.company', 'product', 'user', 'agency'])->findOrFail($policyId);
        $action = $actionId ? \AlphaDirect\Models\PolicyAction::find($actionId) : \AlphaDirect\Models\PolicyAction::where('policy_id', $policyId)->orderBy('id', 'desc')->first();
        $term = $termId ? \AlphaDirect\PolicyTerm::find($termId) : \AlphaDirect\PolicyTerm::where('policy_id', $policyId)->orderBy('id', 'desc')->first();

        $policy_coverages = \AlphaDirect\Models\PolicyCoverage::with([
                'coverage',
                'coverageDetail' => fn($q) => $q->select('*'), // explicitly select all columns including coverage_value_string
                'coverageDetail.coverage',
                'riskAddress',
                'extentionDetail',
                'extentionDetail.extention',
                'specifedItems',
                'note'
            ])
            ->where('policy_id', $policyId)
            ->when($action, fn($q) => $q->where('action_id', $action->id))
            // ENDORSE: keep coverages soft-deleted DURING this action so the
            // sheet still shows the removed cover at P 0.00 instead of dropping
            // it. Mirrors the motor cancelled-vehicle carve-out
            // (v2-policy-schedule 959-999): non-motor coverages had no such
            // exception, so a coverage deleted in an endorsement silently
            // vanished from the schedule. Scoped to the CURRENT action via the
            // action_id filter above — only rows deleted in THIS endorse are
            // re-included; deletions from prior actions keep their own
            // action_id and are unaffected. The trashed rows are marked
            // status = 1 in the loop below so the blade renders them at 0.
            //
            // ...except action_id says which action a row BELONGS to, not
            // which action DELETED it, so withTrashed() alone also resurrected
            // rows this endorse never cancelled — a duplicate soft-deleted by
            // policy:cleanup-duplicate-coverages, or a replicated-then-removed
            // copy. Those rendered as a full section at "Total of subcoverages
            // P 0.00" (coverage sections carry no cancelled styling, unlike
            // motor rows), which operators correctly read as a deleted
            // coverage still showing on the policy. visibleForEndorse() keeps
            // the documented cancel and drops the ghosts.
            ->when(($action->transaction_type ?? null) === 'ENDORSE',
                fn($q) => $q->withTrashed()->visibleForEndorse($action->id))
            ->orderBy('risk_address_id')->orderBy('coverage_id')->get();

        // Two policy_coverages rows for the SAME (coverage, risk address) on
        // this action printed the coverage TWICE — the second one an empty
        // header reading "Total of subcoverages P 0.00" — even though the Edit
        // page listed it once (Buildings Combined, COM policy 136286). The Edit
        // wizard hides that shell row in editData()'s dedup pipe; this loader
        // had no equivalent, so quote and edit disagreed. Same rule, shared:
        // shells (no child rows in ANY bucket, zero value) are dropped only
        // while a sibling copy carries data, and soft-deleted rows kept by
        // visibleForEndorse() are never touched.
        $policy_coverages = \AlphaDirect\Models\PolicyCoverage::dropEmptyDuplicates($policy_coverages);

        // Engineering products (16 COM, 18 DOM): pre-load specialist coverage
        // rows so v2-quote-sheet-engineering's `erection_all_risk` /
        // `contractors_all_risk` / `plant_all_risk` partials can read
        // $coverages->earCoverage etc. The partials are shared with the
        // engineering-policy-document blade (which attaches these the same
        // way) so V2 Quote Sheet now mirrors the Policy Document layout.
        $isEngineering = in_array($policy->product_id, [16, 17, 18, 19], true);
        $earCovRow = $carCovRow = $parCovRow = null;
        if ($isEngineering) {
            // Scope the EAR/CAR/PAR schedule rows to the CURRENT action's
            // policy_coverages. {ear,car,par}_coverages.policy_coverage_id
            // points at the exact policy_coverages row — the same key the Edit
            // PAR/EAR/CAR screen and the save-upsert use (SpecialistCoverage
            // Controller). A policy that was unissued & recaptured (or renewed)
            // can carry several par_coverages rows; selecting the global latest
            // id rendered a STALE schedule on the quote even though the edit
            // screen showed the recaptured items. Prefer the row tied to this
            // action's coverages, falling back to latest-by-id so policies
            // whose rows predate policy_coverage_id still render.
            $engPcIds = $policy_coverages->pluck('id')->filter()->values()->all();
            $pickSpecialistRow = function (string $table) use ($policyId, $engPcIds) {
                if (!empty($engPcIds)) {
                    $scoped = \DB::table($table)
                        ->where('policy_id', $policyId)
                        ->whereIn('policy_coverage_id', $engPcIds)
                        ->orderBy('id', 'desc')->first();
                    if ($scoped) return $scoped;
                }
                return \DB::table($table)->where('policy_id', $policyId)->orderBy('id', 'desc')->first();
            };
            $earCovRow = $pickSpecialistRow('ear_coverages');
            $carCovRow = $pickSpecialistRow('car_coverages');
            $parCovRow = $pickSpecialistRow('par_coverages');
        }

        // Pre-fetch all sub-coverage data at once to avoid N+1 queries
        $parentCodes = collect($policy_coverages)
            ->map(fn($c) => $c->coverage?->s_CoverageCode)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $allSubCoverages = $parentCodes
            ? \AlphaDirect\Models\CoverageMaster::whereIn('s_ParentCoverageCode', $parentCodes)
                ->where('s_DISPLAYTOUSER', '1')->where('s_UsageType', 'CHILD')
                ->where('isDocDisplay', 'Y')->orderBy('n_DisplaySequence')->get()
                ->groupBy('s_ParentCoverageCode')
            : collect();

        // Cancel Sub Coverage endorse: subcoverages are intentionally zeroed
        // (SI/Premium = 0) but must still RENDER on the V2 Quote / Policy
        // Document (showing the 0) instead of being dropped by the empty-row
        // filter below. Scoped to this reason only — every other action keeps
        // the "drop 0/0 rows" behaviour. Mirrors editData()'s display rule.
        // Scoped to DomCom products (7, 8) only.
        $reasonNormForCancel = strtolower(preg_replace('/\s+/', '', (string) ($action->transaction_reason ?? '')));
        $isCancelSubEndorse = $action
            && in_array((int) ($policy->product_id ?? 0), [7, 8], true)
            && $action->transaction_type === 'ENDORSE'
            && in_array($reasonNormForCancel, ['subcovcancel', 'cancelsubcoverage'], true);

        // DomCom (product 7/8) ONLY: coverage premium counts ONLY type='Extention'
        // (all other types excluded).
        $isDomCom = in_array((int) ($policy->product_id ?? 0), [7, 8], true);

        foreach ($policy_coverages as $cov) {
            // A coverage soft-deleted DURING this endorse is re-included above
            // via withTrashed(). The sheet already renders a removed/cancelled
            // cover at P 0.00 off `status == 1` (v2-quote-sheet 2615/2891/
            // 3043/3130), so flag the trashed row cancelled IN MEMORY (never
            // persisted) to reuse that existing path — no blade changes needed.
            // Its child rows (detail/motor/ext/specified/Fidelity data) are all
            // soft-deleted too, so every premium bucket already sums to 0.
            if ($cov->trashed()) {
                $cov->status = 1;
            }

            // Mirror PolicyCreateController::editData()'s sub-coverage filter so the
            // V2 Quote / Policy Document render the SAME rows as the edit page:
            // drop empty detail rows (coverage_value AND calculated_value both 0).
            // These are stale duplicate policy_coverage_detail rows left by older
            // save bugs — the edit page hides them, but the quote rendered them,
            // doubling sub-coverage rows on the V2 Quote/Doc.
            // EXCEPTION: a Cancel Sub Coverage endorse keeps non-soft-deleted
            // rows even at 0/0 so the cancelled cover shows with its 0 value.
            // EXCEPTION: the two descriptor rows the blade prints as a section
            // HEADER rather than a table line — "Basis of cover" (Public
            // Liability: Claims Made / Claims Occurring) and "Means of
            // Conveyance" (Goods In Transit). Both carry the pick solely in
            // limit_id with SI and premium left at 0, so the ">0" test dropped
            // them here and the header loop at v2-quote-sheet.blade.php:2743
            // iterated nothing — printing "Basis of cover :" with no value even
            // though the DB held the pick and the edit page showed it.
            // editData() grew an escape hatch for these rows in 8145319d9; this
            // copy did not, and drifted.
            // Deliberately narrower than editData's (which also spares
            // coverage_value_string / ratefactor_* rows): restricted to these two
            // screen names because EVERY itemised-table branch in the blade
            // excludes them by name, so a re-included row can only ever feed the
            // header and can never add a line — including the Computer Equipment
            // branch at :3756, which renders rows with no money guard at all.
            // Both rows are 0/0, so the "Total of subcoverages" sums below are
            // unchanged.
            $headerOnlyRows = ['Basis of cover', 'Means of Conveyance'];
            $cov->setRelation('coverageDetail', $cov->coverageDetail->filter(
                fn($d) => (float) ($d->coverage_value ?? 0) > 0 || (float) ($d->calculated_value ?? 0) > 0
                    || ($isCancelSubEndorse && $d->deleted_at === null)
                    || ($d->deleted_at === null
                        && in_array($d->coverage?->s_ScreenName, $headerOnlyRows, true)
                        && !in_array(trim((string) ($d->limit_id ?? '')), ['', '0'], true))
            )->values());

            $master = $cov->coverage;
            $cov->all_sub_coverages = $master
                ? $allSubCoverages->get($master->s_CoverageCode, collect())
                : collect();
            $cov->coverage_calculated_value = $cov->coverageDetail->sum('calculated_value');
            $cov->coverage_coverage_value = $cov->coverageDetail->sum('coverage_value');
            $cov->extention_calculated_value = $cov->extentionDetail
                ->when($isDomCom, fn ($c) => $c->where('type', '!=', 'Excess'))
                ->sum('extention_calculated_value');
            $cov->total_calculated_prorated_premium = 0;

            // The v2-quote-sheet blade iterates extensions separated by type.
            // Synthesize properties for each type so the blade loops render
            // extensions for non-motor sections (Office Contents, Fire, etc.).
            // We flatten the master's s_ScreenName onto each row so the blade
            // can read $extention_items->s_ScreenName directly.
            $filterAndMapExtensions = function ($type) use ($cov) {
                return $cov->extentionDetail
                    ->filter(fn($r) => empty($r->deleted_at) && ($r->type ?? '') === $type)
                    ->map(function ($r) {
                        if (isset($r->extention)) {
                            if (!isset($r->s_ScreenName)) {
                                $r->s_ScreenName = $r->extention->s_ScreenName ?? null;
                            }
                            if (!isset($r->s_CoverageName)) {
                                $r->s_CoverageName = $r->extention->s_ScreenName ?? null;
                            }
                        }
                        return $r;
                    })
                    ->values();
            };

            $cov->extension_with_type_extention = $filterAndMapExtensions('Extention');
            $cov->extension_with_type_memoranda = $filterAndMapExtensions('Memoranda');
            $cov->extension_with_type_excess = $filterAndMapExtensions('Excess');
            $cov->extension_with_type_firstamount = $filterAndMapExtensions('FirstAmountPayable');
            $cov->extension_with_type_burglaralarmwarranty = $filterAndMapExtensions('BurglarAlarmWarranty');

            // Attach specialist coverage rows so the included partials render.
            $cov->earCoverage = null;
            $cov->carCoverage = null;
            $cov->parCoverage = null;
            if ($isEngineering && $master) {
                $code = strtoupper($master->s_CoverageCode ?? '');
                if ($earCovRow && (str_contains($code, 'EAR') || str_contains($code, 'ERECTIONALLRISKS'))) {
                    $cov->earCoverage = $earCovRow;
                } elseif ($carCovRow && (str_contains($code, 'CAR') || str_contains($code, 'CONTRACTORSALLRISKS'))) {
                    $cov->carCoverage = $carCovRow;
                } elseif ($parCovRow && (str_contains($code, 'PAR') || str_contains($code, 'PLANTALLRISKS'))) {
                    $cov->parCoverage = $parCovRow;
                }
            }
        }

        $regionVat = \DB::table('regions')->where('id', 7)->value('vat') ?? 14;
        // Engineering quote: when policy.premium hasn't been aggregated yet
        // (the EAR Schedule's Total Premium lives in ear_coverages.total_premium),
        // sum the schedule totals so the Quote Sheet shows the correct figure.
        $prem = $action?->premium ?? $policy->premium ?? 0;
        if ($isEngineering && (float) $prem <= 0) {
            $prem = (float)($earCovRow->total_premium ?? 0)
                  + (float)($carCovRow->total_premium ?? 0)
                  + (float)($parCovRow->total_premium ?? 0);
        }
        $premExcVAT = $prem / (1 + ($regionVat / 100));
        $today = now()->format('d/m/Y');
        $fromDate = $term ? \Carbon\Carbon::parse($term->term_start_date)->format('d/m/Y') : $today;
        $toDate = $term ? \Carbon\Carbon::parse($term->term_end_date)->format('d/m/Y') : '';

        // Pro-rata diff-in-days. Aligned with PolicyCreateController's
        // calculatePremium ENDORSE branch so the V2 Quote per-row pro-rata
        // factor matches what the Rate button computed:
        //   $diff_in_days_main = days of the most recent state-establishing
        //     action with id < current. State types: NEWBUSINESS,
        //     ANNIVERSARY-RENEW, RENEW, REINSTATE, REISSUE.
        //   $diff_in_days_new_coverage = days of CURRENT action.
        // Using a different denominator (term length, or only RENEW) caused
        // V2 Quote totals to drift from the Rate banner.
        $diff_in_days_main = 0;
        $diff_in_days_new_coverage = 0;
        if ($action && $action->effective_from && $action->effective_to) {
            $a1 = strtotime((string) $action->effective_from);
            $a2 = strtotime((string) $action->effective_to);
            if ($a1 && $a2 && $a2 >= $a1) {
                $diff_in_days_new_coverage = (int) (($a2 - $a1) / 86400) + 1;
            }
        }
        if ($action) {
            // Same source-action priority as PolicyCreateController::calculatePremium
            // ENDORSE branch: prefer a state action with matching effective_to
            // (quarterly billing), then one whose period contains the endorse,
            // then most-recent fallback.
            $stateTypes = ['NEWBUSINESS', 'ANNIVERSARY-RENEW', 'RENEW', 'REINSTATE', 'REISSUE'];
            $prevAct = \AlphaDirect\Models\PolicyAction::where('policy_id', $policyId)
                ->where('id', '<', $action->id)
                ->whereIn('transaction_type', $stateTypes)
                ->where('effective_to', $action->effective_to)
                ->orderByDesc('id')
                ->first();
            if (!$prevAct && $action->effective_from) {
                $prevAct = \AlphaDirect\Models\PolicyAction::where('policy_id', $policyId)
                    ->where('id', '<', $action->id)
                    ->whereIn('transaction_type', $stateTypes)
                    ->where('effective_from', '<=', $action->effective_from)
                    ->where('effective_to', '>=', $action->effective_from)
                    ->orderByDesc('id')
                    ->first();
            }
            if (!$prevAct) {
                $prevAct = \AlphaDirect\Models\PolicyAction::where('policy_id', $policyId)
                    ->where('id', '<', $action->id)
                    ->whereIn('transaction_type', $stateTypes)
                    ->orderByDesc('id')
                    ->first();
            }
            if ($prevAct && $prevAct->effective_from && $prevAct->effective_to) {
                $p1 = strtotime((string) $prevAct->effective_from);
                $p2 = strtotime((string) $prevAct->effective_to);
                if ($p1 && $p2 && $p2 >= $p1) {
                    $diff_in_days_main = (int) (($p2 - $p1) / 86400) + 1;
                }
            }
        }
        // Final fallback — keep the old term-day computation so brand-new
        // policies (no prior state action) still produce sane numbers.
        if ($diff_in_days_main === 0 && $term && $term->term_start_date && $term->term_end_date) {
            $t1 = strtotime((string) $term->term_start_date);
            $t2 = strtotime((string) $term->term_end_date);
            if ($t1 && $t2 && $t2 >= $t1) {
                $diff_in_days_main = (int) (($t2 - $t1) / 86400) + 1;
            }
        }

        // Period dates must come from the SELECTED action (not the policy term),
        // so a REISSUE / RENEW / endorse shows ITS OWN effective period.
        $actFrom = $action?->effective_from ? \Carbon\Carbon::parse($action->effective_from)->format('d/m/Y') : $fromDate;
        $actTo   = $action?->effective_to ? \Carbon\Carbon::parse($action->effective_to)->format('d/m/Y') : $toDate;

        // Annual Period — full annual term anchored on the latest ANNIVERSARY-RENEW
        // (or NEWBUSINESS), per V1 PolicyController. Defaults to the action dates.
        $annualPeriodStart = $actFrom;
        $annualPeriodEnd   = $actTo;
        if ($action && in_array($action->transaction_type, ['RENEW', 'REINSTATE', 'REISSUE'], true)) {
            $annualRef = \AlphaDirect\Models\PolicyAction::where('policy_id', $policyId)
                ->where('transaction_type', 'ANNIVERSARY-RENEW')->where('status', 'ISSUED')->whereNull('deleted_at')
                ->where('effective_from', '<=', $action->effective_from)->orderByDesc('effective_from')->first()
                ?? \AlphaDirect\Models\PolicyAction::where('policy_id', $policyId)
                    ->where('transaction_type', 'NEWBUSINESS')->where('status', 'ISSUED')->whereNull('deleted_at')->first();
            if ($annualRef && $annualRef->effective_from) {
                $annualStart = \Carbon\Carbon::parse($annualRef->effective_from);
                $annualEnd   = $annualStart->copy()->addYearNoOverflow()->subDay();
                $annualPeriodStart = $annualStart->format('d/m/Y');
                $annualPeriodEnd   = $annualEnd->format('d/m/Y');
            }
        } elseif ($action && $action->transaction_type === 'NEWBUSINESS'
            && in_array((int) ($policy->premium_freq ?? 0), [1, 5], true) && $action->effective_from) {
            $annualStart = \Carbon\Carbon::parse($action->effective_from);
            $annualEnd   = $annualStart->copy()->addYearNoOverflow()->subDay();
            $annualPeriodStart = $annualStart->format('d/m/Y');
            $annualPeriodEnd   = $annualEnd->format('d/m/Y');
        }

        // DOMG (Domestic, product_id 8) must never surface commercial-only
        // PARENT sections in the V2 Index of Sections. Mirrors V1
        // ProductCoverage::getProductCov(8), which excludes the 12 commercial
        // product_coverage pivot rows 217-228 = coverage ids 3 (Office
        // Contents), 4-10, 11 (Business All Risks), 12, 16, 17 (House
        // Holders). DOMG index must list only: Houseowner-Buildings,
        // Household Contents, Personal All Risks, Personal Accident,
        // Computer Equipment, Workers Compensation, Personal Motor.
        $domgExcludedCoverageIds = ((int) $policy->product_id === 8)
            ? [3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 16, 17]
            : [];

        // DOMG Index (UW/Monika 2026-06-12, supersedes the 2026-06-11
        // on-policy intersection): the Index of Sections must ALWAYS list
        // every domestic section with a Yes/No "Section Taken" flag —
        // matching the legacy quote — so not-taken sections render with
        // "No" / P 0.00 instead of disappearing from the index. The
        // 2026-06-11 fix intersected with the policy's own parents, which
        // stopped the commercial leak but also dropped every "No" row.
        // Keep the leak protection by allowlisting the domestic PARENT
        // screen names instead, and union with the parents actually on the
        // policy so a taken section can never vanish from the index.
        $domgIndexScreenNames = ((int) $policy->product_id === 8)
            ? [
                'Houseowner-Buildings', 'Household Contents', 'Personal All Risks',
                'Personal Accident', 'Computer Equipment', 'Workers Compensation',
                'Personal Motor',
            ]
            : [];
        $domgOnPolicyParentIds = ((int) $policy->product_id === 8)
            ? collect($policy_coverages)->pluck('coverage_id')->filter()->unique()->values()->all()
            : [];

        $array = [
            'today' => $today, 'policy' => $policy, 'policyAction' => $action,
            'policyTerm' => $term,
            'policy_coverages' => $policy_coverages,
            'fromDateStart' => $action?->effective_from ? \Carbon\Carbon::parse($action->effective_from)->format('d/m/Y') : $fromDate,
            'fromDateEnd' => $action?->effective_to ? \Carbon\Carbon::parse($action->effective_to)->format('d/m/Y') : $toDate,
            'renewStart' => $actFrom, 'renewEnd' => $actTo,
            'annRenewStart' => $actFrom, 'annRenewEnd' => $actTo,
            'endorStart' => $action?->effective_from ? \Carbon\Carbon::parse($action->effective_from)->format('d/m/Y') : $fromDate,
            'endorENd' => $action?->effective_to ? \Carbon\Carbon::parse($action->effective_to)->format('d/m/Y') : $toDate,
            'periodLabel' => $fromDate . ' - ' . $toDate,
            'aboutAlpha' => '', 'currentlyInsured' => '', 'exclusionText' => '',
            'entitiesName' => '', 'property_business_being' => '', 'look_up_non' => '',
            'motor' => \DB::table('motor')->whereIn('policy_coverage_id', $policy_coverages->pluck('id'))->whereNull('deleted_at')->get(),
            'noteMotor' => '', 'tradersData' => collect(), 'pData' => collect(),
            'pExData' => \DB::table('policy_excesses_data')->where('policy_id', $policyId)->get(),
            'pBusiExData' => collect(),
            'totalMiniPerExcessesOfCommericialMotor' => 0, 'totalMiniAmountExcessesOfCommericialMotor' => 0,
            'totalMiniPerExcessesOfPersonalMotor' => 0, 'totalMiniAmountExcessesOfPersonalMotor' => 0,
            // Blade accesses these rows via $motorData['key'] (array syntax),
            // so we cast each stdClass → assoc array. DB::table()->get()
            // returns stdClass objects by default which would throw
            // "Cannot use object of type stdClass as array" at line 1176+.
            'policyComCover' => \DB::table('policy_coverages')
                ->join('motor', 'motor.policy_coverage_id', '=', 'policy_coverages.id')
                ->where('policy_coverages.policy_id', $policyId)->where('policy_coverages.coverage_id', 22)
                ->when($action, fn($q) => $q->where('policy_coverages.action_id', $action->id))
                ->whereNull('motor.deleted_at')
                ->select('motor.*', 'policy_coverages.status as cancelStatus', 'motor.calculated_value as finalSum')
                // Fold the cover type onto its canonical token. Read straight
                // from DB::table, so the Motor model accessor does not apply —
                // and the blade gates the whole COM section on
                // `type_of_cover_main != "third_party_only"`, so a row holding
                // the display label rendered a third-party vehicle with the
                // comprehensive blocks. See AlphaDirect\Support\MotorCoverType.
                ->get()->map(function ($r) {
                    $row = (array) $r;
                    $row['type_of_cover']      = \AlphaDirect\Support\MotorCoverType::normalize($row['type_of_cover'] ?? null);
                    $row['type_of_cover_main'] = \AlphaDirect\Support\MotorCoverType::normalize($row['type_of_cover_main'] ?? null);
                    return $row;
                })->toArray(),
            'policyComCoverDom' => [],
            // Motor Traders External — section-level rows from motor_traders
            // joined to the policy_coverage. Blade (v2-quote-sheet.blade.php
            // ~line 1784) iterates this list to build the Index-of-Sections
            // row for "Motor Traders External". Aliasing
            // loss_or_damage_calculated_value as finalSumMotorExternal
            // matches the blade contract; the 11 other *_calculated_value
            // columns it reads are passed through via motor_traders.*.
            // Endorse-only fields (endors_flag, previousActionIdCov,
            // pro_rate_premium, cancelStatus) are zero-defaulted because
            // the motor_traders table does not carry them.
            'policyMotorIndex' => \DB::table('motor_traders')
                ->join('policy_coverages', 'policy_coverages.id', '=', 'motor_traders.policy_coverage_id')
                ->where('policy_coverages.policy_id', $policyId)
                ->when($action, fn($q) => $q->where('policy_coverages.action_id', $action->id))
                ->whereNull('motor_traders.deleted_at')
                ->select(
                    'motor_traders.*',
                    'policy_coverages.status as cancelStatus',
                    'motor_traders.loss_or_damage_calculated_value as finalSumMotorExternal'
                )
                ->get()
                ->map(function ($r) {
                    $r = (array) $r;
                    $r['specified_items'] = (float) \DB::table('policy_specified_items')
                        ->where('policy_coverage_id', $r['policy_coverage_id'] ?? 0)
                        ->whereNull('deleted_at')
                        ->sum('calculated_value');
                    $r['endors_flag']         = $r['endors_flag']         ?? 0;
                    $r['previousActionIdCov'] = $r['previousActionIdCov'] ?? 0;
                    $r['pro_rate_premium']    = $r['pro_rate_premium']    ?? 0;
                    return $r;
                })
                ->toArray(),
            'policyMotorIndexInternal' => \DB::table('motor_traders_internal')
                ->join('policy_coverages', 'policy_coverages.id', '=', 'motor_traders_internal.policy_coverage_id')
                ->where('policy_coverages.policy_id', $policyId)
                ->when($action, fn($q) => $q->where('policy_coverages.action_id', $action->id))
                ->whereNull('motor_traders_internal.deleted_at')
                ->select(
                    'motor_traders_internal.*',
                    'policy_coverages.status as cancelStatus',
                    'motor_traders_internal.loss_or_damage_calculated_value as finalSumMotorInternal'
                )
                ->get()
                ->map(function ($r) {
                    $r = (array) $r;
                    $r['specified_items'] = (float) \DB::table('policy_specified_items')
                        ->where('policy_coverage_id', $r['policy_coverage_id'] ?? 0)
                        ->whereNull('deleted_at')
                        ->sum('calculated_value');
                    $r['endors_flag']         = $r['endors_flag']         ?? 0;
                    $r['previousActionIdCov'] = $r['previousActionIdCov'] ?? 0;
                    $r['pro_rate_premium']    = $r['pro_rate_premium']    ?? 0;
                    return $r;
                })
                ->toArray(),
            'specifed_items' => \DB::table('policy_specified_items')->whereIn('policy_coverage_id', $policy_coverages->pluck('id'))->whereNull('deleted_at')->get(),
            'flag' => 'v2_quotationPdf',
            'all_coverages' => \DB::table('product_coverage')
                ->join('tb_cvgpccoverages', 'tb_cvgpccoverages.id', '=', 'product_coverage.coverage_id')
                ->where('product_coverage.product_id', $policy->product_id)
                ->where('tb_cvgpccoverages.s_UsageType', 'PARENT')->where('tb_cvgpccoverages.s_DISPLAYTOUSER', '1')
                ->when($domgExcludedCoverageIds, fn($q) => $q->whereNotIn('tb_cvgpccoverages.id', $domgExcludedCoverageIds))
                ->when($domgIndexScreenNames, fn($q) => $q->where(function ($w) use ($domgIndexScreenNames, $domgOnPolicyParentIds) {
                    $w->whereIn('tb_cvgpccoverages.s_ScreenName', $domgIndexScreenNames);
                    if (!empty($domgOnPolicyParentIds)) {
                        $w->orWhereIn('tb_cvgpccoverages.id', $domgOnPolicyParentIds);
                    }
                }))
                ->orderBy('tb_cvgpccoverages.n_DisplaySequence')->select('tb_cvgpccoverages.*')->get(),
            // Index of Sections table on engineering quote sheet iterates
            // $indexSections and reads $section->name. Build it here as the
            // PARENT coverage masters available for this product, aliased so
            // ->name matches the s_ScreenName the blade compares against.
            'indexSections' => \DB::table('product_coverage')
                ->join('tb_cvgpccoverages', 'tb_cvgpccoverages.id', '=', 'product_coverage.coverage_id')
                ->where('product_coverage.product_id', $policy->product_id)
                ->where('tb_cvgpccoverages.s_UsageType', 'PARENT')
                ->where('tb_cvgpccoverages.s_DISPLAYTOUSER', '1')
                ->when($domgExcludedCoverageIds, fn($q) => $q->whereNotIn('tb_cvgpccoverages.id', $domgExcludedCoverageIds))
                ->when($domgIndexScreenNames, fn($q) => $q->where(function ($w) use ($domgIndexScreenNames, $domgOnPolicyParentIds) {
                    $w->whereIn('tb_cvgpccoverages.s_ScreenName', $domgIndexScreenNames);
                    if (!empty($domgOnPolicyParentIds)) {
                        $w->orWhereIn('tb_cvgpccoverages.id', $domgOnPolicyParentIds);
                    }
                }))
                ->orderBy('tb_cvgpccoverages.n_DisplaySequence')
                ->select('tb_cvgpccoverages.id', 'tb_cvgpccoverages.s_ScreenName as name', 'tb_cvgpccoverages.s_CoverageCode as code')
                ->get(),
            'premium_coverages' => \AlphaDirect\Models\PolicyCoverage::select('coverage_id')
                ->where('policy_id', $policyId)->when($action, fn($q) => $q->where('action_id', $action->id))
                ->groupBy('coverage_id')->orderBy('coverage_id')->get(),
            'regionVat' => $regionVat, 'premiumExcludingVAT' => $premExcVAT,
            'vat' => $premExcVAT * ($regionVat / 100), 'ServiceCharge8' => 0, 'vatServiceCharge' => 0,
            'premiumProratedExcludingVAT' => 0, 'vatProrated' => 0,
            'ServiceProratedCharge8' => 0, 'vatProratedServiceCharge' => 0, 'total_coverage_prorated_premium' => 0,
            // Pro-rata divisors — see computation above. Always pass both
            // (even when 0) so no Undefined-variable throws leak through
            // the 40+ foreach loops in v2-quote-sheet.blade.php that use them.
            'diff_in_days_main' => $diff_in_days_main,
            'diff_in_days_new_coverage' => $diff_in_days_new_coverage,
            'diff_in_days_old_coverage' => 0,

            // Canonical totals from PolicyCreateController::calculatePremium
            // (Rate button) — stamped onto policy_actions when the user hits
            // Rate. policy_actions.premium = pro-rata for ENDORSE / annual
            // for other transactions; .annual_premium = full year always.
            // Blade reads these so V2 Quote always agrees with Rate banner.
            'rateAnnualPremium' => $action ? (float) ($action->annual_premium ?? $action->premium ?? 0) : 0,
            'rateProRataPremium' => $action && $action->transaction_type === 'ENDORSE'
                ? (float) ($action->premium ?? 0)
                : 0,

            // ── Safety zero-defaults ──
            // v2-quote-sheet.blade.php references ~80 scalars from the
            // legacy PolicyController endorse/cancel/renew paths. On new-
            // business quotes most are 0, but Blade @php blocks throw
            // "Undefined variable" in PHP 8.x when they read a var that
            // wasn't passed. Pre-populating with 0 lets the blade's
            // existing math (e.g. `$x + $sumSubCOverages1`) evaluate
            // correctly without crashing the PDF.
            'policyCoveragesData' => \DB::table('policy_coverages_data')
                ->whereIn('policyCoverageID',
                    \AlphaDirect\Models\PolicyCoverage::where('policy_id', $policyId)
                        ->when($action, fn($q) => $q->where('action_id', $action->id))
                        // ENDORSE: include Fidelity data under a coverage deleted
                        // in THIS action so its Basis Of Cover section still
                        // renders (the blade zeroes the amounts for status == 1,
                        // matching the active layout with P 0.00). The premium
                        // SUM below stays deleted-excluded, so the Index of
                        // Sections and grand total are unaffected.
                        // Same visibleForEndorse() gate as the main coverage
                        // query above — without it the Fidelity Basis Of Cover
                        // block still rendered under a ghost coverage row.
                        ->when(($action->transaction_type ?? null) === 'ENDORSE',
                            fn($q) => $q->withTrashed()->visibleForEndorse($action->id))
                        ->pluck('id')
                )
                ->get()
                ->map(fn($r) => (array) $r)
                ->toArray(),
            'policyCoveragesDataSum' => \AlphaDirect\Models\PolicyCoveragesData::join('policy_coverages', 'policy_coverages.id', '=', 'policy_coverages_data.policyCoverageID')
                ->where('policy_coverages.policy_id', $policyId)
                ->where('policy_coverages.coverage_id', 9)
                ->where('policy_coverages.action_id', $action?->id ?? 0)
                // Exclude Fidelity data under soft-deleted parent coverages so the
                // Index of Sections matches the Fidelity section (which renders
                // from soft-delete-aware PolicyCoverage). Without this, a removed/
                // re-added Fidelity coverage left a stale policy_coverages_data row
                // and the index double-counted the premium (630 -> 1260).
                ->whereNull('policy_coverages.deleted_at')
                ->sum('policy_coverages_data.premium'),
            // Fidelity Guarantee (coverage 9) pro-rata charge raised by the
            // CURRENT endorse only. Mirrors policyCoveragesDataSum but adds the
            // endors_flag + previousActionIdCov=action gate so it picks up just
            // this transaction's pro-rata row. On non-ENDORSE no rows match
            // (endors_flag never '1'), so it resolves to 0 naturally.
            'policyCoveragesDataEndorseSum' => \AlphaDirect\Models\PolicyCoveragesData::join('policy_coverages', 'policy_coverages.id', '=', 'policy_coverages_data.policyCoverageID')
                ->where('policy_coverages.policy_id', $policyId)
                ->where('policy_coverages.coverage_id', 9)
                ->where('policy_coverages.action_id', $action?->id ?? 0)
                ->whereNull('policy_coverages.deleted_at')
                ->where('policy_coverages_data.endors_flag', '1')
                ->where('policy_coverages_data.previousActionIdCov', $action?->id ?? 0)
                ->sum('policy_coverages_data.premium'),
            'sumIndexInsuredCalculated' => 0, 'SumIndexExtCalculated' => 0,
            'sum' => 0, 'sumSubCOverages' => 0, 'sumSubCOverages1' => 0,
            'fidelityGurntee' => 0, 'totalProRata' => 0,
            'prorataCoverage' => 0,
            'finalMotorSum' => 0, 'finalMotorSumCancel' => 0,
            'finalMotorSumProRata' => 0, 'finalMotorSumPrev' => 0,
            'finalMotorPersonalSum' => 0, 'finalMotorPersonaProrata' => 0,
            'finalMotorPersonalSumCancel' => 0, 'finalMotorSumPrevPersonal' => 0,
            'finalMotorSumInternal' => 0, 'finalMotorSumInternalProRata' => 0,
            'finalMotorSumInternalCancel' => 0,
            'finalMotorexternalSum' => 0, 'finalMotorexternalSumCancel' => 0,
            'finalMotorexternalProRata' => 0,
            'sumMotorPersonal' => 0, 'SumMotorCom' => 0,
            'sum_insured' => 0, 'sum_insuredMotor' => 0, 'sum_insuredMotorInternal' => 0,
            'commMotorSumInsuredTotal' => 0, 'commMotorTotal' => 0,
            'commMotorTotalEndors' => 0, 'commMotorTotalPre' => 0,
            'totalCommercial' => 0, 'totalCommercialPremium' => 0,
            'totalCoverageCalculatedValue' => 0,
            'totalExtensionsOfCommericialMotor' => 0, 'totalExtensionsOfCommericialMotorPremuim' => 0,
            'totalExtensionsOfCommericialMotorPremuimInternal' => 0,
            'totalExtensionsOfMotorPremuim' => 0,
            'totalExtensionsOfPersonalMotor' => 0, 'totalExtensionsOfPersonalMotorSumInsured' => 0,
            'totalExtensionsOfPersonallMotorPremuim' => 0,
            'totalExternalExcessPremium' => 0, 'totalExternalExcessSumInsured' => 0,
            'totalExternalExtentionPremium' => 0, 'totalExternalExtentionSumInsured' => 0,
            'totalExternalPremium' => 0, 'totalExternalSumInsured' => 0,
            'totalInternalExcessPremium' => 0, 'totalInternalExcessSumInsured' => 0,
            'totalInternalExtentionPremium' => 0, 'totalInternalExtentionSumInsured' => 0,
            'totalInternalPremium' => 0, 'totalInternalSumInsured' => 0,
            'totalIndexSumExtCalculated' => 0, 'totalIndexSumInsuredCalculated' => 0,
            'totalProRataPremium' => 0, 'totalProRataPremiumVatFreq' => 0,
            'totalProRataPremiumVatFreqCancel' => 0,
            'totalSumOfCalculatedValues' => 0, 'totalSumOfCoveragesValues' => 0,
            'totalSumOfExtentionCalculatedValues' => 0, 'totalSumOfExtentionCoveragesValues' => 0,
            'totalSumOfPerilsCalculatedValues' => 0, 'totalSumOfPerilsCoveragesValues' => 0,
            'total_SumOfCoveragesValues' => 0, 'total_SumOfCoveragesValues1' => 0,
            'moneyRatefactorValue' => 0, 'theftRatefactorValue' => 0,
            'vat_month' => 0, 'vat_pro_data' => 0,
            'annualPeriodStart' => $annualPeriodStart, 'annualPeriodEnd' => $annualPeriodEnd,
            'actionDates' => collect(),
            'covragesIds' => [], 'motorData' => collect(), 'motorDataDom' => collect(),
            'motorDataInternal' => collect(), 'motorDatas' => collect(),
            'motorTradersData' => collect(),
            'entitiesData' => collect(), 'tbCvgpclimits' => collect(),
            'tbCvgpcextentionlimits' => collect(),
            'extention_items' => collect(),
            'uniqueCoverages' => collect(), 'getSubCoverPresent' => false,
            'getSubCoverPresent1' => false,
            'flagCom' => false, 'matchFound' => false,
            'coverageCode' => null, 'coverageMaster' => null, 'coverageType' => null,
            'dateValue' => null, 'getActionId' => null,
            'newIndex' => 0, 'newSpecifyIndex' => 0, 'newSubIndex' => 0,
            'parsed' => null, 'header' => null, 'format' => null,
            'entity' => null, 'coverage' => null, 'allcoverage' => null,
            'item' => null, 'value' => null, 'x' => 0, 'y' => 0, 'z' => 0,
            'index' => 0, 'e' => null,
        ];

        // Apply central defaults FIRST, then let computed values win.
        // This prevents "Undefined variable" crashes for any symbol the
        // blade references but the V2 job didn't explicitly compute.
        // See app/Support/QuoteSheetDefaults.php for the full list.
        $array = array_merge(\AlphaDirect\Support\QuoteSheetDefaults::all(), $array);

        // Cancel Sub Coverage endorse (DomCom 7/8): the blade greys out zeroed
        // sub-coverage rows (same treatment as a cancelled coverage).
        $array['isCancelSubEndorse'] = $isCancelSubEndorse;

        // Specialist coverage premium totals for specialist-product.blade.php's
        // "Index of Sections" Gross column + the detail schedules.
        //
        // The model get*Total() helpers (and the detail blades) are policy-
        // scoped: they sum/show EVERY specialist row for the policy across all
        // transactions, so after an endorse/cancel one declaration appears
        // multiple times and the Gross stacks (e.g. P 21,000 vs Rate's correct
        // P 4,986.64). Resolve the SELECTED transaction's coverage ids ONCE
        // here (the action is reliably available in the job) and pass them to
        // the blades so every specialist query scopes to this transaction —
        // matching Rate, showing each declaration once. Fall back to policy-
        // wide only if this action has no coverages, so the schedule can never
        // go blank.
        $specActionPcIds = $action
            ? \DB::table('policy_coverages')->where('policy_id', $policyId)
                ->where('action_id', $action->id)->whereNull('deleted_at')->pluck('id')
            : collect();
        $specPcIds = $specActionPcIds->isNotEmpty()
            ? $specActionPcIds
            : \DB::table('policy_coverages')->where('policy_id', $policyId)
                ->whereNull('deleted_at')->pluck('id');
        $array['specialistPcIds'] = $specPcIds->all();

        $specTotal = function (string $table, string $col) use ($specPcIds) {
            if ($specPcIds->isEmpty() || !\Schema::hasTable($table) || !\Schema::hasColumn($table, $col)) {
                return 0.0;
            }
            $q = \DB::table($table)->whereIn('policy_coverage_id', $specPcIds);
            if (\Schema::hasColumn($table, 'deleted_at')) $q->whereNull('deleted_at');
            return (float) $q->sum($col);
        };
        $array['getMedicalTotal']                 = $specTotal('medical_malpractice_coverages', 'annual_premium');
        $array['getProfessionalIndemnityTotal']   = $specTotal('professional_indemnity_coverages', 'premium');
        $array['getTravelTotal']                  = $specTotal('travel_coverages', 'total');
        $array['getMedicalEvacuationTotal']       = $specTotal('medical_evacuation_coverages', 'premium');
        $array['getCommercialCrimeTotal']         = $specTotal('commercial_crime_coverages', 'premium');
        $array['getEnvironmentalLiabilityTotal']  = $specTotal('environmental_liability_coverages', 'premium');
        $array['getBondsTotal']                   = $specTotal('bonds_coverages', 'premium');
        $array['getMarineCargoOnceOffTotal']      = $specTotal('marine_cargo_once_off_coverages', 'premium');
        $array['getMarineCargoOpenTotal']         = $specTotal('marine_cargo_open_coverages', 'premium');
        // Machinery Breakdown: same treatment as Marine D&O below — rebuild the
        // total from the section JSON instead of trusting the `premium` column.
        // MB is priced per section (SECTION 1 + Machinery Listing + SECTION 2 +
        // SECTION 3) and the scalar is now a computed total, but records saved
        // before that change can hold a stale or blank figure while every
        // section is priced, which showed the cover at P 0.00 on the Index of
        // Sections. resolvedPremium() falls back to the scalar when the
        // sections carry no premium, so section-less rows are unaffected.
        $mbTotal = 0.0;
        if (!$specPcIds->isEmpty() && \Schema::hasTable('machinery_breakdown_coverages')) {
            $mbQ = \DB::table('machinery_breakdown_coverages')
                ->whereIn('policy_coverage_id', $specPcIds);
            if (\Schema::hasColumn('machinery_breakdown_coverages', 'deleted_at')) {
                $mbQ->whereNull('deleted_at');
            }
            foreach ($mbQ->get() as $row) {
                $mbTotal += \AlphaDirect\Models\MachineryBreakdownCoverage::resolvedPremium($row);
            }
        }
        $array['getMachineryBreakdownTotal'] = $mbTotal;
        // Marine D&O: rebuild premium total from the JSON section + misc
        // columns rather than trusting the `premium` column. The form's
        // recalculateMarineDirectorsOfficersPremium() now sums
        // sections 1/2/3 + insured persons + misc_items into `premium`, but
        // records saved before that change have premium = sections-only and
        // their misc_items JSON premiums were dropped from the Index Gross
        // and Total Premium rows. Rebuilding from JSON makes the total
        // deterministic and equal to Sections + MISC ITEM for every record.
        $mdoTotal = 0.0;
        if (!$specPcIds->isEmpty() && \Schema::hasTable('marine_directors_officers_coverages')) {
            $mdoQ = \DB::table('marine_directors_officers_coverages')
                ->whereIn('policy_coverage_id', $specPcIds);
            if (\Schema::hasColumn('marine_directors_officers_coverages', 'deleted_at')) {
                $mdoQ->whereNull('deleted_at');
            }
            $sumItemPremiums = function ($json) {
                $items = is_string($json) ? json_decode($json, true) : ($json ?? []);
                if (!is_array($items)) return 0.0;
                $sum = 0.0;
                foreach ($items as $it) {
                    if (!is_array($it)) continue;
                    $sum += (float) str_replace(',', '', (string) ($it['premium'] ?? 0));
                }
                return $sum;
            };
            foreach ($mdoQ->get() as $row) {
                $rowSum = 0.0;
                foreach (['section1_items', 'insured_persons_listing', 'section2_items', 'section3_items', 'misc_items'] as $col) {
                    $rowSum += $sumItemPremiums($row->{$col} ?? null);
                }
                if ($rowSum <= 0.0) {
                    $rowSum = (float) ($row->premium ?? 0);
                }
                $mdoTotal += $rowSum;
            }
        }
        $array['getMarineDirectorsOfficersTotal'] = $mdoTotal;

        // Per-section specialist pro-rata (endorse charge/refund incl VAT) for
        // the "Index of Sections" table on the engineering + specialist blades.
        // Computed here in PHP and keyed by coverage screen name so the views
        // only print it — no pro-rata calculation in the blade. Empty for
        // non-endorse actions and COM/DOM. Gross/totals are unchanged.
        $regionVatRate = (float) (\AlphaDirect\Region::where('id', 7)->value('vat') ?? 0);
        $specialistProRata = \AlphaDirect\Services\SpecialistEndorse\SpecialistEndorseCalculator::proRataInclVatByScreenName(
            $policyId, $action, 1 + ($regionVatRate / 100)
        );
        $array['specialistProRata'] = $specialistProRata;
        // Column totals for the "Index of Sections" Total Premium row — summed
        // here in PHP so the blade only prints them (no calculation in view).
        $array['specialistProRataTotalRefundInclVat']  = round(array_sum(array_column($specialistProRata, 'refundInclVat')), 2);
        $array['specialistProRataTotalPremiumInclVat'] = round(array_sum(array_column($specialistProRata, 'premiumInclVat')), 2);

        // ENDORSE Final Premium block — on an endorse the bottom "Final Premium
        // Including VAT" should show the PRO-RATA charge (net of any refund),
        // not the full annual gross. Computed here (incl/excl/VAT split) so the
        // blade only prints the appropriate trio per transaction type.
        $netProRataInclVat = round($array['specialistProRataTotalPremiumInclVat'] - $array['specialistProRataTotalRefundInclVat'], 2);
        $vatMul = 1 + ($regionVatRate / 100);
        $array['specialistProRataFinalInclVat'] = $netProRataInclVat;
        $array['specialistProRataFinalExclVat'] = $vatMul > 0 ? round($netProRataInclVat / $vatMul, 2) : $netProRataInclVat;
        $array['specialistProRataFinalVat']     = round($netProRataInclVat - $array['specialistProRataFinalExclVat'], 2);

        // When the policy has a Travel coverage, ask v2-quote-sheet to @include
        // v2-quote-sheet-travel.blade.php. Mirrors PolicyController::streamPolicyPdf
        // (line 23306) so async-job PDFs match legacy controller PDFs.
        $hasTravelCoverage = false;
        foreach ($policy_coverages as $pc) {
            $code = $pc->coverage->s_CoverageCode ?? null;
            if (in_array($code, ['TRAVEL', 'TRAVELINSURANCE'], true)) {
                $hasTravelCoverage = true;
                break;
            }
        }
        $array['quoteDetailsView'] = $hasTravelCoverage ? 'v2/livewire/pdf/v2-quote-sheet-travel' : null;

        // Set exclusion text for Public Liability coverage
        $hasPublicLiability = $policy_coverages->contains(fn($cov) => $cov->coverage && $cov->coverage->s_CoverageCode === 'PUBLICLIABILITY');
        if ($hasPublicLiability && $policy->customer) {
            $insuredName = $policy->customer->company_name ?? $policy->customer->name ?? 'The Insured';
            $array['exclusionText'] = "Anyone entering these premises does so entirely at their own risk. {$insuredName}, its owners, directors, management, employees, representatives, agents and contractors accept no liability or responsibility whatsoever for the death of, injury or harm to any person, or for the loss of or damage to any property of any person or entity, whether caused by or arising from the negligence or otherwise of {$insuredName}, its owners, directors, management, employees, representatives, agents and contractors.";
        }

        return [
            'policy' => $policy,
            'action' => $action,
            'term' => $term,
            'policy_coverages' => $policy_coverages,
            'array' => $array,
        ];
    }

    /**
     * Verify the rendered PDF is a real, complete PDF — not a zero-byte
     * file, an HTML error page mislabelled as .pdf, or a truncated write.
     *
     * Walks the same storage stack as the file-exists check above:
     *   local filesystem  →  Storage::disk('public')  →  Storage::disk('s3')
     * Whichever yields the bytes first is what we verify. On a normal PROD
     * render StorageService::putWithFallback writes to S3, so the file is
     * only on S3 — the previous local-only check was a regression that
     * marked every successful render as 'failed'.
     *
     * Returns null when the PDF passes all checks. Returns a short, human-
     * readable failure reason string otherwise (becomes job.message).
     *
     * Checks (cheapest first):
     *   1. Bytes are readable from some disk.
     *   2. Size >= 5 KB. A real V2 quote sheet is >100 KB even before
     *      wordings; anything under 5 KB means the render bailed early.
     *   3. First 5 bytes match '%PDF-'. Rules out HTML error pages or
     *      JSON garbage that a crashed renderer might have written.
     *   4. Last 1 KB contains '%%EOF'. PDFs end with %%EOF; truncated
     *      writes / interrupted uploads / disk-full conditions lose it.
     */
    private function verifyPdfBinaryAcrossDisks(string $storagePath): ?string
    {
        $sources = [
            'local'        => fn() => @file_get_contents(storage_path('app/public/' . $storagePath)),
            'disk:public'  => fn() => \Illuminate\Support\Facades\Storage::disk('public')->get($storagePath),
            'disk:s3'      => fn() => \Illuminate\Support\Facades\Storage::disk('s3')->get($storagePath),
        ];

        $content = null;
        $sourceUsed = null;
        $trace = [];

        foreach ($sources as $name => $reader) {
            try {
                $bytes = $reader();
                if (is_string($bytes) && strlen($bytes) > 0) {
                    $content = $bytes;
                    $sourceUsed = $name;
                    break;
                }
                $trace[$name] = is_string($bytes) ? 'empty' : 'null';
            } catch (\Throwable $e) {
                // File doesn't exist on this disk, or the disk threw. Try next.
                $trace[$name] = 'error: ' . $e->getMessage();
            }
        }

        if ($content === null) {
            return 'bytes unreadable on every disk (' . implode(', ', array_map(
                fn($k, $v) => "{$k}={$v}", array_keys($trace), array_values($trace)
            )) . ')';
        }

        \Log::info('GenerateQuotationPdfJob: verifying PDF bytes', [
            'pdfJobId'    => $this->pdfJobId,
            'source_disk' => $sourceUsed,
            'size_bytes'  => strlen($content),
            'path'        => $storagePath,
        ]);

        $size = strlen($content);
        if ($size < 5 * 1024) {
            return "file too small ({$size} bytes from {$sourceUsed}; expected >= 5 KB for a real quote sheet)";
        }

        $head = substr($content, 0, 5);
        if ($head !== '%PDF-') {
            $hex = bin2hex($head);
            return "header mismatch — expected '%PDF-', got '{$hex}' from {$sourceUsed} (renderer probably emitted HTML/JSON)";
        }

        // %%EOF must appear in the last 1 KB. PDFs sometimes have a
        // trailing newline after %%EOF so contains-check beats ends-with.
        $tail = substr($content, max(0, $size - 1024));
        if (strpos($tail, '%%EOF') === false) {
            return "trailer %%EOF not found in last 1 KB of {$sourceUsed} (PDF was truncated)";
        }

        return null;
    }

    /**
     * Pick the blade template for a policy based on product_id. DomCom
     * (7, 8) and most others render the standard v2-quote-sheet; engineering
     * (16, 18) gets the engineering variant; specialist (17, 19), Commercial
     * Liabilities (20), Marine (22), and Miscellaneous (24) get the specialist
     * variant — that blade's @foreach loop already dispatches to
     * marine_cargo_once_off_pdf / marine_cargo_open_pdf /
     * marine_directors_officers_pdf / medical_evacuation_pdf by coverage
     * code, and silently skips coverages it doesn't recognise, so routing
     * these products here renders the schedule without affecting other
     * product paths.
     */
    public static function pickQuoteBladeView(int $productId): string
    {
        if (in_array($productId, [16], true)) {
            return 'v2.livewire.pdf.v2-quote-sheet-engineering';
        }
        if (in_array($productId, [17, 18, 19, 20, 22, 23, 24], true)) {
            return 'v2.livewire.pdf.specialist-product';
        }
        return 'v2.livewire.pdf.v2-quote-sheet';
    }

    /**
     * Generate V2 Quote Sheet PDF using DomPDF (no wkhtmltopdf needed).
     * Falls back from SnappyPDF automatically.
     */
    protected function generateWithDomPdf(int $policyId, ?int $termId, ?int $actionId, string $fileName): void
    {
        $stepStart = microtime(true);
        \Log::info("PDF STEP: Starting buildBladeData");
        $built = self::buildBladeData($policyId, $termId, $actionId);
        if ($this->beatFn) ($this->beatFn)('after-buildBladeData');
        \Log::info("PDF STEP: buildBladeData completed", ['elapsed_ms' => round((microtime(true) - $stepStart) * 1000, 2)]);
        $buildTime = round((microtime(true) - $stepStart) * 1000, 2);
        \Log::info("PDF PROFILE: buildBladeData", ['ms' => $buildTime, 'pdfJobId' => $this->pdfJobId]);
        $policy           = $built['policy'];
        $action           = $built['action'];
        $policy_coverages = $built['policy_coverages'];

        // Optional title override stored on the V2PdfJob row — used by the
        // async Policy Doc path (DomCom 7/8) so the same blade renders with
        // the heading "POLICY DOCUMENT" instead of "INSURANCE QUOTATION/PROPOSAL".
        $jobRow = V2PdfJob::find($this->pdfJobId);
        if ($jobRow && !empty($jobRow->document_title)) {
            $built['array']['documentTitle'] = $jobRow->document_title;
        }
        $array            = $built['array'];

        libxml_use_internal_errors(true);

        // Route through PdfGeneratorService: Puppeteer primary, Snappy/DomPDF fallback.
        // Store via StorageService which tries S3 first then local disk.
        $pdfService     = app(\AlphaDirect\Services\PdfGeneratorService::class);
        $storageService = app(\AlphaDirect\Services\StorageService::class);

        $view = self::pickQuoteBladeView((int) $policy->product_id);

        $stepStart = microtime(true);
        $binary = $pdfService->fromView($view, $array, [
            'format'     => 'A4',
            'landscape'  => false,
            'filename'   => $fileName,
        ]);
        $renderTime = round((microtime(true) - $stepStart) * 1000, 2);
        if ($this->beatFn) ($this->beatFn)('after-pdfRender');
        \Log::info("PDF PROFILE: DomPDF render", ['ms' => $renderTime, 'pdfJobId' => $this->pdfJobId, 'view' => $view]);

        // Write the generated quote sheet to a temp path so PDFMerger can read it.
        // Canonical PDF-merge temp dir — the container entrypoint pre-creates
        // storage/app/temp/pdf_merge as www-data with group-write + setgid, so
        // both the web and queue-worker containers (shared EFS) can write it.
        // (Was app/temp_pdf_merge, which the entrypoint never provisioned → the
        // ad-hoc 0755 mkdir failed with "Permission denied" across users.)
        $tempDir = storage_path('app/temp/pdf_merge');
        if (!is_dir($tempDir)) @mkdir($tempDir, 0775, true);
        $quoteSheetTmp = $tempDir . '/quote_' . $this->pdfJobId . '_' . time() . '.pdf';
        file_put_contents($quoteSheetTmp, $binary);

        // Build the legacy file chain: [quote_sheet, DECLARATION, cover_page,
        // general_exceptions, ...per-coverage wordings, ...specialist wordings]
        // Mirrors PolicyController.php @ lines 23568-23770.
        $stepStart = microtime(true);
        $wordingFiles = $this->buildWordingFileList($policy, $action, $policy_coverages);
        $wordingTime = round((microtime(true) - $stepStart) * 1000, 2);
        \Log::info("PDF PROFILE: buildWordingFileList", ['ms' => $wordingTime, 'files' => count($wordingFiles), 'pdfJobId' => $this->pdfJobId]);
        $filenames = array_merge([$quoteSheetTmp], $wordingFiles);

        // Only keep files that actually exist on disk — the V2 container may
        // not have every CoverageWiseMultimark static asset (sync pulls from
        // S3 on boot; missing files get skipped rather than failing the job).
        $existing = [];
        foreach ($filenames as $fp) {
            if ($fp && file_exists($fp) && is_file($fp) && filesize($fp) > 0) {
                $existing[] = $fp;
            } else if ($fp) {
                \Log::warning("PDF merge: skipping missing/empty file {$fp}");
            }
        }

        \Log::info('V2 quote PDF merge plan', [
            'total_candidates' => count($filenames),
            'existing' => count($existing),
            'files' => array_map('basename', $existing),
        ]);

        if (count($existing) > 1) {
            // Merge quote sheet + wording PDFs. Per-file addPDF is wrapped so a
            // single malformed/unsupported PDF (encrypted, AES-256, weird
            // producer that the PDFMerger backend can't parse) doesn't blow up
            // the entire chain — the bad file is logged + skipped and the
            // rest still merge. Previously a single bad source PDF aborted
            // the merge and the quote went out as schedule-only with no
            // visible reason in the response.
            $stepStart = microtime(true);
            try {
                $merger = \PDFMerger::init();
                $added = 0;
                $skipped = [];
                foreach ($existing as $fp) {
                    try {
                        $merger->addPDF($fp, 'all', 'P', 'A4');
                        $added++;
                    } catch (\Throwable $eAdd) {
                        $skipped[] = ['file' => basename($fp), 'size' => @filesize($fp), 'error' => $eAdd->getMessage()];
                        \Log::warning('V2 quote PDF: skipping unmergeable file', [
                            'file' => basename($fp),
                            'size' => @filesize($fp),
                            'error' => $eAdd->getMessage(),
                        ]);
                    }
                }
                if ($added > 0) {
                    $merger->merge();
                    $mergedTmp = $tempDir . '/merged_' . $this->pdfJobId . '_' . time() . '.pdf';
                    $merger->save($mergedTmp);
                    $binary = file_get_contents($mergedTmp);
                    @unlink($mergedTmp);
                    $mergeTime = round((microtime(true) - $stepStart) * 1000, 2);
                    \Log::info('V2 quote PDF merged with wordings', [
                        'merge_time_ms'  => $mergeTime,
                        'merged_size_kb' => round(strlen($binary) / 1024, 1),
                        'added'          => $added,
                        'skipped'        => count($skipped),
                        'skipped_files'  => $skipped,
                    ]);
                } else {
                    \Log::error('V2 quote PDF: no files could be added to merger; keeping quote-sheet only', ['skipped' => $skipped]);
                }
            } catch (\Throwable $e) {
                \Log::error('PDFMerger failed — falling back to quote-sheet only: ' . $e->getMessage());
                // keep $binary as the quote-sheet-only version
            }
        } else {
            \Log::info('V2 quote PDF: no wordings to merge — using quote sheet alone');
        }

        // Clean up the quote sheet temp file
        @unlink($quoteSheetTmp);

        $storagePath = 'quote_sheet/' . $fileName;
        $stepStart = microtime(true);
        $stored = $storageService->putWithFallback($storagePath, $binary, [
            'ContentType' => 'application/pdf',
            'visibility'  => 'public',
        ]);
        $storageTime = round((microtime(true) - $stepStart) * 1000, 2);
        if ($this->beatFn) ($this->beatFn)('after-storage');

        \Log::info('V2 quote PDF stored', [
            'storage_time_ms' => $storageTime,
            'path' => $stored['path'], 'disk' => $stored['disk'], 'size' => $stored['size'],
        ]);

        // Architectural note: previously this code also updated V1's
        // `policy_actions.quotation_doc` column. That write is now removed.
        //
        // V2 has its own canonical PDF metadata store: v2_pdf_jobs.file_name
        // (set by GenerateQuotationPdfJob in the same flow + queried by the
        // /v2-pdf-status and /document-jobs UI surfaces). V1 has a separate
        // PDF generation pipeline that owns its own policy_actions.quotation_doc
        // — V2 does not need to interleave with that.
        //
        // Skipping the V1 write also keeps Phase 1 architecture clean: V2
        // writes nothing to V1 legacy tables. Phase 3 (when V2 takes over)
        // doesn't need to "uncomment" anything — v2_pdf_jobs is already
        // the system of record for V2 PDF state.
    }

    /**
     * Build the ordered list of static wording PDFs to append to the quote
     * sheet. Mirrors legacy PolicyController.php @ line 23568-23770 (DECLARATION
     * + Cover + General Exceptions + per-coverage-id product wordings).
     *
     * All paths resolve to storage/app/CoverageWiseMultimark/ which is pulled
     * from the `documents` S3 disk on container boot via
     * `php artisan storage:sync-static-pdfs down`.
     *
     * Specialist CAR/EAR/PAR policy-wording uploads (the customer-uploaded
     * PDFs on policy_wording_path) are pulled from S3 into a temp dir.
     */
    private function buildWordingFileList($policy, $action, $policy_coverages): array
    {
        $files = [];
        $base  = storage_path('app/CoverageWiseMultimark');

        // Always first after the quote sheet
        $files[] = $base . '/DECLARATION.pdf';

        // COM vs DOM cover page + general exceptions wording.
        // Guarantee (23) and Miscellaneous (24) belong here too: per UW they
        // are Organisation-holder / annual-term COMG lines only — see
        // Support\CompanyOnlyProducts, which lists 20, 23, 24 together. Without
        // them both products fell to the else-branch and every Bonds / Medical
        // Evacuation / Commercial Crime document shipped with the DOMESTIC
        // cover page and the Domestics general-exceptions wording.
        $isCom = in_array($policy->product_id, [7, 16, 17, 20, 22, 23, 24], true);
        if ($isCom) {
            $files[] = $base . '/Cover_Page.pdf';
            $files[] = $base . '/NewCoveragePdf/newdocCom/GENERAL_EXCEPTIONS_CONDITIONS_PROVISIONS.pdf';
        } else {
            $files[] = $base . '/Cover_DPage.pdf';
            $files[] = $base . '/NewCoveragePdf/newdocDom/GENERAL_EXCEPTIONS_CONDITIONS_PROVISIONS_Domestics.pdf';
        }

        // Per-coverage product wordings — taken from distinct coverage_ids
        // attached to this action. Duplicate-guarded so each wording appears
        // at most once even if two coverage rows share a coverage_id.
        $seen = [];
        $coverageIds = collect($policy_coverages)->pluck('coverage_id')->filter()->unique()->values()->all();

        foreach ($coverageIds as $cid) {
            $path = $this->wordingPathForCoverage((int) $cid, $isCom);
            if ($path && !isset($seen[$path])) {
                $files[] = $path;
                $seen[$path] = true;
            }
        }

        // Specialist user-uploaded wording PDFs queried directly by policy_id.
        //
        // Why direct-query (not the Eloquent HasOne relation off $policy_coverages):
        // CAR/EAR/PAR used to walk $cov->carCoverage/earCoverage/parCoverage,
        // which joins on policy_coverage_id. Any row saved with
        // policy_coverage_id NULL (the same legacy bug we patched for MB and
        // Marine D&O) is invisible to that relation, so the wording PDF
        // existed on disk + in the DB but was silently skipped in the merge.
        // Querying by policy_id matches MB/MM/PI/Travel/Marine and is safe
        // against orphaned NULL-policy_coverage_id rows.
        //
        // Reads try local `public` disk first then S3 — uploads land on
        // whichever disk StorageService::storeWithFallback succeeded against,
        // so the read mirror keeps both envs working.
        // DIRECTORSOFFICERSLIABILITY reuses the marine_directors_officers
        // schedule on the frontend, so MARINE_DO covers both DOL and Marine
        // D&O wordings — no separate entry needed.
        $tempDir = storage_path('app/temp_policy_wordings');
        if (!is_dir($tempDir)) @mkdir($tempDir, 0755, true);

        $specialistDirect = [
            'CAR'                    => \AlphaDirect\Models\CarCoverage::class,
            'EAR'                    => \AlphaDirect\Models\EarCoverage::class,
            'PAR'                    => \AlphaDirect\Models\ParCoverage::class,
            'MARINE_ONCEOFF'         => \AlphaDirect\Models\MarineCargoOnceOffCoverage::class,
            'MARINE_OPEN'            => \AlphaDirect\Models\MarineCargoOpenCoverage::class,
            'MARINE_DO'              => \AlphaDirect\Models\MarineDirectorsOfficersCoverage::class,
            'MACHINERY_BD'           => \AlphaDirect\Models\MachineryBreakdownCoverage::class,
            'MEDICAL_MALPRACTICE'    => \AlphaDirect\Models\MedicalMalpracticeCoverage::class,
            'PROFESSIONAL_INDEMNITY' => \AlphaDirect\Models\ProfessionalIndemnityCoverage::class,
            'TRAVEL'                 => \AlphaDirect\Models\TravelCoverage::class,
            'MEDICAL_EVACUATION'     => \AlphaDirect\Models\MedicalEvacuationCoverage::class,
            'COMMERCIAL_CRIME'       => \AlphaDirect\Models\CommercialCrimeCoverage::class,
            'ENVIRONMENTAL_LIABILITY'=> \AlphaDirect\Models\EnvironmentalLiabilityCoverage::class,
            'BONDS'                  => \AlphaDirect\Models\BondsCoverage::class,
        ];

        // policy_coverage_id fallback set — used to recover rows that were
        // saved with NULL policy_id (a legacy save bug seen for CAR/EAR/PAR/MB
        // in particular). Without this, those wordings exist in storage and
        // in the row but the policy_id query misses them and the merged
        // Quote Sheet ships without the wording attachment.
        $pcIds = collect($policy_coverages)->pluck('id')->filter()->unique()->values()->all();

        // Standard fall-back wordings for specialist sections that have NO
        // per-coverage entry in wordingPathForCoverage() and rely on a
        // per-policy manual upload. PI / MEDMAL were missing across all
        // policies (Underwriting 2026-06-11) because the underwriter rarely
        // uploads a wording and there was no default. When the coverage is
        // present on the policy but no uploaded wording resolves, we append
        // the standard static instead. Fail-open: if the asset isn't on the
        // box yet the merge simply omits it (same as before), so this is safe
        // to ship ahead of the PDFs landing. Underwriting must drop the
        // approved PDFs at these exact paths under storage/app/.
        $standardWording = [
            'PROFESSIONAL_INDEMNITY' => storage_path('app/CoverageWiseMultimark/NewCoveragePdf/newdocCom/PROFESSIONAL_INDEMNITY.pdf'),
            'MEDICAL_MALPRACTICE'    => storage_path('app/CoverageWiseMultimark/NewCoveragePdf/newdocCom/MEDICAL_MALPRACTICE.pdf'),
        ];

        $seenWordings = [];
        foreach ($specialistDirect as $label => $modelClass) {
            if (!class_exists($modelClass)) continue;
            try {
                $rows = $modelClass::where(function ($q) use ($policy, $pcIds) {
                        $q->where('policy_id', $policy->id);
                        if (!empty($pcIds)) {
                            $q->orWhereIn('policy_coverage_id', $pcIds);
                        }
                    })
                    ->whereNotNull('policy_wording_path')
                    ->where('policy_wording_path', '!=', '')
                    ->get();
            } catch (\Throwable $e) {
                \Log::warning("Specialist wording query failed for {$label}: " . $e->getMessage());
                continue;
            }
            \Log::info("V2 quote PDF: wording rows for {$label}", [
                'policy_id' => $policy->id,
                'rows'      => $rows->count(),
            ]);
            $attached = 0;
            foreach ($rows as $row) {
                $path = $row->policy_wording_path ?? null;
                if (!$path || isset($seenWordings[$path])) continue;
                $seenWordings[$path] = true;

                $content = null;
                $diskTrace = [];
                foreach (['public', 's3'] as $disk) {
                    try {
                        $exists = \Illuminate\Support\Facades\Storage::disk($disk)->exists($path);
                        $diskTrace[$disk] = $exists ? 'found' : 'missing';
                        if ($exists) {
                            $content = \Illuminate\Support\Facades\Storage::disk($disk)->get($path);
                            break;
                        }
                    } catch (\Throwable $e) {
                        $diskTrace[$disk] = 'error: ' . $e->getMessage();
                    }
                }
                if ($content === null) {
                    \Log::warning("Specialist wording not found on any disk for {$label}", [
                        'policy_id'         => $policy->id,
                        'row_id'            => $row->id,
                        'policy_coverage_id'=> $row->policy_coverage_id ?? null,
                        'path'              => $path,
                        'disks'             => $diskTrace,
                    ]);
                    continue;
                }
                $tmp = $tempDir . '/' . $label . '_' . $row->id . '_' . time() . '_' . basename($path);
                if (@file_put_contents($tmp, $content) !== false) {
                    $files[] = $tmp;
                    $attached++;
                }
            }

            // Standard-wording fall-back: coverage present on the policy but
            // no uploaded wording resolved → use the default static if it
            // exists. Existence is checked WITHOUT the wording-path filter
            // (the $rows query above only returns rows that already have an
            // upload).
            if ($attached === 0 && isset($standardWording[$label]) && is_file($standardWording[$label])) {
                try {
                    $present = $modelClass::where(function ($q) use ($policy, $pcIds) {
                        $q->where('policy_id', $policy->id);
                        if (!empty($pcIds)) $q->orWhereIn('policy_coverage_id', $pcIds);
                    })->exists();
                } catch (\Throwable $e) {
                    $present = false;
                }
                if ($present) {
                    $files[] = $standardWording[$label];
                    \Log::info("V2 quote PDF: used STANDARD wording fallback for {$label}", [
                        'policy_id' => $policy->id,
                        'path'      => $standardWording[$label],
                    ]);
                }
            }
        }

        // Customer Complaints Procedure — regulatory back-matter required on
        // EVERY policy document (COM + DOM). Added LAST so it lands at the very
        // end of the merged document (after all cover/exceptions/per-coverage/
        // specialist wordings), per Underwriting (Elaine 2026-06-11): the
        // complaints procedure must be the final page of every policy doc.
        // The merge order is [quote-sheet, ...this list], so appending here
        // makes it the closing page. Static asset already present in
        // CoverageWiseMultimark (see static-pdfs health check); fail-open if
        // it is absent on the box — the merge step skips missing files.
        $files[] = $base . '/Customer_Complaints_Procedure_July_2023.pdf';

        return $files;
    }

    /**
     * Map coverage_id → static wording PDF path. Mirrors the legacy switch
     * in PolicyController.php @ 23585-23766 exactly.
     *
     * Cross-contamination guard (CFO/Underwriting 2026-06-11): this map is
     * keyed by coverage_id only, which is product-blind, so a domestic policy
     * could pick up a commercial wording (and vice-versa) — e.g. Home Owners
     * and Household Contents exist in BOTH the newdocCom and newdocDom folders.
     * $isCom carries the policy's product nature; for a cover that exists in
     * both folders we swap to the folder matching the product. We swap ONLY
     * when the same-named file exists in the correct folder, otherwise the
     * original path is kept (fail-open — never drop a wording that has no
     * equivalent on the other side) and a warning is logged so genuine
     * mapping contamination is visible in prod logs for follow-up.
     */
    private function wordingPathForCoverage(int $coverageId, bool $isCom = true): ?string
    {
        $com = storage_path('app/CoverageWiseMultimark/NewCoveragePdf/newdocCom');
        $dom = storage_path('app/CoverageWiseMultimark/NewCoveragePdf/newdocDom');
        $path = match ($coverageId) {
            1   => "{$com}/FIRE_AND_ALLIED_PERILS.pdf",
            2   => "{$com}/Building_Combined.pdf",
            3   => "{$com}/OFFICE_CONTENTS.pdf",
            4   => "{$com}/Business_Interruptions.pdf",
            5   => "{$com}/Accounts_Received.pdf",
            6   => "{$com}/THEFT_D.pdf",
            7   => "{$com}/MONEY.pdf",
            8   => "{$com}/GLASS.pdf",
            9   => "{$com}/FIDELITY_GUARANTEE.pdf",
            10  => "{$com}/Goods_In_Transit.pdf",
            11  => "{$com}/Business_All_Risks.pdf",
            12  => "{$com}/Accidental_Damage.pdf",
            13  => "{$com}/STATED_BENEFITS_D.pdf",
            14  => "{$dom}/PERSONAL_ACCIDENT.pdf",
            15  => "{$com}/Motor_Traders_External_Risks_Commercial.pdf",
            16  => "{$com}/MOTOR_TRADERS_WORDING_Internal.pdf",
            17  => "{$com}/Household_Contents.pdf",
            18  => "{$com}/Home_Owners.pdf",
            19  => "{$com}/ELECTRONIC_EQUIPMENT.pdf",
            20  => "{$com}/WORKERSMAN_COMPENSATION.pdf",
            21  => "{$com}/Public_Liability.pdf",
            22  => "{$com}/MOTOR_SECTION.pdf",
            23  => "{$dom}/Home_Owners.pdf",
            24  => "{$dom}/Household_Contents.pdf",
            25  => "{$dom}/All_Risks.pdf",
            27  => "{$dom}/Domestic_Motor_Comprehensive.pdf",
            44  => "{$dom}/Employers_Liability.pdf",
            218 => "{$com}/MOTOR_TRADERS_WORDING_Internal.pdf",
            224 => "{$dom}/Employers_Liability.pdf",
            225 => "{$dom}/PERSONAL_ACCIDENT.pdf",
            446 => "{$dom}/COMPUTER_EQUIPMENT.pdf",
            default => null,
        };

        if ($path === null) return null;

        // Product-folder guard — see method docblock.
        $wantDir  = $isCom ? $com : $dom;
        $otherDir = $isCom ? $dom : $com;
        if (str_starts_with($path, $otherDir . '/')) {
            $candidate = $wantDir . substr($path, strlen($otherDir));
            if (is_file($candidate)) {
                \Log::info('wordingPathForCoverage: redirected wording to correct product folder', [
                    'coverage_id' => $coverageId, 'isCom' => $isCom, 'from' => $path, 'to' => $candidate,
                ]);
                return $candidate;
            }
            \Log::warning('wordingPathForCoverage: wording resolved from the OTHER product folder and no same-named equivalent exists — review product_coverage mapping', [
                'coverage_id' => $coverageId, 'isCom' => $isCom, 'path' => $path,
            ]);
        }

        return $path;
    }
}