'use strict';
/**
 * brand.js — ONE Alpha Direct brand, ONE report shell (CFO 2026-08-04:
 * "the reports from Alpha Brain look terrible … branded in our colors, using
 * our Antica font").
 *
 * WHY THIS FILE EXISTS. The brain grew three different brands, so every report
 * looked like it came from a different company:
 *   mailer.js       navy #0D1B2A  orange #F4A623   (the legacy Finance palette)
 *   fridayReport.js navy #010066  orange #FE7F0C   (a third set, from nowhere)
 *   stray literals  #0B1272, #FF6600                (one-off hand-typed hexes)
 * On top of that fridayReport.js and weeklyExtract.js each hand-rolled their own
 * <html> shell instead of sharing one, so headings, spacing and table styling
 * drifted apart report by report.
 *
 * THE CANON (org skill exco-ca4-cfo-copilot §3 / alpha-direct-org brand.md):
 *   Alpha Navy   #1D3270
 *   Direct Orange #F47C20
 * The old #0D1B2A / #F4A623 pair is the documented dark-background / Word
 * fallback set — NOT the primary. Anything else was a mistake and is retired.
 *
 * TYPOGRAPHY. Headings are Book Antiqua (the CFO's "Antica"), with a real
 * fallback chain because no email client is guaranteed to have it:
 * Book Antiqua → Palatino Linotype → Palatino → Georgia → serif. Body copy stays
 * on a system sans stack for legibility at small sizes, and figures use a
 * tabular/mono stack so columns of Pula line up.
 *
 * EMAIL-SAFE BY CONSTRUCTION. Tables not flex/grid, inline styles not <style>,
 * bgcolor ATTRIBUTES alongside CSS background-color (Outlook drops the CSS when
 * a report is pasted into Word), no web fonts, no external images. Pure string
 * building — no I/O, no Date.now(), nothing to stub in a test.
 */

// ── The canonical palette ────────────────────────────────────────────────────
const BRAND = {
  navy: '#1D3270',        // Alpha Navy — headings, table headers, footer
  navyDeep: '#142449',    // deeper navy for the footer band
  navyMid: '#2C468C',     // (unused placeholder kept out of the API; see navyTint)
  navyTint: '#EEF1F8',    // pale navy — zebra striping, quiet callouts
  navyLine: '#D6DDED',    // hairline borders on navy-tinted surfaces
  orange: '#F47C20',      // Direct Orange — accent rules, eyebrow, emphasis
  orangeDeep: '#C9611A',  // pressed/darker orange
  orangeTint: '#FEF1E6',  // pale orange — "verify me" row highlight

  // Severity — used by the auditor's breach rows. Deliberately restrained: the
  // report is read by finance staff every week and must not look like a siren.
  critical: '#B3261E',
  criticalTint: '#FCEEED',
  warn: '#B26A00',
  warnTint: '#FDF4E5',
  ok: '#1B7F4B',
  okTint: '#EAF6EF',

  text: '#1B1B1F',
  textMute: '#5A5F6B',
  textFaint: '#8A8F9A',
  border: '#E2E5EB',
  page: '#F5F6F8',
  white: '#FFFFFF',
};

// Headings — the CFO's "Antica" (Book Antiqua) with a genuine fallback chain.
const FONT_HEAD = `'Book Antiqua','Palatino Linotype',Palatino,Georgia,'Times New Roman',serif`;
// Body — system sans; more legible than a serif at 12–13px in a mail client.
const FONT_BODY = `-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,Helvetica,sans-serif`;
// Figures — tabular so Pula columns align; mono is the only reliable way in email.
const FONT_NUM = `'Consolas','Courier New',Courier,monospace`;

function escHtml(s) {
  if (s == null) return '';
  return String(s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

/** Pula, always 2dp, thousands separated. Never returns NaN. */
function money(n) {
  const v = Number(n);
  if (!isFinite(v)) return 'BWP 0.00';
  const neg = v < 0;
  const s = Math.abs(v).toLocaleString('en-BW', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  return (neg ? '-BWP ' : 'BWP ') + s;
}

function num(n) {
  const v = Number(n);
  return isFinite(v) ? v.toLocaleString('en-BW') : '0';
}

// ── Building blocks ─────────────────────────────────────────────────────────

/**
 * sectionHeading(text) — a navy Book Antiqua heading with an orange underline.
 * The single visual device that separates report sections.
 */
function sectionHeading(text) {
  return `<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:26px 0 10px">
    <tr><td>
      <div style="font-family:${FONT_HEAD};font-size:17px;font-weight:700;color:${BRAND.navy};line-height:1.3">${escHtml(text)}</div>
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:7px">
        <tr><td width="46" height="3" bgcolor="${BRAND.orange}" style="width:46px;height:3px;background-color:${BRAND.orange};line-height:3px;font-size:0">&nbsp;</td></tr>
      </table>
    </td></tr>
  </table>`;
}

/**
 * statRow(stats) — a row of headline figures. stats: [{ label, value, sub?, accent? }]
 * Rendered as a table (never flex) so Outlook lays it out correctly.
 */
function statRow(stats = []) {
  if (!stats.length) return '';
  const w = Math.floor(100 / stats.length);
  const cells = stats.map((s) => `
    <td valign="top" width="${w}%" style="padding:5px">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="${BRAND.white}" style="background-color:${BRAND.white};border:1px solid ${BRAND.border};border-radius:6px">
        <tr><td height="3" bgcolor="${s.accent || BRAND.orange}" style="height:3px;background-color:${s.accent || BRAND.orange};line-height:3px;font-size:0">&nbsp;</td></tr>
        <tr><td style="padding:12px 14px">
          <div style="font-family:${FONT_BODY};font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:${BRAND.textMute}">${escHtml(s.label)}</div>
          <div style="font-family:${FONT_HEAD};font-size:22px;font-weight:700;color:${BRAND.navy};margin:5px 0 3px;line-height:1.1">${escHtml(String(s.value))}</div>
          ${s.sub ? `<div style="font-family:${FONT_BODY};font-size:11px;color:${BRAND.textMute}">${escHtml(s.sub)}</div>` : ''}
        </td></tr>
      </table>
    </td>`).join('');
  return `<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:6px 0 10px"><tr>${cells}</tr></table>`;
}

/**
 * table({ columns, rows, empty }) — the ONE report table.
 *   columns: [{ key, label, align?, kind? }]  kind: 'money' | 'num' | 'text'
 *   rows:    plain objects; a row may carry _highlight: 'critical'|'warn'|'ok'
 *            to tint the whole line, and _note for a small second line.
 */
function table({ columns = [], rows = [], empty = 'Nothing to report.' } = {}) {
  const head = columns.map((c) => {
    const align = c.align || (c.kind === 'money' || c.kind === 'num' ? 'right' : 'left');
    return `<th align="${align}" bgcolor="${BRAND.navy}" style="background-color:${BRAND.navy};color:${BRAND.white};`
      + `padding:9px 11px;font-family:${FONT_BODY};font-size:10px;font-weight:700;letter-spacing:.1em;`
      + `text-transform:uppercase;text-align:${align};white-space:nowrap">${escHtml(c.label)}</th>`;
  }).join('');

  const body = rows.length === 0
    ? `<tr><td colspan="${columns.length || 1}" style="padding:16px;text-align:center;font-family:${FONT_BODY};font-size:12px;color:${BRAND.textFaint}">${escHtml(empty)}</td></tr>`
    : rows.map((r, i) => {
        const tint = r._highlight === 'critical' ? BRAND.criticalTint
          : r._highlight === 'warn' ? BRAND.warnTint
          : r._highlight === 'ok' ? BRAND.okTint
          : (i % 2 ? BRAND.navyTint : BRAND.white);
        const cells = columns.map((c) => {
          const align = c.align || (c.kind === 'money' || c.kind === 'num' ? 'right' : 'left');
          const raw = r[c.key];
          const val = c.kind === 'money' ? money(raw) : c.kind === 'num' ? num(raw) : (raw == null ? '' : String(raw));
          const fam = (c.kind === 'money' || c.kind === 'num') ? FONT_NUM : FONT_BODY;
          const weight = c.emphasis ? '700' : '400';
          const colour = c.kind === 'money' && Number(raw) < 0 ? BRAND.critical : BRAND.text;
          return `<td align="${align}" style="padding:8px 11px;font-family:${fam};font-size:12px;`
            + `text-align:${align};color:${colour};font-weight:${weight};border-bottom:1px solid ${BRAND.border}">${escHtml(val)}</td>`;
        }).join('');
        const note = r._note
          ? `<tr bgcolor="${tint}" style="background-color:${tint}"><td colspan="${columns.length}" style="padding:0 11px 9px;font-family:${FONT_BODY};font-size:11px;line-height:1.5;color:${BRAND.textMute};border-bottom:1px solid ${BRAND.border}">${escHtml(r._note)}</td></tr>`
          : '';
        return `<tr bgcolor="${tint}" style="background-color:${tint}">${cells}</tr>${note}`;
      }).join('');

  return `<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;border:1px solid ${BRAND.border};border-radius:6px;overflow:hidden">
    <thead><tr>${head}</tr></thead>
    <tbody>${body}</tbody>
  </table>`;
}

/** callout(level, html) — a tinted, left-barred box for a ruling or a warning. */
function callout(level, html) {
  const map = {
    critical: { bg: BRAND.criticalTint, bar: BRAND.critical, fg: BRAND.critical },
    warn: { bg: BRAND.warnTint, bar: BRAND.warn, fg: BRAND.warn },
    ok: { bg: BRAND.okTint, bar: BRAND.ok, fg: BRAND.ok },
    info: { bg: BRAND.navyTint, bar: BRAND.navy, fg: BRAND.navy },
  };
  const c = map[level] || map.info;
  return `<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="${c.bg}" style="background-color:${c.bg};border-left:3px solid ${c.bar};margin:14px 0">
    <tr><td style="padding:12px 14px;font-family:${FONT_BODY};font-size:12px;line-height:1.6;color:${c.fg}">${html}</td></tr>
  </table>`;
}

/** paragraph(html) — body copy at the report's standard size/leading. */
function paragraph(html) {
  return `<p style="margin:0 0 12px;font-family:${FONT_BODY};font-size:13px;line-height:1.65;color:${BRAND.text}">${html}</p>`;
}

/**
 * reportShell({ title, eyebrow, generatedAt, bodyHtml, footerNote, width })
 * The ONE outer document every Alpha Brain report renders into. Navy masthead,
 * orange hairline, Book Antiqua title, navy footer band.
 */
function reportShell({ title, eyebrow, generatedAt, bodyHtml, footerNote, width = 860 } = {}) {
  return `<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="light only">
<title>${escHtml(title || 'Alpha Brain Report')}</title>
</head>
<body style="margin:0;padding:0;background-color:${BRAND.page};font-family:${FONT_BODY};color:${BRAND.text};-webkit-font-smoothing:antialiased">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="${BRAND.page}" style="background-color:${BRAND.page}">
    <tr><td align="center" style="padding:26px 12px">
      <table role="presentation" width="${width}" cellpadding="0" cellspacing="0" border="0" style="width:${width}px;max-width:100%;background-color:${BRAND.white};border-radius:8px;overflow:hidden;border:1px solid ${BRAND.border}">

        <!-- Masthead -->
        <tr><td bgcolor="${BRAND.navy}" style="background-color:${BRAND.navy};padding:20px 28px">
          <div style="font-family:${FONT_HEAD};font-size:21px;font-weight:700;color:${BRAND.white};letter-spacing:.01em;line-height:1.2">Alpha Direct Insurance</div>
          <div style="font-family:${FONT_BODY};font-size:10px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:${BRAND.orange};margin-top:4px">${escHtml(eyebrow || 'Alpha Brain')}</div>
        </td></tr>
        <tr><td height="3" bgcolor="${BRAND.orange}" style="height:3px;background-color:${BRAND.orange};line-height:3px;font-size:0">&nbsp;</td></tr>

        <!-- Title -->
        <tr><td style="padding:24px 28px 4px">
          <h1 style="margin:0;font-family:${FONT_HEAD};font-size:25px;line-height:1.25;font-weight:700;color:${BRAND.navy}">${escHtml(title || '')}</h1>
          ${generatedAt ? `<div style="margin-top:7px;font-family:${FONT_BODY};font-size:11px;color:${BRAND.textMute}">Generated ${escHtml(generatedAt)} · Africa/Gaborone</div>` : ''}
        </td></tr>

        <!-- Body -->
        <tr><td style="padding:14px 28px 8px">${bodyHtml || ''}</td></tr>

        ${footerNote ? `<tr><td style="padding:10px 28px 22px;font-family:${FONT_BODY};font-size:11px;line-height:1.6;color:${BRAND.textFaint}">${escHtml(footerNote)}</td></tr>` : ''}

        <!-- Footer band -->
        <tr><td bgcolor="${BRAND.navyDeep}" style="background-color:${BRAND.navyDeep};padding:15px 28px">
          <div style="font-family:${FONT_HEAD};font-size:13px;font-weight:700;color:${BRAND.white}">Alpha Direct Insurance Company (Pty) Ltd</div>
          <div style="font-family:${FONT_BODY};font-size:10px;color:rgba(255,255,255,.6);margin-top:3px">Alpha Brain · automated report · Gaborone, Botswana</div>
        </td></tr>

      </table>
    </td></tr>
  </table>
</body>
</html>`;
}

module.exports = {
  BRAND, FONT_HEAD, FONT_BODY, FONT_NUM,
  escHtml, money, num,
  reportShell, sectionHeading, statRow, table, callout, paragraph,
};
