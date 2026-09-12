/**
 * Smart Underwriting — the classification exceptions, and the underwriter's
 * final say over them.
 *
 * The client sends their schedule in whatever shape they use; the AI maps it
 * into our format — Coverage, Extension, Miscellaneous Item, Excess. Two
 * things are deliberately NOT decided by the AI:
 *
 *   "please check"        a line it placed but is not confident about. It stays
 *                         where it was put, flagged, and can be moved.
 *   "needs classification" a line it would not guess a bucket for at all. It
 *                         sits unplaced until a person picks the bucket.
 *
 * The reason for the second one is money, not tidiness: a line guessed into
 * Excess or Coverage changes the premium and nothing downstream would ever
 * flag it, so the reader is instructed to leave it alone rather than guess.
 *
 * Everything here is pure — a risk object in, a NEW risk object out — so the
 * review screen can hold an amended copy, save it, and hand the same object to
 * the wizard's apply path without any of the three disagreeing.
 */
import { parseExcess } from './smartUwPrefill'
import type {
  SmartUwRisk, SmartUwCoverage, SmartUwUnclassified,
} from './smartUwPrefill'

/** Our four buckets. Keys are the extraction's array names. */
export type Bucket = 'details' | 'extensions' | 'specified_items' | 'excesses'

export const BUCKETS: Bucket[] = ['details', 'extensions', 'specified_items', 'excesses']

/** What the underwriter sees them called — Graphite's own words, not the JSON's. */
export const BUCKET_LABEL: Record<Bucket, string> = {
  details: 'Coverage',
  extensions: 'Extension',
  specified_items: 'Miscellaneous Item',
  excesses: 'Excess',
}

/** Where a line lives. `section: null` = the risk-level unclassified bucket. */
export interface LineRef {
  section: number | null
  bucket: Bucket | null
  index: number
}

/** Figures a line carries, whichever bucket it is in. */
export interface LineAmounts {
  sum_insured: number | null
  rate: number | null
  premium: number | null
}

/** An unplaced line, waiting for a person. */
export interface PendingLine extends LineAmounts {
  ref: LineRef
  text: string
  /** the section it was printed under; '' when it sat under none */
  sectionTitle: string
  reason: string
}

/** A placed line the reader is not confident about. */
export interface FlaggedLine extends LineAmounts {
  ref: LineRef
  bucket: Bucket
  sectionTitle: string
  text: string
  reason: string
}

const num = (v: any): number | null => {
  if (v === null || v === undefined || v === '') return null
  const n = Number(v)
  return isNaN(n) ? null : n
}

const str = (v: any) => String(v ?? '').trim()

const clone = <T,>(v: T): T => JSON.parse(JSON.stringify(v ?? null))

const sections = (risk: SmartUwRisk): SmartUwCoverage[] =>
  Array.isArray(risk?.coverages) ? risk.coverages : []

const rowsOf = (cov: SmartUwCoverage | undefined, bucket: Bucket): any[] => {
  const v = cov ? (cov as any)[bucket] : null
  return Array.isArray(v) ? v : []
}

export const sectionTitle = (cov: SmartUwCoverage | undefined): string =>
  str(cov?.section) || '(untitled section)'

/** The one piece of text that identifies a line, whichever bucket holds it. */
export function lineText(bucket: Bucket, row: any): string {
  if (bucket === 'details') return str(row?.description)
  if (bucket === 'excesses') return str(row?.text)
  return str(row?.name) || str(row?.text)
}

export function lineAmounts(bucket: Bucket, row: any): LineAmounts {
  if (bucket === 'excesses') {
    // An excess carries no sum insured; its numbers are the minimum amount
    // and the percentage, and min_amount is the only one that is money.
    return { sum_insured: null, rate: num(row?.min_percent), premium: num(row?.min_amount) }
  }
  return {
    sum_insured: num(row?.sum_insured),
    rate: num(row?.rate),
    premium: num(row?.premium),
  }
}

/** Lines waiting to be classified — per section first, then the risk-level ones. */
export function pendingLines(risk: SmartUwRisk | null | undefined): PendingLine[] {
  if (!risk) return []
  const out: PendingLine[] = []

  sections(risk).forEach((cov, si) => {
    const rows = Array.isArray(cov?.unclassified) ? cov.unclassified : []
    rows.forEach((row: SmartUwUnclassified, i) => {
      out.push({
        ref: { section: si, bucket: null, index: i },
        text: str(row?.text),
        sectionTitle: sectionTitle(cov),
        reason: str(row?.reason),
        sum_insured: num(row?.sum_insured),
        rate: num(row?.rate),
        premium: num(row?.premium),
      })
    })
  })

  const top = Array.isArray(risk.unclassified) ? risk.unclassified : []
  top.forEach((row: SmartUwUnclassified, i) => {
    out.push({
      ref: { section: null, bucket: null, index: i },
      text: str(row?.text),
      // A top-level line may still name the heading it was printed under —
      // useful context, but it is NOT a section on this risk, so it reads as
      // free text rather than as a match.
      sectionTitle: str(row?.section),
      reason: str(row?.reason),
      sum_insured: num(row?.sum_insured),
      rate: num(row?.rate),
      premium: num(row?.premium),
    })
  })

  return out
}

/** Placed lines flagged "please check". */
export function flaggedLines(risk: SmartUwRisk | null | undefined): FlaggedLine[] {
  if (!risk) return []
  const out: FlaggedLine[] = []

  sections(risk).forEach((cov, si) => {
    BUCKETS.forEach((bucket) => {
      rowsOf(cov, bucket).forEach((row, i) => {
        if (!row?.needs_check) return
        out.push({
          ref: { section: si, bucket, index: i },
          bucket,
          sectionTitle: sectionTitle(cov),
          text: lineText(bucket, row) || '(unnamed line)',
          reason: str(row?.check_reason),
          ...lineAmounts(bucket, row),
        })
      })
    })
  })

  return out
}

export interface ExceptionCount {
  pending: number
  flagged: number
  total: number
}

export function exceptionCount(risk: SmartUwRisk | null | undefined): ExceptionCount {
  const pending = pendingLines(risk).length
  const flagged = flaggedLines(risk).length
  return { pending, flagged, total: pending + flagged }
}

/**
 * Do these two buckets hold the same KIND of figures?
 *
 * An excess's numbers are a percentage and a minimum amount. Every other
 * bucket's are a sum insured, a rate and a premium. lineAmounts() packs the
 * excess pair into the rate/premium slots so one shape serves all four, which
 * is fine for a round trip but NOT for a move between the two kinds.
 */
const sameMoneyShape = (a: Bucket, b: Bucket) => (a === 'excesses') === (b === 'excesses')

/**
 * Build the row a bucket expects from a line's text and figures.
 *
 * Each bucket has its own shape, and the apply path reads these keys directly
 * (smartUwApply::buildSectionChildren) — so a placement has to produce the
 * same shape the extractor would have produced, not a generic blob.
 *
 * `from` is the bucket the figures CAME from, set only when moving an
 * already-placed line. When it holds the other kind of money the figures are
 * dropped rather than re-slotted: carrying them across turned "10% of the
 * claim, min P10,000" into a coverage line with rate 10 and premium 10,000,
 * and turned a coverage line's sum insured into nothing at all. The wording is
 * kept verbatim, an excess's own numbers are re-read from that wording, and
 * check_reason says the figures need entering — which the section table
 * renders whether or not the line is flagged.
 */
function makeRow(bucket: Bucket, text: string, amounts: LineAmounts, from?: Bucket): any {
  const crossKind = !!from && !sameMoneyShape(from, bucket)
  const a: LineAmounts = crossKind
    ? { sum_insured: null, rate: null, premium: null }
    : amounts
  const base = {
    needs_check: false,
    check_reason: crossKind
      ? `Moved from ${BUCKET_LABEL[from!]} — the figures were not carried over `
        + `because ${BUCKET_LABEL[from!]} and ${BUCKET_LABEL[bucket]} do not hold `
        + 'the same kind of amount. Enter them if the schedule states them.'
      : null,
    placed_by_hand: true,
  }

  if (bucket === 'details') {
    return {
      ...base,
      description: text,
      sum_insured: a.sum_insured,
      rate: a.rate,
      premium: a.premium,
    }
  }

  if (bucket === 'specified_items') {
    return {
      ...base,
      name: text,
      sum_insured: a.sum_insured,
      rate: a.rate,
      premium: a.premium,
    }
  }

  if (bucket === 'extensions') {
    // An extension with no figures is a wording-only clause (a warranty, a
    // memorandum). It has to carry `text`, or the apply path treats it as an
    // empty row and reports it unplaced instead of writing the wording.
    const hasMoney = !!(a.sum_insured || a.premium)
    return {
      ...base,
      name: text,
      sum_insured: a.sum_insured,
      premium: a.premium,
      text: hasMoney ? null : text,
    }
  }

  // Excess. The clause is kept verbatim and its numbers pulled out of the
  // wording, exactly as the extractor does — but an amount the underwriter
  // typed wins over one parsed from the sentence. On a move from another
  // bucket the typed figures are gone (they were the wrong kind), so the
  // wording is the only source, which is exactly what it should be.
  const parsed = parseExcess(text)
  return {
    ...base,
    text,
    min_percent: a.rate ?? parsed.min_percent ?? null,
    min_amount: a.premium ?? parsed.min_amount ?? null,
  }
}

/** Remove a pending line from wherever it sits. Mutates the cloned risk. */
function dropPendingAt(next: SmartUwRisk, ref: LineRef): void {
  if (ref.section === null) {
    const top = Array.isArray(next.unclassified) ? next.unclassified : []
    next.unclassified = top.filter((_, i) => i !== ref.index)
    return
  }
  const covs = sections(next)
  const cov = covs[ref.section]
  if (!cov) return
  const rows = Array.isArray(cov.unclassified) ? cov.unclassified : []
  cov.unclassified = rows.filter((_, i) => i !== ref.index)
}

/**
 * Place an unclassified line into one of our four buckets on one section.
 *
 * `text` and the figures are passed in rather than read off the line, because
 * the underwriter can correct both before placing — the schedule's wording is
 * a starting point, not a rule.
 */
export function placePending(
  risk: SmartUwRisk,
  ref: LineRef,
  target: { section: number; bucket: Bucket },
  text: string,
  amounts: LineAmounts
): SmartUwRisk {
  const next: SmartUwRisk = clone(risk)
  const covs = sections(next)
  const cov = covs[target.section]
  if (!cov) return risk

  // A pending line that was un-placed from a bucket remembers which one, so
  // the cross-shape guard in makeRow applies on this two-hop route too:
  // excess -> "needs classification" -> Coverage used to carry "10%" and
  // "P10,000" into the coverage's rate and premium.
  const pending = ref.section === null
    ? (Array.isArray(next.unclassified) ? next.unclassified : [])[ref.index]
    : (Array.isArray(covs[ref.section]?.unclassified)
        ? (covs[ref.section] as any).unclassified
        : [])[ref.index]
  const origin = BUCKETS.find((b) => b === (pending as any)?._from)

  const rows = rowsOf(cov, target.bucket)
  ;(cov as any)[target.bucket] = [...rows, makeRow(target.bucket, text, amounts, origin)]
  dropPendingAt(next, ref)

  return next
}

/**
 * Leave a line off the quote.
 *
 * Not the same as classifying it: the underwriter has read it and decided it
 * carries no cover (a heading, a total, a note the broker printed). It is
 * removed from the exceptions so the count means something.
 */
export function dropPending(risk: SmartUwRisk, ref: LineRef): SmartUwRisk {
  const next: SmartUwRisk = clone(risk)
  dropPendingAt(next, ref)

  return next
}

/** "I have read it, it is in the right bucket" — clears the flag only. */
export function confirmFlagged(risk: SmartUwRisk, ref: LineRef): SmartUwRisk {
  if (ref.section === null || !ref.bucket) return risk
  const next: SmartUwRisk = clone(risk)
  const cov = sections(next)[ref.section]
  const row = rowsOf(cov, ref.bucket)[ref.index]
  if (!row) return risk
  row.needs_check = false
  row.check_reason = null
  row.placed_by_hand = true

  return next
}

/**
 * Move a placed line into a different bucket of the same section.
 *
 * The mover for both kinds of exception: a "please check" line the underwriter
 * disagrees with, and any line at all they want re-bucketed while building the
 * quote. Its text and figures come across, converted into the target bucket's
 * shape.
 */
export function moveLine(risk: SmartUwRisk, ref: LineRef, to: Bucket): SmartUwRisk {
  if (ref.section === null || !ref.bucket || ref.bucket === to) return risk
  const next: SmartUwRisk = clone(risk)
  const cov = sections(next)[ref.section]
  if (!cov) return risk

  const from = rowsOf(cov, ref.bucket)
  const row = from[ref.index]
  if (!row) return risk

  const text = lineText(ref.bucket, row)
  const amounts = lineAmounts(ref.bucket, row)

  ;(cov as any)[ref.bucket] = from.filter((_, i) => i !== ref.index)
  // ref.bucket is passed as the SOURCE so makeRow can refuse to re-slot an
  // excess's percentage/minimum as a rate/premium, or the other way round.
  ;(cov as any)[to] = [...rowsOf(cov, to), makeRow(to, text, amounts, ref.bucket)]

  return next
}

/**
 * Send a placed line back to "needs classification".
 *
 * The escape hatch for a line the underwriter cannot place either — better
 * parked as an exception than left in a bucket nobody believes.
 */
export function unplaceLine(risk: SmartUwRisk, ref: LineRef): SmartUwRisk {
  if (ref.section === null || !ref.bucket) return risk
  const next: SmartUwRisk = clone(risk)
  const cov = sections(next)[ref.section]
  if (!cov) return risk

  const from = rowsOf(cov, ref.bucket)
  const row = from[ref.index]
  if (!row) return risk

  const text = lineText(ref.bucket, row)
  const a = lineAmounts(ref.bucket, row)
  ;(cov as any)[ref.bucket] = from.filter((_, i) => i !== ref.index)
  cov.unclassified = [
    ...(Array.isArray(cov.unclassified) ? cov.unclassified : []),
    {
      text,
      sum_insured: a.sum_insured,
      rate: a.rate,
      premium: a.premium,
      reason: 'Sent back for classification by the underwriter.',
      // Remember where the figures came from, so placing this line into a
      // bucket that holds a different kind of money drops them instead of
      // re-slotting them — the same guard the single-hop moveLine applies.
      _from: ref.bucket,
    },
  ]

  return next
}
