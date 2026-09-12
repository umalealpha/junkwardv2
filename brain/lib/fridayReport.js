'use strict';
/**
 * fridayReport.js — the Friday 6 p.m. consolidated non-payment report
 * (CFO 2026-07-18).
 *
 * ONE consolidated email to Underwriting, Finance, Claims and the CFO with two
 * sections:
 *   Section 1 — DEACTIVATED: everyone currently suspended (stage
 *     deactivate_candidate OR grace) so the teams can chase payment — banks
 *     sometimes silently change a client's account.
 *   Section 2 — CANCELLED: everyone cancelled for non-payment during the grace
 *     period (stage cancel_candidate).
 *
 * Columns (CFO, exact): policy number, client, product, agent, amount overdue,
 * days overdue, status.
 *
 * PURE + deterministic. `now` is passed IN — nothing here calls Date.now() /
 * new Date() with no argument, so tests are reproducible. This module RENDERS
 * data/HTML/CSV; it never sends. Recipients come from SETTINGS (env), not
 * hardcoded addresses (see reportRecipients()).
 *
 * DPA: client/agent are internal-only; nothing is sent to any external model.
 */

// The CFO's exact column set, in order.
const COLUMNS = [
  { key: 'policyNumber', label: 'Policy Number' },
  { key: 'client', label: 'Client' },
  { key: 'product', label: 'Product' },
  { key: 'agent', label: 'Agent' },
  { key: 'amountOverdue', label: 'Amount Overdue' },
  { key: 'daysOverdue', label: 'Days Overdue' },
  { key: 'status', label: 'Status' },
];

// Which stages land in which section, and the status label shown.
const DEACTIVATED_STAGES = ['deactivate_candidate', 'grace'];
const CANCELLED_STAGES = ['cancel_candidate'];
const STAGE_STATUS = {
  deactivate_candidate: 'DEACTIVATED',
  grace: 'DEACTIVATED (in grace period)',
  cancel_candidate: 'CANCELLED',
};

// Settings keys (env). No real addresses are hardcoded — Operations sets these,
// mirroring how the high-arrears recipients were moved to settings.
const RECIPIENT_ENV = {
  underwriting: 'BRAIN_FRIDAY_UNDERWRITING_RECIPIENTS',
  finance: 'BRAIN_FRIDAY_FINANCE_RECIPIENTS',
  claims: 'BRAIN_FRIDAY_CLAIMS_RECIPIENTS',
  cfo: 'BRAIN_FRIDAY_CFO_RECIPIENTS',
};

// ── pure helpers ─────────────────────────────────────────────────
function escHtml(s) {
  if (s == null) return '';
  return String(s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function money(n) {
  const v = Number(n);
  if (!isFinite(v)) return 'BWP 0.00';
  return 'BWP ' + v.toLocaleString('en-BW', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// now may be a Date, epoch ms, or ISO string. Returns a Date (or null).
function asDate(now) {
  if (now instanceof Date) return isNaN(now.getTime()) ? null : now;
  if (typeof now === 'number') return new Date(now);
  if (typeof now === 'string') { const d = new Date(now); return isNaN(d.getTime()) ? null : d; }
  return null;
}

// Deterministic "YYYY-MM-DD HH:mm" in UTC (no locale drift in tests).
function stampUtc(d) {
  if (!d) return '';
  const p = (n) => String(n).padStart(2, '0');
  return `${d.getUTCFullYear()}-${p(d.getUTCMonth() + 1)}-${p(d.getUTCDate())} `
       + `${p(d.getUTCHours())}:${p(d.getUTCMinutes())}`;
}

// One affected policy → the exact CFO report row (+ signalConfidence flag so
// GRA-0203-risky rows can be marked in the UI without changing the columns).
function toRow(policy = {}) {
  return {
    policyNumber: policy.policyNumber || '',
    client: policy.customerName || '',
    product: policy.product || '',
    agent: policy.agent || '',
    amountOverdue: Number(policy.amountOverdue) || 0,
    daysOverdue: Number(policy.daysOverdue) || 0,
    status: STAGE_STATUS[policy.stage] || 'UNKNOWN',
    signalConfidence: policy.signalConfidence === 'uncertain' ? 'uncertain' : 'clean',
  };
}

function sumOverdue(rows) {
  return rows.reduce((t, r) => t + (Number(r.amountOverdue) || 0), 0);
}

/**
 * buildFridayReport(affected, { now }) → the consolidated report data.
 * `now` is REQUIRED for a deterministic timestamp (pass a fixed Date in tests).
 */
function buildFridayReport(affected, { now } = {}) {
  const list = Array.isArray(affected) ? affected : [];
  const deactivated = list.filter((p) => DEACTIVATED_STAGES.includes(p && p.stage)).map(toRow);
  const cancelled = list.filter((p) => CANCELLED_STAGES.includes(p && p.stage)).map(toRow);
  const generatedAt = stampUtc(asDate(now));

  return {
    title: 'Non-Payment Collections — Friday Report',
    generatedAt,
    columns: COLUMNS,
    sections: {
      deactivated: {
        title: 'Section 1 — Deactivated (currently suspended · chase payment)',
        columns: COLUMNS,
        rows: deactivated,
      },
      cancelled: {
        title: 'Section 2 — Cancelled (non-payment during grace period)',
        columns: COLUMNS,
        rows: cancelled,
      },
    },
    counts: { deactivated: deactivated.length, cancelled: cancelled.length },
    totals: { deactivatedOverdue: sumOverdue(deactivated), cancelledOverdue: sumOverdue(cancelled) },
  };
}

/**
 * reportRecipients(env) → the consolidated recipient list from SETTINGS.
 * env defaults to process.env. Returns { underwriting, finance, claims, cfo, all }
 * (all = de-duplicated union). Empty until Operations sets the env keys.
 */
function reportRecipients(env = process.env) {
  const split = (v) => String(v || '').split(',').map((s) => s.trim()).filter(Boolean);
  const out = {};
  for (const [group, key] of Object.entries(RECIPIENT_ENV)) out[group] = split(env[key]);
  out.all = [...new Set([].concat(out.underwriting, out.finance, out.claims, out.cfo))];
  return out;
}

// ── CSV ──────────────────────────────────────────────────────────
function csvCell(v) {
  const s = v == null ? '' : String(v);
  return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
}

function csvSection(title, rows) {
  const lines = [csvCell(title), COLUMNS.map((c) => csvCell(c.label)).join(',')];
  if (rows.length === 0) {
    lines.push(csvCell('(none)'));
  } else {
    for (const r of rows) lines.push(COLUMNS.map((c) => csvCell(r[c.key])).join(','));
  }
  return lines;
}

/**
 * renderFridayReportCsv(report) → CSV string with both sections.
 */
function renderFridayReportCsv(report) {
  const d = report.sections.deactivated.rows;
  const c = report.sections.cancelled.rows;
  const lines = []
    .concat(csvCell(`Non-Payment Collections — Friday Report,${report.generatedAt}`))
    .concat('')
    .concat(csvSection('DEACTIVATED', d))
    .concat('')
    .concat(csvSection('CANCELLED', c));
  return lines.join('\r\n');
}

// ── HTML ─────────────────────────────────────────────────────────
// Brand comes from lib/brand.js — the ONE palette and the ONE report shell
// (CFO 2026-08-04: "the reports from Alpha Brain look terrible … branded in our
// colors, using our Antica font").
//
// This file used to hardcode navy #010066 / orange #FE7F0C, which matched NOTHING
// — mailer.js was on #0D1B2A/#F4A623 and the company standard is Alpha Navy
// #1D3270 / Direct Orange #F47C20. Three palettes across one product is why the
// reports read as if they came from three different companies. It also rolled its
// own <html> shell, so spacing and headings drifted from every other report.
// Both are now shared. Colour cells keep a bgcolor ATTRIBUTE alongside the CSS
// (Outlook drops the CSS when a report is pasted into Word).
const brand = require('./brand');
const NAVY = brand.BRAND.navy;
const ORANGE = brand.BRAND.orange;
const FONT_HEAD = brand.FONT_HEAD;

function htmlTable(section) {
  const head = COLUMNS.map((c, i) =>
    `<th align="${i >= 4 && i <= 5 ? 'right' : 'left'}" bgcolor="${NAVY}" `
    + `style="background-color:${NAVY};color:#FFFFFF;padding:9px 11px;font-size:11px;`
    + `text-transform:uppercase;letter-spacing:.08em;text-align:${i >= 4 && i <= 5 ? 'right' : 'left'}">`
    + `${escHtml(c.label)}</th>`).join('');

  const body = section.rows.length === 0
    ? `<tr><td colspan="${COLUMNS.length}" style="padding:14px;text-align:center;color:#999999;font-size:13px">No policies in this section.</td></tr>`
    : section.rows.map((r, i) => {
        const uncertain = r.signalConfidence === 'uncertain';
        const bg = uncertain ? brand.BRAND.orangeTint : (i % 2 ? brand.BRAND.navyTint : brand.BRAND.white);
        const flag = uncertain
          ? ` <span style="background-color:${ORANGE};color:#FFFFFF;font-size:9px;font-weight:700;padding:1px 5px;border-radius:3px">VERIFY</span>`
          : '';
        return `<tr bgcolor="${bg}" style="background-color:${bg}">`
          + `<td style="padding:8px 11px;font-family:'Courier New',monospace;font-size:12px;color:${NAVY};font-weight:700">${escHtml(r.policyNumber)}${flag}</td>`
          + `<td style="padding:8px 11px;font-size:13px">${escHtml(r.client)}</td>`
          + `<td style="padding:8px 11px;font-size:13px;color:#666666">${escHtml(r.product)}</td>`
          + `<td style="padding:8px 11px;font-size:13px">${escHtml(r.agent)}</td>`
          + `<td align="right" style="padding:8px 11px;font-size:13px;text-align:right;font-family:'Courier New',monospace">${escHtml(money(r.amountOverdue))}</td>`
          + `<td align="right" style="padding:8px 11px;font-size:13px;text-align:right">${escHtml(String(r.daysOverdue))}</td>`
          + `<td style="padding:8px 11px;font-size:12px;font-weight:600">${escHtml(r.status)}</td>`
          + `</tr>`;
      }).join('');

  return `
    <div style="font-family:${FONT_HEAD};font-size:16px;font-weight:700;color:${NAVY};margin:22px 0 8px">${escHtml(section.title)}</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #E5E7EB;border-radius:6px;overflow:hidden">
      <thead><tr>${head}</tr></thead>
      <tbody>${body}</tbody>
    </table>`;
}

/**
 * renderFridayReportHtml(report) → branded consolidated HTML (both sections).
 */
function renderFridayReportHtml(report) {
  const d = report.sections.deactivated;
  const c = report.sections.cancelled;
  return brand.reportShell({
    title: 'Non-Payment Collections — Friday Report',
    eyebrow: 'Alpha Brain · Collections',
    generatedAt: report.generatedAt,
    width: 900, // seven columns need more room than the default shell
    bodyHtml: brand.paragraph(
      'Consolidated view for Underwriting, Finance, Claims and the CFO. '
      + 'Teams should chase payment on <strong>deactivated</strong> policies '
      + '(banks sometimes silently change a client\'s account).'
    )
      + brand.statRow([
        { label: 'Deactivated', value: String(d.rows.length), sub: money(report.totals.deactivatedOverdue), accent: brand.BRAND.warn },
        { label: 'Cancelled', value: String(c.rows.length), sub: money(report.totals.cancelledOverdue), accent: brand.BRAND.critical },
      ])
      + htmlTable(d)
      + htmlTable(c),
    footerNote: 'Automated collections report. Suspension and cancellation are always human-approved — '
      + 'this report lists them, it does not execute them.',
  });
}

module.exports = {
  buildFridayReport,
  renderFridayReportHtml,
  renderFridayReportCsv,
  reportRecipients,
  toRow,
  COLUMNS,
  RECIPIENT_ENV,
  STAGE_STATUS,
  DEACTIVATED_STAGES,
  CANCELLED_STAGES,
};
