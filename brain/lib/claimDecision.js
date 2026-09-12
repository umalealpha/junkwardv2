'use strict';
/**
 * claimDecision.js — the Finance Manager's one-screen claim brief. Enter a claim
 * number and get: policy details, payment/premium position, the OCR of the police
 * report + claim documents, and a single verdict — PAY / NO-PAY / INVESTIGATE —
 * with the reasons.
 *
 * It composes the pieces already built (deterministic, on-prem, no external, no
 * PII leaves): premiumAdvisor (on-risk / arrears), fraud (backdating, duplicate,
 * pay-then-claim), docCheck (police-report vs payment dates). The document TEXT
 * comes from the existing local OCR (RapidOCR / Graphite OcrExtractor); this
 * module consumes the extracted fields only.
 *
 * The verdict is a RECOMMENDATION — the FM still authorises. Money is never moved
 * here.
 *
 * Verdict:
 *   NO-PAY      — no cover or clear backdating (loss before cover / before the
 *                 FIRST premium, police report predating the first premium, or a
 *                 formal cancellation effective before the loss)
 *   INVESTIGATE — lapsed/inactive status (Finance referral — payment plan may
 *                 apply), arrears to confirm, soft fraud flags, document
 *                 mismatch, or a poor OCR read (also emails Claims for the scan)
 *   PAY         — on risk, documents consistent, no flags
 */

const { decide } = require('./premiumAdvisor');
const fraud = require('./fraud');
const doc = require('./docCheck');

const NO_PAY_FRAUD = new Set(['loss_before_cover', 'loss_before_payment']);

function build(bundle = {}) {
  const { claim = {}, policy = {}, premium = {}, extracted = {}, priorClaims = [] } = bundle;

  // ── run the checks ──
  const premiumRes = decide({ arrearsMonths: premium.arrearsMonths, financeApproved: premium.financeApproved });
  const fraudRes = fraud.assess({
    coverStart: policy.coverStart, dateOfLoss: claim.dateOfLoss, dateReported: claim.dateReported,
    claimType: claim.claimType, paymentDate: premium.paymentDate,
    sumInsured: policy.sumInsured, estimate: claim.estimate, priorClaims,
  });
  const docRes = doc.crossCheck(
    { dateOfLoss: claim.dateOfLoss, amount: claim.amount, paymentDate: premium.paymentDate,
      coverStart: policy.coverStart, isFirstPayment: premium.isFirstPayment },
    extracted
  );

  // ── decide the verdict ──
  const reasons = [];
  const actions = [];
  // CFO 2026-07-21: NO-PAY for policy status ONLY when the policy was formally
  // CANCELLED with an effective date on/before the loss. A "lapsed"/"inactive"
  // status is usually arrears-driven — that is a Finance referral (a payment
  // plan may be in place), NEVER an automatic decline.
  const statusAtLoss = String(policy.statusAtLoss || '').toLowerCase();
  const cancelledBeforeLoss = statusAtLoss === 'cancelled' && policy.cancellationEffective && claim.dateOfLoss &&
    String(policy.cancellationEffective).slice(0, 10) <= String(claim.dateOfLoss).slice(0, 10);
  const statusReferral = !cancelledBeforeLoss && ['lapsed', 'cancelled', 'inactive'].includes(statusAtLoss);
  const fraudNoPay = fraudRes.flags.filter((f) => NO_PAY_FRAUD.has(f.code));
  const docNoPay = docRes.flags.some((f) => f.code === 'doc_predates_payment' && f.severity === 'severe');

  let verdict = 'PAY';
  if (cancelledBeforeLoss || fraudNoPay.length || docNoPay) {
    verdict = 'NO-PAY';
    if (cancelledBeforeLoss) reasons.push(`policy was formally cancelled effective ${String(policy.cancellationEffective).slice(0, 10)} — before the date of loss`);
    fraudNoPay.forEach((f) => reasons.push(f.detail));
    if (docNoPay) reasons.push('the police report / receipt is dated before the first premium was paid (backdating)');
  } else {
    const investigate = [];
    if (statusReferral) investigate.push(`policy status is "${statusAtLoss}" at the date of loss — refer to Finance (a payment plan may apply); NO-PAY only on a formal cancellation effective before the loss`);
    if (premiumRes.decision === 'REFER') investigate.push(`premium is ${premiumRes.arrearsMonths} month(s) in arrears — confirm with Finance`);
    fraudRes.flags.forEach((f) => { if (f.severity === 'soft' || f.severity === 'severe') investigate.push(f.detail); });
    docRes.flags.filter((f) => f.code !== 'doc_predates_payment').forEach((f) => investigate.push(f.detail));
    // any duplicate (severe, non-void) forces investigate at least
    if (fraudRes.recommendation === 'hold' && !fraudNoPay.length) investigate.push('possible duplicate claim — verify');
    if (investigate.length) { verdict = 'INVESTIGATE'; reasons.push(...investigate); }
    else reasons.push('on risk, documents consistent, no flags');
  }

  // CFO 2026-07-21: an unreadable/low-confidence document pack goes to the
  // Claims team by email for a hand check (alert_ verbs execute via the
  // orchestrator's sendAlert when arms are wired).
  if (docRes.recommendation === 'manual_review') {
    actions.push({ do: 'alert_claims_docs_review', dept: 'Claims',
      note: 'OCR confidence below the floor — Claims to verify the documents by hand' });
  }

  // ── assemble the brief the FM reads ──
  const sections = {
    policy: {
      policyNumber: policy.policyNumber, product: policy.product, insured: policy.insured,
      coverStart: policy.coverStart, coverEnd: policy.coverEnd, sumInsured: policy.sumInsured,
      statusAtLoss: policy.statusAtLoss,
    },
    payment: {
      onRisk: premiumRes.decision === 'GO',
      arrearsMonths: premiumRes.arrearsMonths, amountOwing: premium.amountOwing,
      lastPaymentDate: premium.paymentDate,
    },
    documents: {
      policeReport: extracted.policeReport != null ? !!extracted.policeReport : (extracted.incidentDate ? true : null),
      incidentDate: extracted.incidentDate || null, documentDate: extracted.docDate || null,
      documentAmount: extracted.amount != null ? extracted.amount : null,
      ocrConfidence: extracted.confidence != null ? extracted.confidence : null,
    },
    checks: { premium: premiumRes.decision, fraud: fraudRes.recommendation, documents: docRes.recommendation },
  };

  return { claimNumber: claim.claimNumber, verdict, reasons, actions, sections,
           detail: { premium: premiumRes, fraud: fraudRes, documents: docRes } };
}

/** renderBrief(result) -> plain text for the FM screen / email. */
function renderBrief(r) {
  const s = r.sections;
  const line = (k, v) => `${k}: ${v == null ? '—' : v}`;
  return [
    `CLAIM ${r.claimNumber || ''} — VERDICT: ${r.verdict}`,
    '',
    'Why: ' + (r.reasons.length ? r.reasons.join('; ') : '—'),
    '',
    'Policy',
    '  ' + line('Number', s.policy.policyNumber),
    '  ' + line('Product', s.policy.product),
    '  ' + line('Sum insured', s.policy.sumInsured),
    '  ' + line('Status at loss', s.policy.statusAtLoss),
    'Premium',
    '  ' + line('On risk', s.payment.onRisk ? 'yes' : 'no'),
    '  ' + line('Months in arrears', s.payment.arrearsMonths),
    '  ' + line('Amount owing', s.payment.amountOwing),
    'Documents (OCR)',
    '  ' + line('Police report', s.documents.policeReport),
    '  ' + line('Incident date', s.documents.incidentDate),
    '  ' + line('OCR confidence', s.documents.ocrConfidence),
    'Checks',
    '  ' + line('Premium', s.checks.premium) + ' · ' + line('Fraud', s.checks.fraud) + ' · ' + line('Documents', s.checks.documents),
  ].join('\n');
}

module.exports = { build, renderBrief };
