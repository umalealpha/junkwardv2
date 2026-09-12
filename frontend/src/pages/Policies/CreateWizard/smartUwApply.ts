/**
 * Smart Underwriting — apply an extracted schedule to a policy for real.
 *
 * The panel's "Load into form" fills the form and stops there. This module is
 * the other half the operators asked for: it ADDS or EDITS the risk address and
 * the coverage sections on the policy, through the EXACT same endpoints the
 * DOM/COM wizard uses when a human types them —
 *   POST   /policies/{id}/risk-addresses      (addRiskAddress)
 *   POST   /policies/{id}/coverages           (addCoverage)
 *   PUT    /policies/{id}/coverages/{covId}   (updateCoverage)
 * — with the same payload shape, so every backend validator, singleton-family
 * rule, action scoping and endorse pro-rata stamp behaves identically. There is
 * no new write path and no direct table access.
 *
 * Add vs edit is decided the way the wizard decides it: a coverage already on
 * THIS transaction for THIS risk address is a PUT against its policy_coverages
 * id; anything else is a POST. So re-running an apply updates in place instead
 * of stacking a duplicate section.
 */
import apiClient from '../../../api/client'
import { addRiskAddress, addCoverage, updateCoverage } from '../../../api/policyCreate'
import {
  matchCoverageMaster, matchDetailLines, isMotorCoverageCode, ratePercent,
  similarity, parseExcess, looksLikeExcess, MATCH_FLOOR,
  type SmartUwRisk, type SmartUwCoverage, type CoverageMasterLite,
} from './smartUwPrefill'

/** Everything the apply needs from the wizard's own state. */
export interface ApplyContext {
  policyId: number
  termId: number
  actionId: number
  productId: number | null
  availableCoverages: CoverageMasterLite[]
  savedAddresses: Array<{
    id: number
    address_name?: string
    physical_address?: string
    risk_state?: number | null
    risk_city?: number | null
    const_type?: string
  }>
  savedCoverages: Array<{
    _dbId?: number | null
    coverage_id?: number | null
    risk_address_id?: number | null
  }>
  /** the risk-address form as it stands — supplies the three fields a broker
   *  schedule never carries (state, city, construction type) when a NEW
   *  address has to be created */
  riskForm: {
    address_name?: string
    physical_address?: string
    occupation?: string
    risk_state?: number | null
    risk_city?: number | null
    const_type?: string
  }
}

export type ApplyStatus = 'added' | 'updated' | 'skipped' | 'failed'

export interface ApplyOutcome {
  section: string
  status: ApplyStatus
  message?: string
  /** extracted lines that matched no template row — left for the operator */
  unmatched?: string[]
}

export interface ApplyResult {
  riskAddressId: number | null
  riskAddress: ApplyOutcome | null
  coverages: ApplyOutcome[]
  /** step 1 of the vehicle flow — the fleet register. Attaching a vehicle to a
   *  motor coverage (step 2) stays a per-vehicle click, so it is not here. */
  vehicles?: VehicleOutcome[]
}

const norm = (s: any) => String(s ?? '').toUpperCase().replace(/[^A-Z0-9]/g, '')
const isMainGroupRow = (s: any) =>
  String(s?.s_CoverageGroupName ?? '').trim().toLowerCase() === 'main'

function errText(e: any, fallback: string): string {
  const d = e?.response?.data
  if (d?.errors) {
    const first = Object.values(d.errors)[0]
    return Array.isArray(first) ? String(first[0]) : String(first)
  }
  return d?.message || d?.error || e?.message || fallback
}

/**
 * The risk address every extracted coverage will hang off.
 *
 * Reuse before create, in this order: an existing address that looks like the
 * schedule's, then the transaction's only address, then a new one. Creating is
 * last because a schedule carries no state / city / construction type and the
 * backend requires all three (PolicyCreateController::addRiskAddress) — so a
 * new address can only be built when the operator has already picked those in
 * section 4. That is not a limitation of this code: the same three fields block
 * a manual Add for exactly the same reason.
 */
export async function resolveRiskAddress(
  ctx: ApplyContext,
  name: string,
  physicalAddress: string,
  occupation: string
): Promise<{ id: number | null; outcome: ApplyOutcome }> {
  const wantName = norm(name)
  const wantAddr = norm(physicalAddress)

  const match = ctx.savedAddresses.find((a) => {
    const an = norm(a.address_name)
    const ap = norm(a.physical_address)
    return (!!wantName && an === wantName) || (!!wantAddr && ap === wantAddr)
  })
  if (match) {
    return {
      id: match.id,
      outcome: { section: 'Risk address', status: 'skipped',
        message: `Reused "${match.address_name || match.physical_address}" — already on this transaction.` },
    }
  }

  if (ctx.savedAddresses.length === 1) {
    const only = ctx.savedAddresses[0]
    return {
      id: only.id,
      outcome: { section: 'Risk address', status: 'skipped',
        message: `Used the transaction's existing address "${only.address_name || only.physical_address}". `
          + 'The schedule\'s address was not added as a second site.' },
    }
  }

  const state = ctx.riskForm.risk_state
  const city = ctx.riskForm.risk_city
  const constType = ctx.riskForm.const_type
  if (!state || !city || !constType) {
    return {
      id: null,
      outcome: { section: 'Risk address', status: 'failed',
        message: 'No risk address to attach the coverages to. A schedule carries no state, '
          + 'city or construction type and all three are required, so fill them in section 4 '
          + '(the address name and physical address are already loaded) and apply again.' },
    }
  }

  try {
    const res = await addRiskAddress(ctx.policyId, {
      term_id: ctx.termId,
      action_id: ctx.actionId,
      address_name: name || ctx.riskForm.address_name || '',
      physical_address: physicalAddress || ctx.riskForm.physical_address || '',
      occupation: occupation || ctx.riskForm.occupation || '',
      risk_state: state,
      risk_city: city,
      const_type: constType,
    } as any)
    const id = Number(res?.data?.id ?? res?.id ?? 0) || null
    return {
      id,
      outcome: { section: 'Risk address', status: 'added',
        message: `Added "${name || physicalAddress}".` },
    }
  } catch (e: any) {
    return {
      id: null,
      outcome: { section: 'Risk address', status: 'failed',
        message: errText(e, 'Could not add the risk address.') },
    }
  }
}

/** Extensions, misc items and excesses built from one extracted section. */
export interface SectionChildren {
  extensions: any[]
  specified_items: any[]
  excesses: any[]
  /** extension names this coverage does not offer — reported, never invented */
  unplacedExts: string[]
}

/**
 * The child rows of a coverage: extensions, specified / miscellaneous items
 * and excesses.
 *
 * Shared by Apply (which POSTs them) and "Load into form" (which seeds the
 * form's ext / misc / excess grids with them). It used to live inside the
 * apply, so loading a section dropped every extension and misc item on the
 * floor and the operator retyped them — the one part of the extraction that
 * is slowest to enter by hand.
 *
 * @param excessLines cover lines that read as an excess ("EXCESS ON TRUCKS
 *                    10% MIN 10000"), already filtered out of the detail pool
 *                    by the caller.
 */
export async function buildSectionChildren(
  section: SmartUwCoverage,
  master: CoverageMasterLite,
  excessLines: Array<{ description?: string | null }> = []
): Promise<SectionChildren> {
  // ── Excesses (policy_coverage_excess) ─────────────────────────────────
  // Both the extractor's own excesses[] and any cover line that reads as one.
  const excesses = [
    ...(Array.isArray(section.excesses) ? section.excesses : []).map((e) =>
      (e.min_percent !== null && e.min_percent !== undefined)
        || (e.min_amount !== null && e.min_amount !== undefined)
        ? e
        : parseExcess(e.text)
    ),
    ...excessLines.map((d) => parseExcess(d.description)),
  ]
    .filter((e) => String(e.text ?? '').trim() !== '')
    .map((e) => ({
      excesses: String(e.text ?? ''),
      min_percent: e.min_percent ?? undefined,
      min_amt: e.min_amount ?? undefined,
    }))

  // ── Specified / miscellaneous items ───────────────────────────────────
  // specified_coverage_id is nullable, so a named item still saves when the
  // coverage has no master entry for it — the name and the figures are what
  // the operator needs to see, and a wrong id would be worse than none.
  const rawItems = Array.isArray(section.specified_items) ? section.specified_items : []
  let specified_items: any[] = []
  if (rawItems.length > 0) {
    let siOptions: any[] = []
    try {
      const r = await apiClient.get(`/lookups/coverages/${master.id}/specified-items`)
      siOptions = r.data?.data ?? []
    } catch {
      siOptions = []
    }
    specified_items = rawItems
      .filter((it) => String(it.name ?? '').trim() !== '')
      .map((it) => {
        let best: any = null
        let bestScore = 0
        siOptions.forEach((o) => {
          const sc = similarity(it.name, o.name)
          if (sc > bestScore) { bestScore = sc; best = o }
        })
        const si = Number(it.sum_insured ?? 0)
        const prem = Number(it.premium ?? 0)
        return {
          specified_coverage_id: bestScore >= MATCH_FLOOR && best ? best.id : null,
          name: String(it.name),
          sum_insured: si || 0,
          rate: Number(ratePercent(si, prem, it.rate ?? null)) || 0,
          calculated_value: prem || 0,
        }
      })
  }

  // ── Extensions ────────────────────────────────────────────────────────
  // An extension row is meaningless without its master id (extentions_id is
  // the FK the backend writes), so an extension the coverage does not offer is
  // REPORTED as unplaced rather than invented.
  const rawExts = Array.isArray(section.extensions) ? section.extensions : []
  const extensions: any[] = []
  const unplacedExts: string[] = []
  if (rawExts.length > 0) {
    let extMasters: any[] = []
    try {
      const r = await apiClient.get(`/lookups/coverages/${master.id}/extensions`)
      extMasters = r.data?.data ?? []
    } catch {
      extMasters = []
    }
    rawExts.forEach((ex) => {
      const name = String(ex.name ?? '').trim()
      if (!name) return
      let best: any = null
      let bestScore = 0
      extMasters.forEach((m) => {
        const sc = similarity(name, m.s_ScreenName || m.s_CoverageCode)
        if (sc > bestScore) { bestScore = sc; best = m }
      })
      if (!best || bestScore < MATCH_FLOOR) {
        unplacedExts.push(name)
        return
      }
      const si = Number(ex.sum_insured ?? 0)
      const prem = Number(ex.premium ?? 0)
      // A name match alone is not an extension. The backend accepts a row on
      // s_ScreenName alone (PolicyCreateController's $hasValue), and the
      // wizard's own filter (filledExts) does not — so pushing one here wrote
      // P0.00 rows an underwriter typing the same schedule would never create,
      // and stamped each of them into the endorse pro-rata gate.
      if (!si && !prem && !String(ex.text ?? '').trim()) {
        unplacedExts.push(name)

        return
      }
      extensions.push({
        extentions_id: best.id,
        s_ScreenName: best.s_ScreenName || name,
        type: best.type || 'Extention',
        extention_type: best.extention_type || 'NUMBER',
        extention_coverage_value: si || undefined,
        extention_calculated_value: prem || undefined,
        // A wording-only extension (a warranty, a memorandum) carries text
        // rather than a value; keep it so the clause survives the import.
        extention_text_value: ex.text ? String(ex.text) : undefined,
      })
    })
  }

  return { excesses, specified_items, extensions, unplacedExts }
}

/**
 * The same child rows, in the shape the FORM reads.
 *
 * Two shapes exist here on purpose and must not be confused:
 *
 *   ADD  — what Apply POSTs. Numbers, and blanks left out entirely, which is
 *          what PolicyCreateController writes to policy_coverage_extension /
 *          specified_coverage_items / policy_coverage_excess.
 *   READ — what StepCoverages puts in its grids. ExtensionEntry,
 *          SpecifiedItemEntry and ExcessEntry are typed all-strings, and the
 *          extension grid merges a saved row onto the master template with
 *          `{...master, ...saved}` keyed on extentions_id.
 *
 * Feeding the ADD shape into the form breaks both halves of that merge: an
 * `undefined` overwrites the master's default with nothing, and a number lands
 * in an input declared as a string. So convert once, here, instead of letting
 * the two shapes drift apart in the callers.
 */
export function toFormChildren(children: SectionChildren): {
  extensions: any[]
  specified_items: any[]
  excesses: any[]
  /** misc items the form would silently drop — reported to the operator */
  unplacedItems: string[]
} {
  // A zero or a missing figure reads as an empty box, never "0" — the same
  // thing Apply does by omitting the key.
  const str = (v: any) => (v === null || v === undefined || v === '' || Number(v) === 0 ? '' : String(v))

  return {
    // Only the keys the extraction actually knows. Everything else —
    // limits, limit type, rate, the discount triplet — stays whatever the
    // master template supplied.
    // Only the keys that carry a value. The grid merges these onto the master
    // template with {...master, ...saved}, so writing '' for a figure the
    // schedule did not state would erase the master's own predefined_value.
    extensions: children.extensions.map((e) => {
      const row: any = {
        extentions_id: e.extentions_id,
        s_ScreenName: String(e.s_ScreenName ?? ''),
        type: e.type ?? 'Extention',
        extention_type: e.extention_type ?? 'NUMBER',
      }
      const si = str(e.extention_coverage_value)
      const prem = str(e.extention_calculated_value)
      const text = e.extention_text_value ? String(e.extention_text_value) : ''
      if (si) row.extention_coverage_value = si
      if (prem) row.extention_calculated_value = prem
      if (text) row.extention_text_value = text

      return row
    }),
    // A misc row with no master pick is dropped by the grid's own save filter
    // (`filledSi` requires specified_coverage_id — SpecifiedItemEntry has no
    // free-text path). Seeding one anyway would look loaded and then vanish on
    // Add, so those are reported instead. Apply is different on purpose: it
    // POSTs straight past the form, and the column is nullable.
    specified_items: children.specified_items
      .filter((i) => !!i.specified_coverage_id)
      .map((i) => ({
        specified_coverage_id: i.specified_coverage_id,
        name: String(i.name ?? ''),
        sum_insured: str(i.sum_insured),
        rate: str(i.rate),
        calculated_value: str(i.calculated_value),
      })),
    unplacedItems: children.specified_items
      .filter((i) => !i.specified_coverage_id)
      .map((i) => String(i.name ?? ''))
      .filter(Boolean),
    excesses: children.excesses.map((x) => ({
      excesses: String(x.excesses ?? ''),
      min_percent: str(x.min_percent),
      min_amt: str(x.min_amt),
    })),
  }
}

/**
 * Rows already on the coverage, with the extracted ones merged over them.
 *
 * Keyed on the id that identifies the row to the backend. A row the extraction
 * did not mention is carried forward exactly as the server gave it to us —
 * which is what the wizard does by re-sending its whole hydrated grid, and the
 * only way to survive updateCoverage's replace-set semantics.
 */
function mergeById(existingRows: any, incoming: any[], key: string): any[] {
  const existing = Array.isArray(existingRows) ? existingRows : []
  if (existing.length === 0) {
    return incoming
  }

  const idOf = (row: any) => String(row?.[key] ?? '').trim().toUpperCase()
  const replaced = new Set(incoming.map(idOf).filter((k) => k !== ''))
  const carried = existing.filter((row) => !replaced.has(idOf(row)))

  return [...carried, ...incoming]
}

/**
 * Excesses are STORED as type='Excess' rows in policy_extention_detail, so the
 * coverage read payload returns them inside `extensions` — there is no
 * `excesses` key on it at all. That matters when carrying rows forward:
 * leaving them in the extensions set while also sending the `excesses` payload
 * supplies the same excess twice, and updateCoverage appends its conversion
 * after the carried copy, inserting a duplicate whose premium is then summed
 * into extention_calculated_value a second time.
 *
 * So the carried rows are split by type: real extensions go back as
 * `extensions`, excesses go back as `excesses` in the payload shape the
 * backend converts, and each set is supplied exactly once.
 */
function isExcessRow(row: any): boolean {
  return String(row?.type ?? '').trim().toLowerCase() === 'excess'
}

function toExcessPayload(row: any): any {
  return {
    // Same conversion the wizard's own hydrator uses (StepCoverages), and it
    // must stay that way: `||` not `??`, s_ScreenName first.
    //
    // The read payload coerces the value — 'extention_text_value' => $e->…
    // ?? '' — so the field is '' rather than null on a legacy row, or one saved
    // with figures but no wording. `??` does not fall through on '', so the
    // wording came back empty, updateCoverage rebuilt the row as
    // s_ScreenName => 'Excess', the smart-sync custom_name match then failed,
    // and the original row was soft-deleted and re-inserted under a new name.
    // On an ENDORSE that is a refund plus a re-charge for the same excess.
    excesses: row?.s_ScreenName || row?.custom_name || row?.extention_text_value || '',
    min_percent: row?.extention_excess_min_value ?? 0,
    min_amt: row?.extention_excess_max_value ?? 0,
    discount_surcharge: row?.extention_discount_surcharge ?? '',
    discount_surcharge_type: row?.extention_discount_surcharge_type ?? '',
    discount_surcharge_value: row?.extention_discount_surcharge_value ?? 0,
    premium: row?.extention_calculated_value ?? 0,
  }
}

/**
 * Add or edit ONE extracted section on the policy.
 *
 * The subcoverage template is fetched here for the same reason StepCoverages
 * fetches it: the detail rows' identities (sub_coverage_id) and names live in
 * the master table, and a schedule line can only be placed once they are known.
 */
export async function applySection(
  ctx: ApplyContext,
  section: SmartUwCoverage,
  riskAddressId: number
): Promise<ApplyOutcome> {
  const title = String(section.section || 'section')
  const master = matchCoverageMaster(ctx.availableCoverages, section.section, section.coverage_hint)
  if (!master) {
    return { section: title, status: 'skipped',
      message: 'No matching coverage on this product — add it by hand.' }
  }

  const isDomCom = ctx.productId === 7 || ctx.productId === 8
  const isMotor = isMotorCoverageCode(master.s_CoverageCode)

  // A motor section's money is per-vehicle, not per-coverage: writing its
  // schedule total onto the coverage would double-count against the vehicle
  // rows the Vehicles step creates. So it is reported, never written.
  if (isMotor) {
    return { section: title, status: 'skipped',
      message: 'Motor premium lives on the vehicle rows — add the vehicles in the '
        + 'Vehicles step, then Rate.' }
  }

  const lines = Array.isArray(section.details) ? section.details : []

  // A schedule writes an excess as a cover line ("EXCESS ON TRUCKS 10% MIN
  // 10000") because it has nowhere else to put it. On the policy it is a
  // policy_coverage_excess row, not a detail row, so route it out of the
  // detail pool before matching — otherwise it either matches nothing and
  // looks lost, or worse, matches a real row by a stray word.
  const excessLines = lines.filter((d) => looksLikeExcess(d.description))
  const coverLines = lines.filter((d) => !looksLikeExcess(d.description))

  let rows: any[] = []
  try {
    const r = await apiClient.get(`/lookups/coverages/${master.id}/subcoverages`)
    rows = r.data?.data ?? []
  } catch {
    rows = []
  }
  // COM/DOM never carry the legacy "Main" group row — same filter the wizard
  // applies, so this cannot introduce the row the DomCom quote must not show.
  if (isDomCom) rows = rows.filter((row) => !isMainGroupRow(row))

  const { assignments, unmatched } = matchDetailLines(coverLines, rows)
  const details = assignments.map(({ row, line }) => {
    const si = Number(line.sum_insured ?? 0)
    const prem = Number(line.premium ?? 0)
    return {
      description: rows[row].s_ScreenName,
      // The MASTER coverage id of the subcoverage row, which is what
      // handleSaveCoverage sends and the backend stores on
      // policy_coverage_detail.
      coverage_id: rows[row].id,
      coverage_value: si,
      // Percent — the wizard and the backend both price a row as
      // sum x rate / 100, while the schedule writes a decimal factor.
      rate: Number(ratePercent(si, prem, line.rate ?? null)) || 0,
      calculated_value: prem,
      // Endorse pro-rata must rate these: they are genuinely new values.
      changed: true,
    }
  })

  const { excesses, specified_items, extensions, unplacedExts } =
    await buildSectionChildren(section, master, excessLines)

  // Descriptive lines that carry no money and are not excesses — "Third
  // party", "Transporting groceries (Bokomo Agent)". They are real
  // underwriting information, so they go into the coverage note rather than
  // being dropped on the floor.
  const descriptive = coverLines
    .filter((d) => !Number(d.sum_insured ?? 0) && !Number(d.premium ?? 0))
    .map((d) => String(d.description ?? '').trim())
    .filter(Boolean)

  const money = coverLines.filter(
    (d) => Number(d.sum_insured ?? 0) > 0 || Number(d.premium ?? 0) > 0
  )
  const siTotal = money.reduce((a, d) => a + Number(d.sum_insured || 0), 0)
  const premTotal = section.section_premium !== null && section.section_premium !== undefined
    ? Number(section.section_premium)
    : money.reduce((a, d) => a + Number(d.premium || 0), 0)

  // Lines the reader would not guess a bucket for are NOT applied — nothing
  // is written on a guess. They are named in the outcome instead, so an
  // underwriter who applied without classifying them is told which lines are
  // still missing from the section rather than discovering it at Rate.
  const stillUnclassified = (Array.isArray(section.unclassified) ? section.unclassified : [])
    .map((u) => String(u?.text || '').trim())
    .filter(Boolean)
    .map((t) => t + ' (needs classification)')

  const leftovers = [
    ...unmatched.map((u) => String(u.description || '')),
    ...unplacedExts,
    ...stillUnclassified,
  ].filter(Boolean)

  // Nothing placed anywhere and no coverage-level figure to fall back on —
  // writing an empty coverage would just add a P0.00 section to the schedule.
  const nothingToWrite = details.length === 0 && excesses.length === 0
    && specified_items.length === 0 && extensions.length === 0
  if (nothingToWrite && (isDomCom || siTotal === 0)) {
    return {
      section: title,
      status: 'skipped',
      message: rows.length === 0
        ? 'This coverage has no subcoverage template to place the schedule lines in.'
        : 'None of the schedule lines matched a row on this coverage — enter them by hand.',
      unmatched: leftovers,
    }
  }

  const notes = [
    `Smart UW: ${title}`,
    ...descriptive,
    section.notes ? String(section.notes) : '',
  ].filter(Boolean).join(' · ')

  const payload: any = {
    risk_address_id: riskAddressId,
    action_id: ctx.actionId,
    term_id: ctx.termId,
    coverage_id: master.id,
    // COM/DOM keep every figure in the detail rows — the coverage-level trio
    // is not even rendered for products 7/8, so writing it would create a
    // value the operator can neither see nor correct.
    coverage_value: isDomCom ? 0 : siTotal,
    rate: isDomCom ? undefined : Number(ratePercent(siTotal, premTotal, null)) || undefined,
    calculated_value: isDomCom ? undefined : premTotal || undefined,
    details,
    extensions: extensions.length > 0 ? extensions : undefined,
    specified_items: specified_items.length > 0 ? specified_items : undefined,
    excesses: excesses.length > 0 ? excesses : undefined,
    notes,
  }

  // Goods In Transit keeps "what property / business is being carried" in its
  // own policy_coverages column, and the schedule states it in words
  // ("Transporting groceries (Bokomo Agent)").
  if (norm(master.s_CoverageCode) === 'GOODSINTRANSIT' && descriptive.length > 0) {
    payload.property_business_being = descriptive.join('; ')
  }

  // Same add-vs-edit rule as the wizard: an existing row for this coverage on
  // this risk address and this action is a PUT, so applying twice updates in
  // place instead of stacking a second section.
  const existing = ctx.savedCoverages.find(
    (c) => Number(c.coverage_id) === Number(master.id)
      && Number(c.risk_address_id) === Number(riskAddressId)
      && !!c._dbId
  )

  if (existing) {
    // updateCoverage treats extensions / specified_items / excesses as
    // REPLACE-SETS: any active row missing from the payload is soft-deleted,
    // and on an ENDORSE it is stamped previousActionIdCov so the next Rate
    // writes a negative delta. The manual path is safe from that because it
    // re-sends the whole grid, hydrated from the database. Apply sends only
    // the rows the extractor placed — so applying a schedule onto a coverage
    // that already carried extensions or misc items deleted the rest and
    // refunded them. Merge over what is there instead: the extraction wins on
    // a row it matched, everything else is carried forward untouched.
    // The merge has to run even when the extraction placed NO extension of
    // its own. updateCoverage folds the `excesses` payload into
    // $validated['extensions'], which CREATES that key server-side and arms
    // the replace-set: a section carrying only an excess therefore deleted
    // every real extension on the coverage and, on an ENDORSE, refunded them.
    // So whenever a set that materialises `extensions` is being sent, carry
    // the existing rows forward explicitly.
    const carriesExtensions = !!payload.extensions || !!payload.excesses
    if (carriesExtensions) {
      const existingExt: any[] = Array.isArray((existing as any).extensions)
        ? (existing as any).extensions
        : []
      // Real extensions only — the excess rows are carried through the
      // `excesses` payload below, or they would be sent twice.
      const merged = mergeById(
        existingExt.filter((row) => !isExcessRow(row)),
        payload.extensions || [],
        'extentions_id'
      )
      if (merged.length > 0) {
        payload.extensions = merged
      }
      // Carry the coverage's existing excesses too. updateCoverage treats the
      // converted excesses as part of the extensions replace-set, so an excess
      // the extraction did not mention is deleted unless it is re-sent.
      const carriedExcesses = existingExt.filter(isExcessRow).map(toExcessPayload)
      if (carriedExcesses.length > 0) {
        payload.excesses = mergeById(
          carriedExcesses, payload.excesses || [], 'excesses'
        )
      }
    }
    if (payload.specified_items) {
      payload.specified_items = mergeById(
        (existing as any).specified_items, payload.specified_items, 'specified_coverage_id'
      )
    }
    // No merge against `existing.excesses` here: the coverage read payload has
    // no such key — excesses come back inside `extensions` as type='Excess' —
    // so this was a no-op that read as protection. They are carried forward
    // from the extensions set above instead.

    // The coverage note is the underwriter's, and updateCoverage overwrites it
    // whenever the key is present. Re-applying a schedule would replace what
    // they had written with "Smart UW: <section>". On an update the note is
    // left alone; on a new coverage there is nothing to lose.
    delete payload.notes
  }

  const placed = [
    details.length ? `${details.length} detail line(s)` : '',
    extensions.length ? `${extensions.length} extension(s)` : '',
    specified_items.length ? `${specified_items.length} misc item(s)` : '',
    excesses.length ? `${excesses.length} excess(es)` : '',
  ].filter(Boolean).join(', ') || 'coverage-level values only'

  try {
    if (existing?._dbId) {
      await updateCoverage(ctx.policyId, Number(existing._dbId), payload)
      return {
        section: title,
        status: 'updated',
        message: `Updated ${master.s_CoverageName || master.s_CoverageCode} — ${placed}.`,
        unmatched: leftovers,
      }
    }
    await addCoverage(ctx.policyId, payload)
    return {
      section: title,
      status: 'added',
      message: `Added ${master.s_CoverageName || master.s_CoverageCode} — ${placed}.`,
      unmatched: leftovers,
    }
  } catch (e: any) {
    return { section: title, status: 'failed', message: errText(e, 'Save failed.') }
  }
}

/**
 * Apply the whole segment: risk address first (every coverage needs its id),
 * then the sections one at a time.
 *
 * Sequential on purpose. These writes are not independent — the backend
 * recomputes the action's totals on each one, and firing them in parallel has
 * produced interleaved recomputes before. One at a time is also what a human
 * clicking Add Coverage does.
 */
/* ────────────────────────────────────────────────────────────────────────────
 * Vehicles — the two-step flow the motor cover actually uses
 *
 * A vehicle is NOT a coverage line, and it cannot be written in one hop. The
 * legacy screen (ManageCoverages) makes this explicit and the V2 endpoints
 * mirror it:
 *
 *   1. the vehicle is registered on the policy   → POST /policies/{id}/vehicles
 *      (the `vehicle` table: per policy + term + action, the fleet register)
 *   2. THEN, only if the underwriter wants it covered, it is attached to a
 *      motor coverage                            → POST /policies/{id}/coverages/{covId}/motor
 *      (the `motor` table: keyed (policy_coverage_id, registration_no), which
 *      is what carries the sum insured, the premium and the per-vehicle
 *      extensions/excesses/specified items)
 *
 * Step 2 is a per-vehicle decision, never automatic: a schedule lists vehicles
 * the broker wants quoted, and the underwriter may decline or defer any of
 * them. That is why the panel adds the register in bulk but attaches to cover
 * one click at a time.
 *
 * This is also why the extracted vehicles previously "did not load": nothing
 * ever performed step 1, so the plates existed only in the extraction JSON —
 * the Vehicles step and the motor coverage's vehicle dropdown both read the
 * `vehicle` table and so showed an empty fleet.
 * ──────────────────────────────────────────────────────────────────────────── */

/** One extracted vehicle row, as the extractor returns it. */
export interface SmartUwMotorRow {
  registration?: string | null
  make_model?: string | null
  year?: number | null
  sum_insured?: number | null
  rate?: number | null
  premium?: number | null
}

export interface VehicleOutcome {
  /** the plate, as it will be keyed on the policy */
  plate: string
  status: ApplyStatus
  message?: string
  /** `vehicle`.id once registered — what step 2 needs */
  vehicleId?: number | null
}

/**
 * Split "Venter Trailer" into a make and a model the way addVehicle needs.
 *
 * Both are `required|string` server-side, so a one-word description cannot be
 * sent as a make with no model. First word is the make, the rest the model;
 * a single word repeats as both rather than failing validation on a vehicle
 * the schedule genuinely only named once.
 */
export function splitMakeModel(makeModel: string): { make: string; model: string } {
  const parts = String(makeModel || '').trim().split(/\s+/).filter(Boolean)
  if (parts.length === 0) return { make: 'Unknown', model: 'Unknown' }
  if (parts.length === 1) return { make: parts[0], model: parts[0] }
  return { make: parts[0], model: parts.slice(1).join(' ') }
}

/**
 * Step 1 — register the extracted vehicles on the policy.
 *
 * Idempotent by plate: a plate already on this policy+action is reported as
 * `skipped` with its existing id, so the operator can still attach it to cover
 * and a second Apply never stacks duplicates. A row the extractor could not
 * read a plate for is skipped too — the backend requires one, and inventing a
 * placeholder would put a fictional registration on a real policy.
 *
 * @param existingVehicles plates already on the transaction, from
 *        GET /policies/{id}/vehicles, so this can decide add vs skip without
 *        relying on the backend to reject a duplicate.
 */
export async function applyVehicles(
  ctx: ApplyContext,
  rows: SmartUwMotorRow[],
  riskAddressId: number | null,
  existingVehicles: Array<{ id: number; vehiclePlate?: string | null }> = []
): Promise<VehicleOutcome[]> {
  const byPlate = new Map<string, number>()
  for (const v of existingVehicles) {
    const p = norm(v.vehiclePlate)
    if (p) byPlate.set(p, v.id)
  }

  const out: VehicleOutcome[] = []
  for (const row of rows) {
    const plate = String(row.registration ?? '').trim().toUpperCase()
    const label = plate || String(row.make_model ?? '').trim() || 'vehicle'

    if (!plate) {
      out.push({
        plate: label,
        status: 'skipped',
        message: 'No registration could be read for this row — add it by hand in '
          + 'the Vehicles step.',
      })
      continue
    }

    const already = byPlate.get(norm(plate))
    if (already) {
      out.push({
        plate, status: 'skipped', vehicleId: already,
        message: 'Already on this transaction.',
      })
      continue
    }

    const { make, model } = splitMakeModel(String(row.make_model ?? ''))
    try {
      const { data } = await apiClient.post(`/policies/${ctx.policyId}/vehicles`, {
        vehiclePlate: plate,
        make,
        model,
        // addVehicle validates year as a max-4 STRING, not a number.
        year: row.year ? String(row.year) : null,
        estimated_value: row.sum_insured ?? null,
        risk_id: riskAddressId ?? null,
      })
      const id = Number(data?.id) || null
      if (id) byPlate.set(norm(plate), id)
      out.push({ plate, status: 'added', vehicleId: id })
    } catch (e: any) {
      out.push({ plate, status: 'failed', message: errText(e, 'Could not register this vehicle.') })
    }
  }
  return out
}

/**
 * Step 2 — attach one registered vehicle to a motor coverage.
 *
 * The sum insured and premium go on THIS row, not on the coverage: motor money
 * is per-vehicle, which is why applySection deliberately refuses to write a
 * motor section's total. `type_of_cover` defaults to Comprehensive to match the
 * wizard's own Add-vehicle call (StepCoverages) — the underwriter changes it on
 * the row afterwards, the same as for a hand-added vehicle.
 *
 * Rate is intentionally not sent: addMotorVehicle takes coverage_value and
 * calculated_value only, and the rate the schedule quoted is already implied by
 * the two of them. Re-Rate after attaching, as with any motor edit.
 */
export async function attachVehicleToMotor(
  ctx: ApplyContext,
  coverageId: number,
  vehicleId: number,
  row: SmartUwMotorRow
): Promise<ApplyOutcome> {
  const label = String(row.registration ?? '').trim().toUpperCase() || 'vehicle'
  try {
    await apiClient.post(`/policies/${ctx.policyId}/coverages/${coverageId}/motor`, {
      vehicle_id: vehicleId,
      type_of_cover: 'Comprehensive',
      coverage_value: row.sum_insured ?? 0,
      calculated_value: row.premium ?? 0,
    })
    return { section: label, status: 'added', message: 'Attached to motor cover — Rate to price it.' }
  } catch (e: any) {
    return { section: label, status: 'failed', message: errText(e, 'Could not attach to motor cover.') }
  }
}

/**
 * The motor coverage sections already saved on this transaction — the possible
 * targets for step 2. Empty means the underwriter has not added a motor section
 * yet; nothing here creates one, because which motor coverage a fleet belongs
 * under (and whether the policy carries one at all) is an underwriting call.
 */
export function motorCoverageTargets(
  ctx: ApplyContext
): Array<{ coverageId: number; name: string }> {
  const masterById = new Map<number, CoverageMasterLite>()
  for (const m of ctx.availableCoverages) masterById.set(m.id, m)

  const seen = new Set<number>()
  const out: Array<{ coverageId: number; name: string }> = []
  for (const c of ctx.savedCoverages) {
    const dbId = Number(c._dbId) || 0
    const master = c.coverage_id ? masterById.get(Number(c.coverage_id)) : undefined
    if (!dbId || !master || seen.has(dbId)) continue
    if (!isMotorCoverageCode(master.s_CoverageCode)) continue
    seen.add(dbId)
    out.push({ coverageId: dbId, name: String(master.s_CoverageName || 'Motor') })
  }
  return out
}

export async function applyExtraction(
  ctx: ApplyContext,
  risk: SmartUwRisk,
  address: { name: string; physicalAddress: string; occupation: string },
  existingVehicles: Array<{ id: number; vehiclePlate?: string | null }> = []
): Promise<ApplyResult> {
  const { id, outcome } = await resolveRiskAddress(
    ctx, address.name, address.physicalAddress, address.occupation
  )
  if (!id) {
    return { riskAddressId: null, riskAddress: outcome, coverages: [] }
  }

  const sections = Array.isArray(risk.coverages) ? risk.coverages : []
  const results: ApplyOutcome[] = []
  for (const s of sections) {
    results.push(await applySection(ctx, s, id))
  }

  // Step 1 of the vehicle flow runs with the Apply: registering a vehicle on
  // the policy commits the underwriter to nothing (no cover, no premium), and
  // until it happens the plates are invisible to both the Vehicles step and the
  // motor coverage's vehicle picker. Step 2 — putting a vehicle ON cover — is
  // the decision, so it stays a deliberate per-vehicle click in the panel.
  const motorRows = (Array.isArray(risk.motor) ? risk.motor : []) as SmartUwMotorRow[]
  const vehicles = motorRows.length
    ? await applyVehicles(ctx, motorRows, id, existingVehicles)
    : []

  return { riskAddressId: id, riskAddress: outcome, coverages: results, vehicles }
}
