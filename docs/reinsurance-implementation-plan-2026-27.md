# Reinsurance Implementation Plan — 2026/27 Treaty Year

**Prepared for:** CFO, Alpha Direct Insurance Company (Pty) Ltd
**Date:** 3 August 2026
**Scope:** General Quota Share Treaty + Motor Quota Share Treaty, both 1 July 2026 – 30 June 2027, placed through J.B. Boda.
**Companion document:** `docs/reinsurance-SOP.md`

---

## 1. Bottom line

**We do not need to build a reinsurance module from scratch.** The hardest part — the cession mathematics for a 30/70 Quota Share — is already built, already test-verified, and covers both slips.

Three things need your decision:

1. **The verified engine is not connected to anything.** `backend/app/Reinsurance/ReinsuranceEngine.php` has zero callers in the codebase. It computes correctly and is called by nothing. Connecting it is the single highest-value piece of work.
2. **Two parallel reinsurance data models exist.** A legacy product/formula chain that is live and wired into policy issuance, and a newer spec-driven treaty schema that is dormant. One must be the authority or the numbers will diverge.
3. **One genuinely new sub-module is required: RI Technical Accounting.** Quarterly statements of account, reserve deposits, brokerage, VAT, settlement. None of this exists in any form today.

Against these two slips: **5 requirements are built, 3 are partially built, 15 are missing.** The count looks discouraging; the substance is not. What is finished is the load-bearing part.

---

## 2. The two treaties at a glance

| | General Quota Share | Motor Quota Share |
|---|---|---|
| Broker | J.B. Boda | J.B. Boda |
| Slip leader | FM Re Botswana | Continental Re Botswana |
| Period | 1 Jul 2026 – 30 Jun 2027 | 1 Jul 2026 – 30 Jun 2027 |
| Basis | Underwriting year | Underwriting year |
| Retention / Cession | 30% / 70% | 30% / 70% |
| Ceding commission | Flat 37.50% | Sliding 40% → 22.5%, provisional 25% |
| Profit commission | 30%, mgmt expense 7.5%, losses c/f 5 years | Not applicable |
| Loss ratio cap | Not applicable | 75% |
| Event limit | BWP 70,000,000 | BWP 70,000,000 (Riot & Strike BWP 50,000,000) |
| Annual aggregates | SRCC Zimbabwe BWP 20,000,000; War/Civil War Goods in Transit BWP 6,000,000 | Riot & Strike BWP 50,000,000 |
| Notice of claims | BWP 500,000 | BWP 500,000 (ceded portion) |
| Cash loss limit | BWP 500,000 | BWP 250,000 (ceded portion) |
| Accounts | Quarterly, 45 days after quarter close, confirmed within 14 days | Same |
| EPI 2026/27 (100%) | BWP 24,872,310 | BWP 70,000,000 |
| Brokerage | 2.50% | 2.50% |
| Currency | BWP, VAT applies | BWP, VAT applies |

Both treaties are plain Quota Share. That is why the existing engine covers the maths for both without modification.

---

## 3. What we already have

| Asset | Where | State |
|---|---|---|
| Calculation engine — QS, Surplus, Excess of Loss, Facultative; flat / sliding / profit commission; reinsurer split; reconciliation gate on every output | `backend/app/Reinsurance/ReinsuranceEngine.php` | Verified 17/17 against Python reference, 16/16 in PHP runtime (18 Jun 2026). **Not called by anything.** |
| Treaty configuration schema — `treaty_master`, `treaty_proportional`, `treaty_xl_layers`, `reinsurer_shares`, `ri_computations` | `backend/database/migrations/2026_06_18_000001_create_reinsurance_treaty_tables.php` | Built. DEMO seed data only. |
| Legacy allocation chain — reinsurance types, coverage groups, formulas, treaties, reinsurers, `policy_reinsurance` | `backend/app/Models/`, 2023 migrations | Live and wired into policy issuance. |
| Reinsurance REST API | `backend/app/Http/Controllers/Api/V1/ReinsuranceApiController.php` (961 lines) | Full CRUD on types, coverage groups, formulas, treaties. |
| Policy-level hooks | `GET /policies/{id}/reinsurance`, `POST /policies/{id}/reinsurance/recalculate`, `GET /policies/{id}/reinsurance/status` | Live. |
| React admin screens — Treaty, Formula, Coverage Grouping, Reinsurance Type, Reinsurers | `frontend/src/pages/Reinsurance/` (~3,000 lines) | Built. |
| Operational tooling — `treaty:seed`, `treaty:check`, treaty rollover, reinsurance backfill, setup check | `backend/app/Console/Commands/`, `backend/app/Services/Reinsurance/` | Built. |
| Exports — risk profile, claims profile, summary | `backend/app/Exports/` | Built. |
| Written SOP with formulas, reconciliation checklist and hard rules | `docs/reinsurance-SOP.md` | Current. |

**The foundation is real.** This is not a greenfield project.

---

## 4. Gap analysis against the two slips

Status key: **Built** — works today, needs only treaty terms loaded. **Partial** — logic exists but is incomplete for these slips. **Missing** — must be developed.

| # | Treaty requirement | Status | What is needed |
|---|---|---|---|
| 1 | Quota Share 30/70 cession, both treaties | Built | Load terms |
| 2 | Flat ceding commission 37.50% (General) | Built | Load terms |
| 3 | Claim recovery at 70% | Built | Connect to claims |
| 4 | Reinsurer split with sum-to-100% gate | Built | Load signed lines |
| 5 | Reconciliation gate on every published number | Built | — |
| 6 | Sliding-scale commission (Motor) | Partial | Bands and clamping work. The provisional 25% for the first three quarters, and the Q4 adjustment to final, are not implemented. |
| 7 | Profit commission (General) | Partial | Formula works. The 5-year loss carry-forward store does not exist — the engine accepts a single carried-forward figure with no year-by-year history. |
| 8 | Written line vs signed line; "of cession" vs "of 100%" basis | Partial | `reinsurer_shares` holds one percentage column with no basis flag and no written/signed distinction. GIC Re is expressed both ways (17% of cession = 11.90% of 100%). |
| 9 | **Engine connected to policies and claims** | Missing | Zero callers today. Highest-priority build. |
| 10 | Event limit BWP 70,000,000 per occurrence | Missing | Cap on ceded recovery, plus the hours clause that defines an occurrence: 72 hours for windstorm / earthquake / strike-riot, 168 hours for all other perils, 75 hours within an 80km radius for fire. |
| 11 | Annual aggregate limits — three of them | Missing | Erosion tracking per underwriting year, peril and territory. SRCC Zimbabwe 20m, War Goods in Transit 6m, Motor Riot & Strike 50m. |
| 12 | Schedule A per-class limits — eleven limits across both slips | Missing | Capacity table plus a breach referral at underwriting. General: Real Property 10m, Engineering 10m, Electronic Equipment 6m, Goods in Transit 3m, Miscellaneous & Financial Loss 1m, Fidelity Guarantee 1m, Accidental Damage 7.5m. Motor: Own Damage 5m per vehicle, trailer 1.5m, Passenger Liability 2.5m per event, Third Party Damage 10m per event. |
| 13 | Loss ratio cap 75% (Motor) | Missing | — |
| 14 | Inwards facultative capped at 25% of treaty limit | Missing | Underwriting validation, with the Choppies / Kamoso / Motovac exception at 100% of treaty capacity. |
| 15 | Facultative mandatory above BWP 50,000,000 sum insured | Missing | Hard gate before bind, per company underwriting standard. |
| 16 | Territorial scope validation | Missing | General: Botswana, Zimbabwe, Zambia plus the worldwide and group carve-outs, excluding USA and Canada. Motor: Sub-Saharan Africa and Indian Ocean Islands. |
| 17 | Co-insurance 50% (General) | Missing | Gross-up before cession. |
| 18 | Cash loss and notice-of-claims triggers | Missing | Claim-side alerts at BWP 500,000 and BWP 250,000 ceded. |
| 19 | Quarterly statement of account | Missing | New sub-module. |
| 20 | Reserve deposit — 40% premium reserve, interest 2% below average call rate, foreign reinsurers only | Missing | New sub-module. |
| 21 | Brokerage 2.50% | Missing | New sub-module. |
| 22 | VAT, and delay-in-payment interest at 110% of prime | Missing | New sub-module. |
| 23 | IFRS 17 outputs — reinsurance contracts held, ceded premium, amounts recoverable from reinsurers | Missing | Ledger mapping. |

---

## 5. The one genuinely new build: RI Technical Accounting

Items 19 to 22 above are a coherent sub-module that does not exist anywhere in Graphite v2 today. It must produce, per treaty per quarter:

written premium ceded, less returns and cancellations; ceding commission at the applicable rate; brokerage at 2.50%; claims paid less salvages and recoveries; outstanding claims by year of occurrence; cash loss recoveries; reserve deposit retained and interest accrued; VAT; and the net balance due either way.

It must then run the contractual clock: render within 45 days of quarter close, confirm or object within 14 days, and charge delay interest at 110% of market prime on anything overdue.

**This is a repeating 45-day cycle. Build it to generate from the system, not from Excel.** The same applies to the 14-day confirmation reminder and the reserve-deposit interest accrual — all three are automation candidates rather than manual finance tasks.

---

## 6. Phased delivery

| Phase | Work | Effort | Depends on |
|---|---|---|---|
| 1 | **Load treaty terms.** Both slips into `treaty_master`, `treaty_proportional`, `reinsurer_shares`. Configuration, not code. | 3 days | Full signed line schedule (see blockers) |
| 2 | **Connect the engine.** Wire `ReinsuranceEngine` into policy issuance and claims settlement. Decide the authoritative data model. Migrate `policy_reinsurance` money columns from string to decimal. | 10–12 days | Phase 1 |
| 3 | **Treaty limits and underwriting controls.** Event limit and hours clause, three annual aggregates, eleven Schedule A limits, loss ratio cap, facultative gates, territorial validation, co-insurance. | 15 days | Phase 2 |
| 4 | **Complete the commission engine.** Sliding-scale provisional-to-final adjustment, profit commission with 5-year carry-forward history, written vs signed lines with basis conversion. | 8–10 days | Phase 2 |
| 5 | **RI Technical Accounting.** The new sub-module in section 5. | 20–25 days | Phases 2 and 4 |
| 6 | **IFRS 17 reporting and RI dashboard.** Ceded premium, amounts recoverable, reinsurance contracts held; treaty position dashboard. | 8–10 days | Phase 5 |

**Total: 64–75 developer-days.**

### The deadline that matters

The treaties incepted 1 July 2026. The first quarter closes 30 September 2026. Accounts are due within 45 days — **14 November 2026**.

From today that is roughly 15 weeks. At 64–75 developer-days, **one developer arrives with no margin at all. Two developers finish with room to test and reconcile.**

---

## 7. Blockers and decisions for the CFO

| # | Issue | Why it matters | Owner |
|---|---|---|---|
| 1 | **Full signed line schedule is missing.** General shows FM Re written 30% of 100%, signed 29% of 100%, plus a handwritten note for PBC Re at 10% of cession. Motor shows Continental Re at 25% of 100% and GIC Re SA at 22.50% written / 17% of cession. Neither placement reaches 100%. | The engine rejects any reinsurer split that does not total 100%. That gate is deliberate and should not be bypassed. Until the complete signing schedule arrives we cannot load either treaty as live. | J.B. Boda / Reinsurance team |
| 2 | **Motor slip is not executed by Alpha Direct.** The Reinsured signing page is blank. On the General slip the signature is present (Paul Beka, Operations Manager) but the date line is blank. | Loading unexecuted terms as authoritative is a control weakness. | Operations |
| 3 | **No excess of loss or catastrophe programme has been supplied.** Reinsurers' liability caps at BWP 70,000,000 per occurrence. A BWP 150,000,000 occurrence cedes 70m and leaves 80m net to Alpha Direct. | If a Cat XL protects the retention it must be loaded too — the engine already supports XL layers and reinstatements. If none exists, that is a net-retention exposure question, not a systems question. | CFO / Reinsurance team |
| 4 | **General EPI basis.** 2025/26 was BWP 8,500,000. 2026/27 is BWP 24,872,310 — an increase of roughly 193%. | Profit commission and the sliding scale both key off premium. A wrong EPI distorts commission accruals all year. Confirm whether this is real growth or a change of basis. | CFO |
| 5 | **Open reconciliation query from 18 June 2026.** The SOP records an unresolved XL worked-example discrepancy raised with Pako, Kago and Bokani. | Still unanswered. It does not block the two Quota Share treaties, but it must be closed before any XL layer goes live. | Reinsurance team |

---

## 8. Recommendations

1. **Resource two developers, not one.** The first statement of account is due 14 November 2026 and the build is 64–75 developer-days. One developer has no safety margin.

2. **Make the verified engine the single computation authority.** Keep the legacy tables for product-to-coverage allocation only, and migrate the `policy_reinsurance` money columns from string to decimal — storing ceded premium as text is not adequate for IFRS 17 reporting or audit.

3. **Do not publish a real reinsurance number until the full signed line schedule arrives.** Run both treaties under the DEMO flag until then. The reconciliation gate exists to protect us; let it do its job.

---

## 9. Governance

**Data protection.** The engine operates on policy numbers, claim numbers, sums insured and amounts only. No insured names, Omang numbers, bank details or addresses. This must not change as the module is extended.

**Reconciliation.** No reinsurance figure is published unless `reconciles = true`, reinsurer shares total 100%, the treaty matches class of business and treaty year, the calculation is in a single currency, and the treaty is not flagged as DEMO. CFO sign-off is required before go-live.

**IFRS 17 language.** Reinsurance contracts held; ceded premium; amounts recoverable from reinsurers.
