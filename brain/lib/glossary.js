'use strict';
/**
 * glossary.js — the plain-English definition of every number the Alpha Brain
 * console shows. ONE source of truth, so the dashboard can never display a
 * figure without also being able to explain it.
 *
 * Why this exists (CFO 2026-07-28): the console showed big numbers with no
 * reference — "the compliance side doesn't make sense; the numbers don't add
 * up". Every tile now carries: what it means, how it is counted, which table it
 * came from, what is deliberately EXCLUDED, and who acts on it.
 *
 * Rules for anything added here:
 *  - `howCounted` must match the actual SQL/logic in the module named by `code`.
 *    If the code changes, this changes in the same commit.
 *  - `excludes` is not optional. Most "the numbers don't add up" complaints are
 *    an undocumented filter (a date window, a status filter), not a bug.
 *  - No customer data, no PII — definitions only.
 *
 * Pure data + pure functions. No I/O, no deps (see test/glossary.test.js).
 */

// Every metric the console renders, keyed by the id the frontend uses.
const METRICS = {
  total_exceptions: {
    label: 'Total exceptions',
    means: 'How many things the Brain thinks a human should look at today, across every team.',
    howCounted: 'The sum of the per-team counts below. Nothing else. If the team numbers do not add up to this, that is a bug — report it.',
    source: 'The nightly sweep — the per-team lists added together',
    excludes: 'Anything already approved or closed by a person. Routine no-action events (they are collapsed into one summary line each, not counted individually).',
    whoActs: 'Nobody — it is a headline. Act on the team numbers.',
    formula: 'Finance + Compliance + Claims + Underwriting',
  },

  finance: {
    label: 'Finance',
    means: 'Money the company is owed, or is at risk of not collecting.',
    howCounted: 'Overdue debtor accounts + policies on the non-payment clock + claims approved while badly in arrears. Today this is almost entirely debtors.',
    source: 'The nightly sweep, reading the policy ledger (invoices and receipts) and the payment records',
    excludes: 'Accounts with nothing overdue past 60 days and no unapplied credit — they drop out on purpose.',
    whoActs: 'Debtors team, then Finance.',
  },

  compliance: {
    label: 'Compliance',
    means: 'Customers on the active book whose KYC paperwork is not clean.',
    howCounted: 'One row per customer KYC record that is NOT approved, where the customer holds at least one in-force policy, and the record was opened in the last 400 days.',
    source: 'The KYC checker, reading the masked customer-KYC view — yes/no flags only, never a raw Omang, passport or scan',
    excludes: 'THREE filters that make this number much smaller than "everyone missing documents": (1) KYC records older than 400 days, (2) customers with no in-force policy, (3) records already marked approved. See "KYC gap on the active book" for the unfiltered figure.',
    whoActs: 'KYC team.',
  },

  live_arms: {
    label: 'Live arms',
    means: 'Whether the Brain is allowed to actually DO anything — send a message, cancel a policy, move money.',
    howCounted: 'OFF unless the switch BRAIN_LIVE_ARMS is deliberately turned on. It has never been on.',
    source: "The Brain's own off switch — one setting, checked before every outward action",
    excludes: 'Nothing. OFF means: it decides, it ranks, it records your approval — and it executes nothing.',
    whoActs: 'CFO only. Nothing turns this on by itself.',
  },

  // ── Finance detail ───────────────────────────────────────────────
  debtors: {
    label: 'Overdue debtor accounts',
    means: 'Accounts with a balance older than 60 days, or sitting on money we received but never matched to an invoice.',
    howCounted: 'The Brain re-ages every account from scratch: it applies each receipt to the oldest open invoice first, then buckets what is left into 61-90 / 91-120 / over 120 days. An account appears if anything lands past 60 days, or if there is unapplied credit.',
    source: "The Brain's own ageing calculation over the policy ledger, last 365 days",
    excludes: 'The 0-30 and 31-60 buckets on their own — current debt is not an exception. NOTE: this deliberately does NOT use Graphite\'s own ageing report, which mis-handles unallocated receipts; that is the bug this replaces.',
    whoActs: 'Debtors team.',
    caveat: 'A number close to the size of the whole book means the ageing inputs need a second look, not that every customer is in arrears. Flagged to Finance.',
  },

  collections: {
    label: 'Non-payment clock',
    means: 'Policies that have missed two months in a row, and where they are on the suspend → grace → cancel path.',
    howCounted: 'Two consecutive unpaid months starts the clock. Then: suspension candidate, 15-day grace, cancel candidate — and a cancel candidate is only ever "ready" once a 5-day notice has been served.',
    source: 'The non-payment clock, fed by its own read of the payment records',
    excludes: 'One-off failed debits (a single bounce is not the clock). Policies already cancelled.',
    whoActs: 'Debtors team raise it; suspension and cancellation are always human-approved.',
    caveat: 'If all four stages read zero while Finance is large, the collections feed did not produce rows this sweep — that is a data gap, not a clean book.',
  },

  high_arrears: {
    label: 'High-arrears claims',
    means: 'Claims that were approved for payment even though the policy was more than 3 months behind.',
    howCounted: 'Claim marked finance-approved AND arrears months greater than 3.',
    source: 'The nightly sweep, comparing claim approvals against arrears months',
    excludes: 'Claims still under assessment.',
    whoActs: 'Finance oversight — CFO visibility.',
  },

  // ── Compliance detail ────────────────────────────────────────────
  kyc: {
    label: 'KYC exceptions',
    means: 'Active customers whose identity or address paperwork is missing, expired or unreadable.',
    howCounted: 'Same as the Compliance tile — see its filters.',
    source: 'The KYC checker (see the Compliance tile)',
    excludes: 'See the Compliance tile. The 400-day window is the big one.',
    whoActs: 'KYC team.',
  },

  kyc_gap_active_book: {
    label: 'KYC gap on the active book',
    means: 'In-force policies where the customer has NOT ONE identity document on file. The honest "how bad is it" number.',
    howCounted: 'Count of policies with status = 1 (activated and in force) whose customer has no Omang, passport, licence, proof of income, proof of residence or KYC form present.',
    source: 'The compliance census — a count of active policies against the masked customer-KYC view',
    excludes: 'Policies sold but never activated (status 0) and lapsed policies (status 2). Including those took this figure from about 3,650 to about 15,700 and was wrong.',
    whoActs: 'KYC team — this is the backlog, not today\'s work.',
    caveat: 'This is BIGGER than the Compliance exception count on purpose: it ignores the 400-day window and the record-status filter. Two different questions.',
  },

  healthcare_non_compliance: {
    label: 'Healthcare non-compliance',
    means: 'Active health policies (MIS / ADH) where the customer paperwork is not clean.',
    howCounted: 'In-force health policies split into: no documents at all, and KYC record not approved.',
    source: 'The healthcare check — counts of active health policies against the masked customer-KYC view',
    excludes: 'Health-scheme-specific rules — waiting periods, pre-existing-condition declarations, dependant proof, scheme member cards. Those rules have NOT been defined by Compliance yet, so they are not counted. This tile is identity paperwork only.',
    whoActs: 'KYC team + Healthcare.',
    caveat: 'This is the paperwork half only. Anyone reading it as full health-scheme compliance is reading it wrong.',
  },

  // Healthcare has its OWN entries for its own tiles. Do not reuse the
  // whole-book kyc_gap_active_book / kyc / by_category definitions here: the
  // heading on a tile comes from the label below, so borrowing them printed
  // health-only figures under whole-book names and produced two different
  // numbers with the same title — the exact complaint this file exists to kill.
  healthcare_no_docs: {
    label: 'Health policies with no documents',
    means: 'Active health policies where the customer has NOT ONE identity document on file.',
    howCounted: 'In-force health policies (MIS / ADH) whose customer has no Omang, passport, licence, proof of income, proof of residence or KYC form present.',
    source: 'The healthcare check — counts of active health policies against the masked customer-KYC view',
    excludes: 'Health policies sold but never activated, and lapsed ones. Domestic and commercial policies — this is health only.',
    whoActs: 'KYC team + Healthcare.',
    caveat: 'HEALTH ONLY. The whole-book version of this figure is "KYC gap on the active book" and is much bigger. Do not compare the two as if they were the same number.',
  },

  healthcare_kyc_not_approved: {
    label: 'Health policies with documents not approved',
    means: 'Active health policies where documents WERE sent, but the KYC record is not approved — rejected, expired, or waiting on a re-check.',
    howCounted: 'In-force health policies whose customer has at least one document present AND a KYC record still marked Unchecked, Unapprove or Recheck.',
    source: 'The healthcare check — counts of active health policies against the masked customer-KYC view',
    excludes: 'Customers with no documents at all — they are counted in the "no documents" figure instead, so the two never double-count.',
    whoActs: 'KYC team.',
    caveat: 'HEALTH ONLY. Not the same population as the whole-book KYC exception queue.',
  },

  healthcare_active_policies: {
    label: 'Active health policies',
    means: 'How many health policies are in force. The denominator for every healthcare percentage on this page.',
    howCounted: 'Policies in force whose number starts MIS or ADH.',
    source: 'The healthcare check, counting in-force policies by policy-number prefix',
    excludes: 'Domestic and commercial policies. Health policies sold but never activated, and lapsed ones.',
    whoActs: 'Nobody — context for the figures beside it.',
  },

  fraud: {
    label: 'Fraud signals',
    means: 'Claims carrying warning signs — hold them, or refer them for a closer look.',
    howCounted: 'Each claim is scored on hard and soft signals: loss before cover started, late reporting, repeat patterns. A severe signal is a HOLD; several soft signals is a REFER.',
    source: 'The nightly sweep, scoring each claim reported in the last 60 days',
    excludes: 'Claims that score clean. Two checks are quiet because their inputs are not sourced yet — cover-start date and claim estimate — so this under-reports rather than guesses.',
    whoActs: 'Claims. A hold always needs a person to release it.',
  },

  event: {
    label: 'Lifecycle events',
    means: 'Things that happened — a payment came in, a claim was registered, a car was written off — where the Brain worked out what each department now owes.',
    howCounted: 'Events that need a signature are listed one by one. Routine events that need no action are collapsed into ONE line per type with the count on it.',
    source: 'The nightly sweep over the last 2 days of events',
    excludes: 'Seven event types are deliberately not derived yet, including "claim paid" — there is no reliable payment date in Graphite, so triggering on it would tell customers the wrong thing.',
    whoActs: 'Whichever department the event lands on. Anything marked "needs a signature" is waiting on a person.',
    caveat: 'Collapsing the routine ones is why a huge day of payments does not become 50,000 queue lines. Nothing is dropped — the count is on the summary line.',
  },

  by_category: {
    label: 'Active book by product',
    means: 'How many in-force policies we have, split into health (MIS), domestic (DOM) and commercial (COM).',
    howCounted: 'Policies with status = 1, grouped by the policy-number prefix: MIS/ADH = health, DOMG = domestic, COMG/COMD = commercial.',
    source: 'The compliance census, grouping in-force policies by policy-number prefix',
    excludes: 'Never-activated and lapsed policies.',
    whoActs: 'Nobody — context for the numbers above.',
  },

  // ── Housekeeping ─────────────────────────────────────────────────
  sweep: {
    label: 'Last sweep',
    means: 'When the Brain last looked, and whether it looked at real data.',
    howCounted: 'The sweep runs once every morning at 07:45 Gaborone — deliberately AFTER the overnight debit run, so arrears reflect the debits that actually went through, and the list is ready before Finance sit down at 08:00.',
    source: 'The nightly scheduler. Source "ro [live]" means the read-only copy of Graphite; anything saying "fixture" means TEST DATA, not real figures.',
    excludes: 'Nothing that happened since the sweep. This is a photograph, not a live feed.',
    whoActs: 'Nobody — but if the date is not today, stop and tell IT.',
  },

  priority: {
    label: 'Priority',
    means: 'How urgent the Brain thinks an item is, 0 to 100.',
    howCounted: '80 and above = high (cancel candidates, fraud holds, high-arrears). 40 to 79 = medium. Below 40 = low / for information.',
    source: 'The nightly sweep — each exception is given its priority as it is created',
    excludes: 'It is a ranking, not a rand value. A high priority is not automatically a big number.',
    whoActs: 'Work top down.',
  },

  // ── Data-Analytics workbook tabs (the extract's analytics sheets) ─────────
  // One entry per new tab in the Data-Analytics workbook. The read-me sheet of
  // that workbook renders these so every tab explains itself before anyone
  // queries a figure. Definitions only — no customer data.
  wb_weekly_update: {
    label: 'Weekly Update (workbook tab)',
    means: 'The one-page claims picture for the current financial year: reserves and payments by group and by claim type, with the loss ratios the CFO signs off on.',
    howCounted: 'Reserve is the full estimated cost of a claim; payment is what has actually been paid. Loss Ratio on Reserve is reserve divided by premium; Loss Ratio on Payment is paid divided by premium. Premium is already net of VAT.',
    source: 'The read-only analytics feed the morning sweep builds from the Graphite replica.',
    excludes: 'BONU legal business is excluded from every total (it sits on its own manual line). No budget or variance figures. VAT is not re-added — the premium is already net of VAT.',
    whoActs: 'Data Analytics team; the CFO for sign-off.',
    caveat: 'Figures are indicative and read-only — the Brain lists them, it changes nothing. No customer names, identity numbers, bank details or addresses appear; counts and money totals only.',
  },
  wb_major_claims: {
    label: 'Major Claims (workbook tab)',
    means: 'The standing watch-list of large claims (reserve over 300,000 Pula), plus claim frequency by type and each broker\'s loss ratio.',
    howCounted: 'A claim is listed when its full estimated reserve is over 300,000 Pula. Claim frequency is the claim count divided by the in-force policy count for that type. A broker is flagged when its loss ratio on reserve is above 70 percent.',
    source: 'The read-only analytics feed built from the Graphite replica each morning.',
    excludes: 'Voided coverage lines are removed before reserves are summed. Direct, unbrokered business does not appear in the broker section. No customer names — claims are shown by claim number only.',
    whoActs: 'Claims and Underwriting; a broker over 70 percent escalates to the CFO.',
    caveat: 'Figures are indicative and read-only — the Brain lists, it does not settle, pay or repudiate anything. Claim numbers and amounts only; no personal data.',
  },
  wb_premium_analysis: {
    label: 'Premium Analysis (workbook tab)',
    means: 'Written premium for the financial year, broken down by product line, split motor versus non-motor, and by business group.',
    howCounted: 'Premium is summed for issued policies in the current financial year and shown net of VAT. Each product line is wholly motor or non-motor, so the two split columns add back to the line total. Share is each line as a percentage of the total.',
    source: 'The read-only analytics feed built from the Graphite replica each morning.',
    excludes: 'BONU legal business is excluded from the group total. Prior-year and year-on-year columns stay blank until a prior-year figure is loaded. No budget or variance figures. VAT is not re-added.',
    whoActs: 'Data Analytics and Finance.',
    caveat: 'Figures are indicative and read-only — the Brain lists them and changes nothing. Money totals only; no customer names, identity numbers, bank details or addresses.',
  },
  wb_claims_analysis: {
    label: 'Claims Analysis (workbook tab)',
    means: 'Claims for the financial year — reserves and payments — broken down by claim type and by business group.',
    howCounted: 'Reserve is the full estimated cost; paid is what has been paid. Both are summed for claims reported in the current financial year, with voided coverage lines removed. Share is each type as a percentage of the total reserve.',
    source: 'The read-only analytics feed built from the Graphite replica each morning.',
    excludes: 'BONU legal business is excluded from the group total. Prior-year and year-on-year columns stay blank until a prior-year figure is loaded. No budget or variance figures.',
    whoActs: 'Data Analytics, Claims and Finance.',
    caveat: 'Figures are indicative and read-only — the Brain lists, it settles nothing. Claim counts and amounts only; no personal data.',
  },
  wb_renewals: {
    label: 'Renewals 30-day trigger (workbook tab)',
    means: 'Policies falling due within the next 30 days, as a chase list for renewals.',
    howCounted: 'One row per in-force policy whose expiry date is today or up to 30 days away, ordered by soonest expiry. It carries the policy number, the GFS reference, the product line and the days left.',
    source: 'The read-only analytics feed built from the Graphite replica each morning.',
    excludes: 'A summed Portal-plus-Graphite premium is deliberately left off this tab — combining the two double-counts about 21 percent of it, so it stays out pending written sign-off. Already-expired policies are excluded.',
    whoActs: 'The renewals desk.',
    caveat: 'Figures are indicative and read-only — the Brain lists, it renews nothing. Policy and product references only; no customer names, identity numbers, bank details or addresses.',
  },
  wb_kyc_completeness: {
    label: 'KYC Completeness (workbook tab)',
    means: 'How complete customer identity paperwork is, by branch and selling agent — as counts, not names.',
    howCounted: 'For each branch and agent, the count of in-force policies and how many of those customers have at least one identity document on file, read through the masked view. Percent complete is the second divided by the first.',
    source: 'The read-only analytics feed, reading identity paperwork only through the masked customer view.',
    excludes: 'The document itself is never read — only whether one is present. No Omang, passport, licence, bank or address values appear. Direct, unbrokered policies are not shown.',
    whoActs: 'The KYC team.',
    caveat: 'Figures are indicative and read-only — the Brain lists, it chases nobody. Counts and percentages only; the agent is a staff number, never a name.',
  },
};

// Short answers to the questions people actually ask the console.
const FAQ = [
  {
    q: 'What is the Alpha Brain, in one sentence?',
    a: 'Every night it reads Graphite, works out which policies and customers need a human, ranks them, and hands each team its list. It never acts by itself.',
  },
  {
    q: 'Can it cancel a policy or send a customer a message?',
    a: 'No. Live arms are OFF and always have been. It decides and queues; a named person approves; the approval is recorded. Nothing has ever been executed.',
  },
  {
    q: 'Why is Compliance only a few hundred when thousands of customers are missing documents?',
    a: 'The Compliance queue is today\'s chase list: not-approved KYC records, active policy, opened in the last 400 days. The full backlog is the separate "KYC gap on the active book" figure, which is much bigger. Both are correct; they answer different questions.',
  },
  {
    q: 'Why do the team numbers not equal the total?',
    a: 'They must. Total is the sum of the teams and nothing else. If it does not add up on screen, that is a bug — report it.',
  },
  {
    q: 'Why is the collections clock showing zero?',
    a: 'Because the collections feed returned no rows on the last sweep. That is a data gap to fix, not evidence that everyone is paying.',
  },
  {
    q: 'Is customer data leaving the building?',
    a: 'No. KYC is read through a masked view that returns yes/no flags only — never a raw Omang, passport or scanned document. Nothing goes to an outside AI service.',
  },
];

/** metric(id) → the definition, or null. */
function metric(id) {
  return METRICS[id] || null;
}

/** glossary() → the whole payload the console fetches once and caches. */
function glossary() {
  return {
    version: 1,
    metrics: METRICS,
    faq: FAQ,
    note: 'Plain-English definitions only. No customer data.',
  };
}

/**
 * missingDefinitions(ids) → the ids the console wants to render that have no
 * definition yet. The console shows a number ONLY when it can explain it, so
 * this is the guard-rail: a new tile without a glossary entry fails the test.
 */
function missingDefinitions(ids = []) {
  return ids.filter((id) => !METRICS[id]);
}

module.exports = { METRICS, FAQ, metric, glossary, missingDefinitions };
