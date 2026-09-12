'use strict';
/**
 * claimForms.js — claim type → official Alpha Direct claim form(s) + required
 * supporting documents.
 *
 * Seed authority: claimsdoc "Claims in Graphite - Response.pdf" (CFO 2026-07-08).
 * This is a PURE data + resolver module — no DB, no filesystem, no network — so
 * it can be unit-tested in isolation and reused by the claim-registered email
 * step (attach the right blank form) and later by the document-checklist stage.
 *
 * NEVER GUESSES. A claim type with no official form on record resolves to
 * { status: 'needs_human' } so the handler is prompted rather than a wrong or
 * blank form being emailed to a customer.
 *
 * Form file basenames are the real files under
 *   claimsdoc/claim-forms/Claim forms/
 * The caller resolves that directory (per environment) and attaches the file.
 */

// Canonical claim types (from the Response table) → official form file(s) + the
// required supporting documents the claimant must return with the form.
const FORMS = {
  'glass': {
    forms: ['GLASS CLAIM FORM.pdf'],
    docs: ['Glass Claim Form', 'Registration Book', 'Two Repair Quotations',
           'Police Affidavit (where damage resulted from attempted theft/break-in)'],
  },
  'motor accident': {
    forms: ['MOTOR ACCIDENT CLAIM FORM.pdf'],
    docs: ['Motor Claim Form', 'Police Report', 'Recently Certified Driver’s Licence',
           'Registration Book', 'Two Repair Quotations (≥1 from approved panel)'],
  },
  'property damage': {
    forms: ['PROPERTY LOSS CLAIM FORM.pdf'],
    docs: ['Property Claim Form', 'Two Repair Quotations', 'Purchase Invoices',
           'Incident Report', 'Maintenance/Inspection/Security Records',
           'Contracts and Warranties (where applicable)'],
    note: 'Uses the Property Loss form (no separate "Property" form on record) — confirm.',
  },
  'all risk': {
    forms: ['ALL RISK CLAIM FORM.pdf'],
    docs: ['All Risk Claim Form', 'Technical Report', 'Two Replacement/Repair Quotations',
           'Police Report/Affidavit (if stolen)'],
  },
  'mobile and electronic device': {
    forms: ['MOBILE AND ELECTRONIC DEVICE CLAIM FORM.pdf'],
    docs: [],
  },
  'workmen’s compensation': {
    forms: ['WORKMEN_COMPENSATION_FORM.pdf'],
    docs: ['Workmen’s Compensation Claim Form', 'Labour Documents', 'Claimant Statement',
           'At least Two Witness Statements', 'Payslips (12 months prior to incident)',
           'Medical Report', 'Proof of Employment', 'Copy of ID'],
  },
  'public liability': {
    forms: ['LIABILITY CLAIM FORM.pdf'],
    docs: ['Liability Claim Form', 'Statements', 'Medical Report/Records (where injury involved)',
           'Legal Correspondence (where legal action is being taken)'],
  },
  'fire': {
    forms: ['PROPERTY LOSS CLAIM FORM.pdf'],
    docs: ['Property Loss Claim Form', 'Repair Estimates', 'Purchase Invoices',
           'Police Report', 'Fire Report', 'Inventory of Damaged Property'],
  },
  'theft/burglary': {
    forms: ['BURGLARY CLAIM FORM.pdf'],
    docs: ['Burglary Claim Form', 'Police Report', 'Proof of Ownership',
           'Replacement Quotations', 'Security Alarm Report'],
  },
  'goods in transit': {
    forms: ['GIT CLAIM FORM.pdf'],
    docs: ['GIT Claim Form', 'Police Report', 'Consignment Invoices',
           'Driver’s Licence', 'Registration Books for Trucks and Trailers'],
  },
  'money': {
    // Response table: Money -> Burglary Claim Form / Fidelity Claim Form (both).
    forms: ['BURGLARY CLAIM FORM.pdf', 'FEDILITY CLAIM FORM.pdf'],
    docs: ['Property Claim Form', 'Police Affidavit', 'Incident Report', 'Reconciliation Invoices'],
  },
  'locks & keys': { forms: ['LOCKS AND KEYS CLAIM FORM.pdf'], docs: [] },
  'legal': { forms: ['Legal Claim Form.pdf'], docs: [] },
  'fidelity guarantee': { forms: ['FEDILITY CLAIM FORM.pdf'], docs: [] },
  'business interruption': { forms: ['BUSINESS INTERRUPTION FORM.pdf'], docs: [] },
  'professional indemnity': { forms: ['Professional_Indemnity_Claim_Form.pdf'], docs: [] },

  // Product lines with a form on file but not in the Response table (extend as used):
  'directors & officers liability': { forms: ['AD_Directors_Officers_Liability_Claim_Form_v1.0.pdf'], docs: [] },
  'contractors all risks': { forms: ['Contractors_All_Risks_Claim_Form.pdf'], docs: [] },
  'erection all risk': { forms: ['Erection_All_Risk_Insurance_Claim_Form.pdf'], docs: [] },
  'plant all risks': { forms: ['Plant_All_Risks_Claim_Form.pdf'], docs: [] },
  'machinery breakdown': { forms: ['Machinery Breakdown.pdf'], docs: [] },
  'marine cargo': { forms: ['AD_Marine_Cargo_Open_Claim_Form_v1.0.pdf'], docs: [] },
  'medical malpractice': { forms: ['Medical Malpractice Claim Form.pdf'], docs: [] },
  'defective workmanship': { forms: ['DEFECTIVE WORKMANSHIP CLAIM FORM.pdf'], docs: [] },
  'travel': { forms: ['TRAVEL INSURANCE CLAIM.pdf'], docs: [] },
};

// Response table lists these types but NO official form file exists yet.
// They must route to a human, not send a wrong form.
const MISSING_FORM = {
  'bonu': 'Bonu',
  'accidental death insurance': 'Accidental Death Insurance',
  'hospital cash back': 'Hospital Cash Back',
};

// Aliases: label the tracker/Graphite may use -> canonical key above.
// Reconcile with the tracker master_data "graphiteClaimTypeMap" labels over time.
const ALIASES = {
  'motor': 'motor accident',
  'motor claim': 'motor accident',
  'windscreen': 'glass',
  'glass motor': 'glass',
  'glass non-motor': 'glass',
  'liability': 'public liability',
  'wca': 'workmen’s compensation',
  'workmens compensation': 'workmen’s compensation',
  'workmen compensation': 'workmen’s compensation',
  'git': 'goods in transit',
  'theft': 'theft/burglary',
  'burglary': 'theft/burglary',
  'all risks': 'all risk',
  'cellphone': 'mobile and electronic device',
  'mobile': 'mobile and electronic device',
  'electronic device': 'mobile and electronic device',
  'fidelity': 'fidelity guarantee',
  'indemnity': 'professional indemnity',
  // CFO 2026-07-21: the short 'car'/'ear' aliases are REMOVED — "car" is how a
  // customer says motor and "ear" hides inside "wear and tear"; both mis-mapped
  // to Contractors/Erection All Risks forms. Those products must be named in
  // full (they still match on 'contractors all risks' / 'erection all risk').
  'd&o': 'directors & officers liability',
};

// Normalize a free-text claim-type label: lowercase, strip the noise words
// ("claim", "form", "insurance", "policy"), collapse whitespace/parentheses.
function normalize(label) {
  return String(label || '')
    .toLowerCase()
    .replace(/’/g, '’')            // keep curly apostrophe consistent
    .replace(/[()]/g, ' ')
    .replace(/\b(claim|form|insurance|policy|cover)\b/g, ' ')
    .replace(/[.,]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

/**
 * resolveClaimForm(claimTypeLabel[, subType])
 * -> { status: 'ok', type, forms: [file...], docs: [...], note? }
 * -> { status: 'needs_human', type|null, reason }
 * Never throws; unknown/blank -> needs_human.
 */
function resolveClaimForm(claimTypeLabel, subType) {
  const raw = [claimTypeLabel, subType].filter(Boolean).join(' ');
  const norm = normalize(raw);
  if (!norm) return { status: 'needs_human', type: null, reason: 'no claim type supplied' };

  // Direct canonical hit.
  if (FORMS[norm]) return { status: 'ok', type: norm, ...FORMS[norm] };

  // Alias hit.
  if (ALIASES[norm] && FORMS[ALIASES[norm]]) {
    const key = ALIASES[norm];
    return { status: 'ok', type: key, ...FORMS[key] };
  }

  // WHOLE-WORD matching only (CFO 2026-07-21): "carport" must not hit "car",
  // "wear and tear" must not hit "ear" — a fragment inside a word can never
  // select an official form.
  const wordHit = (haystack, k) =>
    new RegExp('\\b' + k.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b').test(haystack);

  // Known-but-no-form-on-record type.
  for (const k of Object.keys(MISSING_FORM)) {
    if (norm === k || wordHit(norm, k)) {
      return { status: 'needs_human', type: MISSING_FORM[k],
               reason: 'no official claim form on record for this type' };
    }
  }

  // Loose whole-word match against canonical keys / aliases (last resort, still
  // deterministic — longest key first so "all risk" wins over "risk").
  const keys = [...Object.keys(FORMS), ...Object.keys(ALIASES)].sort((a, b) => b.length - a.length);
  for (const k of keys) {
    if (wordHit(norm, k)) {
      const canon = FORMS[k] ? k : ALIASES[k];
      if (FORMS[canon]) return { status: 'ok', type: canon, ...FORMS[canon] };
    }
  }

  return { status: 'needs_human', type: null,
           reason: `unrecognised claim type "${claimTypeLabel}" — no form mapped` };
}

module.exports = { resolveClaimForm, FORMS, MISSING_FORM, ALIASES, normalize };
