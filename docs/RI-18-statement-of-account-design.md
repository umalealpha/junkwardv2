# RI-18 — Quarterly statement of account: model and build order

**Alpha Direct Insurance Company (Pty) Ltd · Gaborone**
Prepared by Snehal Zunjarrao, Developer, Graphite v2 · 3 September 2026
Treaty year 1 Jul 2026 – 30 Jun 2027

---

## The deadline, and what actually has to be true by it

The treaties incepted 1 July 2026. The first quarter closed 30 September.
Accounts are due 45 days later — **14 November, a Saturday, so Friday 13
November**. That is about **50 working days** from today for a build estimated at
20–25.

The margin is comfortable. It stops being comfortable if the two things this
build cannot supply itself are still outstanding in October.

---

## What this cannot do without someone else

**1. It cannot be rendered to reinsurers without the signing schedule.**
BR-ACC-03 requires the statement broken down by share, and BR-SEC-08 requires
every ceded amount — premium, commission, claim recovery, cash loss, reserve
deposit, balance — allocated to each reinsurer in proportion to its
participation, reconciling to the total. Neither panel reaches the cession:
General is 34.00 points short, Motor 33.10.

So the statement can be **computed, checked and agreed internally** without it.
It cannot be **issued**. That shapes the build order below: everything that does
not need the panel comes first, and the per-reinsurer split is last.

**2. The Motor reserve deposit has no stated terms for foreign reinsurers.**
BR-ACC-12 gives General 40.00% premium reserve at 2.00% below the average call
rate. The Motor slip says only "Domestic Reinsurers – Nil", yet Article 9
provides for a deposit against reinsurers not domiciled in Botswana — and GIC Re
South Africa is foreign. No percentage, no rate. RI-01 open item 7.

**Answered 7 September 2026:** Reinsurance ruled that the reserve deposit applies
to the GQS treaty, so Motor retains nil and open item 7 is closed. Both ledgers
compute — General on BR-ACC-12's 40%, Motor at a stated nil.

**3. Delay interest needs a rate feed.** BR-ACC-10 sets 110% of the market prime
lending rate. The multiple is a treaty term and is in config; the prime rate
moves and is not ours to invent. It needs either a stored rate with an effective
date, keyed by whoever maintains it, or a manual entry per statement.

---

## Where the numbers come from

Every accounting item resolves to data that already exists. Nothing here needs a
new capture screen.

| Accounting item | Source | Notes |
|---|---|---|
| BR-ACC-04 Written premium ceded | `CessionSource::totalsFor()` | Follows the live basis. Less returns and cancellations — a negated endorsement is already a negative cession |
| BR-ACC-05 Commissions and expenses | `config('reinsurance.terms')` | General 37.5%, Motor provisional 25%, surplus 30%. Brokerage 2.50% is a deduction, not a commission |
| BR-ACC-06 Claims paid less salvages and recoveries | `claim_reserves_coverages` | `payment_amt` less `salvage_payment` less `subrogation_payment`, excluding `is_payment_voided` |
| BR-ACC-07 Outstanding losses | `reserve_amt` less `payment_amt`, both COALESCEd | Split by **year of occurrence** for General, **underwriting year** for Motor. Not the same axis — see below. **The General axis is blocked**: `claims.incident_date` is populated on 4 of 3,550 claims |

### The first end-to-end run — WITHDRAWN AS A FIGURE

An earlier version of this section presented General 2025 Q3 as "the first complete account on the real book", with a balance of 213,740.82. **That figure is withdrawn. It is not a sound account and must not be quoted.**

It was arithmetically consistent and it mixed two cession bases in one statement:

| Line | Basis it actually came from | Population |
|---|---|---|
| Premium 384,858.87 | `CessionSource` → whatever `reinsurance.engine` says → **legacy** | 50 actions |
| Claims 17,174.50 ceded | `policy_reinsurance_regulatory`, read **directly** | 9 actions |

`StatementPremiumBuilder` goes through `CessionSource` and so follows the configured basis. `StatementClaimsBuilder` and `StatementOutstandingBuilder` query the regulatory table directly and so are always on the regulatory basis regardless of configuration. A statement built today therefore draws its premium from the whole legacy book and its claims from the nine actions staged under the regulatory mapping. The two sides are not the same population and the balance between them means nothing.

The clue was visible in the run itself — premium covering the entire book against claims covering almost none — and was not interrogated.

**The account still foots**, by two independently computed routes, and that is the point worth keeping: footing proves the arithmetic, not the inputs. A statement can add up perfectly and still be built from two incompatible sources, which is exactly what happened here.

No figure from this build should be quoted until all three builders read the same basis through `CessionSource`, and until the regulatory basis is staged across the book by RI-17's production run. As at 4 September 2026 `reinsurance.engine` is `legacy`, `policy_reinsurance_regulatory` holds 52 rows over 9 actions, and no cutover has been run.

### Cash loss — BR-ACC-08, half buildable

**A cash loss is money we were entitled to ask for early.** Above the limit the Reinsured may demand payment rather than wait for the quarterly account — on demand for General provided Annexure A is completed (BR-CLM-05), within five working days for Motor (BR-CLM-06). A breach nobody acted on is the reinsurer's share of a large claim financed out of our own cash for up to a quarter and a half: 45 days to render, 14 to confirm.

**The two treaties measure it differently**, and it is the same distinction the participation basis draws. General's 500,000 is set "for 100% of the treaty", so it is tested against the **gross** claim; Motor's 250,000 is "for the ceded portion", so it is tested against what reinsurers actually owe. Testing both on one basis would either miss Motor breaches or invent General ones — a 300,000 Motor claim ceding 70% is 210,000 and entitles us to nothing.

**Entitlements are computable; recoveries are not.** BR-ACC-08 puts cash loss *recoveries* on the account — cash already received, deducted so a reinsurer is not billed twice for the same claim. Nothing records that a demand was made or paid: there is no cash loss register, the same gap RI-02 records against the letter-of-credit register. So the statement carries no recovery line, because inventing one would credit reinsurers with money they never sent.

**It has never fired on real data, and cannot yet.** Eighty-five claims on the book exceed 250,000 gross, the largest at 2,024,155.19 — but the largest claim that reaches a *cession* is 23,991.47 gross and 13,779.33 ceded. The big claims sit on policies with no regulatory cession staged; only 52 regulatory rows exist. Cash loss detection therefore depends on the regulatory basis being run across the book, which is RI-17's production run.

### Delay interest, and which due date it runs from

BR-ACC-10 charges 110% of the market prime lending rate on overdue balances, from due date to date of payment. **Which due date depends on who owes**, and BR-ACC-09 settles the two directions differently: the cedant pays on a cheque attached basis *at the same time the accounts are rendered*, so a balance due **to** reinsurers runs from the render date; reinsurers pay *at the same time the accounts are confirmed*, so a balance due **from** them runs from the confirmation date. One date for both would either charge us interest from a day we owed nothing, or let a reinsurer hold our money for the 45 days we had to prepare the account.

**Interest is never charged on interest, and the first version did.** `interestFor()` read `balance()`, which by then included the interest line the previous run had written — a rebuild charged 545.41 where the first run charged 542.47, compounding a rate BR-ACC-10 states as simple. The base now excludes the delay-interest line explicitly, so it is right regardless of call order. The docblock had claimed this was impossible; the test disagreed.

**The rate is effective-dated, not a single figure.** Interest runs across a period and a rate that moved inside it changes the answer; one "current rate" would apply today's number to last quarter's delay. **Empty means unpriceable, not zero** — an overdue balance with no published rate is reported as overdue and uncomputable, because accruing zero on it reads as *paid on time*, which is the opposite of the truth.

Day count is **actual/365**. Neither the slips nor Article 10.5 state a convention; this is the Botswana money-market norm and the easier of the candidates to check by hand. 360 would pay about 1.4% more. A convention, recorded as one.

### The Motor reserve deposit

**RI-01 open item 7 was closed on 7 September 2026: the reserve deposit applies to the GQS treaty, so Motor retains nil.** The terms stay `null` in config, and **null now means nil rather than refuse** — `termsFor()` coalesces Motor's `retained_pct` to `0.0`, so the builder produces a nil deposit instead of declining.

Until the ruling it declined, and that was right at the time: zero would have asserted that no deposit was due, which the Motor slip does not say, and General's 40% by analogy would have retained real money on terms nobody agreed. The refusal in `depositsFor()` survives for a **third treaty** arriving with no terms, which is what it was really guarding.

**The ruling is a default, not a ceiling.** The builder reads its percentages, margin and call rate **per treaty** rather than hardcoding General's, so stated Motor terms still override the nil — pinned by a test that configures 30% and gets 30,000 on a 100,000 base. The first cut of that change hardcoded the zero past the config and the test caught it: a config knob that does nothing is worse than none.

### Per-reinsurer allocation

**The arithmetic problem is rounding, not percentages.** BR-SEC-08 wants every ceded amount allocated in proportion to participation *and* reconciling to the total, and those two fight each other: 100.00 split three ways at 33.333% rounds to 33.33 three times and comes to 99.99. The missing cent is a statement that does not foot, and it is the reinsurer who finds it.

So the cents are **dealt, not multiplied** — every share is floored to a whole cent by the largest-remainder method and the leftover cents go to the largest fractional parts. The allocation sums to the item by construction. Ties break on the earlier participation, so the same panel always produces the same split: an allocation that reshuffled cents between rebuilds could not be reconciled against a copy already issued. The sign is carried separately, because flooring a negative moves it away from zero and would over-credit a commission.

**It refuses a short panel, and that refusal is the step.** General is 34.00 points short of the cession and Motor 33.10. Allocating anyway would either scale the placed reinsurers up — handing them business they never signed for — or leave a gap making the statement disagree with itself. `previewFor()` shows the shape and names the shortfall without writing a row, so it can be checked now and written the day the schedule lands.

Memorandum items are not allocated: outstanding losses report a reserve position rather than money moving, and BR-SEC-08 is about what settles.

### The rendered document

The account renders through the house PDF facade, the same call `FacSlipService` makes, and **fails rather than falling back to HTML** for the reason recorded there: an earlier version of that service stored raw HTML at a `.pdf` path, so a reinsurer would have been emailed a contractual document no reader could open, and the register would have recorded it as sent.

Two refusals and one declaration:

- **An account that does not foot is not rendered at all.** `summaryFor()` computes the settling total and the balance by different routes on purpose; when they disagree no document is produced for anyone to send.
- **"Cannot be issued" is not "cannot be produced."** Without the signing schedule the figures are right and the per-reinsurer allocation is absent — precisely the document somebody sends by mistake. So it is produced, because the quarter still has to be checked internally, and it declares itself **INTERNAL WORKING COPY — NOT FOR ISSUE TO REINSURERS** in a bordered panel in the brand's orange, with the reason beside it.

**Everything printed must be ASCII, and this is not a style preference.** The PDF facade tries wkhtmltopdf first and falls back to DomPDF, and the two disagree on Unicode: wkhtmltopdf prints an em dash, DomPDF prints a replacement character. The same code therefore yields a clean document on a box with wkhtmltopdf and a corrupted one on a box without — found by extracting the text of a real render, where the title read `GENERAL QUOTA SHARE ? STATEMENT OF ACCOUNT`. Two tests pin it: one scans the template, and one decodes HTML entities first, because `&bull;` is ASCII in the file, expands to U+2022, and survived the first fix as a single mojibake byte.

**The same eight em dashes are in `fac-slip.blade.php`, which is already in production.** That template renders through wkhtmltopdf where it is installed, so the slips issued to date are clean — but any environment falling back to DomPDF produces a corrupted contractual document, silently. Not changed here: it is a production template with its own decision to make.

### The clocks, and the gap they exist to close

`treaty:statement-clocks` runs daily at 07:30 and reports three things. Two are deadlines: BR-ACC-01's 45 days from the quarter's close to rendering, and BR-ACC-02's 14 days from rendering to confirmation. **The third is the one no query over the statements table can see** — a quarter that closed and has no statement at all. It cannot be overdue, because nothing is looking at it, and it reads exactly like a quarter that has not closed yet. So the quarters are enumerated from the calendar and compared against what exists, rather than the other way round.

**It reports; it does not render.** Rendering an account sends figures to reinsurers and is somebody's decision, not a scheduler's — nothing here moves a statement's status. `--open` will create the missing draft shells, because an empty draft commits nothing and puts the quarter where the rest of the process can see it, and the scheduled run does not pass it.

Three details that decide whether the clocks are right:

- **The confirmation clock cannot start before the account is rendered.** Reinsurers confirm "following receipt", so an unrendered statement has *no* confirmation deadline — which is not the same as meeting one, and the two must not read alike.
- **A disputed statement stays on the clock.** Raising an objection is what BR-ACC-02 provides for, but it does not settle the account; treating a dispute as done would drop it out of every report while the money stays unpaid. Only *confirmed* and *settled* leave.
- **The job always exits success.** A late statement is news, not a failed job — a scheduler that reports failure every night until somebody renders an account trains people to ignore it. Lateness goes to the log at warning level instead.

Daily rather than weekly because BR-ACC-10 charges interest at 110% of prime from the due date: a deadline missed by a week is a week of interest nobody meant to incur.

**As at 3 September 2026 nothing is due.** Q1 2026/27 closes 30 September and the first account is due **14 November 2026**.

### The reserve deposit

**A deposit is decided by the reinsurer, not by the business.** BR-ACC-14 retains one only against reinsurers *not domiciled in Botswana*; BR-ACC-13 retains none at all where a letter of credit or irrevocable guarantee is on file. Two reinsurers on the same cession can attract 40%, nil and nil for different reasons, so the ledger is per reinsurer and each nil records **which kind of nil it is** — a domicile does not lapse and a letter of credit does.

**It accumulates; it does not roll.** BR-GOV-12 is "no portfolio entry and no portfolio withdrawal", so there is no annual portfolio transfer to release the reserve against, and BR-ACC-16 releases it on termination — or quarterly once the portfolio is run off or the provision is deleted. The ordinary quarter retains and carries forward.

**Interest accrues on the opening balance only.** BR-ACC-15 accrues "from the dates on which the respective amounts are credited to the Reserve Fund", and an amount retained this quarter is credited when this account is rendered — 45 days after the close, under BR-ACC-01. So it earns nothing in the quarter that created it. **This is a convention rather than a quotation**, and it is the conservative one: crediting mid-quarter would pay reinsurers more.

What it cannot do yet, and reports rather than zeroing:

- **The panel is empty.** `reinsurer_shares` holds no rows, so there is nothing to apportion the retention across. Domicile, by contrast, *is* on file for all 22 counterparties — 7 Botswana, 15 foreign — so BR-ACC-14's test is fully supported the moment shares arrive.
- **The average call rate has no source in this system.** It is a Bank of Botswana rate and is left `null` rather than guessed: a plausible-looking rate would accrue interest nobody agreed on somebody else's money. The retention still computes without it, which is the part that moves cash.
- **Motor is a stated nil, not a refusal** — settled by ruling on 7 September 2026, the deposit applying to the GQS treaty. It refused until then, because the Motor slip gives only "Domestic Reinsurers – Nil" while Article 9 provides for a deposit against anyone not domiciled in Botswana. RI-01 open item 7, closed.

Two things to confirm when the panel lands: the `reinsurer` table mixes reinsurers with intermediaries (`Oak Tree Intermediaries`, `Genesis Risk Managers` and others carry a `counterparty_type`), and the `GIC Re` record is domiciled `IN` while open item 7 refers to *GIC Re South Africa*. Foreign either way, so the treatment is unchanged.

### Brokerage and VAT

**Brokerage is 2.50% of ceded premium, not of premium net of commission.** BR-COM-16 gives the rate and not the base. BR-COM-17 — "no *other* deductions from premium" — puts brokerage alongside the ceding commission, both taken off premium. Compounding them would also make the order they are applied in matter, and nothing in the slips sets an order.

**No VAT line is written, and that is the reading of the slips rather than a gap.** BR-ACC-18 is explicit that all treaty figures exclude VAT unless otherwise stated, so an account built from them is VAT-exclusive. BR-ACC-17 says VAT applies to the transactions at the rate current at the time — that VAT exists, not that it belongs inside this account. **Which lines would attract it is a tax question, not a treaty one**, and the flows run in different directions: premium ceded to a non-resident reinsurer is an imported service, ceding commission is the cedant supplying a service to that reinsurer, and brokerage is the intermediary's. The mechanism, rate and arithmetic are built and tested; `terms.vat.applies_to` is empty until Finance rules, and a statement reports `vat.configured = false` rather than quietly carrying no VAT.

### Which treaty a cession belongs to

**The split is by group, not by regulatory class.** RI-05 is the authority: it lists the General-treaty groups (`PROPERTYANDBI_COM`, `ELECTRONIC_EQ_AND_BI_COM`, `GOODSINTRANSIT_COM`, `MISC_COM`, `FIDELITYG_COM`, `ACCIDENTAL_DAMAGE_COM`, `ENGINEERING_AND_BI_COM`) and the Motor-treaty groups (`MOTOR_COM`, `MOTOR_TRAILERS_COM`, `MOTOR_TRADERS_COM_EXT`, `MOTOR_TRADERS_COM_INT`, plus a Passenger Liability class with no populated group). The regulatory class is what a statement *reports* by — BR-RPT-02, per class of business — not what decides the treaty. Several groups share a class, so the two questions are different even where they currently agree.

Three notes on the mapping:

- The group table also holds `MOTOR_DOM` (15), `MOTOR_TRAILERS_DOM` (29) and `MOTOR_PER_ACCIDENT` (16), which RI-05 does not list. All are motor business, so all sit on the Motor side — *which* treaty is not in doubt. **Whether the domestic variants are inside the 2026/27 treaty at all is open with Tlamelo**, and if the answer is no the fault is upstream in their being ceded, not in where they are reported.
- `CessionSource::MOTOR_GROUP_IDS` is `[13,14,15,28,29]` and **omits 30 (`MOTOR_TRADERS_COM_INT`)**, which RI-05 names explicitly. A defect in that constant, tracked separately; the statement split does not inherit it.
- An unrecognised group falls to **General**, the residual treaty, rather than to neither — dropping it from both statements would make the money disappear silently.

### 350,149,075.01 that reaches no statement

Every builder joins the regulatory row to `reinsurance_group` on **both** the detail's `group_id` and the row's `group_code`. That join is a consistency check, and a row whose `group_code` is null fails it and vanishes. On the test server that is **7 Property rows carrying 350,149,075.01 of sum insured — none survive the join, and nothing counted them.**

The join is deliberately **not** loosened. Making it tolerant would pull units of unknown mapping into every statement's figures, and that is a decision about the data rather than about the query: either the `group_code` is backfilled or somebody rules the units out of scope. `StatementOutstandingBuilder::unmappedUnits()` measures the hole in the meantime.

### What step 4 found in the data

Three things, all of which read as a correct statement rather than as an error:

- **`incident_date` is populated on 4 of 3,550 claims**, `reported_date` on 70. Year of occurrence cannot be computed, so the General axis raises rather than substituting `created_at` — the day a claim was keyed is not the day the loss happened, and a statement built on it would look identical to a correct one. **This needs either a backfill of the date of loss or a decision from Tlamelo that reported date is an acceptable proxy.** Motor is unaffected and works today.
- **`reserve_amt` is NULL on 9,159 of 16,996 rows and `payment_amt` on 9,716.** `reserve − payment` is NULL on any such row and `SUM` skips NULLs, so the outstanding position summed to **0.00** on exactly the rows that carry an unpaid reserve. Across the table the naive subtraction gives 7,981,590.25 against the true 19,439,588.95 — understated by 11.46m.
- **`balance` is not `reserve_amt − payment_amt`** on 366 rows, and where it differs it looks cumulative (812.88 against a 406.44 reserve). `totalReserveAmt` is zero on all 16,996 rows. Neither is usable; outstanding is computed from the two components.
| BR-ACC-08 Cash loss recoveries | `fac_placements` + a new cash-loss register | Cash calls must appear as a separate line and be refunded in the same quarter (BR-CLM-07) |
| Reserve deposit and interest | New ledger | General only until item 2 above is answered |
| VAT | `config('reinsurance.accounts')` | Rate current at the transaction, and all treaty figures are VAT-exclusive (BR-ACC-18) |

**One caution on the claims table.** `payment_amt`, `reserve_amt` and the
salvage and subrogation columns are `decimal(10,2)` — a ceiling of
99,999,999.99. A single claim above 100 million cannot be stored, and the
treaties carry an event limit of 70,000,000 with sums insured far above it. Not
a blocker for the first statement; worth widening before it is one.

**The outstanding-losses axis differs by treaty and that is not a detail.**
General breaks down by year of occurrence, Motor by underwriting year. The same
claim sits in different buckets on each. Building one axis and reusing it would
be wrong on whichever treaty it was not built for.

---

## The model

Four tables. Names follow the existing `policy_reinsurance_*` convention.

**`treaty_statements`** — one row per (treaty, underwriting year, quarter).
Carries the period, the render due date (close + 45 days), the confirmation due
date (render + 14), status, and who rendered it. Statuses: `draft`, `rendered`,
`confirmed`, `disputed`, `settled`.

**`treaty_statement_items`** — the lines. One row per (statement, item type,
class, occurrence or underwriting year). Item type is the BR-ACC-04 to
BR-ACC-08 set plus brokerage, VAT, reserve deposit and interest. Signed amounts:
premium positive to reinsurers, claims negative, so the balance is a sum rather
than a rule.

**`treaty_statement_shares`** — the same items split per reinsurer. Written only
when the panel reaches the cession; empty until then, which is honest rather
than provisional.

**`treaty_reserve_deposits`** — per reinsurer per quarter. Retained amount,
release, interest accrued, and the balance carried. Only for reinsurers not
domiciled in Botswana (BR-ACC-14), and skipped entirely where a letter of credit
is on file (BR-ACC-13).

Money is `decimal(20,2)`, as in `policy_reinsurance_regulatory` and for the same
reason: these figures reach a solvency return.

---

## Build order

Ordered so that nothing waits on Tlamelo until it has to, and so each step is
checkable on its own.

**All eleven are built.** The last column is what each still needs to produce a *figure* — the mechanism exists either way, and every one of them refuses rather than guessing when the input is absent. Nothing in this table is outstanding development work.

| # | Step | Built | Needs a number from someone before it produces figures |
|---|---|---|---|
| 1 | The four tables, migrations, models | ✅ | — |
| 2 | Premium and commission items from the cession | ✅ | — |
| 3 | Claims paid, salvages, recoveries | ✅ | — |
| 4 | Outstanding losses, both axes | ✅ | **Date of loss** — General's occurrence axis refuses; Motor works today |
| 5 | Brokerage, VAT, the balance | ✅ | **A VAT ruling** — brokerage and the balance work; no VAT line is written |
| 6 | General reserve deposit ledger and interest | ✅ | **The panel** and **the average call rate** — the retention computes, interest does not |
| 7 | The 45- and 14-day clocks as scheduled jobs | ✅ | — (running daily at 07:30) |
| 8 | The rendered statement document | ✅ | — (marks itself an internal working copy while the panel is unplaced) |
| 9 | Per-reinsurer allocation | ✅ | **Signing schedule** — refuses a short panel; `previewFor()` shows the shape |
| 10 | Motor reserve deposit | ✅ | — (RI-01 open item 7 closed 7 Sep 2026; Motor retains a stated nil, and configured terms still override) |
| 11 | Delay interest | ✅ | **A prime rate** — reports a balance as overdue and unpriceable |
| — | Cash loss entitlements, BR-ACC-08 | ✅ | Waits on the regulatory basis being staged across the book |

**Above all of these sits one thing the table cannot show:** no statement figure is sound until `reinsurance.engine` is `regulatory` and the book is staged. As at 4 September 2026 the engine is `legacy` and 9 of 50 actions are staged, so the claims, outstanding and cash loss builders all refuse outright — see *The first end-to-end run* above.

Steps 1 to 8 are **22 days and need nobody**. That is the whole statement except
the split by reinsurer, and it can be reconciled against the cession before
anyone signs anything.

Steps 9 to 11 are four days and each waits on a different person.

---

## How it will be checked

The same way the cession was, because it worked: reproduce a figure someone else
produced independently, to the cent, before believing any of it.

- **The premium side** must reconcile to the cession for the same period. Same
  engine, same basis, so a difference is a fault in this build rather than a
  question of interpretation.
- **The claims side** must reconcile to the claims ledger for the quarter,
  gross, before any ceded share is taken.
- **The balance** must equal the sum of its items. A statement that does not
  foot is not a statement.
- **Every per-reinsurer split** must sum back to the total item (BR-SEC-08).

---

## What is deliberately not in this build

**Profit commission.** BR-COM-08: the first profit or loss statement for an
underwriting year is ascertained 24 months from inception and submitted with the
fourth quarter account. For 2026/27 that is **mid-2028**. It belongs with the
commission engine, which is sequenced to August 2027.

**The sliding scale adjustment.** Motor's provisional 25% adjusts at the fourth
quarter. First adjustment 14 August 2027.

Both are real requirements. Neither is due before this statement is.
