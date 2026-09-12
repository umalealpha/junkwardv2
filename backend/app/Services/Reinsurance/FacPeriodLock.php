<?php

namespace AlphaDirect\Services\Reinsurance;

use AlphaDirect\Models\FacPeriodSnapshot;
use AlphaDirect\Models\FacPlacement;
use AlphaDirect\Models\FacPlacementEvent;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * A closed month freezes the lines inside it — the control FacMoneyPathsTest pinned.
 *
 * Closing a period writes fac_period_snapshots, and Finance posts the journal
 * from that snapshot. Until now NOTHING read it back: a placement's premium could
 * be edited after its month closed, the register would move, the snapshot would
 * not, and no control anywhere reported the difference. The register and the
 * posted journal simply stopped agreeing, silently. FacRegisterService::update()
 * already refuses to re-derive `financial_year` for this exact reason -- "some of
 * those periods are already closed" -- so the intent was recognised; only the
 * enforcement was missing.
 *
 * THE GUARD IS ON THE MODEL, NOT THE CONTROLLER, and that is the whole design.
 * The test that found this changed the premium with
 * FacPlacement::find($id)->update([...]) -- no controller, no service. A guard in
 * FacRegisterApiController would have left that path open, and with it every
 * console command, cron job and tinker session. Eloquent's saving event is the
 * only place that sees them all.
 *
 * WHAT COUNTS AS INSIDE A CLOSED PERIOD. payableByCounterparty() scopes a period
 * as `created_at <= period_end` on anything that is not a draft, so a snapshot is
 * a RUNNING TOTAL rather than a month's activity -- a line created in June sits in
 * the June, July and August snapshots alike. A placement is therefore locked when
 * it was created on or before the LATEST closed period end, because changing it
 * would move every snapshot from its own month forward, not just one.
 *
 * ONLY THE FIELDS A SNAPSHOT ACTUALLY CARRIES ARE FROZEN. Locking the whole row
 * would stop a placement being marked settled after its month closed, which is
 * ordinary operational work and moves no money that a snapshot reports. So the
 * money and the grouping keys are frozen; status is frozen only where it crosses
 * the draft boundary, because that is what adds a line to a period or removes one.
 *
 * A REOPENING IS ALLOWED AND RECORDED, never silent. BR-style corrections do
 * happen -- a premium captured wrong in a month Finance has closed has to be
 * fixable -- so withReopened() exists. It demands a reason, writes an append-only
 * event against the placement, and leaves the audit trail the model already keeps.
 * A bypass nobody can see afterwards would make the lock worth nothing.
 */
class FacPeriodLock
{
    /**
     * Fields whose value reaches a frozen snapshot.
     *
     * The three sums payableByCounterparty() reports, the four columns it groups
     * by -- placement_type, currency, counterparty_id, counterparty_name, which
     * together are the snapshot's unique key -- plus the two that decide which
     * period a line belongs to at all.
     */
    public const FROZEN_FIELDS = [
        'gross_ceded_premium',
        'gross_ceded_premium_excl_vat',
        'commission_excl_vat',
        'placement_type',
        'currency',
        'counterparty_id',
        'counterparty_name',
        'financial_year',
        'created_at',
    ];

    /** Set only inside withReopened(). The reason, never just a boolean. */
    private static ?string $reopenReason = null;

    /**
     * The answer to "how far is the register closed", memoised.
     *
     * WITHOUT THIS THE GUARD COSTS A QUERY PER SAVE. It runs on every
     * FacPlacement save in the application, and a bulk path — the auto-entry
     * command raising instalment lines, or an import — saves thousands in one
     * process. The first cut resolved a fresh MAX(period_end) each time and took
     * the reinsurance suite from four minutes past ten.
     *
     * ON THE INSTANCE AND NOT IN A STATIC, deliberately. A static would survive
     * between tests: one that closed a period would leave every later test
     * believing the register was closed to that date, against a database holding
     * no snapshots at all. That is the same class of leak as the connection one
     * StatementReserveDepositBuilderTest's tearDown exists to prevent. Bound as a
     * singleton in AppServiceProvider, so it is shared within a process and
     * rebuilt with the container — which the test suite does per test.
     *
     * `resolved` is separate because null is a real answer: nothing is closed.
     */
    private ?string $closedTo = null;
    private bool $resolved = false;

    /** Called when a snapshot is written or removed — the answer has moved. */
    public function forget(): void
    {
        $this->closedTo = null;
        $this->resolved = false;
    }

    /**
     * Run a change against a closed period, with the reason on the record.
     *
     * The reason is required and must say something: an empty one is refused
     * rather than stored blank, because "reopened: " in an audit trail answers
     * no question anybody will later ask of it.
     *
     * @template T
     * @param  callable():T  $fn
     * @return T
     */
    public static function withReopened(string $reason, callable $fn)
    {
        if (trim($reason) === '') {
            throw new RuntimeException(
                'Reopening a closed FAC period needs a reason. The snapshot Finance posted '
                . 'from will stop agreeing with the register, and somebody has to be able to '
                . 'find out why.'
            );
        }

        $previous = self::$reopenReason;
        self::$reopenReason = trim($reason);

        try {
            return $fn();
        } finally {
            self::$reopenReason = $previous;
        }
    }

    /** The latest closed period end, or null where nothing has been closed. */
    public function latestClosedPeriodEnd(): ?string
    {
        if ($this->resolved) {
            return $this->closedTo;
        }

        $d = FacPeriodSnapshot::whereNotNull('closed_at')->max('period_end');

        $this->closedTo = $d === null ? null : substr((string) $d, 0, 10);
        $this->resolved = true;

        return $this->closedTo;
    }

    /**
     * Whether a placement falls inside a period that has been closed.
     *
     * A placement with no created_at has not been written yet and belongs to no
     * period. A DRAFT is never locked: drafts are excluded from every snapshot,
     * so nothing frozen depends on one -- which is also why the draft boundary
     * itself is guarded below.
     */
    public function locks(FacPlacement $placement): bool
    {
        $closedTo = $this->latestClosedPeriodEnd();

        if ($closedTo === null) {
            return false;
        }

        $createdAt = $placement->getOriginal('created_at') ?? $placement->created_at;

        if ($createdAt === null) {
            return false;
        }

        $wasDraft = (string) ($placement->getOriginal('status') ?? $placement->status) === 'draft';

        if ($wasDraft && (string) $placement->status === 'draft') {
            return false;
        }

        return substr((string) $createdAt, 0, 10) <= $closedTo;
    }

    /**
     * The frozen fields this save would move, with their before and after.
     *
     * STATUS IS ONLY A CHANGE WHERE IT CROSSES THE DRAFT BOUNDARY. Confirming a
     * draft adds a line to every snapshot from its creation month forward, and
     * returning one to draft removes it; moving between placed, client_paid and
     * settled changes no figure a snapshot carries.
     *
     * @return array<string,array{from:mixed,to:mixed}>
     */
    public function violations(FacPlacement $placement): array
    {
        $out = [];

        foreach (self::FROZEN_FIELDS as $field) {
            if (! $placement->isDirty($field)) {
                continue;
            }

            $from = $placement->getOriginal($field);
            $to   = $placement->getAttribute($field);

            // A decimal cast makes "100.00" and 100 both dirty and equal. Compare
            // the numbers where both sides are numeric, so a no-op re-save of an
            // untouched row is not reported as a breach.
            if (is_numeric($from) && is_numeric($to) && abs((float) $from - (float) $to) < 0.005) {
                continue;
            }

            if ((string) $from === (string) $to) {
                continue;
            }

            $out[$field] = ['from' => $from, 'to' => $to];
        }

        if ($placement->isDirty('status')) {
            $wasDraft = (string) $placement->getOriginal('status') === 'draft';
            $isDraft  = (string) $placement->status === 'draft';

            if ($wasDraft !== $isDraft) {
                $out['status'] = [
                    'from' => $placement->getOriginal('status'),
                    'to'   => $placement->status,
                ];
            }
        }

        return $out;
    }

    /**
     * Refuse the save, or record the reopening and let it through.
     *
     * Called from FacPlacement's saving event, so every path reaches it.
     */
    public function guard(FacPlacement $placement): void
    {
        if (! $placement->exists || ! $this->locks($placement)) {
            return;
        }

        $violations = $this->violations($placement);

        if ($violations === []) {
            return;
        }

        $closedTo = $this->latestClosedPeriodEnd();

        if (self::$reopenReason === null) {
            throw new RuntimeException(sprintf(
                'Placement %s sits in a period closed to %s, and %s cannot change: %s. '
                . 'The snapshot Finance posts from would stop agreeing with the register and '
                . 'no control reports the difference. Wrap a genuine correction in '
                . 'FacPeriodLock::withReopened($reason, fn () => ...) so the reopening is on '
                . 'the record, or re-close the period afterwards.',
                $placement->getKey(),
                $closedTo,
                count($violations) === 1 ? 'one field' : count($violations) . ' fields',
                implode(', ', array_keys($violations))
            ));
        }

        $this->recordReopening($placement, $violations, $closedTo, self::$reopenReason);
    }

    /**
     * Write the reopening to the append-only trail.
     *
     * ON THE PLACEMENT'S OWN TRAIL, because that is where somebody looking at a
     * line that disagrees with a posted journal will actually look. The model is
     * Auditable, so the before-and-after is captured anyway; this records the
     * REASON, which no automatic audit can supply.
     */
    private function recordReopening(
        FacPlacement $placement,
        array $violations,
        ?string $closedTo,
        string $reason
    ): void {
        FacPlacementEvent::create([
            'fac_placement_id' => $placement->getKey(),
            'event'            => 'period_reopened',
            'summary'          => sprintf(
                'Changed %s behind a period closed to %s — %s',
                implode(', ', array_keys($violations)),
                $closedTo ?? '(unknown)',
                $reason
            ),
            'payload'          => [
                'closed_to'  => $closedTo,
                'reason'     => $reason,
                'violations' => $violations,
            ],
            'actor_id'         => Auth::id(),
            'actor_name'       => Auth::user()->name ?? null,
        ]);
    }
}
