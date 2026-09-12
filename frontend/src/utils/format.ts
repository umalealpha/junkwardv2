/**
 * Shared number formatting utilities for the entire app.
 * All monetary values should use fmtPula() for display.
 * All number inputs should use formatNumberInput() + unformatNumber().
 */

/**
 * Format a number as Pula currency with commas: P 1,234,567.00
 *
 * Negative values render with accounting-style credit notation:
 *   -288.04 → "(Credit P 288.04)"
 *
 * UAT 2026-05-26 (Arjun M6, Prathap BUG-005): Finance pushed back on the
 * literal "P -288.04" rendering — they read the minus sign as a data
 * quality bug rather than a deliberate credit. Credit notation makes
 * the intent unambiguous on ledger / claim / policy detail screens.
 *
 * If you want raw signed output (e.g. a delta column that should literally
 * show + or -), use fmtPulaSigned() instead.
 */
export function fmtPula(v: number | string | null | undefined): string {
  const n = typeof v === 'string' ? parseFloat(v.replace(/,/g, '')) : (v ?? 0)
  if (isNaN(n)) return 'P 0.00'
  if (n < 0) {
    return '(Credit P ' + Math.abs(n).toLocaleString('en-BW', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ')'
  }
  return 'P ' + n.toLocaleString('en-BW', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

/**
 * Format a number as Pula with literal sign retained:
 *   -288.04 → "P -288.04"
 *    288.04 → "P 288.04"
 *
 * Use this only when the caller explicitly wants to show a directional
 * delta (e.g. a "Change" column in a reconciliation report). For balance
 * / amount displays prefer fmtPula() which uses credit notation.
 */
export function fmtPulaSigned(v: number | string | null | undefined): string {
  const n = typeof v === 'string' ? parseFloat(v.replace(/,/g, '')) : (v ?? 0)
  if (isNaN(n)) return 'P 0.00'
  return 'P ' + n.toLocaleString('en-BW', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

/**
 * Format a date string defensively. Returns the fallback ('—' by default)
 * for null / undefined / empty / malformed dates. Without a guard,
 * `new Date(null).toLocaleDateString()` returns "1970-01-01" and
 * `new Date('foo').toLocaleDateString()` returns "Invalid Date" — both
 * surface in the UI as confusing user-visible defects.
 *
 * Default style: "27 May 2026" (en-GB long-form, no weekday). Pass a
 * different Intl options bag for variants.
 *
 * UAT 2026-05-28: drop-in replacement for inline `new Date(x).toLocaleDateString()`
 * call sites that didn't already null-guard.
 */
export function fmtDate(
  v: string | number | Date | null | undefined,
  opts: Intl.DateTimeFormatOptions = { day: 'numeric', month: 'short', year: 'numeric' },
  fallback = '—',
): string {
  if (v === null || v === undefined || v === '') return fallback
  const d = v instanceof Date ? v : new Date(v)
  if (isNaN(d.getTime())) return fallback
  return d.toLocaleDateString('en-GB', opts)
}

/**
 * Format a timestamp defensively as "27 May 2026, 14:35". Same null /
 * undefined / empty / malformed-date guards as fmtDate. Use for audit
 * trails, log timestamps, "last updated" stamps — anywhere you want both
 * date AND time. For date-only use, prefer fmtDate.
 *
 * UAT 2026-06-02: drop-in replacement for the local `fmtDate` helpers in
 * Anomalies / Consents / Reconciliation / Wordings that all rendered
 * date+time inline. Reconciliation uses fallback 'Never' which the third
 * argument supports.
 */
export function fmtDateTime(
  v: string | number | Date | null | undefined,
  opts: Intl.DateTimeFormatOptions = { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' },
  fallback = '—',
): string {
  if (v === null || v === undefined || v === '') return fallback
  const d = v instanceof Date ? v : new Date(v)
  if (isNaN(d.getTime())) return fallback
  return d.toLocaleString('en-GB', opts)
}

/**
 * Mask a national ID / Omang for display in list views, revealing only the
 * last 4 characters: "123456789" → "••• ••6789".
 *
 * DPA (Botswana Data Protection Act) 2026-07: the Omang is identifiable PII
 * and must not be shown in full in list/table contexts. The unmasked value
 * remains available on the customer DETAIL page behind the DPO-gated reveal.
 *
 * Defensive: null / undefined / empty, or values shorter than 4 usable
 * characters, render the neutral fallback ('—' by default) rather than a
 * partial that could leak. Non-alphanumeric separators (spaces, dashes) are
 * ignored when counting length so a short-but-spaced value can't slip through.
 */
export function maskId(
  v: string | number | null | undefined,
  fallback = '—',
): string {
  if (v === null || v === undefined) return fallback
  const raw = String(v).trim()
  if (raw === '') return fallback
  // Count only alphanumeric characters when deciding if it's long enough to
  // safely reveal a suffix; anything under 4 is treated as un-maskable.
  const alnum = raw.replace(/[^0-9a-zA-Z]/g, '')
  if (alnum.length < 4) return fallback
  const last4 = alnum.slice(-4)
  return `••• ••${last4}`
}

/** Format a number with commas (no currency prefix): 1,234,567.00 */
export function fmtNumber(v: number | string | null | undefined, decimals = 2): string {
  const n = typeof v === 'string' ? parseFloat(v.replace(/,/g, '')) : (v ?? 0)
  if (isNaN(n)) return '0'
  return n.toLocaleString('en-BW', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })
}

/** Format a number with commas for display in an input field: 1,234,567 */
export function formatNumberInput(v: string | number | null | undefined): string {
  if (v === null || v === undefined || v === '') return ''
  const str = String(v).replace(/,/g, '')
  const num = parseFloat(str)
  if (isNaN(num)) return String(v)
  const parts = str.split('.')
  const intPart = parseInt(parts[0], 10)
  const formatted = isNaN(intPart) ? parts[0] : intPart.toLocaleString('en-BW')
  return parts.length > 1 ? `${formatted}.${parts[1]}` : formatted
}

/** Strip commas and non-numeric characters from formatted number string: "1,234.5abc" → "1234.5" */
export function unformatNumber(v: string): string {
  // Remove commas first, then keep only digits, decimal point, and minus sign
  return v.replace(/,/g, '').replace(/[^0-9.-]/g, '')
}

/**
 * Render a number as a short English magnitude hint. Banding uses the
 * "hundred + unit" form common in Botswana / southern Africa and in
 * everyday European English — so 500,000 reads as "5 hundred thousand"
 * instead of "500 thousand", and 300,000,000 reads as "3 hundred million"
 * instead of "300 million".
 *
 * Bands:
 *   100 ≤ n <          1,000 -> N hundred
 *   1,000 ≤ n <      100,000 -> N thousand
 *   100,000 ≤ n <   1,000,000 -> N hundred thousand
 *   1M ≤ n <         100M     -> N million
 *   100M ≤ n <         1B     -> N hundred million
 *   1B ≤ n <           100B   -> N billion
 *   100B ≤ n <           1T   -> N hundred billion
 *   n ≥ 1T                    -> N trillion
 *
 * Returns '' for 0 / NaN / negatives so callers can render nothing.
 */
export function toWordsShort(v: number | string | null | undefined): string {
  if (v === null || v === undefined || v === '') return ''
  const n = typeof v === 'string' ? parseFloat(v.replace(/,/g, '')) : v
  if (!Number.isFinite(n) || n <= 0) return ''
  const trim = (x: number) => x.toFixed(2).replace(/\.?0+$/, '')

  if (n >= 1_000_000_000_000)   return `≈ ${trim(n / 1_000_000_000_000)} trillion`
  if (n >= 100_000_000_000)     return `≈ ${trim(n / 100_000_000_000)} hundred billion`
  if (n >= 1_000_000_000)       return `≈ ${trim(n / 1_000_000_000)} billion`
  if (n >= 100_000_000)         return `≈ ${trim(n / 100_000_000)} hundred million`
  if (n >= 1_000_000)           return `≈ ${trim(n / 1_000_000)} million`
  if (n >= 100_000)             return `≈ ${trim(n / 100_000)} hundred thousand`
  if (n >= 1_000)               return `≈ ${trim(n / 1_000)} thousand`
  if (n >= 100)                 return `≈ ${trim(n / 100)} hundred`
  return ''
}

/**
 * Compact Pula for headline / KPI figures: "P 42.57M", "P 1.5K", "P 950".
 *
 * The two dashboards historically each rolled their own compact formatter
 * (Sales used `P 42,570,000` in full, Finance used a local `fmtP` → `P 42.57M`)
 * so the same premium read differently across screens. This is the ONE shared
 * compact form for big figures on both dashboards. For full-precision ledger /
 * detail amounts keep using fmtPula(); this is for scannable summary tiles.
 *
 * Negatives render with the same credit notation as fmtPula so a compact tile
 * never shows a bare minus that Finance reads as a data bug.
 */
export function fmtPulaCompact(v: number | string | null | undefined): string {
  const n = typeof v === 'string' ? parseFloat(v.replace(/,/g, '')) : (v ?? 0)
  if (isNaN(n)) return 'P 0.00'
  const sign = n < 0
  const abs = Math.abs(n)
  let body: string
  if (abs >= 1_000_000) body = (abs / 1_000_000).toFixed(2) + 'M'
  else if (abs >= 1_000) body = (abs / 1_000).toFixed(1) + 'K'
  else body = abs.toLocaleString('en-BW', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
  return sign ? `(Credit P ${body})` : `P ${body}`
}
