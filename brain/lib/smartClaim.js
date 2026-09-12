'use strict';
/**
 * smartClaim.js — the AI-assisted smarts, all through the shared PII-guarded
 * client (lib/ai.js). Every function is deterministic-first: the rule/thresholds
 * give the answer, AI only adds a fallback or a second opinion, and if AI is
 * unavailable the function still returns a safe result. No PII is ever sent —
 * inputs here are claim-type LABELS and MASKED NUMBERS only, never narratives or
 * customer identity.
 */

const aiDefault = require('./ai');
const { FORMS, resolveClaimForm } = require('./claimForms');

const CANON = Object.keys(FORMS); // canonical claim-type keys the picker knows

/**
 * classifyClaimType(label, {ai}) — map a claim-type LABEL to a known type.
 * Deterministic picker first; only if it can't, ask the model to choose from the
 * known list (label only, not a narrative). The model can only ever return a
 * value we already validate against CANON — it cannot invent a type.
 * -> { type, source:'rule'|'ai'|'none', forms? }
 */
async function classifyClaimType(label, opts = {}) {
  const ai = opts.ai || aiDefault;
  const det = resolveClaimForm(label);
  if (det.status === 'ok') return { type: det.type, source: 'rule', forms: det.forms };

  const prompt =
    'Pick the ONE best-matching insurance claim type for the label below, from this exact list. ' +
    'Reply with ONLY the exact list value, or NONE.\n' +
    'List: ' + CANON.join(' | ') + '\n' +
    'Label: ' + String(label || '').slice(0, 80);
  const ans = await ai.ask(prompt, opts);
  if (!ans) return { type: null, source: 'none' };
  // exact match ONLY (CFO 2026-07-21): a fragment reply like "all" or "motor"
  // must never substring-match into the wrong claim type / official form
  const pick = ans.trim().toLowerCase();
  const match = CANON.find((c) => pick === c);
  if (match && FORMS[match]) return { type: match, source: 'ai', forms: FORMS[match].forms };
  return { type: null, source: 'none' };
}

/**
 * assessmentRoute({repairCost, sumInsured, writeOffThreshold}, {ai}) — decide
 * repair vs write-off vs refer from MASKED NUMBERS. Deterministic thresholds are
 * the truth; AI adds a second opinion in `.ai` (never overrides).
 * -> { route:'repair'|'total_loss'|'refer', ratio, reason, ai }
 */
async function assessmentRoute(input = {}, opts = {}) {
  const ai = opts.ai || aiDefault;
  const rc = Number(input.repairCost) || 0;
  const si = Number(input.sumInsured) || 0;
  const thr = input.writeOffThreshold != null ? Number(input.writeOffThreshold) : 0.7;
  const ratio = si > 0 ? rc / si : null;

  let route;
  if (ratio == null) route = 'refer';
  else if (ratio >= thr) route = 'total_loss';
  else if (ratio <= 0.5) route = 'repair';
  else route = 'refer';

  const reason =
    ratio == null
      ? 'no sum insured on record — refer to a human'
      : `repair ${rc} vs sum insured ${si} = ${(ratio * 100).toFixed(0)}% (write-off at ${(thr * 100).toFixed(0)}%)`;

  const second = await ai.askJSON(
    'Insurance motor claim routing from numbers only. ' +
    `repairCost=${rc} sumInsured=${si} writeOffThresholdPercent=${thr * 100}. ` +
    'Reply JSON: {"route":"repair|total_loss|refer","reason":"short"}.',
    opts
  );

  return { route, ratio, reason, ai: second || null };
}

module.exports = { classifyClaimType, assessmentRoute, CANON };
