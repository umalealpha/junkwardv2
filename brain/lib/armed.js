'use strict';
/**
 * armed.js — the ONE real OFF switch (CFO 2026-07-13).
 *
 * Every outbound send (email / SMS / WhatsApp / alert) and every external write
 * MUST call refuseIfOff() first. When BRAIN_LIVE_ARMS is not exactly 'true' the
 * action is REFUSED and logged — even if the provider keys / credentials are
 * present. This is deliberately independent of whether Mailgun/Infobip are
 * configured: keys being plugged in must NOT be enough to send.
 *
 * Read at call-time (process.env), not cached, so the switch is authoritative
 * and a flip takes effect without a stale module cache.
 */

function liveArmsOn() {
  return process.env.BRAIN_LIVE_ARMS === 'true';
}

/**
 * refuseIfOff(action, meta) -> boolean
 *   Returns TRUE when the caller must NOT proceed (arms are OFF) and logs the
 *   refusal. Returns FALSE only when live arms are ON.
 */
function refuseIfOff(action, meta) {
  if (liveArmsOn()) return false;
  let m = '';
  try { m = meta ? ' ' + JSON.stringify(meta).slice(0, 200) : ''; } catch (_) {}
  console.log(`[brain] LIVE ARMS OFF — refused ${action}${m} (nothing sent or written)`);
  return true;
}

/**
 * internalReportsOn() -> boolean
 *
 * A SEPARATE, NARROWER switch for INTERNAL reports only (BRAIN_INTERNAL_REPORTS).
 * Added 2026-07-28 for the weekly team extracts the CFO asked for.
 *
 * Why it is not just liveArmsOn(): live arms govern actions that touch a CUSTOMER
 * or move MONEY — a suspension SMS, a cancellation, a refund. Emailing our own
 * Debtors team its own worklist is neither. Forcing the weekly extract through the
 * customer-action switch would mean turning customer arms ON just to send a
 * spreadsheet to staff, which is exactly the accident this file exists to prevent.
 *
 * The guard-rails on this switch: internal recipients only (checked by the caller
 * against an approved-domain list), attachments built from the brain's own
 * snapshot, and no customer-facing content. It can never send to a customer.
 * Off by default, like everything else.
 */
function internalReportsOn() {
  return process.env.BRAIN_INTERNAL_REPORTS === 'true';
}

/** refuseInternalIfOff(action, meta) — the internal-report twin of refuseIfOff. */
function refuseInternalIfOff(action, meta) {
  if (internalReportsOn()) return false;
  let m = '';
  try { m = meta ? ' ' + JSON.stringify(meta).slice(0, 200) : ''; } catch (_) {}
  console.log(`[brain] INTERNAL REPORTS OFF — refused ${action}${m} (nothing sent)`);
  return true;
}

/**
 * omniPushOn() -> boolean
 *
 * A SEPARATE, NARROWER switch for the Graphite→Omni analytics PUSH only
 * (BRAIN_OMNI_PUSH_ARMED). Added 2026-08-31 (CFO status request) so the analytics
 * push can go live INDEPENDENTLY of BRAIN_LIVE_ARMS.
 *
 * Why it is not just liveArmsOn(): live arms govern actions that touch a CUSTOMER
 * or move MONEY — a suspension SMS, a cancellation, a refund. The Omni push is a
 * PII-free, counts-only, read-only OUTBOUND feed to our OWN internal Omni system;
 * it contacts no customer and writes nothing back to Graphite. Forcing it through
 * the customer-action switch would mean turning cancellations ON just to ship
 * analytics — exactly the accident this file exists to prevent. Off by default.
 */
function omniPushOn() {
  return process.env.BRAIN_OMNI_PUSH_ARMED === 'true';
}

/** refuseOmniPushIfOff(action, meta) — the Omni-analytics-push twin of refuseIfOff. */
function refuseOmniPushIfOff(action, meta) {
  if (omniPushOn()) return false;
  let m = '';
  try { m = meta ? ' ' + JSON.stringify(meta).slice(0, 200) : ''; } catch (_) {}
  console.log(`[brain] OMNI PUSH OFF — refused ${action}${m} (nothing sent)`);
  return true;
}

module.exports = {
  liveArmsOn, refuseIfOff,
  internalReportsOn, refuseInternalIfOff,
  omniPushOn, refuseOmniPushIfOff,
};
