'use strict';
/**
 * ai.js — ONE shared AI client for all tracker features. Runs off the Claude
 * subscription against a configurable gateway (DeepSeek / local Ollama). No
 * Anthropic. Every feature must stay fully functional WITHOUT it: `ask` returns
 * null on any failure or when unconfigured, and callers fall back to their
 * deterministic behaviour.
 *
 * HARD RULE (AD-POL-AI-GOV-001): customer PII must never reach an external model.
 * `ask` refuses — returns null — any prompt that looks like it carries PII (ID /
 * Omang numbers, bank/account numbers, email addresses, long digit runs). Callers
 * must pass MASKED, structural, or numeric data only. This guard is defence in
 * depth, not a licence to be careless.
 *
 * Config (env or opts): AI_GATEWAY_URL, AI_GATEWAY_KEY, AI_MODEL (default worker-cheap = DeepSeek).
 */

function cfg(o = {}) {
  return {
    url: o.url || process.env.AI_GATEWAY_URL || '',
    key: o.key || process.env.AI_GATEWAY_KEY || '',
    model: o.model || process.env.AI_MODEL || 'worker-cheap',
    timeoutMs: o.timeoutMs || 15000,
    temperature: o.temperature != null ? o.temperature : 0.2,
  };
}

function isConfigured(o) { return !!cfg(o).url; }

// PII shapes we refuse to transmit. Deliberately broad.
const PII_PATTERNS = [
  /[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/,   // email
  /\b\d{9}\b/,                                         // Botswana Omang (9 digits)
  /\b\d{10,}\b/,                                       // bank/account/long id
  /\b(?:\d[ -]?){12,}\b/,                              // spaced card/account
  // Botswana mobiles (CFO 2026-07-21): local 8-digit (7X XXX XXX, spaced or
  // not) and +267 / 267-prefixed forms — these are shorter than every rule
  // above and were slipping through.
  /\b7\d(?:[ -]?\d){6}\b/,                             // 71234567 / 71 234 567
  /(?:\+|\b)267[ -]?\d(?:[ -]?\d){6,7}\b/,             // +267 71 234 567
];
function containsLikelyPII(s) {
  s = String(s == null ? '' : s);
  return PII_PATTERNS.some((re) => re.test(s));
}

/** ask(prompt, opts) -> string | null. Never throws. */
async function ask(prompt, o = {}) {
  const c = cfg(o);
  // CFO 2026-08-31: AI analysis is an INTERNAL capability (root-cause on our own
  // exception queue), not a customer/money action, so it is no longer gated on the
  // customer arm (BRAIN_LIVE_ARMS). It runs when the brain is enabled + a gateway
  // is configured; the PII guard below still refuses any prompt that looks like it
  // carries personal data, so nothing personal ever leaves for the model.
  if (process.env.BRAIN_ENABLED !== 'true') return null; // brain disabled → skip
  if (!c.url) return null;                          // unconfigured → graceful skip
  if (containsLikelyPII(prompt)) {
    console.warn('[ai] refused: prompt looks like it contains PII — not sent');
    return null;
  }
  try {
    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), c.timeoutMs);
    const r = await fetch(c.url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...(c.key ? { Authorization: 'Bearer ' + c.key } : {}) },
      body: JSON.stringify({ model: c.model, messages: [{ role: 'user', content: prompt }], temperature: c.temperature }),
      signal: ctrl.signal,
    });
    clearTimeout(timer);
    if (!r.ok) return null;
    const d = await r.json();
    const txt = d && d.choices && d.choices[0] && d.choices[0].message && d.choices[0].message.content;
    return (txt && String(txt).trim()) || null;
  } catch (_) {
    return null;
  }
}

function stripFence(s) {
  s = String(s == null ? '' : s).trim();
  if (s.startsWith('```')) s = s.replace(/^```[a-zA-Z]*\n?/, '').replace(/\n?```$/, '').trim();
  return s;
}

/** askJSON(prompt, opts) -> parsed object | null. */
async function askJSON(prompt, o = {}) {
  const t = await ask(prompt, o);
  if (!t) return null;
  try { return JSON.parse(stripFence(t)); } catch (_) { return null; }
}

module.exports = { ask, askJSON, isConfigured, containsLikelyPII, cfg };
