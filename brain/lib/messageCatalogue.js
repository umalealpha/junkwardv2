'use strict';
/**
 * messageCatalogue.js — customer notification copy for 14 policy/claim lifecycle
 * events, each with { sms, whatsapp, email:{subject,text} }.
 *
 * Provenance: built on the off-subscription lane (DeepSeek draft + Gemini verify),
 * passed a structure/placeholder gate, then verified by Opus and reviewed by Fable;
 * 5 messages reworked for regulated-insurer compliance and tone (decline recourse,
 * "sent to your bank" not "in your account", Agreement-of-Loss explanation, and
 * date/amount on renewal + lapse). Data only — wire into lib/notifier.js when ready.
 *
 * Placeholders (filled by the notifier at send time), all optional except the
 * claim/policy reference: {contactName} {claimNumber} {policyNumber} {link}
 * {amount} {date} {supplierName} {assessorName}.
 */
const catalogue = require('./messageCatalogue.json');

/** getMessage('claim_paid') -> { event, sms, whatsapp, email:{subject,text} } | null */
function getMessage(event) {
  return catalogue[event] || null;
}

module.exports = { catalogue, getMessage };
