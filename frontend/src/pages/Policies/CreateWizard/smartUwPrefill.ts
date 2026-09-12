/**
 * Smart Underwriting prefill — pure mapping helpers.
 *
 * The Smart Upload page extracts a broker schedule into the smart-uw-engine
 * schema (see smart-uw-engine/schema.py::TARGET_SCHEMA and its PHP port
 * PhpScheduleExtractor::targetSchema). Everything in that payload was, until
 * now, dropped on the floor: the wizard prefill filled customer contact, term
 * dates and the risk address only, so the coverage sections, sums insured,
 * rates and the vehicle schedule never reached a policy.
 *
 * This module turns an extracted section into something the coverage form can
 * hold. It is deliberately pure and lookup-driven — the coverage id is resolved
 * from the product's OWN coverage master list at runtime (availableCoverages),
 * never from a hardcoded id table, so a product whose coverage set differs
 * still resolves correctly and an unknown section reports "no match" instead of
 * silently landing on the wrong coverage.
 */

// ── Extracted payload ──────────────────────────────────────────────────────

/**
 * Set on any line the reader placed WITHOUT being confident of the bucket.
 *
 * The line is still placed — it shows on the review screen as "please check"
 * and the underwriter can move it. Never a reason to hide or drop a line.
 */
export interface NeedsCheck {
  needs_check?: boolean | null
  check_reason?: string | null
  /** true once an underwriter placed or confirmed this line by hand, so the
   *  screen can show whose call it was rather than re-flagging it */
  placed_by_hand?: boolean | null
}

export interface SmartUwDetail extends NeedsCheck {
  description?: string | null
  sum_insured?: number | null
  rate?: number | null
  premium?: number | null
}

/** An extension / warranty / clause the schedule lists under a section. */
export interface SmartUwExtension extends NeedsCheck {
  name?: string | null
  sum_insured?: number | null
  premium?: number | null
  text?: string | null
}

/** A miscellaneous / specified item line (named property with its own value). */
export interface SmartUwSpecifiedItem extends NeedsCheck {
  name?: string | null
  sum_insured?: number | null
  rate?: number | null
  premium?: number | null
}

/** An excess / deductible clause. */
export interface SmartUwExcess extends NeedsCheck {
  text?: string | null
  min_percent?: number | null
  min_amount?: number | null
}

/**
 * A line the reader would NOT guess a bucket for.
 *
 * Deliberately not a coverage line, an extension, an item or an excess: a
 * wrong Excess or Coverage changes the premium and nothing downstream would
 * flag it, so an unreadable line waits here until an underwriter places it on
 * the review screen. `section` is set only on the top-level bucket, for a line
 * that sat under no section at all.
 */
export interface SmartUwUnclassified {
  text?: string | null
  section?: string | null
  sum_insured?: number | null
  rate?: number | null
  premium?: number | null
  reason?: string | null
  /**
   * The bucket this line was un-placed FROM, when it got here by an
   * underwriter sending a placed line back for classification. Held as a
   * plain string to keep this module free of a cycle with smartUwClassify.
   *
   * Without it, un-placing an excess and then placing it as a Coverage passed
   * the excess's "10% / min P10,000" straight into the coverage's rate and
   * premium slots, because the cross-shape guard had nothing to compare
   * against. A wrong figure in Coverage or Excess changes the premium.
   */
  _from?: string | null
}

export interface SmartUwCoverage {
  section?: string | null
  coverage_hint?: string | null
  section_premium?: number | null
  details?: SmartUwDetail[] | null
  // The three child buckets a Graphite coverage carries besides its detail
  // rows. The extractor emits them per section (see
  // PhpScheduleExtractor::targetSchema); older extractions have none of these
  // keys, which is why every consumer treats them as optional.
  extensions?: SmartUwExtension[] | null
  specified_items?: SmartUwSpecifiedItem[] | null
  excesses?: SmartUwExcess[] | null
  /** lines of THIS section the reader refused to guess a bucket for */
  unclassified?: SmartUwUnclassified[] | null
  notes?: string | null
}

export interface SmartUwMotor {
  registration?: string | null
  make_model?: string | null
  year?: number | null
  sum_insured?: number | null
  rate?: number | null
  premium?: number | null
}

export interface SmartUwRisk {
  policy?: Record<string, any> | null
  customer?: Record<string, any> | null
  risk_location?: Record<string, any> | null
  coverages?: SmartUwCoverage[] | null
  motor?: SmartUwMotor[] | null
  /** lines that belong to no section at all — same "never guessed" rule */
  unclassified?: SmartUwUnclassified[] | null
  notes?: string | null
  /** set by the job when the reader could not map the schedule at all; the
   *  segment is still handed over so a person can classify it by hand */
  _unmapped?: string | null
  /** "please check" / "needs classification" lines, as the backend counted them */
  _exceptions?: string[] | null
  _errors?: string[] | null
  /**
   * LABELS of the lines and columns the reader withheld from the AI provider
   * before sending — never the values. Shown to the underwriter as a neutral
   * notice, and deliberately not counted by exceptionCount(): a withheld
   * column is not a line anybody can place, and counting it would leave the
   * segment permanently un-confirmable.
   */
  _redacted_lines?: string[] | null
}

/** Coverage master row as /lookups/products/{id}/coverages returns it. */
export interface CoverageMasterLite {
  id: number
  s_CoverageName?: string | null
  s_CoverageCode?: string | null
}

/**
 * coverage_hint (engine vocabulary) -> candidate s_CoverageCode values.
 *
 * The hints come from schema.py::COVERAGE_HINTS; the codes are Graphite's own,
 * as used by EditPolicyExport::$requiredCoverages and the singleton-family
 * lists in PolicyCreatePage. Several hints legitimately map to more than one
 * code because the code differs by product (commercial vs domestic motor) or
 * because the column is spelled inconsistently in the master table
 * (BUSINESSINTERUPTION carries the historic single-R spelling).
 *
 * A hint absent from this map, or 'other', falls through to the name match
 * below — which is the normal path for sections the engine could not bucket,
 * e.g. "WORKMENS COMPENSATIONS" (workers comp is not in COVERAGE_HINTS at all).
 */
const HINT_CODES: Record<string, string[]> = {
  fire: ['FIRE'],
  buildings_combined: ['BUILDINGSCOMBINED'],
  office_contents: ['OFFICECONTENTS'],
  business_interruption: ['BUSINESSINTERUPTION', 'BUSINESSINTERRUPTION'],
  public_liability: ['PUBLICLIABILITY'],
  products_liability: ['PRODUCTSLIABILITY'],
  goods_in_transit: ['GOODSINTRANSIT'],
  motor: ['COMMERCIALMOTOR', 'DOMESTICMOTOR', 'PERSONALMOTOR', 'MOTOR'],
  fidelity_guarantee: ['FIDELITYGUARANTEE'],
  money: ['MONEY'],
  glass: ['GLASS'],
  theft: ['THEFT'],
  machinery_breakdown: ['MACHINERYBREAKDOWN'],
  electronic_equipment: ['ELECTRONICEQUIPMENT'],
  all_risks: ['BUSINESSALLRISKS', 'PERSONALALLRISKS'],
  plant_all_risks: ['PLANTALLRISKS'],
  contractors_all_risks: ['CONTRACTORSALLRISKS'],
  marine: ['MARINEOPENCOVER', 'MARINEONCEOFFCOVER', 'MARINECARGOOPEN', 'MARINECARGOONCEOFF'],
  group_personal_accident: ['GROUPPERSONALACCIDENT', 'PERSONALACCIDENTDOM'],
  employers_liability: ['EMPLOYERSLIABILITY'],
  professional_indemnity: ['PROFESSIONALINDEMNITY'],
  loss_of_rent: ['LOSSOFRENT'],
}

/** Motor coverage codes — these carry a VEHICLE schedule, not detail lines. */
// DOMMOTOR is the code the backend actually keys domestic motor on — it is in
// $noDetailCodes / $skipDetailUpserts (PolicyCreateController), so a coverage
// with this code accepts NO detail rows. Its absence here meant Smart Upload
// treated domestic motor as an ordinary coverage: the figures were seeded into
// the sub-coverage grid, the operator pressed Add, and the backend discarded
// every row without a word. DOMESTICMOTOR / MOTORDOMTP are kept because the
// controller recognises them elsewhere; this list is a superset on purpose.
const MOTOR_CODES = [
  'COMMERCIALMOTOR', 'DOMMOTOR', 'DOMESTICMOTOR', 'PERSONALMOTOR', 'MOTOR', 'MOTORDOMTP',
]

const code = (s: any) => String(s ?? '').toUpperCase().replace(/[^A-Z0-9]/g, '')

/**
 * Word tokens, compared on their first 4 characters.
 *
 * Schedules and the coverage master disagree on word FORM far more often than
 * on wording: "ESTIMATED CARRY PER ANNUM" against a master row reading
 * "Estimated Annual Carryings", "WORKMENS COMPENSATIONS" against "Workers
 * Compensation". Exact token equality scores those at zero; a 4-char prefix
 * treats ANNUM/ANNUAL and WORKMENS/WORKERS as the same word, which is what a
 * human reading the two lines does.
 */
const STOP = new Set(['THE', 'AND', 'PER', 'OF', 'FOR', 'ON', 'IN', 'TO', 'A', 'AN', 'ANY'])

function tokens(s: any): string[] {
  const words = String(s ?? '').toUpperCase().split(/[^A-Z0-9]+/).filter(Boolean)
  const out = new Set<string>()
  words.forEach((w) => {
    if (STOP.has(w) || w.length < 2) return
    out.add(w.slice(0, 4))
  })
  return [...out]
}

/**
 * 0..1 similarity between two labels. 1 = identical once normalised.
 *
 * Symmetric on purpose: scoring only the shorter side would let a one-word
 * master row ("Limit") swallow every line that happens to contain it.
 */
export function similarity(a: any, b: any): number {
  const ca = code(a)
  const cb = code(b)
  if (!ca || !cb) return 0
  if (ca === cb) return 1
  const ta = tokens(a)
  const tb = tokens(b)
  if (!ta.length || !tb.length) return 0
  const hits = ta.filter((t) => tb.includes(t)).length
  const overlap = hits / Math.max(ta.length, tb.length)
  // A clean containment ("Limit per load" inside "LOAD LIMIT PER LOAD") is a
  // strong signal the token ratio alone under-rates.
  const contains = ca.includes(cb) || cb.includes(ca)
  return contains ? Math.max(overlap, 0.75) : overlap
}

/**
 * Legal-form words that say nothing about WHO a company is.
 * "Diesel Heads Mechanics (Pty) Ltd" and "DIESEL HEADS MECHANICS PTY LTD" are
 * the same insured; a comparison that keeps these calls them different.
 */
// Legal form and grammar only. NOT noise: HOLDINGS and GROUP — they identify a
// company ("Kalahari Holdings" is not "Kalahari Transport"), and stripping them
// collapsed the first into a subset of the second, matching two unrelated firms.
const ENTITY_NOISE = [
  'PTY', 'PROPRIETARY', 'LTD', 'LIMITED', 'LIMITEE', 'INC', 'INCORPORATED',
  'CC', 'CLOSECORPORATION', 'TA', 'TRADINGAS', 'THE', 'AND',
]

/** An entity name reduced to the words that identify it, de-duplicated. */
export function identifyingWords(value: any): string[] {
  const words = String(value ?? '')
    // Fold accents first: "Sefalana Café" must still be "Sefalana Cafe".
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toUpperCase()
    .replace(/&/g, ' AND ')
    .replace(/[^A-Z0-9]+/g, ' ')
    .split(' ')
    .filter(Boolean)
    .filter((w) => !ENTITY_NOISE.includes(w))

  return Array.from(new Set(words))
}

/** An entity name reduced to the part that identifies it. */
export function normaliseEntityName(value: any): string {
  return identifyingWords(value).join('')
}

/**
 * Is the schedule's INSURED the same party as the policy holder?
 *
 * The insured on a broker schedule IS the policy name — the company on an
 * Organisation policy. Applying a schedule whose insured is someone else files
 * one client's cover under another client's policy, and nothing downstream
 * catches it: the coverages, sums insured and premiums are all perfectly valid,
 * just on the wrong policy. So the names are compared before anything is
 * written.
 *
 * Forgiving about FORM, strict about IDENTITY — and the comparison runs on the
 * identifying words ONLY. An earlier version fell back to a similarity score
 * over the RAW names, where "PTY" and "LTD" counted as agreement and
 * "Alpha (Pty) Ltd" matched "Beta (Pty) Ltd". Two unrelated companies, one
 * policy. Only a genuinely blank name means "nothing to compare"; a name made
 * entirely of legal-form words is still a name.
 *
 * Mirrors sameEntity() in SmartUploadController — keep the two in step.
 */
export function insuredMatchesPolicy(insured: any, policyHolder: any): boolean {
  const rawA = String(insured ?? '').trim()
  const rawB = String(policyHolder ?? '').trim()
  if (!rawA || !rawB) return true

  const wa = identifyingWords(rawA)
  const wb = identifyingWords(rawB)

  if (!wa.length || !wb.length) {
    const compact = (v: string) => v.toUpperCase().replace(/[^A-Z0-9]/g, '')

    return compact(rawA) === compact(rawB)
  }

  // Whole-word containment only — a substring test accepted "Smith" as
  // "Smithson Enterprises".
  const subset = (x: string[], y: string[]) => x.every((w) => y.includes(w))
  if (subset(wa, wb) || subset(wb, wa)) return true

  const hits = wa.filter((w) => wb.includes(w)).length

  return hits / Math.max(wa.length, wb.length) >= MATCH_FLOOR
}

/** Below this, a label pair is not the same thing. Tuned against real
 *  schedules: it accepts ANNUM/ANNUAL-style wording drift and rejects
 *  merely-related sections (Public vs Products Liability score 0.5). */
export const MATCH_FLOOR = 0.6

/**
 * Resolve an extracted section to one of the product's own coverages.
 *
 * Code match first (exact, from the hint), then the section title against the
 * master's name and code. Returns null rather than a weak guess — the panel
 * shows those as "pick the coverage yourself", which is a far better outcome
 * than P4.5m landing under the wrong section.
 */
export function matchCoverageMaster(
  available: CoverageMasterLite[],
  section: any,
  hint: any
): CoverageMasterLite | null {
  if (!Array.isArray(available) || available.length === 0) return null

  const wanted = HINT_CODES[String(hint ?? '').toLowerCase()] ?? []
  for (const c of wanted) {
    const hit = available.find((a) => code(a.s_CoverageCode) === code(c))
    if (hit) return hit
  }

  let best: CoverageMasterLite | null = null
  let bestScore = 0
  available.forEach((a) => {
    const s = Math.max(similarity(section, a.s_CoverageName), similarity(section, a.s_CoverageCode))
    if (s > bestScore) { bestScore = s; best = a }
  })
  return bestScore >= MATCH_FLOOR ? best : null
}

export function isMotorCoverageCode(c: any): boolean {
  return MOTOR_CODES.includes(code(c))
}

/**
 * The rate the wizard needs, as a PERCENT string.
 *
 * StepCoverages computes premium as `sum × rate / 100`, so the field is a
 * percentage — but a broker schedule writes the same rate as a decimal factor
 * (Diesel Heads: SI 4,500,000 × 0.002 = P9,000). Feeding 0.002 straight in
 * would price that section at P90 instead of P9,000.
 *
 * So derive the rate from the two figures that are unambiguous — premium and
 * sum insured — whenever both are present. That is exactly the wizard's own
 * formula rearranged, so the premium it recomputes lands back on the
 * schedule's own number to the cent. The extracted rate is only used when one
 * of them is missing, and then only as-is: guessing at its scale is how you
 * silently misprice a section.
 */
export function ratePercent(
  sumInsured: number | null | undefined,
  premium: number | null | undefined,
  extractedRate: number | null | undefined
): string {
  const si = Number(sumInsured ?? 0)
  const prem = Number(premium ?? 0)
  if (si > 0 && prem > 0) {
    const pct = (prem / si) * 100
    return String(Number(pct.toFixed(6)))
  }
  if (extractedRate === null || extractedRate === undefined) return ''
  const r = Number(extractedRate)
  return isNaN(r) ? '' : String(r)
}

/** Numbers go into the form as plain strings — no thousands separators, which
 *  the form's own parseFloat path would have to strip again. */
export function numStr(v: number | null | undefined): string {
  if (v === null || v === undefined) return ''
  const n = Number(v)
  return isNaN(n) ? '' : String(n)
}

export interface LineAssignment {
  /** index into the subcoverage rows */
  row: number
  line: SmartUwDetail
  score: number
}

export interface LineMatchResult {
  assignments: LineAssignment[]
  /** lines that matched nothing well enough — surfaced, never dropped */
  unmatched: SmartUwDetail[]
}

/**
 * Pair extracted detail lines with the coverage's subcoverage template rows.
 *
 * Greedy best-first over every (line, row) pair: the strongest pair is taken,
 * both sides are then spent, repeat. That beats per-line best-match, which
 * lets an early line claim a row a later line matches better.
 *
 * A line with no sum insured AND no premium carries no money (an excess note,
 * a "third party" heading) and is not assigned — it would blank a template row.
 */
export function matchDetailLines(
  lines: SmartUwDetail[],
  rows: Array<{ s_ScreenName?: string | null }>
): LineMatchResult {
  const money = (d: SmartUwDetail) => Number(d.sum_insured ?? 0) > 0 || Number(d.premium ?? 0) > 0
  const candidates = (lines || []).map((l, i) => ({ l, i })).filter(({ l }) => money(l))

  const pairs: LineAssignment[] = []
  candidates.forEach(({ l }) => {
    (rows || []).forEach((r, ri) => {
      const score = similarity(l.description, r.s_ScreenName)
      if (score >= MATCH_FLOOR) pairs.push({ row: ri, line: l, score })
    })
  })
  pairs.sort((a, b) => b.score - a.score)

  const takenRows = new Set<number>()
  const takenLines = new Set<SmartUwDetail>()
  const assignments: LineAssignment[] = []
  pairs.forEach((p) => {
    if (takenRows.has(p.row) || takenLines.has(p.line)) return
    takenRows.add(p.row)
    takenLines.add(p.line)
    assignments.push(p)
  })

  return {
    assignments,
    unmatched: candidates.map(({ l }) => l).filter((l) => !takenLines.has(l)),
  }
}


/**
 * Read an excess clause written as free text.
 *
 * Schedules write these as one string — "Excess HCV: 10% of the claim min
 * P10,000.00", "EXCESS ON TRUCKS 10% MIN 10000" — while policy_coverage_excess
 * keeps the percentage and the minimum amount as their own columns. Pulling
 * them apart here means the excess arrives as data rather than as a sentence
 * nobody can rate off.
 *
 * Anything it cannot parse still comes back with the full text, so the clause
 * is never lost — only its numbers are left blank.
 */
export function parseExcess(text: any): SmartUwExcess {
  const raw = String(text ?? '').trim()
  if (!raw) return { text: '' }

  const pct = raw.match(/(\d+(?:\.\d+)?)\s*%/)
  // The minimum: a number following "min", tolerating "P", spaces and commas.
  const min = raw.match(/min[^0-9]{0,12}([0-9][0-9,\s]*(?:\.\d+)?)/i)
  const toNum = (v: string | undefined) => {
    if (!v) return null
    const n = parseFloat(v.replace(/[,\s]/g, ''))
    return isNaN(n) ? null : n
  }
  return {
    text: raw,
    min_percent: toNum(pct?.[1]),
    min_amount: toNum(min?.[1]),
  }
}

/** True when a line reads as an excess/deductible clause rather than a cover
 *  line, so it can be routed to the excesses bucket instead of a detail row. */
export function looksLikeExcess(text: any): boolean {
  return /\bexcess\b|\bdeductible\b/i.test(String(text ?? ''))
}
