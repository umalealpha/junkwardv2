import { useState, useEffect, useRef } from 'react'
import { createPortal } from 'react-dom'
import { useNavigate } from 'react-router-dom'
import { InputField, SelectField, TextAreaField, Section } from './FormField'
import { useToast } from '../../../components/common/Toast'
import { useConfirm } from '../../../components/common/ConfirmDialog'
import type { CoverageForm, SavedRiskAddress, SubCoverageEntry, ExtensionEntry, SpecifiedItemEntry, ExcessEntry } from './types'
import { INITIAL_COVERAGE } from './types'
import apiClient from '../../../api/client'
import { fetchMotorTradersExternal, saveMotorTradersExternal, fetchMotorTradersInternal, saveMotorTradersInternal } from '../../../api/policies'
import { formatNumberInput, unformatNumber, toWordsShort } from '../../../utils/format'
import { MOTOR_COVER_TYPES, normalizeMotorCoverType } from '../../../utils/motorCoverType'
import { matchDetailLines, ratePercent, numStr, type SmartUwDetail } from './smartUwPrefill'

// Default warranty text for Burglar Alarm Warranty extension (Office Content)
const BURGLAR_ALARM_WARRANTY_DEFAULT = `It is warranted that:
(a) The intruder alarm installed in the premises described in the Schedule shall be set in operation at all times when the premises are left unoccupied.
(b) The said alarm shall be maintained in efficient working order and shall be subject to a Maintenance Agreement.
(c) The Insured shall immediately advise the Alarm Company by telephone, email, facsimile or a written communication if any defect is discovered in the said alarm system.
(d) The Insured shall take all reasonable precautions and such additional precautions as are required by the insurers to safeguard the property insured during the period required to rectify any faults in the said system.
(e) The alarm shall be linked to a Control Room.
(f) This insurance shall not cover loss of or damage to the property following loss or or damage unless such keys have been obtained by violence or threat of violence to any person.`

// Default stated benefits text for Employees Liability / Workers Compensation
const STATED_BENEFITS_DEFAULT = `Death 6x Annual Earnings max P 200,000
Permanent Total Disablement 5x Annual Earnings Max P 250,000
Temporary Total Disablement 66.66% of weekly earnings up to 26 weeks
Medical Expenses P 75,000`

// Money input: commas on display, raw value stored without commas, short
// magnitude hint below (e.g. "≈ 1 hundred thousand") via toWordsShort.
//
// IMPORTANT: handleChange MUST call unformatNumber on the input value before
// emitting to the parent. The displayed value is formatted (e.g. "9,000")
// but state must hold the raw digits ("9000"). If you store the formatted
// string, parseFloat will stop at the first comma — typing 900000 results
// in state = "90,0000" which parseFloat reads as 90, breaking every
// downstream sum/calc and persisting wrong values to the DB.
function MoneyInput({ value, onChange, placeholder, className, readOnly }: {
  value: string
  onChange: (v: string) => void
  placeholder?: string
  className?: string
  readOnly?: boolean
}) {
  const words = toWordsShort(value)

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    onChange(unformatNumber(e.target.value))
  }

  return (
    <>
      <input
        type="text"
        inputMode="decimal"
        value={formatNumberInput(value)}
        onChange={handleChange}
        placeholder={placeholder ?? '0.00'}
        readOnly={readOnly}
        className={className}
      />
      {words && <div className="text-[10px] text-ink-faint italic mt-0.5 leading-tight">{words}</div>}
    </>
  )
}

// Searchable item picker. The master list can run to hundreds of entries
// (e.g. dozens of "Food & Drink" variants), so a plain <select> meant
// scrolling to find one. This renders a trigger button + a type-to-filter
// dropdown. The dropdown is portalled to <body> with fixed coordinates from
// the trigger's bounding rect, so it escapes the Misc-Items table's
// overflow-x-auto clipping (and any transformed ancestor) — the reason an
// earlier inline-panel attempt was reverted.
function SiCombobox({ value, options, onChange, disabled, loading }: {
  value: number | string | null
  options: { id: number; name: string; rate: string }[]
  onChange: (id: number) => void
  disabled?: boolean
  loading?: boolean
}) {
  const [open, setOpen] = useState(false)
  const [query, setQuery] = useState('')
  const [rect, setRect] = useState<{ top: number; left: number; width: number } | null>(null)
  const btnRef = useRef<HTMLButtonElement>(null)

  const label = (o: { name: string; rate: string }) =>
    `${o.name}${o.rate ? ` (${Number(o.rate).toFixed(6).replace(/\.?0+$/, '')}%)` : ''}`
  // value may arrive as a string (the list stores specified_coverage_id via
  // String(id)), so coerce both sides — strict === against numeric o.id would
  // never match and the trigger would keep showing "-- Select item --".
  const selected = (value === null || value === undefined || value === '')
    ? null
    : (options.find(o => Number(o.id) === Number(value)) ?? null)

  const openPanel = () => {
    if (disabled || loading) return
    const r = btnRef.current?.getBoundingClientRect()
    if (r) setRect({ top: r.bottom + 4, left: r.left, width: r.width })
    setQuery('')
    setOpen(true)
  }

  const term = query.trim().toLowerCase()
  const filtered = term ? options.filter(o => label(o).toLowerCase().includes(term)) : options

  return (
    <>
      <button type="button" ref={btnRef} disabled={disabled || loading} onClick={openPanel}
        className="w-full px-2 py-1.5 text-sm border border-line rounded bg-surface text-left disabled:opacity-60 truncate">
        {loading ? 'Loading items...' : (selected ? label(selected) : '-- Select item --')}
      </button>
      {open && rect && createPortal(
        <>
          <div className="fixed inset-0 z-[998]" onClick={() => setOpen(false)} />
          <div className="fixed z-[999] bg-surface border border-line rounded shadow-lg"
               style={{ top: rect.top, left: rect.left, width: Math.max(rect.width, 280) }}>
            <input autoFocus type="text" value={query} onChange={e => setQuery(e.target.value)}
              placeholder="Search items..."
              className="w-full px-2 py-1.5 text-sm border-b border-line outline-none" />
            <div className="max-h-64 overflow-y-auto">
              {filtered.length === 0 ? (
                <div className="px-2 py-2 text-xs text-ink-faint italic">No items match “{query}”.</div>
              ) : filtered.map(o => (
                <button type="button" key={o.id}
                  onClick={() => { onChange(o.id); setOpen(false) }}
                  className={`block w-full text-left px-2 py-1.5 text-sm hover:bg-status-warning-bg ${Number(o.id) === Number(value) ? 'bg-status-warning-bg font-medium' : ''}`}>
                  {label(o)}
                </button>
              ))}
            </div>
          </div>
        </>,
        document.body
      )}
    </>
  )
}

// RADIO type extensions: show Yes/No radio buttons (matching old project UI)
function RadioLimitField({ extension, idx, onUpdate }: {
  extension: ExtensionEntry
  idx: number
  onUpdate: (idx: number, key: string, value: any) => void
}) {
  // Use Yes (id: 2) and No (id: 23) limit ids from database
  const radioOptions = extension.limits && extension.limits.length > 0
    ? extension.limits
    : [
        { id: 2, name: 'Yes' },
        { id: 23, name: 'No' }
      ]

  // Ensure both values are compared as numbers
  const selectedId = extension.extention_limit_id ? Number(extension.extention_limit_id) : null

  return (
    <div className="flex gap-4 mt-1.5">
      {radioOptions.map((option: { id: number; name: string }) => (
        <label key={option.id} className="flex items-center gap-1.5 cursor-pointer text-xs">
          <input
            type="radio"
            name={`ext_limit_${idx}`}
            checked={selectedId === option.id}
            onChange={() => onUpdate(idx, 'extention_limit_id', option.id)}
            className="w-3.5 h-3.5 text-primary focus:ring-primary"
          />
          <span className="text-ink-muted">{option.name}</span>
        </label>
      ))}
    </div>
  )
}

interface Props {
  coverageForm: CoverageForm
  setCoverageForm: (v: CoverageForm) => void
  savedCoverages: CoverageForm[]
  setSavedCoverages: (v: CoverageForm[]) => void
  savedAddresses: SavedRiskAddress[]
  availableCoverages: any[]
  // Smart Underwriting: detail lines extracted from a broker schedule, waiting
  // for this coverage's subcoverage template to arrive so their sums insured /
  // rates / premiums can be written into the matching rows. Null on every
  // ordinary visit, so the whole path is inert unless the operator came from
  // Smart Upload's "Review & Issue" and pressed Load on a section.
  smartUwLines?: { coverageId: number; lines: SmartUwDetail[] } | null
  onSmartUwApplied?: () => void
  policyId?: number
  // Policy product id. COM/DOM (7/8) suppress the legacy "Main" coverage block
  // (UI + the coverage-wise policy_coverage_detail row); other products keep it.
  productId?: number | null
  onSave: (finalForm?: CoverageForm) => void
  onDelete: (idx: number) => void
  onEdit: (idx: number) => void
  onReinstate?: (idx: number) => void
  editingCoverageIndex?: number | null
  errors: Record<string, string>
  setErrors: (fn: (p: Record<string, string>) => Record<string, string>) => void
  saving?: boolean
  // Canonical total from policy_actions.annual_premium for the resolved action.
  // When > 0 the Total Premium row uses this so the table reconciles with the
  // step badge / Rate banner / Policy Detail Total Coverage Premium. Falls
  // back to local per-coverage sum during initial create (no action yet).
  backendTotalPremium?: number
}

const MOTOR_CODES = ['COMMERCIALMOTOR', 'PERSONALMOTOR', 'MOTOR', 'DOMMOTOR']

// Motor Traders Ext/Int — single-row-per-coverage data model. Three main
// fields shown for every type-of-cover; the 11 extensions + 4 minimum-limit
// fields hide for `TPMotorTraders*` (third party only) and show for the
// Comprehensive / FTPFT variants, mirroring the legacy
// ManageCoverages::MotorTradersExternal() hydration branches.
const MT_MAIN_ROWS: Array<[string, string]> = [
  ['Loss Or Damage', 'loss_or_damage'],
  ['Third Party Liability', 'third_party_liability'],
  ['Medical Benefits', 'medical_benefits'],
]
const MT_EXT_ROWS: Array<[string, string]> = [
  ['Vehicles Lent Or Hired To Customers', 'vehicle_lent_hire'],
  ['Social, Domestic And Pleasure Use', 'social_domestic_pleasure'],
  ['Unauthorised Use By Employees', 'unauthoried_use'],
  ['Windscreen', 'windscreen'],
  ['Contingent Liability', 'contigent_liability'],
  ['Wreckage Removal', 'wreckage_removal'],
  ['Loss Of Keys', 'loss_of_key'],
  ["Loss Of Use Of Customer's Vehicle", 'Loss_of_use_of_customer'],
  ['Motor Cycle, Motor Tricycle Or Quad Bike', 'motor_cycle_motor_tricycle'],
  ['Passenger Liability In Respect Of Motor Cycles And Motor Tricycles', 'passanger_liability_respect_of_motor'],
  ['Special Type Vehicle', 'special_type_vehicle'],
]
const MT_MIN_ROWS: Array<[string, string]> = [
  ['Own Damage Minimum %', 'own_damage_minimun_percent'],
  ['Own Damage Minimum Amount', 'own_damage_minimum_amount'],
  ['Windscreen Minimum %', 'windscreen_minimun_percent'],
  ['Windscreen Minimum Amount', 'windscreen_minimum_amount'],
]

// Coverage types where the main Sum Insured / Rate / Calculated Premium trio
// is not captured at the parent-coverage level — the operator drives pricing
// from extensions, schedules, or downstream forms instead.
const HIDE_MAIN_COVERAGE_FIELDS_CODES = [
  'MEDICAMALPRACTICEINSURANCE',
  'PROFESSIONALINDEMNITY',
  'TRAVELINSURANCE',
  'CONTRACTORSALLRISKS',
  'PLANTALLRISKS',
  'ERECTIONALLRISKS',
  'DIRECTORSOFFICERSLIABILITY',
  'MACHINERYBREAKDOWN',
  'MARINEONCEOFFCOVER',
  'MARINEOPENCOVER',
  'MOTORTRADERSEXTERNAL',
  'MOTORTRADERSINTERNAL',
  'MEDICALEVACUATION',
  'COMMERCIALCRIME',
  'ENVIRONMENTALLIABILITY',
  'BONDSANDGUARANTEES',
]

// Per-vehicle premium columns on the motor table. For motor coverages,
// extensions live as premium_* columns on motor (NOT in policy_extention_detail),
// so aggregating motor's premium = calculated_value (own damage)
// + every premium_* field. Used to compute the section's true Sum Insured /
// Premium aggregate displayed on the Commercial Motor / Personal Motor /
// Motor Traders sub-coverage row.
const MOTOR_PREMIUM_FIELDS = [
  'premium_wreckage_removal', 'premium_window_glass', 'premium_locks_keys',
  'premium_parts_accessories', 'premium_audio_accessories', 'premium_riot_strike',
  'premium_car_hire_theft', 'premium_credit_shortfall', 'premium_insured_driver',
  'premium_insured_family', 'premium_medical_expenses', 'premium_passenger_liability',
  'premium_third_party_liability', 'premium_specified_accessories',
  'premium_unorthorised_passanger_liability', 'premium_parking_facilities',
  'premium_com_windscreen',
]
// TYPE_OF_COVER_OPTIONS removed — used only by the inline Add-Vehicle form,
// which has been replaced by a redirect to the Vehicles tab where the modal
// owns its own option set.

export default function StepCoverages({ coverageForm, setCoverageForm, savedCoverages, savedAddresses, availableCoverages, smartUwLines, onSmartUwApplied, policyId, productId, onSave, onDelete, onEdit, onReinstate, editingCoverageIndex, errors, setErrors, saving, backendTotalPremium }: Props) {
  const navigate = useNavigate()
  const { toast } = useToast()
  const confirm = useConfirm()
  const [subLoading, setSubLoading] = useState(false)
  const [subEntries, setSubEntries] = useState<SubCoverageEntry[]>([])
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  // COM/DOM (product 7/8): the legacy "Main" group sub-coverage (the parent
  // self-row) is dropped — UW enters data per row, and no coverage-wise
  // policy_coverage_detail "Main" row is written. Other products keep it.
  const isDomComProduct = productId === 7 || productId === 8
  const isMainGroupRow = (s: any) => String(s?.s_CoverageGroupName ?? '').trim().toLowerCase() === 'main'

  const update = (key: keyof CoverageForm, value: any) => {
    setCoverageForm({ ...coverageForm, [key]: value })
    setErrors(p => ({ ...p, [`cov_${key}`]: '' }))
    // DEBUG: Log WC field updates
    if (key.includes('ratefactor')) {
      console.log(`DEBUG: Updated ${key} = ${value}`)
    }
  }

  // Snapshot of each existing sub-coverage row's loaded values, keyed by
  // policy_coverage_detail id. handleSave compares the submitted values against
  // this to decide whether the operator actually changed a row THIS session —
  // the signal the backend uses to pro-rate only genuinely-touched lines on an
  // endorsement (an untouched carried-forward line must not be charged).
  const subOriginalsRef = useRef<Record<string, { cv: number; calc: number }>>({})
  const snapshotSubOriginals = (rows: any[]) => {
    const m: Record<string, { cv: number; calc: number }> = {}
    rows.forEach(r => {
      const id = Number(r?.id ?? 0)
      if (id > 0) {
        m[String(id)] = {
          cv:   parseFloat(String(r.coverage_value ?? '').replace(/,/g, '')) || 0,
          calc: parseFloat(String(r.calculated_value ?? '').replace(/,/g, '')) || 0,
        }
      }
    })
    subOriginalsRef.current = m
  }

  // Fetch subcoverages when coverage type changes — always fetch master template
  // and merge with existing saved data (if any). Shows all available subcoverages
  // with blank rows for ones not yet filled, plus any duplicates added via "+".
  useEffect(() => {
    if (!coverageForm.coverage_id) {
      setSubEntries([])
      return
    }
    const existingSubs = coverageForm.subcoverages || []
    setSubLoading(true)
    apiClient.get(`/lookups/coverages/${coverageForm.coverage_id}/subcoverages`)
      .then(r => {
        let subs = r.data.data ?? []
        console.log('API Response - Subcoverages:', subs)  // DEBUG

        // Display ALL subcoverages dynamically, grouped by s_CoverageGroupName
        // (previously filtered Houseowners to only 'SUM INSURED', excluding 'Description of cover' section)

        const merged: any[] = []
        const usedExistingIds = new Set<number>()

        // First, merge master template with existing values
        // Match by coverage_id (sub_coverage_id in API response), not detail row id
        // CRITICAL: Handle data corruption where existing rows have sub_coverage_id = parent coverage_id (e.g., 20 instead of 111)
        subs.forEach((s: any) => {
          let existing = existingSubs.find(e => {
            // Try to match by sub_coverage_id (coverage master ID) first
            const eSubId = e.sub_coverage_id ?? e.id
            return eSubId === s.id
          })

          // If no match, check if existing row has corrupted sub_coverage_id (parent coverage_id instead of child)
          // Match by s_ScreenName or s_CoverageCode instead
          if (!existing && coverageForm.coverage_id) {
            existing = existingSubs.find(e => {
              const matchByScreenName = e.s_ScreenName === s.s_ScreenName
              const matchByCode = e.s_CoverageCode === s.s_CoverageCode
              return (matchByScreenName || matchByCode) && e.s_ParentCoverageCode === s.s_ParentCoverageCode
            })
            if (existing) {
              console.warn(`[DATA FIX] Matched existing row by screen name (corrupted sub_coverage_id=${existing.sub_coverage_id}, fixing to ${s.id})`)
            }
          }

          if (existing) {
            // Mark as used by the coverage_id, not the detail row id
            usedExistingIds.add(existing.sub_coverage_id ?? existing.id)
          }
          merged.push({
            id: existing?.detail_id ?? existing?.id ?? 0,  // Use detail_id if available, fallback to id, then 0 for new rows
            s_ScreenName: s.s_ScreenName || s.s_CoverageName || '',
            s_CoverageGroupName: s.s_CoverageGroupName || '',
            s_SubCoverageMainName: s.s_SubCoverageMainName || '',
            s_CoverageCode: s.s_CoverageCode || '',          // Subcoverage code (e.g., "BASIS OF COVER")
            s_ParentCoverageCode: s.s_ParentCoverageCode || '', // Parent coverage code (e.g., "PUBLICLIABILITY")
            s_LimitTypeCode: (s as any).s_LimitTypeCode || '', // Limit type (NUMBER, NOEDIT, etc.) from API
            dropdown_options: (s as any).dropdown_options || [], // Options for DROPDOWN type from API
            radio_options: (s as any).radio_options || [],       // Options for RADIO type from API
            coverage_value: existing?.coverage_value || '',
            // Keep the master's default rate for a second-field-only row (dropdown /
            // free text saved with SI = Premium = 0, e.g. Public Liability "Basis of
            // cover"). Those rows persist rate 0, which would otherwise replace the
            // master default (100) with 0.00 on every re-open. Rows that carry money
            // keep their saved rate exactly as before.
            rate: (() => {
              const num = (v: any) => parseFloat(String(v ?? '').replace(/,/g, '')) || 0
              const savedRate = existing?.rate
              const rowHasMoney = num(existing?.coverage_value) > 0 || num(existing?.calculated_value) > 0
              if (savedRate && (rowHasMoney || num(savedRate) > 0)) return savedRate
              return s.rate ? String(s.rate) : ''
            })(),
            calculated_value: existing?.calculated_value || '',
            sub_coverage_id: s.id,  // Master coverage ID (use correct one from API, not corrupted existing data)
            // Workers Compensation / Stated Benefits (per-row) — preserved
            // on hydrate so the composite layout reads back what the
            // operator saved last time.
            coverage_value_string:       (existing as any)?.coverage_value_string ?? '',
            discount_surcharge:          (existing as any)?.discount_surcharge ?? '',
            discount_surcharge_type:     (existing as any)?.discount_surcharge_type ?? '',
            discount_surcharge_value:    (existing as any)?.discount_surcharge_value ?? '',
            ratefactor_type:             (existing as any)?.ratefactor_type ?? '',
            ratefactor_value:            (existing as any)?.ratefactor_value ?? '',
            ratefactor_value_check:      (existing as any)?.ratefactor_value_check ?? '',
            ratefactor_AnnualWages:      (existing as any)?.ratefactor_AnnualWages ?? '',
            ratefactor_deposit_min_pre:  (existing as any)?.ratefactor_deposit_min_pre ?? '',
            limit_id:                    (existing as any)?.limit_id ?? '',  // DROPDOWN + RADIO second-field pick
          })
        })

        // Then, append any existing rows that weren't matched to master
        // (these are duplicates added via "+")
        existingSubs.forEach(e => {
          const eSubId = e.sub_coverage_id ?? e.id
          if (!usedExistingIds.has(eSubId)) {
            // Ensure sub_coverage_id is set for these rows too
            // IMPORTANT: Override id to use detail_id (actual database row ID) if available
            merged.push({
              ...e,
              id: e.detail_id ?? e.id ?? 0,  // Use detail_id for deletion, fallback to id
              sub_coverage_id: e.sub_coverage_id ?? e.id,
            })
          }
        })

        // COM/DOM: drop the legacy "Main" group row up-front so it is neither
        // rendered nor included when handleSave derives filledSubs from subEntries.
        const finalRows = isDomComProduct ? merged.filter(m => !isMainGroupRow(m)) : merged
        snapshotSubOriginals(finalRows)
        setSubEntries(finalRows)
      })
      .catch(() => {
        const fallback = existingSubs.length > 0 ? existingSubs : []
        snapshotSubOriginals(fallback)
        setSubEntries(fallback)
      })
      .finally(() => setSubLoading(false))
  }, [coverageForm.coverage_id, editingCoverageIndex, (coverageForm as any)._dbId, isDomComProduct])

  // ── Smart Underwriting: write extracted schedule figures into the rows ────
  // Runs once the subcoverage template for the loaded coverage has actually
  // arrived — that is the only moment a schedule line can be matched to a real
  // row, since the row names come from the master table.
  //
  // Deliberately a SEPARATE effect rather than a patch inside the fetch above:
  // adding smartUwLines to that effect's deps would re-fetch (and so re-blank
  // the rows) the instant the lines were cleared, and it also has to cover the
  // case where the operator loads a section whose coverage is ALREADY selected,
  // where coverage_id never changes and the fetch never re-runs.
  //
  // It patches AFTER snapshotSubOriginals, which is correct: these rows really
  // are changed relative to what was on the policy, so endorse pro-rata must
  // see them as changed and rate them.
  useEffect(() => {
    if (!smartUwLines || smartUwLines.coverageId !== coverageForm.coverage_id) return
    if (subLoading || subEntries.length === 0) return

    const { assignments } = matchDetailLines(smartUwLines.lines, subEntries)
    if (assignments.length > 0) {
      setSubEntries(prev => {
        const next = prev.map(r => ({ ...r }))
        assignments.forEach(({ row, line }) => {
          if (!next[row]) return
          const si   = Number(line.sum_insured ?? 0)
          const prem = Number(line.premium ?? 0)
          // Rate goes in as a PERCENT — the wizard prices a row as
          // sum x rate / 100 — and is derived from premium and sum insured so
          // the premium it recomputes lands back on the schedule's own figure.
          next[row].coverage_value   = numStr(si || null)
          next[row].rate             = ratePercent(si, prem, line.rate ?? null)
          next[row].calculated_value = numStr(prem || null)
          next[row]._changed         = true
        })
        return next
      })
    }
    // Cleared either way. A section whose lines matched nothing must not sit
    // pending and then land on the NEXT coverage the operator opens; the
    // unmatched lines stay visible in the Smart UW panel to be typed in.
    onSmartUwApplied?.()
  }, [smartUwLines, subLoading, subEntries.length, coverageForm.coverage_id])

  const updateSubEntry = (idx: number, key: 'coverage_value' | 'rate' | 'calculated_value', value: string) => {
    setSubEntries(prev => {
      const next = [...prev]
      const entry = { ...next[idx], [key]: value }
      const v = parseFloat(entry.coverage_value)
      const r = parseFloat(entry.rate)
      const p = parseFloat(entry.calculated_value)

      if (key === 'coverage_value' || key === 'rate') {
        // SI + Rate → Premium
        entry.calculated_value = (!isNaN(v) && !isNaN(r)) ? (v * r / 100).toFixed(2) : ''
      } else if (key === 'calculated_value') {
        // Premium + SI → Rate (if we have both)
        if (!isNaN(v) && !isNaN(p) && v > 0) {
          entry.rate = (p / v * 100).toFixed(6).replace(/\.?0+$/, '')
        } else if (isNaN(v) || v === 0) {
          // Premium + Rate → SI (if we have rate but no SI)
          if (!isNaN(r) && !isNaN(p) && r > 0) {
            entry.coverage_value = (p / r * 100).toFixed(2)
          }
        }
      }
      next[idx] = entry
      return next
    })
  }

  // Workers Compensation field setter — Premium is hand-entered so we DO NOT
  // recompute calculated_value from rate × coverage_value, and we DO NOT
  // auto-apply the discount/surcharge math (UW does that calculation
  // themselves and types the final Premium directly). Pure passthrough setter
  // for any of the WC-only fields on a given sub-row.
  const updateSubEntryRaw = (idx: number, key: keyof SubCoverageEntry, value: string) => {
    setSubEntries(prev => {
      const next = [...prev]
      next[idx] = { ...next[idx], [key]: value }
      return next
    })
  }

  // Add a blank row below the clicked subcoverage (+ button). Description
  // is COPIED from the source row (so e.g. clicking + on "Buildings" gives
  // another "Buildings" row the operator can edit), while Sum Insured /
  // Rate / Premium start blank for fresh entry. Group stays with the source
  // so the row renders under the same section.
  // Clone immediately on "+" click - create new row in tb_cvgpccoverages with auto-incremented ID
  const addDuplicateRow = (idx: number) => {
    const source = subEntries[idx]
    console.log('addDuplicateRow - source entry:', {
      idx,
      source_id: source.id,
      source_sub_coverage_id: source.sub_coverage_id,
      source_name: source.s_ScreenName,
      policyId,
      coverage_id: coverageForm.coverage_id,
    })
    if (!source.sub_coverage_id || !policyId) {
      console.warn('Cannot clone: missing policyId or sub_coverage_id', { policyId, sub_coverage_id: source.sub_coverage_id })
      toast.error('Missing policy ID or sub-coverage ID. Please reload the page.')
      return
    }

    // Call API to clone master immediately (baseURL already includes /api/v1)
    const url = `/policies/${policyId}/coverage-masters/${source.sub_coverage_id}/clone`
    console.log('Cloning coverage:', { policyId, masterId: source.sub_coverage_id, url })

    apiClient.post(url)
      .then(res => {
        console.log('Clone successful:', res.data)
        const clonedId = res.data.cloned_id
        // Add row with the cloned ID from tb_cvgpccoverages
        setSubEntries(prev => {
          const newRow: SubCoverageEntry = {
            id: clonedId,  // Use the real cloned ID from database
            s_ScreenName: source.s_ScreenName,
            s_CoverageGroupName: source.s_CoverageGroupName,
            s_SubCoverageMainName: source.s_SubCoverageMainName,
            coverage_value: '',
            rate: '',
            calculated_value: '',
            isCustom: true,
            sub_coverage_id: clonedId,  // Track the cloned ID for saving
            // Copy second field configuration from source so Free Text and other fields appear in new row
            s_LimitTypeCode: source.s_LimitTypeCode,
            s_CoverageCode: source.s_CoverageCode,
            s_ParentCoverageCode: source.s_ParentCoverageCode,
            dropdown_options: source.dropdown_options,
            radio_options: source.radio_options,
            ratefactor_value: source.ratefactor_value || '',
            ratefactor_type: source.ratefactor_type || '',
            coverage_value_string: source.coverage_value_string || '',
            limit_id: source.limit_id || '',
          }
          const next = [...prev]
          next.splice(idx + 1, 0, newRow)
          return next
        })
      })
      .catch(err => {
        const errorData = {
          error: err,
          response: err.response?.data,
          status: err.response?.status,
          message: err.response?.data?.message || err.message,
          url,
        }
        console.error('Failed to clone coverage:', errorData)

        let errorMsg = 'Failed to add subcoverage. '
        if (err.response?.status === 404) {
          errorMsg += 'Coverage or policy not found (404).'
        } else if (err.response?.status === 500) {
          errorMsg += 'Server error. Check console for details.'
        } else if (err.response?.data?.message) {
          errorMsg = err.response.data.message
        } else if (err.message) {
          errorMsg += err.message
        } else {
          errorMsg += 'Please try again.'
        }

        toast.error(errorMsg)
      })
  }

  // Remove a subcoverage row (- button)
  const removeRow = (idx: number) => {
    const entry = subEntries[idx]
    const dbId = (coverageForm as any)._dbId

    console.warn(`Deleting row idx=${idx}: s_ScreenName="${entry.s_ScreenName}", id=${entry.id}, detail_id=${entry.detail_id}`)

    // If entry has an ID, it's already saved — delete it in the database
    if (entry.id && dbId && policyId) {
      const deleteItem = async () => {
        try {
          await apiClient.delete(
            `/policies/${policyId}/coverages/${dbId}/details/${entry.id}`
          )
          setSubEntries(prev =>
            prev.map((sub, i) => i === idx ? { ...sub, deleted_at: new Date().toISOString() as any } : sub)
          )
        } catch (err) {
          console.error('Failed to delete subcoverage row:', err)
          toast.error('Failed to delete row')
        }
      }
      deleteItem()
    } else {
      // New unsaved row — just remove from local state
      setSubEntries(prev => prev.filter((_, i) => i !== idx))
    }
  }

  // Reinstate a deleted subcoverage row (↻ button)
  const reinstateRow = (idx: number) => {
    const entry = subEntries[idx]
    const dbId = (coverageForm as any)._dbId

    if (entry.id && dbId && policyId) {
      const restoreItem = async () => {
        try {
          await apiClient.post(
            `/policies/${policyId}/coverages/${dbId}/details/${entry.id}/reinstate`
          )
          setSubEntries(prev =>
            prev.map((sub, i) => i === idx ? { ...sub, deleted_at: null as any } : sub)
          )
        } catch (err) {
          console.error('Failed to reinstate subcoverage row:', err)
          toast.error('Failed to reinstate row')
        }
      }
      restoreItem()
    }
  }

  // ── Extensions & Specified Items ──────────────────────
  const [extLoading, setExtLoading] = useState(false)
  const [extEntries, setExtEntries] = useState<ExtensionEntry[]>([])
  const [siLoading, setSiLoading] = useState(false)
  const [siOptions, setSiOptions] = useState<{ id: number; name: string; rate: string }[]>([])
  const [siEntries, setSiEntries] = useState<SpecifiedItemEntry[]>([])
  // Free-text filter for the added Miscellaneous Items list (find a row fast
  // when many items are captured). Filters by item description only.
  const [siSearch, setSiSearch] = useState('')
  const [excEntries, setExcEntries] = useState<ExcessEntry[]>([])
  const [allFidelityData, setAllFidelityData] = useState<any[]>([]) // Store ALL data from database
  const [fidelityEntries, setFidelityEntries] = useState<any[]>([])
  const [fidelityBasisOfCover, setFidelityBasisOfCover] = useState<'Blanket' | 'Named_Position' | ''>('')
  const [theftQuestions, setTheftQuestions] = useState<Record<string, string>>({})

  // When basis of cover changes, restructure entries and populate appropriate fields
  const handleFidelityBasisChange = (value: 'Blanket' | 'Named_Position' | '') => {
    setFidelityBasisOfCover(value)

    // Only restructure entries if a basis is actually selected
    if (value) {
      // If no entries exist, add one empty entry to show the form
      if (fidelityEntries.length === 0) {
        const newEntry = value === 'Blanket'
          ? {
              cover_type: 'Blanket',
              cover_area: 'All employees',
              amount_to_be_guaranteed: '',
              premium: ''
            }
          : {
              cover_type: 'Named_Position',
              name_and_position: '',
              designation: '',
              length_of_service: '',
              amount_to_be_guaranteed: '',
              premium: ''
            }
        setFidelityEntries([newEntry])
      } else {
        // Fetch and filter data from database based on selected cover_type
        const filtered = allFidelityData.filter((entry: any) => entry.cover_type === value)

        if (filtered.length === 0) {
          // No matching data found - create a new empty entry for the selected basis
          const newEntry = value === 'Blanket'
            ? { cover_type: 'Blanket', cover_area: 'All employees', amount_to_be_guaranteed: '', premium: '' }
            : { cover_type: 'Named_Position', name_and_position: '', designation: '', length_of_service: '', amount_to_be_guaranteed: '', premium: '' }
          setFidelityEntries([newEntry])
        } else {
          // Display only the filtered entries that match the selected basis cover_type
          setFidelityEntries(filtered)
        }
      }
    }
  }

  // Fetch extensions when coverage changes — use existing data if editing.
  // Reset siOptions first so stale master rows from a previously-opened coverage
  // never leak into the dropdown while the new list is loading.
  // IMPORTANT: the dependency includes editingCoverageIndex so that clicking
  // Edit on the SAME coverage twice re-syncs the sub/ext/si/exc entries from
  // the persisted row. Without it the second edit session keeps whatever
  // values the user had in the form on the first attempt — which read as
  // "update isn't working the first time". Ref: user bug report.
  useEffect(() => {
    setSiOptions([])
    if (!coverageForm.coverage_id) { setExtEntries([]); setSiEntries([]); setExcEntries([]); return }

    // If form has saved extensions/specified/excesses (editing), load them directly.
    // Always sync — not just when non-empty — so reopening clears stale state.
    const existingExts = (coverageForm as any).extensions || []
    const existingSi = (coverageForm as any).specified_items || []
    let existingExc = (coverageForm as any).excesses || []

    // Filter: separate excess-like entries from extensions
    // (old policies may have stored excesses in the extensions array)
    const excessLikeInExts = existingExts.filter((e: any) =>
      e.type === 'Excess' ||
      (e.s_ScreenName || '').toLowerCase().includes('excess') ||
      (e.extention_limit_type || '').toLowerCase().includes('excess')
    )

    // Merge any excess entries found in extensions with explicit excesses
    if (excessLikeInExts.length > 0) {
      const convertedExcesses = excessLikeInExts.map((e: any) => ({
        excesses: e.s_ScreenName || e.custom_name || '',
        min_percent: (e.extention_excess_min_value ?? e.min_percent) || '',
        min_amt: (e.extention_excess_max_value ?? e.min_amt) || '',
        discount_surcharge: e.extention_discount_surcharge || e.discount_surcharge || '',
        discount_surcharge_type: e.extention_discount_surcharge_type || e.discount_surcharge_type || '',
        discount_surcharge_value: e.extention_discount_surcharge_value || e.discount_surcharge_value || '',
        premium: e.extention_calculated_value || e.premium || '',
      }))
      existingExc = [...existingExc, ...convertedExcesses]
    }

    // Set initial state with saved data while API fetch is in progress
    setExtEntries(existingExts.filter((e: any) =>
      e.type !== 'Excess' &&
      !(e.s_ScreenName || '').toLowerCase().includes('excess') &&
      !(e.extention_limit_type || '').toLowerCase().includes('excess')
    ))
    setSiEntries(existingSi)
    setExcEntries(existingExc)
    // Fidelity data is handled in a separate useEffect

    // Always fetch specified item dropdown options for this coverage only.
    // Backend scopes by specified_coverage_items.coverage_id — mirrors legacy
    // ManageCoverages::getAllMainCoverages() where each coverage owns its
    // specifiedCoverages relation, so motor items never appear on fire, etc.
    const covIdAtFetch = coverageForm.coverage_id
    setSiLoading(true)
    apiClient.get(`/lookups/coverages/${covIdAtFetch}/specified-items`)
      .then(r => {
        // Guard against stale response: if the user switched coverage while
        // the request was in flight, ignore this response.
        if (covIdAtFetch !== coverageForm.coverage_id) return
        setSiOptions((r.data.data ?? []).map((s: any) => ({ id: s.id, name: s.name, rate: String(s.rate ?? 0) })))
      })
      .catch(() => setSiOptions([]))
      .finally(() => setSiLoading(false))

    // Always fetch all master extensions for this coverage (don't skip if there's
    // saved data). When editing, merge master extensions with saved data so the
    // user can see all available extensions with pre-filled values where they exist.
    setExtLoading(true)
    apiClient.get(`/lookups/coverages/${coverageForm.coverage_id}/extensions`)
      .then(r => {
        // Guard against stale response
        if (covIdAtFetch !== coverageForm.coverage_id) return

        const masterExts = (r.data.data ?? []).map((e: any) => ({
          extentions_id: e.id,
          s_ScreenName: e.s_ScreenName || '',
          s_CoverageCode: e.s_CoverageCode || '',
          s_ExtensionsGroupName: e.s_ExtensionsGroupName || '',
          type: e.type || 'Extention',
          extention_type: e.extention_type || 'NUMBER',
          extention_limit_type: e.extention_limit_type || null,
          rate: e.rate ? String(e.rate) : '',
          limits: e.limits || [],
          extention_coverage_value: e.predefined_value ? String(e.predefined_value) : '',
          extention_text_value: '',
          extention_limit_id: null,
          extention_excess_min_value: '',
          extention_excess_max_value: '',
          extention_discount_surcharge: '',
          extention_discount_surcharge_type: '',
          extention_discount_surcharge_value: '',
          extention_calculated_value: '',
        }))

        // Merge master extensions with saved data:
        // Show all master extensions, but pre-fill with saved data where it exists
        // Filter out excess entries (they belong in excesses section, not extensions)
        const existingExtsOnly = existingExts.filter((e: any) =>
          e.type !== 'Excess' &&
          !(e.s_ScreenName || '').toLowerCase().includes('excess') &&
          !(e.extention_limit_type || '').toLowerCase().includes('excess')
        )
        const mergedExts = masterExts.map((master: ExtensionEntry) => {
          const saved = existingExtsOnly.find((s: ExtensionEntry) => s.extentions_id === master.extentions_id)
          // When merging saved data with master, preserve limits from master
          // (saved data from DB doesn't include limits, those come from API master)
          return saved ? { ...master, ...saved, limits: master.limits } : master
        })

        // Sort extensions: Burglar Alarm Warranty always appears at the end
        const sortedExts = mergedExts.sort((a: ExtensionEntry, b: ExtensionEntry) => {
          const aIsBurglar = (a.s_ScreenName || '').toLowerCase().includes('burglar alarm warranty') ? 1 : 0
          const bIsBurglar = (b.s_ScreenName || '').toLowerCase().includes('burglar alarm warranty') ? 1 : 0
          return aIsBurglar - bIsBurglar
        })

        setExtEntries(sortedExts)
      })
      .catch(() => setExtEntries(existingExts))
      .finally(() => setExtLoading(false))
    // _smartUwSeed is bumped every time Smart Upload loads a section. Without
    // it, loading a SECOND section that resolves to the same coverage_id (or
    // re-loading onto a coverage already open) left this effect asleep: the
    // grids kept the previous rows and handleSave rebuilt the payload FROM the
    // grids, so the newly loaded extensions and misc items were silently
    // dropped while the sub-coverage figures went through.
  }, [coverageForm.coverage_id, editingCoverageIndex, (coverageForm as any)._dbId,
      (coverageForm as any)._smartUwSeed])

  // Auto-fill Burglar Alarm Warranty text when extension is present and is_taken=true
  useEffect(() => {
    const burglarExt = extEntries.find(e =>
      (e.s_ScreenName || '').toLowerCase().includes('burglar alarm warranty')
    )
    const isTaken = burglarExt?.is_taken !== false  // default true
    // If extension exists, is taken (Yes selected), but warranty text is empty, pre-fill with default
    if (burglarExt && isTaken && !(coverageForm as any).burglar_alarm_warranty) {
      setCoverageForm({ ...coverageForm, burglar_alarm_warranty: BURGLAR_ALARM_WARRANTY_DEFAULT } as any)
    }
  }, [extEntries, coverageForm.coverage_id, (coverageForm as any).burglar_alarm_warranty])

  // Auto-fill Stated Benefits text for Employees Liability / Workers Compensation
  useEffect(() => {
    // Auto-fill with default benefits text when:
    // 1. Coverage has just been loaded (via API response or form initialization)
    // 2. stated_benefits is empty
    // 3. Subcoverages are present (indicating this is an Employees Liability / WC coverage)
    if (coverageForm.coverage_id && !(coverageForm as any).stated_benefits && subEntries.length > 0) {
      setCoverageForm({ ...coverageForm, stated_benefits: STATED_BENEFITS_DEFAULT } as any)
    }
  }, [coverageForm.coverage_id, subEntries.length])

  const updateExtEntry = (idx: number, key: string, value: any) => {
    setExtEntries(prev => {
      const next = [...prev]
      const entry = { ...next[idx], [key]: value }
      // Auto-calc premium from coverage_value * rate / 100
      if (key === 'extention_coverage_value') {
        const v = parseFloat(value); const r = parseFloat(entry.rate)
        entry.extention_calculated_value = (!isNaN(v) && !isNaN(r)) ? (v * r / 100).toFixed(2) : ''
      }
      // When "Burglar Alarm Warranty" is toggled to "Yes" and warranty text is empty, pre-fill with default
      if (key === 'is_taken' && value === true && (entry.s_ScreenName || '').toLowerCase().includes('burglar alarm warranty')) {
        if (!(coverageForm as any).burglar_alarm_warranty) {
          setCoverageForm({ ...coverageForm, burglar_alarm_warranty: BURGLAR_ALARM_WARRANTY_DEFAULT } as any)
        }
      }
      next[idx] = entry
      return next
    })
  }

  // Legacy parity: Miscellaneous Items starts empty with "+ Add Row" — do NOT
  // auto-populate every master row. Master options are held in siOptions and
  // presented as a dropdown per added row.

  const updateSiEntry = (idx: number, key: string, value: any) => {
    setSiEntries(prev => {
      const next = [...prev]
      const entry: any = { ...next[idx], [key]: value }
      // When the master pick changes, pull the master's name + rate and
      // recalculate the premium from the existing sum_insured. Anything
      // further downstream (coverage total) is derived in handleSave.
      if (key === 'specified_coverage_id') {
        const opt = siOptions.find(o => o.id === Number(value))
        if (opt) { entry.name = opt.name; entry.rate = opt.rate }
        else     { entry.name = '';      entry.rate = '' }
      }
      if (key === 'sum_insured' || key === 'rate' || key === 'specified_coverage_id') {
        const s = parseFloat(entry.sum_insured)
        const r = parseFloat(entry.rate)
        entry.calculated_value = (!isNaN(s) && !isNaN(r)) ? (s * r / 100).toFixed(2) : ''
      }
      next[idx] = entry
      return next
    })
  }

  // Add a master row — dropdown selects which master item applies. Mirrors
  // legacy graphiteBWV8 addSpecifiedRow; no free-text / custom path.
  const addSiRow = () => {
    setSiEntries(prev => [...prev, {
      specified_coverage_id: null,
      name: '',
      sum_insured: '',
      rate: '',
      calculated_value: '',
    }])
  }

  const removeSiRow = (idx: number) => {
    const entry = siEntries[idx]
    const dbId = (coverageForm as any)._dbId

    // If entry has an ID, it's already saved — soft-delete it in the database
    if (entry.id && dbId && policyId) {
      const deleteItem = async () => {
        try {
          await apiClient.put(
            `/policies/${policyId}/coverages/${dbId}/specified-items/${entry.id}`,
            { deleted_at: new Date().toISOString() }
          )
          setSiEntries(prev =>
            prev.map((si, i) => i === idx ? { ...si, deleted_at: new Date().toISOString() } : si)
          )
        } catch (err) {
          console.error('Failed to delete miscellaneous item:', err)
        }
      }
      deleteItem()
    } else {
      // New unsaved row — just remove from local state
      setSiEntries(prev => prev.filter((_, i) => i !== idx))
    }
  }

  const reinstateSiRow = (idx: number) => {
    const entry = siEntries[idx]
    const dbId = (coverageForm as any)._dbId

    if (entry.id && dbId && policyId) {
      const reinstateItem = async () => {
        try {
          await apiClient.put(
            `/policies/${policyId}/coverages/${dbId}/specified-items/${entry.id}`,
            { deleted_at: null }
          )
          setSiEntries(prev =>
            prev.map((si, i) => i === idx ? { ...si, deleted_at: null } : si)
          )
        } catch (err) {
          console.error('Failed to reinstate miscellaneous item:', err)
        }
      }
      reinstateItem()
    }
  }

  const addExcess = () => setExcEntries(prev => [...prev, { excesses: '', min_percent: '', min_amt: '', discount_surcharge: '', discount_surcharge_type: '', discount_surcharge_value: '', premium: '' }])
  const updateExcEntry = (idx: number, key: string, value: string) => {
    setExcEntries(prev => { const next = [...prev]; next[idx] = { ...next[idx], [key]: value }; return next })
  }

  // ── Determine second field type for subcoverages based on SUBCOVERAGE code ─────
  // Logic derived from old blade file (manage-coverages.blade.php lines 3951-4050)
  // Checks subcoverage code (s_CoverageCode) to determine field type, not parent coverage code
  // Returns { type, label, storageField } or null if no second field
  const getSubcoverageSecondField = (limitTypeCode?: string, dropdownOptions?: string[], coverageCode?: string, parentCode?: string, radioOptions?: any[], screenName?: string) => {
    const limitType = (limitTypeCode || '').toUpperCase()
    if (limitType === 'RADIO') {
      console.log('RADIO CHECK - limitTypeCode:', limitTypeCode, 'limitType:', limitType, 'radioOptions:', radioOptions)
    }

    // Special case: Goods In Transit subcoverages - mixed dropdown and free text
    // (Hardcoded pattern from old project manage-coverages.blade.php)
    // Different field types based on subcoverage name
    if (parentCode?.toUpperCase() === 'GOODSINTRANSIT') {
      const screen = (screenName || '').toUpperCase()
      // Basis of cover and Means of Conveyance are dropdowns
      if (screen.includes('BASIS') || screen.includes('COVER')) {
        return { type: 'select', label: 'Select Option', storageField: 'limit_id', options: ['All Risk', 'Specific Perils', 'Limited Cover'] }
      }
      if (screen.includes('MEANS') || screen.includes('CONVEYANCE') || screen.includes('CONV')) {
        return { type: 'select', label: 'Select Option', storageField: 'limit_id', options: ['Road', 'Rail', 'Air', 'Sea', 'Multi-modal'] }
      }
      // All others are free text (Estimated Annual Carry, Limit Per Load, etc.)
      return { type: 'text', label: 'Free Text', storageField: 'coverage_value_string' }
    }

    // Special case: Rent subcoverage needs "Elect/One Of Month" dropdown (1-12)
    // Works for all parent coverages: Fire > Rent, Building > Rent, Combined > Rent, etc.
    // (Hardcoded pattern from old project for months - stores in ratefactor_type like Stock)
    if (coverageCode?.toUpperCase() === 'RENT') {
      return { type: 'select', label: 'Elect/One Of Month', storageField: 'ratefactor_type', options: ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'] }
    }

    // Special case: STOCK coverage needs "Select Decl M/Q/A" dropdown
    // (Hardcoded pattern from old project)
    if (coverageCode?.toUpperCase() === 'STOCK') {
      return { type: 'select', label: 'Select Decl M/Q/A', storageField: 'ratefactor_type', options: ['Monthly', 'Quarterly', 'Annually'] }
    }

    // Special case: THEFT "Enter sum insured" needs "Basis of Cover" dropdown
    // (Database has NUMBER type, but old project hardcodes this pattern)
    if (coverageCode?.toUpperCase() === 'ENTER SUM INSURED' && parentCode?.toUpperCase() === 'THEFT') {
      return { type: 'select', label: 'Basis of Cover', storageField: 'ratefactor_type', options: ['Full Value', 'First Loss'] }
    }

    // Pure data-driven approach: Use ONLY s_LimitTypeCode from API
    // NOEDIT → coverage_value_string (for coverages like PUBLIC LIABILITY, FIRE subcoverages)
    if (limitType === 'NOEDIT') {
      return { type: 'text', label: 'Free Text', storageField: 'coverage_value_string' }
    }

    // DROPDOWN → limit_id (for subcoverages with dropdown limits, e.g., Fire > Rent "Elect/One Of Month")
    // Options come from API (dropdown_options), not hardcoded
    if (limitType === 'DROPDOWN') {
      return { type: 'select', label: 'Select Option', storageField: 'limit_id', options: dropdownOptions || [] }
    }

    // Fire subcoverages (Buildings, Contents, Plant and Machinery, Stock, Miscellaneous)
    // should save Free Text to coverage_value_string, not ratefactor_value
    // This matches the old project (graphiteBWV8) behavior
    const fireSubcoverages = ['BUILDINGS', 'CONTENTS', 'PLANT', 'MISCELLANEOUS']
    const subCoverageCode = (coverageCode || '').toUpperCase()
    const isFireSubcoverage = fireSubcoverages.some(code => subCoverageCode.includes(code))

    if (limitType === 'NUMBER' && isFireSubcoverage) {
      return { type: 'text', label: 'Free Text', storageField: 'coverage_value_string' }
    }

    // NUMBER → ratefactor_value (for coverages like ELECTRONIC EQUIPMENT, ACCIDENTAL DAMAGE)
    if (limitType === 'NUMBER') {
      return { type: 'text', label: 'Free Text', storageField: 'ratefactor_value' }
    }

    // RADIO → radio buttons (for THEFT subcoverages like Damage to Buildings, Additional Claims)
    // Store in limit_id field, options come from API (radio_options)
    if (limitType === 'RADIO') {
      return { type: 'radio', label: '', storageField: 'limit_id', options: radioOptions || [] }
    }

    // No s_LimitTypeCode? Don't show second field (API should always provide this)
    return null
  }

  // ── Motor Vehicles (COMMERCIALMOTOR / PERSONALMOTOR) ──────
  const selectedCov = availableCoverages.find((c: any) => c.id === coverageForm.coverage_id)
  const covCode = selectedCov?.s_CoverageCode?.toUpperCase?.() || (coverageForm as any).coverage_code?.toUpperCase?.() || ''
  const isMotorCoverage = MOTOR_CODES.includes(covCode)
  const isFidelityGuarantee = covCode === 'FIDELITYGUARANTEE'
  const hidesMainCoverageFields = HIDE_MAIN_COVERAGE_FIELDS_CODES.includes(covCode)
  const [motorVehicles, setMotorVehicles] = useState<any[]>([])
  const [availableVehicles, setAvailableVehicles] = useState<any[]>([])
  const [selectedVehicleId, setSelectedVehicleId] = useState<string>('')
  const [vehicleSearch, setVehicleSearch] = useState('')
  const [showVehicleDropdown, setShowVehicleDropdown] = useState(false)
  const [motorLoading, setMotorLoading] = useState(false)
  // showAddVehicle is still READ at the !showAddVehicle gates below to keep
  // the existing-vehicles selector visible; setter is unused after the inline
  // Add-Vehicle form was replaced by a redirect to the Vehicles tab.
  const [showAddVehicle, _setShowAddVehicle] = useState(false)
  const _BLANK_VEHICLE = { vehicle_plate: '', make_id: '', model_id: '', make: '', model: '', year: '', engine_number: '', chassis_number: '', estimated_value: '', is_imported: false, type_of_cover: 'Comprehensive', use: '', coverage_value: '', calculated_value: '', wreckage_removal: '0', window_glass: '0', locks_keys: '0', parts_accessories: '0', riot_strike: '0', credit_shortfall: '0', premium_wreckage_removal: '', premium_window_glass: '', premium_locks_keys: '', premium_parts_accessories: '', premium_riot_strike: '', premium_credit_shortfall: '', own_damage: '', own_damage_minimun_percent: '', own_damage_minimum_amount: '', windscreen: '', windscreen_minimun_percent: '', windscreen_minimum_amount: '', loss_of_keys: '', loss_of_keys_minimun_percent: '', loss_of_keys_minimum_amount: '' }
  const [_newVehicle, _setNewVehicle] = useState({ ..._BLANK_VEHICLE })
  // UAT 2026-05-26: setter + makesLoaded state currently unused — kept
  // with underscore prefix so TS doesn't flag them (TS6133) while Snehal
  // wires up the make/model loader. Delete once the loader lands or the
  // state is genuinely abandoned.
  const [_makes, _setMakes] = useState<any[]>([])
  const [_models, _setModels] = useState<any[]>([])
  const [_makesLoaded, _setMakesLoaded] = useState(false)

  // ── Motor Traders Ext/Int ──
  // Separate single-row data model — one row per policy_coverage in the
  // motor_traders / motor_traders_internal tables. Loaded when the operator
  // opens an existing MotorTraders coverage; persisted via dedicated PUT
  // endpoints in handleSave. New-coverage saves stash the data in
  // `pendingMtSave` and the useEffect below fires the PUT once the parent
  // assigns a _dbId.
  const isMotorTradersExternal = covCode === 'MOTORTRADERSEXTERNAL'
  const isMotorTradersInternal = covCode === 'MOTORTRADERSINTERNAL'
  const isMotorTradersCoverage = isMotorTradersExternal || isMotorTradersInternal
  const [mtData, setMtData] = useState<Record<string, any>>({})
  const [mtLoading, setMtLoading] = useState(false)
  const [pendingMtSave, setPendingMtSave] = useState<{
    kind: 'external' | 'internal'
    data: Record<string, any>
    coverageId: number
    riskAddressId: number | null
    existingDbIds: number[]
  } | null>(null)

  useEffect(() => {
    if (!isMotorTradersCoverage || !policyId) return
    const dbId = (coverageForm as any)._dbId
    if (!dbId) { setMtData({}); return }
    setMtLoading(true)
    const fetcher = isMotorTradersExternal ? fetchMotorTradersExternal : fetchMotorTradersInternal
    fetcher(policyId, dbId)
      .then(row => setMtData(row ? { ...row } : {}))
      .catch(() => setMtData({}))
      .finally(() => setMtLoading(false))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isMotorTradersCoverage, isMotorTradersExternal, policyId, (coverageForm as any)._dbId])

  // Pending-save pickup: when savedCoverages gains a new entry matching the
  // criteria we stashed at save-click time, PUT the MT data against the new
  // _dbId. This is what makes brand-new MotorTraders coverages persist on a
  // single Save (no double-click required).
  useEffect(() => {
    if (!pendingMtSave || !policyId) return
    const candidate = savedCoverages.find((c: any) => {
      const id = (c as any)._dbId
      return id
        && !pendingMtSave.existingDbIds.includes(id)
        && c.coverage_id === pendingMtSave.coverageId
        && ((c as any).risk_address_id ?? null) === pendingMtSave.riskAddressId
    })
    if (!candidate) return
    const saver = pendingMtSave.kind === 'external' ? saveMotorTradersExternal : saveMotorTradersInternal
    saver(policyId, (candidate as any)._dbId, pendingMtSave.data)
      .then(() => setPendingMtSave(null))
      .catch(err => {
        // Same reason as the inline save in handleSave — never swallow it.
        setPendingMtSave(null)
        toast.error(
          err?.response?.data?.error ||
          err?.response?.data?.message ||
          err?.message ||
          'Motor Traders details could not be saved'
        )
      })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [savedCoverages, pendingMtSave, policyId])

  const mtTypeOfCover = (mtData.type_of_cover as string) || ''
  const showMtMain = mtTypeOfCover !== '' && mtTypeOfCover.indexOf('TPMotorTraders') !== 0
  const showMtExtensions = showMtMain  // Comprehensive + FTPFT show extensions; TP-only hides them
  const showMtTpOnly = mtTypeOfCover.indexOf('TPMotorTraders') === 0
  const setMtField = (k: string, v: any) => setMtData(prev => ({ ...prev, [k]: v }))

  // Aggregated Sum Insured / Premium for motor coverages — sums across
  // every vehicle on this policy_coverage (which is already scoped by
  // risk_address_id + action_id, since motorVehicles is loaded via
  // /coverages/{dbId}/motor) plus every specified item linked to those
  // vehicles. Per the data model: motor extensions live on the motor row
  // itself (premium_*), not in policy_extention_detail, so we sum
  // calculated_value + all premium_* fields per vehicle.
  //
  //   Sum Insured = Σ motor.coverage_value + Σ specified_items.sum_insured
  //   Premium     = Σ (motor.calculated_value + Σ motor.premium_*) + Σ specified_items.calculated_value
  const motorAggregate = (() => {
    if (!isMotorCoverage) return { sumInsured: 0, premium: 0 }
    const num = (v: any) => parseFloat(v) || 0
    // Cancelled (soft-deleted) vehicles + their per-vehicle items are
    // excluded from the aggregate — they contribute 0 to the section
    // premium, matching the backend's recomputeActionTotals view.
    const activeMotors = motorVehicles.filter((v: any) => !v.deleted_at)
    const activeMotorIds = new Set(activeMotors.map((v: any) => v.id))
    const sis = ((coverageForm as any).specified_items || []).filter((s: any) =>
      s.motor_id && activeMotorIds.has(s.motor_id) && !s.deleted_at)
    const sumInsured = activeMotors.reduce((s, v) => s + num(v.coverage_value), 0)
      + sis.reduce((s: number, x: any) => s + num(x.sum_insured), 0)
    const premium = activeMotors.reduce((s, v) => {
      const own = num(v.calculated_value)
      const ext = MOTOR_PREMIUM_FIELDS.reduce((a, f) => a + num(v[f]), 0)
      return s + own + ext
    }, 0)
      + sis.reduce((s: number, x: any) => s + num(x.calculated_value ?? x.calculatedValue), 0)
    return { sumInsured, premium }
  })()

  // Load motor vehicles when motor coverage + has _dbId (existing coverage)
  useEffect(() => {
    if (!isMotorCoverage || !policyId) return
    const dbId = (coverageForm as any)._dbId
    if (dbId) {
      setMotorLoading(true)
      Promise.all([
        apiClient.get(`/policies/${policyId}/coverages/${dbId}/motor`),
        apiClient.get(`/policies/${policyId}/coverages/${dbId}/available-vehicles`),
      ]).then(([mRes, vRes]) => {
        setMotorVehicles(mRes.data.data ?? [])
        setAvailableVehicles(vRes.data.data ?? [])
      }).catch(() => {}).finally(() => setMotorLoading(false))
    } else if (policyId) {
      // New coverage (no _dbId yet) — pass the picked risk_address_id as a
      // query hint so the backend can still filter the dropdown. Without
      // this the unsaved-coverage path returned no risk-scoping and the
      // dropdown stayed empty.
      // action_id comes from the /edit?action_id= URL (PolicyDetailPage links
      // with it) — the dropdown is action-wise and there is no coverage row
      // yet for the backend to read the action off, so without this hint the
      // list falls back to the newest action rather than the one on screen.
      const ra = (coverageForm as any).risk_address_id
      const act = new URLSearchParams(window.location.search).get('action_id')
      const qs = new URLSearchParams()
      if (ra) qs.set('risk_address_id', String(ra))
      if (act) qs.set('action_id', act)
      const params = qs.toString() ? `?${qs.toString()}` : ''
      apiClient.get(`/policies/${policyId}/coverages/0/available-vehicles${params}`)
        .then(r => setAvailableVehicles(r.data.data ?? []))
        .catch(() => {})
    }
  }, [isMotorCoverage, coverageForm.coverage_id, policyId, (coverageForm as any).risk_address_id])

  // Initialize Fidelity Guarantee data when coverage is loaded
  useEffect(() => {
    if (!coverageForm.coverage_id || !isFidelityGuarantee) return

    const existingFidelity = (coverageForm as any).fidelity_data || []

    // Store ALL fidelity data from database for filtering by dropdown
    setAllFidelityData(existingFidelity)

    // Detect basis from first entry's cover_type
    let detectedBasis: 'Blanket' | 'Named_Position' | '' = ''
    if (existingFidelity.length > 0) {
      const firstEntry = existingFidelity[0]
      if (firstEntry.cover_type === 'Blanket') {
        detectedBasis = 'Blanket'
      } else if (firstEntry.cover_type === 'Named_Position') {
        detectedBasis = 'Named_Position'
      }
    }

    // Filter and show entries matching the detected basis
    if (detectedBasis) {
      const filteredEntries = existingFidelity.filter((entry: any) => entry.cover_type === detectedBasis)
      setFidelityEntries(filteredEntries)
      setFidelityBasisOfCover(detectedBasis)
    } else {
      setFidelityEntries([])
    }
  }, [coverageForm.coverage_id, editingCoverageIndex, isFidelityGuarantee])

  // Initialize Theft General Questions when coverage is loaded
  useEffect(() => {
    if (!coverageForm.coverage_id || covCode !== 'THEFT') {
      setTheftQuestions({})
      return
    }
    const existingQuestions = (coverageForm as any).theft_questions || {}
    setTheftQuestions(existingQuestions)
  }, [coverageForm.coverage_id, editingCoverageIndex, covCode])

  // Populate Money coverage default text if fields are empty
  useEffect(() => {
    if (!coverageForm.coverage_id || covCode !== 'MONEY') return

    const MEMORANDA_DEFAULT = `Specified Limitations/Details
Money contained in a locked safe or strongroom situated in a building at the insured premises outside the hours during which the commercial operations of the insured are conducted.

1. In respect of the safe or strongroom described below: Description of Saferoom/strongroom
2. In respect of any safe or strongroom not specified in the schedule above the limit shall be according to the grading of such safe or strongroom as

a. No SABS grading  P 2,500.00
b. SABS category 1 grading  P 5,000.00
c. SABS category 2 grading  P 12,500.00
d. SABS category 2 HD grading   P 25,000.00
e. SABS category 2 ADM grading  P 50,000.00
f. SABS category 2 ADM grading D3   P 75,000.00
g. SABS category 3 grading  P 100,000.00
h. SABS category 4 grading  P 200,000.00
i. SABS category 5 grading  P 500,000.00

Provided that the company's liability shall not exceed the limit of indemnity shown under the schedule for the respective premises.`

    const CASH_CARRYING_DEFAULT = `It is warranted that the Company will not be liable to indemnify the Insured in respect of loss of money:-
a) In transit unless such transit is uninterrupted between the Insured's premises and their Bank/Building Society.
b) From any unattended vehicle.
c) Where such money is in transit and the following precautions are not taken
(i) Money up to P10 000 must be carried by one senior employee or principal
(ii) Money between P10 000 and P20 000 must be carried by two senior employees or principals in a vehicle
(iii) Money in excess of P20 000 must be carried by a professional armed security service organisation`

    // Check if either field is empty to trigger population
    const hasMemorandaText = coverageForm.memoranda_warranty && coverageForm.memoranda_warranty.trim() !== ''
    const hasCashText = coverageForm.cash_warranty && coverageForm.cash_warranty.trim() !== ''

    // Only populate if at least one field is empty (don't overwrite user entries)
    if (!hasMemorandaText || !hasCashText) {
      const updated = {...coverageForm}
      if (!hasMemorandaText) updated.memoranda_warranty = MEMORANDA_DEFAULT
      if (!hasCashText) updated.cash_warranty = CASH_CARRYING_DEFAULT
      setCoverageForm(updated)
    }
  }, [coverageForm.coverage_id, covCode])

  // Accepts optional vehicleIdOverride so a one-click "tap to add" UX can
  // bypass the two-step "select then click +" flow. When omitted, falls back
  // to the selectedVehicleId state (kept for the green "+" button + the
  // alternate picker that uses the searchbox-as-display pattern).
  const addVehicleToCoverage = async (vehicleIdOverride?: string) => {
    const vehicleIdToUse = vehicleIdOverride ?? selectedVehicleId
    if (!vehicleIdToUse || !policyId) return
    let dbId = (coverageForm as any)._dbId

    // First-time add: coverage hasn't been saved yet, so there's no
    // policy_coverages row to attach the motor to. Auto-create a minimal
    // coverage shell using the picked Risk Address + Coverage Type, then
    // proceed. This matches the operator's mental model — picking a vehicle
    // and clicking + should "just work" without forcing a separate
    // Add Coverage round-trip first. Existing saved-coverage path is
    // unchanged (skips this branch when dbId already exists).
    if (!dbId) {
      if (!coverageForm.risk_address_id || !coverageForm.coverage_id) {
        toast.warning('Pick Risk Address and Coverage Type before adding vehicles.')
        return
      }
      try {
        const r = await apiClient.post(`/policies/${policyId}/coverages`, {
          risk_address_id: Number(coverageForm.risk_address_id),
          coverage_id: Number(coverageForm.coverage_id),
          coverage_value: 0,
          calculated_value: 0,
        })
        dbId = r.data?.data?.id
        if (!dbId) { toast.error('Could not create coverage shell.'); return }
        // Persist _dbId on the form so subsequent edits use update path,
        // not another create. Cast through any since CoverageForm doesn't
        // declare _dbId on its public type.
        setCoverageForm({ ...coverageForm, _dbId: dbId } as any)
      } catch (e: any) {
        toast.error(e?.response?.data?.message || e?.response?.data?.error || 'Failed to save coverage shell.')
        return
      }
    }

    setMotorLoading(true)
    try {
      await apiClient.post(`/policies/${policyId}/coverages/${dbId}/motor`, {
        vehicle_id: Number(vehicleIdToUse), type_of_cover: 'Comprehensive', coverage_value: 0, calculated_value: 0,
      })
      const [mRes, vRes] = await Promise.all([
        apiClient.get(`/policies/${policyId}/coverages/${dbId}/motor`),
        apiClient.get(`/policies/${policyId}/coverages/${dbId}/available-vehicles`),
      ])
      setMotorVehicles(mRes.data.data ?? [])
      setAvailableVehicles(vRes.data.data ?? [])
      setSelectedVehicleId('')
    } catch (e: any) { toast.error(e?.response?.data?.error || 'Failed to add vehicle') }
    setMotorLoading(false)
  }

  const updateMotorRow = async (motorId: number, field: string, value: any) => {
    const dbId = (coverageForm as any)._dbId
    if (!dbId || !policyId) return
    // Snapshot the pre-edit value so a rejected save can be rolled back —
    // otherwise the optimistic UI keeps showing a change that never landed
    // (e.g. clearing an excess field on an ISSUED action, which the backend
    // blocks with a 409). Swallowing that error is what made edits silently
    // "not save": the field looked cleared, then reverted on the next reload.
    const prevValue = motorVehicles.find(m => m.id === motorId)?.[field]
    setMotorVehicles(prev => prev.map(m => m.id === motorId ? { ...m, [field]: value } : m))
    try {
      await apiClient.put(`/policies/${policyId}/coverages/${dbId}/motor/${motorId}`, { [field]: value })
    } catch (e: any) {
      setMotorVehicles(prev => prev.map(m => m.id === motorId ? { ...m, [field]: prevValue } : m))
      toast.error(
        e?.response?.data?.error ||
        e?.response?.data?.message ||
        e?.message ||
        'Failed to save change'
      )
    }
  }

  // ── Per-motor Specified Items ──
  // Accepts a field/value pair OR a fields object. The object form lets the
  // DOM/COM (22/27) per-vehicle MISC editor write Sum Insured and the
  // auto-recomputed Premium in a single optimistic setState + one PUT, so the
  // frozen Premium/Rate stay in lock-step with Sum Insured. Only the specified
  // item is touched here — the motor's own premium is never written.
  const updateMotorSpecifiedItem = async (itemId: number, fieldOrFields: string | Record<string, any>, value?: any) => {
    const dbId = (coverageForm as any)._dbId
    if (!dbId || !policyId) return
    // Find the item in state to get motor_id
    const item = (coverageForm.specified_items || []).find((s: any) => s.id === itemId)
    if (!item?.motor_id) return
    const fields: Record<string, any> = typeof fieldOrFields === 'object'
      ? fieldOrFields
      : { [fieldOrFields]: value }
    // Update local state optimistically
    setCoverageForm({
      ...coverageForm,
      specified_items: (coverageForm.specified_items || []).map((s: any) =>
        s.id === itemId ? { ...s, ...fields } : s
      ) as any,
    } as any)
    try {
      await apiClient.put(`/policies/${policyId}/coverages/${dbId}/motor/${item.motor_id}/specified-items/${itemId}`, fields)
    } catch {}
  }

  const deleteMotorSpecifiedItem = async (itemId: number) => {
    const dbId = (coverageForm as any)._dbId
    if (!dbId || !policyId) return
    const item = (coverageForm.specified_items || []).find((s: any) => s.id === itemId)
    if (!item?.motor_id) return
    if (!(await confirm({ message: 'Remove this specified item?', danger: true, confirmText: 'Remove' }))) return
    try {
      await apiClient.delete(`/policies/${policyId}/coverages/${dbId}/motor/${item.motor_id}/specified-items/${itemId}`)
      // Soft delete: keep the row (mark deleted_at) so it renders greyed with a
      // Reinstate button — consistent with every other coverage. The backend
      // DELETE already soft-deletes (sets deleted_at), it does not hard-remove.
      setCoverageForm({
        ...coverageForm,
        specified_items: (coverageForm.specified_items || []).map((s: any) =>
          s.id === itemId ? { ...s, deleted_at: new Date().toISOString() } : s
        ) as any,
      } as any)
    } catch {}
  }

  const reinstateMotorSpecifiedItem = async (itemId: number) => {
    const dbId = (coverageForm as any)._dbId
    if (!dbId || !policyId) return
    const item = (coverageForm.specified_items || []).find((s: any) => s.id === itemId)
    if (!item?.motor_id) return
    try {
      await apiClient.post(`/policies/${policyId}/coverages/${dbId}/motor/${item.motor_id}/specified-items/${itemId}/reinstate`)
      setCoverageForm({
        ...coverageForm,
        specified_items: (coverageForm.specified_items || []).map((s: any) =>
          s.id === itemId ? { ...s, deleted_at: null } : s
        ) as any,
      } as any)
    } catch {}
  }

  // Add-item flow. MotorVehicleRow renders either a master dropdown or a
  // custom free-text input; this callback persists whichever was submitted.
  // For master: specifiedCoverageId is the FK, customName is empty.
  // For custom: specifiedCoverageId is 0/null, customName carries the text.
  const addMotorSpecifiedItem = async (motorId: number, specifiedCoverageId: number, sum: number, rate: number, customName?: string) => {
    const dbId = (coverageForm as any)._dbId
    if (!dbId || !policyId) return
    const master = specifiedCoverageId ? siOptions.find(o => o.id === specifiedCoverageId) : null
    const name = master?.name ?? customName ?? ''
    const calcVal = Math.round(sum * rate) / 100
    const tempId = -Date.now()
    const optimistic: any = { id: tempId, motor_id: motorId, specified_coverage_id: specifiedCoverageId || null, name, sum_insured: sum, rate, calculated_value: calcVal, _pending: true }
    // setCoverageForm is typed as (v: CoverageForm) => void — no functional
    // updater support. We snapshot the array under coverageForm and pass
    // the next state directly. Sequential user-triggered adds are fine;
    // concurrent races aren't a real risk in this UI.
    const baseItems = (coverageForm as any).specified_items || []
    setCoverageForm({ ...coverageForm, specified_items: [...baseItems, optimistic] } as any)
    try {
      const payload: any = { sum_insured: sum, rate, calculated_value: calcVal }
      if (specifiedCoverageId) payload.specified_coverage_id = specifiedCoverageId
      if (customName)          payload.name = customName
      const r = await apiClient.post(`/policies/${policyId}/coverages/${dbId}/motor/${motorId}/specified-items`, payload)
      const newItem = r.data?.data
      setCoverageForm({
        ...coverageForm,
        specified_items: [...baseItems, { ...newItem, motor_id: motorId, name, sum_insured: sum, rate, calculated_value: newItem?.calculatedValue ?? calcVal }],
      } as any)
    } catch (e: any) {
      setCoverageForm({ ...coverageForm, specified_items: baseItems } as any)
      const errs = e?.response?.data?.errors
      const firstErr = errs && typeof errs === 'object' ? (Object.values(errs)[0] as any)?.[0] : null
      toast.error(
        e?.response?.data?.error ||
        firstErr ||
        e?.response?.data?.message ||
        e?.message ||
        'Failed to add item'
      )
    }
  }

  const upsertMotorNote = async (motorId: number, note: string) => {
    const dbId = (coverageForm as any)._dbId
    // Without a saved coverage there's no motor row to attach the note to.
    // Silently dropping it here is what made multi-line notes "disappear on
    // submit", so warn the operator instead of no-op'ing.
    if (!dbId || !policyId) {
      toast.warning('Save the coverage before adding a note for this vehicle, otherwise the note will not be stored.')
      return
    }
    setMotorVehicles(prev => prev.map(m => m.id === motorId ? { ...m, note } : m))
    try {
      await apiClient.put(`/policies/${policyId}/coverages/${dbId}/motor/${motorId}/note`, { note })
    } catch (e: any) {
      // Surface backend rejections (e.g. 422 "registration number required",
      // 404 motor not found) so a failed save is never silent.
      toast.error(
        e?.response?.data?.error ||
        e?.response?.data?.message ||
        e?.message ||
        'Failed to save the note for this vehicle.'
      )
    }
  }

  // Cancel = soft-delete. The vehicle row stays in the list (greyed out)
  // with a Reinstate option, mirroring the coverage-level cancel pattern.
  // Pro-rata refund is computed by the backend's recomputeActionTotals.
  const deleteMotorRow = async (motorId: number) => {
    const dbId = (coverageForm as any)._dbId
    if (!dbId || !policyId || !(await confirm({ message: 'Cancel this vehicle? Pro-rata refund applies on the next Rate. You can reinstate later.', danger: true, confirmText: 'Cancel vehicle', cancelText: 'Keep' }))) return
    try {
      await apiClient.delete(`/policies/${policyId}/coverages/${dbId}/motor/${motorId}`)
      // Mark the row as cancelled in local state (don't remove — the user
      // needs to see it to Reinstate). Backend already soft-deleted the
      // motor row + per-vehicle children.
      setMotorVehicles(prev => prev.map(m => m.id === motorId ? { ...m, deleted_at: new Date().toISOString() } : m))
    } catch (e: any) { toast.error('Failed to cancel') }
  }

  const reinstateMotorRow = async (motorId: number) => {
    const dbId = (coverageForm as any)._dbId
    if (!dbId || !policyId || !(await confirm('Reinstate this vehicle? Pro-rata applies on the next Rate.'))) return
    try {
      await apiClient.post(`/policies/${policyId}/coverages/${dbId}/motor/${motorId}/reinstate`)
      setMotorVehicles(prev => prev.map(m => m.id === motorId ? { ...m, deleted_at: null } : m))
    } catch (e: any) { toast.error('Failed to reinstate') }
  }

  // Wrap onSave to attach subcoverages
  const handleSave = async () => {
    // Motor + Motor-Traders coverages do NOT use policy_coverage_detail.
    // Premium / sum-insured for these lives in dedicated tables:
    //   COMMERCIALMOTOR / DOMMOTOR  → `motor` (per-vehicle rows)
    //   MOTORTRADERSEXTERNAL        → `motor_traders` (14 SI + 14 premium cols)
    //   MOTORTRADERSINTERNAL        → `motor_traders_internal` (same shape)
    // Drop every subcoverage row before submit so no phantom Main row lands
    // in policy_coverage_detail (which would inflate Rate banner + V2 Quote).
    const isCommercialMotor = covCode === 'COMMERCIALMOTOR'
    const isDomMotor = covCode === 'DOMMOTOR'
    const skipDetails = isMotorTradersExternal || isMotorTradersInternal || isCommercialMotor || isDomMotor

    // Delete-on-zero/blank: an already-saved subcoverage row (has a DB id) whose
    // values are ALL zero or blank at Save is treated as a delete of that item.
    // "No value" means every numeric field (Sum Insured / Rate / Premium /
    // discount) is <= 0 AND every text/select field (free text / ratefactor /
    // limit) is empty — so an explicit 0/0.00 counts the same as a cleared
    // field. A row with ANY non-zero number or any text is left untouched
    // (saved normally, never deleted). Scoped to persisted, not-already-deleted
    // rows. Confirm once for the whole batch, then soft-delete via the same
    // endpoint as the “−” button (stamps pro-rata + recomputes totals).
    const dbId = (coverageForm as any)._dbId
    const numVal = (v: any) => parseFloat(String(v ?? '').replace(/,/g, '')) || 0
    // Text/select fields count as "has a value" only when non-empty AND not the
    // literal "0". Some coverages (e.g. Business All Risks) persist
    // ratefactor_type / limit_id as the string "0" by default — and `!"0"` is
    // false in JS, so a plain truthiness check wrongly treated the row as
    // non-blank and silently blocked the delete (Fire worked only because its
    // ratefactor_type is empty).
    const hasText = (v: any) => { const t = String(v ?? '').trim(); return t !== '' && t !== '0' }
    const rowIsZeroOrBlank = (s: SubCoverageEntry) =>
      numVal(s.coverage_value) <= 0 &&
      numVal(s.rate) <= 0 &&
      numVal(s.calculated_value) <= 0 &&
      numVal(s.discount_surcharge_value) <= 0 &&
      // Workers Compensation / Stated Benefits carry their value in Annual
      // Wages + Deposit/Min Prem (numeric) and the "All Employees" check —
      // include them so a WC/SB row isn't treated as blank while those hold
      // data. Empty on every other coverage, so this is a no-op elsewhere.
      numVal(s.ratefactor_AnnualWages) <= 0 &&
      numVal(s.ratefactor_deposit_min_pre) <= 0 &&
      !hasText(s.ratefactor_type) &&
      !hasText(s.ratefactor_value) &&
      !hasText(s.ratefactor_value_check) &&
      !hasText(s.coverage_value_string) &&
      !hasText(s.limit_id)
    const blankedExisting = skipDetails ? [] : subEntries.filter(s =>
      s.id && !s.deleted_at && rowIsZeroOrBlank(s)
    )
    if (blankedExisting.length > 0) {
      // User cancels → abort the whole Save (nothing saved or deleted) so they
      // can restore the value or use the “−” button.
      if (!(await confirm({ message: 'Do you really want to delete this item?', danger: true, confirmText: 'Delete' }))) return
      if (dbId && policyId) {
        try {
          await Promise.all(blankedExisting.map(c =>
            apiClient.delete(`/policies/${policyId}/coverages/${dbId}/details/${c.id}`)
          ))
        } catch (err) {
          console.error('Failed to soft-delete blanked subcoverage row(s):', err)
          toast.error('Failed to delete item')
          return
        }
        const deletedIds = new Set(blankedExisting.map(c => c.id))
        setSubEntries(prev => prev.map(s =>
          deletedIds.has(s.id) ? { ...s, deleted_at: new Date().toISOString() as any } : s
        ))
      }
    }

    // Keep any row that has at least one meaningful field set (incl. rate-only rows, dropdowns like Rent/Stock)
    let filledSubs = skipDetails
      ? []
      : subEntries.filter(s =>
          !s.deleted_at && (
            s.coverage_value || s.rate || s.calculated_value ||
            s.ratefactor_type || s.ratefactor_value || s.coverage_value_string ||
            s.discount_surcharge_value || s.limit_id
          )
        )

    // Motor coverages: the sub-coverage row is a label, not a data source.
    // Stamp the motor schedule aggregate (already scoped by risk_address +
    // action_id via the loaded motorVehicles) onto the first sub-cov row so
    // policy_coverage_detail matches what's displayed. Other rows get zeroed
    // so the section total doesn't double-count.
    if (isMotorCoverage && filledSubs.length > 0) {
      filledSubs = filledSubs.map((s, i) => i === 0
        ? { ...s, coverage_value: motorAggregate.sumInsured.toFixed(2), calculated_value: motorAggregate.premium.toFixed(2) }
        : { ...s, coverage_value: '0', calculated_value: '0' }
      )
    }

    // Endorse pro-rata correctness: mark each sub-coverage row as changed vs the
    // value it was loaded with. On an endorsement the backend pro-rates only the
    // lines the operator actually added/edited — a carried-forward line left
    // untouched (current value === loaded value, and it already has a DB id) is
    // marked _changed=false so it is still saved but NOT charged. Genuinely new
    // rows (no DB id) and any value edit stay _changed=true and are charged as
    // before. We only ever set false when we're SURE nothing changed, so this
    // can never under-charge a real edit; non-endorse actions ignore the flag.
    const numOr0 = (v: any) => parseFloat(String(v ?? '').replace(/,/g, '')) || 0
    filledSubs = filledSubs.map((s: any) => {
      const rid = Number(s.id ?? 0)
      const orig = rid > 0 ? subOriginalsRef.current[String(rid)] : undefined
      const unchanged = !!orig
        && numOr0(s.coverage_value) === orig.cv
        && numOr0(s.calculated_value) === orig.calc
      return { ...s, _changed: !unchanged }
    })

    // Only include extensions that have actual values filled (matching backend pattern)
    const filledExts = extEntries.filter(e =>
      e.extention_coverage_value
        || e.extention_text_value
        || e.extention_limit_id
        || e.extention_discount_surcharge_value
        || e.extention_calculated_value
        || e.extention_sum_insured
    ).map((e: ExtensionEntry) => {
      // Ensure type is set correctly (from API it's already correct)
      // If missing, default to 'Extention'
      return {
        ...e,
        type: e.type || 'Extention'
      }
    })
    // A specified item counts when it has a master pick AND either a
    // sum_insured or a manually entered premium. Legacy Submit.php sums
    // policy_specified_items.calculated_value regardless of auto vs manual.
    const filledSi = siEntries.filter(s =>
      !s.deleted_at && s.specified_coverage_id && (s.sum_insured || s.calculated_value)
    )
    // Motor-attached specified items are managed via per-vehicle endpoints,
    // not the coverage bulk save. Preserve them on motor coverages so the UI
    // doesn't lose per-vehicle misc items when the coverage is saved.
    const motorAttachedSi = isMotorCoverage
      ? ((coverageForm as any).specified_items || []).filter((s: any) => s.motor_id)
      : []
    const filledExc = excEntries
      .filter(e => e.excesses || e.min_percent || e.min_amt || e.discount_surcharge || e.discount_surcharge_value || e.premium)
      // Backend validates these as `nullable|numeric`. Two ways a bad value
      // reaches it: (1) an empty string — not null, so `numeric` runs against
      // it and fails ("...must be a number"); (2) a formatted/legacy value the
      // edit-load hydrator carried back from policy_extention_detail (e.g.
      // "5,000") that was never re-typed, so the comma survives. Run each
      // numeric field through unformatNumber (same normaliser as MoneyInput),
      // then omit anything that isn't a finite number so the backend's `?? 0`
      // defaults apply instead of submitting "" / a comma'd string.
      .map(e => {
        const num = (v: any) => {
          if (v === '' || v === null || v === undefined) return undefined
          const clean = unformatNumber(String(v))
          return clean !== '' && Number.isFinite(Number(clean)) ? clean : undefined
        }
        return {
          ...e,
          min_percent: num(e.min_percent),
          min_amt: num(e.min_amt),
          discount_surcharge_value: num(e.discount_surcharge_value),
          premium: num(e.premium),
        }
      })
    // Fidelity Guarantee: keep rows that have at least one field filled and are not soft-deleted
    const filledFidelity = fidelityEntries.filter(f =>
      !f.deleted_at && (f.cover_type || f.cover_area || f.name_and_position || f.designation || f.length_of_service || f.amount_to_be_guaranteed || f.premium)
    )
    // Main coverage Sum Insured = sum of subcoverage sum insureds (legacy parity)
    const totalValue = filledSubs.reduce((sum, s) => sum + (parseFloat(s.coverage_value) || 0), 0)
    // Main coverage Premium = subcoverages + specified items + extensions.
    // Mirrors graphiteBWV8 Submit.php calculatePremium() where
    // $premiumNew = sum_calculated_value + sum_specified_items + sum_exts_calculated_value + ...
    const subsPremium = filledSubs.reduce((sum, s) => sum + (parseFloat(s.calculated_value) || 0), 0)
    const siPremium   = filledSi.reduce((sum, s) => sum + (parseFloat(s.calculated_value) || 0), 0)
    // DomCom (product 7/8): only type='Extention' rows are premium. Excess /
    // Perils / Memoranda / FirstAmountPayable / BurglarAlarmWarranty reuse
    // extention_calculated_value for a limit or excess amount, and the backend
    // canonical (recomputeActionTotals) excludes them. Without this filter the
    // saved policy_coverages.calculated_value picked up the limit as premium
    // (Business Interruption stamping 1,000,720.01 instead of 720.01). Same
    // filter as the live total below and the Added Coverages row.
    const extsPremium = filledExts
      .filter(e => !isDomComProduct || ((e as any).type || 'Extention') === 'Extention')
      .reduce((sum, e) => sum + (parseFloat(e.extention_calculated_value) || 0), 0)
    const fidelityPremium = filledFidelity.reduce((sum, f) => sum + (parseFloat(f.premium) || 0), 0)
    const totalPremium = subsPremium + siPremium + extsPremium + fidelityPremium

    const hasAnyPremium = filledSubs.length > 0 || filledSi.length > 0 || filledExts.length > 0 || filledFidelity.length > 0

    const updatedForm: CoverageForm = {
      ...coverageForm,
      subcoverages: filledSubs.length > 0 ? filledSubs : undefined,
      extensions: filledExts.length > 0 ? filledExts : undefined,
      specified_items: isMotorCoverage
        ? ([...motorAttachedSi, ...filledSi].length > 0 ? [...motorAttachedSi, ...filledSi] : undefined)
        : (filledSi.length > 0 ? filledSi : undefined),
      excesses: filledExc.length > 0 ? (filledExc as any) : undefined,
      // Fidelity Guarantee stores data in policy_coverages_data, not policy_coverage_detail
      // So don't send coverage_value/calculated_value for Fidelity Guarantee.
      // Motor Traders Ext/Int store the 14 SI + 14 premium columns in their
      // own table — force coverage_value / calculated_value to 0 on the
      // parent policy_coverages row so it doesn't add a phantom Main premium
      // on top of the motor_traders sum (the 4.00 issue).
      coverage_value: isFidelityGuarantee
        ? undefined
        : ((isMotorTradersExternal || isMotorTradersInternal)
          ? '0'
          : (filledSubs.length > 0
            ? String(totalValue)
            : (isMotorCoverage && !coverageForm.coverage_value ? '0' : coverageForm.coverage_value))),
      calculated_value: isFidelityGuarantee
        ? undefined
        : ((isMotorTradersExternal || isMotorTradersInternal)
          ? '0'
          : (hasAnyPremium ? totalPremium.toFixed(2) : coverageForm.calculated_value)),
      // Explicitly preserve Workers Compensation fields
      ratefactor_type: coverageForm.ratefactor_type,
      ratefactor_value: coverageForm.ratefactor_value,
      ratefactor_value_check: coverageForm.ratefactor_value_check,
      ratefactor_AnnualWages: coverageForm.ratefactor_AnnualWages,
      ratefactor_deposit_min_pre: coverageForm.ratefactor_deposit_min_pre,
      // Public Liability retroactive date
      retroactive_date: coverageForm.retroactive_date,
      // Fidelity Guarantee data (stored in policy_coverages_data)
      fidelity_data: isFidelityGuarantee && filledFidelity.length > 0 ? filledFidelity : undefined,
      // Theft General Questions (stored in theft_questions table)
      theft_questions: covCode === 'THEFT' && Object.keys(theftQuestions).length > 0 ? theftQuestions : undefined,
      // Goods In Transit field (stored in policy_coverages table)
      property_business_being: covCode === 'GOODSINTRANSIT' ? (coverageForm as any).property_business_being : undefined,
      // Office Content: Burglar Alarm Warranty text (stored in policy_coverages table)
      burglar_alarm_warranty: (() => {
        const burglarExt = extEntries.find(e =>
          (e.s_ScreenName || '').toLowerCase().includes('burglar alarm warranty')
        )
        const isTaken = burglarExt?.is_taken !== false  // default true for backward compatibility
        return burglarExt && isTaken ? (coverageForm as any).burglar_alarm_warranty : undefined
      })(),
      // Stated Benefits for Employees Liability / Workers Compensation (stored in policy_coverages table)
      stated_benefits: (coverageForm as any).stated_benefits || undefined,
    }
    setCoverageForm(updatedForm)

    // Show success message
    const isEditing = editingCoverageIndex !== null && editingCoverageIndex !== undefined
    setSuccessMessage(isEditing ? 'Coverage edited successfully' : 'Coverage added successfully')
    setTimeout(() => setSuccessMessage(null), 3000)

    onSave(updatedForm)

    // Motor Traders Ext/Int — persist the panel data against this coverage's
    // _dbId via dedicated endpoints. Existing coverage: save inline. New
    // coverage: stash mtData + a snapshot of existing _dbIds; the
    // pendingMtSave useEffect picks up the new _dbId as soon as the parent
    // adds the saved row to savedCoverages and fires the PUT.
    if (isMotorTradersCoverage && Object.keys(mtData).length > 0 && policyId) {
      const dbIdForMt = (updatedForm as any)._dbId
      if (dbIdForMt) {
        const saver = isMotorTradersExternal ? saveMotorTradersExternal : saveMotorTradersInternal
        // Surface a rejected save instead of swallowing it. The endpoint
        // answers 409 when the selected action isn't a QUOTE (an ENDORSE the
        // operator already issued, an ISSUED anniversary, …). Logging that to
        // the console while still flashing "Coverage edited successfully" is
        // what made Motor Traders Ext/Int edits look saved and then come back
        // unchanged on the next open — same failure mode already fixed for
        // per-vehicle motor rows in updateMotorRow.
        saver(policyId, dbIdForMt, mtData).catch(err => {
          setSuccessMessage(null)
          toast.error(
            err?.response?.data?.error ||
            err?.response?.data?.message ||
            err?.message ||
            'Motor Traders details could not be saved'
          )
        })
      } else {
        setPendingMtSave({
          kind: isMotorTradersExternal ? 'external' : 'internal',
          data: { ...mtData },
          coverageId: coverageForm.coverage_id as number,
          riskAddressId: ((coverageForm as any).risk_address_id as number) ?? null,
          existingDbIds: savedCoverages.map((c: any) => (c as any)._dbId).filter(Boolean) as number[],
        })
      }
    }
  }

  // Cancel edit mode — reset form and notify parent
  const handleCancelEdit = () => {
    setCoverageForm({ ...INITIAL_COVERAGE })
    setSubEntries([])
    setExtEntries([])
    setSiEntries([])
    setExcEntries([])
    setFidelityEntries([])
    setTheftQuestions({})
    setErrors(() => ({}))
    onEdit(-1) // Signal to parent to exit edit mode
  }

  return (
    <div className="space-y-6">
      {/* Saved coverages */}
      {savedCoverages.length > 0 && (
        <Section title={`Added Coverages (${savedCoverages.length})`} action={saving ? <span className="inline-flex items-center gap-1 text-xs text-primary"><span className="w-3 h-3 border-2 border-primary border-t-transparent rounded-full animate-spin" /> Saving...</span> : undefined}>
          <table className="w-full text-sm">
            <thead>
              <tr className="text-left text-xs text-ink-muted border-b">
                <th className="pb-2">Coverage</th>
                <th className="pb-2">Risk Address</th>
                <th className="pb-2 text-right">Sum Insured</th>
                <th className="pb-2 text-right">Rate %</th>
                <th className="pb-2 text-right">Premium</th>
                <th className="pb-2"></th>
              </tr>
            </thead>
            <tbody>
              {savedCoverages.map((c, i) => (
                <SavedCoverageRow key={i} coverage={c} index={i} savedAddresses={savedAddresses} onDelete={onDelete} onEdit={onEdit} onCancel={handleCancelEdit} onReinstate={onReinstate} isEditing={editingCoverageIndex === i} policyId={policyId} isDomComProduct={isDomComProduct} />
              ))}
              <tr className="font-semibold">
                <td colSpan={4} className="py-2 text-right">Total Premium:</td>
                <td className="py-2 text-right">P {(() => {
                  // Use the backend canonical total (= policy_actions.annual_premium
                  // for the resolved action) when available so this row reconciles
                  // with the step badge / Rate banner / Policy Detail Total Coverage
                  // Premium / V2 Quote. The local per-coverage sum can drift from
                  // canonical because it doesn't see motor.premium_* extensions or
                  // policy_coverages_data (Fidelity) rows. Fall back to local sum
                  // during initial create (no action yet).
                  if ((backendTotalPremium ?? 0) > 0) {
                    return (backendTotalPremium as number).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                  }
                  const num = (v: any) => parseFloat(v ?? 0) || 0
                  const localSum = savedCoverages.reduce((sum, c) => {
                    // Motor: backend aggregates the whole section into
                    // calculated_value (per-vehicle rows aren't on this object).
                    if (MOTOR_CODES.includes(((c as any).coverage_code || '').toUpperCase())) {
                      return sum + num(c.calculated_value)
                    }
                    const subPremium = (c.subcoverages || []).reduce((s, sub) => s + num(sub.calculated_value), 0)
                    // DomCom (product 7/8): only type='Extention' rows carry premium.
                    // Excess / Perils / Memoranda / FirstAmountPayable / BurglarAlarmWarranty
                    // rows reuse extention_calculated_value for a limit or excess amount and
                    // are excluded from the canonical annual — see the same filter on the
                    // edit-form live total and recomputeActionTotals.
                    const extPremium = ((c as any).extensions || [])
                      .filter((e: any) => !isDomComProduct || (e.type || 'Extention') === 'Extention')
                      .reduce((s: number, e: any) => s + num(e.extention_calculated_value ?? e.calculatedValue), 0)
                    const siPremium = ((c as any).specified_items || []).filter((x: any) => !x.deleted_at).reduce((s: number, x: any) => s + num(x.calculated_value ?? x.calculatedValue), 0)
                    const motorPremium = ((c as any).motor || (c as any).vehicles || []).reduce((s: number, v: any) => {
                      const vSpecified = (v.specifiedItems || v.specified_items || []).reduce((ss: number, x: any) => ss + num(x.calculated_value ?? x.calculatedValue), 0)
                      return s + num(v.calculated_value ?? v.calculatedValue) + vSpecified
                    }, 0)
                    // Fidelity Guarantee premium lives in policy_coverages_data (fidelity_data).
                    const fidelityPremium = ((c as any).fidelity_data || []).filter((f: any) => !f.deleted_at).reduce((s: number, f: any) => s + num(f.premium), 0)
                    const calc = (subPremium + extPremium + siPremium + motorPremium + fidelityPremium) || num(c.calculated_value)
                    return sum + calc
                  }, 0)
                  return localSum.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                })()}</td>
                <td></td>
              </tr>
            </tbody>
          </table>
          {false && (
            <div className="hidden"></div>
          )}
        </Section>
      )}

      {/* Add coverage form */}
      <div id="add-coverage-form" className="scroll-mt-24">
      {(() => {
        const covCode = availableCoverages.find((c: any) => c.id === coverageForm.coverage_id)?.s_CoverageCode?.toUpperCase() || ''
        return (
      <>
      {successMessage && (
        <div className="mb-4 p-3 bg-status-success-bg border border-status-success-fg rounded-lg flex items-center gap-2">
          <svg className="w-5 h-5 text-status-success-fg" fill="currentColor" viewBox="0 0 20 20">
            <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
          </svg>
          <span className="text-sm text-status-success-fg font-medium">{successMessage}</span>
        </div>
      )}
      <Section title="Add Coverage">
        {savedAddresses.length === 0 ? (
          <p className="text-sm text-status-warning-fg bg-status-warning-bg p-3 rounded">Please add at least one Risk Address in the previous step before adding coverages.</p>
        ) : (
          <>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <SelectField label="Risk Address" value={coverageForm.risk_address_id} required
                onChange={v => {
                  update('risk_address_id', v ? parseInt(v) : null)
                  setErrors(p => ({ ...p, cov_duplicate_family: '' }))
                }}
                options={savedAddresses.map(a => ({ id: a.id, name: a.address_name }))}
                error={errors.cov_risk_address_id} />

              <SelectField label="Coverage Type" value={coverageForm.coverage_id} required
                onChange={v => {
                  const covId = v ? parseInt(v) : null
                  const cov = availableCoverages.find((c: any) => c.id === covId)
                  setCoverageForm({
                    ...coverageForm,
                    coverage_id: covId,
                    coverage_name: cov?.s_CoverageName || cov?.s_CoverageCode || '',
                    coverage_value: '',
                    rate: '',
                    calculated_value: '',
                    subcoverages: [],  // Clear saved subcoverages so fresh template is fetched
                    retroactive_date: covId === 21 ? coverageForm.retroactive_date : '', // Keep for PL, clear for others
                  })
                  setTheftQuestions({})  // Reset theft questions when coverage type changes
                  setErrors(p => ({ ...p, cov_coverage_id: '', cov_duplicate_family: '' }))
                }}
                options={(availableCoverages ?? []).map((c: any) => ({ id: c.id, name: c.s_CoverageName || c.s_CoverageCode }))}
                error={errors.cov_coverage_id} />

              {/* ── Retroactive Date (Public Liability only) ────── */}
              {coverageForm.coverage_id === 21 && (
                <div className="flex flex-col gap-1">
                  <label className="text-sm font-medium text-ink-muted">Retroactive Date</label>
                  <input
                    key={`retroactive-date-${coverageForm.coverage_id}`}
                    type="date"
                    value={coverageForm.retroactive_date ?? ''}
                    onChange={e => {
                      setCoverageForm({ ...coverageForm, retroactive_date: e.target.value })
                      setErrors(p => ({ ...p, cov_retroactive_date: '' }))
                    }}
                    className="w-full px-3 py-2 border border-line rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                  />
                </div>
              )}
            </div>

            {/* ── Goods In Transit: Property Being Field ─────────────────────
                For GOODSINTRANSIT coverage only. Stores text description in policy_coverages.property_business_being.
                Appears BEFORE subcoverages section, right after coverage type selection. */}
            {covCode === 'GOODSINTRANSIT' && coverageForm.coverage_id && (
              <div className="border rounded-lg overflow-hidden">
                <div className="px-4 py-3 bg-status-info-bg border-b">
                  <label className="block text-sm font-semibold text-ink-muted mb-1">
                    All property usual to the Insured's business being:
                  </label>
                  <p className="text-xs text-ink-muted mb-3">
                    (including ropes, tarpaulins and packing materials in connection with the transit)
                  </p>
                  <textarea
                    value={(coverageForm as any).property_business_being || ''}
                    onChange={e => setCoverageForm({ ...coverageForm, property_business_being: e.target.value } as any)}
                    placeholder="Enter description of property..."
                    rows={4}
                    className="w-full px-3 py-2 text-sm border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary" />
                </div>
              </div>
            )}

            {/* ── Subcoverages (Description of cover) ─────────── */}
            {subLoading && (
              <div className="flex items-center gap-2 py-4">
                <div className="w-4 h-4 border-2 border-primary border-t-transparent rounded-full animate-spin" />
                <span className="text-sm text-ink-muted">Loading subcoverages...</span>
              </div>
            )}

            {/* Motor Traders Ext/Int: data lives entirely in
                motor_traders / motor_traders_internal (14 SI + 14 premium cols).
                Suppress the legacy "Main" subcoverage row inherited from
                tb_cvgpccoverages so the Add Coverage panel doesn't write a
                stray policy_coverage_detail row (which would surface as a
                4.00 phantom Premium in the wizard / Rate / V2 Quote). */}
            {!subLoading && subEntries.length > 0 && !isMotorTradersExternal && !isMotorTradersInternal && (
              <div className="border rounded-lg overflow-hidden">
                {(() => {
                  let currentGroup = ''
                  // Sort subcoverages: group by name, new rows first within each group
                  const sortedEntries = [...subEntries].sort((a, b) => {
                    // Always ensure "Description of cover" comes before "Extensions and Clauses" (for all coverages)
                    const groupA = (a.s_CoverageGroupName ?? '').toLowerCase()
                    const groupB = (b.s_CoverageGroupName ?? '').toLowerCase()
                    if (groupA !== groupB) {
                      if (groupA === 'description of cover') return -1
                      if (groupB === 'description of cover') return 1
                      if (groupA === 'extensions and clauses') return 1
                      if (groupB === 'extensions and clauses') return -1
                    }
                    // For Computer Equipment: sort Description of cover entries in specific order
                    if (covCode === 'COMPUTEREQUIPMENT' && groupA === 'description of cover') {
                      const screenA = (a.s_ScreenName ?? '').toLowerCase()
                      const screenB = (b.s_ScreenName ?? '').toLowerCase()
                      const order = ['material damage', 'consequential loss', 'increased cost', 'reinstatement']
                      const indexA = order.findIndex(name => screenA.includes(name))
                      const indexB = order.findIndex(name => screenB.includes(name))
                      if (indexA !== -1 && indexB !== -1) return indexA - indexB
                    }
                    // For Electronics Equipment: group by s_CoverageGroupName to keep related items together
                    if (covCode === 'ELECTRONICEQUIPMENT') {
                      if (groupA !== groupB) {
                        return groupA.localeCompare(groupB)
                      }
                    }
                    // First, sort by s_ScreenName (Buildings, Rent, Plant, etc.)
                    const nameCompare = (a.s_ScreenName ?? '').localeCompare(b.s_ScreenName ?? '')
                    if (nameCompare !== 0) return nameCompare
                    // Within same name, put new custom rows (isCustom=true) first, then existing rows
                    if (a.isCustom === true && b.isCustom !== true) return -1
                    if (a.isCustom !== true && b.isCustom === true) return 1
                    // Maintain original order within same group
                    return 0
                  })

                  // Cache field configuration per coverage type (s_ScreenName)
                  // All rows of the same type will use the same field layout
                  const fieldConfigCache: Record<string, any> = {}
                  sortedEntries.forEach(sub => {
                    const key = sub.s_ScreenName ?? ''
                    if (!fieldConfigCache[key]) {
                      fieldConfigCache[key] = getSubcoverageSecondField(
                        sub.s_LimitTypeCode,
                        (sub as any).dropdown_options,
                        sub.s_CoverageCode,
                        sub.s_ParentCoverageCode,
                        (sub as any).radio_options,
                        sub.s_ScreenName
                      )
                    }
                  })

                  // Track seen combinations: first occurrence gets +, duplicates get -
                  const seenCombinations: Record<string, boolean> = {}
                  const seenHeadings: Record<string, boolean> = {}

                  return sortedEntries.map((sub) => {
                    // Find actual index in subEntries by object reference — fresh rows
                    // all share id=0 / detail_id=undefined, so property-based findIndex
                    // collapsed every keystroke onto subEntries[0] (Buildings).
                    const idx = subEntries.indexOf(sub)
                    if (idx === -1) return null // Safety check

                    const showGroupHeader = sub.s_CoverageGroupName && sub.s_CoverageGroupName !== currentGroup
                    if (sub.s_CoverageGroupName && sub.s_CoverageGroupName !== currentGroup) currentGroup = sub.s_CoverageGroupName

                    // Combination key: s_ScreenName + s_CoverageGroupName (mirrors old Graphite logic)
                    const combination = `${sub.s_ScreenName}_${sub.s_CoverageGroupName}`
                    const isFirstOccurrence = !seenCombinations[combination]
                    if (isFirstOccurrence) seenCombinations[combination] = true

                    // Track heading names to avoid duplicate heading displays
                    const isFirstHeading = sub.s_SubCoverageMainName === 'Heading' && !seenHeadings[sub.s_ScreenName ?? '']
                    if (sub.s_SubCoverageMainName === 'Heading') seenHeadings[sub.s_ScreenName ?? ''] = true

                    // Skip rendering duplicate heading rows entirely
                    if (sub.s_SubCoverageMainName === 'Heading' && !isFirstHeading) return null

                    return (
                      <div key={`${sub.id}-${idx}`}>
                        {showGroupHeader && (
                          <div className="bg-surface-2 px-4 py-2 border-b">
                            <h4 className="text-sm font-bold text-ink">{sub.s_CoverageGroupName}</h4>
                          </div>
                        )}
                        {sub.s_SubCoverageMainName === 'Heading' && isFirstHeading ? (
                          <div className="px-4 py-2 bg-surface-2 border-b">
                            <span className="text-sm font-bold text-ink-muted">{sub.s_ScreenName}</span>
                          </div>
                        ) : (covCode === 'WORKERSCOMPENSATION' || covCode === 'STATEDBENEFITS') ? (
                          /* ── Workers Compensation / Stated Benefits row ──
                             Both coverages share the legacy admin layout:
                             Description | Free-text note (composite ratefactor
                             fields on the "Free text" master row) | Discount
                             | Discount Type | Discount Value | Premium | (+/-).
                             Premium is hand-entered — UW computes the
                             discount/surcharge themselves; we just persist
                             the inputs for audit.
                             Stated Benefits is a SEPARATE coverage record
                             (own coverage_id = 13, own master subcoverages
                             "Free text" and "Burns disfigurement") that
                             reuses the same per-row composite shape, so the
                             render path is shared but the saved
                             policy_coverages row stays under STATEDBENEFITS. */
                          <div className={`border-b ${sub.deleted_at ? 'bg-status-danger-bg opacity-60' : ''}`}>
                            <div className="px-4 py-3 hover:bg-status-warning-bg/40 transition">
                              <div className="grid grid-cols-12 gap-2 items-start">
                                {/* Description — shows the master sub-coverage name */}
                                <div className="col-span-2">
                                  <label className="block text-[10px] text-ink-faint mb-0.5">Description</label>
                                  <div className="w-full px-2 py-1 text-xs bg-surface-2 border border-line rounded-md text-ink-muted">
                                    {sub.s_ScreenName ?? ''}
                                  </div>
                                </div>

                                {/* Free Text field */}
                                <div className="col-span-2">
                                  <label className="block text-[10px] text-ink-faint mb-0.5">Free Text</label>
                                  <textarea
                                    rows={2}
                                    value={sub.coverage_value_string ?? ''}
                                    onChange={e => updateSubEntryRaw(idx, 'coverage_value_string', e.target.value)}
                                    placeholder="Enter text..."
                                    readOnly={!!sub.deleted_at}
                                    className="w-full px-2 py-1 text-xs border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary"
                                  />
                                  {sub.s_ScreenName === 'Free text' && (
                                    <div className="mt-2 p-2 bg-status-warning-bg border border-status-warning-fg rounded space-y-2">
                                      <label className="flex items-center gap-2 text-[11px]">
                                        <input type="checkbox"
                                          checked={sub.ratefactor_value_check === 'All Employees'}
                                          onChange={e => updateSubEntryRaw(idx, 'ratefactor_value_check', e.target.checked ? 'All Employees' : '')}
                                          disabled={!!sub.deleted_at}
                                          className="w-3.5 h-3.5 border rounded" />
                                        <span className="text-ink-muted">All Employees</span>
                                        <span className="ml-auto text-[10px] text-ink-muted font-semibold">OR</span>
                                      </label>
                                      <input type="number"
                                        value={sub.ratefactor_value ?? ''}
                                        onChange={e => updateSubEntryRaw(idx, 'ratefactor_value', e.target.value)}
                                        placeholder="No of Employees"
                                        readOnly={!!sub.deleted_at}
                                        className="w-full px-2 py-1 text-xs border rounded" />
                                      <select value={sub.ratefactor_type ?? ''}
                                        onChange={e => updateSubEntryRaw(idx, 'ratefactor_type', e.target.value)}
                                        disabled={!!sub.deleted_at}
                                        className="w-full px-2 py-1 text-xs border rounded">
                                        <option value="">Select Individual Cover</option>
                                        <option value="Yes">Yes</option>
                                        <option value="No">No</option>
                                      </select>
                                      <MoneyInput
                                        value={sub.ratefactor_AnnualWages ?? ''}
                                        onChange={v => updateSubEntryRaw(idx, 'ratefactor_AnnualWages', v)}
                                        placeholder="Annual Wages"
                                        className="w-full px-2 py-1 text-xs border rounded" />
                                      <MoneyInput
                                        value={sub.ratefactor_deposit_min_pre ?? ''}
                                        onChange={v => updateSubEntryRaw(idx, 'ratefactor_deposit_min_pre', v)}
                                        placeholder="Deposit and Min Premium"
                                        className="w-full px-2 py-1 text-xs border rounded" />
                                    </div>
                                  )}
                                </div>

                                {/* Sum Insured — for Workers Compensation only */}
                                {covCode === 'WORKERSCOMPENSATION' && (
                                  <div className="col-span-1">
                                    <label className="block text-[10px] text-ink-faint mb-0.5">Sum Insured</label>
                                    <MoneyInput
                                      value={sub.coverage_value ?? ''}
                                      onChange={v => updateSubEntryRaw(idx, 'coverage_value', v)}
                                      placeholder="0.00"
                                      readOnly={!!sub.deleted_at}
                                      className="w-full px-2 py-1 text-xs border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary"
                                    />
                                  </div>
                                )}

                                {/* Select Discount (None / Discount / Surcharge) */}
                                <div className="col-span-2">
                                  <label className="block text-[10px] text-ink-faint mb-0.5">Select Discount</label>
                                  <select
                                    value={sub.discount_surcharge ?? ''}
                                    onChange={e => updateSubEntryRaw(idx, 'discount_surcharge', e.target.value)}
                                    className="w-full px-2 py-2 text-sm border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary">
                                    <option value="">- Select -</option>
                                    <option value="Discount">Discount</option>
                                    <option value="Surcharge">Surcharge</option>
                                  </select>
                                </div>

                                {/* Discount Type (Flat / Percentage) */}
                                <div className="col-span-1">
                                  <label className="block text-[10px] text-ink-faint mb-0.5">Type</label>
                                  <select
                                    value={sub.discount_surcharge_type ?? ''}
                                    onChange={e => updateSubEntryRaw(idx, 'discount_surcharge_type', e.target.value)}
                                    disabled={!sub.discount_surcharge}
                                    className="w-full px-2 py-2 text-sm border border-line rounded-md disabled:bg-surface-2 disabled:text-ink-faint">
                                    <option value="">-</option>
                                    <option value="Flat">Flat</option>
                                    <option value="Percentage">%</option>
                                  </select>
                                </div>

                                {/* Discount/Surcharge Value */}
                                <div className="col-span-2">
                                  <label className="block text-[10px] text-ink-faint mb-0.5">Disc Value</label>
                                  <MoneyInput
                                    value={sub.discount_surcharge_value ?? ''}
                                    onChange={v => updateSubEntryRaw(idx, 'discount_surcharge_value', v)}
                                    className="w-full px-2 py-2 text-sm border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary"
                                  />
                                </div>

                                {/* Premium — hand-entered, no auto-math */}
                                <div className="col-span-2">
                                  <label className="block text-[10px] text-ink-faint mb-0.5">Premium</label>
                                  <MoneyInput
                                    value={sub.calculated_value}
                                    onChange={v => updateSubEntryRaw(idx, 'calculated_value', v)}
                                    className="w-full px-2 py-2 text-sm border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary"
                                  />
                                </div>

                                {/* +/- (same semantics as standard rows) */}
                                <div className="col-span-1 flex justify-end pt-4">
                                  {sub.deleted_at ? (
                                    <button
                                      type="button"
                                      onClick={() => reinstateRow(idx)}
                                      className="w-9 h-9 flex items-center justify-center rounded-md bg-primary text-white hover:bg-primary text-sm font-bold"
                                      title="Reinstate row"
                                    >↻</button>
                                  ) : isFirstOccurrence ? (
                                    <button
                                      type="button"
                                      onClick={() => addDuplicateRow(idx)}
                                      className="w-9 h-9 flex items-center justify-center rounded-md bg-primary text-white hover:bg-primary text-lg font-bold"
                                      title="Add another row"
                                    >+</button>
                                  ) : (
                                    <button
                                      type="button"
                                      onClick={() => removeRow(idx)}
                                      className="w-9 h-9 flex items-center justify-center rounded-md bg-status-danger-fg text-white hover:bg-status-danger-fg text-lg font-bold"
                                      title="Remove row"
                                    >-</button>
                                  )}
                                </div>
                              </div>
                            </div>
                          </div>
                        ) : covCode === 'HOUSEOWNERS' ? (
                          /* ── House Holders row ──
                             Display layout: Sum Insured | Free Text | Select Discount | Discount Type | Discount Value | Premium | (+/-) */
                          <div className={`border-b ${sub.deleted_at ? 'bg-status-danger-bg opacity-60' : ''}`}>
                            <div className="px-4 py-3 hover:bg-status-warning-bg/40 transition">
                              <div className="grid grid-cols-12 gap-2 items-start">
                                {/* Sum Insured — editable input */}
                                <div className="col-span-1">
                                  <label className="block text-[10px] text-ink-faint mb-0.5">{covCode === 'PUBLICLIABILITY' ? 'Limit of Liability' : 'Sum Insured'}</label>
                                  <MoneyInput
                                    value={sub.coverage_value ?? ''}
                                    onChange={v => updateSubEntryRaw(idx, 'coverage_value', v)}
                                    readOnly={!!sub.deleted_at}
                                    className="w-full px-2 py-1.5 text-xs border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary"
                                  />
                                </div>

                                {/* Free Text — editable text area */}
                                <div className="col-span-3">
                                  <label className="block text-[10px] text-ink-faint mb-0.5">Free Text</label>
                                  <textarea
                                    rows={2}
                                    value={sub.ratefactor_value ?? ''}
                                    onChange={e => updateSubEntryRaw(idx, 'ratefactor_value', e.target.value)}
                                    placeholder="Enter description..."
                                    readOnly={!!sub.deleted_at}
                                    className="w-full px-2 py-1 text-xs border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary"
                                  />
                                </div>

                                {/* Select Discount */}
                                <div className="col-span-2">
                                  <label className="block text-[10px] text-ink-faint mb-0.5">Select Discount</label>
                                  <select
                                    value={sub.discount_surcharge ?? ''}
                                    onChange={e => updateSubEntryRaw(idx, 'discount_surcharge', e.target.value)}
                                    disabled={!!sub.deleted_at}
                                    className="w-full px-2 py-1.5 text-xs border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary">
                                    <option value="">- Select -</option>
                                    <option value="Discount">Discount</option>
                                    <option value="Surcharge">Surcharge</option>
                                  </select>
                                </div>

                                {/* Discount Type */}
                                <div className="col-span-1">
                                  <label className="block text-[10px] text-ink-faint mb-0.5">Type</label>
                                  <select
                                    value={sub.discount_surcharge_type ?? ''}
                                    onChange={e => updateSubEntryRaw(idx, 'discount_surcharge_type', e.target.value)}
                                    disabled={!sub.discount_surcharge || !!sub.deleted_at}
                                    className="w-full px-2 py-1.5 text-xs border border-line rounded-md disabled:bg-surface-2 disabled:text-ink-faint">
                                    <option value="">-</option>
                                    <option value="Flat">Flat</option>
                                    <option value="Percentage">%</option>
                                  </select>
                                </div>

                                {/* Discount Value */}
                                <div className="col-span-2">
                                  <label className="block text-[10px] text-ink-faint mb-0.5">Disc Value</label>
                                  <MoneyInput
                                    value={sub.discount_surcharge_value ?? ''}
                                    onChange={v => updateSubEntryRaw(idx, 'discount_surcharge_value', v)}
                                    readOnly={!!sub.deleted_at}
                                    className="w-full px-2 py-1.5 text-xs border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary"
                                  />
                                </div>

                                {/* Premium */}
                                <div className="col-span-2">
                                  <label className="block text-[10px] text-ink-faint mb-0.5">Premium</label>
                                  <MoneyInput
                                    value={sub.calculated_value}
                                    onChange={v => updateSubEntryRaw(idx, 'calculated_value', v)}
                                    readOnly={!!sub.deleted_at}
                                    className="w-full px-2 py-1.5 text-xs border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary"
                                  />
                                </div>

                                {/* +/- button */}
                                <div className="col-span-1 flex justify-end pt-4">
                                  {sub.deleted_at ? (
                                    <button
                                      type="button"
                                      onClick={() => reinstateRow(idx)}
                                      className="w-9 h-9 flex items-center justify-center rounded-md bg-primary text-white hover:bg-primary text-sm font-bold"
                                      title="Reinstate row"
                                    >↻</button>
                                  ) : isFirstOccurrence ? (
                                    <button
                                      type="button"
                                      onClick={() => addDuplicateRow(idx)}
                                      className="w-9 h-9 flex items-center justify-center rounded-md bg-primary text-white hover:bg-primary text-lg font-bold"
                                      title="Add another row"
                                    >+</button>
                                  ) : (
                                    <button
                                      type="button"
                                      onClick={() => removeRow(idx)}
                                      className="w-9 h-9 flex items-center justify-center rounded-md bg-status-danger-fg text-white hover:bg-status-danger-fg text-lg font-bold"
                                      title="Remove row"
                                    >-</button>
                                  )}
                                </div>
                              </div>
                            </div>
                          </div>
                        ) : (
                          <div className={`border-b ${sub.deleted_at ? 'bg-status-danger-bg opacity-60' : ''}`}>
                            <div className="px-4 py-3 hover:bg-surface-2 transition">
                              <div className="grid grid-cols-12 gap-3 items-center">
                                {/* Description of cover — locked for master rows
                                    (comes from tb_cvgpcsubcoverages.s_ScreenName),
                                    also locked for new custom rows (added via "+").
                                    Expands to col-span-3 when no second field. */}
                                {(() => {
                                  // Use cached field config for consistency across all rows of same type
                                  const hasSecondField = fieldConfigCache[sub.s_ScreenName ?? '']
                                  return (
                                    <div className={hasSecondField ? 'col-span-2' : 'col-span-3'}>
                                      <label className="block text-[10px] text-ink-faint mb-0.5">Description</label>
                                      <div
                                        title={sub.s_ScreenName}
                                        className="w-full px-2 py-1.5 text-sm text-ink-muted bg-surface-2 border border-line rounded-md truncate"
                                      >{sub.s_ScreenName}</div>
                                    </div>
                                  )
                                })()}

                                {/* Second field — type and storage varies by coverage
                                    Stored in ratefactor_value/ratefactor_type/coverage_value_string depending on coverage */}
                                {(() => {
                                  // Use cached field config - all rows of same coverage type show same field
                                  const field = fieldConfigCache[sub.s_ScreenName ?? '']
                                  if (sub.s_LimitTypeCode === 'RADIO') {
                                    console.log(`DEBUG: ${sub.s_ScreenName} - LimitType: ${sub.s_LimitTypeCode}, Field:`, field, 'RadioOptions:', (sub as any).radio_options)
                                  }
                                  if (!field) return null

                                  const value = field.storageField === 'ratefactor_type' ? sub.ratefactor_type
                                    : field.storageField === 'coverage_value_string' ? sub.coverage_value_string
                                    : field.storageField === 'limit_id' ? (sub as any).limit_id
                                    : sub.ratefactor_value

                                  return (
                                    <div className="col-span-2">
                                      <label className="block text-[10px] text-ink-faint mb-0.5">{field.label}</label>
                                      {field.type === 'text' && (
                                        <input
                                          type="text"
                                          value={value || ''}
                                          onChange={e => updateSubEntryRaw(idx, field.storageField as any, e.target.value)}
                                          placeholder={field.label}
                                          readOnly={!!sub.deleted_at}
                                          className="w-full px-2 py-1.5 text-sm border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary"
                                        />
                                      )}
                                      {field.type === 'date' && (
                                        <input
                                          type="date"
                                          value={value || ''}
                                          onChange={e => updateSubEntryRaw(idx, field.storageField as any, e.target.value)}
                                          readOnly={!!sub.deleted_at}
                                          className="w-full px-2 py-1.5 text-sm border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary"
                                        />
                                      )}
                                      {/* Selects write ONLY their own column (limit_id /
                                          ratefactor_type), exactly as graphiteBWV8 does —
                                          every legacy select binds straight to its own field
                                          via wire:model.defer and never touches
                                          coverage_value_string.

                                          This used to also stamp coverage_value_string with
                                          "<label> - <option>". That broke the schedule:
                                          v2-policy-schedule.blade.php prints the
                                          tb_cvgpclimits label (e.g. "Claims Made") only when
                                          coverage_value_string IS NULL, so the stamp
                                          suppressed the real label and printed "Select
                                          Option - Claims Made" instead. On coverages where
                                          coverage_value_string is its own visible free-text
                                          box (Goods In Transit) it also silently overwrote
                                          the operator's typed text. */}
                                      {field.type === 'select' && (
                                        <select
                                          value={value || ''}
                                          onChange={e => updateSubEntryRaw(idx, field.storageField as any, e.target.value)}
                                          disabled={!!sub.deleted_at}
                                          className="w-full px-2 py-1.5 text-sm border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary"
                                        >
                                          <option value="">- Select -</option>
                                          {field.options?.map((opt: any) => (
                                            <option key={opt.id || opt} value={opt.id || opt}>
                                              {opt.name || opt}
                                            </option>
                                          ))}
                                        </select>
                                      )}
                                      {field.type === 'radio' && (
                                        <div className="flex gap-4 mt-1">
                                          {field.options?.map((opt: any) => (
                                            <label key={opt.id || opt} className="flex items-center gap-2 cursor-pointer text-sm">
                                              <input
                                                type="radio"
                                                name={`sub_radio_${idx}`}
                                                value={opt.id || opt}
                                                checked={value === String(opt.id || opt)}
                                                onChange={e => updateSubEntryRaw(idx, field.storageField as any, e.target.value)}
                                                disabled={!!sub.deleted_at}
                                                className="w-4 h-4"
                                              />
                                              <span>{opt.name || opt}</span>
                                            </label>
                                          ))}
                                        </div>
                                      )}
                                    </div>
                                  )
                                })()}

                                {/* Sum Insured for Employers Liability (Common Law Liability) in Worker Compensation */}
                                {(sub.s_ScreenName || '').toLowerCase().includes('employers liability') && (sub.s_ScreenName || '').toLowerCase().includes('common law') && (
                                  <div className="col-span-2">
                                    <label className="block text-[10px] text-ink-faint mb-0.5">Sum Insured</label>
                                    <MoneyInput
                                      value={sub.coverage_value}
                                      onChange={v => updateSubEntry(idx, 'coverage_value', v)}
                                      readOnly={!!sub.deleted_at}
                                      className="w-full px-3 py-2 text-sm border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary"
                                    />
                                  </div>
                                )}

                                {/* Sum Insured — readonly on motor: live aggregate of every vehicle's
                                    coverage_value + every specified item's sum_insured for this policy_coverage
                                    (already scoped by risk_address_id + action_id).
                                    Expands to col-span-3 when no second field.
                                    Special cases (data-driven from second field type):
                                    - Business Interruption "Basis of cover" shows Free Text (stored in ratefactor_value) instead of Sum Insured (old project parity)
                                    - Goods In Transit with dropdown second field shows Free Text (stored in coverage_value_string) instead of Sum Insured (old project parity)
                                    - Goods In Transit with text second field shows Sum Insured normally
                                    - Employers Liability (Common Law Liability) in Worker Compensation has separate Sum Insured field */}
                                {(() => {
                                  const secondField = fieldConfigCache[sub.s_ScreenName ?? '']
                                  const isBiBasisOfCover = sub.s_ParentCoverageCode?.toUpperCase() === 'BUSINESSINTERUPTION' && sub.s_ScreenName === 'Basis of cover'
                                  const isGitWithDropdown = sub.s_ParentCoverageCode?.toUpperCase() === 'GOODSINTRANSIT' && secondField?.type === 'select'
                                  const isEmployersLiability = (sub.s_ScreenName || '').toLowerCase().includes('employers liability') && (sub.s_ScreenName || '').toLowerCase().includes('common law')
                                  const hasSecondField = secondField && !isEmployersLiability
                                  if (isEmployersLiability) return null
                                  return (
                                    <div className={hasSecondField ? 'col-span-2' : 'col-span-3'}>
                                      <label className="block text-[10px] text-ink-faint mb-0.5">{isBiBasisOfCover || isGitWithDropdown ? 'Free Text' : covCode === 'PUBLICLIABILITY' ? 'Limit of Liability' : 'Sum Insured'}</label>
                                      {isMotorCoverage ? (
                                        <div className="w-full px-3 py-2 text-sm text-ink-muted bg-surface-2 border border-line rounded-md">
                                          {motorAggregate.sumInsured > 0 ? motorAggregate.sumInsured.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—'}
                                        </div>
                                      ) : isBiBasisOfCover ? (
                                        <input
                                          type="text"
                                          value={sub.ratefactor_value ?? ''}
                                          onChange={e => updateSubEntryRaw(idx, 'ratefactor_value', e.target.value)}
                                          placeholder="Enter text..."
                                          readOnly={!!sub.deleted_at}
                                          className="w-full px-3 py-2 text-sm border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary"
                                        />
                                      ) : isGitWithDropdown ? (
                                        <input
                                          type="text"
                                          value={sub.coverage_value_string ?? ''}
                                          onChange={e => updateSubEntryRaw(idx, 'coverage_value_string', e.target.value)}
                                          placeholder="Enter text..."
                                          readOnly={!!sub.deleted_at}
                                          className="w-full px-3 py-2 text-sm border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary"
                                        />
                                      ) : (
                                        <MoneyInput
                                          value={sub.coverage_value}
                                          onChange={v => updateSubEntry(idx, 'coverage_value', v)}
                                          readOnly={!!sub.deleted_at}
                                          className="w-full px-3 py-2 text-sm border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary"
                                        />
                                      )}
                                    </div>
                                  )
                                })()}

                                {/* Theft "Enter sum insured" — Free Text field (stored in coverage_value_string) */}
                                {(() => {
                                  const isTheftEnterSumInsured = sub.s_ParentCoverageCode?.toUpperCase() === 'THEFT' && sub.s_ScreenName === 'Enter sum insured'
                                  if (!isTheftEnterSumInsured) return null
                                  return (
                                    <div className="col-span-2">
                                      <label className="block text-[10px] text-ink-faint mb-0.5">Free Text</label>
                                      <input
                                        type="text"
                                        value={sub.coverage_value_string ?? ''}
                                        onChange={e => updateSubEntryRaw(idx, 'coverage_value_string', e.target.value)}
                                        placeholder="Enter text..."
                                        readOnly={!!sub.deleted_at}
                                        className="w-full px-3 py-2 text-sm border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary"
                                      />
                                    </div>
                                  )
                                })()}

                                {/* Rate */}
                                <div className="col-span-1">
                                  <label className="block text-[10px] text-ink-faint mb-0.5">Rate %</label>
                                  <input
                                    type="number"
                                    readOnly={!!sub.deleted_at}
                                    value={sub.rate}
                                    onChange={e => updateSubEntry(idx, 'rate', e.target.value)}
                                    placeholder="0.00"
                                    className="w-full px-3 py-2 text-sm border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary"
                                  />
                                </div>

                                {/* Premium — readonly on motor: live aggregate of every vehicle's
                                    (calculated_value + all premium_* extension fields) + every specified
                                    item's calculated_value. Mirrors the canonical motor section premium. */}
                                <div className="col-span-2">
                                  <label className="block text-[10px] text-ink-faint mb-0.5">Premium</label>
                                  {isMotorCoverage ? (
                                    <div className="w-full px-3 py-2 text-sm text-ink-muted bg-surface-2 border border-line rounded-md">
                                      {motorAggregate.premium > 0 ? motorAggregate.premium.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—'}
                                    </div>
                                  ) : (
                                    <MoneyInput
                                      value={sub.calculated_value}
                                      onChange={v => updateSubEntry(idx, 'calculated_value', v)}
                                      readOnly={!!sub.deleted_at}
                                      className="w-full px-3 py-2 text-sm border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary"
                                    />
                                  )}
                                </div>

                                {/* + for first occurrence, - for duplicates — hidden on motor (vehicle rows manage this) */}
                                <div className="col-span-1 flex justify-end">
                                  {isMotorCoverage ? null : sub.deleted_at ? (
                                    <button
                                      type="button"
                                      onClick={() => reinstateRow(idx)}
                                      className="w-9 h-9 flex items-center justify-center rounded-md bg-primary text-white hover:bg-primary text-sm font-bold"
                                      title="Reinstate row"
                                    >↻</button>
                                  ) : isFirstOccurrence ? (
                                    <button
                                      type="button"
                                      onClick={() => addDuplicateRow(idx)}
                                      className="w-9 h-9 flex items-center justify-center rounded-md bg-primary text-white hover:bg-primary text-lg font-bold"
                                      title="Add another row"
                                    >+</button>
                                  ) : (
                                    <button
                                      type="button"
                                      onClick={() => removeRow(idx)}
                                      className="w-9 h-9 flex items-center justify-center rounded-md bg-status-danger-fg text-white hover:bg-status-danger-fg text-lg font-bold"
                                      title="Remove row"
                                    >-</button>
                                  )}
                                </div>
                              </div>
                            </div>
                          </div>
                        )}
                      </div>
                    )
                  })
                })()}

                {/* Subcoverage totals */}
                <div className="px-4 py-3 bg-status-info-bg border-t flex items-center justify-between">
                  <span className="text-sm font-semibold text-ink-muted">
                    Total: {subEntries.filter(s => s.coverage_value).length} subcoverage(s) filled
                  </span>
                  <div className="flex gap-6 text-sm">
                    <span>{covCode === 'PUBLICLIABILITY' ? 'Limit of Liability' : 'Sum Insured'}: <b>P {(isMotorCoverage
                      ? motorAggregate.sumInsured
                      : subEntries.reduce((s, e) => s + (parseFloat(e.coverage_value) || 0), 0)
                    ).toLocaleString(undefined, { minimumFractionDigits: 2 })}</b></span>
                    <span>Premium: <b>P {(isMotorCoverage
                      ? motorAggregate.premium
                      // Mirror the ACTION PAGE / V2 quote sheet total, not a raw
                      // sum of every loaded row. The raw arrays include master
                      // template rows (subcoverages/extensions the operator hasn't
                      // filled) which inflated this live total above what is saved
                      // and shown on the policy-action page. Count only:
                      //   - subcoverages that have a Sum Insured (filled)
                      //   - specified items with a master pick + value, EXCLUDING
                      //     soft-deleted rows (the backend returns them with
                      //     deleted_at set so the UI can show a Reinstate button;
                      //     a deleted row must not add to the premium)
                      //   - extensions that are actually filled AND, on DomCom
                      //     (product 7/8), only type='Extention' (Excess / Memoranda
                      //     / BurglarAlarm etc. are excluded from the premium there).
                      : subEntries
                          .filter(s => s.coverage_value)
                          .reduce((s, e) => s + (parseFloat(e.calculated_value) || 0), 0)
                        + extEntries
                          .filter(e =>
                            e.extention_coverage_value
                            || e.extention_text_value
                            || e.extention_limit_id
                            || e.extention_discount_surcharge_value
                            || e.extention_calculated_value
                            || e.extention_sum_insured
                          )
                          .filter(e => !isDomComProduct || ((e as any).type || 'Extention') === 'Extention')
                          .reduce((s, e) => s + (parseFloat(e.extention_calculated_value) || 0), 0)
                        + siEntries
                          .filter(s => !(s as any).deleted_at && (s as any).specified_coverage_id && ((s as any).sum_insured || (s as any).calculated_value))
                          .reduce((s, e) => s + (parseFloat(e.calculated_value) || 0), 0)
                    ).toLocaleString(undefined, { minimumFractionDigits: 2 })}</b></span>
                  </div>
                </div>

                {/* Stated Benefits for Workers Compensation only - displayed as read-only formatted text */}
                {(covCode === 'WORKERSCOMPENSATION' || covCode === 'STATEDBENEFITS') && (
                <div className="px-4 py-3 border-t">
                  <label className="block text-sm font-semibold text-ink-muted mb-2">Benefits for the circumstances</label>
                  <div className="w-full px-3 py-3 text-sm bg-surface-2 border border-line rounded-md whitespace-pre-wrap font-mono text-ink-muted">
                    {(coverageForm as any).stated_benefits || ''}
                  </div>
                </div>
                )}
              </div>
            )}

            {/* ── Extensions for Stated Benefits (rendered AFTER subcoverages) ── */}
            {!extLoading && extEntries.length > 0 && coverageForm.coverage_id && covCode === 'STATEDBENEFITS' && (
              <div className="border rounded-lg overflow-hidden">
                <div className="bg-status-info-bg px-4 py-2 border-b">
                  <h4 className="text-sm font-bold text-primary">Extensions ({extEntries.length})</h4>
                </div>
                {(() => {
                  // Separate FirstAmountPayable extensions from regular extensions
                  const regularExts = extEntries.filter(e => (e as any).type !== 'FirstAmountPayable')

                  // Group regular extensions sequentially: header extensions (s_ExtensionsGroupName="Heading")
                  // define group sections, and following non-header extensions belong under them
                  const grouped: { header?: string; exts: (any)[] }[] = []
                  let currentGroup: { header?: string; exts: (any)[] } = { exts: [] }

                  const headerGroupNames = ['heading', 'header', 'group']

                  regularExts.forEach((ext) => {
                    const groupName = ((ext as any).s_ExtensionsGroupName || '').trim().toLowerCase()
                    const isHeaderExt = headerGroupNames.includes(groupName)

                    if (isHeaderExt) {
                      // Start a new group with this header extension's name
                      if (currentGroup.exts.length > 0) {
                        grouped.push(currentGroup)
                      }
                      currentGroup = {
                        header: ext.s_ScreenName || '',
                        exts: []
                      }
                    } else {
                      // Regular extension - add to current group
                      const origIdx = extEntries.findIndex(e => e.extentions_id === ext.extentions_id)
                      currentGroup.exts.push({ ...ext, _idx: origIdx })
                    }
                  })

                  // Push the last group if it has extensions
                  if (currentGroup.exts.length > 0) {
                    grouped.push(currentGroup)
                  }

                  return (
                    <>
                      {/* Regular extensions section */}
                      {grouped.map((group, groupIdx) => (
                        <div key={groupIdx}>
                          {/* Group header (if this group has a header name) */}
                          {group.header && (
                            <div className="px-4 py-2 bg-status-warning-bg border-b border-status-warning-fg">
                              <span className="text-sm font-bold text-status-warning-fg">{group.header}</span>
                            </div>
                          )}
                          {/* Extensions in this group */}
                          {group.exts.map((ext) => {
                            const idx = ext._idx
                            return (
                              <div key={ext.extentions_id} className="px-4 py-3 border-b hover:bg-surface-2 transition">
                                <div className="grid grid-cols-12 gap-3 items-start">
                                  {/* Extension Name */}
                                  <div className="col-span-2">
                                    <label className="block text-[10px] text-ink-faint mb-0.5">Extension Name</label>
                                    <div className="text-sm text-ink font-medium" title={ext.s_ScreenName || ''}>
                                      {ext.s_ScreenName || ''}
                                    </div>
                                  </div>

                                  {/* Free Text field */}
                                  <div className="col-span-3">
                                    <label className="block text-[10px] text-ink-faint mb-0.5">Free Text</label>
                                    <textarea
                                      value={ext.extention_text_value ?? ''}
                                      onChange={e => updateExtEntry(idx, 'extention_text_value', e.target.value)}
                                      placeholder="Enter text..."
                                      rows={2}
                                      className="w-full px-2 py-1.5 text-sm border border-line rounded" />
                                  </div>

                                  {/* Select Discount */}
                                  <div className="col-span-2">
                                    <label className="block text-[10px] text-ink-faint mb-0.5">Select Discount</label>
                                    <select value={ext.extention_discount_surcharge || ''}
                                      onChange={e => updateExtEntry(idx, 'extention_discount_surcharge', e.target.value)}
                                      className="w-full px-2 py-1.5 text-xs border rounded bg-surface">
                                      <option value="">- Select -</option>
                                      <option value="Discount">Discount</option>
                                      <option value="Surcharge">Surcharge</option>
                                    </select>
                                  </div>

                                  {/* Discount Type */}
                                  <div className="col-span-1">
                                    <label className="block text-[10px] text-ink-faint mb-0.5">Type</label>
                                    <select value={ext.extention_discount_surcharge_type || ''}
                                      onChange={e => updateExtEntry(idx, 'extention_discount_surcharge_type', e.target.value)}
                                      className="w-full px-2 py-1.5 text-xs border rounded bg-surface">
                                      <option value="">- Select -</option>
                                      <option value="Flat">Flat</option>
                                      <option value="Percentage">%</option>
                                    </select>
                                  </div>

                                  {/* Disc Value */}
                                  <div className="col-span-2">
                                    <label className="block text-[10px] text-ink-faint mb-0.5">Disc Value</label>
                                    <MoneyInput value={ext.extention_discount_surcharge_value ?? ''}
                                      onChange={v => updateExtEntry(idx, 'extention_discount_surcharge_value', v)}
                                      className="w-full px-2 py-1.5 text-sm border rounded" />
                                  </div>

                                  {/* Premium */}
                                  <div className="col-span-2">
                                    <label className="block text-[10px] text-ink-faint mb-0.5">Premium</label>
                                    <MoneyInput value={ext.extention_calculated_value ?? ''}
                                      onChange={v => updateExtEntry(idx, 'extention_calculated_value', v)}
                                      className="w-full px-2 py-1.5 text-sm border rounded" />
                                  </div>
                                </div>
                              </div>
                            )
                          })}
                        </div>
                      ))}
                    </>
                  )
                })()}
              </div>
            )}

            {/* Show main coverage fields only when no subcoverages.
                Hidden for motor coverages (Commercial Motor 22 / Domestic
                Motor 27 / etc) — the per-vehicle motor schedule is the
                source of truth there. Also hidden for Fidelity Guarantee
                which uses policy_coverages_data instead of policy_coverage_detail. */}
            {!subLoading && subEntries.length === 0 && coverageForm.coverage_id && !isMotorCoverage && !isFidelityGuarantee && !hidesMainCoverageFields && !isDomComProduct && (
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label className="block text-xs text-ink-muted mb-1">{covCode === 'PUBLICLIABILITY' ? 'Limit of Liability' : 'Sum Insured (BWP)'} <span className="text-status-danger-fg">*</span></label>
                  <MoneyInput value={coverageForm.coverage_value || ''}
                    onChange={v => {
                      const r = parseFloat(coverageForm.rate)
                      const calc = !isNaN(parseFloat(v)) && !isNaN(r) ? (parseFloat(v) * r / 100).toFixed(2) : ''
                      setCoverageForm({ ...coverageForm, coverage_value: v, calculated_value: calc })
                      setErrors(p => ({ ...p, cov_coverage_value: '' }))
                    }}
                    className="w-full px-3 py-2 text-sm border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary" />
                  {errors.cov_coverage_value && <p className="text-xs text-status-danger-fg mt-1">{errors.cov_coverage_value}</p>}
                </div>

                <InputField label="Rate (%)" type="number" value={coverageForm.rate}
                  onChange={v => {
                    const cv = parseFloat(coverageForm.coverage_value || '')
                    const calc = !isNaN(cv) && !isNaN(parseFloat(v)) ? (cv * parseFloat(v) / 100).toFixed(2) : ''
                    setCoverageForm({ ...coverageForm, rate: v, calculated_value: calc })
                  }}
                  placeholder="0.00" />

                <div>
                  <label className="block text-xs text-ink-muted mb-1">Calculated Premium (BWP)</label>
                  <MoneyInput value={coverageForm.calculated_value || ''}
                    onChange={v => setCoverageForm({ ...coverageForm, calculated_value: v })}
                    className="w-full px-3 py-2 text-sm border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary" />
                </div>
              </div>
            )}

            {/* ── Motor Traders Ext/Int panel ── */}
            {isMotorTradersCoverage && coverageForm.coverage_id && (
              <div className="border rounded-lg overflow-hidden">
                <div className="bg-status-info-bg px-4 py-2 border-b">
                  <h4 className="text-sm font-bold text-primary">
                    Motor Traders {isMotorTradersExternal ? 'External' : 'Internal'}
                    {mtLoading && <span className="ml-2 text-xs text-ink-muted">loading…</span>}
                  </h4>
                </div>
                <div className="p-4 space-y-4">
                  <div>
                    <label className="block text-xs text-ink-muted mb-1">Type Of Cover *</label>
                    <select
                      value={mtTypeOfCover}
                      onChange={e => setMtField('type_of_cover', e.target.value)}
                      className="w-full px-3 py-2 text-sm border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary">
                      <option value="">-Select-</option>
                      <option value={isMotorTradersExternal ? 'ComprehensiveMotorTradersExternal' : 'ComprehensiveMotorTradersInternal'}>Comprehensive</option>
                      <option value={isMotorTradersExternal ? 'TPMotorTradersExternal' : 'TPMotorTradersInternal'}>Third party only</option>
                      <option value={isMotorTradersExternal ? 'TPFTMotorTradersExternal' : 'TPFTMotorTradersInternal'}>Third party, fire and theft</option>
                    </select>
                    {!(coverageForm as any)._dbId && (
                      <p className="mt-1 text-xs text-status-warning-fg">Save the coverage first, then re-open to enter Motor Traders details.</p>
                    )}
                  </div>

                  {/* Comprehensive / FTPFT: full field grid. TP only: only the third-party row. */}
                  {(showMtMain || showMtTpOnly) && (
                    <div className="space-y-2">
                      <h5 className="text-sm font-bold text-ink-muted capitalize">
                        Motor Traders {isMotorTradersExternal ? 'External' : 'Internal'} {' '}
                        {mtTypeOfCover.indexOf('Comprehensive') === 0 ? 'Comprehensive' : (showMtTpOnly ? 'FTP' : 'FTPFT')}
                      </h5>
                      {(showMtMain ? MT_MAIN_ROWS : MT_MAIN_ROWS.filter(([, k]) => k === 'third_party_liability')).map(([label, prefix]) => (
                        <div key={prefix} className="grid grid-cols-1 md:grid-cols-3 gap-3 items-center">
                          <label className="text-sm text-ink-muted">{label} :</label>
                          <MoneyInput
                            value={(mtData[`${prefix}_coverage_value`] ?? '') as any}
                            onChange={v => setMtField(`${prefix}_coverage_value`, v)}
                            className="w-full px-3 py-2 text-sm border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary"
                            placeholder="Sum Insured" />
                          <MoneyInput
                            value={(mtData[`${prefix}_calculated_value`] ?? '') as any}
                            onChange={v => setMtField(`${prefix}_calculated_value`, v)}
                            className="w-full px-3 py-2 text-sm border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary"
                            placeholder="Premium" />
                        </div>
                      ))}
                    </div>
                  )}

                  {showMtExtensions && (
                    <>
                      <hr />
                      <h5 className="text-sm font-bold text-ink-muted">Extensions and Clauses</h5>
                      <div className="space-y-2">
                        {MT_EXT_ROWS.map(([label, prefix]) => (
                          <div key={prefix} className="grid grid-cols-1 md:grid-cols-3 gap-3 items-center">
                            <label className="text-sm text-ink-muted">{label} :</label>
                            <MoneyInput
                              value={(mtData[`${prefix}_coverage_value`] ?? '') as any}
                              onChange={v => setMtField(`${prefix}_coverage_value`, v)}
                              className="w-full px-3 py-2 text-sm border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary"
                              placeholder="Sum Insured" />
                            <MoneyInput
                              value={(mtData[`${prefix}_calculated_value`] ?? '') as any}
                              onChange={v => setMtField(`${prefix}_calculated_value`, v)}
                              className="w-full px-3 py-2 text-sm border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary"
                              placeholder="Premium" />
                          </div>
                        ))}
                      </div>
                      <hr />
                      <h5 className="text-sm font-bold text-ink-muted">Minimum Limits</h5>
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                        {MT_MIN_ROWS.map(([label, key]) => (
                          <div key={key}>
                            <label className="block text-xs text-ink-muted mb-1">{label}</label>
                            <input
                              type="text"
                              value={(mtData[key] ?? '') as any}
                              onChange={e => setMtField(key, e.target.value)}
                              className="w-full px-3 py-2 text-sm border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary"
                              placeholder={key.endsWith('percent') ? '%' : 'Amount'} />
                          </div>
                        ))}
                      </div>
                    </>
                  )}
                </div>
              </div>
            )}

            {/* ── Motor Vehicles (shown first for motor coverages) ── */}
            {isMotorCoverage && coverageForm.coverage_id && (
              <div className="border rounded-lg overflow-hidden">
                <div className="bg-status-info-bg px-4 py-2 border-b">
                  <h4 className="text-sm font-bold text-primary">Summary Of Vehicles ({motorVehicles.length})</h4>
                </div>
                {availableVehicles.length > 0 && !showAddVehicle && (
                  <div className="px-4 py-3 border-b bg-surface-2 space-y-2">
                    <div className="flex items-center gap-3">
                      <div className="flex-1">
                        <label className="block text-[10px] text-ink-faint mb-0.5">Select Existing Vehicle</label>
                        <input type="text" value={vehicleSearch}
                          onChange={e => setVehicleSearch(e.target.value)}
                          placeholder="Filter by plate, make, model..."
                          className="w-full px-3 py-2 text-sm border rounded" />
                      </div>
                      <button type="button" onClick={() => { addVehicleToCoverage(); setVehicleSearch('') }} disabled={!selectedVehicleId || motorLoading}
                        title={selectedVehicleId ? 'Add selected vehicle to this coverage' : 'Pick a vehicle first'}
                        className="mt-4 w-10 h-10 flex items-center justify-center rounded-md bg-status-success-fg text-white hover:bg-status-success-fg disabled:opacity-50 text-lg font-bold">+</button>
                    </div>
                    {/* Always-visible inline list — operator no longer has to
                        focus the search box to see the auto-loaded vehicles
                        for the current risk_id + action_id. The search box
                        above is a soft filter on this same list.
                        Click a row to add the vehicle to the coverage
                        immediately — no separate "+" click required. */}
                    <div className="bg-surface border rounded-md max-h-48 overflow-y-auto">
                      {availableVehicles
                        .filter((v: any) => { const q = vehicleSearch.toLowerCase(); return !q || (v.label||'').toLowerCase().includes(q) || (v.plate||'').toLowerCase().includes(q) })
                        .map((v: any) => (
                          <button key={v.id} type="button"
                            disabled={motorLoading}
                            onClick={() => { addVehicleToCoverage(String(v.id)); setVehicleSearch('') }}
                            title="Click to add this vehicle to the coverage"
                            className="w-full text-left px-3 py-2 text-sm border-b last:border-0 hover:bg-status-info-bg disabled:opacity-50">
                            <span className="font-medium">{v.plate}</span> — {v.make} {v.model}
                          </button>
                        ))}
                    </div>
                  </div>
                )}
                <div className="px-4 py-2 border-b flex justify-end">
                  <button type="button" onClick={() => {
                    if (policyId) navigate(`/policies/${policyId}?tab=vehicles&add=1`)
                  }} className="text-sm text-primary hover:text-primary font-medium">
                    + Add New Vehicle
                  </button>
                </div>
                {/* Inline Add-new-vehicle form removed — the "+ Add New Vehicle"
                    button above now redirects to the Vehicles tab where the
                    full Add Vehicle modal lives. */}
                {motorLoading && <div className="px-4 py-3 text-sm text-ink-muted">Loading vehicles...</div>}
                {!motorLoading && (
                  <div className="overflow-x-auto">
                    <table className="w-full text-sm table-fixed">
                      <thead>
                        <tr className="text-left text-[10px] text-ink-muted border-b bg-surface-2">
                          <th className="px-2 py-2 w-[14%]">Description</th>
                          <th className="px-2 py-2 w-[10%]">Reg No</th>
                          <th className="px-2 py-2 w-[10%]">Est. Value</th>
                          <th className="px-2 py-2 w-[14%]">Usage</th>
                          <th className="px-2 py-2 w-[18%]">Type of Cover</th>
                          <th className="px-2 py-2 w-[15%]">Sum Insured</th>
                          <th className="px-2 py-2 w-[15%]">Premium</th>
                          <th className="px-2 py-2 w-[4%]"></th>
                        </tr>
                      </thead>
                      <tbody>
                        {motorVehicles.map((m: any) => (
                          <MotorVehicleRow
                            key={m.id}
                            motor={m}
                            specifiedItems={((coverageForm as any).specified_items || []).filter((s: any) => s.motor_id === m.id)}
                            siOptions={siOptions}
                            siLoading={siLoading}
                            note={m.note || ''}
                            onUpdate={updateMotorRow}
                            onDelete={deleteMotorRow}
                            onReinstate={reinstateMotorRow}
                            onUpdateItem={updateMotorSpecifiedItem}
                            onAddItem={addMotorSpecifiedItem}
                            onDeleteItem={deleteMotorSpecifiedItem}
                            onReinstateItem={reinstateMotorSpecifiedItem}
                            onUpdateNote={upsertMotorNote}
                          />
                        ))}
                        {motorVehicles.length === 0 && (
                          <tr>
                            <td colSpan={8} className="px-3 py-4 text-center text-xs text-ink-faint">
                              No vehicles yet. Select an existing vehicle or add a new one above — first row will land here.
                            </td>
                          </tr>
                        )}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            )}

            {/* ── Fidelity Guarantee: Basis of Cover ──────────────
                Choose between Blanket or Named/Position. MUST appear before Extensions */}
            {isFidelityGuarantee && coverageForm.coverage_id && (
              <div className="mb-4 p-4 border rounded-lg bg-status-info-bg">
                <label className="block text-sm font-medium text-ink-muted mb-2">Select Basis of Cover *</label>
                <select value={fidelityBasisOfCover}
                  onChange={e => handleFidelityBasisChange(e.target.value as 'Blanket' | 'Named_Position' | '')}
                  className="w-full px-3 py-2 text-sm border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary">
                  <option value="">- Select -</option>
                  <option value="Blanket">Blanket</option>
                  <option value="Named_Position">Named / Position</option>
                </select>
                {fidelityBasisOfCover === 'Blanket' && (
                  <div className="mt-3 pt-3 border-t border-primary space-y-2">
                    <div className="flex items-center gap-4">
                      <div className="flex-1">
                        <label className="block text-[10px] text-ink-faint mb-0.5">Cover Type</label>
                        <div className="text-sm font-medium text-ink">Blanket</div>
                      </div>
                      <div className="flex-1">
                        <label className="block text-[10px] text-ink-faint mb-0.5">Cover Area</label>
                        <div className="text-sm font-medium text-ink">All employees</div>
                      </div>
                    </div>
                  </div>
                )}
              </div>
            )}

            {/* ── Fidelity Guarantee Items ────────────────────────────────
                For FIDELITYGUARANTEE coverage only. Stores rows in policy_coverages_data.
                Only visible after Basis of Cover selection. */}
            {isFidelityGuarantee && coverageForm.coverage_id && fidelityBasisOfCover && (
              <div className="space-y-3">
                {fidelityEntries.length === 0 && (
                  <div className="flex justify-end">
                    <button type="button"
                      onClick={() => setFidelityEntries([{
                        cover_type: fidelityBasisOfCover,
                        cover_area: 'All employees',
                        name_and_position: '',
                        designation: '',
                        length_of_service: '',
                        amount_to_be_guaranteed: '',
                        premium: ''
                      }])}
                      className="px-4 py-2 text-sm font-medium text-white bg-primary rounded-md hover:bg-primary">
                      + Add Item
                    </button>
                  </div>
                )}

              <div className="border rounded-lg overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm table-fixed">
                      <thead>
                        <tr className="text-left text-[10px] text-ink-muted border-b bg-surface-2">
                          {fidelityBasisOfCover === 'Blanket' ? (
                            <>
                              <th className="px-3 py-2 w-[40%]">Amount to Guarantee</th>
                              <th className="px-3 py-2 w-[40%]">Premium</th>
                              <th className="px-3 py-2 w-[20%]"></th>
                            </>
                          ) : (
                            <>
                              <th className="px-3 py-2 w-[15%]">Name & Position</th>
                              <th className="px-3 py-2 w-[12%]">Designation</th>
                              <th className="px-3 py-2 w-[13%]">Length of Service</th>
                              <th className="px-3 py-2 w-[25%]">Amount to Guarantee</th>
                              <th className="px-3 py-2 w-[20%]">Premium</th>
                              <th className="px-3 py-2 w-[15%]"></th>
                            </>
                          )}
                        </tr>
                      </thead>
                      <tbody>
                        {fidelityEntries.map((fd, idx) => (
                          <tr key={idx} className={`border-b ${fd.deleted_at ? 'bg-status-danger-bg opacity-60' : 'hover:bg-surface-2'}`}>
                            {fidelityBasisOfCover === 'Blanket' ? (
                              <>
                                <td className="px-3 py-1">
                                  <MoneyInput value={fd.amount_to_be_guaranteed || ''} readOnly={!!fd.deleted_at}
                                    onChange={v => setFidelityEntries(prev => prev.map((f, i) => i === idx ? {...f, amount_to_be_guaranteed: v} : f))}
                                    className="w-full px-2 py-1.5 text-sm border border-line rounded" />
                                </td>
                                <td className="px-3 py-1">
                                  <MoneyInput value={fd.premium || ''} readOnly={!!fd.deleted_at}
                                    onChange={v => setFidelityEntries(prev => prev.map((f, i) => i === idx ? {...f, premium: v} : f))}
                                    className="w-full px-2 py-1.5 text-sm border border-line rounded" />
                                </td>
                                <td className="px-3 py-1 text-center"></td>
                              </>
                            ) : (
                              <>
                                <td className="px-3 py-1">
                                  <input type="text" value={fd.name_and_position || ''} readOnly={!!fd.deleted_at}
                                    onChange={e => setFidelityEntries(prev => prev.map((f, i) => i === idx ? {...f, name_and_position: e.target.value} : f))}
                                    className="w-full px-2 py-1.5 text-sm border border-line rounded" />
                                </td>
                                <td className="px-3 py-1">
                                  <input type="text" value={fd.designation || ''} readOnly={!!fd.deleted_at}
                                    onChange={e => setFidelityEntries(prev => prev.map((f, i) => i === idx ? {...f, designation: e.target.value} : f))}
                                    className="w-full px-2 py-1.5 text-sm border border-line rounded" />
                                </td>
                                <td className="px-3 py-1">
                                  <input type="text" value={fd.length_of_service || ''} readOnly={!!fd.deleted_at}
                                    onChange={e => setFidelityEntries(prev => prev.map((f, i) => i === idx ? {...f, length_of_service: e.target.value} : f))}
                                    className="w-full px-2 py-1.5 text-sm border border-line rounded" />
                                </td>
                                <td className="px-3 py-1">
                                  <MoneyInput value={fd.amount_to_be_guaranteed || ''} readOnly={!!fd.deleted_at}
                                    onChange={v => setFidelityEntries(prev => prev.map((f, i) => i === idx ? {...f, amount_to_be_guaranteed: v} : f))}
                                    className="w-full px-2 py-1.5 text-sm border border-line rounded" />
                                </td>
                                <td className="px-3 py-1">
                                  <MoneyInput value={fd.premium || ''} readOnly={!!fd.deleted_at}
                                    onChange={v => setFidelityEntries(prev => prev.map((f, i) => i === idx ? {...f, premium: v} : f))}
                                    className="w-full px-2 py-1.5 text-sm border border-line rounded" />
                                </td>
                                <td className="px-3 py-1 text-center">
                                  {fd.deleted_at ? (
                                    <button type="button" onClick={() => setFidelityEntries(prev => prev.map((f, i) => i === idx ? {...f, deleted_at: null} : f))}
                                      className="w-8 h-8 flex items-center justify-center rounded-md bg-primary text-white hover:bg-primary text-sm font-bold">↻</button>
                                  ) : idx === 0 ? (
                                    <button type="button" onClick={() => setFidelityEntries([...fidelityEntries, {
                                      cover_type: 'Named_Position',
                                      name_and_position: '',
                                      designation: '',
                                      length_of_service: '',
                                      amount_to_be_guaranteed: '',
                                      premium: ''
                                    }])}
                                      className="w-8 h-8 flex items-center justify-center rounded-md bg-primary text-white hover:bg-primary text-lg font-bold">+</button>
                                  ) : (
                                    <button type="button" onClick={() => {
                                      if (fd.id) {
                                        setFidelityEntries(prev => prev.map((f, i) => i === idx ? {...f, deleted_at: new Date().toISOString()} : f))
                                      } else {
                                        setFidelityEntries(prev => prev.filter((_, i) => i !== idx))
                                      }
                                    }}
                                      className="w-8 h-8 flex items-center justify-center rounded-md bg-status-danger-fg text-white hover:bg-status-danger-fg text-lg font-bold">−</button>
                                  )}
                                </td>
                              </>
                            )}
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
              </div>
              </div>
            )}

            {/* ── Theft General Questions ────────────────────────────────────
                For THEFT coverage only. Displays 6 general questions about
                security measures. Based on old project manage-coverages.blade.php */}
            {covCode === 'THEFT' && coverageForm.coverage_id && (
              <div className="border rounded-lg overflow-hidden mb-4">
                <div className="bg-status-info-bg px-4 py-2 border-b">
                  <h4 className="text-sm font-bold text-primary">General Questions</h4>
                </div>
                <div className="p-4 space-y-4">
                  {/* Question 1: Physical protections dropdown */}
                  <div>
                    <label className="block text-sm font-medium text-ink-muted mb-1">
                      1. Physical protections implemented at the premises *
                    </label>
                    <select
                      value={theftQuestions.physical_protection_implemented || ''}
                      onChange={e => setTheftQuestions(prev => ({ ...prev, physical_protection_implemented: e.target.value }))}
                      className="w-full px-3 py-2 text-sm border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary">
                      <option value="">- Select -</option>
                      <option value="Perimeter Fence">Perimeter Fence</option>
                      <option value="Armed Security Guards">Armed Security Guards</option>
                      <option value="Other">Other</option>
                    </select>
                  </div>

                  {/* Question 2: Are premises alarmed */}
                  <div>
                    <label className="block text-sm font-medium text-ink-muted mb-2">
                      2. Are the premises alarmed? *
                    </label>
                    <div className="flex gap-6">
                      {['Yes', 'No'].map(option => (
                        <label key={option} className="flex items-center gap-2 cursor-pointer">
                          <input
                            type="radio"
                            name="premises_alarmed"
                            value={option}
                            checked={theftQuestions.premises_alarmed === option}
                            onChange={e => setTheftQuestions(prev => ({ ...prev, premises_alarmed: e.target.value }))}
                            className="w-4 h-4 text-primary focus:ring-primary"
                          />
                          <span className="text-sm text-ink-muted">{option}</span>
                        </label>
                      ))}
                    </div>
                  </div>

                  {/* Question 3: Subscribe to armed security with conditional field */}
                  <div>
                    <label className="block text-sm font-medium text-ink-muted mb-2">
                      3. Do you subscribe to an armed security service? *
                    </label>
                    <div className="flex gap-6">
                      {['Yes', 'No'].map(option => (
                        <label key={option} className="flex items-center gap-2 cursor-pointer">
                          <input
                            type="radio"
                            name="subscribe_armed_security"
                            value={option}
                            checked={theftQuestions.subscribe_armed_security === option}
                            onChange={e => setTheftQuestions(prev => ({ ...prev, subscribe_armed_security: e.target.value }))}
                            className="w-4 h-4 text-primary focus:ring-primary"
                          />
                          <span className="text-sm text-ink-muted">{option}</span>
                        </label>
                      ))}
                    </div>
                    {/* Conditional field 3.1: Name of Company (shown when question 3 = Yes) */}
                    {theftQuestions.subscribe_armed_security === 'Yes' && (
                      <div className="mt-3 ml-6">
                        <label className="block text-sm font-medium text-ink-muted mb-1">
                          3.1 Name of Security Company *
                        </label>
                        <input
                          type="text"
                          value={theftQuestions.security_company || ''}
                          onChange={e => setTheftQuestions(prev => ({ ...prev, security_company: e.target.value }))}
                          placeholder="Enter company name"
                          className="w-full px-3 py-2 text-sm border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary"
                        />
                      </div>
                    )}
                  </div>

                  {/* Question 4: Maintenance contract */}
                  <div>
                    <label className="block text-sm font-medium text-ink-muted mb-2">
                      4. Do you have a maintenance contract for the alarm? *
                    </label>
                    <div className="flex gap-6">
                      {['Yes', 'No'].map(option => (
                        <label key={option} className="flex items-center gap-2 cursor-pointer">
                          <input
                            type="radio"
                            name="maintenance_contract"
                            value={option}
                            checked={theftQuestions.maintenance_contract === option}
                            onChange={e => setTheftQuestions(prev => ({ ...prev, maintenance_contract: e.target.value }))}
                            className="w-4 h-4 text-primary focus:ring-primary"
                          />
                          <span className="text-sm text-ink-muted">{option}</span>
                        </label>
                      ))}
                    </div>
                  </div>

                  {/* Question 5: When was alarm installed */}
                  <div>
                    <label className="block text-sm font-medium text-ink-muted mb-1">
                      5. When was the alarm installed? *
                    </label>
                    <input
                      type="date"
                      value={theftQuestions.alarmed_installed_date || ''}
                      onChange={e => setTheftQuestions(prev => ({ ...prev, alarmed_installed_date: e.target.value }))}
                      className="w-full px-3 py-2 text-sm border border-line rounded-md focus:ring-2 focus:ring-primary focus:border-primary"
                    />
                  </div>

                  {/* Question 6: Opening and closing signals monitored */}
                  <div>
                    <label className="block text-sm font-medium text-ink-muted mb-2">
                      6. Are opening and closing signals monitored? *
                    </label>
                    <div className="flex gap-6">
                      {['Yes', 'No'].map(option => (
                        <label key={option} className="flex items-center gap-2 cursor-pointer">
                          <input
                            type="radio"
                            name="opening_closing_signals"
                            value={option}
                            checked={theftQuestions.opening_closing_signals === option}
                            onChange={e => setTheftQuestions(prev => ({ ...prev, opening_closing_signals: e.target.value }))}
                            className="w-4 h-4 text-primary focus:ring-primary"
                          />
                          <span className="text-sm text-ink-muted">{option}</span>
                        </label>
                      ))}
                    </div>
                  </div>
                </div>
              </div>
            )}

            {/* ── Extensions ─────────────────────────────────────────────────
                Layout matches legacy graphiteBWV8 manage-coverages.blade.php
                (line 4250+): Yes/No radio + Select Discount (Discount/Surcharge)
                + Select Type (Flat/Percentage) + Discounted/Surcharge Value +
                Premium. Excludes motor coverages (extEntries is empty for them
                — per-vehicle premium_* columns drive motor extensions instead)
                and specialist products (handled by SpecialistCoveragePage). */}
            {!extLoading && extEntries.length > 0 && coverageForm.coverage_id && !isMotorCoverage && covCode !== 'THEFT' && covCode !== 'STATEDBENEFITS' && (
              <div className="border rounded-lg overflow-hidden">
                <div className="bg-status-info-bg px-4 py-2 border-b">
                  <h4 className="text-sm font-bold text-primary">{covCode === 'ACCIDENTALDAMAGE' ? 'Memoranda' : `Extensions (${extEntries.length})`}</h4>
                </div>
                {(() => {
                  // Separate FirstAmountPayable extensions from regular extensions
                  const firstAmountPayableExts = extEntries.filter(e => (e as any).type === 'FirstAmountPayable')
                  let regularExts = extEntries.filter(e => (e as any).type !== 'FirstAmountPayable')

                  // For Electronics Equipment: exclude "Free Text" extension
                  if (covCode === 'ELECTRONICEQUIPMENT') {
                    regularExts = regularExts.filter(e => !(e.s_ScreenName || '').toLowerCase().includes('free text'))
                  }

                  // Group regular extensions sequentially: header extensions (s_ExtensionsGroupName="Heading")
                  // define group sections, and following non-header extensions belong under them
                  const grouped: { header?: string; exts: (any)[] }[] = []
                  let currentGroup: { header?: string; exts: (any)[] } = { exts: [] }

                  const headerGroupNames = ['heading', 'header', 'group']

                  regularExts.forEach((ext) => {
                    const groupName = ((ext as any).s_ExtensionsGroupName || '').trim().toLowerCase()
                    const isHeaderExt = headerGroupNames.includes(groupName)

                    if (isHeaderExt) {
                      // Start a new group with this header extension's name
                      if (currentGroup.exts.length > 0) {
                        grouped.push(currentGroup)
                      }
                      currentGroup = {
                        header: ext.s_ScreenName || '',
                        exts: []
                      }
                    } else {
                      // Regular extension - add to current group
                      const origIdx = extEntries.findIndex(e => e.extentions_id === ext.extentions_id)
                      currentGroup.exts.push({ ...ext, _idx: origIdx })
                    }
                  })

                  // Push the last group if it has extensions
                  if (currentGroup.exts.length > 0) {
                    grouped.push(currentGroup)
                  }

                  return (
                    <>
                      {/* FirstAmountPayable no longer rendered here — moved to render AFTER grouped extensions */}

                      {/* Regular extensions section */}
                      {grouped.map((group, groupIdx) => (
                        <div key={groupIdx}>
                          {/* Group header (if this group has a header name) */}
                          {group.header && (
                            <div className="px-4 py-2 bg-status-warning-bg border-b border-status-warning-fg">
                              <span className="text-sm font-bold text-status-warning-fg">{group.header}</span>
                            </div>
                          )}
                          {/* Extensions in this group */}
                          {group.exts.map((ext) => {
                            const idx = ext._idx
                            // Skip "Defined Events" extensions from Professional Indemnity - they have special rendering below
                            // But allow FirstAmountPayable type extensions (like Accidental Damage's "Defined Events" extension)
                            if ((ext.s_ScreenName || '').toLowerCase().includes('defined events') &&
                                ext.type !== 'FirstAmountPayable') {
                              return null
                            }
                            return (
                              <div key={ext.extentions_id} className="px-4 py-3 border-b hover:bg-surface-2 transition">
                                <div className="grid grid-cols-12 gap-3 items-start">
                                  {/* Extension Name column */}
                                  <div className="col-span-2">
                                    <label className="block text-[10px] text-ink-faint mb-0.5">Extension Name</label>
                                    <div className="text-sm text-ink font-medium" title={ext.s_ScreenName || ''}>
                                      {ext.s_ScreenName || ''}
                                    </div>
                                    {/* Yes/No radio buttons: shown when extention_limit_type is RADIO */}
                                    {(ext.extention_limit_type || '').toString().trim().toUpperCase() === 'RADIO' && (
                                      <RadioLimitField
                                        extension={ext}
                                        idx={idx}
                                        onUpdate={updateExtEntry}
                                      />
                                    )}
                                  </div>

                                  {/* Sum Insured field for RADIO type extensions — hidden for Money, Business All Risks, Householders, Houseowners, and Burglar Alarm Warranty in ELECTRONICEQUIPMENT */}
                                  {(ext.extention_limit_type || '').toString().trim().toUpperCase() === 'RADIO' && covCode !== 'MONEY' && covCode !== 'BUSINESSALLRISKS' && !['HOUSEHOLDERS', 'HOUSEOWNERS'].includes(covCode) && !(covCode === 'ELECTRONICEQUIPMENT' && (ext.s_ScreenName || '').toLowerCase().includes('burglar alarm')) && (
                                    <div className="col-span-2">
                                      <label className="block text-[10px] text-ink-faint mb-0.5">{covCode === 'PUBLICLIABILITY' ? 'Limit of Liability' : 'Sum Insured'}</label>
                                      <MoneyInput value={ext.extention_sum_insured ?? ''}
                                        onChange={v => updateExtEntry(idx, 'extention_sum_insured', v)}
                                        className="w-full px-2 py-1.5 text-sm border rounded" />
                                    </div>
                                  )}

                                  {/* Text field for NUMBER type extensions OR Free Text field for NOEDIT/DROPDOWN types */}
                                  {(ext.extention_limit_type || '').toString().trim().toUpperCase() !== 'RADIO' && covCode !== 'MONEY' && covCode !== 'ELECTRONICEQUIPMENT' && covCode !== 'PUBLICLIABILITY' && !(covCode === 'PERSONALACCIDENT' && (ext.s_ScreenName || '').toLowerCase().includes('burns')) && (
                                    <div className="col-span-2">
                                      {ext.extention_type === 'NUMBER' ? (
                                        <>
                                          <label className="block text-[10px] text-ink-faint mb-0.5">{['HOUSEHOLDERS', 'GOODSINTRANSIT'].includes(covCode) ? 'Sum Insured' : 'Free Text'}</label>
                                          <input
                                            type="text"
                                            value={ext.extention_coverage_value ?? ''}
                                            onChange={e => updateExtEntry(idx, 'extention_coverage_value', e.target.value.replace(/\D/g, ''))}
                                            placeholder="Enter number..."
                                            className="w-full px-2 py-1.5 text-sm border border-line rounded"
                                          />
                                        </>
                                      ) : (
                                        <>
                                          <label className="block text-[10px] text-ink-faint mb-0.5">{['HOUSEHOLDERS', 'GOODSINTRANSIT'].includes(covCode) ? 'Sum Insured' : 'Free Text'}</label>
                                          <textarea
                                            value={ext.extention_text_value ?? ''}
                                            onChange={e => updateExtEntry(idx, 'extention_text_value', e.target.value)}
                                            placeholder="Enter text..."
                                            rows={3}
                                            className="w-full px-2 py-1.5 text-sm border border-line rounded" />
                                        </>
                                      )}
                                    </div>
                                  )}


                                  {/* Free Text field for Locks and Keys extension in Money coverage */}
                                  {covCode === 'MONEY' && (ext.s_ScreenName || '').toLowerCase().includes('lock') && (
                                    <div className="col-span-2">
                                      <label className="block text-[10px] text-ink-faint mb-0.5">Free Text</label>
                                      <input
                                        type="text"
                                        value={ext.extention_ratefactor_value ?? ''}
                                        onChange={e => updateExtEntry(idx, 'extention_ratefactor_value', e.target.value)}
                                        placeholder="Enter text..."
                                        className="w-full px-2 py-1.5 text-sm border border-line rounded"
                                      />
                                    </div>
                                  )}

                                  {/* Sum Insured field for GIT extensions (Basis of cover, Means of conveyance) */}
                                  {covCode === 'GOODSINTRANSIT' && ((ext.s_ScreenName || '').toLowerCase().includes('basis') || (ext.s_ScreenName || '').toLowerCase().includes('means')) && (
                                    <div className="col-span-2">
                                      <label className="block text-[10px] text-ink-faint mb-0.5">Sum Insured</label>
                                      <MoneyInput
                                        value={ext.extention_sum_insured ?? ''}
                                        onChange={v => updateExtEntry(idx, 'extention_sum_insured', v)}
                                        className="w-full px-2 py-1.5 text-sm border border-line rounded"
                                      />
                                    </div>
                                  )}

                                  {/* Sum Insured field for Stated Benefits extensions */}
                                  {!['FIRE', 'BUILDINGSCOMBINED', 'OFFICECONTENTS', 'BUSINESSINTERRUPTION', 'BUSINESSINTERUPTION', 'ACCOUNTSRECEIVABLE', 'THEFT', 'GLASS', 'FIDELITYGUARANTEE', 'BUSINESSALLRISKS', 'GOODSINTRANSIT', 'ACCIDENTALDAMAGE', 'HOUSEOWNERS', 'HOUSEHOLDERS'].includes(covCode) && !(covCode === 'ELECTRONICEQUIPMENT' && (ext.s_ScreenName || '').toLowerCase().includes('burglar alarm')) && (
                                    <div className="col-span-2">
                                      <label className="block text-[10px] text-ink-faint mb-0.5">{covCode === 'PUBLICLIABILITY' ? 'Limit of Liability' : 'Sum Insured'}</label>
                                      <MoneyInput value={ext.extention_sum_insured ?? ''}
                                        onChange={v => updateExtEntry(idx, 'extention_sum_insured', v)}
                                        className="w-full px-2 py-1.5 text-sm border rounded" />
                                    </div>
                                  )}

                                  {/* Sum Insured field for RADIO extensions - only for Business All Risks */}
                                  {(ext.extention_limit_type || '').toString().trim().toUpperCase() === 'RADIO' && covCode === 'BUSINESSALLRISKS' && (
                                    <div className="col-span-2">
                                      <label className="block text-[10px] text-ink-faint mb-0.5">Sum Insured</label>
                                      <MoneyInput value={ext.extention_sum_insured ?? ''}
                                        onChange={v => updateExtEntry(idx, 'extention_sum_insured', v)}
                                        className="w-full px-2 py-1.5 text-sm border rounded" />
                                    </div>
                                  )}

                                  {/* Select Discount / Surcharge */}
                                  <div className="col-span-2">
                                    <label className="block text-[10px] text-ink-faint mb-0.5">Select Discount</label>
                                    <select value={ext.extention_discount_surcharge ?? ''}
                                      onChange={e => updateExtEntry(idx, 'extention_discount_surcharge', e.target.value)}
                                      className="w-full px-2 py-1.5 text-sm border rounded">
                                      <option value="">- Select -</option>
                                      <option value="Discount">Discount</option>
                                      <option value="Surcharge">Surcharge</option>
                                    </select>
                                  </div>

                                  {/* Discount / Surcharge Type — Flat or Percentage */}
                                  <div className="col-span-2">
                                    <label className="block text-[10px] text-ink-faint mb-0.5">Type</label>
                                    <select value={ext.extention_discount_surcharge_type ?? ''}
                                      onChange={e => updateExtEntry(idx, 'extention_discount_surcharge_type', e.target.value)}
                                      className="w-full px-2 py-1.5 text-sm border rounded">
                                      <option value="">- Select -</option>
                                      <option value="Flat">Flat</option>
                                      <option value="Percentage">Percentage</option>
                                    </select>
                                  </div>

                                  {/* Discounted / Surcharge value */}
                                  <div className="col-span-2">
                                    <label className="block text-[10px] text-ink-faint mb-0.5">Discounted/Surcharge Value</label>
                                    <MoneyInput value={ext.extention_discount_surcharge_value ?? ''}
                                      onChange={v => updateExtEntry(idx, 'extention_discount_surcharge_value', v)}
                                      className="w-full px-2 py-1.5 text-sm border rounded" />
                                  </div>

                                  {/* Premium */}
                                  <div className="col-span-2">
                                    <label className="block text-[10px] text-ink-faint mb-0.5">Premium</label>
                                    <MoneyInput value={ext.extention_calculated_value || ''}
                                      onChange={v => updateExtEntry(idx, 'extention_calculated_value', v)}
                                      className="w-full px-2 py-1.5 text-sm border rounded" />
                                  </div>
                                </div>

                                {/* Min % and Minimum Amount fields for Theft, Glass, or other excess-type extensions */}
                                {(covCode === 'THEFT' || covCode === 'GLASS' || (ext.s_ScreenName || '').toLowerCase().includes('excess')) && !(ext.s_ScreenName || '').toLowerCase().includes('burglar') && !(covCode === 'GLASS' && ((ext.s_ScreenName || '').toLowerCase().includes('special') || (ext.s_ScreenName || '').toLowerCase().includes('riot'))) && (
                                  <div className="col-span-12 mt-2 pt-2 border-t border-line">
                                    <div className="grid grid-cols-2 gap-3 w-1/2">
                                      <div>
                                        <label className="block text-[10px] text-ink-faint mb-0.5">Min %</label>
                                        <input
                                          type="text"
                                          value={ext.extention_excess_min_value ?? ''}
                                          onChange={e => updateExtEntry(idx, 'extention_excess_min_value', e.target.value.replace('%', ''))}
                                          placeholder="0"
                                          className="w-full px-2 py-1.5 text-sm border rounded"
                                        />
                                      </div>
                                      <div>
                                        <label className="block text-[10px] text-ink-faint mb-0.5">Minimum Amount</label>
                                        <MoneyInput
                                          value={ext.extention_excess_max_value ?? ''}
                                          onChange={v => updateExtEntry(idx, 'extention_excess_max_value', v)}
                                          className="w-full px-2 py-1.5 text-sm border rounded"
                                        />
                                      </div>
                                    </div>
                                  </div>
                                )}
                              </div>
                            )
                          })}
                        </div>
                      ))}

                      {/* FirstAmountPayable section (rendered AFTER grouped extensions for all coverages) */}
                      {firstAmountPayableExts.length > 0 && (
                        <div>
                          {covCode !== 'GOODSINTRANSIT' && (
                            <div className="px-4 py-3 bg-surface border-b border-line">
                              <h4 className="text-sm font-bold text-ink mb-3">First Amount Payable</h4>
                            </div>
                          )}
                          <div className={`px-4 py-2 border-b ${covCode === 'GOODSINTRANSIT' ? 'bg-status-info-bg border-primary' : 'bg-status-warning-bg border-status-warning-fg'}`}>
                            <span className={`text-sm font-bold ${covCode === 'GOODSINTRANSIT' ? 'text-primary' : 'text-status-warning-fg'}`}>
                              {firstAmountPayableExts[0]?.s_ScreenName}
                            </span>
                          </div>
                          {firstAmountPayableExts.map((ext) => {
                            const idx = extEntries.findIndex(e => e.extentions_id === ext.extentions_id)
                            return (
                              <div key={ext.extentions_id} className="px-4 py-3 border-b hover:bg-surface-2 transition">
                                <div className="grid grid-cols-12 gap-3 items-start">
                                  {/* Free Text field */}
                                  <div className="col-span-3">
                                    <label className="block text-[10px] text-ink-faint mb-0.5">{covCode === 'GOODSINTRANSIT' ? ext.s_ScreenName : 'Free Text'}</label>
                                    <textarea
                                      value={ext.extention_text_value ?? ''}
                                      onChange={e => updateExtEntry(idx, 'extention_text_value', e.target.value)}
                                      placeholder="Enter text..."
                                      rows={2}
                                      className="w-full px-2 py-1.5 text-sm border border-line rounded" />
                                  </div>

                                  {/* Min % and Minimum Amount fields */}
                                  <div className="col-span-3">
                                    <div className="space-y-2">
                                      <div>
                                        <label className="block text-[10px] text-ink-faint mb-0.5">Min %</label>
                                        <input
                                          type="text"
                                          value={ext.extention_excess_min_value ?? ''}
                                          onChange={e => updateExtEntry(idx, 'extention_excess_min_value', e.target.value.replace('%', ''))}
                                          placeholder="0"
                                          className="w-full px-2 py-1.5 text-sm border rounded"
                                        />
                                        <span className="text-[8px] text-status-danger-fg">No % sign</span>
                                      </div>
                                      <div>
                                        <label className="block text-[10px] text-ink-faint mb-0.5">Minimum Amount</label>
                                        <MoneyInput
                                          value={ext.extention_excess_max_value ?? ''}
                                          onChange={v => updateExtEntry(idx, 'extention_excess_max_value', v)}
                                          className="w-full px-2 py-1.5 text-sm border rounded"
                                        />
                                      </div>
                                    </div>
                                  </div>

                                  {/* Select Discount */}
                                  <div className="col-span-2">
                                    <label className="block text-[10px] text-ink-faint mb-0.5">Select Discount</label>
                                    <select value={ext.extention_discount_surcharge ?? ''}
                                      onChange={e => updateExtEntry(idx, 'extention_discount_surcharge', e.target.value)}
                                      className="w-full px-2 py-1.5 text-sm border rounded">
                                      <option value="">- Select -</option>
                                      <option value="Discount">Discount</option>
                                      <option value="Surcharge">Surcharge</option>
                                    </select>
                                  </div>

                                  {/* Discount Type */}
                                  <div className="col-span-2">
                                    <label className="block text-[10px] text-ink-faint mb-0.5">Type</label>
                                    <select value={ext.extention_discount_surcharge_type ?? ''}
                                      onChange={e => updateExtEntry(idx, 'extention_discount_surcharge_type', e.target.value)}
                                      className="w-full px-2 py-1.5 text-sm border rounded">
                                      <option value="">- Select -</option>
                                      <option value="Flat">Flat</option>
                                      <option value="Percentage">Percentage</option>
                                    </select>
                                  </div>

                                  {/* Discounted/Surcharge Value */}
                                  <div className="col-span-1">
                                    <label className="block text-[10px] text-ink-faint mb-0.5">Discounted/Surcharge Value</label>
                                    <MoneyInput value={ext.extention_discount_surcharge_value ?? ''}
                                      onChange={v => updateExtEntry(idx, 'extention_discount_surcharge_value', v)}
                                      className="w-full px-2 py-1.5 text-sm border rounded" />
                                  </div>

                                  {/* Premium */}
                                  <div className="col-span-1">
                                    <label className="block text-[10px] text-ink-faint mb-0.5">Premium</label>
                                    <MoneyInput value={ext.extention_calculated_value || ''}
                                      onChange={v => updateExtEntry(idx, 'extention_calculated_value', v)}
                                      className="w-full px-2 py-1.5 text-sm border rounded" />
                                  </div>
                                </div>
                              </div>
                            )
                          })}
                        </div>
                      )}
                    </>
                  )
                })()}
                {/* ── Burglar Alarm Warranty Textarea ─────────────────────────────
                    For Office Content coverage: show warranty text when "Burglar
                    Alarm Warranty" extension is set to "Yes". The text is editable
                    and persists to policy_coverages.burglar_alarm_warranty.
                    Appears at the end of the Extensions section. */}
                {(() => {
                  const burglarExt = extEntries.find(e =>
                    (e.s_ScreenName || '').toLowerCase().includes('burglar alarm warranty')
                  )
                  const isTaken = burglarExt?.is_taken !== false  // default true for backward compatibility
                  return burglarExt && isTaken && (
                    <>
                      <hr className="border-line" />
                      <div className="px-4 py-3 bg-status-warning-bg">
                        <h4 className="text-sm font-bold text-status-warning-fg mb-3">Burglar Alarm Warranty</h4>
                        <textarea
                          value={(coverageForm as any).burglar_alarm_warranty || ''}
                          onChange={e => setCoverageForm({ ...coverageForm, burglar_alarm_warranty: e.target.value } as any)}
                          className="w-full px-3 py-2 text-sm border rounded font-mono"
                          style={{ minHeight: '200px' }}
                          placeholder="Enter Burglar Alarm Warranty terms"
                        />
                      </div>
                    </>
                  )
                })()}
              </div>
            )}
            {extLoading && <div className="text-sm text-ink-muted py-2">Loading extensions...</div>}

            {/* API validation errors from coverage add/update — shows field-specific errors from backend */}
            {(() => {
              const fieldErrors = Object.entries(errors)
                .filter(([k]) => !['_general', 'cov_duplicate_family', 'cov_risk_address_id', 'cov_coverage_id', 'cov_coverage_value'].includes(k))
                .filter(([, v]) => !!v)
              return fieldErrors.length > 0 ? (
                <div className="mb-4 p-3 bg-status-danger-bg border border-status-danger-fg rounded-md text-status-danger-fg text-sm">
                  <div className="flex items-start justify-between">
                    <div>
                      <div className="font-medium mb-1">Validation errors:</div>
                      <ul className="list-disc list-inside space-y-0.5">
                        {fieldErrors.map(([k, v]) => (
                          <li key={k} className="text-xs">
                            <span className="font-medium">{k}:</span> {v}
                          </li>
                        ))}
                      </ul>
                    </div>
                    <button type="button" onClick={() => {
                      const updated = { ...errors }
                      fieldErrors.forEach(([k]) => { delete updated[k] })
                      setErrors(() => updated)
                    }} className="ml-2 text-status-danger-fg hover:text-status-danger-fg shrink-0">✕</button>
                  </div>
                </div>
              ) : null
            })()}

            {/* Duplicate singleton-family coverage error (CAR/EAR/PAR/MB/MM/PI/D&O/
                Marine Open/Marine Once-Off/Travel) — rendered above Miscellaneous
                Items per UX request. Source: handleSaveCoverage in PolicyCreatePage
                + coverageMutation.onError for backend 422 responses. */}
            {errors.cov_duplicate_family && (
              <div className="mb-4 p-3 bg-status-danger-bg border border-status-danger-fg rounded-md text-status-danger-fg text-sm flex items-center justify-between">
                <span>{errors.cov_duplicate_family}</span>
                <button type="button" onClick={() => setErrors(p => ({ ...p, cov_duplicate_family: '' }))} className="ml-2 text-status-danger-fg hover:text-status-danger-fg">✕</button>
              </div>
            )}

            {/* ── Miscellaneous Items ───────────────────────────────────────
                Legacy parity (graphiteBWV8 manage-coverages.blade.php line 5489+):
                  * Dropdown-only — every row picks from specified_coverage_items
                    for this coverage. No free-text path.
                  * Changing the dropdown updates the row's rate from the master
                    and recomputes the premium (sum × rate / 100). Downstream
                    the coverage total picks this up in handleSave.
                  * Persisted rows stay as editable dropdowns too — operator
                    can swap one item for another and the new rate flows through.
                  * Hidden on motor coverages (commercial + domestic) — per-vehicle
                    Specified Items + Notes cover that flow instead. */}

            {/* ── Theft Excess Section ─────────────────────────────────────────────
                For Theft coverage: show Excess Name, Min %, Minimum Amount,
                Select Discount, Discount Type, Discounted/Surcharge Value, Premium.
                Stored in policy_excesses_data table. */}
            {coverageForm.coverage_id && (covCode === 'THEFT' || covCode === 'COMPUTEREQUIPMENT') && (
              <div className="border rounded-lg overflow-hidden mb-4">
                <div className="bg-status-info-bg px-4 py-2 border-b flex items-center justify-between">
                  <h4 className="text-sm font-bold text-primary">Excess</h4>
                  <button type="button" onClick={addExcess}
                    className="px-3 py-1 text-xs font-medium text-primary bg-status-info-bg border border-primary rounded hover:bg-status-info-bg">+ Add</button>
                </div>
                {excEntries.length > 0 ? (
                  excEntries.map((exc, idx) => (
                    <div key={idx} className="px-4 py-3 border-b hover:bg-surface-2 transition">
                      {/* Row 1: Excess Name, Min %, Minimum Amount */}
                      <div className="grid grid-cols-12 gap-2 items-start mb-3">
                        {/* Excess Name */}
                        <div className="col-span-3">
                          <label className="block text-[10px] text-ink-faint mb-0.5">Excess Name</label>
                          <textarea value={exc.excesses || ''}
                            onChange={e => updateExcEntry(idx, 'excesses', e.target.value)}
                            className="w-full px-2 py-1.5 text-xs border rounded" placeholder="e.g. Basic Excess" rows={2} />
                        </div>

                        {/* Min % */}
                        <div className="col-span-2">
                          <label className="block text-[10px] text-ink-faint mb-0.5">Min %</label>
                          <input type="text" value={exc.min_percent || ''}
                            onChange={e => updateExcEntry(idx, 'min_percent', e.target.value.replace('%', ''))}
                            className="w-full px-2 py-1.5 text-xs border rounded" placeholder="0" />
                          <span style={{color:'red'}} className="text-[8px]">No % sign</span>
                        </div>

                        {/* Minimum Amount */}
                        <div className="col-span-3">
                          <label className="block text-[10px] text-ink-faint mb-0.5">Minimum Amount</label>
                          <MoneyInput value={exc.min_amt || ''}
                            onChange={v => updateExcEntry(idx, 'min_amt', v)}
                            className="w-full px-2 py-1.5 text-xs border rounded" />
                        </div>

                        {/* Select Discount */}
                        <div className="col-span-4">
                          <label className="block text-[10px] text-ink-faint mb-0.5">Select Discount</label>
                          <select value={exc.discount_surcharge || ''}
                            onChange={e => updateExcEntry(idx, 'discount_surcharge', e.target.value)}
                            className="w-full px-2 py-1.5 text-xs border rounded bg-surface">
                            <option value="">- Select -</option>
                            <option value="Discount">Discount</option>
                            <option value="Surcharge">Surcharge</option>
                          </select>
                        </div>
                      </div>

                      {/* Row 2: Discount Type, Discounted/Surcharge Value, Premium, Remove button */}
                      <div className="grid grid-cols-12 gap-2 items-start">
                        {/* Discount Type */}
                        <div className="col-span-2">
                          <label className="block text-[10px] text-ink-faint mb-0.5">Select Disco...</label>
                          <select value={exc.discount_surcharge_type || ''}
                            onChange={e => updateExcEntry(idx, 'discount_surcharge_type', e.target.value)}
                            className="w-full px-2 py-1.5 text-xs border rounded bg-surface">
                            <option value="">- Select -</option>
                            <option value="Flat">Flat</option>
                            <option value="Percentage">Percentage</option>
                          </select>
                        </div>

                        {/* Discounted/Surcharge Value */}
                        <div className="col-span-3">
                          <label className="block text-[10px] text-ink-faint mb-0.5">Discounted/Surcharge Value</label>
                          <MoneyInput value={exc.discount_surcharge_value || ''}
                            onChange={v => updateExcEntry(idx, 'discount_surcharge_value', v)}
                            className="w-full px-2 py-1.5 text-xs border rounded" />
                        </div>

                        {/* Premium */}
                        <div className="col-span-3">
                          <label className="block text-[10px] text-ink-faint mb-0.5">Premium</label>
                          <MoneyInput value={exc.premium || ''}
                            onChange={v => updateExcEntry(idx, 'premium', v)}
                            className="w-full px-2 py-1.5 text-xs border rounded" />
                        </div>

                        {/* Remove button */}
                        <div className="col-span-4 flex justify-end">
                          <button type="button" onClick={() => setExcEntries(p => p.filter((_, i) => i !== idx))}
                            className="px-3 py-1 text-xs font-medium text-status-danger-fg bg-status-danger-bg border border-status-danger-fg rounded hover:bg-status-danger-bg">− Remove</button>
                        </div>
                      </div>
                    </div>
                  ))
                ) : (
                  <div className="px-4 py-3 text-sm text-ink-faint">No excesses added. Click "+ Add Excess" if applicable.</div>
                )}
              </div>
            )}

            {coverageForm.coverage_id && !isMotorCoverage && (
              <div className="border rounded-lg overflow-hidden">
                <div className="bg-status-warning-bg px-4 py-2 border-b flex items-center justify-between">
                  <h4 className="text-sm font-bold text-status-warning-fg flex items-center gap-2">
                    Miscellaneous Items
                    {siEntries.length > 0 && <span className="text-ink-muted font-normal">({siEntries.length})</span>}
                    {siLoading && (
                      <span className="inline-flex items-center gap-1 text-[10px] font-normal text-status-warning-fg">
                        <span className="w-3 h-3 border-2 border-status-warning-fg border-t-transparent rounded-full animate-spin" />
                        loading options...
                      </span>
                    )}
                  </h4>
                  <button type="button" onClick={addSiRow} disabled={siLoading}
                    className="px-3 py-1 text-xs font-medium text-status-warning-fg bg-status-warning-bg border border-status-warning-fg rounded hover:bg-status-warning-bg disabled:opacity-50">+ Add Item</button>
                </div>
                {siEntries.length > 1 && (
                  <div className="px-4 py-2 border-b bg-surface">
                    <input
                      type="text"
                      value={siSearch}
                      onChange={e => setSiSearch(e.target.value)}
                      placeholder="Search added items by description..."
                      className="w-full px-3 py-1.5 text-xs border border-line rounded focus:ring-2 focus:ring-status-warning-fg focus:border-status-warning-fg"
                    />
                  </div>
                )}
                {siLoading && siEntries.length === 0 ? (
                  <p className="px-4 py-3 text-xs text-ink-muted italic">Loading master items for this coverage...</p>
                ) : siEntries.length === 0 && siOptions.length === 0 ? (
                  <p className="px-4 py-3 text-xs text-status-warning-fg italic">
                    No specified-item master entries configured for this coverage. Add rows via Coverage Management → Specified Coverage Items.
                  </p>
                ) : siEntries.length === 0 ? (
                  <p className="px-4 py-3 text-xs text-ink-muted italic">
                    No items added yet. Click <b>+ Add Item</b> to pick from the {siOptions.length} configured item{siOptions.length === 1 ? '' : 's'} for this coverage.
                  </p>
                ) : (
                  <div className="overflow-x-auto">
                    <table className="w-full text-sm table-fixed">
                      <thead>
                        <tr className="text-left text-[10px] text-ink-muted border-b bg-surface-2">
                          <th className="px-3 py-2 w-[35%]">Item Description</th>
                          <th className="px-3 py-2 w-[20%]">{covCode === 'PUBLICLIABILITY' ? 'Limit of Liability' : 'Sum Insured'}</th>
                          <th className="px-3 py-2 w-[14%]">Rate %</th>
                          <th className="px-3 py-2 w-[20%]">Premium</th>
                          <th className="px-3 py-2 w-[11%]"></th>
                        </tr>
                      </thead>
                      <tbody>
                        {(() => {
                          const term = siSearch.trim().toLowerCase()
                          const rows = siEntries
                            .map((si, idx) => ({ si, idx }))
                            .filter(({ si }) => {
                              if (!term) return true
                              const opt = siOptions.find(o => o.id === Number(si.specified_coverage_id))
                              return (si.name || opt?.name || '').toLowerCase().includes(term)
                            })
                          if (rows.length === 0) {
                            return (
                              <tr><td colSpan={5} className="px-3 py-3 text-xs text-ink-muted italic text-center">No items match “{siSearch}”.</td></tr>
                            )
                          }
                          return rows.map(({ si, idx }) => (
                          <tr key={idx} className={`border-b ${si.deleted_at ? 'bg-status-danger-bg opacity-60' : 'hover:bg-surface-2'}`}>
                            <td className="px-3 py-1">
                              <SiCombobox
                                value={si.specified_coverage_id ?? null}
                                disabled={!!si.deleted_at}
                                loading={siLoading}
                                options={(() => {
                                  // Merge saved selection into options if missing so the
                                  // preselected item still shows correctly in edit mode.
                                  if (!si.specified_coverage_id || siOptions.some(o => o.id === Number(si.specified_coverage_id))) return siOptions
                                  return [{ id: Number(si.specified_coverage_id), name: si.name || `Item #${si.specified_coverage_id}`, rate: si.rate || '0' }, ...siOptions]
                                })()}
                                onChange={id => updateSiEntry(idx, 'specified_coverage_id', String(id))}
                              />
                            </td>
                            <td className="px-3 py-1">
                              <MoneyInput value={si.sum_insured}
                                readOnly={!!si.deleted_at}
                                onChange={v => updateSiEntry(idx, 'sum_insured', v)}
                                className="w-full px-2 py-1.5 text-sm border border-line rounded" />
                            </td>
                            <td className="px-3 py-1">
                              <div title={si.rate ? `Full rate: ${si.rate}%` : 'Rate is managed on the specified-item master'}
                                className="w-full px-2 py-1.5 text-sm text-ink-muted bg-surface-2 border border-line rounded truncate">
                                {si.rate ? Number(si.rate).toFixed(6).replace(/\.?0+$/, '') : '—'}
                              </div>
                            </td>
                            <td className="px-3 py-1">
                              <MoneyInput value={si.calculated_value}
                                readOnly={true}
                                onChange={() => {}}
                                className="w-full px-2 py-1.5 text-sm border border-line rounded bg-surface-2" />
                            </td>
                            <td className="px-3 py-1 text-center">
                              {si.deleted_at ? (
                                <button type="button" onClick={() => reinstateSiRow(idx)}
                                  className="px-2 py-1 text-xs font-medium text-white bg-primary rounded hover:bg-primary">
                                  Reinstate
                                </button>
                              ) : (
                                <button type="button" onClick={() => removeSiRow(idx)}
                                  className="text-status-danger-fg hover:text-status-danger-fg text-sm font-bold">✕</button>
                              )}
                            </td>
                          </tr>
                          ))
                        })()}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            )}

            {/* ── Excesses / Deductibles ──────────────────────────
                Captures minimum excess amounts and percentage thresholds for claim processing.
                Shows for Fidelity Guarantee and Business All Risks coverages.
            */}
            {coverageForm.coverage_id && covCode === 'BUSINESSALLRISKS' && (
              <div className="border rounded-lg overflow-hidden">
                {/* Section header with count and add button */}
                <div className="bg-status-danger-bg px-4 py-2 border-b flex items-center justify-between">
                  <h4 className="text-sm font-bold text-status-danger-fg">Excesses / Deductibles ({excEntries.length})</h4>
                  <button type="button" onClick={addExcess}
                    className="px-3 py-1 text-xs font-medium text-status-danger-fg bg-status-danger-bg border border-status-danger-fg rounded hover:bg-status-danger-bg">+ Add Excess</button>
                </div>
                {/* Render each excess row with name, min %, and min amount */}
                {excEntries.map((exc, idx) => (
                  <div key={idx} className="px-4 py-3 border-b hover:bg-surface-2 transition">
                    <div className="grid grid-cols-12 gap-2 items-start">
                      {/* Excess Name - description of excess type (Standard, Preferred Customer, etc) */}
                      <div className="col-span-4">
                        <label className="block text-[10px] text-ink-faint mb-0.5">Excess Name</label>
                        <textarea value={exc.excesses}
                          onChange={e => updateExcEntry(idx, 'excesses', e.target.value)}
                          className="w-full px-2 py-1.5 text-xs border rounded" placeholder="e.g. Standard Excess" rows={2} />
                      </div>

                      {/* Min % - minimum percentage threshold for this excess tier (numeric only, no % sign) */}
                      <div className="col-span-4">
                        <label className="block text-[10px] text-ink-faint mb-0.5">Min %</label>
                        <input type="text" value={exc.min_percent}
                          onChange={e => updateExcEntry(idx, 'min_percent', e.target.value.replace('%', ''))}
                          className="w-full px-2 py-1.5 text-xs border rounded" placeholder="0" />
                        <span style={{color:'red'}} className="text-[8px]">No % sign</span>
                      </div>

                      {/* Minimum Amount - absolute minimum amount payable as excess (currency) */}
                      <div className="col-span-4">
                        <label className="block text-[10px] text-ink-faint mb-0.5">Minimum Amount</label>
                        <MoneyInput value={exc.min_amt}
                          onChange={v => updateExcEntry(idx, 'min_amt', v)}
                          className="w-full px-2 py-1.5 text-xs border rounded" />
                      </div>

                      {/* Delete/Remove button - removes the entire excess row from the coverage */}
                      <div className="col-span-12 flex justify-end pt-2">
                        <button type="button" onClick={() => setExcEntries(p => p.filter((_, i) => i !== idx))}
                          className="px-3 py-1 text-xs font-medium text-status-danger-fg bg-status-danger-bg border border-status-danger-fg rounded hover:bg-status-danger-bg">− Remove</button>
                      </div>
                    </div>
                  </div>
                ))}
                {excEntries.length === 0 && (
                  <div className="px-4 py-3 text-sm text-ink-faint">No excesses added. Click "+ Add Excess" if applicable.</div>
                )}
              </div>
            )}

            {/* ── Electronics Equipment: Excesses ──
                For Electronics Equipment: show Excess Name, Min %, Minimum Amount,
                Select Discount, Discount Type, Discounted/Surcharge Value, Premium.
                Stored in policy_extention_detail table with type='Excess'. */}
            {coverageForm.coverage_id && covCode === 'ELECTRONICEQUIPMENT' && (
              <div className="border rounded-lg overflow-hidden mb-4">
                <div className="bg-status-warning-bg px-4 py-2 border-b flex items-center justify-between">
                  <h4 className="text-sm font-bold text-status-warning-fg">Excesses ({excEntries.length})</h4>
                  <button type="button" onClick={addExcess}
                    className="px-3 py-1 text-xs font-medium text-status-warning-fg bg-status-warning-bg border border-status-warning-fg rounded hover:bg-status-warning-bg">+ Add Excess</button>
                </div>
                {excEntries.length > 0 ? (
                  excEntries.map((exc, idx) => (
                    <div key={idx} className="px-4 py-3 border-b hover:bg-surface-2 transition">
                      {/* Row 1: Excess Name, Min %, Minimum Amount */}
                      <div className="grid grid-cols-12 gap-2 items-start mb-3">
                        {/* Excess Name */}
                        <div className="col-span-3">
                          <label className="block text-[10px] text-ink-faint mb-0.5">Excess Name</label>
                          <textarea value={exc.excesses || ''}
                            onChange={e => updateExcEntry(idx, 'excesses', e.target.value)}
                            className="w-full px-2 py-1.5 text-xs border rounded" placeholder="e.g. Basic Excess" rows={2} />
                        </div>

                        {/* Min % */}
                        <div className="col-span-2">
                          <label className="block text-[10px] text-ink-faint mb-0.5">Min %</label>
                          <input type="text" value={exc.min_percent || ''}
                            onChange={e => updateExcEntry(idx, 'min_percent', e.target.value.replace('%', ''))}
                            className="w-full px-2 py-1.5 text-xs border rounded" placeholder="0" />
                          <span style={{color:'red'}} className="text-[8px]">No % sign</span>
                        </div>

                        {/* Minimum Amount */}
                        <div className="col-span-3">
                          <label className="block text-[10px] text-ink-faint mb-0.5">Minimum Amount</label>
                          <MoneyInput value={exc.min_amt || ''}
                            onChange={v => updateExcEntry(idx, 'min_amt', v)}
                            className="w-full px-2 py-1.5 text-xs border rounded" />
                        </div>

                        {/* Select Discount */}
                        <div className="col-span-4">
                          <label className="block text-[10px] text-ink-faint mb-0.5">Select Discount</label>
                          <select value={exc.discount_surcharge || ''}
                            onChange={e => updateExcEntry(idx, 'discount_surcharge', e.target.value)}
                            className="w-full px-2 py-1.5 text-xs border rounded bg-surface">
                            <option value="">- Select -</option>
                            <option value="Discount">Discount</option>
                            <option value="Surcharge">Surcharge</option>
                          </select>
                        </div>
                      </div>

                      {/* Row 2: Discount Type, Discounted/Surcharge Value, Premium, Remove button */}
                      <div className="grid grid-cols-12 gap-2 items-start">
                        {/* Discount Type */}
                        <div className="col-span-2">
                          <label className="block text-[10px] text-ink-faint mb-0.5">Select Disco...</label>
                          <select value={exc.discount_surcharge_type || ''}
                            onChange={e => updateExcEntry(idx, 'discount_surcharge_type', e.target.value)}
                            className="w-full px-2 py-1.5 text-xs border rounded bg-surface">
                            <option value="">- Select -</option>
                            <option value="Flat">Flat</option>
                            <option value="Percentage">Percentage</option>
                          </select>
                        </div>

                        {/* Discounted/Surcharge Value */}
                        <div className="col-span-3">
                          <label className="block text-[10px] text-ink-faint mb-0.5">Discounted/Surcharge Value</label>
                          <MoneyInput value={exc.discount_surcharge_value || ''}
                            onChange={v => updateExcEntry(idx, 'discount_surcharge_value', v)}
                            className="w-full px-2 py-1.5 text-xs border rounded" />
                        </div>

                        {/* Premium */}
                        <div className="col-span-3">
                          <label className="block text-[10px] text-ink-faint mb-0.5">Premium</label>
                          <MoneyInput value={exc.premium || ''}
                            onChange={v => updateExcEntry(idx, 'premium', v)}
                            className="w-full px-2 py-1.5 text-xs border rounded" />
                        </div>

                        {/* Remove button */}
                        <div className="col-span-4 flex justify-end">
                          <button type="button" onClick={() => setExcEntries(p => p.filter((_, i) => i !== idx))}
                            className="px-3 py-1 text-xs font-medium text-status-danger-fg bg-status-danger-bg border border-status-danger-fg rounded hover:bg-status-danger-bg">− Remove</button>
                        </div>
                      </div>
                    </div>
                  ))
                ) : (
                  <div className="px-4 py-3 text-sm text-ink-faint">No excesses added. Click "+ Add Excess" if applicable.</div>
                )}
              </div>
            )}

            {/* ── Money Coverage: Memoranda and Warranties ── */}
            {coverageForm.coverage_id && covCode === 'MONEY' && (
              <div className="space-y-4">
                {/* Memoranda and Warranties */}
                <div className="border rounded-lg overflow-hidden">
                  <div className="bg-status-accent-bg px-4 py-2 border-b">
                    <h4 className="text-sm font-bold text-status-accent-fg">Memoranda and Warranties</h4>
                  </div>
                  <div className="px-4 py-3">
                    <textarea value={coverageForm.memoranda_warranty || ''}
                      onChange={e => setCoverageForm({...coverageForm, memoranda_warranty: e.target.value})}
                      placeholder="Enter Memoranda and Warranties..."
                      rows={5}
                      className="w-full px-3 py-2 text-sm border rounded-md focus:ring-2 focus:ring-status-accent-fg focus:border-status-accent-fg" />
                  </div>
                </div>

                {/* Cash Carrying Warranty */}
                <div className="border rounded-lg overflow-hidden">
                  <div className="bg-status-danger-bg px-4 py-2 border-b">
                    <h4 className="text-sm font-bold text-status-danger-fg">Cash Carrying Warranty</h4>
                  </div>
                  <div className="px-4 py-3">
                    <textarea value={coverageForm.cash_warranty || ''}
                      onChange={e => setCoverageForm({...coverageForm, cash_warranty: e.target.value})}
                      placeholder="Enter Cash Carrying Warranty..."
                      rows={5}
                      className="w-full px-3 py-2 text-sm border rounded-md focus:ring-2 focus:ring-status-danger-fg focus:border-status-danger-fg" />
                  </div>
                </div>
              </div>
            )}

            {/* ── Motor Vehicles (moved to Extensions section above) ── */}
            {false && isMotorCoverage && coverageForm.coverage_id && (
              <div className="border rounded-lg overflow-hidden">
                <div className="bg-status-info-bg px-4 py-2 border-b">
                  <h4 className="text-sm font-bold text-primary">Summary Of Vehicles</h4>
                </div>
                {/* Vehicle selector */}
                {/* Existing vehicle selector */}
                {availableVehicles.length > 0 && !showAddVehicle && (
                  <div className="px-4 py-3 border-b bg-surface-2 flex items-center gap-3">
                    <div className="flex-1 relative">
                      <label className="block text-[10px] text-ink-faint mb-0.5">Select Existing Vehicle</label>
                      <input type="text" value={vehicleSearch}
                        onChange={e => { setVehicleSearch(e.target.value); setShowVehicleDropdown(true) }}
                        onFocus={() => setShowVehicleDropdown(true)}
                        placeholder="Search by plate, make, model..."
                        className="w-full px-3 py-2 text-sm border rounded" />
                      {showVehicleDropdown && (
                        <div className="absolute z-50 w-full mt-1 bg-surface border rounded-md shadow-lg max-h-48 overflow-y-auto">
                          {availableVehicles.filter((v: any) => { const q = vehicleSearch.toLowerCase(); return !q || (v.label||'').toLowerCase().includes(q) || (v.plate||'').toLowerCase().includes(q) })
                            .map((v: any) => (
                              <button key={v.id} type="button"
                                onClick={() => { setSelectedVehicleId(String(v.id)); setVehicleSearch(`${v.make} ${v.model} — ${v.plate}`); setShowVehicleDropdown(false) }}
                                className="w-full text-left px-3 py-2 text-sm hover:bg-status-info-bg border-b last:border-0">
                                <span className="font-medium">{v.plate}</span> — {v.make} {v.model}
                              </button>
                            ))}
                        </div>
                      )}
                    </div>
                    <button type="button" onClick={() => { addVehicleToCoverage(); setVehicleSearch('') }} disabled={!selectedVehicleId || motorLoading}
                      className="mt-4 w-10 h-10 flex items-center justify-center rounded-md bg-status-success-fg text-white hover:bg-status-success-fg disabled:opacity-50 text-lg font-bold">+</button>
                  </div>
                )}
                {/* Add New Vehicle — redirects to the Vehicles tab on the
                    Policy Detail page where the full Add Vehicle modal lives
                    (captures Chassis, Engine, Motor Type, etc.). The inline
                    form below is left intact but unreachable. */}
                <div className="px-4 py-2 border-b flex justify-end">
                  <button type="button" onClick={() => {
                    if (policyId) navigate(`/policies/${policyId}?tab=vehicles&add=1`)
                  }} className="text-sm text-primary hover:text-primary font-medium">
                    + Add New Vehicle
                  </button>
                </div>
                {/* Inline Add-new-vehicle form removed — the "+ Add New Vehicle"
                    button above now redirects to the Vehicles tab where the
                    full Add Vehicle modal lives. */}
                {motorLoading && <div className="px-4 py-3 text-sm text-ink-muted">Loading vehicles...</div>}
                {/* Vehicle summary table */}
                {motorVehicles.length > 0 && (
                  <div className="overflow-x-auto">
                    <table className="w-full text-sm table-fixed">
                      <thead>
                        <tr className="text-left text-[10px] text-ink-muted border-b bg-surface-2">
                          <th className="px-2 py-2 w-[14%]">Description</th>
                          <th className="px-2 py-2 w-[10%]">Reg No</th>
                          <th className="px-2 py-2 w-[10%]">Est. Value</th>
                          <th className="px-2 py-2 w-[14%]">Usage</th>
                          <th className="px-2 py-2 w-[18%]">Type of Cover</th>
                          <th className="px-2 py-2 w-[15%]">Sum Insured</th>
                          <th className="px-2 py-2 w-[15%]">Premium</th>
                          <th className="px-2 py-2 w-[4%]"></th>
                        </tr>
                      </thead>
                      <tbody>
                        {motorVehicles.map((m: any) => (
                          <MotorVehicleRow
                            key={m.id}
                            motor={m}
                            specifiedItems={((coverageForm as any).specified_items || []).filter((s: any) => s.motor_id === m.id)}
                            siOptions={siOptions}
                            siLoading={siLoading}
                            note={m.note || ''}
                            onUpdate={updateMotorRow}
                            onDelete={deleteMotorRow}
                            onReinstate={reinstateMotorRow}
                            onUpdateItem={updateMotorSpecifiedItem}
                            onAddItem={addMotorSpecifiedItem}
                            onDeleteItem={deleteMotorSpecifiedItem}
                            onReinstateItem={reinstateMotorSpecifiedItem}
                            onUpdateNote={upsertMotorNote}
                          />
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
                {motorVehicles.length === 0 && !motorLoading && (
                  <div className="px-4 py-3 text-sm text-ink-faint">No vehicles added. Select a vehicle above and click +.</div>
                )}
              </div>
            )}

            {/* Coverage-level Discount/Surcharge + Notes — hidden on motor.
                Motor uses per-vehicle Note for this Vehicle instead. */}
            {!isMotorCoverage && (
              <>
                {/* Discount/Surcharge fields — COMMENTED OUT */}
                {/* <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <SelectField label="Discount/Surcharge" value={coverageForm.discount_type}
                    onChange={v => update('discount_type', v)}
                    options={[{ id: 'Discount', name: 'Discount' }, { id: 'Surcharge', name: 'Surcharge' }]}
                    placeholder="None" />

                  {coverageForm.discount_type && (
                    <InputField label="Discount/Surcharge Value" type="number" value={coverageForm.discount_value}
                      onChange={v => update('discount_value', v)} placeholder="Amount or %" />
                  )}
                </div> */}

                <TextAreaField label="Coverage Notes" value={coverageForm.notes} rows={4}
                  onChange={v => update('notes', v)} placeholder="Any notes for this coverage..." />
              </>
            )}

            <button onClick={handleSave} disabled={saving}
              className="px-4 py-2 bg-primary text-white text-sm font-medium rounded-md hover:bg-primary disabled:opacity-50">
              {saving ? 'Saving...' : (coverageForm as any)._dbId ? 'Update Coverage' : 'Add Coverage'}
            </button>
          </>
        )}
      </Section>
      </>
        )
      })()}
      </div>
    </div>
  )
}

/** Saved coverage row — extracted outside to prevent focus loss */
/** Motor vehicle row — expandable to show all fields + specified items */
function MotorVehicleRow({ motor: m, specifiedItems, note, siOptions, siLoading, onUpdate, onDelete, onReinstate, onUpdateItem, onAddItem, onDeleteItem, onReinstateItem, onUpdateNote }: {
  motor: any
  specifiedItems?: any[]   // items with motor_id === m.id
  note?: string            // per-motor note text
  siOptions?: { id: number; name: string; rate: string }[]  // specified_coverage_items master for this coverage
  siLoading?: boolean      // true while siOptions are still being fetched
  onUpdate: (id: number, field: string, value: any) => void
  onDelete: (id: number) => void
  onReinstate?: (id: number) => void
  onUpdateItem?: (itemId: number, fieldOrFields: string | Record<string, any>, value?: any) => void
  onAddItem?: (motorId: number, specifiedCoverageId: number, sum: number, rate: number, customName?: string) => void
  onDeleteItem?: (itemId: number) => void
  onReinstateItem?: (itemId: number) => void
  onUpdateNote?: (motorId: number, note: string) => void
}) {
  const [expanded, setExpanded] = useState(false)
  const [noteDraft, setNoteDraft] = useState(note ?? '')
  useEffect(() => { setNoteDraft(note ?? '') }, [note])
  // Auto-save the per-vehicle note shortly after typing stops. The note used
  // to persist ONLY on blur, so clicking "Update Coverage" right after typing
  // (without the textarea blurring/flushing first) saved a stale value and the
  // newest lines were discarded — i.e. "only the first few lines saved". This
  // debounced save guarantees the FULL multi-line note is persisted while the
  // operator is still typing, well before they hit the button.
  const noteSaveTimer = useRef<ReturnType<typeof setTimeout> | null>(null)
  useEffect(() => {
    if (!onUpdateNote) return
    if (noteDraft === (note ?? '')) return
    if (noteSaveTimer.current) clearTimeout(noteSaveTimer.current)
    noteSaveTimer.current = setTimeout(() => { onUpdateNote(m.id, noteDraft) }, 500)
    return () => { if (noteSaveTimer.current) clearTimeout(noteSaveTimer.current) }
  }, [noteDraft])
  // Add-item inline form — master-dropdown only (no free-text / custom path).
  const [adding, setAdding] = useState(false)
  const [newMasterId, setNewMasterId] = useState('')
  const [newSum, setNewSum] = useState('')
  const [newRate, setNewRate] = useState('')
  const resetAdd = () => { setAdding(false); setNewMasterId(''); setNewSum(''); setNewRate('') }
  const SUB_COVERAGES = [
    { key: 'wreckage_removal', pKey: 'premium_wreckage_removal', label: 'Wreckage Removal' },
    { key: 'window_glass', pKey: 'premium_window_glass', label: 'Window Glass' },
    { key: 'locks_keys', pKey: 'premium_locks_keys', label: 'Locks & Keys' },
    { key: 'parts_accessories', pKey: 'premium_parts_accessories', label: 'Parts & Accessories' },
    { key: 'audio_accessories', pKey: 'premium_audio_accessories', label: 'Audio Accessories' },
    { key: 'riot_strike', pKey: 'premium_riot_strike', label: 'Riot & Strike' },
    { key: 'car_hire_theft', pKey: 'premium_car_hire_theft', label: 'Car Hire (Theft)' },
    { key: 'credit_shortfall', pKey: 'premium_credit_shortfall', label: 'Credit Shortfall' },
  ]
  return (
    <>
      <tr className={`border-b hover:bg-surface-2 ${m.deleted_at ? 'opacity-50 bg-status-danger-bg' : ''}`}>
        <td className="px-2 py-1" onClick={e => e.stopPropagation()}>
          <input type="text" value={m.vehicle_name || `${m.make || ''} ${m.model || ''}`.trim()}
            onChange={e => onUpdate(m.id, 'vehicle_name', e.target.value)}
            placeholder="Make Model"
            disabled={!!m.deleted_at}
            className="w-full px-2 py-1.5 text-xs font-medium border border-line rounded disabled:bg-surface-2" />
        </td>
        <td className="px-2 py-1" onClick={e => e.stopPropagation()}>
          <input type="text" value={m.registration_no || ''}
            onChange={e => onUpdate(m.id, 'registration_no', e.target.value.toUpperCase())}
            placeholder="Plate"
            title={`Engine: ${m.engine_number || '—'}\nChassis: ${m.chassis_number || '—'}`}
            className="w-full px-2 py-1.5 text-xs border border-line rounded font-mono uppercase" />
        </td>
        <td className="px-2 py-1" onClick={e => e.stopPropagation()}>
          <MoneyInput value={m.estimated_value || ''}
            onChange={v => onUpdate(m.id, 'estimated_value', v)}
            className="w-full px-2 py-1.5 text-xs border border-line rounded" />
        </td>
        <td className="px-2 py-1" onClick={e => e.stopPropagation()}>
          <select value={m.use || ''} onChange={e => onUpdate(m.id, 'use', e.target.value)}
            className="w-full px-2 py-1.5 text-sm border border-line rounded">
            <option value="">-Select-</option>
            <option value="Private">Private</option>
            <option value="Commercial">Commercial</option>
            <option value="Business">Business</option>
          </select>
        </td>
        <td className="px-2 py-1" onClick={e => e.stopPropagation()}>
          {/* Values are the canonical DB tokens the quotation matches on, NOT
              the labels — see utils/motorCoverType. normalize() keeps rows
              saved before that fix (which hold the label) on the right option. */}
          <select value={normalizeMotorCoverType(m.type_of_cover)} onChange={e => onUpdate(m.id, 'type_of_cover', e.target.value)}
            className="w-full px-2 py-1.5 text-sm border border-line rounded">
            {/* Placeholder only while the row has no cover type. Selecting it
                back would PUT '' — which the backend turns to null and drops,
                so the grid would show blank while the DB kept the old value. */}
            {!normalizeMotorCoverType(m.type_of_cover) && <option value="">-Select-</option>}
            {MOTOR_COVER_TYPES.map(t => <option key={t.value} value={t.value}>{t.label}</option>)}
          </select>
        </td>
        <td className="px-2 py-1" onClick={e => e.stopPropagation()}>
          <MoneyInput value={m.coverage_value || ''}
            onChange={v => onUpdate(m.id, 'coverage_value', v)}
            className="w-full px-2 py-1.5 text-sm border border-line rounded" />
        </td>
        <td className="px-2 py-1" onClick={e => e.stopPropagation()}>
          <MoneyInput value={m.calculated_value || ''}
            onChange={v => onUpdate(m.id, 'calculated_value', v)}
            className="w-full px-2 py-1.5 text-sm border border-line rounded" />
        </td>
        <td className="px-1 py-1 text-center">
          <div className="flex items-center gap-1">
            <button type="button" onClick={() => setExpanded(!expanded)}
              title={expanded ? 'Collapse' : 'Expand for more fields'}
              className="text-ink-muted hover:text-ink text-xs px-1">
              {expanded ? '▲' : '▼'}
            </button>
            {m.deleted_at ? (
              <button type="button" onClick={() => onReinstate?.(m.id)}
                title="Reinstate this vehicle"
                className="text-status-success-fg hover:text-status-success-fg text-xs font-medium px-1">Reinstate</button>
            ) : (
              <button type="button" onClick={() => onDelete(m.id)}
                title="Cancel this vehicle (soft-delete; reinstate later)"
                className="text-status-danger-fg hover:text-status-danger-fg text-xs font-medium px-1">Cancel</button>
            )}
          </div>
        </td>
      </tr>
      {expanded && (
        <tr className="bg-status-info-bg border-b">
          <td colSpan={8} className="px-4 py-3">
            <div className="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
              <div>
                <label className="block text-[10px] text-ink-muted mb-0.5">Make</label>
                <input type="text" value={m.make || ''} onChange={e => onUpdate(m.id, 'make', e.target.value)}
                  className="w-full px-2 py-1.5 border rounded text-sm" />
              </div>
              <div>
                <label className="block text-[10px] text-ink-muted mb-0.5">Model</label>
                <input type="text" value={m.model || ''} onChange={e => onUpdate(m.id, 'model', e.target.value)}
                  className="w-full px-2 py-1.5 border rounded text-sm" />
              </div>
              <div>
                <label className="block text-[10px] text-ink-muted mb-0.5">Engine No</label>
                <input type="text" value={m.engine_number || ''} onChange={e => onUpdate(m.id, 'engine_number', e.target.value)}
                  className="w-full px-2 py-1.5 border rounded text-sm" />
              </div>
              <div>
                <label className="block text-[10px] text-ink-muted mb-0.5">Chassis No</label>
                <input type="text" value={m.chassis_number || ''} onChange={e => onUpdate(m.id, 'chassis_number', e.target.value)}
                  className="w-full px-2 py-1.5 border rounded text-sm" />
              </div>
              <div>
                <label className="block text-[10px] text-ink-muted mb-0.5">Tracking Device</label>
                <select value={m.tracking_device || ''} onChange={e => onUpdate(m.id, 'tracking_device', e.target.value)}
                  className="w-full px-2 py-1.5 border rounded text-sm">
                  <option value="">None</option>
                  <option value="Yes">Yes</option>
                  <option value="No">No</option>
                </select>
              </div>
              <div>
                <label className="block text-[10px] text-ink-muted mb-0.5">Security Features</label>
                <input type="text" value={m.security_features || ''} onChange={e => onUpdate(m.id, 'security_features', e.target.value)}
                  className="w-full px-2 py-1.5 border rounded text-sm" />
              </div>
            </div>
            <h4 className="text-xs font-semibold text-ink-muted mt-3 mb-2">Sub-Coverages & Extensions</h4>
            <div className="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
              {SUB_COVERAGES.map(sc => (
                <div key={sc.key} className="flex items-center gap-2">
                  <div className="flex-1">
                    <label className="block text-[10px] text-ink-muted mb-0.5">{sc.label}</label>
                    <div className="flex gap-1">
                      <select value={m[sc.key] || '0'} onChange={e => onUpdate(m.id, sc.key, e.target.value)}
                        className="w-16 px-1 py-1 text-xs border rounded">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                      </select>
                      <div className="flex-1">
                        <MoneyInput value={m[sc.pKey] || ''} placeholder="Premium"
                          onChange={v => onUpdate(m.id, sc.pKey, v)}
                          className="w-full px-2 py-1 text-xs border rounded" />
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
            {/* ── Per-motor Specified Items ── */}
            <div className="mt-3 flex items-center justify-between">
              <h4 className="text-xs font-semibold text-status-warning-fg">
                Specified Items for this Vehicle
                {specifiedItems && specifiedItems.length > 0 && <span className="ml-1 text-ink-muted">({specifiedItems.length})</span>}
              </h4>
              {onAddItem && (
                <button type="button"
                  onClick={() => { if (adding) resetAdd(); else setAdding(true) }}
                  className="px-2 py-0.5 text-[10px] font-medium bg-status-warning-bg text-status-warning-fg rounded hover:bg-status-warning-bg">
                  {adding ? 'Cancel' : '+ Add Item'}
                </button>
              )}
            </div>
            {adding && onAddItem && (
              <div className="flex gap-2 mt-1 mb-2 items-center bg-status-warning-bg border border-status-warning-fg rounded p-2">
                <div className="flex-1">
                  <SiCombobox value={newMasterId ? Number(newMasterId) : null}
                    options={siOptions ?? []}
                    loading={siLoading}
                    onChange={id => {
                      setNewMasterId(String(id))
                      const opt = siOptions?.find(o => o.id === id)
                      if (opt?.rate) setNewRate(opt.rate)
                    }} />
                </div>
                <input type="text" inputMode="decimal" value={formatNumberInput(newSum)}
                  onChange={e => setNewSum(unformatNumber(e.target.value))} placeholder="Sum insured"
                  className="w-32 px-2 py-1 text-xs border rounded" />
                <input type="number" value={newRate} readOnly
                  title="Rate is managed on the specified-item master"
                  className="w-20 px-2 py-1 text-xs border rounded bg-surface-2 text-ink-muted" placeholder="Rate %" />
                <button type="button"
                  disabled={!newMasterId || !newSum}
                  onClick={() => {
                    onAddItem(m.id, Number(newMasterId), Number(newSum), Number(newRate || 0))
                    resetAdd()
                  }}
                  className="px-3 py-1 text-xs font-medium text-white bg-status-warning-fg rounded hover:bg-status-warning-fg disabled:opacity-50">
                  Add
                </button>
              </div>
            )}
            {specifiedItems && specifiedItems.length > 0 ? (
              <table className="w-full text-xs mt-1 border rounded">
                <thead className="bg-status-warning-bg text-ink-muted uppercase text-[10px]">
                  <tr>
                    <th className="px-2 py-1 text-left">Description</th>
                    <th className="px-2 py-1 text-right w-[20%]">Sum Insured</th>
                    <th className="px-2 py-1 text-right w-[12%]">Rate %</th>
                    <th className="px-2 py-1 text-right w-[20%]">Premium</th>
                    <th className="px-2 py-1 w-[5%]"></th>
                  </tr>
                </thead>
                <tbody>
                  {specifiedItems.map((si: any) => (
                    <tr key={si.id} className={`border-t ${si.deleted_at ? 'bg-status-danger-bg opacity-60' : ''}`}>
                      <td className="px-2 py-1">
                        {/* DOM/COM 22/27 MISC item: Description is a master-item
                            dropdown shown pre-selected. Changing it pulls the new
                            master's Rate and re-derives Premium (Sum × Rate).
                            Legacy free-text/custom items (no master FK) keep the
                            read-only label so their name isn't lost. */}
                        {si.specified_coverage_id ? (
                          <SiCombobox
                            value={Number(si.specified_coverage_id)}
                            disabled={!!si.deleted_at}
                            options={(() => {
                              const opts = siOptions ?? []
                              if (opts.some(o => o.id === Number(si.specified_coverage_id))) return opts
                              return [{ id: Number(si.specified_coverage_id), name: si.name || `Item #${si.specified_coverage_id}`, rate: String(si.rate ?? '0') }, ...opts]
                            })()}
                            loading={siLoading}
                            onChange={id => {
                              const opt = (siOptions ?? []).find(o => o.id === id)
                              const newRate = Number(opt?.rate ?? si.rate ?? 0)
                              const sum = Number(si.sum_insured ?? 0)
                              const calc = Math.round(sum * newRate) / 100
                              onUpdateItem?.(si.id, { specified_coverage_id: id, name: opt?.name ?? si.name, rate: newRate, calculated_value: calc })
                            }} />
                        ) : (
                          <input type="text" value={si.name ?? ''} readOnly
                            className="w-full px-1 py-0.5 bg-transparent text-xs" />
                        )}
                      </td>
                      <td className="px-2 py-1">
                        <input type="text" inputMode="decimal" value={formatNumberInput(si.sum_insured ?? '')}
                          readOnly={!!si.deleted_at}
                          onChange={e => {
                            const raw = unformatNumber(e.target.value)
                            const rate = Number(si.rate ?? 0)
                            const calc = Math.round(Number(raw || 0) * rate) / 100
                            onUpdateItem?.(si.id, { sum_insured: raw, calculated_value: calc })
                          }}
                          className="w-full px-1 py-0.5 border rounded text-xs text-right" />
                      </td>
                      <td className="px-2 py-1">
                        {/* Rate is frozen — it is owned by the specified-item master. */}
                        <input type="number" value={si.rate ?? ''} readOnly
                          title="Rate comes from the specified-item master"
                          className="w-full px-1 py-0.5 border rounded text-xs text-right bg-surface-2 text-ink-muted" />
                      </td>
                      <td className="px-2 py-1">
                        {/* Premium is frozen — auto-derived from Sum Insured × Rate. */}
                        <input type="text" inputMode="decimal" value={formatNumberInput(si.calculated_value ?? '')} readOnly
                          title="Premium = Sum Insured × Rate"
                          className="w-full px-1 py-0.5 border rounded text-xs text-right bg-surface-2 text-ink-muted" />
                      </td>
                      <td className="px-1 py-1 text-center">
                        {si.deleted_at ? (
                          onReinstateItem && (
                            <button type="button" onClick={() => onReinstateItem(si.id)}
                              className="px-2 py-0.5 text-[10px] font-medium text-white bg-primary rounded hover:bg-primary">Reinstate</button>
                          )
                        ) : (
                          onDeleteItem && (
                            <button type="button" onClick={() => onDeleteItem(si.id)}
                              className="text-status-danger-fg hover:text-status-danger-fg text-xs">✕</button>
                          )
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            ) : (
              <div className="text-[11px] text-ink-faint italic mb-2">No specified items linked to this vehicle.</div>
            )}

            {/* ── Per-motor Note ── */}
            <div className="mt-3">
              <h4 className="text-xs font-semibold text-ink-muted mb-1">Note for this Vehicle</h4>
              <textarea rows={4} value={noteDraft} onChange={e => setNoteDraft(e.target.value)}
                onBlur={() => { if (onUpdateNote && noteDraft !== (note ?? '')) onUpdateNote(m.id, noteDraft) }}
                placeholder="e.g. Theft/Hijacking Excess: 20% of Each Claim"
                className="w-full px-2 py-1 border rounded text-xs resize-y" />
            </div>

            <h4 className="text-xs font-semibold text-ink-muted mt-3 mb-2">Deductibles / Excess</h4>
            <div className="grid grid-cols-3 md:grid-cols-3 gap-3 text-sm">
              {[
                { label: 'Own Damage', key: 'own_damage', pctKey: 'own_damage_minimun_percent', amtKey: 'own_damage_minimum_amount' },
                { label: 'Windscreen', key: 'windscreen', pctKey: 'windscreen_minimun_percent', amtKey: 'windscreen_minimum_amount' },
                { label: 'Loss of Keys', key: 'loss_of_keys', pctKey: 'loss_of_keys_minimun_percent', amtKey: 'loss_of_keys_minimum_amount' },
              ].map(d => (
                <div key={d.key} className="border rounded p-2 bg-surface">
                  <label className="block text-[10px] text-ink-muted font-semibold mb-1">{d.label}</label>
                  <div className="grid grid-cols-2 gap-1">
                    <div>
                      <label className="block text-[9px] text-ink-faint">Min %</label>
                      <input type="number" value={m[d.pctKey] || ''} onChange={e => onUpdate(m.id, d.pctKey, e.target.value)}
                        className="w-full px-1 py-1 text-xs border rounded" />
                    </div>
                    <div>
                      <label className="block text-[9px] text-ink-faint">Min Amt</label>
                      <MoneyInput value={m[d.amtKey] || ''} onChange={v => onUpdate(m.id, d.amtKey, v)}
                        className="w-full px-1 py-1 text-xs border rounded" />
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </td>
        </tr>
      )}
    </>
  )
}

function SavedCoverageRow({ coverage: c, index: i, savedAddresses, onDelete, onEdit, onCancel, onReinstate, isEditing, policyId, isDomComProduct }: {
  coverage: CoverageForm; index: number; savedAddresses: SavedRiskAddress[]; onDelete: (idx: number) => void; onEdit: (idx: number) => void; onCancel?: () => void; onReinstate?: (idx: number) => void; isEditing?: boolean; policyId?: number; isDomComProduct?: boolean
}) {
  const isCancelled = !!((c as any).deleted_at)
  const [expanded, setExpanded] = useState(false)
  const covCode = (((c as any).coverage_code) || '').toUpperCase()
  const isEarCoverage = covCode === 'ERECTIONALLRISKS' || covCode === 'EAR'
  const isParCoverage = covCode === 'PLANTALLRISKS' || covCode === 'PAR'
  const isCarCoverage = covCode === 'CONTRACTORSALLRISKS' || covCode === 'CAR'
  const isMedMalCoverage = covCode === 'MEDICAMALPRACTICEINSURANCE' || covCode === 'MM'
  const isTravelCoverage = covCode === 'TRAVELINSURANCE' || covCode === 'TRAVEL'
  const isPiCoverage = covCode === 'PROFESSIONALINDEMNITY' || covCode === 'PI'
  const isMachineryBreakdownCoverage = covCode === 'MACHINERYBREAKDOWN' || covCode === 'MB'
  const isMarineCargoOnceOffCoverage = covCode === 'MARINEONCEOFFCOVER' || covCode === 'MARINECARGOONCEOFF'
  const isMarineCargoOpenCoverage = covCode === 'MARINEOPENCOVER' || covCode === 'MARINECARGOOPEN'
  const isMarineDoCoverage = covCode === 'MARINEDO' || covCode === 'MARINEDIRECTORSOFFICERS'
  const isDoCoverage = covCode === 'DIRECTORSOFFICERSLIABILITY' || covCode === 'DOL'
  const isMedicalEvacuationCoverage = covCode === 'MEDICALEVACUATION'
  const isCommercialCrimeCoverage = covCode === 'COMMERCIALCRIME'
  const isEnvironmentalLiabilityCoverage = covCode === 'ENVIRONMENTALLIABILITY'
  const isBondsCoverage = covCode === 'BONDSANDGUARANTEES'
  // For specialist coverages that drive editing through their own schedule
  // page (MB Schedule, EAR Schedule, etc.), the row-level Edit button is
  // misleading — operators should use the dedicated schedule link instead.
  // Motor Traders is an exception: it's in HIDE_MAIN_COVERAGE_FIELDS_CODES
  // because pricing comes from the MT-specific panel (not the Sum Insured /
  // Rate / Premium trio), but the panel itself lives INLINE inside the Add /
  // Edit Coverage form, not on a separate schedule page. Without the row
  // Edit button (and no MT-schedule link fallback), saved MT rows became
  // un-editable — the operator could see the row but not get back to the
  // saved type_of_cover / extensions / minimum-limits to amend them.
  const isMotorTradersRow = covCode === 'MOTORTRADERSEXTERNAL' || covCode === 'MOTORTRADERSINTERNAL'
  const hidesRowEditButton = HIDE_MAIN_COVERAGE_FIELDS_CODES.includes(covCode) && !isMotorTradersRow
  const earHref = policyId && (c as any)._dbId
    ? `/policies/${policyId}/specialist-coverage/ear?policy_coverage_id=${(c as any)._dbId}`
    : null
  const parHref = policyId && (c as any)._dbId
    ? `/policies/${policyId}/specialist-coverage/par?policy_coverage_id=${(c as any)._dbId}`
    : null
  const carHref = policyId && (c as any)._dbId
    ? `/policies/${policyId}/specialist-coverage/car?policy_coverage_id=${(c as any)._dbId}`
    : null
  const medMalHref = policyId && (c as any)._dbId
    ? `/policies/${policyId}/specialist-coverage/medical-malpractice?policy_coverage_id=${(c as any)._dbId}`
    : null
  const travelHref = policyId && (c as any)._dbId
    ? `/policies/${policyId}/specialist-coverage/travel-insurance?policy_coverage_id=${(c as any)._dbId}`
    : null
  const piHref = policyId && (c as any)._dbId
    ? `/policies/${policyId}/specialist-coverage/professional-indemnity?policy_coverage_id=${(c as any)._dbId}`
    : null
  const machineryBreakdownHref = policyId && (c as any)._dbId
    ? `/policies/${policyId}/specialist-coverage/machinery-breakdown?policy_coverage_id=${(c as any)._dbId}`
    : null
  const marineCargoOnceOffHref = policyId && (c as any)._dbId
    ? `/policies/${policyId}/specialist-coverage/marine-cargo-once-off?policy_coverage_id=${(c as any)._dbId}`
    : null
  const marineCargoOpenHref = policyId && (c as any)._dbId
    ? `/policies/${policyId}/specialist-coverage/marine-cargo-open?policy_coverage_id=${(c as any)._dbId}`
    : null
  const marineDoHref = policyId && (c as any)._dbId
    ? `/policies/${policyId}/specialist-coverage/marine-directors-officers?policy_coverage_id=${(c as any)._dbId}`
    : null
  const doHref = policyId && (c as any)._dbId
    ? `/policies/${policyId}/specialist-coverage/marine-directors-officers?policy_coverage_id=${(c as any)._dbId}`
    : null
  const medicalEvacuationHref = policyId && (c as any)._dbId
    ? `/policies/${policyId}/specialist-coverage/medical-evacuation?policy_coverage_id=${(c as any)._dbId}`
    : null
  const commercialCrimeHref = policyId && (c as any)._dbId
    ? `/policies/${policyId}/specialist-coverage/commercial-crime?policy_coverage_id=${(c as any)._dbId}`
    : null
  const environmentalLiabilityHref = policyId && (c as any)._dbId
    ? `/policies/${policyId}/specialist-coverage/environmental-liability?policy_coverage_id=${(c as any)._dbId}`
    : null
  const bondsHref = policyId && (c as any)._dbId
    ? `/policies/${policyId}/specialist-coverage/bonds?policy_coverage_id=${(c as any)._dbId}`
    : null
  return (
    <>
      <tr className={`border-b border-line${isEditing ? ' bg-status-warning-bg' : ''}${isCancelled ? ' bg-surface-2 text-ink-faint line-through' : ''}`}>
        <td className="py-2">
          <button type="button" onClick={() => setExpanded(!expanded)}
            className="flex items-center gap-1 text-left">
            {c.coverage_name || `Coverage #${c.coverage_id}`}
            <span className="flex gap-1 ml-1">
              {c.subcoverages && c.subcoverages.length > 0 && <span className="text-[10px] px-1 rounded bg-status-info-bg text-primary">{c.subcoverages.length} sub</span>}
              {(c as any).extensions?.length > 0 && <span className="text-[10px] px-1 rounded bg-status-info-bg text-primary">{(c as any).extensions.length} ext</span>}
              {(c as any).specified_items?.length > 0 && !MOTOR_CODES.includes(((c as any).coverage_code || '').toUpperCase()) && <span className="text-[10px] px-1 rounded bg-status-warning-bg text-status-warning-fg">{(c as any).specified_items.length} items</span>}
              {(c as any).excesses?.length > 0 && <span className="text-[10px] px-1 rounded bg-status-danger-bg text-status-danger-fg">{(c as any).excesses.length} excess</span>}
              {(c as any).motor_count > 0 && <span className="text-[10px] px-1 rounded bg-status-success-bg text-status-success-fg">{(c as any).motor_count} vehicles</span>}
              {(c.subcoverages?.length || (c as any).extensions?.length || (c as any).specified_items?.length) ? <span className="text-ink-faint text-[10px]">{expanded ? '▲' : '▼'}</span> : null}
            </span>
          </button>
        </td>
        <td className="py-2 text-ink-muted">{savedAddresses.find(a => a.id === c.risk_address_id)?.address_name || '-'}</td>
        <td className="py-2 text-right">P {(() => {
          const num = (v: any) => parseFloat(v ?? 0) || 0
          // Motor: backend already aggregates Σ vehicle SI + Σ MISC SI into
          // coverage_value (per-vehicle rows aren't on this object). Use it.
          if (MOTOR_CODES.includes(covCode)) return num(c.coverage_value).toLocaleString()
          // Stated Benefits has no Sum Insured of its own — its exposure is the
          // Annual Wages entered per subcoverage (stored in ratefactor_AnnualWages,
          // not coverage_value). Display-only: surface that here instead of P 0.
          if (covCode === 'STATEDBENEFITS') {
            return (c.subcoverages || []).reduce((s, sub) => s + num(sub.ratefactor_AnnualWages), 0).toLocaleString()
          }
          const subValue = (c.subcoverages || []).reduce((s, sub) => s + num(sub.coverage_value), 0)
          const siValue = ((c as any).specified_items || []).reduce((s: number, x: any) => s + num(x.sum_insured), 0)
          const motorValue = ((c as any).motor || (c as any).vehicles || []).reduce((s: number, v: any) => s + num(v.coverage_value), 0)
          const calc = (subValue + siValue + motorValue) || num(c.coverage_value)
          return calc.toLocaleString()
        })()}</td>
        <td className="py-2 text-right">{(() => {
          const cv = c.subcoverages?.length
            ? c.subcoverages.reduce((s, sub) => s + (parseFloat(sub.coverage_value) || 0), 0)
            : parseFloat(c.coverage_value || '0')
          const calc = c.subcoverages?.length
            ? c.subcoverages.reduce((s, sub) => s + (parseFloat(sub.calculated_value) || 0), 0)
            : parseFloat(c.calculated_value || '0')
          if (cv > 0 && calc > 0) return (calc / cv * 100).toFixed(2) + '%'
          if (c.subcoverages?.length) return c.subcoverages[0]?.rate ? c.subcoverages[0].rate + '%' : '—'
          return c.rate && c.rate !== '0' ? c.rate + '%' : '—'
        })()}</td>
        <td className="py-2 text-right font-medium">P {(() => {
          const num = (v: any) => parseFloat(v ?? 0) || 0
          // Motor coverages keep their per-vehicle data on the motor table (not
          // on this saved-coverage object, which only carries motor_count). The
          // backend now returns the whole section total — all vehicles' base +
          // extension premium + per-vehicle MISC — in calculated_value, so use
          // it directly rather than re-summing an array we don't have here.
          if (MOTOR_CODES.includes(covCode)) {
            return num(c.calculated_value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
          }
          const subPremium = (c.subcoverages || []).reduce((s, sub) => s + num(sub.calculated_value), 0)
          // DomCom (product 7/8): only type='Extention' rows are premium. The other
          // types (Excess, Perils, Memoranda, FirstAmountPayable, BurglarAlarmWarranty)
          // store a limit / excess amount in extention_calculated_value and are excluded
          // from policy_actions.annual_premium — Business Interruption on COMG2024101717
          // read 1,000,720 here (a 1,000,000 non-Extention row + the real 720) while the
          // canonical Total stayed correct. Same filter as the edit-form live total.
          const extPremium = ((c as any).extensions || [])
            .filter((e: any) => !isDomComProduct || (e.type || 'Extention') === 'Extention')
            .reduce((s: number, e: any) => s + num(e.extention_calculated_value ?? e.calculatedValue), 0)
          // A cancelled/struck-through row should show the FULL premium being
          // removed. When the whole coverage is cancelled its children are all
          // soft-deleted, so skip the deleted_at filter in that case — otherwise
          // the row understates by whatever child rows the cancel soft-deleted
          // (e.g. the misc specified-items, while fidelity_data is left intact).
          const siPremium = ((c as any).specified_items || []).filter((x: any) => isCancelled || !x.deleted_at).reduce((s: number, x: any) => s + num(x.calculated_value ?? x.calculatedValue), 0)
          const motorPremium = ((c as any).motor || (c as any).vehicles || []).reduce((s: number, v: any) => {
            const vSpecified = (v.specifiedItems || v.specified_items || []).reduce((ss: number, x: any) => ss + num(x.calculated_value ?? x.calculatedValue), 0)
            return s + num(v.calculated_value ?? v.calculatedValue) + vSpecified
          }, 0)
          // Fidelity Guarantee's premium lives in policy_coverages_data (fidelity_data),
          // not in subcoverages/specified_items. Without this term the row shows only
          // the misc specified-items and drops the blanket / named-position premium.
          const fidelityPremium = ((c as any).fidelity_data || []).filter((f: any) => isCancelled || !f.deleted_at).reduce((s: number, f: any) => s + num(f.premium), 0)
          const calc = (subPremium + extPremium + siPremium + motorPremium + fidelityPremium) || num(c.calculated_value)
          return calc.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
        })()}</td>
        <td className="py-2 text-right">
          <div className="flex items-center justify-end gap-2">
            {isEarCoverage && earHref && (
              <a href={earHref}
                className="text-xs px-2 py-1 rounded bg-primary text-white hover:bg-primary"
                title="Open Erection All Risks – Policy Schedule">
                Open EAR Schedule
              </a>
            )}
            {isEarCoverage && !earHref && (
              <span className="text-xs text-ink-faint" title="Save the coverage first to open the EAR Schedule">EAR Schedule</span>
            )}
            {isParCoverage && parHref && (
              <a href={parHref}
                className="text-xs px-2 py-1 rounded bg-primary text-white hover:bg-primary"
                title="Open Plant All Risks – Policy Schedule">
                Open PAR Schedule
              </a>
            )}
            {isParCoverage && !parHref && (
              <span className="text-xs text-ink-faint" title="Save the coverage first to open the PAR Schedule">PAR Schedule</span>
            )}
            {isCarCoverage && carHref && (
              <a href={carHref}
                className="text-xs px-2 py-1 rounded bg-primary text-white hover:bg-primary"
                title="Open Contractors All Risks – Policy Schedule">
                Open CAR Schedule
              </a>
            )}
            {isCarCoverage && !carHref && (
              <span className="text-xs text-ink-faint" title="Save the coverage first to open the CAR Schedule">CAR Schedule</span>
            )}
            {isMedMalCoverage && medMalHref && (
              <a href={medMalHref}
                className="text-xs px-2 py-1 rounded bg-primary text-white hover:bg-primary"
                title="Open Medical Malpractice – Policy Schedule">
                Open MM Schedule
              </a>
            )}
            {isMedMalCoverage && !medMalHref && (
              <span className="text-xs text-ink-faint" title="Save the coverage first to open the Medical Malpractice Schedule">MM Schedule</span>
            )}
            {isTravelCoverage && travelHref && (
              <a href={travelHref}
                className="text-xs px-2 py-1 rounded bg-primary text-white hover:bg-primary"
                title="Open Travel Insurance – Policy Schedule">
                Open Travel Schedule
              </a>
            )}
            {isTravelCoverage && !travelHref && (
              <span className="text-xs text-ink-faint" title="Save the coverage first to open the Travel Insurance Schedule">Travel Schedule</span>
            )}
            {isPiCoverage && piHref && (
              <a href={piHref}
                className="text-xs px-2 py-1 rounded bg-primary text-white hover:bg-primary"
                title="Open Professional Indemnity – Policy Schedule">
                Open PI Schedule
              </a>
            )}
            {isPiCoverage && !piHref && (
              <span className="text-xs text-ink-faint" title="Save the coverage first to open the Professional Indemnity Schedule">PI Schedule</span>
            )}
            {isMachineryBreakdownCoverage && machineryBreakdownHref && (
              <a href={machineryBreakdownHref}
                className="text-xs px-2 py-1 rounded bg-primary text-white hover:bg-primary"
                title="Open Machinery Breakdown – Policy Schedule">
                Open MB Schedule
              </a>
            )}
            {isMachineryBreakdownCoverage && !machineryBreakdownHref && (
              <span className="text-xs text-ink-faint" title="Save the coverage first to open the Machinery Breakdown Schedule">MB Schedule</span>
            )}
            {isMarineCargoOnceOffCoverage && marineCargoOnceOffHref && (
              <a href={marineCargoOnceOffHref}
                className="text-xs px-2 py-1 rounded bg-primary text-white hover:bg-primary"
                title="Open Marine Cargo Once-Off – Policy Schedule">
                Open Marine Once-Off Schedule
              </a>
            )}
            {isMarineCargoOnceOffCoverage && !marineCargoOnceOffHref && (
              <span className="text-xs text-ink-faint" title="Save the coverage first to open the Marine Cargo Once-Off Schedule">Marine Once-Off Schedule</span>
            )}
            {isMarineCargoOpenCoverage && marineCargoOpenHref && (
              <a href={marineCargoOpenHref}
                className="text-xs px-2 py-1 rounded bg-primary text-white hover:bg-primary"
                title="Open Marine Cargo Open Cover – Policy Schedule">
                Open Marine Open Schedule
              </a>
            )}
            {isMarineCargoOpenCoverage && !marineCargoOpenHref && (
              <span className="text-xs text-ink-faint" title="Save the coverage first to open the Marine Cargo Open Cover Schedule">Marine Open Schedule</span>
            )}
            {isMarineDoCoverage && marineDoHref && (
              <a href={marineDoHref}
                className="text-xs px-2 py-1 rounded bg-primary text-white hover:bg-primary"
                title="Open Marine Directors & Officers – Policy Schedule">
                Open Marine D&O Schedule
              </a>
            )}
            {isMarineDoCoverage && !marineDoHref && (
              <span className="text-xs text-ink-faint" title="Save the coverage first to open the Marine D&O Schedule">Marine D&O Schedule</span>
            )}
            {isDoCoverage && doHref && (
              <a href={doHref}
                className="text-xs px-2 py-1 rounded bg-primary text-white hover:bg-primary"
                title="Open Directors & Officers Liability – Policy Schedule">
                Open D&O Schedule
              </a>
            )}
            {isDoCoverage && !doHref && (
              <span className="text-xs text-ink-faint" title="Save the coverage first to open the D&O Schedule">D&O Schedule</span>
            )}
            {isMedicalEvacuationCoverage && medicalEvacuationHref && (
              <a href={medicalEvacuationHref}
                className="text-xs px-2 py-1 rounded bg-primary text-white hover:bg-primary"
                title="Open Medical Evacuation – Policy Schedule">
                Open Medical Evac Schedule
              </a>
            )}
            {isMedicalEvacuationCoverage && !medicalEvacuationHref && (
              <span className="text-xs text-ink-faint" title="Save the coverage first to open the Medical Evacuation Schedule">Medical Evac Schedule</span>
            )}
            {isCommercialCrimeCoverage && commercialCrimeHref && (
              <a href={commercialCrimeHref}
                className="text-xs px-2 py-1 rounded bg-primary text-white hover:bg-primary"
                title="Open Commercial Crime – Policy Schedule">
                Open Commercial Crime Schedule
              </a>
            )}
            {isCommercialCrimeCoverage && !commercialCrimeHref && (
              <span className="text-xs text-ink-faint" title="Save the coverage first to open the Commercial Crime Schedule">Commercial Crime Schedule</span>
            )}
            {isEnvironmentalLiabilityCoverage && environmentalLiabilityHref && (
              <a href={environmentalLiabilityHref}
                className="text-xs px-2 py-1 rounded bg-primary text-white hover:bg-primary"
                title="Open Environmental Liability – Policy Schedule">
                Open Environmental Liability Schedule
              </a>
            )}
            {isEnvironmentalLiabilityCoverage && !environmentalLiabilityHref && (
              <span className="text-xs text-ink-faint" title="Save the coverage first to open the Environmental Liability Schedule">Environmental Liability Schedule</span>
            )}
            {isBondsCoverage && bondsHref && (
              <a href={bondsHref}
                className="text-xs px-2 py-1 rounded bg-primary text-white hover:bg-primary"
                title="Open Bonds and Guarantees – Policy Schedule">
                Open Bonds Schedule
              </a>
            )}
            {isBondsCoverage && !bondsHref && (
              <span className="text-xs text-ink-faint" title="Save the coverage first to open the Bonds Schedule">Bonds Schedule</span>
            )}
            {!hidesRowEditButton && (isEditing
              ? <button type="button" onClick={() => onCancel?.()} className="text-status-warning-fg hover:text-status-warning-fg text-xs font-medium">Cancel Edit</button>
              : !isCancelled && <button type="button" onClick={() => onEdit(i)} className="text-primary hover:text-primary text-xs">Edit</button>
            )}
            {isCancelled
              ? (onReinstate && <button type="button" onClick={() => onReinstate(i)} className="text-xs text-status-success-fg hover:text-status-success-fg" title="Reinstate (un-cancel); pro-rata applies in next endorsement">Reinstate</button>)
              : <button type="button" onClick={() => onDelete(i)} className="text-xs text-status-danger-fg hover:text-status-danger-fg" title="Cancel coverage (soft-delete; can be reinstated)">Delete</button>
            }
          </div>
        </td>
      </tr>
      {expanded && (
        <tr className="bg-surface-2">
          <td colSpan={6} className="px-4 py-3">
            {/* Subcoverages */}
            {c.subcoverages && c.subcoverages.length > 0 && (
              <div className="mb-3">
                <h5 className="text-[10px] font-semibold text-ink-muted uppercase mb-1">Description of Cover</h5>
                <table className="w-full text-xs">
                  <tbody>
                    {c.subcoverages.map((sub, si) => (
                      <tr key={si} className="border-b border-line">
                        <td className="py-1 text-ink-muted">{sub.s_ScreenName}</td>
                        <td className="py-1 text-right">P {parseFloat(sub.coverage_value || '0').toLocaleString()}</td>
                        <td className="py-1 text-right">{sub.rate || '-'}%</td>
                        <td className="py-1 text-right font-medium">P {parseFloat(sub.calculated_value || '0').toLocaleString()}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
            {/* Extensions */}
            {(c as any).extensions?.length > 0 && (
              <div className="mb-3">
                <h5 className="text-[10px] font-semibold text-primary uppercase mb-1">Extensions ({(c as any).extensions.length})</h5>
                <div className="grid grid-cols-2 md:grid-cols-3 gap-1 text-xs">
                  {(c as any).extensions.map((e: any, ei: number) => (
                    <div key={ei} className="flex justify-between px-2 py-1 bg-status-info-bg rounded">
                      <span>{e.s_ScreenName || `Ext #${ei + 1}`}</span>
                      <span className="font-medium">{e.extention_coverage_value ? `P ${Number(e.extention_coverage_value).toLocaleString()}` : e.extention_text_value || '—'}</span>
                    </div>
                  ))}
                </div>
              </div>
            )}
            {/* Specified Items — hide on motor coverages (they're shown per-motor under each vehicle row) */}
            {(c as any).specified_items?.length > 0 && !MOTOR_CODES.includes(((c as any).coverage_code || '').toUpperCase()) && (
              <div className="mb-3">
                <h5 className="text-[10px] font-semibold text-status-warning-fg uppercase mb-1">Specified Items ({(c as any).specified_items.length})</h5>
                <table className="w-full text-xs">
                  <tbody>
                    {(c as any).specified_items.map((s: any, si: number) => (
                      <tr key={si} className="border-b border-status-warning-fg">
                        <td className="py-1">{s.name || `Item #${si + 1}`}</td>
                        <td className="py-1 text-right">P {Number(s.sum_insured || 0).toLocaleString()}</td>
                        <td className="py-1 text-right">{s.rate}%</td>
                        <td className="py-1 text-right font-medium">P {Number(s.calculated_value || 0).toLocaleString()}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
            {/* Excesses */}
            {(c as any).excesses?.length > 0 && (
              <div className="mb-3">
                <h5 className="text-[10px] font-semibold text-status-danger-fg uppercase mb-1">Excesses ({(c as any).excesses.length})</h5>
                <div className="grid grid-cols-3 gap-1 text-xs">
                  {(c as any).excesses.map((e: any, ei: number) => (
                    <div key={ei} className="px-2 py-1 bg-status-danger-bg rounded">
                      {e.excesses || 'Excess'} — {e.min_percent}% / P {Number(e.min_amt || 0).toLocaleString()}
                    </div>
                  ))}
                </div>
              </div>
            )}
            {/* Notes */}
            {c.notes && <div className="text-xs text-ink-muted italic">Note: {c.notes}</div>}
            {/* Motor count */}
            {(c as any).motor_count > 0 && <div className="text-xs text-status-success-fg font-medium">{(c as any).motor_count} vehicle(s) attached</div>}
          </td>
        </tr>
      )}
    </>
  )
}
