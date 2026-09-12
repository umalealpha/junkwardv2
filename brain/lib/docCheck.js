'use strict';
/**
 * docCheck.js — turn OCR output into a backdating / mismatch check on a claim.
 *
 * The OCR itself is already local + free (Omni RapidOCR, Graphite OcrExtractor,
 * Tesseract.js intake). This module consumes the EXTRACTED fields only (dates,
 * amounts, a confidence score) — the raw document text stays in the on-prem
 * system, nothing leaves, no external service. That keeps customer PII in place.
 *
 * It catches the CFO's case: a customer pays, then files a claim for a loss the
 * paperwork shows happened earlier. If the police report / receipt date sits
 * BEFORE the payment date, that is strong backdating evidence → hold.
 *
 * Poor OCR must never auto-decide: below the confidence floor it asks for a
 * human check rather than acting on a bad read.
 */

const DAY = 86400000;
// date-only diff (datetimes truncated so same-day can never compute as -1)
const days = (a, b) => Math.floor((Date.parse(String(b).slice(0, 10)) - Date.parse(String(a).slice(0, 10))) / DAY);
const DEFAULTS = { toleranceDays: 2, amountTolerancePct: 0.1, minConfidence: 0.6, firstPaymentWindowDays: 31 };

/**
 * crossCheck(stated, extracted, opts) -> { recommendation, flags }
 *   stated:    { dateOfLoss, amount, paymentDate, coverStart?, isFirstPayment? }
 *   extracted: { incidentDate?, docDate?, amount?, confidence? }  (from OCR)
 *   recommendation: 'hold' | 'refer' | 'manual_review' | 'ok'
 */
function crossCheck(stated = {}, extracted = {}, opts = {}) {
  const c = { ...DEFAULTS, ...opts };
  const flags = [];
  const add = (code, severity, detail) => flags.push({ code, severity, detail });

  const conf = extracted.confidence != null ? Number(extracted.confidence) : null;
  const lowConfidence = conf != null && conf < c.minConfidence;
  if (lowConfidence) add('low_ocr_confidence', 'note', `OCR confidence ${conf} below ${c.minConfidence} — verify by hand`);

  const docEvent = extracted.incidentDate || extracted.docDate || null;

  // Backdating: the document's own event date predates the premium payment.
  // CFO 2026-07-21: severe only on NEW BUSINESS (the payment is the policy's
  // first premium) — on an in-force monthly policy the latest debit routinely
  // lands after the incident, which is arrears, not backdating.
  if (docEvent && stated.paymentDate && days(stated.paymentDate, docEvent) < 0) {
    const firstPayment = stated.isFirstPayment === true ||
      (stated.coverStart && Math.abs(days(stated.coverStart, stated.paymentDate)) <= c.firstPaymentWindowDays);
    if (firstPayment) add('doc_predates_payment', 'severe', 'the document date is before the first premium was paid');
    else if (!stated.coverStart) add('doc_predates_latest_payment', 'soft', 'document date is before the latest payment and cover start is unknown — verify payment history');
  }

  // The stated loss date and the document's incident date disagree.
  if (extracted.incidentDate && stated.dateOfLoss &&
      Math.abs(days(extracted.incidentDate, stated.dateOfLoss)) > c.toleranceDays) {
    add('loss_date_mismatch', 'soft',
        `document incident date and stated loss date differ by more than ${c.toleranceDays} day(s)`);
  }

  // Amounts disagree beyond tolerance.
  const sa = Number(stated.amount), ea = Number(extracted.amount);
  if (Number.isFinite(sa) && Number.isFinite(ea) && Math.max(sa, ea) > 0 &&
      Math.abs(sa - ea) / Math.max(sa, ea) > c.amountTolerancePct) {
    add('amount_mismatch', 'soft', `stated amount ${sa} vs document amount ${ea}`);
  }

  // Poor OCR must never auto-decide (CFO 2026-07-21): below the confidence
  // floor every comparison flag is demoted to a note and a human reviews —
  // a misread date on a blurry police report cannot drive a hold.
  if (lowConfidence) {
    for (const f of flags) if (f.code !== 'low_ocr_confidence') f.severity = 'note';
    return { recommendation: 'manual_review', flags };
  }

  const severe = flags.some((f) => f.severity === 'severe');
  const soft = flags.filter((f) => f.severity === 'soft').length;
  let recommendation;
  if (severe) recommendation = 'hold';
  else if (soft >= 1) recommendation = 'refer';
  else recommendation = 'ok';

  return { recommendation, flags };
}

module.exports = { crossCheck, DEFAULTS };
