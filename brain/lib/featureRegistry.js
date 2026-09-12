'use strict';
/**
 * featureRegistry.js — every feature built into the Alpha Brain, in plain
 * English, with an HONEST wiring status.
 *
 * Why (CFO 2026-07-28): "where are the other 27 features I have built". The
 * console only ever showed two lines — overdue debtors and KYC — because only
 * FIVE of the decision modules are wired into the nightly sweep. The rest are
 * in the image and have passing tests, but nothing calls them, so nobody can
 * see them. Hiding that was the mistake. This registry surfaces it.
 *
 * Status vocabulary (do not invent new ones without adding them here):
 *   live      — runs in the nightly sweep and produces what you see on screen
 *   ready     — built + tested + in the image, but NOT called by the sweep.
 *               Turning it on is wiring work, not a rebuild.
 *   held      — deliberately blocked. `heldBecause` says why. Do not "fix".
 *   plumbing  — a pipe, not a feature (mail, SMS, database reader, auth).
 *
 * Rule: `module` must name a real file in brain/lib (or brain/). The test walks
 * the folder and fails if the registry and the folder disagree — so this can
 * never quietly go stale.
 */

const FEATURES = [
  // ── LIVE: what actually produces the numbers on the console ──────────────
  {
    id: 'collections-clock', name: 'Non-payment clock', module: 'collectionsClock',
    team: 'Finance', status: 'live',
    does: 'Watches for two missed months in a row, then walks the policy through suspend → 15-day grace → cancel, and will not call anything "ready to cancel" until a 5-day notice has been served.',
    why: 'Replaces someone reading arrears reports and deciding by hand.',
  },
  {
    id: 'collections-leak', name: 'Debit-on-a-dead-policy watch', module: 'collectionsLeak',
    team: 'Finance', status: 'live',
    does: 'The reverse of the non-payment clock: finds MIS policies that are deactivated, cancelled or expired but whose RealPay mandate is still live and still taking successful debits, and lists each one for a person to check and stop.',
    why: 'Money leaves the company on policies that no longer exist. The non-payment clock only watches the active book; this watches the dead one — the MIS gap the Finance Exceptions control skips.',
  },
  {
    id: 'ageing', name: 'Correct debtor ageing', module: 'ageing',
    team: 'Finance', status: 'live',
    does: 'Re-ages every account from the invoices and receipts, applying each receipt to the oldest invoice first, so a settled invoice actually drops out of 90 days.',
    why: 'Graphite\'s own ageing report mis-handles unallocated receipts. This is the fix.',
  },
  {
    id: 'kyc-chaser', name: 'KYC chasing', module: 'kycChaser',
    team: 'Compliance', status: 'live',
    does: 'Works out which identity and address documents are missing, expired or unreadable per customer, and escalates to a payout hold once reminders are exhausted. Commercial goes deeper, to beneficial ownership.',
    why: 'Replaces a spreadsheet of who has not sent their Omang.',
  },
  {
    id: 'fraud', name: 'Fraud signals', module: 'fraud',
    team: 'Claims', status: 'live',
    does: 'Scores a claim on hard and soft signals — loss before cover started, late reporting, repeat patterns — and returns proceed / refer / hold.',
    why: 'Catches the obvious ones before money moves.',
  },
  {
    id: 'event-engine', name: 'Lifecycle event fan-out', module: 'eventEngine',
    team: 'All', status: 'live',
    does: 'One event (payment received, claim registered, total loss) fans out into the exact actions each department owes, and marks which ones need a human signature.',
    why: 'This is the original idea: stop each department finding out separately.',
  },
  {
    id: 'compliance-census', name: 'Compliance census', module: 'complianceCensus',
    team: 'Compliance', status: 'live',
    does: 'Counts-only picture of the active book: policies by product, and active policies with no documents at all. Never reads a customer row.',
    why: 'Gives the honest size of the KYC backlog, not just today\'s chase list.',
  },
  {
    id: 'healthcare-compliance', name: 'Healthcare non-compliance', module: 'healthcareCompliance',
    team: 'Compliance', status: 'live',
    does: 'Splits out active health policies (MIS / ADH) whose customer paperwork is not clean — no documents at all, or KYC record not approved.',
    why: 'Health was invisible: it sat inside the one Compliance number with no way to see it.',
    gap: 'Identity paperwork ONLY. Waiting periods, pre-existing-condition declarations, dependant proof and scheme member cards are NOT counted — Compliance has not defined those rules.',
  },
  {
    id: 'friday-report', name: 'Friday non-payment report', module: 'fridayReport',
    team: 'Finance', status: 'live',
    does: 'Builds the weekly deactivated / cancelled report every Friday 18:00.',
    why: 'The CFO\'s standing weekly report, built automatically.',
    gap: 'It is BUILT every week; it only EMAILS once recipient addresses are configured.',
  },
  {
    id: 'weekly-extract', name: 'Weekly team extracts', module: 'weeklyExtract',
    team: 'All', status: 'live',
    does: 'Every Monday at midnight, builds one Excel workbook per team — KYC, Debtors, Data Analytics, Finance, Accounts — with that team\'s exceptions, and emails it.',
    why: 'CFO 28 Jul: teams should not have to come and look; the list should land in their inbox.',
    gap: 'Sends only to the addresses Operations configures. No addresses set = built and downloadable, nothing emailed.',
  },
  {
    id: 'self-serve-extract', name: 'Self-service data extract', module: 'weeklyExtract',
    team: 'All', status: 'live',
    does: 'Any authorised person can pull the same data on demand as Excel or CSV, by team or by dataset.',
    why: 'CFO 28 Jul: "we need a place where people can extract data".',
  },
  {
    id: 'pre-intimation', name: 'Overdue pre-intimation', module: 'preIntimation',
    team: 'Finance', status: 'live',
    does: 'Groups overdue policies into stages and drafts a respectful overdue email per policy — and deliberately holds back the "uncertain" set from any action.',
    why: 'Tell the customer before you suspend them.',
    gap: 'Drafting is live. SENDING is a gated arm — off.',
  },
  {
    id: 'omni-intel', name: 'Omni nightly intel pull', module: 'omniIntel',
    team: 'Finance', status: 'live',
    does: 'Pulls Omni\'s counts-only compliance and collections summary each night so both systems see the same picture.',
    why: 'Stops Omni and Graphite quoting different numbers.',
  },
  {
    id: 'access-control', name: 'Tiered access', module: 'rbac',
    team: 'All', status: 'live',
    does: 'Totals are open to every employee. Customer-level rows are restricted to Finance, Compliance, Underwriting and Claims.',
    why: 'People can see the score without seeing the customers.',
  },

  // ── READY: built, tested, in the image — nothing calls it yet ────────────
  {
    id: 'renewals', name: 'Renewal calendar', module: 'renewals',
    team: 'Underwriting', status: 'ready',
    does: 'Watches expiries and works out when to make the retention offer — one month before lapse — then chases, and flags policies that should be re-rated instead of renewed.',
    why: 'Replaces manually watching expiry dates.',
    toTurnOn: 'Needs the renewals feed added to the nightly sweep.',
  },
  {
    id: 'refund-engine', name: 'Refund with credit note', module: 'refundEngine',
    team: 'Finance', status: 'ready',
    does: 'Builds a refund from the policy\'s own figures and forces both legs together — the money back AND the credit note — so revenue is not left overstated.',
    why: 'Today refunds are done in Excel and FNB and the credit note gets forgotten.',
    toTurnOn: 'Needs a refund feed, and the money leg stays CFO-approved.',
  },
  {
    id: 'reinsurance', name: 'Reinsurance recoveries', module: 'reinsurance',
    team: 'Finance', status: 'ready',
    does: 'Works out the reinsurers\' share of a big claim and watches concentration, instead of building facultative bordereaux by hand.',
    why: 'Hand-calculated recoveries get missed.',
    toTurnOn: 'Needs the treaty terms loaded and a claims feed.',
  },
  {
    id: 'premium-advisor', name: 'Premium-position go/no-go', module: 'premiumAdvisor',
    team: 'Claims', status: 'ready',
    does: 'Answers "is this customer on risk?" from their premium position — within one month behind is still covered; beyond that it stops and asks.',
    why: 'The rule exists; nothing applies it automatically.',
    toTurnOn: 'Wire into claim registration.',
  },
  {
    id: 'claim-decision', name: 'One-screen claim brief', module: 'claimDecision',
    team: 'Claims', status: 'ready',
    does: 'Type a claim number, get the policy, the premium position, the read of the police report and documents, and one verdict: pay / do not pay / investigate, with reasons.',
    why: 'The Finance Manager currently assembles this by hand from four screens.',
    toTurnOn: 'Needs the claim documents reader pointed at the live store.',
  },
  {
    id: 'commission-guard', name: 'Commission-farming guard', module: 'commissionGuard',
    team: 'Underwriting', status: 'ready',
    does: 'Spots one person\'s card paying for many unrelated customers so an agent harvests commission — and flags the commission, never the customer\'s cover.',
    why: 'A real pattern with no control on it.',
    toTurnOn: 'Needs the payments feed added to the sweep.',
  },
  {
    id: 'payer-guard', name: 'Payer-anomaly guard', module: 'payerGuard',
    team: 'Finance', status: 'ready',
    does: 'The broader version: one bank account or card funding many customers across domestic, commercial and instant.',
    why: 'Money-laundering and commission-abuse signal.',
    toTurnOn: 'Same payments feed as above.',
  },
  {
    id: 'doc-check', name: 'Backdating / document mismatch', module: 'docCheck',
    team: 'Claims', status: 'ready',
    does: 'Compares the dates and amounts read off the documents against what was keyed, and flags a mismatch or a backdate.',
    why: 'Backdating is currently caught by luck.',
    toTurnOn: 'Needs the document-read output fed in.',
  },
  {
    id: 'claim-forms', name: 'Right claim form, automatically', module: 'claimForms',
    team: 'Claims', status: 'ready',
    does: 'Maps every claim type to the correct official form and the exact supporting documents required.',
    why: 'Wrong form = a claim that stalls for two weeks.',
    toTurnOn: 'Wire to claim registration.',
  },
  {
    id: 'claim-form-email', name: 'Email the claim form on registration', module: 'claimFormEmail',
    team: 'Claims', status: 'ready',
    does: 'When a claim is registered, sends the customer the right blank form and copies Claims.',
    why: 'Removes a manual step on every single claim.',
    toTurnOn: 'Needs the claim-registered trigger live AND arms on. Sending to customers is a gated arm.',
  },
  {
    id: 'claim-link', name: 'Claimant self-service link', module: 'claimLink',
    team: 'Claims', status: 'ready',
    does: 'Issues a secure one-time link so a claimant can check their own claim status without phoning.',
    why: 'Cuts status-chasing calls.',
    toTurnOn: 'Needs the public page hosted on Graphite.',
  },
  {
    id: 'high-arrears-alert', name: 'High-arrears approval alert', module: 'highArrearsAlert',
    team: 'Finance', status: 'ready',
    does: 'Alerts finance oversight when a claim is approved despite the customer being more than 3 months behind.',
    why: 'CFO directive — visibility, not a block.',
    toTurnOn: 'The DETECTION is live on the dashboard; only the email is off (no recipients set).',
  },
  {
    id: 'smart-claim', name: 'AI second opinion on claims', module: 'smartClaim',
    team: 'Claims', status: 'ready',
    does: 'Rules decide first; a local or DeepSeek model adds a second opinion. If the model is unavailable the answer is still safe.',
    why: 'Extra pair of eyes without depending on one.',
    toTurnOn: 'Needs the local model endpoint configured. No customer data is ever sent to it.',
  },
  {
    id: 'message-catalogue', name: 'Customer message library', module: 'messageCatalogue',
    team: 'All', status: 'ready',
    does: 'Approved SMS, WhatsApp and email wording for 14 policy and claim moments, so every customer gets the same message.',
    why: 'Stops staff writing their own version each time.',
    toTurnOn: 'Used the moment any notification arm is turned on.',
  },
  {
    id: 'orchestrator', name: 'Action orchestrator', module: 'orchestrator',
    team: 'All', status: 'ready',
    does: 'Takes a decision and either does the safe part, queues anything needing a signature, or emits a request to another system.',
    why: 'The piece that would let the Brain act — with dual approval on money.',
    toTurnOn: 'Runs today with NO live connections on purpose: it collects intentions and fires nothing.',
  },

  // ── HELD: deliberately blocked ───────────────────────────────────────────
  {
    id: 'claim-paid-trigger', name: '"Your claim is paid" notification', module: 'eventEngine',
    team: 'Claims', status: 'held',
    does: 'Would tell the customer the moment their claim is paid.',
    heldBecause: 'There is no reliable payment-date anywhere in Graphite. The only dated payment log is for VOIDED payments. Turning this on would tell customers "paid" on the wrong day. This is the biggest single gap in the automation.',
    unblockedBy: 'A real payment-date field on claim payments.',
  },
  {
    id: 'auto-cancel-arrears', name: 'Automatic cancellation for arrears', module: 'collectionsClock',
    team: 'Finance', status: 'held',
    does: 'Would cancel a policy once the clock runs out.',
    heldBecause: 'Graphite\'s cancellation does NOT refund unearned premium. Auto-cancelling would create a refund liability that nothing calculates. Flag-only is correct until the refund side works.',
    unblockedBy: 'The refund engine wired to cancellation.',
  },

  // ── PLUMBING: pipes, not features ────────────────────────────────────────
  { id: 'mailer', name: 'Email sender', module: 'mailer', team: 'All', status: 'plumbing', does: 'Sends the branded emails. Blocked while arms are off.' },
  { id: 'infobip', name: 'SMS sender', module: 'infobip', team: 'All', status: 'plumbing', does: 'Sends SMS. Blocked while arms are off.' },
  { id: 'notifier', name: 'Notification router', module: 'notifier', team: 'All', status: 'plumbing', does: 'Picks the channel — SMS, WhatsApp, email — and records the attempt.' },
  { id: 'teams-notify', name: 'Teams alerts', module: 'teamsNotify', team: 'All', status: 'plumbing', does: 'Posts admin alerts to Microsoft Teams.' },
  { id: 'graphite-ro', name: 'Read-only Graphite reader', module: 'graphiteRo', team: 'All', status: 'plumbing', does: 'Reads Graphite with a select-only account on the replica. Cannot write, by design.' },
  { id: 'collections-ro', name: 'Collections reader', module: 'collectionsRo', team: 'Finance', status: 'plumbing', does: 'The purpose-built read for the non-payment clock.' },
  { id: 'graphite-adapters', name: 'Graphite write adapters', module: 'graphite', team: 'All', status: 'plumbing', does: 'The write-back adapters. Off by default.' },
  { id: 'graphite-erp', name: 'Claim push to Graphite', module: 'graphiteErp', team: 'Claims', status: 'plumbing', does: 'Posts a new claim into Graphite and returns the claim number.' },
  { id: 'graphite-master-sync', name: 'Master-data sync', module: 'graphiteMasterSync', team: 'All', status: 'plumbing', does: 'Keeps the reference lists in step with Graphite.' },
  { id: 'ai-client', name: 'Shared AI client', module: 'ai', team: 'All', status: 'plumbing', does: 'One guarded client for local or DeepSeek models. No Anthropic. Never receives customer data.' },
  { id: 'armed', name: 'The off switch', module: 'armed', team: 'All', status: 'plumbing', does: 'The single gate every outward action must pass. Off = nothing leaves.' },
  { id: 'glossary', name: 'Plain-English definitions', module: 'glossary', team: 'All', status: 'plumbing', does: 'The definition behind every number on screen.' },
  { id: 'feature-registry', name: 'This list', module: 'featureRegistry', team: 'All', status: 'plumbing', does: 'Every feature and its honest status.' },
  { id: 'xlsx', name: 'Excel writer', module: 'xlsx', team: 'All', status: 'plumbing', does: 'Builds real .xlsx workbooks with no outside libraries.' },
  { id: 'track-session', name: 'Self-service session', module: 'trackSession', team: 'Claims', status: 'plumbing', does: 'Signed short-lived session for the claimant status page.' },
  { id: 'dashboard-page', name: 'Internal dashboard', module: 'dashboardPage', team: 'All', status: 'plumbing', does: 'The Brain\'s own admin screen, separate from the Graphite console.' },
];

const STATUS_META = {
  live:     { label: 'Live on this dashboard', means: 'Runs every night and produces what you see here.' },
  ready:    { label: 'Built, not switched on',  means: 'Written and tested, sitting in the system. Switching it on is wiring, not building.' },
  held:     { label: 'Deliberately held',       means: 'Blocked on purpose. The reason is shown. Do not "fix" it.' },
  plumbing: { label: 'Plumbing',                means: 'A pipe the features run on, not something you act on.' },
};

/** counts() → how many features sit in each status. */
function counts() {
  const out = { live: 0, ready: 0, held: 0, plumbing: 0 };
  for (const f of FEATURES) out[f.status] = (out[f.status] || 0) + 1;
  return out;
}

/** registry() → the payload the console fetches for its Features tab. */
function registry() {
  const c = counts();
  return {
    version: 1,
    total: FEATURES.length,
    counts: c,
    statusMeta: STATUS_META,
    headline: `${c.live} features feed this dashboard. ${c.ready} more are built and tested but not switched on. ${c.held} are held on purpose.`,
    features: FEATURES,
  };
}

/** byStatus(status) → the features in one status. */
function byStatus(status) {
  return FEATURES.filter((f) => f.status === status);
}

module.exports = { FEATURES, STATUS_META, counts, registry, byStatus };
