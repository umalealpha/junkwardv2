/**
 * Microsoft Teams webhook notifications (fire-and-forget).
 *
 * Used by server.js to alert admins when a backdate change happens.
 * Never blocks the API response — failures are logged and swallowed.
 *
 * Webhook URL is read from settings table (key: 'teamsWebhookUrl').
 * If empty, no notification is sent.
 */

/**
 * Build an Adaptive Card payload for a backdate event.
 * @param {object} ev - { claim_number, username, user_role, changes: [{field, old, new}], grantInfo: string|null, timestamp }
 * @returns {object} Teams MessageCard payload
 */
function buildBackdateCard(ev) {
  const changesText = (ev.changes || []).map(c =>
    `**${c.field}**: ${c.old || '(blank)'} → ${c.new || '(blank)'}`
  ).join('  \n');

  const grantLine = ev.grantInfo
    ? `Authorised by grant: ${ev.grantInfo}`
    : 'Admin override (no grant required)';

  return {
    "@type": "MessageCard",
    "@context": "https://schema.org/extensions",
    summary: `Backdate alert for claim ${ev.claim_number || ev.claim_id}`,
    themeColor: "EF4444",
    title: `⚠ Backdate alert — ${ev.claim_number || ev.claim_id}`,
    sections: [
      {
        facts: [
          { name: "User",      value: `${ev.username} (${ev.user_role})` },
          { name: "Claim",     value: ev.claim_number || ev.claim_id },
          { name: "When",      value: ev.timestamp || new Date().toISOString() },
          { name: "Authority", value: grantLine },
        ],
        text: `**Changes:**  \n${changesText || '(none)'}`
      }
    ]
  };
}

/**
 * Post a backdate alert to the configured Teams webhook.
 * Fire-and-forget — does not throw, does not block.
 * @param {string} webhookUrl - Teams incoming webhook URL (empty = no-op)
 * @param {object} ev - event payload (see buildBackdateCard)
 */
function sendBackdateAlert(webhookUrl, ev) {
  // CFO 2026-08-31: Teams alerts go to our own STAFF, so they are gated by the
  // INTERNAL arm (BRAIN_INTERNAL_REPORTS), not the customer arm (BRAIN_LIVE_ARMS).
  // Meta is claim/user only (no PII). Nothing customer-facing here.
  if (require('./armed').refuseInternalIfOff('teams:send', {
    claim: (ev && (ev.claim_number || ev.claim_id)) || null,
    user:  (ev && ev.username) || null,
  })) {
    return;
  }
  if (!webhookUrl) return;

  const body = JSON.stringify(buildBackdateCard(ev));
  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), 5000);

  fetch(webhookUrl, {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body,
    signal:  ctrl.signal,
  })
    .then(res => {
      clearTimeout(timer);
      if (!res.ok) console.warn(`[Teams] Webhook responded ${res.status}`);
    })
    .catch(err => {
      clearTimeout(timer);
      console.warn('[Teams] Webhook send failed:', err.message);
    });
}

module.exports = { sendBackdateAlert, buildBackdateCard };
