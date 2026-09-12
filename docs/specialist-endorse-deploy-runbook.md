# Specialist Endorse — Deploy & Test Runbook

**Date:** 2026-05-25
**CFO sign-off:** ✅ confirmed for `newDays / totalDays` factor across all 10 specialist coverages.
**Scope:** Endorse + pro-rata + cancel/reinstate for 10 specialist tables. Motor untouched. COM/DOM untouched.

## What is being deployed

### Migrations (3)
| File | What it does |
|---|---|
| `2026_05_25_100001_add_action_id_term_id_to_specialist_coverages_table.php` | Adds `action_id` + `term_id` to travel / med-mal / PI |
| `2026_05_25_100002_add_endorsement_columns_to_specialist_coverages_table.php` | Adds `pro_rate_premium`, `previousActionIdCov`, `endors_flag` to all 10 |
| `2026_05_25_100003_add_soft_deletes_to_specialist_coverages_table.php` | Adds `deleted_at` to all 10 (cancel/reinstate support) |

All migrations are **idempotent** (re-runnable). They only ADD columns, never DROP. Down() is provided but should be considered a manual rollback step.

### Code changes
| File | Change |
|---|---|
| `app/Services/SpecialistEndorse/SpecialistCoverageRegistry.php` | NEW — single source of truth for the 10 tables + premium columns |
| `app/Services/SpecialistEndorse/SpecialistEndorseCalculator.php` | NEW — pro-rata writer + cancel/reinstate primitives |
| `app/Services/BackdatedEndorse/BackdatedEndorseRefresher.php` | Merges specialist tables into CHILD_TABLES at runtime |
| `app/Models/PolicyAction.php` | `sumActionAnnualPremium` now includes specialist sums; `calculatePremiumEndorse` calls writer; `newPolicyActionReplace` clones specialist rows |
| `app/Http/Controllers/Api/V1/PolicyCreateController.php` | `deleteAction` cascade extended to all 10; `cancelSpecialistCoverage` + `reinstateSpecialistCoverage` endpoints |
| `routes/api_v1.php` | 2 new specialist cancel/reinstate routes |

## Step-by-step deploy (DEV → LIVE)

### Phase A — Dev deploy (must pass before live)

1. **Snapshot baseline (before any change).** Pick 5 sample policies covering each scenario:
   - 2 motor-only policies (smoke check that motor-only is unaffected — see Edge case E5 in `specialist_endorse_calculation_cases.csv`)
   - 1 each of: CAR, EAR, PAR

   For each, run on dev:
   ```sql
   SELECT id, policy_id, transaction_type, status, premium, annual_premium
   FROM policy_actions
   WHERE policy_id IN (<5 sample ids>) AND status = 'ISSUED'
   ORDER BY policy_id, id;
   ```
   Save the output as `dev-snapshot-before.csv`.

2. **Apply the 3 migrations on dev.** Order matters — run in filename order. Idempotent so re-running is safe.

3. **Deploy code on dev.** Composer dump-autoload if needed (registry is a new namespace).

4. **Re-rate the same 5 policies** on dev (open the policy → Rate). Capture the same snapshot as `dev-snapshot-after.csv`.

5. **Diff the snapshots:**
   - **Motor-only policies:** `premium` and `annual_premium` MUST be byte-identical. If they changed, phantom specialist rows exist on those policies — investigate before live.
   - **CAR/EAR/PAR policies:** `annual_premium` may change if a specialist row carries premium that wasn't being summed before. This is expected and a feature, not a bug. Verify against `specialist_endorse_calculation_cases.csv` rows for that product.

6. **Run UW UAT scripts** from `specialist_endorse_calculation_cases.csv` — one row per product. Verify pro-rata matches the "Impact Incl-VAT BWP" column within ±0.10 BWP rounding tolerance.

7. **Cancel/Reinstate API smoke test (Postman / curl):**
   ```
   POST /api/v1/policies/{id}/coverages/{covId}/specialist/car_coverages/{rowId}/cancel
   POST /api/v1/policies/{id}/coverages/{covId}/specialist/car_coverages/{rowId}/reinstate
   ```
   After cancel, re-rate and verify `pro_rate_premium` on the row is **negative** equal to `-annual × factor`. After reinstate, re-rate and verify positive.

   *Note: no UI button yet for specialist cancel/reinstate. UW tests via API for now.*

### Phase B — Live deploy (only after Phase A passes)

1. **Migrations first** (5 min window). Apply in filename order.
2. **Code deploy** (within 30 min of migration). PSR-4 autoload picks up the new `SpecialistEndorse` namespace automatically.
3. **Post-deploy verification:** Re-run the 5-policy snapshot diff on live within 15 min.
4. **Rollback ready:** If motor-only premium drifts, revert code first (migrations can stay — they only ADD columns, harmless if not used).

## Known risks

| Risk | Mitigation |
|---|---|
| Phantom specialist row inflates motor-only policy annual sum | Snapshot diff in Phase A step 5 catches this. Clean phantom rows before live deploy. |
| Marine Cargo Open is declaration-based — newDays factor may not match product rule | CFO approved 2026-05-25 to apply standard factor. Flag MCO endorses in UW UAT to confirm operator expectation matches. |
| Travel mid-trip endorse — refund logic for unused days | Test cases TRV1–TRV4 cover this. Cap rule in writer prevents over-refund. |
| Backdated endorse on specialist row doesn't propagate to future RENEW | `BackdatedEndorseRefresher` now includes the 10 specialist tables. Test case E1 verifies propagation. |

## UW handover checklist

- [ ] Phase A dev deploy completed and snapshot diff clean
- [ ] At least 1 test case per product in `specialist_endorse_calculation_cases.csv` verified by UW on dev
- [ ] Cancel/Reinstate API tested via Postman (no UI yet)
- [ ] Marine Cargo Open endorse reviewed with CFO/UW for product rule alignment
- [ ] Live deploy date scheduled with rollback owner identified

## Out of scope (Phase 5+)

- UI buttons for specialist cancel/reinstate (currently API-only)
- Per-coverage Livewire wizard pages for specialist endorse
- Bulk endorse across multiple specialist rows in one transaction
- Auto-generated endorse letter PDFs per product

## Addendum 2026-05-27 — endorse data flow + pro-rata display

Follow-up work so a specialist ENDORSE behaves like COM/DOM end-to-end on the React UI. All additive; no COM/DOM or motor logic changed.

### 1. Endorse replicates specialist data
`POST /policies/{id}/new-transaction` (`PolicyCreateController::newTransaction`) now calls
`PolicyAction::replicateSpecialistCoveragesIfMissing()` after `newPolicyAction()`, so the new
action's CAR/PAR/EAR/etc. schedule + premium carry forward (previously the endorse opened blank).
Matching is by **coverage_id + risk-address name** (not `risk_address_id`, which is re-pointed per action).

### 2. Coverage list premium (edit screen)
`PolicyController::coverages()` surfaces each specialist coverage's premium as `calculated_value`
(registry `premium_cols`), so the Added Coverages row + total show it like COM/DOM child rows.

### 3. Rate banner pro-rata
`PolicyCreateController::calculatePremium()` adds a `$deltaSpecialist` term to the ENDORSE banner
sum = `sumAnnualForAction(thisAction) − sumAnnualForAction(immediatelyPreviousAction)`. Handles
**add / change / cancel** (cancel = soft-deleted row drops out → negative delta = refund) and
stacked endorses. Gated by the existing specialist product guard; no-op for COM/DOM.

### 4. V2 Quote / Policy Doc "Index of Sections" pro-rata
The Pro Rata Refund / Pro Rata Premium incl. VAT columns were hardcoded `P 0.00`. Now computed in
PHP — `SpecialistEndorseCalculator::proRataInclVatByScreenName()` — and passed via
`$specialistProRata` (keyed by coverage screen name) from `GenerateQuotationPdfJob::buildBladeData`.
Blades only print it (no calculation in the view):
`v2-quote-sheet-engineering.blade.php` and `specialist-product.blade.php`. Basis = per-section
`(thisAction − prevAction) annual × factor × (1+VAT)`, factor = `newDays/prevDays` (mirrors the
Rate banner so the PDF reconciles). Gross/Total/VAT columns are unchanged.

**Verify before live:** on a specialist endorse, change a section premium → Rate → confirm banner
pro-rata = delta × factor, then open V2 Quote + Policy Doc and confirm the Pro Rata columns match.
Repeat for add-coverage and cancel-coverage (refund). CFO sign-off on one worked example each.

### 5. Product coverage (Rate guard)
Specialist set unified to **[16, 17, 18, 19, 20, 22]** (`KycDomComProducts::IDS` minus plain
Commercial 7 / Domestic 8). Product **20 (Commercial Liabilities)** was added to the Rate
specialist guard in `PolicyCreateController::calculatePremium` — previously its PI/MM/D&O
premium was excluded from BOTH the rated annual and the endorse pro-rata.

| Product | Id | Rate banner | Quote/Doc blade | Specialist tables |
|---|---|---|---|---|
| Engineering | 16 | ✅ | v2-quote-sheet-engineering | CAR / PAR / EAR / Machinery Bd |
| Commercial Insurance Travel | 17 | ✅ | specialist-product | travel + PI/MM/MB |
| Domestic Insurance Travel | 18 | ✅ | specialist-product | travel (+CAR/PAR/EAR dom) |
| Commercial Liabilities | 20 | ✅ (now) | specialist-product | PI / MM / D&O |
| Marine | 22 | ✅ | specialist-product | marine cargo once-off / open / D&O |

**Caveat — product 19 (Specialist Domestic):** in the Rate guard, but `pickQuoteBladeView`
routes it to the default DomCom blade (not specialist-product), so its Quote/Doc "Index of
Sections" pro-rata won't render until 19 is added to the specialist-product route. Not in the
current test scope; flag if Specialist-Domestic endorse quotes are needed.
