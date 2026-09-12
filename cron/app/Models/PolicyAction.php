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

    protected $guarded = [];
    use SoftDeletes;

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
     * re-created on every renew even though the save-side guard
     * (PolicyCreateController::updateCoverage $enforceOwnExtensions) already
     * blocks it on manual saves.
     *
     * Mirrors the save-side guard and the policy:cleanup-foreign-extensions
     * command: a set, non-zero parent that differs from the target coverage is
     * foreign; no/zero parent (own-coverage or custom rows) is kept.
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
     * row that already exists on the target coverage. Mirrors the backend copy.
     *
     * `extentions_id` alone is enough for master-backed rows (one master id =
     * one extension = one type). It is NOT enough for custom / Excess rows,
     * which carry NO extentions_id: matching them on `extentions_id = NULL`
     * made every excess row on the coverage look like the same row, so the
     * de-dup block collapsed a coverage's whole excess list onto the first row
     * (the rest soft-deleted) and the value loop overwrote that single survivor
     * once per source row (last write wins).
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
     * TRUE when the target coverage already carries this business key but the
     * row is soft-deleted — i.e. it was REMOVED on the target batch and must
     * not be re-created by an additive replicate. Mirrors the backend copy.
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

    /**
     * Signature used to collapse EXACT-duplicate SOURCE child rows so a
     * pre-existing duplicate is not faithfully carried forward into every
     * later action (renew / anniversary / endorse). Mirrors the backend copy.
     *
     * id / timestamps / deleted_at / the parent FK are excluded because they
     * legitimately differ. For extension + sub-coverage rows the per-action
     * pro-rata stamps are excluded too: `previousActionIdCov`, `endors_flag`
     * and `pro_rate_premium` are re-stamped by every Rate / endorse run, so
     * two rows for the SAME master differing only in those stamps are
     * duplicates.
     */
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
     * another action. Twin of the backend copy — keep both in step.
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
     * live and carried it (and its tombstone) into the next action.
     * Keep in step with the backend copy of this model.
     */
    protected static function replicationSourceRowIsCancelled($row): bool
    {
        if (!is_object($row)) {
            return false;
        }

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

    public static function replicateRecordsIfMissing($recordFromId, $recordToId, $model, $columnName, $othercolumns = [], $uniqueKey = null, $withRelation = [])
    {

        try {
            $query = $model::query();
            if (!empty($withRelation)) {
                $query->with(array_keys($withRelation));
            }
            if ($uniqueKey == 'coverage') {

                $query = $model::where($columnName, $recordFromId)->with('riskAddress');
                $dataToBeReplicate = $query->get();

                // Fetch existing data for ToID.
                //
                // withTrashed(): a coverage the operator CANCELLED on the target
                // batch (soft-deleted) is still "already there". Without it the
                // SoftDeletes scope hid those rows, this additive replicate saw
                // the coverage as missing and inserted a SECOND, live copy of it
                // — with a full duplicate set of extension / sub-coverage /
                // specified-item children — every time the path re-ran. The
                // endors_flag / status / deleted_at sync below is kept for LIVE
                // matches only, so a target cancellation is never reversed.
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
                // V2 Quote and two identical lines in the Endorse Change Summary.
                // Twin of the backend method; keep both copies in step.
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

                            // 🔥 Check column differences and update
                            $updateData = [];

                            if ($existing['endors_flag'] != $record->endors_flag) {
                                $updateData['endors_flag'] = $record->endors_flag;
                            }

                            if ($existing['status'] != $record->status) {
                                $updateData['status'] = $record->status;
                            }

                            // deleted_at is deliberately NOT synced — copying the
                            // source's tombstone onto a matched target header
                            // tombstoned the whole section on the target, via a
                            // query-builder update that fires no model events and
                            // so never reached `audits`. Keep in step with the
                            // backend copy of this model.

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
                            // is not copied into the new action as well.
                            // Extension / sub-coverage rows ignore the
                            // per-action pro-rata stamps when comparing — see
                            // replicationChildSignature().
                            $seenChildSig = [];
                            foreach (self::dedupeReplicationChildren($relatedItems) as $related) {
                                // Foreign-extension guard: never carry an extension/
                                // excess row onto a coverage it doesn't belong to.
                                if (self::isForeignExtensionRow($related, $toReplicate->coverage_id)) {
                                    continue;
                                }
                                // A row CANCELLED on the source is not carried
                                // forward at all. See replicationSourceRowIsCancelled().
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
                            // syncs onto the target. See replicationSourceRowIsCancelled().
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
                            
                            if ($relatedModelName === 'PolicySpecifiedItem') {

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

                                // Twin of the backend guard: the per-vehicle
                                // item may exist but be SOFT-DELETED on the
                                // target batch (cancelled by the operator).
                                // The SoftDeletes scope hides it from the
                                // lookup above, so this branch cloned a fresh
                                // LIVE copy on every re-run and the cancelled
                                // item came back. Leave it cancelled.
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
                                    'endors_flag'
                                ];

                                // (rest of your update logic continues...)
                            }
                            }

                            // ------------------------------------------------------------------
                            // Fetch duplicates (same fields + same relation id)
                            // ------------------------------------------------------------------
                            $duplicates = $relatedModelClass::where($relationId, $policy_coverage_id_new)
                                ->where($compareData)
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
                          
                            if($relatedModelName !== 'PolicySpecifiedItem' || ($relatedModelName==='PolicySpecifiedItem' && !$compareData['motor_id'])){
                            $existingRecord = $relatedModelClass::where($relationId, $policy_coverage_id_new)
                                ->where($compareData)
                                ->first();
                            if (!$existingRecord) {
                                // The row may exist but be SOFT-DELETED on the
                                // target batch — i.e. it was removed there (by
                                // the operator, or by a clean-up of duplicates).
                                // The SoftDeletes scope hides it from the lookup
                                // above, so this branch used to clone a
                                // brand-new LIVE copy on every re-run of the
                                // additive replicate: the removed extension came
                                // back and the table grew a fresh row each time.
                                // Leave it removed instead (same rule as
                                // BackdatedEndorseRefresher EC-5).
                                $removedScope = function ($q) use ($relationId, $policy_coverage_id_new, $compareData) {
                                    $q->where($relationId, $policy_coverage_id_new)->where($compareData);
                                };
                                if (self::childRowRemovedInTarget($relatedModelClass, $removedScope)) {
                                    Log::info('replicateRecordsIfMissing: child row is soft-deleted on the target batch — not re-cloned', [
                                        'model'        => $relatedModelName,
                                        'to_coverage'  => $policy_coverage_id_new,
                                        'business_key' => $compareData,
                                    ]);
                                    continue;
                                }
                                $cloned = $related->replicate();
                                $cloned->$relationId = $policy_coverage_id_new;
                                $cloned->save();
                            } else {
                                $dataToUpdate = $related->toArray();

                                $excluded = [
                                    'policy_coverage_id',
                                    'id',
                                    'created_at',
                                    'updated_at',
                                    'previousActionIdCov',
                                    'pro_rate_premium',
                                    'endors_flag'
                                ];

                                // Unconditional now: whether a row is live is the
                                // TARGET action's own state, and RENEW /
                                // ANNIVERSARY-RENEW were never a valid exception.
                                // Cancelled source rows are skipped above instead.
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
            // $seenRelSig guard further down (and of the backend copy). This is
            // a blind copier: it faithfully reproduced a SOURCE action that
            // already carried two policy_coverages rows for the same
            // (coverage_id, risk address), so one pre-existing duplicate was
            // re-created on every renew / endorse and rendered as the same
            // section twice on the V2 Quote and twice in the Endorse Change
            // Summary. The uniqueness rule is addCoverage's own: one
            // (coverage, risk address) per action.
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
                    $riskAddress = RiskAddress::Action($recordToId)->AddressName($fromRiskAddress->address_name)->first();
                    $toReplicate->risk_address_id = $riskAddress->id;
                }
                if ($model == 'AlphaDirect\Vehicle') {
                    $fromRiskAddress = RiskAddress::find($fromReplicate->risk_id);
                    $riskAddress = RiskAddress::Action($recordToId)->AddressName($fromRiskAddress->address_name)->first();
                    $toReplicate->risk_id = $riskAddress->id;
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
                                $vehicle = Vehicle::ActionId($recordToId)->EngineNo($fromVehicle->engineNo)->first();
                                $toReplicateRelation->entity_id = $vehicle->id;
                                $toReplicateRelation->save();
                            }
                            if ($realtedData->entity_type == "Member") {
                                $fromVehicle = PolicyBeneficiary::find($realtedData->entity_id);
                                $member = PolicyBeneficiary::Action($recordToId)->FirstName($fromVehicle->first_name)
                                    ->FirstName($fromVehicle->first_name)->MiddleName($fromVehicle->middle_name)->LastName($fromVehicle->last_name)
                                    ->first();
                                $toReplicateRelation->entity_id = $member->id;
                                $toReplicateRelation->save();
                            }
                            if ($realtedData->entity_type == "Device") {
                                $fromVehicle = PolicyCellPhone::find($realtedData->entity_id);
                                $device = PolicyCellPhone::Action($recordToId)->DeviceType($fromVehicle->device_type)
                                    ->Imei($fromVehicle->imei)->first();
                                $toReplicateRelation->entity_id = $device->id;
                                $toReplicateRelation->save();
                            }
                        }
                        continue;
                    }
                    if (is_a($fromReplicate->$relation, 'Illuminate\Database\Eloquent\Collection')) {
                        Log::info("Replicating relation many: $relation");
                        // Skip exact-duplicate SOURCE rows so a duplicate from a
                        // historical double-replication is NOT copied forward
                        // into the new action. This is the ANNIVERSARY path
                        // (policy:renew-annual → newPolicyAction → here): without
                        // the guard every anniversary faithfully re-copied the
                        // duplicates, so the new term's quote rendered (and
                        // charged) the same extension / sub-coverage twice, and
                        // the duplicates compounded term after term. Signature
                        // excludes id / timestamps / deleted_at / the parent FK
                        // and — for extension + sub-coverage rows — the
                        // per-action pro-rata stamps, which otherwise made two
                        // copies of one extension look like distinct rows.
                        // Mirrors the backend copy.
                        $seenRelSig = [];
                        foreach (self::dedupeReplicationChildren($fromReplicate->$relation) as $realtedData) {
                            // Foreign-extension guard: don't replicate an extension/
                            // excess row onto a coverage it doesn't belong to.
                            if (self::isForeignExtensionRow($realtedData, $toReplicate->coverage_id)) {
                                continue;
                            }
                            $sig = self::replicationChildSignature($realtedData, $relationId);
                            if (isset($seenRelSig[$sig])) {
                                continue;
                            }
                            $seenRelSig[$sig] = true;

                            $toReplicateRelation = $realtedData->replicate();
                            $toReplicateRelation->$relationId = $toReplicate->id;
                            $toReplicateRelation->save();
                        }
                    } else {
                        if ($fromReplicate->$relation) {

                            $toReplicateRelation = $fromReplicate->$relation->replicate();
                            $toReplicateRelation->$relationId = $toReplicate->id;
                            $toReplicateRelation->save();
                        }
                    }

                }
                $originalMotors = Motor::where('policy_coverage_id', $toReplicate->id)->get();

                // Per-vehicle note re-pointing is now handled by
                // PolicyAction::replicateMotorNotesIfMissing() (called from
                // newPolicyAction / newPolicyActionReplace), which maps each
                // note onto its OWN new motor by registration_no. The previous
                // nested loop here stamped EVERY cloned note with the LAST
                // motor's id, collapsing multi-vehicle notes onto one vehicle
                // (and clobbering coverage-level notes), so it is removed.

                // Fetch cloned MISC under the new policy coverage
                $clonedMisc = PolicySpecifiedItem::where('policy_coverage_id', $toReplicate->id)->get();
                foreach ($clonedMisc as $misc) {
                    foreach ($originalMotors as $motor) {
                        $misc->motor_id = $motor->id; // update to the new motor ID
                        $misc->save();
                    }
                }
            }
            return $insertedData;
        } catch (\Exception $e) {
            // Handle or log the exception
            // Example: Log::error($e->getMessage());
            return [];
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
    public static function newPolicyActionReplace($newPolicy, $selectedAction)
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
                'motorExteranal' => 'policy_coverage_id',
                // Fidelity: use coverageDataFidelity (FK policyCoverageID). The
                // policyCoveragesData relation's default FK policy_coverage_id
                // doesn't exist on the table → throws, silently swallowed, so
                // Fidelity was never carried forward on cron renew.
                'coverageDataFidelity' => 'policyCoverageID'
            ]);
            static::replicateRecordsIfMissing($selectedActions[0], $newPolicy->id, 'AlphaDirect\Models\PolicyCoverage', 'action_id', [
                'term_id' => $newPolicy->term_id,
                'row_type' => 'OLD',
            ], 'coverage', [
                'motor' => 'policy_coverage_id',
            ]);

            // Carry per-vehicle + coverage-level motor notes forward. Must run
            // AFTER motors are replicated (call above) so the new motor rows
            // exist to re-point onto. See method docblock.
            static::replicateMotorNotesIfMissing(
                (int) $selectedActions[0],
                (int) $newPolicy->id
            );
        }

    }

    /**
     * Carry motor section notes forward into a freshly-replicated action.
     *
     * policy_coverage_notes are NOT reliably carried by the `note` relation
     * the replicators traverse:
     *   - It is a hasOne, so replicateRecordsIfMissing()'s main loop (which
     *     only clones iterable hasMany relations) skips it entirely — on
     *     batch RENEW nothing came across at all.
     *   - Where it IS cloned (replicateRecords), only a single row is copied
     *     and its motor_id was previously mis-stamped to the LAST motor.
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
     */
    public static function replicateMotorNotesIfMissing(int $sourceActionId, int $targetActionId): void
    {
        if ($sourceActionId <= 0 || $targetActionId <= 0 || $sourceActionId === $targetActionId) {
            return;
        }

        $pcKey = static function ($row): string {
            $name = strtolower(trim((string) ($row->risk_address_name ?? '')));
            return (int) $row->coverage_id . '|' . $name;
        };

        // Coverage-level rows store motor_id as 0 OR NULL (legacy stores 0).
        $coverageLevel = static function ($query) {
            $query->where(function ($q) {
                $q->whereNull('motor_id')->orWhere('motor_id', 0);
            });
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
                ->where($coverageLevel)
                ->exists();
            if (!$coverageHasNote) {
                $srcCovNote = PolicyCoverageNote::where('policy_coverage_id', $spc->id)
                    ->where($coverageLevel)
                    ->latest('id')
                    ->first();
                if ($srcCovNote) {
                    $clone = $srcCovNote->replicate();
                    $clone->policy_coverage_id = $targetPcId;
                    $clone->save();
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

                // Already carried forward for this vehicle? leave it.
                $exists = PolicyCoverageNote::where('policy_coverage_id', $targetPcId)
                    ->where('motor_id', $tgtMotorId)
                    ->exists();
                if ($exists) {
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
     * action's coverage_id + risk-address NAME — the same key the rest of
     * newPolicyActionReplace already uses. Skips silently when:
     *   - the table doesn't exist on this env
     *   - the source row isn't present
     *   - the target already has its own row for that pc (idempotent)
     *
     * Motor / COM-DOM tables are intentionally NOT touched here.
     *
     * Cron twin of the backend PolicyAction method of the same name. The
     * cron app has no SpecialistCoverageRegistry service, so the canonical
     * table list is inlined here; keep it in sync with
     * App\Services\SpecialistEndorse\SpecialistCoverageRegistry::TABLES.
     */
    public static function replicateSpecialistCoveragesIfMissing(int $sourceActionId, int $targetActionId): void
    {
        // Canonical specialist coverage tables (one row per policy_coverage,
        // except marine cargo open/once-off which can carry multiple rows).
        // The last four were added to the backend registry but never mirrored
        // here, so a renew on cron_server cloned an EMPTY schedule for those
        // products — the renewed quote opened with no coverage and no premium.
        $tables = [
            'car_coverages',
            'par_coverages',
            'ear_coverages',
            'travel_coverages',
            'medical_malpractice_coverages',
            'machinery_breakdown_coverages',
            'professional_indemnity_coverages',
            'marine_directors_officers_coverages',
            'marine_cargo_once_off_coverages',
            'marine_cargo_open_coverages',
            'medical_evacuation_coverages',
            'commercial_crime_coverages',
            'environmental_liability_coverages',
            'bonds_coverages',
        ];

        // Match source pc → target pc by (coverage_id + risk-address NAME),
        // NOT by risk_address_id: newPolicyActionReplace() gives each action
        // its own risk_address rows and re-points policy_coverages.risk_address_id
        // to the new id (matched by name). Keying on the raw id would miss every
        // coverage that carries a real risk address. Mirrors the backend copy.
        $pcKey = static function ($row): string {
            $name = strtolower(trim((string) ($row->risk_address_name ?? '')));
            return (int) $row->coverage_id . '|' . $name;
        };

        $sourceCoverages = DB::table('policy_coverages as pc')
            ->leftJoin('risk_address as ra', 'pc.risk_address_id', '=', 'ra.id')
            ->where('pc.action_id', $sourceActionId)
            ->whereNull('pc.deleted_at')
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
                // MULTIPLE declaration rows per coverage — clone them ALL.
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
                // Twin of the backend guard: rows SOFT-DELETED on the target
                // count as carried (cancelled there by the operator). Filtering
                // them out made the schedule look "missing" and cloned a fresh
                // LIVE copy back in on every re-run, reversing the cancellation.
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
                        $clone['term_id'] = $srcRow->term_id;
                    }
                    if (in_array('policy_id', $cols, true) && $policyId > 0) {
                        $clone['policy_id'] = $policyId;
                    }
                    // Endorse bookkeeping — stamp the source action so the
                    // endorse pro-rata engine can pair the carried row to its
                    // baseline. Pro-rata stays 0 here; the rating step fills it.
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
     * Kept SEPARATE from the DOM/COM newPolicyActionReplace() on purpose:
     * the specialist anniversary cron must NOT depend on the shared motor
     * renewal path. This makes a verbatim replica of $selectedAction into
     * $newPolicy — every table that carries the schedule — so a specialist
     * ANNIVERSARY-RENEW opens with the SAME data as the NEWBUSINESS / last
     * ISSUED action it was cloned from:
     *
     *   - beneficiaries, cell phones, risk addresses, vehicles
     *   - policy_coverages + their children (detail / extensions / specified
     *     items / entities / notes / motor internal+external / fidelity data)
     *   - motor rows + per-vehicle & coverage-level motor notes
     *   - the ten specialist one-to-one coverage tables
     *     (car/par/ear/travel/marine/…) via
     *     replicateSpecialistCoveragesIfMissing()
     *
     * Idempotent end-to-end (uses the same *IfMissing replicators), so a
     * re-run never duplicates rows.
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
                'motorExteranal' => 'policy_coverage_id',
                // Fidelity: use coverageDataFidelity (correct FK policyCoverageID);
                // policyCoveragesData's default FK doesn't exist → silently dropped.
                'coverageDataFidelity' => 'policyCoverageID',
            ]);
            static::replicateRecordsIfMissing($selectedActions[0], $newPolicy->id, 'AlphaDirect\Models\PolicyCoverage', 'action_id', [
                'term_id' => $newPolicy->term_id,
                'row_type' => 'OLD',
            ], 'coverage', [
                'motor' => 'policy_coverage_id',
            ]);

            // The ten specialist one-to-one coverage tables — the part the
            // shared DOM/COM replicator never carries. Without this the
            // anniversary quote opens with an empty specialist schedule.
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
                    'motorInternal' => 'policy_coverage_id',
                    'motorExteranal' => 'policy_coverage_id',
                    'motor' => 'policy_coverage_id',
                    'coverageDetail' => 'policy_coverage_id',
                    'specifedItems' => 'policy_coverage_id',
                    'entities' => 'policy_coverage_id',
                    'extentionDetail' => 'policy_coverage_id',
                    'note' => 'policy_coverage_id',
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
    public static function calculatePremium($actionId, $termId, $policy_id)
    {
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
    /**
     * Set a freshly-replicated RENEW action's premium from its SOURCE action.
     *
     * A RENEW is a pure replica of a full-period source (NEWBUSINESS /
     * ANNIVERSARY-RENEW / RENEW / REINSTATE / REISSUE), so its premium must be
     * carried VERBATIM — including any manual UW override the coverage tree
     * cannot re-derive. Re-summing the replicated tree (calculatePremiumRenew)
     * diverges from the issued figure and, when the tree replication truncates,
     * collapses to a fraction of the real premium. Only an ENDORSE source (whose
     * premium is a pro-rata delta, not a full-period figure) is recomputed.
     *
     * Mirrors backend PolicyAction::setRenewPremiumFromSource so the cron server
     * and the app produce identical RENEW premiums.
     */
    public static function setRenewPremiumFromSource($targetActionId, $sourceAction): void
    {
        $fullPeriodTypes = ['NEWBUSINESS', 'ANNIVERSARY-RENEW', 'RENEW', 'REINSTATE', 'REISSUE'];

        if ($sourceAction && in_array($sourceAction->transaction_type, $fullPeriodTypes, true)) {
            $updates = ['premium' => $sourceAction->premium];
            if (\Schema::hasColumn('policy_actions', 'annual_premium')) {
                $updates['annual_premium'] = $sourceAction->annual_premium ?? $sourceAction->premium;
            }
            PolicyAction::where('id', $targetActionId)->update($updates);
            return;
        }

        // ENDORSE (or unknown) source — recompute from the replicated tree; the
        // source's own premium is only a pro-rata delta.
        static::calculatePremiumRenew(
            $targetActionId,
            $sourceAction->term_id ?? null,
            $sourceAction->policy_id ?? null
        );
    }

    public static function calculatePremiumRenew($actionId, $termId, $policy_id)
    {
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
            ->whereIn('policy_coverage_id', $coverages)->whereNull('deleted_at')->sum('calculated_value');
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
     * calculatePremiumRenew() above reads only the standard coverage buckets
     * (coverage-detail / specified items / extensions / fidelity) and the motor
     * blocks — it NEVER touches the ten specialist one-to-one tables
     * (car/par/ear/travel/marine/…), so on a specialist policy it returns 0 and
     * zeros the action (and its invoice). This method mirrors the Rate button
     * (PolicyCreateController::calculatePremium): it sums the standard buckets
     * for the action's coverages AND the specialist tables, with the same
     * column rules — car_coverages is ADDITIVE (Section 1 + 2 + 3, it has no
     * grand total); every other table is FIRST-AVAILABLE (a grand total with
     * section fallbacks, or annual_premium||premium). Writes the total to
     * policy_actions.premium and returns it. Self-contained (no
     * SpecialistCoverageRegistry dependency), mirrors the backend copy.
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

            // The specialist one-to-one tables — the part the legacy renew sum
            // misses. Columns + additive/first-available rules mirror
            // PolicyCreateController::calculatePremium and SpecialistCoverageRegistry.
            // Kept in step with the backend twin: the four newest tables
            // (medical evacuation / commercial crime / environmental liability /
            // bonds) were added there but never here, so those sections priced
            // as 0 on every cron-server renew. travel_coverages reads `total`
            // (gross incl VAT — the canonical premium), not `policy_amount`,
            // which under-priced every travel renewal by the VAT.
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


}