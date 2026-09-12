import { useState } from 'react'
import { FacDialog, FacFieldset, FacField, FacRow, FacNote } from './FacUI'
import type { FacCaptureIntent } from './FacCaptureChoice'
import {
  lookupFacPolicy, facMoney,
  type FacPolicyLookup, type FacPlacementDetail, type FacStatus,
} from '../../api/fac'

/**
 * The placement form — used to capture a new line AND to correct a saved one.
 *
 * It is one component on purpose. A capture error used to be permanent because
 * there was no edit screen at all, and the obvious fix — a second, simpler form
 * for corrections — would have been the worse outcome: the derivations (gross
 * from premium × risk %, the warranty due date from signing date + window) and
 * the VAT arithmetic would have existed twice and drifted. An edit that computes
 * the payable differently from the capture that made it is not a correction.
 *
 * So the same fields, the same derivations and the same rejection handling serve
 * both, and `mode` changes only what the two genuinely do not share: the title,
 * the button, and the status picker (a correction can move a line between the
 * pre-settlement statuses; a capture always lands on `placed`).
 */

export type FacDraft = {
  policy_number: string
  counterparty_id: string
  placement_type: 'fac' | 'auto_fac'
  fac_slip_no: string
  risk_carrier: string
  ri_group_label: string
  cession_sum_insured: string
  source_premium: string
  /** Held as a PERCENTAGE — 17.07 for 17.07%. Converted on the way out. */
  risk_pct: string
  currency: string
  gross_ceded_premium: string
  /** Held as a PERCENTAGE — 27.5 for 27.5%. Converted on the way out. */
  commission_pct: string
  vat_applicable: boolean
  fx_rate: string
  fx_rate_date: string
  fx_rate_source: string
  slip_signed_date: string
  ppw_terms: string
  /** How the ceded premium is paid. Separate from the payment warranty above. */
  premium_frequency: string
  ppw_days: string
  notes: string
  /** Edit only. A capture always lands on `placed`. */
  status: string
}

export const EMPTY_FAC_DRAFT: FacDraft = {
  policy_number: '', counterparty_id: '', placement_type: 'fac', fac_slip_no: '',
  risk_carrier: '', ri_group_label: '', cession_sum_insured: '', source_premium: '', risk_pct: '',
  currency: 'BWP', gross_ceded_premium: '', commission_pct: '', vat_applicable: true,
  fx_rate: '', fx_rate_date: '', fx_rate_source: '',
  slip_signed_date: '', ppw_terms: '', ppw_days: '', premium_frequency: '', notes: '', status: '',
}

/**
 * The statuses a correction may set, mirroring CAPTURABLE_STATUSES on the
 * server. Everything past this point is a money event with its own permission
 * and its own side effects, so it is reached from its own button — never by
 * editing a dropdown.
 */
const CAPTURABLE_STATUSES: FacStatus[] = ['draft', 'placed', 'awaiting_premium']

/**
 * Every field this form can be rejected on, labelled as the form labels it, in
 * the order the form shows them.
 *
 * It exists so a rejection can always be NAMED. Marking the field alone was not
 * enough: the dialog is taller than the viewport, so "they are marked below"
 * pointed at something the capturer could not see, and a rejection on a field
 * that is conditionally hidden (the exchange-rate group when the currency is
 * Pula) or that has no box of its own (the VAT tick, the notes area) was marked
 * nowhere at all. Reinsurance reported it as "it says it will list the fields
 * but doesn't list any" — which was exactly right.
 *
 * Insertion order IS the display order, so the summary reads top-to-bottom like
 * the form. Keys the server can return but this form never sends are included
 * so nothing can arrive unlabelled.
 */
const FIELD_LABELS: Record<string, string> = {
  policy_number:        'Policy number',
  placement_type:       'Basis',
  status:               'Status',
  counterparty_id:      'Who we pay',
  risk_carrier:         'Risk carried by',
  fac_slip_no:          'FAC slip number',
  ri_group_label:       'RI group',
  cession_sum_insured:  'Sum insured ceded',
  risk_pct:             'Total risk %',
  slip_signed_date:     'Slip signed on',
  ppw_terms:            'Payment window',
  premium_frequency:    'Premium paid',
  ppw_days:             'Days',
  ppw_due_date:         'Premium due date',
  currency:             'Currency',
  source_premium:       'Full policy premium',
  gross_ceded_premium:  'Gross ceded premium',
  commission_pct:       'Commission %',
  vat_applicable:       'Botswana VAT',
  vat_rate:             'VAT rate',
  fx_rate:              'Exchange rate',
  fx_rate_date:         'Rate date',
  fx_rate_source:       'Rate source',
  notes:                'Notes',
  financial_year:       'Financial year',
  insured_name:         'Insured',
  policy_type:          'Policy type',
  period_from:          'Period from',
  period_to:            'Period to',
  underwriter_name:     'Underwriter',
}

const FIELD_ORDER = Object.keys(FIELD_LABELS)

function fieldLabel(field: string): string {
  return FIELD_LABELS[field] ?? field.replace(/_/g, ' ')
}

/** Rejections in the order the form shows the fields, not the order they arrived. */
function orderedErrors(errors: Record<string, string>): Array<[string, string]> {
  return Object.entries(errors).sort(([a], [b]) => {
    const ia = FIELD_ORDER.indexOf(a)
    const ib = FIELD_ORDER.indexOf(b)
    return (ia === -1 ? FIELD_ORDER.length : ia) - (ib === -1 ? FIELD_ORDER.length : ib)
  })
}

/**
 * The premium payment warranty, as Reinsurance actually negotiates it.
 *
 * It is a window running from the day the slip was signed — not the fixed date
 * the form used to ask for. Picking one of these fills the days, and the due
 * date the breach alarm watches is worked out from signing date + days.
 * "Other" leaves the days to be typed.
 */
const PPW_WINDOWS: Array<{ terms: string; days: string }> = [
  { terms: '', days: '' },
  { terms: '30 days', days: '30' },
  { terms: '60 days', days: '60' },
  { terms: '90 days', days: '90' },
  { terms: 'Monthly', days: '30' },
  { terms: 'Quarterly', days: '90' },
  { terms: 'Other', days: '' },
]

/** Botswana VAT. Mirrors config('fac.vat_rate') — the server recomputes on save. */
const VAT_RATE = 0.14

/**
 * The due date the form shows, worked out the same way the server works out the
 * one it stores.
 *
 * Read back off the LOCAL date parts, never toISOString(): Botswana is UTC+2, so
 * local midnight is the previous day in UTC and toISOString() lands a day early.
 * The form would have promised the capturer 8 November while the breach alarm
 * watched 9 November — the same date shown and stored two different ways.
 */
export function addDays(isoDate: string, days: number): string {
  const d = new Date(`${isoDate}T00:00:00`)
  if (Number.isNaN(d.getTime())) return ''
  d.setDate(d.getDate() + days)
  return localDate(d)
}

/** Today, in Botswana's own terms. See addDays for why toISOString() is not used. */
export function todayLocal(): string {
  return localDate(new Date())
}

function localDate(d: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

function round2(v: number): number {
  return Math.round(v * 100) / 100
}

/** A stored number into a form string, without exposing binary-float noise. */
function numStr(v: number | null | undefined): string {
  if (v === null || v === undefined) return ''
  return String(v)
}

/**
 * A stored decimal share into the percentage the form asks for.
 *
 * The multiplication is trimmed because 0.275 × 100 is 27.500000000000004 in
 * IEEE-754. Left alone that lands in the input, goes back to the server as
 * 0.27500000000000005 and is recorded in the trail as a commission change on an
 * edit that never touched commission. Six places is more than the widest column
 * (risk_pct is decimal(12,6)) so nothing real is lost.
 */
function pctStr(v: number | null | undefined): string {
  if (v === null || v === undefined) return ''
  return String(Number((v * 100).toFixed(6)))
}

/** A saved placement, back into the form that made it. */
export function facDraftFrom(p: FacPlacementDetail): FacDraft {
  return {
    policy_number: p.policyNumber ?? '',
    counterparty_id: p.counterpartyId ? String(p.counterpartyId) : '',
    placement_type: p.placementType,
    fac_slip_no: p.facSlipNo ?? '',
    risk_carrier: p.riskCarrier ?? '',
    ri_group_label: p.riGroupLabel ?? '',
    cession_sum_insured: numStr(p.cessionSumInsured),
    source_premium: numStr(p.sourcePremium),
    risk_pct: pctStr(p.riskPct),
    currency: p.currency || 'BWP',
    gross_ceded_premium: numStr(p.grossCededPremium),
    commission_pct: pctStr(p.commissionPct),
    vat_applicable: p.vatApplicable,
    fx_rate: numStr(p.fxRate),
    fx_rate_date: p.fxRateDate ?? '',
    fx_rate_source: p.fxRateSource ?? '',
    slip_signed_date: p.slipSignedDate ?? '',
    ppw_terms: p.ppwTerms ?? '',
    premium_frequency: p.premiumFrequency ?? '',
    ppw_days: numStr(p.ppwDays),
    notes: p.notes ?? '',
    status: p.status,
  }
}

export function facDraftToPayload(
  f: FacDraft,
  mode: 'create' | 'edit',
  intent?: FacCaptureIntent,
): Record<string, unknown> {
  const num = (v: string) => (v === '' ? undefined : Number(v))

  /**
   * Percentages are typed as percentages and STORED as decimals.
   *
   * The form used to ask for the decimal itself — "0.1707 for 17.07%" — and both
   * testers typed the percentage anyway, which the server refused with a bare
   * "The given data was invalid". Asking for the number people actually hold in
   * their heads removes the trap; the conversion belongs here, at the boundary,
   * so the stored convention and the June reconciliation are untouched.
   */
  const pct = (v: string) => (v === '' ? undefined : Number(v) / 100)

  return {
    policy_number: f.policy_number.trim(),
    counterparty_id: Number(f.counterparty_id),
    placement_type: f.placement_type,
    fac_slip_no: f.fac_slip_no.trim() || undefined,
    risk_carrier: f.risk_carrier.trim() || undefined,
    ri_group_label: f.ri_group_label.trim() || undefined,
    cession_sum_insured: num(f.cession_sum_insured),
    source_premium: num(f.source_premium),
    risk_pct: pct(f.risk_pct),
    currency: f.currency,
    gross_ceded_premium: num(f.gross_ceded_premium),
    commission_pct: pct(f.commission_pct) ?? 0,
    vat_applicable: f.vat_applicable,
    fx_rate: num(f.fx_rate),
    fx_rate_date: f.fx_rate_date || undefined,
    fx_rate_source: f.fx_rate_source.trim() || undefined,
    // An unsigned placement has no signing date by definition — the field is not
    // even shown. Sending whatever the draft happens to hold would put a date on a
    // slip nobody has signed, and the premium warranty would start counting down
    // from it.
    slip_signed_date: intent === 'new' ? undefined : (f.slip_signed_date || undefined),
    ppw_days: num(f.ppw_days),
    ppw_terms: f.ppw_terms || undefined,
    premium_frequency: f.premium_frequency || undefined,
    // Recorded so the trail says which of the two processes was followed.
    capture_intent: mode === 'create' ? intent : undefined,
    // Cleared deliberately rather than dropped: `undefined` would leave a note
    // that the capturer has just emptied sitting on the record.
    notes: mode === 'edit' ? f.notes.trim() : (f.notes.trim() || undefined),
    // Omitted unless it is a status this form is allowed to set.
    //
    // A `client_paid` or `ready_to_settle` line CAN be corrected — only settled
    // and cancelled are closed — but echoing its own status back would be
    // rejected by the validator, which accepts none of the post-capture ones. The
    // line would have been uneditable for the sake of a field the capturer never
    // touched and the picker never showed them.
    // The entry choice decides whether this is money owed.
    //
    // Everything used to be captured as `placed`, which put unsigned placements
    // straight into the payable, the frozen month-end snapshot and the journal
    // Finance posts — before the reinsurer had agreed to anything. `draft` is the
    // status the register already reserves for "captured, not yet owed", so an
    // unsigned placement lands there and joins the payable when the signed slip
    // comes back.
    status: mode === 'edit'
      ? (CAPTURABLE_STATUSES.includes(f.status as FacStatus) ? f.status : undefined)
      : (intent === 'new' ? 'draft' : 'placed'),
  }
}

export default function FacPlacementDialog({
  mode,
  intent,
  initial,
  counterparties,
  onSave,
  onClose,
}: {
  mode: 'create' | 'edit'
  /**
   * Which of the two capture processes this is. Required on a create — the point
   * of asking at entry is that the form can then be specific. Absent on an edit,
   * where the line already exists and its state says which it was.
   */
  intent?: FacCaptureIntent
  initial: FacDraft
  counterparties: Array<{ id: number; company_name: string }>
  /** Must reject on failure — the 422 is read here and marked on the fields. */
  onSave: (payload: Record<string, unknown>) => Promise<unknown>
  onClose: () => void
}) {
  const [form, setForm] = useState<FacDraft>(initial)
  const [lookup, setLookup] = useState<FacPolicyLookup | null>(null)
  const [saving, setSaving] = useState(false)
  // Per-field rejections from the server, plus anything it could not attribute to
  // a field. A single "The given data was invalid" alert named nothing and made
  // the capturer hunt through eighteen fields — reported by Reinsurance 10 Aug 2026.
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [formError, setFormError] = useState<string | null>(null)

  const isEdit = mode === 'edit'
  // On a create, the entry choice drives the form. `signed` is the assumption for
  // an edit: the line exists, so nothing here is deciding whether it is placed.
  const isUnsigned = !isEdit && intent === 'new'
  const isForeignNoRate = form.currency !== 'BWP' && !form.fx_rate

  // ── What the form works out for you ─────────────────────────────────
  //
  // The reinsurer's share used to be worked out on a calculator and typed in.
  // Give the form the full premium and the share of the risk and it does the
  // multiplication — and while it is doing it, the gross field is read-only, so
  // there is never a typed figure sitting on top of a derived one.
  const isGrossDerived = form.source_premium !== '' && form.risk_pct !== ''
  const grossValue = isGrossDerived
    ? String(round2(Number(form.source_premium) * (Number(form.risk_pct) / 100)))
    : form.gross_ceded_premium

  const grossNum = Number(grossValue) || 0
  const commissionAmount = grossNum * ((Number(form.commission_pct) || 0) / 100)
  const vatAmount = form.vat_applicable ? grossNum - grossNum / (1 + VAT_RATE) : 0
  const ppwDueDate = form.slip_signed_date && form.ppw_days
    ? addDays(form.slip_signed_date, Number(form.ppw_days))
    : ''

  /** Replace one field and clear the server's complaint about it. */
  function setField<K extends keyof FacDraft>(key: K, value: FacDraft[K]) {
    setForm(f => ({ ...f, [key]: value }))
    setErrors(e => (key in e ? Object.fromEntries(Object.entries(e).filter(([k]) => k !== key)) : e))
  }

  async function runLookup(policyNumber: string) {
    if (!policyNumber.trim()) { setLookup(null); return }
    try {
      setLookup(await lookupFacPolicy(policyNumber.trim()))
    } catch {
      setLookup(null)
    }
  }

  /**
   * Everything the form can tell is wrong, in ONE pass.
   *
   * This used to check only the three the server treats as required and return
   * on the first failure, which meant a capturer with four problems was told
   * about one, fixed it, submitted, and was told about the next. Every rule the
   * server will certainly apply is mirrored here so a single submit produces a
   * single complete list. The server remains the authority — it revalidates and
   * recomputes, and anything it rejects that this missed is displayed the same
   * way.
   */
  function localErrors(): Record<string, string> {
    const e: Record<string, string> = {}

    if (!form.policy_number.trim()) e.policy_number = 'A policy number is required.'
    if (!form.counterparty_id) e.counterparty_id = 'Choose who we pay.'
    if (!grossValue) {
      e.gross_ceded_premium =
        'Enter the gross ceded premium, or the full policy premium and the risk % to work it out.'
    }

    // ── What each of the two processes needs ────────────────────────────
    //
    // A placement said to be signed cannot be captured without the slip number or
    // the date — both are on the document in front of the capturer, and the date
    // is what the premium payment warranty counts from, so without it the breach
    // alarm can never fire on the line.
    //
    // A NEW placement is not asked for a slip number: the register allocates one
    // on save and generates the slip from it. Demanding it here was the same trap
    // in a different place — Reinsurance captured a placement without a number and
    // it could never have a slip at all.
    if (!isEdit && !isUnsigned && !form.fac_slip_no.trim()) {
      e.fac_slip_no = 'A slip number is required. It is on the signed slip.'
    }
    if (!isEdit && !isUnsigned && !form.slip_signed_date) {
      e.slip_signed_date =
        'The date the reinsurer signed is required — the premium warranty runs from it.'
    }

    const num = (v: string) => (v === '' ? null : Number(v))

    const risk = num(form.risk_pct)
    if (risk !== null && (Number.isNaN(risk) || risk < 0 || risk > 100)) {
      e.risk_pct = 'The total risk % must be between 0% and 100%.'
    }

    // Mirrors the server's arithmetic guard, which refuses 100% and above: a full
    // commission leaves the reinsurer with nothing, so it is a decimal point in
    // the wrong place rather than a term.
    const comm = num(form.commission_pct)
    if (comm !== null && (Number.isNaN(comm) || comm < 0 || comm >= 100)) {
      e.commission_pct = comm !== null && comm >= 100
        ? 'The commission % must be under 100%. A rate like 275 is usually 27.5 typed without the decimal point.'
        : 'The commission % cannot be negative.'
    }

    const premium = num(form.source_premium)
    if (premium !== null && (Number.isNaN(premium) || premium < 0)) {
      e.source_premium = 'The full policy premium cannot be negative.'
    }

    const days = num(form.ppw_days)
    if (days !== null && (Number.isNaN(days) || days < 1 || days > 1095)) {
      e.ppw_days = 'The payment window must be between 1 and 1095 days.'
    }

    if (form.currency !== 'BWP' && form.fx_rate !== '' && !(Number(form.fx_rate) > 0)) {
      e.fx_rate = 'The exchange rate must be greater than zero.'
    }

    if (form.notes.length > 2000) {
      e.notes = `Notes must be 2000 characters or fewer — this is ${form.notes.length}.`
    }

    return e
  }

  /**
   * Show a set of rejections and take the capturer to the first one.
   *
   * The scroll is the point. The list at the foot of the form names every field,
   * but on a dialog this tall the capturer still has to be moved to where the
   * work is.
   */
  function applyErrors(fieldErrors: Record<string, string>, message: string) {
    setErrors(fieldErrors)
    setFormError(message)

    // After paint, so the fields have their data-invalid marks by the time we look
    // for the first one. Scoped to `.fac-dialog` rather than the document: the
    // register renders at most one of these at a time, and scoping stops the
    // scroll escaping into an invalid field on the page behind the overlay.
    requestAnimationFrame(() => {
      const first = document.querySelector('.fac-dialog [data-invalid="true"]')
      if (!first) return
      first.scrollIntoView({ block: 'center', behavior: 'smooth' })
      first.querySelector<HTMLElement>('input, select, textarea')?.focus({ preventScroll: true })
    })
  }

  async function submit() {
    const local = localErrors()
    if (Object.keys(local).length) {
      applyErrors(local, 'Some fields need attention — every one is listed below.')
      return
    }

    setErrors({})
    setFormError(null)
    setSaving(true)
    try {
      await onSave(facDraftToPayload({ ...form, gross_ceded_premium: grossValue }, mode, intent))
    } catch (err: any) {
      // Laravel returns 422 as { message, errors: { field: [reason, …] } }. Only
      // the message was being read, so the reasons — which name the field and say
      // what is wrong with it — were thrown away and replaced by a browser alert.
      //
      // EVERY reason is kept, not just the first per field, because a field can
      // fail two rules at once and the second is often the one that explains it.
      const body = err?.response?.data
      const fieldErrors: Record<string, string> = {}
      for (const [field, reasons] of Object.entries(body?.errors ?? {})) {
        fieldErrors[field] = Array.isArray(reasons) ? reasons.map(String).join(' ') : String(reasons)
      }

      if (Object.keys(fieldErrors).length) {
        applyErrors(fieldErrors, 'The server rejected these fields — every one is listed below.')
      } else {
        // A rejection thrown by the arithmetic guard names no field at all, so it
        // goes at the foot of the form rather than nowhere.
        setErrors({})
        setFormError(body?.message || `The placement could not be ${isEdit ? 'amended' : 'saved'}.`)
      }
    } finally {
      setSaving(false)
    }
  }

  return (
    <FacDialog
      title={isEdit
        ? 'Edit placement details'
        : isUnsigned ? 'New placement — for signature' : 'Placement already signed'}
      width="lg"
      onClose={onClose}
      footer={
        <>
          <button className="fac-btn fac-btn--primary flex-1" disabled={saving} onClick={submit}>
            {saving
              ? 'Saving…'
              : isEdit ? 'Save changes'
              : isUnsigned ? 'Save and generate slip'
              : 'Save and attach signed slip'}
          </button>
          <button className="fac-btn" onClick={onClose}>Cancel</button>
        </>
      }
    >
      {/* Which process this is, restated so it is never in doubt mid-capture. */}
      {!isEdit && (
        <div style={{ marginBottom: 14 }}>
          {isUnsigned ? (
            <FacNote>
              <strong>The reinsurer has not signed this yet.</strong> It is held as a draft
              and does <strong>not</strong> count as money owed. Save it and the next step is
              to generate the slip and send it for signature — the premium warranty starts
              running from the day it comes back signed.
            </FacNote>
          ) : (
            <FacNote tone="ok">
              <strong>Already signed by the reinsurer.</strong> This joins the payable
              immediately, so the signing date and the slip number are both required. The
              next step is to attach the signed slip.
            </FacNote>
          )}
        </div>
      )}

      {isEdit && (
        <div style={{ marginBottom: 14 }}>
          <FacNote>
            Every field you change is recorded on the trail below the placement — who
            changed it, when, and the value before and after. Figures are recomputed on
            save, so the payable, the VAT split and the warranty date all follow the
            correction.
          </FacNote>
        </div>
      )}

      <FacFieldset legend="Policy">
        <div className="fac-grid-2">
          <FacField
            label="Policy number *"
            error={errors.policy_number}
            hint={isEdit
              ? 'Changing this re-reads the insured, the period and the status from Graphite.'
              : undefined}
          >
            <input type="text" className="fac-input" value={form.policy_number}
              placeholder="COMG2024105335"
              onChange={e => setField('policy_number', e.target.value)}
              onBlur={e => runLookup(e.target.value)} />
          </FacField>
          <FacField label="Basis" error={errors.placement_type}>
            <select className="fac-select" value={form.placement_type}
              onChange={e => setField('placement_type', e.target.value as 'fac' | 'auto_fac')}>
              <option value="fac">FAC</option>
              <option value="auto_fac">Auto FAC</option>
            </select>
          </FacField>
        </div>

        {/*
          The status picker exists only on a correction, and only while the line
          is still in capture. Once the client premium is confirmed received the
          line carries a liability Finance has already counted, so unwinding it
          is a settlement-desk action with its own permission — not an edit. The
          server drops the field in that case regardless of what is sent.
        */}
        {isEdit && CAPTURABLE_STATUSES.includes(initial.status as FacStatus) && (
          <div className="fac-grid-2 mt-3">
            <FacField label="Status" error={errors.status}
                      hint="Settlement and cancellation have their own buttons.">
              <select className="fac-select" value={form.status}
                onChange={e => setField('status', e.target.value)}>
                <option value="draft">Draft</option>
                <option value="placed">Placed</option>
                <option value="awaiting_premium">Awaiting premium</option>
              </select>
            </FacField>
          </div>
        )}

        {lookup && (
          <div className="mt-2">
            {lookup.inGraphite ? (
              <FacNote tone={lookup.isActive ? 'ok' : 'warn'}>
                <strong>{lookup.insuredName || 'Insured name not held'}</strong><br />
                {lookup.policyStatus} · {lookup.policyType || 'period unknown'} ·{' '}
                {lookup.periodFrom || '?'} to {lookup.periodTo || '?'}
                {!lookup.isActive && (
                  <><br /><strong>
                    This policy is not active in Graphite. A risk cannot be ceded on a policy
                    that was never issued — check with Underwriting before capturing.
                  </strong></>
                )}
                {lookup.coverage?.requiresFac && (
                  <><br />Graphite says this policy needs facultative cover of{' '}
                    {facMoney(lookup.coverage.requiredPremium)} in ceded premium.{' '}
                    {lookup.coverage.placedCount} placement(s) already registered.</>
                )}
                {lookup.receipts && (
                  <><br />Client premium received to date: {facMoney(lookup.receipts.received)}.</>
                )}
              </FacNote>
            ) : (
              <FacNote tone="danger">
                <strong>Not found in Graphite.</strong><br />
                You can still register it — the line will be flagged as unmatched so Finance
                can see it.
              </FacNote>
            )}
          </div>
        )}
      </FacFieldset>

      <FacFieldset legend="Counterparties">
        <div className="fac-grid-2">
          <FacField label="Who we pay *" error={errors.counterparty_id}
                    hint="The broker if one fronts the placement, otherwise the reinsurer.">
            <select className="fac-select" value={form.counterparty_id}
              onChange={e => setField('counterparty_id', e.target.value)}>
              <option value="">Select…</option>
              {counterparties.map(c => <option key={c.id} value={c.id}>{c.company_name}</option>)}
            </select>
          </FacField>
          <FacField label="Risk carried by" error={errors.risk_carrier} hint="Can be a panel — free text.">
            <input type="text" className="fac-input" value={form.risk_carrier}
              placeholder="Grand Re, Trans Axis Re, NCA Re"
              onChange={e => setField('risk_carrier', e.target.value)} />
          </FacField>
        </div>
      </FacFieldset>

      <FacFieldset legend="Cover">
        <div className="fac-grid-3">
          <FacField
            label={isUnsigned || isEdit ? 'FAC slip number' : 'FAC slip number *'}
            error={errors.fac_slip_no}
            hint={isUnsigned
              ? 'Leave blank and the next number is allocated on save. Type one to add this line to a slip that already exists.'
              : undefined}
          >
            <input type="text" className="fac-input" value={form.fac_slip_no}
              placeholder={isUnsigned ? 'Allocated automatically' : '2025-113'}
              onChange={e => setField('fac_slip_no', e.target.value)} />
          </FacField>
          <FacField label="RI group" error={errors.ri_group_label}>
            <input type="text" className="fac-input" value={form.ri_group_label} placeholder="BUILDINGS COMBINED"
              onChange={e => setField('ri_group_label', e.target.value)} />
          </FacField>
          <FacField label="Sum insured ceded" error={errors.cession_sum_insured}>
            <input type="number" step="0.01" className="fac-input" value={form.cession_sum_insured}
              onChange={e => setField('cession_sum_insured', e.target.value)} />
          </FacField>
        </div>
        <div className="fac-grid-3 mt-3">
          <FacField label="Total risk %" error={errors.risk_pct}
                    hint="The share of the risk ceded — type 17.07 for 17.07%.">
            <input type="number" step="0.01" min="0" max="100" className="fac-input" value={form.risk_pct}
              placeholder="17.07"
              onChange={e => setField('risk_pct', e.target.value)} />
          </FacField>
        </div>
      </FacFieldset>

      {/*
        The premium payment warranty.

        It used to be a single fixed date. Reinsurance pointed out that what
        is actually agreed on the slip is a PERIOD running from the day the
        slip was signed, so that is what gets captured — and the due date the
        breach alarm watches is worked out from it, shown here before saving.
      */}
      <FacFieldset legend="Premium payment warranty">
        <div className="fac-grid-3">
          {/*
            Asked for only when there is a signature to date. On a placement going
            out for signature the field was worse than useless — it invited a date
            for an event that has not happened, and the breach alarm would then
            count down against it. The window itself IS agreed on the slip before
            signing, so the terms and the days are captured either way.
          */}
          {isUnsigned ? (
            <FacField label="Slip signed on"
                      hint="Recorded when the signed slip comes back — not yet known.">
              <input type="text" className="fac-input" value="Awaiting signature" readOnly disabled />
            </FacField>
          ) : (
            <FacField label={isEdit ? 'Slip signed on' : 'Slip signed on *'}
                      error={errors.slip_signed_date}
                      hint="The day the reinsurer signed. The window runs from here.">
              <input type="date" className="fac-input" value={form.slip_signed_date}
                onChange={e => setField('slip_signed_date', e.target.value)} />
            </FacField>
          )}
          <FacField label="Payment window" error={errors.ppw_terms}
                    hint="As the slip words it.">
            <select className="fac-select" value={form.ppw_terms}
              onChange={e => {
                const chosen = PPW_WINDOWS.find(w => w.terms === e.target.value)
                setField('ppw_terms', e.target.value)
                // "Other" keeps whatever days are already typed; every named
                // window sets its own, so the two can never drift apart.
                if (chosen && chosen.terms !== 'Other') setField('ppw_days', chosen.days)
              }}>
              {PPW_WINDOWS.map(w => (
                <option key={w.terms} value={w.terms}>{w.terms || 'Not agreed yet'}</option>
              ))}
            </select>
          </FacField>
          <FacField label="Days" error={errors.ppw_days}
                    hint="Days from signing by which the premium must reach us.">
            <input type="number" step="1" min="1" className="fac-input" value={form.ppw_days}
              disabled={form.ppw_terms !== '' && form.ppw_terms !== 'Other'}
              onChange={e => setField('ppw_days', e.target.value)} />
          </FacField>

          {/*
            NOT the payment warranty, and next to it deliberately so the difference
            is visible. Slip 2026-002 carries both: "90 DAY PPW" is the deadline for
            the premium to reach us, "(Quarterly Payments)" is how it is broken up.
            Leaving this blank prints neither on the slip — silence rather than
            asserting a single annual payment nobody agreed.
          */}
          <FacField label="Premium paid" error={errors.premium_frequency}
                    hint="How the ceded premium is paid. Prints on the slip with the instalment amount.">
            <select className="fac-select" value={form.premium_frequency}
                    onChange={e => setField('premium_frequency', e.target.value)}>
              <option value="">Not stated</option>
              <option value="annual">Annually</option>
              <option value="semi_annual">Semi-annually</option>
              <option value="quarterly">Quarterly</option>
              <option value="monthly">Monthly</option>
            </select>
          </FacField>
        </div>
        <p className="fac-hint mt-3">
          {isUnsigned
            ? <>The due date is worked out when the signed slip is filed — <strong>signing date + {form.ppw_days || '…'} days</strong>. Capture the window now so it is on the slip the reinsurer signs.</>
            : ppwDueDate
              ? <>The client's premium must reach us by <strong>{ppwDueDate}</strong>. The register warns before that date and flags the line after it.</>
              : <>Give a signing date and a window and the due date is worked out here. Left blank, the line shows "not set" and no warranty alarm can fire on it.</>}
        </p>
      </FacFieldset>

      <FacFieldset legend="Money">
        <div className="fac-grid-4">
          <FacField label="Currency" error={errors.currency}>
            <select className="fac-select" value={form.currency}
              onChange={e => setField('currency', e.target.value)}>
              {['BWP', 'USD', 'ZAR', 'EUR', 'GBP'].map(c => <option key={c} value={c}>{c}</option>)}
            </select>
          </FacField>
          <FacField label="Full policy premium" error={errors.source_premium}
                    hint="The whole premium received. Leave blank to type the ceded share yourself.">
            <input type="number" step="0.01" min="0" className="fac-input" value={form.source_premium}
              onChange={e => setField('source_premium', e.target.value)} />
          </FacField>
          <FacField
            label="Gross ceded premium *"
            error={errors.gross_ceded_premium}
            hint={isGrossDerived
              ? `Worked out for you — ${facMoney(Number(form.source_premium), form.currency)} × ${form.risk_pct}%. Clear the full policy premium to type it by hand.`
              : undefined}
          >
            <input type="number" step="0.01" className="fac-input" value={grossValue}
              readOnly={isGrossDerived}
              onChange={e => setField('gross_ceded_premium', e.target.value)} />
          </FacField>
          <FacField label="Commission %" error={errors.commission_pct} hint="Type 27.5 for 27.5%.">
            <input type="number" step="0.01" min="0" max="99.99" className="fac-input" value={form.commission_pct}
              placeholder="27.5"
              onChange={e => setField('commission_pct', e.target.value)} />
          </FacField>
        </div>

        <div className="mt-3" data-invalid={errors.vat_applicable ? 'true' : 'false'}>
          <label className="flex items-center gap-2" style={{ fontSize: 13 }}>
            <input type="checkbox" checked={form.vat_applicable}
              onChange={e => setField('vat_applicable', e.target.checked)} />
            Botswana VAT applies — the gross above INCLUDES it
          </label>
          {/* A tick has no field box to carry a rejection, so it carries its own. */}
          {errors.vat_applicable && <span className="fac-error">{errors.vat_applicable}</span>}
        </div>

        {form.currency !== 'BWP' && (
          <div className="fac-grid-3 mt-3">
            <FacField label="Exchange rate" error={errors.fx_rate} hint="1 unit = this many Pula.">
              <input type="number" step="0.00000001" className="fac-input" value={form.fx_rate}
                onChange={e => setField('fx_rate', e.target.value)} />
            </FacField>
            <FacField label="Rate date" error={errors.fx_rate_date}>
              <input type="date" className="fac-input" value={form.fx_rate_date}
                onChange={e => setField('fx_rate_date', e.target.value)} />
            </FacField>
            <FacField label="Rate source" error={errors.fx_rate_source}>
              <input type="text" className="fac-input" value={form.fx_rate_source}
                placeholder="Bank of Botswana middle rate"
                onChange={e => setField('fx_rate_source', e.target.value)} />
            </FacField>
          </div>
        )}

        {isForeignNoRate && (
          <div className="mt-3">
            <FacNote tone="warn">
              Without a rate this placement will have no Pula value and will show as
              "exchange rate missing". It will not be treated as Pula.
            </FacNote>
          </div>
        )}

        {/*
          What the placement comes to, before it is saved. VAT is broken out
          on its own line — it is inside the gross, not on top of it, and
          Reinsurance asked to see the effect rather than infer it.
        */}
        {grossNum > 0 && (
          <div className="fac-panel mt-3">
            <div className="fac-panel-body" style={{ padding: '10px 14px' }}>
              <FacRow k="Gross ceded premium" v={facMoney(grossNum, form.currency)} strong />
              {form.vat_applicable && (
                <>
                  <FacRow k={`VAT inside the gross (${(VAT_RATE * 100).toFixed(0)}%)`}
                          v={facMoney(vatAmount, form.currency)} />
                  <FacRow k="Gross excluding VAT" v={facMoney(grossNum - vatAmount, form.currency)} />
                </>
              )}
              {form.commission_pct !== '' && (
                <>
                  <FacRow k={`Commission at ${form.commission_pct}%`}
                          v={facMoney(commissionAmount, form.currency)} />
                  <FacRow k="Net ceded premium" v={facMoney(grossNum - commissionAmount, form.currency)} />
                </>
              )}
            </div>
          </div>
        )}
        <p className="fac-hint mt-2">
          We pay the counterparty the gross. Commission is a separate receivable, and
          every figure here is recomputed on save — a net that disagrees with its gross is rejected.
        </p>
      </FacFieldset>

      <FacFieldset legend="Notes">
        <FacField label="Notes" error={errors.notes}>
          <textarea rows={2} className="fac-input" value={form.notes}
            onChange={e => setField('notes', e.target.value)} />
        </FacField>
      </FacFieldset>

      {/*
        The complete list, always.

        Marking the fields is not enough on its own — this dialog is taller than
        the viewport, a rejection can land on a field that is conditionally
        hidden (the exchange-rate group under a Pula placement) or on one with no
        box of its own, and the capturer then reads "some fields need attention"
        with nothing visibly wrong. Naming every one here means the count the
        capturer sees always matches the count the server refused.
      */}
      {formError && (
        <div className="mt-3">
          <FacNote tone="danger">
            <strong>{formError}</strong>
            {Object.keys(errors).length > 0 && (
              <ul className="fac-error-list">
                {orderedErrors(errors).map(([field, reason]) => (
                  <li key={field}>
                    <strong>{fieldLabel(field)}</strong> — {reason}
                  </li>
                ))}
              </ul>
            )}
          </FacNote>
        </div>
      )}
    </FacDialog>
  )
}
