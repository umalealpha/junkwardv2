# Graphite v2 — Reinsurance Gap Analysis and Implementation Plan

## 2026/27 Treaty Year

**Document:** RI-02 — Gap Analysis and Implementation Plan
**Prepared for:** Chief Financial Officer, Alpha Direct Insurance Company (Pty) Ltd
**Date:** 3 August 2026
**Reference:** RI-01 — Reinsurance Business Requirements Specification
**Status:** For approval and resourcing decision

---

## Status note — 4 September 2026

**This analysis was written on 3 August 2026 and the tables below have not been re-cut since. Nine of the rows marked "Complete gap" are now built.** They are listed here rather than edited in place, so the original assessment stays readable against what was actually delivered.

| Requirement | Assessed as | Now |
|---|---|---|
| BR-CAP-01 to 11 — Schedule A class limits | Complete gap | `RegulatoryCessionCalculator::classLimitFor()`, with `ScheduleAConformanceTest` |
| BR-CAP-12 to 15 — event limits | Complete gap | `EventLimitCalculator` |
| BR-CAP-16, 17 — annual aggregates | Complete gap | `AnnualAggregateCalculator` |
| BR-CAP-20 to 27 — hours clause | Complete gap | `EventLimitCalculator` |
| BR-CAP-28 to 33 — CBI sub-limits | Complete gap | `TreatyControlsCalculator` |
| BR-COM-16, 17 — brokerage 2.50% | Complete gap | `StatementBalanceBuilder` |
| BR-ACC-01, 02 — the 45- and 14-day clocks | None anywhere in Graphite v2 | `treaty:statement-clocks`, daily at 07:30 in both apps |
| BR-ACC-04 to 08 — the five accounting items | Complete gap | The statement builders. BR-ACC-08 is complete both halves since 4 Sep 2026 — `treaty_cash_loss_recoveries` records the demand and the receipt separately, and only what was received is deducted |
| BR-ACC-10 — delay interest | Complete gap | `StatementDelayInterestBuilder`. Reports a balance as overdue and unpriceable until a prime rate is on file |
| BR-ACC-12 to 16 — reserve deposit | Complete gap | `StatementReserveDepositBuilder`. General computes on BR-ACC-12's 40%; Motor retains a stated nil since Reinsurance ruled on 7 Sep 2026 that the deposit applies to the GQS treaty. Interest still waits on the average call rate |

**Everything else in the tables below stands.** The territorial validation, the underwriting controls, the exclusion schedules, off-set, cash calls, per-reinsurer claims agreement and IFRS 17 are all still gaps.

**And a caution that applies to every "now built" row above:** built is not the same as producing a figure. No statement figure is sound until the cession is cut over — `reinsurance.engine` reads `legacy` and 9 of 50 actions are staged. See RI-19, which carries the live outstanding list.

---

## 1. Purpose and method

Document RI-01 states what the two treaties require. This document answers the second question: **how do we deliver those requirements in Graphite v2, what already exists, and what has to be built.**

Every row in the gap register carries the requirement identifier from RI-01, so any figure here can be traced back to a treaty clause.

**Priority scale.** Priorities are set by the three real contractual dates, not by opinion.

| Priority | Driven by | Date |
|---|---|---|
| **P1** | First quarterly statement of account for both treaties — 45 days after the quarter closing 30 September 2026 | **14 November 2026** |
| **P2** | First sliding scale commission adjustment — Motor fourth quarter account for the 2026/27 underwriting year | **14 August 2027** |
| **P3** | First profit or loss statement — General, ascertained 24 months from inception and submitted with the fourth quarter account | **2028** |

---

## 2. Executive summary

**Graphite v2 does not need a reinsurance module built from scratch.** The cession mathematics for a 30/70 Quota Share is already built and test-verified, and both treaties are plain Quota Share.

Four findings drive this plan.

**The verified engine is connected to nothing.** `backend/app/Reinsurance/ReinsuranceEngine.php` passed 17 of 17 checks against the Python reference and 16 of 16 in the PHP runtime. A search of the entire backend returns exactly one reference to it — its own file. It computes correctly and nothing calls it. Connecting it is the single highest-value piece of work in this plan.

**Two parallel reinsurance data models exist.** A legacy product-and-formula chain that is live and wired into policy issuance, and a newer treaty schema that is dormant on demonstration data. Running both invites the two to disagree. One must be the authority.

**Reinsurance technical accounting does not exist in any form.** Quarterly statements of account, reserve deposits, brokerage, VAT, off-set and settlement. This is the largest single build and it carries the earliest hard deadline.

**One month of treaty business has already been written and not ceded.** The treaties incepted on 1 July 2026. Policies written since then must be ceded retrospectively before the first statement can be rendered.

**Revised effort: 92 to 112 developer-days.** This is higher than the 64 to 75 days quoted before the full contractual wordings were available. The wordings added the hours clause, event limit proration across underwriting years, contingent business interruption sub-limits, the two-basis participation model, class-specific exclusion schedules, and a materially fuller accounting specification. The earlier figure was based on the summary slips alone.

**The critical path to 14 November 2026 is 45 to 55 days** — treaty configuration, engine integration and technical accounting. That is achievable with two developers. The remaining 47 to 57 days can complete against the later contractual dates.

---

## 3. Current Graphite v2 reinsurance estate

| Asset | Location | State |
|---|---|---|
| Calculation engine — Quota Share, Surplus, Excess of Loss, Facultative; flat, sliding and profit commission; reinsurer split with a sum-to-100% gate; a reconciliation gate on every output | `backend/app/Reinsurance/ReinsuranceEngine.php` | Verified June 2026. **Zero callers.** |
| Treaty configuration schema — `treaty_master`, `treaty_proportional`, `treaty_xl_layers`, `reinsurer_shares`, `ri_computations` | `backend/database/migrations/2026_06_18_000001_*` | Built. Demonstration data only, flagged `is_demo`. |
| Legacy allocation chain — reinsurance types, coverage groups, formulas, treaties, reinsurers, `policy_reinsurance` | `backend/app/Models/`, 2023 migrations | Live and wired into policy issuance. Money columns stored as text. |
| Reinsurance REST API — full CRUD on types, coverage groups, formulas, treaties | `backend/app/Http/Controllers/Api/V1/ReinsuranceApiController.php` | 961 lines. Built. |
| Policy hooks — read reinsurance, recalculate, status | `backend/routes/api_v1.php` | Live. |
| Admin screens — Treaty, Formula, Coverage Grouping, Reinsurance Type, Reinsurers | `frontend/src/pages/Reinsurance/` | ~3,000 lines. Built. |
| Operational tooling — `treaty:seed`, `treaty:check`, treaty rollover, reinsurance backfill, setup check | `backend/app/Console/Commands/`, `backend/app/Services/Reinsurance/` | Built. `BackfillReinsurance` is directly useful for the 1 July catch-up. |
| Exports — risk profile, claims profile, summary | `backend/app/Exports/` | Built. |
| Documented procedure — formulas, reconciliation checklist, hard rules | `docs/reinsurance-SOP.md` | Current. |

---

## 4. Gap register

**Status key:** **Exists** — works today, needs configuration only. **Enhance** — logic exists but is incomplete. **New** — must be developed.

### 4.1 Treaty governance and scope

| RI-01 Ref | Requirement | Current Graphite capability | Gap | Development required | Priority |
|---|---|---|---|---|---|
| BR-GOV-01 | Two treaties administered separately | `treaty_master` carries `treaty_id`, `treaty_year` and `class_of_business`; unique key on treaty and year | None structurally | Configure both treaties; map products to the correct treaty | P1 |
| BR-GOV-02, 03 | Period and cancellation notice | `effective_date` and `expiry_date` on `treaty_master` | No notice-of-cancellation tracking | Add notice dates and a renewal reminder | P3 |
| BR-GOV-04 | **Underwriting year basis** | Not modelled. `ri_computations` has no underwriting year column; policy cession is transaction-driven | **Significant.** Premium and claims must attach to the underwriting year of the original policy, not the accounting period of the transaction | Add underwriting year derivation and stamping across cession, claims and accounting. This is the single most pervasive change in the plan | P1 |
| BR-GOV-05, 06, 07 | Class definitions and reinsured entity scope | `class_of_business` on `treaty_master`; product and coverage grouping in the legacy chain | No rule excluding companies acquired during the period (Motor) | Extend product-to-treaty mapping; add acquired-entity exclusion flag | P1 |
| BR-GOV-08, 09, 10 | Original currency, FX at policy issue date, loss settlement at remittance rate | Engine enforces single currency per calculation; no FX rate store | Multi-currency cession not supported | Add FX rate table keyed to policy issue date and remittance date | P2 |
| BR-GOV-11 | Botswana law, Gaborone arbitration | Not applicable to system | None | Record as treaty metadata | P3 |
| BR-GOV-12, 13 | Portfolio entry and withdrawal | Not modelled | Nil for General; Motor withdrawal option on termination | Record as configuration; build withdrawal calculation only if exercised | P3 |
| BR-GOV-14 | Extended expiry | Not modelled | Loss occurrence in progress at expiry must not fall to renewal | Add occurrence-spanning-expiry handling to event logic | P2 |
| BR-GOV-15, 16 | Special cancellation and premium calculation on Gross Earned Premium Income | Not modelled | No earned premium calculation for treaty purposes | Build only if cancellation arises; document the calculation | P3 |
| BR-GOV-17, 18 | Interpretation, errors and omissions | Not applicable | None | No development | — |

### 4.2 Territorial scope

| RI-01 Ref | Requirement | Current Graphite capability | Gap | Development required | Priority |
|---|---|---|---|---|---|
| BR-TER-01 to 03 | General base territory plus two worldwide carve-outs | No territorial validation in the reinsurance path | **Complete gap** | Build a territory rule set per treaty: base territories, per-coverage worldwide overrides, and USA/Canada exclusions | P2 |
| BR-TER-04, 05 | Group territorial extensions for Choppies, Motovac and Kamoso | Group and client entities exist in policy data | No treaty-level group extension rules | Add a group register with per-group territory extensions | P2 |
| BR-TER-06 | Motor territory — 47 named countries | None | Complete gap | Load the country schedule; validate risk location on bind | P2 |
| BR-TER-07, 08, 09 | Special acceptances, including Botho University outside territorial scope | None | Complete gap | Build a special acceptance register with leader-approval evidence and an explicit territorial override | P2 |

### 4.3 Cession and retention

| RI-01 Ref | Requirement | Current Graphite capability | Gap | Development required | Priority |
|---|---|---|---|---|---|
| BR-CES-01 | Retention 30% / cession 70% | **Exists.** `ReinsuranceEngine::quotaShare()`, verified | None | Load `cession_pct` for both treaties | P1 |
| BR-CES-02, 03 | Original Gross Rate; premium less returns, cancellations and inuring premiums | Engine takes gross premium as an input | No derivation of the treaty premium base from policy transactions | Build the premium base calculation, netting returns, cancellations and inuring premiums | P1 |
| BR-CES-04 | Claim recovery at ceded proportion; reinsurers share salvages and recoveries | Engine computes recovery; **no claims integration** | Engine is not called from claims | Wire the engine into claim settlement, salvage and recovery events | P1 |
| BR-CES-05, 06 | Claim expenses shared; ex gratia excluded without consent | Not modelled | Expense allocation and ex gratia flag absent | Add expense categorisation and an ex gratia consent flag on claims | P1 |
| BR-CES-07 | Recovery contingent on premium payment; off-set constitutes compliance | Not modelled | Complete gap | Add premium-paid gate on recovery, satisfied by off-set | P2 |
| BR-CES-08 | Co-insurance 50% (General) | Not modelled | Gross-up before cession absent | Add co-insurance share handling ahead of the cession calculation | P2 |
| BR-CES-09, 10 | One Risk definition; follow the fortunes | Risk records exist per policy | No treaty-level one-risk designation | Add one-risk grouping for capacity testing | P2 |

### 4.4 Capacity and limits

| RI-01 Ref | Requirement | Current Graphite capability | Gap | Development required | Priority |
|---|---|---|---|---|---|
| BR-CAP-01 to 11 | Schedule A — 7 General class limits and 4 Motor class limits | None. `treaty_proportional` has a single `treaty_capacity` column | **Complete gap.** Eleven distinct per-class limits on differing bases — per situation, per risk, per vehicle, per trailer, per event | Build a treaty class-limit table with basis of cover, plus a breach check and underwriting referral at bind and endorsement | P2 |
| BR-CAP-12 to 15 | Event limits — BWP 70m both treaties, plus BWP 50m Motor Riot and Strike | None | Complete gap | Add per-occurrence cap on aggregated ceded recovery, with a separate peril-specific cap | P2 |
| BR-CAP-16, 17 | General annual aggregates — SRCC Zimbabwe BWP 20m, War Goods in Transit BWP 6m | None | Complete gap | Build aggregate erosion tracking by underwriting year, peril and territory | P2 |
| BR-CAP-18 | Event limit proration where an event spans two or more underwriting years | None | Complete gap | Build proration of the event limit by each underwriting year's contribution to total event losses | P2 |
| BR-CAP-19 | Marine terrorism aggregate for US situated risks — 2× per event, 4× annual | None | Complete gap | Low volume; implement as a referral rule rather than automated capping | P3 |
| BR-CAP-20 to 27 | **Hours clause** — 72h, 168h and, for General, 75h within an 80 km radius; peril cascade; Reinsured's election of commencement and division | None | **Complete gap.** This is the mechanism that defines what an event is, so every event limit and aggregate depends on it | Build claim clustering into loss occurrences by peril and time window, including the 80 km geographic radius test for the General fire category, with manual override of the commencement date and time and the ability to split an occurrence | P2 |
| BR-CAP-28 to 33 | Contingent business interruption sub-limits | None | Complete gap | Add CBI sub-limit validation at underwriting; accumulation reporting | P3 |

### 4.5 Commission

| RI-01 Ref | Requirement | Current Graphite capability | Gap | Development required | Priority |
|---|---|---|---|---|---|
| BR-COM-01 | General flat ceding commission 37.50% | **Exists.** `commissionFlat()` | None | Load the rate | P1 |
| BR-COM-11 | Motor sliding scale — 26-band table | **Enhance.** `commissionSliding()` accepts bands with minimum and maximum clamping; `slide_loss_ratio_bands` column exists | Bands not loaded; loss ratio derivation absent | Load the 26 bands; build the ceded loss ratio calculation per underwriting year | P2 |
| BR-COM-12, 13 | Provisional 25% for three quarters, then adjustment | **Enhance.** `slide_provisional_rate` column exists but the engine does not use it | Provisional-to-final adjustment not implemented | Build quarterly provisional booking and the fourth-quarter adjustment entry, including subsequent quarters | P2 |
| BR-COM-14 | Loss Ratio CAP 75% | None | Complete gap, and the intended meaning is unresolved — see RI-01 open item 5 | Implement once the interpretation is confirmed | P2 |
| BR-COM-02 to 10 | General profit commission — 30%, management expenses 7.5%, 5-year carry-forward, 24-month statement cycle, 20% materiality trigger | **Enhance.** `commissionProfit()` implements the formula and accepts a carried-forward figure | No year-by-year carry-forward store; no statement cycle; no materiality trigger | Build a 5-year underwriting-year result ledger with deficit carry-forward, the 24 and 12 month statement schedule, and the 20% variation trigger | P3 |
| BR-COM-15 | Run-off termination — final commission only after all claims settled | None | Complete gap | Add run-off mode to the commission calculation | P3 |
| BR-COM-16, 17 | Brokerage 2.50%; no other deductions | None | Complete gap | Add brokerage as a statement line and a deduction in settlement | P1 |

### 4.6 Claims

| RI-01 Ref | Requirement | Current Graphite capability | Gap | Development required | Priority |
|---|---|---|---|---|---|
| BR-CLM-01, 02 | Notice of claims thresholds — BWP 500,000, on differing bases | None | Complete gap | Add per-treaty notification thresholds with basis (100% of treaty versus ceded portion) and automatic alerting | P1 |
| BR-CLM-03, 04 | Notification within 30 days with nine specified data items | Claims module holds most of the underlying data | No reinsurance notification record or dispatch | Build a claim notification record, the 30-day clock, and a notification pack containing the nine items | P1 |
| BR-CLM-05, 06 | Cash loss limits — BWP 500,000 General on demand, BWP 250,000 Motor within five working days | None | Complete gap | Build cash loss request tracking with the Annexure A form and the five-working-day clock | P1 |
| BR-CLM-07 | Cash calls refunded in the same quarter, shown as a separate statement line | None | Complete gap | Add cash loss recovery as a distinct statement line item | P1 |
| BR-CLM-08 | Reinsured settles at discretion | Claims settlement exists | None | No development | — |
| BR-CLM-09, 10, 11 | Quarterly outstanding claims register, per year of occurrence and per underwriting year, within six weeks | Claims reserves exist; bordereaux exports exist for direct claims | No reinsurance outstanding claims register on the required dimensions | Build the register with both dimensions, the six-week clock and post-cancellation continuation | P1 |
| BR-CLM-12 | Claims agreement per reinsurer participation | None | Complete gap | Add per-reinsurer claim agreement tracking | P3 |

### 4.7 Accounting and settlement

| RI-01 Ref | Requirement | Current Graphite capability | Gap | Development required | Priority |
|---|---|---|---|---|---|
| BR-ACC-01, 02 | Quarterly statement within 45 days; confirmation within 14 days | **None anywhere in Graphite v2** | Complete gap | Build the statement of account generator and the two contractual clocks as scheduled jobs | P1 |
| BR-ACC-03 | Broken down by share and class of insurance | `splitByReinsurers()` exists for a single amount | No statement structure | Build statement line structure by reinsurer and class | P1 |
| BR-ACC-04 to 08 | Five accounting items — premiums net of returns and inuring premiums; commissions and expenses; claims paid net of salvages and recoveries; outstanding losses by year; cash loss recoveries | None | Complete gap | Build all five line types with drill-down to source transactions | P1 |
| BR-ACC-09 | Settlement — cedant on cheque attached basis at rendering; reinsurer at confirmation | None | Complete gap | Build settlement instruction generation and receipt matching | P1 |
| BR-ACC-10 | Delay interest at 110% of market prime | None | Complete gap | Add a prime rate table and overdue interest accrual | P2 |
| BR-ACC-11 | Off-set of confirmed balances, surviving termination | None | Complete gap | Build a balance ledger per reinsurer with off-set | P2 |
| BR-ACC-12 to 16 | Reserve deposit — 40% premium reserve, interest 2% below average call rate, foreign reinsurers only, letter of credit alternative, release on termination or quarterly on run-off | None | Complete gap | Build a reserve deposit ledger with reinsurer domicile, letter of credit register, an interest accrual job keyed to a call rate table, and release logic | P1 |
| BR-ACC-17, 18 | VAT at the rate current at the transaction; treaty figures VAT-exclusive | None in the reinsurance path | Complete gap | Add VAT treatment to statement lines with a VAT-exclusive base | P1 |
| BR-ACC-19 | Motor repatriation clause | None | Complete gap | Record as configuration; implement if it affects settlement | P3 |
| BR-ACC-20 | Payments through the intermediary — discharge rules differ by direction | None | Complete gap | Model broker as a settlement counterparty with directional discharge rules | P2 |

### 4.8 Underwriting controls and validations

| RI-01 Ref | Requirement | Current Graphite capability | Gap | Development required | Priority |
|---|---|---|---|---|---|
| BR-UWC-01, 02 | Inwards facultative restricted to 25% of treaty limit, with the Choppies, Kamoso and Motovac exception at 100% | None | Complete gap | Add a facultative inwards cap check with a group exception register | P2 |
| BR-UWC-03 | PML acceptances — minimum MPL 50% on referral | None | Complete gap | Add PML basis flag and minimum MPL validation | P2 |
| BR-UWC-04, 05 | Prior facultative permitted to reduce gross commitment, not to protect net retention; net retention excess of loss permitted | None | Complete gap | Add facultative purpose classification | P3 |
| BR-UWC-06 | No change to underwriting practice without written approval | None | Complete gap | Procedural control; add a change log with approval evidence | P3 |
| BR-UWC-07 | Policy period 12 months plus odd time not exceeding 18 months | Policy term data exists | No treaty-level validation | Add policy period validation at bind | P2 |
| BR-UWC-08, 09, 11 | Accidental Damage above BWP 7.5m excluded; BI indemnity periods over 12 months notified at renewal; ALoP above BWP 10m excluded | None | Complete gap | Add quantitative exclusion gates | P2 |
| BR-UWC-10 | Eighteen engineering referral risk categories, including three BWP 10,000,000 value thresholds | None | Complete gap | Add engineering referral rules — automate the value thresholds, present the qualitative categories as a referral checklist | P2 |
| BR-UWC-12 | Fidelity Guarantee non-cumulative liability | None | Complete gap | Add non-cumulative limit check | P3 |
| BR-UWC-13, 14 | SRCC geographic carve-outs and six excluded occupancy classes | None | Complete gap | Add SRCC eligibility rules by risk location and occupancy | P2 |
| BR-UWC-15, 16, 17 | Full exclusion schedules — approximately 90 narrative exclusions across General and Motor | None | Complete gap | **Scope decision.** Automate the quantitative and location-based exclusions only. Present the narrative exclusions as a structured underwriter checklist with attestation and audit trail. Automated adjudication of narrative exclusions is not proposed | P2 |
| BR-UWC-18, 19, 20 | Sanctions LMA3100, Communicable Disease LMA5394, Cyber Loss LMA5411, Institute Cyber Attack LMA5402, RUB and Five Powers | None | Complete gap | Include in the exclusion checklist; sanctions screening is a separate compliance capability | P3 |
| BR-STD-01 | Facultative mandatory above BWP 50,000,000 sum insured (company standard) | None | Complete gap | Hard gate before bind, requiring evidence of placement | P2 |

### 4.9 Security and reinsurer participations

| RI-01 Ref | Requirement | Current Graphite capability | Gap | Development required | Priority |
|---|---|---|---|---|---|
| BR-SEC-01 | Several liability | `splitByReinsurers()` allocates severally and rejects any split not totalling 100% | None | Load participations | P1 |
| BR-SEC-02 to 05 | Written lines as percentage of order; signing provisions, sign-down, disproportionate signing, post-commencement variation | `reinsurer_shares` holds a single `share_pct` column | No written versus signed line distinction; no sign-down calculation | Add written line, signed line, "to stand" flag and a sign-down calculation with variation history | P1 |
| BR-SEC-06 | Participations expressed on two bases — percentage of cession and percentage of 100% | No basis field | **Material.** Misreading the basis misstates every reinsurer allocation by roughly 1.43× | Add an explicit basis field with conversion, and block any participation stored without a basis | P1 |
| BR-SEC-07 | Current participations | Demonstration data only | Real participations not loaded, and do not aggregate to 100% | Load once the complete signing schedule is received | P1 |
| BR-SEC-08 | Every ceded amount allocated per reinsurer and reconciling to the total | **Exists** for a single amount | Not applied across statement lines | Extend the split to every statement line with a reconciliation gate | P1 |
| BR-SEC-09, 10 | Endorsement agreement per participation; modes of execution | None | Complete gap | Add endorsement tracking with execution evidence | P3 |

### 4.10 Reporting, records and data protection

| RI-01 Ref | Requirement | Current Graphite capability | Gap | Development required | Priority |
|---|---|---|---|---|---|
| BR-RPT-01 | IFRS 17 — reinsurance contracts held, ceded premium, amounts recoverable | None | Complete gap | Build ledger mapping and disclosure extracts | P2 |
| BR-RPT-02 | Reporting per treaty, underwriting year, class and reinsurer | `ri_computations` stores per-policy and per-claim results with a breakdown payload | No underwriting year dimension; no aggregation layer | Add the underwriting year dimension and a reporting aggregation layer | P1 |
| BR-RPT-03 | Renewal information pack and statistics | Risk profile and claims profile exports exist | Not aligned to reinsurer requirements | Extend exports to renewal pack format | P3 |
| BR-RPT-04 | Reinsurer inspection rights | Audit trail on `ri_computations` | No inspection extract | Build a read-only inspection extract | P3 |
| BR-RPT-05 | Data protection — POPIA compliance, consent, cross-border transfer, breach notification | Engine operates on policy and claim numbers and amounts only, per company standard | Consent and cross-border transfer records absent | Add consent evidence and transfer logging for any data shared with the broker or reinsurers | P2 |
| BR-RPT-06 | Electronic records at named locations | Records held in Graphite v2 | None | Record as configuration | P3 |

---

## 5. Module map

### Reuse without change

The Quota Share, flat commission, sliding scale and profit commission calculations in `ReinsuranceEngine`; the reinsurer split with its sum-to-100% gate; the reconciliation gate; `treaty_master`; `ri_computations` as a results store; the legacy product-to-coverage allocation chain; the reinsurance CRUD API; the five React admin screens; and `treaty:check`, `treaty:seed`, `TreatyRolloverService` and `BackfillReinsurance`.

### Enhance

| Component | Change |
|---|---|
| `treaty_proportional` | Add event limits, riot and strike sub-limits, loss ratio cap, co-insurance percentage, brokerage rate, VAT treatment and reserve deposit terms |
| `reinsurer_shares` | Add participation basis, written line, signed line, "to stand" flag, reinsurer domicile and variation history |
| `ri_computations` | Add underwriting year, loss occurrence reference and aggregate erosion linkage |
| `policy_reinsurance` | **Migrate money columns from text to `decimal(18,2)`** — currently `totalSumInsured`, `totalPremium`, `treatySI`, `treatyPercentage` and `treatyPremium` are all stored as strings |
| `ReinsuranceEngine` | Add event limit capping, loss ratio cap, aggregate erosion and provisional-to-final commission adjustment |
| Treaty admin screens | Surface every new configuration field |

### Build new

Treaty class limit register (Schedule A); loss occurrence and hours clause engine; annual aggregate erosion ledger; sliding scale band table; underwriting year ledger; **statement of account generator with line detail**; reserve deposit ledger with interest accrual; cash loss register; reinsurance outstanding claims register; reinsurer balance and off-set ledger; settlement instruction and receipt matching; territorial and group extension rule set; special acceptance register; underwriting validation gates with exclusion checklist and attestation; IFRS 17 ledger mapping; and a treaty position dashboard.

### Integrations

| Integration | Purpose |
|---|---|
| Policy issuance — bind, endorsement, cancellation, renewal | Trigger cession; apply capacity, territorial and exclusion gates before bind |
| Claims — registration, reserve movement, settlement, salvage and recovery | Trigger recovery, notification thresholds, cash loss and the outstanding claims register |
| Finance ledger | IFRS 17 postings, VAT, brokerage, settlement and delay interest |
| Broker channel to J.B. Boda | Statement dispatch, confirmation tracking, cash loss requests, outstanding claims register |
| Document store | Annexure A cash loss request forms, signed slips, endorsements, execution evidence |
| Scheduler | 45-day statement render, 14-day confirmation reminder, six-week outstanding claims deadline, reserve deposit interest accrual, delay interest accrual |

**Automation note.** The 45-day statement cycle, the 14-day confirmation reminder, the six-week outstanding claims deadline and the reserve deposit interest accrual are all repeating obligations. They must be generated by the system on a schedule, not produced manually in Excel.

---

## 6. Delivery phases

| Phase | Work | Effort | Priority |
|---|---|---|---|
| 1 | **Treaty data model and configuration.** Extend the schema for event limits, aggregates, class limits, hours clause parameters, commission scale, participations with basis, reserve deposit terms, brokerage and VAT. Load both treaties. | 8–10 days | P1 |
| 2 | **Engine integration and data migration.** Wire the engine into policy issuance and claims. Establish the underwriting year dimension. Decide the authoritative data model. Migrate `policy_reinsurance` money columns from text to decimal. Backfill cessions for business written since 1 July 2026. | 12–15 days | P1 |
| 3 | **Reinsurance technical accounting.** Statement of account generator with all five accounting item types; reinsurer allocation across every line; brokerage; VAT; reserve deposit ledger and interest accrual; cash loss register; outstanding claims register; settlement, balances and off-set; the 45-day and 14-day clocks. | 25–30 days | P1 |
| 4 | **Event limits, aggregates and the hours clause.** Loss occurrence clustering by peril and time window including the 80 km radius test; event limit capping; riot and strike sub-limit; three annual aggregates with erosion tracking; event limit proration across underwriting years. | 15–18 days | P2 |
| 5 | **Commission completion.** Sliding scale band table and ceded loss ratio derivation; provisional-to-final adjustment; loss ratio cap; written versus signed line handling with basis conversion; profit commission underwriting-year ledger with 5-year carry-forward. | 10–12 days | P2 / P3 |
| 6 | **Underwriting controls and validations.** Schedule A class limits with referral; territorial and group extension rules; facultative inwards cap and mandatory facultative gate; PML minimum; policy period; quantitative exclusion gates; structured exclusion checklist with attestation; special acceptance register. | 12–15 days | P2 |
| 7 | **Reporting, IFRS 17 and dashboard.** Ceded premium, amounts recoverable, reinsurance contracts held; reporting by treaty, underwriting year, class and reinsurer; treaty position dashboard; inspection extract. | 10–12 days | P2 |

**Total: 92 to 112 developer-days.**

### Sequencing against the contractual dates

| Milestone | Date | Phases required | Effort |
|---|---|---|---|
| First quarterly statement of account | **14 Nov 2026** | 1, 2, 3 | **45–55 days** |
| First sliding scale adjustment | **14 Aug 2027** | 4, 5, 6, 7 | 47–57 days |
| First profit or loss statement | **2028** | Phase 5 profit commission ledger | Included above |

From today, 14 November 2026 is approximately fifteen weeks, or seventy-three working days. **Two developers deliver the 45 to 55 day critical path with a working margin. One developer does not.**

The profit commission work carries no urgency — the first General profit or loss statement is not ascertained until 24 months from inception. That materially de-risks the plan and should not be pulled forward at the expense of the accounting build.

---

## 7. Risks

| # | Risk | Impact | Mitigation |
|---|---|---|---|
| 1 | **Underwriting year basis is not modelled anywhere.** Both treaties account on underwriting year; Graphite v2 cedes on transaction. | Every premium, claim, commission and statement figure is on the wrong basis. Pervasive rather than localised. | Address in Phase 2, before the accounting build. Do not defer. |
| 2 | **One month of treaty business is already written and unceded.** The treaties incepted 1 July 2026. | The first statement of account will be incomplete. | Run `BackfillReinsurance` against the loaded treaty configuration as part of Phase 2; reconcile the backfill before the statement is rendered. |
| 3 | **Two parallel reinsurance data models.** | The legacy chain and the new engine can produce different ceded figures for the same policy. | Make the engine the single computation authority; retain the legacy chain for product-to-coverage allocation only. |
| 4 | **Money stored as text in `policy_reinsurance`.** | Precision loss and a weak audit position for IFRS 17 reporting. | Migrate to `decimal(18,2)` in Phase 2 with a reconciliation of pre- and post-migration values. |
| 5 | **The 80 km radius test in the General hours clause depends on risk location quality.** | Fire occurrences cannot be clustered correctly where location data is missing or imprecise. | Assess location data completeness in Phase 4; provide manual occurrence assignment as a fallback. |
| 6 | **Loss Ratio CAP interpretation is unresolved** (RI-01 open item 5). | If it caps recoveries rather than commission, the retained loss position on Motor is materially different. | Obtain written confirmation from the Leading Reinsurer before Phase 5. |
| 7 | **Participation basis ambiguity** (RI-01 open item 2). | Misreading "of 100%" as "of cession" misstates every reinsurer allocation by roughly 1.43×. | Block any participation stored without an explicit basis; obtain written confirmation. |
| 8 | Approximately 90 narrative exclusions across the two schedules. | Attempting automated adjudication would consume the schedule and still not be reliable. | Automate quantitative and location-based exclusions only; structured checklist with attestation for the remainder. Confirm this scope decision. |

---

## 8. Blockers

| # | Blocker | Effect | Owner |
|---|---|---|---|
| 1 | **Complete signing schedule not received for either treaty.** | The engine rejects any reinsurer split that does not total 100%. That gate is deliberate and should not be bypassed. Neither treaty can be loaded as live, and no statement of account can be rendered. Phase 1 cannot complete. | J.B. Boda / Reinsurance |
| 2 | **Motor slip not executed by Alpha Direct; General execution date blank.** | Loading unexecuted terms as authoritative is a control weakness. | Operations |
| 3 | **Participation basis not confirmed.** | Blocks Phase 1 configuration of `reinsurer_shares`. | Reinsurance / Broker |
| 4 | **Loss Ratio CAP meaning not confirmed.** | Blocks the Motor commission build in Phase 5. Does not block the first statement, which uses the 25% provisional rate. | CFO / Leading Reinsurer |
| 5 | **No excess of loss or catastrophe programme supplied.** | Reinsurers' liability caps at BWP 70,000,000 per occurrence. A BWP 150,000,000 occurrence cedes 70 million and leaves 80 million net. The engine already supports excess of loss layers and reinstatements if a programme exists. | CFO / Reinsurance |
| 6 | **Open reconciliation query from 18 June 2026.** An excess of loss worked example in the specification does not reconcile; the query to the reinsurance team is unanswered. | Does not block the two Quota Share treaties. Must be closed before any excess of loss layer goes live. | Reinsurance |

---

## 9. Recommendations

**1. Resource two developers and start Phase 1 on receipt of the signing schedule.** The critical path to the first statement of account is 45 to 55 developer-days against approximately seventy-three working days. One developer has no margin against a contractual date; two have a working margin. Every day the signing schedule is outstanding comes off that margin.

**2. Make the verified engine the single computation authority, and fix the underwriting year basis before anything else is built on top of it.** Retain the legacy tables for product-to-coverage allocation only, and migrate the `policy_reinsurance` money columns from text to decimal. Underwriting year is the basis both treaties account on; building the statement of account before it is modelled would mean rebuilding the statement of account.

**3. Sequence to the three contractual dates, not to feature completeness.** Deliver treaty configuration, engine integration and technical accounting by mid-October for the 14 November statement. Deliver limits, commission and underwriting controls against 14 August 2027. Leave profit commission until last — the first General profit or loss statement is not ascertained until 2028.

---

## 10. Governance and acceptance criteria

No reinsurance figure is published unless every one of the following holds.

| # | Gate |
|---|---|
| 1 | The computation reconciles — gross equals retained plus ceded on every proportional record. |
| 2 | Reinsurer participations total 100% of the order and every allocation reconciles to the line total. |
| 3 | The treaty selected matches class of business, territory and underwriting year. Missing or contradictory configuration is flagged, never assumed. |
| 4 | The calculation is in a single currency. |
| 5 | The treaty is not flagged as demonstration data. |
| 6 | The statement of account balances and every line traces to source policy or claim transactions. |
| 7 | CFO sign-off before go-live. |

**Data protection.** The reinsurance engine operates on policy numbers, claim numbers, sums insured and amounts only — no insured names, Omang numbers, bank details or residential addresses. This must not change as the module is extended. Where personal data is shared with the broker or reinsurers under the treaty data protection clauses, consent and cross-border transfer must be evidenced.

**IFRS 17 language.** Reinsurance contracts held; ceded premium; amounts recoverable from reinsurers.

---

*Companion document: RI-01 — Reinsurance Business Requirements Specification. Effort figures are estimates in developer-days and exclude user acceptance testing, reinsurer onboarding and data remediation of pre-existing policy records. This document supersedes the combined draft `reinsurance-implementation-plan-2026-27.md` of the same date.*
