<?php

namespace AlphaDirect\Services\Travel;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Mints Alpha Direct policy numbers for Travel Insurance.
 *
 *   TRVL + YYYY + NNNNNN        e.g. TRVL2026000001
 *
 *   TRVL    fixed prefix for the Travel book (the Instant book uses MIS)
 *   YYYY    the year the policy is created in
 *   NNNNNN  six-digit sequence, zero-padded, restarting at 1 each year
 *
 * Why a counter table rather than the MIS pattern
 * ------------------------------------------------
 * Every other product mints its number from the tail of the policies table —
 * 'MIS' . year . str_pad(max(id) + 1, 6) — which is not a per-year sequence at
 * all (it tracks the global auto-increment) and races: two concurrent creates
 * read the same max and build the same number. Travel numbers are required to
 * restart per year AND to never collide, so the sequence is held explicitly in
 * policy_number_sequences, one row per (prefix, year).
 *
 * How a number is handed out
 * --------------------------
 * One statement does the whole increment:
 *
 *   UPDATE policy_number_sequences
 *      SET last_sequence = LAST_INSERT_ID(last_sequence + 1)
 *    WHERE prefix = ? AND period_year = ?
 *
 * MySQL's LAST_INSERT_ID(expr) stashes the value it just wrote against THIS
 * connection, so the follow-up SELECT LAST_INSERT_ID() reads back our own
 * increment and no one else's. The row lock lives and dies inside that single
 * statement, so concurrent minters queue for microseconds.
 *
 * The obvious alternative — SELECT ... FOR UPDATE, add one, write it back —
 * was measured deadlocking (MySQL 1213) with four concurrent minters on a
 * fresh year: the insert-intention lock from seeding the counter row and the
 * FOR UPDATE that follows it interleave badly. It never produced a duplicate,
 * but a third of the calls failed outright. Hence the single-statement bump,
 * plus a jittered retry for the lock failures that remain possible under load.
 *
 * Three independent guards stand between us and a duplicate:
 *
 *   1. The atomic bump — two callers cannot be handed the same sequence.
 *   2. A probe against the numbers already issued (policies.policyNumber and
 *      mapfre_quote_submissions.policy_number) — steps over any number a
 *      legacy import or a restored backup is already squatting on.
 *   3. The UNIQUE indexes on both of those columns — the database has the
 *      final say, so a bug here surfaces as a failed write, never as two
 *      policies sharing a number.
 *
 * The counter self-seeds from the highest number already issued for the year,
 * so it is correct on a database that already holds TRVL rows and it cannot
 * re-issue a number if the counter row is ever lost.
 *
 * Every read on the minting path is pinned to the WRITE connection. This
 * database is configured with read/write splitting, and LAST_INSERT_ID() is
 * per-connection state — read it from a replica and you get someone else's
 * value or zero. A replica lagging by a single transaction would likewise seed
 * a counter too low or miss a number already issued. Correctness here outranks
 * spreading the read load.
 */
final class TravelPolicyNumberGenerator
{
    /** Fixed prefix for the Travel Insurance book. */
    public const PREFIX = 'TRVL';

    /** Width of the zero-padded sequence, e.g. 1 -> "000001". */
    public const SEQUENCE_WIDTH = 6;

    /** Last number available in a year: TRVL{year}999999. */
    public const MAX_SEQUENCE = 999999;

    /** TRVL + 4-digit year + 6-digit sequence. */
    public const PATTERN = '/^TRVL(\d{4})(\d{6})$/';

    /** Length of PREFIX . YYYY — where the sequence starts (1-indexed for SQL). */
    private const SEQUENCE_OFFSET = 8;

    /**
     * How many taken numbers we step over before giving up. Only ever exercised
     * when numbers arrived from outside this generator; a hundred in a row means
     * the counter is badly out of step and a human should look.
     */
    private const MAX_PROBES = 100;

    /** Attempts before a deadlock / lock-wait failure is raised to the caller. */
    private const MAX_LOCK_RETRIES = 4;

    private const SEQUENCE_TABLE = 'policy_number_sequences';

    /**
     * Issue the next travel policy number for a year (default: this year).
     *
     * Safe to call inside a caller's transaction: the bump then commits with
     * that transaction, so a rolled-back policy write releases its number
     * instead of leaving a hole in the year's sequence.
     *
     * @throws RuntimeException when the year's numbers are exhausted.
     * @throws QueryException   when the counter cannot be locked after retries.
     */
    public function next(?int $year = null): string
    {
        $year ??= (int) now()->format('Y');

        for ($attempt = 1; $attempt <= self::MAX_LOCK_RETRIES; $attempt++) {
            try {
                return $this->mint($year);
            } catch (QueryException $e) {
                if (!$this->isTransientLockFailure($e) || $attempt === self::MAX_LOCK_RETRIES) {
                    throw $e;
                }

                Log::warning('travel_policy_number.lock_retry', [
                    'year'    => $year,
                    'attempt' => $attempt,
                    'msg'     => $e->getMessage(),
                ]);

                // Jittered backoff — two minters that collided must not line up
                // again on the retry.
                usleep(random_int(20_000, 120_000) * $attempt);
            }
        }

        // Unreachable: the final attempt either returns or rethrows.
        throw new RuntimeException("Travel policy number for {$year} could not be issued");
    }

    /**
     * The travel policy number for a bound MAPFRE contract, keyed on the
     * submission reference — minting one on first call and returning the same
     * number on every call after that.
     *
     * The reference is what stops a resubmitted bind being sold twice
     * (PublicTravelController derives it from the MAPFRE quote id, else from a
     * hash of the trip), so keying on it means a replayed request reuses its
     * policy number instead of burning a second one.
     *
     * Only ever called after a bind has succeeded upstream, which is why a
     * reconstructed audit row (below) is recorded as `submitted`.
     */
    public function assignTo(string $reference, ?int $year = null): string
    {
        $existing = $this->submissions()
            ->where('reference', $reference)
            ->useWritePdo()
            ->value('policy_number');

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        // The audit row is written best-effort by MapfreTravelClient, so it can
        // be missing entirely when that write failed. Create it BEFORE minting:
        // a number issued with no row to hold it is recorded nowhere, and the
        // next replay of the same bind would mint a second one for one sale.
        $this->ensureSubmissionRow($reference);

        $policyNumber = $this->next($year);

        // Conditional claim, not a blind update: only the caller that finds the
        // column still empty writes it. Two concurrent replays therefore settle
        // on one number instead of the second overwriting the first.
        $stored = $this->submissions()
            ->where('reference', $reference)
            ->where(function ($q) {
                $q->whereNull('policy_number')->orWhere('policy_number', '');
            })
            ->update(['policy_number' => $policyNumber, 'updated_at' => now()]);

        if ($stored === 0) {
            $winner = $this->submissions()
                ->where('reference', $reference)
                ->useWritePdo()
                ->value('policy_number');

            if (is_string($winner) && $winner !== '') {
                // A racing replay claimed the row first. Hand back its number —
                // ours is burned, which is the cheap half of the trade against
                // one sale carrying two policy numbers.
                Log::warning('travel_policy_number.lost_race_reusing_stored', [
                    'reference'     => $reference,
                    'minted'        => $policyNumber,
                    'stored'        => $winner,
                ]);

                return $winner;
            }

            // Row still absent: the reconstruction above also failed, so the DB
            // is unreachable or the audit table is gone. The number is valid and
            // still returned to the portal, but nothing on our side records it.
            Log::error('travel_policy_number.not_persisted', [
                'reference'     => $reference,
                'policy_number' => $policyNumber,
            ]);
        }

        return $policyNumber;
    }

    /** Build a number from its parts. TRVL + 2026 + 1 -> "TRVL2026000001". */
    public static function format(int $year, int $sequence): string
    {
        return self::PREFIX
            . $year
            . str_pad((string) $sequence, self::SEQUENCE_WIDTH, '0', STR_PAD_LEFT);
    }

    /** Does this string look like a travel policy number? */
    public static function matches(string $policyNumber): bool
    {
        return (bool) preg_match(self::PATTERN, $policyNumber);
    }

    // ─── internals ──────────────────────────────────────────────────────────

    /** One minting pass: seed the year if needed, then bump until free. */
    private function mint(int $year): string
    {
        $this->seedCounterIfMissing($year);

        for ($probe = 0; $probe < self::MAX_PROBES; $probe++) {
            $sequence = $this->bumpCounter($year);

            if ($sequence > self::MAX_SEQUENCE) {
                throw new RuntimeException(
                    "Travel policy numbers for {$year} are exhausted at " . self::MAX_SEQUENCE
                );
            }

            $candidate = self::format($year, $sequence);

            if ($this->isTaken($candidate)) {
                // Issued outside this generator (import, restore, manual fix).
                // Burn the sequence and move on — never hand back a number that
                // already names a policy.
                Log::warning('travel_policy_number.sequence_already_taken', [
                    'candidate' => $candidate,
                    'year'      => $year,
                ]);
                continue;
            }

            return $candidate;
        }

        throw new RuntimeException(
            'Travel policy number generator could not find a free sequence for '
            . $year . ' within ' . self::MAX_PROBES . ' attempts'
        );
    }

    /**
     * Atomically claim the next sequence for the year and return it.
     *
     * Both statements MUST run on the write connection: this database is
     * configured with a read host, and LAST_INSERT_ID() is per-connection
     * state, so a read-replica SELECT would answer with someone else's value
     * (or zero). Hence the explicit $useReadPdo = false.
     */
    private function bumpCounter(int $year): int
    {
        $updated = DB::update(
            'UPDATE ' . self::SEQUENCE_TABLE . '
                SET last_sequence = LAST_INSERT_ID(last_sequence + 1),
                    updated_at    = ?
              WHERE prefix = ? AND period_year = ?',
            [now(), self::PREFIX, $year]
        );

        if ($updated === 0) {
            // Cannot happen after seeding, but a missing counter must never
            // silently restart the year at 000001.
            throw new RuntimeException("Travel policy number counter for {$year} is missing");
        }

        $row = DB::selectOne('SELECT LAST_INSERT_ID() AS seq', [], false);

        return (int) ($row->seq ?? 0);
    }

    /**
     * Create the year's counter row, seeded from the highest number already
     * issued for that year (0 when the year is fresh). insertOrIgnore leans on
     * the UNIQUE (prefix, period_year) key, so two callers racing to open the
     * same year end up with one row, not two counters.
     */
    private function seedCounterIfMissing(int $year): void
    {
        $exists = DB::table(self::SEQUENCE_TABLE)
            ->where('prefix', self::PREFIX)
            ->where('period_year', $year)
            ->useWritePdo()
            ->exists();

        if ($exists) {
            return;
        }

        DB::table(self::SEQUENCE_TABLE)->insertOrIgnore([
            'prefix'        => self::PREFIX,
            'period_year'   => $year,
            'last_sequence' => $this->highestIssued($year),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    /**
     * Highest sequence already issued for a year across every table that can
     * hold a travel policy number. Only read when seeding a counter, so the
     * scans stay off the minting path.
     */
    private function highestIssued(int $year): int
    {
        $like   = self::PREFIX . $year . '%';
        $length = strlen(self::PREFIX) + 4 + self::SEQUENCE_WIDTH;

        $highest = (int) DB::table('policies')
            ->where('policyNumber', 'like', $like)
            ->whereRaw('CHAR_LENGTH(policyNumber) = ?', [$length])
            ->selectRaw('MAX(CAST(SUBSTRING(policyNumber, ?) AS UNSIGNED)) AS seq', [self::SEQUENCE_OFFSET + 1])
            ->useWritePdo()
            ->value('seq');

        try {
            $fromSubmissions = (int) $this->submissions()
                ->where('policy_number', 'like', $like)
                ->whereRaw('CHAR_LENGTH(policy_number) = ?', [$length])
                ->selectRaw('MAX(CAST(SUBSTRING(policy_number, ?) AS UNSIGNED)) AS seq', [self::SEQUENCE_OFFSET + 1])
                ->useWritePdo()
                ->value('seq');

            $highest = max($highest, $fromSubmissions);
        } catch (\Throwable $e) {
            // Audit table or column not present yet (pre-migration): the
            // policies scan alone still keeps us from re-issuing a number.
            Log::warning('travel_policy_number.submission_scan_failed', ['msg' => $e->getMessage()]);
        }

        return $highest;
    }

    /**
     * Make sure the bind has an audit row to carry its policy number.
     *
     * MapfreTravelClient writes that row best-effort — an audit failure must
     * never break a contract call — so by the time we mint, it may not exist.
     * The row we reconstruct here carries only what is knowable at this point:
     * the reference and the fact that the bind succeeded. The payload and the
     * MAPFRE ids stay null, and the warning marks it as reconstructed so the
     * gap in the audit trail is visible rather than silently papered over.
     *
     * insertOrIgnore leans on the UNIQUE reference key, so a client write that
     * lands in the same instant wins and we simply fall through to the update.
     * Never throws: a bind is already money on the books at this point.
     */
    private function ensureSubmissionRow(string $reference): void
    {
        try {
            if ($this->submissions()->where('reference', $reference)->useWritePdo()->exists()) {
                return;
            }

            $now = now();

            $this->submissions()->insertOrIgnore([
                'reference' => $reference,
                'status'    => 'submitted',
                // submitted_at is NOT optional here. MapfreSubmissionsController
                // counts bound_today / bound_month with
                // where('submitted_at','>=',...), and NULL fails that test — so a
                // row reconstructed without it is a real sale the ops console
                // reports in neither tile, and which is missing from the
                // failure-rate denominator too. assignTo() only ever runs after a
                // successful bind, so stamping it now is accurate, not a guess.
                'submitted_at' => $now,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);

            Log::warning('travel_policy_number.audit_row_reconstructed', [
                'reference' => $reference,
            ]);
        } catch (\Throwable $e) {
            Log::error('travel_policy_number.audit_row_reconstruct_failed', [
                'reference' => $reference,
                'msg'       => $e->getMessage(),
            ]);
        }
    }

    /** Is this number already naming a policy anywhere? */
    private function isTaken(string $candidate): bool
    {
        if (DB::table('policies')->where('policyNumber', $candidate)->useWritePdo()->exists()) {
            return true;
        }

        try {
            return $this->submissions()->where('policy_number', $candidate)->useWritePdo()->exists();
        } catch (\Throwable $e) {
            Log::warning('travel_policy_number.submission_probe_failed', ['msg' => $e->getMessage()]);
            return false;
        }
    }

    /** Deadlock (1213) / lock-wait timeout (1205) — worth retrying, unlike a real error. */
    private function isTransientLockFailure(QueryException $e): bool
    {
        $code = (int) ($e->errorInfo[1] ?? 0);

        return in_array($code, [1205, 1213], true);
    }

    /**
     * The MAPFRE bind audit rows — on the V2 ops DB, same as the client writes.
     */
    private function submissions()
    {
        return DB::connection('mysql_system')->table('mapfre_quote_submissions');
    }
}
