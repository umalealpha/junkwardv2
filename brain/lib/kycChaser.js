'use strict';
/**
 * kycChaser.js — chase missing / invalid KYC documents to compliance, at scale.
 *
 * OCR is LOCAL and FREE — no paid API (70k customers would burn an API in a day),
 * and customer IDs are PII that must never leave the building. Tiered:
 *   Tier 1  RapidOCR (already in omni core/doc_parse; CPU, fast) — reads the bulk.
 *   Tier 2  Ollama vision (llava/moondream on the M4 mini or the server) — fallback
 *           for poor reads / "is this really an ID?" classification.
 * This module is the BRAIN: given the OCR result per document it decides what is
 * missing or invalid and runs the chase ladder. It consumes OCR OUTPUT only
 * (detected type, confidence, expiry) — never the raw ID content, nothing external.
 *
 * Never auto-rejects on a poor scan → low confidence goes to a human (manual review).
 * Never cancels cover → flags KYC-incomplete and escalates; a poor read is not the
 * customer's fault.
 */

const DEFAULTS = {
  confidenceFloor: 0.6,
  reminderCap: 2,
  graceDays: 14,
  // "3 compulsory docs" for motor comprehensive — CONFIRM the exact set with UW.
  requiredByProduct: {
    motor_comprehensive: ['id', 'drivers_licence', 'proof_of_address'],
    motor_third_party: ['id'],
    // Individual KYC/AML — deeper than just ID.
    domestic: ['id', 'proof_of_address', 'proof_of_income', 'source_of_funds', 'next_of_kin'],
    // Full entity due diligence (FICA/AML) — goes all the way to beneficial ownership.
    commercial: [
      'certificate_of_incorporation', 'company_registration', 'memorandum_articles',
      'directors_ids', 'shareholders_register', 'ultimate_beneficial_ownership',
      'board_resolution', 'authorised_signatory_id', 'proof_of_business_address',
      'tax_clearance', 'source_of_funds', 'bank_confirmation',
    ],
    instant: ['id'],
    healthcare: ['id', 'proof_of_address', 'dependant_ids', 'proof_of_income'], // medical aid — CONFIRM with health team
  },
};

/** one document's verdict from its OCR result */
function checkDoc(doc, cfg) {
  if (!doc || !doc.present) return { ok: false, reason: 'missing' };
  const o = doc.ocr || {};
  if (o.confidence != null && Number(o.confidence) < cfg.confidenceFloor) return { ok: false, reason: 'low_confidence' };
  if (o.detectedType && doc.type && o.detectedType !== doc.type) return { ok: false, reason: 'wrong_document' };
  if (o.expiry && Date.parse(o.expiry) < Date.parse(cfg.today || o.today || new Date().toISOString().slice(0, 10))) return { ok: false, reason: 'expired' };
  return { ok: true, reason: 'valid' };
}

/**
 * assess(customer, opts) -> { status, kycCompliant, missing, invalid, needsReview, actions }
 *   customer: { product, docs:[{type, present, ocr:{detectedType, confidence, expiry}}], remindersSent, firstRequestedDaysAgo }
 *   status: COMPLIANT | INCOMPLETE | REVIEW
 */
function assess(customer = {}, opts = {}) {
  const cfg = { ...DEFAULTS, ...opts, requiredByProduct: { ...DEFAULTS.requiredByProduct, ...(opts.requiredByProduct || {}) } };
  const required = cfg.requiredByProduct[customer.product] || ['id'];
  const byType = new Map((customer.docs || []).map((d) => [d.type, d]));

  const missing = [], invalid = [], needsReview = [];
  for (const type of required) {
    const doc = byType.get(type) || { type, present: false };
    const v = checkDoc(doc, cfg);
    if (v.ok) continue;
    if (v.reason === 'missing') missing.push(type);
    else if (v.reason === 'low_confidence') needsReview.push({ type, reason: 'poor scan — verify by hand' });
    else invalid.push({ type, reason: v.reason });
  }

  let status = 'COMPLIANT';
  if (missing.length || invalid.length) status = 'INCOMPLETE';
  else if (needsReview.length) status = 'REVIEW';
  const kycCompliant = status === 'COMPLIANT';

  // chase ladder — request, remind up to the cap, then escalate + flag cover
  const actions = [];
  const outstanding = missing.length + invalid.length;
  if (outstanding > 0) {
    const sent = Number(customer.remindersSent) || 0;
    const overdue = (Number(customer.firstRequestedDaysAgo) || 0) >= cfg.graceDays;
    if (sent === 0) actions.push({ do: 'request_documents', items: [...missing, ...invalid.map((i) => i.type)] });
    else if (sent <= cfg.reminderCap && !overdue) actions.push({ do: 'remind_customer', reminderNo: sent + 1 });
    else actions.push({ do: 'escalate_flag_kyc_hold', note: 'reminders exhausted / past grace — flag KYC-incomplete, block claim payout until resolved' });
  }
  if (needsReview.length) actions.push({ do: 'manual_review', items: needsReview.map((r) => r.type) });

  return { status, kycCompliant, missing, invalid, needsReview, actions };
}

module.exports = { assess, checkDoc, DEFAULTS };
