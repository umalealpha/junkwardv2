/**
 * Smart Underwriting extraction, shown INSIDE the policy wizard.
 *
 * Arriving from Smart Upload's "Review & Issue", the extracted schedule rides
 * in location.state. The wizard used to consume only the customer/date/address
 * part of it, so an operator who had just watched an extraction succeed landed
 * on an empty Coverages section with no sign of the sections, sums insured or
 * vehicles the AI had read. This panel is that missing half.
 *
 * It loads, it does not save. Each section's button fills the coverage form
 * (coverage type + sums insured + rate + premium); the operator still clicks
 * Add Coverage, so every existing validation, singleton-family rule and
 * backend guard applies exactly as if they had typed it. Nothing here writes
 * to a policy on its own.
 */
import { fmtPula } from '../../../utils/format'
import {
  matchCoverageMaster, isMotorCoverageCode,
  type SmartUwRisk, type SmartUwCoverage, type CoverageMasterLite,
} from './smartUwPrefill'
import type { ApplyResult, ApplyStatus, ApplyOutcome } from './smartUwApply'
// The exceptions the reader deliberately left to a person: lines it would not
// guess a bucket for, and lines it placed without confidence.
import {
  exceptionCount, pendingLines, flaggedLines, BUCKET_LABEL,
} from './smartUwClassify'

interface Props {
  risk: SmartUwRisk
  segment?: string
  availableCoverages: CoverageMasterLite[]
  /** true once a risk address exists — a coverage cannot be added without one */
  hasRiskAddress: boolean
  productSelected: boolean
  onLoad: (section: SmartUwCoverage, master: CoverageMasterLite) => void
  /** section currently loaded into the form, by index, so the row can say so */
  loadedIndex: number | null
  /** extensions / misc items the loaded section named that this coverage
   *  has no master row for — the form would drop them, so they are named */
  unplacedExtensions?: string[]
  /** section whose extension / misc masters are still being fetched */
  loadingIndex?: number | null
  /** set when the schedule's insured is not this policy's holder — nothing
   *  may be written to the policy while it is */
  insuredMismatch?: { insured: string; policy: string } | null
  /** how many risk addresses this action already carries */
  savedAddressCount: number
  onLoadRiskAddress: (name: string, physicalAddress: string, occupation: string) => void
  riskAddressLoaded: boolean
  /** coverage_ids already on this transaction — a section that matches one of
   *  them is an EDIT, not an add, and saying so avoids a duplicate section */
  existingCoverageIds: number[]
  /** write the whole segment to the policy through the wizard's own endpoints */
  onApply: (name: string, physicalAddress: string, occupation: string) => void
  applying: boolean
  applyResult: ApplyResult | null

  /* ── vehicles: register first, then attach to cover ──────────────────────
   * Two steps because the policy stores them in two places — the `vehicle`
   * fleet register, and a `motor` row under a motor coverage that carries the
   * sum insured and premium. Step 2 is per-vehicle on purpose: a schedule
   * lists what the broker wants quoted, not what the underwriter accepts. */

  /** step 1 for every extracted row — POST /policies/{id}/vehicles */
  onRegisterVehicles?: () => void
  /** where each plate currently stands, keyed by UPPER-CASE registration */
  vehicleState?: Record<string, { vehicleId: number | null; onCover: boolean }>
  /** motor sections already saved on this transaction — the attach targets */
  motorTargets?: Array<{ coverageId: number; name: string }>
  motorTargetId?: number | null
  onMotorTargetChange?: (coverageId: number | null) => void
  /** step 2 for one row, by its index in risk.motor */
  onAttachToMotor?: (motorIndex: number) => void
  /** plate whose attach call is in flight */
  attachingPlate?: string | null
}

const money = (v: number | null | undefined) =>
  v === null || v === undefined ? '—' : fmtPula(Number(v) || 0)

export default function SmartUwPrefillPanel({
  risk, segment, availableCoverages, hasRiskAddress, productSelected, onLoad, loadedIndex,
  unplacedExtensions, loadingIndex, insuredMismatch,
  savedAddressCount, onLoadRiskAddress, riskAddressLoaded, existingCoverageIds,
  onApply, applying, applyResult,
  onRegisterVehicles, vehicleState, motorTargets, motorTargetId,
  onMotorTargetChange, onAttachToMotor, attachingPlate,
}: Props) {
  const coverages = Array.isArray(risk.coverages) ? risk.coverages : []
  const motor = Array.isArray(risk.motor) ? risk.motor : []
  const exceptions = exceptionCount(risk)
  const pending = pendingLines(risk)
  const flagged = flaggedLines(risk)

  // The site this segment insures. A schedule that names no separate site puts
  // the address on the insured block instead (the Diesel Heads sheet has only
  // PHYSICAL ADDRESS / POSTAL BOX), so fall back to those rather than showing
  // nothing — that fallback is why "the risk address from the sheet did not
  // come through" on a schedule with no risk_location of its own.
  const loc = risk.risk_location || {}
  const cust = risk.customer || {}
  const addrName = String(loc.name || cust.name || '').trim()
  // Whatever address the schedule carries belongs to the RISK, whichever label
  // the broker used for it — "Physical Address", "Residential Address" or a
  // bare "Address". A schedule with no risk_location of its own puts the site
  // on the insured block, which is why those are read here too.
  const addrPhysical = String(
    loc.physical_address || loc.address
    || cust.physical_address || cust.residential_address || cust.address
    || cust.postal_address || ''
  ).trim()
  const addrOccupation = String(cust.occupation || '').trim()
  const hasAddr = !!(addrName || addrPhysical)

  if (coverages.length === 0 && motor.length === 0 && !hasAddr) return null

  return (
    <div className="rounded-lg border border-orange-300 bg-orange-50/50 p-4 space-y-4">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <div>
          <div className="text-sm font-semibold text-ink">
            Extracted from the uploaded schedule
          </div>
          <div className="text-xs text-ink-muted mt-0.5">
            {segment ? `Segment "${segment}" — ` : ''}
            {coverages.length} section{coverages.length === 1 ? '' : 's'}
            {motor.length > 0 && `, ${motor.length} vehicle${motor.length === 1 ? '' : 's'}`}.
            Apply writes them to this transaction; Load fills the form instead so
            you can check a section before saving it.
          </div>
        </div>
        <button
          type="button"
          disabled={applying || !productSelected || !!insuredMismatch}
          onClick={() => onApply(addrName, addrPhysical, addrOccupation)}
          className="px-4 py-2 text-sm bg-primary text-primary-contrast rounded-md
            disabled:opacity-40 whitespace-nowrap"
        >
          {applying ? 'Applying…' : 'Apply to this transaction'}
        </button>
      </div>

      {insuredMismatch && (
        <div className="rounded-md border border-red-300 bg-red-50 p-3 text-sm">
          <div className="font-semibold text-red-800">Not matching — nothing can be written</div>
          <div className="text-red-700 mt-1">
            The schedule is for <span className="font-medium">{insuredMismatch.insured}</span>,
            but this policy belongs to <span className="font-medium">{insuredMismatch.policy}</span>.
          </div>
          <div className="text-red-700 mt-1">
            The insured on a schedule is the policy holder. Open the policy for that
            insured, or correct the name on the policy, then come back.
          </div>
        </div>
      )}

      {/* Exceptions, shown where the quote is actually being built.
          Apply never writes a line the AI would not classify — so if these
          are left as they are, the section reaches the policy WITHOUT them.
          Said here rather than only on the upload screen, because this is
          where the underwriter can put them right. */}
      {exceptions.total > 0 && (
        <div className="rounded-md border border-amber-300 bg-amber-50 p-3 text-xs space-y-1.5">
          <div className="font-semibold text-amber-900">
            {exceptions.pending > 0 && `${exceptions.pending} line(s) need classification`}
            {exceptions.pending > 0 && exceptions.flagged > 0 && ' · '}
            {exceptions.flagged > 0 && `${exceptions.flagged} line(s) to check`}
          </div>
          <ul className="list-disc pl-4 space-y-0.5 text-amber-800">
            {pending.slice(0, 8).map((l, i) => (
              <li key={`p${i}`}>
                <span className="font-medium">Needs classification</span>
                {l.sectionTitle ? ` · ${l.sectionTitle}` : ''} — {l.text}
              </li>
            ))}
            {flagged.slice(0, 8).map((l, i) => (
              <li key={`f${i}`}>
                <span className="font-medium">Please check</span> · {l.sectionTitle} — {l.text}
                {' '}(read as {BUCKET_LABEL[l.bucket]})
              </li>
            ))}
          </ul>
          {/* Each list is sliced to 8 on its own, so the hidden count is
              per-list. Subtracting 16 from the combined total under-reported
              it whenever only one list overflowed — 20 pending and 0 flagged
              read "4 more" while 12 were hidden. */}
          {Math.max(0, pending.length - 8) + Math.max(0, flagged.length - 8) > 0 && (
            <div className="text-amber-800">
              …and {Math.max(0, pending.length - 8) + Math.max(0, flagged.length - 8)} more.
            </div>
          )}
          <div className="text-amber-800">
            Nothing was guessed. An unclassified line is not applied — place it on
            Smart Upload's review screen (Needs classification), or add it here by
            hand as a coverage line, extension, miscellaneous item or excess.
          </div>
        </div>
      )}

      {applyResult && <ApplySummary result={applyResult} />}

      {!productSelected && (
        <div className="text-xs text-amber-800 bg-amber-50 rounded-md p-2">
          Pick the product in Policy Details first — the coverage list depends on it.
        </div>
      )}
      {productSelected && !hasRiskAddress && (
        <div className="text-xs text-amber-800 bg-amber-50 rounded-md p-2">
          Add the risk address first. A coverage is always saved against one.
        </div>
      )}

      {hasAddr && (
        <div className="rounded-md border border-line bg-surface p-3 space-y-2">
          <div className="flex flex-wrap items-start justify-between gap-2">
            <div className="min-w-0">
              <div className="text-sm font-medium text-ink">Risk address from the schedule</div>
              <div className="text-xs text-ink-muted mt-0.5 break-words">
                {[addrName, addrPhysical].filter(Boolean).join(' — ') || '—'}
                {addrOccupation && <span className="text-ink-faint"> · {addrOccupation}</span>}
              </div>
            </div>
            <button
              type="button"
              disabled={!!insuredMismatch}
              onClick={() => onLoadRiskAddress(addrName, addrPhysical, addrOccupation)}
              className="px-3 py-1.5 text-sm bg-primary text-primary-contrast rounded-md
                disabled:opacity-40 whitespace-nowrap"
            >
              {riskAddressLoaded ? 'Loaded ✓' : 'Load into form'}
            </button>
          </div>
          <div className="text-xs text-ink-faint">
            {savedAddressCount > 0
              ? `This transaction already has ${savedAddressCount} risk address${savedAddressCount === 1 ? '' : 'es'} — `
                + 'loading adds another rather than replacing one. '
              : ''}
            State, city and construction type are required and the schedule
            carries none, so pick those before you press Add.
          </div>
        </div>
      )}

      <div className="space-y-2">
        {coverages.map((c, i) => {
          const master = matchCoverageMaster(availableCoverages, c.section, c.coverage_hint)
          const isMotor = master ? isMotorCoverageCode(master.s_CoverageCode) : false
          const lines = Array.isArray(c.details) ? c.details : []
          // What the schedule says this section costs — section total if it
          // carried one, else the sum of its own lines.
          const total = c.section_premium !== null && c.section_premium !== undefined
            ? Number(c.section_premium || 0)
            : lines.reduce((a, d) => a + Number(d.premium || 0), 0)

          return (
            <div key={i} className="rounded-md border border-line bg-surface p-3 space-y-2">
              <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0">
                  <div className="text-sm font-medium text-ink">{c.section || '(untitled section)'}</div>
                  <div className="text-xs mt-0.5">
                    {master ? (
                      <span className="text-green-700">
                        → {master.s_CoverageName || master.s_CoverageCode}
                        {existingCoverageIds.includes(master.id) && (
                          <span className="text-amber-700">
                            {'  '}· already on this transaction — Edit that coverage
                            instead of adding a second one
                          </span>
                        )}
                      </span>
                    ) : (
                      <span className="text-amber-700">
                        No matching coverage on this product — pick it yourself
                        {c.coverage_hint ? ` (read as "${c.coverage_hint}")` : ''}
                      </span>
                    )}
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  <span className="text-sm tabular-nums text-ink">{money(total)}</span>
                  <button
                    type="button"
                    disabled={!master || !productSelected || !hasRiskAddress
                      || loadingIndex === i || !!insuredMismatch}
                    onClick={() => master && onLoad(c, master)}
                    className="px-3 py-1.5 text-sm bg-primary text-primary-contrast rounded-md
                      disabled:opacity-40 whitespace-nowrap"
                  >
                    {loadingIndex === i
                      ? 'Loading…'
                      : loadedIndex === i ? 'Loaded ✓' : 'Load into form'}
                  </button>
                </div>
              </div>

              {!!(Array.isArray(c.unclassified) ? c.unclassified.length : 0) && (
                <div className="text-xs text-amber-800 bg-amber-50 rounded-md p-2">
                  {c.unclassified!.length} line(s) in this section are not classified
                  and will NOT be applied:{' '}
                  {c.unclassified!.map((u) => String(u?.text || '').trim()).filter(Boolean).join('; ')}.
                </div>
              )}

              {loadedIndex === i && !!unplacedExtensions?.length && (
                <div className="text-xs text-amber-800 bg-amber-50 rounded-md p-2">
                  Loaded, but this coverage has no row matching:{' '}
                  {unplacedExtensions.join('; ')}. Add those by hand.
                </div>
              )}

              {lines.length > 0 && (
                <div className="overflow-x-auto">
                  <table className="w-full text-xs">
                    <thead>
                      <tr className="text-[10px] uppercase tracking-wide text-ink-faint">
                        <th className="text-left font-medium py-1">Line</th>
                        <th className="text-right font-medium py-1">Sum insured</th>
                        <th className="text-right font-medium py-1">Rate</th>
                        <th className="text-right font-medium py-1">Premium</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-line">
                      {lines.map((d, di) => (
                        <tr key={di}>
                          <td className="py-1 text-ink">{d.description || '—'}</td>
                          <td className="py-1 text-right tabular-nums">{money(d.sum_insured)}</td>
                          <td className="py-1 text-right tabular-nums">
                            {d.rate === null || d.rate === undefined ? '—' : String(d.rate)}
                          </td>
                          <td className="py-1 text-right tabular-nums">{money(d.premium)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}

              {isMotor && motor.length > 0 && (
                <div className="text-xs text-ink-muted">
                  This is a motor section — its {motor.length} vehicle
                  {motor.length === 1 ? '' : 's'} belong in the Vehicles step, listed below.
                </div>
              )}
            </div>
          )
        })}
      </div>

      {motor.length > 0 && (
        <div>
          <div className="flex flex-wrap items-baseline justify-between gap-2 mb-1">
            <div className="text-xs font-semibold text-ink-muted">
              Vehicle schedule ({motor.length})
            </div>
            <button
              type="button"
              disabled={applying || !!insuredMismatch || !hasRiskAddress || !onRegisterVehicles}
              onClick={() => onRegisterVehicles?.()}
              className="px-3 py-1 text-xs border border-line rounded-md bg-surface
                hover:bg-surface-2 disabled:opacity-40 whitespace-nowrap"
            >
              Register all on the policy
            </button>
          </div>

          {/* Why two steps, said once, where the operator is deciding.
              Registering puts the plate in the policy's fleet (no cover, no
              premium); attaching is the underwriting decision, per vehicle. */}
          <div className="text-[11px] text-ink-muted mb-1.5">
            Registering adds the vehicle to the policy's fleet so the Vehicles step
            and the motor cover's vehicle picker can see it — it does not put it on
            cover. Use <span className="font-medium">Add to cover</span> per vehicle
            for that, then Rate.
          </div>

          {!hasRiskAddress && (
            <div className="text-[11px] text-amber-800 bg-amber-50 rounded-md p-2 mb-1.5">
              A risk address has to exist first — vehicles are registered against one.
            </div>
          )}

          {hasRiskAddress && motorTargets && motorTargets.length === 0 && (
            <div className="text-[11px] text-amber-800 bg-amber-50 rounded-md p-2 mb-1.5">
              No motor section on this transaction yet, so there is nothing to attach
              a vehicle to. Add the motor coverage first — which motor section a fleet
              sits under is an underwriting call, so it is never created here.
            </div>
          )}

          {hasRiskAddress && !!motorTargets && motorTargets.length > 1 && (
            <div className="flex items-center gap-2 text-[11px] text-ink-muted mb-1.5">
              <span>Attach to:</span>
              <select
                value={motorTargetId ?? ''}
                onChange={(e) => onMotorTargetChange?.(Number(e.target.value) || null)}
                className="border border-line rounded-md bg-surface px-2 py-1 text-[11px]"
              >
                {motorTargets.map((t) => (
                  <option key={t.coverageId} value={t.coverageId}>{t.name}</option>
                ))}
              </select>
            </div>
          )}

          <div className="border border-line rounded-md bg-surface overflow-auto max-h-72">
            <table className="w-full text-xs">
              <thead className="sticky top-0 bg-surface-2">
                <tr className="text-[10px] uppercase tracking-wide text-ink-faint">
                  <th className="text-left font-medium px-2 py-1">Registration</th>
                  <th className="text-left font-medium px-2 py-1">Make / model</th>
                  <th className="text-right font-medium px-2 py-1">Year</th>
                  <th className="text-right font-medium px-2 py-1">Sum insured</th>
                  <th className="text-right font-medium px-2 py-1">Premium</th>
                  <th className="text-right font-medium px-2 py-1">On the policy</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {motor.map((m, mi) => {
                  const plate = String(m.registration || '').trim().toUpperCase()
                  const state = plate ? vehicleState?.[plate] : undefined
                  const canAttach =
                    !!state?.vehicleId && !state.onCover && !!motorTargetId && !insuredMismatch
                  return (
                    <tr key={mi}>
                      <td className="px-2 py-1 font-medium text-ink">{plate || '—'}</td>
                      <td className="px-2 py-1 text-ink">{m.make_model || '—'}</td>
                      <td className="px-2 py-1 text-right tabular-nums">{m.year ?? '—'}</td>
                      <td className="px-2 py-1 text-right tabular-nums">{money(m.sum_insured)}</td>
                      <td className="px-2 py-1 text-right tabular-nums">{money(m.premium)}</td>
                      <td className="px-2 py-1 text-right whitespace-nowrap">
                        {!plate ? (
                          <span className="text-amber-800" title="addVehicle needs a registration">
                            no plate read
                          </span>
                        ) : state?.onCover ? (
                          <span className="text-green-700">on cover</span>
                        ) : state?.vehicleId ? (
                          <button
                            type="button"
                            disabled={!canAttach || attachingPlate === plate}
                            onClick={() => onAttachToMotor?.(mi)}
                            className="px-2 py-0.5 text-[11px] border border-line rounded
                              bg-surface hover:bg-surface-2 disabled:opacity-40"
                          >
                            {attachingPlate === plate ? 'Adding…' : 'Add to cover'}
                          </button>
                        ) : (
                          <span className="text-ink-faint">not registered</span>
                        )}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {risk.notes && (
        <div className="text-xs text-ink-muted">
          <span className="font-semibold">Extractor notes: </span>{String(risk.notes)}
        </div>
      )}
    </div>
  )
}

/**
 * What the apply actually did, per row, read back from the calls themselves.
 *
 * Deliberately verbose about skips and failures: an apply that silently did
 * four of five things is the failure mode that costs an underwriter a whole
 * afternoon of reconciliation.
 */
function ApplySummary({ result }: { result: ApplyResult }) {
  const tone: Record<ApplyStatus, string> = {
    added:   'text-green-700',
    updated: 'text-green-700',
    skipped: 'text-amber-700',
    failed:  'text-red-700',
  }
  const rows: ApplyOutcome[] = [
    ...(result.riskAddress ? [result.riskAddress] : []),
    ...result.coverages,
    // Vehicles report as their plate. Registered is not the same as covered,
    // so the message says which — an operator reading "ADDED · B424BVO" and
    // assuming it is on cover is exactly the reconciliation trap this summary
    // exists to prevent.
    ...(result.vehicles ?? []).map((v): ApplyOutcome => ({
      section: v.plate,
      status: v.status,
      message: v.message
        ?? (v.status === 'added' ? 'Registered on the policy — not yet on cover.' : undefined),
    })),
  ]
  return (
    <div className="rounded-md border border-line bg-surface p-3 space-y-1">
      <div className="text-xs font-semibold text-ink-muted">Applied to the policy</div>
      {rows.map((r, i) => (
        <div key={i} className="text-xs">
          <span className={`font-medium uppercase ${tone[r.status]}`}>{r.status}</span>
          <span className="text-ink"> · {r.section}</span>
          {r.message && <span className="text-ink-muted"> — {r.message}</span>}
          {!!r.unmatched?.length && (
            <div className="text-ink-faint pl-4">
              not placed, enter by hand: {r.unmatched.join('; ')}
            </div>
          )}
        </div>
      ))}
    </div>
  )
}
