# RI-19 — Reinsurance 2026/27: the outstanding list

**Alpha Direct Insurance Company (Pty) Ltd · Gaborone**
Prepared by Snehal Zunjarrao, Developer, Graphite v2 · **Revised 10 September 2026** (first issued 4 September)
Treaty year 1 Jul 2026 – 30 Jun 2027

This supersedes every earlier list. Each item states who holds it and what it changes. Everything here was verified against the code and the test suite on 10 September 2026, not recalled. Figures measured against the database carry the date they were measured.

**Section 9 records what changed since the 4 September issue.** The short version: Reinsurance ruled on five points on 7 September and all of section 4 is closed; three items in section 2 are done; section 3.3 has changed character; and two new items have opened.

---

## 0. The one thing above all others

**No statement figure is sound until the cession is cut over.** `reinsurance.engine` still reads `legacy` — verified in config on 10 September.

As measured on 4 September and not re-measured since: `policy_reinsurance_regulatory` held 52 rows across 9 actions, against 50 actions in the legacy table, and all four statement tables were empty.

Until the regulatory basis is staged across the book and the engine switched, the claims, outstanding and cash-loss builders **refuse outright** — deliberately, because before that guard existed a statement drew premium from the whole legacy book and claims from nine regulatory actions, balanced to 213,740.82, and footed perfectly. That figure is withdrawn.

Everything in section 3 depends on this. Nothing in sections 4–7 does.

---

## 1. What is built and green

Not a to-do list — stated so the outstanding items below are read in proportion.

- All eleven statement steps, plus cash-loss entitlements (BR-ACC-08) and both registers.
- The regulatory cession calculator, reproducing the COMG2026213751 working to the cent and passing Reinsurance's work-paper cases.
- Event limits, annual aggregates, the hours clause, CBI sub-limits, participation, Schedule A conformance.
- The 45- and 14-day clocks, scheduled daily at 07:30 in both apps.
- **The cession seam is complete.** ADRisk IT's code-level review of RI-20 found two reads still outside it — a claims RI-recovery breakdown and the underwriting review endpoint. Both moved on 7 September and are on `main`.
- **The reserve deposit rate is pinned to the statement** that was rendered on it, rather than re-read from config on every rebuild (9 September).

**Test position, run 10 September 2026:**

| Suite | Tests | Assertions | Result |
|---|---|---|---|
| Feature — Reinsurance | 438 | 1,137 | **Green** |
| Unit — whole application | 587 | 1,787 | **Green** |

**Both suites are green as at 10 September.** The unit suite had been failing on `MapfreTravelClientTest::test_disabled_flag_short_circuits_every_method`, and the first issue of this document recorded that as *"a real defect in the travel integration"*. **That diagnosis was wrong and is withdrawn.** The production guard is correct. The guards call `IntegrationSettings::isEnabled()`, which reads the `integration_settings` table and falls back to `config('services.mapfre.enabled')` **only when there is no row** — and the server carries a row saying enabled. So the test disabled the integration with a lever the code does not consult, the guard answered true from the database, and the call went out. That precedence is deliberate: an operator toggling an integration from the admin UI is meant to outrank config without a redeploy. What was wrong is that a *unit* test reached that database at all, against its own docblock’s promise of "no live DB". Fixed on 10 September by pointing `mysql_system` at an empty in-memory database, so the test’s own config decides. **No defect exists in the travel integration, and nobody should be sent looking for one.**

---

## 2. Ours — actionable now, nothing blocking

| # | Item | Detail |
|---|---|---|
| 2.1 | ~~Push the branch~~ | **Done.** The branch is pushed and the work is merged to `main`. |
| 2.2 | ~~Track three files~~ | **Done.** All three are tracked. |
| 2.3 | **Verify the cron port** | `treaty:statement-clocks` was ported into `cron/`, which is a separate codebase. Syntax, command discovery, namespace and DB config check out; it has never been executed there because `cron/` has no local `vendor/`. **One server run settles it, and this job is the only thing that will notice 13 November.** |
| 2.4 | ~~RI-02 is stale~~ | **Done, 4 September.** A dated status note heads RI-02. |
| 2.5 | ~~A config comment contradicts a merged ruling~~ | **Done, 10 September.** `reinsurance.php` headed the Motor reserve block "RI-01 OPEN ITEM 7, UNANSWERED" and declared "NULL MEANS REFUSE, NOT NIL", both reversed by the ruling. Six further places carried the same claim — `TreatyReserveDeposit`’s docblock, a test docblock contradicting the method name below it, RI-18 in four spots and RI-02 in two — and all are corrected. |

**What RI-02 still correctly lists as gaps, and this document does not carry**, because they are a wider programme than the statement of account: territorial validation (BR-TER), the underwriting controls and exclusion schedules (BR-UWC, roughly 90 narrative exclusions), off-set of confirmed balances (BR-ACC-11), cash calls refunded in the same quarter (BR-CLM-07), claims agreement per reinsurer participation (BR-CLM-12), run-off termination commission (BR-COM-15), event limit proration across underwriting years (BR-CAP-18), the marine terrorism aggregate (BR-CAP-19), and IFRS 17 (BR-RPT-01, deferred to August 2027).

**None of that set is gated on an answer from anybody.** It is the largest remaining block of work that needs nobody, and none of it has been started.

---

## 3. Ours — blocked on the cutover

| # | Item | Detail |
|---|---|---|
| 3.1 | **Run RI-17 in production** | Stages the regulatory basis across the book. Needs production access, which this machine does not have. Unblocks 3.2, cash-loss detection, and any real statement. |
| 3.2 | **Switch `reinsurance.engine` to `regulatory`** | Only after 3.1, and only once the reconciliation is checked. |
| 3.3 | ~~49,810,404.11 of ceded sum insured reaches no statement~~ | **Changed character, 9 September — see below. It is no longer ours to fix.** |
| 3.4 | ~~Two registers do not exist~~ | **Built, 4 September** — `treaty_letters_of_credit` and `treaty_cash_loss_recoveries`, both migrated and live. |

Also unproved, and blocked on the same access as 3.1: the two seam branches added on 7 September carry the legacy behaviour verbatim so nothing changes today, but their **regulatory branches have never been run against real data**. Every earlier seam move was proved identical first. These were not.

### 3.3 in full — now reported rather than hidden, and the decision is not ours

The seven Property rows carry a NULL `group_code`, and that is *correct*: their regulatory class aggregates several groups and an allocation computed across four groups has no single group code. Every builder joined on both `group_id` and `group_code`, so those rows failed the join and vanished.

**As of 9 September the hole is returned with the figures.** `outstandingFor()` now reports it beside `unclassified_groups`, which is where somebody reading an outstanding position would look. Before that, `unmappedUnits()` was public, correct, covered by a test — and **called by no production code**. A figure computed on request that nothing requests is the same as no figure at all.

**Reported, not raised, and the test pins both halves.** These units are absent whether anyone looks or not, so refusing would withhold a correct outstanding position over rows it never contained. Loosening the join to pull them in would change every statement's figures to include units whose mapping is unknown.

**So closing it is a decision about the data, not about the query: either the `group_code` is backfilled, or somebody rules the units out of scope.** That moves this item into section 5 — it now waits on Reinsurance, not on us. The test asserts the reported position is *unchanged* alongside the hole being reported, so a later attempt to "fix" it by widening the join fails loudly.

**Two measures, and they are not the same number.** 350,149,075.01 is the *sum insured* on those rows across every layer. 49,810,404.11 is the *ceded* portion — quota share and surplus only. The FAC and auto-FAC in the difference are capacity outside the treaty. Quoting the first as a cession is the error this document made in its first issue.

---

## 4. Reinsurance — ruled on 7 September 2026

**All five questions in this section are answered. Two changed the build, three confirmed it.** Recorded so nobody reopens them.

| # | Question | Ruling | Effect |
|---|---|---|---|
| 4.1 | Motor reserve deposit — percentage and rate | **Nil by ruling.** The deposit applies to the GQS treaty only. | **RI-01 open item 7 is closed.** The builder used to throw, on the grounds that "Domestic Reinsurers - Nil" was not the same as a stated nil while Article 9 provides for a deposit against foreign reinsurers. Somebody has now said. The refusal survives for a third treaty arriving with no terms, which is what it was really for. |
| 4.2 | Letters of credit — which reinsurers | **None exist.** | No code depended on one. BR-ACC-13's discharge is read from the register, so an empty register already retains the full 40% from every foreign reinsurer. The branch stays: "none today" is not "none ever". |
| 4.3 | Brokerage 2.50% — on ceded premium, or after commission | **Confirmed: gross ceded premium.** | Already built that way, reasoned from the slips giving 2.50% without a base and BR-COM-17's "no other deductions from premium". The confirmation matters because the compounded reading is the more natural one to a fresh reader, and 2.50% on premium net of a 32.5% commission understates the fee by about a third. **No longer an assumption.** |
| 4.4 | Reserve deposit — accumulates to termination, or released annually | **Released and re-established each year.** | **Changed the build.** The previous reading had BR-GOV-12's "no portfolio entry and no portfolio withdrawal" leaving no annual transfer to release against, so the deposit accumulated indefinitely. Q4 now retains *and then releases the whole position*, both lines shown rather than netted — releasing only the opening balance would strand Q4's own retention and the deposit would never reach nil. `balance_carried` is nil at every year end. |
| 4.5 | Outstanding losses — case reserves only, or plus IBNR | **Confirmed: case reserves only.** | Nothing in this system holds an IBNR reserve, so outstanding was case-only by construction rather than by choice. Now written into the builder explicitly, because an IBNR column arriving on that table would otherwise be summed in silently by a query with no reason to exclude it. |

---

## 5. Reinsurance — outstanding

### 5A. Asked, awaiting reply

| # | Question | What turns on it | Asked |
|---|---|---|---|
| 5A.1 | **The surplus — 40,000,000 or 12,000,000?** Send the signed slip or endorsement, and confirm which figure applies. | No surplus treaty appears in either signed slip; 40,000,000 is configured on written confirmation alone. Four lines on the 3,000,000 net retention is 12,000,000; four on the gross 10,000,000 first line is 40,000,000. **Every Property and Engineering figure moves on the answer.** | FAC-02 §5.1 |
| 5A.2 | **Where to capture MPL, co-insurance, and the engineering project and plant values.** | Six of the eight FAC placement mandate conditions cannot be tested without them. Whether they belong on the FAC placement or upstream at underwriting is an underwriting decision. | FAC-02 §5.2 |
| 5A.3 | **Three testing actions.** Generate one slip on the server and confirm the layout including the logo; enter a Fire and Allied Perils schedule and confirm the printed block; send one bordereau to the broker and mark up the columns. | Our test machine renders PDFs with a different engine, so it proves the wording but not the appearance. No bordereau sample was ever supplied. | FAC-02 §5.3 |
| 5A.4 | **Which quarter carries the annual release.** | The 4.4 ruling says "each year" and not which account renders it. Year-end is built as the conservative choice — it returns the money in the account for the period that ended. Q1 of the following year is equally defensible. | 7 Sep 2026 |
| 5A.5 | **The seven unmapped Property rows** — backfill `group_code`, or rule the units out of scope? | Was 3.3. The hole is now reported with the figures; closing it is a data decision. | 9 Sep 2026 |
| 5A.6 | **Are the Motor domestic variants inside the 2026/27 treaty at all?** | `MOTOR_DOM`, `MOTOR_TRAILERS_DOM` and `MOTOR_PER_ACCIDENT` are motor business, so *which* treaty is not in doubt. If they are not in the treaty, the fault is upstream in their being ceded. | Open with Tlamelo |

### 5B. Not yet asked, and larger

These come from RI-10 and RI-01 and move far more money than section 5A.

| # | Question | What turns on it |
|---|---|---|
| 5B.1 | **Class limits** — written confirmation that Schedule A is overridden, or do we revert? | We cede **47,000,000** on Accidental Damage and **210,000,000** on Goods in Transit under the instruction as given. With Schedule A applied, ceded sum insured returns to **34,727,000**. |
| 5B.2 | **Sum insured basis** | Schedule A states a different basis per class — any one situation, any one risk, any one vehicle, any one event. We test on the group total, which matches none of them. |
| 5B.3 | **Six mapping rows we cannot apply** | RI-10 section 4: `MOTOR_TRADERS_COM`, `MOTORTRADERSEXTERNAL`, Engineering, Passenger Liability, ten domestic rows, PERSONALACCIDENT→Liability. "The proposed mappings are okay" did not resolve them individually. `MOTOR_TRADERS_COM_EXT` in particular carries `regulatory_mapping = Property` while the treaty split puts it on Motor by its group code. |
| 5B.4 | **Three group codes** | `WC_COM`, `PUBLICLIABANDDEFECTIVEWORKMAN_COM`, `MOTOR_DOM` carry cessions and appear in neither treaty's class list. |
| 5B.5 | **Loss Ratio CAP 75% on Motor** | RI-01 item 5. Under one reading it caps the sliding-scale commission; under the other it caps Reinsurers' claims liability at a 75% loss ratio, and recoveries stop there. Materially different. |
| 5B.6 | **Motor sliding scale below a 50% loss ratio** | RI-01 item 6. The table starts at 50%. Behaviour below it is unstated. |
| 5B.7 | **Profit commission on Motor** | RI-01 item 8. The slip gives sliding scale only; Article 7 of the wording refers to "commission and profit commission". Built as none — an assumption. |
| 5B.8 | **General EPI up 193%** | RI-01 item 9. 8,500,000 to 24,872,310. Confirm, since profit commission and the deposit both scale off it. |
| 5B.9 | **Motor slip execution** | RI-01 items 3 and 4. The Reinsured signing page is blank, and all reinsurer stamps post-date the 1 July commencement. |
| 5B.10 | **Passenger Liability** | Confirmed as Motor cover, requiring a treaty wording amendment per the MQS Treaty. Ours to map once the amendment lands — RI-05 has it at 2,500,000 with no populated group. |

---

## 6. Not Reinsurance

| # | Item | Holder |
|---|---|---|
| 6.1 | **The average call rate figure** for the reserve deposit (BR-ACC-12), and whether a different rate applies each treaty year | Finance / Treasury. **The mechanism is resolved** — as of 9 September the rate is pinned to the statement it was rendered on, so a rebuild can no longer restate a settled quarter at a later rate. Where the rate lives was our decision and is made. The figure is not ours, and nothing computes until Finance supplies one. |
| 6.2 | **Market prime lending rate** for delay interest (BR-ACC-10) | Finance / Treasury. `prime_rate` is still an empty array. An overdue balance is reported as overdue *and uncomputable*, because accruing zero on a late balance reads as "paid on time". |
| 6.3 | **VAT applicability** (BR-ACC-17, 18) | Finance. No VAT line is written; BR-ACC-18 makes treaty figures VAT-exclusive, so this is a reading rather than a gap. |
| 6.4 | **Date of loss backfill** | Claims — **or Reinsurance.** `claims.incident_date` is populated on **4 of 3,550**. BR-CLM-09 requires it per claim on the quarterly outstanding register, and BR-ACC-07's General occurrence axis refuses without it. RI-18 records a second route: a ruling that **reported date is an acceptable proxy** closes it without a backfill. That is considerably cheaper than backfilling 3,546 claims and has not been put as a question. |

---

## 7. Assumptions we have made

Each is documented in code and would change figures if wrong.

- ~~Brokerage on ceded premium~~ — **confirmed by ruling 4.3 on 7 September. No longer an assumption.**
- **Reserve interest accrues on the opening balance only**, since a retention is credited when the account is rendered. Conservative; the alternative pays reinsurers more. Untouched by the 4.4 ruling, which changed release rather than accrual.
- **Delay interest on actual/365.** No convention is stated; 360 would pay about 1.4% more.
- **An unknown reinsurer domicile is treated as foreign**, so a deposit is retained and queried rather than silently omitted. Ruling 4.2 confirms no letter of credit discharges any of them today.
- **An unrecognised reinsurance group falls to General**, the residual treaty, rather than to neither.
- **Motor profit commission is nil** — pending 5B.7.

---

## 8. Deferred by agreement

- Commission engine and IFRS 17 — August 2027.
- `CessionSource::MOTOR_GROUP_IDS` omits group 30. **Not a defect**: it reproduces the legacy motor tab verbatim so the seam stays a move rather than a rewrite. Group 30 carries one cession row; group 29, which the list includes, carries none.

---

## 9. What changed since the 4 September issue

**Closed**

- All five questions in the old section 4, ruled on by Reinsurance on 7 September. Two changed the build (Motor nil, annual release); three confirmed it (brokerage base, no letters of credit, case reserves only). RI-01 open item 7 is closed with them.
- Old 2.1, push the branch — done and merged.
- Old 2.2, track three files — done.
- The two seam reads ADRisk IT found in their review of RI-20 — moved 7 September.
- The bare `CAST` on `treatySI` and `treatyPremium`, which stopped at the first comma and turned "1,234.56" into 1.00 — fixed 9 September. It moved no figure: 0 of 3,573 rows on the live book carry a comma, which is why it was worth closing while closing it was free.

**Changed**

- Old 3.3 is no longer ours. The unmapped hole is reported with the figures as of 9 September; closing it needs a backfill or a scope ruling, so it is now 5A.5.
- The reserve deposit rate mechanism was raised with Reinsurance and withdrawn as a question — where the rate lives is our decision, and it is made. Only the figure remains, with Finance.

**Opened**

- 5A.4, which quarter carries the annual release.
- ~~2.5, a config comment that contradicts a merged ruling~~ — opened and closed on 10 September, along with six others carrying the same claim.

**Corrected**

- **The MAPFRE diagnosis is withdrawn.** The 10 September issue of this document recorded the failing unit test as *"a real defect in the travel integration"* and routed it to that module’s owner as item 6.5. That was wrong. The production guard is correct; the *test* disabled the integration through config while `IntegrationSettings::isEnabled()` consults the database, which carries a row saying enabled. A unit test was reading a live toggle. Fixed the same day and item 6.5 is deleted — **there is no travel-integration defect and nobody should be sent to look for one.** The unit suite is green at 587 tests, 1,787 assertions.
- The surplus is recorded in 5A.1 as *asked and awaiting reply*, not as unasked. FAC-02 §5.1 put it in writing. RI-14 of 3 September recorded it as closed on the strength of the Final Terms showing four lines on a 10,000,000 retention; **that was wrong**, and RI-12, FAC-02 and this document all hold it open — not because the arithmetic is indefensible but because no signed surplus slip has ever been received.
- 350,149,075.01 and 49,810,404.11 are sum insured and ceded sum insured respectively, and are not alternatives to one another.
