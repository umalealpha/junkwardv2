# RI-05 — 2026/27 Treaty & Formula Configuration Spec (manual)

**Alpha Direct Insurance Company (Pty) Ltd** · Companion to RI-04 (runbook).
Source of truth: `docs/Alpha-Direct-Capacities-Table-2026-27.xlsx` (built from the two signed 2026/27 slips).

## The shape of the change

- **One treaty row**, effective **2026-07-01 → 2027-06-30**, **plain Quota Share, 30% retention / 70% cession** for every class — exactly mirroring the single `MUNICH_COM_2024_2025` row, which held all commercial classes in one treaty.
- **Capacities are the same as 24/25.** The only per-formula edits are: **Net-Retention % → 30, Quota-Share % → 70**, and **drop all Surplus / AutoFAC / Facultative-Placement layers** (the 26/27 treaties provide none — Capacities Notes 2 & 3).
- **Coverage grouping is reused** — no group changes needed for the mapped classes.

> **Why one treaty row, not two.** Contractually 26/27 is two placements (General, leader FM Re; Motor, leader Continental Re). But the cession split lives in the *formulas*, and the calc only cares which treaty is active by date — one row computes every class correctly, just as the single 24/25 commercial row did. The General-vs-Motor split only matters when you produce the **two separate statements of account** (different reinsurers, commissions, settlements) — an accounting-layer concern built on new tables later (RI-02), not a cession-calc requirement. The `General` / `Motor` labels in §1 below are kept only to tag which contract each class belongs to for that later work.

## Order of operations

1. Build the new **formulas** (clone the 24/25 ones, two edits each).
2. Create the **one treaty** and attach all its formulas.
3. **Close** the 24/25 treaty (`effective_to = 2026-06-30`) so nothing double-cedes.
4. Recalculate a test policy and check `policy_reinsurance`.

---

## 1. Formulas to create

For each class, create **two** formulas by duplicating the 24/25 originals, changing only **percentage** and the **code/name** (append `-2627`). SI allocation stays as shown. **Do not recreate** the "drop" formulas.

### General-treaty classes (product 7) — all attach to the one 2026/27 treaty

| Class | Group | SI allocation | Net-Retention (clone → set %30) | Quota-Share (clone → set %70) | Drop (do NOT create) |
|---|---|---|---|---|---|
| Property MD+BI | `PROPERTYANDBI_COM` (19) | 10,000,000 | clone f#3 | clone f#2 | f#4 Surplus, f#32 Fac, f#33 FacPlacement |
| Electronic Equipment | `ELECTRONIC_EQ_AND_BI_COM` (4) | 6,000,000 | clone f#5 | clone f#6 | f#34 FacPlacement |
| Goods in Transit | `GOODSINTRANSIT_COM` (8) | 3,000,000 | clone f#7 | clone f#8 | f#35 FacPlacement |
| Misc & Financial Loss | `MISC_COM` (10) | 1,000,000 | clone f#20 | clone f#21 | f#22 AutoFAC |
| Fidelity Guarantee | `FIDELITYG_COM` (7) | 1,000,000 | clone f#9 | clone f#10 | f#36 FacPlacement |
| Accidental Damage | `ACCIDENTAL_DAMAGE_COM` (1) | 7,500,000 | clone f#11 | clone f#12 | f#37 FacPlacement |
| Engineering combined ⚠ | `ENGINEERING_AND_BI_COM` (6) — **empty** | 10,000,000 | *no 24/25 template* | *no 24/25 template* | — |

### Motor-treaty classes (product 7) — all attach to the same 2026/27 treaty

| Class | Group | SI allocation | Net-Retention (%30) | Quota-Share (%70) | Drop |
|---|---|---|---|---|---|
| Own Damage — vehicle | `MOTOR_COM` (14) | 5,000,000 | clone f#13 | clone f#14 | f#15 AutoFAC, f#43 FacPlacement |
| Own Damage — trailer | `MOTOR_TRAILERS_COM` (28) | 1,500,000 | clone f#45 | clone f#46 | f#47 AutoFAC, f#48 FacPlacement |
| Third Party Damage ⚠ | `MOTOR_TRADERS_COM_EXT` (13) | 10,000,000 | clone f#16 | clone f#17 | f#18 AutoFAC, f#44 FacPlacement |
| Third Party Damage ⚠ | `MOTOR_TRADERS_COM_INT` (30) | 10,000,000 | clone f#63 | clone f#64 | f#65 AutoFAC, f#66 FacPlacement |
| Passenger Liability ⚠ | *no populated group* | 2,500,000 | *new* | *new* | — |

**Per-formula field values** (Net-Retention example): reinsurance type **Net Retention**, formula type **TSI**, operator **× (multiply)**, **percentage 30**, SI allocation = class capacity, group = class group, product 7. Quota-Share: reinsurance type **Quota Share**, everything else identical, **percentage 70**. (Every value except percentage and code/name matches the 24/25 original — that's why cloning is safest.)

---

## 2. Treaty to create

**One** treaty row, mirroring the single 24/25 commercial treaty and holding **all** the §1 formulas (both General- and Motor-class):

| Field | Value |
|---|---|
| treaty_name | `ALPHA_QS_2026_2027` (or keep your `MUNICH_COM`-style convention) |
| treaty_number | *(from the slip)* |
| effective_from / to | 2026-07-01 / 2027-06-30 |
| status | Active |
| proportional_share | 70 |
| provisional_commission | *(informational)* |
| event_limit | 70,000,000 |
| exclusions | *(informational)* |
| Formulas attached | **all** formulas from §1 (General + Motor classes) |

The text config columns are informational — the cession math comes from the formulas. Brokerage 2.50% applies to both contracts (Capacities Note 9); commission is General flat 37.5% / Motor provisional 25% — both belong to the statement layer, not this row.

> If domestic (product 8) is also renewing, add a **second** row only to mirror the existing `MUNICH_DOM` split — i.e. one row per *product*, exactly as 24/25 did — not one row per General/Motor contract.

---

## 3. Close the 24/25 treaties

Set `effective_to = 2026-06-30` on the outgoing treaties so exactly one treaty is active per class from 1 July:

- Treaty **17** `MUNICH_COM_2024_2025` → `effective_to = 2026-06-30`.
- Treaty **16** `MUNICH_DOM_2024_2025` — already expired by date (2025-11-11); close only if it should stay retired.

> **Critical:** if treaty 17 stays open past 1 July while the new treaties are live, every commercial policy **cedes twice**. Verify with:
> ```sql
> SELECT id, treaty_name, effective_from, effective_to FROM reinsurance_treaty
> WHERE status = 1 AND effective_from <= '2026-08-01' AND effective_to >= '2026-08-01';
> ```

---

## 4. Classes deliberately NOT ceded under 26/27

Not in either Schedule A (Capacities Note 4) → **net retention only, no formulas**:

- Public Liability, Workers Compensation, Personal Accident, Stated Benefits.

This matches the earlier removal of `GROUPPERSONALACCIDENT_COM` from the treaty and WC/Stated-Benefits not reinsuring — correct for 26/27.

---

## 5. Open items — resolved from coverage data (2026-07-28)

| Item | What the data shows | Resolution |
|---|---|---|
| Third Party Damage (10M) | Standard-motor `THIRD PARTY LIABILITY` coverages exist (master ids 204/206/238/251/366/388), **separate** from motor-traders (15/16). Legacy `MOTORTHIRDPARTYLIABILITY` formulas feed **only** the Motor Traders groups. | **Underwriting decision (validation session):** does "Third Party Damage 10M" cede standard-motor TPL, the motor-traders groups, or both? For test, keep the motor-traders mapping; flag that standard TPL is not currently ceded under a 10M class. |
| Passenger Liability (2.5M) | Only `PASSENGER LIABILITY EXCLUSION` (365/387) exists — an exclusion, no positive coverage; nothing grouped. | **Leave unconfigured.** Confirm with underwriting it is subsumed in comprehensive motor, not a standalone ceded class. |
| Engineering (10M) | `CONTRACTORSALLRISKS` (5001) and `ERECTIONALLRISKS` (5003) exist but sit in **no** RI group; `ENGINEERING_AND_BI_COM` is empty. | **Map CAR + EAR (+ machinery/ALoP if present) to `ENGINEERING_AND_BI_COM` at 10M, 30/70** — or fold into Property (same 10M). Configurable now; confirm the coverage list. |
| Product 8 (DOM) | Treaty 16 ceded DOM property/electronic/misc/motor/trailers (+ PA/WC). 26/27 General covers Personal Lines. | **In scope** — replicate §1's 30/70 for `PROPERTYANDBI_DOM`, `ELECTRONICEQANDBI_DOM`, `MISCANDFG_DOM`, `MOTOR_DOM`, `MOTOR_TRAILERS_DOM`. **Drop PA/WC DOM** (excluded). |

**Configurable now:** all General + Motor classes in §1, the Engineering map, and the five Product-8 DOM groups.
**Goes to the validation session:** Third Party Damage mapping and Passenger Liability treatment.

---

## 6. Verify after configuring

```sql
-- new treaty reachable through the whole chain
SELECT tm.treaty_name, fm.formula_code, gm.group_code, fd.percentage, fd.si_allocation
FROM reinsurance_treaty tm
JOIN reinsurance_treaty_details td ON td.treaty_id = tm.id
JOIN reinsurance_formula fm        ON fm.id = td.formula_attached
JOIN reinsurance_formula_details fd ON fd.formula_id = fm.id
JOIN reinsurance_group gm          ON gm.id = fd.group_id
WHERE tm.treaty_name = 'ALPHA_QS_2026_2027'
ORDER BY gm.group_code, fm.id;
```
Then recalculate a test policy dated after 2026-07-01 and confirm every class shows **30% net retention / 70% quota share** and no surplus/fac columns.
