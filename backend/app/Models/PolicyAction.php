<?php
namespace AlphaDirect\Models;

use AlphaDirect\PolicyBeneficiary;
use AlphaDirect\PolicyCellPhone;
use Illuminate\Database\Eloquent\Model;
use AlphaDirect\Vehicle;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;
use Illuminate\Support\Arr;
use AlphaDirect\Policy;
use Illuminate\Support\Facades\DB;
use AlphaDirect\PolicyTerm;
use AlphaDirect\Models\Motor;
use AlphaDirect\Models\MotorTraders;
use AlphaDirect\Models\MotorTradersInternal;
use AlphaDirect\Models\PolicyCoverage;
use AlphaDirect\Models\PolicyCoverageDetail;
use AlphaDirect\Models\PolicyExtentionDetails;
use AlphaDirect\Models\PolicySpecifiedItem;
use AlphaDirect\Models\PolicyCoveragesData;
use AlphaDirect\PolicyCoverNote;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use Log;

class PolicyAction extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use SoftDeletes;

    protected $table      = 'policy_actions';
    // Removed `protected $connection = 'mysql_write'` — V1 legacy table.
    // Post-pivot mysql_write points at v2-prod (no policy_actions there).
    // Default 'mysql' connection (V1 read replica) is correct.
    protected $guarded    = [];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        if (isset(\Auth::user()->id)) {
            static::creating(function ($model) {
                $model->created_by = \Auth::user()->id;
            });
            static::updating(function ($model) {
                $model->updated_by = \Auth::user()->id;
            });
        }
    }

    public function transformAudit(array $data): array
    {
        if (Arr::has($data, 'new_values')) {
            if ($this->auditEvent != 'created') {
                if (isset($this->policy_id)) {
                    $policy = Policy::where('id', $this->policy_id)->first();
                    if ($policy != null) {
                        $data['policy_id'] = $policy->id;
                        $data['tags'] = 'Alter Term/Date';
                        $data['policy_number'] = $policy->policyNumber;
                    }
                }
            }
        }
        //to store customer as a causer in case of APP/API call
        // if (Arr::has($data, 'new_values.lead_source')) {
        //     $data['customer_id'] = $data['new_values']['customer_id'];
        // }

        return $data;

    }

    // scope methods
    public function scopePolicy($query, $policy_id)
    {
        $query->where('policy_id', $policy_id);
    }

    public function scopeTerm($query, $term_id)
    {
        $query->where('term_id', $term_id);
    }

    public function scopeStatus($query, $status)
    {
        $query->where('status', $status);
    }

    public function scopeQuoted($query)
    {
        $query->where('status', 'QUOTE');
    }

    public function scopeIssued($query)
    {
        $query->where('status', 'ISSUED');
    }

    // custom methods
    public static function getActions($policy_id, $term_id)
    {
        return static::select('id', 'transaction_type', 'status', 'previous_action_id', 'effective_from', 'effective_to', 'current_frequency_id')->policy($policy_id)->term($term_id)
            ->orderBy('effective_from', 'ASC')->get();
    }

    /**
     * Foreign-extension guard for the coverage-replication paths.
     *
     * A policy_extention_detail row records its true owning coverage in
     * s_ParentCoverageID. When endorse/renew replication carries a coverage's
     * extension/excess rows forward, a row whose s_ParentCoverageID resolves to
     * a DIFFERENT coverage than the target is mis-attached ("foreign") and must
     * NOT be cloned — otherwise the product 7/8 foreign-extension bug is
     * re-created on every endorse/renew even though the save-side guard
     * (PolicyCreateController::updateCoverage $enforceOwnExtensions) already
     * blocks it on manual saves.
     *
     * The rule mirrors that save-side guard and the policy:cleanup-foreign-extensions
     * command: a set, non-zero parent that differs from the target coverage is
     * foreign. Rows with no/zero parent (own-coverage or custom rows) are kept.
     * The invariant is universal (an extension belongs to exactly one coverage),
     * so it is applied for every product, not just 7/8.
     */
    protected static function isForeignExtensionRow($row, $targetCoverageId): bool
    {
        if (!($row instanceof PolicyExtentionDetails)) {
            return false;
        }
        $parent = (int) ($row->s_ParentCoverageID ?? 0);
        return $parent !== 0 && $parent !== (int) $targetCoverageId;
    }

    /**
     * Business key used to pair a SOURCE policy_extention_detail row with the
     * row that already exists on the target coverage.
     *
     * `extentions_id` alone is enough for master-backed rows (one master id =
     * one extension = one type). It is NOT enough for custom / Excess rows,
     * which carry NO extentions_id at all: matching them on
     * `extentions_id = NULL` made every excess row on the coverage look like
     * the same row, so the de-dup block collapsed a coverage's whole excess
     * list onto the first row (the rest soft-deleted) and the value loop
     * overwrote that single survivor once per source row (last write wins).
     * For those rows the key falls back to type + the free-text name, which is
     * what identifies them on the save side (updateCoverage matches
     * id → extentions_id → custom_name).
     */
    protected static function extensionBusinessKey($row): array
    {
        $key = ['extentions_id' => $row->extentions_id];

        if (!empty($row->extentions_id)) {
            return $key;
        }

        $key['type'] = $row->type;

        $customName = \Schema::hasColumn('policy_extention_detail', 'custom_name')
            ? trim((string) ($row->custom_name ?? ''))
            : '';

        if ($customName !== '') {
            $key['custom_name'] = $row->custom_name;
        } else {
            $key['extention_text_value'] = $row->extention_text_value;
        }

        return $key;
    }

    /**
     * Signature used to collapse EXACT-duplicate SOURCE child rows so a
     * pre-existing duplicate is not faithfully carried forward into every
     * later action (renew / anniversary / endorse).
     *
     * id / timestamps / deleted_at / the parent FK are excluded because they
     * legitimately differ. For extension + sub-coverage rows the per-action
     * pro-rata stamps are excluded too: `previousActionIdCov`, `endors_flag`
     * and `pro_rate_premium` are re-stamped by every Rate / endorse run, so
     * two rows for the SAME master differing only in those stamps are
     * duplicates — while the old attribute-complete signature saw them as
     * distinct rows and copied both forward on every anniversary.
     */
    /**
     * TRUE when the target coverage already carries this business key but the
     * row is soft-deleted — i.e. it was REMOVED on the target batch and must
     * not be re-created by an additive replicate.
     *
     * Queried through the raw query builder so no SoftDeletes scope is applied
     * (the $matchScope closure only calls where(), so it is builder-agnostic).
     */
    protected static function childRowRemovedInTarget(string $relatedModelClass, callable $matchScope): bool
    {
        $table = (new $relatedModelClass)->getTable();

        if (!\Schema::hasColumn($table, 'deleted_at')) {
            return false;
        }

        return DB::table($table)
            ->where($matchScope)
            ->whereNotNull('deleted_at')
            ->exists();
    }

    protected static function replicationChildSignature($row, $parentFk): string
    {
        $attrs = $row->getAttributes();
        unset($attrs['id'], $attrs['created_at'], $attrs['updated_at'], $attrs['deleted_at'], $attrs[$parentFk]);

        if ($row instanceof PolicyExtentionDetails || $row instanceof PolicyCoverageDetail) {
            unset($attrs['previousActionIdCov'], $attrs['endors_flag'], $attrs['pro_rate_premium']);
        }

        return md5(json_encode($attrs));
    }

    /**
     * Collapse duplicate sub-coverage SOURCE rows before they are copied into
     * another action.
     *
     * replicationChildSignature() only collapses rows that are IDENTICAL, so a
     * duplicate pair that differs in any non-money column (rate 0 vs 100, a
     * limit_id or coverage_value_string on one copy only) read as two distinct
     * sub-coverages and was faithfully re-copied on every renew / endorse. Both
     * copies then landed in the premium recipes, which sum
     * policy_coverage_detail raw — the section printed double on the V2 Quote
     * and the Rate banner charged double (UW report: a COM Public Liability
     * section printing P 666.68 for a P 333.34 cover).
     *
     * A sub-coverage row's real identity is (policy_coverage_id, coverage_id):
     * that is the key both save paths upsert on (addCoverage / updateCoverage
     * via updateOrCreate) and the key the child-row sync below matches on. A
     * "+ Add another row" line is NOT a duplicate — the wizard clones the
     * master into tb_cvgpccoverages first, so each extra line carries its own
     * coverage_id and survives untouched.
     *
     * The RICHEST copy wins (highest premium, then highest sum insured, then
     * the original), so collapsing can never drop a real premium in favour of
     * an empty twin. Other models are returned unchanged.
     */
    protected static function dedupeReplicationChildren($items)
    {
        $items = collect($items);
        if ($items->isEmpty() || !($items->first() instanceof PolicyCoverageDetail)) {
            return $items;
        }

        $keepIds = [];
        $winners = [];
        foreach ($items as $row) {
            $key = (int) ($row->coverage_id ?? 0);
            if ($key === 0) {
                // No business key to compare on — leave the row untouched.
                $keepIds[] = $row->id;
                continue;
            }
            if (!isset($winners[$key]) || self::detailRowIsRicher($row, $winners[$key])) {
                $winners[$key] = $row;
            }
        }
        foreach ($winners as $winner) {
            $keepIds[] = $winner->id;
        }

        return $items->filter(fn ($r) => in_array($r->id, $keepIds, true))->values();
    }

    /**
     * Is this SOURCE child row soft-deleted (cancelled on the source action)?
     *
     * Needed because `Motor` has the SoftDeletes trait commented out
     * (app/Models/Motor.php), so its rows come back from a relation whether or
     * not deleted_at is set — the replicator then read a CANCELLED vehicle as
     * live and carried it (and its tombstone) into the next action. That is the
     * root of COMG2025133674 ("a vehicle deleted upon renewal reappears in the
     * next transaction"), which was previously worked around by letting the
     * source's deleted_at overwrite the target's. Skipping the row at source is
     * the correct fix and is what makes that overwrite unnecessary.
     */
    protected static function replicationSourceRowIsCancelled($row): bool
    {
        if (!is_object($row)) {
            return false;
        }

        // Model may or may not have SoftDeletes; read the raw attribute.
        $deletedAt = $row->getAttribute('deleted_at') ?? null;

        return !empty($deletedAt);
    }

    /** Strictly-richer test, so ties keep the row already chosen (the original). */
    private static function detailRowIsRicher($candidate, $current): bool
    {
        $calcNew = (float) ($candidate->calculated_value ?? 0);
        $calcOld = (float) ($current->calculated_value ?? 0);
        if ($calcNew !== $calcOld) {
            return $calcNew > $calcOld;
        }

        return (float) ($candidate->coverage_value ?? 0) > (float) ($current->coverage_value ?? 0);
    }

    /**
     * $protectTargetEdits (APPEND-ONLY mode, opt-in — default false keeps every
     * existing caller byte-for-byte):
     *
     * The child-row sync below overwrites an EXISTING target row's value columns
     * from the source with no edit protection. That is correct for a renewal
     * being built from its source, but WRONG when filling a batch that carries
     * its own operator edits — the blind sync silently reverts them. With this
     * flag on:
     *   - a target row the target action itself edited (endors_flag = 1 AND
     *     previousActionIdCov = target action) is LEFT ALONE — append only;
     *   - a matched coverage keeps its own endors_flag / status / deleted_at;
     *   - a newly inserted row has pro_rate_premium zeroed, so the source
     *     action's pro-rata never leaks onto another action's line.
     * Rows the target never touched still sync, which is what makes forward
     * propagation of a correction work.
     *
     * Cancellations are protected in every mode, flag or not: a soft-deleted
     * coverage is matched via withTrashed and a soft-deleted child row via
     * childRowRemovedInTarget, so a cancel is never re-inserted as a live row.
     */
    /**
     * Stamp a child row that an APPEND-ONLY replicate is about to INSERT into
     * $targetActionId, so the TARGET action's pro-rata engine can actually see it.
     *
     * replicate() copies every column of the source row, previousActionIdCov
     * included — i.e. the appended line still claims "I was added by the SOURCE
     * action". Every pro-rata consumer hard-gates on
     * previousActionIdCov === the action being rated (the motor / extension /
     * specified-item / motor-traders loops in
     * PolicyCreateController::writeLineLevelProRata, and the header delta sums in
     * recomputeActionTotals), so a row carrying the source's stamp is skipped
     * outright. That is the bug behind "Fill missing added the cover but the
     * premium never moved": the line printed on the downstream schedule at
     * P 0.00 and the batch re-derived its old total, leaving UN-ISSUE as the only
     * way to get the new cover charged.
     *
     * Re-stamping to the target says "this line arrives on THIS batch", which is
     * what actually happened, so it rates as a normal addition against the prior
     * action's baseline. pro_rate_premium is zeroed with it — that money column
     * is the SOURCE batch's delta and must never be inherited; the target's own
     * Rate / recompute writes the correct figure.
     *
     * endors_flag is deliberately NOT set. The append-only edit protection tests
     * endors_flag = 1 AND previousActionIdCov = target ("the operator edited this
     * row HERE"), so setting it would make a re-run treat every appended row as an
     * operator edit and silently stop value-syncing it.
     *
     * Called ONLY from the append-only paths ($protectTargetEdits / the fill
     * modes); the destructive rebuild's clones are untouched.
     */
    public static function stampAppendedChildRow($row, int $targetActionId): void
    {
        $attrs = $row->getAttributes();
        if (array_key_exists('previousActionIdCov', $attrs)) {
            $row->previousActionIdCov = $targetActionId;
        }
        if (array_key_exists('pro_rate_premium', $attrs)) {
            $row->pro_rate_premium = 0;
        }
    }

    public static function replicateRecordsIfMissing($recordFromId, $recordToId, $model, $columnName, $othercolumns = [], $uniqueKey = null, $withRelation = [], $onlyCoverageIds = null, $protectTargetEdits = false)
    {

        try {
            $query = $model::query();
            if (!empty($withRelation)) {
                $query->with(array_keys($withRelation));
            }
            if ($uniqueKey == 'coverage') {

                $query = $model::where($columnName, $recordFromId)->with('riskAddress');
                $dataToBeReplicate = $query->get();

                // Coverage-selective FORWARD: when a coverage-id filter is
                // supplied (Refresh Endorsement Range → "Forward Coverage(s)"),
                // only replicate the source coverages whose master coverage_id
                // is in the list. Default null = no filter, so every existing
                // caller (newPolicyActionReplace, renew, etc.) is unaffected.
                if (!empty($onlyCoverageIds)) {
                    $dataToBeReplicate = $dataToBeReplicate->whereIn('coverage_id', $onlyCoverageIds)->values();
                }

                // Fetch existing data for ToID.
                //
                // withTrashed(): a coverage the operator CANCELLED on the target
                // batch (soft-deleted) is still "already there". Without it the
                // SoftDeletes scope hid those rows, this additive replicate saw
                // the coverage as missing and inserted a SECOND, live copy of it
                // — with a full duplicate set of extension / sub-coverage /
                // specified-item children — every time the path re-ran (endorse
                // issue forward-propagation, Refresh "fill missing", batch
                // renew). That is the duplicate-extension-rows leak on
                // anniversary quotes. The endors_flag / status / deleted_at sync
                // below is kept for LIVE matches only, so a target cancellation
                // is never quietly reversed either.
                // Address-name key for the "already on the target?" test.
                // Normalised (trim + lowercase, null-safe) so a whitespace or
                // case difference cannot read as a different address — the same
                // normalisation the pro-rata baseline matcher already uses.
                $addrKey = static fn ($n) => strtolower(trim((string) ($n ?? '')));

                // Source-side address names, resolved through withTrashed().
                //
                // The `riskAddress` relation applies the SoftDeletes scope, so a
                // coverage whose risk_address row had been soft-deleted resolved
                // to NULL on this side, while the target side (a raw join, which
                // ignores model scopes) still reported the real name. The two
                // could then NEVER match: the coverage looked "missing" on every
                // re-run and each run inserted ANOTHER live policy_coverages row
                // with a full duplicate child set — the same section twice on the
                // V2 Quote and two identical lines in the Endorse Change Summary
                // (policy 120909 action 48878: pc 183233 + 183234, both
                // Accidental Damage, both sub-coverage 74 at 125.00).
                $srcAddrNameById = [];
                $srcAddrIds = collect($dataToBeReplicate)->pluck('risk_address_id')->filter()->unique()->all();
                if (!empty($srcAddrIds)) {
                    $srcAddrNameById = RiskAddress::withTrashed()
                        ->whereIn('id', $srcAddrIds)
                        ->pluck('address_name', 'id')
                        ->all();
                }

                // leftJoin, not join: a target coverage with a NULL
                // risk_address_id was dropped from this list entirely by the
                // inner join, so it was invisible to the check below and got
                // duplicated too.
                $existingCombos = $model::withTrashed()
                    ->where('policy_coverages.action_id', $recordToId)
                    ->leftJoin('risk_address', $model::getModel()->getTable() . '.risk_address_id', '=', 'risk_address.id')
                    ->select(
                        $model::getModel()->getTable() . '.id',
                        'coverage_id',
                        'risk_address.address_name as risk_address_name',
                        $model::getModel()->getTable() . '.endors_flag',
                        $model::getModel()->getTable() . '.status',
                        $model::getModel()->getTable() . '.deleted_at'
                    )
                    ->get()
                    ->toArray();

                $newRecords = [];
                // Business keys already QUEUED for insert in this pass.
                // $existingCombos is read once, before the loop, so it can only
                // ever describe the target as it was on entry. When the SOURCE
                // action itself carries two rows for the same
                // (coverage_id, address) — a duplicate inherited from an older
                // replication — both missed the check and both were inserted, in
                // the same pass, with identical created_at. Queueing each key
                // once collapses a duplicated source to a single target row.
                $queuedKeys = [];

                foreach ($dataToBeReplicate as $record) {
                    $found = false;

                    foreach ($existingCombos as $existing) {

                        if (
                            $existing['coverage_id'] == $record->coverage_id &&
                            $addrKey($existing['risk_address_name'])
                                === $addrKey($srcAddrNameById[$record->risk_address_id] ?? ($record->riskAddress->address_name ?? null))
                        ) {
                            $found = true; // Already exists

                            // Target coverage was CANCELLED on this batch. It
                            // counts as present (no duplicate insert) but its
                            // rows are left exactly as the operator left them —
                            // no value sync, no un-delete. Keep scanning: if a
                            // LIVE row for the same key also exists, that one
                            // still gets synced below.
                            if (!empty($existing['deleted_at'])) {
                                continue;
                            }

                            // APPEND-ONLY: the coverage is already on the target
                            // — leave its flags exactly as the operator left
                            // them.
                            if ($protectTargetEdits) {
                                break;
                            }

                            // 🔥 Check column differences and update
                            $updateData = [];

                            if ($existing['endors_flag'] != $record->endors_flag) {
                                $updateData['endors_flag'] = $record->endors_flag;
                            }

                            if ($existing['status'] != $record->status) {
                                $updateData['status'] = $record->status;
                            }

                            // deleted_at is deliberately NOT synced. Copying the
                            // source's tombstone onto a matched target header
                            // tombstoned the WHOLE SECTION on the target — and
                            // because this is a query-builder update it fires no
                            // model events, so it never reached `audits` and the
                            // loss was unrecoverable by replay. A coverage
                            // cancelled on the source is a decision about the
                            // SOURCE action; the target keeps its own state.
                            // (The reverse case is already handled: a coverage
                            // cancelled on the TARGET is matched via withTrashed
                            // above and skipped, so it is never revived either.)

                            // If there are changes → update row
                            if (!empty($updateData)) {
                                $model::where('id', $existing['id'])->update($updateData);
                            }

                            break;
                        }
                    }

                    // If record not found in ToID → Insert (once per key)
                    if (!$found) {
                        $queueKey = $record->coverage_id . '|'
                            . $addrKey($srcAddrNameById[$record->risk_address_id] ?? ($record->riskAddress->address_name ?? null));
                        if (isset($queuedKeys[$queueKey])) {
                            Log::info('replicateRecordsIfMissing: duplicate SOURCE coverage collapsed — not replicated twice', [
                                'to_action'         => $recordToId,
                                'source_coverage'   => $record->id,
                                'coverage_id'       => $record->coverage_id,
                                'risk_address_id'   => $record->risk_address_id,
                            ]);
                            continue;
                        }
                        $queuedKeys[$queueKey] = true;
                        $newRecords[] = $record;
                    }
                }

                // Replace only the new ones for insert
                $dataToBeReplicate = collect($newRecords)->values();
            }
            // coverage if end
            // else 
            if ($uniqueKey == 'vehiclePlate') {

                $dataToBeReplicate = $query->where($columnName, $recordFromId)->get();
                $existingValues = $model::where($columnName, $recordToId)
                    ->pluck($uniqueKey)
                    ->toArray();

                $filteredData = collect();

                foreach ($dataToBeReplicate as $record) {
                    // if (!in_array($record->$uniqueKey, $existingValues)) {
                    $filteredData->push($record);
                    //}
                }

                $dataToBeReplicate = $filteredData;
                // dd($dataToBeReplicate);
            }
            if ($uniqueKey != 'vehiclePlate' && $uniqueKey != 'coverage') {

                $dataToBeReplicate = $query->where($columnName, $recordFromId)->get();

                if (empty($uniqueKey)) {
                    // No business key supplied — the PolicyBeneficiary call in
                    // newPolicyActionReplace / newPolicyActionReplaceSpecialist
                    // passes none. pluck(null) built `select `` from
                    // policy_beneficiary where action_id = ?` and threw
                    // "SQLSTATE[42S22] Unknown column ''", which the outer catch
                    // logged as "Replication failed" and returned [] — so NO
                    // beneficiary ever carried forward on a specialist
                    // anniversary / monthly / quarterly renew (all three route
                    // through newPolicyActionReplaceSpecialist).
                    //
                    // Fall back to a composite identity built from the row's own
                    // columns, minus the ones that legitimately differ between
                    // the source and target action (own id, the owning
                    // action/term, timestamps). That keeps the pass idempotent —
                    // a re-run of the same renew/refresh re-matches the row it
                    // already inserted instead of cloning a second copy.
                    $identity = static function ($record) use ($columnName) {
                        $attrs = collect($record->getAttributes())
                            ->except(['id', $columnName, 'term_id', 'created_at', 'updated_at', 'deleted_at'])
                            ->map(fn ($v) => is_null($v) ? '' : (string) $v)
                            ->sortKeys()
                            ->toArray();

                        return md5(json_encode($attrs));
                    };

                    $existingValues = $model::where($columnName, $recordToId)
                        ->get()
                        ->map($identity)
                        ->all();

                    $dataToBeReplicate = $dataToBeReplicate->filter(function ($record) use ($identity, $existingValues) {
                        return !in_array($identity($record), $existingValues, true);
                    });
                } else {
                    $existingValues = $model::where($columnName, $recordToId)
                        ->pluck($uniqueKey)
                        ->toArray();

                    $dataToBeReplicate = $dataToBeReplicate->filter(function ($record) use ($uniqueKey, $existingValues) {
                        return !in_array($record->$uniqueKey, $existingValues);
                    });
                }

            }  // replica for other than coverage (vehicle,benificiery etc..) else close
            $insertedData = [];

            // added new for motor
            $insertedData = ['to' => []];
            //  dd($dataToBeReplicate);
            if ($dataToBeReplicate->count() > 0) {  //coverage replication

                // if ($model === "AlphaDirect\Vehicle") {
                // Vehicle::where('action_id', $recordToId)->forceDelete();
                // }
                // Normal replication flow
                //dd($dataToBeReplicate);
                foreach ($dataToBeReplicate as $key => $fromReplicate) {
                    try {
                    // Handle RiskAddress mapping
                    // ✅ For PolicyCoverage model
                    if ($model === "AlphaDirect\Models\PolicyCoverage") {
                        // Replicate the coverage onto the NEW action and apply the
                        // standard override columns (term_id / row_type).
                        $toReplicate = $fromReplicate->replicate();
                        $toReplicate->$columnName = $recordToId;
                        foreach ($othercolumns as $col => $value) {
                            $toReplicate->$col = $value;
                        }

                        // Re-point risk_address_id at the NEW (renew) action's own
                        // risk address, matched by address_name. Risk addresses are
                        // replicated before coverages so the match exists; only when
                        // none is found do we keep the source id as a fallback.
                        // (Previously the re-point was overwritten by the generic
                        // else-branch below, so the renewed coverage kept the SOURCE
                        // action's risk_address_id.)
                        if ($fromReplicate->risk_address_id) {
                            $fromRiskAddress = RiskAddress::find($fromReplicate->risk_address_id);
                            if ($fromRiskAddress) {
                                $riskAddress = RiskAddress::Action($recordToId)
                                    ->AddressName($fromRiskAddress->address_name)
                                    ->first();
                                if ($riskAddress) {
                                    $toReplicate->risk_address_id = $riskAddress->id;
                                }
                            }
                        }
                    } elseif ($model === "AlphaDirect\Vehicle") {

                        // 1. Get FROM Risk Address
                        $fromRisk = RiskAddress::find($fromReplicate->risk_id);
                        if (!$fromRisk) {
                            // No risk on FROM -> nothing to do
                            continue;
                        }

                        // 2. Find matching TO Risk Address
                        $toRisk = RiskAddress::Action($recordToId)
                            ->AddressName($fromRisk->address_name)
                            ->first();

                        if (!$toRisk) {
                            // No matching risk -> skip
                            continue;
                        }

                        // 3. Check if vehicle already exists in TO action + risk
                        $existingVehicle = Vehicle::where('action_id', $recordToId)
                            ->where('risk_id', $toRisk->id)
                            ->where('vehiclePlate', $fromReplicate->vehiclePlate)
                            ->first();

                        if ($existingVehicle) {

                            // 4. UPDATE existing TO vehicle
                            $updateData = collect($fromReplicate->toArray())
                                ->except(['id', 'action_id', 'risk_id', 'created_at', 'updated_at', 'deleted_at'])
                                ->toArray();

                            $existingVehicle->update($updateData);

                        } else {

                            // 5. INSERT new vehicle (replicate)
                            $newVehicle = $fromReplicate->replicate();
                            $newVehicle->action_id = $recordToId;
                            $newVehicle->risk_id = $toRisk->id;
                            $newVehicle->save();
                        }

                        continue; // proceed safely to next foreach iteration
                    } else {
                        $toReplicate = $fromReplicate->replicate();
                        $toReplicate->$columnName = $recordToId;
                        foreach ($othercolumns as $col => $value) {
                            $toReplicate->$col = $value;
                        }
                    }

                    $toReplicate->save();
                    $insertedData['to'][] = $toReplicate->id;

                    // Handle relations
                    foreach ($withRelation as $relation => $relationId) {

                        $relatedItems = $fromReplicate->$relation;

                        if (!$relatedItems)
                            continue;

                        if (is_iterable($relatedItems)) {
                            // Collapse exact-duplicate SOURCE rows so a
                            // duplicate already sitting on the source coverage
                            // is not copied into the new action as well (same
                            // guard replicateRecords() applies). Extension /
                            // sub-coverage rows ignore the per-action pro-rata
                            // stamps when comparing — see
                            // replicationChildSignature().
                            $seenChildSig = [];
                            foreach (self::dedupeReplicationChildren($relatedItems) as $related) {
                                // Foreign-extension guard: never carry an extension/
                                // excess row onto a coverage it doesn't belong to.
                                if (self::isForeignExtensionRow($related, $toReplicate->coverage_id)) {
                                    continue;
                                }
                                // A row CANCELLED on the source is not carried
                                // forward at all — cloning it would insert a
                                // dead, already-tombstoned row into the new
                                // action. See replicationSourceRowIsCancelled().
                                if (self::replicationSourceRowIsCancelled($related)) {
                                    continue;
                                }
                                $childSig = self::replicationChildSignature($related, $relationId);
                                if (isset($seenChildSig[$childSig])) {
                                    continue;
                                }
                                $seenChildSig[$childSig] = true;
                                // Per-child guard: one bad child row must never
                                // abort the whole tree. Log & skip it, keep going.
                                try {
                                    $cloned = $related->replicate();
                                    $cloned->$relationId = $toReplicate->id;
                                    // APPEND-ONLY: re-stamp the row onto the
                                    // target action so its pro-rata engine
                                    // rates it — see stampAppendedChildRow.
                                    if ($protectTargetEdits) {
                                        self::stampAppendedChildRow($cloned, (int) $recordToId);
                                    }
                                    $cloned->save();
                                } catch (\Throwable $e) {
                                    Log::warning('replicateRecordsIfMissing: skipped child ' . $relation . ' that failed to replicate', [
                                        'to_coverage' => $toReplicate->id,
                                        'error'       => $e->getMessage(),
                                    ]);
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // Per-coverage guard: a single coverage that fails to
                    // replicate is logged and skipped so every OTHER coverage
                    // still replicates. Renewal must never stop half-way.
                    Log::warning('replicateRecordsIfMissing: skipped a coverage that failed to replicate', [
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
                }
            }

            // Sync sub-relations for ALREADY-EXISTING (matched) target coverages —
            // regardless of whether the main loop above inserted any new coverages.
            // Without this, when an ENDORSE changed values on a coverage row that
            // already existed in a downstream Anniversary / Renew QUOTE (matched
            // by coverage_id + risk_address_name), those value changes never
            // propagated — the main loop only handled NEW coverages and this
            // block was previously gated to "else" so it only ran when source
            // had zero new coverages. The just-inserted coverages already had
            // their relations copied inline by the main loop, so we exclude
            // those ids here to avoid duplicate inserts.
            if ($uniqueKey == 'coverage' && !empty($withRelation)) {
                $skipInsertedCoverageIds = $insertedData['to'] ?? [];
                foreach ($withRelation as $relation => $relationId) {
                    $relatedModelClass = get_class((new $model)->$relation()->getRelated());

                    $coverageFromReplicate = PolicyCoverage::where('policy_coverages.action_id', $recordFromId)
                        ->join('risk_address', 'policy_coverages.risk_address_id', '=', 'risk_address.id')
                        ->whereNull('policy_coverages.deleted_at')
                        // Coverage-selective FORWARD: keep the sub-relation sync
                        // scoped to the same coverage_ids as the main loop above.
                        ->when(!empty($onlyCoverageIds), fn ($q) => $q->whereIn('policy_coverages.coverage_id', $onlyCoverageIds))
                        //->where('coverage_id', 22)
                        ->get(['policy_coverages.*', 'risk_address.address_name as risk_address_name']);

                    $coverageToReplicate = PolicyCoverage::where('policy_coverages.action_id', $recordToId)
                        ->join('risk_address', 'policy_coverages.risk_address_id', '=', 'risk_address.id')
                        ->whereNull('policy_coverages.deleted_at')
                        ->when(!empty($skipInsertedCoverageIds), fn ($q) => $q->whereNotIn('policy_coverages.id', $skipInsertedCoverageIds))
                       // ->where('coverage_id', 22)
                        ->get(['policy_coverages.*', 'risk_address.address_name as risk_address_name']);

                    foreach ($coverageFromReplicate as $fromCoverage) {
                        try {

                        $toCoverage = $coverageToReplicate->first(function ($toCoverage) use ($fromCoverage) {
                            return (
                                $toCoverage->coverage_id == $fromCoverage->coverage_id &&
                                trim(strtolower($toCoverage->risk_address_name)) == trim(strtolower($fromCoverage->risk_address_name))
                            );
                        });
                        // dd($toCoverage);
                        // Skip if no matching coverage found
                        if (!$toCoverage) {
                            continue;
                        }

                        $policy_coverage_id_old = $fromCoverage->id;
                        $policy_coverage_id_new = $toCoverage->id;

                        // Motor-attached specified items only exist on motor
                        // coverages (master coverage_id 22 / 27). On every other
                        // coverage a specified item is coverage-level regardless
                        // of any stray motor_id, so the motor-registration path is
                        // skipped and the relaxed NULL/0 match is used instead.
                        $isMotorCoverage = in_array((int) ($toCoverage->coverage_id ?? 0), [22, 27], true);

                        $relatedItems = $relatedModelClass::where($relationId, $policy_coverage_id_old)->get();
                        // dd($relatedItems );
                        // 🧩 FIXED: use continue instead of return
                        if (!isset($relatedItems) || $relatedItems->isEmpty()) {
                            continue;
                        }

                        foreach ($relatedItems as $related) {
                            if (!$related || !is_object($related)) {
                                continue;
                            }
                            $compareData = [];
                            $relatedModelName = class_basename($relatedModelClass);

                            // Foreign-extension guard: skip mis-attached extension/
                            // excess rows so they aren't synced onto a coverage they
                            // don't belong to (matched target = $toCoverage).
                            if (self::isForeignExtensionRow($related, $toCoverage->coverage_id)) {
                                continue;
                            }
                            // A row CANCELLED on the source neither inserts nor
                            // syncs onto the target. Without this the sync below
                            // pushed the source's tombstone onto a live target
                            // row and the section vanished from an ISSUED
                            // action. See replicationSourceRowIsCancelled().
                            if (self::replicationSourceRowIsCancelled($related)) {
                                continue;
                            }

                            switch ($relatedModelName) {
                                case 'PolicyExtentionDetails':
                                    // extentions_id for master-backed rows;
                                    // type + free-text name for custom / Excess
                                    // rows (extentions_id NULL) which would
                                    // otherwise all match each other. See
                                    // extensionBusinessKey().
                                    $compareData = self::extensionBusinessKey($related);
                                    break;

                                case 'PolicySpecifiedItem':
                                    $compareData = collect($related->only(['specified_coverage_id', 'motor_id']))->toArray();
                                    break;

                                case 'Motor':
                                    $compareData = collect($related->only(['registration_no']))->toArray();
                                    break;
                                case 'PolicyCoverageDetail':
                                    $compareData = collect($related->only(['coverage_id']))->toArray();
                                    break;
                                default:
                                    $compareData = collect($related->getAttributes())
                                        ->except(['id', $relationId, 'created_at', 'updated_at', 'deleted_at'])
                                        ->toArray();
                                    break;

                            }
                             //dd( $compareData);
                            if (empty($compareData)) {
                                continue;
                            }
                            $getMotorIdMisc = null;

                            if ($relatedModelName === 'PolicySpecifiedItem' && $isMotorCoverage) {

                                // 1️⃣ Get registration from OLD motor
                                if (empty($compareData['motor_id'])) {
                                    continue;
                                }

                                $oldMotor = Motor::find($compareData['motor_id']);
                                if (!$oldMotor || empty($oldMotor->registration_no)) {
                                    continue;
                                }

                                $registrationNo = $oldMotor->registration_no;

                                // 2️⃣ Check registration exists in OLD specified items
                                $existsInOld = PolicySpecifiedItem::join(
                                        'motor as m',
                                        'm.id',
                                        '=',
                                        'policy_specified_items.motor_id'
                                    )
                                    ->where('policy_specified_items.' . $relationId, $related->$relationId) // OLD coverage
                                    ->where('m.registration_no', $registrationNo)
                                    ->exists();

                                if (!$existsInOld) {
                                    continue;
                                }

                                // 3️⃣ Check registration exists in CURRENT motors
                                $motor = Motor::where('registration_no', $registrationNo)
                                    ->where('policy_coverage_id', $policy_coverage_id_new)
                                    ->first();
                                if (!$motor) {
                                    continue;
                                }

                                // 4️⃣ Find existing specified item in CURRENT coverage
                                $existingRecord = $relatedModelClass::where($relationId, $policy_coverage_id_new)
                                    ->where('motor_id', $motor->id)
                                    ->first();
                                /* ⬇⬇⬇ KEEP YOUR LOGIC BELOW (UNCHANGED) ⬇⬇⬇ */

                                if (!$existingRecord) {

                                // Same rule as the coverage-level branch below:
                                // the per-vehicle item may exist but be
                                // SOFT-DELETED on the target batch — the
                                // operator CANCELLED it on this endorse. The
                                // SoftDeletes scope hides it from the lookup
                                // above, so this branch cloned a fresh LIVE
                                // copy on every re-run of the additive
                                // replicate (Refresh "fill missing",
                                // endorse-issue forward propagation, batch
                                // renew) and the cancelled item came back.
                                // Leave it cancelled.
                                $removedMotorItemScope = function ($q) use ($relationId, $policy_coverage_id_new, $motor) {
                                    $q->where($relationId, $policy_coverage_id_new)
                                        ->where('motor_id', $motor->id);
                                };
                                if (self::childRowRemovedInTarget($relatedModelClass, $removedMotorItemScope)) {
                                    Log::info('replicateRecordsIfMissing: per-vehicle specified item is cancelled on the target batch — not re-cloned', [
                                        'to_coverage'  => $policy_coverage_id_new,
                                        'motor_id'     => $motor->id,
                                        'registration' => $registrationNo,
                                    ]);
                                    continue;
                                }

                                /* ➕ Create missing specified item */
                                $cloned = $related->replicate();
                                $cloned->$relationId = $policy_coverage_id_new;
                                $cloned->motor_id = $motor->id;
                                if ($protectTargetEdits) {
                                    self::stampAppendedChildRow($cloned, (int) $recordToId);
                                }
                                $cloned->save();

                            } else {

                                /* 🔄 Update existing specified item */
                                $dataToUpdate = $related->toArray();

                                $transactionType = PolicyAction::where('id', $recordToId)
                                    ->value('transaction_type');

                                $excluded = [
                                    'policy_coverage_id',
                                    'id',
                                    'created_at',
                                    'updated_at',
                                    'action_id',
                                    'motor_id',
                                    'endors_flag',
                                ];

                                // (rest of your update logic continues...)
                            }
                            }

                            // Coverage-level specified items store motor_id as
                            // NULL (V2) or 0 (legacy/replicated) interchangeably.
                            // The source item may carry one form while the row
                            // already carried into the renew/endorse coverage
                            // carries the other, so an exact motor_id match (via
                            // ->where($compareData)) treats the existing row as
                            // "missing" and clones a NULL-motor duplicate on every
                            // anniversary-renew refresh. Match coverage-level SIs
                            // by specified_coverage_id with motor_id NULL-or-0
                            // instead. Same fix class as updateCoverage's
                            // existingActive query; motor-attached SIs (motor_id
                            // set) keep the exact compareData match.
                            $isCoverageLevelSI = $relatedModelName === 'PolicySpecifiedItem'
                                && (!$isMotorCoverage || empty($compareData['motor_id']));
                            $matchScope = function ($q) use ($relationId, $policy_coverage_id_new, $compareData, $isCoverageLevelSI) {
                                $q->where($relationId, $policy_coverage_id_new);
                                if ($isCoverageLevelSI) {
                                    $q->where('specified_coverage_id', $compareData['specified_coverage_id'] ?? null)
                                        ->where(function ($m) {
                                            $m->whereNull('motor_id')->orWhere('motor_id', 0);
                                        });
                                } else {
                                    $q->where($compareData);
                                }
                            };

                            // ------------------------------------------------------------------
                            // Fetch duplicates (same fields + same relation id)
                            // ------------------------------------------------------------------
                            $duplicates = $relatedModelClass::where($matchScope)
                                ->orderBy('id')
                                ->get();

                            // If duplicates found → keep first, delete extra
                            if ($duplicates->count() > 0) {

                                // Keep the first record
                                $keep = $duplicates->shift();

                                // Delete only extra records
                                if ($duplicates->isNotEmpty()) {
                                    $relatedModelClass::whereIn('id', $duplicates->pluck('id'))->delete();
                                }

                                // No need to insert again
                                // continue;
                            }

                            if($relatedModelName !== 'PolicySpecifiedItem' || $isCoverageLevelSI){
                            $existingRecord = $relatedModelClass::where($matchScope)
                                ->first();
                            if (!$existingRecord) {
                                // The row may exist but be SOFT-DELETED on the
                                // target batch — i.e. the operator removed it
                                // there (or a UW/Snehal clean-up removed a
                                // duplicate). The SoftDeletes scope hides it
                                // from the lookup above, so this branch used to
                                // clone a brand-new LIVE copy of it on every
                                // re-run of the additive replicate (endorse-issue
                                // forward propagation, Refresh "fill missing",
                                // batch renew) — the removed extension came
                                // back and the table grew a fresh row each time.
                                // Treat "removed on the target" like the
                                // BackdatedEndorseRefresher EC-5 rule: leave it
                                // alone, never re-add, never duplicate.
                                if (self::childRowRemovedInTarget($relatedModelClass, $matchScope)) {
                                    Log::info('replicateRecordsIfMissing: child row is soft-deleted on the target batch — not re-cloned', [
                                        'model'        => $relatedModelName,
                                        'to_coverage'  => $policy_coverage_id_new,
                                        'business_key' => $compareData,
                                    ]);
                                    continue;
                                }
                                $cloned = $related->replicate();
                                $cloned->$relationId = $policy_coverage_id_new;
                                // APPEND-ONLY: never carry the SOURCE action's
                                // pro-rata onto a row we are adding to another
                                // action — that batch's pro-rata is its own,
                                // and the row must be stamped onto THIS action
                                // or the target's rating loops skip it entirely.
                                if ($protectTargetEdits) {
                                    self::stampAppendedChildRow($cloned, (int) $recordToId);
                                }
                                $cloned->save();
                            } else {
                                // APPEND-ONLY: leave a row the TARGET action
                                // itself edited exactly as the operator left it.
                                // Same test as BackdatedEndorseRefresher's
                                // universal edit protection (wizard-stamped
                                // during the target action).
                                if ($protectTargetEdits
                                    && (int) ($existingRecord->endors_flag ?? 0) === 1
                                    && (int) ($existingRecord->previousActionIdCov ?? 0) === (int) $recordToId) {
                                    continue;
                                }

                                $dataToUpdate = $related->toArray();

                                $excluded = [
                                    'policy_coverage_id',
                                    'id',
                                    'created_at',
                                    'updated_at',
                                    'previousActionIdCov',
                                    'pro_rate_premium',
                                    'endors_flag',
                                    'policyCoverageID',
                                ];
                                // NEVER copy the source's deleted_at onto a target
                                // row. Whether a row is live is the TARGET action's
                                // own state; a value sync must not decide it.
                                //
                                // History: this exclusion was commented out on
                                // 10-02-2026 (COMG2025133674 — "a vehicle deleted
                                // upon Renewal reappears in the next transaction").
                                // The real cause of that ticket was Motor having
                                // SoftDeletes disabled, so the replicator read the
                                // cancelled vehicle as live; letting the tombstone
                                // ride along masked it. The side effect was that
                                // ANY cancellation on the source silently
                                // tombstoned the matching row on every downstream
                                // action — including ISSUED ones, unaudited,
                                // wiping whole sections off issued policies.
                                //
                                // The source-cancelled skip above now handles the
                                // original ticket at its root, so this exclusion is
                                // restored, and unconditionally: RENEW and
                                // ANNIVERSARY-RENEW targets were never a valid
                                // exception either.
                                $excluded[] = 'deleted_at';

                                $dataToUpdate = collect($dataToUpdate)->except($excluded)->toArray();
                                
                                $existingRecord->update($dataToUpdate);
                            }
                            }
                        }
                        } catch (\Throwable $e) {
                            Log::warning('replicateRecordsIfMissing: skipped sync for a coverage that failed', [
                                'error' => $e->getMessage(),
                            ]);
                            continue;
                        }
                    }
                }
            }

            return $insertedData;
        } catch (\Exception $e) {
            Log::error("Replication failed: " . $e->getMessage());
            return [];
        }
    }


    public static function replicateRecordsReplace(
        $recordFromId,
        $recordToId,
        $model,
        $columnName,
        $othercolumns,
        $withRelation = []
    ) {

        try {
            $query = $model::where($columnName, $recordToId);
            foreach ($othercolumns as $othercolumnsName => $columnValue) {
                $query->where($othercolumnsName, $columnValue);


                $alreadyExists = $query->exists();

                if ($alreadyExists) {
                    $query->delete(); // delete selected records
                }
            }


            // ✅ Now reuse  standard replicate method
            return static::replicateRecords(
                $recordFromId,
                $recordToId,
                $model,
                $columnName,
                $othercolumns,
                $withRelation
            );
        } catch (\Exception $e) {
            // Log::error($e->getMessage());
            return [];
        }
    }
    public static function replicateRecords($recordFromId, $recordToId, $model, $columnName, $othercolumns, $withRelation = [])
    {
        try {
            if (!empty($withRelation)) {
                $dataToBeReplicate = $model::with(array_keys($withRelation))->where($columnName, $recordFromId)->get();
            } else {
                $dataToBeReplicate = $model::where($columnName, $recordFromId)->get();
            }

            $insertedData = [];
            // Coverage-level duplicate collapse, twin of the child-row
            // $seenRelSig guard further down. This is a blind copier: it
            // faithfully reproduced a SOURCE action that already carried two
            // policy_coverages rows for the same (coverage_id, risk address),
            // so one pre-existing duplicate was re-created on every renew /
            // endorse and rendered as the same section twice on the V2 Quote
            // and twice in the Endorse Change Summary. The uniqueness rule is
            // addCoverage's own: one (coverage, risk address) per action.
            $seenCoverageKey = [];
            foreach ($dataToBeReplicate as $key => $fromReplicate) {
                if ($model == "AlphaDirect\Models\PolicyCoverage") {
                    $srcAddrName = $fromReplicate->risk_address_id
                        ? RiskAddress::withTrashed()->where('id', $fromReplicate->risk_address_id)->value('address_name')
                        : null;
                    $covKey = $fromReplicate->coverage_id . '|' . strtolower(trim((string) ($srcAddrName ?? '')));
                    if (isset($seenCoverageKey[$covKey])) {
                        \Log::info('replicateRecords: duplicate SOURCE coverage collapsed — not replicated twice', [
                            'to_action'        => $recordToId,
                            'source_coverage'  => $fromReplicate->id,
                            'kept_source'      => $seenCoverageKey[$covKey],
                            'coverage_id'      => $fromReplicate->coverage_id,
                            'risk_address_id'  => $fromReplicate->risk_address_id,
                        ]);
                        continue;
                    }
                    $seenCoverageKey[$covKey] = $fromReplicate->id;
                }
                $insertedData['form'][$key] = $fromReplicate->id;
                $toReplicate = $fromReplicate->replicate();
                $toReplicate->$columnName = $recordToId;
                foreach ($othercolumns as $othercolumnsName => $columnValue) {
                    $toReplicate->$othercolumnsName = $columnValue;
                }
                if ($model == "AlphaDirect\Models\PolicyCoverage") {
                    $fromRiskAddress = RiskAddress::find($fromReplicate->risk_address_id);
                    if ($fromRiskAddress) {
                        $riskAddress = RiskAddress::Action($recordToId)
                            ->AddressName($fromRiskAddress->address_name)->first();
                        if ($riskAddress) {
                            $toReplicate->risk_address_id = $riskAddress->id;
                        } else {
                            \Log::warning('replicateRecords: no matching RiskAddress on new action for PolicyCoverage; keeping source id', [
                                'source_coverage_id' => $fromReplicate->id,
                                'source_risk_address_id' => $fromReplicate->risk_address_id,
                                'address_name' => $fromRiskAddress->address_name,
                                'new_action_id' => $recordToId,
                            ]);
                        }
                    } else {
                        \Log::warning('replicateRecords: source RiskAddress missing for PolicyCoverage; keeping source id', [
                            'source_coverage_id' => $fromReplicate->id,
                            'source_risk_address_id' => $fromReplicate->risk_address_id,
                        ]);
                    }
                }
                if ($model == 'AlphaDirect\Vehicle') {
                    $fromRiskAddress = RiskAddress::find($fromReplicate->risk_id);
                    if ($fromRiskAddress) {
                        $riskAddress = RiskAddress::Action($recordToId)
                            ->AddressName($fromRiskAddress->address_name)->first();
                        if ($riskAddress) {
                            $toReplicate->risk_id = $riskAddress->id;
                        } else {
                            \Log::warning('replicateRecords: no matching RiskAddress on new action for Vehicle; keeping source id', [
                                'source_vehicle_id' => $fromReplicate->id,
                                'source_risk_id' => $fromReplicate->risk_id,
                                'address_name' => $fromRiskAddress->address_name,
                                'new_action_id' => $recordToId,
                            ]);
                        }
                    } else {
                        \Log::warning('replicateRecords: source RiskAddress missing for Vehicle; keeping source id', [
                            'source_vehicle_id' => $fromReplicate->id,
                            'source_risk_id' => $fromReplicate->risk_id,
                        ]);
                    }
                }

                $toReplicate->save();
                $insertedData['to'][$key] = $toReplicate->id;
                foreach ($withRelation as $relation => $relationId) {
                    if ($relation == "entities") {
                        foreach ($fromReplicate->$relation as $realtedData) {
                            $toReplicateRelation = $realtedData->replicate();
                            $toReplicateRelation->$relationId = $toReplicate->id;
                            if ($realtedData->entity_type == "Vehicle") {
                                $fromVehicle = Vehicle::find($realtedData->entity_id);
                                if ($fromVehicle) {
                                    $vehicle = Vehicle::ActionId($recordToId)
                                        ->EngineNo($fromVehicle->engineNo)->first();
                                    if ($vehicle) {
                                        $toReplicateRelation->entity_id = $vehicle->id;
                                    } else {
                                        \Log::warning('replicateRecords: no matching Vehicle on new action for entity relation; keeping source entity_id', [
                                            'source_entity_id' => $realtedData->entity_id,
                                            'engineNo' => $fromVehicle->engineNo,
                                            'new_action_id' => $recordToId,
                                        ]);
                                    }
                                } else {
                                    \Log::warning('replicateRecords: source Vehicle missing for entity relation; keeping source entity_id', [
                                        'source_entity_id' => $realtedData->entity_id,
                                    ]);
                                }
                                $toReplicateRelation->save();
                            }
                            if ($realtedData->entity_type == "Member") {
                                $fromVehicle = PolicyBeneficiary::find($realtedData->entity_id);
                                if ($fromVehicle) {
                                    $member = PolicyBeneficiary::Action($recordToId)->FirstName($fromVehicle->first_name)
                                        ->FirstName($fromVehicle->first_name)->MiddleName($fromVehicle->middle_name)->LastName($fromVehicle->last_name)
                                        ->first();
                                    if ($member) {
                                        $toReplicateRelation->entity_id = $member->id;
                                    } else {
                                        \Log::warning('replicateRecords: no matching Member on new action for entity relation; keeping source entity_id', [
                                            'source_entity_id' => $realtedData->entity_id,
                                            'first_name' => $fromVehicle->first_name,
                                            'middle_name' => $fromVehicle->middle_name,
                                            'last_name' => $fromVehicle->last_name,
                                            'new_action_id' => $recordToId,
                                        ]);
                                    }
                                } else {
                                    \Log::warning('replicateRecords: source Member missing for entity relation; keeping source entity_id', [
                                        'source_entity_id' => $realtedData->entity_id,
                                    ]);
                                }
                                $toReplicateRelation->save();
                            }
                            if ($realtedData->entity_type == "Device") {
                                $fromVehicle = PolicyCellPhone::find($realtedData->entity_id);
                                if ($fromVehicle) {
                                    $device = PolicyCellPhone::Action($recordToId)->DeviceType($fromVehicle->device_type)
                                        ->Imei($fromVehicle->imei)->first();
                                    if ($device) {
                                        $toReplicateRelation->entity_id = $device->id;
                                    } else {
                                        \Log::warning('replicateRecords: no matching Device on new action for entity relation; keeping source entity_id', [
                                            'source_entity_id' => $realtedData->entity_id,
                                            'device_type' => $fromVehicle->device_type,
                                            'imei' => $fromVehicle->imei,
                                            'new_action_id' => $recordToId,
                                        ]);
                                    }
                                } else {
                                    \Log::warning('replicateRecords: source Device missing for entity relation; keeping source entity_id', [
                                        'source_entity_id' => $realtedData->entity_id,
                                    ]);
                                }
                                $toReplicateRelation->save();
                            }
                        }
                        continue;
                    }
                    if (is_a($fromReplicate->$relation, 'Illuminate\Database\Eloquent\Collection')) {
                        Log::info("Replicating relation many: $relation");
                        // Skip exact-duplicate source rows so pre-existing
                        // duplicates (from a historical double-replication) are
                        // NOT copied forward into the new action. Without this,
                        // every anniversary/endorse faithfully re-copied the
                        // duplicates, so the V2 Quote/Doc rendered every
                        // sub-coverage, specified item and extension twice.
                        // Signature excludes id / timestamps / deleted_at and
                        // the parent FK (which is re-pointed), so genuinely
                        // distinct rows are still replicated; identical
                        // duplicates collapse to one.
                        $seenRelSig = [];
                        foreach (self::dedupeReplicationChildren($fromReplicate->$relation) as $realtedData) {
                            // Foreign-extension guard: don't replicate an extension/
                            // excess row onto a coverage it doesn't belong to.
                            if (self::isForeignExtensionRow($realtedData, $toReplicate->coverage_id)) {
                                continue;
                            }
                            // Signature is built by replicationChildSignature()
                            // so extension / sub-coverage rows also ignore the
                            // per-action pro-rata stamps (previousActionIdCov /
                            // endors_flag / pro_rate_premium). Comparing whole
                            // attribute sets meant a duplicated extension whose
                            // copies had been stamped by different Rate runs
                            // looked like two distinct rows, so the ANNIVERSARY
                            // cron (newPolicyAction → here) carried the
                            // duplicate into the new term every year.
                            $sig = self::replicationChildSignature($realtedData, $relationId);
                            if (isset($seenRelSig[$sig])) {
                                continue;
                            }
                            $seenRelSig[$sig] = true;

                            $toReplicateRelation = $realtedData->replicate();
                            $toReplicateRelation->$relationId = $toReplicate->id;
                            $toReplicateRelation->save();
                            // Track old → new motor_id so per-motor child rows
                            // (notes, specified_items) can be re-pointed below.
                            // Without this every per-vehicle row gets stamped
                            // with the LAST motor_id (nested loop bug) — the
                            // user's motor-wise notes / misc items collapsed to
                            // a single vehicle on every endorsement.
                            if ($relation === 'motor') {
                                $motorIdMap[$realtedData->id] = $toReplicateRelation->id;
                            }
                        }
                    } else {
                        if ($fromReplicate->$relation) {

                            $toReplicateRelation = $fromReplicate->$relation->replicate();
                            $toReplicateRelation->$relationId = $toReplicate->id;
                            $toReplicateRelation->save();
                        }
                    }

                }

                // Re-point per-motor child rows using the (old → new) motor_id
                // map built while replicating the motor relation. Use bulk
                // DB::table updates rather than ->save() per row — saving
                // through Eloquent fires the audit observer per update which
                // adds an extra JOIN query and an audit-row insert each.
                // On policies with many coverages × motors that compounds
                // into a 60s timeout. Direct DB updates skip both.
                $motorIdMap = $motorIdMap ?? [];
                if (!empty($motorIdMap)) {
                    foreach ($motorIdMap as $oldMotorId => $newMotorId) {
                        DB::table('policy_coverage_notes')
                            ->where('policy_coverage_id', $toReplicate->id)
                            ->where('motor_id', $oldMotorId)
                            ->update(['motor_id' => $newMotorId, 'updated_at' => now()]);
                        DB::table('policy_specified_items')
                            ->where('policy_coverage_id', $toReplicate->id)
                            ->where('motor_id', $oldMotorId)
                            ->update(['motor_id' => $newMotorId, 'updated_at' => now()]);
                    }
                }
                // Reset the map for the next coverage iteration so motor IDs
                // don't leak across unrelated coverages.
                $motorIdMap = [];
            }
            return $insertedData;
        } catch (\Exception $e) {
            // Surface the failure upstream. Previously this swallowed the
            // exception which left endorsements with missing coverages /
            // motors / notes and no log trace. Monika's #8 report on
            // COMG2024112441 was exactly this — the endorse QUOTE had no
            // replicated data because a relation threw and nobody knew.
            \Log::error('PolicyAction::replicateRecords failed', [
                'from'   => $recordFromId,
                'to'     => $recordToId,
                'model'  => $model,
                'column' => $columnName,
                'error'  => $e->getMessage(),
                'line'   => $e->getLine(),
                'file'   => basename($e->getFile()),
            ]);
            throw $e;
        }
    }

    public static function replicateRecordsEndorse($recordFromId, $recordToId, $model, $columnName, $othercolumns, $withRelation = [])
    {
        try {
            $insertedData = [];
            // Fetch records with or without relations
            $query = $model::where($columnName, $recordFromId);
            if (!empty($withRelation)) {
                $query = $query->with(array_keys($withRelation));
            }
            $dataToBeReplicate = $query->get();
            foreach ($dataToBeReplicate as $key => $fromReplicate) {
                // Check if the record already exists for the target
                $existingRecord = $model::where($columnName, $recordToId);

                foreach ($othercolumns as $otherColumnName => $columnValue) {
                    $existingRecord->where($otherColumnName, $columnValue);
                }
                $existingRecord1 = $existingRecord->first();
                $existingRecord = $existingRecord->exists();

                $exist = $existingRecord ? 1 : 0;
                if ($exist == 1) {
                    if (!empty($withRelation)) {
                        foreach ($withRelation as $relation => $relationId) {
                            // Handle other relations and check uniqueness
                            if (is_a($fromReplicate->$relation, 'Illuminate\Database\Eloquent\Collection')) {
                                foreach ($fromReplicate->$relation as $relatedData) {
                                    if ($relation == 'motor') {
                                        $exists = Motor::where('registration_no', $relatedData->registration_no)
                                            ->where('policy_coverage_id', $existingRecord1->id)
                                            ->exists();
                                        if (!$exists) {
                                            $toReplicateRelation = $relatedData->replicate();
                                            $toReplicateRelation->$relationId = $existingRecord1->id;
                                            $toReplicateRelation->save();
                                            //continue; // Skip if already exists
                                        }
                                    } else if ($relation == 'coverageDetail') {
                                        $exists = PolicyCoverageDetail::where('coverage_id', $existingRecord1->coverage_id)
                                            ->where('policy_coverage_id', $existingRecord1->id)
                                            ->exists();
                                        if (!$exists) {
                                            // continue; // Skip if already exists
                                            $toReplicateRelation = $relatedData->replicate();
                                            $toReplicateRelation->$relationId = $existingRecord1->id;
                                            $toReplicateRelation->save();
                                        }

                                    } else if ($relation == 'extentionDetail') {
                                        $exists = PolicyExtentionDetails::where('policy_coverage_id', $existingRecord1->id)
                                            ->exists();
                                        if (!$exists) {
                                            // continue; // Skip if already exists
                                            $toReplicateRelation = $relatedData->replicate();
                                            $toReplicateRelation->$relationId = $existingRecord1->id;
                                            $toReplicateRelation->save();
                                        }

                                    } else if ($relation == 'note') {
                                        // $exists = PolicyCoverageNote::
                                        // where('policy_coverage_id', $existingRecord1->id)
                                        // ->exists();
                                        // if ($exists) {
                                        //     continue; // Skip if already exists
                                        // }

                                        $toReplicateRelation = $relatedData->replicate();
                                        $toReplicateRelation->$relationId = $existingRecord1->id;
                                        $toReplicateRelation->save();


                                    } else {
                                        if ($fromReplicate->$relation && $relation != 'coverageDetail' && $relation != 'motor' && $relation != 'note' && $relation != 'extentionDetail') {
                                            // $exists = $relation::where($relationId, $existingRecord1->id)
                                            //     ->where('id', $fromReplicate->$relation->id)
                                            //     ->exists();

                                            // if (!$exists) {
                                            $toReplicateRelation = $fromReplicate->$relation->replicate();
                                            $toReplicateRelation->$relationId = $existingRecord1->id;
                                            $toReplicateRelation->save();
                                            // }
                                        }
                                    }
                                }
                            } else {
                                if ($fromReplicate->$relation) {
                                    // $exists = $relation::where($relationId, $existingRecord1->id)
                                    //     ->where('id', $fromReplicate->$relation->id)
                                    //     ->exists();

                                    // if (!$exists) {
                                    $toReplicateRelation = $fromReplicate->$relation->replicate();
                                    $toReplicateRelation->$relationId = $existingRecord1->id;
                                    $toReplicateRelation->save();
                                    //}
                                }
                            }
                        }
                    }
                    continue; // Skip if record already exists

                }

                // // Proceed with replication
                $toReplicate = $fromReplicate->replicate();
                $toReplicate->$columnName = $recordToId;

                foreach ($othercolumns as $otherColumnName => $columnValue) {
                    $toReplicate->$otherColumnName = $columnValue;
                }

                // Handle PolicyCoverage case with RiskAddress check
                if ($model == "AlphaDirect\Models\PolicyCoverage") {
                    $fromRiskAddress = RiskAddress::find($fromReplicate->risk_address_id);
                    if ($fromRiskAddress) {
                        $riskAddress = RiskAddress::Action($recordToId)
                            ->AddressName($fromRiskAddress->address_name)
                            ->first();

                        if ($riskAddress) {
                            $toReplicate->risk_address_id = $riskAddress->id;
                        }
                    }
                }
                //  $toReplicate->save();
                $insertedData['to'][$key] = $toReplicate->id;
                // Handle relations and ensure uniqueness
                if (!empty($withRelation)) {
                    // foreach ($withRelation as $relation => $relationId) {
                    //     if ($relation == "entities"){
                    //         foreach ($fromReplicate->$relation as $realtedData){
                    //             $toReplicateRelation = $realtedData->replicate();
                    //             $toReplicateRelation->$relationId = $toReplicate->id;
                    //             if ($realtedData->entity_type=="Vehicle"){
                    //                 $fromVehicle = Vehicle::find($realtedData->entity_id);
                    //                 $vehicle = Vehicle::ActionId($recordToId)->EngineNo($fromVehicle->engineNo)->first();
                    //                 $toReplicateRelation->entity_id = $vehicle->id;
                    //                 $toReplicateRelation->save();
                    //             }
                    //             if ($realtedData->entity_type=="Member"){
                    //                 $fromVehicle = PolicyBeneficiary::find($realtedData->entity_id);
                    //                 $member = PolicyBeneficiary::Action($recordToId)->FirstName($fromVehicle->first_name)
                    //                     ->FirstName($fromVehicle->first_name)->MiddleName($fromVehicle->middle_name)->LastName($fromVehicle->last_name)
                    //                     ->first();
                    //                 $toReplicateRelation->entity_id = $member->id;
                    //                 $toReplicateRelation->save();
                    //             }
                    //             if ($realtedData->entity_type=="Device"){
                    //                 $fromVehicle = PolicyCellPhone::find($realtedData->entity_id);
                    //                 $device = PolicyCellPhone::Action($recordToId)->DeviceType($fromVehicle->device_type)
                    //                     ->Imei($fromVehicle->imei)->first();
                    //                 $toReplicateRelation->entity_id = $device->id;
                    //                 $toReplicateRelation->save();
                    //             }
                    //         }
                    //         continue;
                    //     }                                         
                    //     // Handle other relations and check uniqueness
                    //     if (is_a($fromReplicate->$relation, 'Illuminate\Database\Eloquent\Collection')) {
                    //         foreach ($fromReplicate->$relation as $relatedData) {
                    //             if($relation=='motor'){
                    //                 $exists = Motor::where('registration_no', $toReplicate->registration_no)
                    //                     ->where('policy_coverage_id', $toReplicate->id)
                    //                     ->exists();
                    //                 if (!$exists) {
                    //                     $toReplicateRelation = $relatedData->replicate();
                    //                     $toReplicateRelation->$relationId = $toReplicate->id;
                    //                     $toReplicateRelation->save();
                    //                    // continue; // Skip if already exists
                    //                 }

                    //             }
                    //             else if($relation=='coverageDetail'){
                    //                 $exists = PolicyCoverageDetail::where('coverage_id', $toReplicate->coverage_id)
                    //                     ->where('policy_coverage_id', $toReplicate->id)
                    //                     ->exists();
                    //                 if (!$exists) {
                    //                     //continue; // Skip if already exists
                    //                     $toReplicateRelation = $relatedData->replicate();
                    //                     $toReplicateRelation->$relationId =  $toReplicate->id;
                    //                     $toReplicateRelation->save(); 
                    //                 }

                    //             }else if($relation=='extentionDetail'){
                    //                 $exists = PolicyExtentionDetails::where('policy_coverage_id', $existingRecord1->id)
                    //                     ->exists();
                    //                 if (!$exists) {
                    //                    // continue; // Skip if already exists
                    //                    $toReplicateRelation = $relatedData->replicate();
                    //                    $toReplicateRelation->$relationId =  $existingRecord1->id;
                    //                    $toReplicateRelation->save();
                    //                 }

                    //             }
                    //             else if($relation=='note'){
                    //                 // $exists = PolicyCoverageNote::
                    //                 // where('policy_coverage_id', $existingRecord1->id)
                    //                 // ->exists();
                    //                 // if ($exists) {
                    //                 //     continue; // Skip if already exists
                    //                 // }

                    //                 $toReplicateRelation = $relatedData->replicate();
                    //                 $toReplicateRelation->$relationId =  $existingRecord1->id;
                    //                 $toReplicateRelation->save();
                    //             }else{
                    //                 if ($fromReplicate->$relation && $relation!='coverageDetail' && $relation!='motor' && $relation!='note' && $relation!='extentionDetail') {
                    //                     // $exists = $relation::where($relationId, $toReplicate->id)
                    //                     //     ->where('id', $fromReplicate->$relation->id)
                    //                     //     ->exists();

                    //                     // if (!$exists) {
                    //                         $toReplicateRelation = $fromReplicate->$relation->replicate();
                    //                         $toReplicateRelation->$relationId = $toReplicate->id;
                    //                         $toReplicateRelation->save();
                    //                     //}
                    //                 }
                    //             }
                    //         }
                    //     } else {
                    //         if ($fromReplicate->$relation) {
                    //             // $exists = $relation::where($relationId, $toReplicate->id)
                    //             //     ->where('id', $fromReplicate->$relation->id)
                    //             //     ->exists();

                    //             // if (!$exists) {
                    //                 $toReplicateRelation = $fromReplicate->$relation->replicate();
                    //                 $toReplicateRelation->$relationId = $toReplicate->id;
                    //                 $toReplicateRelation->save();
                    //            // }
                    //         }
                    //     }
                    // }
                }
            }

            return $insertedData;
        } catch (\Exception $e) {
            \Log::error($e->getMessage());
            return [];
        }
    }
    /**
     * $protectTargetEdits — APPEND-ONLY push. Passed straight through to
     * replicateRecordsIfMissing (see its docblock): rows the target lacks are
     * added, rows the TARGET ACTION itself edited are left alone, and inserted
     * rows carry no pro-rata from the source. Default false keeps every
     * existing caller unchanged.
     */
    public static function newPolicyActionReplace($newPolicy, $selectedAction, $protectTargetEdits = false)
    {

        $selectedActions = is_array($selectedAction) ? $selectedAction : [$selectedAction];

        if (!empty($selectedActions[0]) && ($newPolicy->id != null) && ($newPolicy->term_id != null)) {

            static::replicateRecordsIfMissing($selectedActions[0], $newPolicy->id, 'AlphaDirect\PolicyBeneficiary', 'action_id', ['term_id' => $newPolicy->term_id]);
            static::replicateRecordsIfMissing($selectedActions[0], $newPolicy->id, 'AlphaDirect\PolicyCellPhone', 'action_id', ['term_id' => $newPolicy->term_id], 'imei');
            static::replicateRecordsIfMissing($selectedActions[0], $newPolicy->id, 'AlphaDirect\Models\RiskAddress', 'action_id', ['term_id' => $newPolicy->term_id], 'address_name');
            static::replicateRecordsIfMissing($selectedActions[0], $newPolicy->id, 'AlphaDirect\Vehicle', 'action_id', ['term_id' => $newPolicy->term_id], 'vehiclePlate');
            static::replicateRecordsIfMissing($selectedActions[0], $newPolicy->id, 'AlphaDirect\Models\PolicyCoverage', 'action_id', [
                'term_id' => $newPolicy->term_id,
                'row_type' => 'OLD',
            ], 'coverage', [
                'extentionDetail' => 'policy_coverage_id',
                'coverageDetail' => 'policy_coverage_id',
                'specifedItems' => 'policy_coverage_id',
                'entities' => 'policy_coverage_id',
                'note' => 'policy_coverage_id',
                'motorInternal' => 'policy_coverage_id',
                'motorExteranal' => 'policy_coverage_id'
            ], null, $protectTargetEdits);
            static::replicateRecordsIfMissing($selectedActions[0], $newPolicy->id, 'AlphaDirect\Models\PolicyCoverage', 'action_id', [
                'term_id' => $newPolicy->term_id,
                'row_type' => 'OLD',
            ], 'coverage', [
                'motor' => 'policy_coverage_id',
            ], null, $protectTargetEdits);
             static::replicateRecordsIfMissing($selectedActions[0], $newPolicy->id, 'AlphaDirect\Models\PolicyCoverage', 'action_id', [
                'term_id' => $newPolicy->term_id,
                'row_type' => 'OLD',
            ], 'coverage', [
                // Fidelity Guarantee detail. Use `coverageDataFidelity` (FK
                // `policyCoverageID`), NOT `policyCoveragesData` whose default
                // FK `policy_coverage_id` doesn't exist on the table and throws
                // "Unknown column" — silently swallowed by the per-child guard,
                // so Fidelity never carried forward on cron/refresh renew.
                'coverageDataFidelity' => 'policyCoverageID',
            ], null, $protectTargetEdits);

            // Specialist (non-motor, non-COM/DOM) one-to-one coverage tables.
            // Cloned via a direct DB walk because they aren't wired into the
            // PolicyCoverage Eloquent relations the block above traverses,
            // and we don't want to add ten new relations to PolicyCoverage
            // just for the replication step. Idempotent: skips when the
            // target action already has a row for that policy_coverage.
            static::replicateSpecialistCoveragesIfMissing(
                (int) $selectedActions[0],
                (int) $newPolicy->id
            );

            // Carry per-vehicle + coverage-level motor notes forward. Must run
            // AFTER motors are replicated (second PolicyCoverage call above) so
            // the new motor rows exist to re-point onto. See method docblock.
            static::replicateMotorNotesIfMissing(
                (int) $selectedActions[0],
                (int) $newPolicy->id
            );
        }

    }

    /**
     * Propagate a PER-VEHICLE cancel from the source action into a downstream
     * action, whatever that action's transaction_type is.
     *
     * WHY THIS EXISTS. deleteMotorVehicle soft-deletes one `motor` row on the
     * endorse. Replication then refuses to carry that tombstone forward
     * (replicationSourceRowIsCancelled) — which is right for a batch being
     * CREATED, because cloning a dead row is pointless, but it means a batch
     * that ALREADY EXISTS keeps its own LIVE copy of the vehicle for ever. On a
     * monthly DomCom that is every RENEW after the endorse, each one still
     * charging for a vehicle that is off cover. Letting the source's
     * `deleted_at` overwrite the target (the old workaround) is not an option:
     * it wiped live sections off ISSUED actions, which is why it was removed.
     *
     * So the removal is propagated EXPLICITLY here, by business key rather
     * than by row id:
     *   - registrations soft-deleted on the source, MINUS any registration
     *     still live there (a cancel-then-reinstate in the same action is not
     *     a cancel, and must not delete anything downstream);
     *   - matched in the target on registration_no, which survives replication
     *     while motor.id does not — the same stable key the pro-rata baseline
     *     and specified-item matching already use;
     *   - the vehicle's motor_id-keyed children go with it (specified
     *     accessories carry premium; notes are display-only but must not
     *     outlive the vehicle), mirroring deleteMotorVehicle exactly.
     *
     * No premium is touched here. Callers reprice AFTER this runs — Refresh via
     * setRenewPremiumFromSource / finaliseTargetPremium, issue-time via
     * BackdatedEndorseRefresher::reconcileRenewTarget — and both derive a
     * RENEW's premium from live rows only, so dropping the row is what moves
     * the money and the invoice.
     *
     * Idempotent: a target that no longer has the vehicle live is a no-op, so
     * it is safe on every refresh pass and every propagation site.
     *
     * @return int number of target vehicles cancelled
     */
    public static function propagateCancelledMotorRows(int $sourceActionId, int $targetActionId): int
    {
        if ($sourceActionId <= 0 || $targetActionId <= 0 || $sourceActionId === $targetActionId) {
            return 0;
        }

        $plate = static fn ($v): string => strtoupper(trim((string) ($v ?? '')));

        // Source coverages: raw builder, so no SoftDeletes scope hides a
        // coverage that was itself cancelled — its vehicles are cancelled too
        // and must still propagate.
        $sourcePcIds = DB::table('policy_coverages')
            ->where('action_id', $sourceActionId)
            ->pluck('id')
            ->all();
        if (empty($sourcePcIds)) {
            return 0;
        }

        $sourceMotors = DB::table('motor')
            ->whereIn('policy_coverage_id', $sourcePcIds)
            ->get(['registration_no', 'deleted_at']);
        if ($sourceMotors->isEmpty()) {
            return 0;
        }

        $cancelled = [];
        $stillLive = [];
        foreach ($sourceMotors as $sm) {
            $reg = $plate($sm->registration_no);
            if ($reg === '') {
                continue;                       // no stable key — never guess
            }
            if (empty($sm->deleted_at)) {
                $stillLive[$reg] = true;
            } else {
                $cancelled[$reg] = true;
            }
        }

        // Reinstated in the same action ⇒ not a cancel.
        $toCancel = array_keys(array_diff_key($cancelled, $stillLive));
        if (empty($toCancel)) {
            return 0;
        }

        $targetPcIds = DB::table('policy_coverages')
            ->where('action_id', $targetActionId)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();
        if (empty($targetPcIds)) {
            return 0;
        }

        // The stamp column is guarded the same way the accessory tables below
        // are: a schema without it (an older deployment, or a slimmed test
        // schema) must still cancel, just without the reinstate guard.
        $hasStamp   = \Schema::hasColumn('motor', 'previousActionIdCov');
        $victimCols = ['id', 'policy_coverage_id', 'registration_no'];
        if ($hasStamp) {
            $victimCols[] = 'previousActionIdCov';
        }

        $victims = DB::table('motor')
            ->whereIn('policy_coverage_id', $targetPcIds)
            ->whereNull('deleted_at')
            ->get($victimCols);

        // CROSS-ACTION REINSTATE GUARD.
        //
        // array_diff_key($cancelled, $stillLive) above only excludes a plate
        // cancelled and reinstated within the SOURCE action. A plate cancelled
        // on the source and deliberately re-added by a LATER action is still a
        // cancel as far as the source is concerned, but the later row is live,
        // rated and paid for — the pro-rata engine charges it as a fresh add
        // (baseline 0) precisely because it treats a cross-action reinstate as
        // new cover. Deleting it here takes away cover the customer bought, and
        // the ENDORSE->RENEW billing block then reprices every later RENEW down
        // to match.
        //
        // previousActionIdCov is the "added / changed on THIS action" stamp every
        // rating loop gates on. Replication copies it verbatim, so a row that
        // reached the target by propagation still carries the id of the action
        // that really added it. A stamp naming an action chronologically AFTER
        // the source therefore means: something later than this cancel put the
        // vehicle back. Leave it alone.
        //
        // applyForwardWindow is strictly-after in (effective_from, id) order and
        // excludes the source itself, so a same-date lower-id action — which
        // cannot have reinstated anything the source later cancelled — does not
        // qualify. Errs toward KEEPING cover: an unmatched or zero stamp falls
        // through to the cancel, and a stale bill is visible and fixable in a
        // way that silently deleted cover is not.
        $reinstatedBy = [];
        $stamps = $hasStamp ? array_values(array_unique(array_filter(
            array_map(static fn ($v) => (int) ($v->previousActionIdCov ?? 0), $victims->all()),
            static fn ($id) => $id > 0
        ))) : [];
        if (!empty($stamps)) {
            $source = self::withTrashed()->find($sourceActionId);
            if ($source) {
                $reinstatedBy = self::withTrashed()
                    ->where('policy_id', $source->policy_id)
                    ->whereIn('id', $stamps)
                    ->tap(fn ($q) => self::applyForwardWindow($q, $source))
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            }
        }

        $removed = 0;
        foreach ($victims as $tm) {
            if (!in_array($plate($tm->registration_no), $toCancel, true)) {
                continue;
            }

            $stamp = (int) ($tm->previousActionIdCov ?? 0);
            if ($stamp > 0 && in_array($stamp, $reinstatedBy, true)) {
                Log::info('propagateCancelledMotorRows: cross-action reinstate kept', [
                    'source_action_id'  => $sourceActionId,
                    'target_action_id'  => $targetActionId,
                    'registration_no'   => $tm->registration_no,
                    'reinstated_by'     => $stamp,
                ]);
                continue;
            }

            DB::table('motor')->where('id', $tm->id)->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);

            // Per-vehicle accessories: real premium, keyed on the TARGET's own
            // motor_id, so they must be dropped in lockstep or the renewal
            // keeps billing the removed vehicle's accessories.
            if (\Schema::hasTable('policy_specified_items')
                && \Schema::hasColumn('policy_specified_items', 'deleted_at')) {
                DB::table('policy_specified_items')
                    ->where('policy_coverage_id', (int) $tm->policy_coverage_id)
                    ->where('motor_id', (int) $tm->id)
                    ->whereNull('deleted_at')
                    ->update(['deleted_at' => now(), 'updated_at' => now()]);
            }

            if (\Schema::hasTable('policy_coverage_notes')) {
                DB::table('policy_coverage_notes')
                    ->where('policy_coverage_id', (int) $tm->policy_coverage_id)
                    ->where('motor_id', (int) $tm->id)
                    ->whereNull('deleted_at')
                    ->update(['deleted_at' => now(), 'updated_at' => now()]);
            }

            $removed++;
        }

        if ($removed > 0) {
            Log::info('propagateCancelledMotorRows: cancelled vehicles propagated', [
                'source_action_id' => $sourceActionId,
                'target_action_id' => $targetActionId,
                'registrations'    => $toCancel,
                'rows_cancelled'   => $removed,
            ]);
        }

        return $removed;
    }

    /**
     * Carry motor section notes forward into a freshly-replicated action.
     *
     * policy_coverage_notes are NOT reliably carried by the `note` relation
     * the replicators traverse:
     *   - It is a hasOne, so replicateRecordsIfMissing()'s main loop (which
     *     only clones iterable hasMany relations) skips it entirely — on
     *     batch RENEW nothing came across at all.
     *   - Where it IS cloned (replicateRecords), backend scopes note() to
     *     coverageLevel() so per-vehicle rows (motor_id > 0, cover 22/27) are
     *     never copied, and the cron copy mis-assigns motor_id.
     * Either way the V2 Quote Sheet — which looks notes up by the NEW
     * motor_id — rendered nothing for renewed/endorsed motor sections.
     *
     * This copies both buckets explicitly, AFTER motors exist:
     *   - coverage-level note (motor_id NULL/0): one per coverage;
     *   - per-vehicle notes (motor_id > 0): matched to the new motor by
     *     registration_no, the same stable key specified items use.
     *
     * Idempotent: a coverage/vehicle that already has its note in the target
     * is left as-is, and a verbatim clone still carrying the stale source
     * motor_id is re-pointed rather than duplicated.
     *
     * $overwrite = true switches the "IfMissing" semantics to "sync": a target
     * note that already exists is UPDATED to match the source instead of being
     * left alone. Forward-propagation on issue/refresh needs this — a batch
     * generated before an endorse note was edited keeps the stale text, so
     * insert-if-missing alone never refreshes it. New-batch creation keeps the
     * default (false) so a manual per-batch note is never clobbered.
     */
    public static function replicateMotorNotesIfMissing(int $sourceActionId, int $targetActionId, bool $overwrite = false): void
    {
        if ($sourceActionId <= 0 || $targetActionId <= 0 || $sourceActionId === $targetActionId) {
            return;
        }

        $pcKey = static function ($row): string {
            $name = strtolower(trim((string) ($row->risk_address_name ?? '')));
            return (int) $row->coverage_id . '|' . $name;
        };

        $sourceCoverages = DB::table('policy_coverages as pc')
            ->leftJoin('risk_address as ra', 'pc.risk_address_id', '=', 'ra.id')
            ->where('pc.action_id', $sourceActionId)
            ->whereNull('pc.deleted_at')
            ->get(['pc.id', 'pc.coverage_id', 'ra.address_name as risk_address_name']);
        if ($sourceCoverages->isEmpty()) {
            return;
        }

        $targetCoverages = DB::table('policy_coverages as pc')
            ->leftJoin('risk_address as ra', 'pc.risk_address_id', '=', 'ra.id')
            ->where('pc.action_id', $targetActionId)
            ->whereNull('pc.deleted_at')
            ->get(['pc.id', 'pc.coverage_id', 'ra.address_name as risk_address_name']);
        if ($targetCoverages->isEmpty()) {
            return;
        }

        $targetByKey = [];
        foreach ($targetCoverages as $tpc) {
            $targetByKey[$pcKey($tpc)] = (int) $tpc->id;
        }

        foreach ($sourceCoverages as $spc) {
            $targetPcId = $targetByKey[$pcKey($spc)] ?? null;
            if (!$targetPcId) {
                continue;
            }

            // ── Coverage-level note (motor_id NULL/0) ──────────────────────
            $coverageHasNote = PolicyCoverageNote::where('policy_coverage_id', $targetPcId)
                ->coverageLevel()
                ->exists();
            if (!$coverageHasNote) {
                $srcCovNote = PolicyCoverageNote::where('policy_coverage_id', $spc->id)
                    ->coverageLevel()
                    ->latest('id')
                    ->first();
                if ($srcCovNote) {
                    $clone = $srcCovNote->replicate();
                    $clone->policy_coverage_id = $targetPcId;
                    $clone->save();
                }
            } elseif ($overwrite) {
                // Target already has a coverage-level note — overwrite its text
                // with the source's edited note so a back-dated endorse change
                // shows on the downstream RENEW / ANNIVERSARY-RENEW batch.
                $srcCovNote = PolicyCoverageNote::where('policy_coverage_id', $spc->id)
                    ->coverageLevel()
                    ->latest('id')
                    ->first();
                if ($srcCovNote) {
                    $tgtCovNote = PolicyCoverageNote::where('policy_coverage_id', $targetPcId)
                        ->coverageLevel()
                        ->latest('id')
                        ->first();
                    if ($tgtCovNote) {
                        $dirty = false;
                        if ((string) ($tgtCovNote->note ?? '') !== (string) ($srcCovNote->note ?? '')) {
                            $tgtCovNote->note = $srcCovNote->note;
                            $dirty = true;
                        }
                        if (\Schema::hasColumn('policy_coverage_notes', 'benefits_note')
                            && (string) ($tgtCovNote->benefits_note ?? '') !== (string) ($srcCovNote->benefits_note ?? '')) {
                            $tgtCovNote->benefits_note = $srcCovNote->benefits_note;
                            $dirty = true;
                        }
                        if ($dirty) {
                            $tgtCovNote->save();
                        }
                    }
                }
            }

            // ── Per-vehicle notes (motor_id > 0) ───────────────────────────
            // Target motors keyed by registration (incl. soft-deleted so a
            // cancelled vehicle's note still maps onto its row).
            $targetMotorByReg = [];
            foreach (Motor::where('policy_coverage_id', $targetPcId)->get(['id', 'registration_no']) as $tm) {
                $reg = trim((string) ($tm->registration_no ?? ''));
                if ($reg !== '') {
                    $targetMotorByReg[$reg] = (int) $tm->id;
                }
            }
            if (empty($targetMotorByReg)) {
                continue;
            }

            $sourceNotes = PolicyCoverageNote::where('policy_coverage_id', $spc->id)
                ->where('motor_id', '>', 0)
                ->get();

            foreach ($sourceNotes as $sn) {
                $srcMotor = Motor::find($sn->motor_id);
                $reg = $srcMotor ? trim((string) ($srcMotor->registration_no ?? '')) : '';
                if ($reg === '' || !isset($targetMotorByReg[$reg])) {
                    continue;
                }
                $tgtMotorId = $targetMotorByReg[$reg];

                // Already carried forward for this vehicle?
                $existing = PolicyCoverageNote::where('policy_coverage_id', $targetPcId)
                    ->where('motor_id', $tgtMotorId)
                    ->first();
                if ($existing) {
                    // Sync mode (issue/refresh): overwrite the stale text so an
                    // edited endorse note reflects downstream. New-batch mode
                    // (default) leaves an already-carried note untouched.
                    if ($overwrite) {
                        $dirty = false;
                        if ((string) ($existing->note ?? '') !== (string) ($sn->note ?? '')) {
                            $existing->note = $sn->note;
                            $dirty = true;
                        }
                        if (\Schema::hasColumn('policy_coverage_notes', 'benefits_note')
                            && (string) ($existing->benefits_note ?? '') !== (string) ($sn->benefits_note ?? '')) {
                            $existing->benefits_note = $sn->benefits_note;
                            $dirty = true;
                        }
                        if ($dirty) {
                            $existing->save();
                        }
                    }
                    continue;
                }

                // A verbatim clone carrying the stale source motor_id — re-point
                // it instead of inserting a duplicate.
                $stale = PolicyCoverageNote::where('policy_coverage_id', $targetPcId)
                    ->where('motor_id', $sn->motor_id)
                    ->first();
                if ($stale) {
                    $stale->motor_id = $tgtMotorId;
                    $stale->save();
                    continue;
                }

                $clone = $sn->replicate();
                $clone->policy_coverage_id = $targetPcId;
                $clone->motor_id = $tgtMotorId;
                $clone->save();
            }
        }
    }

    /**
     * Clone every specialist coverage row (car/par/ear/travel/med-mal/
     * machinery/PI/marine-D&O/marine-cargo-once-off/marine-cargo-open)
     * from $sourceActionId into $targetActionId. Matching is by the
     * target action's policy_coverage_id that lines up with the source
     * action's coverage_id + risk_address — the same key the rest of
     * newPolicyActionReplace already uses. Skips silently when:
     *   - the table doesn't exist on this env
     *   - the source row isn't present
     *   - the target already has its own row for that pc (idempotent)
     *
     * Motor / COM-DOM tables are intentionally NOT touched here.
     */
    public static function replicateSpecialistCoveragesIfMissing(int $sourceActionId, int $targetActionId, ?array $onlyCoverageIds = null): void
    {
        $tables = \AlphaDirect\Services\SpecialistEndorse\SpecialistCoverageRegistry::tableNames();

        // Match source pc → target pc by (coverage_id + risk-address NAME),
        // NOT by risk_address_id: newPolicyAction()/newPolicyActionReplace()
        // give each action its own risk_address rows and re-point
        // policy_coverages.risk_address_id to the new id (matched by name).
        // Keying on the raw id therefore misses every coverage that carries
        // a real risk address — the specialist row never clones and the
        // endorse opens with an empty schedule. Name-based matching mirrors
        // how replicateRecordsIfMissing() already pairs coverages
        // (see ~line 350) and is robust whether risk_address_id is null or
        // remapped. `pcKey()` collapses the null-address case to a stable
        // "coverage_id|" so single-risk specialist products still match.
        $pcKey = static function ($row): string {
            $name = strtolower(trim((string) ($row->risk_address_name ?? '')));
            return (int) $row->coverage_id . '|' . $name;
        };

        $sourceCoverages = DB::table('policy_coverages as pc')
            ->leftJoin('risk_address as ra', 'pc.risk_address_id', '=', 'ra.id')
            ->where('pc.action_id', $sourceActionId)
            ->whereNull('pc.deleted_at')
            // Coverage-selective FORWARD: only carry specialist rows for the
            // requested coverage_ids when a filter is supplied (default = all).
            ->when(!empty($onlyCoverageIds), fn ($q) => $q->whereIn('pc.coverage_id', $onlyCoverageIds))
            ->get(['pc.id', 'pc.coverage_id', 'pc.risk_address_id', 'pc.policy_id', 'ra.address_name as risk_address_name']);
        if ($sourceCoverages->isEmpty()) return;

        $policyId = (int) ($sourceCoverages->first()->policy_id ?? 0);

        $targetCoverages = DB::table('policy_coverages as pc')
            ->leftJoin('risk_address as ra', 'pc.risk_address_id', '=', 'ra.id')
            ->where('pc.action_id', $targetActionId)
            ->whereNull('pc.deleted_at')
            ->get(['pc.id', 'pc.coverage_id', 'pc.risk_address_id', 'ra.address_name as risk_address_name']);
        if ($targetCoverages->isEmpty()) return;

        $targetByKey = [];
        foreach ($targetCoverages as $tpc) {
            $targetByKey[$pcKey($tpc)] = (int) $tpc->id;
        }

        foreach ($tables as $table) {
            if (!\Schema::hasTable($table)) continue;
            $cols = \Schema::getColumnListing($table);

            foreach ($sourceCoverages as $spc) {
                $targetPcId = $targetByKey[$pcKey($spc)] ?? null;
                if (!$targetPcId) continue;

                // Source rows scoped to source pc. Most specialist tables are
                // 1:1 with pc, but marine cargo (open / once-off) can carry
                // MULTIPLE declaration rows per coverage — clone them ALL so the
                // endorse/cancel carries the full schedule, not just the first.
                $srcRows = DB::table($table)
                    ->where('policy_coverage_id', $spc->id)
                    ->when(in_array('deleted_at', $cols, true), function ($q) {
                        $q->whereNull('deleted_at');
                    })
                    ->get();
                if ($srcRows->isEmpty()) continue;

                // Idempotency — if the target pc already carries its own row(s),
                // the set was replicated already; skip to avoid extra copies.
                //
                // Rows SOFT-DELETED on the target count as carried: they were
                // cancelled on that batch by the operator. Filtering them out
                // made the schedule look "missing" and cloned a fresh LIVE copy
                // back in on every re-run (Refresh "fill missing", forward
                // propagation, batch renew), reversing the cancellation. Leave
                // them cancelled.
                $exists = DB::table($table)
                    ->where('policy_coverage_id', $targetPcId)
                    ->exists();
                if ($exists) continue;

                foreach ($srcRows as $srcRow) {
                    $clone = (array) $srcRow;
                    unset($clone['id']);
                    $clone['policy_coverage_id'] = $targetPcId;
                    if (in_array('action_id', $cols, true)) {
                        $clone['action_id'] = $targetActionId;
                    }
                    if (in_array('term_id', $cols, true) && property_exists($srcRow, 'term_id')) {
                        // term stays the same across endorsements within a term;
                        // copy source value as-is.
                        $clone['term_id'] = $srcRow->term_id;
                    }
                    if (in_array('policy_id', $cols, true) && $policyId > 0) {
                        $clone['policy_id'] = $policyId;
                    }
                    // Endorsement bookkeeping — mark this row as "replicated by
                    // endorse" so the wizard's universal-edit protection (used
                    // by BackdatedEndorseRefresher) can tell untouched rows
                    // apart from operator edits later. Pro-rata stays 0 here;
                    // calculatePremiumEndorse fills it after the wizard saves.
                    if (in_array('previousActionIdCov', $cols, true)) {
                        $clone['previousActionIdCov'] = $sourceActionId;
                    }
                    if (in_array('endors_flag', $cols, true)) {
                        $clone['endors_flag'] = 0;
                    }
                    if (in_array('pro_rate_premium', $cols, true)) {
                        $clone['pro_rate_premium'] = 0;
                    }
                    if (in_array('created_at', $cols, true)) {
                        $clone['created_at'] = now();
                    }
                    if (in_array('updated_at', $cols, true)) {
                        $clone['updated_at'] = now();
                    }
                    if (in_array('deleted_at', $cols, true)) {
                        $clone['deleted_at'] = null;
                    }

                    DB::table($table)->insert($clone);
                }
            }
        }
    }

    /**
     * Dedicated action replicator for SPECIALIST products (16-22) only.
     *
     * Kept SEPARATE from the DOM/COM newPolicyActionReplace() on purpose so
     * the specialist anniversary path (cron + FE "DOM/COM Batch Renew") never
     * depends on the shared motor renewal replicator. Makes a verbatim replica
     * of $selectedAction into $newPolicy — every table that carries the
     * schedule — so a specialist ANNIVERSARY-RENEW opens with the SAME data as
     * the NEWBUSINESS / last ISSUED action it was cloned from:
     *
     *   - beneficiaries, cell phones, risk addresses, vehicles
     *   - policy_coverages + children (detail / extensions / specified items /
     *     entities / notes / motor internal+external / fidelity data)
     *   - motor rows + per-vehicle & coverage-level motor notes
     *   - the ten specialist one-to-one coverage tables via
     *     replicateSpecialistCoveragesIfMissing()
     *
     * Idempotent end-to-end (uses the *IfMissing replicators), so a re-run
     * never duplicates rows. Twin of the cron-app method of the same name.
     */
    public static function newPolicyActionReplaceSpecialist($newPolicy, $selectedAction)
    {
        $selectedActions = is_array($selectedAction) ? $selectedAction : [$selectedAction];

        if (!empty($selectedActions[0]) && ($newPolicy->id != null) && ($newPolicy->term_id != null)) {

            static::replicateRecordsIfMissing($selectedActions[0], $newPolicy->id, 'AlphaDirect\PolicyBeneficiary', 'action_id', ['term_id' => $newPolicy->term_id]);
            static::replicateRecordsIfMissing($selectedActions[0], $newPolicy->id, 'AlphaDirect\PolicyCellPhone', 'action_id', ['term_id' => $newPolicy->term_id], 'imei');
            static::replicateRecordsIfMissing($selectedActions[0], $newPolicy->id, 'AlphaDirect\Models\RiskAddress', 'action_id', ['term_id' => $newPolicy->term_id], 'address_name');
            static::replicateRecordsIfMissing($selectedActions[0], $newPolicy->id, 'AlphaDirect\Vehicle', 'action_id', ['term_id' => $newPolicy->term_id], 'vehiclePlate');
            static::replicateRecordsIfMissing($selectedActions[0], $newPolicy->id, 'AlphaDirect\Models\PolicyCoverage', 'action_id', [
                'term_id' => $newPolicy->term_id,
                'row_type' => 'OLD',
            ], 'coverage', [
                'extentionDetail' => 'policy_coverage_id',
                'coverageDetail' => 'policy_coverage_id',
                'specifedItems' => 'policy_coverage_id',
                'entities' => 'policy_coverage_id',
                'note' => 'policy_coverage_id',
                'motorInternal' => 'policy_coverage_id',
                'motorExteranal' => 'policy_coverage_id'
            ]);
            static::replicateRecordsIfMissing($selectedActions[0], $newPolicy->id, 'AlphaDirect\Models\PolicyCoverage', 'action_id', [
                'term_id' => $newPolicy->term_id,
                'row_type' => 'OLD',
            ], 'coverage', [
                'motor' => 'policy_coverage_id',
            ]);
            static::replicateRecordsIfMissing($selectedActions[0], $newPolicy->id, 'AlphaDirect\Models\PolicyCoverage', 'action_id', [
                'term_id' => $newPolicy->term_id,
                'row_type' => 'OLD',
            ], 'coverage', [
                // Fidelity Guarantee detail — see note above; must use
                // `coverageDataFidelity` (correct FK `policyCoverageID`).
                'coverageDataFidelity' => 'policyCoverageID',
            ]);

            // The ten specialist one-to-one coverage tables — the part the
            // shared DOM/COM replicator never carries on its own.
            static::replicateSpecialistCoveragesIfMissing(
                (int) $selectedActions[0],
                (int) $newPolicy->id
            );

            // Carry per-vehicle + coverage-level motor notes forward. Must run
            // AFTER motors are replicated (call above) so the new motor rows
            // exist to re-point onto. See replicateMotorNotesIfMissing docblock.
            static::replicateMotorNotesIfMissing(
                (int) $selectedActions[0],
                (int) $newPolicy->id
            );
        }
    }
    public static function newPolicyAction($newPolicy, $selectedAction)
    {
        \DB::transaction(function () use ($newPolicy, $selectedAction) {
            if (($selectedAction != null) && ($newPolicy->id != null) && ($newPolicy->term_id != null)) {
                // current policy
                $currentPolicy = PolicyAction::find($selectedAction);

                // replicate different table's record for new policy action
                static::replicateRecords($currentPolicy->id, $newPolicy->id, 'AlphaDirect\PolicyBeneficiary', 'action_id', ['term_id' => $newPolicy->term_id]);
                static::replicateRecords($currentPolicy->id, $newPolicy->id, 'AlphaDirect\PolicyCellPhone', 'action_id', ['term_id' => $newPolicy->term_id]);
                static::replicateRecords($currentPolicy->id, $newPolicy->id, 'AlphaDirect\Models\RiskAddress', 'action_id', ['term_id' => $newPolicy->term_id]);
                static::replicateRecords($currentPolicy->id, $newPolicy->id, 'AlphaDirect\Vehicle', 'action_id', ['term_id' => $newPolicy->term_id]);
                static::replicateRecords($currentPolicy->id, $newPolicy->id, 'AlphaDirect\Models\PolicyCoverage', 'action_id', [
                    'term_id' => $newPolicy->term_id,
                    'row_type' => 'OLD',
                ], [
                    'motorInternal'        => 'policy_coverage_id',
                    'motorExteranal'       => 'policy_coverage_id',
                    'motor'                => 'policy_coverage_id',
                    'coverageDetail'       => 'policy_coverage_id',
                    'specifedItems'        => 'policy_coverage_id',
                    'entities'             => 'policy_coverage_id',
                    'extentionDetail'      => 'policy_coverage_id',
                    'note'                 => 'policy_coverage_id',
                    // `coverageDataFidelity` and `policyCoveragesData` both
                    // hasMany the same `policy_coverages_data` table — the
                    // first one specifies the correct legacy FK
                    // `policyCoverageID` (camelCase). The second uses
                    // Laravel's default `policy_coverage_id` which does not
                    // exist on that table, throwing
                    // "Column not found: 1054 Unknown column
                    //  policy_coverages_data.policy_coverage_id". So we
                    // replicate via `coverageDataFidelity` only.
                    'coverageDataFidelity' => 'policyCoverageID',
                ]);

                // Carry motor section notes forward (coverage-level +
                // per-vehicle). Runs after the PolicyCoverage replicate above
                // so the new motor rows exist to re-point onto.
                static::replicateMotorNotesIfMissing((int) $currentPolicy->id, (int) $newPolicy->id);
            }
        });
    }

    public static function newPolicyActionEndorse($newPolicy, $selectedAction)
    {
        \DB::transaction(function () use ($newPolicy, $selectedAction) {
            $selectedActions = is_array($selectedAction) ? $selectedAction : [$selectedAction];
            if (!empty($selectedActions) && ($newPolicy->id != null) && ($newPolicy->term_id != null)) {
                $currentPolicy = PolicyAction::find($selectedActions[0]);
                // replicate different table's record for new policy action
                static::replicateRecordsEndorse($currentPolicy->id, $newPolicy->id, 'AlphaDirect\Vehicle', 'action_id', ['term_id' => $newPolicy->term_id]);
                // static::replicateRecordsEndorse($currentPolicy->id,$newPolicy->id,'AlphaDirect\PolicyBeneficiary','action_id',['term_id' => $newPolicy->term_id]);
                // static::replicateRecordsEndorse($currentPolicy->id,$newPolicy->id,'AlphaDirect\PolicyCellPhone','action_id',['term_id' => $newPolicy->term_id]);
                // static::replicateRecordsEndorse($currentPolicy->id,$newPolicy->id,'AlphaDirect\Models\RiskAddress','action_id',['term_id' => $newPolicy->term_id]);
                // static::replicateRecordsEndorse($currentPolicy->id,$newPolicy->id,'AlphaDirect\Models\PolicyCoverage','action_id',[
                //     'term_id' => $newPolicy->term_id,
                //     'row_type' => 'OLD',
                // ],[
                //     'extentionDetail' => 'policy_coverage_id',
                //     'coverageDetail' => 'policy_coverage_id',
                //     'specifedItems' => 'policy_coverage_id',
                //     'entities' => 'policy_coverage_id',
                //     'note' => 'policy_coverage_id',
                //     'motorInternal' => 'policy_coverage_id',
                //     'motorExteranal' => 'policy_coverage_id',
                //     'motor' => 'policy_coverage_id',
                // ]);
            }
        });
    }
    /**
     * Pro-rata premium for ENDORSE actions (MVP — see endorsement_prorata_prompt.md).
     *
     * Formula:
     *   new_annual    = sum of all current-action line premiums (full-year)
     *   prev_annual   = previous ISSUED action's annual_premium baseline
     *   delta_annual  = new_annual - prev_annual
     *   pro_rata_amt  = delta_annual × ((effective_to - effective_from) / total_term_days)
     *
     * Stored on policy_actions:
     *   annual_premium → full-year value of current action (for next endorsement to baseline)
     *   premium        → pro-rata charge/refund for THIS endorsement period
     *                    (positive = charge customer, negative = refund)
     *
     * Out of scope (phase 2 per spec):
     *   - Per-line state machine (ADD/CHANGE/CANCEL detection per coverage/extension/misc)
     *   - Stacked / backdated re-stack
     *   - Minimum retained premium cap
     *   - Claim-block check
     *
     * Returns calculated values so the caller (or tests) can inspect without
     * having to re-query the row.
     */
    public static function calculatePremiumEndorse($actionId, $termId, $policy_id)
    {
        $policyAction = PolicyAction::find($actionId);
        if (!$policyAction) return null;

        // Sum all current-action line premiums — this gives the new annual baseline.
        $newAnnual = static::sumActionAnnualPremium($actionId, $termId, $policy_id);

        // Previous baseline: most recent ISSUED action with an annual_premium.
        // Falls back to its `premium` column for legacy rows that pre-date this
        // migration (we treat that legacy `premium` as the annual figure since
        // pre-fix the system never wrote pro-rata into `premium` anyway).
        $prevAction = PolicyAction::where('policy_id', $policy_id)
            ->where('id', '<', $actionId)
            ->where('status', 'ISSUED')
            ->orderByDesc('id')
            ->first();
        $prevAnnual = (float) ($prevAction->annual_premium ?? $prevAction->premium ?? 0);

        // Pro-rata factor — guard against zero-day terms / inverted dates.
        $effFrom = $policyAction->effective_from ? Carbon::parse($policyAction->effective_from) : null;
        $effTo   = $policyAction->effective_to   ? Carbon::parse($policyAction->effective_to)   : null;
        $unusedDays = ($effFrom && $effTo) ? max(0, $effTo->diffInDays($effFrom)) : 0;

        // Total term days — use the policy term, not just this action's window,
        // so an endorsement effective for the rest of a 365-day term divides by 365.
        $term = PolicyTerm::find($termId);
        $totalDays = ($term && $term->term_start_date && $term->term_end_date)
            ? max(1, Carbon::parse($term->term_end_date)->diffInDays(Carbon::parse($term->term_start_date)))
            : 365;

        $proRataFactor = $unusedDays / $totalDays;
        $deltaAnnual   = $newAnnual - $prevAnnual;
        $proRataAmount = round($deltaAnnual * $proRataFactor, 2);

        PolicyAction::where('id', $actionId)->update([
            'annual_premium' => round($newAnnual, 2),
            'premium'        => $proRataAmount,
        ]);

        // Per-line pro_rate_premium so V2 Quote / Policy Doc blades can show
        // pro-rata in column 4 (charge) and column 3 (refund / cancel).
        // Convention used by the templates:
        //   endors_flag = 1            → row was touched by this endorsement
        //   previousActionIdCov = $aid → links the row to this action
        //   pro_rate_premium    = annual_value × factor
        // Only write to rows scoped to this action so we don't overwrite
        // prior endorsements' calculated values.
        $coverageIds = PolicyCoverage::where('policy_id', $policy_id)
            ->whereNull('deleted_at')
            ->where('action_id', $actionId)
            ->where('term_id', $termId)
            ->pluck('id');
        if ($coverageIds->isNotEmpty()) {
            // policy_coverage_detail (sub-coverages)
            DB::table('policy_coverage_detail')
                ->whereIn('policy_coverage_id', $coverageIds)
                ->whereNull('deleted_at')
                ->update([
                    'pro_rate_premium'      => DB::raw('ROUND(calculated_value * ' . $proRataFactor . ', 2)'),
                    'endors_flag'           => 1,
                    'previousActionIdCov'   => $actionId,
                    'updated_at'            => now(),
                ]);
            // motor (per-vehicle)
            DB::table('motor')
                ->whereIn('policy_coverage_id', $coverageIds)
                ->whereNull('deleted_at')
                ->update([
                    'pro_rate_premium' => DB::raw('ROUND(calculated_value * ' . $proRataFactor . ', 2)'),
                    'endors_flag'      => 1,
                    'updated_at'       => now(),
                ]);
            // policy_extention_detail — the blade aggregates these via
            // SumIndexExtCalculated for col 4 too.
            if (\Schema::hasColumn('policy_extention_detail', 'pro_rate_premium')) {
                DB::table('policy_extention_detail')
                    ->whereIn('policy_coverage_id', $coverageIds)
                    ->whereNull('deleted_at')
                    ->where('type', 'Extention')
                    ->update([
                        'pro_rate_premium' => DB::raw('ROUND(extention_calculated_value * ' . $proRataFactor . ', 2)'),
                        'endors_flag'      => 1,
                        'updated_at'       => now(),
                    ]);
            }

            // Specialist (non-motor, non-COM/DOM) coverage rows — writes
            // per-row pro_rate_premium across the ten specialist tables.
            // Soft-deleted rows in this action get a NEGATIVE pro-rata
            // (cancel refund); active rows get the positive charge. This
            // path is no-op for envs where Phase-0 columns aren't applied
            // (the writer guards on pro_rate_premium column presence).
            \AlphaDirect\Services\SpecialistEndorse\SpecialistEndorseCalculator::writeProRata(
                (int) $actionId, (int) $termId, (int) $policy_id, (float) $proRataFactor
            );
        }

        return [
            'annual_premium'  => $newAnnual,
            'prev_annual'     => $prevAnnual,
            'delta_annual'    => $deltaAnnual,
            'unused_days'     => $unusedDays,
            'total_days'      => $totalDays,
            'pro_rata_factor' => $proRataFactor,
            'pro_rata_amount' => $proRataAmount,
        ];
    }

    /**
     * Helper — sum every line under an action into an annual premium total.
     * Mirrors the line aggregation calculatePremium does inline so both code
     * paths stay in sync. ENDORSE uses this to compute new_annual; non-ENDORSE
     * actions still use calculatePremium directly for backward compat.
     */
    public static function sumActionAnnualPremium($actionId, $termId, $policy_id): float
    {
        $coverages = PolicyCoverage::where('policy_id', $policy_id)
            ->whereNull('deleted_at')
            ->where('action_id', $actionId)
            ->where('term_id', $termId)
            ->pluck('id');
        $coverageIds = PolicyCoverage::where('policy_id', $policy_id)
            ->whereNull('deleted_at')
            ->where('action_id', $actionId)
            ->where('term_id', $termId)
            ->pluck('coverage_id');

        $sumSpecified  = PolicySpecifiedItem::whereIn('policy_coverage_id', $coverages)->whereNull('deleted_at')->sum('calculated_value');
        $sumDetail     = PolicyCoverageDetail::whereIn('policy_coverage_id', $coverages)->whereNull('deleted_at')->sum('calculated_value');
        $sumExt        = PolicyExtentionDetails::whereIn('policy_coverage_id', $coverages)
            ->whereIn('s_ParentCoverageID', $coverageIds)
            ->where('type', 'Extention')
            ->sum('extention_calculated_value');
        $sumPolicyData = PolicyCoveragesData::where('policy_id', $policy_id)->whereIn('policyCoverageID', $coverages)->sum('premium');

        $sumMotor = 0;
        if ($coverageIds->intersect([15, 16, 22, 27])->isNotEmpty()) {
            $sumMotor = (float) Motor::whereIn('policy_coverage_id', $coverages)
                ->whereNull('deleted_at')
                ->sum('calculated_value');
        }

        // Specialist (non-motor, non-COM/DOM) coverage rows carry their own
        // premium columns. Without this addition an endorse that only
        // changed (say) a CAR section1 premium would compute zero delta_annual
        // and miscalculate pro-rata. Soft-deleted specialist rows are
        // excluded — the cancel refund flows through writeProRata, not
        // through this sum.
        $sumSpecialist = \AlphaDirect\Services\SpecialistEndorse\SpecialistEndorseCalculator::sumAnnualForAction(
            (int) $actionId, (int) $termId, (int) $policy_id
        );

        return (float) ($sumDetail + $sumSpecified + $sumExt + $sumPolicyData + $sumMotor + $sumSpecialist);
    }

    public static function calculatePremium($actionId, $termId, $policy_id)
    {
        // Delegate to pro-rata calculator for any mid-term transaction type
        // that covers a partial period: ENDORSE, CANCEL, REINSTATE, EXTENSION-
        // COVER, ENDORSE-RENEW, REISSUE. NEWBUSINESS / RENEW / ANNIVERSARY-
        // RENEW issue a fresh full-term action so they keep the existing
        // annual-sum behaviour. The pro-rata math is frequency-agnostic — it
        // operates on annual_premium baselines, so monthly / quarterly /
        // half-yearly / annual policies all produce correct pro-rata
        // amounts. Premium frequency only affects how the resulting
        // amount is BILLED downstream (in policy_ledger), not how it's
        // calculated here.
        $action = PolicyAction::find($actionId);
        $proRataTypes = ['ENDORSE', 'CANCEL', 'REINSTATE', 'EXTENSION-COVER', 'ENDORSE-RENEW', 'REISSUE'];
        if ($action && in_array($action->transaction_type, $proRataTypes, true)) {
            static::calculatePremiumEndorse($actionId, $termId, $policy_id);
            return;
        }

        $policyAction = PolicyAction::where('id', $actionId)->first();
        $policyTerm = PolicyTerm::where('id', $termId)
            //->where('status','Active')
            ->first();

        $policyActionPrev = PolicyAction::where('policy_id', '=', $policyAction->policy_id)
            ->where('id', '<', $actionId)
            ->where('transaction_type', '=', 'RENEW')
            ->where('effective_to', '=', $policyAction->effective_to)
            ->orderBy('id', 'desc')->take(1)
            ->first();
        $diff_in_days_new_coverage = 0;
        $diff_in_days_main = 0;
        $policyActionCnt = PolicyAction::where('policy_id', $policyAction->policy_id)->count();
        if ($policyActionCnt > 1) {
            if (!isset($policyActionPrev) || $policyActionPrev == NULL) {
                $policyActionPrev = PolicyAction::where('policy_id', '=', $policyAction->policy_id)
                    ->where('id', '<', $actionId)
                    ->where('transaction_type', '=', 'NEWBUSINESS')
                    ->orderBy('id', 'desc')->take(1)
                    ->first();
            }
            // $diff_in_days_main = Carbon::parse($policyTerm->term_end_date)->diffInDays(Carbon::parse($this->policy->term_start_date));
            $datetime1 = strtotime($policyActionPrev->effective_from); // convert to timestamps
            $datetime2 = strtotime($policyActionPrev->effective_to); // convert to timestamps
            $diff_in_days_main = (int) (($datetime2 - $datetime1) / 86400);
        }

        $coverages = PolicyCoverage::where('policy_id', $policy_id)->whereNull('deleted_at')->where('action_id', $actionId)->where('term_id', $termId)->whereNull('deleted_at')->pluck('id');
        $coverages_coverage_id = PolicyCoverage::where('policy_id', $policy_id)->whereNull('deleted_at')->where('action_id', $actionId)->where('term_id', $termId)->whereNull('deleted_at')->pluck('coverage_id');
        $personal_motor_coverages = PolicyCoverage::where(function ($query) {
            $query->where('coverage_id', 15)
                ->orWhere('coverage_id', 16)
                ->orWhere('coverage_id', 27)
                ->orWhere('coverage_id', 22);
        })->where('policy_id', $policy_id)
            ->where('action_id', $actionId)
            ->where('term_id', $termId)
            ->first();
        $premiumNew = 0;
        $proratapremium = 0;
        $proratapremiumMain = 0;
        $internal_motor_sum_calculated_value = 0;
        $external_motor_sum_calculated_value = 0;
        $motor_sum_calculated_value = 0;
        $sum_specified_items = PolicySpecifiedItem::whereIn('policy_coverage_id', $coverages)->sum('calculated_value');
        $sum_calculated_value = PolicyCoverageDetail::whereIn('policy_coverage_id', $coverages)->whereNull('deleted_at')->sum('calculated_value');
        $sum_exts_calculated_value = PolicyExtentionDetails::whereIn('policy_coverage_id', $coverages)->whereIn('s_ParentCoverageID', $coverages_coverage_id)->where('type', 'Extention')->sum('extention_calculated_value');
        $policyCoveragesDataSum = PolicyCoveragesData::Where('policy_id', $policy_id)->sum('premium');
        // $this->action->premium = PolicyCoverageDetail::whereIn('policy_coverage_id', $coverages)->sum('calculated_value');
        if (!empty($personal_motor_coverages)) {
            $motor_sum_calculated_value = Motor::whereIn('policy_coverage_id', $coverages)->whereNull('deleted_at')->sum('calculated_value');
            $internal_motor_sum_calculated_value = MotorTradersInternal::whereIn('policy_coverage_id', $coverages)
                ->selectRaw('
                SUM(
                    loss_or_damage_calculated_value +
                    third_party_liability_calculated_value +
                    medical_benefits_calculated_value +
                    vehicle_lent_hire_calculated_value +
                    social_domestic_pleasure_calculated_value +
                    unauthoried_use_calculated_value +
                    windscreen_calculated_value +
                    contigent_liability_calculated_value +
                    wreckage_removal_calculated_value +
                    Loss_of_use_of_customer_calculated_value +
                    loss_of_key_calculated_value +
                    motor_cycle_motor_tricycle_calculated_value +
                    special_type_vehicle_calculated_value +
                    passanger_liability_respect_of_motor_calculated_value
                ) as grand_total
            ')
                ->first();

            $external_motor_sum_calculated_value = MotorTraders::whereIn('policy_coverage_id', $coverages)
                ->selectRaw('
                SUM(
                    loss_or_damage_calculated_value +
                    third_party_liability_calculated_value +
                    medical_benefits_calculated_value +
                    vehicle_lent_hire_calculated_value +
                    social_domestic_pleasure_calculated_value +
                    unauthoried_use_calculated_value +
                    windscreen_calculated_value +
                    contigent_liability_calculated_value +
                    wreckage_removal_calculated_value +
                    Loss_of_use_of_customer_calculated_value +
                    loss_of_key_calculated_value +
                    motor_cycle_motor_tricycle_calculated_value +
                    special_type_vehicle_calculated_value +
                    passanger_liability_respect_of_motor_calculated_value
                ) as grand_total
            ')
                ->first();
            if ($external_motor_sum_calculated_value) {
                $external_motor_sum_calculated_value = $external_motor_sum_calculated_value->grand_total ?? 0;
            }
            if ($internal_motor_sum_calculated_value) {
                $internal_motor_sum_calculated_value = $internal_motor_sum_calculated_value->grand_total ?? 0;
            }
            $premiumNew = $sum_calculated_value + $sum_specified_items + $sum_exts_calculated_value + $motor_sum_calculated_value + $internal_motor_sum_calculated_value + $external_motor_sum_calculated_value + $policyCoveragesDataSum;

        } else {
            $premiumNew = $sum_calculated_value + $sum_specified_items + $policyCoveragesDataSum + $sum_exts_calculated_value;

        }
        PolicyAction::where('id', $actionId)
            ->update([
                'premium' => $premiumNew
            ]);

    }
   public static function calculatePremiumRenew($actionId, $termId, $policy_id, bool $strict = false)
    {
        // RENEW premium MUST equal what the source action (ANNIVERSARY-RENEW /
        // RENEW / NEWBUSINESS) was ISSUED at — the batch/refresh replicates the
        // source coverage tree verbatim, so re-summing it should reproduce the
        // source figure exactly. The canonical issue-time recipe is
        // PolicyCreateController::recomputeActionTotals; the local inline sum
        // below DIVERGED from it for motor coverages (it added the motor pc's
        // policy_coverage_detail on top of the motor block — double-counting the
        // motor base — and summed only a 9-column subset for cover 22, omitted
        // Motor Traders, and over-scoped the extension/fidelity buckets). That
        // divergence is exactly why an anniversary issued at 13,575 renewed at
        // ~16k. Delegate to the canonical recipe so the RENEW is a true replica
        // of the source — this fixes the batch crons AND the Refresh Endorsement
        // button at once, since both funnel through here. (Same reflection
        // bridge BackdatedEndorseRefresher already uses for ENDORSE annual
        // recompute.) Falls back to the legacy inline sum only if the canonical
        // recipe is unavailable, so no environment breaks.
        //
        // ONLY a genuinely missing method falls back. invoke() used to sit
        // inside the same try as the ReflectionMethod construction under a
        // catch-all \Throwable, so ANY failure raised by the canonical recipe
        // itself -- a Carbon parse on a null effective_to, a missing column --
        // was logged as "unavailable" and silently routed to the legacy sum
        // BELOW, which double-counts motor (it adds the motor pc's
        // policy_coverage_detail on top of the motor block) and over-scopes
        // the Fidelity bucket. That is a wrong premium written silently. A
        // real failure now propagates: the Rate button reports it and the
        // crons log it per policy, instead of quietly invoicing an inflated
        // figure.
        $reflMethod = null;
        try {
            $controllerClass = '\\AlphaDirect\\Http\\Controllers\\Api\\V1\\PolicyCreateController';
            $reflMethod = new \ReflectionMethod($controllerClass, 'recomputeActionTotals');
            $reflMethod->setAccessible(true);
        } catch (\ReflectionException $e) {
            Log::warning('calculatePremiumRenew: canonical recomputeActionTotals unavailable, falling back to legacy inline sum', [
                'action_id' => $actionId,
                'error'     => $e->getMessage(),
            ]);
            if ($strict) {
                throw $e;
            }
        }

        if ($reflMethod) {
            try {
                $reflMethod->invoke(null, (int) $policy_id, (int) $actionId);
                return;
            } catch (\Throwable $e) {
                // The canonical recipe EXISTS but failed. Previously this was
                // caught by the same catch-all as the reflection lookup and
                // logged as "unavailable", so execution fell through to the
                // legacy sum below and wrote a figure that double-counts motor
                // and over-scopes the Fidelity bucket -- silently, as a
                // premium. Batch callers (the renew crons, whose per-policy
                // loop has no try/catch) keep the old lenient behaviour so one
                // bad policy cannot abort a whole run; an interactive caller
                // passes $strict and gets the real error instead of a wrong
                // number.
                Log::error('calculatePremiumRenew: canonical recomputeActionTotals FAILED, falling back to legacy inline sum', [
                    'action_id' => $actionId,
                    'policy_id' => $policy_id,
                    'strict'    => $strict,
                    'error'     => $e->getMessage(),
                ]);
                if ($strict) {
                    throw $e;
                }
            }
        }

        $policyAction = PolicyAction::where('id', $actionId)->first();
        $policyTerm = PolicyTerm::where('id', $termId)
            //->where('status','Active')
            ->first();

        $diff_in_days_new_coverage = Carbon::parse($policyAction->effective_to)->diffInDays(Carbon::parse($policyAction->effective_from));

        $coverages = PolicyCoverage::where('policy_id', $policy_id)->whereNull('deleted_at')->where('action_id', $actionId)->where('term_id', $termId)->whereNull('deleted_at')->where('status', '0')->pluck('id');
        $coverages_coverage_id = PolicyCoverage::where('policy_id', $policy_id)->whereNull('deleted_at')->where('action_id', $actionId)->where('term_id', $termId)->whereNull('deleted_at')->where('status', '0')->pluck('coverage_id');
        $personal_motor_coverages = PolicyCoverage::where(function ($query) {
            $query->where('coverage_id', 15)
                ->orWhere('coverage_id', 16)
                ->orWhere('coverage_id', 27)
                ->orWhere('coverage_id', 22);
        })->where('policy_id', $policy_id)
            ->where('action_id', $actionId)
            ->where('term_id', $termId)
            ->where('status', '0')
            ->first();
        $premiumNew = 0;
        $proratapremium = 0;
        $proratapremiumMain = 0;
        $internal_motor_sum_calculated_value = 0;
        $external_motor_sum_calculated_value = 0;
        $motor_sum_calculated_value = 0;
        $sum_specified_items = PolicySpecifiedItem::whereIn('policy_coverage_id', $coverages)->whereNull('deleted_at')->sum('calculated_value');
        $sum_calculated_value = PolicyCoverageDetail::join('tb_cvgpccoverages', 'tb_cvgpccoverages.id', '=', 'policy_coverage_detail.coverage_id')
            ->whereIn('policy_coverage_id', $coverages)->whereNull('policy_coverage_detail.deleted_at')->sum('calculated_value');
        $sum_exts_calculated_value = PolicyExtentionDetails::whereIn('policy_coverage_id', $coverages)->whereIn('s_ParentCoverageID', $coverages_coverage_id)->whereNull('deleted_at')->where('type', 'Extention')->sum('extention_calculated_value');
        $policyCoveragesDataSum = PolicyCoveragesData::Where('policy_id', $policy_id)->whereIn('policyCoverageID', $coverages)->sum('premium');
        // $this->action->premium = PolicyCoverageDetail::whereIn('policy_coverage_id', $coverages)->sum('calculated_value');
        if (!empty($personal_motor_coverages)) {

            // added by snehal on 28-10-25
            if ($coverages_coverage_id->contains(22)) {
                $motor_sum_calculated_value = Motor::whereIn('policy_coverage_id', $coverages)
                    ->whereNull('deleted_at')
                    ->selectRaw('
                                                        SUM( calculated_value +
                                                            premium_passenger_liability +
                                                            premium_unorthorised_passanger_liability +
                                                            premium_parking_facilities +
                                                            premium_com_windscreen +
                                                            premium_riot_strike +
                                                            premium_locks_keys +
                                                            premium_wreckage_removal +
                                                            premium_credit_shortfall +
                                                            premium_third_party_liability
                                                        ) as total_premium
                                                    ')
                    ->first();

            }
            if ($coverages_coverage_id->contains(27)) {
                $motor_sum_calculated_value = Motor::whereIn('policy_coverage_id', $coverages)
                    ->whereNull('deleted_at')
                    ->selectRaw('
                                            SUM(
                                            calculated_value +
                                                premium_passenger_liability +
                                                premium_unorthorised_passanger_liability +
                                                premium_parking_facilities +
                                                premium_com_windscreen +
                                                premium_riot_strike +
                                                premium_locks_keys +
                                                premium_wreckage_removal +
                                                premium_window_glass +
                                                premium_parts_accessories+
                                                premium_audio_accessories+
                                                premium_credit_shortfall+
                                                premium_car_hire_theft+
                                                premium_insured_driver+
                                                premium_insured_family+
                                                premium_medical_expenses+
                                                premium_specified_accessories+
                                                premium_third_party_liability
                                            ) as total_premium
                                        ')
                    ->first();
            }
            //old code commented by snehal on 28-10-25
            // $motor_sum_calculated_value = Motor::whereIn('policy_coverage_id', $coverages)->whereNull('deleted_at')->sum('calculated_value');

            $internal_motor_sum_calculated_value = MotorTradersInternal::whereIn('policy_coverage_id', $coverages)
                ->whereNull('deleted_at')
                ->selectRaw('
                        SUM(
                            loss_or_damage_calculated_value +
                            third_party_liability_calculated_value +
                            medical_benefits_calculated_value +
                            vehicle_lent_hire_calculated_value +
                            social_domestic_pleasure_calculated_value +
                            unauthoried_use_calculated_value +
                            windscreen_calculated_value +
                            contigent_liability_calculated_value +
                            wreckage_removal_calculated_value +
                            Loss_of_use_of_customer_calculated_value +
                            loss_of_key_calculated_value +
                            motor_cycle_motor_tricycle_calculated_value +
                            special_type_vehicle_calculated_value +
                            passanger_liability_respect_of_motor_calculated_value
                        ) as grand_total
                    ')
                ->first();

            $external_motor_sum_calculated_value = MotorTraders::whereIn('policy_coverage_id', $coverages)
                ->whereNull('deleted_at')
                ->selectRaw('
                    SUM(
                        loss_or_damage_calculated_value +
                        third_party_liability_calculated_value +
                        medical_benefits_calculated_value +
                        vehicle_lent_hire_calculated_value +
                        social_domestic_pleasure_calculated_value +
                        unauthoried_use_calculated_value +
                        windscreen_calculated_value +
                        contigent_liability_calculated_value +
                        wreckage_removal_calculated_value +
                        Loss_of_use_of_customer_calculated_value +
                        loss_of_key_calculated_value +
                        motor_cycle_motor_tricycle_calculated_value +
                        special_type_vehicle_calculated_value +
                        passanger_liability_respect_of_motor_calculated_value
                    ) as grand_total
                ')
                ->first();
            if ($external_motor_sum_calculated_value) {
                $external_motor_sum_calculated_value = $external_motor_sum_calculated_value->grand_total ?? 0;
            }
            if ($internal_motor_sum_calculated_value) {
                $internal_motor_sum_calculated_value = $internal_motor_sum_calculated_value->grand_total ?? 0;
            }
            if ($motor_sum_calculated_value) {
                $motor_sum_calculated_value = $motor_sum_calculated_value->total_premium ?? 0;
            }

            $premiumNew = $sum_calculated_value + $sum_specified_items + $sum_exts_calculated_value + $motor_sum_calculated_value + $internal_motor_sum_calculated_value + $external_motor_sum_calculated_value + $policyCoveragesDataSum;
            // dd($sum_calculated_value, $sum_specified_items ,$sum_exts_calculated_value , $motor_sum_calculated_value , $internal_motor_sum_calculated_value , $external_motor_sum_calculated_value , $policyCoveragesDataSum);
        } else {
            $premiumNew = $sum_calculated_value + $sum_specified_items + $policyCoveragesDataSum + $sum_exts_calculated_value;

        }

        PolicyAction::where('id', $actionId)
            ->update([
                'premium' => $premiumNew
            ]);
    }

    /**
     * Renewal premium for SPECIALIST products (16–22) — the Rate-button recipe.
     *
     * The legacy calculatePremiumRenew() inline sum reads only the standard
     * coverage buckets (coverage-detail / specified items / extensions /
     * fidelity) and the motor blocks — it NEVER touches the ten specialist
     * one-to-one tables (car/par/ear/travel/marine/…), so on a specialist
     * policy it returns 0 and zeros the action (and its invoice). This method
     * mirrors the Rate button (PolicyCreateController::calculatePremium): it
     * sums the standard buckets for the action's coverages AND the specialist
     * tables, with the same column rules — car_coverages is ADDITIVE
     * (Section 1 + 2 + 3, it has no grand total); every other table is
     * FIRST-AVAILABLE (a grand total with section fallbacks, or
     * annual_premium||premium). Writes the total to policy_actions.premium and
     * returns it. Self-contained (no SpecialistCoverageRegistry dependency) so
     * the cron-server app, which lacks that service, can use it too.
     */
    public static function calculatePremiumRenewSpecialist($actionId, $termId, $policy_id): float
    {
        // Coverages for THIS action (action-scoped, exactly like the Rate button).
        $coverages = PolicyCoverage::where('policy_id', $policy_id)
            ->where('action_id', $actionId)
            ->where('term_id', $termId)
            ->whereNull('deleted_at')
            ->where('status', '0')
            ->pluck('id');

        $premiumNew = 0.0;

        if ($coverages->isNotEmpty()) {
            $coverages_coverage_id = PolicyCoverage::whereIn('id', $coverages)->pluck('coverage_id');

            // Standard buckets — specialist policies normally carry none, but
            // include them so a mixed schedule still totals like the Rate button.
            $premiumNew += (float) PolicyCoverageDetail::join('tb_cvgpccoverages', 'tb_cvgpccoverages.id', '=', 'policy_coverage_detail.coverage_id')
                ->whereIn('policy_coverage_id', $coverages)
                ->whereNull('policy_coverage_detail.deleted_at')
                ->sum('calculated_value');
            $premiumNew += (float) PolicySpecifiedItem::whereIn('policy_coverage_id', $coverages)
                ->whereNull('deleted_at')
                ->sum('calculated_value');
            $premiumNew += (float) PolicyExtentionDetails::whereIn('policy_coverage_id', $coverages)
                ->whereIn('s_ParentCoverageID', $coverages_coverage_id)
                ->whereNull('deleted_at')
                ->where('type', 'Extention')
                ->sum('extention_calculated_value');
            $premiumNew += (float) PolicyCoveragesData::where('policy_id', $policy_id)
                ->whereIn('policyCoverageID', $coverages)
                ->sum('premium');

            // The ten specialist one-to-one tables — the part the legacy renew
            // sum misses. Columns + additive/first-available rules mirror
            // PolicyCreateController::calculatePremium and SpecialistCoverageRegistry.
            $specialistTables = [
                'ear_coverages'                       => ['total_premium', 'section1_total_premium', 'section3_total_premium'],
                'car_coverages'                       => ['section1_total_premium', 'section2_total_premium', 'section3_total_premium'],
                'par_coverages'                       => ['total_premium', 'section2_total_premium'],
                'professional_indemnity_coverages'    => ['premium'],
                'medical_malpractice_coverages'       => ['annual_premium', 'premium'],
                'marine_cargo_once_off_coverages'     => ['premium'],
                'marine_cargo_open_coverages'         => ['premium'],
                'marine_directors_officers_coverages' => ['premium', 'annual_premium'],
                'machinery_breakdown_coverages'       => ['premium'],
                'medical_evacuation_coverages'        => ['premium'],
                'commercial_crime_coverages'          => ['premium'],
                'environmental_liability_coverages'   => ['premium'],
                'bonds_coverages'                     => ['premium'],
                'travel_coverages'                    => ['total'],
            ];
            $additiveTables = ['car_coverages']; // sum ALL sections; others first-available

            foreach ($specialistTables as $table => $cols) {
                if (!\Schema::hasTable($table)) {
                    continue;
                }
                $tableCols = \Schema::getColumnListing($table);
                $q = DB::table($table);
                // Scope to THIS action's coverages — NOT the whole policy — so
                // prior endorsements' rows (one set per action) aren't summed in.
                if (in_array('policy_coverage_id', $tableCols, true)) {
                    $q->whereIn('policy_coverage_id', $coverages);
                } else {
                    $q->where('policy_id', $policy_id);
                    if (in_array('action_id', $tableCols, true)) {
                        $q->where('action_id', $actionId);
                    }
                }
                if (in_array('deleted_at', $tableCols, true)) {
                    $q->whereNull('deleted_at');
                }
                $sumAllColumns = in_array($table, $additiveTables, true);
                foreach ($q->get() as $row) {
                    foreach ($cols as $col) {
                        if (in_array($col, $tableCols, true) && !empty($row->{$col})) {
                            $premiumNew += (float) $row->{$col};
                            if (!$sumAllColumns) {
                                break; // alternatives — first available only
                            }
                        }
                    }
                }
            }
        }

        $premiumNew = round($premiumNew, 2);
        PolicyAction::where('id', $actionId)->update(['premium' => $premiumNew]);
        return $premiumNew;
    }

    /**
     * Transaction types whose `premium` is a FULL-PERIOD figure — the amount
     * for the whole period, which a renewal of that same period copies as-is.
     * ENDORSE is deliberately absent: its premium is a pro-rata DELTA.
     */
    public const FULL_PERIOD_TYPES = ['NEWBUSINESS', 'ANNIVERSARY-RENEW', 'RENEW', 'REINSTATE', 'REISSUE'];

    /**
     * PURE-REPLICA renewal premium.
     *
     * When the SOURCE action is a full-period transaction (NEWBUSINESS /
     * ANNIVERSARY-RENEW / RENEW / REINSTATE / REISSUE), its `premium` is the
     * per-period figure UW actually ISSUED — possibly a manual override that
     * the coverage tree alone no longer reproduces (e.g. COMG2024124982's
     * anniversary hand-set to 13,575 with a "credit shortfall" note). A renewal
     * is a verbatim continuation of that same period, so it MUST carry the
     * source figure EXACTLY — copy it, do NOT re-derive it. Re-summing the
     * replicated tree (calculatePremiumRenew) is what produced the wrong ~16k
     * RENEW: it can never match a manual override, and historically also
     * double-counted motor coverages.
     *
     * Only when the source is a mid-term ENDORSE — whose `premium` is a
     * pro-rata DELTA, not a full-period amount — do we fall back to recomputing
     * the full-period premium from the (refreshed) tree via the canonical
     * recipe (calculatePremiumRenew → recomputeActionTotals).
     *
     * Used by both DomCom auto-renew crons and the Refresh Endorsement button
     * so anniversary→renew is a pure replica in every path.
     */
    public static function setRenewPremiumFromSource($targetActionId, $sourceAction, bool $strict = false): void
    {
        if ($sourceAction && in_array($sourceAction->transaction_type, self::FULL_PERIOD_TYPES, true)) {
            $updates = ['premium' => $sourceAction->premium];
            if (\Schema::hasColumn('policy_actions', 'annual_premium')) {
                $updates['annual_premium'] = $sourceAction->annual_premium ?? $sourceAction->premium;
            }
            PolicyAction::where('id', $targetActionId)->update($updates);
            return;
        }

        // ENDORSE (or unknown) source — recompute full-period premium from the
        // replicated tree; the source's own premium is only a pro-rata delta.
        static::calculatePremiumRenew(
            $targetActionId,
            $sourceAction->term_id ?? null,
            $sourceAction->policy_id ?? null,
            $strict
        );
    }

    /**
     * The action a RENEW must take its premium FROM — the nearest PREVIOUS
     * period's ISSUED action.
     *
     * Ordered by effective_from DESC with id only as a same-date tiebreak. A
     * pure id-DESC pick grabs a later-CREATED but earlier-dated action (a
     * back-dated re-issue, an out-of-order RENEW batch), which is not this
     * renewal's real predecessor and would copy the wrong premium onto it.
     *
     * The FREQUENCY GATE applies to a full-period source only: a quarterly
     * renew must never inherit a monthly period's figure. Compared by
     * frequency where both actions carry one, else by day-span rounded to whole
     * months, because calendar quarters vary 90/91/92 days and months 28-31 —
     * a day-equality test wrongly rejects a valid same-frequency copy. When the
     * gate fails there is no verbatim source, so null is returned and the
     * caller must decide (recompute, or refuse).
     *
     * An ENDORSE source is returned as-is: setRenewPremiumFromSource handles it
     * by recomputing the full-period figure, since an endorse's own premium is
     * a pro-rata delta.
     *
     * Same rule the bulk remediation command applies
     * (FixRenewPremiumMismatch / renewals:fix-premium-mismatch), kept here so
     * the Rate button and that command cannot drift apart.
     */
    public static function renewPremiumSourceFor($renew)
    {
        $source = self::where('policy_id', $renew->policy_id)
            ->where('status', 'ISSUED')
            ->whereNull('deleted_at')
            ->where('id', '!=', $renew->id)
            ->where('effective_from', '<', $renew->effective_from)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        if (!$source) {
            return null;
        }

        $fullPeriod = in_array($source->transaction_type, self::FULL_PERIOD_TYPES, true);

        if ($fullPeriod && !self::sameFrequencyPeriod($renew, $source)) {
            return null;
        }

        return $source;
    }

    /**
     * Do these two actions cover the same LENGTH of period? Frequency stamp
     * first (it is the operator's own answer), day-span rounded to whole months
     * as the fallback for the cron-created rows that never stamp one.
     */
    public static function sameFrequencyPeriod($a, $b): bool
    {
        $af = (int) ($a->current_frequency_id ?? 0);
        $bf = (int) ($b->current_frequency_id ?? 0);
        if ($af > 0 && $bf > 0) {
            return $af === $bf;
        }

        return self::wholeMonthSpan($a->effective_from, $a->effective_to)
            === self::wholeMonthSpan($b->effective_from, $b->effective_to);
    }

    /** Day span rounded to whole months (absorbs 28-31 / 90-92 day variance). */
    private static function wholeMonthSpan($from, $to): int
    {
        if (!$from || !$to) {
            return 0;
        }
        $days = Carbon::parse($from)->diffInDays(Carbon::parse($to));
        return (int) round(($days + 1) / 30);
    }

    /**
     * Forward-propagation anchor for an ISSUED source action.
     *
     * OPERATOR RULE (2026-08-12): the anchor is the EFFECTIVE DATE, for EVERY
     * transaction type and EVERY frequency. What is on cover is decided by the
     * effective date the operator entered; transaction_date is only when the row
     * was saved or last (re-)issued and must never decide propagation.
     *
     * This replaces the earlier ENDORSE-only carve-out that anchored ANNUAL /
     * indeterminate-frequency endorsements on transaction_date. That carve-out
     * existed because an ANNUAL ENDORSE's effective_from is DEFAULTED to the
     * annual term start (see AddTransaction::mount), so a term-start anchor
     * reaches BACKWARD over batches that pre-date the change. But it broke every
     * genuinely BACK-DATED endorsement: issuePolicy re-stamps transaction_date on
     * each issue, so a change effective 30/03 but issued in August anchors on the
     * August date, excludes the 08/07 ANNIVERSARY-RENEW that plainly follows it,
     * and the refresh reports "Nothing to refresh" while the dropdown shows the
     * dates in the right order (policy 194804). Effective date is what the
     * operator sees, so effective date is what propagation now uses; where the
     * endorse's effective_from was left at the term start, the wider reach is
     * accepted deliberately.
     *
     * effective_from is also the right marker on its own merits for every other
     * type (RENEW, ANNIVERSARY-RENEW, NEWBUSINESS, REINSTATE), which store their
     * true period start there. transaction_date on these is merely when the batch
     * was generated or last issued, and that can fall on EITHER side of the next
     * batch's effective_from:
     *   - a cron-generated renewal is stamped ~90 days BEFORE its own period;
     *   - a renewal an operator EDITS / re-issues is stamped to "now", which
     *     can be AFTER a later batch that already exists.
     * In the latter case a transaction_date anchor jumps PAST the next batch
     * and silently excludes it — the "Refresh Endorsement" that reports
     * "0 downstream action(s) updated" right after an anniversary is edited.
     * effective_from is stable regardless of when the batch was touched.
     *
     * Returns a Y-m-d string suitable for an `effective_from` comparison.
     */
    public static function forwardPropagationAnchor($action): string
    {
        // Effective date only — every type, every frequency. transaction_date is
        // used solely as a legacy fallback when effective_from is absent.
        $anchor = !empty($action->effective_from)
            ? $action->effective_from
            : $action->transaction_date;

        return \Carbon\Carbon::parse($anchor)->toDateString();
    }

    // ── CHRONOLOGY: (effective_from, id) ─────────────────────────────────
    //
    // Operator rule (2026-08-06): an action's place in the timeline is its
    // effective_from, and when two actions share the same effective_from the
    // tie is broken by policy_actions.id — the higher id was transacted later,
    // so it holds the newer state. Every "what came before / what comes after"
    // question must answer with this SAME key, otherwise the answers disagree:
    //
    //   Real case (policy 120909, quarterly): three ENDORSEs and the
    //   ANNIVERSARY-RENEW all effective 01/01/2026. Propagation compared dates
    //   only (`>` excluded the siblings entirely, `>=` reached BACKWARD into
    //   the lower-id ones) and creation picked its source by id alone, so
    //   endorse #2/#3 never inherited endorse #1's vehicles and opened on a
    //   different — sometimes FUTURE — coverage state.
    //
    // Three helpers below; use them instead of hand-rolling date comparisons.

    /**
     * Constrain $query to the actions that come STRICTLY AFTER $source in
     * (effective_from, id) order — i.e. the forward-propagation targets.
     *
     * Same effective date + higher id  → target (the later same-day action).
     * Same effective date + lower id   → NOT a target (data never flows back).
     *
     * $anchor defaults to forwardPropagationAnchor($source), which is what
     * decides WHICH date the source sits on (a DomCom annual ENDORSE anchors on
     * transaction_date, everything else on effective_from). The id tie-break is
     * only meaningful when the anchor is the source's own effective_from, which
     * is exactly the same-date-sibling case.
     */
    public static function applyForwardWindow($query, $source, ?string $anchor = null)
    {
        $anchor   = $anchor ?? self::forwardPropagationAnchor($source);
        $sourceId = (int) (is_object($source) ? ($source->id ?? 0) : $source);

        return $query->where(function ($q) use ($anchor, $sourceId) {
            $q->where('effective_from', '>', $anchor)
              ->orWhere(function ($same) use ($anchor, $sourceId) {
                  $same->where('effective_from', '=', $anchor)
                       ->where('id', '>', $sourceId);
              });
        });
    }

    /**
     * Constrain $query to the actions on/before $until in (effective_from, id)
     * order — the inclusive upper bound of a From → To range. Without the id
     * tie-break a "To = the 2nd of three same-date endorsements" range would
     * silently swallow the 3rd one too.
     */
    public static function applyUpperBound($query, $until)
    {
        $date  = \Carbon\Carbon::parse($until->effective_from)->toDateString();
        $tilId = (int) $until->id;

        return $query->where(function ($q) use ($date, $tilId) {
            $q->where('effective_from', '<', $date)
              ->orWhere(function ($same) use ($date, $tilId) {
                  $same->where('effective_from', '=', $date)
                       ->where('id', '<=', $tilId);
              });
        });
    }

    /**
     * Order $query along the timeline: effective_from, then id. Callers that
     * replicate action-to-action MUST use this so same-date actions are walked
     * oldest → newest instead of in whatever order the storage engine returns.
     */
    public static function applyChronoOrder($query)
    {
        return $query->orderBy('effective_from')->orderBy('id');
    }

    /**
     * The action whose data is IN FORCE at $effectiveFrom — the replication
     * source for a new transaction effective on that date.
     *
     * "Latest by id" is wrong here: on a back-dated transaction that picks a
     * row effective AFTER the new date (a future RENEW batch), so the new
     * action opens on a coverage state that did not exist yet. Answer the
     * date-ordered question with the date-ordered key: the newest action
     * starting on/before $effectiveFrom, ties broken by the higher id.
     *
     * @param  array<string>  $statuses  status filter ([] = any)
     * @param  array<string>  $types     transaction_type filter ([] = any)
     */
    public static function inForceAt(int $policyId, $effectiveFrom, array $statuses = ['ISSUED'], int $excludeId = 0, array $types = [])
    {
        $date = \Carbon\Carbon::parse($effectiveFrom)->toDateString();

        return self::where('policy_id', $policyId)
            ->when(!empty($statuses), fn ($q) => $q->whereIn('status', $statuses))
            ->when(!empty($types), fn ($q) => $q->whereIn('transaction_type', $types))
            ->when($excludeId > 0, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where('effective_from', '<=', $date)
            ->whereNull('deleted_at')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    // ── FREQUENCY IS ACTION-WISE ──────────────────────────────────────────
    //
    // policy_actions.current_frequency_id is the frequency the operator had
    // selected ON THAT TRANSACTION. EditWizard stamps it on the action being
    // edited (and back-fills the pre-existing ones with the outgoing value),
    // AddTransaction stamps it on every new action. policies.premium_freq is
    // NOT the same thing — it only ever holds the most recent edit, so a
    // policy whose NEWBUSINESS was Quarterly and whose ANNIVERSARY-RENEW was
    // switched to Annual prints "Annual" against every historical action.
    //
    // Legacy rows written before the column existed — and the cron-created
    // RENEW/ANNIVERSARY-RENEW actions, which never stamp it — leave it NULL.
    // A NULL means "unchanged": callers resolve it by carrying the newest
    // earlier stamp forward, walking the same (effective_from, id) chronology
    // every other timeline question uses (see PolicyController::actions).

    /** Human label for a premium_freq / current_frequency_id code. */
    public static function frequencyLabel($freq): string
    {
        return match ((int) $freq) {
            1       => 'Monthly',
            2       => 'Three Installments',
            3       => 'Annual',
            4       => 'Semiannual',
            5       => 'Quarterly',
            6       => 'Manual Input',
            default => 'Monthly',
        };
    }

}
