# Bug Report — Policy Action page shows wrong per-coverage Total (SI / Premium)

**Status:** Open · **Severity:** High (finance-facing display) · **Billing impact:** None — see Scope
**Area:** Policies → Policy Actions tab (per-coverage total)
**Reported by:** Snehal Zunjarrao · **Analysed:** 2026-07-22
**Example:** Policy 127503 (COM…, Commercial / All Risks)

---

## Summary

On the **Policy Actions** tab, the per-coverage **"Total Sum Insured / Total Premium"** line is
wrong for **some** coverages (Fidelity Guarantee, motor coverages with sub-coverage premiums,
and coverages holding comma-formatted legacy values). The **v2 Quote Sheet** shows the correct
figure for the same coverages.

Root cause: the two figures come from **different engines**. The quote sheet uses the canonical,
Rate-aligned total; the action page **re-sums child rows on the frontend** with a hand-rolled
function that omits several premium channels.

## Scope / what is NOT affected

- The **canonical / billed premium** is correct. The grand total is read from the stored
  `policy_actions.annual_premium` (kept fresh by `recomputeActionTotals` / `calculatePremium`).
- Only the **per-coverage display total** re-computed on the frontend is wrong. This is a
  **display correctness** bug, not a mis-billing. It matters because staff compare it to the
  quote sheet and lose trust in the figure.

## Correct engine (reference)

- Canonical recipe: `backend/resources/views/v2/livewire/pdf/v2-quote-sheet.blade.php` lines **783–1023**
  (`$sectionTotalsByName` + grand total). Per coverage it sums: detail premium + motor bucket
  (`calculated_value` + **every** `premium_*` field) + motor traders + specified items +
  extension detail + `policy_coverages_data.premium` (**Fidelity Guarantee only**), with
  cancelled/replaced/deleted filtering.
- Backend deliberately reads the grand total from the stored value, not a re-sum:
  `backend/app/Http/Controllers/Api/V1/PolicyController.php` lines **952–965** (comment cites
  policy 213363 drifting **9,837 re-sum vs 4,054 canonical**).

## Faulty engine (action page)

- `coverageTotals(c)` — `frontend/src/pages/Policies/PolicyDetailPage.tsx` lines **2852–2868**.
- Rendered as the "Total Sum Insured / Total Premium" line at lines **3469–3478**
  (hidden when both are `<= 0`, line **3471**).
- It sums only five arrays: `details`, `extensions`, `specifiedItems`, `motorTraders`, `vehicles`.

## Divergences → coverages that total wrong

| # | Coverage affected | What the action page drops | Effect | Evidence |
|---|---|---|---|---|
| 1 | **Fidelity Guarantee** | Premium + SI arrive in `fidelityData`; `coverageTotals` never reads it | Total shows **0**; row hidden by `<=0` guard | PolicyController.php **1028–1037**; PolicyDetailPage.tsx **2855–2866**, **3471** |
| 2 | **Motor / Commercial Motor** | Only `vehicle.calculatedValue` counted; the ~17 `premium_*` sub-coverage fields omitted | Premium **understated** by all motor extensions | PolicyDetailPage.tsx **2860**; correct pattern in StepCoverages.tsx **1104–1108** (list **259–267**) |
| 3 | **Any coverage w/ legacy comma values** | `num()` = bare `parseFloat`, no comma strip → `"1,250.00"` → `1` | Value **truncated at the comma** | PolicyDetailPage.tsx **2853**; same bug already fixed for display at ~**12476–12484** |
| 4 | **Specialist / coverage-level premium** in a DomCom action | Coverage-level `calculated_value` ignored; `localCoverageSum` reads camelCase vs backend snake_case | Total **0** | PolicyDetailPage.tsx **2835** vs PolicyController.php **1023** |

Highest confidence: **#1 Fidelity Guarantee** (ties to the recent Fidelity replication fix).

## Steps to reproduce

1. Open a policy whose action contains a **Fidelity Guarantee** coverage (or a motor coverage with sub-coverage premiums).
2. Go to **Policy Actions** and view the coverage card's "Total Sum Insured / Total Premium".
3. Generate the **v2 Quote Sheet** for the same action.
4. **Expected:** the two totals match. **Actual:** the action page reads 0 (Fidelity) or low (motor / comma values).

## Proposed fix (options — not yet implemented)

1. **Single source of truth (preferred):** return a per-coverage total from the backend using the
   canonical recipe and display it; drop the frontend re-sum so quote sheet and action page cannot diverge.
2. **If keeping the FE sum:** mirror the canonical recipe in `coverageTotals` — add
   `fidelityData.premium` + SI, add the motor `premium_*` fields, read coverage-level `calculated_value`,
   and use the comma-safe number parser. The wizard aggregate (StepCoverages.tsx **1104–1108**) already
   handles motor + fidelity correctly and can be reused.

_No product code was changed for this report._
