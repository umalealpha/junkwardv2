// Canonical filename builder for every PDF / document download in
// Graphite V2.
//
//   {policyNumber}_{DocType}_{YYYY-MMM-DD}_{HH-mm-ss}.{ext}
//   e.g. COMG2026213667_Quote_2026-JUN-13_15-42-08.pdf
//
// The format is sortable, human-readable, and survives copy-paste into
// emails / SharePoint without quoting.
//
// `policyNumber` from the API may include an action suffix like '/01'
// (multi-action policies). Slashes are illegal in most filesystems, so
// we strip everything from the first '/' onwards. Falls back to
// `policy_{id}` if the caller has no policy number yet.

const MONTHS = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'] as const

const pad = (n: number) => String(n).padStart(2, '0')

export function timestampStamp(d: Date = new Date()): string {
  return `${d.getFullYear()}-${MONTHS[d.getMonth()]}-${pad(d.getDate())}_${pad(d.getHours())}-${pad(d.getMinutes())}-${pad(d.getSeconds())}`
}

/**
 * Convert a human-readable document title like "Policy Document" or
 * "cover note" into a PascalCase docType ("PolicyDocument", "CoverNote"),
 * stripping any character that isn't a letter or digit. Used when the
 * caller's docType is dynamic (e.g. comes from an API response).
 */
export function docTypeFromTitle(title: string | null | undefined, fallback = 'Document'): string {
  if (!title) return fallback
  const cleaned = title
    .split(/\s+/)
    .map(w => w.replace(/[^A-Za-z0-9]+/g, ''))
    .filter(Boolean)
    .map(w => w.charAt(0).toUpperCase() + w.slice(1))
    .join('')
  return cleaned || fallback
}

export function policyBase(policyNumber: string | null | undefined, policyId?: number | string | null): string {
  if (policyNumber && policyNumber.length > 0) {
    return policyNumber.split('/')[0]
  }
  return policyId !== undefined && policyId !== null ? `policy_${policyId}` : 'policy'
}

/**
 * Build a canonical filename for any generated document.
 *
 * docType — PascalCase label used in the filename. Examples:
 *   "Quote", "AccountStatement", "PolicyDocument", "CoverNote",
 *   "CancelNote", "RefundNote", "CreditNote", "Invoice", "RateSheet",
 *   "KYCDocument"
 *
 * ext defaults to "pdf" — pass "xlsx" / "csv" / etc. for non-PDF docs.
 */
export function buildDocFilename(
  policyNumber: string | null | undefined,
  docType: string,
  opts: { policyId?: number | string | null; ext?: string; at?: Date } = {},
): string {
  const base = policyBase(policyNumber, opts.policyId)
  const stamp = timestampStamp(opts.at)
  const ext = (opts.ext ?? 'pdf').replace(/^\./, '')
  return `${base}_${docType}_${stamp}.${ext}`
}
