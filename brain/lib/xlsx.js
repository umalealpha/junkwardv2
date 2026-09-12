'use strict';
/**
 * xlsx.js — build a real .xlsx workbook with Node built-ins only.
 *
 * The brain is deliberately zero-dependency, and the CFO asked for the weekly
 * team extracts "in excel" — not CSV. An .xlsx file is a ZIP of XML parts, and
 * Node already ships both halves (zlib for deflate, crypto not needed), so the
 * whole writer is ~150 lines and adds no supply chain.
 *
 * Supports what a data extract actually needs and nothing more (YAGNI):
 *   - multiple sheets
 *   - a bold, filled header row (Alpha Direct navy + white text)
 *   - text / number / date cells, auto-detected
 *   - frozen header row + auto-filter, so 50k rows are usable
 *   - sensible column widths
 *
 * Plus the CFO's report-formatting standard, all additive and opt-in so every
 * existing caller keeps working:
 *   - Book Antiqua on header + body (the recipient's Office renders the name)
 *   - a money format (#,##0.00, right-aligned) selected per-column via
 *     `kind:'money'` — the value renders as a real number, not text
 *   - subtly banded body rows
 *   - live formula cells: a cell value of { f:'SUM(...)' } emits a real <f>,
 *     so a Summary sheet can total another sheet's column
 *
 * No merged cells, no charts. If a report needs those, it is a report, not an
 * extract.
 */

const zlib = require('zlib');

const NAVY = '0D1B2A';   // Alpha Direct brand navy
const ORANGE = 'F4A623'; // Alpha Direct brand orange

// ── ZIP (store/deflate) ─────────────────────────────────────────────────────
const CRC_TABLE = (() => {
  const t = new Int32Array(256);
  for (let n = 0; n < 256; n++) {
    let c = n;
    for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
    t[n] = c;
  }
  return t;
})();

function crc32(buf) {
  let c = -1;
  for (let i = 0; i < buf.length; i++) c = CRC_TABLE[(c ^ buf[i]) & 0xff] ^ (c >>> 8);
  return (c ^ -1) >>> 0;
}

/** zip(entries) → Buffer. entries = [{ name, data:Buffer }]. */
function zip(entries) {
  const locals = [];
  const centrals = [];
  let offset = 0;
  for (const e of entries) {
    const nameBuf = Buffer.from(e.name, 'utf8');
    const deflated = zlib.deflateRawSync(e.data, { level: 6 });
    // Only compress when it actually helps (tiny XML parts can grow).
    const useDeflate = deflated.length < e.data.length;
    const body = useDeflate ? deflated : e.data;
    const method = useDeflate ? 8 : 0;
    const crc = crc32(e.data);

    const local = Buffer.alloc(30 + nameBuf.length);
    local.writeUInt32LE(0x04034b50, 0);   // local file header signature
    local.writeUInt16LE(20, 4);           // version needed
    local.writeUInt16LE(0, 6);            // flags
    local.writeUInt16LE(method, 8);
    local.writeUInt16LE(0, 10);           // time
    local.writeUInt16LE(0x21, 12);        // date — fixed 1 Jan 1980 (deterministic output)
    local.writeUInt32LE(crc, 14);
    local.writeUInt32LE(body.length, 18);
    local.writeUInt32LE(e.data.length, 22);
    local.writeUInt16LE(nameBuf.length, 26);
    local.writeUInt16LE(0, 28);           // extra length
    nameBuf.copy(local, 30);

    const central = Buffer.alloc(46 + nameBuf.length);
    central.writeUInt32LE(0x02014b50, 0);
    central.writeUInt16LE(20, 4);         // version made by
    central.writeUInt16LE(20, 6);         // version needed
    central.writeUInt16LE(0, 8);
    central.writeUInt16LE(method, 10);
    central.writeUInt16LE(0, 12);
    central.writeUInt16LE(0x21, 14);
    central.writeUInt32LE(crc, 16);
    central.writeUInt32LE(body.length, 20);
    central.writeUInt32LE(e.data.length, 24);
    central.writeUInt16LE(nameBuf.length, 28);
    central.writeUInt16LE(0, 30);         // extra
    central.writeUInt16LE(0, 32);         // comment
    central.writeUInt16LE(0, 34);         // disk
    central.writeUInt16LE(0, 36);         // internal attrs
    central.writeUInt32LE(0, 38);         // external attrs
    central.writeUInt32LE(offset, 42);
    nameBuf.copy(central, 46);

    locals.push(local, body);
    centrals.push(central);
    offset += local.length + body.length;
  }
  const centralBuf = Buffer.concat(centrals);
  const end = Buffer.alloc(22);
  end.writeUInt32LE(0x06054b50, 0);
  end.writeUInt16LE(0, 4);
  end.writeUInt16LE(0, 6);
  end.writeUInt16LE(entries.length, 8);
  end.writeUInt16LE(entries.length, 10);
  end.writeUInt32LE(centralBuf.length, 12);
  end.writeUInt32LE(offset, 16);
  end.writeUInt16LE(0, 20);
  return Buffer.concat([...locals, centralBuf, end]);
}

// ── XML helpers ─────────────────────────────────────────────────────────────
// Excel rejects a file containing raw control characters, so strip them here
// rather than discovering it when someone cannot open the workbook.
function xmlEsc(v) {
  return String(v == null ? '' : v)
    // eslint-disable-next-line no-control-regex
    .replace(/[\x00-\x08\x0B\x0C\x0E-\x1F]/g, '')
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

/** colName(0) → 'A', colName(26) → 'AA'. */
function colName(i) {
  let s = '';
  let n = i + 1;
  while (n > 0) { const r = (n - 1) % 26; s = String.fromCharCode(65 + r) + s; n = Math.floor((n - 1) / 26); }
  return s;
}

const ISO_DATE = /^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(:\d{2})?)?/;
const isNum = (v) => typeof v === 'number' && Number.isFinite(v);

/** Excel serial date (days since 1899-12-30) from an ISO date string. */
function excelSerial(iso) {
  const ms = Date.parse(iso.length <= 10 ? `${iso}T00:00:00Z` : iso);
  if (Number.isNaN(ms)) return null;
  return ms / 86_400_000 + 25569;
}

/**
 * cellXml(ref, value, styleIdx, dateStyleIdx) → one <c> element.
 *
 * Auto-typing (unchanged): finite number → numeric, ISO date string → serial +
 * date style, everything else → inline string.
 *
 * Two additive shapes:
 *   - Live formula: value = { f: 'SUM(...)' } → a real formula cell. A cached
 *     <v>0</v> is written so a viewer that does not recalculate still shows a
 *     number, not a blank; Excel overwrites it on open.
 *   - dateStyleIdx lets a banded body row swap in the banded date xf. It
 *     defaults to XF.DATE (2), so every existing caller is byte-for-byte
 *     unchanged.
 */
function cellXml(ref, value, styleIdx, dateStyleIdx) {
  const s = styleIdx ? ` s="${styleIdx}"` : '';
  if (value && typeof value === 'object' && typeof value.f === 'string') {
    return `<c r="${ref}"${s}><f>${xmlEsc(value.f)}</f><v>0</v></c>`;
  }
  if (value == null || value === '') return `<c r="${ref}"${s}/>`;
  if (isNum(value)) return `<c r="${ref}"${s}><v>${value}</v></c>`;
  const str = String(value);
  if (ISO_DATE.test(str)) {
    const serial = excelSerial(str);
    // Date format xf (default 2). Fall through to text when the date will not parse.
    if (serial != null) return `<c r="${ref}" s="${dateStyleIdx == null ? XF.DATE : dateStyleIdx}"><v>${serial}</v></c>`;
  }
  return `<c r="${ref}"${s} t="inlineStr"><is><t xml:space="preserve">${xmlEsc(str)}</t></is></c>`;
}

function sheetXml(sheet) {
  const cols = sheet.columns || [];
  const widths = cols.map((c, i) =>
    `<col min="${i + 1}" max="${i + 1}" width="${Math.min(60, Math.max(10, (c.width || String(c.label || c.key).length + 6)))}" customWidth="1"/>`).join('');
  const header = `<row r="1">${cols.map((c, i) => cellXml(`${colName(i)}1`, c.label || c.key, XF.HEADER)).join('')}</row>`;
  const body = (sheet.rows || []).map((row, r) => {
    // Banded on odd (1-based) body rows: r=0 is the first data row. Subtle fill,
    // nothing else — the money/date formats are composed into their own banded xfs.
    const banded = r % 2 === 0;
    const cells = cols.map((c, i) => {
      const ref = `${colName(i)}${r + 2}`;
      let v = row[c.key];
      const money = c.kind === 'money';
      // A money column renders as a REAL right-aligned number. Coerce a numeric
      // string so it formats (a formula object or blank passes straight through).
      if (money && v != null && v !== '' && typeof v !== 'object') {
        const n = Number(v);
        if (Number.isFinite(n)) v = n;
      }
      const base = money ? (banded ? XF.BAND_MONEY : XF.MONEY) : (banded ? XF.BAND : undefined);
      const dateStyle = banded ? XF.BAND_DATE : XF.DATE;
      return cellXml(ref, v, base, dateStyle);
    }).join('');
    return `<row r="${r + 2}">${cells}</row>`;
  }).join('');
  const lastCol = colName(Math.max(0, cols.length - 1));
  const lastRow = (sheet.rows || []).length + 1;
  return `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>`
    + `<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">`
    + `<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>`
    + (widths ? `<cols>${widths}</cols>` : '')
    + `<sheetData>${header}${body}</sheetData>`
    + (cols.length ? `<autoFilter ref="A1:${lastCol}${lastRow}"/>` : '')
    + `</worksheet>`;
}

// Very light grey for banded body rows — subtle, prints cleanly, never fights
// the money/date formats layered on top of it.
const BAND = 'F2F2F2';

// The cellXfs indices below are a CONTRACT. cellXml() and sheetXml() reference
// them by number, and the counts in this string MUST equal the number of
// numFmts / fonts / fills / cellXfs elements or Excel refuses to open the file.
//
//   cellXf  0  default          Book Antiqua 11
//           1  header           bold white on navy fill
//           2  date             yyyy-mm-dd
//           3  money            #,##0.00, right-aligned
//           4  banded default   light band fill
//           5  banded money     #,##0.00, right-aligned, band fill
//           6  banded date      yyyy-mm-dd, band fill
const STYLES_XML = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>`
  + `<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">`
  + `<numFmts count="2"><numFmt numFmtId="164" formatCode="yyyy-mm-dd"/><numFmt numFmtId="165" formatCode="#,##0.00"/></numFmts>`
  + `<fonts count="2">`
  + `<font><sz val="11"/><name val="Book Antiqua"/></font>`
  + `<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Book Antiqua"/></font>`
  + `</fonts>`
  + `<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>`
  + `<fill><patternFill patternType="solid"><fgColor rgb="FF${NAVY}"/><bgColor indexed="64"/></patternFill></fill>`
  + `<fill><patternFill patternType="solid"><fgColor rgb="FF${BAND}"/><bgColor indexed="64"/></patternFill></fill></fills>`
  + `<borders count="1"><border/></borders>`
  + `<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>`
  + `<cellXfs count="7">`
  + `<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>`
  + `<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>`
  + `<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>`
  + `<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="right"/></xf>`
  + `<xf numFmtId="0" fontId="0" fillId="3" borderId="0" xfId="0" applyFill="1"/>`
  + `<xf numFmtId="165" fontId="0" fillId="3" borderId="0" xfId="0" applyNumberFormat="1" applyFill="1" applyAlignment="1"><alignment horizontal="right"/></xf>`
  + `<xf numFmtId="164" fontId="0" fillId="3" borderId="0" xfId="0" applyNumberFormat="1" applyFill="1"/>`
  + `</cellXfs></styleSheet>`;

// Style index helpers — keep cellXml/sheetXml readable and the contract in one place.
const XF = { DEFAULT: 0, HEADER: 1, DATE: 2, MONEY: 3, BAND: 4, BAND_MONEY: 5, BAND_DATE: 6 };

/**
 * build(sheets) → Buffer of a valid .xlsx.
 * sheets = [{ name, columns:[{key,label,width?}], rows:[{key:value}] }]
 */
function build(sheets) {
  const list = (Array.isArray(sheets) ? sheets : [sheets]).filter(Boolean);
  if (!list.length) throw new Error('xlsx.build: at least one sheet required');
  // Excel sheet names: max 31 chars, none of : \ / ? * [ ]
  const safeName = (n, i) => (String(n || `Sheet${i + 1}`).replace(/[:\\/?*[\]]/g, '-').slice(0, 31) || `Sheet${i + 1}`);

  const parts = [
    { name: '[Content_Types].xml', data: Buffer.from(`<?xml version="1.0" encoding="UTF-8" standalone="yes"?>`
      + `<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">`
      + `<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>`
      + `<Default Extension="xml" ContentType="application/xml"/>`
      + `<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>`
      + `<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>`
      + list.map((_, i) => `<Override PartName="/xl/worksheets/sheet${i + 1}.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>`).join('')
      + `</Types>`, 'utf8') },
    { name: '_rels/.rels', data: Buffer.from(`<?xml version="1.0" encoding="UTF-8" standalone="yes"?>`
      + `<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">`
      + `<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>`
      + `</Relationships>`, 'utf8') },
    { name: 'xl/workbook.xml', data: Buffer.from(`<?xml version="1.0" encoding="UTF-8" standalone="yes"?>`
      + `<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" `
      + `xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>`
      + list.map((s, i) => `<sheet name="${xmlEsc(safeName(s.name, i))}" sheetId="${i + 1}" r:id="rId${i + 1}"/>`).join('')
      + `</sheets></workbook>`, 'utf8') },
    { name: 'xl/_rels/workbook.xml.rels', data: Buffer.from(`<?xml version="1.0" encoding="UTF-8" standalone="yes"?>`
      + `<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">`
      + list.map((_, i) => `<Relationship Id="rId${i + 1}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet${i + 1}.xml"/>`).join('')
      + `<Relationship Id="rId${list.length + 1}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>`
      + `</Relationships>`, 'utf8') },
    { name: 'xl/styles.xml', data: Buffer.from(STYLES_XML, 'utf8') },
    ...list.map((s, i) => ({ name: `xl/worksheets/sheet${i + 1}.xml`, data: Buffer.from(sheetXml(s), 'utf8') })),
  ];
  return zip(parts);
}

/** toCsv(sheet) → the same data as CSV, for people who prefer it. */
function toCsv(sheet) {
  const cols = sheet.columns || [];
  const cell = (v) => { const s = String(v == null ? '' : v); return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s; };
  return [cols.map((c) => cell(c.label || c.key)).join(',')]
    .concat((sheet.rows || []).map((r) => cols.map((c) => cell(r[c.key])).join(',')))
    .join('\n');
}

module.exports = { build, toCsv, zip, crc32, colName, xmlEsc, NAVY, ORANGE };
