'use strict';
/**
 * commissionGuard.js — stop commission farming on instant insurance, where one
 * person's card is used to buy policies for many unrelated customers so an agent
 * harvests commission (e.g. one card paying for Motel, Artsi, Molifai, Pako, Kago).
 *
 * Guard-rail choice (CFO 2026-07-09): do NOT block the customer's cover — flag
 * the pattern and HOLD the agent's commission on those policies for review. The
 * fraud simply does not get paid; genuine buyers stay covered. A strict hard cap
 * is available via opts if wanted.
 *
 * Keys on the payment's TOKEN (Graphite TransactionToken) + the agent (agent_id)
 * — never the raw card number. Deterministic, internal, no external calls, no PII
 * leaves. Feeds the Agent Portal pay-run (hold/reject the commission line).
 */

const DEFAULTS = { maxCustomersPerCard: 3, agentFarmCustomerCount: 4 };

const mask = (t) => { const s = String(t || ''); return s.length > 4 ? '…' + s.slice(-4) : s; };
const uniq = (arr) => Array.from(new Set(arr));

/**
 * scan(acquisitions, opts) -> { flaggedCards, flaggedAgents, commissionHold, summary }
 *   acquisition: { cardToken, customerId, agentId, policyNumber, date, amount }
 */
function scan(acquisitions = [], opts = {}) {
  const c = { ...DEFAULTS, ...opts };
  const byCard = new Map();
  for (const a of acquisitions) {
    if (!a || !a.cardToken) continue;
    const g = byCard.get(a.cardToken) || [];
    g.push(a); byCard.set(a.cardToken, g);
  }

  const flaggedCards = [];
  const commissionHold = [];
  const agentHits = new Map(); // agentId -> { cards:Set, customers:Set }

  for (const [cardToken, list] of byCard) {
    const customers = uniq(list.map((x) => x.customerId));
    if (customers.length > c.maxCustomersPerCard) {
      const agents = uniq(list.map((x) => x.agentId));
      flaggedCards.push({
        cardToken: mask(cardToken), distinctCustomers: customers.length,
        customers, agents, policies: uniq(list.map((x) => x.policyNumber)),
        reason: `one card funded ${customers.length} different customers (limit ${c.maxCustomersPerCard})`,
      });
      for (const a of list) {
        commissionHold.push({ policyNumber: a.policyNumber, agentId: a.agentId, cardToken: mask(cardToken),
          reason: 'card funds multiple unrelated customers — commission held for review' });
        const h = agentHits.get(a.agentId) || { cards: new Set(), customers: new Set() };
        h.cards.add(cardToken); h.customers.add(a.customerId); agentHits.set(a.agentId, h);
      }
    }
  }

  const flaggedAgents = [];
  for (const [agentId, h] of agentHits) {
    if (h.customers.size >= c.agentFarmCustomerCount) {
      flaggedAgents.push({ agentId, reason: 'commission-farming pattern (many customers via shared card(s))',
        cardTokens: Array.from(h.cards).map(mask), customerCount: h.customers.size });
    }
  }

  return {
    flaggedCards, flaggedAgents, commissionHold,
    summary: { cardsFlagged: flaggedCards.length, agentsFlagged: flaggedAgents.length, commissionsHeld: commissionHold.length },
  };
}

/**
 * checkAcquisition(acq, history, opts) — real-time guard at point of sale.
 * history: prior acquisitions on the SAME card. Cover is always allowed; the
 * commission is held once a card crosses the customer limit.
 * -> { allowCover, holdCommission, distinctCustomersOnCard, reason }
 */
function checkAcquisition(acq = {}, history = [], opts = {}) {
  const c = { ...DEFAULTS, ...opts };
  const customers = uniq([...history.filter((h) => h.cardToken === acq.cardToken).map((h) => h.customerId), acq.customerId]);
  const over = customers.length > c.maxCustomersPerCard;
  return {
    allowCover: true, // never leave a genuine customer uncovered
    holdCommission: over,
    hardBlock: over && !!opts.hardCap,
    distinctCustomersOnCard: customers.length,
    reason: over
      ? `this card now covers ${customers.length} different customers — commission held for review`
      : 'ok',
  };
}

module.exports = { scan, checkAcquisition, DEFAULTS };
