<?php

namespace AlphaDirect\Services\Reinsurance;

use AlphaDirect\Mail\FacSlipMail;
use AlphaDirect\Models\FacPlacement;
use AlphaDirect\Models\FacPlacementAttachment;
use AlphaDirect\Models\FacSlip;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * FAC slips — generated from the register, then sent to the reinsurer or broker.
 *
 * A slip is a PARENT over one or more placement lines, because a slip number is
 * not unique per line. On the June FY26 sheet slip 2024-149 carries three lines
 * (M P MINING, one per monthly instalment) and slip 2024-060 carries two.
 * Generating per line would send one reinsurer three near-identical documents
 * for a single risk.
 *
 * GENERATION is automatic. SENDING is not: an outbound slip is a contractual
 * communication to a reinsurer, so it stays an explicit, permissioned human
 * action unless `fac.slips.auto_send` is deliberately turned on.
 */
class FacSlipService
{
    public function __construct(private FacRegisterService $register)
    {
    }

    /**
     * Build (or rebuild) the slip document for a slip number and store it on S3.
     *
     * Rebuilding bumps the version and supersedes the previous slip rather than
     * overwriting it — a reinsurer may already hold the old one, so it has to
     * stay retrievable.
     */
    public function generate(string $slipNo, ?string $placementType = null): FacSlip
    {
        $lines = FacPlacement::query()
            ->where('fac_slip_no', $slipNo)
            ->when($placementType, fn ($q) => $q->where('placement_type', $placementType))
            ->whereNull('deleted_at')
            ->where('is_reversal', false)
            ->orderBy('period_from')
            ->orderBy('id')
            ->get();

        if ($lines->isEmpty()) {
            throw new \RuntimeException("No placements found on slip {$slipNo}.");
        }

        // Every line on a slip must face the same counterparty. If it does not,
        // the slip number has been reused for two different placements and that
        // is a data problem the underwriter has to fix, not something to paper
        // over by picking one.
        $counterparties = $lines->pluck('counterparty_name')->filter()->unique();
        if ($counterparties->count() > 1) {
            throw new \RuntimeException(sprintf(
                'Slip %s carries more than one counterparty (%s). Split the slip before generating it.',
                $slipNo,
                $counterparties->implode(', ')
            ));
        }

        // Nor may a slip span two currencies. The money rows are the SUM of the
        // lines, so a slip carrying Pula and Dollars states a total that is in no
        // currency at all — on a document the reinsurer signs. Refusing is the
        // only honest outcome: there is no exchange rate on the slip, and picking
        // one here would invent a figure nobody agreed.
        $currencies = $lines->pluck('currency')->filter()->unique();
        if ($currencies->count() > 1) {
            throw new \RuntimeException(sprintf(
                'Slip %s carries more than one currency (%s). A slip states one total, so it '
                . 'must be written in one currency. Split the slip before generating it.',
                $slipNo,
                $currencies->sort()->implode(', ')
            ));
        }

        $first = $lines->first();

        return DB::transaction(function () use ($slipNo, $lines, $first) {
            // Always a new version, never an overwrite. A reinsurer may already
            // hold the previous slip, so it has to stay retrievable. (Soft
            // deletes would also collide with the (slip_no, version) unique key.)
            $existing = FacSlip::withTrashed()->where('slip_no', $slipNo)->max('version');
            $version  = $existing ? ((int) $existing) + 1 : 1;

            /*
             * The wording an underwriter typed carries forward to the new version.
             *
             * Regenerating rebuilds the FIGURES from the placement lines. It is not
             * an instruction to discard the legal terms somebody entered by hand —
             * and losing them silently on a regeneration would be worse than never
             * having captured them, because the slip would still print, just with
             * the migration default back in place of the agreed wording.
             *
             * Read before the supersede below, which is what makes this the
             * outgoing version.
             */
            $prior = FacSlip::where('slip_no', $slipNo)
                ->where('status', '!=', 'superseded')
                ->orderByDesc('version')
                ->first();

            $carried = [];
            foreach ([
                'description_of_risk', 'territorial_scope', 'deductible_text',
                'risk_ceded_text',
                // The placement terms the underwriter typed. Carried for the same
                // reason as the rest: losing them on a regeneration is worse than
                // never having captured them, because the slip still prints.
                'slip_notes',
            ] as $term) {
                // Only when the prior slip actually holds one. Passing null would
                // override the column default — territorial_scope has one — and
                // blank a term that was never edited.
                if ($prior && ($prior->{$term} ?? null) !== null && $prior->{$term} !== '') {
                    $carried[$term] = $prior->{$term};
                }
            }

            /*
             * THE BASIS, AND WHICH AUTHORITY STATED IT.
             *
             * Reinsurance's rule of 24 August 2026 was that the slip follows the
             * policy, so the basis could only be read from it. On 7 September they
             * asked for the underwriter to state it as agreed with the reinsurer,
             * because a facultative cession can genuinely be written on a
             * different basis from the policy underneath it.
             *
             * AN UNDERWRITER'S OVERRIDE SURVIVES A REGENERATION. Letting the
             * policy win here would silently revert an agreed reinsurance term to
             * the policy's, print it, and leave nothing behind to say it had
             * changed — the exact failure the carry-forward above exists to stop.
             *
             * The third branch is the case the old rule already allowed: a policy
             * that cannot answer, carrying two bases or none, which the
             * underwriter had stated by hand before this column existed.
             */
            $policyBasis = $this->register->basisOfCoverFor(
                $first->policy_id ? (int) $first->policy_id : null
            );
            $priorBasis  = ($prior->basis_of_cover ?? null) ?: null;
            $priorSource = $prior->basis_of_cover_source ?? null;

            if ($priorSource === 'underwriter' && $priorBasis !== null) {
                $basis       = $priorBasis;
                $basisSource = 'underwriter';
            } elseif ($policyBasis) {
                $basis       = $policyBasis;
                $basisSource = 'policy';
            } elseif ($priorBasis !== null) {
                $basis       = $priorBasis;
                $basisSource = 'underwriter';
            } else {
                $basis       = null;
                $basisSource = null;
            }

            FacSlip::where('slip_no', $slipNo)
                ->where('status', '!=', 'superseded')
                ->update(['status' => 'superseded']);

            $slip = FacSlip::create([
                'slip_no'           => $slipNo,
                'version'           => $version,
                'financial_year'    => $first->financial_year,
                'placement_type'    => $first->placement_type,
                'policy_number'     => $first->policy_number,
                'policy_id'         => $first->policy_id,
                'insured_name'      => $first->insured_name,
                'counterparty_id'   => $first->counterparty_id,
                'counterparty_name' => $first->counterparty_name,
                'risk_carrier'      => $first->risk_carrier,
                'status'            => 'generated',
                'generated_at'      => now(),
                'created_by'        => Auth::id(),

                // Term-sheet fields, defaulted from the placement lines. They
                // are editable afterwards — Reinsurance owns the wording — but
                // a slip must be printable the moment it is generated.
                'broker_agent'      => $this->brokerAgentFor($first),
                'cover_granted'     => $first->ri_group_label,

                // Decided above, with the authority that stated it recorded
                // beside it. The policy answers where it can; an underwriter's
                // override is kept and is not silently reverted here.
                'basis_of_cover'        => $basis,
                'basis_of_cover_source' => $basisSource,
                'period_from'       => $first->period_from,
                'period_to'         => $lines->max('period_to') ?: $first->period_to,
                'limit_of_indemnity'=> $lines->max('cession_sum_insured'),
                // The UNDERWRITER in charge of the placement, not whoever happened
                // to be logged in when the slip was generated. Reinsurance reported
                // this printing blank: the logged-in user's name was empty and the
                // underwriter — which the placement does hold — was never reached.
                // Who generated it is on the audit trail, which is its proper place.
                'prepared_by_name'  => $first->underwriter_name ?: (Auth::user()->name ?? null),
            ] + $carried);

            // Pre-populate the acceptance panel from the lines, so the document
            // goes out with something for the reinsurer to sign against.
            foreach ($lines as $l) {
                \AlphaDirect\Models\FacSlipAcceptance::create([
                    'fac_slip_id'       => $slip->id,
                    'reinsurer_id'      => $l->counterparty_id,
                    'accepting_company' => $l->risk_carrier ?: $l->counterparty_name,
                    'share_pct'         => $l->risk_pct,
                    'amount'            => $l->cession_sum_insured,
                ]);
            }
            $slip->load('acceptances');

            $pdf  = $this->renderPdf($slip, $lines);
            $path = sprintf(
                '%s/slips/%s/%s-v%d.pdf',
                trim((string) config('fac.slips.s3_prefix', 'fac'), '/'),
                preg_replace('/[^A-Za-z0-9\-_]/', '_', $slipNo),
                preg_replace('/[^A-Za-z0-9\-_]/', '_', $slipNo),
                $version
            );

            // Storing is where this fails in practice, not rendering.
            //
            // The test environment's S3 credentials are rejected outright
            // ("InvalidAccessKeyId: The AWS Access Key Id you provided does not
            // exist in our records"), and the raw SDK exception reached the
            // capturer as a wall of bucket URL and error code naming neither the
            // cause nor anybody who could fix it — so it read as "slip generation
            // is broken" when the slip itself was fine.
            //
            // Still NO fallback: a slip is a contractual document and storing it
            // somewhere unintended is worse than not storing it. But the refusal
            // now says what happened and who owns it.
            try {
                Storage::disk(config('fac.slips.disk', 's3'))->put($path, $pdf);
            } catch (\Throwable $e) {
                Log::error('FAC slip could not be stored', [
                    'slip'  => $slipNo,
                    'disk'  => config('fac.slips.disk', 's3'),
                    'path'  => $path,
                    'error' => $e->getMessage(),
                ]);
                throw new \RuntimeException(sprintf(
                    'The slip for %s was produced but could not be filed to document storage, '
                    . 'so nothing was saved. The placement itself is fine — this is a storage '
                    . 'configuration problem on disk "%s". Tell IT. Detail: %s',
                    $slipNo,
                    config('fac.slips.disk', 's3'),
                    $e->getMessage()
                ));
            }
            $slip->update(['document_path' => $path]);

            // Stamp the lines and file the slip against the first line so it is
            // reachable from the placement detail screen.
            FacPlacement::whereIn('id', $lines->pluck('id'))->update([
                'fac_slip_id'       => $slip->id,
                'slip_generated_at' => now(),
            ]);

            FacPlacementAttachment::create([
                'fac_placement_id' => $first->id,
                'doc_type'         => 'fac_slip',
                'original_name'    => "FAC-Slip-{$slipNo}-v{$version}.pdf",
                'path'             => $path,
                'mime'             => 'application/pdf',
                'size'             => strlen($pdf),
                'note'             => sprintf('Auto-generated slip covering %d placement line(s).', $lines->count()),
                'uploaded_by'      => Auth::id(),
                'uploaded_by_name' => Auth::user()->name ?? null,
            ]);

            $this->register->recordEvent($first, 'slip_generated', sprintf(
                'FAC slip %s v%d generated for %s, covering %d line(s).',
                $slipNo,
                $version,
                $slip->counterparty_name ?? 'the counterparty',
                $lines->count()
            ), ['slip_no' => $slipNo, 'version' => $version, 'lines' => $lines->count()]);

            if (config('fac.slips.auto_send')) {
                $this->send($slip);
            }

            return $slip->fresh();
        });
    }

    /**
     * Email the slip to the counterparty.
     *
     * Refuses to send without an address rather than silently doing nothing —
     * a slip that was never sent must not look sent.
     */
    public function send(FacSlip $slip, ?string $overrideTo = null): FacSlip
    {
        if (!$slip->document_path) {
            throw new \RuntimeException('This slip has no generated document. Generate it first.');
        }

        $to = $overrideTo
            ?: ($slip->counterparty_id
                ? DB::table('reinsurer')->where('id', $slip->counterparty_id)->value('email')
                : null);

        if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException(sprintf(
                'No valid email address is held for %s. Add it on the Reinsurers screen, then send.',
                $slip->counterparty_name ?? 'this counterparty'
            ));
        }

        $lines = $slip->placements()->whereNull('deleted_at')->get();
        $bcc   = (array) config('fac.recipients.slip_bcc', []);
        $pdf   = Storage::disk(config('fac.slips.disk', 's3'))->get($slip->document_path);

        try {
            $mail = Mail::to($to);
            if ($bcc) {
                $mail->bcc($bcc);
            }
            $mail->send(new FacSlipMail($slip, $lines, $pdf));

            $slip->update([
                'status'     => 'sent',
                'sent_at'    => now(),
                'sent_to'    => $to,
                'send_error' => null,
            ]);
            FacPlacement::whereIn('id', $lines->pluck('id'))->update(['slip_sent_at' => now()]);
        } catch (\Throwable $e) {
            // The failure is recorded ON the slip. It never silently reads as sent.
            $slip->update(['send_error' => $e->getMessage()]);
            Log::warning('FAC slip send failed', ['slip' => $slip->slip_no, 'error' => $e->getMessage()]);
            throw new \RuntimeException('The slip could not be emailed: ' . $e->getMessage());
        }

        $first = $lines->first();
        if ($first) {
            $this->register->recordEvent($first, 'slip_sent', sprintf(
                'FAC slip %s v%d emailed to %s (%s).',
                $slip->slip_no,
                $slip->version,
                $slip->counterparty_name ?? 'counterparty',
                $to
            ), ['slip_no' => $slip->slip_no, 'to' => $to]);
        }

        return $slip->fresh();
    }

    /**
     * Generate every slip that has placements but no document yet.
     * Used by the nightly `fac:generate-slips` command.
     *
     * @return array{generated:int, skipped:int, errors:array<int,string>}
     */
    public function generateMissing(int $limit = 200): array
    {
        $slipNos = FacPlacement::query()
            ->whereNull('deleted_at')
            ->where('is_reversal', false)
            ->whereNotNull('fac_slip_no')
            ->where('fac_slip_no', '!=', '-')
            ->whereNull('slip_generated_at')
            ->whereIn('status', ['placed', 'awaiting_premium', 'client_paid', 'ready_to_settle'])
            ->distinct()
            ->limit($limit)
            ->pluck('fac_slip_no');

        $generated = 0;
        $skipped   = 0;
        $errors    = [];

        foreach ($slipNos as $no) {
            try {
                $this->generate($no);
                $generated++;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = "{$no}: " . $e->getMessage();
            }
        }

        return ['generated' => $generated, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * "DIRECT" when no broker fronts the placement — the real slips print the
     * word rather than leaving the row blank. A counterparty typed as a broker
     * IS the broker/agent.
     */
    private function brokerAgentFor(FacPlacement $first): string
    {
        if (!$first->counterparty_id) {
            return 'DIRECT';
        }
        $type = DB::table('reinsurer')->where('id', $first->counterparty_id)->value('counterparty_type');

        return $type === 'broker' ? (string) $first->counterparty_name : 'DIRECT';
    }

    /** Render the slip. FAILS if the PDF renderer is unavailable — never falls back to HTML. */
    private function renderPdf(FacSlip $slip, $lines): string
    {
        $slip->loadMissing('acceptances');
        $data = [
            'slip'        => $slip,
            'lines'       => $lines,
            'generatedAt' => now(),
            // What is actually insured, itemised. Read from the FIRST line's
            // placement: a slip groups lines that share a number, and the schedule
            // describes the risk, which is the same risk on every line.
            'schedule'    => \AlphaDirect\Models\FacPlacementScheduleItem::scheduleFor(
                optional($lines->first())->id
            ),
        ];

        try {
            return \PDF::loadView('Reinsurance.fac-slip', $data)->output();
        } catch (\Throwable $e) {
            // FAIL, do not fall back. An earlier version returned raw HTML and
            // stored it at a .pdf path — so send() would have emailed a reinsurer
            // a corrupt contractual document that no PDF reader could open, and
            // the register would have recorded it as sent.
            Log::error('FAC slip PDF renderer unavailable', [
                'slip'  => $slip->slip_no,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException(
                'The slip could not be turned into a PDF, so nothing was saved. '
                . 'The document generator is unavailable — tell IT before trying again.'
            );
        }
    }
}
