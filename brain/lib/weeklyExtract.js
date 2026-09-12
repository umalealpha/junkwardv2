'use strict';
/**
 * weeklyExtract.js — the data-extract layer. Two jobs, one definition of truth:
 *
 *   1. SELF-SERVICE  — GET /api/extract?dataset=…&format=xlsx|csv, so anyone
 *      authorised can pull their own list instead of asking for a report.
 *   2. WEEKLY EMAIL  — every Monday at midnight (Africa/Gaborone) each team gets
 *      its own Excel workbook in its inbox.
 *
 * CFO 2026-07-28: "we need a place where people can extract data" and "all these
 * exceptions should be emailed to the kyc team and debtors team, data analytics
 * team, finance team, accounts team weekly in excel at midnight".
 *
 * Design rules:
 *  - ONE dataset definition per team, used by BOTH paths. The Monday workbook and
 *    the self-service download can never disagree.
 *  - Every workbook opens with a "Read me" sheet carrying the plain-English
 *    definition of each column and each filter, straight out of lib/glossary.js.
 *    An extract with no definitions is how "the numbers don't add up" starts.
 *  - Row caps are STATED, never silent: the Read me sheet says how many rows were
 *    written and how many existed.
 *  - Recipients live in settings/env only. No address is hardcoded, so this ships
 *    safe: no addresses configured = workbooks built and downloadable, nothing
 *    emailed.
 *  - DPA: internal recipients only, enforced at send time. Rows carry policy
 *    numbers, product, amounts and stage — never an Omang, passport, bank number
 *    or scanned document. Those never leave the masked view.
 */

const xlsx = require('./xlsx');
const glossary = require('./glossary');

// Hard cap per sheet. The full queue can be 100k+ rows; an Excel file that size
// is unusable and the build would stall the event loop. Stated in the Read me.
const MAX_ROWS = Number(process.env.BRAIN_EXTRACT_MAX_ROWS) || 50_000;

// The columns of a queue exception, in the order a human reads them.
const QUEUE_COLUMNS = [
  { key: 'priority', label: 'Priority', width: 10 },
  { key: 'team', label: 'Team', width: 14 },
  { key: 'domain', label: 'Type', width: 16 },
  { key: 'ref', label: 'Reference', width: 22 },
  { key: 'title', label: 'What is wrong', width: 52 },
  { key: 'detail', label: 'Detail', width: 78 },
  { key: 'needsApproval', label: 'Needs approval', width: 16 },
  { key: 'status', label: 'Status', width: 12 },
];

// The columns of the affected-policies (non-payment clock) list.
const AFFECTED_COLUMNS = [
  { key: 'policyNumber', label: 'Policy Number', width: 20 },
  { key: 'customerName', label: 'Client', width: 30 },
  { key: 'product', label: 'Product', width: 22 },
  { key: 'agent', label: 'Agent', width: 24 },
  { key: 'channel', label: 'Channel', width: 14 },
  { key: 'billingType', label: 'Billing', width: 14 },
  { key: 'amountOverdue', label: 'Amount Overdue', width: 18 },
  { key: 'monthsUnpaid', label: 'Months Unpaid', width: 15 },
  { key: 'daysOverdue', label: 'Days Overdue', width: 14 },
  { key: 'stage', label: 'Stage', width: 22 },
  { key: 'graceEndsAt', label: 'Grace Ends', width: 14 },
  { key: 'signalConfidence', label: 'Confidence', width: 14 },
  { key: 'reason', label: 'Reason', width: 40 },
];

/**
 * DATASETS — one per audience. `pick` filters the flattened queue.
 * `metrics` names the glossary entries whose definitions go on the Read me sheet.
 */
const DATASETS = {
  kyc: {
    label: 'KYC team',
    title: 'KYC & compliance exceptions',
    plain: 'Every active customer whose identity or address paperwork is not clean, plus the healthcare paperwork gap.',
    recipientEnv: 'BRAIN_EXTRACT_KYC_RECIPIENTS',
    metrics: ['compliance', 'kyc', 'kyc_gap_active_book', 'healthcare_non_compliance', 'priority', 'sweep'],
    pick: (it) => it.team === 'Compliance',
    columns: QUEUE_COLUMNS,
  },
  debtors: {
    label: 'Debtors team',
    title: 'Debtors & non-payment exceptions',
    plain: 'Overdue accounts and every policy on the two-missed-months clock, with the stage it has reached.',
    recipientEnv: 'BRAIN_EXTRACT_DEBTORS_RECIPIENTS',
    metrics: ['debtors', 'collections', 'priority', 'sweep'],
    pick: (it) => it.team === 'Finance' && ['debtors', 'collections'].includes(it.domain),
    columns: QUEUE_COLUMNS,
    withAffected: true,
  },
  finance: {
    label: 'Finance team',
    title: 'Finance exceptions',
    plain: 'Everything on the Finance queue: overdue accounts, the non-payment clock, and claims approved while badly in arrears.',
    recipientEnv: 'BRAIN_EXTRACT_FINANCE_RECIPIENTS',
    metrics: ['finance', 'debtors', 'collections', 'high_arrears', 'priority', 'sweep'],
    pick: (it) => it.team === 'Finance',
    columns: QUEUE_COLUMNS,
    withAffected: true,
  },
  accounts: {
    label: 'Accounts team',
    title: 'Ledger exceptions',
    plain: 'Accounts with money past 60 days or receipts we hold but never matched to an invoice, plus high-arrears claim approvals.',
    recipientEnv: 'BRAIN_EXTRACT_ACCOUNTS_RECIPIENTS',
    metrics: ['debtors', 'high_arrears', 'priority', 'sweep'],
    pick: (it) => it.team === 'Finance' && ['debtors', 'high_arrears'].includes(it.domain),
    columns: QUEUE_COLUMNS,
  },
  analytics: {
    label: 'Data Analytics team',
    title: 'All exceptions — full extract',
    plain: 'Every exception the Brain raised this week, all teams, for analysis.',
    recipientEnv: 'BRAIN_EXTRACT_ANALYTICS_RECIPIENTS',
    metrics: ['total_exceptions', 'finance', 'compliance', 'debtors', 'collections', 'kyc',
      'kyc_gap_active_book', 'healthcare_non_compliance', 'high_arrears', 'priority', 'sweep',
      // The Data-Analytics workbook TABS (analytics dataset only) — each explains
      // itself on the Read me before anyone queries a figure.
      'wb_weekly_update', 'wb_major_claims', 'wb_premium_analysis', 'wb_claims_analysis',
      'wb_renewals', 'wb_kyc_completeness'],
    pick: () => true,
    columns: QUEUE_COLUMNS,
    withAffected: true,
  },
};

/** flatten(queue) → every exception across every team, highest priority first. */
function flatten(queue) {
  const teams = (queue && queue.teams) || {};
  return Object.values(teams).flat()
    .filter(Boolean)
    .sort((a, b) => (b.priority || 0) - (a.priority || 0));
}

/** A queue item reduced to the display columns only — never the nested objects. */
function toRow(it) {
  return {
    priority: Number(it.priority) || 0,
    team: it.team || '',
    domain: it.domain || '',
    ref: it.ref || '',
    title: it.title || '',
    detail: it.detail || '',
    needsApproval: it.needsApproval ? 'YES' : 'no',
    status: it.status || 'open',
  };
}

/** The Read me sheet: what this file is, what got cut, and every definition. */
function readMeSheet(ds, meta) {
  const rows = [
    { a: 'What this is', b: ds.plain },
    { a: 'Built for', b: ds.label },
    { a: 'Data as at', b: meta.generatedAt || 'unknown — the sweep has not run' },
    { a: 'Source', b: meta.source || 'unknown' },
    { a: 'Rows in this file', b: String(meta.written) },
    { a: 'Rows that existed', b: String(meta.available) },
    { a: 'Rows left out', b: meta.available > meta.written
      ? `${meta.available - meta.written} — this extract is capped at ${MAX_ROWS} rows per sheet. Ask for a split extract if you need the rest.`
      : 'none' },
    { a: 'Live arms', b: meta.liveArms ? 'ON' : 'OFF — the Brain decided and listed these; it has sent, moved and cancelled nothing' },
    { a: '', b: '' },
    { a: 'WHAT EACH NUMBER MEANS', b: '' },
  ];
  for (const id of ds.metrics) {
    const m = glossary.metric(id);
    if (!m) continue;
    rows.push({ a: '', b: '' });
    rows.push({ a: m.label, b: m.means });
    rows.push({ a: '  How it is counted', b: m.howCounted });
    rows.push({ a: '  What is left out', b: m.excludes });
    if (m.caveat) rows.push({ a: '  Read this', b: m.caveat });
    rows.push({ a: '  Who acts', b: m.whoActs || '' });
  }
  rows.push({ a: '', b: '' });
  rows.push({ a: 'DATA PROTECTION', b: 'This file carries policy numbers, products, amounts and stages. It does NOT carry Omang or passport numbers, bank details or scanned documents. Internal use only — do not forward outside Alpha Direct.' });
  return {
    name: 'Read me',
    columns: [{ key: 'a', label: 'Item', width: 30 }, { key: 'b', label: 'Explanation', width: 110 }],
    rows,
  };
}

// ── DATA-ANALYTICS WORKBOOK TABS (CFO's locked rules) ────────────────────────
// These EXTEND the analytics workbook only. Rules baked in (CFO 2026-08):
//   - Reserve = full estimated cost. Loss Ratio on Reserve = reserve ÷ premium;
//     Loss Ratio on Payment = paid ÷ premium (NEVER "Incurred").
//   - Premium values are ALREADY ex-VAT — never re-derive VAT.
//   - No budgets, no variance. BONU excluded from every Total.
//   - FY = Jul–Jun. Every Total / Check / ratio is a LIVE formula, never a
//     pasted value. No PII — counts, refs and money totals only.
// This zero-dependency writer has no percent number-format (money #,##0.00 and
// date only), so every ratio is shown as a 2-dp number with "%" in the header,
// computed live (e.g. reserve ÷ premium × 100). Loss-ratio flag thresholds
// compare the raw fraction (0.7 = 70%).
const GROUP_LABELS = { COMG: 'Commercial', DOMG: 'Domestic', MIS: 'MIS (Health)' };
const LOSS_RATIO_LINES = ['Instant Insurance', 'Motor Comprehensive', 'Corporate & Personal Lines', 'Bonu Legal Insurance'];

/** FY label from a date (Jul–Jun). Aug 2026 → { fy:2027, yy:'27' }. */
function fyLabel(dateish) {
  const d = dateish ? new Date(dateish) : new Date();
  const t = Number.isNaN(d.getTime()) ? new Date() : d;
  const endYear = t.getUTCMonth() >= 6 ? t.getUTCFullYear() + 1 : t.getUTCFullYear();
  return { fy: endYear, yy: String(endYear).slice(-2) };
}

// A finite number passes through; anything else (null/''/NaN) → '' (blank cell).
const numOr = (v) => (typeof v === 'number' && Number.isFinite(v)) ? v
  : (v != null && v !== '' && Number.isFinite(Number(v)) ? Number(v) : '');
const byGroup = (arr, code) => (arr || []).find((r) => String(r.group || '').toUpperCase() === code) || null;

/** A local sheet with cell-ref helpers so formulas reference the RIGHT cells. */
function analyticsSheet(name, columns) {
  const rows = [];
  const idx = {};
  columns.forEach((c, i) => { idx[c.key] = i; });
  const L = (key) => xlsx.colName(idx[key]);            // column letter for a key
  const nextRn = () => rows.length + 2;                 // Excel row of the NEXT push
  const push = (row = {}) => { rows.push(row); return rows.length + 1; }; // → its Excel row
  return { name, columns, rows, L, nextRn, push };
}

/**
 * buildAnalyticsSheets(analytics, generatedAt) → the six Data-Analytics tabs.
 * NEVER throws: a null/empty snapshot renders headers + a "no data" state, with
 * zero-safe formulas (IF(denom=0,"",…)) so nothing shows an Excel #DIV/0!.
 * Column letters below are a CONTRACT with each sheet's column order.
 */
function buildAnalyticsSheets(analytics, generatedAt) {
  const a = analytics || {};
  const { fy, yy } = fyLabel(generatedAt);
  const sheets = [];

  // ── 1. Weekly Update-FY{yy} ────────────────────────────────────────────────
  // cols: A item · B reserve · C payment · D premium · E lrr · F lrp
  {
    const s = analyticsSheet(`Weekly Update-FY${yy}`, [
      { key: 'item', label: 'Item', width: 46 },
      { key: 'reserve', label: 'Reserve', width: 18, kind: 'money' },
      { key: 'payment', label: 'Payment', width: 18, kind: 'money' },
      { key: 'premium', label: 'Premium ex-VAT', width: 18, kind: 'money' },
      { key: 'lrr', label: 'Loss Ratio on Reserve %', width: 22, kind: 'money' },
      { key: 'lrp', label: 'Loss Ratio on Payment %', width: 22, kind: 'money' },
    ]);
    const { push, nextRn } = s;
    const kRes = { item: 'Total Reserve YTD' };
    const kPay = { item: 'Total Payments YTD' };
    const kLRR = { item: 'Overall Loss Ratio on Reserve %' };
    const kLRP = { item: 'Overall Loss Ratio on Payment %' };
    push({ item: `WEEKLY UPDATE — FY${fy} · ex-VAT · reserve = full estimated cost · no budgets/variance · BONU excluded from totals` });
    push({ item: 'KPIs (year to date)' });
    push(kRes); push(kPay); push(kLRR); push(kLRP);
    push({});

    // Claims by Group: COMG/DOMG/MIS + Total(excl BONU) + a manual BONU line.
    push({ item: 'CLAIMS BY GROUP (FY, excl BONU)' });
    const gN = [];
    for (const code of ['COMG', 'DOMG', 'MIS']) {
      const r = byGroup(a.claims_by_group, code);
      gN.push(push({ item: `${GROUP_LABELS[code]} (${code})`, reserve: numOr(r && r.reserve), payment: numOr(r && r.payment) }));
    }
    const gTot = push({ item: 'Total (excl BONU)',
      reserve: { f: `SUM(B${gN[0]}:B${gN[gN.length - 1]})` },
      payment: { f: `SUM(C${gN[0]}:C${gN[gN.length - 1]})` } });
    push({ item: 'BONU Legal (manual entry — excluded from Total)' });
    push({});

    // Claims by Type: one row per type + Total.
    push({ item: 'CLAIMS BY TYPE (FY)' });
    const tN = [];
    const types = a.claims_by_type || [];
    if (types.length) {
      for (const t of types) tN.push(push({ item: t.claim_type || '(unknown)', reserve: numOr(t.reserve), payment: numOr(t.payment) }));
    } else {
      tN.push(push({ item: '(no claims by type this period)' }));
    }
    const tTot = push({ item: 'Total',
      reserve: { f: `SUM(B${tN[0]}:B${tN[tN.length - 1]})` },
      payment: { f: `SUM(C${tN[0]}:C${tN[tN.length - 1]})` } });

    // Check: Group Total − Type Total should be 0 (live formula).
    push({ item: 'CHECK: Group Total − Type Total (should be 0)',
      reserve: { f: `B${gTot}-B${tTot}` }, payment: { f: `C${gTot}-C${tTot}` } });
    push({});

    // Loss Ratio Summary from loss_ratio_detail (Bonu Legal row blank).
    push({ item: 'LOSS RATIO SUMMARY (FY, ex-VAT)' });
    const lrLines = (a.loss_ratio_detail && a.loss_ratio_detail.length)
      ? a.loss_ratio_detail
      : LOSS_RATIO_LINES.map((d) => ({ detail: d, premium_ex_vat: null, reserve: null, paid: null }));
    const lrN = [];
    for (const d of lrLines) {
      const isBonu = /bonu/i.test(d.detail || '');
      const rn = nextRn();
      lrN.push(push({
        item: d.detail || '',
        reserve: isBonu ? '' : numOr(d.reserve),
        payment: isBonu ? '' : numOr(d.paid),
        premium: isBonu ? '' : numOr(d.premium_ex_vat),
        lrr: isBonu ? '' : { f: `IF(D${rn}=0,"",B${rn}/D${rn}*100)` },
        lrp: isBonu ? '' : { f: `IF(D${rn}=0,"",C${rn}/D${rn}*100)` },
      }));
    }
    const lrTot = nextRn();
    push({ item: 'Total (excl BONU)',
      reserve: { f: `SUM(B${lrN[0]}:B${lrN[lrN.length - 1]})` },
      payment: { f: `SUM(C${lrN[0]}:C${lrN[lrN.length - 1]})` },
      premium: { f: `SUM(D${lrN[0]}:D${lrN[lrN.length - 1]})` },
      lrr: { f: `IF(D${lrTot}=0,"",B${lrTot}/D${lrTot}*100)` },
      lrp: { f: `IF(D${lrTot}=0,"",C${lrTot}/D${lrTot}*100)` } });

    // Fill the KPI header row (references the totals below).
    kRes.reserve = { f: `B${gTot}` };
    kPay.payment = { f: `C${gTot}` };
    kLRR.lrr = { f: `IF(D${lrTot}=0,"",B${gTot}/D${lrTot}*100)` };
    kLRP.lrp = { f: `IF(D${lrTot}=0,"",C${gTot}/D${lrTot}*100)` };
    sheets.push({ name: s.name, columns: s.columns, rows: s.rows });
  }

  // ── 2. Major Claims FY{yy} ─────────────────────────────────────────────────
  // cols: A ref · B ctype · C count · D reserve · E paid · F premium · G status
  //       · H repud · I inforce · J freq · K lrr · L flag
  {
    const s = analyticsSheet(`Major Claims FY${yy}`, [
      { key: 'ref', label: 'Claim No / Type / Broker / Metric', width: 34 },
      { key: 'ctype', label: 'Claim Type', width: 18 },
      { key: 'count', label: 'Claims', width: 10 },
      { key: 'reserve', label: 'Reserve', width: 18, kind: 'money' },
      { key: 'paid', label: 'Paid', width: 18, kind: 'money' },
      { key: 'premium', label: 'Premium ex-VAT (FY)', width: 18, kind: 'money' },
      { key: 'status', label: 'Status', width: 10 },
      { key: 'repud', label: 'Repudiated', width: 12 },
      { key: 'inforce', label: 'In-force', width: 10 },
      { key: 'freq', label: 'Claim Frequency %', width: 16, kind: 'money' },
      { key: 'lrr', label: 'Loss Ratio on Reserve %', width: 22, kind: 'money' },
      { key: 'flag', label: 'Flag', width: 22 },
    ]);
    const { push, nextRn } = s;
    const kReg = { ref: 'Registered (major claims, reserve > BWP 300k)' };
    const kRes = { ref: 'Total Reserve' };
    const kPaid = { ref: 'Total Paid' };
    const kOpen = { ref: 'Open' };
    const kClosed = { ref: 'Closed' };
    const kRepud = { ref: 'Repudiations' };
    push({ ref: `MAJOR CLAIMS — FY${fy} · reserve = full estimated cost · watch-list: reserve > BWP 300,000` });
    push({ ref: 'KPIs' });
    push(kReg); push(kRes); push(kPaid); push(kOpen); push(kClosed); push(kRepud);
    push({});

    push({ ref: 'MAJOR CLAIMS (detail)' });
    const mc = a.major_claims || [];
    const mcN = [];
    if (mc.length) {
      for (const c of mc) mcN.push(push({ ref: c.claim_no || '', ctype: c.claim_type || '', reserve: numOr(c.reserve), paid: numOr(c.paid), status: c.status || '', repud: c.repudiated ? 'YES' : '' }));
    } else {
      mcN.push(push({ ref: '(no major claims this period)' }));
    }
    const mcF = mcN[0], mcL = mcN[mcN.length - 1];
    if (mc.length) {
      kReg.count = { f: `COUNTA(A${mcF}:A${mcL})` };
      kRes.reserve = { f: `SUM(D${mcF}:D${mcL})` };
      kPaid.paid = { f: `SUM(E${mcF}:E${mcL})` };
      kOpen.count = { f: `COUNTIF(G${mcF}:G${mcL},"open")` };
      kClosed.count = { f: `COUNTIF(G${mcF}:G${mcL},"closed")` };
      kRepud.count = { f: `COUNTIF(H${mcF}:H${mcL},"YES")` };
    } else {
      kReg.count = 0; kRes.reserve = 0; kPaid.paid = 0; kOpen.count = 0; kClosed.count = 0; kRepud.count = 0;
    }
    push({});

    // Claim-Type breakdown with Claim Frequency % = claim_count ÷ in-force.
    // NB: claims_by_type keys on a claim-type CODE while inforce_by_type carries
    // a product NAME in its claim_type field (upstream gap) — matched best-effort
    // (case-insensitive equality); no match → in-force blank → frequency blank.
    push({ ref: 'CLAIMS BY TYPE — with claim frequency (FY)' });
    const inforceMap = new Map((a.inforce_by_type || []).map((r) => [String(r.claim_type || '').toLowerCase(), r.inforce_policies]));
    const cbt = a.claims_by_type || [];
    const ctN = [];
    if (cbt.length) {
      for (const t of cbt) {
        const rn = nextRn();
        const key = String(t.claim_type || '').toLowerCase();
        const inf = inforceMap.has(key) ? inforceMap.get(key) : '';
        ctN.push(push({ ref: t.claim_type || '', count: numOr(t.claim_count), reserve: numOr(t.reserve), paid: numOr(t.payment),
          inforce: numOr(inf),
          freq: (numOr(inf) !== '') ? { f: `IF(I${rn}=0,"",C${rn}/I${rn}*100)` } : '' }));
      }
      push({ ref: 'Total',
        count: { f: `SUM(C${ctN[0]}:C${ctN[ctN.length - 1]})` },
        reserve: { f: `SUM(D${ctN[0]}:D${ctN[ctN.length - 1]})` },
        paid: { f: `SUM(E${ctN[0]}:E${ctN[ctN.length - 1]})` } });
    } else {
      push({ ref: '(no claims by type this period)' });
    }
    push({});

    // Broker breakdown with Loss Ratio on Reserve % + a Flag column.
    push({ ref: 'BROKER LOSS RATIOS — Loss Ratio on Reserve (FY) · Flag: > 70% escalates to CFO' });
    const brk = a.broker_lr || [];
    const brN = [];
    if (brk.length) {
      for (const b of brk) {
        const rn = nextRn();
        brN.push(push({ ref: b.broker || '(direct/unbrokered)', count: numOr(b.claim_count), reserve: numOr(b.reserve), paid: numOr(b.payment), premium: numOr(b.premium_fy),
          lrr: { f: `IF(F${rn}=0,"",D${rn}/F${rn}*100)` },
          flag: { f: `IF(F${rn}=0,"",IF(D${rn}/F${rn}>0.7,"ESCALATE TO CFO",""))` } }));
      }
      const brTot = nextRn();
      push({ ref: 'Total',
        count: { f: `SUM(C${brN[0]}:C${brN[brN.length - 1]})` },
        reserve: { f: `SUM(D${brN[0]}:D${brN[brN.length - 1]})` },
        paid: { f: `SUM(E${brN[0]}:E${brN[brN.length - 1]})` },
        premium: { f: `SUM(F${brN[0]}:F${brN[brN.length - 1]})` },
        lrr: { f: `IF(F${brTot}=0,"",D${brTot}/F${brTot}*100)` },
        flag: { f: `IF(F${brTot}=0,"",IF(D${brTot}/F${brTot}>0.7,"ESCALATE TO CFO",""))` } });
    } else {
      push({ ref: '(no broker claims this period)' });
    }
    sheets.push({ name: s.name, columns: s.columns, rows: s.rows });
  }

  // ── 3a. Premium Analysis ───────────────────────────────────────────────────
  // cols: A item · B premium · C motor · D non_motor · E share · F prior · G yoy
  {
    const s = analyticsSheet('Premium Analysis', [
      { key: 'item', label: 'Item / Product Line / Group', width: 34 },
      { key: 'premium', label: 'Premium ex-VAT', width: 20, kind: 'money' },
      { key: 'motor', label: 'Motor', width: 18, kind: 'money' },
      { key: 'non_motor', label: 'Non-Motor', width: 18, kind: 'money' },
      { key: 'share', label: 'Share %', width: 12, kind: 'money' },
      { key: 'prior', label: 'Prior Year', width: 18, kind: 'money' },
      { key: 'yoy', label: 'YoY %', width: 12, kind: 'money' },
    ]);
    const { push, nextRn } = s;
    const exTotal = { item: 'YTD Total (ex-VAT)' };
    push({ item: `PREMIUM ANALYSIS — FY${fy} · ex-VAT · no budgets/variance · BONU excluded from group total` });
    push({ item: 'Executive Summary' });
    push(exTotal);
    push({ item: 'Prior Year (ex-VAT) — for later, no prior data loaded' });
    push({ item: 'YoY % — for later, no prior data loaded' });
    push({});

    push({ item: 'BY PRODUCT LINE (ex-VAT)' });
    const lines = a.premium_by_line || [];
    const plRefs = [];
    if (lines.length) {
      for (const l of lines) {
        const obj = { item: l.product_line || '', premium: numOr(l.premium_ex_vat), motor: numOr(l.motor), non_motor: numOr(l.non_motor) };
        plRefs.push({ obj, rn: push(obj) });
      }
    } else {
      plRefs.push({ rn: push({ item: '(no premium by line this period)' }) });
    }
    const plF = plRefs[0].rn, plL = plRefs[plRefs.length - 1].rn;
    const plTot = nextRn();
    push({ item: 'Total', premium: { f: `SUM(B${plF}:B${plL})` }, motor: { f: `SUM(C${plF}:C${plL})` }, non_motor: { f: `SUM(D${plF}:D${plL})` }, share: { f: `IF(B${plTot}=0,"",100)` } });
    for (const { obj, rn } of plRefs) if (obj) obj.share = { f: `IF(B${plTot}=0,"",B${rn}/B${plTot}*100)` };
    exTotal.premium = { f: `B${plTot}` };

    push({});
    push({ item: 'MOTOR vs NON-MOTOR (ex-VAT)' });
    push({ item: 'Motor', premium: { f: `C${plTot}` }, share: { f: `IF(B${plTot}=0,"",C${plTot}/B${plTot}*100)` } });
    push({ item: 'Non-Motor', premium: { f: `D${plTot}` }, share: { f: `IF(B${plTot}=0,"",D${plTot}/B${plTot}*100)` } });

    push({});
    push({ item: 'DETAILED BREAKDOWN BY GROUP (ex-VAT, excl BONU)' });
    const pbg = a.premium_by_group || [];
    const gN = [];
    for (const code of ['COMG', 'DOMG', 'MIS']) { const r = byGroup(pbg, code); gN.push(push({ item: `${GROUP_LABELS[code]} (${code})`, premium: numOr(r && r.premium_ex_vat) })); }
    push({ item: 'Total (excl BONU)', premium: { f: `SUM(B${gN[0]}:B${gN[gN.length - 1]})` } });
    sheets.push({ name: s.name, columns: s.columns, rows: s.rows });
  }

  // ── 3b. Claims Analysis ────────────────────────────────────────────────────
  // cols: A item · B count · C reserve · D paid · E share · F prior · G yoy
  // (claims have no motor/non-motor split dataset, so By Claim Type replaces
  //  the Premium tab's By Product Line + Motor split.)
  {
    const s = analyticsSheet('Claims Analysis', [
      { key: 'item', label: 'Item / Claim Type / Group', width: 34 },
      { key: 'count', label: 'Claims', width: 10 },
      { key: 'reserve', label: 'Reserve', width: 18, kind: 'money' },
      { key: 'paid', label: 'Paid', width: 18, kind: 'money' },
      { key: 'share', label: 'Share of Reserve %', width: 16, kind: 'money' },
      { key: 'prior', label: 'Prior Year', width: 18, kind: 'money' },
      { key: 'yoy', label: 'YoY %', width: 12, kind: 'money' },
    ]);
    const { push, nextRn } = s;
    const exRes = { item: 'YTD Total Reserve' };
    const exPaid = { item: 'YTD Total Paid' };
    push({ item: `CLAIMS ANALYSIS — FY${fy} · reserve = full estimated cost · no budgets/variance · BONU excluded from group total` });
    push({ item: 'Executive Summary' });
    push(exRes); push(exPaid);
    push({ item: 'Prior Year — for later, no prior data loaded' });
    push({ item: 'YoY % — for later, no prior data loaded' });
    push({});

    push({ item: 'BY CLAIM TYPE (FY)' });
    const cbt = a.claims_by_type || [];
    const ctRefs = [];
    if (cbt.length) {
      for (const t of cbt) { const obj = { item: t.claim_type || '', count: numOr(t.claim_count), reserve: numOr(t.reserve), paid: numOr(t.payment) }; ctRefs.push({ obj, rn: push(obj) }); }
    } else {
      ctRefs.push({ rn: push({ item: '(no claims by type this period)' }) });
    }
    const ctF = ctRefs[0].rn, ctL = ctRefs[ctRefs.length - 1].rn;
    const ctTot = nextRn();
    push({ item: 'Total', count: { f: `SUM(B${ctF}:B${ctL})` }, reserve: { f: `SUM(C${ctF}:C${ctL})` }, paid: { f: `SUM(D${ctF}:D${ctL})` }, share: { f: `IF(C${ctTot}=0,"",100)` } });
    for (const { obj, rn } of ctRefs) if (obj) obj.share = { f: `IF(C${ctTot}=0,"",C${rn}/C${ctTot}*100)` };
    exRes.reserve = { f: `C${ctTot}` };
    exPaid.paid = { f: `D${ctTot}` };

    push({});
    push({ item: 'DETAILED BREAKDOWN BY GROUP (excl BONU)' });
    const cbg = a.claims_by_group || [];
    const gN = [];
    for (const code of ['COMG', 'DOMG', 'MIS']) { const r = byGroup(cbg, code); gN.push(push({ item: `${GROUP_LABELS[code]} (${code})`, reserve: numOr(r && r.reserve), paid: numOr(r && r.payment) })); }
    push({ item: 'Total (excl BONU)', reserve: { f: `SUM(C${gN[0]}:C${gN[gN.length - 1]})` }, paid: { f: `SUM(D${gN[0]}:D${gN[gN.length - 1]})` } });
    sheets.push({ name: s.name, columns: s.columns, rows: s.rows });
  }

  // ── 4. Renewals (30-day trigger) ───────────────────────────────────────────
  // NO summed Portal+Graphite premium column — blocked (≈21% double-count).
  {
    const s = analyticsSheet('Renewals (30-day trigger)', [
      { key: 'policy_no', label: 'Policy No', width: 22 },
      { key: 'gfs_ref', label: 'GFS Ref', width: 18 },
      { key: 'product_line', label: 'Product Line', width: 28 },
      { key: 'days_to_expiry', label: 'Days to Expiry', width: 14 },
      { key: 'expiry_date', label: 'Expiry Date', width: 14 },
    ]);
    const { push } = s;
    push({ policy_no: 'NOTE: a summed Portal+Graphite premium is intentionally OMITTED here (about 21% double-count) — pending written sign-off.' });
    const rens = a.renewals_trigger || [];
    if (rens.length) {
      const rN = [];
      for (const r of rens) rN.push(push({ policy_no: r.policy_no || '', gfs_ref: r.gfs_ref || '', product_line: r.product_line || '', days_to_expiry: numOr(r.days_to_expiry), expiry_date: r.expiry_date || '' }));
      push({ policy_no: 'Total policies due (<= 30 days)', days_to_expiry: { f: `COUNTA(A${rN[0]}:A${rN[rN.length - 1]})` } });
    } else {
      push({ policy_no: '(no policies within 30 days of expiry this period)' });
    }
    sheets.push({ name: s.name, columns: s.columns, rows: s.rows });
  }

  // ── 5. KYC Completeness ────────────────────────────────────────────────────
  // cols: A branch · B agent · C policies · D complete · E pct  (counts, no PII)
  {
    const s = analyticsSheet('KYC Completeness', [
      { key: 'branch', label: 'Branch', width: 30 },
      { key: 'agent', label: 'Agent ID', width: 12 },
      { key: 'policies', label: 'Policies', width: 12 },
      { key: 'complete', label: 'Complete', width: 12 },
      { key: 'pct', label: '% Complete', width: 12, kind: 'money' },
    ]);
    const { push, nextRn } = s;
    const kyc = a.kyc_completeness || [];
    if (kyc.length) {
      const kN = [];
      for (const r of kyc) { const rn = nextRn(); kN.push(push({ branch: r.branch || '', agent: r.agent || '', policies: numOr(r.policies), complete: numOr(r.complete), pct: { f: `IF(C${rn}=0,"",D${rn}/C${rn}*100)` } })); }
      const tRn = nextRn();
      push({ branch: 'Total', policies: { f: `SUM(C${kN[0]}:C${kN[kN.length - 1]})` }, complete: { f: `SUM(D${kN[0]}:D${kN[kN.length - 1]})` }, pct: { f: `IF(C${tRn}=0,"",D${tRn}/C${tRn}*100)` } });
    } else {
      push({ branch: '(no KYC completeness data this period)' });
    }
    sheets.push({ name: s.name, columns: s.columns, rows: s.rows });
  }

  return sheets;
}

/**
 * buildDataset(key, { queue, affected, analytics }) → { sheets, meta } for one
 * audience. Pure: no I/O, no email. Callers supply the data (testable).
 */
function buildDataset(key, data = {}) {
  const ds = DATASETS[key];
  if (!ds) throw new Error(`unknown dataset: ${key}`);
  const queue = data.queue || {};
  const all = flatten(queue).filter(ds.pick);
  const rows = all.slice(0, MAX_ROWS).map(toRow);
  const meta = {
    dataset: key,
    label: ds.label,
    title: ds.title,
    plain: ds.plain,
    generatedAt: queue.generatedAt || null,
    source: queue.source || null,
    liveArms: !!queue.liveArms,
    available: all.length,
    written: rows.length,
  };

  const sheets = [readMeSheet(ds, meta)];

  // Summary sheet — the counts, so the reader can reconcile before scrolling.
  const byDomain = {};
  for (const it of all) byDomain[it.domain || 'other'] = (byDomain[it.domain || 'other'] || 0) + 1;
  sheets.push({
    name: 'Summary',
    columns: [{ key: 'k', label: 'Type', width: 26 }, { key: 'n', label: 'Count', width: 12 }],
    rows: Object.entries(byDomain).sort((a, b) => b[1] - a[1]).map(([k, n]) => ({ k, n }))
      .concat([{ k: 'TOTAL', n: all.length }]),
  });

  sheets.push({ name: 'Exceptions', columns: ds.columns, rows });

  if (ds.withAffected && Array.isArray(data.affected) && data.affected.length) {
    sheets.push({
      name: 'Non-payment clock',
      columns: AFFECTED_COLUMNS,
      rows: data.affected.slice(0, MAX_ROWS),
    });
  }

  // The Data-Analytics workbook is EXTENDED with the analytics tabs (Weekly
  // Update / Major Claims / Premium / Claims / Renewals / KYC). Analytics-only —
  // the other teams' workbooks are unchanged. Guarded + never throws.
  if (key === 'analytics') {
    for (const sh of buildAnalyticsSheets(data.analytics, meta.generatedAt)) sheets.push(sh);
  }
  return { sheets, meta };
}

/** workbook(key, data) → Buffer of the .xlsx. */
function workbook(key, data) {
  const { sheets, meta } = buildDataset(key, data);
  return { buffer: xlsx.build(sheets), meta };
}

/** csv(key, data) → the Exceptions sheet as CSV, for people who prefer it. */
function csv(key, data) {
  const { sheets, meta } = buildDataset(key, data);
  const sheet = sheets.find((s) => s.name === 'Exceptions');
  return { text: xlsx.toCsv(sheet), meta };
}

/** filename(key, generatedAt) → alpha-brain-kyc-2026-07-28.xlsx */
function filename(key, generatedAt, ext = 'xlsx') {
  const day = String(generatedAt || '').slice(0, 10) || 'undated';
  return `alpha-brain-${key}-${day}.${ext}`;
}

/**
 * recipients(env) → { kyc:[…], debtors:[…], …, cfo:[…] }.
 * Comma-separated env per team, same pattern as fridayReport. Empty until
 * Operations sets them — nothing is hardcoded, so nothing can be emailed by
 * accident.
 */
function recipients(env = process.env) {
  const split = (v) => String(v || '').split(',').map((s) => s.trim()).filter(Boolean);
  const out = {};
  for (const [key, ds] of Object.entries(DATASETS)) out[key] = split(env[ds.recipientEnv]);
  out.cfo = split(env.BRAIN_EXTRACT_CFO_RECIPIENTS);
  return out;
}

// Only internal addresses may receive an extract. Belt-and-braces on top of the
// configured lists: a typo cannot post the company's debtors book to gmail.
const INTERNAL_DOMAINS = String(process.env.BRAIN_INTERNAL_EMAIL_DOMAINS || 'alphadirect.co.bw')
  .split(',').map((s) => s.trim().toLowerCase()).filter(Boolean);

/** internalOnly(list) → the addresses that are on an approved internal domain. */
function internalOnly(list = []) {
  return list.filter((a) => {
    const at = String(a).lastIndexOf('@');
    if (at < 0) return false;
    const domain = String(a).slice(at + 1).toLowerCase();
    return INTERNAL_DOMAINS.some((d) => domain === d || domain.endsWith(`.${d}`));
  });
}

/** The covering email. Short, plain, no attachments-only guessing games. */
function renderEmailHtml(meta) {
  const cut = meta.available > meta.written
    ? `<p style="margin:0 0 10px">This file holds the first ${meta.written.toLocaleString()} of ${meta.available.toLocaleString()} rows. The rest are available on request.</p>`
    : '';
  return `<div style="font-family:Inter,Arial,sans-serif;color:#1F2937;font-size:15px;line-height:1.6">`
    + `<div style="background:#0D1B2A;padding:18px 22px"><span style="color:#F4A623;font-family:'Book Antiqua',Georgia,serif;font-size:20px">Alpha Brain — ${xlsx.xmlEsc(meta.title)}</span></div>`
    + `<div style="padding:22px">`
    + `<p style="margin:0 0 10px">For: <b>${xlsx.xmlEsc(meta.label)}</b></p>`
    + `<p style="margin:0 0 10px">${xlsx.xmlEsc(meta.plain || '')}</p>`
    + `<p style="margin:0 0 10px"><b>${meta.written.toLocaleString()}</b> item${meta.written === 1 ? '' : 's'} to work through. Data as at ${xlsx.xmlEsc(meta.generatedAt || 'unknown')}.</p>`
    + cut
    + `<p style="margin:0 0 10px">The first sheet of the attached workbook explains every column and every filter. Read it before you query a number.</p>`
    + `<p style="margin:0 0 10px;color:#6B7280;font-size:13px">The Brain listed these. It has not sent, moved or cancelled anything — every action still needs a person.</p>`
    + `<p style="margin:18px 0 0;color:#6B7280;font-size:13px">Internal use only. Do not forward outside Alpha Direct.</p>`
    + `</div></div>`;
}

module.exports = {
  DATASETS, QUEUE_COLUMNS, AFFECTED_COLUMNS, MAX_ROWS,
  flatten, toRow, buildDataset, workbook, csv, filename,
  recipients, internalOnly, renderEmailHtml,
  // Data-Analytics workbook tabs (analytics dataset extension).
  buildAnalyticsSheets, fyLabel,
};
