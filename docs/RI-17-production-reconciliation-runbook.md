# RI-17 — Running the cession reconciliation against production

**Alpha Direct Insurance Company (Pty) Ltd · Gaborone**
Prepared by Snehal Zunjarrao, Developer, Graphite v2 · 3 September 2026
Treaty year 1 Jul 2026 – 30 Jun 2027

---

## Why this exists

Every reinsurance figure quoted so far comes from the test server, which holds
**nine policies in the 2026/27 treaty year** and one of them is 98% of the value.
That is enough to prove the engine works. It is not enough for anyone to sign.

This runbook produces the same output against production. It is written so that
whoever holds production access can run it without knowing anything about the
reinsurance work — the developer who built it cannot reach that environment.

## What it does and does not change

| | |
|---|---|
| **Reads** | `policy_reinsurance`, `policy_reinsurance_details`, `policies`, `policy_actions`, `policy_term`, `reinsurance_group`, `reinsurance_group_coverage` |
| **Writes** | `policy_reinsurance_regulatory` only — a new table, created by step 2 |
| **Never touches** | `policy_reinsurance`. The live cession is unchanged by every step here |
| **Never switches** | The cession basis. `RI_CESSION_ENGINE` stays unset, which means `legacy` |

**Step 4 is the only step that writes anything.** Everything else reads.

---

## Before you start

- Production database credentials, and a shell on a host that can reach it.
- Roughly 20 minutes of runtime for a book of a few thousand policies. Step 4
  is the slow one.
- **Do not set `RI_CESSION_ENGINE`.** If it is already set to `regulatory`
  in the production environment, stop and say so — that would mean the basis
  has been switched without the sign-off, and nothing below should run until
  that is understood.

---

## Step 1 — Confirm which database you are on

```bash
php artisan reinsurance:regulatory-cession --check-write
```

Expect the host, database name and `read_only: 0`, then `WRITABLE — a test
write succeeded and was rolled back`.

**Check the host and database name are production.** This is the only guard
against running the whole sequence against the wrong server; the command reports
what it is connected to rather than trusting a name.

If it reports `REPLICA — this server will reject writes`, you are on a read
replica. Steps 1, 3, 5 and 6 still work. Step 4 does not.

---

## Step 2 — Create the table the regulatory basis is stored in

```bash
php artisan migrate --path=database/migrations/2026_09_01_120000_create_policy_reinsurance_regulatory_table.php
php artisan migrate --path=database/migrations/2026_09_01_130000_fix_regulatory_grain_to_include_group.php
```

Two migrations, in that order. The second corrects the unique key on the first:
a risk address can carry two reinsurance groups of the same regulatory class,
and the original key did not allow for it. Running only the first will fail four
actions in every hundred at step 4.

Both are additive. Neither alters an existing table.

---

## Step 3 — Look at the data before computing anything

```bash
php artisan reinsurance:regulatory-cession --diagnose
```

Two things to read out of this, both expected:

**Staged rows repeat.** On the test server they repeat 114.6 times — 408,724
rows over 3,567 distinct units. The regulatory read takes the maximum per unit
so it is unaffected; the legacy chain sums them, which is why the two bases
disagree about a policy's sum insured. **Record the factor production reports.**
It is a data question for Operations, not a blocker here.

**Group classes.** Expect *"Every group carries exactly one regulatory class"*.
If any group holds two, its risks cannot be routed and step 4 will allocate them
wrongly. Stop and report it.

---

## Step 4 — Store the regulatory allocation · THE ONLY WRITING STEP

```bash
php artisan reinsurance:cession-reconciliation --persist --csv=../docs/RI-16-production.csv
```

Defaults, all of them deliberate:

- **Treaty year 2026/27 only.** Scoped on term overlap, so a policy incepting
  1 March 2027 is included for the part of its term inside the year. Add
  `--all-years` only if someone asks for it explicitly — earlier figures were
  wrong because 39 of 48 policies came from other treaty years.
- **Test policies excluded.** `--include-test` brings them back, and a book
  inflated by demonstration policies is not a figure anyone should sign.

It is idempotent. Re-running replaces each action's rows inside one transaction
rather than appending, so a run interrupted halfway can simply be run again.

**Keep `docs/RI-16-production.csv`.** It is the per-policy detail Reinsurance checks.

---

## Step 4b — Remove anything stored outside the treaty year

```bash
php artisan reinsurance:cession-reconciliation --prune
```

Only needed if step 4 was ever run without the treaty-year scope, but harmless
either way — it reports *"nothing to prune"* when there is nothing.

**This is not housekeeping.** On the test server the table had been populated
before the scope existed and held 48 actions of which 9 belonged to 2026/27. The
other 39 were allocated on this year's terms against policies written under
different ones, and on the regulatory basis the facultative coverage gap read
them as live: it reported **793,000,000 of facultative required on
COMG2025146236, whose term ran 1 to 31 March 2025**. Step 5 will refuse to pass
while any remain.

---

## Step 5 — Confirm nothing was missed

```bash
php artisan reinsurance:cession-reconciliation --cutover-check
```

Expect *"Every staged action has a regulatory allocation"*.

If it lists actions that would report nil, run step 4 again and re-check. If the
same actions persist, one of them may have staged reinsurance rows against a
policy that no longer exists — the test server has exactly one such case, action
41916, with 63 staged rows and no row in `policy_actions`. Report those; do not
try to fix them here.

---

## Step 6 — Read the result

The reconciliation prints the book on both bases and **no delta between them**,
deliberately. What the legacy chain stores is the total it *allocated* across
every layer and every formula — the retained leg alongside the ceded one, counted
once per formula — so it is not a cession and cannot be subtracted from one. The
two columns sit side by side and are labelled for what each is.

What to send on:

1. The summary table — sum insured, regulatory cession, legacy allocated.
2. The by-class breakdown.
3. `docs/RI-16-production.csv`.
4. The duplication factor from step 3.

**Accident showing nil ceded is correct**, not a gap. It is absent from
Reinsurance's own Allocation Rules table A, and their step 2 makes anything
absent 100% retained with an exception raised.

---

## What must not happen without the routing sign-off

Setting `RI_CESSION_ENGINE=regulatory` switches what the whole system reports as
ceded. Every read site now follows that flag, so it takes effect everywhere at
once. It is not part of this runbook and must not be done on the strength of it.

The sign-off is item 2 of the outstanding list in RI-13.

---

## If something goes wrong

Nothing here changes the live cession, so there is no rollback to perform.

- **Step 4 fails partway** — re-run it. Each action is replaced in its own
  transaction.
- **You want the table gone** — `php artisan migrate:rollback --step=2` drops
  `policy_reinsurance_regulatory`. Nothing else references it while the basis
  is `legacy`.
- **Figures look implausible** — send them anyway, with the duplication factor
  from step 3. Two of the three surprises found on the test server turned out to
  be real conditions in the data rather than faults in the engine.
