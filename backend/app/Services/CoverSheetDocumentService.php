<?php

namespace AlphaDirect\Services;

use AlphaDirect\Models\PolicyAction;
use AlphaDirect\Models\V2PdfJob;
use AlphaDirect\Policy;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the one-page policy cover sheet: the branded A4 page we post instead
 * of the full 10–20 page pack.
 *
 * What it produces is an ordinary policy document as far as the rest of
 * Graphite is concerned — a PDF in the same storage directory, recorded as a
 * v2_pdf_jobs row with document_title = 'POLICY COVER SHEET'. That means it
 * lists beside the Policy Document and downloads through the existing
 * downloadQuotePdf route with no new download plumbing.
 *
 * The QR on the face points at /p/d/{token} on the BACKEND host, and the token
 * is minted per policy AND action, so a scan opens the document for the exact
 * transaction the sheet summarises.
 *
 * The only precondition is an ISSUED transaction. It does NOT require the
 * policy document to exist first: the QR carries a token naming the policy and
 * action, resolved when the client scans rather than when the sheet is
 * printed, so a sheet produced today starts working the moment that action's
 * document is generated. When it is missing the caller gets a `warning` to
 * pass on to staff, not a refusal.
 */
class CoverSheetDocumentService
{
    /** Where GenerateQuotationPdfJob writes, and therefore where this writes. */
    public const STORAGE_DIR = 'quote_sheet';

    /** Same artwork the policy document pulls, so the pair match on paper. */
    public const LOGO_URL = 'https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/New_Logo_on_S3/image_2021_06_18T06_30_44_271Z.png';

    public function __construct(private CoverSheetAccessService $access)
    {
    }

    /**
     * Generate the sheet for a policy and action.
     *
     * @return array{ok:bool, error?:string, message?:string, warning?:?string, jobId?:int, fileName?:string, actionId?:?int, scanUrl?:string}
     */
    public function generate(Policy $policy, ?int $actionId = null, ?int $byUserId = null): array
    {
        // Same action-resolution rule as generateV2QuoteSheet: honour the
        // action the user picked in the dropdown, else the latest.
        $action = $actionId
            ? PolicyAction::find($actionId)
            : PolicyAction::where('policy_id', $policy->id)->orderByDesc('id')->first();

        if ($action && (int) $action->policy_id !== (int) $policy->id) {
            return ['ok' => false, 'error' => 'action_mismatch',
                'message' => 'That transaction does not belong to this policy.'];
        }

        $resolvedActionId = $action?->id;

        // The only precondition is that the transaction is ISSUED. A cover
        // sheet summarises cover that is actually in force, so a QUOTE has
        // nothing to summarise.
        if (!$action || (string) $action->status !== 'ISSUED') {
            CoverSheetAccessLog::denied(CoverSheetAccessLog::EVENT_SHEET_FAILED, 'action_not_issued', [
                'policy_id' => $policy->id,
                'action_id' => $resolvedActionId,
                'user_id'   => $byUserId,
                'meta'      => ['status' => $action->status ?? null],
            ]);

            return ['ok' => false, 'error' => 'not_issued',
                'message' => 'Issue this transaction first — a cover sheet can only be produced for issued cover.'];
        }

        // Whether the policy document exists yet does NOT gate the sheet.
        // The QR carries a token naming the policy and action, and it is
        // resolved when the client scans, not when the sheet is printed — so a
        // sheet produced now starts working the moment the Policy Document for
        // this action is generated. Until then a client who scans gets the
        // "we cannot open your document yet" page telling them to call us,
        // which is recorded as DOWNLOAD_FAILED / no_document_for_action.
        //
        // Staff are warned rather than blocked, so the warning has to be
        // surfaced in the UI that calls this.
        $document = $this->access->latestDocument((int) $policy->id, $resolvedActionId ? (int) $resolvedActionId : null);

        try {
            // One live token per policy: issuing revokes the previous one, so
            // an older printed sheet stops working. Deliberate — a reprint
            // should invalidate the sheet it replaces.
            $token   = $this->access->issueToken($policy, $resolvedActionId, $byUserId, 'cover_sheet');
            $scanUrl = $this->scanUrl($token);

            $html = view('coversheet.sheet', $this->faceData($policy, $action, $scanUrl))->render();

            $pdf = \PDF::loadHTML($html)->setPaper('a4', 'portrait');
            $bytes = $pdf->output();

            $fileName = 'COVERSHEET' . $policy->id
                . '_' . ($resolvedActionId ?: 0)
                . '_' . time() . '_cover_sheet.pdf';

            $this->store($fileName, $bytes);

            $job = V2PdfJob::create([
                'policy_id'          => $policy->id,
                'action_id'          => $resolvedActionId,
                'created_by_user_id' => $byUserId,
                'status'             => 'completed',
                'progress'           => 100,
                'message'            => 'Cover sheet ready for download.',
                'file_name'          => $fileName,
                'document_title'     => CoverSheetAccessService::COVER_SHEET_TITLE,
            ]);

            CoverSheetAccessLog::ok(CoverSheetAccessLog::EVENT_SHEET_GENERATED, [
                'policy_id' => $policy->id,
                'action_id' => $resolvedActionId,
                'user_id'   => $byUserId,
                'meta'      => [
                    'pdf_job_id'      => $job->id,
                    'file_name'       => $fileName,
                    'bytes'           => strlen($bytes),
                    // Null when the Policy Document for this action has not
                    // been generated yet — the QR starts working once it is.
                    'opens_pdf_job_id' => $document->id ?? null,
                ],
            ]);

            return [
                'ok'       => true,
                'jobId'    => (int) $job->id,
                'fileName' => $fileName,
                'actionId' => $resolvedActionId,
                'scanUrl'  => $scanUrl,
                // Non-blocking: the sheet is valid, but tell staff the QR has
                // nothing to open until the Policy Document for this action
                // is generated.
                'warning'  => $document
                    ? null
                    : 'Cover sheet created, but the Policy Document for this transaction has not been generated yet. The QR code will start working as soon as it is — generate it before posting the sheet.',
            ];
        } catch (\Throwable $e) {
            CoverSheetAccessLog::error(CoverSheetAccessLog::EVENT_SHEET_FAILED, 'render_failed', [
                'policy_id' => $policy->id,
                'action_id' => $resolvedActionId,
                'user_id'   => $byUserId,
                'meta'      => ['error' => $e->getMessage()],
            ]);
            Log::error('cover_sheet.generate_failed', [
                'policy_id' => $policy->id,
                'action_id' => $resolvedActionId,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return ['ok' => false, 'error' => 'render_failed',
                'message' => 'The cover sheet could not be generated. ' . $e->getMessage()];
        }
    }

    // ─── Internals ───────────────────────────────────────────────────────

    /**
     * The URL printed into the QR (and in small type beside it).
     *
     * Must be the BACKEND host: the ALB routes by host-header, so the frontend
     * domain would never reach these Laravel routes. SELF_URL is the existing
     * setting the quote-sheet QR already uses — if it points at the frontend,
     * that must be corrected before any sheet is printed, because the URL
     * lives on paper for the whole policy term.
     */
    private function scanUrl(string $token): string
    {
        $base = rtrim((string) (env('COVER_SHEET_BASE_URL') ?: env('SELF_URL') ?: config('app.url')), '/');

        return $base . '/p/d/' . $token;
    }

    /** The QR image, as a data URI so DomPDF needs no network for it. */
    private function qrDataUri(string $scanUrl): string
    {
        // Same library the quote sheets already use.
        $result = \Endroid\QrCode\Builder\Builder::create()
            ->writer(new \Endroid\QrCode\Writer\PngWriter())
            ->data($scanUrl)
            ->size(600)
            ->margin(8)
            ->build();

        return 'data:image/png;base64,' . base64_encode($result->getString());
    }

    /**
     * Everything printed on the face.
     *
     * Insured name follows the policy document's own rule — company name for
     * an Organisation, otherwise the customer's name — so the sheet and the
     * pack never disagree about who is insured.
     */
    private function faceData(Policy $policy, ?PolicyAction $action, string $scanUrl): array
    {
        $policy->loadMissing(['customer', 'profile', 'profile.company', 'product', 'agency']);

        $totals = app(CoverSheetTotalsService::class)->forAction(
            (int) $policy->id,
            $action?->id ? (int) $action->id : null,
            (int) ($policy->product_id ?? 0)
        );

        $isOrg = ($policy->profile->entity_type ?? null) === 'Organisation';
        $insuredName = $isOrg
            ? (string) ($policy->profile->company->name ?? '')
            : trim((string) ($policy->customer->firstName ?? '') . ' ' . (string) ($policy->customer->lastName ?? ''));

        $product = (string) ($policy->product->name ?? $policy->product->s_ProductName ?? '');

        return [
            'today'          => now()->format('d/m/Y'),
            'logoUrl'        => self::LOGO_URL,
            'policyNumber'   => (string) $policy->policyNumber,
            'lineOfBusiness' => $product !== '' ? $product : 'Insurance Policy',
            'classOfCover'   => $product !== '' ? $product : 'As per schedule',
            'insuredName'    => $insuredName !== '' ? $insuredName : 'The Insured',
            'insuredAddress' => $this->mailingAddress($policy),
            'periodFrom'     => $this->date($action?->effective_from),
            'periodTo'       => $this->date($action?->effective_to),
            // Summed across the coverage tables that hold a sum insured,
            // scoped to this action. Null when it cannot be totalled (e.g.
            // Motor Traders), and the blade then points at the schedule
            // rather than printing a figure we cannot stand behind.
            'sumInsured'     => $totals['sumInsured'] !== null && $totals['complete']
                ? $this->money($totals['sumInsured'])
                : null,
            // Canonical: the Rate banner value for this action, never a
            // per-section sum.
            'premium'        => $this->money($totals['premium']),
            'premiumBasis'   => 'Gross premium for this period, including VAT',
            'intermediary'   => (string) ($policy->agency->name ?? '') ?: null,
            'issuedOn'       => now()->format('d/m/Y'),
            'qrDataUri'      => $this->qrDataUri($scanUrl),
            'scanUrl'        => $scanUrl,
        ];
    }

    /** Postal address as one line, or null when we hold nothing usable. */
    private function mailingAddress(Policy $policy): ?string
    {
        $parts = array_filter([
            $policy->profile->postal_address ?? null,
            $policy->profile->physical_address ?? null,
            $policy->profile->cities->name ?? null,
        ], fn ($v) => is_string($v) && trim($v) !== '');

        if ($parts === []) {
            return null;
        }

        return implode(', ', array_map('trim', array_slice($parts, 0, 2)));
    }

    private function date($value): string
    {
        if (empty($value)) {
            return '—';
        }

        try {
            return Carbon::parse((string) $value)->format('d/m/Y');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    private function money($value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return 'BWP ' . number_format((float) $value, 2);
    }

    /**
     * Write the PDF where the existing download route looks for it: the local
     * storage path first, then mirror to the named disks. Disks are always
     * named — the default disk points at a dead legacy bucket.
     */
    private function store(string $fileName, string $bytes): void
    {
        $path = self::STORAGE_DIR . '/' . $fileName;
        $abs  = storage_path('app/public/' . $path);

        $dir = dirname($abs);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $written = @file_put_contents($abs, $bytes);

        // Mirror to the 'public' disk when it resolves elsewhere (containers
        // with EFS mounts), so the download route's second lookup succeeds.
        try {
            Storage::disk('public')->put($path, $bytes);
        } catch (\Throwable $e) {
            Log::warning('cover_sheet.public_disk_write_failed', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
        }

        if ($written === false) {
            // Not fatal on its own — the disk write above may have succeeded —
            // but it must be visible.
            Log::warning('cover_sheet.local_write_failed', ['path' => $abs]);
        }
    }
}
