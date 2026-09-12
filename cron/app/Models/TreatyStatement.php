<?php

namespace AlphaDirect\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

/**
 * One quarterly statement of account, for one treaty.
 *
 * The clocks are the reason this is a model rather than a query. Accounts are
 * rendered within 45 days of the quarter close and confirmed within 14 of being
 * rendered (BR-ACC-01, BR-ACC-02), and both dates are STORED when they are set
 * rather than computed on read: the clock that governs is the one that applied
 * when the quarter closed, not one recalculated later from a rule that has since
 * been edited.
 */
class TreatyStatement extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** The treaty year runs July to June, so quarter 1 is Jul–Sep. */
    public const YEAR_STARTS_MONTH = 7;

    /**
     * The reinsurance groups the Motor treaty covers. General takes the rest.
     *
     * THE SPLIT IS BY GROUP, NOT BY REGULATORY CLASS. RI-05 is the authority and
     * it lists the two treaties by group: General takes PROPERTYANDBI_COM,
     * ELECTRONIC_EQ_AND_BI_COM, GOODSINTRANSIT_COM, MISC_COM, FIDELITYG_COM,
     * ACCIDENTAL_DAMAGE_COM and ENGINEERING_AND_BI_COM; Motor takes the rest
     * below. An earlier version of this split on regulatory_class = 'Motor',
     * which is a different and coarser question — several groups share a class,
     * and the class is what a statement REPORTS by (BR-RPT-02, per class of
     * business), not what decides which treaty a cession belongs to.
     *
     * RI-05 NAMES FOUR; THE GROUP TABLE HOLDS SEVEN. RI-05 lists MOTOR_COM (14),
     * MOTOR_TRAILERS_COM (28), MOTOR_TRADERS_COM_EXT (13) and
     * MOTOR_TRADERS_COM_INT (30), plus a Passenger Liability class with no
     * populated group. The table also carries the domestic variants MOTOR_DOM
     * (15) and MOTOR_TRAILERS_DOM (29), and MOTOR_PER_ACCIDENT (16) which may be
     * the unpopulated Passenger Liability. All seven are motor business, so all
     * seven sit here: WHICH treaty a motor cession belongs to is not in doubt.
     * Whether the domestic variants are inside the 2026/27 treaty at all is a
     * separate question, outstanding with Tlamelo — and if the answer is no, the
     * fault is upstream in them being ceded, not in where they are reported.
     *
     * NOTE FOR THE CESSION SEAM: CessionSource::MOTOR_GROUP_IDS is
     * [13,14,15,28,29] and omits 30 (MOTOR_TRADERS_COM_INT), which RI-05 lists
     * explicitly. That is a defect in that constant, tracked separately.
     */
    public const MOTOR_GROUP_CODES = [
        'MOTOR_COM',
        'MOTOR_DOM',
        'MOTOR_TRAILERS_COM',
        'MOTOR_TRAILERS_DOM',
        'MOTOR_TRADERS_COM_EXT',
        'MOTOR_TRADERS_COM_INT',
        'MOTOR_PER_ACCIDENT',
    ];

    /**
     * Whether a treaty covers a reinsurance group.
     *
     * AN UNRECOGNISED GROUP FALLS TO GENERAL rather than to nothing. Dropping it
     * from both statements would make the money disappear silently, which is the
     * failure this whole build keeps turning up; General is the residual treaty,
     * so that is where an unknown belongs until somebody says otherwise. Callers
     * that care should ask isClassifiableGroup() and report the count.
     */
    public static function coversGroup(string $treaty, ?string $groupCode): bool
    {
        $isMotorGroup = in_array(strtoupper(trim((string) $groupCode)), self::MOTOR_GROUP_CODES, true);

        return strtolower(trim($treaty)) === 'motor' ? $isMotorGroup : ! $isMotorGroup;
    }

    /**
     * Whether a group code is one we recognise at all.
     *
     * Seven regulatory rows under class Property carry a BLANK group_code on the
     * test server. They are not Motor, so they land in General and are reported
     * — but they are also not identifiable, and a statement leaning on them is
     * one somebody should look at before it is rendered.
     */
    public static function isClassifiableGroup(?string $groupCode): bool
    {
        $code = strtoupper(trim((string) $groupCode));

        return $code !== '' && (
            in_array($code, self::MOTOR_GROUP_CODES, true) || str_ends_with($code, '_COM')
                || str_ends_with($code, '_DOM') || str_ends_with($code, '_EXT')
        );
    }

    /** BR-ACC-01. */
    public const RENDER_DAYS = 45;

    /** BR-ACC-02. */
    public const CONFIRM_DAYS = 14;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_RENDERED  = 'rendered';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_DISPUTED  = 'disputed';
    public const STATUS_SETTLED   = 'settled';

    protected $table = 'treaty_statements';

    protected $guarded = [];

    protected $casts = [
        'underwriting_year' => 'integer',
        'quarter'           => 'integer',
        'period_start'      => 'date',
        'period_end'        => 'date',
        'render_due'        => 'date',
        'confirm_due'       => 'date',
        'rendered_at'       => 'datetime',
        'confirmed_at'      => 'datetime',
    ];

    /**
     * Every reinsurer's share of every line on this statement.
     *
     * THROUGH THE ITEMS, because a share hangs off an ITEM and not off the
     * statement — BR-SEC-08 allocates every ceded amount per reinsurer, so the
     * grain is the line rather than the account. A reinsurer therefore appears
     * once per line it participates in, and a per-reinsurer total is a sum
     * across them rather than a single row.
     */
    public function shares()
    {
        return $this->hasManyThrough(
            TreatyStatementShare::class,
            TreatyStatementItem::class,
            'treaty_statement_id',      // items.treaty_statement_id
            'treaty_statement_item_id', // shares.treaty_statement_item_id
            'id',
            'id'
        );
    }

    public function items()
    {
        return $this->hasMany(TreatyStatementItem::class, 'treaty_statement_id');
    }

    public function reserveDeposits()
    {
        return $this->hasMany(TreatyReserveDeposit::class, 'treaty_statement_id');
    }

    /**
     * The dates of one quarter of a treaty year.
     *
     * Quarter 1 of 2026/27 is 1 Jul – 30 Sep 2026, and quarter 4 ends 30 Jun
     * 2027. Derived rather than typed, because a quarter typed wrongly puts a
     * whole statement on the wrong side of a deadline.
     *
     * @return array{start:string,end:string,render_due:string}
     */
    public static function periodFor(int $underwritingYear, int $quarter): array
    {
        if ($quarter < 1 || $quarter > 4) {
            throw new InvalidArgumentException("Quarter must be 1 to 4, got {$quarter}.");
        }

        $startMonth = self::YEAR_STARTS_MONTH + (($quarter - 1) * 3);
        $startYear  = $underwritingYear + intdiv($startMonth - 1, 12);
        $startMonth = (($startMonth - 1) % 12) + 1;

        $start = sprintf('%04d-%02d-01', $startYear, $startMonth);
        $end   = date('Y-m-t', strtotime($start . ' +2 months'));

        return [
            'start'      => $start,
            'end'        => $end,
            'render_due' => date('Y-m-d', strtotime($end . ' +' . self::RENDER_DAYS . ' days')),
        ];
    }

    /**
     * The net balance: the sum of every item, and nothing else.
     *
     * Amounts are signed on purpose so this stays an addition. A balance worked
     * out by adding some items and subtracting others is a rule that has to be
     * kept in step with the item list, and it will not be.
     */
    public function balance(): float
    {
        // SETTLING ITEMS ONLY. Outstanding losses, salvages and recoveries are
        // on the statement to be read, not to be paid: outstanding is a reserve
        // position rather than money moving this quarter, and salvages and
        // recoveries are already netted inside claims_paid. Summing every item
        // instead of this scope overstated the balance by the whole outstanding
        // book, and double-counted every salvage.
        return round((float) $this->items()->settling()->sum('amount'), 2);
    }

    /** What the statement reports but does not settle — Article 10.2.4. */
    public function memorandumTotal(): float
    {
        return round(
            (float) $this->items()
                ->whereIn('item_type', TreatyStatementItem::MEMORANDUM_ONLY)
                ->sum('amount'),
            2
        );
    }

    /**
     * Whether the statement is late, and by how many days.
     *
     * A rendered statement is judged on when it was rendered; an unrendered one
     * on today. Both matter: BR-ACC-10 charges interest from the due date to the
     * date of payment, so lateness has to be measurable before it is settled and
     * afterwards.
     *
     * @return array{late:bool,days:int,against:string}
     */
    public function renderLateness(?string $asAt = null): array
    {
        // RAW ATTRIBUTES, NOT THE CASTS. Eloquent's date casting resolves through
        // the connection, so reading $this->render_due needs a database — and
        // comparing two dates does not. Taken raw, this works on an unsaved
        // model with no connection at all, which is what lets the clock be
        // tested without standing a database up to hold two strings.
        $due = $this->attributes['render_due'] ?? null;

        if ($due === null || $due === '') {
            return ['late' => false, 'days' => 0, 'against' => 'no due date'];
        }

        $rendered   = $this->attributes['rendered_at'] ?? null;
        $measuredAt = $rendered ?: ($asAt ?? date('Y-m-d'));

        $days = (int) floor(
            (strtotime(substr((string) $measuredAt, 0, 10)) - strtotime(substr((string) $due, 0, 10)))
            / 86400
        );

        return [
            'late'    => $days > 0,
            'days'    => max($days, 0),
            'against' => $rendered ? 'rendered' : 'today',
        ];
    }

    /**
     * The 14-day clock — BR-ACC-02.
     *
     * IT CANNOT START BEFORE THE ACCOUNT IS RENDERED. Reinsurers confirm or
     * object "following receipt", so an unrendered statement has no confirmation
     * deadline at all — which is not the same as having one it is meeting. The
     * two must not read alike, so this says which it is.
     *
     * @return array{late:bool,days:int,against:string}
     */
    public function confirmLateness(?string $asAt = null): array
    {
        $rendered = $this->attributes['rendered_at'] ?? null;

        if ($rendered === null || $rendered === '') {
            return ['late' => false, 'days' => 0, 'against' => 'not yet rendered'];
        }

        $due = $this->attributes['confirm_due'] ?? null;

        if ($due === null || $due === '') {
            return ['late' => false, 'days' => 0, 'against' => 'no due date'];
        }

        $confirmed  = $this->attributes['confirmed_at'] ?? null;
        $measuredAt = $confirmed ?: ($asAt ?? date('Y-m-d'));

        $days = (int) floor(
            (strtotime(substr((string) $measuredAt, 0, 10)) - strtotime(substr((string) $due, 0, 10)))
            / 86400
        );

        return [
            'late'    => $days > 0,
            'days'    => max($days, 0),
            'against' => $confirmed ? 'confirmed' : 'today',
        ];
    }

    /**
     * The confirmation deadline for an account rendered on a date — BR-ACC-02.
     *
     * Fourteen days from receipt, and receipt is taken as the day it was
     * rendered: nothing in this system observes when a reinsurer opened it, and
     * assuming a slower delivery would quietly extend their window.
     */
    public static function confirmDueAfter(string $renderedAt): string
    {
        return date(
            'Y-m-d',
            strtotime(substr($renderedAt, 0, 10) . ' +' . self::CONFIRM_DAYS . ' days')
        );
    }

    /**
     * Every quarter that has CLOSED by a given date, from the treaty's first
     * underwriting year.
     *
     * THE POINT IS THE QUARTERS WITH NO STATEMENT. A statement that was never
     * started cannot be overdue, because nothing is looking at it — it is
     * invisible to every query that reads the statements table, and a quarter
     * silently skipped looks exactly like a quarter that has not closed yet.
     * Enumerating from the calendar rather than from the table is the only way
     * that gap is visible at all.
     *
     * @return array<int,array{underwriting_year:int,quarter:int,period_end:string,render_due:string}>
     */
    public static function quartersClosedBy(string $asAt, ?int $firstYear = null): array
    {
        $firstYear ??= (int) config('reinsurance.treaty.first_underwriting_year', 2026);
        $asAtTs = strtotime(substr($asAt, 0, 10));

        $out = [];

        // The treaty cannot have run for more than a few years before somebody
        // notices; the bound is a guard against a misconfigured first year
        // spinning here rather than a statement about the treaty's life.
        for ($year = $firstYear; $year <= $firstYear + 50; $year++) {
            for ($q = 1; $q <= 4; $q++) {
                $p = self::periodFor($year, $q);

                if (strtotime($p['end']) > $asAtTs) {
                    return $out;
                }

                $out[] = [
                    'underwriting_year' => $year,
                    'quarter'           => $q,
                    'period_end'        => $p['end'],
                    'render_due'        => $p['render_due'],
                ];
            }
        }

        return $out;
    }
}
