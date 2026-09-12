<?php

namespace AlphaDirect\Services\Reinsurance;

use AlphaDirect\Models\TreatyReserveDeposit;
use AlphaDirect\Models\TreatyStatement;
use AlphaDirect\Models\TreatyStatementItem;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * The rendered statement of account — RI-18 step 8, BR-ACC-03.
 *
 * A DOCUMENT THAT DOES NOT FOOT IS NOT RENDERED AT ALL. Everything before this
 * produced figures; this turns them into something a reinsurer receives, and the
 * one failure that must never leave the building is an account whose lines do
 * not add up to its balance. summaryFor() computes the two by different routes
 * on purpose, and this refuses when they disagree.
 *
 * IT FAILS RATHER THAN FALLING BACK, exactly as FacSlipService does and for the
 * reason recorded there: an earlier version of that service returned raw HTML
 * and stored it at a .pdf path, so a reinsurer would have been emailed a
 * contractual document no PDF reader could open, and the register would have
 * recorded it as sent. If the renderer is unavailable, nothing is produced.
 *
 * "CANNOT BE ISSUED" IS NOT "CANNOT BE PRODUCED". Without the signing schedule
 * the figures are right and the per-reinsurer allocation is absent, which is
 * precisely the document somebody would send by mistake. So it is produced —
 * the quarter still has to be checked and agreed internally — and it declares
 * itself an internal working copy on its face, in the brand's own orange, rather
 * than in a footnote.
 */
class StatementDocumentService
{
    /** Line labels, in the order Article 10.2 lists them. */
    private const SETTLING_ORDER = [
        TreatyStatementItem::PREMIUM         => 'Written premium ceded, less returns and cancellations',
        TreatyStatementItem::COMMISSION      => 'Ceding commission',
        TreatyStatementItem::BROKERAGE       => 'Brokerage',
        TreatyStatementItem::CLAIMS_PAID     => 'Claims paid, less salvages and recoveries',
        TreatyStatementItem::CASH_LOSS_RECOVERY => 'Cash loss recoveries',
        TreatyStatementItem::RESERVE_DEPOSIT => 'Reserve deposit retained',
        TreatyStatementItem::RESERVE_RELEASE => 'Reserve deposit released',
        TreatyStatementItem::RESERVE_INTEREST => 'Interest on reserve deposit',
        TreatyStatementItem::VAT             => 'VAT',
        TreatyStatementItem::DELAY_INTEREST  => 'Delay in payment interest',
    ];

    private const MEMORANDUM_LABELS = [
        TreatyStatementItem::OUTSTANDING_LOSSES => 'Outstanding losses',
        TreatyStatementItem::SALVAGES           => 'Salvages',
        TreatyStatementItem::RECOVERIES         => 'Recoveries',
    ];

    public function __construct(private StatementBalanceBuilder $balances)
    {
    }

    /**
     * Why this account could not be sent to reinsurers, if it could not.
     *
     * @return array<int,string>
     */
    public function issueBlockers(TreatyStatement $statement): array
    {
        $blockers = [];

        if ($statement->shares()->count() === 0) {
            $blockers[] = 'No signing schedule. BR-ACC-03 requires the account broken down by '
                . 'share and BR-SEC-08 requires every ceded amount allocated per reinsurer, '
                . 'reconciling to the total. The panel is not placed.';
        }

        if ($statement->items()->count() === 0) {
            $blockers[] = 'The statement carries no items. Nothing has been built for this quarter.';
        }

        return $blockers;
    }

    /**
     * Everything the template prints, assembled and checked.
     *
     * @return array<string,mixed>
     */
    public function viewData(TreatyStatement $statement): array
    {
        $summary = $this->balances->summaryFor($statement);

        if (! $summary['foots']) {
            throw new RuntimeException(sprintf(
                'This statement does not foot and will not be rendered: the settling lines sum '
                . 'to %s and the balance reads %s. An account that does not add up must not '
                . 'become a document somebody can send.',
                number_format($summary['settling'], 2),
                number_format($summary['balance'], 2)
            ));
        }

        $blockers = $this->issueBlockers($statement);

        return [
            'statement'       => $statement,
            'period'          => TreatyStatement::periodFor(
                (int) $statement->underwriting_year,
                (int) $statement->quarter
            ),
            'confirmDue'      => $statement->getRawOriginal('confirm_due'),
            'sections'        => $this->settlingSections($statement),
            'memorandum'      => $this->memorandumRows($statement),
            'outstandingAxis' => $this->axisLabel($statement),
            'shares'          => $this->shareRows($statement),
            'reserves'        => $this->reserveRows($statement),
            'balance'         => $summary['balance'],
            'issuable'        => $blockers === [],
            'blockers'        => $blockers,
            'cessionBasis'    => (string) ($statement->cession_basis ?: 'legacy'),
            'generatedAt'     => date('j F Y'),
        ];
    }

    /**
     * Render the account. FAILS if the PDF renderer is unavailable — never falls
     * back to HTML, because HTML stored at a .pdf path is a document a reinsurer
     * cannot open and the system records as sent.
     */
    public function renderPdf(TreatyStatement $statement): string
    {
        $data = $this->viewData($statement);

        try {
            return \PDF::loadView('Reinsurance.treaty-statement', $data)->output();
        } catch (\Throwable $e) {
            Log::error('Treaty statement PDF renderer unavailable', [
                'statement' => $statement->id,
                'treaty'    => $statement->treaty,
                'error'     => $e->getMessage(),
            ]);

            throw new RuntimeException(
                'The statement could not be turned into a PDF, so nothing was saved. '
                . 'The document generator is unavailable — tell IT before trying again.'
            );
        }
    }

    /**
     * The settling items, grouped by type and broken down by class.
     *
     * BY CLASS, BECAUSE BR-ACC-03 SAYS SO, and the section total is the sum of
     * the class lines rather than the rate applied to a total — so the account
     * adds up as printed. A cent of difference between the two is real and the
     * printed one is the one that has to be right.
     *
     * @return array<int,array<string,mixed>>
     */
    private function settlingSections(TreatyStatement $statement): array
    {
        $items = $statement->items()->settling()->get();
        $sections = [];

        foreach (self::SETTLING_ORDER as $type => $heading) {
            $ofType = $items->where('item_type', $type);

            if ($ofType->isEmpty()) {
                continue;
            }

            $rows = [];
            foreach ($ofType->sortBy('regulatory_class') as $i) {
                $rows[] = [
                    'label'  => $i->regulatory_class ?: $heading,
                    'rate'   => $i->rate !== null
                        ? number_format((float) $i->rate * 100, 2) . '%'
                        : null,
                    'amount' => (float) $i->amount,
                ];
            }

            $sections[] = [
                'heading'     => strtoupper($heading),
                'rows'        => $rows,
                // ASCII ONLY IN ANYTHING THAT REACHES THE PAGE. The PDF facade
                // tries wkhtmltopdf first and falls back to DomPDF, and the
                // fallback renders an em dash as a replacement character — so a
                // statement produced on a box without wkhtmltopdf would go to a
                // reinsurer with mojibake in its headings.
                'total_label' => $heading . ' - total',
                'total'       => round((float) $ofType->sum('amount'), 2),
            ];
        }

        return $sections;
    }

    /** @return array<int,array<string,mixed>> */
    private function memorandumRows(TreatyStatement $statement): array
    {
        $rows = [];

        foreach ($statement->items()->whereIn('item_type', array_keys(self::MEMORANDUM_LABELS))
            ->orderBy('item_type')->orderBy('period_year')->orderBy('regulatory_class')->get() as $i) {
            $label = self::MEMORANDUM_LABELS[$i->item_type] ?? $i->item_type;

            if ($i->regulatory_class) {
                $label .= ' - ' . $i->regulatory_class;
            }
            if ($i->period_year) {
                $label .= ' (' . $i->period_year . ')';
            }

            $rows[] = ['label' => $label, 'rate' => null, 'amount' => (float) $i->amount];
        }

        return $rows;
    }

    /** Which axis the outstanding losses are shown on — BR-ACC-07. */
    private function axisLabel(TreatyStatement $statement): string
    {
        $axis = $statement->items()
            ->where('item_type', TreatyStatementItem::OUTSTANDING_LOSSES)
            ->value('period_axis');

        return $axis === TreatyStatementItem::AXIS_UNDERWRITING
            ? 'underwriting year'
            : 'year of occurrence';
    }

    /**
     * Each reinsurer's total across the account.
     *
     * SUMMED ACROSS THE LINES, because a share hangs off an item: BR-SEC-08
     * allocates every ceded amount per reinsurer, so a reinsurer appears once
     * per line it participates in and its position on the account is the sum.
     *
     * THE PERCENTAGE PRINTED IS THE SHARE OF 100%, which is how the slips read
     * it and how the panel is quoted — GIC Re's own note is "17% of cession or
     * 11.90% of 100%". Printing the cession basis beside a 100%-basis panel
     * would invite a reinsurer to check its share against the wrong number.
     *
     * @return array<int,array<string,mixed>>
     */
    private function shareRows(TreatyStatement $statement): array
    {
        $rows = [];

        foreach ($statement->shares()->get() as $s) {
            $name = (string) $s->reinsurer;

            $rows[$name] ??= ['reinsurer' => $name, 'share_pct' => 0.0, 'amount' => 0.0];
            $rows[$name]['amount'] += (float) $s->amount;
            $rows[$name]['share_pct'] = (float) ($s->share_of_hundred_pct ?? 0);
        }

        foreach ($rows as &$r) {
            $r['amount'] = round($r['amount'], 2);
        }

        ksort($rows);

        return array_values($rows);
    }

    /** @return array<int,array<string,mixed>> */
    private function reserveRows(TreatyStatement $statement): array
    {
        return TreatyReserveDeposit::where('treaty_statement_id', $statement->id)
            ->orderBy('reinsurer')->get()
            ->map(fn (TreatyReserveDeposit $d) => [
                'reinsurer'       => (string) $d->reinsurer,
                'retained_pct'    => (float) $d->retained_pct,
                'balance_carried' => (float) $d->balance_carried,
                'exemption'       => $d->exemption(),
            ])->all();
    }
}
