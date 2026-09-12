# Reinsurance (RI) Function — SOP & Runbook

**Status:** engine built + verified (Python reference 17/17, PHP in-container 16/16, 2026-06-18). Treaty config tables + DEMO seeder in place. API + dashboard = next slice. **Real treaty terms pending** from the reinsurance team (Pako / Kago / Bokani).

**Data protection (AD-POL-AI-GOV-001):** the engine works on **policy numbers, claim numbers, sums insured and amounts only**. No insured names, Omang/ID, bank or address data.

**Hard rules**
1. Never assume a rate or layer — read every parameter from the treaty tables.
2. Every output must reconcile. If `reconciles=false`, do not publish the number.
3. IFRS 17 terms: *reinsurance contracts held; ceded premium; amounts recoverable from reinsurers.*

---

## Treaty table schema
- **treaty_master** — `treaty_id, treaty_year, treaty_type (QS|SURPLUS|XL|FAC), class_of_business, currency, effective_date, expiry_date, basis (per_risk|per_event|per_policy), status, is_demo`
- **treaty_proportional** — `cession_pct (QS), retention_amount + lines + treaty_capacity (Surplus), commission_basis (FLAT|SLIDING|PROFIT), commission_rate_flat, slide_* , slide_loss_ratio_bands(json), profit_commission_pct, reinsurer_mgmt_expense_pct, loss_carryforward_flag`
- **treaty_xl_layers** — one row per layer: `layer_no, layer_limit, layer_attachment (=priority for layer 1), premium_basis (ROL|PCT_GNPI|FLAT), layer_premium_or_rate, gnpi, num_reinstatements, free_reinstatements, reinstatement_pct, aggregate_deductible, aggregate_limit`
- **reinsurer_shares** — `treaty_id, treaty_year, reinsurer_id, share_pct` (Σ = 100%)
- **ri_computations** — stored results per policy/claim (inputs, every output, `breakdown` json, `reconciles`).

---

## Formulas (engine = `backend/app/Reinsurance/ReinsuranceEngine.php`)

### 1. Quota Share
- Ceded premium = gross_premium × cession_pct
- Ceding commission = ceded_premium × commission_rate
- Claim recovery = gross_claim × cession_pct
- Net retained premium = gross_premium − ceded + commission; Net retained claim = gross_claim − recovery

### 2. Surplus
- If sum_insured ≤ retention → 0% ceded (fully retained).
- Ceded share = min( (SI − retention)/SI , capacity/SI ), capacity = lines × retention.
- Ceded premium / claim recovery = gross × ceded share.

### 3. Excess of Loss
- Retained below priority = min(loss, P).
- Per layer i: recovery_i = clamp( loss − attachment_i , 0 , limit_i ). Layer 1 attaches at P; each higher layer at the top of the one below.
- Loss above top = max( loss − (attach_top + limit_top) , 0 ) → retained.
- **Reconciliation:** loss = retained_below + Σ recovery_i + above_top.
- Per-event: aggregate qualifying claims (hours clause / event window) before applying P + layers. Aggregate limit/deductible tracked per layer. No ceding commission on XL unless overridden.
- **Reinstatement premium** = (recovery_in_layer / layer_limit) × layer_premium × reinstatement_pct, after free reinstatements used, capped at num_reinstatements; once exhausted remaining cover = 0.

### 4. Facultative
- FAC placement mandatory when sum_insured > BWP 50,000,000 — confirm placement exists before treating as covered.
- Proportional FAC → §2 with risk-specific cession; FAC XL → §3 with risk-specific priority/layers.

### 5. Commission variants
- **Flat:** ceded × commission_rate_flat.
- **Sliding:** provisional rate → at adjustment set by actual loss ratio against bands, clamped to [min,max]; book provisional-to-final adjustment.
- **Profit:** profit_commission_pct × treaty_profit; treaty_profit = ceded − ceding_commission − incurred_ceded_claims − ceded×mgmt_expense_pct − loss_carried_forward. profit ≤ 0 → 0 commission, carry deficit forward if flagged.
- Split every commission/amount across reinsurers by share_pct.

---

## ⚠️ Spec correction (awaiting confirmation)
The spec's XL worked example for **loss 12,000,000** (L2 = 4,000,000, above-top = 0) **does not reconcile** — retained 2M + 7M + 0 = 9M ≠ 12M. By the clamp formula the correct result is **L2 = 5,000,000, above-top = 2,000,000** (reconciles to 12M). Engine computes the reconciling version. Query sent to Pako/Kago/Bokani 2026-06-18 to confirm or supply the treaty rule that would legitimately make L2 = 4,000,000.

---

## Reconciliation checklist (before any RI number is published)
1. `reconciles = true` on every record (proportional: gross = retained + ceded; XL: loss = retained_below + Σ recoveries + above_top).
2. Reinsurer shares Σ = 100%; each split reconciles to the total.
3. Treaty selected matches class_of_business + treaty_year; flag any missing/contradictory config instead of guessing.
4. Single currency per treaty calculation.
5. Not `is_demo` (real treaty terms loaded) — DEMO config is for testing only.
6. CFO checkpoint before go-live.

## Test verification
`backend/app/Reinsurance/ReinsuranceEngine.php` verified against the spec's worked examples — QS 30%, Surplus (0%/80%/capped 18%), XL (1.5M/4M/12M/20M), reinstatement exhaustion, profit-commission loss-carryforward, reinsurer split, and a global reconciliation gate. All pass.
