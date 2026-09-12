import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useClaimsFnolEnabled, useCreateFnol, CLAIMS_FNOL_ROLES } from '../../hooks/useFnol'
import { getStoredRoles } from '../../api/auth'
import type { CreateFnolPayload } from '../../api/fnol'
import apiClient from '../../api/client'
import {
  fetchBrokers,
  fetchClaimHandlers,
  fetchReinsurers,
  fetchMasterSuppliers,
  fetchConfigEntries,
} from '../../api/claimsMasterData'
import { fetchAssessors } from '../../api/assessors'
import {
  COMMENT_STATUSES,
  AWAITING_SUB_REASONS,
  SUB_REASON_STATUS,
} from '../../components/claims/ClaimCreateStageAccordions'
import EmptyState from '../../components/common/EmptyState'
import Button from '../../components/common/Button'
import { useToast } from '../../components/common/Toast'

/**
 * FnolCreatePage — a 1:1 replica of the legacy Claims-Tracker "New Claim" form
 * (D:\ADRisk\claims: index.html #editOv + js/claims.js openAdd/saveClaim +
 * js/ui.js toggleClaimTypeFields), rebuilt with Graphite design tokens.
 *
 * MODELLING DECISION — option (b): the FNOL create form is a full replica of the
 * tracker "New Claim" modal — BASIC INFORMATION + classification cascade +
 * COMMENTS + the 7 numbered STAGE accordions. An FNOL has no claim_id yet, so the
 * stage data cannot live on `claim_tracker_workflow` (keyed by claim) at intake:
 * it is collected by the reused ClaimCreateStageAccordions component, parked on
 * the FNOL as `stage_data` (JSON), and applied to the new claim's
 * claim_tracker_workflow row on CONVERT (ClaimFnolController::convert reuses
 * ClaimStageTimelineService). Comments are owned by THIS page (wired to the FNOL
 * comment_status columns), so the accordions component is rendered with
 * showComments={false} to avoid double-rendering the Comments block.
 *
 * CUTOVER — the whole surface is gated by the `claims_fnol` runtime flag. When
 * ON this is the single "new claim" path (the Claims chrome "+ New Claim" button
 * and /claims/create both open it). When OFF the feature is inert and the legacy
 * ClaimCreatePage remains the create path (see ClaimCreateGate). Flag flip is a
 * separate ops/claims-domain decision — the default stays OFF.
 */

// ── Static option lists (mirror the tracker's config.js / vendors.js) ──
const CHANNELS = ['Broker', 'Direct']
const CLAIM_TYPES = ['Motor Claim', 'Non-Motor Claim', 'Glass', 'Lock & Key']
const CUSTOMER_TYPES = ['COM', 'DOM', 'MIS', 'Others']
// vendors.js NON_MOTOR_TYPES — the tracker's Non-Motor sub-type vocabulary.
const NON_MOTOR_TYPES = [
  'All Risk', 'Legal', 'Hospital Cash Back', 'Fire', 'Workmen Compensation (WCA)',
  'Money', 'Commercial-Building', 'Domestic-Building', 'Accidental Death',
  'Travel Insurance', 'Commercial-Contents', 'Medical Malpractice',
  'Goods In Transit (GIT)', 'Mobile and Electronics', 'Defective Workmanship',
  'Business Interruption', 'Fidelity', 'Domestic-Contents', 'Machinery Breakdown',
  'Plant All Risk', "Contractors' All Risk",
  // Added per claims-team v5 #06. NB: "Third Party Only" (a MOTOR cover) and
  // "Homeowners" (duplicate of "Houseowners") were deliberately excluded.
  'Bonu', 'Burglary', 'Houseowners', 'Public Liability',
]
// The two flavours of the tracker's "Other" sentinel option.
const OTHER_SPECIFY = 'Others (Specify Below)' // broker / glass supplier / non-motor assessor
const OTHER = 'Others'                          // claims handler / customer type

export default function FnolCreatePage() {
  const navigate = useNavigate()
  const { toast } = useToast()
  const enabled = useClaimsFnolEnabled()
  const hasRole = getStoredRoles().some((r) => CLAIMS_FNOL_ROLES.includes(r))
  const createFnol = useCreateFnol()
  // ── Basic Information (tracker field order) ──
  const [channel, setChannel] = useState('Broker')
  const [brokerSel, setBrokerSel] = useState('')
  const [brokerOther, setBrokerOther] = useState('')
  const [clientName, setClientName] = useState('')
  const [contactPhone, setContactPhone] = useState('')
  const [reinsurer, setReinsurer] = useState('')
  const [policyNumber, setPolicyNumber] = useState('')
  const [claimsHandlerSel, setClaimsHandlerSel] = useState('')
  const [claimsHandlerOther, setClaimsHandlerOther] = useState('')
  const [reportedDate, setReportedDate] = useState(() => new Date().toISOString().split('T')[0])
  const [allocatedDate, setAllocatedDate] = useState('')
  const [lossDate, setLossDate] = useState('')
  const [claimType, setClaimType] = useState('')
  const [customerTypeSel, setCustomerTypeSel] = useState('')
  const [customerTypeOther, setCustomerTypeOther] = useState('')
  const [nonMotorSubType, setNonMotorSubType] = useState('')
  const [assessorSel, setAssessorSel] = useState('')
  const [assessorOther, setAssessorOther] = useState('')
  const [motorAssessorSel, setMotorAssessorSel] = useState('')
  const [motorAssessorOther, setMotorAssessorOther] = useState('')
  const [glassSupplierSel, setGlassSupplierSel] = useState('')
  const [glassSupplierOther, setGlassSupplierOther] = useState('')
  const [plateNumber, setPlateNumber] = useState('')
  const [description, setDescription] = useState('')

  // ── Comments ──
  const [commentStatus, setCommentStatus] = useState('')
  const [commentSubReason, setCommentSubReason] = useState('')

  const [errors, setErrors] = useState<Record<string, string>>({})

  // ── Option lists (graceful — degrade to just the "Other" path on failure) ──
  const [brokers, setBrokers] = useState<string[]>([])
  const [handlers, setHandlers] = useState<string[]>([])
  const [reinsurers, setReinsurers] = useState<string[]>([])
  const [glassSuppliers, setGlassSuppliers] = useState<string[]>([])
  const [nonMotorAssessors, setNonMotorAssessors] = useState<string[]>([])
  const [motorAssessors, setMotorAssessors] = useState<string[]>([])
  const [facClients, setFacClients] = useState<string[]>([])
  useEffect(() => {
    let alive = true
    fetchBrokers().then((r) => alive && setBrokers(r.map((b) => b.name).filter(Boolean))).catch(() => {})
    fetchClaimHandlers().then((r) => alive && setHandlers(r.map((h) => h.name).filter(Boolean))).catch(() => {})
    fetchReinsurers().then((r) => alive && setReinsurers(r.data.map((x) => x.companyName ?? '').filter(Boolean))).catch(() => {})
    fetchMasterSuppliers({ type: 'glass', per_page: 200 })
      .then((r) => alive && setGlassSuppliers(r.data.map((s) => s.name).filter(Boolean))).catch(() => {})
    fetchAssessors({ category: 'non_motor', per_page: 200 })
      .then((r) => alive && setNonMotorAssessors(r.data.map((a) => a.name).filter(Boolean))).catch(() => {})
    fetchAssessors({ category: 'motor', per_page: 200 })
      .then((r) => alive && setMotorAssessors(r.data.map((a) => a.name).filter(Boolean))).catch(() => {})
    fetchConfigEntries('fac_clients')
      .then((r) => alive && setFacClients(r.map((e) => e.label).filter(Boolean))).catch(() => {})
    return () => { alive = false }
  }, [])

  // ── Optional policy lookup — resolves the typed policy number → numeric id so
  //    the FNOL carries a resolvable policy for the (unchanged) convert step.
  const [resolvedPolicyId, setResolvedPolicyId] = useState<number | null>(null)
  const [policyInfo, setPolicyInfo] = useState('')
  const [lookingUp, setLookingUp] = useState(false)
  async function lookupPolicy() {
    const policy = policyNumber.trim()
    if (!policy) return
    setLookingUp(true); setPolicyInfo(''); setResolvedPolicyId(null)
    try {
      const { data } = await apiClient.get('/claims/claim-types-by-policy', { params: { policy } })
      const d = data.data
      setResolvedPolicyId(typeof d.policy_id === 'number' ? d.policy_id : null)
      setPolicyInfo(`✓ ${d.product_type ?? 'Policy'} found${d.product_id ? ` (product ${d.product_id})` : ''}`)
    } catch (e) {
      const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
      setPolicyInfo(msg || 'Policy not found — you can still record the FNOL without it.')
    } finally {
      setLookingUp(false)
    }
  }

  // ── Cascade (js/ui.js toggleClaimTypeFields + checkFACClient + checkMajorClaim) ──
  const isMotor = claimType === 'Motor Claim'
  const isNonMotor = claimType === 'Non-Motor Claim'
  const isGlassOrKey = claimType === 'Glass' || claimType === 'Lock & Key'
  const showBroker = channel === 'Broker'
  const showCustomerType = isMotor || isNonMotor
  const showNonMotor = isNonMotor
  const showGlass = isGlassOrKey
  const isFac = useMemo(
    () => !!clientName.trim() && facClients.some((f) => f.toUpperCase() === clientName.trim().toUpperCase()),
    [clientName, facClients],
  )
  function clearErr(k: string) { setErrors((p) => ({ ...p, [k]: '' })) }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    const errs: Record<string, string> = {}
    // Tracker saveClaim() hard-validates only these three on a new claim.
    if (!clientName.trim()) errs.claimant_name = 'Client Name is required'
    if (!reportedDate) errs.reported_date = 'Reported Date is required'
    if (!description.trim()) errs.description = 'Claim Description is required for new claims'
    // Neither the reported date nor the loss date can be in the future.
    const today = new Date().toISOString().split('T')[0]
    if (reportedDate && reportedDate > today) errs.reported_date = 'Reported Date cannot be in the future'
    if (lossDate && lossDate > today) errs.loss_date = 'Date of Loss cannot be in the future'
    if (allocatedDate && allocatedDate > today) errs.claim_allocated_on = 'Allocated Date cannot be in the future'
    if (Object.keys(errs).length > 0) { setErrors(errs); return }

    // Resolve the tracker's "Others" selections to their typed values.
    const brokerName = showBroker
      ? (brokerSel === OTHER_SPECIFY ? brokerOther.trim() : brokerSel)
      : ''
    const handler = claimsHandlerSel === OTHER ? claimsHandlerOther.trim() : claimsHandlerSel
    const glass = showGlass
      ? (glassSupplierSel === OTHER_SPECIFY ? glassSupplierOther.trim() : glassSupplierSel)
      : ''
    const assessor = showNonMotor
      ? (assessorSel === OTHER_SPECIFY ? assessorOther.trim() : assessorSel)
      : isMotor
      ? (motorAssessorSel === OTHER_SPECIFY ? motorAssessorOther.trim() : motorAssessorSel)
      : ''

    const payload: CreateFnolPayload = {
      claimant_name: clientName.trim(),
      description: description.trim(),
      policy_number: policyNumber.trim() || null,
      policy_id: resolvedPolicyId,
      claim_type: claimType || null,
      loss_date: lossDate || null,          // Date of Loss — when the loss occurred
      reported_date: reportedDate || null,  // Reported Date — when it was reported
      claim_allocated_on: allocatedDate || null, // Allocated Date — carried onto claims.claim_allocated_on at convert
      contact_phone: contactPhone.trim() || null,
      channel: channel || null,
      broker_name: brokerName || null,
      claims_handler: handler || null,
      plate_number: plateNumber.trim() || null,
      reserve_amount: null,
      claim_paid_amount: null, // set at claim edit, not at intake (claims-team v6)
      customer_type: showCustomerType ? (customerTypeSel || null) : null,
      customer_type_other: showCustomerType && customerTypeSel === OTHER ? (customerTypeOther.trim() || null) : null,
      non_motor_sub_type: showNonMotor ? (nonMotorSubType || null) : null,
      assessor: assessor || null,
      assessor_other:
        showNonMotor && assessorSel === OTHER_SPECIFY ? (assessorOther.trim() || null)
        : isMotor && motorAssessorSel === OTHER_SPECIFY ? (motorAssessorOther.trim() || null)
        : null,
      glass_supplier: glass || null,
      glass_supplier_other: showGlass && glassSupplierSel === OTHER_SPECIFY ? (glassSupplierOther.trim() || null) : null,
      reinsurer: isFac ? (reinsurer || null) : null,
      is_fac: isFac,
      comment_status: commentStatus || null,
      comment_sub_reason: commentStatus === SUB_REASON_STATUS ? (commentSubReason || null) : null,
    }

    try {
      const fnol = await createFnol.mutateAsync(payload)
      toast.success(`FNOL ${fnol.fnol_number} recorded — convert it to register the claim`)
      navigate(`/claims/fnol/${fnol.id}`)
    } catch (err) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
      toast.error(msg || 'Could not record the FNOL. Please try again.')
    }
  }

  if (!enabled) {
    return (
      <div className="p-8">
        <EmptyState
          title="FNOL Intake is not enabled"
          description="This module is turned off. An administrator can enable it under Admin → Integrations."
          action={<button onClick={() => navigate('/claims')} className="text-sm text-primary underline">Back to Claims</button>}
        />
      </div>
    )
  }
  if (!hasRole) {
    return (
      <div className="p-8">
        <EmptyState
          title="No access"
          description="FNOL Intake is available to Claims handlers, Claims Managers and administrators."
          action={<button onClick={() => navigate('/claims')} className="text-sm text-primary underline">Back to Claims</button>}
        />
      </div>
    )
  }

  // ── Shared token-based classes (no hardcoded colours — guardrail-clean) ──
  const inputCls = 'w-full min-h-[44px] md:min-h-0 px-3 py-2 border border-line rounded-md text-sm bg-surface text-ink focus:outline-none focus:ring-1 focus:ring-primary'
  const labelCls = 'block text-sm font-medium text-ink mb-1'
  const reqCls = 'text-status-danger-fg'
  const errCls = 'mt-1 text-xs text-status-danger-fg'
  const hintCls = 'text-[0.68rem] font-normal text-ink-muted'
  const sectionCls = 'font-heading text-sm font-bold text-brand-navy uppercase tracking-wide border-b-2 border-brand-orange/40 pb-1.5 mb-3'

  return (
    <div className="max-w-4xl space-y-4">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 className="font-heading text-2xl font-bold text-ink">New Claim</h1>
          <p className="text-xs text-ink-muted mt-0.5">Complete all applicable fields. Client Name, Reported Date and Description are required.</p>
        </div>
        <button onClick={() => navigate('/claims/fnol')} className="text-sm text-primary underline">Back to FNOL list</button>
      </div>

      <form onSubmit={handleSubmit} className="bg-surface rounded-lg border border-line p-5 space-y-6">
        {/* ── Basic Information ── */}
        <div>
          <p className={sectionCls}>Basic Information</p>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {/* Channel */}
            <div>
              <label className={labelCls}>Channel</label>
              <select value={channel} onChange={(e) => setChannel(e.target.value)} className={inputCls}>
                {CHANNELS.map((c) => <option key={c} value={c}>{c}</option>)}
              </select>
            </div>

            {/* Broker Name (Channel = Broker) */}
            {showBroker && (
              <div>
                <label className={labelCls}>Broker Name <span className={reqCls}>*</span></label>
                <select value={brokerSel} onChange={(e) => setBrokerSel(e.target.value)} className={inputCls}>
                  <option value="">Select broker…</option>
                  {brokers.map((b) => <option key={b} value={b}>{b}</option>)}
                  <option value={OTHER_SPECIFY}>{OTHER_SPECIFY}</option>
                </select>
              </div>
            )}
            {showBroker && brokerSel === OTHER_SPECIFY && (
              <div>
                <label className={labelCls}>Specify Broker</label>
                <input value={brokerOther} onChange={(e) => setBrokerOther(e.target.value)} className={inputCls} placeholder="Enter broker name…" />
              </div>
            )}

            {/* Client Name */}
            <div>
              <label className={labelCls}>Client Name <span className={reqCls}>*</span></label>
              <input value={clientName} onChange={(e) => { setClientName(e.target.value); clearErr('claimant_name') }} className={inputCls} placeholder="Full name" />
              {errors.claimant_name && <p className={errCls}>{errors.claimant_name}</p>}
            </div>

            {/* Contact Phone */}
            <div>
              <label className={labelCls}>Contact Phone <span className={hintCls}>— used for SMS status updates</span></label>
              <input type="tel" value={contactPhone} onChange={(e) => setContactPhone(e.target.value)} className={inputCls} placeholder="+267…" />
            </div>

            {/* FAC tag (auto) */}
            {isFac && (
              <div className="sm:col-span-2 rounded-md bg-brand-navy text-white px-4 py-2.5 text-sm font-bold">
                📋 FAC (FACULTATIVE) CLAIM — This client falls under a Facultative policy.
              </div>
            )}

            {/* Reinsurer (FAC only) */}
            {isFac && (
              <div>
                <label className={labelCls}>Reinsurer</label>
                <select value={reinsurer} onChange={(e) => setReinsurer(e.target.value)} className={inputCls}>
                  <option value="">Select reinsurer…</option>
                  {reinsurers.map((r) => <option key={r} value={r}>{r}</option>)}
                </select>
              </div>
            )}

            {/* Policy Number (+ optional lookup) */}
            <div>
              <label className={labelCls}>Policy Number <span className={reqCls}>*</span></label>
              <div className="flex gap-2">
                <input
                  value={policyNumber}
                  onChange={(e) => { setPolicyNumber(e.target.value); setResolvedPolicyId(null); setPolicyInfo('') }}
                  className={inputCls}
                  placeholder="DOMG…"
                />
                <Button type="button" variant="secondary" onClick={lookupPolicy} loading={lookingUp} disabled={!policyNumber.trim()}>
                  Look up
                </Button>
              </div>
              {policyInfo && <p className={`mt-1 text-xs ${resolvedPolicyId ? 'text-status-success-fg' : 'text-ink-muted'}`}>{policyInfo}</p>}
            </div>

            {/* Claim Number — auto-assigned by Graphite, locked on new */}
            <div>
              <label className={labelCls}>Claim Number <span className={reqCls}>*</span> <span className={hintCls}>— auto-assigned by Graphite</span></label>
              <input value="Pending — auto-assigned by Graphite" readOnly disabled className={`${inputCls} bg-surface-2 cursor-not-allowed text-ink-muted`} />
            </div>

            {/* Claims Handler (+ Other) */}
            <div>
              <label className={labelCls}>Claims Handler</label>
              <select value={claimsHandlerSel} onChange={(e) => setClaimsHandlerSel(e.target.value)} className={inputCls}>
                <option value="">Select handler…</option>
                {handlers.map((h) => <option key={h} value={h}>{h}</option>)}
                <option value={OTHER}>{OTHER}</option>
              </select>
            </div>
            {claimsHandlerSel === OTHER && (
              <div>
                <label className={labelCls}>Handler Name (Other) <span className={reqCls}>*</span></label>
                <input value={claimsHandlerOther} onChange={(e) => setClaimsHandlerOther(e.target.value)} className={inputCls} placeholder="Type handler name…" />
              </div>
            )}

            {/* Reported Date */}
            <div>
              <label className={labelCls}>Reported Date <span className={reqCls}>*</span></label>
              <input type="date" value={reportedDate} onChange={(e) => { setReportedDate(e.target.value); clearErr('reported_date') }} className={inputCls} />
              {errors.reported_date && <p className={errCls}>{errors.reported_date}</p>}
            </div>

            {/* Allocated Date — immediately after Reported Date (claims-team v6).
                "Claims Allocated On" was renamed "Allocated Date" (v5); now also
                captured at intake and carried onto the claim (claims.claim_allocated_on)
                by the convert step. Optional. */}
            <div>
              <label className={labelCls}>Allocated Date</label>
              <input type="date" value={allocatedDate} onChange={(e) => { setAllocatedDate(e.target.value); clearErr('claim_allocated_on') }} className={inputCls} />
              {errors.claim_allocated_on && <p className={errCls}>{errors.claim_allocated_on}</p>}
            </div>

            {/* Date of Loss — immediately after Reported Date (claims-team request #5).
                Optional at FNOL: a claim can be logged before the loss date is known
                (v5 feedback). A real claim still requires it at convert-time. */}
            <div>
              <label className={labelCls}>Date of Loss</label>
              <input type="date" value={lossDate} onChange={(e) => { setLossDate(e.target.value); clearErr('loss_date') }} className={inputCls} />
              {errors.loss_date && <p className={errCls}>{errors.loss_date}</p>}
            </div>

            {/* Claim Type */}
            <div>
              <label className={labelCls}>Claim Type <span className={reqCls}>*</span></label>
              <select value={claimType} onChange={(e) => setClaimType(e.target.value)} className={inputCls}>
                <option value="">Select…</option>
                {CLAIM_TYPES.map((t) => <option key={t} value={t}>{t}</option>)}
              </select>
            </div>

            {/* Customer Type (Motor + Non-Motor) */}
            {showCustomerType && (
              <div>
                <label className={labelCls}>Customer Type <span className={reqCls}>*</span></label>
                <select value={customerTypeSel} onChange={(e) => setCustomerTypeSel(e.target.value)} className={inputCls}>
                  <option value="">Select…</option>
                  {CUSTOMER_TYPES.map((c) => <option key={c} value={c}>{c}</option>)}
                </select>
              </div>
            )}
            {showCustomerType && customerTypeSel === OTHER && (
              <div>
                <label className={labelCls}>Customer Type (Other) <span className={reqCls}>*</span></label>
                <input value={customerTypeOther} onChange={(e) => setCustomerTypeOther(e.target.value)} className={inputCls} placeholder="Describe the customer type…" />
              </div>
            )}

            {/* Non-Motor sub-type + assessor (Non-Motor) */}
            {showNonMotor && (
              <div>
                <label className={labelCls}>Non-Motor Claim Type <span className={reqCls}>*</span></label>
                <select value={nonMotorSubType} onChange={(e) => setNonMotorSubType(e.target.value)} className={inputCls}>
                  <option value="">Select Non-Motor Type…</option>
                  {NON_MOTOR_TYPES.map((t) => <option key={t} value={t}>{t}</option>)}
                </select>
              </div>
            )}
            {showNonMotor && (
              <div>
                <label className={labelCls}>Non-Motor Assessor <span className={reqCls}>*</span></label>
                <select value={assessorSel} onChange={(e) => setAssessorSel(e.target.value)} className={inputCls}>
                  <option value="">Select Non-Motor Assessor…</option>
                  {nonMotorAssessors.map((a) => <option key={a} value={a}>{a}</option>)}
                  <option value={OTHER_SPECIFY}>{OTHER_SPECIFY}</option>
                </select>
              </div>
            )}
            {showNonMotor && assessorSel === OTHER_SPECIFY && (
              <div>
                <label className={labelCls}>Specify Non-Motor Assessor</label>
                <input value={assessorOther} onChange={(e) => setAssessorOther(e.target.value)} className={inputCls} placeholder="Enter assessor name…" />
              </div>
            )}

            {/* Glass Supplier (Glass / Lock & Key) */}
            {showGlass && (
              <div>
                <label className={labelCls}>Glass Supplier <span className={reqCls}>*</span></label>
                <select value={glassSupplierSel} onChange={(e) => setGlassSupplierSel(e.target.value)} className={inputCls}>
                  <option value="">Select Glass Supplier…</option>
                  {glassSuppliers.map((s) => <option key={s} value={s}>{s}</option>)}
                  <option value={OTHER_SPECIFY}>{OTHER_SPECIFY}</option>
                </select>
              </div>
            )}
            {showGlass && glassSupplierSel === OTHER_SPECIFY && (
              <div>
                <label className={labelCls}>Specify Glass Supplier</label>
                <input value={glassSupplierOther} onChange={(e) => setGlassSupplierOther(e.target.value)} className={inputCls} placeholder="Enter glass supplier name…" />
              </div>
            )}

            {/* Motor Assessor (Motor claims) */}
            {isMotor && (
              <div>
                <label className={labelCls}>Motor Assessor</label>
                <select value={motorAssessorSel} onChange={(e) => setMotorAssessorSel(e.target.value)} className={inputCls}>
                  <option value="">Select Motor Assessor…</option>
                  {motorAssessors.map((a) => <option key={a} value={a}>{a}</option>)}
                  <option value={OTHER_SPECIFY}>{OTHER_SPECIFY}</option>
                </select>
              </div>
            )}
            {isMotor && motorAssessorSel === OTHER_SPECIFY && (
              <div>
                <label className={labelCls}>Specify Motor Assessor</label>
                <input value={motorAssessorOther} onChange={(e) => setMotorAssessorOther(e.target.value)} className={inputCls} placeholder="Enter assessor name…" />
              </div>
            )}

            {/* Plate Number */}
            <div>
              <label className={labelCls}>Plate Number</label>
              <input value={plateNumber} onChange={(e) => setPlateNumber(e.target.value)} className={inputCls} placeholder="B123ABC" />
            </div>

            {/* Reserve is set at claim edit-time, not on the new-claim form (v5 #10). */}

            {/* Claim Paid Amount is set at claim edit-time, not on the new-claim form (claims-team v6). */}

            {/* Claim Description */}
            <div className="sm:col-span-2">
              <label className={labelCls}>Claim Description <span className={reqCls}>*</span></label>
              <textarea value={description} onChange={(e) => { setDescription(e.target.value); clearErr('description') }} rows={3} className={inputCls} placeholder="Describe the incident — required for new claims" />
              {errors.description && <p className={errCls}>{errors.description}</p>}
            </div>
          </div>
        </div>

        {/* ── Comments ── */}
        <div>
          <p className={sectionCls}>Comments</p>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className={labelCls}>Comment Status</label>
              <select value={commentStatus} onChange={(e) => setCommentStatus(e.target.value)} className={inputCls}>
                <option value="">Select…</option>
                {COMMENT_STATUSES.map((s) => <option key={s} value={s}>{s}</option>)}
              </select>
            </div>
            {commentStatus === SUB_REASON_STATUS && (
              <div>
                <label className={labelCls}>Awaiting — What specifically?</label>
                <select value={commentSubReason} onChange={(e) => setCommentSubReason(e.target.value)} className={inputCls}>
                  <option value="">Select…</option>
                  {AWAITING_SUB_REASONS.map((r) => <option key={r} value={r}>{r}</option>)}
                </select>
              </div>
            )}
          </div>
        </div>

        {/* Stage tracking (the 7 numbered stages + PO options) is filled at claim
            EDIT time (SLA Timeline tab), not at intake (claims-team v5 #04). */}

        <div className="flex items-center gap-2 pt-1">
          <Button type="submit" loading={createFnol.isPending}>Save Claim</Button>
          <Button type="button" variant="ghost" onClick={() => navigate('/claims/fnol')}>Cancel</Button>
        </div>
      </form>
    </div>
  )
}
