# RI-04 — Treaty-Year Configuration Runbook (legacy tables)

**Alpha Direct Insurance Company (Pty) Ltd**
Companion to RI-02 (Gap Analysis) and RI-03 (Delivery Schedule).
Scope: stand up a **new treaty year** in the live legacy `reinsurance_*` tables — **configuration only, no schema change** — so policies in the new period cede correctly. This is the same mechanism used to wire Motor Traders Internal.

> This covers the **cession split** (Net Retention / Quota Share / Fac). It does **not** deliver the statement of account, underwriting-year basis, participations-with-basis, reserve deposit, brokerage or VAT — those need new tables (RI-02 "Build new"). See RI-02/RI-03.

---

## 1. The mental model — how a cession is selected

```
reinsurance_treaty            (active when effective_from <= TODAY <= effective_to)
  -> reinsurance_treaty_details   (formula_attached)
    -> reinsurance_formula        (s_FormulaType, reinsurance_type_id)
      -> reinsurance_formula_details (group_id, operator, si_allocation, percentage)
        -> reinsurance_group
          -> reinsurance_group_coverage (coverage_id)  --> matches policy coverages
```

Two rules that matter for a year change:

1. **The treaty is chosen by *today's* date** (`Carbon::now()`), not the policy/action effective date. So the new year is switched on purely by `effective_from`/`effective_to`.
2. **The cession split lives in `reinsurance_formula_details.percentage`** (a Net-Retention formula at, say, 30% and a Quota-Share formula at 70%) — **not** in the treaty's `proportional_share` text column. The legacy cession math I traced (`MotorComTradersReinsurance`, etc.) reads the *formulas*; the treaty's `proportional_share` / `provisional_commission` / `event_limit` / `exclusions` are text scalars that the legacy calc does not read (informational there).

---

## 2. The one non-negotiable — no date overlap per class

The active COM treaty (id 17, `MUNICH_COM_2024_2025`) runs to **2026-11-11**. If you insert a new treaty effective **2026-07-01** without closing id 17 first, **both** match "today" and the policy **cedes twice**.

> **Always close the outgoing treaty's `effective_to` to the day before the new one incepts, before inserting the new treaty.** Verify afterwards that exactly one treaty is active per class for any date in range.

---

## 3. ID assignment (verified 2026-07-28)

| Table | Auto-increment? | Action |
|---|---|---|
| `reinsurance_treaty` | **No** | assign explicit id — next = **32** |
| `reinsurance_treaty_details` | **No** | assign explicit ids — next = **527** |
| `reinsurance_formula` | Yes | omit id |
| `reinsurance_formula_details` | Yes | omit id |
| `reinsurance_group` | Yes | omit id |
| `reinsurance_group_coverage` | Yes | omit id |

Re-check the maxes at run time (`SELECT MAX(id) ...`) in case rows were added since.

---

## 4. Steps

### Step 0 — gather the real terms (blocked)
From the signing schedule: cession %, commission %, layer allocations, and reinsurer participations. Until confirmed (RI-02 Blockers 1–3), treat any values below as **provisional placeholders**. Participations load separately (Step 6).

### Step 1 — close the outgoing treaty
```sql
UPDATE reinsurance_treaty SET effective_to = '2026-06-30', updated_at = NOW() WHERE id = 17;
```

### Step 2 — create the new treaty row
```sql
INSERT INTO reinsurance_treaty
  (id, treaty_name, treaty_number, status, effective_from, effective_to,
   created_at, updated_at, added_by,
   provisional_commission, proportional_share, cash_loss_advise, event_limit, exclusions)
VALUES
  (32, 'MUNICH_COM_2026_2027', '<slip number>', 1, '2026-07-01', '2027-06-30',
   NOW(), NOW(), '<user>',
   '<prov comm %>', '<cession %>', '0', '<event limit>', '<exclusions>');
```
(The text config columns are informational for the legacy calc — the math comes from the formulas.)

### Step 3 — formulas: reuse or clone
- **Option A — terms unchanged.** Reuse the existing formulas; just attach them to the new treaty in Step 4. Simplest and lowest-risk.
- **Option B — terms changed** (e.g. new split or new layers). Clone the relevant `reinsurance_formula` rows with a `-2627` suffix on `formula_code`/`formula_name`, then set the new `percentage`/`si_allocation` in cloned `reinsurance_formula_details` rows. Both tables are auto-increment now, so omit `id`. (This is exactly the pattern used for the Motor Traders Internal formulas 63–66.)

### Step 4 — attach the formulas to the new treaty
One `reinsurance_treaty_details` row per formula the treaty covers (explicit ids):
```sql
INSERT INTO reinsurance_treaty_details (id, treaty_id, formula_attached, created_at, updated_at)
VALUES
  (527, 32, <formula_id_1>, NOW(), NOW()),
  (528, 32, <formula_id_2>, NOW(), NOW());
-- ... one per formula
```

### Step 5 — group / coverage mappings
Reuse the existing `reinsurance_group` + `reinsurance_group_coverage`. If you took **Option A**, the `formula_details` already point at the right groups — **nothing to do here**. Only add/clone group_coverage rows if the *set of covered coverages* changed for the new year.

### Step 6 — participations (`reinsurer_shares`) — separate, blocked
`reinsurer_shares` is currently **empty (0 rows)**. Load it only when the signing schedule totals 100% (RI-02 Blocker 1). Not required for the Net/QS split table; **required** for per-reinsurer figures and the statement of account.

---

## 5. Verify

```sql
-- (a) Exactly ONE active treaty per class for a chosen date
SELECT id, treaty_name, effective_from, effective_to
FROM reinsurance_treaty
WHERE status = 1 AND effective_from <= '2026-08-01' AND effective_to >= '2026-08-01';

-- (b) New treaty is reachable through the whole chain
SELECT tm.treaty_name, fm.formula_code, gm.group_code, gd.coverage_id
FROM reinsurance_treaty tm
JOIN reinsurance_treaty_details td ON td.treaty_id = tm.id
JOIN reinsurance_formula fm        ON fm.id = td.formula_attached
JOIN reinsurance_formula_details fd ON fd.formula_id = fm.id
JOIN reinsurance_group gm          ON gm.id = fd.group_id
JOIN reinsurance_group_coverage gd ON gd.group_id = gm.id
WHERE tm.id = 32
ORDER BY fm.id, gd.coverage_id;
```
Then recalculate a test policy dated in the new period and check `policy_reinsurance` for correct Net/QS figures.

---

## 6. Applying safely

Your MySQL client points at the **read-only replica**, so run changes through a **guarded PHP script on the writable master** (`graphite-test-write`, user `graphitebwlive`) — the pattern proven this session (`wire_internal.php`): **DRY-RUN by default**, print the exact statements and a **rollback block**, then re-run with `APPLY` inside a transaction (all these tables are InnoDB). Never leave the outgoing treaty open while the new one is live.

---

## 7. Bottom line

- **Reproduce the 2026/27 cession numbers → configuration only, these tables, no schema change.**
- **Get the effective dates right** — close the old treaty, no overlap — or it double-cedes.
- **The statement of account and treaty-wording features are out of scope here** — new tables (RI-02).
