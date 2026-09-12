import { useState, useEffect, useRef } from 'react'
import { useParams, useNavigate, useSearchParams, Link } from 'react-router-dom'
import CreatableSelect from 'react-select/creatable'
import { useClaim, useUpdateClaimStatus, useClaimCreateData, useClaimReviewNotes, useCreateClaimReviewNote } from '../../hooks/useClaims'
import { useCanSeeClaimSlaPanel, canEditClaimSlaTimeline } from '../../hooks/useClaimsSla'
import { useCanSeeClaimDecisionPanel } from '../../hooks/useClaimsDecision'
import ClaimSlaTab from './ClaimSlaTab'
import ClaimDecisionTab from './ClaimDecisionTab'
import ClaimPurchaseOrdersTab from './ClaimPurchaseOrdersTab'
import { useCanSeeClaimPurchaseOrders } from '../../hooks/useClaimPurchaseOrders'
import apiClient from '../../api/client'
import { getStoredPermissions, getStoredUser } from '../../api/auth'
import { MentionComposer, MentionText, countMyMentions } from '../../components/claims/ReviewNoteMentions'
// setClaimCommentStatus is only used by the (currently commented-out) Comment
// status panel in TabReviewNotes — kept out of the import so noUnusedLocals passes.
import { getClaimCommentStatus, /* setClaimCommentStatus, */ type ClaimCommentStatusOptions } from '../../api/claims'
import { updateUnionLegalClaim, MATTER_RELATES_TO, MATTER_TYPES } from '../../api/unions'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import { useToast } from '../../components/common/Toast'
import type {
  ClaimDetail, ClaimReserve, ClaimAttachment,
  ClaimQuote, ClaimActivity, ClaimComplaint,
  ClosingDocuments,
} from '../../api/claims'
import {
  uploadClaimAttachment, deleteClaimAttachment,
  fetchClosingDocuments,
  fetchReserveTransactionTypes, fetchReserveSubTypes, fetchReservePayees,
  fetchVoidPaymentInfo, createReserve, voidReservePayment,
  fetchReserveCoverages,
  createClaimComplaint,
  updateClaimComplaint,
  fetchComplaintLookups,
  fetchComplaintPrefill,
  fetchClaimComplaints,
  uploadComplaintDocument,
  type ComplaintPayload,
  type ComplaintLookups,
  fetchClaimPolicyActions,
} from '../../api/claims'
import type { ReserveLookupItem, ReservePayee, VoidPaymentInfo, ReserveCoverageRow, ReserveCoveragesResponse, PolicyActionOption } from '../../api/claims'
import { fetchPolicyVehicles } from '../../api/policies'
import { fmtPula, fmtDate, fmtDateTime } from '../../utils/format'
import SendClaimFormButton from '../../components/claims/SendClaimFormButton'

// ── Helpers ──────────────────────────────────────────────────
// Delegates to the shared fmtPula so negative values render as
// "(Credit P X.XX)" rather than "P -X.XX" — UAT 2026-05-26 (Arjun M6,
// Prathap BUG-005). Finance reads the literal minus as a data bug.
// fmtDate is also delegated to the shared utility (adds invalid-date
// guard the local version lacked; fallback char is now em-dash '—').
const fmt = (v?: number | null) => v != null ? fmtPula(v) : '-'
const yesNo = (v?: boolean | string | number | null) => {
  if (v === true || v === 'Yes' || v === '1' || v === 1) return 'Yes'
  // null/undefined/empty/false/0 all treated as "No" (matches graphiteBWV8)
  return 'No'
}

const STATUS_COLORS: Record<string, string> = {
  Pending:              'bg-status-warning-bg text-status-warning-fg border-status-warning-fg',
  'Pending Assessment': 'bg-status-warning-bg text-status-warning-fg border-status-warning-fg',
  New:                  'bg-status-info-bg text-primary border-primary',
  Approved:             'bg-status-success-bg text-status-success-fg border-status-success-fg',
  Rejected:             'bg-status-danger-bg text-status-danger-fg border-status-danger-fg',
  Closed:               'bg-surface-2 text-ink-muted border-line',
  Reopen:               'bg-status-warning-bg text-status-warning-fg border-status-warning-fg',
  Open:                 'bg-status-info-bg text-primary border-primary',
}

// Must match backend ClaimsV2Controller::allowedTransitions() exactly
const STATUS_TRANSITIONS: Record<string, string[]> = {
  New:                  ['Pending Assessment', 'Approved', 'Rejected', 'Open'],
  // Legacy rows carry status 'Pending' (not 'Pending Assessment') — treat as same bucket
  Pending:              ['Pending Assessment', 'Approved', 'Rejected', 'Open'],
  'Pending Assessment': ['Approved', 'Rejected', 'Open'],
  Approved:             ['Closed', 'Open'],
  Rejected:             ['Closed', 'Reopen', 'Open'],
  Closed:               ['Reopen', 'Open'],
  Reopen:               ['Pending Assessment', 'Approved', 'Open'],
  Open:                 ['Pending Assessment', 'Approved', 'Rejected', 'Closed', 'Reopen'],
}

// Sub-statuses shown when a claim is moved into the 'Open' status.
// Must match the $openSubStatuses list in backend ClaimsController::updateStatus().
const OPEN_SUB_STATUSES: string[] = [
  'Awaiting Claim Documents',
  'Awaiting Premium Confirmation',
  'Awaiting Invoice',
  'Awaiting Assessment Report',
  'Awaiting Signed AOL',
  'Awaiting Signed Form of Release',
  'Awaiting Signed Cash In Lieu',
  'Awaiting Signed Ex-Gratia',
  'Awaiting Third Party Insurance Confirmation',
  'Awaiting KYC Compliance',
  'Awaiting Excess to be Paid',
  'Awaiting Salvage to be paid',
  'Awaiting Proof of Payment',
  "Awaiting Demand Documents from third party's insurer",
  'Subrogation on going',
  'Legal Proceedings on going',
  'Repudiation',
  'File with Finance for payment',
  'Claim withdrawn',
  'Claim below Excess',
  'Claim Under Review',
  'Inter-Departmental Assistance Pending - Underwriting / System Developers',
  'Approved',
  'Closed',
  'Re-open',
]

type TabKey = 'policy' | 'details' | 'assessor' | 'attachments' | 'reserves' | 'suppliers' | 'invoice' | 'reinsurance' | 'activity' | 'complaints' | 'reviewNotes' | 'sla' | 'decision' | 'purchaseOrders'
const TABS: { key: TabKey; label: string }[] = [
  { key: 'policy',      label: 'Policy Details' },
  { key: 'details',     label: 'Claim Details' },
  { key: 'assessor',    label: 'Assessor' },
  { key: 'attachments', label: 'Attachments' },
  { key: 'reserves',    label: 'Reserves / Payments' },
  { key: 'reviewNotes', label: 'Claim Review' },
  { key: 'suppliers',   label: 'Suppliers' },
  { key: 'invoice',     label: 'Invoice' },
  { key: 'reinsurance', label: 'Reinsurance' },
  { key: 'activity',    label: 'Activity Log' },
  { key: 'complaints',  label: 'Complaint Log' },
]

// ── Small reusable pieces ────────────────────────────────────
function Card({ title, children, accent, action }: { title: React.ReactNode; children: React.ReactNode; accent?: string; action?: React.ReactNode }) {
  const bg = accent ?? 'bg-surface-2'
  return (
    <div className="bg-surface rounded-xl border border-line shadow-sm overflow-hidden">
      <div className={`${bg} px-5 py-3 border-b border-line flex items-center justify-between gap-2`}>
        <h2 className="text-sm font-semibold text-ink-muted uppercase tracking-wide">{title}</h2>
        {action ? <div className="flex items-center gap-2">{action}</div> : null}
      </div>
      <div className="p-5">{children}</div>
    </div>
  )
}

function DL({ items }: { items: [string, React.ReactNode][] }) {
  return (
    <dl className="grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
      {items.map(([label, value], i) => (
        <div key={i}>
          <dt className="text-ink-faint">{label}</dt>
          <dd className="font-medium text-ink mt-0.5">{value ?? '-'}</dd>
        </div>
      ))}
    </dl>
  )
}

function FileLink({ url, label }: { url?: string; label: string }) {
  if (!url) return <span className="text-ink-faint text-sm">Not available</span>
  return (
    <a href={url} target="_blank" rel="noopener noreferrer"
      className="inline-flex items-center gap-1.5 text-sm text-primary hover:underline">
      <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
      </svg>
      {label}
    </a>
  )
}

function Empty({ text }: { text: string }) {
  return <p className="text-sm text-ink-faint italic py-4 text-center">{text}</p>
}

// ── Tab content components ───────────────────────────────────

function TabPolicyDetails({ c }: { c: ClaimDetail }) {
  return (
    <div className="space-y-6">
      {c.policy && (
        <Card title="Policy Information" accent="bg-status-info-bg">
          <DL items={[
            ['Policy Number', <Link to={`/policies/${c.policy.id}`} className="text-primary hover:underline font-medium">{c.policy.policy_number}</Link>],
            ['Product', c.policy.product_name || '-'],
            ['Premium', fmt(c.policy.premium)],
            ['Status', c.policy.status === 1 ? 'Active' : c.policy.status === 2 ? 'Cancelled' : c.policy.status === 0 ? 'Pending' : 'Expired'],
            ['Agent', c.policy.agent_name || '-'],
          ]} />
        </Card>
      )}
      {c.customer && (
        <Card title={c.customer.is_company ? 'Company / Organisation' : 'Customer'} accent="bg-status-success-bg">
          <DL items={[
            ...(c.customer.is_company ? [['Company Name', c.customer.company_name || '-']] as [string, React.ReactNode][] : []),
            ...(c.customer.individual_name ? [['Contact Person', c.customer.individual_name]] as [string, React.ReactNode][] : [['Name', c.customer.name] as [string, React.ReactNode]]),
            ['Email', c.customer.email || '-'],
            ['Phone', c.customer.cellphone || '-'],
          ]} />
        </Card>
      )}
    </div>
  )
}

function TabInvoice({ quotes, reserves }: { quotes?: ClaimQuote[]; reserves?: ClaimReserve[] }) {
  // Show supplier invoices + reserve payments with invoice numbers
  const supplierInvoices = (quotes || []).filter(q => q.invoice)
  const reserveInvoices = (reserves || []).filter(r => r.invoiceNo)
  if (supplierInvoices.length === 0 && reserveInvoices.length === 0) {
    return <Card title="Invoices"><p className="text-sm text-ink-faint">No invoices recorded for this claim.</p></Card>
  }
  return (
    <div className="space-y-6">
      {supplierInvoices.length > 0 && (
        <Card title="Supplier Invoices">
          <div className="space-y-3">
            {supplierInvoices.map(q => (
              <div key={q.id} className="flex items-center justify-between p-3 bg-surface-2 rounded-lg">
                <div>
                  <p className="font-medium text-ink">Quote #{q.id}</p>
                  <p className="text-xs text-ink-faint mt-0.5">{q.invoiceNotes || 'No notes'}</p>
                  <p className="text-xs text-ink-faint mt-0.5">{fmtDate(q.createdAt)}</p>
                </div>
                <div className="flex items-center gap-3">
                  <span className="font-bold text-ink">{fmt(q.total)}</span>
                  {q.invoice && <a href={q.invoice} target="_blank" rel="noopener" className="text-primary hover:underline text-sm">Download</a>}
                </div>
              </div>
            ))}
          </div>
        </Card>
      )}
      {reserveInvoices.length > 0 && (
        <Card title="Reserve / Payment Invoices">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-surface-2 text-xs text-ink-muted uppercase">
                <tr><th className="px-3 py-2 text-left">Date</th><th className="px-3 py-2 text-left">Invoice #</th><th className="px-3 py-2 text-left">Type</th><th className="px-3 py-2 text-left">Payee</th><th className="px-3 py-2 text-right">Amount</th></tr>
              </thead>
              <tbody className="divide-y divide-line">
                {reserveInvoices.map(r => (
                  <tr key={r.id}>
                    <td className="px-3 py-2">{fmtDate(r.date)}</td>
                    <td className="px-3 py-2 font-mono text-xs">{r.invoiceNo}</td>
                    <td className="px-3 py-2">{(r as any).transactionTypeName || r.transactionType}</td>
                    <td className="px-3 py-2">{(r as any).payeeName || r.payee || '-'}</td>
                    <td className="px-3 py-2 text-right font-medium">{fmt(r.coverages?.reduce((s, c) => s + (c.paymentAmt || 0), 0))}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      )}
    </div>
  )
}

/**
 * Build the Classification & Allocation key/value list rendered above the
 * tab-level edit modal on the detail page. Mirrors the read-back of the
 * 13 DOM/COM common fields the operator captures at create-time
 * (graphiteBWV8 main.blade.php classification block + V2 ClassAlloc).
 *
 * Returns [] when none of the fields are populated, so non-DOM/COM claims
 * (Life, Motor, Legal, Hospital Cash, etc.) don't get an empty section.
 *
 * `createData` provides the lookup tables for resolving IDs → names:
 *  - internal_users      → Service Representative + Claims Allocated To
 *  - attorneys_primary   → Primary Attorney Assigned
 *  - attorneys_co        → Co-Attorney Assigned
 *  - loss_types_dom_com  → Type of Loss (1=Property, 2=Liability)
 */
function buildClassificationAllocationItems(
  c: any,
  createData: any,
  claimSubTypes: { id: any; name: string }[] = [],
): [string, React.ReactNode][] {
  const items: [string, React.ReactNode][] = []
  const push = (label: string, value: React.ReactNode) => {
    if (value === null || value === undefined || value === '' || value === '-') return
    items.push([label, value])
  }
  const lookupName = (list: { id: any; name: string }[] | undefined, id: any): string | null => {
    if (!list || id === null || id === undefined || id === '') return null
    const hit = list.find((o: any) => String(o.id) === String(id))
    return hit ? hit.name : null
  }

  // Policy action / term the claim is filed against (claims.policy_action_id).
  // Same label format as the header badge & create-flow selector.
  if (c.policy_action) {
    const pa = c.policy_action
    const from = pa.effective_from ? new Date(pa.effective_from).toLocaleDateString('en-GB') : '—'
    const to   = pa.effective_to   ? new Date(pa.effective_to).toLocaleDateString('en-GB')   : '—'
    const window = (pa.effective_from || pa.effective_to) ? ` (${from} – ${to})` : ''
    const label = `${pa.transaction_type} - ${pa.status}${window}`
    push('Policy Action / Term', pa.deleted ? (
      <span className="inline-flex items-center gap-2">
        <span className="text-status-danger-fg line-through">{label}</span>
        <span className="inline-flex items-center px-2 py-0.5 rounded bg-status-danger-bg text-status-danger-fg text-xs font-semibold border border-status-danger-fg">Deleted</span>
      </span>
    ) : label)
  }

  // Location: backend resolves to the address_name string already, but
  // fall back to the raw location_id when only the FK is present.
  push('Select Location', c.location || c.location_id || null)
  // Stored as the lookup row id (e.g. "77") — resolve via the
  // createData.reported_by lookup so the operator sees the relation
  // name (Insured / Co-Insured / Agent / etc.) instead of the raw id.
  push('Claim Reported By',
    lookupName(createData?.reported_by, c.claim_reported_by) || c.claim_reported_by || null)

  // Total Reserve Amount / Total Paid Amount intentionally NOT shown here:
  // they were stale fields stored on new_claims at creation-time only and
  // diverged from the live totals shown in the Claim Status Details banner
  // (which derive from claim_reserves_coverages with voided rows excluded).
  // Single source of truth wins — the banner is authoritative.
  push('Type of Loss',
    lookupName(createData?.loss_types_dom_com, c.type_of_loss) || c.type_of_loss || null)
  push('Service Representative',
    lookupName(createData?.internal_users, c.service_representative_id) || c.service_representative_id || null)
  push('Catastrophe Loss',  c.catastrophe_loss != null ? yesNo(c.catastrophe_loss) : null)
  push('Primary Attorney Involved', c.attorney_involved != null ? yesNo(c.attorney_involved) : null)
  if (String(c.attorney_involved) === '1') {
    push('Primary Attorney Assigned',
      lookupName(createData?.attorneys_primary, c.primary_attorney_assigned_id) || c.primary_attorney_assigned_id || null)
    push('Primary Attorney Assigned Date', c.p_a_assigned_date ? fmtDate(c.p_a_assigned_date) : null)
  }
  push('Co-Attorney Involved', c.co_attorney_involved != null ? yesNo(c.co_attorney_involved) : null)
  if (String(c.co_attorney_involved) === '1') {
    push('Co-Attorney Assigned',
      lookupName(createData?.attorneys_co, c.co_attorney_assigned_id) || c.co_attorney_assigned_id || null)
    push('Co-Attorney Assigned Date', c.c_a_assigned_date ? fmtDate(c.c_a_assigned_date) : null)
  }
  push('DFS Complaint', c.dfs_complaint != null ? yesNo(c.dfs_complaint) : null)
  push('Claims Allocated To', c.allocated_to || lookupName(createData?.internal_users, c.claim_allocated_to))
  push('Allocated Date', c.allocated_on ? fmtDate(c.allocated_on) : (c.claim_allocated_on ? fmtDate(c.claim_allocated_on) : null))
  push('Date First Visited', c.date_first_visited ? fmtDate(c.date_first_visited) : null)
  push('Claim Submission Date', (c as any).submission_date ? fmtDate((c as any).submission_date) : null)
  // V8 Classification block extras: Claim Sub Type, Reported by Broker/Agent,
  // Date of Loss, Description of Loss, Event Name. Resolve the sub-type and
  // broker/agent ids to their display names so operators see human-readable
  // values. Event Name is free-text on new_claims for All Risks claim types.
  push('Claim Sub Type',
    lookupName(claimSubTypes, c.claim_sub_type_id) || c.claim_sub_type || null)
  push('Reported by Broker/Agent',
    lookupName(createData?.agents_options, c.reportedByBrokerAgent) || c.reportedByBrokerAgent || null)
  push('Date of Loss', c.date_of_loss ? fmtDate(c.date_of_loss) : null)
  push('Description of Loss', c.description_of_loss || null)
  push('Event Name', c.event_name || null)

  return items
}

// ── Inline-edit form primitives (module-level for stable identity) ──
// Defining these inside the renderSubClaimEditForm / renderClassification
// EditForm helpers gave them new function identities every render, which
// caused React to remount the inputs and steal focus after every keystroke.
// Hoisting them up here keeps the component type constant across renders.
type EditDraftProps = {
  draft: Record<string, any>
  upd: (k: string, v: any) => void
}
const EditField = ({ k, l, type = 'text', draft, upd, disabled = false }: { k: string; l: string; type?: string; disabled?: boolean } & EditDraftProps) => (
  <div>
    <label className="block text-xs font-medium text-ink-faint mb-1">{l}</label>
    <input type={type} value={draft[k] ?? ''} onChange={e => upd(k, e.target.value)} disabled={disabled}
      className={`w-full px-3 py-2 border border-line rounded-md text-sm ${disabled ? 'bg-surface-2 text-ink-muted cursor-not-allowed' : ''}`} />
  </div>
)
const EditArea = ({ k, l, rows = 2, draft, upd }: { k: string; l: string; rows?: number } & EditDraftProps) => (
  <div className="col-span-2">
    <label className="block text-xs font-medium text-ink-faint mb-1">{l}</label>
    <textarea rows={rows} value={draft[k] ?? ''} onChange={e => upd(k, e.target.value)}
      className="w-full px-3 py-2 border border-line rounded-md text-sm" />
  </div>
)
const EditSel = ({ k, l, opts, draft, upd }: { k: string; l: string; opts: { id: any; name: string }[] | undefined } & EditDraftProps) => (
  <div>
    <label className="block text-xs font-medium text-ink-faint mb-1">{l}</label>
    <select value={String(draft[k] ?? '')} onChange={e => upd(k, e.target.value)}
      className="w-full px-3 py-2 border border-line rounded-md text-sm">
      <option value="">--</option>
      {(opts || []).map(o => <option key={String(o.id)} value={String(o.id)}>{o.name}</option>)}
    </select>
  </div>
)
const EditYesNo = ({ k, l, draft, upd }: { k: string; l: string } & EditDraftProps) => (
  <div>
    <label className="block text-xs font-medium text-ink-faint mb-1">{l}</label>
    <select value={String(draft[k] ?? '')} onChange={e => upd(k, e.target.value)}
      className="w-full px-3 py-2 border border-line rounded-md text-sm">
      <option value="">--</option>
      <option value="1">Yes</option>
      <option value="0">No</option>
    </select>
  </div>
)

/**
 * Inline editor for the type-specific sub-claim card (currently scoped to
 * Goods In Transit columns; other claim types still see the read-only DL
 * because their auto-render covers the fields without a form). Backed by
 * the parent's `sectionDraft` state — `draft` and `setDraft` are
 * passed in so the caller controls when to flush via PUT /claims/{id}.
 */
function renderSubClaimEditForm({
  claimType, draft, setDraft, error, copyOfContractUrl, copyOfContractPath, files, setFiles, plateOpts,
}: {
  claimType: string | null | undefined
  draft: Record<string, any>
  setDraft: React.Dispatch<React.SetStateAction<Record<string, any>>>
  error: string | null
  copyOfContractUrl?: string
  copyOfContractPath?: string
  files: Record<string, File | null>
  setFiles: React.Dispatch<React.SetStateAction<Record<string, File | null>>>
  /** Term-scoped vehicle plates — when non-empty, plate fields render as a select. */
  plateOpts?: { id: string; name: string }[]
}) {
  const setFile = (k: string, f: File | null) => setFiles((p: Record<string, File | null>) => ({ ...p, [k]: f }))
  const upd = (k: string, v: any) => setDraft((p: any) => ({ ...p, [k]: v }))
  const ctKey = (claimType || '').toUpperCase().replace(/[^A-Z0-9]/g, '')

  // All Risks family — dropdowns + conditional reveal blocks. Mirrors the
  // ClaimCreatePage All Risks section so the per-section editor and the
  // create form share field labels and visibility rules.
  const isAllRisksType = ctKey === 'BUSINESSALLRISKS' || ctKey === 'PERSONALALLRISKS'
    || ctKey === 'ELECTRONICEQUIPMENT' || ctKey === 'ALLRISK'
  // Burglary family (THEFT / BURGLARY / MONEY) — V8 burglary.blade.php
  // shares one table for all three claim type aliases. Same field set,
  // same Yes/No dropdowns, anyone_on_premises_brief revealed only when
  // anyone_on_premises === '1'.
  const isBurglaryType = ctKey === 'BURGLARY' || ctKey === 'THEFT'
    || ctKey === 'BURGLARYTHEFT' || ctKey === 'MONEY'
  // Workers Compensation / Stated Benefits — V8 workers_compensation.blade
  // shares one table. Two sections (Injured Person + Accident). EMPLOYER
  // section is commented out in V8 so we don't render it.
  // address_of_contractor is revealed only when your_direct_employ === '0'.
  const isWorkersCompType = ctKey === 'WORKERSCOMPENSATION' || ctKey === 'STATEDBENEFITS'
  // Defective Workmanship — three sections (Accident/Incident, Claimant's
  // Vehicle, Incident Details). All fields visible — no V8 conditional
  // visibility on this form.
  const isDefectiveType = ctKey === 'DEFECTIVEWORKMANSHIP'
  // Mobile/Electronic Devices + Office Contents — V8 main.blade.php groups
  // both onto mobileAndElectronicDevices.blade.php + the
  // mobile_and_electronic_devices_claim table. sole_owner_of_property is
  // revealed only when is_sole_owner_of_property === '0'.
  const isMobileType = ctKey === 'MOBILEELECTRONICDEVICES' || ctKey === 'OFFICECONTENTS'
    || ctKey === 'MOBILEANDELECTRONICDEVICES'
  // Fidelity Guarantee — V8 fidelity_guarantee.blade.php. Two visible
  // inputs (defaulting employees, employees_been_involved Yes/No) plus
  // V2-only circumstances textarea (V8 has it commented out but the
  // column exists and V2 has surfaced it).
  const isFidelityType = ctKey === 'FIDELITYGUARANTEE'
  // Fire — V8 fire.blade.php. Conditional reveal blocks for
  // details_during_burglary (anyone_during_burglary=1),
  // name/telephone/guard_during_fire (premises_guarded_by_watchman=1),
  // suspect_person_details (suspect_any_person=1),
  // insurance_against_fire_details (other_insurance_against_fire=1).
  // contract_of_agreement is the S3-uploaded security agent contract.
  const isFireType = ctKey === 'FIRE'
  // Public Liability / Liability — V8 public_liability.blade.php. Six
  // sections (Insured, Accident, Job, Party Claim, Witness 1, Witness 2).
  // accident_liability/accident_person/accident_contacted are 'YES'/'NO'
  // strings (not int 1/0) per V8 migration.
  const isPublicLiabilityType = ctKey === 'PUBLICLIABILITY' || ctKey === 'LIABILITY'
  // Contractors All Risks / Public Liability — V8
  // contractors_all_risks_public_liability.blade.php. Four sections
  // (Responsible Person / Contract Details / Insurance Responsibility /
  // Loss-Damage Details with two file uploads).
  const isCarplType = ctKey === 'CONTRACTORSALLRISKS'
    || ctKey === 'CONTRACTORSALLRISKSPUBLICLIABILITY' || ctKey === 'CARPL'
  // Erection All Risk — V8 erection_all_risk.blade.php. Five labelled
  // sections (A: Insured / B: Accident / C: Damaged Works / D: Other
  // Insurances / E: Previous Losses). Three Yes/No fields conditionally
  // reveal follow-up textareas.
  const isEarType = ctKey === 'ERECTIONALLRISK'
  // Property Loss / Damage — V8 main.blade.php groups BUILDINGSCOMBINED,
  // ACCIDENTALDAMAGE, HOUSEHOLDERS, HOUSEOWNERS, HOUSEOWNER-BUILDINGS,
  // HOUSEHOLDERS-CONTENTS onto property_loss_damage.blade.php + the
  // property_loss_damage table. All 16 visible fields are free-text
  // textareas (no Yes/No booleans on this table — questions like
  // `previously_suffered_loss` are answered narratively).
  const isPropertyLossType = ctKey === 'PROPERTYLOSSDAMAGE' || ctKey === 'PROPERTYDAMAGE'
    || ctKey === 'BUILDINGSCOMBINED' || ctKey === 'ACCIDENTALDAMAGE'
    || ctKey === 'HOUSEHOLDERS' || ctKey === 'HOUSEOWNERS'
    || ctKey === 'HOUSEOWNERBUILDINGS' || ctKey === 'HOUSEHOLDERSCONTENTS'
  // Travel Insurance — V8 travel_insurance.blade.php. Seven sections
  // (Claimant / Policy & Journey / Bank / Other Insurance Yes-No +
  // conditional follow-up / Compulsory Docs / Type of Refund / Refund
  // Docs). Six compulsory file uploads + 19 refund-type-specific uploads;
  // re-uploading any file replaces the stored S3 path. The conditional
  // refund-doc sections only render for the matching type_of_refund.
  const isTravelInsuranceType = ctKey === 'TRAVELINSURANCE'
  // Professional Indemnity — V8 professional_indemnity.blade.php. Five
  // sections (Insured / Claimant / Contract & Claim / Circumstances /
  // Investigation). Six Yes/No fields with conditional follow-ups
  // (contract_in_place reveals file or details textarea; verbal_written_
  // demand → demand_received_date; served_with_summons → summons_served_
  // date; attorney_appointed → attorney_details; own_investigation →
  // findings file).
  const isPiType = ctKey === 'PROFESSIONALINDEMNITY'
  // Plant All Risks — V8 plant_all_risks.blade.php. Four sections
  // (Responsible Person on Site / Site Details / Plant Details /
  // Loss-Damage Details with 3 Yes/No selects + Police fields).
  const isParType = ctKey === 'PLANTALLRISKS'
  // Machinery Breakdown — form AD-CLM-MB-001. Six sections; six Yes/No
  // questions each followed by a free-text "Details" field.
  const isMbType = ctKey === 'MACHINERYBREAKDOWN'
  // Machinery Breakdown — Loss of Profit (form AD MB LOP v1.0). Consequential
  // BI loss filed after the physical-damage MB claim; five Yes/No questions.
  const isMbLopType = ctKey === 'MACHINERYBREAKDOWNLOSSOFPROFIT'
  // Medical Malpractice — V8 medical_malpractice.blade.php. Four
  // sections (Insured Party Information / Claimant (Patient)
  // Information / Details of Allegation / Supporting Documents). Five
  // file uploads in Section 4; no conditional reveal blocks (every
  // field always visible).
  const isMmType = ctKey === 'MEDICALMALPRACTICE'
  // Glass / Windscreen — V8 glass.blade.php (motor sub-form). Three
  // sections (Damage Details / Replacement Quotes / Damage Photos &
  // Descriptions). Six file uploads — 4 photos with text descriptions
  // + 2 replacement-quote documents (V2 extension).
  const isGlassType = ctKey === 'GLASS'
  // Locks & Keys / Key Loss — V8 key_loss.blade.php (motor sub-form).
  // One section; V8 active fields are purpose / lossDate /
  // descriptionofLoss / registered_claim. V2 extensions add chassis_num,
  // financial_interest, key_reason ('lost'/'damaged'/'stolen') and
  // estimate + a police_affidavit file upload.
  const isLokType = ctKey === 'LOCKSANDKEYS' || ctKey === 'KEYLOSS'

  return (
    <div className="space-y-3">
      {error && <div className="p-3 bg-status-danger-bg border border-status-danger-fg rounded-md text-status-danger-fg text-sm">{error}</div>}
      {ctKey === 'GOODSINTRANSIT' ? (
        <div className="grid grid-cols-2 gap-3">
          <EditArea  draft={draft} upd={upd} k="address_of_premises_loss" l="Address of premises where loss occurred" />
          <EditArea  draft={draft} upd={upd} k="details_of_driver" l="Details of the carrier / driver" />
          <EditField draft={draft} upd={upd} k="property_last_seen" l="Property last seen" />
          <EditField draft={draft} upd={upd} k="date_time_of_loss" l="Date and time of loss" type="datetime-local" />
          <EditArea  draft={draft} upd={upd} k="brief_description_incident" l="Brief description of incident" rows={3} />
          <EditField draft={draft} upd={upd} k="date_time_police_advised" l="Date/time police were advised" type="datetime-local" />
          <EditField draft={draft} upd={upd} k="police_station_name" l="Police station name" />
          <EditField draft={draft} upd={upd} k="witnesses_name" l="Witness names" />
          <EditField draft={draft} upd={upd} k="witnesses_mobile_number" l="Witness mobile number" />
          <EditField draft={draft} upd={upd} k="total_value_of_loss" l="Total value of loss (BWP)" type="number" />
          <EditField draft={draft} upd={upd} k="consignment_from" l="Consignment from" />
          <EditField draft={draft} upd={upd} k="consignment_transported_to" l="Consignment transported to" />
          <div className="col-span-2"><EditField draft={draft} upd={upd} k="vehicle_registration_number" l="Vehicle registration number" /></div>
          <EditYesNo draft={draft} upd={upd} k="is_carrier_contracted" l="Carrier contracted?" />
          <EditYesNo draft={draft} upd={upd} k="carrier_has_own_GIT_ins" l="Carrier has own GIT insurance?" />
          <EditYesNo draft={draft} upd={upd} k="other_insurance_against_theft" l="Other insurance against theft?" />
          {String(draft.is_carrier_contracted) === '1' && (
            <div className="col-span-2 space-y-2">
              {copyOfContractPath && (
                <div className="text-xs text-ink-faint italic">
                  Existing contract on file:&nbsp;
                  {copyOfContractUrl ? (
                    <a href={copyOfContractUrl} target="_blank" rel="noopener noreferrer"
                       className="text-primary hover:underline break-all">
                      {(copyOfContractPath || '').split('/').pop()}
                    </a>
                  ) : <span className="text-ink-muted">{(copyOfContractPath || '').split('/').pop()}</span>}
                </div>
              )}
              <div>
                <label className="block text-xs font-medium text-ink-faint mb-1">
                  {copyOfContractPath ? 'Replace contract (PDF / Image)' : 'Upload contract (PDF / Image)'}
                </label>
                <input type="file" accept=".pdf,.jpg,.jpeg,.png"
                  onChange={e => setFile('copy_of_contract', e.target.files?.[0] ?? null)}
                  className="w-full text-xs text-ink-faint file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border file:border-line file:text-xs file:font-medium file:bg-surface-2 file:text-ink-muted hover:file:bg-surface-2" />
                {files.copy_of_contract && (
                  <p className="mt-1 text-xs text-status-success-fg truncate">
                    Will upload: {files.copy_of_contract.name}
                  </p>
                )}
              </div>
            </div>
          )}
          {String(draft.other_insurance_against_theft) === '1' && (
            <EditArea draft={draft} upd={upd} k="insurance_against_theft_details" l="Details of other insurance" />
          )}
          <EditArea draft={draft} upd={upd} k="details_of_previous_loss_records" l="Details of records of previous loss" />
        </div>
      ) : isAllRisksType ? (
        // All Risks / Electronic Equipment / Personal All Risks — V8 parity.
        // Uses dropdowns for the binary fields (with the same Damaged/Stolen
        // and "Loss by theft"/"Loss by other cause" labels as Create), and
        // mirrors V8's two conditional reveal blocks (#otherCauseInput when
        // loss_cause=0; #classStolen when property_stolen_damaged=0).
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="block text-xs font-medium text-ink-faint mb-1">Has the property been stolen or damaged?</label>
            <select value={String(draft.property_stolen_damaged ?? '')} onChange={e => upd('property_stolen_damaged', e.target.value)}
              className="w-full px-3 py-2 border border-line rounded-md text-sm">
              <option value="">—</option><option value="1">Damaged</option><option value="0">Stolen</option>
            </select>
          </div>
          <div>
            <label className="block text-xs font-medium text-ink-faint mb-1">Have you ever before sustained previous loss?</label>
            <select value={String(draft.loss_cause ?? '')} onChange={e => upd('loss_cause', e.target.value)}
              className="w-full px-3 py-2 border border-line rounded-md text-sm">
              <option value="">—</option><option value="1">Loss by theft</option><option value="0">Loss by other cause</option>
            </select>
          </div>
          {String(draft.loss_cause) === '0' && (
            <div className="col-span-2">
              <EditField draft={draft} upd={upd} k="loss_by_other_cause" l="Other cause (please provide details)" />
            </div>
          )}
          {String(draft.property_stolen_damaged) === '0' && (
            <>
              <div>
                <label className="block text-xs font-medium text-ink-faint mb-1">Was the property stolen from a car or unlocked premises?</label>
                <select value={String(draft.stolenfromcar_unlockedpremises ?? '')} onChange={e => upd('stolenfromcar_unlockedpremises', e.target.value)}
                  className="w-full px-3 py-2 border border-line rounded-md text-sm">
                  <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                </select>
              </div>
              <div>
                <label className="block text-xs font-medium text-ink-faint mb-1">Has a thorough search been made for the article(s)?</label>
                <select value={String(draft.thorough_search_made_for_article ?? '')} onChange={e => upd('thorough_search_made_for_article', e.target.value)}
                  className="w-full px-3 py-2 border border-line rounded-md text-sm">
                  <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                </select>
              </div>
              <div>
                <label className="block text-xs font-medium text-ink-faint mb-1">Are you the sole owner of the property?</label>
                <select value={String(draft.is_sole_owner_of_property ?? '')} onChange={e => upd('is_sole_owner_of_property', e.target.value)}
                  className="w-full px-3 py-2 border border-line rounded-md text-sm">
                  <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                </select>
              </div>
              {String(draft.is_sole_owner_of_property) === '0' && (
                <EditField draft={draft} upd={upd} k="sole_owner_of_property" l="Name of the owner" />
              )}
            </>
          )}
        </div>
      ) : isBurglaryType ? (
        // Burglary / Theft / Money — V8 burglary.blade.php parity.
        // Mirrors ClaimCreatePage labels and conditional reveal of
        // anyone_on_premises_brief. description_of_incident is in the
        // burglary table but commented out in V8's blade; we omit it.
        <div className="grid grid-cols-2 gap-3">
          <div className="col-span-2">
            <EditArea draft={draft} upd={upd} k="address_of_premises" l="Address of premises where theft occurred" rows={2} />
          </div>
          <EditField draft={draft} upd={upd} k="date_time_police_advised" l="Date the police were advised of loss" type="date" />
          <div>
            <label className="block text-xs font-medium text-ink-faint mb-1">Was anyone at the premises during the burglary?</label>
            <select value={String(draft.anyone_on_premises ?? '')} onChange={e => upd('anyone_on_premises', e.target.value)}
              className="w-full px-3 py-2 border border-line rounded-md text-sm">
              <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
            </select>
          </div>
          {String(draft.anyone_on_premises) === '1' && (
            <div className="col-span-2">
              <EditArea draft={draft} upd={upd} k="anyone_on_premises_brief" l="Details in brief" rows={2} />
            </div>
          )}
          <div>
            <label className="block text-xs font-medium text-ink-faint mb-1">Is the premises guarded by a watchman?</label>
            <select value={String(draft.guarded_by_watchman ?? '')} onChange={e => upd('guarded_by_watchman', e.target.value)}
              className="w-full px-3 py-2 border border-line rounded-md text-sm">
              <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
            </select>
          </div>
          <div>
            <label className="block text-xs font-medium text-ink-faint mb-1">Were all means of access properly secured at the time of theft?</label>
            <select value={String(draft.premises_properly_secured ?? '')} onChange={e => upd('premises_properly_secured', e.target.value)}
              className="w-full px-3 py-2 border border-line rounded-md text-sm">
              <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
            </select>
          </div>
          <div className="col-span-2">
            <EditArea draft={draft} upd={upd} k="total_value_contents_of_premises" l="Total value of contents at time of theft" rows={2} />
          </div>
          <div className="col-span-2">
            <EditField draft={draft} upd={upd} k="stock_books_records_located" l="Where were stock books / records located at time of theft?" />
          </div>
        </div>
      ) : isWorkersCompType ? (
        // Workers Compensation / Stated Benefits — V8 parity. Two sections
        // (Injured Person + Accident). address_of_contractor is hidden
        // unless your_direct_employ === '0' (V8 #your_direct_employ_div).
        <div className="space-y-3">
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line">The Injured Person</div>
          <div className="grid grid-cols-2 gap-3">
            <EditField draft={draft} upd={upd} k="injured_name" l="Name" />
            <EditField draft={draft} upd={upd} k="injured_age" l="Age" type="number" />
            <EditField draft={draft} upd={upd} k="injured_occupation" l="Normal Occupation" />
            <EditField draft={draft} upd={upd} k="injured_nationality" l="Nationality" />
            <div>
              <label className="block text-xs font-medium text-ink-faint mb-1">Status</label>
              <select value={String(draft.injured_status ?? '')} onChange={e => upd('injured_status', e.target.value)}
                className="w-full px-3 py-2 border border-line rounded-md text-sm">
                <option value="">—</option><option value="Married">Married</option><option value="Single">Single</option>
              </select>
            </div>
            <EditField draft={draft} upd={upd} k="injured_service_period" l="Period of service" />
            <div>
              <label className="block text-xs font-medium text-ink-faint mb-1">Is he/she in your direct employ?</label>
              <select value={String(draft.your_direct_employ ?? '')} onChange={e => upd('your_direct_employ', e.target.value)}
                className="w-full px-3 py-2 border border-line rounded-md text-sm">
                <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
              </select>
            </div>
            <div className="col-span-2">
              <EditArea draft={draft} upd={upd} k="injured_address" l="Address" rows={2} />
            </div>
            {String(draft.your_direct_employ) === '0' && (
              <div className="col-span-2">
                <EditArea draft={draft} upd={upd} k="address_of_contractor" l="If not, give name and address of Contractor" rows={2} />
              </div>
            )}
          </div>
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">The Accident</div>
          <div className="grid grid-cols-3 gap-3">
            <EditField draft={draft} upd={upd} k="date" l="Date" type="date" />
            <EditField draft={draft} upd={upd} k="time" l="Time" type="time" />
            <EditField draft={draft} upd={upd} k="place" l="Place" />
            <div className="col-span-3">
              <EditField draft={draft} upd={upd} k="how_accident_occur" l="How did the accident occur?" />
            </div>
            <div className="col-span-3">
              <EditField draft={draft} upd={upd} k="first_report_accident" l="When and to whom did he/she first report the accident?" />
            </div>
            <div className="col-span-3">
              <EditField draft={draft} upd={upd} k="period_of_disablement" l="Probable period of disablement in your opinion" />
            </div>
          </div>
        </div>
      ) : isDefectiveType ? (
        // Defective Workmanship — V8 parity. Three sections in V8 blade:
        // Accident/Incident details, Claimant's Vehicle, Incident Details.
        // Yes/No selects for vehicle_drivable + vehicle_handed_claimant.
        <div className="space-y-3">
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line">Details of the Accident / Incident</div>
          <div className="grid grid-cols-2 gap-3">
            <EditField draft={draft} upd={upd} k="location_of_accident" l="Location of accident / incident" />
            <EditField draft={draft} upd={upd} k="accident_date_time" l="Accident Date Time" type="datetime-local" />
            <EditField draft={draft} upd={upd} k="owners_name" l="Owner's Name" />
            <EditField draft={draft} upd={upd} k="telephone_number" l="Telephone Number" />
            <EditField draft={draft} upd={upd} k="mobile_number" l="Mobile Number" />
            <div className="col-span-2">
              <EditArea draft={draft} upd={upd} k="address" l="Address" rows={2} />
            </div>
          </div>
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Claimant's Vehicle</div>
          <div className="grid grid-cols-3 gap-3">
            <EditField draft={draft} upd={upd} k="make" l="Make" />
            <EditField draft={draft} upd={upd} k="model" l="Model" />
            <EditField draft={draft} upd={upd} k="registration" l="Registration" />
            <div className="col-span-3">
              <label className="block text-xs font-medium text-ink-faint mb-1">Is the vehicle drivable?</label>
              <select value={String(draft.vehicle_drivable ?? '')} onChange={e => upd('vehicle_drivable', e.target.value)}
                className="w-full px-3 py-2 border border-line rounded-md text-sm">
                <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
              </select>
            </div>
          </div>
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Incident Details</div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-medium text-ink-faint mb-1">Was the vehicle handed to the claimant?</label>
              <select value={String(draft.vehicle_handed_claimant ?? '')} onChange={e => upd('vehicle_handed_claimant', e.target.value)}
                className="w-full px-3 py-2 border border-line rounded-md text-sm">
                <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
              </select>
            </div>
            <EditField draft={draft} upd={upd} k="when_vehicle_handed" l="When was the vehicle handed?" type="datetime-local" />
            <div className="col-span-2">
              <EditArea draft={draft} upd={upd} k="allegations_received" l="Date and allegations received from claimant" rows={3} />
            </div>
          </div>
        </div>
      ) : isMobileType ? (
        // Mobile/Electronic Devices + Office Contents — V8 parity. Two
        // implicit sections (Insured Contact + Device/Property Loss).
        // sole_owner_of_property is revealed only when
        // is_sole_owner_of_property === '0'.
        <div className="space-y-3">
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line">Insured Contact</div>
          <div className="grid grid-cols-2 gap-3">
            <EditField draft={draft} upd={upd} k="insured_name" l="Insured's Name" />
            <EditField draft={draft} upd={upd} k="email_address" l="E-mail Address" type="email" />
            <EditField draft={draft} upd={upd} k="telephone_no" l="Telephone No" />
            <EditField draft={draft} upd={upd} k="address" l="Address" />
          </div>
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Device / Property Loss</div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-medium text-ink-faint mb-1">Has the property been stolen or damaged?</label>
              <select value={String(draft.property_stolen_damaged ?? '')} onChange={e => upd('property_stolen_damaged', e.target.value)}
                className="w-full px-3 py-2 border border-line rounded-md text-sm">
                <option value="">—</option><option value="1">Damaged</option><option value="0">Stolen</option>
              </select>
            </div>
            <EditField draft={draft} upd={upd} k="date_time_loss_discovered" l="Date and time when loss/damage was discovered" type="datetime-local" />
            <EditField draft={draft} upd={upd} k="whom_discovered" l="By whom discovered?" />
            <div>
              <label className="block text-xs font-medium text-ink-faint mb-1">Are you the sole owner of the property?</label>
              <select value={String(draft.is_sole_owner_of_property ?? '')} onChange={e => upd('is_sole_owner_of_property', e.target.value)}
                className="w-full px-3 py-2 border border-line rounded-md text-sm">
                <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
              </select>
            </div>
            {String(draft.is_sole_owner_of_property) === '0' && (
              <div className="col-span-2">
                <EditField draft={draft} upd={upd} k="sole_owner_of_property" l="Name of the owner" />
              </div>
            )}
          </div>
        </div>
      ) : isFidelityType ? (
        // Fidelity Guarantee — V8 parity. V8 blade has only the multi-row
        // repeater (Name/Position) + Yes/No radio; we use a textarea with
        // one entry per line (matching V2 Create form pattern). The
        // `circumstances` textarea is V2-extended (V8 column exists,
        // blade commented out — V2 surfaces it).
        <div className="grid grid-cols-1 gap-3">
          <EditArea draft={draft} upd={upd} k="defaulting_employees_name" l="Defaulting Employees (one per line as 'Name — Position')" rows={3} />
          <div>
            <label className="block text-xs font-medium text-ink-faint mb-1">Have the employees been involved in or suspected of any previous loss?</label>
            <select value={String(draft.employees_been_involved ?? '')} onChange={e => upd('employees_been_involved', e.target.value)}
              className="w-full px-3 py-2 border border-line rounded-md text-sm">
              <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
            </select>
          </div>
          <EditArea draft={draft} upd={upd} k="circumstances" l="Full details of circumstances of the loss and how it was discovered" rows={4} />
        </div>
      ) : isFireType ? (
        // Fire — V8 parity. fire.blade.php structure with conditional
        // reveal blocks. The contract_of_agreement file is uploaded via
        // a separate path (handled by the section save flow's hasFiles
        // branch + backend's $request->hasFile('contract_of_agreement')).
        <div className="grid grid-cols-2 gap-3">
          <div className="col-span-2">
            <EditArea draft={draft} upd={upd} k="address_of_theft_occurred" l="Address of premises where fire occurred" rows={2} />
          </div>
          <EditField draft={draft} upd={upd} k="property_last_seen" l="When was the property last seen by you?" />
          <EditField draft={draft} upd={upd} k="date_time_of_theft" l="Date and time of fire" type="datetime-local" />
          <div className="col-span-2">
            <EditArea draft={draft} upd={upd} k="brief_description_incident" l="Brief description of incident" rows={3} />
          </div>
          <EditField draft={draft} upd={upd} k="date_time_police_advised" l="Date and time police were advised of loss" type="datetime-local" />
          <EditField draft={draft} upd={upd} k="police_station_name" l="Name of police station" />
          <div>
            <label className="block text-xs font-medium text-ink-faint mb-1">Was anyone at the premises during the loss?</label>
            <select value={String(draft.anyone_during_burglary ?? '')} onChange={e => upd('anyone_during_burglary', e.target.value)}
              className="w-full px-3 py-2 border border-line rounded-md text-sm">
              <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
            </select>
          </div>
          <EditField draft={draft} upd={upd} k="days_premises_unoccupied" l="How many days have the premises been unoccupied in the past 12 months?" />
          {String(draft.anyone_during_burglary) === '1' && (
            <div className="col-span-2">
              <EditArea draft={draft} upd={upd} k="details_during_burglary" l="Details in brief who was in the premises" rows={2} />
            </div>
          )}
          <div>
            <label className="block text-xs font-medium text-ink-faint mb-1">Is the premises guarded by a watchman?</label>
            <select value={String(draft.premises_guarded_by_watchman ?? '')} onChange={e => upd('premises_guarded_by_watchman', e.target.value)}
              className="w-full px-3 py-2 border border-line rounded-md text-sm">
              <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
            </select>
          </div>
          <div>
            <label className="block text-xs font-medium text-ink-faint mb-1">Were all means of access properly secured at time of fire?</label>
            <select value={String(draft.premises_properly_secured ?? '')} onChange={e => upd('premises_properly_secured', e.target.value)}
              className="w-full px-3 py-2 border border-line rounded-md text-sm">
              <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
            </select>
          </div>
          {String(draft.premises_guarded_by_watchman) === '1' && (
            <>
              <EditField draft={draft} upd={upd} k="name_of_guard" l="Name of guard" />
              <EditField draft={draft} upd={upd} k="telephone_of_guard" l="Telephone number of guard" />
              <div className="col-span-2">
                <EditArea draft={draft} upd={upd} k="guard_during_fire" l="Where was the guard during the fire?" rows={2} />
              </div>
            </>
          )}
          <EditField draft={draft} upd={upd} k="name_of_security_agent" l="Name of security agent" />
          <div>
            <label className="block text-xs font-medium text-ink-faint mb-1">Do you suspect any person?</label>
            <select value={String(draft.suspect_any_person ?? '')} onChange={e => upd('suspect_any_person', e.target.value)}
              className="w-full px-3 py-2 border border-line rounded-md text-sm">
              <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
            </select>
          </div>
          {String(draft.suspect_any_person) === '1' && (
            <div className="col-span-2">
              <EditArea draft={draft} upd={upd} k="suspect_person_details" l="Give details of suspect person" rows={2} />
            </div>
          )}
          <EditField draft={draft} upd={upd} k="total_value_premises_buildings" l="Total value of the buildings at time of loss (BWP)" type="number" />
          <div>
            <label className="block text-xs font-medium text-ink-faint mb-1">Any other insurances against fire on the same property?</label>
            <select value={String(draft.other_insurance_against_fire ?? '')} onChange={e => upd('other_insurance_against_fire', e.target.value)}
              className="w-full px-3 py-2 border border-line rounded-md text-sm">
              <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
            </select>
          </div>
          {String(draft.other_insurance_against_fire) === '1' && (
            <div className="col-span-2">
              <EditArea draft={draft} upd={upd} k="insurance_against_fire_details" l="Details of other insurances against fire" rows={2} />
            </div>
          )}
          <EditField draft={draft} upd={upd} k="estimated_amount_of_damaged" l="Estimated amount of the damaged property (BWP)" type="number" />
          <div className="col-span-2">
            <EditArea draft={draft} upd={upd} k="details_of_previous_loss" l="Previous losses in the premises or any other premises owned by you" rows={2} />
          </div>
        </div>
      ) : isPublicLiabilityType ? (
        // Public Liability / Liability — V8 parity. Six sections from
        // V8 public_liability.blade.php. accident_liability /
        // accident_person / accident_contacted are 'YES'/'NO' strings.
        <div className="space-y-3">
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line">Insured's Details</div>
          <div className="grid grid-cols-2 gap-3">
            <EditField draft={draft} upd={upd} k="insured_name" l="Name" />
            <EditField draft={draft} upd={upd} k="insured_treding_name" l="Business or Trading name" />
            <div className="col-span-2">
              <EditField draft={draft} upd={upd} k="insured_postal_address" l="Postal Address" />
            </div>
            <EditField draft={draft} upd={upd} k="insured_email" l="Email address" type="email" />
            <EditField draft={draft} upd={upd} k="insured_telephone_no" l="Telephone no" />
            <EditField draft={draft} upd={upd} k="insured_facsimile" l="Facsimile" />
            <EditField draft={draft} upd={upd} k="insured_mobile_no" l="Mobile no" />
          </div>
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Details of the Accident / Incident</div>
          <div className="grid grid-cols-2 gap-3">
            <EditField draft={draft} upd={upd} k="accident_date" l="Date" type="date" />
            <EditField draft={draft} upd={upd} k="accident_time" l="Time" type="time" />
            <div className="col-span-2">
              <EditArea draft={draft} upd={upd} k="accident_incident" l="Location of accident / incident" rows={2} />
            </div>
            <div className="col-span-2">
              <EditArea draft={draft} upd={upd} k="accident_injuries" l="Details of damaged property and/or injuries suffered" rows={3} />
            </div>
            <div>
              <label className="block text-xs font-medium text-ink-faint mb-1">Have you admitted responsibility / liability?</label>
              <select value={String(draft.accident_liability ?? '')} onChange={e => upd('accident_liability', e.target.value)}
                className="w-full px-3 py-2 border border-line rounded-md text-sm">
                <option value="">—</option><option value="YES">Yes</option><option value="NO">No</option>
              </select>
            </div>
            <div>
              <label className="block text-xs font-medium text-ink-faint mb-1">Product/service-related claim?</label>
              <select value={String(draft.accident_person ?? '')} onChange={e => upd('accident_person', e.target.value)}
                className="w-full px-3 py-2 border border-line rounded-md text-sm">
                <option value="">—</option><option value="YES">Yes</option><option value="NO">No</option>
              </select>
            </div>
            <div className="col-span-2">
              <label className="block text-xs font-medium text-ink-faint mb-1">Were emergency services contacted?</label>
              <select value={String(draft.accident_contacted ?? '')} onChange={e => upd('accident_contacted', e.target.value)}
                className="w-full px-3 py-2 border border-line rounded-md text-sm">
                <option value="">—</option><option value="YES">Yes</option><option value="NO">No</option>
              </select>
            </div>
          </div>
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Job at which the accident occurred</div>
          <div className="grid grid-cols-2 gap-3">
            <div className="col-span-2"><EditField draft={draft} upd={upd} k="attach_contractor" l="Are you the head contractor? If not, who is?" /></div>
            <div className="col-span-2"><EditField draft={draft} upd={upd} k="attach_employee" l="Was anyone other than yourself or employee involved?" /></div>
            <div className="col-span-2"><EditArea draft={draft} upd={upd} k="attach_employed" l="If so, give names, addresses and state by whom employed" rows={2} /></div>
            <div className="col-span-2"><EditField draft={draft} upd={upd} k="attach_blame" l="Do you think you or any of your employee(s) was to blame?" /></div>
            <div className="col-span-2"><EditArea draft={draft} upd={upd} k="attach_circumstances" l="Has any other accident occurred under similar circumstances?" rows={2} /></div>
            <div className="col-span-2"><EditField draft={draft} upd={upd} k="attach_property" l="Was there any damage to property?" /></div>
            <div className="col-span-2"><EditArea draft={draft} upd={upd} k="attach_details" l="If so, please give details" rows={2} /></div>
            <div className="col-span-2"><EditArea draft={draft} upd={upd} k="attach_owner" l="Name and address of property owner" rows={2} /></div>
            <div className="col-span-2"><EditArea draft={draft} upd={upd} k="attach_damage" l="Damage" rows={2} /></div>
          </div>
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Party Making Claim Against You</div>
          <div className="grid grid-cols-2 gap-3">
            <EditField draft={draft} upd={upd} k="claim_name" l="Name" />
            <EditField draft={draft} upd={upd} k="claim_telephone" l="Telephone" />
            <EditField draft={draft} upd={upd} k="claim_mobile" l="Mobile no" />
            <EditField draft={draft} upd={upd} k="claim_postal" l="Postal address" />
            <div className="col-span-2"><EditField draft={draft} upd={upd} k="claim_solicitor" l="Solicitor's name" /></div>
          </div>
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Witness 1</div>
          <div className="grid grid-cols-2 gap-3">
            <EditField draft={draft} upd={upd} k="witness1_name" l="Name" />
            <EditField draft={draft} upd={upd} k="witness1_telephone" l="Telephone no" />
            <EditField draft={draft} upd={upd} k="witness1_mobile" l="Mobile no" />
            <EditField draft={draft} upd={upd} k="witness1_postal" l="Postal address" />
            <div className="col-span-2"><EditField draft={draft} upd={upd} k="witness1_relationship" l="Relationship (employee/family/friend)" /></div>
          </div>
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Witness 2</div>
          <div className="grid grid-cols-2 gap-3">
            <EditField draft={draft} upd={upd} k="witness2_name" l="Name" />
            <EditField draft={draft} upd={upd} k="witness2_telephone" l="Telephone no" />
            <EditField draft={draft} upd={upd} k="witness2_mobile" l="Mobile no" />
            <EditField draft={draft} upd={upd} k="witness2_postal" l="Postal address" />
            <EditField draft={draft} upd={upd} k="witness2_relationship" l="Relationship" />
            <EditField draft={draft} upd={upd} k="witness2_damage" l="Was there any damage to property?" />
          </div>
        </div>
      ) : isEarType ? (
        // Erection All Risk — V8 parity. Five labelled sections; Yes/No
        // selects for the 5 booleans with 3 conditional reveals
        // (responsible_for_damage_details, recovery_details,
        // third_party_liability_details).
        <div className="space-y-3">
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line">A. Details of Insured</div>
          <div className="grid grid-cols-2 gap-3">
            <EditField draft={draft} upd={upd} k="insured_occupation" l="Occupation of the Insured" />
            <EditField draft={draft} upd={upd} k="supervisor_engineer_name" l="Name of Supervisor Engineer" />
            <EditField draft={draft} upd={upd} k="period_from" l="Period of Insurance: From" type="date" />
            <EditField draft={draft} upd={upd} k="period_to" l="Period of Insurance: To" type="date" />
          </div>
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">B. Particulars of Accident</div>
          <div className="grid grid-cols-2 gap-3">
            <EditField draft={draft} upd={upd} k="date_of_occurrence" l="Date of Occurrence" type="date" />
            <EditField draft={draft} upd={upd} k="time_of_occurrence" l="Time of Occurrence" type="time" />
            <div className="col-span-2"><EditArea draft={draft} upd={upd} k="site_of_damage" l="Site where damage occurred" rows={2} /></div>
            <div className="col-span-2"><EditField draft={draft} upd={upd} k="nearest_railway_station" l="Nearest Railway Station" /></div>
          </div>
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Details of the Damage</div>
          <div className="space-y-3">
            <EditArea draft={draft} upd={upd} k="damage_contract_works" l="a) Contract Works" rows={2} />
            <EditArea draft={draft} upd={upd} k="damage_plant_equipment" l="b) Construction Plant & Equipment" rows={2} />
            <EditArea draft={draft} upd={upd} k="damage_third_party_property" l="c) Property belonging to Third Parties" rows={2} />
            <EditArea draft={draft} upd={upd} k="cause_of_damage" l="Cause of Damage" rows={2} />
            <div>
              <label className="block text-xs font-medium text-ink-faint mb-1">Is anyone responsible for the damage?</label>
              <select value={String(draft.responsible_for_damage ?? '')} onChange={e => upd('responsible_for_damage', e.target.value)}
                className="w-full px-3 py-2 border border-line rounded-md text-sm">
                <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
              </select>
            </div>
            {String(draft.responsible_for_damage) === '1' && (
              <EditArea draft={draft} upd={upd} k="responsible_for_damage_details" l="Provide details" rows={2} />
            )}
            <div>
              <label className="block text-xs font-medium text-ink-faint mb-1">Is there any possibility of recovery?</label>
              <select value={String(draft.possibility_of_recovery ?? '')} onChange={e => upd('possibility_of_recovery', e.target.value)}
                className="w-full px-3 py-2 border border-line rounded-md text-sm">
                <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
              </select>
            </div>
            {String(draft.possibility_of_recovery) === '1' && (
              <EditArea draft={draft} upd={upd} k="recovery_details" l="Recovery Details" rows={2} />
            )}
          </div>
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">C. Details of the Damaged Section / Works</div>
          <div className="space-y-3">
            <EditArea draft={draft} upd={upd} k="how_damage_occurred" l="How did the damage occur?" rows={2} />
            <EditArea draft={draft} upd={upd} k="probable_cause" l="What was its probable cause?" rows={2} />
            <EditArea draft={draft} upd={upd} k="progress_of_construction" l="Progress of construction at time of damage" rows={2} />
            <EditArea draft={draft} upd={upd} k="how_items_repaired" l="How will the damaged items be repaired?" rows={2} />
            <div>
              <label className="block text-xs font-medium text-ink-faint mb-1">Will alterations / improvements be made during repairs?</label>
              <select value={String(draft.alterations_during_repairs ?? '')} onChange={e => upd('alterations_during_repairs', e.target.value)}
                className="w-full px-3 py-2 border border-line rounded-md text-sm">
                <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
              </select>
            </div>
            <EditField draft={draft} upd={upd} k="witness_name" l="Name of Witness" />
            <EditArea draft={draft} upd={upd} k="witness_address" l="Address of Witness" rows={2} />
            <div>
              <label className="block text-xs font-medium text-ink-faint mb-1">Are existing buildings / surrounding properties damaged?</label>
              <select value={String(draft.surrounding_properties_damaged ?? '')} onChange={e => upd('surrounding_properties_damaged', e.target.value)}
                className="w-full px-3 py-2 border border-line rounded-md text-sm">
                <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
              </select>
            </div>
            <div>
              <label className="block text-xs font-medium text-ink-faint mb-1">Is Third Party Liability involved?</label>
              <select value={String(draft.third_party_liability ?? '')} onChange={e => upd('third_party_liability', e.target.value)}
                className="w-full px-3 py-2 border border-line rounded-md text-sm">
                <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
              </select>
            </div>
            {String(draft.third_party_liability) === '1' && (
              <EditArea draft={draft} upd={upd} k="third_party_liability_details" l="Third Party Liability Details" rows={2} />
            )}
          </div>
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Estimated Costs for Repair of Damage</div>
          <div className="grid grid-cols-2 gap-3">
            <EditField draft={draft} upd={upd} k="estimated_cost_contract_works" l="a) Contract Works (BWP)" type="number" />
            <EditField draft={draft} upd={upd} k="estimated_cost_plant_machinery" l="b) Construction Plant & Machinery (BWP)" type="number" />
            <EditField draft={draft} upd={upd} k="estimated_cost_third_party_property" l="c) Third Party Property (BWP)" type="number" />
            <EditField draft={draft} upd={upd} k="estimated_cost_owners_surrounding" l="d) Owner's Surrounding Property (BWP)" type="number" />
          </div>
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">D. Other Insurances</div>
          <EditArea draft={draft} upd={upd} k="other_insurance_details" l="Provide details of other Insurance covering the present loss" rows={2} />
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">E. Previous Losses</div>
          <EditArea draft={draft} upd={upd} k="previous_losses_details" l="Provide details of previous Claims, if any, on the project" rows={2} />
        </div>
      ) : isCarplType ? (
        // Contractors All Risks / Public Liability — V8 parity. Four
        // section blocks. Yes/No selects for the two responsible_*_claim
        // booleans. Two file uploads (works_claim_documentary_evidence,
        // works_claim_bill_of_quantities) handled by the section save
        // flow's hasFiles branch + backend's $request->hasFile() picks.
        <div className="space-y-3">
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line">Responsible Person on Site &amp; Contact Numbers</div>
          <div className="grid grid-cols-2 gap-3">
            <EditField draft={draft} upd={upd} k="responsible_person_name" l="Name" />
            <EditField draft={draft} upd={upd} k="responsible_person_phone" l="Phone" />
            <EditField draft={draft} upd={upd} k="responsible_person_cellphone" l="Cellphone" />
            <EditField draft={draft} upd={upd} k="responsible_person_email" l="Email" type="email" />
            <EditField draft={draft} upd={upd} k="responsible_person_fax" l="Fax" />
          </div>
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Contract Details</div>
          <div className="grid grid-cols-2 gap-3">
            <EditField draft={draft} upd={upd} k="parties_to_contract" l="Parties to the Contract" />
            <EditField draft={draft} upd={upd} k="contract_value" l="Contract Value (BWP)" type="number" />
            <EditField draft={draft} upd={upd} k="contract_number" l="Contract Number" />
            <EditField draft={draft} upd={upd} k="code" l="Code" />
            <EditField draft={draft} upd={upd} k="contract_commencement_date" l="Contract Commencement Date" type="date" />
            <EditField draft={draft} upd={upd} k="expected_contract_completion_date" l="Expected Contract Completion Date" type="date" />
            <div className="col-span-2"><EditArea draft={draft} upd={upd} k="description_of_contract" l="Description of Contract" rows={2} /></div>
            <div className="col-span-2"><EditArea draft={draft} upd={upd} k="site_physical_address" l="Site Physical Address" rows={2} /></div>
          </div>
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Insurance Responsibility</div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-medium text-ink-faint mb-1">Responsible for arranging Project Insurance (Contract Works)?</label>
              <select value={String(draft.responsible_contract_works_claim ?? '')} onChange={e => upd('responsible_contract_works_claim', e.target.value)}
                className="w-full px-3 py-2 border border-line rounded-md text-sm">
                <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
              </select>
            </div>
            <div>
              <label className="block text-xs font-medium text-ink-faint mb-1">Responsible for arranging Public Liability Insurance?</label>
              <select value={String(draft.responsible_public_liability_claim ?? '')} onChange={e => upd('responsible_public_liability_claim', e.target.value)}
                className="w-full px-3 py-2 border border-line rounded-md text-sm">
                <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
              </select>
            </div>
          </div>
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Loss / Damage Details</div>
          <div className="grid grid-cols-2 gap-3">
            <EditField draft={draft} upd={upd} k="loss_date" l="Date of Loss / Damage" type="date" />
            <EditField draft={draft} upd={upd} k="loss_time" l="Time" type="time" />
            <div className="col-span-2"><EditArea draft={draft} upd={upd} k="loss_details" l="Details of Loss / Damage" rows={3} /></div>
            <div className="col-span-2"><EditArea draft={draft} upd={upd} k="cause_of_loss" l="Cause of Loss / Damage" rows={2} /></div>
            <EditField draft={draft} upd={upd} k="party_responsible_name" l="Party Responsible — Name" />
            <EditField draft={draft} upd={upd} k="party_responsible_contact" l="Party Responsible — Contact" />
            <div className="col-span-2"><EditField draft={draft} upd={upd} k="estimated_cost_of_repair_replacement" l="Estimated Cost of Repair / Replacement (BWP)" type="number" /></div>
            <EditField draft={draft} upd={upd} k="police_station" l="Police Station (theft only)" />
            <EditField draft={draft} upd={upd} k="police_reference" l="Reference (theft only)" />
          </div>
        </div>
      ) : isPropertyLossType ? (
        // Property Loss / Damage — V8 parity. 16 free-text fields organized
        // into V8's section groupings (Loss occurrence / place / cause /
        // previous loss / police / other interest / other insurance / value).
        <div className="space-y-3">
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line">Loss / Damage Occurrence</div>
          <EditArea draft={draft} upd={upd} k="loss_damage_discovered" l="When was loss/damage discovered?" rows={2} />
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Loss / Damage Place</div>
          <EditArea draft={draft} upd={upd} k="loss_damage_occurred" l="Place where loss/damage occurred" rows={2} />
          <EditArea draft={draft} upd={upd} k="premises_occupied" l="Were premises occupied? By whom?" rows={2} />
          <EditArea draft={draft} upd={upd} k="last_occupied" l="If not occupied, when last occupied?" rows={2} />
          <EditArea draft={draft} upd={upd} k="purpose_of_occupation" l="Purpose of occupation" rows={2} />
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Cause of Loss / Damage</div>
          <EditArea draft={draft} upd={upd} k="nature_interruption" l="Nature of your interruption" rows={2} />
          <EditArea draft={draft} upd={upd} k="loss_for_each_item" l="Details & estimated amount of loss for each item to be claimed" rows={3} />
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Previous Loss / Damage</div>
          <EditArea draft={draft} upd={upd} k="previously_suffered_loss" l="Have you previously suffered loss/damage?" rows={2} />
          <EditArea draft={draft} upd={upd} k="give_details" l="If so, give details" rows={2} />
          <EditArea draft={draft} upd={upd} k="name_of_insurer" l="If insured, provide name of insurer" rows={1} />
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Police</div>
          <EditArea draft={draft} upd={upd} k="reference_no_station" l="Police reference number, station and date reported" rows={2} />
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Other Interest</div>
          <EditArea draft={draft} upd={upd} k="interest_insured_property" l="Any other party with an interest in the insured property?" rows={2} />
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Other Insurance</div>
          <EditArea draft={draft} upd={upd} k="other_insurance_covering" l="Any other insurance covering this loss/damage?" rows={2} />
          <EditArea draft={draft} upd={upd} k="give_name_insurer" l="If so, give name of insurer" rows={1} />
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Value</div>
          <EditArea draft={draft} upd={upd} k="value_all_property" l="Estimated total value of all property insured" rows={1} />
          <EditArea draft={draft} upd={upd} k="when_last_valued" l="When last valued?" rows={1} />
        </div>
      ) : isTravelInsuranceType ? (
        // Travel Insurance — V8 parity. Six visible sections + conditional
        // refund-doc sections gated on draft.type_of_refund. File inputs
        // render a "View Uploaded Document" link when the path is present
        // on the sub-claim (via the *_url helpers backend show() attaches);
        // selecting a replacement file uploads + replaces the path.
        (() => {
          const refund = String(draft.type_of_refund ?? '')
          const FileRow = ({ k, l }: { k: string; l: string }) => {
            const existingPath = draft[k]
            const existingUrl = draft[`${k}_url`]
            const picked = files[k]
            return (
              <div>
                <label className="block text-xs font-medium text-ink-faint mb-1">{l}</label>
                {existingPath && existingUrl && !picked && (
                  <a href={existingUrl} target="_blank" rel="noopener noreferrer"
                     className="inline-block mb-2 text-xs text-primary hover:underline break-all">
                    View Uploaded Document
                  </a>
                )}
                <input type="file"
                       onChange={e => setFile(k, e.target.files?.[0] || null)}
                       className="w-full text-sm" />
                {picked && <p className="mt-1 text-xs text-status-success-fg truncate">{picked.name}</p>}
              </div>
            )
          }
          return (
            <div className="space-y-3">
              <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line">Claimant Details</div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-ink-faint mb-1">Title</label>
                  <select value={String(draft.title ?? '')} onChange={e => upd('title', e.target.value)}
                          className="w-full px-3 py-2 border border-line rounded-md text-sm">
                    <option value="">—</option>
                    <option value="Mr">Mr</option>
                    <option value="Mrs">Mrs</option>
                    <option value="Miss">Miss</option>
                    <option value="Ms">Ms</option>
                    <option value="Other">Other</option>
                  </select>
                </div>
                {String(draft.title) === 'Other' && <EditField draft={draft} upd={upd} k="other_title" l="Other title" />}
                <EditField draft={draft} upd={upd} k="surname" l="Surname" />
                <EditField draft={draft} upd={upd} k="forename" l="Forename(s)" />
                <EditField draft={draft} upd={upd} k="dob" l="Date of Birth" type="date" />
                <EditField draft={draft} upd={upd} k="passport_no" l="Passport No" />
                <EditField draft={draft} upd={upd} k="nationality" l="Nationality" />
                <EditField draft={draft} upd={upd} k="telephone" l="Telephone" />
                <EditField draft={draft} upd={upd} k="post_code" l="Post Code" />
                <EditField draft={draft} upd={upd} k="mobile" l="Mobile" />
                <EditField draft={draft} upd={upd} k="email" l="Email" type="email" />
                <div className="col-span-2"><EditArea draft={draft} upd={upd} k="home_address" l="Home Address" rows={2} /></div>
              </div>
              <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Travel Insurance Policy and Journey Details</div>
              <div className="grid grid-cols-2 gap-3">
                <EditField draft={draft} upd={upd} k="policy_number" l="Policy Number" />
                <EditField draft={draft} upd={upd} k="issued_by" l="Issued by (Insurance Company)" />
                <EditField draft={draft} upd={upd} k="issued_on" l="Issued on" />
                <EditField draft={draft} upd={upd} k="valid_from" l="Valid from" type="date" />
                <EditField draft={draft} upd={upd} k="valid_to" l="Valid to" type="date" />
              </div>
              <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Bank Details (for Claim Reimbursement Purposes only)</div>
              <div className="grid grid-cols-2 gap-3">
                <EditField draft={draft} upd={upd} k="beneficiary" l="Beneficiary (if different from insured)" />
                <EditField draft={draft} upd={upd} k="bank_name" l="Bank Name" />
                <EditField draft={draft} upd={upd} k="bank_address" l="Bank Address" />
                <EditField draft={draft} upd={upd} k="account_number" l="Account Number" />
                <EditField draft={draft} upd={upd} k="iban" l="IBAN" />
                <EditField draft={draft} upd={upd} k="swift_code" l="SWIFT Code" />
                <EditField draft={draft} upd={upd} k="bic_code" l="BIC Code" />
              </div>
              <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Do you have any other Insurance Policy?</div>
              <div>
                <label className="block text-xs font-medium text-ink-faint mb-1">Other Insurance Policy</label>
                <select value={String(draft.other_insurance_policy ?? '')} onChange={e => upd('other_insurance_policy', e.target.value)}
                        className="w-full px-3 py-2 border border-line rounded-md text-sm">
                  <option value="">—</option>
                  <option value="No">No</option>
                  <option value="Yes">Yes</option>
                </select>
              </div>
              {String(draft.other_insurance_policy) === 'Yes' && (
                <div className="grid grid-cols-2 gap-3">
                  <EditField draft={draft} upd={upd} k="name_insurance_company" l="Name of the Insurance Company" />
                  <EditField draft={draft} upd={upd} k="phone_number" l="Phone Number" />
                  <div className="col-span-2"><EditArea draft={draft} upd={upd} k="address" l="Address" rows={2} /></div>
                </div>
              )}
              <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Compulsory Documentation</div>
              <FileRow k="compulsory_doc_proof_of_residence" l="Proof of residence in the Country where the Policy was issued" />
              <FileRow k="compulsory_doc_claim_form"          l="Claim form duly completed" />
              <FileRow k="compulsory_doc_insurance_policy"    l="Copy of Insurance Policy" />
              <FileRow k="compulsory_doc_detailed_letter"     l="Detailed letter explaining the loss" />
              <FileRow k="compulsory_doc_receipts"            l="ORIGINAL official Receipts of ALL incurred costs" />
              <FileRow k="compulsory_doc_passport_copy"       l="Copy of insured's passport (FIRST page + exit/entry dates)" />
              <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Type of Refund</div>
              <div>
                <label className="block text-xs font-medium text-ink-faint mb-1">Type of Refund</label>
                <select value={refund} onChange={e => upd('type_of_refund', e.target.value)}
                        className="w-full px-3 py-2 border border-line rounded-md text-sm">
                  <option value="">Select Type of Refund</option>
                  <option value="Medical Expenses">Medical Expenses</option>
                  <option value="Emergency Dental Care">Emergency Dental Care</option>
                  <option value="Delayed Luggage">Delayed Luggage</option>
                  <option value="Loss of Luggage">Loss of Luggage</option>
                  <option value="Flight Delay">Flight Delay</option>
                  <option value="Delayed Departure">Delayed Departure</option>
                  <option value="Loss of Personal Documents">Loss of Personal Documents</option>
                  <option value="Trip Cancellation">Trip Cancellation</option>
                  <option value="Curtailment">Curtailment</option>
                </select>
              </div>
              {(refund === 'Medical Expenses' || refund === 'Emergency Dental Care') && (
                <>
                  <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Medical Expenses / Emergency Dental Care — Documents</div>
                  <FileRow k="medical_dental_care_doc_1" l="Medical report with admission medical clinic" />
                  <FileRow k="medical_dental_care_doc_2" l="Clinical and/or Laboratory Results" />
                  <FileRow k="medical_dental_care_doc_3" l="Bank Account Information" />
                </>
              )}
              {refund === 'Delayed Luggage' && (
                <>
                  <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Delayed Luggage — Documents</div>
                  <FileRow k="claim_delayed_luggage_doc_1" l="Property Irregularity Report issued by the Carrier" />
                  <FileRow k="claim_delayed_luggage_doc_2" l="Incident Report from Client" />
                  <FileRow k="claim_delayed_luggage_doc_3" l="Original receipts for basic necessity items bought" />
                </>
              )}
              {refund === 'Loss of Personal Documents' && (
                <>
                  <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Loss of Personal Documents — Documents</div>
                  <FileRow k="claim_loss_personal_doc_doc_1" l="Statement of Loss (Police report)" />
                  <FileRow k="claim_loss_personal_doc_doc_2" l="Receipts of document replacement incurred costs" />
                </>
              )}
              {refund === 'Loss of Luggage' && (
                <>
                  <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Lost Luggage — Documents</div>
                  <FileRow k="claim_lost_luggage_doc_1" l="Property Irregularity Report issued by the Carrier" />
                  <FileRow k="claim_lost_luggage_doc_2" l="Certificate of lost luggage issued by the Carrier" />
                  <FileRow k="claim_lost_luggage_doc_3" l="Copy of the Carrier settlement/reimbursement form" />
                  <FileRow k="claim_lost_luggage_doc_4" l="Incident Report from Client" />
                </>
              )}
              {(refund === 'Trip Cancellation' || refund === 'Curtailment') && (
                <>
                  <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Trip Cancellation / Curtailment — Documents</div>
                  <FileRow k="claim_trip_cancel_doc_1" l="List of the services hired for the trip" />
                  <FileRow k="claim_trip_cancel_doc_2" l="Conditions and proof of cancellation of the said services" />
                  <FileRow k="claim_trip_cancel_doc_3" l="Certificate of non-refundable costs" />
                  <FileRow k="claim_trip_cancel_doc_4" l="The payment receipts of the hired services for the trip" />
                </>
              )}
              {(refund === 'Flight Delay' || refund === 'Delayed Departure') && (
                <>
                  <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Delayed Flight — Documents</div>
                  <FileRow k="claim_delayed_flight_doc_1" l="Certificate Issued by the Carrier" />
                  <FileRow k="claim_delayed_flight_doc_2" l="Copy of original travel ticket" />
                  <FileRow k="claim_delayed_flight_doc_3" l="Copy of replacement ticket indicating the paid amount" />
                </>
              )}
            </div>
          )
        })()
      ) : isPiType ? (
        // Professional Indemnity — V8 parity. Five sections; Yes/No
        // selects for the 6 booleans with conditional follow-up fields
        // (contract_copy file or contract_no_details, demand date,
        // summons date, attorney_details, investigation_findings file).
        (() => {
          const PiFileRow = ({ k, l }: { k: string; l: string }) => {
            const existingPath = draft[k]
            const existingUrl = draft[`${k}_url`]
            const picked = files[k]
            return (
              <div>
                <label className="block text-xs font-medium text-ink-faint mb-1">{l}</label>
                {existingPath && existingUrl && !picked && (
                  <a href={existingUrl} target="_blank" rel="noopener noreferrer"
                     className="inline-block mb-2 text-xs text-primary hover:underline break-all">
                    View Uploaded Document
                  </a>
                )}
                <input type="file"
                       onChange={e => setFile(k, e.target.files?.[0] || null)}
                       className="w-full text-sm" />
                {picked && <p className="mt-1 text-xs text-status-success-fg truncate">{picked.name}</p>}
              </div>
            )
          }
          return (
            <div className="space-y-3">
              <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line">Insured's Details</div>
              <div className="grid grid-cols-2 gap-3">
                <EditField draft={draft} upd={upd} k="type_of_business" l="Type of Business" />
                <EditField draft={draft} upd={upd} k="contact_person" l="Contact Person" />
                <EditField draft={draft} upd={upd} k="designation" l="Designation" />
                <EditField draft={draft} upd={upd} k="insured_email" l="E-mail Address" type="email" />
                <EditField draft={draft} upd={upd} k="insured_cell_tel" l="Cell / Tel Number" />
              </div>
              <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Claimant / Potential Claimant Details</div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-ink-faint mb-1">Claimant type</label>
                  <select value={String(draft.claimant_type ?? '')} onChange={e => upd('claimant_type', e.target.value)}
                          className="w-full px-3 py-2 border border-line rounded-md text-sm">
                    <option value="">—</option>
                    <option value="Business">Business</option>
                    <option value="Individual">Individual</option>
                  </select>
                </div>
                <EditField draft={draft} upd={upd} k="claimant_name_surname" l="Name & Surname" />
                <EditField draft={draft} upd={upd} k="claimant_email" l="E-mail Address" type="email" />
                <EditField draft={draft} upd={upd} k="claimant_cell_tel" l="Cell / Tel Number" />
              </div>
              <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Details of Contract and Claim</div>
              <EditArea draft={draft} upd={upd} k="insured_retained_to_do" l="What was the insured retained/contracted to do?" rows={3} />
              <div>
                <label className="block text-xs font-medium text-ink-faint mb-1">Was there a contract in place?</label>
                <select value={String(draft.contract_in_place ?? '')} onChange={e => upd('contract_in_place', e.target.value)}
                        className="w-full px-3 py-2 border border-line rounded-md text-sm">
                  <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                </select>
              </div>
              {String(draft.contract_in_place) === '1' && <PiFileRow k="contract_copy" l="Attach copy of contract" />}
              {String(draft.contract_in_place) === '0' && <EditArea draft={draft} upd={upd} k="contract_no_details" l="Provide details" rows={2} />}
              <div className="grid grid-cols-2 gap-3">
                <EditField draft={draft} upd={upd} k="work_performed_date" l="When was the work performed?" type="date" />
                <EditField draft={draft} upd={upd} k="person_performed_work" l="Person who performed the work" />
              </div>
              <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Circumstances</div>
              <EditArea draft={draft} upd={upd} k="circumstances" l="Circumstances giving rise to the claim (allegations of negligence)" rows={4} />
              <EditField draft={draft} upd={upd} k="first_aware_date" l="When did the insured first become aware of the claim/circumstance?" type="date" />
              <EditArea draft={draft} upd={upd} k="reason_for_reporting" l="Reason for reporting the incident" rows={2} />
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-ink-faint mb-1">Reported for notification purposes only?</label>
                  <select value={String(draft.notification_purposes_only ?? '')} onChange={e => upd('notification_purposes_only', e.target.value)}
                          className="w-full px-3 py-2 border border-line rounded-md text-sm">
                    <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium text-ink-faint mb-1">Received a verbal/written demand for compensation?</label>
                  <select value={String(draft.verbal_written_demand ?? '')} onChange={e => upd('verbal_written_demand', e.target.value)}
                          className="w-full px-3 py-2 border border-line rounded-md text-sm">
                    <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                  </select>
                </div>
                {String(draft.verbal_written_demand) === '1' && (
                  <EditField draft={draft} upd={upd} k="demand_received_date" l="Date demand received" type="date" />
                )}
                <div>
                  <label className="block text-xs font-medium text-ink-faint mb-1">Has the insured been served with a Summons?</label>
                  <select value={String(draft.served_with_summons ?? '')} onChange={e => upd('served_with_summons', e.target.value)}
                          className="w-full px-3 py-2 border border-line rounded-md text-sm">
                    <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                  </select>
                </div>
                {String(draft.served_with_summons) === '1' && (
                  <EditField draft={draft} upd={upd} k="summons_served_date" l="Date Summons served" type="date" />
                )}
                <div>
                  <label className="block text-xs font-medium text-ink-faint mb-1">Has the insured appointed an Attorney/Loss Adjustor?</label>
                  <select value={String(draft.attorney_appointed ?? '')} onChange={e => upd('attorney_appointed', e.target.value)}
                          className="w-full px-3 py-2 border border-line rounded-md text-sm">
                    <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                  </select>
                </div>
                <EditField draft={draft} upd={upd} k="amount_claimed" l="Amount claimed (BWP)" type="number" />
              </div>
              {String(draft.attorney_appointed) === '1' && (
                <EditArea draft={draft} upd={upd} k="attorney_details" l="Provide details" rows={2} />
              )}
              <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Insured's Investigation</div>
              <div>
                <label className="block text-xs font-medium text-ink-faint mb-1">Has the insured conducted their own investigation?</label>
                <select value={String(draft.own_investigation ?? '')} onChange={e => upd('own_investigation', e.target.value)}
                        className="w-full px-3 py-2 border border-line rounded-md text-sm">
                  <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                </select>
              </div>
              {String(draft.own_investigation) === '1' && (
                <PiFileRow k="investigation_findings" l="Attach findings" />
              )}
              <EditArea draft={draft} upd={upd} k="views_on_liability" l="Insured's views/comments on Liability" rows={3} />
              <EditArea draft={draft} upd={upd} k="views_on_amount_claimed" l="Insured's views/comments on Amount Claimed" rows={3} />
              <EditArea draft={draft} upd={upd} k="additional_details" l="Additional details to notify insurer" rows={3} />
            </div>
          )
        })()
      ) : isParType ? (
        // Plant All Risks — V8 parity. Four sections. Three Yes/No
        // selects in the Loss / Damage Details section.
        <div className="space-y-3">
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line">Responsible Person on Site &amp; Contact Numbers</div>
          <div className="grid grid-cols-2 gap-3">
            <EditField draft={draft} upd={upd} k="responsible_person_name" l="Name" />
            <EditField draft={draft} upd={upd} k="responsible_person_phone" l="Phone" />
            <EditField draft={draft} upd={upd} k="responsible_person_cellphone" l="Cellphone" />
            <EditField draft={draft} upd={upd} k="responsible_person_email" l="Email" type="email" />
            <EditField draft={draft} upd={upd} k="responsible_person_fax" l="Fax" />
          </div>
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Site Details</div>
          <EditArea draft={draft} upd={upd} k="site_physical_address" l="Site Physical Address" rows={2} />
          <EditField draft={draft} upd={upd} k="site_code" l="Code" />
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Plant Details</div>
          <EditArea draft={draft} upd={upd} k="item_description" l="Item of Plant Stolen/Damaged (full description / model / serial number)" rows={3} />
          <EditField draft={draft} upd={upd} k="item_number_sum_insured" l="Item Number on Policy Schedule / Sum Insured" />
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Loss / Damage Details</div>
          <div className="grid grid-cols-2 gap-3">
            <EditField draft={draft} upd={upd} k="date_of_loss" l="Date of Loss / Damage" type="date" />
            <EditField draft={draft} upd={upd} k="time_of_loss" l="Time" type="time" />
            <div className="col-span-2"><EditArea draft={draft} upd={upd} k="details_of_loss" l="Details of Loss / Damage" rows={3} /></div>
            <div className="col-span-2"><EditArea draft={draft} upd={upd} k="cause_of_loss" l="Cause of Loss / Damage (e.g. Act of God / Operator Error)" rows={2} /></div>
            <EditField draft={draft} upd={upd} k="party_responsible_name" l="Party Responsible — Name" />
            <EditField draft={draft} upd={upd} k="party_responsible_contact" l="Party Responsible — Contact" />
            <div className="col-span-2"><EditField draft={draft} upd={upd} k="estimated_cost" l="Estimated Cost of Repair / Replacement (BWP)" type="number" /></div>
            <div>
              <label className="block text-xs font-medium text-ink-faint mb-1">Is the unit uneconomical to repair / write off?</label>
              <select value={String(draft.uneconomical_to_repair ?? '')} onChange={e => upd('uneconomical_to_repair', e.target.value)}
                      className="w-full px-3 py-2 border border-line rounded-md text-sm">
                <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
              </select>
            </div>
            <div>
              <label className="block text-xs font-medium text-ink-faint mb-1">Is the unit subject to Finance / Hire Purchase?</label>
              <select value={String(draft.subject_to_finance ?? '')} onChange={e => upd('subject_to_finance', e.target.value)}
                      className="w-full px-3 py-2 border border-line rounded-md text-sm">
                <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
              </select>
            </div>
            <div className="col-span-2">
              <label className="block text-xs font-medium text-ink-faint mb-1">Was the unit on hire at time of accident / theft?</label>
              <select value={String(draft.on_hire_at_time ?? '')} onChange={e => upd('on_hire_at_time', e.target.value)}
                      className="w-full px-3 py-2 border border-line rounded-md text-sm">
                <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
              </select>
            </div>
            <EditField draft={draft} upd={upd} k="police_station" l="Police Station (Theft claims only)" />
            <EditField draft={draft} upd={upd} k="police_reference" l="Reference (Theft claims only)" />
          </div>
        </div>
      ) : isMbType ? (
        // Machinery Breakdown — form AD-CLM-MB-001. Six sections, six Yes/No
        // selects each followed by a Details textarea.
        <div className="space-y-3">
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line">Policy Details</div>
          <div className="grid grid-cols-2 gap-3">
            <EditField draft={draft} upd={upd} k="insured" l="Insured" />
            <EditField draft={draft} upd={upd} k="period_of_insurance" l="Period of Insurance" />
            <EditField draft={draft} upd={upd} k="sum_insured" l="Sum Insured (BWP)" type="number" />
          </div>
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Insured Contact &amp; Business</div>
          <div className="grid grid-cols-2 gap-3">
            <EditField draft={draft} upd={upd} k="contact_person" l="Contact Person" />
            <EditField draft={draft} upd={upd} k="designation" l="Designation" />
            <EditField draft={draft} upd={upd} k="phone" l="Phone" />
            <EditField draft={draft} upd={upd} k="cellphone" l="Cellphone" />
            <EditField draft={draft} upd={upd} k="email" l="Email" type="email" />
            <EditField draft={draft} upd={upd} k="years_in_operation" l="Years in Operation" />
          </div>
          <EditArea draft={draft} upd={upd} k="postal_physical_address" l="Postal / Physical Address" rows={2} />
          <EditArea draft={draft} upd={upd} k="nature_of_business" l="Nature of Business / Industry" rows={2} />
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Damaged Machinery</div>
          <EditArea draft={draft} upd={upd} k="item_description" l="Description of Item per Policy Schedule" rows={2} />
          <div className="grid grid-cols-2 gap-3">
            <EditField draft={draft} upd={upd} k="make_model" l="Make / Model" />
            <EditField draft={draft} upd={upd} k="serial_number" l="Serial Number" />
            <EditField draft={draft} upd={upd} k="year_of_manufacture" l="Year of Manufacture" />
            <EditField draft={draft} upd={upd} k="date_commissioned" l="Date Originally Commissioned" type="date" />
            <EditField draft={draft} upd={upd} k="current_replacement_value" l="Current Replacement Value (BWP)" type="number" />
          </div>
          <EditArea draft={draft} upd={upd} k="technical_specs" l="Technical Specs (kW, RPM, voltage, capacity)" rows={2} />
          <div>
            <label className="block text-xs font-medium text-ink-faint mb-1">Under Manufacturer / AMC Contract at date of loss?</label>
            <select value={String(draft.under_amc_contract ?? '')} onChange={e => upd('under_amc_contract', e.target.value)}
                    className="w-full px-3 py-2 border border-line rounded-md text-sm">
              <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
            </select>
          </div>
          <EditArea draft={draft} upd={upd} k="amc_contract_details" l="AMC Contract — Details" rows={2} />
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Loss / Damage Details</div>
          <div className="grid grid-cols-3 gap-3">
            <EditField draft={draft} upd={upd} k="date_of_loss" l="Date of Loss" type="date" />
            <EditField draft={draft} upd={upd} k="time_of_loss" l="Time of Loss" type="time" />
            <EditField draft={draft} upd={upd} k="date_loss_discovered" l="Date Loss Discovered" type="date" />
          </div>
          <EditArea draft={draft} upd={upd} k="site_location" l="Site / Location of Loss (full physical address or GPS)" rows={2} />
          <EditField draft={draft} upd={upd} k="equipment_status" l="Equipment status at time of loss (working / idle / cleaning / overhaul / relocation)" />
          <EditArea draft={draft} upd={upd} k="cause_of_loss" l="Cause of Loss (mechanical, electrical, operator error, foreign body, short circuit, etc.)" rows={2} />
          <EditArea draft={draft} upd={upd} k="damage_description" l="Description of Damage (which parts, extent of damage)" rows={3} />
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Repair, Replacement &amp; Salvage</div>
          <EditField draft={draft} upd={upd} k="estimated_cost" l="Estimated Cost of Repair / Replacement (BWP)" type="number" />
          <EditArea draft={draft} upd={upd} k="proposed_repairer" l="Proposed Repairer / OEM Service Centre (Name, Address, Contact)" rows={2} />
          <EditArea draft={draft} upd={upd} k="salvage_location" l="Salvage Location — where will the damaged item be physically held?" rows={2} />
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Financier, Recovery &amp; Other Insurance</div>
          <div>
            <label className="block text-xs font-medium text-ink-faint mb-1">Sole owner of the damaged machinery?</label>
            <select value={String(draft.sole_owner ?? '')} onChange={e => upd('sole_owner', e.target.value)}
                    className="w-full px-3 py-2 border border-line rounded-md text-sm">
              <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
            </select>
          </div>
          <EditArea draft={draft} upd={upd} k="sole_owner_details" l="Sole owner — Details" rows={2} />
          <EditArea draft={draft} upd={upd} k="co_owner_financier" l="If no — co-owner / lessor / hire-purchase financier name" rows={2} />
          <div>
            <label className="block text-xs font-medium text-ink-faint mb-1">Subject to bank loan, lease, hire-purchase or notarial bond?</label>
            <select value={String(draft.subject_to_finance ?? '')} onChange={e => upd('subject_to_finance', e.target.value)}
                    className="w-full px-3 py-2 border border-line rounded-md text-sm">
              <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
            </select>
          </div>
          <EditArea draft={draft} upd={upd} k="finance_details" l="Finance — Details" rows={2} />
          <EditArea draft={draft} upd={upd} k="financier_bank_reference" l="Financier / Bank name and account / agreement reference" rows={2} />
          <div>
            <label className="block text-xs font-medium text-ink-faint mb-1">Third party (supplier / contractor / repairer) responsible for the loss?</label>
            <select value={String(draft.third_party_responsible ?? '')} onChange={e => upd('third_party_responsible', e.target.value)}
                    className="w-full px-3 py-2 border border-line rounded-md text-sm">
              <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
            </select>
          </div>
          <EditArea draft={draft} upd={upd} k="third_party_details" l="Third party responsible — Details" rows={2} />
          <EditField draft={draft} upd={upd} k="third_party_name_contact" l="Third Party Name & Contact" />
          <div>
            <label className="block text-xs font-medium text-ink-faint mb-1">Recovery claim lodged with third party?</label>
            <select value={String(draft.recovery_claim_lodged ?? '')} onChange={e => upd('recovery_claim_lodged', e.target.value)}
                    className="w-full px-3 py-2 border border-line rounded-md text-sm">
              <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
            </select>
          </div>
          <EditArea draft={draft} upd={upd} k="recovery_details" l="Recovery claim — Details" rows={2} />
          <div>
            <label className="block text-xs font-medium text-ink-faint mb-1">Machinery insured under any other policy?</label>
            <select value={String(draft.other_insurance ?? '')} onChange={e => upd('other_insurance', e.target.value)}
                    className="w-full px-3 py-2 border border-line rounded-md text-sm">
              <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
            </select>
          </div>
          <EditArea draft={draft} upd={upd} k="other_insurance_details" l="Other insurance — Details" rows={2} />
          <EditArea draft={draft} upd={upd} k="other_insurer_policy" l="Other Insurer / Policy Number / Sum Insured" rows={2} />
          <EditArea draft={draft} upd={upd} k="loss_history" l="Loss History — incidents involving this or similar machinery in the past 3 years (claimed or not)" rows={3} />
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Prevention &amp; Declaration</div>
          <EditArea draft={draft} upd={upd} k="procedural_improvements" l="Procedural improvements proposed or implemented to prevent recurrence" rows={3} />
          <div className="grid grid-cols-3 gap-3">
            <EditField draft={draft} upd={upd} k="declaration_name" l="Name" />
            <EditField draft={draft} upd={upd} k="declaration_capacity" l="Capacity" />
            <EditField draft={draft} upd={upd} k="declaration_date" l="Date" type="date" />
          </div>
        </div>
      ) : isMbLopType ? (
        // Machinery Breakdown — Loss of Profit. Eight sections; five Yes/No
        // selects each followed by a details textarea.
        <div className="space-y-3">
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line">Linked Machinery Breakdown Claim</div>
          <div className="grid grid-cols-3 gap-3">
            <EditField draft={draft} upd={upd} k="mb_claim_number" l="Machinery Breakdown Claim Number" />
            <EditField draft={draft} upd={upd} k="date_of_breakdown" l="Date of Breakdown / Damage" type="date" />
            <EditField draft={draft} upd={upd} k="mb_physical_claim_status" l="Status of MB Physical Damage Claim" />
          </div>
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Insured Contact &amp; Business</div>
          <div className="grid grid-cols-2 gap-3">
            <EditField draft={draft} upd={upd} k="insured" l="Insured (Registered Name)" />
            <EditField draft={draft} upd={upd} k="contact_person" l="Contact Person" />
            <EditField draft={draft} upd={upd} k="designation" l="Designation" />
            <EditField draft={draft} upd={upd} k="phone_cellphone" l="Phone / Cellphone" />
            <EditField draft={draft} upd={upd} k="email" l="Email" type="email" />
            <EditField draft={draft} upd={upd} k="years_in_operation" l="Years in Operation" />
          </div>
          <EditArea draft={draft} upd={upd} k="postal_physical_address" l="Postal / Physical Address" rows={2} />
          <EditArea draft={draft} upd={upd} k="site_premises_affected" l="Site / Premises affected" rows={2} />
          <EditArea draft={draft} upd={upd} k="nature_of_business" l="Nature of Business and main products / services" rows={2} />
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Operating Profile (Pre-Loss Baseline)</div>
          <div className="grid grid-cols-2 gap-3">
            <EditField draft={draft} upd={upd} k="production_capacity" l="Production capacity (units / hour or day)" />
            <EditField draft={draft} upd={upd} k="operating_hours_per_day" l="Operating hours per day" />
            <EditField draft={draft} upd={upd} k="number_of_shifts" l="Number of shifts (1 / 2 / 3)" />
            <EditField draft={draft} upd={upd} k="operating_days_per_week" l="Operating days per week" />
            <EditField draft={draft} upd={upd} k="standard_turnover_prior_12m" l="Standard turnover prior 12 months (BWP)" type="number" />
            <EditField draft={draft} upd={upd} k="standard_output_prior_12m" l="Standard output prior 12 months (units)" />
          </div>
          <div>
            <label className="block text-xs font-medium text-ink-faint mb-1">Is the business seasonal?</label>
            <select value={String(draft.is_seasonal ?? '')} onChange={e => upd('is_seasonal', e.target.value)}
                    className="w-full px-3 py-2 border border-line rounded-md text-sm">
              <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
            </select>
          </div>
          <EditArea draft={draft} upd={upd} k="seasonal_details" l="Seasonal — Details" rows={2} />
          <EditArea draft={draft} upd={upd} k="peak_months_pattern" l="Peak months / seasonal pattern" rows={2} />
          <div className="grid grid-cols-2 gap-3">
            <EditField draft={draft} upd={upd} k="comparable_period_turnover" l="Comparable period turnover — prior year (BWP)" type="number" />
            <EditField draft={draft} upd={upd} k="comparable_period_output" l="Comparable period output — prior year (units)" />
          </div>
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Indemnity Period &amp; Downtime</div>
          <EditArea draft={draft} upd={upd} k="damaged_item_description" l="Damaged item description and item number per Policy Schedule" rows={2} />
          <div className="grid grid-cols-2 gap-3">
            <EditField draft={draft} upd={upd} k="date_production_halted" l="Date production halted" type="date" />
            <EditField draft={draft} upd={upd} k="time_excess_start_end" l="Time excess (waiting period) start and end" />
            <EditField draft={draft} upd={upd} k="date_production_partial_resumed" l="Date production partially resumed" type="date" />
            <EditField draft={draft} upd={upd} k="date_production_full_resumed" l="Date production fully resumed (or expected)" type="date" />
            <EditField draft={draft} upd={upd} k="total_full_shutdown_days" l="Total full-shutdown days" />
            <EditField draft={draft} upd={upd} k="total_reduced_capacity_days" l="Total reduced-capacity days" />
            <EditField draft={draft} upd={upd} k="indemnity_period_max_end_date" l="Indemnity period max end-date" type="date" />
          </div>
          <div>
            <label className="block text-xs font-medium text-ink-faint mb-1">Is the loss continuing as at the date of this form?</label>
            <select value={String(draft.loss_continuing ?? '')} onChange={e => upd('loss_continuing', e.target.value)}
                    className="w-full px-3 py-2 border border-line rounded-md text-sm">
              <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
            </select>
          </div>
          <EditArea draft={draft} upd={upd} k="loss_continuing_details" l="Loss continuing — Details" rows={2} />
          <EditArea draft={draft} upd={upd} k="production_impact_description" l="How the breakdown affected production / turnover (which lines, what %)" rows={3} />
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Loss of Profit — A. Reduction in Turnover / Output</div>
          <div className="grid grid-cols-2 gap-3">
            <EditField draft={draft} upd={upd} k="standard_turnover_indemnity" l="Standard turnover during indemnity period to date (BWP)" type="number" />
            <EditField draft={draft} upd={upd} k="actual_turnover_indemnity" l="Actual turnover during indemnity period to date (BWP)" type="number" />
            <EditField draft={draft} upd={upd} k="reduction_in_turnover" l="Reduction in turnover (BWP)" type="number" />
            <EditField draft={draft} upd={upd} k="gross_profit_rate" l="Rate of Gross Profit applied (%)" />
            <EditField draft={draft} upd={upd} k="gross_profit_lost" l="Calculated Gross Profit lost (BWP)" type="number" />
          </div>
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Loss of Profit — B. Increased Cost of Working</div>
          <div className="grid grid-cols-2 gap-3">
            <EditField draft={draft} upd={upd} k="icw_outsourcing" l="Outsourcing / sub-contracting (BWP)" type="number" />
            <EditField draft={draft} upd={upd} k="icw_equipment_hire" l="Hire of replacement / temporary equipment (BWP)" type="number" />
            <EditField draft={draft} upd={upd} k="icw_express_freight" l="Express freight / expedited shipping (BWP)" type="number" />
            <EditField draft={draft} upd={upd} k="icw_overtime_labour" l="Overtime and additional labour (BWP)" type="number" />
            <EditField draft={draft} upd={upd} k="icw_temporary_premises" l="Temporary premises / utilities (BWP)" type="number" />
            <EditField draft={draft} upd={upd} k="icw_other" l="Other ICW (itemise) (BWP)" />
            <EditField draft={draft} upd={upd} k="icw_total" l="Total ICW (BWP)" type="number" />
          </div>
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Loss of Profit — C. Savings</div>
          <div className="grid grid-cols-2 gap-3">
            <EditField draft={draft} upd={upd} k="savings_raw_materials" l="Raw materials / consumables not used (BWP)" type="number" />
            <EditField draft={draft} upd={upd} k="savings_power_utilities" l="Power / utilities not consumed (BWP)" type="number" />
            <EditField draft={draft} upd={upd} k="savings_wages" l="Wages saved (staff stood down) (BWP)" type="number" />
            <EditField draft={draft} upd={upd} k="savings_other" l="Other savings (itemise) (BWP)" />
            <EditField draft={draft} upd={upd} k="savings_total" l="Total savings (BWP)" type="number" />
          </div>
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Loss of Profit — D. Estimated Claim</div>
          <div className="grid grid-cols-2 gap-3">
            <EditField draft={draft} upd={upd} k="gross_loss_of_profit" l="Estimated Gross Loss of Profit (A + B − C) (BWP)" type="number" />
            <EditField draft={draft} upd={upd} k="less_time_excess" l="Less Time Excess (BWP)" type="number" />
            <EditField draft={draft} upd={upd} k="less_self_insured_retention" l="Less Self-Insured Retention (BWP)" type="number" />
            <EditField draft={draft} upd={upd} k="net_estimated_claim" l="Net estimated claim (BWP)" type="number" />
          </div>
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Mitigation Actions</div>
          <EditArea draft={draft} upd={upd} k="mitigation_steps" l="Mitigation steps taken (with dates)" rows={3} />
          <div>
            <label className="block text-xs font-medium text-ink-faint mb-1">Was alternative production capacity available within the group / region?</label>
            <select value={String(draft.alt_production_available ?? '')} onChange={e => upd('alt_production_available', e.target.value)}
                    className="w-full px-3 py-2 border border-line rounded-md text-sm">
              <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
            </select>
          </div>
          <EditArea draft={draft} upd={upd} k="alt_production_details" l="Details (location, capacity used, cost)" rows={2} />
          <div>
            <label className="block text-xs font-medium text-ink-faint mb-1">Was replacement / hire equipment sourced?</label>
            <select value={String(draft.replacement_equipment_sourced ?? '')} onChange={e => upd('replacement_equipment_sourced', e.target.value)}
                    className="w-full px-3 py-2 border border-line rounded-md text-sm">
              <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
            </select>
          </div>
          <EditArea draft={draft} upd={upd} k="replacement_supplier_terms" l="Supplier and rental terms" rows={2} />
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Other Insurance &amp; Loss History</div>
          <div>
            <label className="block text-xs font-medium text-ink-faint mb-1">Is there any other Business Interruption / LOP cover that may respond?</label>
            <select value={String(draft.other_bi_cover ?? '')} onChange={e => upd('other_bi_cover', e.target.value)}
                    className="w-full px-3 py-2 border border-line rounded-md text-sm">
              <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
            </select>
          </div>
          <EditArea draft={draft} upd={upd} k="other_bi_details" l="Other BI / LOP cover — Details" rows={2} />
          <EditArea draft={draft} upd={upd} k="other_insurer_policy" l="Other insurer / policy / period / sum insured" rows={2} />
          <EditArea draft={draft} upd={upd} k="loss_history" l="Loss history — prior LOP / BI claims in past 24 months" rows={3} />
          <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Declaration</div>
          <div className="grid grid-cols-3 gap-3">
            <EditField draft={draft} upd={upd} k="declaration_name" l="Name" />
            <EditField draft={draft} upd={upd} k="declaration_capacity" l="Capacity" />
            <EditField draft={draft} upd={upd} k="declaration_date" l="Date" type="date" />
          </div>
        </div>
      ) : isMmType ? (
        // Medical Malpractice — V8 parity. Four sections; the 5
        // Section-4 file inputs render an inline FileRow with "View
        // Uploaded Document" link when an existing path is present.
        (() => {
          const MmFileRow = ({ k, l }: { k: string; l: string }) => {
            const existingPath = draft[k]
            const existingUrl = draft[`${k}_url`]
            const picked = files[k]
            return (
              <div>
                <label className="block text-xs font-medium text-ink-faint mb-1">{l}</label>
                {existingPath && existingUrl && !picked && (
                  <a href={existingUrl} target="_blank" rel="noopener noreferrer"
                     className="inline-block mb-2 text-xs text-primary hover:underline break-all">
                    View Uploaded Document
                  </a>
                )}
                <input type="file"
                       onChange={e => setFile(k, e.target.files?.[0] || null)}
                       className="w-full text-sm" />
                {picked && <p className="mt-1 text-xs text-status-success-fg truncate">{picked.name}</p>}
              </div>
            )
          }
          return (
            <div className="space-y-3">
              <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line">Section 1: Insured Party Information</div>
              <div className="grid grid-cols-2 gap-3">
                <EditField draft={draft} upd={upd} k="insured_full_name" l="Full Name of Insured" />
                <EditField draft={draft} upd={upd} k="professional_title_role" l="Professional Title / Role" />
                <EditField draft={draft} upd={upd} k="license_registration_number" l="License or Registration Number" />
                <EditField draft={draft} upd={upd} k="facility_practice_name" l="Facility / Practice Name" />
                <EditField draft={draft} upd={upd} k="insured_contact_number" l="Contact Number" />
                <EditField draft={draft} upd={upd} k="insured_email" l="Email Address" type="email" />
                <div className="col-span-2"><EditArea draft={draft} upd={upd} k="address_of_practice" l="Address of Practice" rows={2} /></div>
              </div>
              <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Section 2: Claimant (Patient) Information</div>
              <div className="grid grid-cols-2 gap-3">
                <EditField draft={draft} upd={upd} k="claimant_full_name" l="Full Name" />
                <EditField draft={draft} upd={upd} k="claimant_date_of_birth" l="Date of Birth" type="date" />
                <EditField draft={draft} upd={upd} k="claimant_contact_number" l="Contact Number" />
                <div className="col-span-2"><EditArea draft={draft} upd={upd} k="claimant_mailing_address" l="Mailing Address" rows={2} /></div>
              </div>
              <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Section 3: Details of Allegation</div>
              <div className="grid grid-cols-2 gap-3">
                <EditField draft={draft} upd={upd} k="date_of_alleged_incident" l="Date of Alleged Incident" type="date" />
                <EditField draft={draft} upd={upd} k="date_of_notification" l="Date of Notification of Allegation" type="date" />
                <div className="col-span-2"><EditField draft={draft} upd={upd} k="how_notified" l="How were you notified? (letter, legal notice)" /></div>
                <div className="col-span-2"><EditArea draft={draft} upd={upd} k="nature_of_services_provided" l="Nature of Services Provided" rows={3} /></div>
                <div className="col-span-2"><EditArea draft={draft} upd={upd} k="description_of_allegation" l="Detailed Description of Allegation" rows={4} /></div>
              </div>
              <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Section 4: Supporting Documents</div>
              <MmFileRow k="notification_letter"   l="Notification letter / legal document" />
              <MmFileRow k="patient_records"        l="Patient records and treatment notes" />
              <MmFileRow k="investigation_reports"  l="Internal investigation reports" />
              <MmFileRow k="correspondence"         l="Correspondence with claimant" />
              <MmFileRow k="expert_legal_opinions"  l="Expert or legal opinions" />
            </div>
          )
        })()
      ) : isGlassType ? (
        // Glass / Windscreen — V8 parity + V2 extensions. Three
        // sections; 6 file inputs render an inline FileRow with
        // "View Uploaded Document" link when an existing path is
        // present.
        (() => {
          const GlassFileRow = ({ k, l }: { k: string; l: string }) => {
            const existingPath = draft[k]
            const existingUrl = draft[`${k}_url`]
            const picked = files[k]
            return (
              <div>
                <label className="block text-xs font-medium text-ink-faint mb-1">{l}</label>
                {existingPath && existingUrl && !picked && (
                  <a href={existingUrl} target="_blank" rel="noopener noreferrer"
                     className="inline-block mb-2 text-xs text-primary hover:underline break-all">
                    View Uploaded Document
                  </a>
                )}
                <input type="file"
                       onChange={e => setFile(k, e.target.files?.[0] || null)}
                       className="w-full text-sm" />
                {picked && <p className="mt-1 text-xs text-status-success-fg truncate">{picked.name}</p>}
              </div>
            )
          }
          return (
            <div className="space-y-3">
              <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line">Glass / Windscreen Damage</div>
              <div className="grid grid-cols-2 gap-3">
                <EditField draft={draft} upd={upd} k="incident_date" l="Date of Damage" type="date" />
                <div>
                  <label className="block text-xs font-medium text-ink-faint mb-1">Damage Extent</label>
                  <select value={String(draft.extent ?? '')} onChange={e => upd('extent', e.target.value)}
                          className="w-full px-3 py-2 border border-line rounded-md text-sm">
                    <option value="">—</option>
                    <option value="Cracked">Cracked</option>
                    <option value="Shattered">Shattered</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium text-ink-faint mb-1">Damage Location</label>
                  <select value={String(draft.damage_location ?? '')} onChange={e => upd('damage_location', e.target.value)}
                          className="w-full px-3 py-2 border border-line rounded-md text-sm">
                    <option value="">—</option>
                    <option value="windscreen">Windscreen</option>
                    <option value="rear">Rear Glass</option>
                    <option value="side">Side Window</option>
                  </select>
                </div>
                <div className="col-span-2"><EditArea draft={draft} upd={upd} k="cause" l="Cause of Damage" rows={3} /></div>
              </div>
              <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Replacement Quotes</div>
              <div className="grid grid-cols-2 gap-3">
                <EditField draft={draft} upd={upd} k="company_1" l="Quote 1 — Company" />
                <EditField draft={draft} upd={upd} k="amount_quote_1" l="Quote 1 — Amount" type="number" />
                <EditField draft={draft} upd={upd} k="company_2" l="Quote 2 — Company" />
                <EditField draft={draft} upd={upd} k="amount_quote_2" l="Quote 2 — Amount" type="number" />
              </div>
              <div className="grid grid-cols-2 gap-3">
                <GlassFileRow k="quote_1" l="Quote 1 Document" />
                <GlassFileRow k="quote_2" l="Quote 2 Document" />
              </div>
              <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line pt-3">Damage Photos &amp; Descriptions</div>
              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-2">
                  <GlassFileRow k="incidentFront" l="Front Side" />
                  <EditArea draft={draft} upd={upd} k="front_image_description" l="Front — Description" rows={2} />
                </div>
                <div className="space-y-2">
                  <GlassFileRow k="incidentBack" l="Back Side" />
                  <EditArea draft={draft} upd={upd} k="back_image_description" l="Back — Description" rows={2} />
                </div>
                <div className="space-y-2">
                  <GlassFileRow k="incidentRight" l="Right Side" />
                  <EditArea draft={draft} upd={upd} k="right_image_description" l="Right — Description" rows={2} />
                </div>
                <div className="space-y-2">
                  <GlassFileRow k="incidentLeft" l="Left Side" />
                  <EditArea draft={draft} upd={upd} k="left_image_description" l="Left — Description" rows={2} />
                </div>
              </div>
            </div>
          )
        })()
      ) : isLokType ? (
        // Locks & Keys — V8 active fields + V2 extensions. One section;
        // the police_affidavit file input renders an inline FileRow
        // with "View Uploaded Document" link when an existing path is
        // present.
        (() => {
          const LokFileRow = ({ k, l }: { k: string; l: string }) => {
            const existingPath = draft[k]
            const existingUrl = draft[`${k}_url`]
            const picked = files[k]
            return (
              <div>
                <label className="block text-xs font-medium text-ink-faint mb-1">{l}</label>
                {existingPath && existingUrl && !picked && (
                  <a href={existingUrl} target="_blank" rel="noopener noreferrer"
                     className="inline-block mb-2 text-xs text-primary hover:underline break-all">
                    View Uploaded Document
                  </a>
                )}
                <input type="file"
                       onChange={e => setFile(k, e.target.files?.[0] || null)}
                       className="w-full text-sm" />
                {picked && <p className="mt-1 text-xs text-status-success-fg truncate">{picked.name}</p>}
              </div>
            )
          }
          return (
            <div className="space-y-3">
              {/* Full graphiteBWV8 admin/policy/key_loss.blade.php form. */}
              <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line">Loss Of Key Claim Information</div>
              <div className="grid grid-cols-2 gap-3">
                <EditField draft={draft} upd={upd} k="financial_interest" l="Financial Interest" />
                <EditField draft={draft} upd={upd} k="chassis_num" l="Chassis Number" />
                <EditField draft={draft} upd={upd} k="purpose" l="Purpose of use" />
                <div>
                  <label className="block text-xs font-medium text-ink-faint mb-1">Is the key lost or damaged or stolen</label>
                  <select value={String(draft.key_reason ?? '')} onChange={e => upd('key_reason', e.target.value)}
                          className="w-full px-3 py-2 border border-line rounded-md text-sm">
                    <option value="">—</option>
                    <option value="lost">Lost</option>
                    <option value="damaged">Damaged</option>
                    <option value="stolen">Stolen</option>
                  </select>
                </div>
                <EditField draft={draft} upd={upd} k="estimate" l="Replacement Estimate" />
                <EditField draft={draft} upd={upd} k="lossDate" l="Date of Loss / Stolen / Damage" type="date" />
                <EditField draft={draft} upd={upd} k="registered_claim" l="Date of claim registered" type="date" />
              </div>
              <EditArea draft={draft} upd={upd} k="descriptionofLoss" l="Description" rows={3} />
              <LokFileRow k="police_affidavit" l="Police Affidavit" />

              <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line">Insured Details</div>
              <div className="grid grid-cols-2 gap-3">
                <EditField draft={draft} upd={upd} k="name_of_insured" l="Name of Insured" />
                <EditField draft={draft} upd={upd} k="insured_address" l="Address" />
                <EditField draft={draft} upd={upd} k="insured_occupation" l="Occupation" />
                <EditField draft={draft} upd={upd} k="insured_email" l="Email" type="email" />
                <EditField draft={draft} upd={upd} k="insured_contact_no" l="Contact No" />
              </div>

              <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line">Vehicle Details</div>
              <div className="grid grid-cols-2 gap-3">
                {plateOpts && plateOpts.length > 0 ? (
                  <EditSel draft={draft} upd={upd} k="vehicle_plate" l="Registration Number"
                    opts={draft.vehicle_plate && !plateOpts.some(o => o.id === String(draft.vehicle_plate))
                      ? [{ id: String(draft.vehicle_plate), name: `${draft.vehicle_plate} (not on term)` }, ...plateOpts]
                      : plateOpts} />
                ) : (
                  <EditField draft={draft} upd={upd} k="vehicle_plate" l="Registration Number" />
                )}
                <div>
                  <label className="block text-xs font-medium text-ink-faint mb-1">Is Imported?</label>
                  <select value={String(draft.is_imported ?? '')} onChange={e => upd('is_imported', e.target.value)}
                          className="w-full px-3 py-2 border border-line rounded-md text-sm">
                    <option value="">—</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                  </select>
                </div>
                <EditField draft={draft} upd={upd} k="make" l="Make" />
                <EditField draft={draft} upd={upd} k="year" l="Manufacturing Year" />
                <EditField draft={draft} upd={upd} k="model" l="Model" />
              </div>

              <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pb-1 border-b border-line">Quotation</div>
              <div className="grid grid-cols-2 gap-3">
                <EditField draft={draft} upd={upd} k="company_1" l="Quote 1 — Name of company" />
                <EditField draft={draft} upd={upd} k="amount_quote_1" l="Quote 1 — Amount of quote" type="number" />
                <EditField draft={draft} upd={upd} k="company_2" l="Quote 2 — Name of company" />
                <EditField draft={draft} upd={upd} k="amount_quote_2" l="Quote 2 — Amount of quote" type="number" />
              </div>
              <div className="grid grid-cols-2 gap-3">
                <LokFileRow k="quote_1" l="Quote 1 — Document" />
                <LokFileRow k="quote_2" l="Quote 2 — Document" />
              </div>
            </div>
          )
        })()
      ) : (
        // Generic editor for other DOM/COM sub-tables: one input per
        // populated key, except internal / file-path columns. Keeps
        // the inline editor available for non-GIT types without
        // hand-rolling a layout for each.
        <div className="grid grid-cols-2 gap-3">
          {Object.keys(draft)
            .filter(k => !['id', 'newclaim_id', 'policyNumber', 'claim_sub_type_id',
              'created_at', 'updated_at', 'deleted_at', 'copy_of_contract_url'].includes(k))
            .map(k => (
              <EditField key={k} draft={draft} upd={upd} k={k} l={k.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())} />
            ))}
        </div>
      )}
    </div>
  )
}

/**
 * Inline editor for the Classification & Allocation card. Mirrors the
 * 13 DOM/COM common fields the create flow captures via ClassAlloc
 * (graphiteBWV8 main.blade.php classification block). All fields write
 * to top-level new_claims columns the backend already accepts in the
 * /claims/{id} update validate rules.
 */
function renderClassificationEditForm({
  draft, setDraft, createData, riskAddresses, claimSubTypes, policyActionOpts, claimType, claimTypeOpts, error, lossDateLocked = false,
}: {
  draft: Record<string, any>
  setDraft: React.Dispatch<React.SetStateAction<Record<string, any>>>
  createData: any
  riskAddresses: { id: number; name: string }[]
  claimSubTypes: { id: any; name: string }[]
  policyActionOpts: { id: any; name: string }[]
  claimType: string | null | undefined
  // Claim-type options for this claim's policy (from claim-types-by-policy).
  claimTypeOpts: { id: any; name: string }[]
  error: string | null
  // Date of Loss is read-only once the claim is Approved (PO issued) — server
  // enforces this too; this just reflects the lock in the UI.
  lossDateLocked?: boolean
}) {
  const upd = (k: string, v: any) => setDraft((p: any) => ({ ...p, [k]: v }))
  const YesNoOpts = [{ id: '1', name: 'Yes' }, { id: '0', name: 'No' }]
  // Same gate as ClaimCreatePage — V8 main.blade.php:177 only renders Sub
  // Type for these 10 claim type codes.
  const SUB_TYPE_CLAIM_TYPES = new Set([
    'BUSINESSINTERRUPTION', 'BUSINESSALLRISKS', 'ELECTRONICEQUIPMENT',
    'PERSONALALLRISKS', 'THEFT', 'DEFECTIVEWORKMANSHIP',
    'FIDELITYGUARANTEE', 'WORKERSCOMPENSATION', 'STATEDBENEFITS', 'MONEY',
  ])
  const ctKey = (claimType || '').toUpperCase().replace(/[^A-Z0-9]/g, '')
  const showClaimSubType = SUB_TYPE_CLAIM_TYPES.has(ctKey)
  // All Risks family swaps Reported by Broker/Agent for Event Name in the
  // Classification block — same swap as ClaimCreatePage.ClassAlloc.
  const ALL_RISKS_TYPES = new Set([
    'BUSINESSALLRISKS', 'PERSONALALLRISKS', 'ELECTRONICEQUIPMENT', 'ALLRISK',
  ])
  const isAllRisksType = ALL_RISKS_TYPES.has(ctKey)
  // Workers Compensation / Stated Benefits hide the Reported by Broker/Agent
  // slot entirely (no Event Name surfacing here either) — matches V8.
  const isWorkersCompType = ctKey === 'WORKERSCOMPENSATION' || ctKey === 'STATEDBENEFITS'

  return (
    <div className="space-y-3">
      {error && <div className="p-3 bg-status-danger-bg border border-status-danger-fg rounded-md text-status-danger-fg text-sm">{error}</div>}
      <div className="grid grid-cols-2 gap-3">
        {/* Claim Type — editable by everyone. Changing it re-routes the
            claim to a different type/sub-table flow. */}
        <EditSel draft={draft} upd={upd} k="claim_type" l="Claim Type" opts={claimTypeOpts} />
        {/* Policy action / term the claim is filed against. Mirrors the
            create-flow selector; persisted to claims.policy_action_id. */}
        {policyActionOpts.length > 0 && (
          <EditSel draft={draft} upd={upd} k="policy_action_id" l="Policy Action / Term" opts={policyActionOpts} />
        )}
        {showClaimSubType && (
          <EditSel draft={draft} upd={upd} k="claim_sub_type_id" l="Claim Sub Type" opts={claimSubTypes} />
        )}
        <EditSel   draft={draft} upd={upd} k="location_id" l="Select Location" opts={riskAddresses} />
        <EditSel   draft={draft} upd={upd} k="claim_reported_by" l="Claim Reported By" opts={createData?.reported_by} />
        <EditField draft={draft} upd={upd} k="reserve_amount" l="Total Reserve Amount (BWP)" type="number" />
        <EditField draft={draft} upd={upd} k="paid_amount"    l="Total Paid Amount (BWP)"    type="number" />
        <EditSel   draft={draft} upd={upd} k="type_of_loss" l="Type of Loss" opts={createData?.loss_types_dom_com} />
        <EditSel   draft={draft} upd={upd} k="service_representative_id" l="Service Representative" opts={createData?.internal_users} />
        <EditSel   draft={draft} upd={upd} k="catastrophe_loss"   l="Catastrophe Loss"          opts={YesNoOpts} />
        <EditSel   draft={draft} upd={upd} k="attorney_involved"  l="Primary Attorney Involved" opts={YesNoOpts} />
        {String(draft.attorney_involved) === '1' && (
          <>
            <EditSel   draft={draft} upd={upd} k="primary_attorney_assigned_id" l="Primary Attorney Assigned" opts={createData?.attorneys_primary} />
            <EditField draft={draft} upd={upd} k="p_a_assigned_date" l="Primary Attorney Assigned Date" type="date" />
          </>
        )}
        <EditSel draft={draft} upd={upd} k="co_attorney_involved" l="Co-Attorney Involved" opts={YesNoOpts} />
        {String(draft.co_attorney_involved) === '1' && (
          <>
            <EditSel   draft={draft} upd={upd} k="co_attorney_assigned_id" l="Co-Attorney Assigned" opts={createData?.attorneys_co} />
            <EditField draft={draft} upd={upd} k="c_a_assigned_date" l="Co-Attorney Assigned Date" type="date" />
          </>
        )}
        <EditSel   draft={draft} upd={upd} k="dfs_complaint" l="DFS Complaint" opts={YesNoOpts} />
        <EditSel   draft={draft} upd={upd} k="claim_allocated_to" l="Claims Allocated To" opts={createData?.internal_users} />
        <EditField draft={draft} upd={upd} k="claim_allocated_on" l="Allocated Date" type="date" />
        <EditField draft={draft} upd={upd} k="date_first_visited" l="Date First Visited" type="date" />
        {/* Claim Submission Date is edited in the SLA Timeline tab (Stage 5, before
            PO issue date) per claims-team v6; still shown read-only in the summary. */}
        {/* V8 Classification block extras —
            • All Risks family shows Event Name in this slot.
            • WORKERSCOMPENSATION / STATEDBENEFITS hide the slot entirely.
            • Everything else shows Reported by Broker/Agent. */}
        {isAllRisksType ? (
          <EditField draft={draft} upd={upd} k="event_name" l="Event Name" />
        ) : isWorkersCompType ? null : (
          <EditSel draft={draft} upd={upd} k="reportedByBrokerAgent" l="Reported by Broker/Agent" opts={createData?.agents_options} />
        )}
        <EditField draft={draft} upd={upd} k="date_of_loss" l={lossDateLocked ? 'Date of Loss (locked — claim approved)' : 'Date of Loss'} type="date" disabled={lossDateLocked} />
      </div>
      <EditArea draft={draft} upd={upd} k="description_of_loss" l="Description of Loss" rows={3} />
    </div>
  )
}

function TabClaimDetails({ c, editable, onRefresh }: { c: ClaimDetail; editable: boolean; onRefresh: () => void }) {
  const { toast } = useToast()
  const statusColor = STATUS_COLORS[c.status] ?? 'bg-surface-2 text-ink-muted border-line'
  // Lookups (internal users, attorney lists, loss types) — used to
  // resolve the IDs stored on new_claims into human-friendly names for
  // the Classification & Allocation card. Cached by react-query so this
  // doesn't refetch when the user toggles tabs.
  const { data: createData } = useClaimCreateData()

  // ── Section-wise inline editing ──────────────────────────────────
  // Each tab card can be flipped into an editable form independently
  // (rather than opening the global Edit Claim modal). Saves only the
  // fields belonging to that section, so opening Classification &
  // Allocation doesn't churn the GIT details and vice-versa.
  const [editingSection, setEditingSection] = useState<'subclaim' | 'classification' | null>(null)
  const [sectionDraft, setSectionDraft] = useState<Record<string, any>>({})
  const [sectionSaving, setSectionSaving] = useState(false)
  const [sectionError, setSectionError] = useState<string | null>(null)
  // ── Inline edit for the prominent Vehicle Registration banner ──
  // Lets a handler correct a missing/wrong plate without opening the full
  // Edit Claim form. Persists via the existing PUT /claims/{id} (audited,
  // writes new_claims.vehicle_plate + claims), then refetches.
  const [editingReg, setEditingReg] = useState(false)
  const [regDraft, setRegDraft] = useState('')
  const [savingReg, setSavingReg] = useState(false)

  async function saveVehicleReg() {
    setSavingReg(true)
    try {
      await apiClient.put(`/claims/${c.id}`, { vehicle_plate: regDraft.trim() })
      setEditingReg(false)
      toast.success('Vehicle registration updated')
      onRefresh()
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Failed to update vehicle registration')
    } finally {
      setSavingReg(false)
    }
  }
  // Optional file uploads attached to the section save (e.g. GIT
  // copy_of_contract). Keyed by backend field name so each section can
  // attach multiple files in future without a schema change here.
  const [sectionFiles, setSectionFiles] = useState<Record<string, File | null>>({})

  // ── Union legal claim (BONU / BOWASEWU) inline edit ──────────────
  // Self-contained edit state for the union claim's own fields; saves to the
  // union endpoint (which also syncs the mirrored claim_legal row).
  const u = c.unionLegalClaim as Record<string, any> | null | undefined
  const [unionEdit, setUnionEdit] = useState(false)
  const [unionDraft, setUnionDraft] = useState<Record<string, string>>({})
  const [unionSaving, setUnionSaving] = useState(false)
  const [unionError, setUnionError] = useState<string | null>(null)
  const unionChild = (unionEdit ? unionDraft.matter_relates_to : u?.matter_relates_to) === 'Child'
  function openUnionEdit() {
    if (!u) return
    setUnionError(null)
    setUnionDraft({
      region: u.region ?? '',
      claim_type: u.claim_type ?? 'Legal',
      matter_relates_to: u.matter_relates_to ?? '',
      child_financially_dependent: u.child_financially_dependent == null ? '' : (Number(u.child_financially_dependent) === 1 ? 'yes' : 'no'),
      dependent_omang_passport: u.dependent_omang_passport ?? '',
      dependent_dob: (u.dependent_dob ?? '').slice(0, 10),
      matter_type: u.matter_type ?? '',
      matter_arose_date: (u.matter_arose_date ?? '').slice(0, 10),
      proposed_course_of_action: u.proposed_course_of_action ?? '',
    })
    setUnionEdit(true)
  }
  async function saveUnionEdit() {
    if (!u) return
    setUnionSaving(true)
    setUnionError(null)
    try {
      const d = unionDraft
      const payload: Record<string, any> = {
        region: d.region || null,
        claim_type: d.claim_type || null,
        matter_relates_to: d.matter_relates_to || undefined,
        matter_type: d.matter_type || undefined,
        matter_arose_date: d.matter_arose_date || null,
        proposed_course_of_action: d.proposed_course_of_action || null,
      }
      if (d.matter_relates_to === 'Child') {
        if (d.child_financially_dependent) payload.child_financially_dependent = d.child_financially_dependent === 'yes'
        payload.dependent_omang_passport = d.dependent_omang_passport || null
        payload.dependent_dob = d.dependent_dob || null
      }
      await updateUnionLegalClaim(u.union_id, u.id, payload)
      setUnionEdit(false)
      onRefresh()
    } catch (e: any) {
      setUnionError(e?.response?.data?.error || e?.response?.data?.message || e?.message || 'Update failed')
    } finally {
      setUnionSaving(false)
    }
  }

  // Per-policy risk addresses for the Classification "Select Location"
  // dropdown. Fetched once when the policy id resolves; reused while the
  // user toggles edit on/off. Mirrors the create-page wiring so the same
  // labels show on detail-page edit as appear at create-time.
  const [policyRiskAddresses, setPolicyRiskAddresses] = useState<Array<{ id: number; name: string }>>([])
  useEffect(() => {
    const pid = c.policy?.id
    if (!pid) { setPolicyRiskAddresses([]); return }
    apiClient.get(`/policies/${pid}/risk-addresses`)
      .then(r => {
        const rows: any[] = r.data?.data ?? []
        setPolicyRiskAddresses(rows.map((a: any) => ({
          id: a.id,
          name: a.addressName || a.address || a.physical_address || a.address_name
            || [a.city, a.state].filter(Boolean).join(', ') || `Address #${a.id}`,
        })))
      })
      .catch(() => setPolicyRiskAddresses([]))
  }, [c.policy?.id])

  // Policy action terms for the Classification "Policy Action / Term" dropdown.
  // Same list the create flow offered (excludes cancel-type terms). Fetched
  // by claim id so the backend scopes it to this claim's policy.
  const [policyActions, setPolicyActions] = useState<PolicyActionOption[]>([])
  useEffect(() => {
    if (!c.id) { setPolicyActions([]); return }
    fetchClaimPolicyActions(c.id)
      .then(setPolicyActions)
      .catch(() => setPolicyActions([]))
  }, [c.id])
  // Build {id,name} options for EditSel, e.g. "NEWBUSINESS — QUOTE (19/01/2026 – 28/02/2027)".
  const policyActionOpts = policyActions.map(a => ({
    id: a.id,
    name: `${a.transactionType} — ${a.status}`
      + ((a.effectiveFrom || a.effectiveTo)
        ? ` (${a.effectiveFrom?.slice(0, 10) ?? '—'} – ${a.effectiveTo?.slice(0, 10) ?? '—'})`
        : ''),
  }))
  // The terms endpoint excludes soft-deleted terms, so a claim still linked
  // to a deleted term would show "--" in the dropdown. Prepend the current
  // (deleted) term — marked "(Deleted)" — so the selection stays visible.
  if (c.policy_action?.deleted && !policyActionOpts.some(o => String(o.id) === String(c.policy_action!.id))) {
    const pa = c.policy_action
    policyActionOpts.unshift({
      id: pa.id,
      name: `${pa.transaction_type} — ${pa.status} (Deleted)`,
    })
  }

  // Term-scoped plate list for the Vehicle Registration editor — same source
  // as the create page's dropdown (GET /policies/{id}/vehicles?action_id=),
  // scoped to the term the claim was filed against. Empty list ⇒ the editor
  // falls back to free text (terms with no captured vehicles).
  const [platesForTerm, setPlatesForTerm] = useState<Array<{ id: string; name: string }>>([])
  useEffect(() => {
    const policyId = c.policy?.id
    if (!policyId) { setPlatesForTerm([]); return }
    fetchPolicyVehicles(policyId, c.policy_action?.id ?? undefined)
      .then(res => setPlatesForTerm(
        (res.data ?? [])
          .filter(v => v?.vehiclePlate)
          .map(v => ({
            id: String(v.vehiclePlate),
            name: [v.vehiclePlate, [v.make, v.model].filter(Boolean).join(' ')].filter(Boolean).join(' — '),
          }))
      ))
      .catch(() => setPlatesForTerm([]))
  }, [c.policy?.id, c.policy_action?.id])

  // Claim-type options for this claim's policy — used by the (always-shown)
  // Claim Type dropdown in the Classification card. `id` is the stored
  // claim_type label, matching what the create flow submits.
  const [claimTypeOpts, setClaimTypeOpts] = useState<{ id: any; name: string }[]>([])
  useEffect(() => {
    const pid = c.policy?.id
    if (!pid) { setClaimTypeOpts([]); return }
    apiClient.get('/claims/claim-types-by-policy', { params: { policy: pid } })
      .then(r => {
        const types: any[] = r.data?.data?.claim_types ?? []
        setClaimTypeOpts(types.map(t => ({ id: t.id, name: t.name })))
      })
      .catch(() => setClaimTypeOpts([]))
  }, [c.policy?.id])

  // Claim sub-types filtered by claim_type — mirrors ClaimCreatePage.
  // graphiteBWV8 main.blade.php:177 shows the Sub Type dropdown only for
  // a fixed list of claim types; the lookup rows are keyed by claim_type
  // code. Same endpoint here resolves the right rows for both the read-
  // mode display (id → name) and the edit dropdown.
  const [claimSubTypes, setClaimSubTypes] = useState<Array<{ id: any; name: string }>>([])
  useEffect(() => {
    const ct = c.claim_type
    if (!ct) { setClaimSubTypes([]); return }
    apiClient.get('/claims/sub-types-by-claim-type', { params: { claim_type: ct } })
      .then(r => setClaimSubTypes(r.data?.data ?? []))
      .catch(() => setClaimSubTypes([]))
  }, [c.claim_type])

  function openSectionEdit(section: 'subclaim' | 'classification') {
    setSectionError(null)
    setSectionFiles({})
    if (section === 'subclaim') {
      setSectionDraft({ ...(c.subClaimData ?? {}) })
    } else {
      setSectionDraft({
        // Claim Type — hydrated so the Super Admin dropdown prefills the
        // current value; ignored by the backend for non-super-admins.
        claim_type: c.claim_type ?? '',
        location_id: (c as any).location_id ?? '',
        policy_action_id: c.policy_action?.id != null ? String(c.policy_action.id) : '',
        // String()-coerce the lookup-id fields the backend validates as
        // `string` (type_of_loss, claim_reported_by, reportedByBrokerAgent).
        // They come back numeric for some claims, and saveSectionEdit posts
        // the whole draft, so an untouched numeric value fails the string rule.
        claim_reported_by: (c as any).claim_reported_by != null ? String((c as any).claim_reported_by) : '',
        reserve_amount: (c as any).reserve_amount ?? '',
        paid_amount: (c as any).paid_amount ?? '',
        type_of_loss: (c as any).type_of_loss != null ? String((c as any).type_of_loss) : '',
        service_representative_id: (c as any).service_representative_id ?? '',
        catastrophe_loss: (c as any).catastrophe_loss != null ? String((c as any).catastrophe_loss) : '',
        attorney_involved: (c as any).attorney_involved != null ? String((c as any).attorney_involved) : '',
        primary_attorney_assigned_id: (c as any).primary_attorney_assigned_id != null ? String((c as any).primary_attorney_assigned_id) : '',
        p_a_assigned_date: (c as any).p_a_assigned_date ? String((c as any).p_a_assigned_date).slice(0, 10) : '',
        co_attorney_involved: (c as any).co_attorney_involved != null ? String((c as any).co_attorney_involved) : '',
        co_attorney_assigned_id: (c as any).co_attorney_assigned_id != null ? String((c as any).co_attorney_assigned_id) : '',
        c_a_assigned_date: (c as any).c_a_assigned_date ? String((c as any).c_a_assigned_date).slice(0, 10) : '',
        dfs_complaint: (c as any).dfs_complaint != null ? String((c as any).dfs_complaint) : '',
        claim_allocated_to: (c as any).claim_allocated_to ?? '',
        claim_allocated_on: (c as any).claim_allocated_on ? String((c as any).claim_allocated_on).slice(0, 10) : '',
        date_first_visited: (c as any).date_first_visited ? String((c as any).date_first_visited).slice(0, 10) : '',
        submission_date: (c as any).submission_date ? String((c as any).submission_date).slice(0, 10) : '',
        // V8 Classification block extras
        reportedByBrokerAgent: (c as any).reportedByBrokerAgent != null ? String((c as any).reportedByBrokerAgent) : '',
        date_of_loss: (c as any).date_of_loss ? String((c as any).date_of_loss).slice(0, 10) : '',
        description_of_loss: (c as any).description_of_loss ?? '',
        claim_sub_type_id: (c as any).claim_sub_type_id ?? '',
        // event_name occupies the same slot as reportedByBrokerAgent for
        // All Risks family claims; hydrate it so Edit prefills the value.
        event_name: (c as any).event_name ?? '',
      })
    }
    setEditingSection(section)
  }

  function cancelSectionEdit() {
    setEditingSection(null)
    setSectionDraft({})
    setSectionFiles({})
    setSectionError(null)
  }

  async function saveSectionEdit() {
    setSectionSaving(true)
    setSectionError(null)
    try {
      // Strip blank values so we don't overwrite stored data with empty
      // strings on partial edits, and so backend `nullable|*` rules
      // don't reject "" when the schema expects a valid type.
      const cleaned: Record<string, any> = {}
      for (const [k, v] of Object.entries(sectionDraft)) {
        if (v === '' || v === null || v === undefined) continue
        cleaned[k] = v
      }

      const hasFiles = Object.values(sectionFiles).some(f => f != null)

      if (hasFiles) {
        // Multipart path — Laravel doesn't reliably parse multipart
        // bodies on the PUT verb (PHP itself only auto-parses POST), so
        // POST with `_method=PUT` to trigger Laravel's MethodOverride
        // and route the request to ClaimsController::update().
        const fd = new FormData()
        fd.append('_method', 'PUT')
        if (editingSection === 'subclaim') {
          fd.append('sub_claim_data', JSON.stringify(cleaned))
        } else {
          for (const [k, v] of Object.entries(cleaned)) fd.append(k, String(v))
        }
        for (const [k, f] of Object.entries(sectionFiles)) {
          if (f) fd.append(k, f)
        }
        await apiClient.post(`/claims/${c.id}`, fd, {
          headers: { 'Content-Type': 'multipart/form-data' },
        })
      } else {
        const payload = editingSection === 'subclaim'
          ? { sub_claim_data: cleaned }
          : cleaned
        await apiClient.put(`/claims/${c.id}`, payload)
      }

      setEditingSection(null)
      setSectionDraft({})
      setSectionFiles({})
      onRefresh()
    } catch (e: any) {
      const d = e?.response?.data ?? {}
      const detail = d.errors ? Object.values(d.errors).flat().join(' · ') : ''
      setSectionError([d.message || 'Save failed', detail].filter(Boolean).join(' — '))
    } finally {
      setSectionSaving(false)
    }
  }

  // Legacy inline-edit state retained only because the dead `false &&`
  // blocks below still reference it for type-checking. The active
  // editing path is `editingSection` above. Do not delete unless those
  // dead blocks are also removed.
  const [editing, setEditing] = useState(false)
  const [saving, setSaving] = useState(false)
  const [form, setForm] = useState({
    claim_type: c.claim_type || '',
    category: c.category || '',
    incident_date: (c as any).incident_date || (c as any).date_of_loss || '',
    incident_time: (c as any).incident_time || '',
    incident_location: (c as any).location || (c as any).incident_location || '',
    incident_description: (c as any).description_of_loss || (c as any).incident_description || '',
    reported_by: c.claim_reported_by || (c as any).reported_by || '',
    reported_date: (c as any).reported_date || '',
  })

  async function handleSave() {
    setSaving(true)
    try {
      await apiClient.put(`/claims/${c.id}`, form)
      setEditing(false)
      onRefresh()
    } catch (e: any) { toast.error(e.response?.data?.message || 'Update failed') }
    finally { setSaving(false) }
  }

  return (
    <div className="space-y-6">
      {/* Policy & Customer row */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {c.policy && (
          <Card title="Policy" accent="bg-status-info-bg">
            <DL items={[
              ['Policy #', <Link to={`/policies/${c.policy.id}`} className="text-primary hover:underline">{c.policy.policy_number}</Link>],
              ['Product', c.policy.product_name || '-'],
              ['Premium', fmt(c.policy.premium)],
              ['Agent', c.policy.agent_name || '-'],
              ['Status', c.policy.status === 1 ? 'Active' : c.policy.status === 2 ? 'Cancelled' : c.policy.status === 0 ? 'Pending' : 'Expired'],
            ]} />
          </Card>
        )}
        {c.customer && (
          <Card title="Customer" accent="bg-status-success-bg">
            <DL items={[
              ['Name', c.customer.name],
              ['Email', c.customer.email || '-'],
              ['Phone', c.customer.cellphone || '-'],
            ]} />
          </Card>
        )}
      </div>

      {/* Selected vehicle's registration — shown prominently for motor claims.
          Sourced from new_claims.vehicle_plate first, then the claim_vehicle
          record (vehicleDetails), whichever holds the plate. */}
      {(c.is_motor_claim || c.policy?.form_template === 'vehicle') && (() => {
        const vehicleReg = String(
          c.vehicle_plate
          || (c.vehicleDetails as any)?.registration
          || (c.vehicleDetails as any)?.vehicle_plate
          || (c.vehicleDetails as any)?.registration_no
          || (c.vehicleDetails as any)?.reg_number
          || ''
        ).trim()
        return (
          <div className="flex items-center gap-4 rounded-lg border border-line bg-status-info-bg px-5 py-4">
            <span className="text-2xl" aria-hidden>🚗</span>
            <div className="flex-1">
              <div className="text-[11px] font-semibold uppercase tracking-wide text-ink-muted">Vehicle Registration</div>
              {editingReg ? (
                <div className="flex items-center gap-2 mt-1">
                  {platesForTerm.length > 0 ? (
                    /* No Enter/Escape keydown on the select: Firefox dispatches
                       keydown while the native popup is open, so Enter would save
                       the stale pre-selection value and Escape would discard the
                       edit session. Save/Cancel buttons handle both. */
                    <div className="relative">
                      <select
                        autoFocus
                        value={regDraft}
                        onChange={e => setRegDraft(e.target.value)}
                        className="appearance-none min-w-[18rem] max-w-full pl-3 pr-10 py-2 border border-line rounded-lg bg-surface shadow-sm text-base font-semibold tracking-wide text-ink cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary"
                      >
                        {!regDraft && <option value="">— Select plate —</option>}
                        {/* Legacy value not on this term stays selectable/visible. */}
                        {regDraft && !platesForTerm.some(o => o.id === regDraft) && (
                          <option value={regDraft}>{regDraft} (not on term)</option>
                        )}
                        {platesForTerm.map(o => <option key={o.id} value={o.id}>{o.name}</option>)}
                      </select>
                      {/* Chevron — appearance-none removes the native arrow. */}
                      <svg aria-hidden viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth={2}
                        className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-ink-faint">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 8l4 4 4-4" />
                      </svg>
                    </div>
                  ) : (
                  <input
                    autoFocus
                    value={regDraft}
                    onChange={e => setRegDraft(e.target.value)}
                    onKeyDown={e => { if (e.key === 'Enter') { saveVehicleReg() } if (e.key === 'Escape') { setEditingReg(false) } }}
                    placeholder="e.g. B 123 ABC"
                    className="w-56 pl-3 pr-3 py-2 border border-line rounded-lg bg-surface shadow-sm text-base font-semibold tracking-wide text-ink focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary"
                  />
                  )}
                  <button type="button" onClick={saveVehicleReg} disabled={savingReg}
                    className="px-4 py-2 text-sm font-medium rounded-lg bg-primary text-primary-contrast shadow-sm hover:opacity-90 transition disabled:opacity-50">
                    {savingReg ? 'Saving…' : 'Save'}
                  </button>
                  <button type="button" onClick={() => setEditingReg(false)} disabled={savingReg}
                    className="px-4 py-2 text-sm font-medium rounded-lg border border-line bg-surface hover:bg-surface-2 text-ink-muted transition">
                    Cancel
                  </button>
                </div>
              ) : (
                <div className="flex items-center gap-3">
                  <span className="text-2xl font-bold text-ink tracking-wider">{vehicleReg || 'Not captured'}</span>
                  {editable && (
                    <button type="button" onClick={() => { setRegDraft(vehicleReg); setEditingReg(true) }}
                      className="text-xs px-2 py-0.5 rounded border border-line hover:bg-surface-2 text-ink-muted">
                      Edit
                    </button>
                  )}
                </div>
              )}
            </div>
          </div>
        )
      })()}

      {/* Claim Details + Other Information cards removed per operator
          feedback — they showed generic metadata (status / updated-at /
          weather / fault_party / etc.) that cluttered the view without
          carrying fields the user actually fills at claim creation time.
          The header above already shows claim_number / type / status,
          and the type-specific cards below (Life / Legal / Hospital Cash
          / Accident / subClaimData) render only the fields that were
          entered. Use the "Edit Claim" button in the header to modify.
          Inline editing is kept available inside `false` blocks below in
          case we need to resurrect it quickly. */}
      {false && <Card title={<div className="flex items-center justify-between w-full"><span>Claim Details</span>
        {editable && !editing && <button onClick={() => setEditing(true)} className="text-xs text-primary hover:underline font-normal">Edit</button>}
        {editing && <div className="flex gap-2"><button onClick={handleSave} disabled={saving} className="text-xs px-3 py-1 bg-primary text-white rounded">{saving ? 'Saving...' : 'Save'}</button><button onClick={() => setEditing(false)} className="text-xs px-3 py-1 border border-line rounded">Cancel</button></div>}
      </div>}>
        {editing ? (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div><label className="block text-xs font-medium text-ink-faint mb-1">Claim Type</label>
              <select value={form.claim_type} onChange={e => setForm({...form, claim_type: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                {/* 'Life' kept alongside 'Accidental Death' so old ADI
                    claims (stored as 'Life' before the product-1 rename)
                    can still be edited without their type being silently
                    overwritten. */}
                {['Motor','Accident','Glass','Key Loss','Fire','Burglary','Accidental Death','Life','Workers Compensation','Business All Risks','Personal All Risks','Property Damage','Accidental Damage','Liability','Office Contents','Business Interruption','Mobile Electronic Devices','Goods In Transit','Fidelity Guarantee'].map(t => <option key={t} value={t}>{t}</option>)}
              </select></div>
            <div><label className="block text-xs font-medium text-ink-faint mb-1">Category</label>
              <input value={form.category} onChange={e => setForm({...form, category: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
            <div><label className="block text-xs font-medium text-ink-faint mb-1">Incident Date</label>
              <input type="date" value={form.incident_date} onChange={e => setForm({...form, incident_date: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
            <div><label className="block text-xs font-medium text-ink-faint mb-1">Incident Time</label>
              <input value={form.incident_time} onChange={e => setForm({...form, incident_time: e.target.value})} placeholder="HH:MM" className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
            <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Location</label>
              <input value={form.incident_location} onChange={e => setForm({...form, incident_location: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
            <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Description</label>
              <textarea value={form.incident_description} onChange={e => setForm({...form, incident_description: e.target.value})} rows={3} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
            <div><label className="block text-xs font-medium text-ink-faint mb-1">Reported By</label>
              <input value={form.reported_by} onChange={e => setForm({...form, reported_by: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
            <div><label className="block text-xs font-medium text-ink-faint mb-1">Reported Date</label>
              <input type="date" value={form.reported_date} onChange={e => setForm({...form, reported_date: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
          </div>
        ) : (
          <DL items={[
            ['Location', (c as any).location || '-'],
            ['Claim Reported By', c.claim_reported_by || '-'],
            ['Is this motor claim', c.is_motor_claim ? 'Yes' : 'No'],
            ...(c.is_motor_claim ? [['Motor', c.vehicle_plate || '-'] as [string, React.ReactNode]] : []),
            ['Type of Loss', c.type_of_loss || '-'],
            ['Date of Loss', fmtDate(c.date_of_loss)],
            ['Service Representative', (c as any).service_representative || '-'],
            ['Catastrophe Loss', yesNo((c as any).catastrophe_loss)],
            ['Primary Attorney Involved', yesNo(c.attorney_involved)],
            ['Co-Attorney Involved', yesNo((c as any).co_attorney_involved)],
            ['DFS Complaint', yesNo((c as any).dfs_complaint)],
            ['Event Name', (c as any).event_name || '-'],
            ['Description of Loss', (c as any).description_of_loss || '-'],
            ['Date First Visited', fmtDate((c as any).date_first_visited)],
            ['Claim Submission Date', fmtDate((c as any).submission_date)],
            ['Reported by Broker/Agent', (c as any).reportedByBrokerAgent || 'NA'],
            ['Third party is insured elsewhere?', yesNo((c as any).third_party_insured_elsewhere)],
            ['Driver as the insured', yesNo(c.driver_as_insured)],
          ]} />
        )}
      </Card>}

      {/* Other Information card also removed — same reason as Claim
          Details above. The claim_number, status, dates, created-by all
          show in the page header already; the rest (weather_condition /
          fault_party / recovery_involved / reason) aren't fields users
          fill for most claim types, so rendering them as 'Not Defined'
          or dashes on Life/Legal/HospitalCash views was confusing. */}
      {false && (() => {
        const ft = c.policy?.form_template
        const isMotorish = ft === 'vehicle' || ft === 'coverage_based' || !ft /* unknown → show everything */
        const baseRows: [string, React.ReactNode][] = [
          ['Claim sub-type', c.claim_sub_type || '-'],
          ['Attorney involved', yesNo(c.attorney_involved)],
          ['Date Of Claim Registered', c.registered_claim || fmtDate(c.created_at)],
          ['Type of loss', c.type_of_loss || '-'],
        ]
        const motorRows: [string, React.ReactNode][] = isMotorish ? [
          ['Recovery involved', yesNo((c as any).recovery_involved)],
          ['Which Party is at fault?', (c as any).fault_party || 'Not Defined'],
          ['Weather Condition', (c as any).weather_condition || '-'],
        ] : []
        const trailingRows: [string, React.ReactNode][] = [
          ['Claim Number', c.claim_number],
          ['Category', c.category || '-'],
          ['Status', (c.status ?? '').trim()
            ? <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium border ${statusColor}`}>{c.status}</span>
            : <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-surface-2 text-ink-faint italic border border-line">Unknown</span>],
          ['Sub Status', c.claim_sub_status || '-'],
          ['Created', fmtDate(c.created_at)],
          ['Created By', c.created_by_name || (c.created_by ? `User #${c.created_by}` : '-')],
          ['Last Updated', fmtDate(c.updated_at)],
          ['Reason', (c as any).reason || '-'],
        ]
        return (
          <Card title="Other Information">
            <DL items={[...baseRows, ...motorRows, ...trailingRows]} />
          </Card>
        )
      })()}

      {/* Accident Details (for motor claims only — vehicle form_template).
          Previously rendered whenever the API returned any accident_details
          object, which could happen on COMG/DOMG policies with motor
          coverages too. Gating on form_template keeps the Life / Legal /
          Hospital Cash views clean. */}
      {(c.policy?.form_template === 'vehicle' || ['MOTORACCIDENT','MOTORTRADERSEXTERNAL','MOTORTRADERSINTERNAL'].includes((c.claim_type || '').toUpperCase().replace(/[^A-Z0-9]/g, ''))) && (c as any).accident_details && (
        <Card title="Accident Details">
          <DL items={[
            ['Place of accident', (c as any).accident_details.place_of_accident || '-'],
            ['Time of accident', (c as any).accident_details.time_of_accident || '-'],
            ['Date of accident', fmtDate((c as any).accident_details.date_of_accident)],
            ['Detail of Accident', (c as any).accident_details.detail_of_accident || '-'],
            ['Purpose of Trip', (c as any).accident_details.purpose_of_trip || '-'],
            ['Which Party at Fault', (c as any).accident_details.fault_party || 'Not Defined'],
            ['Is Other Party Involved', ((c as any).accident_details.third_party == 1 || (c as any).accident_details.third_party == '1') ? 'Yes' : 'No'],
          ]} />
        </Card>
      )}

      {/* Motor — Driver Details (from accident_driver table). Gated on
          form_template=vehicle. */}
      {(c.policy?.form_template === 'vehicle' || ['MOTORACCIDENT','MOTORTRADERSEXTERNAL','MOTORTRADERSINTERNAL'].includes((c.claim_type || '').toUpperCase().replace(/[^A-Z0-9]/g, ''))) && c.accidentDriver && Object.keys(c.accidentDriver).length > 0 && (
        <Card title="Driver Details">
          <DL items={[
            ['Name',         c.accidentDriver.name || '-'],
            ['Mobile',       c.accidentDriver.cellphone || c.accidentDriver.contact_number || '-'],
            ['Date of Birth', fmtDate(c.accidentDriver.dob)],
            ['Address',      c.accidentDriver.address || '-'],
            ['License',      c.accidentDriver.license || '-'],
            ['Purpose',      c.accidentDriver.purpose || '-'],
          ]} />
        </Card>
      )}

      {/* Motor — Passenger Injuries (from accident_passenger_injury). */}
      {(c.policy?.form_template === 'vehicle' || ['MOTORACCIDENT','MOTORTRADERSEXTERNAL','MOTORTRADERSINTERNAL'].includes((c.claim_type || '').toUpperCase().replace(/[^A-Z0-9]/g, ''))) && c.accidentPassengers && c.accidentPassengers.length > 0 && (
        <Card title="Passenger Injuries">
          <div className="space-y-3">
            {c.accidentPassengers.map((p: any, i: number) => (
              <div key={i} className="p-3 bg-surface-2 rounded-lg">
                <DL items={[
                  ['Name',    p.name || '-'],
                  ['Address', p.address || '-'],
                  ['Injury',  p.injury || '-'],
                ]} />
              </div>
            ))}
          </div>
        </Card>
      )}

      {/* MIS — Life Claim Details (claim_life). Rendered only when the
          claim has a life row, so Accident / DOMG claims aren't bloated
          with unrelated fields. Previously the UI showed weather_condition
          and fault_party on Life claims because those came from Other
          Information — now the actual entered fields surface here. */}
      {c.lifeDetails && (
        <Card title="Life Claim Details">
          <DL items={[
            ['Date of Death',   fmtDate(c.lifeDetails.date_of_death)],
            ['Cause of Death',  c.lifeDetails.cause_of_death || '-'],
            ['Description',     c.lifeDetails.description || '-'],
            ['Death Certificate', c.lifeDetails.certificate_url
              ? <FileLink url={c.lifeDetails.certificate_url} label="View certificate" />
              : (c.lifeDetails.certificate ? 'Uploaded' : '-')],
          ]} />
        </Card>
      )}

      {/* Union legal claim (BONU / BOWASEWU) — its own dedicated form fields,
          matching the create form (no Documentation / Declaration here). */}
      {u && (
        <Card
          title={u.form_name || 'Union Legal Claim Details'}
          action={editable && !unionEdit ? (
            <button onClick={openUnionEdit} className="text-xs text-primary hover:underline">Edit</button>
          ) : null}
        >
          {unionError && <div className="mb-3 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">{unionError}</div>}
          {/* Premium paid for the claim month? From the union's monthly payment
              list (Unions › Payments). Checked before the claim is processed. */}
          {(() => {
            const ps = (c as any).unionPaymentStatus as import('../../api/claims').ClaimDetail['unionPaymentStatus']
            if (!ps) return null
            const month = (p: string) => { const [y, m] = p.split('-').map(Number); return new Date(y, m - 1, 1).toLocaleDateString(undefined, { month: 'long', year: 'numeric' }) }
            const cls = ps.paid ? 'bg-status-success-bg text-status-success-fg' : 'bg-status-danger-bg text-status-danger-fg'
            return (
              <div className={`mb-3 rounded-md px-3 py-2 text-sm ${cls}`}>
                <span className="font-semibold">Premium for {month(ps.period)}: {ps.paid ? 'PAID' : 'NOT PAID'}</span>
                {ps.paid && ps.amount != null && <span> · P{Number(ps.amount).toFixed(2)}{ps.paid_on ? ` on ${ps.paid_on}` : ''}</span>}
                <span className="ml-2 text-xs opacity-80">
                  {month(ps.previous_period)}: {ps.previous_paid ? 'paid' : 'not paid'} · {ps.proofs} proof{ps.proofs === 1 ? '' : 's'} of payment filed
                  {' '}· based on {ps.basis === 'matter_arose_date' ? 'the date the matter arose' : 'the filing date'}
                </span>
                {u?.union_id && (
                  <Link to={`/unions/${u.union_id}/payments?period=${ps.period}`} className="ml-2 text-xs underline">View payment list</Link>
                )}
                {!ps.paid && <div className="text-xs mt-1">Confirm with Accounts that this member's premium for the month has been received before processing the claim.</div>}
              </div>
            )
          })()}
          {!unionEdit ? (
            <DL items={[
              ['Policy Number',   u.policy_number || '-'],
              ['Insured Name',    u.insured_name || '-'],
              ['Region',          u.region || '-'],
              ['Omang / Passport', u.omang_passport || '-'],
              ['Cellphone / Telephone', u.cellphone || '-'],
              ['Email Address',   u.email || '-'],
              ['Claim Type',      u.claim_type || '-'],
              ['Who does the matter relate to', u.matter_relates_to || '-'],
              ...(String(u.matter_relates_to) === 'Child' ? [
                ['Child financially dependent & full-time scholar', u.child_financially_dependent == null ? '-' : (Number(u.child_financially_dependent) === 1 ? 'Yes' : 'No')],
                ['Dependent Omang / Passport', u.dependent_omang_passport || '-'],
                ['Dependent Date of Birth', fmtDate(u.dependent_dob)],
              ] as [string, React.ReactNode][] : []),
              ['Type of Matter',  u.matter_type || '-'],
              ['Date matter arose', fmtDate(u.matter_arose_date)],
              ['Proposed course of action', u.proposed_course_of_action || '-'],
            ]} />
          ) : (
            <div className="space-y-3">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                <div><div className="text-ink-faint text-xs mb-1">Policy Number</div><div className="px-3 py-2 bg-surface-2 border rounded">{u.policy_number || '-'}</div></div>
                <div><div className="text-ink-faint text-xs mb-1">Insured Name</div><div className="px-3 py-2 bg-surface-2 border rounded">{u.insured_name || '-'}</div></div>
                <label className="block"><span className="text-ink-faint text-xs">Region</span>
                  <input className="w-full mt-1 px-3 py-2 border rounded" value={unionDraft.region} onChange={e => setUnionDraft(d => ({ ...d, region: e.target.value }))} /></label>
                <label className="block"><span className="text-ink-faint text-xs">Claim Type</span>
                  <input className="w-full mt-1 px-3 py-2 border rounded" value={unionDraft.claim_type} onChange={e => setUnionDraft(d => ({ ...d, claim_type: e.target.value }))} /></label>
                <label className="block"><span className="text-ink-faint text-xs">Who does the matter relate to</span>
                  <select className="w-full mt-1 px-3 py-2 border rounded bg-surface" value={unionDraft.matter_relates_to} onChange={e => setUnionDraft(d => ({ ...d, matter_relates_to: e.target.value }))}>
                    <option value="">— select —</option>
                    {MATTER_RELATES_TO.map(o => <option key={o} value={o}>{o}</option>)}
                  </select></label>
                <label className="block"><span className="text-ink-faint text-xs">Type of Matter</span>
                  <select className="w-full mt-1 px-3 py-2 border rounded bg-surface" value={unionDraft.matter_type} onChange={e => setUnionDraft(d => ({ ...d, matter_type: e.target.value }))}>
                    <option value="">— select —</option>
                    {MATTER_TYPES.map(o => <option key={o} value={o}>{o}</option>)}
                  </select></label>
                {unionChild && (
                  <>
                    <label className="block"><span className="text-ink-faint text-xs">Child financially dependent & full-time scholar</span>
                      <select className="w-full mt-1 px-3 py-2 border rounded bg-surface" value={unionDraft.child_financially_dependent} onChange={e => setUnionDraft(d => ({ ...d, child_financially_dependent: e.target.value }))}>
                        <option value="">—</option><option value="yes">Yes</option><option value="no">No</option>
                      </select></label>
                    <label className="block"><span className="text-ink-faint text-xs">Dependent Omang / Passport</span>
                      <input className="w-full mt-1 px-3 py-2 border rounded" value={unionDraft.dependent_omang_passport} onChange={e => setUnionDraft(d => ({ ...d, dependent_omang_passport: e.target.value }))} /></label>
                    <label className="block"><span className="text-ink-faint text-xs">Dependent Date of Birth</span>
                      <input type="date" className="w-full mt-1 px-3 py-2 border rounded" value={unionDraft.dependent_dob} onChange={e => setUnionDraft(d => ({ ...d, dependent_dob: e.target.value }))} /></label>
                  </>
                )}
                <label className="block"><span className="text-ink-faint text-xs">Date matter arose</span>
                  <input type="date" className="w-full mt-1 px-3 py-2 border rounded" value={unionDraft.matter_arose_date} onChange={e => setUnionDraft(d => ({ ...d, matter_arose_date: e.target.value }))} /></label>
              </div>
              <label className="block text-sm"><span className="text-ink-faint text-xs">Proposed course of action</span>
                <textarea rows={4} className="w-full mt-1 px-3 py-2 border rounded" value={unionDraft.proposed_course_of_action} onChange={e => setUnionDraft(d => ({ ...d, proposed_course_of_action: e.target.value }))} /></label>
              <div className="flex gap-2">
                <button onClick={saveUnionEdit} disabled={unionSaving}
                  className="px-4 py-2 bg-primary text-white rounded text-sm font-medium disabled:opacity-50">{unionSaving ? 'Saving…' : 'Save'}</button>
                <button onClick={() => setUnionEdit(false)} className="px-4 py-2 border rounded text-sm hover:bg-surface-2">Cancel</button>
              </div>
            </div>
          )}
        </Card>
      )}

      {/* MIS — Legal Claim Details (claim_legal). Hidden for union legal claims
          (BONU / BOWASEWU) — those show their own dedicated card below. */}
      {c.legalDetails && !c.unionLegalClaim && (
        <Card title="Legal Claim Details">
          <DL items={[
            ['Legal Firm',       c.legalDetails.legal_firm || '-'],
            ['Lawyer Name',      c.legalDetails.lawyer_name || '-'],
            ['Legal Tel',        c.legalDetails.legal_tel || '-'],
            ['Legal Email',      c.legalDetails.legal_email || '-'],
            ['Representing Member', c.legalDetails.representing_member || '-'],
            ['Member Name',      c.legalDetails.member_name || '-'],
            ['Membership ID',    c.legalDetails.membership_id || '-'],
            ['Member Contact',   c.legalDetails.member_contact || '-'],
            ['Member Email',     c.legalDetails.member_email || '-'],
            ['Loss Reported Date', fmtDate(c.legalDetails.lossreported_date)],
            ['Matter Relates To', c.legalDetails.matter_relatesto || '-'],
            ['Matter Quantum',   c.legalDetails.matter_quantum || '-'],
            ['Jurisdiction',     c.legalDetails.jurisdiction || '-'],
            ['Course of Action', c.legalDetails.course_of_action || '-'],
          ]} />
        </Card>
      )}

      {/* MIS — Hospital Cash Claim Details (claim_hospital_cash). Most
          fields are conditional on hospitalisation_type or
          accident_reported; rendered as a flat list so operators can
          see everything entered. */}
      {c.hospitalCashDetails && (
        <Card title="Hospital Cash Claim Details">
          <DL items={[
            ['Patient Name',        c.hospitalCashDetails.patient_name || '-'],
            ['Patient DOB',         fmtDate(c.hospitalCashDetails.patient_dob)],
            ['Identity Number',     c.hospitalCashDetails.patient_identity_number || '-'],
            ['Relationship',        c.hospitalCashDetails.relationship || '-'],
            ['Occupation Date',     fmtDate(c.hospitalCashDetails.occupation_date)],
            ['GP Name',             c.hospitalCashDetails.gp_name || '-'],
            ['GP Address',          c.hospitalCashDetails.gp_postal_address || '-'],
            ['Hospital',            c.hospitalCashDetails.hospital_name || '-'],
            ['Admitting Doctor',    c.hospitalCashDetails.admitting_doctor || '-'],
            ['Admission Date/Time', `${fmtDate(c.hospitalCashDetails.admission_date)} ${c.hospitalCashDetails.admission_time || ''}`.trim() || '-'],
            ['Discharge Date/Time', `${fmtDate(c.hospitalCashDetails.discharge_date)} ${c.hospitalCashDetails.discharge_time || ''}`.trim() || '-'],
            ['Hospitalisation Type', c.hospitalCashDetails.hospitalisation_type || '-'],
            ['Hospitalisation Reason', c.hospitalCashDetails.hospitalisation_reason || '-'],
            ['Injury Date',         fmtDate(c.hospitalCashDetails.injury_date)],
            ['Accident Circumstances', c.hospitalCashDetails.accident_circumstances || '-'],
            ['Medical Scheme',      c.hospitalCashDetails.medical_scheme || '-'],
            ['Medical Scheme Name', c.hospitalCashDetails.medical_scheme_name || '-'],
            ['Medical Aid Number',  c.hospitalCashDetails.medical_aid_number || '-'],
            ['Other Insurance',     c.hospitalCashDetails.other_insurance || '-'],
            ['Other Insurance Co.', c.hospitalCashDetails.other_insurance_company_name || '-'],
          ]} />
        </Card>
      )}

      {/* Vehicle details */}
      {c.vehicleDetails && Object.keys(c.vehicleDetails).length > 0 && (
        <Card title="Vehicle Details">
          <DL items={Object.entries(c.vehicleDetails).filter(([k]) => !['id','claim_id','created_at','updated_at','deleted_at'].includes(k)).map(([k, v]) => [
            k.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()),
            String(v ?? '-'),
          ])} />
        </Card>
      )}

      {/* DOMG/COMG claim-type-specific payload.
          Backend pulls this from the legacy sub-table (business_interruption,
          burglary, fidelity_guarantee, property_loss_damage, public_liability,
          workers_compensation, goods_in_transit, all_risk_and_electronic_equipment,
          fire, travel_ins, mobile_and_electronic_devices) keyed by
          newclaim_id. Renders as a generic key/value list so new columns
          added server-side show up automatically without a FE change. */}
      {c.subClaimData && Object.keys(c.subClaimData).length > 0 && (
        <Card
          title={`${(c.claim_type || 'Claim')} — Details`}
          action={editable ? (
            editingSection === 'subclaim' ? (
              <>
                <button type="button" onClick={cancelSectionEdit}
                  className="px-3 py-1.5 text-xs font-medium border border-line rounded-md bg-surface hover:bg-surface-2 text-ink-muted">
                  Cancel
                </button>
                <button type="button" onClick={saveSectionEdit} disabled={sectionSaving}
                  className="px-3 py-1.5 text-xs font-medium rounded-md bg-primary text-white hover:bg-primary disabled:opacity-50">
                  {sectionSaving ? 'Saving…' : 'Save'}
                </button>
              </>
            ) : (
              <button type="button" onClick={() => openSectionEdit('subclaim')}
                disabled={editingSection === 'classification'}
                className="px-3 py-1.5 text-xs font-medium border border-line rounded-md bg-surface hover:bg-surface-2 text-ink-muted disabled:opacity-50">
                Edit
              </button>
            )
          ) : null}
        >
          {editingSection === 'subclaim' ? (
            renderSubClaimEditForm({
              claimType: c.claim_type,
              draft: sectionDraft,
              setDraft: setSectionDraft,
              error: sectionError,
              copyOfContractUrl: (c.subClaimData as any)?.copy_of_contract_url,
              copyOfContractPath: (c.subClaimData as any)?.copy_of_contract,
              files: sectionFiles,
              setFiles: setSectionFiles,
              plateOpts: platesForTerm,
            })
          ) : (
            (() => {
              // Friendly labels for sub-table columns whose snake_case form
              // doesn't pretty-print well (compound words, abbreviations,
              // V8 question-style labels). Anything not listed here falls
              // through to the auto-formatter below.
              const LABEL_OVERRIDES: Record<string, string> = {
                // All Risks family
                stolenfromcar_unlockedpremises: 'Was the property stolen from a car or unlocked premises?',
                loss_cause: 'Have you ever before sustained previous loss?',
                loss_by_other_cause: 'Other cause (please provide details)',
                is_sole_owner_of_property: 'Are you the sole owner of the property?',
                sole_owner_of_property: 'Name of the owner',
                thorough_search_made_for_article: 'Has a thorough search been made for the article(s)?',
                property_stolen_damaged: 'Has the property been stolen or damaged?',
                // Burglary / Theft / Money
                address_of_premises: 'Address of premises where theft occurred',
                date_time_police_advised: 'Date the police were advised of loss',
                anyone_on_premises: 'Was anyone at the premises during the burglary?',
                anyone_on_premises_brief: 'Details in brief',
                guarded_by_watchman: 'Is the premises guarded by a watchman?',
                premises_properly_secured: 'Were all means of access properly secured at the time of theft?',
                total_value_contents_of_premises: 'Total value of contents at time of theft',
                stock_books_records_located: 'Where were stock books / records located at time of theft?',
                // Workers Compensation / Stated Benefits — V8 question-style
                // labels (workers_compensation table). injured_address /
                // injured_name etc. otherwise auto-prettify to "Injured
                // Address" which loses the "Injured Person" context.
                injured_name: 'Injured Person — Name',
                injured_age: 'Injured Person — Age',
                injured_address: 'Injured Person — Address',
                injured_status: 'Injured Person — Status',
                injured_occupation: 'Injured Person — Normal Occupation',
                injured_nationality: 'Injured Person — Nationality',
                injured_service_period: 'Injured Person — Period of service',
                your_direct_employ: 'Is he/she in your direct employ?',
                address_of_contractor: 'Name and address of Contractor',
                date: 'Accident Date',
                time: 'Accident Time',
                place: 'Accident Place',
                how_accident_occur: 'How did the accident occur?',
                first_report_accident: 'When and to whom did he/she first report the accident?',
                period_of_disablement: 'Probable period of disablement',
                // Defective Workmanship (defective_workmanship). owners_name
                // etc. otherwise auto-prettify to "Owners Name" without the
                // apostrophe; stick to V8 question-style labels.
                location_of_accident: 'Location of accident / incident',
                accident_date_time: 'Accident Date Time',
                owners_name: "Owner's Name",
                telephone_number: 'Telephone Number',
                mobile_number: 'Mobile Number',
                make: 'Vehicle Make',
                model: 'Vehicle Model',
                registration: 'Vehicle Registration',
                vehicle_drivable: 'Is the vehicle drivable?',
                vehicle_handed_claimant: 'Was the vehicle handed to the claimant?',
                when_vehicle_handed: 'When was the vehicle handed?',
                allegations_received: 'Date and allegations received from claimant',
                // Mobile/Electronic Devices + Office Contents
                // (mobile_and_electronic_devices_claim) — V8 labels.
                insured_name: "Insured's Name",
                email_address: 'E-mail Address',
                telephone_no: 'Telephone No',
                date_time_loss_discovered: 'Date and time when loss/damage was discovered',
                whom_discovered: 'By whom discovered?',
                // Fidelity Guarantee
                defaulting_employees_name: 'Defaulting Employees (Name — Position)',
                employees_been_involved: 'Have employees been involved in or suspected of any previous loss?',
                circumstances: 'Full details of circumstances of the loss and how it was discovered',
                // Contractors All Risks / Public Liability
                // (contractors_all_risks_public_liability) — V8 labels
                // from contractors_all_risks_public_liability.blade.php.
                // `responsible_person_*` is shared with Erection All Risk /
                // Plant All Risks — the labels work for all three since
                // V8 uses the same "Responsible Person on Site" framing.
                responsible_person_name: 'Responsible Person — Name',
                responsible_person_phone: 'Responsible Person — Phone',
                responsible_person_cellphone: 'Responsible Person — Cellphone',
                responsible_person_email: 'Responsible Person — Email',
                responsible_person_fax: 'Responsible Person — Fax',
                parties_to_contract: 'Parties to the Contract',
                contract_value: 'Contract Value',
                contract_number: 'Contract Number',
                description_of_contract: 'Description of Contract',
                site_physical_address: 'Site Physical Address',
                contract_commencement_date: 'Contract Commencement Date',
                expected_contract_completion_date: 'Expected Contract Completion Date',
                responsible_contract_works_claim: 'Responsible for arranging Project Insurance (Contract Works)?',
                responsible_public_liability_claim: 'Responsible for arranging Public Liability Insurance?',
                loss_date: 'Date of Loss / Damage',
                loss_time: 'Time',
                loss_details: 'Details of Loss / Damage',
                cause_of_loss: 'Cause of Loss / Damage',
                party_responsible_name: 'Party Responsible — Name',
                party_responsible_contact: 'Party Responsible — Contact',
                estimated_cost_of_repair_replacement: 'Estimated Cost of Repair / Replacement',
                works_claim_documentary_evidence: 'Documentary Evidence',
                works_claim_bill_of_quantities: 'Bill of Quantities',
                police_station: 'Police Station',
                police_reference: 'Police Reference',
                // Property Loss / Damage (property_loss_damage) — V8 labels
                // from property_loss_damage.blade.php. `previously_suffered_loss`
                // and `other_insurance_covering` overrides defined here are
                // shared with the Business Interruption Yes/No flow — both
                // map to user-friendly question prompts, so the same label
                // works for either table's render path.
                loss_damage_discovered: 'When was loss/damage discovered?',
                loss_damage_occurred: 'Place where loss/damage occurred',
                premises_occupied: 'Were premises occupied? By whom?',
                last_occupied: 'If not occupied, when last occupied?',
                purpose_of_occupation: 'Purpose of occupation',
                nature_interruption: 'Nature of your interruption',
                loss_for_each_item: 'Details & estimated amount of loss for each item',
                previously_suffered_loss: 'Have you previously suffered loss/damage?',
                give_details: 'If so, give details',
                name_of_insurer: 'If insured, provide name of insurer',
                reference_no_station: 'Police reference number, station and date reported',
                interest_insured_property: 'Any other party with an interest in the insured property?',
                other_insurance_covering: 'Any other insurance covering this loss/damage?',
                give_name_insurer: 'If so, give name of insurer',
                value_all_property: 'Estimated total value of all property insured',
                when_last_valued: 'When last valued?',
                // Public Liability / Liability (public_liability).
                // `insured_name` and `insured_treding_name` use V8's "Insured —"
                // prefix to avoid colliding with Mobile/Electronic Devices'
                // "Insured's Name" label (which is also shared).
                insured_treding_name: "Insured — Business or Trading name",
                insured_postal_address: 'Insured — Postal Address',
                insured_email: 'Insured — Email address',
                insured_telephone_no: 'Insured — Telephone no',
                insured_facsimile: 'Insured — Facsimile',
                insured_mobile_no: 'Insured — Mobile no',
                accident_date: 'Accident Date',
                accident_time: 'Accident Time',
                accident_incident: 'Location of accident / incident',
                accident_injuries: 'Damaged property and/or injuries suffered',
                accident_liability: 'Have you admitted responsibility / liability?',
                accident_person: 'Product/service-related claim?',
                accident_contacted: 'Were emergency services contacted?',
                attach_contractor: 'Are you the head contractor? If not, who is?',
                attach_employee: 'Was anyone other than yourself or employee involved?',
                attach_employed: 'Names, addresses and by whom employed',
                attach_blame: 'You or your employee(s) was to blame?',
                attach_circumstances: 'Other accidents under similar circumstances?',
                attach_property: 'Was there any damage to property?',
                attach_details: 'Damage details',
                attach_owner: 'Name and address of property owner',
                attach_damage: 'Damage',
                claim_name: 'Claimant — Name',
                claim_telephone: 'Claimant — Telephone',
                claim_mobile: 'Claimant — Mobile no',
                claim_postal: 'Claimant — Postal address',
                claim_solicitor: "Claimant — Solicitor's name",
                witness1_name: 'Witness 1 — Name',
                witness1_telephone: 'Witness 1 — Telephone no',
                witness1_mobile: 'Witness 1 — Mobile no',
                witness1_postal: 'Witness 1 — Postal address',
                witness1_relationship: 'Witness 1 — Relationship',
                witness2_name: 'Witness 2 — Name',
                witness2_telephone: 'Witness 2 — Telephone no',
                witness2_mobile: 'Witness 2 — Mobile no',
                witness2_postal: 'Witness 2 — Postal address',
                witness2_relationship: 'Witness 2 — Relationship',
                witness2_damage: 'Witness 2 — Any damage to property?',
                // Fire (fire_claim)
                address_of_theft_occurred: 'Address of premises where fire occurred',
                date_time_of_theft: 'Date and time of fire',
                anyone_during_burglary: 'Was anyone at the premises during the loss?',
                details_during_burglary: 'Details in brief',
                days_premises_unoccupied: 'Days premises unoccupied in past 12 months',
                premises_guarded_by_watchman: 'Is the premises guarded by a watchman?',
                name_of_guard: 'Name of guard',
                telephone_of_guard: 'Telephone number of guard',
                guard_during_fire: 'Where was the guard during the fire?',
                name_of_security_agent: 'Name of security agent',
                contract_of_agreement: 'Contract of agreement',
                suspect_any_person: 'Do you suspect any person?',
                suspect_person_details: 'Details of suspect person',
                total_value_premises_buildings: 'Total value of the buildings at time of loss',
                other_insurance_against_fire: 'Any other insurances against fire on the same property?',
                insurance_against_fire_details: 'Details of other insurances against fire',
                estimated_amount_of_damaged: 'Estimated amount of damaged property',
                details_of_previous_loss: 'Details of previous losses',
                // Erection All Risk (erection_all_risk_claims) — V8 labels
                // from erection_all_risk.blade.php. `insured_email` and
                // `insured_name` are also referenced by Public Liability /
                // Mobile labels above, but those rendered claim types do not
                // overlap with EAR at runtime so the question-style override
                // here is fine (V8 EAR blade frames them as Section A details).
                insured_occupation: 'Occupation of the Insured',
                period_from: 'Period of Insurance — From',
                period_to: 'Period of Insurance — To',
                supervisor_engineer_name: 'Name of Supervisor Engineer',
                date_of_occurrence: 'Date of Occurrence',
                time_of_occurrence: 'Time of Occurrence',
                site_of_damage: 'Site of Damage',
                nearest_railway_station: 'Nearest Railway Station',
                damage_contract_works: 'Damage to Contract Works',
                damage_plant_equipment: 'Damage to Construction Plant / Equipment',
                damage_third_party_property: 'Damage to Third Party Property',
                cause_of_damage: 'Cause of Damage',
                responsible_for_damage: 'Anyone responsible for the damage?',
                responsible_for_damage_details: 'If so, give details',
                possibility_of_recovery: 'Possibility of recovery from third party?',
                recovery_details: 'If yes, give details',
                how_damage_occurred: 'Describe how the damage occurred',
                probable_cause: 'Probable cause of damage',
                progress_of_construction: 'Progress of construction at time of damage',
                how_items_repaired: 'How are the items being repaired?',
                alterations_during_repairs: 'Any alterations during repairs?',
                witness_name: 'Witness — Name',
                witness_address: 'Witness — Address',
                surrounding_properties_damaged: 'Were surrounding properties damaged?',
                third_party_liability: 'Any third party liability?',
                third_party_liability_details: 'Third party liability details',
                estimated_cost_contract_works: 'Estimated Cost — Contract Works',
                estimated_cost_plant_machinery: 'Estimated Cost — Construction Plant / Machinery',
                estimated_cost_third_party_property: 'Estimated Cost — Third Party Property',
                estimated_cost_owners_surrounding: "Estimated Cost — Owner's Surrounding Property",
                other_insurance_details: 'Details of Other Insurances',
                previous_losses_details: 'Details of Previous Losses',
                // Travel Insurance (travel_insurance_claim) — V8 labels from
                // travel_insurance.blade.php. Many keys (insured_name, email,
                // mobile, telephone, address, phone_number, policy_number,
                // home_address, dob, surname, forename, post_code, passport_no,
                // nationality) are TI-only but auto-prettify acceptably, so
                // only the question-style / disambiguating ones are overridden
                // here. `address` is shared with Defective Workmanship and
                // Mobile/Electronic Devices but never coexists on the same
                // claim with TI, so the override applies cleanly.
                policy_number: 'Policy Number',
                issued_by: 'Issued by (Insurance Company)',
                issued_on: 'Issued on',
                valid_from: 'Valid from',
                valid_to: 'Valid to',
                beneficiary: 'Beneficiary (if different from Insured)',
                bank_name: 'Bank Name',
                bank_address: 'Bank Address',
                account_number: 'Account Number',
                iban: 'IBAN',
                swift_code: 'SWIFT Code',
                bic_code: 'BIC Code',
                other_insurance_policy: 'Do you have any other Insurance Policy?',
                name_insurance_company: 'Name of the (other) Insurance Company',
                phone_number: 'Phone Number',
                type_of_refund: 'Type of Refund',
                type_of_refund_other: 'Type of Refund — Other',
                compulsory_doc_proof_of_residence: 'Proof of residence in the Country where the Policy was issued',
                compulsory_doc_claim_form: 'Claim form duly completed',
                compulsory_doc_insurance_policy: 'Copy of Insurance Policy',
                compulsory_doc_detailed_letter: 'Detailed letter explaining the loss',
                compulsory_doc_receipts: 'ORIGINAL official Receipts of ALL incurred costs',
                compulsory_doc_passport_copy: "Copy of insured's passport (FIRST page + exit/entry dates)",
                medical_dental_care_doc_1: 'Medical Expenses — Medical report with admission medical clinic',
                medical_dental_care_doc_2: 'Medical Expenses — Clinical and/or Laboratory Results',
                medical_dental_care_doc_3: 'Medical Expenses — Bank Account Information',
                claim_delayed_luggage_doc_1: 'Delayed Luggage — Property Irregularity Report issued by the Carrier',
                claim_delayed_luggage_doc_2: 'Delayed Luggage — Incident Report from Client',
                claim_delayed_luggage_doc_3: 'Delayed Luggage — Original receipts for basic necessity items bought',
                claim_loss_personal_doc_doc_1: 'Loss of Personal Documents — Statement of Loss (Police report)',
                claim_loss_personal_doc_doc_2: 'Loss of Personal Documents — Receipts of document replacement incurred costs',
                claim_lost_luggage_doc_1: 'Lost Luggage — Property Irregularity Report issued by the Carrier',
                claim_lost_luggage_doc_2: 'Lost Luggage — Certificate of lost luggage issued by the Carrier',
                claim_lost_luggage_doc_3: 'Lost Luggage — Copy of the Carrier settlement/reimbursement form',
                claim_lost_luggage_doc_4: 'Lost Luggage — Incident Report from Client',
                claim_trip_cancel_doc_1: 'Trip Cancellation — List of the services hired for the trip',
                claim_trip_cancel_doc_2: 'Trip Cancellation — Conditions and proof of cancellation of the said services',
                claim_trip_cancel_doc_3: 'Trip Cancellation — Certificate of non-refundable costs',
                claim_trip_cancel_doc_4: 'Trip Cancellation — Payment receipts of the hired services for the trip',
                claim_delayed_flight_doc_1: 'Delayed Flight — Certificate Issued by the Carrier',
                claim_delayed_flight_doc_2: 'Delayed Flight — Copy of original travel ticket',
                claim_delayed_flight_doc_3: 'Delayed Flight — Copy of replacement ticket indicating the paid amount',
                // Professional Indemnity (professional_indemnity_claims) —
                // V8 labels from professional_indemnity.blade.php. Several
                // shorter keys (designation, contact_person, etc.) auto-
                // prettify acceptably so only question-style ones are
                // overridden. `insured_email` clashes with Public Liability's
                // "Insured — Email address"; since PI never coexists on the
                // same claim with PL, the PI-friendly label below applies
                // when the View renders PI (which it does via subClaimData
                // from the PI sub-table).
                type_of_business: 'Type of Business',
                contact_person: 'Contact Person',
                designation: 'Designation',
                insured_cell_tel: 'Cell / Tel Number',
                claimant_type: 'Is the claimant a Business or Individual?',
                claimant_name_surname: 'Claimant — Name & Surname',
                claimant_email: 'Claimant — E-mail Address',
                claimant_cell_tel: 'Claimant — Cell / Tel Number',
                insured_retained_to_do: 'What was the insured retained/contracted to do?',
                contract_in_place: 'Was there a contract in place?',
                contract_copy: 'Copy of contract',
                contract_no_details: 'Contract — Provide details',
                work_performed_date: 'When was the work performed?',
                person_performed_work: 'Person who performed the work',
                // `circumstances` is already overridden above (Fidelity
                // Guarantee — "Full details of circumstances of the loss
                // and how it was discovered"). The PI blade frames it as
                // "Circumstances giving rise to the claim" but the
                // Fidelity label is close enough — keep the existing
                // override to avoid duplicate-key.
                first_aware_date: 'When did the insured first become aware of the claim?',
                reason_for_reporting: 'Reason for reporting the incident',
                notification_purposes_only: 'Reported for notification purposes only?',
                verbal_written_demand: 'Received a verbal/written demand for compensation?',
                demand_received_date: 'Date demand received',
                served_with_summons: 'Has the insured been served with a Summons?',
                summons_served_date: 'Date Summons served',
                attorney_appointed: 'Has the insured appointed an Attorney/Loss Adjustor?',
                attorney_details: 'Attorney/Loss Adjustor details',
                amount_claimed: 'Amount claimed',
                own_investigation: 'Has the insured conducted their own investigation?',
                investigation_findings: 'Investigation findings',
                views_on_liability: "Insured's views/comments on Liability",
                views_on_amount_claimed: "Insured's views/comments on Amount Claimed",
                additional_details: 'Additional details to notify insurer',
                // Plant All Risks (plant_all_risks_claims) — V8 labels from
                // plant_all_risks.blade.php. `responsible_person_*`,
                // `site_physical_address`, `party_responsible_*`,
                // `cause_of_loss`, `police_station`, `police_reference`
                // overrides are already defined above (shared with CARPL).
                site_code: 'Code',
                item_description: 'Item of Plant Stolen/Damaged (full description / model / serial number)',
                item_number_sum_insured: 'Item Number on Policy Schedule / Sum Insured',
                date_of_loss: 'Date of Loss / Damage',
                time_of_loss: 'Time',
                details_of_loss: 'Details of Loss / Damage',
                estimated_cost: 'Estimated Cost of Repair / Replacement',
                uneconomical_to_repair: 'Is the unit uneconomical to repair / write off?',
                subject_to_finance: 'Is the unit subject to Finance / Hire Purchase?',
                on_hire_at_time: 'Was the unit on hire at time of accident / theft?',
                // Medical Malpractice (medical_malpractice_claims) — V8
                // labels from medical_malpractice.blade.php. `insured_email`
                // override is already defined above (Public Liability).
                insured_full_name: 'Full Name of Insured',
                professional_title_role: 'Professional Title / Role',
                license_registration_number: 'License or Registration Number',
                facility_practice_name: 'Facility / Practice Name',
                address_of_practice: 'Address of Practice',
                insured_contact_number: 'Contact Number',
                claimant_full_name: 'Claimant — Full Name',
                claimant_date_of_birth: 'Claimant — Date of Birth',
                claimant_contact_number: 'Claimant — Contact Number',
                claimant_mailing_address: 'Claimant — Mailing Address',
                date_of_alleged_incident: 'Date of Alleged Incident',
                nature_of_services_provided: 'Nature of Services Provided',
                date_of_notification: 'Date of Notification of Allegation',
                how_notified: 'How were you notified? (letter, legal notice)',
                description_of_allegation: 'Detailed Description of Allegation',
                notification_letter: 'Notification letter / legal document',
                patient_records: 'Patient records and treatment notes',
                investigation_reports: 'Internal investigation reports',
                correspondence: 'Correspondence with claimant',
                expert_legal_opinions: 'Expert or legal opinions',
                // Locks & Keys / Key Loss (key_loss_claim) — V8 active
                // labels + V2 extensions. `registered_claim` clashes with
                // a top-level new_claims label but the meaning matches.
                purpose: 'Purpose of use',
                lossDate: 'Date of Loss / Stolen / Damage',
                descriptionofLoss: 'Description',
                key_reason: 'Is the key lost, damaged or stolen?',
                third_party_insured_elsewhere: 'Third party is insured elsewhere?',
                driver_as_insured: 'Driver as the insured',
                event_name: 'Event Name',
                police_affidavit: 'Police Affidavit',
                // Full V8 key-loss form extras.
                financial_interest: 'Financial Interest',
                chassis_num: 'Chassis Number',
                estimate: 'Replacement Estimate',
                name_of_insured: 'Name of Insured',
                insured_address: 'Address',
                insured_contact_no: 'Contact No',
                is_imported: 'Is Imported?',
                // Glass / Windscreen (glass_claim) — V8 labels plus V2
                // extensions. `cause` clashes only with EAR's
                // cause_of_damage (different key), so this override is
                // safe.
                incident_date: 'Date of Damage',
                extent: 'Damage Extent',
                cause: 'Cause of Damage',
                damage_location: 'Damage Location',
                company_1: 'Quote 1 — Company',
                amount_quote_1: 'Quote 1 — Amount',
                quote_1: 'Quote 1 Document',
                company_2: 'Quote 2 — Company',
                amount_quote_2: 'Quote 2 — Amount',
                quote_2: 'Quote 2 Document',
                incidentFront: 'Front Side Photo',
                incidentBack: 'Back Side Photo',
                incidentRight: 'Right Side Photo',
                incidentLeft: 'Left Side Photo',
                front_image_description: 'Front — Description',
                back_image_description: 'Back — Description',
                right_image_description: 'Right — Description',
                left_image_description: 'Left — Description',
              }
              return (
            <DL items={Object.entries(c.subClaimData)
              .filter(([k]) => !['id', 'newclaim_id', 'policyNumber', 'claim_sub_type_id',
                'created_at', 'updated_at', 'deleted_at',
                // Hide the *_url helper fields the backend attaches alongside
                // a path field — the path itself renders as a link below.
                'copy_of_contract_url',
                'contract_of_agreement_url',
                'works_claim_documentary_evidence_url',
                'works_claim_bill_of_quantities_url',
                // Travel Insurance — 25 *_url helper fields attached by
                // show(); the path columns themselves render as clickable
                // links via the generic *_url-pair branch below.
                'compulsory_doc_proof_of_residence_url',
                'compulsory_doc_claim_form_url',
                'compulsory_doc_insurance_policy_url',
                'compulsory_doc_detailed_letter_url',
                'compulsory_doc_receipts_url',
                'compulsory_doc_passport_copy_url',
                'medical_dental_care_doc_1_url',
                'medical_dental_care_doc_2_url',
                'medical_dental_care_doc_3_url',
                'claim_delayed_luggage_doc_1_url',
                'claim_delayed_luggage_doc_2_url',
                'claim_delayed_luggage_doc_3_url',
                'claim_loss_personal_doc_doc_1_url',
                'claim_loss_personal_doc_doc_2_url',
                'claim_lost_luggage_doc_1_url',
                'claim_lost_luggage_doc_2_url',
                'claim_lost_luggage_doc_3_url',
                'claim_lost_luggage_doc_4_url',
                'claim_trip_cancel_doc_1_url',
                'claim_trip_cancel_doc_2_url',
                'claim_trip_cancel_doc_3_url',
                'claim_trip_cancel_doc_4_url',
                'claim_delayed_flight_doc_1_url',
                'claim_delayed_flight_doc_2_url',
                'claim_delayed_flight_doc_3_url',
                // Professional Indemnity — 2 *_url helper fields.
                'contract_copy_url',
                'investigation_findings_url',
                // Medical Malpractice — 5 *_url helper fields for the
                // Section-4 supporting document uploads.
                'notification_letter_url',
                'patient_records_url',
                'investigation_reports_url',
                'correspondence_url',
                'expert_legal_opinions_url',
                // Glass / Windscreen — 6 *_url helper fields (4 photos
                // + 2 replacement quotes).
                'incidentFront_url', 'incidentBack_url',
                'incidentRight_url', 'incidentLeft_url',
                'quote_1_url', 'quote_2_url',
                // Locks & Keys — 1 *_url helper field for the police_affidavit.
                'police_affidavit_url',
                // Legacy V8-combined column — V2 uses separate
                // party_responsible_name + party_responsible_contact.
                'party_responsible_name_contact',
              ].includes(k))
              // NOTE: no empty-value filter — every field of the sub-claim
              // form is shown even when blank (rendered as a dash below), so
              // the operator always sees the complete field set for the claim
              // type, matching the always-on Classification & Allocation block.
              .map(([k, v]): [string, React.ReactNode] => [
                LABEL_OVERRIDES[k] ?? k.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()),
                // Pretty-print defaulting_employees_name (JSON map of name
                // → position) and similar JSON-in-text fields.
                (() => {
                  // Empty / not-yet-entered fields still render — as a dash —
                  // so the full field set is always visible in the view.
                  if (v === null || v === undefined || v === '') return '-'
                  // Yes/No display for known boolean-like toggle columns
                  // stored as 0/1 ints in the legacy sub-tables (GIT,
                  // Burglary, etc.). Without this map the UI shows raw
                  // "1" / "0" which operators can't reason about.
                  const yesNoKeys = new Set([
                    'is_carrier_contracted', 'carrier_has_own_GIT_ins',
                    'other_insurance_against_theft',
                    'anyone_on_premises', 'guarded_by_watchman',
                    'premises_properly_secured', 'employees_been_involved',
                    'property_repairable', 'previously_suffered_loss',
                    // V8 stores other_party_interest as TEXT (free-text, e.g.
                    // "credit agreement"), not boolean — do NOT yes/no map it.
                    'other_insurance_covering',
                    // All Risk / Electronic Equipment / Personal All Risks
                    // (all_risk_and_electronic_equipment) — Yes/No fields.
                    'thorough_search_made_for_article',
                    'stolenfromcar_unlockedpremises',
                    'is_sole_owner_of_property',
                    // Workers Compensation / Stated Benefits
                    'your_direct_employ',
                    // Defective Workmanship
                    'vehicle_drivable', 'vehicle_handed_claimant',
                    // Fire (fire_claim)
                    'anyone_during_burglary', 'premises_guarded_by_watchman',
                    'suspect_any_person', 'other_insurance_against_fire',
                    // Public Liability / Liability — V8 stores these as
                    // 'YES'/'NO' strings (not int 1/0). The mapper below
                    // also handles the uppercase string form.
                    'accident_liability', 'accident_person', 'accident_contacted',
                    // Contractors All Risks / PL
                    'responsible_contract_works_claim', 'responsible_public_liability_claim',
                    // Erection All Risk (erection_all_risk_claims) — five
                    // boolean toggles. `third_party_liability` is shared with
                    // the Public Liability flow but always renders Yes/No there
                    // too, so the same key works for both.
                    'responsible_for_damage', 'possibility_of_recovery',
                    'alterations_during_repairs', 'surrounding_properties_damaged',
                    'third_party_liability',
                    // Professional Indemnity (professional_indemnity_claims) —
                    // six boolean toggle columns stored as tinyInt 0/1.
                    'contract_in_place', 'notification_purposes_only',
                    'verbal_written_demand', 'served_with_summons',
                    'attorney_appointed', 'own_investigation',
                    // Plant All Risks (plant_all_risks_claims) — three
                    // boolean toggle columns stored as tinyInt 0/1.
                    'uneconomical_to_repair', 'subject_to_finance',
                    'on_hire_at_time',
                  ])
                  if (yesNoKeys.has(k)) {
                    const s = String(v)
                    if (s === '1' || s === 'true' || s.toUpperCase() === 'YES') return 'Yes'
                    if (s === '0' || s === 'false' || s.toUpperCase() === 'NO') return 'No'
                  }
                  // All Risk binary fields with bespoke labels (NOT Yes/No).
                  // V8 all_risk.blade.php uses these custom radio captions —
                  // mirror them so the read view matches the create form.
                  if (k === 'property_stolen_damaged') {
                    const s = String(v)
                    if (s === '1') return 'Damaged'
                    if (s === '0') return 'Stolen'
                  }
                  if (k === 'loss_cause') {
                    const s = String(v)
                    if (s === '1') return 'Loss by theft'
                    if (s === '0') return 'Loss by other cause'
                  }
                  // GIT contract upload — render as a clickable link to the
                  // CDN-resolved URL the backend attaches in show(). The
                  // basename is what the operator originally uploaded, so
                  // it's the friendlier label.
                  if (k === 'copy_of_contract' && typeof v === 'string' && v) {
                    const url = (c.subClaimData as any)?.copy_of_contract_url || ''
                    const filename = v.split('/').pop() || 'View Contract'
                    return url
                      ? <a href={url} target="_blank" rel="noopener noreferrer" className="text-primary hover:underline break-all">{filename}</a>
                      : filename
                  }
                  // Fire contract_of_agreement — same pattern as GIT.
                  if (k === 'contract_of_agreement' && typeof v === 'string' && v) {
                    const url = (c.subClaimData as any)?.contract_of_agreement_url || ''
                    const filename = v.split('/').pop() || 'View Contract'
                    return url
                      ? <a href={url} target="_blank" rel="noopener noreferrer" className="text-primary hover:underline break-all">{filename}</a>
                      : filename
                  }
                  // Contractors All Risks — two file upload columns.
                  if ((k === 'works_claim_documentary_evidence' || k === 'works_claim_bill_of_quantities')
                      && typeof v === 'string' && v) {
                    const urlKey = `${k}_url`
                    const url = (c.subClaimData as any)?.[urlKey] || ''
                    const filename = v.split('/').pop() || 'View File'
                    return url
                      ? <a href={url} target="_blank" rel="noopener noreferrer" className="text-primary hover:underline break-all">{filename}</a>
                      : filename
                  }
                  // Travel Insurance — 25 file upload columns. Mirrors the
                  // CARPL pattern: each column's *_url sibling is attached
                  // by show(); render the basename as a clickable link.
                  if (typeof v === 'string' && v && (
                    k.startsWith('compulsory_doc_') ||
                    k.startsWith('medical_dental_care_doc_') ||
                    k.startsWith('claim_delayed_luggage_doc_') ||
                    k.startsWith('claim_loss_personal_doc_doc_') ||
                    k.startsWith('claim_lost_luggage_doc_') ||
                    k.startsWith('claim_trip_cancel_doc_') ||
                    k.startsWith('claim_delayed_flight_doc_')
                  )) {
                    const url = (c.subClaimData as any)?.[`${k}_url`] || ''
                    const filename = v.split('/').pop() || 'View Document'
                    return url
                      ? <a href={url} target="_blank" rel="noopener noreferrer" className="text-primary hover:underline break-all">{filename}</a>
                      : filename
                  }
                  // Professional Indemnity — two file upload columns.
                  if ((k === 'contract_copy' || k === 'investigation_findings')
                      && typeof v === 'string' && v) {
                    const url = (c.subClaimData as any)?.[`${k}_url`] || ''
                    const filename = v.split('/').pop() || 'View Document'
                    return url
                      ? <a href={url} target="_blank" rel="noopener noreferrer" className="text-primary hover:underline break-all">{filename}</a>
                      : filename
                  }
                  // Medical Malpractice — five Section-4 file upload columns.
                  if ((k === 'notification_letter' || k === 'patient_records'
                       || k === 'investigation_reports' || k === 'correspondence'
                       || k === 'expert_legal_opinions')
                      && typeof v === 'string' && v) {
                    const url = (c.subClaimData as any)?.[`${k}_url`] || ''
                    const filename = v.split('/').pop() || 'View Document'
                    return url
                      ? <a href={url} target="_blank" rel="noopener noreferrer" className="text-primary hover:underline break-all">{filename}</a>
                      : filename
                  }
                  // Locks & Keys — single police_affidavit upload.
                  if (k === 'police_affidavit' && typeof v === 'string' && v) {
                    const url = (c.subClaimData as any)?.police_affidavit_url || ''
                    const filename = v.split('/').pop() || 'View Affidavit'
                    return url
                      ? <a href={url} target="_blank" rel="noopener noreferrer" className="text-primary hover:underline break-all">{filename}</a>
                      : filename
                  }
                  // Glass / Windscreen — 4 photo paths + 2 quote document
                  // paths. Render the basename as a clickable link.
                  if ((k === 'incidentFront' || k === 'incidentBack'
                       || k === 'incidentRight' || k === 'incidentLeft'
                       || k === 'quote_1' || k === 'quote_2')
                      && typeof v === 'string' && v) {
                    const url = (c.subClaimData as any)?.[`${k}_url`] || ''
                    const filename = v.split('/').pop() || 'View Document'
                    return url
                      ? <a href={url} target="_blank" rel="noopener noreferrer" className="text-primary hover:underline break-all">{filename}</a>
                      : filename
                  }
                  if (typeof v === 'string' && v.startsWith('{')) {
                    try {
                      const parsed = JSON.parse(v)
                      if (parsed && typeof parsed === 'object') {
                        return Object.entries(parsed)
                          .map(([name, pos]) => `${name}${pos ? ` — ${pos}` : ''}`)
                          .join(', ')
                      }
                    } catch { /* fall through */ }
                  }
                  return String(v)
                })(),
              ])} />
              )
            })()
          )}
        </Card>
      )}

      {/* Classification & Allocation — DOM/COM common fields surfaced
          from new_claims / claim_accidents merged in show(). Always
          rendered (view + edit) so operators can open it and fill in the
          fields even when the claim has no classification data yet. */}
      {(() => {
        const items = buildClassificationAllocationItems(c, createData, claimSubTypes)
        return (
          <Card
            title="Classification & Allocation"
            action={editable ? (
              editingSection === 'classification' ? (
                <>
                  <button type="button" onClick={cancelSectionEdit}
                    className="px-3 py-1.5 text-xs font-medium border border-line rounded-md bg-surface hover:bg-surface-2 text-ink-muted">
                    Cancel
                  </button>
                  <button type="button" onClick={saveSectionEdit} disabled={sectionSaving}
                    className="px-3 py-1.5 text-xs font-medium rounded-md bg-primary text-white hover:bg-primary disabled:opacity-50">
                    {sectionSaving ? 'Saving…' : 'Save'}
                  </button>
                </>
              ) : (
                <button type="button" onClick={() => openSectionEdit('classification')}
                  disabled={editingSection === 'subclaim'}
                  className="px-3 py-1.5 text-xs font-medium border border-line rounded-md bg-surface hover:bg-surface-2 text-ink-muted disabled:opacity-50">
                  Edit
                </button>
              )
            ) : null}
          >
            {editingSection === 'classification' ? (
              renderClassificationEditForm({
                draft: sectionDraft,
                setDraft: setSectionDraft,
                createData,
                riskAddresses: policyRiskAddresses,
                claimSubTypes,
                policyActionOpts,
                claimType: c.claim_type,
                claimTypeOpts,
                error: sectionError,
                lossDateLocked: (c.status ?? '').trim() === 'Approved',
              })
            ) : items.length > 0 ? (
              <DL items={items} />
            ) : (
              <p className="text-sm text-ink-faint italic">
                No classification &amp; allocation details captured yet.{editable ? ' Click Edit to add them.' : ''}
              </p>
            )}
          </Card>
        )
      })()}

      {/* Third parties */}
      {c.thirdParties && c.thirdParties.length > 0 && (
        <Card title="Third Parties">
          {c.thirdParties.map((tp: any, i: number) => (
            <div key={i} className="mb-4 last:mb-0 p-3 bg-surface-2 rounded-lg">
              <DL items={Object.entries(tp).filter(([k]) => k !== 'id').map(([k, v]) => [
                k.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()),
                String(v ?? '-'),
              ])} />
            </div>
          ))}
        </Card>
      )}

      {/* Closed note */}
      {c.closed_note && (
        <Card title="Resolution Note">
          <p className="text-sm text-ink-muted whitespace-pre-wrap">{c.closed_note}</p>
        </Card>
      )}
    </div>
  )
}

/**
 * Assessor tab — mirrors graphiteBWV8 admin.claims.accident Assessor section.
 *
 * Flow:
 *  1. User picks an Assessor from the dropdown (users with 'Accessor' role).
 *  2. Optionally ticks "send mail to attorney" — forwards the email to the
 *     attorney assigned to the claim if one exists.
 *  3. Optionally attaches a file (S3).
 *  4. Clicks Send Mail → backend creates a claim_assessment row, uploads the
 *     file, and dispatches an email to the assessor (+ attorney if checked).
 *
 * Below the form, every row in claim_assessment for this claim is shown as
 * an Assessor Report with download links for assessment_report, quotations_parts,
 * valuation, and attached_file.
 */
type AssessorOption = {
  value: string          // "assessor:5" / "lawyer:5"
  id: number
  source: 'user' | 'supplier' | 'assessor' | 'lawyer'
  firstName?: string
  lastName?: string
  name?: string
  email?: string | null
  category?: string | null   // motor | non_motor | both | null
}

const GT_MOTIVE_URL = 'https://www.motolink.app'

function TabAssessor({ claimId, editable }: { claimId: number; editable: boolean }) {
  const [assessors, setAssessors] = useState<AssessorOption[]>([])
  const [reports, setReports] = useState<any[]>([])
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  // Manual workflow toggle — the user decides whether this claim is handled as
  // Motor (→ GT Motive) or Non-Motor (→ email-an-assessor), regardless of the
  // claim's stored type.
  const [workflow, setWorkflow] = useState<'motor' | 'non_motor'>('non_motor')
  const [selectedAssessor, setSelectedAssessor] = useState<string>('')
  const [sendEmailToAssessor, setSendEmailToAssessor] = useState(false)
  const [recipientEmails, setRecipientEmails] = useState<string[]>([])
  const [emailToAdd, setEmailToAdd] = useState('')
  const [files, setFiles] = useState<File[]>([])
  // Non-Motor assessor email: subject + body are composed server-side from the
  // claim. Only Contact Details and Location are entered manually.
  const [contactDetails, setContactDetails] = useState('')
  const [location, setLocation] = useState('')
  // Lawyer email fields (union claims) — replace Contact Details + Location.
  const [proposedAction, setProposedAction] = useState('')
  const [clientName, setClientName] = useState('')
  const [clientId, setClientId] = useState('')
  const [clientPhone, setClientPhone] = useState('')
  const [clientEmail, setClientEmail] = useState('')
  const [flash, setFlash] = useState<{ type: 'ok' | 'err'; msg: string } | null>(null)
  // Cached claim detail (same query key as the page) — drives the email preview.
  const { data: claim } = useClaim(claimId)
  // Union legal claims (BONU / BOWASEWU) appoint a LAWYER from the Lawyers
  // master list instead of an assessor — drives the tab wording + dropdown source.
  const isLawyer = !!(claim as any)?.unionLegalClaim
  const roleLabel = isLawyer ? 'Lawyer' : 'Assessor'
  const roleLower = isLawyer ? 'lawyer' : 'assessor'

  // Prefill the lawyer email fields from the union claim once it loads (keyed on
  // the union-claim id so operator edits aren't clobbered by unrelated refetches).
  const ulcId = (claim as any)?.unionLegalClaim?.id
  useEffect(() => {
    const u = (claim as any)?.unionLegalClaim
    if (!isLawyer || !u) return
    setProposedAction(u.proposed_course_of_action ?? '')
    setClientName(u.insured_name ?? '')
    setClientId(u.omang_passport ?? '')
    setClientPhone(u.cellphone ?? '')
    setClientEmail(u.email ?? '')
  }, [isLawyer, ulcId]) // eslint-disable-line react-hooks/exhaustive-deps

  const loadAll = async () => {
    setLoading(true)
    // Load independently — a failure in reports shouldn't kill the dropdown.
    // The Assessor dropdown is fed from the Assessors master list (admin →
    // Assessors), active records only.
    const [aRes, rRes] = await Promise.allSettled([
      apiClient.get(isLawyer ? '/lawyers' : '/assessors', { params: { active: 1, per_page: 1000 } }),
      apiClient.get(`/claims/${claimId}/assessor-reports`),
    ])
    if (aRes.status === 'fulfilled') {
      const list: AssessorOption[] = (aRes.value.data?.data ?? []).map((a: any) => ({
        value: `${roleLower}:${a.id}`,
        id: a.id,
        source: roleLower as 'assessor' | 'lawyer',
        name: a.name,
        firstName: a.name,
        lastName: '',
        email: a.email ?? null,
        category: a.category ?? null,
      }))
      setAssessors(list)
    } else {
      setFlash({ type: 'err', msg: `Failed to load ${roleLower} list. Add ${roleLower}s under Admin → ${roleLabel}s.` })
    }
    if (rRes.status === 'fulfilled') {
      setReports(rRes.value.data?.data ?? [])
    }
    setLoading(false)
  }

  useEffect(() => { loadAll() }, [claimId, isLawyer]) // eslint-disable-line react-hooks/exhaustive-deps

  // Assessors relevant to the chosen workflow: those tagged for this category,
  // tagged "both", or untagged (so a half-configured DB still lets you appoint).
  const categoryAssessors = assessors.filter(a => {
    const c = a.category
    return !c || c === 'both' || c === workflow
  })
  const labelFor = (a: AssessorOption) => {
    const nm = a.name || `${a.firstName || ''} ${a.lastName || ''}`.trim()
    const tag = a.source === 'supplier' ? ' [External]' : a.source === 'user' ? ' [Internal]' : ''
    return `${nm}${tag}${a.email ? ` (${a.email})` : ''}`
  }
  // Email choices for the "send email to the assessor" picker — assessor
  // addresses for this workflow, minus any already chosen.
  const emailChoices = categoryAssessors
    .map(a => a.email)
    .filter((e): e is string => !!e && !recipientEmails.includes(e))
  const uniqueEmailChoices = Array.from(new Set(emailChoices))

  // Build the email preview client-side, mirroring the server format in
  // ClaimsController::assessorEmailContent so the handler verifies before send.
  const assessorPreview = (() => {
    const policyNo = claim?.policy?.policy_number || ''
    const customer = claim?.customer?.name || ''
    const item = claim?.vehicle_plate || ''
    const type = claim?.claim_type ? claim.claim_type.toUpperCase() : ''
    const claimNo = claim?.claim_number || ''
    const dateOfLoss = claim?.date_of_loss || '-'
    const claimDoc = files.length ? files.map(f => f.name).join(', ') : '-'

    const subjParts = [policyNo, customer, item, type].filter(p => String(p).trim() !== '')
    let subject = subjParts.join(' ')
    if (claimNo) subject += (subject ? ' ' : '') + `CLAIM#: ${claimNo}`

    // Lawyer (union claims) — instruct-and-assist format with the client block.
    if (isLawyer) {
      const body = [
        'Good day,',
        '',
        'Please see attached and assist the client accordingly.',
        '',
        proposedAction.trim() || '-',
        '',
        `Client name: ${clientName.trim() || '-'}`,
        `Client id: ${clientId.trim() || '-'}`,
        `Phone: ${clientPhone.trim() || '-'}`,
        `Email: ${clientEmail.trim() || '-'}`,
        '',
        'We kindly request that you confirm within 48 hours whether you have been able to make contact with the client and provide an update on the matter. Should you require any additional information or documentation to assist, please do not hesitate to reach out.',
        '',
        'Regards',
      ].join('\n')
      return { subject, body }
    }

    const body = [
      'Good day,',
      '',
      'Kindly see below and assess on our behalf:',
      '',
      `Insured Name: ${customer || '-'}`,
      `Policy #: ${policyNo || '-'}`,
      `Claim #: ${claimNo || '-'}`,
      `Date of Loss: ${dateOfLoss}`,
      `Claim Document: ${claimDoc}`,
      `Contact Details: ${contactDetails.trim() || '-'}`,
      `Location: ${location.trim() || '-'}`,
    ].join('\n')

    return { subject, body }
  })()

  function addEmail(email: string) {
    const e = email.trim()
    if (!e || recipientEmails.includes(e)) return
    setRecipientEmails(prev => [...prev, e])
    setEmailToAdd('')
  }
  function removeEmail(email: string) {
    setRecipientEmails(prev => prev.filter(e => e !== email))
  }

  async function handleSendMail() {
    // Button is always enabled, so guard re-entry here instead of via `disabled`.
    if (saving) return
    if (!selectedAssessor) { setFlash({ type: 'err', msg: `Please select a ${roleLower}.` }); return }
    setSaving(true); setFlash(null)
    try {
      const fd = new FormData()
      fd.append('assessor', selectedAssessor)
      fd.append('category', workflow)
      // Subject + body are composed server-side. Lawyer (union) claims supply
      // the client block + proposed course of action; assessors supply Contact
      // Details + Location.
      if (isLawyer) {
        fd.append('proposed_action', proposedAction)
        fd.append('client_name', clientName)
        fd.append('client_id', clientId)
        fd.append('client_phone', clientPhone)
        fd.append('client_email', clientEmail)
      } else {
        fd.append('contact_details', contactDetails)
        fd.append('location', location)
      }
      if (sendEmailToAssessor) recipientEmails.forEach(e => fd.append('recipientEmails[]', e))
      files.forEach(f => fd.append('attachFile[]', f))
      // apiClient defaults to application/json; multipart must be forced so
      // axios sets the boundary and Laravel parses the uploaded files (else
      // they arrive as plain strings → "attachFile.* must be a file").
      const r = await apiClient.post(`/claims/${claimId}/assessor-upload`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      setFlash({ type: 'ok', msg: r.data?.message || `Request sent to ${roleLabel} successfully.` })
      setSelectedAssessor(''); setSendEmailToAssessor(false); setRecipientEmails([]); setFiles([])
      setContactDetails(''); setLocation('')
      await loadAll()
    } catch (e: any) {
      setFlash({ type: 'err', msg: e?.response?.data?.error || e?.response?.data?.message || 'Failed to send' })
    } finally { setSaving(false) }
  }

  const filesFromJson = (raw: any): string[] => {
    if (!raw) return []
    if (Array.isArray(raw)) return raw
    if (typeof raw === 'string') {
      try { const parsed = JSON.parse(raw); return Array.isArray(parsed) ? parsed : [raw] }
      catch { return [raw] }
    }
    return []
  }

  if (loading) return <div className="p-6 text-sm text-ink-faint">Loading {roleLower} data…</div>

  return (
    <div className="space-y-4">
      {/* ── Upload form ── */}
      {editable && (
        <Card title={`${roleLabel} Details`}>
          {flash && (
            <div className={`mb-3 px-3 py-2 rounded-md text-sm ${flash.type === 'ok' ? 'bg-status-success-bg border border-status-success-fg text-status-success-fg' : 'bg-status-danger-bg border border-status-danger-fg text-status-danger-fg'}`}>
              {flash.msg}
            </div>
          )}

          {/* ── Motor / Non-Motor workflow toggle ── */}
          {/* Hidden for lawyer (union) claims — legal matters are always the
              Non-Motor "email a lawyer" flow, so there's no Motor option. */}
          {!isLawyer && (
            <div className="flex items-center gap-2 mb-4">
              {(['motor', 'non_motor'] as const).map(w => (
                <button key={w} type="button" onClick={() => setWorkflow(w)}
                  className={`px-4 py-1.5 rounded-full text-sm font-medium border ${workflow === w
                    ? 'bg-primary text-white border-primary'
                    : 'bg-surface text-ink-muted border-line hover:bg-surface-2'}`}>
                  {w === 'motor' ? 'Motor' : 'Non-Motor'}
                </button>
              ))}
            </div>
          )}

          {workflow === 'motor' ? (
            /* ── Motor → GT Motive ── */
            <div className="py-2">
              <p className="text-sm text-ink-muted mb-3">
                Motor assessments are handled in GT Motive. Open it to appoint the assessor and capture the report.
              </p>
              <a href={GT_MOTIVE_URL} target="_blank" rel="noopener noreferrer"
                className="inline-flex items-center gap-2 px-5 py-2 bg-primary text-white rounded-md text-sm font-medium hover:bg-primary">
                Open GT Motive ↗
              </a>

              {/* MotoLink assessment result, mirrored back onto the claim by the
                  push endpoint. Only rendered once an assessment has synced. */}
              {claim?.motolink && (
                <div className="mt-4 border border-line rounded-md overflow-hidden">
                  <div className="flex items-center justify-between px-4 py-2.5 bg-surface-2 border-b border-line">
                    <span className="text-sm font-semibold text-ink">MotoLink Assessment</span>
                    <span className="text-xs font-medium text-status-success-fg bg-status-success-bg px-2 py-0.5 rounded-full">
                      ✓ Synced{claim.motolink.synced_at
                        ? ' ' + new Date(claim.motolink.synced_at).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
                        : ''}
                    </span>
                  </div>
                  <div className="grid grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-3 px-4 py-3 text-sm">
                    <div><div className="text-xs text-ink-faint">Assessment ID</div><div className="text-ink font-medium">{claim.motolink.assessment_id || '—'}</div></div>
                    <div><div className="text-xs text-ink-faint">Status</div><div className="text-ink font-medium">{claim.motolink.status || '—'}</div></div>
                    <div><div className="text-xs text-ink-faint">Repair cost</div><div className="text-ink font-medium">{claim.motolink.final_cost != null && claim.motolink.final_cost !== '' ? 'BWP ' + Number(claim.motolink.final_cost).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—'}</div></div>
                    <div><div className="text-xs text-ink-faint">Vehicle</div><div className="text-ink font-medium">{[claim.motolink.make, claim.motolink.model].filter(Boolean).join(' ') || '—'}</div></div>
                    <div><div className="text-xs text-ink-faint">Registration</div><div className="text-ink font-medium">{claim.motolink.registration || '—'}</div></div>
                    <div><div className="text-xs text-ink-faint">Total loss</div><div className="text-ink font-medium">{claim.motolink.total_loss ? 'Yes' : 'No'}</div></div>
                  </div>
                  {claim.motolink.write_off_alert && (
                    <div className="px-4 py-2 bg-status-warning-bg text-status-warning-fg text-xs border-t border-line">⚠ Write-off alert flagged by the assessor.</div>
                  )}
                </div>
              )}
            </div>
          ) : (
            /* ── Non-Motor → email an assessor ── */
            <>
              <div className="grid grid-cols-1 md:grid-cols-[200px_1fr] gap-x-4 gap-y-4 items-start">
                <label className="text-sm font-medium text-ink-muted md:pt-2">{roleLabel} *</label>
                <select value={selectedAssessor} onChange={e => setSelectedAssessor(e.target.value)}
                  className="w-full px-3 py-2 border border-line rounded-md text-sm">
                  <option value="">Please select {roleLower}</option>
                  {categoryAssessors.map(a => (
                    <option key={a.value} value={a.value}>{labelFor(a)}</option>
                  ))}
                </select>

                <label className="text-sm font-medium text-ink-muted md:pt-2">Send email to the {roleLower}?</label>
                <div>
                  <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={sendEmailToAssessor}
                      onChange={e => setSendEmailToAssessor(e.target.checked)} className="rounded" />
                    <span className="text-ink-muted">Email the {roleLower} request to one or more {roleLower} addresses</span>
                  </label>

                  {sendEmailToAssessor && (
                    <div className="mt-3 space-y-2">
                      <div className="flex flex-wrap items-center gap-2">
                        <select value={emailToAdd} onChange={e => { if (e.target.value) addEmail(e.target.value) }}
                          className="px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">Select a {roleLower} email…</option>
                          {uniqueEmailChoices.map(e => <option key={e} value={e}>{e}</option>)}
                        </select>
                        <input type="email" value={emailToAdd} onChange={e => setEmailToAdd(e.target.value)}
                          onKeyDown={e => { if (e.key === 'Enter') { e.preventDefault(); addEmail(emailToAdd) } }}
                          placeholder="or type an email + Enter"
                          className="px-3 py-2 border border-line rounded-md text-sm flex-1 min-w-[200px]" />
                        <button type="button" onClick={() => addEmail(emailToAdd)}
                          className="px-3 py-2 text-sm border border-line rounded-md hover:bg-surface-2">Add</button>
                      </div>
                      {recipientEmails.length > 0 && (
                        <div className="flex flex-wrap gap-2">
                          {recipientEmails.map(e => (
                            <span key={e} className="inline-flex items-center gap-1 px-2 py-1 bg-status-info-bg border border-primary text-primary rounded-full text-xs">
                              {e}
                              <button type="button" onClick={() => removeEmail(e)} className="hover:text-primary">×</button>
                            </span>
                          ))}
                        </div>
                      )}
                    </div>
                  )}
                </div>

                <div className="md:col-span-2 text-xs text-ink-faint bg-status-info-bg border border-primary rounded-md px-3 py-2">
                  {isLawyer
                    ? 'The email subject is generated automatically from the claim. The fields below fill the request body, and the uploaded file is attached.'
                    : 'The email subject and body are generated automatically from the claim. Only Contact Details and Location below are entered manually, and the uploaded file is attached as the Claim Document.'}
                </div>

                {isLawyer ? (
                  <>
                    <label className="text-sm font-medium text-ink-muted md:pt-2">Proposed course of action</label>
                    <textarea value={proposedAction} onChange={e => setProposedAction(e.target.value)} rows={3}
                      placeholder="e.g. She requires assistance with divorce as the client seeks legal representation in divorce proceedings."
                      className="w-full px-3 py-2 border border-line rounded-md text-sm" />

                    <label className="text-sm font-medium text-ink-muted md:pt-2">Client name</label>
                    <input value={clientName} onChange={e => setClientName(e.target.value)}
                      className="w-full px-3 py-2 border border-line rounded-md text-sm" />

                    <label className="text-sm font-medium text-ink-muted md:pt-2">Client id</label>
                    <input value={clientId} onChange={e => setClientId(e.target.value)}
                      className="w-full px-3 py-2 border border-line rounded-md text-sm" />

                    <label className="text-sm font-medium text-ink-muted md:pt-2">Phone</label>
                    <input value={clientPhone} onChange={e => setClientPhone(e.target.value)}
                      className="w-full px-3 py-2 border border-line rounded-md text-sm" />

                    <label className="text-sm font-medium text-ink-muted md:pt-2">Email</label>
                    <input value={clientEmail} onChange={e => setClientEmail(e.target.value)}
                      className="w-full px-3 py-2 border border-line rounded-md text-sm" />
                  </>
                ) : (
                  <>
                    <label className="text-sm font-medium text-ink-muted md:pt-2">Contact Details</label>
                    <textarea value={contactDetails} onChange={e => setContactDetails(e.target.value)} rows={3}
                      placeholder={`Contact details for the ${roleLower}`}
                      className="w-full px-3 py-2 border border-line rounded-md text-sm" />

                    <label className="text-sm font-medium text-ink-muted md:pt-2">Location</label>
                    <textarea value={location} onChange={e => setLocation(e.target.value)} rows={3}
                      placeholder="Location"
                      className="w-full px-3 py-2 border border-line rounded-md text-sm" />
                  </>
                )}

                <label className="text-sm font-medium text-ink-muted md:pt-2">File Attachment</label>
                <div>
                  <input type="file" multiple
                    onChange={e => setFiles(prev => [...prev, ...Array.from(e.target.files ?? [])])}
                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                    className="text-sm" />
                  {files.length > 0 && (
                    <ul className="mt-2 space-y-1">
                      {files.map((f, i) => (
                        <li key={i} className="flex items-center gap-2 text-xs text-ink-muted">
                          <span className="text-status-success-fg">{f.name}</span>
                          <button type="button" onClick={() => setFiles(prev => prev.filter((_, idx) => idx !== i))}
                            className="text-status-danger-fg hover:text-status-danger-fg">remove</button>
                        </li>
                      ))}
                    </ul>
                  )}
                </div>

                {/* Live preview of the auto-composed email so the handler can
                    verify the details before sending. Mirrors the server-side
                    format in ClaimsController::assessorEmailContent. */}
                <label className="text-sm font-medium text-ink-muted md:pt-2">Email Preview</label>
                <div className="border border-line rounded-md bg-surface-2 p-3 text-sm">
                  <div className="font-medium text-ink-muted mb-2">Subject: {assessorPreview.subject}</div>
                  <div className="whitespace-pre-wrap text-ink-muted">{assessorPreview.body}</div>
                </div>
              </div>

              <div className="mt-5 pt-4 border-t border-line flex justify-end">
                {/* Always enabled — validation (assessor required) is handled in
                    handleSendMail with a flash message, so the button is never
                    shown disabled. */}
                <button onClick={handleSendMail}
                  className="px-5 py-2 bg-primary text-white rounded-md text-sm font-medium hover:bg-primary">
                  {saving ? 'Sending…' : 'Send Mail'}
                </button>
              </div>
            </>
          )}
        </Card>
      )}

      {/* ── Assessor Reports history ── */}
      <Card title={`${roleLabel} Reports${reports.length ? ' (' + reports.length + ')' : ''}`}>
        {reports.length === 0 ? (
          <div className="py-8 text-center text-sm text-ink-faint">No {roleLower} reports found</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-surface-2 text-ink-muted uppercase text-xs">
                <tr>
                  <th className="px-3 py-2 text-left">{roleLabel}</th>
                  {!isLawyer && <>
                    <th className="px-3 py-2 text-left">Assessment Report</th>
                    <th className="px-3 py-2 text-left">Quotations / Parts</th>
                    <th className="px-3 py-2 text-left">Valuation</th>
                  </>}
                  <th className="px-3 py-2 text-left">Attached</th>
                  <th className="px-3 py-2 text-right">Notes</th>
                  <th className="px-3 py-2 text-right">Date</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {reports.map((r: any) => (
                  <tr key={r.id} className="hover:bg-surface-2">
                    <td className="px-3 py-2 text-ink-muted">{r.assessor_name || '—'}</td>
                    {!isLawyer && <>
                    <td className="px-3 py-2">
                      {filesFromJson(r.assessment_report).map((f: string, i: number) =>
                        <FileLink key={i} url={f} label={`Report ${i + 1}`} />
                      )}
                      {filesFromJson(r.assessment_report).length === 0 && <span className="text-ink-faint text-xs">—</span>}
                    </td>
                    <td className="px-3 py-2">
                      {filesFromJson(r.quotations_parts).map((f: string, i: number) =>
                        <FileLink key={i} url={f} label={`Quote ${i + 1}`} />
                      )}
                      {filesFromJson(r.quotations_parts).length === 0 && <span className="text-ink-faint text-xs">—</span>}
                    </td>
                    <td className="px-3 py-2">
                      {r.valuation ? (filesFromJson(r.valuation).length > 0
                        ? filesFromJson(r.valuation).map((f: string, i: number) => <FileLink key={i} url={f} label={`Val ${i + 1}`} />)
                        : <span className="font-mono">{fmt(r.valuation)}</span>)
                        : <span className="text-ink-faint text-xs">—</span>}
                    </td>
                    </>}
                    <td className="px-3 py-2">
                      {filesFromJson(r.attached_file).map((f: string, i: number) =>
                        <FileLink key={i} url={f} label={`File ${i + 1}`} />
                      )}
                      {filesFromJson(r.attached_file).length === 0 && <span className="text-ink-faint text-xs">—</span>}
                    </td>
                    <td className="px-3 py-2 text-right text-ink-muted">{r.notes || '—'}</td>
                    <td className="px-3 py-2 text-right text-ink-faint text-xs">{r.created_at ? new Date(r.created_at).toLocaleDateString('en-GB') : '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
    </div>
  )
}

// One row in the inline attachment uploader. Mirrors the legacy V8 blade's
// per-row shape: a name, a document-type label, a lookup-driven type key,
// and one-or-more files. Multiple rows are submitted in one Submit click.
//
// Files are modelled as discrete "slots" rather than a single multi-file
// picker because graphiteBWV8's UX is one picker per file, with a "+ Add"
// button that spawns an extra picker each time. Mirroring that here keeps
// parity — users can pick → click Add → pick another → etc. and each picker
// is independently clearable.
type FileSlot = { key: number; file: File | null }

type AttachmentDraftRow = {
  key: number
  name: string
  documentTypeName: string
  type: string
  slots: FileSlot[]
}

let _slotKeySeq = 1
function newSlot(): FileSlot { return { key: _slotKeySeq++, file: null } }

function emptyAttachmentRow(): AttachmentDraftRow {
  return { key: Date.now() + Math.random(), name: '', documentTypeName: '', type: '', slots: [newSlot()] }
}

function TabAttachments({ claimId, attachments, editable, onRefresh }: { claimId: number; attachments?: ClaimAttachment[]; editable: boolean; onRefresh: () => void }) {
  const { toast } = useToast()
  const [uploading, setUploading] = useState(false)
  const [rows, setRows] = useState<AttachmentDraftRow[]>([emptyAttachmentRow()])
  const [fileTypes, setFileTypes] = useState<{ id: string; name: string }[]>([])
  const [deletingId, setDeletingId] = useState<number | null>(null)
  const hasAttachments = attachments && attachments.length > 0

  // Closing Document Details — fetched once per claimId. View-only here:
  // the Attachments tab just surfaces what's stored on the claim row
  // (document_1/2/3 + closed_note). Uploads happen elsewhere (e.g. the
  // Close Claim action), so this card never edits the values.
  const [closing, setClosing] = useState<ClosingDocuments | null>(null)
  const [closingLoading, setClosingLoading] = useState(true)

  // Lazy-load the doc-type dropdown options once. The fallback list inside
  // the backend covers fresh dev DBs where the lookup table is unseeded.
  useEffect(() => {
    apiClient.get('/claims-v2/lookups/file-types')
      .then(r => setFileTypes(r.data?.data ?? []))
      .catch(() => {/* non-fatal: dropdown will just be empty */})
  }, [])

  // Fetch existing closing docs + note so the section can render them.
  useEffect(() => {
    setClosingLoading(true)
    fetchClosingDocuments(claimId)
      .then(d => setClosing(d))
      .catch(() => setClosing(null))
      .finally(() => setClosingLoading(false))
  }, [claimId])

  function updateRow(key: number, patch: Partial<AttachmentDraftRow>) {
    setRows(rs => rs.map(r => r.key === key ? { ...r, ...patch } : r))
  }
  function setSlotFile(rowKey: number, slotKey: number, file: File | null) {
    setRows(rs => rs.map(r => r.key === rowKey
      ? { ...r, slots: r.slots.map(s => s.key === slotKey ? { ...s, file } : s) }
      : r))
  }
  function addSlot(rowKey: number) {
    setRows(rs => rs.map(r => r.key === rowKey ? { ...r, slots: [...r.slots, newSlot()] } : r))
  }
  function removeSlot(rowKey: number, slotKey: number) {
    setRows(rs => rs.map(r => r.key === rowKey
      // Always keep at least one slot per row so the row never collapses
      // to zero pickers — clearing the file alone is enough for that case.
      ? { ...r, slots: r.slots.length === 1 ? r.slots.map(s => ({ ...s, file: null })) : r.slots.filter(s => s.key !== slotKey) }
      : r))
  }
  function addRow() { setRows(rs => [...rs, emptyAttachmentRow()]) }
  function removeRow(key: number) {
    setRows(rs => rs.length === 1 ? rs : rs.filter(r => r.key !== key))
  }

  async function submitAttachments() {
    // Validate every row up-front so the user doesn't get half-applied
    // saves on partial failure.
    const prepared = rows.map(r => ({
      ...r,
      pickedFiles: r.slots.map(s => s.file).filter((f): f is File => !!f),
    }))
    for (const r of prepared) {
      if (!r.name.trim()) { toast.warning('Each attachment row needs a Name.'); return }
      if (!r.documentTypeName.trim()) { toast.warning('Each attachment row needs a Document Type Name.'); return }
      if (!r.type) { toast.warning('Each attachment row needs a Document Type.'); return }
      if (r.pickedFiles.length === 0) { toast.warning('Each attachment row needs at least one file.'); return }
    }

    setUploading(true)
    try {
      // Sequential rather than parallel — each row creates its own DB row,
      // and S3 puts can be heavy; a serial loop keeps user feedback simple
      // and avoids hammering S3 with N concurrent multi-file PUTs.
      for (const r of prepared) {
        await uploadClaimAttachment(claimId, {
          name: r.name.trim(),
          documentTypeName: r.documentTypeName.trim(),
          type: r.type,
          files: r.pickedFiles,
        })
      }
      setRows([emptyAttachmentRow()])
      onRefresh()
    } catch (err: any) {
      // Give the operator the ACTUAL reason instead of a blanket "Upload
      // failed". The failures fall into distinct buckets, each with a
      // different remedy, so map them explicitly:
      const res = err.response
      // 422 — Laravel per-field validation (size >40MB app cap / bad type /
      // missing file). Surface the first field error verbatim.
      const errors = res?.data?.errors as Record<string, string[]> | undefined
      const firstError = errors ? Object.values(errors).flat()[0] : undefined

      let msg: string
      if (firstError) {
        msg = firstError
      } else if (!res) {
        // No response object at all → the request never completed: a client
        // timeout (axios ECONNABORTED after 5 min) or a dropped connection.
        // A large file over prod's slow link is the usual culprit.
        msg = (err.code === 'ECONNABORTED' || /timeout/i.test(err.message || ''))
          ? 'Upload timed out — the file may be too large or the connection too slow. Try a smaller / compressed file.'
          : 'Could not reach the server (network error). Check your connection and try again.'
      } else if (res.status === 413) {
        // Proxy (nginx) rejected the body before it reached the app — the
        // file exceeds the server upload ceiling (~50 MB), above the 40 MB
        // app cap, so no JSON body comes back.
        msg = 'File too large for the server (max ~50 MB). Compress or split the PDF and try again.'
      } else if (typeof res.data?.message === 'string' && res.data.message) {
        msg = res.data.message
      } else if (res.status >= 500) {
        msg = `Server error (${res.status}) while saving the file — please retry, or contact IT if it persists.`
      } else {
        msg = `Upload failed (HTTP ${res.status}).`
      }
      toast.error(msg)
    } finally {
      setUploading(false)
    }
  }

  async function handleDelete(docId: number) {
    if (!confirm('Are you sure you want to delete this attachment?')) return
    setDeletingId(docId)
    try {
      await deleteClaimAttachment(claimId, docId)
      onRefresh()
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Delete failed')
    } finally {
      setDeletingId(null)
    }
  }

  return (
    <div className="space-y-6">
      {/* ── Inline multi-row attachment uploader ────────────────────────────
          Mirrors graphiteBWV8's resources/views/admin/claims/attachment.blade.php:
          Name + Document Type Name + Document Type dropdown + multi-file picker
          per row, with "Add New Attachment" appending another row and "Remove"
          dropping a row. Submit posts each row as its own claim_attachments
          record. */}
      {editable && <Card title="Attachments" action={(
        <button type="button" onClick={addRow}
          className="px-3 py-1.5 text-xs font-medium bg-surface border border-line rounded-md hover:bg-surface-2">
          + Add New Attachment
        </button>
      )}>
        <div className="space-y-5">
          {rows.map((row, idx) => (
            <div key={row.key} className="border border-line rounded-lg p-4 bg-surface-2/50 space-y-3">
              <div className="flex items-center justify-between">
                <span className="text-xs font-semibold text-ink-faint uppercase tracking-wide">Attachment {idx + 1}</span>
                {rows.length > 1 && (
                  <button type="button" onClick={() => removeRow(row.key)}
                    className="px-2 py-1 text-xs font-medium text-status-danger-fg bg-status-danger-bg border border-status-danger-fg rounded-md hover:bg-status-danger-bg">
                    Remove
                  </button>
                )}
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">Name <span className="text-status-danger-fg">*</span></label>
                  <input type="text" value={row.name}
                    onChange={e => updateRow(row.key, { name: e.target.value })}
                    placeholder="Enter Name"
                    className="w-full px-3 py-2 border border-line rounded-md text-sm" />
                </div>
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">Document Type Name <span className="text-status-danger-fg">*</span></label>
                  <input type="text" value={row.documentTypeName}
                    onChange={e => updateRow(row.key, { documentTypeName: e.target.value })}
                    placeholder="Enter Document Type Name"
                    className="w-full px-3 py-2 border border-line rounded-md text-sm" />
                </div>
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">Document Type <span className="text-status-danger-fg">*</span></label>
                  <select value={row.type}
                    onChange={e => updateRow(row.key, { type: e.target.value })}
                    className="w-full px-3 py-2 border border-line rounded-md text-sm bg-surface">
                    <option value="">— Please Select document type —</option>
                    {fileTypes.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">Attachment <span className="text-status-danger-fg">*</span></label>
                  {/* One picker per slot. Mirrors V8's "+ Add" behaviour:
                      each click of Add appends another picker; users can
                      clear individual slots without touching the others. */}
                  <div className="space-y-2">
                    {row.slots.map(slot => (
                      <div key={slot.key} className="flex items-center gap-2">
                        <input type="file"
                          accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.csv,.zip"
                          onChange={e => setSlotFile(row.key, slot.key, e.target.files?.[0] ?? null)}
                          className="block w-full text-xs text-ink-faint file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border file:border-line file:text-xs file:font-medium file:bg-surface file:text-ink-muted hover:file:bg-surface-2" />
                        {(slot.file || row.slots.length > 1) && (
                          <button type="button"
                            onClick={() => removeSlot(row.key, slot.key)}
                            title={row.slots.length === 1 ? 'Clear file' : 'Remove this picker'}
                            className="text-status-danger-fg hover:text-status-danger-fg text-base leading-none px-1">×</button>
                        )}
                      </div>
                    ))}
                  </div>
                  <button type="button"
                    onClick={() => addSlot(row.key)}
                    className="mt-2 inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-white bg-primary rounded hover:bg-primary">
                    <span className="text-sm leading-none">+</span> Add
                  </button>
                </div>
              </div>
            </div>
          ))}
          <p className="text-[11px] text-status-warning-fg bg-status-warning-bg border border-status-warning-fg rounded px-2 py-1.5">
            Note: .eml files cannot be uploaded directly — please zip them first.
          </p>
          <div className="flex justify-end">
            <button type="button" disabled={uploading} onClick={submitAttachments}
              className="px-5 py-2 bg-primary text-white text-sm font-medium rounded-md hover:bg-primary disabled:opacity-50">
              {uploading ? 'Uploading…' : 'Submit'}
            </button>
          </div>
        </div>
      </Card>}

      {/* ── Existing attachments table ──────────────────────────────────── */}
      {hasAttachments ? (
        <Card title="Uploaded Attachments">
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead className="text-ink-faint text-xs uppercase tracking-wide border-b border-line">
                <tr>
                  <th className="py-2 pr-4 text-left font-medium">Name</th>
                  <th className="py-2 pr-4 text-left font-medium">Document Type Name</th>
                  <th className="py-2 pr-4 text-left font-medium">Document Type</th>
                  <th className="py-2 pr-4 text-left font-medium">Attachment</th>
                  <th className="py-2 pr-4 text-left font-medium">Uploaded</th>
                  {editable && <th className="py-2 pr-4 text-right font-medium">Action</th>}
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {attachments!.map(att => (
                  <tr key={att.id} className="align-top">
                    <td className="py-3 pr-4 font-medium text-ink">{att.name || '—'}</td>
                    <td className="py-3 pr-4 text-ink-muted">{att.documentTypeName || <span className="text-ink-faint">—</span>}</td>
                    <td className="py-3 pr-4 text-ink-muted">{att.type || <span className="text-ink-faint">—</span>}</td>
                    <td className="py-3 pr-4">
                      {att.files.length > 0 ? (
                        <div className="flex flex-wrap gap-2">
                          {att.files.map((f, fi) => <FileLink key={fi} url={f.url} label={f.name} />)}
                        </div>
                      ) : <span className="text-xs text-ink-faint">No file</span>}
                    </td>
                    <td className="py-3 pr-4 text-xs text-ink-faint">{fmtDate(att.createdAt)}</td>
                    {editable && <td className="py-3 pr-4 text-right">
                      <button type="button" disabled={deletingId === att.id}
                        onClick={() => handleDelete(att.id)}
                        title="Delete attachment"
                        className="inline-flex items-center px-2 py-1 text-xs font-medium text-status-danger-fg bg-status-danger-bg border border-status-danger-fg rounded hover:bg-status-danger-bg disabled:opacity-50">
                        <svg className="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3" />
                        </svg>
                        {deletingId === att.id ? 'Deleting…' : 'Delete'}
                      </button>
                    </td>}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      ) : (
        <Empty text="No attachments uploaded yet." />
      )}

      {/* ── Closing Document Details ────────────────────────────────────────
          Mirrors the legacy blade's three fixed slots + closing note. Stored
          on the claims row directly (document_1/2/3 + closed_note) so the
          values follow the claim's lifecycle, not a separate documents table. */}
      <Card title="Closing Document Details">
        {closingLoading ? (
          <p className="text-sm text-ink-faint italic">Loading closing documents…</p>
        ) : (
          <div className="divide-y divide-line">
            <ClosingDocViewRow label="Closing Document 1" url={closing?.document1Url} />
            <ClosingDocViewRow label="Closing Document 2" url={closing?.document2Url} />
            <ClosingDocViewRow label="Closing Document 3" url={closing?.document3Url} />
            <div className="grid grid-cols-1 md:grid-cols-[180px_1fr] gap-3 items-start py-3">
              <div className="text-sm font-medium text-ink-muted">Closing Note</div>
              <div className="text-sm text-ink-muted whitespace-pre-wrap">
                {closing?.closedNote ? closing.closedNote : <span className="text-ink-faint">—</span>}
              </div>
            </div>
          </div>
        )}
      </Card>
    </div>
  )
}

// View-only row in the Closing Document Details section. Renders a link to
// the stored file or a placeholder when nothing's been uploaded. Uploads
// happen elsewhere in the app (e.g. the Close Claim flow), not on this tab.
function ClosingDocViewRow({ label, url }: { label: string; url?: string | null }) {
  return (
    <div className="grid grid-cols-1 md:grid-cols-[180px_1fr] gap-3 items-start py-3">
      <div className="text-sm font-medium text-ink-muted">{label}</div>
      <div>
        {url
          ? <FileLink url={url} label="View document" />
          : <span className="text-xs text-ink-faint">No file uploaded yet</span>}
      </div>
    </div>
  )
}

// Default-empty form state for the inline Reserves/Payments form. Mirrors
// graphiteBWV8 reserves.blade fields (Date, Trans Type, Address, Payee,
// Trans Sub Type, plus payment-only Invoice / Memo / Description / VAT /
// Credit Note when a payment-style transaction type is selected). The
// per-coverage allocation amounts live in their own state map keyed by
// row identifier — see `allocations` in TabReserves.
function emptyReserveForm() {
  return {
    transaction_type: 0,
    transaction_sub_type: '' as number | '',
    date: new Date().toISOString().split('T')[0],
    payee: '' as string | number,
    address: '',
    invoice_no: '',
    invoice_date: '',
    invoice_due_date: '',
    memo: '',
    description: '',
    credit_note: false,
    include_vat: false,
  }
}

// graphiteBWV8 splits its form fields three ways based on transaction_type
// (see claims/general.blade.php TransactionType() handler):
//   • Payment types  → show invoice/memo/description block + Transaction Sub Type
//   • Reserve types  → show only Loss Reserve Sub Type
//   • Reset Reserves (44) → show Loss Reserve Sub Type (treated as a reserve
//                           operation in V8 — the dropdown lists the sub-type
//                           buckets the reset applies to)
//   • Anything else → hide both sub-type dropdowns
const PAYMENT_TYPE_IDS       = [43, 90, 92] // Loss Payment, TP Liab Payment, Salvage Payment
const RESERVE_TYPE_IDS       = [42, 89, 91] // Loss Reserve, TP Liab Reserve, Salvage Reserve
const RESET_RESERVE_TYPE_IDS = [44]         // Reset Reserves
const isPaymentType      = (id: number) => PAYMENT_TYPE_IDS.includes(id)
const isReserveType      = (id: number) => RESERVE_TYPE_IDS.includes(id)
const isResetReserveType = (id: number) => RESET_RESERVE_TYPE_IDS.includes(id)

// Stable per-row identifier for the allocation Map. Prefer the backend's
// per-row `rowUid` — motor extensions repeat the same coverageId (and
// policyCoverageId) across every vehicle, so source+policyCoverageId+coverageId
// collided and one input filled all matching vehicle rows (and submitted a
// duplicate reserve per row). Fall back to the composite for older responses.
const rowKeyOf = (r: ReserveCoverageRow) =>
  r.rowUid != null ? `uid-${r.rowUid}` : `${r.source}-${r.policyCoverageId}-${r.coverageId}`

function TabReserves({ claimId, claimNumber, reserves, totalReserve, totalPayment, balance, editable, onRefresh }: {
  claimId: number; claimNumber?: string; reserves?: ClaimReserve[]; totalReserve?: number; totalPayment?: number; balance?: number; editable?: boolean; onRefresh: () => void
}) {
  const { toast } = useToast()
  const [expandedId, setExpandedId] = useState<number | null>(null)
  const [reserveForm, setReserveForm] = useState(emptyReserveForm())
  const [reserveSaving, setReserveSaving] = useState(false)
  const [transTypes, setTransTypes] = useState<ReserveLookupItem[]>([])
  const [subTypes, setSubTypes] = useState<ReserveLookupItem[]>([])
  const [payees, setPayees] = useState<ReservePayee[]>([])

  // Pre-loaded coverage allocation table state. `coverages` is the full
  // policy coverage set returned by /claims-v2/{id}/reserves/coverages,
  // grouped by risk address for DOM/COM products. `allocations` is the
  // per-row amount the user has typed into the Reserve Allocation column.
  const [coverages, setCoverages] = useState<ReserveCoveragesResponse | null>(null)
  const [coveragesLoading, setCoveragesLoading] = useState(false)
  const [allocations, setAllocations] = useState<Record<string, string>>({})
  // Per-coverage write-off flags (rowKey → checked). Only meaningful when the
  // write-off trigger is active (Loss Reserve [42] + "Claim Expense" sub-type);
  // see `writeOffEligible` below. Checking a row fires a Finance/Underwriting
  // notification on submit.
  const [writeOffs, setWriteOffs] = useState<Record<string, boolean>>({})

  // Void-payment confirmation state — graphiteBWV8 has THREE modals on this
  // path: (1) the read-only "Reserve Information" prompt that opens first
  // when the user clicks Void Payment (V8 #voidPaymentDetailsModal); (2) the
  // reason-capture modal that fires when they confirm from (1) (V8
  // #voidPaymentStore); and (3) the post-void "Voided Payment Info" modal
  // for already-voided rows (V8 #voidedPaymentInfoModal).
  const [voidPreview, setVoidPreview] = useState<ClaimReserve | null>(null)
  const [voidTarget, setVoidTarget] = useState<{ crcId: number; reserveId: number } | null>(null)
  const [voidReason, setVoidReason] = useState('')
  const [voidSaving, setVoidSaving] = useState(false)
  const [voidInfo, setVoidInfo] = useState<VoidPaymentInfo | null>(null)
  const [voidInfoLoading, setVoidInfoLoading] = useState(false)

  // Load lookups + coverages on mount. Coverages also re-fetch whenever the
  // parent refetches (i.e. after a successful save / void) so the
  // Reserve Created / Payment Created columns stay in sync.
  useEffect(() => {
    fetchReserveTransactionTypes({ excludeInitial: true })
      .then(setTransTypes).catch(() => setTransTypes([]))
    fetchReservePayees()
      .then(setPayees).catch(() => setPayees([]))
  }, [])

  async function reloadCoverages() {
    setCoveragesLoading(true)
    try {
      const data = await fetchReserveCoverages(claimId)
      setCoverages(data)
    } catch {
      setCoverages(null)
    } finally {
      setCoveragesLoading(false)
    }
  }

  useEffect(() => {
    reloadCoverages()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [claimId, reserves?.length])

  // Reload sub-types whenever the user picks a different transaction type.
  // Mirrors V8's TransactionType() JS which filters the sub-type dropdown
  // on every change of #trans_type.
  useEffect(() => {
    if (!reserveForm.transaction_type) {
      setSubTypes([])
      return
    }
    let cancelled = false
    fetchReserveSubTypes(reserveForm.transaction_type)
      .then(rows => { if (!cancelled) setSubTypes(rows) })
      .catch(() => { if (!cancelled) setSubTypes([]) })
    return () => { cancelled = true }
  }, [reserveForm.transaction_type])

  function setAllocation(key: string, value: string) {
    setAllocations(prev => ({ ...prev, [key]: value }))
  }

  function setWriteOff(key: string, value: boolean) {
    setWriteOffs(prev => ({ ...prev, [key]: value }))
  }

  // The Write Off checkbox is offered only for a Loss Reserve (42) logged
  // against the "Claim Expense" sub-type. The label is data-driven (lives in
  // lookup_data), so match the selected sub-type's *name* to track the
  // business-configured label exactly rather than hard-coding an id.
  const selectedSubTypeName = subTypes.find(
    s => String(s.id) === String(reserveForm.transaction_sub_type)
  )?.name ?? ''
  const writeOffEligible = reserveForm.transaction_type === 42 &&
    selectedSubTypeName.trim().toLowerCase() === 'claim expense'

  // Write-off notification recipients. Picked from the user dropdown (name +
  // email) and/or typed in for addresses not in the list; stored as a flat list
  // of email strings. Only shown once a coverage is flagged for write-off.
  const [writeOffEmails, setWriteOffEmails] = useState<string[]>([])
  const { data: writeOffCreateData } = useClaimCreateData()
  const writeOffUserOptions = (writeOffCreateData?.internal_users ?? [])
    .filter(u => !!u.email)
    .map(u => ({ value: u.email as string, label: `${u.name} (${u.email})` }))
  const anyWriteOffChecked = writeOffEligible &&
    (coverages?.rows ?? []).some(r => !!writeOffs[rowKeyOf(r)])

  async function handleSubmit() {
    if (!reserveForm.transaction_type) {
      toast.warning('Please pick a Transaction Type.')
      return
    }
    const rows = coverages?.rows ?? []
    const items = rows
      .map(r => ({ row: r, amt: Number(allocations[rowKeyOf(r)] ?? '') }))
      .filter(x => Number.isFinite(x.amt) && x.amt > 0)

    if (items.length === 0) {
      toast.warning('Enter at least one Reserve Allocation amount.')
      return
    }

    // Validate any write-off recipient addresses before submitting.
    const writeOffEmailList = anyWriteOffChecked ? writeOffEmails : []
    if (anyWriteOffChecked && writeOffEmailList.length > 0) {
      const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
      const invalid = writeOffEmailList.filter(e => !emailRe.test(e))
      if (invalid.length) {
        toast.warning(`These recipient address(es) look invalid:\n${invalid.join('\n')}`)
        return
      }
    }

    setReserveSaving(true)
    try {
      await createReserve(claimId, {
        transaction_type: reserveForm.transaction_type,
        transaction_sub_type: reserveForm.transaction_sub_type === '' ? null : Number(reserveForm.transaction_sub_type),
        date: reserveForm.date,
        payee: reserveForm.payee === '' ? null : reserveForm.payee,
        address: reserveForm.address || null,
        invoice_no: reserveForm.invoice_no || null,
        invoice_date: reserveForm.invoice_date || null,
        invoice_due_date: reserveForm.invoice_due_date || null,
        memo: reserveForm.memo || null,
        description: reserveForm.description || null,
        credit_note: !!reserveForm.credit_note,
        include_vat: !!reserveForm.include_vat,
        coverages: items.map(x => ({
          coverage_id: Number(x.row.coverageId) || 0,
          coverage_name: x.row.coverageName,
          amount: x.amt,
          // Only send the flag when the trigger is active; the backend
          // re-validates the type/sub-type combination regardless.
          write_off: writeOffEligible ? !!writeOffs[rowKeyOf(x.row)] : false,
        })),
        write_off_emails: writeOffEmailList,
      })
      // Reset form + allocations, reload coverages so the Reserve/Payment
      // Created columns reflect the new totals immediately.
      setReserveForm(emptyReserveForm())
      setAllocations({})
      setWriteOffs({})
      setWriteOffEmails([])
      onRefresh()
      reloadCoverages()
    } catch (e: any) {
      const d = e?.response?.data ?? {}
      const detail = d.errors ? Object.entries(d.errors).map(([k, v]: any) => `${k}: ${Array.isArray(v) ? v.join(', ') : v}`).join('\n') : ''
      toast.error([d.message || d.error || 'Failed to save reserve', detail].filter(Boolean).join('\n\n'))
    } finally { setReserveSaving(false) }
  }

  // Step 1 of the void flow — open the read-only "Reserve Information"
  // preview. Mirrors graphiteBWV8 .voidPaymentBtn → getReserveDeatils → modal.
  // The user clicks "Void This Payment" inside the preview to advance.
  function openVoidPreview(reserveId: number) {
    const target = (reserves || []).find(r => r.id === reserveId)
    if (!target?.voidCrcId) { toast.warning('Nothing to void on this row.'); return }
    setVoidPreview(target)
  }

  // Step 2 — user confirmed in the preview. Swap to the reason-capture modal.
  function openVoidReason() {
    if (!voidPreview?.voidCrcId) return
    setVoidTarget({ crcId: voidPreview.voidCrcId, reserveId: voidPreview.id })
    setVoidPreview(null)
    setVoidReason('')
  }

  async function confirmVoid() {
    if (!voidTarget) return
    if (!voidReason.trim()) { toast.warning('Please enter a reason.'); return }
    setVoidSaving(true)
    try {
      await voidReservePayment(claimId, voidTarget.crcId, voidReason.trim())
      setVoidTarget(null)
      setVoidReason('')
      onRefresh()
    } catch (e: any) {
      toast.error(e.response?.data?.message || e.response?.data?.error || 'Failed to void payment')
    } finally { setVoidSaving(false) }
  }

  async function openVoidInfo(rowId: number) {
    // In the per-coverage row model the row's id IS the
    // claim_reserves_coverages.id — which is exactly what
    // claim_void_payment_logs.claim_reserves_coverages_id keys off.
    setVoidInfoLoading(true)
    try {
      const info = await fetchVoidPaymentInfo(claimId, rowId)
      setVoidInfo(info)
    } catch (e: any) {
      toast.error(e.response?.data?.message || 'Failed to load void info.')
    } finally { setVoidInfoLoading(false) }
  }

  return (
    <div className="space-y-6">
      {/* Summary cards */}
      <div className="grid grid-cols-3 gap-4">
        {[['Total Reserve', totalReserve], ['Total Payment', totalPayment], ['Balance', balance]].map(([label, val]) => (
          <div key={String(label)} className="bg-surface border border-line rounded-xl p-4 text-center shadow-sm">
            <p className="text-xs text-ink-faint uppercase tracking-wide">{String(label)}</p>
            <p className="text-xl font-bold text-ink mt-1">{fmt(val as number | undefined)}</p>
          </div>
        ))}
      </div>

      {/* Inline Add Reserve / Payment form (graphiteBWV8 reserves.blade
          parity). Hidden on Closed claims via the `editable` gate. */}
      {editable !== false && (
        <Card title="Add Reserve / Payment">
          <ReserveInlineForm
            form={reserveForm} setForm={setReserveForm}
            transTypes={transTypes} subTypes={subTypes} payees={payees}
          />

          {/* graphiteBWV8 reserves.blade hides the coverage allocation table
              behind the Transaction Type selection — the table simply does
              not render until the user picks a type. Mirror that here. */}
          {!reserveForm.transaction_type ? null : coveragesLoading ? (
            <p className="text-sm text-ink-faint italic py-6 text-center">Loading coverages…</p>
          ) : !coverages || coverages.rows.length === 0 ? (
            <Empty text="No policy coverages found for this claim." />
          ) : (
            <CoverageAllocationTable
              data={coverages}
              allocations={allocations}
              onChangeAllocation={setAllocation}
              isPayment={isPaymentType(reserveForm.transaction_type)}
              writeOffEligible={writeOffEligible}
              writeOffs={writeOffs}
              onToggleWriteOff={setWriteOff}
            />
          )}

          {/* Write-off recipient address(es). Appears once at least one
              coverage is checked for write-off. Accepts a single address or
              several (comma / semicolon / space separated). Left blank, the
              backend falls back to the configured department mailboxes. */}
          {anyWriteOffChecked && (
            <div className="mt-4 p-4 border border-status-danger-fg bg-status-danger-bg rounded-lg">
              <label className="block text-xs font-semibold text-status-danger-fg mb-1">
                Write-off notification recipients
              </label>
              <CreatableSelect
                isMulti
                isClearable
                options={writeOffUserOptions}
                value={writeOffEmails.map(e =>
                  writeOffUserOptions.find(o => o.value === e) ?? { value: e, label: e })}
                onChange={(selected) =>
                  setWriteOffEmails((selected ?? []).map(o => o.value))}
                // Only let handlers create entries that look like real emails.
                isValidNewOption={(input) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.trim())}
                formatCreateLabel={(input) => `Add "${input}"`}
                placeholder="Search a user by name or email, or type an email…"
                classNamePrefix="rs"
                menuPortalTarget={typeof document !== 'undefined' ? document.body : undefined}
                styles={{
                  control: (base) => ({ ...base, borderColor: 'rgb(var(--danger-fg))', minHeight: 38, fontSize: '0.875rem' }),
                  menuPortal: (base) => ({ ...base, zIndex: 9999 }),
                }}
              />
              <p className="mt-1 text-[11px] text-status-danger-fg">
                Pick one or more users from the list, or type an email address not in the list and press Enter to add it. These recipients are notified that the selected coverage(s) were declared a write-off. Leave blank to use the default Finance &amp; Underwriting mailboxes.
              </p>
            </div>
          )}

          {/* Submit only renders alongside the coverage table — there's
              nothing to save until the user has picked a type and entered
              at least one allocation. */}
          {!!reserveForm.transaction_type && (
            <div className="flex justify-center pt-4">
              <button
                type="button"
                onClick={handleSubmit}
                disabled={reserveSaving || coveragesLoading}
                className="px-8 py-2 bg-primary text-white text-sm font-medium rounded-md hover:bg-primary disabled:opacity-50"
              >
                {reserveSaving ? 'Saving…' : 'Submit'}
              </button>
            </div>
          )}
        </Card>
      )}

      {voidPreview && (
        <ReserveInformationModal
          row={voidPreview}
          claimNumber={claimNumber ?? null}
          onCancel={() => setVoidPreview(null)}
          onConfirm={openVoidReason}
        />
      )}

      {voidTarget && (
        <VoidPaymentModal
          reason={voidReason} setReason={setVoidReason}
          onCancel={() => setVoidTarget(null)}
          onConfirm={confirmVoid}
          saving={voidSaving}
        />
      )}

      {voidInfo && (
        <VoidInfoModal info={voidInfo} onClose={() => setVoidInfo(null)} />
      )}
      {voidInfoLoading && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30">
          <div className="bg-surface rounded-md px-5 py-3 shadow text-sm">Loading void info…</div>
        </div>
      )}

      {/* History table */}
      {!reserves || reserves.length === 0 ? <Empty text="No reserve entries." /> : (
        <div className="bg-surface border border-line rounded-xl shadow-sm overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-surface-2 text-left text-xs text-ink-faint uppercase">
              <tr>
                <th className="px-4 py-3">Date</th>
                <th className="px-4 py-3">Trans Type</th>
                <th className="px-4 py-3">Trans Sub Type</th>
                <th className="px-4 py-3 text-right">(+)</th>
                <th className="px-4 py-3 text-right">(-)</th>
                <th className="px-4 py-3 text-right">Running Balance</th>
                <th className="px-4 py-3">Inserted User</th>
                <th className="px-4 py-3">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {reserves.map(r => (
                <ReserveRow
                  key={r.id}
                  r={r}
                  expanded={expandedId === r.id}
                  toggle={() => setExpandedId(expandedId === r.id ? null : r.id)}
                  onVoid={editable !== false ? () => openVoidPreview(r.id) : undefined}
                  onVoidInfo={() => openVoidInfo(r.id)}
                />
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}

// ── Reserve inline form ─────────────────────────────────────────────────────
// Renders the Date / Trans Type / Address / Payee / Sub Type fields plus the
// payment-only block (Invoice metadata, Memo, Description, VAT, Credit Note)
// when a payment-style transaction type is selected. Mirrors graphiteBWV8
// reserves.blade — except inline on the page rather than inside a modal.
function ReserveInlineForm(props: {
  form: ReturnType<typeof emptyReserveForm>
  setForm: (f: ReturnType<typeof emptyReserveForm>) => void
  transTypes: ReserveLookupItem[]
  subTypes: ReserveLookupItem[]
  payees: ReservePayee[]
}) {
  const { form, setForm, transTypes, subTypes, payees } = props
  const showPaymentFields = isPaymentType(form.transaction_type)
  // Reset Reserves (44) shares the Loss Reserve Sub Type dropdown with the
  // regular reserve types — V8 parity. The ternary at the label line then
  // picks "Loss Reserve Sub Type" because showPaymentFields stays false.
  const showReserveSubType = isReserveType(form.transaction_type) || isResetReserveType(form.transaction_type)
  const showSubTypeSelect = showPaymentFields || showReserveSubType

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label className="block text-xs font-medium text-ink-muted mb-1">Date *</label>
          <input type="date" value={form.date}
            onChange={e => setForm({ ...form, date: e.target.value })}
            className="w-full px-3 py-2 border border-line rounded-md text-sm" />
        </div>
        <div>
          <label className="block text-xs font-medium text-ink-muted mb-1">Transaction Type *</label>
          <select value={form.transaction_type || ''}
            onChange={e => setForm({ ...form, transaction_type: Number(e.target.value), transaction_sub_type: '' })}
            className="w-full px-3 py-2 border border-line rounded-md text-sm bg-surface">
            <option value="">— Please select transaction type —</option>
            {transTypes.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
          </select>
        </div>
        <div>
          <label className="block text-xs font-medium text-ink-muted mb-1">Address</label>
          <input value={form.address}
            onChange={e => setForm({ ...form, address: e.target.value })}
            placeholder="Please enter address"
            className="w-full px-3 py-2 border border-line rounded-md text-sm" maxLength={60} />
        </div>
        <div>
          <label className="block text-xs font-medium text-ink-muted mb-1">Select Payee</label>
          <select value={String(form.payee ?? '')}
            onChange={e => {
              const id = e.target.value
              const supplier = payees.find(p => String(p.id) === id)
              setForm({ ...form, payee: id, address: supplier?.address ?? form.address })
            }}
            className="w-full px-3 py-2 border border-line rounded-md text-sm bg-surface">
            <option value="">— Please select payee —</option>
            {payees.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
          </select>
        </div>
        {showSubTypeSelect && (
          <div className="md:col-span-2">
            <label className="block text-xs font-medium text-ink-muted mb-1">
              {showPaymentFields ? 'Transaction Sub Type' : 'Loss Reserve Sub Type'}
            </label>
            <select
              value={form.transaction_sub_type === '' ? '' : String(form.transaction_sub_type)}
              onChange={e => setForm({ ...form, transaction_sub_type: e.target.value === '' ? '' : Number(e.target.value) })}
              disabled={!subTypes.length}
              className="w-full px-3 py-2 border border-line rounded-md text-sm bg-surface">
              <option value="">— Please select transaction subtype —</option>
              {subTypes.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
            </select>
          </div>
        )}
      </div>

      {showPaymentFields && (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 pt-3 border-t border-line">
          <div>
            <label className="block text-xs font-medium text-ink-muted mb-1">Invoice Date</label>
            <input type="date" value={form.invoice_date}
              onChange={e => setForm({ ...form, invoice_date: e.target.value })}
              className="w-full px-3 py-2 border border-line rounded-md text-sm" />
          </div>
          <div>
            <label className="block text-xs font-medium text-ink-muted mb-1">Invoice Due Date</label>
            <input type="date" value={form.invoice_due_date}
              onChange={e => setForm({ ...form, invoice_due_date: e.target.value })}
              className="w-full px-3 py-2 border border-line rounded-md text-sm" />
          </div>
          <div>
            <label className="block text-xs font-medium text-ink-muted mb-1">Invoice No</label>
            <input value={form.invoice_no}
              onChange={e => setForm({ ...form, invoice_no: e.target.value })}
              className="w-full px-3 py-2 border border-line rounded-md text-sm" />
          </div>
          <div className="md:col-span-3">
            <label className="block text-xs font-medium text-ink-muted mb-1">Memo On Check</label>
            <input value={form.memo}
              onChange={e => setForm({ ...form, memo: e.target.value })}
              className="w-full px-3 py-2 border border-line rounded-md text-sm" />
          </div>
          <div className="md:col-span-3">
            <label className="block text-xs font-medium text-ink-muted mb-1">Description</label>
            <textarea value={form.description}
              onChange={e => setForm({ ...form, description: e.target.value })}
              rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" />
          </div>
          <div className="md:col-span-3 flex gap-4">
            <label className="flex items-center gap-2 text-sm cursor-pointer">
              <input type="checkbox" checked={form.credit_note}
                onChange={e => setForm({ ...form, credit_note: e.target.checked })}
                className="rounded" /> Credit Note
            </label>
            <label className="flex items-center gap-2 text-sm cursor-pointer">
              <input type="checkbox" checked={form.include_vat}
                onChange={e => setForm({ ...form, include_vat: e.target.checked })}
                className="rounded" /> Include VAT
            </label>
          </div>
        </div>
      )}
    </div>
  )
}

// ── Coverage allocation table ───────────────────────────────────────────────
// V8 reserves.blade renders all of the policy's coverages as table rows with
// an editable Reserve Allocation input on each. For DOM/COM products (7, 8,
// 16-19) rows are grouped by risk address; for everything else it's a flat
// list. The user only types allocation amounts — the coverage list itself
// is read-only and pre-populated by the backend.
// graphiteBWV8 reserves.blade renders 5 columns (no Coverage Limit) for these
// "MIS" products and 6 columns (with Coverage Limit) for DOM/COM and the
// schedule-driven products (PI, MM, PAR, EAR, CAR, Travel). Match that here.
const MIS_PRODUCT_IDS = new Set([1, 2, 3, 4, 5, 6, 9, 10])

function CoverageAllocationTable(props: {
  data: ReserveCoveragesResponse
  allocations: Record<string, string>
  onChangeAllocation: (key: string, value: string) => void
  isPayment: boolean
  // When true (Loss Reserve + "Claim Expense"), an extra Write Off column of
  // per-coverage checkboxes is shown. Checking one flags that claimed item as
  // a write-off and notifies Finance + Underwriting on submit.
  writeOffEligible: boolean
  writeOffs: Record<string, boolean>
  onToggleWriteOff: (key: string, value: boolean) => void
}) {
  const { data, allocations, onChangeAllocation, isPayment, writeOffEligible, writeOffs, onToggleWriteOff } = props
  const allocationLabel = isPayment ? 'Payment Allocation' : 'Reserve Allocation'
  const showLimit = !MIS_PRODUCT_IDS.has(data.productId ?? -1)
  const colSpan = (showLimit ? 6 : 5) + (writeOffEligible ? 1 : 0)

  // ── Insured-item search (by coverage name) ──────────────────────────────
  // Reinstates the reserve "find item" affordance: a magnifying-glass search
  // that locates line items by coverage name, highlights the matches, and (on
  // Enter) scrolls to + focuses the matching row's allocation input. Purely a
  // read-only locator over the already-loaded coverage rows — it does not
  // change reserve creation/editing or any amounts.
  const [itemSearch, setItemSearch] = useState('')
  const q = itemSearch.trim().toLowerCase()
  const allocInputs = useRef<Record<string, HTMLInputElement | null>>({})
  const isMatch = (r: ReserveCoverageRow) => !!q && (r.coverageName ?? '').toLowerCase().includes(q)
  const orderedRows: ReserveCoverageRow[] = data.isGrouped && data.groups
    ? Object.values(data.groups).flat()
    : data.rows
  const matchCount = q ? orderedRows.filter(isMatch).length : 0
  const focusFirstMatch = () => {
    const first = orderedRows.find(isMatch)
    if (!first) return
    const el = allocInputs.current[rowKeyOf(first)]
    if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); el.focus() }
  }

  // Render a single coverage row. Extracted so the grouped + flat layouts
  // share identical markup.
  const renderRow = (r: ReserveCoverageRow) => {
    const k = rowKeyOf(r)
    const hit = isMatch(r)
    return (
      <tr key={k} className={`border-t border-line ${q ? (hit ? 'bg-status-warning-bg' : 'opacity-40') : ''}`}>
        <td className="px-4 py-2 text-sm text-ink">{r.coverageName || '—'}</td>
        {showLimit && (
          <td className="px-4 py-2 text-sm text-right text-ink-muted coverage-limit">
            {r.coverageLimit !== null ? fmt(r.coverageLimit) : '—'}
          </td>
        )}
        <td className="px-4 py-2 text-sm text-right text-ink-muted reserve-created">{fmt(r.reserveAmt)}</td>
        <td className="px-4 py-2 text-sm text-right text-ink-muted">{fmt(r.paymentAmt)}</td>
        <td className="px-4 py-2 text-sm text-right text-ink-muted">{fmt(r.balance)}</td>
        <td className="px-4 py-2 text-right">
          <input
            ref={el => { allocInputs.current[k] = el }}
            type="number" step="0.01" min="0"
            inputMode="decimal"
            onWheel={e => e.currentTarget.blur()}
            onKeyDown={e => { if (e.key === 'ArrowUp' || e.key === 'ArrowDown') e.preventDefault() }}
            value={allocations[k] ?? ''}
            onChange={e => onChangeAllocation(k, e.target.value)}
            className={`no-spinner w-32 px-2 py-1.5 border rounded text-sm text-right ${hit ? 'ring-2 ring-status-warning-fg border-status-warning-fg' : ''}`}
          />
        </td>
        {writeOffEligible && (
          <td className="px-4 py-2 text-center">
            {r.writtenOff ? (
              // Already written off — a claimed item can only be written off
              // once, so show the status instead of an actionable checkbox.
              <span
                className="inline-block px-2 py-0.5 text-[10px] font-semibold rounded bg-status-danger-bg text-status-danger-fg"
                title="This item has already been written off and cannot be written off again."
              >
                WRITTEN OFF
              </span>
            ) : (
              <input
                type="checkbox"
                checked={!!writeOffs[k]}
                onChange={e => onToggleWriteOff(k, e.target.checked)}
                className="rounded"
                aria-label={`Write off ${r.coverageName || 'coverage'}`}
              />
            )}
          </td>
        )}
      </tr>
    )
  }

  return (
    <div className="pt-4 border-t border-line mt-4">
      {/* Insured-item search — locate line items by coverage name, then
          highlight + focus the matching allocation row. */}
      <div className="flex items-center gap-3 mb-3">
        <div className="relative w-full max-w-sm">
          <span className="absolute left-2.5 top-1/2 -translate-y-1/2 text-ink-faint pointer-events-none">
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z" />
            </svg>
          </span>
          <input
            type="text"
            value={itemSearch}
            onChange={e => setItemSearch(e.target.value)}
            onKeyDown={e => { if (e.key === 'Enter') { e.preventDefault(); focusFirstMatch() } }}
            placeholder="Search insured items by coverage name…"
            className="w-full pl-8 pr-7 py-1.5 text-sm border border-line rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"
          />
          {itemSearch && (
            <button
              type="button"
              onClick={() => setItemSearch('')}
              className="absolute right-2 top-1/2 -translate-y-1/2 text-ink-faint hover:text-ink-muted"
              aria-label="Clear search"
            >×</button>
          )}
        </div>
        {q && (
          <span className="text-xs text-ink-faint whitespace-nowrap">
            {matchCount} match{matchCount === 1 ? '' : 'es'}{matchCount > 0 ? ' — press Enter to jump' : ''}
          </span>
        )}
      </div>

      <div className="overflow-x-auto">
      <table className="w-full text-sm">
        <thead className="bg-surface-2 text-left text-xs text-ink-faint uppercase">
          <tr>
            <th className="px-4 py-2 font-medium">Coverage Name</th>
            {showLimit && <th className="px-4 py-2 font-medium text-right">Coverage Limit</th>}
            <th className="px-4 py-2 font-medium text-right">Reserve Created</th>
            <th className="px-4 py-2 font-medium text-right">Payment Created</th>
            <th className="px-4 py-2 font-medium text-right">Balance</th>
            <th className="px-4 py-2 font-medium text-right">{allocationLabel}</th>
            {writeOffEligible && <th className="px-4 py-2 font-medium text-center">Write Off</th>}
          </tr>
        </thead>
        <tbody>
          {data.isGrouped && data.groups
            ? Object.entries(data.groups).map(([addr, rows]) => (
                <>
                  <tr key={`hdr-${addr}`} className="bg-surface-2">
                    <td colSpan={colSpan} className="px-4 py-2 text-center font-semibold text-ink-muted">
                      {addr}
                    </td>
                  </tr>
                  {rows.map(renderRow)}
                </>
              ))
            : data.rows.map(renderRow)
          }
        </tbody>
      </table>
      </div>
    </div>
  )
}
// Step-1 read-only modal that opens when the user clicks "Void Payment" on
// an active payment row. Mirrors graphiteBWV8 #voidPaymentDetailsModal — the
// user reviews the payment's metadata and clicks "Void This Payment" to
// advance to the reason-capture modal.
function ReserveInformationModal({ row, claimNumber, onCancel, onConfirm }: {
  row: ClaimReserve
  claimNumber: string | null
  onCancel: () => void
  onConfirm: () => void
}) {
  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto" onClick={onCancel}>
      <div className="bg-surface rounded-xl shadow-2xl w-full max-w-lg p-5 space-y-3 max-h-[85vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-bold text-ink">Reserve Information</h2>
          <button onClick={onCancel} className="text-ink-faint hover:text-ink-muted text-xl leading-none">×</button>
        </div>
        <DL items={[
          ['Reference No', claimNumber ?? '-'],
          ['Date', fmtDate(row.date)],
          ['Transaction Type', row.transactionTypeName || '-'],
          ['Transaction Sub Type', row.transactionSubTypeName || '-'],
          ['Description', row.description || '-'],
          ['Memo On Check', row.memo || '-'],
          ['Payee Name', row.payeeName || '-'],
        ]} />
        <div className="flex justify-end pt-2">
          <button onClick={onConfirm}
            className="px-5 py-2 text-sm font-medium bg-primary text-primary-contrast rounded-md hover:opacity-90">
            Void This Payment
          </button>
        </div>
      </div>
    </div>
  )
}

// Confirmation modal for voiding a payment. Mirrors graphiteBWV8 #voidPaymentStore
// modal — captures a reason, then POSTs to the void endpoint.
function VoidPaymentModal(props: {
  reason: string; setReason: (s: string) => void
  onCancel: () => void; onConfirm: () => void
  saving: boolean
}) {
  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto" onClick={props.onCancel}>
      <div className="bg-surface rounded-xl shadow-2xl w-full max-w-md p-5 space-y-2.5 max-h-[85vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
        <h2 className="text-lg font-bold">Void Payment</h2>
        <p className="text-sm text-ink-muted">This will reverse the payment with an offsetting entry. Please give a reason.</p>
        <div>
          <label className="block text-xs font-medium text-ink-faint mb-1">Reason for Void *</label>
          <textarea rows={4} value={props.reason}
            onChange={e => props.setReason(e.target.value)}
            className="w-full px-3 py-2 border border-line rounded-md text-sm" />
        </div>
        <div className="flex justify-end gap-2 pt-2">
          <button onClick={props.onCancel} className="px-4 py-2 text-sm border border-line rounded-md">Cancel</button>
          <button onClick={props.onConfirm} disabled={props.saving || !props.reason.trim()}
            className="px-4 py-2 text-sm bg-status-danger-fg text-white rounded-md disabled:opacity-50">
            {props.saving ? 'Voiding…' : 'Void'}
          </button>
        </div>
      </div>
    </div>
  )
}

// Read-only "Void Info" modal. Mirrors graphiteBWV8 #voidedPaymentInfoModal —
// shows who voided, when, the amount, and the reason. Backed by the
// claim_void_payment_logs table.
function VoidInfoModal({ info, onClose }: { info: VoidPaymentInfo; onClose: () => void }) {
  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto" onClick={onClose}>
      <div className="bg-surface rounded-xl shadow-2xl w-full max-w-md p-5 space-y-2.5 max-h-[85vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
        <h2 className="text-lg font-bold">Voided Payment Info</h2>
        <DL items={[
          ['Reference No', info.claimNumber || '-'],
          ['Payment Date', fmtDate(info.paymentVoidDate ?? undefined)],
          ['Payment Amount', fmt(info.amount)],
          ['Voided By', info.paymentVoidByName || '-'],
          ['Reason for Void', info.reasonForVoid || '-'],
        ]} />
        <div className="flex justify-end pt-2">
          <button onClick={onClose} className="px-4 py-2 text-sm border border-line rounded-md">Close</button>
        </div>
      </div>
    </div>
  )
}

function ReserveRow({ r, expanded, toggle, onVoid, onVoidInfo }: {
  r: ClaimReserve; expanded: boolean; toggle: () => void
  onVoid?: () => void; onVoidInfo?: () => void
}) {
  const plus  = Number(r.plusAmount  ?? 0)
  const minus = Number(r.minusAmount ?? 0)
  const bal   = Number(r.runningBalance ?? 0)
  // graphiteBWV8 appends " - VOID" to the trans-type label on the reversal
  // entry (is_payment_voided=2). Replicate that here so the user can spot
  // the void row at a glance.
  const typeName = (r.transactionTypeName || String(r.transactionType)) + (r.isVoided ? ' - VOID' : '')
  // Backend now surfaces wasVoided directly (true when the underlying
  // claim_reserves_coverages row carries is_payment_voided=1, i.e. the
  // original that got voided). That row gets the Void Info button instead
  // of the Void Payment button, and renders with a yellow background.
  const wasVoided = !!r.wasVoided
  // graphiteBWV8 paints the original voided row yellow (the "highlighted-row"
  // CSS in general.blade.php). The reversal entry (is_payment_voided=2,
  // surfaced here via r.isVoided) is left un-highlighted — it just carries
  // the " - VOID" suffix on its trans-type label.
  const rowBg = wasVoided ? 'bg-status-warning-bg hover:bg-status-warning-bg' : 'hover:bg-surface-2'
  return (
    <>
      <tr className={rowBg}>
        <td className="px-4 py-3 cursor-pointer" onClick={toggle}>{fmtDate(r.date)}</td>
        <td className="px-4 py-3 cursor-pointer" onClick={toggle}>{typeName}</td>
        <td className="px-4 py-3">
          <span className="inline-flex items-center gap-1.5">
            {r.transactionSubTypeName || '-'}
            {r.hasWriteOff && (
              <span className="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold bg-status-danger-bg text-status-danger-fg uppercase tracking-wide">
                Write-off
              </span>
            )}
          </span>
        </td>
        <td className="px-4 py-3 text-right font-semibold">{plus > 0 ? fmt(plus) : '-'}</td>
        <td className="px-4 py-3 text-right font-semibold text-status-danger-fg">{minus > 0 ? `-${fmt(minus)}` : '-'}</td>
        <td className="px-4 py-3 text-right font-semibold">{fmt(bal)}</td>
        <td className="px-4 py-3">{r.insertedByName || '-'}</td>
        <td className="px-4 py-3">
          <div className="flex gap-1.5">
            {r.canVoid && onVoid && (
              <button onClick={onVoid}
                className="px-2 py-1 text-xs font-medium bg-primary text-white rounded hover:bg-primary">
                Void Payment
              </button>
            )}
            {wasVoided && onVoidInfo && (
              <button onClick={onVoidInfo}
                className="px-2 py-1 text-xs font-medium bg-status-success-fg text-white rounded hover:bg-status-success-fg">
                Void Info
              </button>
            )}
            {!r.canVoid && !wasVoided && <span className="text-ink-faint text-xs">-</span>}
          </div>
        </td>
      </tr>
      {expanded && (
        <tr>
          <td colSpan={8} className="bg-surface-2 px-4 py-3">
            {(r.payee || r.payeeName || (r as any).address ||
              r.invoiceNo || (r as any).invoiceDate ||
              r.memo || r.description ||
              r.includeVat || r.creditNote) && (
              <div className="mb-3 grid grid-cols-2 md:grid-cols-4 gap-x-4 gap-y-1 text-xs">
                {r.payeeName && <div><span className="text-ink-faint">Payee:</span> <span className="font-medium">{r.payeeName}</span></div>}
                {(r as any).address && <div><span className="text-ink-faint">Address:</span> <span className="font-medium">{(r as any).address}</span></div>}
                {r.invoiceNo && <div><span className="text-ink-faint">Invoice #:</span> <span className="font-medium">{r.invoiceNo}</span></div>}
                {(r as any).invoiceDate && <div><span className="text-ink-faint">Invoice Date:</span> <span className="font-medium">{fmtDate((r as any).invoiceDate)}</span></div>}
                {r.memo && <div className="col-span-2 md:col-span-2"><span className="text-ink-faint">Memo:</span> <span className="font-medium">{r.memo}</span></div>}
                {r.description && <div className="col-span-2 md:col-span-4"><span className="text-ink-faint">Description:</span> <span className="font-medium">{r.description}</span></div>}
                {r.includeVat && <div><span className="text-status-success-fg font-medium">✓ Includes VAT</span></div>}
                {r.creditNote == 1 && <div><span className="text-status-warning-fg font-medium">✓ Credit Note</span></div>}
              </div>
            )}
            {r.coverages.length > 0 ? (
              <table className="w-full text-xs">
                <thead className="text-ink-faint uppercase">
                  <tr>
                    <th className="text-left py-1">Coverage</th>
                    <th className="text-right py-1">Reserve</th>
                    <th className="text-right py-1">Payment</th>
                    <th className="text-right py-1">Balance</th>
                  </tr>
                </thead>
                <tbody>
                  {r.coverages.map(cov => (
                    <tr key={cov.coverageId}>
                      <td className="py-1">
                        {cov.coverageName}
                        {cov.writeOff && (
                          <span className="ml-2 inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold bg-status-danger-bg text-status-danger-fg align-middle">
                            WRITE-OFF
                          </span>
                        )}
                      </td>
                      <td className="text-right py-1">{fmt(cov.reserveAmt)}</td>
                      <td className="text-right py-1">{fmt(cov.paymentAmt)}</td>
                      <td className="text-right py-1">{fmt(cov.balance)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            ) : (
              <p className="text-xs text-ink-faint">No coverage breakdown.</p>
            )}
          </td>
        </tr>
      )}
    </>
  )
}

function TabSuppliers({ claimId, quotes, editable, onRefresh }: { claimId: number; quotes?: ClaimQuote[]; editable: boolean; onRefresh: () => void }) {
  const { toast } = useToast()
  const [showForm, setShowForm] = useState(false)
  const [saving, setSaving] = useState(false)
  const [form, setForm] = useState({ supplier_name: '', amount: '', description: '' })

  async function handleAddQuote() {
    setSaving(true)
    try {
      await apiClient.post(`/claims-v2/${claimId}/quotes`, {
        supplier_name: form.supplier_name,
        amount: Number(form.amount) || 0,
        description: form.description,
      })
      setShowForm(false)
      setForm({ supplier_name: '', amount: '', description: '' })
      onRefresh()
    } catch (e: any) { toast.error(e.response?.data?.message || 'Failed') }
    finally { setSaving(false) }
  }
  const qStatusColor = (s?: string) => {
    if (!s) return 'bg-surface-2 text-ink-muted'
    const sl = s.toLowerCase()
    if (sl === 'accepted' || sl === 'approved') return 'bg-status-success-bg text-status-success-fg'
    if (sl === 'rejected') return 'bg-status-danger-bg text-status-danger-fg'
    return 'bg-status-warning-bg text-status-warning-fg'
  }
  return (
    <div className="space-y-4">
      {editable && (
        <div className="flex justify-end">
          <button onClick={() => setShowForm(true)} className="px-4 py-2 bg-primary text-white rounded-md text-sm font-medium hover:bg-primary">+ Add Quote</button>
        </div>
      )}
      {showForm && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto" onClick={() => setShowForm(false)}>
          <div className="bg-surface rounded-xl shadow-2xl w-full max-w-md mx-4 p-5 space-y-2.5 max-h-[85vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
            <h2 className="text-lg font-bold">Add Supplier Quote</h2>
            <div><label className="block text-xs font-medium text-ink-faint mb-1">Supplier Name</label>
              <input value={form.supplier_name} onChange={e => setForm({...form, supplier_name: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
            <div><label className="block text-xs font-medium text-ink-faint mb-1">Amount (P)</label>
              <input type="number" value={form.amount} onChange={e => setForm({...form, amount: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
            <div><label className="block text-xs font-medium text-ink-faint mb-1">Description</label>
              <textarea value={form.description} onChange={e => setForm({...form, description: e.target.value})} rows={3} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
            <div className="flex justify-end gap-2 pt-2">
              <button onClick={() => setShowForm(false)} className="px-4 py-2 text-sm border border-line rounded-md">Cancel</button>
              <button onClick={handleAddQuote} disabled={saving} className="px-4 py-2 text-sm bg-primary text-white rounded-md disabled:opacity-50">{saving ? 'Saving...' : 'Save'}</button>
            </div>
          </div>
        </div>
      )}
      {(!quotes || quotes.length === 0) && <Empty text="No supplier quotes." />}
    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
      {(quotes ?? []).map(q => (
        <div key={q.id} className="bg-surface border border-line rounded-xl p-5 shadow-sm">
          <div className="flex items-start justify-between mb-3">
            <p className="text-sm font-semibold text-ink">Quote #{q.id}</p>
            {q.status && <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${qStatusColor(q.status)}`}>{q.status}</span>}
          </div>
          <dl className="text-sm space-y-2">
            <div className="flex justify-between"><dt className="text-ink-faint">Total</dt><dd className="font-medium">{fmt(q.total)}</dd></div>
            {q.selectReason && <div className="flex justify-between"><dt className="text-ink-faint">Reason</dt><dd>{q.selectReason}</dd></div>}
            {q.invoiceNotes && <div className="flex justify-between"><dt className="text-ink-faint">Notes</dt><dd className="truncate max-w-[180px]">{q.invoiceNotes}</dd></div>}
            <div className="flex justify-between"><dt className="text-ink-faint">Date</dt><dd>{fmtDate(q.createdAt)}</dd></div>
          </dl>
          <div className="flex gap-3 mt-3 pt-3 border-t border-line">
            <FileLink url={q.claimFile} label="Claim File" />
            <FileLink url={q.invoice} label="Invoice" />
          </div>
        </div>
      ))}
    </div>
    </div>
  )
}

function TabActivityLog({ log }: { log?: ClaimActivity[] }) {
  if (!log || log.length === 0) return <Empty text="No activity log entries." />
  const sorted = [...log].sort((a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime())
  return (
    <div className="space-y-0 border-l-2 border-line ml-4">
      {sorted.map(entry => (
        <div key={entry.id} className="relative pl-6 pb-6 last:pb-0">
          <span className="absolute -left-[5px] top-1.5 w-2 h-2 rounded-full bg-primary" />
          <p className="text-sm text-ink">{entry.description}</p>
          <p className="text-xs text-ink-faint mt-0.5">
            {entry.userName && <span className="font-medium">{entry.userName}</span>}
            {entry.userName && ' -- '}{fmtDate(entry.createdAt)}
          </p>
        </div>
      ))}
    </div>
  )
}

// ── Reinsurance tab ────────────────────────────────────────────
// Read-only view of the claim's reinsurance exposure, pro-rated from
// the underlying policy's policy_reinsurance allocation. Not in legacy
// graphiteBWV8 — V2 addition per CFO Finding 6. Indicative only; the
// reinsurer recoverable ledger handles actual figures.
function TabReinsurance({ claimId }: { claimId: number }) {
  const [data, setData] = useState<any>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  useEffect(() => {
    setLoading(true); setError(null)
    apiClient.get(`/claims/${claimId}/reinsurance`)
      .then(r => setData(r.data))
      .catch((e: any) => setError(e?.response?.data?.message || e?.message || 'Failed to load'))
      .finally(() => setLoading(false))
  }, [claimId])

  const fmt = (n: any) => Number(n ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

  if (loading) return <div className="p-8 text-center text-ink-faint">Loading reinsurance exposure…</div>
  if (error)   return <div className="p-4 bg-status-danger-bg border border-status-danger-fg rounded text-sm text-status-danger-fg">{error}</div>
  if (!data)   return <Empty text="No data." />

  if (!data.has_allocation) {
    return (
      <div className="bg-status-warning-bg border border-status-warning-fg rounded-lg p-4 text-sm text-status-warning-fg space-y-2">
        <div className="font-semibold">No reinsurance allocation for this policy.</div>
        <div>{data.message}</div>
      </div>
    )
  }

  const t = data.totals

  return (
    <div className="space-y-4">
      {/* Summary cards */}
      <div className="grid grid-cols-4 gap-3 text-sm">
        <div className="border border-line rounded-lg p-3">
          <div className="text-[10px] uppercase tracking-wide text-ink-faint">Claim reserve (gross)</div>
          <div className="text-base font-semibold">P {fmt(data.claim.total_reserve)}</div>
        </div>
        <div className="border border-line rounded-lg p-3 bg-status-info-bg/40">
          <div className="text-[10px] uppercase tracking-wide text-primary">Est. net retention</div>
          <div className="text-base font-semibold text-primary">P {fmt(t.est_reserve_retained)}</div>
          <div className="text-[10px] text-primary">{t.net_retention_pct}% of policy premium</div>
        </div>
        <div className="border border-line rounded-lg p-3 bg-status-accent-bg/40">
          <div className="text-[10px] uppercase tracking-wide text-status-accent-fg">Est. ceded to reinsurers</div>
          <div className="text-base font-semibold text-status-accent-fg">P {fmt(t.est_reserve_ceded)}</div>
          <div className="text-[10px] text-status-accent-fg">{t.ceded_pct}% of policy premium</div>
        </div>
        <div className="border border-line rounded-lg p-3">
          <div className="text-[10px] uppercase tracking-wide text-ink-faint">Paid (gross)</div>
          <div className="text-base font-semibold">P {fmt(data.claim.total_payment)}</div>
          <div className="text-[10px] text-ink-faint">Retained P {fmt(t.est_payment_retained)} · Ceded P {fmt(t.est_payment_ceded)}</div>
        </div>
      </div>

      {/* Per-treaty breakdown */}
      <div className="bg-surface border border-line rounded-xl overflow-hidden">
        <div className="px-4 py-2 bg-surface-2 border-b border-line text-xs font-medium text-ink-muted uppercase tracking-wide">Treaty breakdown</div>
        <table className="w-full text-sm">
          <thead className="bg-surface-2 text-left text-xs text-ink-faint uppercase">
            <tr>
              <th className="px-4 py-2">Treaty</th>
              <th className="px-4 py-2">Type</th>
              <th className="px-4 py-2 text-right">Share %</th>
              <th className="px-4 py-2 text-right">Est. reserve share</th>
              <th className="px-4 py-2 text-right">Est. payment share</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-line">
            {data.breakdown.map((b: any, i: number) => (
              <tr key={i} className={`hover:bg-surface-2 ${b.is_retained ? 'bg-status-info-bg/20' : ''}`}>
                <td className="px-4 py-2 font-medium text-ink">{b.treaty_name || '—'}</td>
                <td className="px-4 py-2 text-ink-muted">
                  {b.type_name}
                  {b.is_retained && <span className="ml-2 text-[10px] px-1.5 py-0.5 bg-status-info-bg text-primary rounded-full">retained</span>}
                </td>
                <td className="px-4 py-2 text-right">{b.share_pct}%</td>
                <td className="px-4 py-2 text-right font-semibold">P {fmt(b.est_reserve_ceded)}</td>
                <td className="px-4 py-2 text-right">P {fmt(b.est_payment_ceded)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Disclaimer */}
      <div className="text-xs text-ink-faint border-t border-line pt-2 flex items-start gap-1.5">
        <span>ℹ</span>
        <span>
          {data.note} Source: policy action #{data.source_action_id}.
        </span>
      </div>
    </div>
  )
}

// graphiteBWV8 complaintLog.blade hardcodes these three options. Mirror so
// that operators on V2 see the same list and the FE/BE values stay aligned.
const COMPLAINT_OF_OPTIONS = ['Parts', 'Assessors', 'Service Provider']

const EMPTY_COMPLAINT = {
  complainant_name: '', complainant_id_type: '', complainant_omang: '', complainant_passport: '',
  complainant_phone: '', complainant_email: '', complainant_postal_address: '',
  date_filed: '', reference_number: '', nature: '', complaint_of: '', complaint_details: '',
  handler_user_id: null as number | null, handler_name: '', escalation_level: '', status: '',
  rejection_reason: '', resolution: '', closed_at: '',
}
type ComplaintForm = typeof EMPTY_COMPLAINT

// Regulatory Complaints Register capture. Logs a complaint against the claim
// with all fields the quarterly regulator submission needs; prefills complainant
// / handler / reference from the claim's customer (editable) and supports
// attaching supporting correspondence.
function TabComplaints({ claimId, policyId, complaints, editable, onRefresh }: {
  claimId: number
  policyId?: number | null
  complaints?: ClaimComplaint[]
  editable: boolean
  onRefresh: () => void
}) {
  const [rows, setRows] = useState<ClaimComplaint[]>(complaints ?? [])
  const [lookups, setLookups] = useState<ComplaintLookups | null>(null)
  const [showForm, setShowForm] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [form, setForm] = useState<ComplaintForm>(EMPTY_COMPLAINT)
  const [files, setFiles] = useState<File[]>([])
  const [saving, setSaving] = useState(false)
  const [prefilling, setPrefilling] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const upd = (k: keyof ComplaintForm, v: any) => setForm(f => ({ ...f, [k]: v }))

  const loadRows = async () => {
    try { setRows(await fetchClaimComplaints(claimId)) } catch { /* keep current rows */ }
  }

  useEffect(() => {
    fetchComplaintLookups().then(setLookups).catch(() => setLookups(null))
    loadRows()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [claimId])

  const openAdd = async () => {
    setEditingId(null); setError(null); setFiles([]); setForm(EMPTY_COMPLAINT); setShowForm(true)
    setPrefilling(true)
    try {
      const p = await fetchComplaintPrefill(claimId)
      setForm(f => ({
        ...f,
        complainant_name: p.complainant_name ?? '',
        complainant_id_type: p.complainant_id_type || (p.complainant_omang ? 'Omang' : (p.complainant_passport ? 'Passport' : '')),
        complainant_omang: p.complainant_omang ?? '',
        complainant_passport: p.complainant_passport ?? '',
        complainant_phone: p.complainant_phone ?? '',
        complainant_email: p.complainant_email ?? '',
        complainant_postal_address: p.complainant_postal_address ?? '',
        reference_number: p.reference_number ?? '',
        handler_user_id: p.handler_user_id ?? null,
        handler_name: p.handler_name ?? '',
        date_filed: new Date().toISOString().slice(0, 10),
        status: 'Open',
      }))
    } catch { /* prefill is best-effort */ }
    finally { setPrefilling(false) }
  }

  const openEdit = (cp: ClaimComplaint) => {
    setEditingId(cp.id); setError(null); setFiles([]); setShowForm(true)
    setForm({
      complainant_name: cp.complainant_name ?? '',
      complainant_id_type: cp.complainant_id_type || (cp.complainant_omang ? 'Omang' : (cp.complainant_passport ? 'Passport' : '')),
      complainant_omang: cp.complainant_omang ?? '',
      complainant_passport: cp.complainant_passport ?? '',
      complainant_phone: cp.complainant_phone ?? '',
      complainant_email: cp.complainant_email ?? '',
      complainant_postal_address: cp.complainant_postal_address ?? '',
      date_filed: cp.date_filed ?? '',
      reference_number: cp.reference_number ?? '',
      nature: cp.nature ?? '',
      complaint_of: cp.complaint_of ?? cp.complaintOf ?? '',
      complaint_details: cp.complaint_details ?? cp.complaintDetails ?? '',
      handler_user_id: cp.handler_user_id ?? null,
      handler_name: cp.handler_name ?? '',
      escalation_level: cp.escalation_level ?? '',
      status: cp.status ?? '',
      rejection_reason: cp.rejection_reason ?? '',
      resolution: cp.resolution ?? '',
      closed_at: cp.closed_at ?? '',
    })
  }

  const handleCancel = () => {
    setShowForm(false); setEditingId(null); setForm(EMPTY_COMPLAINT); setFiles([]); setError(null)
  }

  const handleSubmit = async () => {
    setError(null)
    if (!form.complainant_name.trim()) { setError('Complainant name is required.'); return }
    if (!form.date_filed) { setError('Date complaint filed is required.'); return }
    if (!form.nature) { setError('Nature of complaint is required.'); return }
    if (!form.complaint_details.trim()) { setError('Complaint details are required.'); return }
    if (!form.status) { setError('Current status is required.'); return }
    if (form.complainant_id_type === 'Omang' && !form.complainant_omang.trim()) { setError('Omang is required for a citizen complainant.'); return }
    if (form.complainant_id_type === 'Passport' && !form.complainant_passport.trim()) { setError('Passport is required for a non-citizen complainant.'); return }

    const payload: ComplaintPayload = {
      policy_id: policyId ?? null,
      claim_id: claimId,
      complainant_name: form.complainant_name.trim(),
      complainant_id_type: form.complainant_id_type || null,
      complainant_omang: form.complainant_omang || null,
      complainant_passport: form.complainant_passport || null,
      complainant_phone: form.complainant_phone || null,
      complainant_email: form.complainant_email || null,
      complainant_postal_address: form.complainant_postal_address || null,
      date_filed: form.date_filed,
      reference_number: form.reference_number || null,
      complaint_of: form.complaint_of || null,
      nature: form.nature,
      complaint_details: form.complaint_details.trim(),
      handler_user_id: form.handler_user_id ?? null,
      handler_name: form.handler_name || null,
      escalation_level: form.escalation_level || null,
      status: form.status,
      rejection_reason: form.rejection_reason || null,
      resolution: form.resolution || null,
      closed_at: form.closed_at || null,
    }
    setSaving(true)
    try {
      let complaintId: number | null = editingId
      if (editingId) {
        await updateClaimComplaint(editingId, payload)
      } else {
        const res = await createClaimComplaint(payload)
        complaintId = res?.data?.id ?? null
      }
      if (complaintId && files.length) {
        // The complaint is already saved — treat the attachment as best-effort
        // so an upload hiccup neither loses the record nor tempts a re-submit
        // (which would duplicate the complaint).
        try {
          await uploadComplaintDocument(complaintId, files, 'Complaint correspondence')
        } catch (upErr: any) {
          const m = upErr?.response?.data?.message || 'attachment upload failed'
          alert('Complaint saved, but the attachment could not be uploaded: ' + m + '. You can re-attach it via Edit.')
        }
      }
      handleCancel()
      await loadRows()
      onRefresh()
    } catch (e: any) {
      const d = e?.response?.data ?? {}
      const detail = d.errors ? Object.values(d.errors).flat().join(' · ') : ''
      setError([d.message || 'Failed to save complaint', detail].filter(Boolean).join(' — '))
    } finally {
      setSaving(false)
    }
  }

  const idOpts = lookups?.id_type ?? ['Omang', 'Passport']
  const natureOpts = lookups?.nature ?? []
  const statusOpts = lookups?.status ?? []
  const escOpts = lookups?.escalation_level ?? []
  const fieldCls = 'w-full px-3 py-2 border border-line rounded-md text-sm'
  const labelCls = 'text-xs text-ink-muted mb-1 block'

  return (
    <div className="space-y-4">
      <Card
        title="Complaint Log"
        action={editable && !showForm ? (
          <button type="button" onClick={openAdd}
            className="px-3 py-1.5 text-xs font-medium rounded-md bg-status-success-fg text-white hover:bg-status-success-fg">
            Add Complaint
          </button>
        ) : null}
      >
        {showForm && (
          <div className="space-y-5 mb-5">
            {error && <div className="p-3 bg-status-danger-bg border border-status-danger-fg rounded-md text-status-danger-fg text-sm">{error}</div>}
            {prefilling && <div className="text-xs text-ink-faint">Prefilling from claim…</div>}

            {/* Complainant */}
            <div>
              <div className="text-xs font-semibold text-ink-muted uppercase mb-2">Complainant</div>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                  <label className={labelCls}>Name of Complainant <span className="text-status-danger-fg">*</span></label>
                  <input value={form.complainant_name} onChange={e => upd('complainant_name', e.target.value)} className={fieldCls} />
                </div>
                <div>
                  <label className={labelCls}>ID Type</label>
                  <select value={form.complainant_id_type} onChange={e => upd('complainant_id_type', e.target.value)} className={fieldCls}>
                    <option value="">— Select —</option>
                    {idOpts.map(o => <option key={o} value={o}>{o}</option>)}
                  </select>
                </div>
                {form.complainant_id_type !== 'Passport' && (
                  <div>
                    <label className={labelCls}>Omang {form.complainant_id_type === 'Omang' && <span className="text-status-danger-fg">*</span>}</label>
                    <input value={form.complainant_omang} onChange={e => upd('complainant_omang', e.target.value)} className={fieldCls} />
                  </div>
                )}
                {form.complainant_id_type !== 'Omang' && (
                  <div>
                    <label className={labelCls}>Passport {form.complainant_id_type === 'Passport' && <span className="text-status-danger-fg">*</span>}</label>
                    <input value={form.complainant_passport} onChange={e => upd('complainant_passport', e.target.value)} className={fieldCls} />
                  </div>
                )}
                <div>
                  <label className={labelCls}>Contact Telephone</label>
                  <input value={form.complainant_phone} onChange={e => upd('complainant_phone', e.target.value)} className={fieldCls} />
                </div>
                <div>
                  <label className={labelCls}>Contact Email</label>
                  <input value={form.complainant_email} onChange={e => upd('complainant_email', e.target.value)} className={fieldCls} />
                </div>
                <div className="md:col-span-2">
                  <label className={labelCls}>Postal Address</label>
                  <textarea value={form.complainant_postal_address} onChange={e => upd('complainant_postal_address', e.target.value)} rows={2} className={fieldCls} />
                </div>
              </div>
            </div>

            {/* Complaint */}
            <div>
              <div className="text-xs font-semibold text-ink-muted uppercase mb-2">Complaint</div>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                  <label className={labelCls}>Date Complaint Filed <span className="text-status-danger-fg">*</span></label>
                  <input type="date" value={form.date_filed} onChange={e => upd('date_filed', e.target.value)} className={fieldCls} />
                </div>
                <div>
                  <label className={labelCls}>Reference / Contract Number</label>
                  <input value={form.reference_number} onChange={e => upd('reference_number', e.target.value)} className={fieldCls} placeholder="Defaults to claim number" />
                </div>
                <div>
                  <label className={labelCls}>Nature of Complaint <span className="text-status-danger-fg">*</span></label>
                  <select value={form.nature} onChange={e => upd('nature', e.target.value)} className={fieldCls}>
                    <option value="">— Select —</option>
                    {natureOpts.map(o => <option key={o} value={o}>{o}</option>)}
                  </select>
                </div>
                <div>
                  <label className={labelCls}>Complaint Of</label>
                  <select value={form.complaint_of} onChange={e => upd('complaint_of', e.target.value)} className={fieldCls}>
                    <option value="">— Optional —</option>
                    {COMPLAINT_OF_OPTIONS.map(o => <option key={o} value={o}>{o}</option>)}
                  </select>
                </div>
                <div className="md:col-span-2">
                  <label className={labelCls}>Description of Complaint <span className="text-status-danger-fg">*</span></label>
                  <textarea value={form.complaint_details} onChange={e => upd('complaint_details', e.target.value)} rows={3} className={fieldCls} placeholder="Enter complaint details" />
                </div>
              </div>
            </div>

            {/* Handling */}
            <div>
              <div className="text-xs font-semibold text-ink-muted uppercase mb-2">Handling</div>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                  <label className={labelCls}>Employee Handling</label>
                  <input value={form.handler_name} onChange={e => upd('handler_name', e.target.value)}
                    className={fieldCls} placeholder="Name of employee handling" />
                </div>
                <div>
                  <label className={labelCls}>Highest Escalation Level</label>
                  <select value={form.escalation_level} onChange={e => upd('escalation_level', e.target.value)} className={fieldCls}>
                    <option value="">— Select —</option>
                    {escOpts.map(o => <option key={o} value={o}>{o}</option>)}
                  </select>
                </div>
                <div>
                  <label className={labelCls}>Current Status <span className="text-status-danger-fg">*</span></label>
                  <select value={form.status} onChange={e => upd('status', e.target.value)} className={fieldCls}>
                    <option value="">— Select —</option>
                    {statusOpts.map(o => <option key={o} value={o}>{o}</option>)}
                  </select>
                </div>
                <div>
                  <label className={labelCls}>Closed Date</label>
                  <input type="date" value={form.closed_at} onChange={e => upd('closed_at', e.target.value)} className={fieldCls} />
                </div>
                {form.status === 'Rejected' && (
                  <div className="md:col-span-2">
                    <label className={labelCls}>Reason for Rejection</label>
                    <textarea value={form.rejection_reason} onChange={e => upd('rejection_reason', e.target.value)} rows={2} className={fieldCls} />
                  </div>
                )}
                <div className="md:col-span-2">
                  <label className={labelCls}>Resolution / Outcome</label>
                  <textarea value={form.resolution} onChange={e => upd('resolution', e.target.value)} rows={2} className={fieldCls} />
                </div>
              </div>
            </div>

            {/* Supporting documents */}
            <div>
              <div className="text-xs font-semibold text-ink-muted uppercase mb-2">Supporting Correspondence</div>
              <input type="file" multiple onChange={e => setFiles(Array.from(e.target.files ?? []))} className="text-sm" />
              {files.length > 0 && <div className="text-xs text-ink-faint mt-1">{files.length} file(s) selected</div>}
            </div>

            <div className="flex justify-center gap-2 pt-2 border-t border-line bg-surface-2 -mx-5 -mb-5 px-5 py-3 mt-3">
              <button type="button" onClick={handleSubmit} disabled={saving}
                className="px-4 py-1.5 text-sm font-medium rounded-md bg-primary text-white hover:bg-primary disabled:opacity-50">
                {saving ? 'Saving…' : (editingId ? 'Update' : 'Submit')}
              </button>
              <button type="button" onClick={handleCancel} disabled={saving}
                className="px-4 py-1.5 text-sm font-medium rounded-md border border-line bg-surface hover:bg-surface-2 text-ink-muted">
                Cancel
              </button>
            </div>
          </div>
        )}

        {(!rows || rows.length === 0) ? (
          <Empty text="No complaints recorded." />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-surface-2 text-left text-xs text-ink-faint uppercase">
                <tr>
                  <th className="px-3 py-3">Reference</th>
                  <th className="px-3 py-3">Complainant</th>
                  <th className="px-3 py-3">Nature</th>
                  <th className="px-3 py-3">Status</th>
                  <th className="px-3 py-3">Handler</th>
                  <th className="px-3 py-3">Date Filed</th>
                  <th className="px-3 py-3">Logged By</th>
                  {editable && <th className="px-3 py-3 text-right">Actions</th>}
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {rows.map(cp => (
                  <tr key={cp.id} className="hover:bg-surface-2 align-top">
                    <td className="px-3 py-3">{cp.reference_number || cp.claim_number || '-'}</td>
                    <td className="px-3 py-3">{cp.complainant_name || '-'}</td>
                    <td className="px-3 py-3">{cp.nature || cp.complaint_of || cp.complaintOf || '-'}</td>
                    <td className="px-3 py-3">{cp.status || '-'}</td>
                    <td className="px-3 py-3">{cp.handler_name || '-'}</td>
                    <td className="px-3 py-3">{cp.date_filed ? fmtDate(cp.date_filed) : fmtDate(cp.createdAt)}</td>
                    <td className="px-3 py-3">{cp.added_by_name || cp.addedBy || '-'}</td>
                    {editable && (
                      <td className="px-3 py-3 text-right">
                        <button type="button" onClick={() => openEdit(cp)}
                          className="text-xs px-2 py-0.5 rounded border border-line hover:bg-surface-2">Edit</button>
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
    </div>
  )
}

// Stable per-author accent colours for the correspondence log, so each
// participant's notes are instantly scannable by colour + initials. The name
// is hashed to a fixed palette entry (same author → same colour every time).
const CORRESPONDENCE_COLORS = [
  { bar: 'bg-primary',    avatar: 'bg-status-info-bg text-primary' },
  { bar: 'bg-status-success-fg', avatar: 'bg-status-success-bg text-status-success-fg' },
  { bar: 'bg-primary',  avatar: 'bg-status-info-bg text-primary' },
  { bar: 'bg-status-warning-fg',   avatar: 'bg-status-warning-bg text-status-warning-fg' },
  { bar: 'bg-status-danger-fg',    avatar: 'bg-status-danger-bg text-status-danger-fg' },
  { bar: 'bg-status-info-fg',    avatar: 'bg-status-info-bg text-status-info-fg' },
  { bar: 'bg-primary',  avatar: 'bg-status-info-bg text-primary' },
  { bar: 'bg-status-success-fg',    avatar: 'bg-status-success-bg text-status-success-fg' },
]
function correspondenceColor(name: string) {
  let h = 0
  for (let i = 0; i < name.length; i++) h = (h * 31 + name.charCodeAt(i)) >>> 0
  return CORRESPONDENCE_COLORS[h % CORRESPONDENCE_COLORS.length]
}
function authorInitials(name: string) {
  const parts = name.trim().split(/\s+/).filter(Boolean)
  return ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase() || '?'
}

// Review Notes — handler-authored notes on the claim review. Tagged recipients
// are notified in-app + by email (with a note preview + "View Review" link).
// Mirrors TabComplaints; adds a recipient picker (active internal users) and
// file attachments.
// In-progress Claim Review draft, kept in a module-level cache so it survives
// tab switches — the tab component unmounts whenever another tab is active
// (see the `activeTab === 'reviewNotes'` conditional render), which otherwise
// wipes everything the user typed. Keyed by claimId and cleared on a
// successful submit. Attached File objects live here too (they can't be
// serialised to storage); the cache lives as long as the SPA page is open.
type ReviewDraft = {
  showForm: boolean
  note: string
  recipientIds: number[]
  recipientEmails: string[]
  userSearch: string
  emailInput: string
  files: File[]
}
const reviewDraftCache = new Map<number, ReviewDraft>()

function TabReviewNotes({ claimId, editable }: { claimId: number; editable: boolean }) {
  const { data: notes, isLoading, refetch } = useClaimReviewNotes(claimId)
  const { data: createData } = useClaimCreateData()
  const createNote = useCreateClaimReviewNote(claimId)
  const internalUsers = createData?.internal_users ?? []

  // "Mentions bell" — current-claim scope. Graphite has no global
  // unread-mentions endpoint (the Claims Tracker's /api/me/mentions/unread was
  // never ported), so this counts resolved @mentions of the signed-in user on
  // THIS claim. Cross-claim unread mentions still reach the user via the app
  // header notification bell (the backend dispatches a `claim_mention`
  // in-app notification on capture). See report / gap note.
  const myUserId = getStoredUser()?.id ?? null
  const myMentions = countMyMentions(notes, myUserId)

  // Seed initial state from any cached draft so a half-written note survives
  // navigating away to another tab (e.g. Attachments) and back.
  const draft = reviewDraftCache.get(claimId)
  const [showForm, setShowForm] = useState(draft?.showForm ?? false)
  const [note, setNote] = useState(draft?.note ?? '')
  const [recipientIds, setRecipientIds] = useState<number[]>(draft?.recipientIds ?? [])
  const [recipientEmails, setRecipientEmails] = useState<string[]>(draft?.recipientEmails ?? [])
  const [userSearch, setUserSearch] = useState(draft?.userSearch ?? '')
  const [emailInput, setEmailInput] = useState(draft?.emailInput ?? '')
  const [files, setFiles] = useState<File[]>(draft?.files ?? [])
  const [error, setError] = useState<string | null>(null)

  // Note priority (claims_comment_status feature). The option list loads from
  // GET /claims/{id}/comment-status (additive — never blocks the tab). Priority
  // tags the note so the backend reminder tick chases unread @mentions.
  //
  // Original design: the same GET also fed a per-claim "Comment status" panel
  // above the review notes (status + "Awaiting — what?" sub-reason, saved via
  // PUT /claims/{id}/comment-status as a claim-edit action; only the reminder
  // sends are flag-gated). That panel is HIDDEN on this tab at Claims' request
  // (Sep 2026). Its state + JSX are commented out (not deleted) so it can be
  // restored verbatim: uncomment the four cs* state lines below, the two setter
  // calls in the .then, the setClaimCommentStatus import, and the
  // <Card title="Comment status"> block in the JSX. The GET still runs because
  // the Priority selector reads its options from csOptions.priorities.
  const [priority, setPriority] = useState<string>('')
  const [csOptions, setCsOptions] = useState<ClaimCommentStatusOptions | null>(null)
  // const [csStatus, setCsStatus] = useState<string>('')
  // const [csSubReason, setCsSubReason] = useState<string>('')
  // const [csSaving, setCsSaving] = useState(false)
  // const [csMsg, setCsMsg] = useState<string | null>(null)
  useEffect(() => {
    let alive = true
    getClaimCommentStatus(claimId)
      .then(r => { if (!alive) return; setCsOptions(r.options) /* ; setCsStatus(r.data.commentStatus ?? ''); setCsSubReason(r.data.commentSubReason ?? '') */ })
      .catch(() => { /* feature dormant / endpoint absent — degrade silently */ })
    return () => { alive = false }
  }, [claimId])

  // Persist the draft on every change so it's current when the tab unmounts.
  useEffect(() => {
    reviewDraftCache.set(claimId, { showForm, note, recipientIds, recipientEmails, userSearch, emailInput, files })
  }, [claimId, showForm, note, recipientIds, recipientEmails, userSearch, emailInput, files])

  const resetForm = () => {
    setNote(''); setRecipientIds([]); setRecipientEmails([]); setUserSearch(''); setEmailInput(''); setFiles([]); setError(null); setPriority('')
    reviewDraftCache.delete(claimId)
  }
  const handleCancel = () => { resetForm(); setShowForm(false) }

  const toggleRecipient = (id: number) =>
    setRecipientIds(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id])

  // Filter the system users by name as the handler types.
  const filteredUsers = internalUsers.filter(u =>
    u.name.toLowerCase().includes(userSearch.trim().toLowerCase()))

  // Manually-entered / external email recipients (in addition to picked users).
  const addEmail = () => {
    const e = emailInput.trim().toLowerCase()
    if (!e) return
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e)) { setError('Enter a valid email address.'); return }
    if (!recipientEmails.includes(e)) setRecipientEmails(prev => [...prev, e])
    setEmailInput(''); setError(null)
  }
  const removeEmail = (e: string) => setRecipientEmails(prev => prev.filter(x => x !== e))

  const handleSubmit = async () => {
    setError(null)
    if (!note.trim()) { setError('Please enter the review note.'); return }
    try {
      await createNote.mutateAsync({
        note: note.trim(),
        recipientIds,
        recipientEmails,
        files,
        priority: priority || undefined,
      })
      resetForm()
      setShowForm(false)
      refetch()
    } catch (e: any) {
      const resp = e?.response
      const d = resp?.data ?? {}
      const detail = d.errors ? Object.values(d.errors).flat().join(' · ') : ''
      // Surface the real HTTP status when the server returns no JSON body.
      // A bare 413 (attachment over the upload limit), 405 (route not
      // reaching Laravel — e.g. stale route cache / proxy) or a network
      // error otherwise collapses to an unhelpful generic message, which
      // hid the actual cause of failed .msg/PDF uploads.
      let base: string | undefined = d.message
      if (!base) {
        if (resp?.status === 413) {
          base = 'Upload rejected — the attachment(s) exceed the server upload limit.'
        } else if (resp?.status === 405) {
          base = 'Server rejected the request (HTTP 405). The review-note endpoint may not be reachable — clear the API route cache on the server.'
        } else if (resp?.status) {
          base = `Failed to log review note (HTTP ${resp.status}).`
        } else {
          base = 'Failed to log review note — no response from the server (possible network issue or upload-size limit).'
        }
      }
      setError([base, detail].filter(Boolean).join(' — '))
    }
  }

  const saving = createNote.isPending

  return (
    <div className="space-y-4">
      {/* Comment status panel — HIDDEN at Claims' request (Sep 2026). Kept as
          code, not deleted, so it can be restored by uncommenting this block
          plus the cs* state lines, the two setter calls in the .then, and the
          setClaimCommentStatus import above.
      {editable && csOptions && (
        <Card title="Comment status">
          <div className="flex flex-wrap items-end gap-3">
            <div>
              <label className="block text-xs text-ink-muted mb-1">Status</label>
              <select value={csStatus}
                onChange={e => { setCsStatus(e.target.value); if (e.target.value !== csOptions.subReasonStatus) setCsSubReason('') }}
                className="px-3 py-2 border border-line rounded-md text-sm bg-surface">
                <option value="">— none —</option>
                {csOptions.statuses.map(s => <option key={s} value={s}>{s}</option>)}
              </select>
            </div>
            {csStatus === csOptions.subReasonStatus && (
              <div>
                <label className="block text-xs text-ink-muted mb-1">Awaiting — what?</label>
                <select value={csSubReason} onChange={e => setCsSubReason(e.target.value)}
                  className="px-3 py-2 border border-line rounded-md text-sm bg-surface">
                  <option value="">— select —</option>
                  {csOptions.awaitingSubReasons.map(r => <option key={r} value={r}>{r}</option>)}
                </select>
              </div>
            )}
            <button type="button" disabled={csSaving}
              onClick={async () => {
                setCsSaving(true); setCsMsg(null)
                try {
                  await setClaimCommentStatus(claimId, { comment_status: csStatus || null, comment_sub_reason: csSubReason || null })
                  setCsMsg('Saved.')
                } catch { setCsMsg('Could not save comment status.') }
                finally { setCsSaving(false) }
              }}
              className="px-4 h-[38px] rounded-md bg-primary text-white text-sm disabled:opacity-50">
              {csSaving ? 'Saving…' : 'Save'}
            </button>
            {csMsg && <span className="text-xs text-ink-muted">{csMsg}</span>}
          </div>
        </Card>
      )}
      */}
      <Card
        title="Claim Review"
        action={
          <div className="flex items-center gap-2">
            {/* Mentions indicator — current-claim scope (see note above). */}
            {myMentions > 0 && (
              <span
                className="inline-flex items-center gap-1 rounded-full bg-status-info-bg px-2 py-0.5 text-xs font-medium text-primary"
                title={`You are mentioned in ${myMentions} review note(s) on this claim`}
              >
                <span aria-hidden>@</span>{myMentions} mention{myMentions === 1 ? '' : 's'}
              </span>
            )}
            {editable && !showForm && (
              <button type="button" onClick={() => setShowForm(true)}
                className="px-3 py-1.5 text-xs font-medium rounded-md bg-status-success-fg text-white hover:bg-status-success-fg">
                Add Review Note
              </button>
            )}
          </div>
        }
      >
        {showForm && (
          <div className="space-y-3 mb-5">
            {error && <div className="p-3 bg-status-danger-bg border border-status-danger-fg rounded-md text-status-danger-fg text-sm">{error}</div>}

            <div className="grid grid-cols-1 md:grid-cols-4 gap-3 items-start">
              <label className="md:col-span-1 text-sm text-ink-muted mt-2">Note</label>
              <div className="md:col-span-3">
                {/* @mention autocomplete resolves against the same internal-user
                    list as the Notify picker (no extra endpoint). Typing "@"
                    suggests staff; picking inserts an @handle the backend
                    ClaimMentionParser resolves. Harmless when claims_mentions
                    is off — the handle is just plain text the server ignores. */}
                <MentionComposer
                  value={note}
                  onChange={setNote}
                  candidates={internalUsers.map(u => ({ id: Number(u.id), name: u.name, email: u.email }))}
                  placeholder="Enter the review note. Type @ to mention a colleague."
                  rows={7}
                  className="w-full px-3 py-2.5 border border-line rounded-md text-sm leading-relaxed resize-y min-h-[9rem]"
                />
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-4 gap-3 items-start">
              <label className="md:col-span-1 text-sm text-ink-muted mt-2">Priority</label>
              <div className="md:col-span-3">
                <select value={priority} onChange={e => setPriority(e.target.value)}
                  className="px-3 py-2 border border-line rounded-md text-sm bg-surface">
                  <option value="">— none —</option>
                  {(csOptions?.priorities ?? []).map(p => (
                    <option key={p.value} value={p.value}>{p.label}</option>
                  ))}
                </select>
                <p className="text-xs text-ink-faint mt-1">How soon an unread @mention on this note is chased. Reminder emails only send when the comment-status feature is enabled.</p>
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-4 gap-3 items-start">
              <label className="md:col-span-1 text-sm text-ink-muted mt-2">Notify</label>
              <div className="md:col-span-3 space-y-2">
                <input value={userSearch} onChange={e => setUserSearch(e.target.value)}
                  placeholder="Search users by name…"
                  className="w-full px-3 py-2 border border-line rounded-md text-sm" />
                <div className="max-h-44 overflow-y-auto border border-line rounded-md divide-y divide-line">
                  {filteredUsers.length === 0 ? (
                    <div className="px-3 py-2 text-sm text-ink-faint">No matching users.</div>
                  ) : filteredUsers.map(u => (
                    <label key={u.id} className="flex items-center gap-2 px-3 py-2 text-sm hover:bg-surface-2 cursor-pointer">
                      <input type="checkbox" checked={recipientIds.includes(Number(u.id))}
                        onChange={() => toggleRecipient(Number(u.id))} />
                      <span>{u.name}</span>
                    </label>
                  ))}
                </div>

                {/* Manual / external email recipients — added alongside picked users. */}
                <div className="flex gap-2">
                  <input value={emailInput} onChange={e => setEmailInput(e.target.value)}
                    onKeyDown={e => { if (e.key === 'Enter') { e.preventDefault(); addEmail() } }}
                    type="email" placeholder="Add email address…"
                    className="flex-1 px-3 py-2 border border-line rounded-md text-sm" />
                  <button type="button" onClick={addEmail}
                    className="px-3 py-2 text-sm font-medium rounded-md border border-line bg-surface hover:bg-surface-2 text-ink-muted">
                    Add
                  </button>
                </div>
                {recipientEmails.length > 0 && (
                  <div className="flex flex-wrap gap-2">
                    {recipientEmails.map(e => (
                      <span key={e} className="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-status-info-bg text-primary text-xs border border-primary">
                        {e}
                        <button type="button" onClick={() => removeEmail(e)} className="text-primary hover:text-primary leading-none">×</button>
                      </span>
                    ))}
                  </div>
                )}
                <p className="text-xs text-ink-faint">Selected users and added emails receive a preview + a link to this claim review.</p>
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-4 gap-3 items-start">
              <label className="md:col-span-1 text-sm text-ink-muted mt-2">Attachments</label>
              <div className="md:col-span-3">
                <input type="file" multiple
                  onChange={e => setFiles(Array.from(e.target.files ?? []))}
                  className="block w-full text-sm text-ink-muted file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-sm file:bg-surface-2 file:text-ink-muted hover:file:bg-surface-2" />
                {files.length > 0 && (
                  <p className="text-xs text-ink-faint mt-1">{files.length} file(s) selected</p>
                )}
              </div>
            </div>

            <div className="flex justify-center gap-2 pt-2 border-t border-line bg-surface-2 -mx-5 -mb-5 px-5 py-3 mt-3">
              <button type="button" onClick={handleSubmit} disabled={saving}
                className="px-4 py-1.5 text-sm font-medium rounded-md bg-primary text-white hover:bg-primary disabled:opacity-50">
                {saving ? 'Saving…' : 'Submit'}
              </button>
              <button type="button" onClick={handleCancel} disabled={saving}
                className="px-4 py-1.5 text-sm font-medium rounded-md border border-line bg-surface hover:bg-surface-2 text-ink-muted">
                Cancel
              </button>
            </div>
          </div>
        )}

        {isLoading ? (
          <Empty text="Loading review notes…" />
        ) : (!notes || notes.length === 0) ? (
          <Empty text="No review notes logged." />
        ) : (
          <div className="space-y-3">
            {notes.map(n => {
              const author = n.createdBy || 'Unknown'
              const c = correspondenceColor(author)
              return (
                <div key={n.id} className="flex overflow-hidden rounded-lg border border-line bg-surface shadow-sm transition-shadow hover:shadow-md">
                  {/* Colour accent bar — stable per author */}
                  <div className={`w-1.5 shrink-0 ${c.bar}`} />
                  <div className="flex-1 p-4">
                    {/* Header: author + date */}
                    <div className="mb-2.5 flex items-center justify-between gap-3">
                      <div className="flex items-center gap-2">
                        <span className={`inline-flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold ${c.avatar}`}>
                          {authorInitials(author)}
                        </span>
                        <span className="text-sm font-semibold text-ink">{author}</span>
                      </div>
                      <span className="whitespace-nowrap text-xs text-ink-faint">{fmtDateTime(n.createdAt)}</span>
                    </div>

                    {/* Note body — spacious, readable. MentionText highlights
                        any @handles the backend parsed into n.mentions (resolved
                        → badge, unresolved → plain highlight); with no mentions
                        it renders the body verbatim. */}
                    <div className="whitespace-pre-wrap rounded-md bg-surface-2 px-3.5 py-3 text-sm leading-relaxed text-ink-muted">
                      <MentionText note={n.note} mentions={n.mentions} />
                    </div>

                    {/* Footer: recipients + attachments */}
                    {(n.recipients.length > 0 || n.files.length > 0) && (
                      <div className="mt-3 flex flex-col gap-2 border-t border-line pt-3 sm:flex-row sm:items-start sm:justify-between">
                        {n.recipients.length > 0 && (
                          <div className="flex flex-wrap items-center gap-1.5">
                            <span className="text-xs font-medium text-ink-faint">To:</span>
                            {n.recipients.map((r, i) => (
                              <span key={i} className="inline-flex items-center gap-1 rounded-full bg-surface-2 px-2 py-0.5 text-xs text-ink-muted">
                                {r.name}
                                {r.notifiedAt
                                  ? <span className="text-status-success-fg" title={`Emailed ${fmtDate(r.notifiedAt)}`}>✓</span>
                                  : <span className="text-ink-faint" title="Email not sent">·</span>}
                              </span>
                            ))}
                          </div>
                        )}
                        {n.files.length > 0 && (
                          <div className="flex flex-wrap items-center gap-1.5">
                            {n.files.map((f, i) => (
                              f.url
                                ? <a key={i} href={f.url} target="_blank" rel="noreferrer"
                                    className="inline-flex items-center gap-1 rounded-full bg-status-info-bg px-2 py-0.5 text-xs text-primary hover:bg-status-info-bg">
                                    <span aria-hidden>📎</span>{f.name}
                                  </a>
                                : <span key={i} className="inline-flex items-center gap-1 rounded-full bg-surface-2 px-2 py-0.5 text-xs text-ink-muted">
                                    <span aria-hidden>📎</span>{f.name}
                                  </span>
                            ))}
                          </div>
                        )}
                      </div>
                    )}
                  </div>
                </div>
              )
            })}
          </div>
        )}
      </Card>
    </div>
  )
}

// ── Main Page Component ──────────────────────────────────────

export default function ClaimDetailPage() {
  const { id } = useParams<{ id: string }>()
  const { toast } = useToast()
  const navigate = useNavigate()
  // Deep-link support: notification emails / in-app links land on
  // /claims/:id?tab=reviewNotes and should open that tab directly.
  const [searchParams] = useSearchParams()
  const { data: claim, isLoading, error, refetch } = useClaim(Number(id))
  const updateStatus = useUpdateClaimStatus()

  // Claims SLA tab — only for recorder roles (Claims Team / Claims Manager)
  // while the `claims_sla` flag is ON. Editing additionally needs claim-edit.
  const slaCanSee = useCanSeeClaimSlaPanel()
  const slaCanEdit = canEditClaimSlaTimeline()

  // Claims decision workflow (approve / repudiate / reverse) — a flagged,
  // ADDITIVE layer separate from the status workflow above. Admin/Super Admin
  // always preview it; Claims Team/Manager see it only when the flag is ON.
  const decisionCanSee = useCanSeeClaimDecisionPanel()

  // PO-in-Graphite Phase 2 — the Omni purchase orders raised for this claim.
  // Flagged (`omni_po`) + role-gated; rendered LIVE from Omni, never stored.
  const poCanSee = useCanSeeClaimPurchaseOrders()

  // UAT 2026-05-26 (Arjun): some claim records take a long time to
  // load — flip a flag after 15s so the spinner gets a "taking longer
  // than expected" hint instead of looking frozen forever.
  const [loadingSlow, setLoadingSlow] = useState(false)
  useEffect(() => {
    if (!isLoading) { setLoadingSlow(false); return }
    const t = window.setTimeout(() => setLoadingSlow(true), 15000)
    return () => window.clearTimeout(t)
  }, [isLoading])

  // Append the SLA + Decision tabs when the user may see each (flag + role).
  const visibleTabs: { key: TabKey; label: string }[] = [
    ...TABS,
    ...(slaCanSee ? [{ key: 'sla' as TabKey, label: 'SLA Timeline' }] : []),
    ...(decisionCanSee ? [{ key: 'decision' as TabKey, label: 'Decision' }] : []),
    ...(poCanSee ? [{ key: 'purchaseOrders' as TabKey, label: 'Purchase Orders' }] : []),
  ]

  const initialTab = searchParams.get('tab') as TabKey | null
  const [activeTab, setActiveTab] = useState<TabKey>(
    initialTab && visibleTabs.some(t => t.key === initialTab) ? initialTab : 'details'
  )
  const [showStatusModal, setShowStatusModal] = useState(false)
  const [newStatus, setNewStatus] = useState('')
  const [closedNote, setClosedNote] = useState('')
  const [subStatus, setSubStatus] = useState('')
  const [statusError, setStatusError] = useState('')

  // Edit claim state
  const [showEditModal, setShowEditModal] = useState(false)
  const [editForm, setEditForm] = useState<Record<string, string>>({})
  const [editSaving, setEditSaving] = useState(false)
  const [editHistory, setEditHistory] = useState<any[]>([])
  const [showEditHistory, setShowEditHistory] = useState(false)
  // Term-scoped plates for the Edit Claim modal's plate fields — same feed
  // as the detail banner (GET /policies/{id}/vehicles?action_id=), scoped to
  // the term the claim was filed against. Empty ⇒ fields stay free-text.
  const [modalPlateOpts, setModalPlateOpts] = useState<Array<{ id: string; name: string }>>([])
  useEffect(() => {
    const policyId = claim?.policy?.id
    if (!policyId || !showEditModal) { return }
    fetchPolicyVehicles(policyId, claim?.policy_action?.id ?? undefined)
      .then(res => setModalPlateOpts(
        (res.data ?? [])
          .filter(v => v?.vehiclePlate)
          .map(v => ({
            id: String(v.vehiclePlate),
            name: [v.vehiclePlate, [v.make, v.model].filter(Boolean).join(' ')].filter(Boolean).join(' — '),
          }))
      ))
      .catch(() => setModalPlateOpts([]))
  }, [claim?.policy?.id, claim?.policy_action?.id, showEditModal])

  async function handleStatusUpdate() {
    if (!newStatus) return
    // Moving into 'Open' requires picking a working sub-status.
    if (newStatus === 'Open' && !subStatus) {
      setStatusError('Please select a sub-status for the Open claim.')
      return
    }
    setStatusError('')
    try {
      await updateStatus.mutateAsync({
        id: Number(id),
        status: newStatus,
        closedNote: closedNote || undefined,
        subStatus: newStatus === 'Open' ? subStatus : undefined,
      })
      setShowStatusModal(false)
      setNewStatus('')
      setClosedNote('')
      setSubStatus('')
      refetch()
    } catch (err: any) {
      setStatusError(err?.response?.data?.message || 'Failed to update status')
    }
  }

  const [editLookups, setEditLookups] = useState<any>(null)
  // Nested state for motor-accident sub-tables. Flat editForm can't hold
  // array-of-objects shapes cleanly, so passenger / third-party repeaters
  // live in their own state and are serialised into the PUT payload
  // alongside editForm at save time.
  const [editAccident, setEditAccident] = useState<Record<string, string>>({})
  const [editDriver, setEditDriver]     = useState<Record<string, string>>({})
  const [editPassengers, setEditPassengers] = useState<Array<Record<string, string>>>([])
  const [editThirdParties, setEditThirdParties] = useState<Array<Record<string, string>>>([])

  // @ts-expect-error TS6133 — kept for re-enable of commented Edit Claim button (~line 5326). Remove this directive when the button is restored.
  async function openEditModal() {
    if (!claim) return
    const c = claim as any
    const life  = c.lifeDetails ?? {}
    const legal = c.legalDetails ?? {}
    const hc    = c.hospitalCashDetails ?? {}
    const sub   = c.subClaimData ?? {}
    setEditForm({
      claim_type: c.claim_type || '',
      claim_sub_type: c.claim_sub_type || '',
      category: c.category || '',
      registered_claim: c.registered_claim || '',
      incident_date: c.incident_date || c.date_of_loss || '',
      incident_time: c.incident_time || '',
      incident_location: c.incident_location || c.location || '',
      incident_description: c.incident_description || c.description_of_loss || '',
      type_of_loss: c.type_of_loss != null ? String(c.type_of_loss) : '',
      event_name: c.event_name || '',
      is_motor_claim: c.is_motor_claim ? '1' : '0',
      vehicle_plate: c.vehicle_plate || '',
      catastrophe_loss: String(c.catastrophe_loss ?? 0) === '1' ? '1' : '0',
      attorney_involved: String(c.attorney_involved ?? 0) === '1' ? '1' : '0',
      co_attorney_involved: String(c.co_attorney_involved ?? 0) === '1' ? '1' : '0',
      dfs_complaint: String(c.dfs_complaint ?? 0) === '1' ? '1' : '0',
      recovery_involved: String(c.recovery_involved ?? 0) === '1' ? '1' : '0',
      third_party_insured_elsewhere: String(c.third_party_insured_elsewhere ?? 0) === '1' ? '1' : '0',
      driver_as_insured: String(c.driver_as_insured ?? 0) === '1' ? '1' : '0',
      weather_condition: c.weather_condition || '',
      fault_party: c.fault_party || '',
      reason: c.reason || '',
      reported_by: c.reported_by || '',
      reported_date: c.reported_date || '',
      claim_allocated_to: c.claim_allocated_to ? String(c.claim_allocated_to) : '',
      claim_allocated_on: c.claim_allocated_on || c.allocated_on || '',

      // ── Life (claim_life) ──
      date_of_death: life.date_of_death ? String(life.date_of_death).slice(0, 10) : '',
      cause_of_death: life.cause_of_death || '',
      life_description: life.description || '',

      // ── Legal (claim_legal) ──
      legal_firm: legal.legal_firm || '',
      lawyer_name: legal.lawyer_name || '',
      legal_tel: legal.legal_tel || '',
      legal_email: legal.legal_email || '',
      member_name: legal.member_name || '',
      membership_id: legal.membership_id || '',
      member_contact: legal.member_contact || '',
      member_email: legal.member_email || '',
      lossreported_date: legal.lossreported_date ? String(legal.lossreported_date).slice(0, 10) : '',
      matter_relatesto: legal.matter_relatesto || '',
      matter_quantum: legal.matter_quantum || '',
      course_of_action: legal.course_of_action || '',
      jurisdiction: legal.jurisdiction || '',

      // ── Hospital Cash (claim_hospital_cash) ──
      patient_name: hc.patient_name || '',
      patient_dob: hc.patient_dob ? String(hc.patient_dob).slice(0, 10) : '',
      patient_identity_number: hc.patient_identity_number || '',
      relationship: hc.relationship || '',
      occupation_date: hc.occupation_date ? String(hc.occupation_date).slice(0, 10) : '',
      hospital_name: hc.hospital_name || '',
      admitting_doctor: hc.admitting_doctor || '',
      admission_date: hc.admission_date ? String(hc.admission_date).slice(0, 10) : '',
      admission_time: hc.admission_time || '',
      discharge_date: hc.discharge_date ? String(hc.discharge_date).slice(0, 10) : '',
      discharge_time: hc.discharge_time || '',
      hospitalisation_type: hc.hospitalisation_type || '',
      injury_date: hc.injury_date ? String(hc.injury_date).slice(0, 10) : '',
      accident_circumstances: hc.accident_circumstances || '',
      is_medical_scheme: hc.medical_scheme || '',
      medical_scheme_name: hc.medical_scheme_name || '',
      medical_aid_number: hc.medical_aid_number || '',

      // ── DOMG/COMG sub-claim ──
      // Hoist a few common fields from sub_claim_data into the flat
      // editForm so the UI can show/edit them directly. On save, we
      // collect the full sub_claim_data back into its own key.
      // Boolean-like cols come back as DB ints (0/1); `|| ''` would erase a 0
      // because it's falsy. Coerce via String() so "No" round-trips.
      previously_suffered_loss: sub.previously_suffered_loss != null ? String(sub.previously_suffered_loss) : '',
      other_party_interest: sub.other_party_interest || '',
      other_insurance_covering: sub.other_insurance_covering != null ? String(sub.other_insurance_covering) : '',
      address_of_premises: sub.address_of_premises || '',
      description_of_incident: sub.description_of_incident || '',
      date_time_police_advised: sub.date_time_police_advised || '',
      total_value_contents_of_premises: sub.total_value_contents_of_premises || '',
      // Fidelity Guarantee — V8 stores employees_been_involved as a
      // boolean; coerce DB ints via String() so a "No" doesn't get erased.
      employees_been_involved: sub.employees_been_involved != null ? String(sub.employees_been_involved) : '',
      circumstances: sub.circumstances || '',
      defaulting_employees_name: sub.defaulting_employees_name || '',
      // Property Loss / Damage — V8 column names per
      // property_loss_damage.blade.php / migration. All fields are
      // free-text on V8 (16 visible textareas). `previously_suffered_loss`
      // and `other_insurance_covering` are already hydrated above (BI
      // block) — those flows safely share form keys.
      loss_damage_discovered: sub.loss_damage_discovered || '',
      loss_damage_occurred: sub.loss_damage_occurred || '',
      premises_occupied: sub.premises_occupied || '',
      last_occupied: sub.last_occupied || '',
      purpose_of_occupation: sub.purpose_of_occupation || '',
      nature_interruption: sub.nature_interruption || '',
      loss_for_each_item: sub.loss_for_each_item || '',
      give_details: sub.give_details || '',
      name_of_insurer: sub.name_of_insurer || '',
      reference_no_station: sub.reference_no_station || '',
      interest_insured_property: sub.interest_insured_property || '',
      give_name_insurer: sub.give_name_insurer || '',
      value_all_property: sub.value_all_property || '',
      when_last_valued: sub.when_last_valued || '',
      // Public Liability + Liability — V8 column names per
      // public_liability.blade.php / create_public_liability migration.
      // accident_liability/accident_person/accident_contacted are stored
      // as 'YES'/'NO' strings (not int 1/0), so no String() coerce needed.
      // insured_name is shared with the Mobile/Electronic Devices block.
      insured_treding_name: sub.insured_treding_name || '',
      insured_postal_address: sub.insured_postal_address || '',
      insured_email: sub.insured_email || '',
      insured_telephone_no: sub.insured_telephone_no || '',
      insured_facsimile: sub.insured_facsimile || '',
      insured_mobile_no: sub.insured_mobile_no || '',
      accident_date: sub.accident_date || '',
      accident_time: sub.accident_time || '',
      accident_incident: sub.accident_incident || '',
      accident_injuries: sub.accident_injuries || '',
      accident_liability: sub.accident_liability || '',
      accident_person: sub.accident_person || '',
      accident_contacted: sub.accident_contacted || '',
      attach_contractor: sub.attach_contractor || '',
      attach_employee: sub.attach_employee || '',
      attach_employed: sub.attach_employed || '',
      attach_blame: sub.attach_blame || '',
      attach_circumstances: sub.attach_circumstances || '',
      attach_property: sub.attach_property || '',
      attach_details: sub.attach_details || '',
      attach_owner: sub.attach_owner || '',
      attach_damage: sub.attach_damage || '',
      claim_name: sub.claim_name || '',
      claim_telephone: sub.claim_telephone || '',
      claim_mobile: sub.claim_mobile || '',
      claim_postal: sub.claim_postal || '',
      claim_solicitor: sub.claim_solicitor || '',
      witness1_name: sub.witness1_name || '',
      witness1_telephone: sub.witness1_telephone || '',
      witness1_mobile: sub.witness1_mobile || '',
      witness1_postal: sub.witness1_postal || '',
      witness1_relationship: sub.witness1_relationship || '',
      witness2_name: sub.witness2_name || '',
      witness2_telephone: sub.witness2_telephone || '',
      witness2_mobile: sub.witness2_mobile || '',
      witness2_postal: sub.witness2_postal || '',
      witness2_relationship: sub.witness2_relationship || '',
      witness2_damage: sub.witness2_damage || '',
      // Workers Compensation / Stated Benefits — V8 column names
      // (workers_compensation table). injured_name is hydrated alongside
      // motor third-party state but the same form key is reused here.
      injured_name: sub.injured_name || '',
      injured_age: sub.injured_age != null ? String(sub.injured_age) : '',
      injured_address: sub.injured_address || '',
      injured_status: sub.injured_status || '',
      injured_occupation: sub.injured_occupation || '',
      injured_nationality: sub.injured_nationality || '',
      injured_service_period: sub.injured_service_period || '',
      your_direct_employ: sub.your_direct_employ != null ? String(sub.your_direct_employ) : '',
      address_of_contractor: sub.address_of_contractor || '',
      // The Accident
      date: sub.date ? String(sub.date).slice(0, 10) : '',
      time: sub.time || '',
      place: sub.place || '',
      how_accident_occur: sub.how_accident_occur || '',
      first_report_accident: sub.first_report_accident || '',
      period_of_disablement: sub.period_of_disablement || '',
      // Fire (fire_claim) — V8 column names per fire.blade.php. The 5
      // boolean cols (anyone_during_burglary, premises_guarded_by_watchman,
      // premises_properly_secured, suspect_any_person, other_insurance_against_fire)
      // come back as DB ints; coerce via String() so "No" doesn't get
      // erased to ''. `police_station_name`, `brief_description_incident`,
      // `property_last_seen` are already hydrated above (shared form keys).
      address_of_theft_occurred: sub.address_of_theft_occurred || '',
      date_time_of_theft: sub.date_time_of_theft || '',
      anyone_during_burglary: sub.anyone_during_burglary != null ? String(sub.anyone_during_burglary) : '',
      details_during_burglary: sub.details_during_burglary || '',
      days_premises_unoccupied: sub.days_premises_unoccupied || '',
      premises_guarded_by_watchman: sub.premises_guarded_by_watchman != null ? String(sub.premises_guarded_by_watchman) : '',
      name_of_guard: sub.name_of_guard || '',
      telephone_of_guard: sub.telephone_of_guard || '',
      guard_during_fire: sub.guard_during_fire || '',
      name_of_security_agent: sub.name_of_security_agent || '',
      suspect_any_person: sub.suspect_any_person != null ? String(sub.suspect_any_person) : '',
      suspect_person_details: sub.suspect_person_details || '',
      total_value_premises_buildings: sub.total_value_premises_buildings || '',
      other_insurance_against_fire: sub.other_insurance_against_fire != null ? String(sub.other_insurance_against_fire) : '',
      insurance_against_fire_details: sub.insurance_against_fire_details || '',
      estimated_amount_of_damaged: sub.estimated_amount_of_damaged || '',
      details_of_previous_loss: sub.details_of_previous_loss || '',
      // Erection All Risk (erection_all_risk_claims) — V8 column names.
      // Five booleans (responsible_for_damage, possibility_of_recovery,
      // alterations_during_repairs, surrounding_properties_damaged,
      // third_party_liability) come back as DB ints; coerce via String()
      // so "No" doesn't get erased to ''. Date columns sliced to
      // YYYY-MM-DD for <input type="date">. Many keys (cause_of_damage,
      // estimated_cost_*, etc.) are EAR-only; the responsible_for_damage_
      // details / recovery_details / third_party_liability_details
      // textareas are conditionally shown in the edit branch.
      insured_occupation: sub.insured_occupation || '',
      period_from: sub.period_from ? String(sub.period_from).slice(0, 10) : '',
      period_to: sub.period_to ? String(sub.period_to).slice(0, 10) : '',
      supervisor_engineer_name: sub.supervisor_engineer_name || '',
      date_of_occurrence: sub.date_of_occurrence ? String(sub.date_of_occurrence).slice(0, 10) : '',
      time_of_occurrence: sub.time_of_occurrence || '',
      site_of_damage: sub.site_of_damage || '',
      nearest_railway_station: sub.nearest_railway_station || '',
      damage_contract_works: sub.damage_contract_works || '',
      damage_plant_equipment: sub.damage_plant_equipment || '',
      damage_third_party_property: sub.damage_third_party_property || '',
      cause_of_damage: sub.cause_of_damage || '',
      responsible_for_damage: sub.responsible_for_damage != null ? String(sub.responsible_for_damage) : '',
      responsible_for_damage_details: sub.responsible_for_damage_details || '',
      possibility_of_recovery: sub.possibility_of_recovery != null ? String(sub.possibility_of_recovery) : '',
      recovery_details: sub.recovery_details || '',
      how_damage_occurred: sub.how_damage_occurred || '',
      probable_cause: sub.probable_cause || '',
      progress_of_construction: sub.progress_of_construction || '',
      how_items_repaired: sub.how_items_repaired || '',
      alterations_during_repairs: sub.alterations_during_repairs != null ? String(sub.alterations_during_repairs) : '',
      witness_name: sub.witness_name || '',
      witness_address: sub.witness_address || '',
      surrounding_properties_damaged: sub.surrounding_properties_damaged != null ? String(sub.surrounding_properties_damaged) : '',
      third_party_liability: sub.third_party_liability != null ? String(sub.third_party_liability) : '',
      third_party_liability_details: sub.third_party_liability_details || '',
      estimated_cost_contract_works: sub.estimated_cost_contract_works || '',
      estimated_cost_plant_machinery: sub.estimated_cost_plant_machinery || '',
      estimated_cost_third_party_property: sub.estimated_cost_third_party_property || '',
      estimated_cost_owners_surrounding: sub.estimated_cost_owners_surrounding || '',
      other_insurance_details: sub.other_insurance_details || '',
      previous_losses_details: sub.previous_losses_details || '',
      // Contractors All Risks / Public Liability
      // (contractors_all_risks_public_liability) — V8 column names.
      // Boolean responsible_*_claim cols come as DB ints (0/1); coerce
      // via String() so "No" doesn't get erased. Date fields are sliced
      // to YYYY-MM-DD so the <input type="date"> control accepts them.
      // The two file path fields are NOT hydrated here — they round-trip
      // via the *_url helpers attached by show() and the FE renders a
      // clickable link in the View read mode.
      responsible_person_name: sub.responsible_person_name || '',
      responsible_person_phone: sub.responsible_person_phone || '',
      responsible_person_cellphone: sub.responsible_person_cellphone || '',
      responsible_person_email: sub.responsible_person_email || '',
      responsible_person_fax: sub.responsible_person_fax || '',
      parties_to_contract: sub.parties_to_contract || '',
      contract_value: sub.contract_value || '',
      contract_number: sub.contract_number || '',
      description_of_contract: sub.description_of_contract || '',
      site_physical_address: sub.site_physical_address || '',
      code: sub.code || '',
      contract_commencement_date: sub.contract_commencement_date ? String(sub.contract_commencement_date).slice(0, 10) : '',
      expected_contract_completion_date: sub.expected_contract_completion_date ? String(sub.expected_contract_completion_date).slice(0, 10) : '',
      responsible_contract_works_claim: sub.responsible_contract_works_claim != null ? String(sub.responsible_contract_works_claim) : '',
      responsible_public_liability_claim: sub.responsible_public_liability_claim != null ? String(sub.responsible_public_liability_claim) : '',
      loss_date: sub.loss_date ? String(sub.loss_date).slice(0, 10) : '',
      loss_time: sub.loss_time || '',
      loss_details: sub.loss_details || '',
      cause_of_loss: sub.cause_of_loss || '',
      party_responsible_name: sub.party_responsible_name || '',
      party_responsible_contact: sub.party_responsible_contact || '',
      estimated_cost_of_repair_replacement: sub.estimated_cost_of_repair_replacement || '',
      police_station: sub.police_station || '',
      police_reference: sub.police_reference || '',
      // Mobile/Electronic Devices + Office Contents — V8 column names
      // (mobile_and_electronic_devices_claim). property_stolen_damaged
      // and is_sole_owner_of_property are already hydrated by the All
      // Risks block above — they're shared columns with the same form
      // key (only one claim type filled per submission). `address` is
      // shared with the Defective Workmanship block above.
      insured_name: sub.insured_name || '',
      email_address: sub.email_address || '',
      telephone_no: sub.telephone_no || '',
      date_time_loss_discovered: sub.date_time_loss_discovered || '',
      whom_discovered: sub.whom_discovered || '',
      // Defective Workmanship — V8 column names (defective_workmanship).
      // Boolean-like cols come back as DB ints (0/1); coerce via String()
      // so a "No" doesn't get erased to ''.
      location_of_accident: sub.location_of_accident || '',
      accident_date_time: sub.accident_date_time || '',
      owners_name: sub.owners_name || '',
      telephone_number: sub.telephone_number || '',
      mobile_number: sub.mobile_number || '',
      address: sub.address || '',
      make: sub.make || '',
      model: sub.model || '',
      registration: sub.registration || '',
      vehicle_drivable: sub.vehicle_drivable != null ? String(sub.vehicle_drivable) : '',
      vehicle_handed_claimant: sub.vehicle_handed_claimant != null ? String(sub.vehicle_handed_claimant) : '',
      when_vehicle_handed: sub.when_vehicle_handed || '',
      allegations_received: sub.allegations_received || '',
      // Goods In Transit — V8 column names (goods_in_transit_claim).
      address_of_premises_loss: sub.address_of_premises_loss || '',
      details_of_driver: sub.details_of_driver || '',
      property_last_seen: sub.property_last_seen || '',
      date_time_of_loss: sub.date_time_of_loss || '',
      brief_description_incident: sub.brief_description_incident || '',
      // shared key between Burglary/Theft and GIT — already present above
      police_station_name: sub.police_station_name || '',
      witnesses_name: sub.witnesses_name || '',
      witnesses_mobile_number: sub.witnesses_mobile_number || '',
      total_value_of_loss: sub.total_value_of_loss || '',
      consignment_transported_to: sub.consignment_transported_to || '',
      consignment_from: sub.consignment_from || '',
      vehicle_registration_number: sub.vehicle_registration_number || '',
      is_carrier_contracted: sub.is_carrier_contracted != null ? String(sub.is_carrier_contracted) : '',
      carrier_has_own_GIT_ins: sub.carrier_has_own_GIT_ins != null ? String(sub.carrier_has_own_GIT_ins) : '',
      other_insurance_against_theft: sub.other_insurance_against_theft != null ? String(sub.other_insurance_against_theft) : '',
      insurance_against_theft_details: sub.insurance_against_theft_details || '',
      details_of_previous_loss_records: sub.details_of_previous_loss_records || '',
      copy_of_contract: sub.copy_of_contract || '',
      // All Risk / Electronic Equipment / Personal All Risks
      // (all_risk_and_electronic_equipment) — V8 column names. Boolean-like
      // ints stringified for the select; free-text fields fall through with
      // `|| ''`.
      property_stolen_damaged: sub.property_stolen_damaged != null ? String(sub.property_stolen_damaged) : '',
      thorough_search_made_for_article: sub.thorough_search_made_for_article != null ? String(sub.thorough_search_made_for_article) : '',
      loss_cause: sub.loss_cause != null ? String(sub.loss_cause) : '',
      loss_by_other_cause: sub.loss_by_other_cause || '',
      stolenfromcar_unlockedpremises: sub.stolenfromcar_unlockedpremises != null ? String(sub.stolenfromcar_unlockedpremises) : '',
      sole_owner_of_property: sub.sole_owner_of_property || '',
      is_sole_owner_of_property: sub.is_sole_owner_of_property != null ? String(sub.is_sole_owner_of_property) : '',
      // Travel Insurance (travel_insurance_claim) — V8 column names. Date
      // columns sliced to YYYY-MM-DD so <input type="date"> accepts them.
      // The 25 file path fields are NOT hydrated here; they round-trip via
      // the *_url helpers attached by show() and render as clickable links
      // in the View / Edit forms (re-uploading replaces the path). Several
      // keys (insured_name, email, mobile, telephone, address,
      // phone_number, policy_number) are shared with other claim types'
      // hydration above — only one claim type is filled per submission so
      // shadowing here is safe.
      title: sub.title || '',
      other_title: sub.other_title || '',
      surname: sub.surname || '',
      forename: sub.forename || '',
      dob: sub.dob ? String(sub.dob).slice(0, 10) : '',
      passport_no: sub.passport_no || '',
      nationality: sub.nationality || '',
      telephone: sub.telephone || '',
      post_code: sub.post_code || '',
      mobile: sub.mobile || '',
      email: sub.email || '',
      home_address: sub.home_address || '',
      policy_number: sub.policy_number || '',
      issued_by: sub.issued_by || '',
      issued_on: sub.issued_on || '',
      valid_from: sub.valid_from ? String(sub.valid_from).slice(0, 10) : '',
      valid_to: sub.valid_to ? String(sub.valid_to).slice(0, 10) : '',
      beneficiary: sub.beneficiary || '',
      bank_name: sub.bank_name || '',
      bank_address: sub.bank_address || '',
      account_number: sub.account_number || '',
      iban: sub.iban || '',
      swift_code: sub.swift_code || '',
      bic_code: sub.bic_code || '',
      other_insurance_policy: sub.other_insurance_policy || '',
      name_insurance_company: sub.name_insurance_company || '',
      phone_number: sub.phone_number || '',
      type_of_refund: sub.type_of_refund || '',
      type_of_refund_other: sub.type_of_refund_other || '',
      // Professional Indemnity (professional_indemnity_claims) — V8 column
      // names. Boolean-like ints (contract_in_place, notification_purposes_
      // only, verbal_written_demand, served_with_summons, attorney_appointed,
      // own_investigation) coerced via String() so a "No" doesn't get
      // erased. Date columns sliced to YYYY-MM-DD. `insured_email` is
      // shared with Public Liability hydration above — fine because only
      // one claim type per submission. The two file path fields are NOT
      // hydrated; they round-trip via the *_url helpers attached by show().
      type_of_business: sub.type_of_business || '',
      contact_person: sub.contact_person || '',
      designation: sub.designation || '',
      insured_cell_tel: sub.insured_cell_tel || '',
      claimant_type: sub.claimant_type || '',
      claimant_name_surname: sub.claimant_name_surname || '',
      claimant_email: sub.claimant_email || '',
      claimant_cell_tel: sub.claimant_cell_tel || '',
      insured_retained_to_do: sub.insured_retained_to_do || '',
      contract_in_place: sub.contract_in_place != null ? String(sub.contract_in_place) : '',
      contract_no_details: sub.contract_no_details || '',
      work_performed_date: sub.work_performed_date ? String(sub.work_performed_date).slice(0, 10) : '',
      person_performed_work: sub.person_performed_work || '',
      // circumstances is shared with Fidelity Guarantee hydration above
      first_aware_date: sub.first_aware_date ? String(sub.first_aware_date).slice(0, 10) : '',
      reason_for_reporting: sub.reason_for_reporting || '',
      notification_purposes_only: sub.notification_purposes_only != null ? String(sub.notification_purposes_only) : '',
      verbal_written_demand: sub.verbal_written_demand != null ? String(sub.verbal_written_demand) : '',
      demand_received_date: sub.demand_received_date ? String(sub.demand_received_date).slice(0, 10) : '',
      served_with_summons: sub.served_with_summons != null ? String(sub.served_with_summons) : '',
      summons_served_date: sub.summons_served_date ? String(sub.summons_served_date).slice(0, 10) : '',
      attorney_appointed: sub.attorney_appointed != null ? String(sub.attorney_appointed) : '',
      attorney_details: sub.attorney_details || '',
      amount_claimed: sub.amount_claimed || '',
      own_investigation: sub.own_investigation != null ? String(sub.own_investigation) : '',
      views_on_liability: sub.views_on_liability || '',
      views_on_amount_claimed: sub.views_on_amount_claimed || '',
      additional_details: sub.additional_details || '',
      // Plant All Risks (plant_all_risks_claims) — V8 column names. Boolean-
      // like cols (uneconomical_to_repair, subject_to_finance, on_hire_at_time)
      // come back as DB ints 0/1; coerce via String() so "No" doesn't get
      // erased. `responsible_person_*`, `site_physical_address`,
      // `party_responsible_*`, `cause_of_loss`, `police_station`,
      // `police_reference` are already hydrated above (shared with CARPL).
      site_code: sub.site_code || '',
      item_description: sub.item_description || '',
      item_number_sum_insured: sub.item_number_sum_insured || '',
      date_of_loss: sub.date_of_loss ? String(sub.date_of_loss).slice(0, 10) : '',
      time_of_loss: sub.time_of_loss || '',
      details_of_loss: sub.details_of_loss || '',
      estimated_cost: sub.estimated_cost || '',
      uneconomical_to_repair: sub.uneconomical_to_repair != null ? String(sub.uneconomical_to_repair) : '',
      subject_to_finance: sub.subject_to_finance != null ? String(sub.subject_to_finance) : '',
      on_hire_at_time: sub.on_hire_at_time != null ? String(sub.on_hire_at_time) : '',
      // Machinery Breakdown (machinery_breakdown_claims) — form AD-CLM-MB-001.
      // date_of_loss / time_of_loss / estimated_cost / item_description /
      // subject_to_finance / cause_of_loss are already hydrated above (shared).
      // Six boolean-like cols come back as DB ints 0/1 — coerce via String().
      insured: sub.insured || '',
      period_of_insurance: sub.period_of_insurance || '',
      sum_insured: sub.sum_insured || '',
      phone: sub.phone || '',
      cellphone: sub.cellphone || '',
      postal_physical_address: sub.postal_physical_address || '',
      nature_of_business: sub.nature_of_business || '',
      years_in_operation: sub.years_in_operation || '',
      make_model: sub.make_model || '',
      serial_number: sub.serial_number || '',
      year_of_manufacture: sub.year_of_manufacture || '',
      date_commissioned: sub.date_commissioned ? String(sub.date_commissioned).slice(0, 10) : '',
      technical_specs: sub.technical_specs || '',
      current_replacement_value: sub.current_replacement_value || '',
      under_amc_contract: sub.under_amc_contract != null ? String(sub.under_amc_contract) : '',
      amc_contract_details: sub.amc_contract_details || '',
      date_loss_discovered: sub.date_loss_discovered ? String(sub.date_loss_discovered).slice(0, 10) : '',
      site_location: sub.site_location || '',
      equipment_status: sub.equipment_status || '',
      damage_description: sub.damage_description || '',
      proposed_repairer: sub.proposed_repairer || '',
      salvage_location: sub.salvage_location || '',
      sole_owner: sub.sole_owner != null ? String(sub.sole_owner) : '',
      sole_owner_details: sub.sole_owner_details || '',
      co_owner_financier: sub.co_owner_financier || '',
      finance_details: sub.finance_details || '',
      financier_bank_reference: sub.financier_bank_reference || '',
      third_party_responsible: sub.third_party_responsible != null ? String(sub.third_party_responsible) : '',
      third_party_details: sub.third_party_details || '',
      third_party_name_contact: sub.third_party_name_contact || '',
      recovery_claim_lodged: sub.recovery_claim_lodged != null ? String(sub.recovery_claim_lodged) : '',
      other_insurance: sub.other_insurance != null ? String(sub.other_insurance) : '',
      other_insurer_policy: sub.other_insurer_policy || '',
      loss_history: sub.loss_history || '',
      procedural_improvements: sub.procedural_improvements || '',
      declaration_name: sub.declaration_name || '',
      declaration_capacity: sub.declaration_capacity || '',
      declaration_date: sub.declaration_date ? String(sub.declaration_date).slice(0, 10) : '',
      // Machinery Breakdown — Loss of Profit (machinery_breakdown_lop_claims).
      // Shared keys (insured, contact_person, designation, email,
      // postal_physical_address, nature_of_business, years_in_operation,
      // other_insurer_policy, loss_history, declaration_*) are already
      // hydrated above. Five boolean-like cols coerce via String().
      mb_claim_number: sub.mb_claim_number || '',
      date_of_breakdown: sub.date_of_breakdown ? String(sub.date_of_breakdown).slice(0, 10) : '',
      mb_physical_claim_status: sub.mb_physical_claim_status || '',
      phone_cellphone: sub.phone_cellphone || '',
      site_premises_affected: sub.site_premises_affected || '',
      production_capacity: sub.production_capacity || '',
      operating_hours_per_day: sub.operating_hours_per_day || '',
      number_of_shifts: sub.number_of_shifts || '',
      operating_days_per_week: sub.operating_days_per_week || '',
      standard_turnover_prior_12m: sub.standard_turnover_prior_12m || '',
      standard_output_prior_12m: sub.standard_output_prior_12m || '',
      is_seasonal: sub.is_seasonal != null ? String(sub.is_seasonal) : '',
      seasonal_details: sub.seasonal_details || '',
      peak_months_pattern: sub.peak_months_pattern || '',
      comparable_period_turnover: sub.comparable_period_turnover || '',
      comparable_period_output: sub.comparable_period_output || '',
      damaged_item_description: sub.damaged_item_description || '',
      date_production_halted: sub.date_production_halted ? String(sub.date_production_halted).slice(0, 10) : '',
      time_excess_start_end: sub.time_excess_start_end || '',
      date_production_partial_resumed: sub.date_production_partial_resumed ? String(sub.date_production_partial_resumed).slice(0, 10) : '',
      date_production_full_resumed: sub.date_production_full_resumed ? String(sub.date_production_full_resumed).slice(0, 10) : '',
      total_full_shutdown_days: sub.total_full_shutdown_days || '',
      total_reduced_capacity_days: sub.total_reduced_capacity_days || '',
      indemnity_period_max_end_date: sub.indemnity_period_max_end_date ? String(sub.indemnity_period_max_end_date).slice(0, 10) : '',
      loss_continuing: sub.loss_continuing != null ? String(sub.loss_continuing) : '',
      loss_continuing_details: sub.loss_continuing_details || '',
      production_impact_description: sub.production_impact_description || '',
      standard_turnover_indemnity: sub.standard_turnover_indemnity || '',
      actual_turnover_indemnity: sub.actual_turnover_indemnity || '',
      reduction_in_turnover: sub.reduction_in_turnover || '',
      gross_profit_rate: sub.gross_profit_rate || '',
      gross_profit_lost: sub.gross_profit_lost || '',
      icw_outsourcing: sub.icw_outsourcing || '',
      icw_equipment_hire: sub.icw_equipment_hire || '',
      icw_express_freight: sub.icw_express_freight || '',
      icw_overtime_labour: sub.icw_overtime_labour || '',
      icw_temporary_premises: sub.icw_temporary_premises || '',
      icw_other: sub.icw_other || '',
      icw_total: sub.icw_total || '',
      savings_raw_materials: sub.savings_raw_materials || '',
      savings_power_utilities: sub.savings_power_utilities || '',
      savings_wages: sub.savings_wages || '',
      savings_other: sub.savings_other || '',
      savings_total: sub.savings_total || '',
      gross_loss_of_profit: sub.gross_loss_of_profit || '',
      less_time_excess: sub.less_time_excess || '',
      less_self_insured_retention: sub.less_self_insured_retention || '',
      net_estimated_claim: sub.net_estimated_claim || '',
      mitigation_steps: sub.mitigation_steps || '',
      alt_production_available: sub.alt_production_available != null ? String(sub.alt_production_available) : '',
      alt_production_details: sub.alt_production_details || '',
      replacement_equipment_sourced: sub.replacement_equipment_sourced != null ? String(sub.replacement_equipment_sourced) : '',
      replacement_supplier_terms: sub.replacement_supplier_terms || '',
      other_bi_cover: sub.other_bi_cover != null ? String(sub.other_bi_cover) : '',
      other_bi_details: sub.other_bi_details || '',
      // Directors & Officers Liability (directors_officers_liability_claims).
      // Shared keys (period_of_insurance, other_insurance, other_insurance_details,
      // declaration_*) are already hydrated above. Boolean tick-all + Yes/No cols
      // come back as DB ints 0/1 — coerce via String().
      notif_claim: sub.notif_claim != null ? String(sub.notif_claim) : '',
      notif_circumstance: sub.notif_circumstance != null ? String(sub.notif_circumstance) : '',
      notif_investigation: sub.notif_investigation != null ? String(sub.notif_investigation) : '',
      notif_subpoena: sub.notif_subpoena != null ? String(sub.notif_subpoena) : '',
      retroactive_date: sub.retroactive_date ? String(sub.retroactive_date).slice(0, 10) : '',
      limit_aggregate: sub.limit_aggregate || '',
      limit_each_claim: sub.limit_each_claim || '',
      self_insured_retention: sub.self_insured_retention || '',
      side_a: sub.side_a != null ? String(sub.side_a) : '',
      side_b: sub.side_b != null ? String(sub.side_b) : '',
      side_c: sub.side_c != null ? String(sub.side_c) : '',
      epl_extension: sub.epl_extension != null ? String(sub.epl_extension) : '',
      insured_company: sub.insured_company || '',
      company_registration_number: sub.company_registration_number || '',
      regulator_license_number: sub.regulator_license_number || '',
      industry_sector: sub.industry_sector || '',
      registered_address: sub.registered_address || '',
      company_secretary_contact: sub.company_secretary_contact || '',
      person1_name_id: sub.person1_name_id || '',
      person1_position: sub.person1_position || '',
      person1_appointment_date: sub.person1_appointment_date ? String(sub.person1_appointment_date).slice(0, 10) : '',
      person1_current_former: sub.person1_current_former || '',
      person2_name_id: sub.person2_name_id || '',
      person2_position: sub.person2_position || '',
      person2_appointment_date: sub.person2_appointment_date ? String(sub.person2_appointment_date).slice(0, 10) : '',
      person2_current_former: sub.person2_current_former || '',
      additional_insured_persons: sub.additional_insured_persons || '',
      date_wrongful_act: sub.date_wrongful_act ? String(sub.date_wrongful_act).slice(0, 10) : '',
      date_claim_first_made: sub.date_claim_first_made ? String(sub.date_claim_first_made).slice(0, 10) : '',
      date_insured_first_aware: sub.date_insured_first_aware ? String(sub.date_insured_first_aware).slice(0, 10) : '',
      claimant_shareholder: sub.claimant_shareholder != null ? String(sub.claimant_shareholder) : '',
      claimant_regulator: sub.claimant_regulator != null ? String(sub.claimant_regulator) : '',
      claimant_liquidator: sub.claimant_liquidator != null ? String(sub.claimant_liquidator) : '',
      claimant_employee: sub.claimant_employee != null ? String(sub.claimant_employee) : '',
      claimant_customer: sub.claimant_customer != null ? String(sub.claimant_customer) : '',
      claimant_creditor: sub.claimant_creditor != null ? String(sub.claimant_creditor) : '',
      claimant_government: sub.claimant_government != null ? String(sub.claimant_government) : '',
      claimant_other: sub.claimant_other != null ? String(sub.claimant_other) : '',
      claimant_names: sub.claimant_names || '',
      claimant_legal_counsel: sub.claimant_legal_counsel || '',
      form_letter_demand: sub.form_letter_demand != null ? String(sub.form_letter_demand) : '',
      form_summons: sub.form_summons != null ? String(sub.form_summons) : '',
      form_subpoena: sub.form_subpoena != null ? String(sub.form_subpoena) : '',
      form_regulator_inquiry: sub.form_regulator_inquiry != null ? String(sub.form_regulator_inquiry) : '',
      form_criminal_charge: sub.form_criminal_charge != null ? String(sub.form_criminal_charge) : '',
      form_internal_investigation: sub.form_internal_investigation != null ? String(sub.form_internal_investigation) : '',
      form_other: sub.form_other != null ? String(sub.form_other) : '',
      alleg_fiduciary_breach: sub.alleg_fiduciary_breach != null ? String(sub.alleg_fiduciary_breach) : '',
      alleg_misstatement: sub.alleg_misstatement != null ? String(sub.alleg_misstatement) : '',
      alleg_insolvent_trading: sub.alleg_insolvent_trading != null ? String(sub.alleg_insolvent_trading) : '',
      alleg_misappropriation: sub.alleg_misappropriation != null ? String(sub.alleg_misappropriation) : '',
      alleg_regulatory_breach: sub.alleg_regulatory_breach != null ? String(sub.alleg_regulatory_breach) : '',
      alleg_employment_practices: sub.alleg_employment_practices != null ? String(sub.alleg_employment_practices) : '',
      alleg_negligence: sub.alleg_negligence != null ? String(sub.alleg_negligence) : '',
      alleg_criminal: sub.alleg_criminal != null ? String(sub.alleg_criminal) : '',
      alleg_defamation: sub.alleg_defamation != null ? String(sub.alleg_defamation) : '',
      alleg_other: sub.alleg_other != null ? String(sub.alleg_other) : '',
      allegation_description: sub.allegation_description || '',
      total_quantum_claimed: sub.total_quantum_claimed || '',
      stage_of_proceedings: sub.stage_of_proceedings || '',
      court_forum: sub.court_forum || '',
      case_reference_number: sub.case_reference_number || '',
      counsel_engaged: sub.counsel_engaged != null ? String(sub.counsel_engaged) : '',
      counsel_engaged_details: sub.counsel_engaged_details || '',
      counsel_firm_attorney: sub.counsel_firm_attorney || '',
      counsel_contact_rate: sub.counsel_contact_rate || '',
      ad_prior_consent: sub.ad_prior_consent != null ? String(sub.ad_prior_consent) : '',
      ad_prior_consent_details: sub.ad_prior_consent_details || '',
      estimated_defense_costs: sub.estimated_defense_costs || '',
      next_hearing_deadline: sub.next_hearing_deadline ? String(sub.next_hearing_deadline).slice(0, 10) : '',
      settlement_offer_made: sub.settlement_offer_made != null ? String(sub.settlement_offer_made) : '',
      settlement_offer_details: sub.settlement_offer_details || '',
      codefendants_insured_persons: sub.codefendants_insured_persons != null ? String(sub.codefendants_insured_persons) : '',
      codefendants_details: sub.codefendants_details || '',
      codefendant_names: sub.codefendant_names || '',
      company_named: sub.company_named != null ? String(sub.company_named) : '',
      company_named_details: sub.company_named_details || '',
      outside_parties_named: sub.outside_parties_named != null ? String(sub.outside_parties_named) : '',
      outside_parties_details: sub.outside_parties_details || '',
      outside_party_names: sub.outside_party_names || '',
      other_policy_details: sub.other_policy_details || '',
      previously_notified: sub.previously_notified != null ? String(sub.previously_notified) : '',
      previously_notified_details: sub.previously_notified_details || '',
      prior_notification_reference: sub.prior_notification_reference || '',
      // Marine Cargo Once-Off (marine_cargo_once_off_claims). Shared keys
      // (insured, contact_person, phone, cellphone, email, date_of_loss,
      // date_loss_discovered, other_insurance*, other_insurer_policy,
      // loss_history, procedural_improvements, recovery_claim_lodged,
      // declaration_*) are already hydrated above. Booleans coerce via String().
      certificate_number: sub.certificate_number || '',
      period_of_cover: sub.period_of_cover || '',
      insured_value: sub.insured_value || '',
      conditions_of_cover: sub.conditions_of_cover || '',
      description_of_goods: sub.description_of_goods || '',
      number_type_packages: sub.number_type_packages || '',
      marks_numbers: sub.marks_numbers || '',
      gross_weight: sub.gross_weight || '',
      net_weight: sub.net_weight || '',
      commercial_invoice_number: sub.commercial_invoice_number || '',
      invoice_value: sub.invoice_value || '',
      cif_value: sub.cif_value || '',
      container_number: sub.container_number || '',
      seal_numbers: sub.seal_numbers || '',
      mode_sea: sub.mode_sea != null ? String(sub.mode_sea) : '',
      mode_air: sub.mode_air != null ? String(sub.mode_air) : '',
      mode_road: sub.mode_road != null ? String(sub.mode_road) : '',
      mode_rail: sub.mode_rail != null ? String(sub.mode_rail) : '',
      mode_multimodal: sub.mode_multimodal != null ? String(sub.mode_multimodal) : '',
      multimodal_route_description: sub.multimodal_route_description || '',
      origin: sub.origin || '',
      destination: sub.destination || '',
      vessel_aircraft_truck_reg: sub.vessel_aircraft_truck_reg || '',
      voyage_flight_trip_no: sub.voyage_flight_trip_no || '',
      date_of_departure: sub.date_of_departure ? String(sub.date_of_departure).slice(0, 10) : '',
      date_of_arrival: sub.date_of_arrival ? String(sub.date_of_arrival).slice(0, 10) : '',
      bill_of_lading_number: sub.bill_of_lading_number || '',
      carrier: sub.carrier || '',
      freight_forwarder: sub.freight_forwarder || '',
      date_ad_notified: sub.date_ad_notified ? String(sub.date_ad_notified).slice(0, 10) : '',
      place_stage_of_loss: sub.place_stage_of_loss || '',
      loss_shortage: sub.loss_shortage != null ? String(sub.loss_shortage) : '',
      loss_pilferage: sub.loss_pilferage != null ? String(sub.loss_pilferage) : '',
      loss_non_delivery: sub.loss_non_delivery != null ? String(sub.loss_non_delivery) : '',
      loss_damage_handling: sub.loss_damage_handling != null ? String(sub.loss_damage_handling) : '',
      loss_wet_seawater: sub.loss_wet_seawater != null ? String(sub.loss_wet_seawater) : '',
      loss_freshwater: sub.loss_freshwater != null ? String(sub.loss_freshwater) : '',
      loss_fire_explosion: sub.loss_fire_explosion != null ? String(sub.loss_fire_explosion) : '',
      loss_hijacking: sub.loss_hijacking != null ? String(sub.loss_hijacking) : '',
      loss_sea_perils: sub.loss_sea_perils != null ? String(sub.loss_sea_perils) : '',
      loss_other: sub.loss_other != null ? String(sub.loss_other) : '',
      loss_description: sub.loss_description || '',
      estimated_value_of_loss: sub.estimated_value_of_loss || '',
      notice_of_loss_issued: sub.notice_of_loss_issued != null ? String(sub.notice_of_loss_issued) : '',
      notice_of_loss_details: sub.notice_of_loss_details || '',
      notice_date_reference: sub.notice_date_reference || '',
      carrier_acknowledged: sub.carrier_acknowledged != null ? String(sub.carrier_acknowledged) : '',
      carrier_acknowledged_details: sub.carrier_acknowledged_details || '',
      carrier_reply_reference: sub.carrier_reply_reference || '',
      joint_survey_held: sub.joint_survey_held != null ? String(sub.joint_survey_held) : '',
      joint_survey_details: sub.joint_survey_details || '',
      surveyor_agent: sub.surveyor_agent || '',
      survey_report_attached: sub.survey_report_attached != null ? String(sub.survey_report_attached) : '',
      survey_report_details: sub.survey_report_details || '',
      police_report_attached: sub.police_report_attached != null ? String(sub.police_report_attached) : '',
      police_report_details: sub.police_report_details || '',
      police_station_ob: sub.police_station_ob || '',
      recovery_claim_details: sub.recovery_claim_details || '',
      carrier_name_address: sub.carrier_name_address || '',
      goods_financed: sub.goods_financed != null ? String(sub.goods_financed) : '',
      goods_financed_details: sub.goods_financed_details || '',
      bank_financier_reference: sub.bank_financier_reference || '',
      // Marine Cargo Open Cover — only the open-cover policy header keys are
      // unique; all other fields share column names hydrated above.
      open_cover_policy_number: sub.open_cover_policy_number || '',
      annual_aggregate_sum_insured: sub.annual_aggregate_sum_insured || '',
      certificate_declaration_number: sub.certificate_declaration_number || '',
      date_of_declaration: sub.date_of_declaration ? String(sub.date_of_declaration).slice(0, 10) : '',
      insured_value_declared: sub.insured_value_declared || '',
      // Locks & Keys / Key Loss (key_loss_claim) — V8 active fields plus
      // V2 form extensions. `registered_claim` and `reason` are already
      // hydrated at the top of this setEditForm (top-level new_claims
      // keys shared across motor claim types). The `police_affidavit`
      // file path is not hydrated here; it round-trips via the *_url
      // helper attached by show().
      purpose: sub.purpose || '',
      lossDate: sub.lossDate ? String(sub.lossDate).slice(0, 10) : '',
      descriptionofLoss: sub.descriptionofLoss || '',
      key_reason: sub.key_reason || '',
      // Full V8 key-loss form extras (insured / vehicle / quotes). make,
      // model, vehicle_plate, insured_email, insured_occupation and the
      // company_/amount_quote_ pairs are hydrated elsewhere in this object.
      financial_interest: sub.financial_interest || '',
      chassis_num: sub.chassis_num || '',
      estimate: sub.estimate || '',
      name_of_insured: sub.name_of_insured || '',
      insured_address: sub.insured_address || '',
      insured_contact_no: sub.insured_contact_no || '',
      is_imported: sub.is_imported || '',
      year: sub.year || '',
      // Glass / Windscreen (glass_claim) — V8 column names plus V2's
      // damage_location + replacement-quote extensions. The 6 file path
      // fields (incidentFront/Back/Right/Left, quote_1, quote_2) round-
      // trip via *_url helpers attached by show(); they're not hydrated
      // into the editForm. `incident_date` is already hydrated at the
      // top of this setEditForm (motor-shared key).
      extent: sub.extent || '',
      cause: sub.cause || '',
      damage_location: sub.damage_location || '',
      company_1: sub.company_1 || '',
      amount_quote_1: sub.amount_quote_1 || '',
      company_2: sub.company_2 || '',
      amount_quote_2: sub.amount_quote_2 || '',
      front_image_description: sub.front_image_description || '',
      back_image_description: sub.back_image_description || '',
      right_image_description: sub.right_image_description || '',
      left_image_description: sub.left_image_description || '',
      // Medical Malpractice (medical_malpractice_claims) — V8 column names.
      // Date columns sliced to YYYY-MM-DD. `insured_email` is already
      // hydrated above (shared with PI / Public Liability). The 5 file
      // path fields are NOT hydrated; they round-trip via *_url helpers
      // attached by show().
      insured_full_name: sub.insured_full_name || '',
      professional_title_role: sub.professional_title_role || '',
      license_registration_number: sub.license_registration_number || '',
      facility_practice_name: sub.facility_practice_name || '',
      address_of_practice: sub.address_of_practice || '',
      insured_contact_number: sub.insured_contact_number || '',
      claimant_full_name: sub.claimant_full_name || '',
      claimant_date_of_birth: sub.claimant_date_of_birth ? String(sub.claimant_date_of_birth).slice(0, 10) : '',
      claimant_contact_number: sub.claimant_contact_number || '',
      claimant_mailing_address: sub.claimant_mailing_address || '',
      date_of_alleged_incident: sub.date_of_alleged_incident ? String(sub.date_of_alleged_incident).slice(0, 10) : '',
      nature_of_services_provided: sub.nature_of_services_provided || '',
      date_of_notification: sub.date_of_notification ? String(sub.date_of_notification).slice(0, 10) : '',
      how_notified: sub.how_notified || '',
      description_of_allegation: sub.description_of_allegation || '',
    })

    // Motor — nested state
    const ad = c.accident_details ?? {}
    setEditAccident({
      place_of_accident: ad.place_of_accident || '',
      time_of_accident:  ad.time_of_accident || '',
      date_of_accident:  ad.date_of_accident ? String(ad.date_of_accident).slice(0, 10) : '',
      detail_of_accident: ad.detail_of_accident || '',
      purpose_of_trip:   ad.purpose_of_trip || '',
      fault_party:       ad.fault_party || '',
      third_party:       String(ad.third_party ?? 0) === '1' || ad.third_party === true ? '1' : '0',
    })
    const drv = c.accidentDriver ?? {}
    setEditDriver({
      name: drv.name || '',
      cellphone: drv.cellphone || drv.contact_number || '',
      dob: drv.dob ? String(drv.dob).slice(0, 10) : '',
      address: drv.address || '',
      license: drv.license || '',
      purpose: drv.purpose || '',
    })
    setEditPassengers((c.accidentPassengers ?? []).map((p: any) => ({
      name: p.name || '',
      address: p.address || '',
      injury: p.injury || '',
    })))
    setEditThirdParties((c.thirdParties ?? []).map((tp: any) => ({
      first_name: tp.first_name || '',
      last_name:  tp.last_name || '',
      cellphone:  tp.cellphone || '',
      address:    tp.address || '',
      make:       tp.make || '',
      model:      tp.model || '',
      registration_no: tp.registration_no || '',
      damage_details:  tp.damage_details || '',
      injured_name:    tp.injured_name || '',
      relationship:    tp.relationship || '',
      hospital_name:   tp.hospital_name || '',
      injured_details: tp.injured_details || '',
    })))

    setShowEditModal(true)
    if (!editLookups) {
      try {
        const { data } = await apiClient.get('/claims/create-data')
        setEditLookups(data?.data ?? null)
      } catch { /* non-fatal */ }
    }
  }

  async function handleEdit() {
    setEditSaving(true)
    try {
      // Coerce boolean-like strings to 0/1 and drop empties so backend
      // validation doesn't reject unchanged fields with empty strings.
      const boolKeys = ['is_motor_claim','catastrophe_loss','attorney_involved','co_attorney_involved','dfs_complaint','recovery_involved','third_party_insured_elsewhere','driver_as_insured']
      const intKeys = ['claim_allocated_to']

      // Fields in editForm that belong to sub_claim_data (DOMG/COMG).
      // These get pulled out of the flat dict and shipped under
      // sub_claim_data so the backend routes them to the matching
      // legacy sub-table. Claim-type decides which keys matter — we
      // send all non-empty ones and let the backend's column-intersect
      // filter drop the irrelevant keys.
      const subClaimKeys = [
        'previously_suffered_loss',
        'other_party_interest','other_insurance_covering','address_of_premises','description_of_incident',
        'date_time_police_advised','total_value_contents_of_premises','employees_been_involved',
        'circumstances','defaulting_employees_name',
        // Property Loss / Damage (property_loss_damage) — V8 column names.
        // Stale `property_description`/`loss_damage_date`/etc. removed.
        'loss_damage_discovered','loss_damage_occurred',
        'premises_occupied','last_occupied','purpose_of_occupation',
        'nature_interruption','loss_for_each_item',
        'give_details','name_of_insurer','reference_no_station',
        'interest_insured_property','give_name_insurer',
        'value_all_property','when_last_valued',
        // Public Liability + Liability (public_liability) — V8 column names.
        // Stale `injured_party_name`/`injury_description` removed — they
        // didn't match V8's table.
        'insured_treding_name','insured_postal_address','insured_email','insured_telephone_no',
        'insured_facsimile','insured_mobile_no',
        'accident_date','accident_time','accident_incident','accident_injuries',
        'accident_liability','accident_person','accident_contacted',
        'attach_contractor','attach_employee','attach_employed','attach_blame',
        'attach_circumstances','attach_property','attach_details','attach_owner','attach_damage',
        'claim_name','claim_telephone','claim_mobile','claim_postal','claim_solicitor',
        'witness1_name','witness1_telephone','witness1_mobile','witness1_postal','witness1_relationship',
        'witness2_name','witness2_telephone','witness2_mobile','witness2_postal','witness2_relationship',
        'witness2_damage',
        // Workers Compensation / Stated Benefits — V8 column names
        // (workers_compensation table).
        'injured_name','injured_age','injured_address','injured_status',
        'injured_occupation','injured_nationality','injured_service_period',
        'your_direct_employ','address_of_contractor',
        'date','time','place','how_accident_occur','first_report_accident','period_of_disablement',
        // Defective Workmanship (defective_workmanship) — V8 column names
        'location_of_accident','accident_date_time','owners_name',
        'telephone_number','mobile_number','address',
        'make','model','registration','vehicle_drivable',
        'vehicle_handed_claimant','when_vehicle_handed','allegations_received',
        // Erection All Risk (erection_all_risk_claims) — V8 column names.
        // 32 fields including 5 booleans + 3 conditional follow-up
        // textareas (responsible_for_damage_details, recovery_details,
        // third_party_liability_details).
        'insured_occupation','period_from','period_to','supervisor_engineer_name',
        'date_of_occurrence','time_of_occurrence',
        'site_of_damage','nearest_railway_station',
        'damage_contract_works','damage_plant_equipment','damage_third_party_property',
        'cause_of_damage',
        'responsible_for_damage','responsible_for_damage_details',
        'possibility_of_recovery','recovery_details',
        'how_damage_occurred','probable_cause','progress_of_construction','how_items_repaired',
        'alterations_during_repairs',
        'witness_name','witness_address',
        'surrounding_properties_damaged',
        'third_party_liability','third_party_liability_details',
        'estimated_cost_contract_works','estimated_cost_plant_machinery',
        'estimated_cost_third_party_property','estimated_cost_owners_surrounding',
        'other_insurance_details','previous_losses_details',
        // Contractors All Risks / Public Liability
        // (contractors_all_risks_public_liability) — V8 column names.
        // The two file fields (works_claim_documentary_evidence,
        // works_claim_bill_of_quantities) ship via FormData files state,
        // not in sub_claim_data, so they're omitted here.
        'responsible_person_name','responsible_person_phone',
        'responsible_person_cellphone','responsible_person_email','responsible_person_fax',
        'parties_to_contract','contract_value','contract_number',
        'description_of_contract','site_physical_address','code',
        'contract_commencement_date','expected_contract_completion_date',
        'responsible_contract_works_claim','responsible_public_liability_claim',
        'loss_date','loss_time','loss_details','cause_of_loss',
        'party_responsible_name','party_responsible_contact',
        'estimated_cost_of_repair_replacement',
        'police_station','police_reference',
        // Mobile/Electronic Devices + Office Contents
        // (mobile_and_electronic_devices_claim) — V8 column names.
        // property_stolen_damaged + is_sole_owner_of_property +
        // sole_owner_of_property are already in this list (All Risks).
        'insured_name','email_address','telephone_no',
        'date_time_loss_discovered','whom_discovered',
        // Fire (fire_claim) — V8 column names. brief_description_incident,
        // police_station_name, property_last_seen, premises_properly_secured
        // are already in this list (shared with Burglary / GIT).
        'address_of_theft_occurred','date_time_of_theft',
        'anyone_during_burglary','details_during_burglary',
        'days_premises_unoccupied',
        'premises_guarded_by_watchman','name_of_guard','telephone_of_guard','guard_during_fire',
        'name_of_security_agent',
        'suspect_any_person','suspect_person_details',
        'total_value_premises_buildings',
        'other_insurance_against_fire','insurance_against_fire_details',
        'estimated_amount_of_damaged','details_of_previous_loss',
        // Goods In Transit (goods_in_transit_claim) — V8 column names
        'address_of_premises_loss','details_of_driver','property_last_seen','date_time_of_loss',
        'brief_description_incident','police_station_name','witnesses_name','witnesses_mobile_number',
        'total_value_of_loss','consignment_transported_to','consignment_from','vehicle_registration_number',
        'is_carrier_contracted','carrier_has_own_GIT_ins','other_insurance_against_theft',
        'insurance_against_theft_details','details_of_previous_loss_records',
        // All Risk / Electronic Equipment / Personal All Risks
        // (all_risk_and_electronic_equipment) — V8 column names
        'property_stolen_damaged','thorough_search_made_for_article',
        'loss_cause','loss_by_other_cause','stolenfromcar_unlockedpremises',
        'sole_owner_of_property','is_sole_owner_of_property',
        // Travel Insurance (travel_insurance_claim) — V8 column names. The
        // 25 file path fields (compulsory_doc_*, medical_dental_care_doc_*,
        // claim_*_doc_*) ship via FormData files state, not in sub_claim_data,
        // so they're omitted here. `address` is already listed (shared with
        // Defective Workmanship); `insured_name` is shared with MED; `policy_
        // number`, `email`, `mobile`, `telephone`, `home_address`,
        // `phone_number`, `post_code` are TI-only.
        'title','other_title','surname','forename','dob',
        'passport_no','nationality','telephone','post_code','mobile',
        'email','home_address',
        'policy_number','issued_by','issued_on','valid_from','valid_to',
        'beneficiary','bank_name','bank_address','account_number',
        'iban','swift_code','bic_code',
        'other_insurance_policy','name_insurance_company','phone_number',
        'type_of_refund','type_of_refund_other',
        // Plant All Risks (plant_all_risks_claims) — V8 column names.
        // responsible_person_*, site_physical_address, party_responsible_*,
        // cause_of_loss, police_station, police_reference are already
        // listed above (shared with CARPL).
        'site_code','item_description','item_number_sum_insured',
        'date_of_loss','time_of_loss','details_of_loss','estimated_cost',
        'uneconomical_to_repair','subject_to_finance','on_hire_at_time',
        // Machinery Breakdown (machinery_breakdown_claims) — form AD-CLM-MB-001.
        // date_of_loss / time_of_loss / cause_of_loss / estimated_cost /
        // item_description / subject_to_finance are shared with the entries
        // above (duplicates are harmless — .includes() only tests membership).
        'insured','period_of_insurance','sum_insured',
        'contact_person','designation','phone','cellphone','email',
        'postal_physical_address','nature_of_business','years_in_operation',
        'make_model','serial_number','year_of_manufacture','date_commissioned',
        'technical_specs','current_replacement_value',
        'under_amc_contract','amc_contract_details',
        'cause_of_loss','date_loss_discovered','site_location','equipment_status','damage_description',
        'proposed_repairer','salvage_location',
        'sole_owner','sole_owner_details','co_owner_financier',
        'finance_details','financier_bank_reference',
        'third_party_responsible','third_party_details','third_party_name_contact',
        'recovery_claim_lodged','recovery_details',
        'other_insurance','other_insurance_details','other_insurer_policy','loss_history',
        'procedural_improvements','declaration_name','declaration_capacity','declaration_date',
        // Machinery Breakdown — Loss of Profit (machinery_breakdown_lop_claims).
        // insured / contact_person / designation / email / postal_physical_address /
        // nature_of_business / years_in_operation / other_insurer_policy / loss_history /
        // declaration_* are shared with the entries above (duplicates harmless —
        // .includes() only tests membership).
        'mb_claim_number','date_of_breakdown','mb_physical_claim_status',
        'phone_cellphone','site_premises_affected',
        'production_capacity','operating_hours_per_day','number_of_shifts','operating_days_per_week',
        'standard_turnover_prior_12m','standard_output_prior_12m',
        'is_seasonal','seasonal_details','peak_months_pattern',
        'comparable_period_turnover','comparable_period_output',
        'damaged_item_description','date_production_halted','time_excess_start_end',
        'date_production_partial_resumed','date_production_full_resumed',
        'total_full_shutdown_days','total_reduced_capacity_days','indemnity_period_max_end_date',
        'loss_continuing','loss_continuing_details','production_impact_description',
        'standard_turnover_indemnity','actual_turnover_indemnity','reduction_in_turnover',
        'gross_profit_rate','gross_profit_lost',
        'icw_outsourcing','icw_equipment_hire','icw_express_freight','icw_overtime_labour',
        'icw_temporary_premises','icw_other','icw_total',
        'savings_raw_materials','savings_power_utilities','savings_wages','savings_other','savings_total',
        'gross_loss_of_profit','less_time_excess','less_self_insured_retention','net_estimated_claim',
        'mitigation_steps','alt_production_available','alt_production_details',
        'replacement_equipment_sourced','replacement_supplier_terms',
        'other_bi_cover','other_bi_details',
        // Directors & Officers Liability (directors_officers_liability_claims).
        // declaration_* / other_insurance / other_insurance_details /
        // period_of_insurance are shared with entries above (dups harmless).
        'notif_claim','notif_circumstance','notif_investigation','notif_subpoena',
        'period_of_insurance','retroactive_date','limit_aggregate','limit_each_claim','self_insured_retention',
        'side_a','side_b','side_c','epl_extension',
        'insured_company','company_registration_number','regulator_license_number',
        'industry_sector','registered_address','company_secretary_contact',
        'person1_name_id','person1_position','person1_appointment_date','person1_current_former',
        'person2_name_id','person2_position','person2_appointment_date','person2_current_former',
        'additional_insured_persons',
        'date_wrongful_act','date_claim_first_made','date_insured_first_aware',
        'claimant_shareholder','claimant_regulator','claimant_liquidator','claimant_employee',
        'claimant_customer','claimant_creditor','claimant_government','claimant_other',
        'claimant_names','claimant_legal_counsel',
        'form_letter_demand','form_summons','form_subpoena','form_regulator_inquiry',
        'form_criminal_charge','form_internal_investigation','form_other',
        'alleg_fiduciary_breach','alleg_misstatement','alleg_insolvent_trading','alleg_misappropriation',
        'alleg_regulatory_breach','alleg_employment_practices','alleg_negligence','alleg_criminal',
        'alleg_defamation','alleg_other',
        'allegation_description','total_quantum_claimed','stage_of_proceedings','court_forum','case_reference_number',
        'counsel_engaged','counsel_engaged_details','counsel_firm_attorney','counsel_contact_rate',
        'ad_prior_consent','ad_prior_consent_details','estimated_defense_costs','next_hearing_deadline',
        'settlement_offer_made','settlement_offer_details',
        'codefendants_insured_persons','codefendants_details','codefendant_names',
        'company_named','company_named_details',
        'outside_parties_named','outside_parties_details','outside_party_names',
        'other_policy_details',
        'previously_notified','previously_notified_details','prior_notification_reference',
        // Marine Cargo Once-Off (marine_cargo_once_off_claims). Shared keys
        // (insured, contact_person, date_of_loss, other_insurance, declaration_*,
        // loss_history, procedural_improvements, recovery_claim_lodged, etc.) are
        // dups of entries above — harmless (.includes() only tests membership).
        'certificate_number','period_of_cover','insured_value','conditions_of_cover',
        'description_of_goods','number_type_packages','marks_numbers','gross_weight','net_weight',
        'commercial_invoice_number','invoice_value','cif_value','container_number','seal_numbers',
        'mode_sea','mode_air','mode_road','mode_rail','mode_multimodal',
        'multimodal_route_description','origin','destination','vessel_aircraft_truck_reg','voyage_flight_trip_no',
        'date_of_departure','date_of_arrival','bill_of_lading_number','carrier','freight_forwarder',
        'date_ad_notified','place_stage_of_loss',
        'loss_shortage','loss_pilferage','loss_non_delivery','loss_damage_handling','loss_wet_seawater',
        'loss_freshwater','loss_fire_explosion','loss_hijacking','loss_sea_perils','loss_other',
        'loss_description','estimated_value_of_loss',
        'notice_of_loss_issued','notice_of_loss_details','notice_date_reference',
        'carrier_acknowledged','carrier_acknowledged_details','carrier_reply_reference',
        'joint_survey_held','joint_survey_details','surveyor_agent',
        'survey_report_attached','survey_report_details',
        'police_report_attached','police_report_details','police_station_ob',
        'recovery_claim_details','carrier_name_address',
        'goods_financed','goods_financed_details','bank_financier_reference',
        // Marine Cargo Open Cover (marine_cargo_open_cover_claims) — only the
        // open-cover policy header keys are unique; the rest are shared with the
        // once-off marine form above (dups harmless).
        'open_cover_policy_number','annual_aggregate_sum_insured','certificate_declaration_number',
        'date_of_declaration','insured_value_declared',
        // Locks & Keys / Key Loss (key_loss_claim) — V8 active fields +
        // V2 extensions. `reason` and `registered_claim` are not added
        // here because they're already top-level new_claims keys
        // (column-intersect filter at the backend dual-writes them
        // when the sub-table also has the column). The `police_affidavit`
        // file ships via FormData files state, not in sub_claim_data.
        'purpose','lossDate','descriptionofLoss',
        'chassis_num','financial_interest','key_reason','estimate',
        // Full V8 key-loss form — insured details + vehicle details.
        // company_/amount_quote_ pairs are already listed under Glass below;
        // quote_1/quote_2/police_affidavit files ship via FormData.
        'name_of_insured','insured_address','insured_occupation','insured_email','insured_contact_no',
        'vehicle_plate','is_imported','make','year','model',
        // Glass / Windscreen (glass_claim) — V8 column names plus V2
        // extensions. The 6 file fields ship via FormData files state,
        // not in sub_claim_data.
        'incident_date','extent','cause','damage_location',
        'company_1','amount_quote_1','company_2','amount_quote_2',
        'front_image_description','back_image_description',
        'right_image_description','left_image_description',
        // Medical Malpractice (medical_malpractice_claims) — V8 column
        // names. `insured_email` is already in this list (shared with PI).
        // The 5 file fields (notification_letter, patient_records,
        // investigation_reports, correspondence, expert_legal_opinions)
        // ship via FormData files state, not in sub_claim_data.
        'insured_full_name','professional_title_role','license_registration_number',
        'facility_practice_name','address_of_practice','insured_contact_number',
        'claimant_full_name','claimant_date_of_birth',
        'claimant_contact_number','claimant_mailing_address',
        'date_of_alleged_incident','nature_of_services_provided',
        'date_of_notification','how_notified','description_of_allegation',
        // Professional Indemnity (professional_indemnity_claims) — V8
        // column names. circumstances + insured_email are already in this
        // list (shared with Fidelity / Public Liability). The two file
        // fields (contract_copy, investigation_findings) ship via
        // FormData files state, not in sub_claim_data, so they're omitted.
        'type_of_business','contact_person','designation','insured_cell_tel',
        'claimant_type','claimant_name_surname','claimant_email','claimant_cell_tel',
        'insured_retained_to_do','contract_in_place','contract_no_details',
        'work_performed_date','person_performed_work',
        'first_aware_date','reason_for_reporting',
        'notification_purposes_only','verbal_written_demand','demand_received_date',
        'served_with_summons','summons_served_date',
        'attorney_appointed','attorney_details','amount_claimed',
        'own_investigation','views_on_liability','views_on_amount_claimed',
        'additional_details',
      ]

      const payload: Record<string, any> = {}
      const subClaimData: Record<string, any> = {}
      for (const [k, v] of Object.entries(editForm)) {
        if (v === '' || v == null) continue
        if (subClaimKeys.includes(k)) { subClaimData[k] = v; continue }
        if (boolKeys.includes(k)) { payload[k] = v === '1' || v === 'true' ? 1 : 0; continue }
        if (intKeys.includes(k))  { payload[k] = Number(v); continue }
        payload[k] = v
      }
      if (Object.keys(subClaimData).length > 0) {
        payload.sub_claim_data = subClaimData
      }

      // Motor-accident nested payloads. Only ship non-empty ones so we
      // don't churn claim_accidents/accident_driver/passengers/third-
      // parties on claims that aren't motor.
      const nonEmpty = (obj: Record<string, string>) =>
        Object.fromEntries(Object.entries(obj).filter(([, v]) => v !== '' && v != null))

      const acc = nonEmpty(editAccident)
      if (Object.keys(acc).length > 0) {
        // Coerce third_party to boolean per backend validator
        if ('third_party' in acc) acc.third_party = (acc.third_party === '1' ? 1 : 0) as any
        payload.accident_details = acc
      }
      const drv = nonEmpty(editDriver)
      if (Object.keys(drv).length > 0) payload.accident_driver = drv

      // Passengers / third parties — replace-all semantics. Send even
      // when empty so a user clearing out all rows propagates. Ship for
      // any motor claim — MIS (form_template='vehicle') AND DOM/COM
      // motor accidents / motor traders (form_template='coverage_based'
      // but claim_type is one of the V8 motor codes).
      const motorClaimTypeKey = (claim?.claim_type || '').toUpperCase().replace(/[^A-Z0-9]/g, '')
      const isMotorClaim = claim?.policy?.form_template === 'vehicle'
        || ['MOTORACCIDENT','MOTORTRADERSEXTERNAL','MOTORTRADERSINTERNAL'].includes(motorClaimTypeKey)
      if (isMotorClaim) {
        payload.accident_passengers   = editPassengers.map(p => nonEmpty(p)).filter(p => Object.keys(p).length > 0)
        payload.accident_third_parties = editThirdParties.map(tp => nonEmpty(tp)).filter(tp => Object.keys(tp).length > 0)
      }

      await apiClient.put(`/claims/${id}`, payload)
      setShowEditModal(false)
      refetch()
    } catch (e: any) { toast.error(e.response?.data?.message || 'Update failed') }
    finally { setEditSaving(false) }
  }

  async function loadEditHistory() {
    try {
      const { data } = await apiClient.get(`/claims/${id}/edit-history`)
      setEditHistory(data.data ?? [])
      setShowEditHistory(true)
    } catch { setEditHistory([]) }
  }

  // Mutations additionally require the claim-edit permission — the backend
  // enforces the same rule on every claim write endpoint, this just hides
  // the controls for view-only roles (e.g. Finance claims review).
  const canEditClaims = getStoredPermissions().includes('claim-edit')
  // Claim is editable if status is NOT Closed
  const isEditable = claim && claim.status !== 'Closed' && canEditClaims

  if (isLoading) return (
    <div className="flex flex-col items-center justify-center h-64 gap-3">
      <LoadingSpinner size="lg" />
      {loadingSlow && (
        <p className="text-xs text-ink-faint italic">Taking longer than expected — server may be slow. Sit tight.</p>
      )}
    </div>
  )
  if (error || !claim) {
    return (
      <div className="p-8 text-center space-y-3">
        <p className="text-status-danger-fg">Failed to load claim details.</p>
        <p className="text-xs text-ink-faint">
          {(error as any)?.response?.data?.message
            ?? (error as Error)?.message
            ?? 'The server did not return a valid claim record.'}
        </p>
        <div className="flex items-center justify-center gap-3 pt-2">
          <button onClick={() => refetch()} className="px-3 py-1.5 bg-primary text-white rounded text-sm hover:bg-primary">Try again</button>
          <button onClick={() => navigate('/claims')} className="text-primary hover:underline text-sm">Back to Claims</button>
        </div>
      </div>
    )
  }

  // A claim with a blank/unknown status (null or '') is treated as 'New' so its
  // transitions apply and the Update Status button still shows — otherwise an
  // "Unknown" claim can never be moved into the flow. Mirrors the backend, which
  // resolves a blank status to 'New' in ClaimsController::updateStatus().
  const fromStatus = (claim.status ?? '').trim() || 'New'
  const allowedStatuses = STATUS_TRANSITIONS[fromStatus] ?? []
  const statusColor = STATUS_COLORS[claim.status] ?? 'bg-surface-2 text-ink-muted border-line'

  return (
    <div className="max-w-6xl mx-auto space-y-4">
      {/* ── Header ── */}
      <div className="flex items-start justify-between">
        <div>
          <button onClick={() => navigate('/claims')} className="text-sm text-ink-faint hover:text-ink-muted mb-2 flex items-center gap-1">
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" /></svg>
            Back to Claims
          </button>
          <div className="flex items-center gap-3">
            <h1 className="text-2xl font-bold font-heading text-ink">{claim.claim_number}</h1>
            <span className="px-2.5 py-1 rounded-lg bg-status-info-bg text-primary text-xs font-semibold">{claim.claim_type}</span>
            <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold border ${statusColor}`}>{claim.status}</span>
          </div>
          {/* Policy action term the claim was registered against (mirrors
              graphiteBWV8's "Action Term" header badge). */}
          {claim.policy_action && (
            <div className="mt-2 flex items-center gap-2">
              {/* Deleted terms turn the badge red so the operator immediately
                  sees the term the claim was filed against no longer exists. */}
              <span className={`inline-flex items-center px-3 py-1 rounded-md text-white text-xs font-semibold ${claim.policy_action.deleted ? 'bg-status-danger-fg' : 'bg-primary'}`}>
                Action Term: {claim.policy_action.transaction_type} - {claim.policy_action.status}
                {(claim.policy_action.effective_from || claim.policy_action.effective_to) && (
                  <> ({claim.policy_action.effective_from ? new Date(claim.policy_action.effective_from).toLocaleDateString('en-GB') : '—'}
                  {' - '}
                  {claim.policy_action.effective_to ? new Date(claim.policy_action.effective_to).toLocaleDateString('en-GB') : '—'})</>
                )}
              </span>
              {claim.policy_action.deleted && (
                <span className="inline-flex items-center px-2 py-1 rounded-md bg-status-danger-bg text-status-danger-fg text-xs font-semibold border border-status-danger-fg">
                  Deleted
                </span>
              )}
            </div>
          )}
        </div>
        <div className="flex gap-2 mt-4">
          {/* Edit Claim button hidden per UX request — leave block here for quick re-enable.
          {isEditable && (
            <button onClick={openEditModal}
              className="px-4 py-2 bg-ink text-white rounded-lg hover:bg-ink text-sm font-medium shadow-sm">
              Edit Claim
            </button>
          )}
          */}
          <button onClick={loadEditHistory}
            className="px-4 py-2 border border-line text-ink-muted rounded-lg hover:bg-surface-2 text-sm font-medium">
            Edit History
          </button>
          {canEditClaims && allowedStatuses.length > 0 && (
            <button onClick={() => { setNewStatus(claim.status ?? ''); setSubStatus(claim.claim_sub_status ?? ''); setStatusError(''); setShowStatusModal(true) }}
              className="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary text-sm font-medium shadow-sm">
              Update Status
            </button>
          )}
          {/* Change Policy button hidden per UX request — leave block here for quick re-enable.
          {isEditable && (
            <button onClick={async () => {
              const target = window.prompt('Re-link this claim to which policy number?', claim?.policy?.policy_number || '')
              if (!target) return
              try {
                const r = await apiClient.post(`/claims/${claim.id}/relink-policy`, { policy_number: target })
                toast.success(r.data?.message || 'Re-linked.')
                window.location.reload()
              } catch (e: any) { toast.error(e?.response?.data?.error || 'Failed to relink') }
            }}
              className="px-4 py-2 border border-status-warning-fg text-status-warning-fg rounded-lg hover:bg-status-warning-bg text-sm font-medium">
              Change Policy
            </button>
          )}
          */}
        </div>
      </div>

      {/* ── Claim Status Details (matches graphiteBWV8) ── */}
      <div className="bg-surface border border-line rounded-xl shadow-sm overflow-hidden">
        <div className="bg-gradient-to-r from-status-info-bg to-status-info-bg px-5 py-2.5 border-b border-line">
          <h3 className="text-sm font-bold text-ink">Claim Status Details</h3>
        </div>
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-6 gap-y-3 px-5 py-4 text-sm">
          <div>
            <p className="text-xs text-ink-faint uppercase tracking-wide">Total Reserve Amount</p>
            <p className="font-semibold text-primary mt-0.5">{fmt(claim.total_reserve)}</p>
          </div>
          <div>
            <p className="text-xs text-ink-faint uppercase tracking-wide">Total Payment Amount</p>
            <p className="font-semibold text-status-success-fg mt-0.5">{fmt(claim.total_payment)}</p>
          </div>
          <div>
            <p className="text-xs text-ink-faint uppercase tracking-wide">Claim Status</p>
            <p className="mt-0.5">
              <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium border ${STATUS_COLORS[claim.status] ?? 'bg-surface-2 text-ink-muted border-line'}`}>{claim.status}</span>
            </p>
          </div>
          {claim.claim_sub_status && (
            <div>
              <p className="text-xs text-ink-faint uppercase tracking-wide">Sub Status</p>
              <p className="font-semibold text-ink mt-0.5">{claim.claim_sub_status}</p>
            </div>
          )}
        </div>
        <div className="grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-2 px-5 pb-4 text-sm border-t border-line pt-3">
          <div><span className="text-ink-faint">Date of Loss:</span> <span className="font-medium text-ink">{fmtDate(claim.date_of_loss)}</span></div>
          <div><span className="text-ink-faint">Balance:</span> <span className="font-medium text-ink">{fmt(claim.balance)}</span></div>
          <div><span className="text-ink-faint">Created By:</span> <span className="font-medium text-ink">{claim.created_by_name || (claim.created_by ? `User #${claim.created_by}` : '-')}</span></div>
          <div><span className="text-ink-faint">Claim Allocated To:</span> <span className="font-medium text-ink">{(claim as any).allocated_to || ((claim as any).claim_allocated_to ? `User #${(claim as any).claim_allocated_to}` : '-')}</span></div>
          <div><span className="text-ink-faint">Claim Allocated On:</span> <span className="font-medium text-ink">{fmtDate((claim as any).claim_allocated_on || (claim as any).allocated_on) || '-'}</span></div>
        </div>
        {/* Send the claimant their form. The component renders its own wrapper
            and returns null until the `claims_form_dispatch` flag is armed — so
            while dark there is no stray divider or padding on the claim page. */}
        <SendClaimFormButton claimId={claim.id} claimNumber={claim.claim_number} />
      </div>

      {/* ── Tabs ── */}
      <div className="sticky top-0 z-10 bg-surface border-b border-line -mx-1 px-1">
        <nav className="flex gap-1 overflow-x-auto">
          {visibleTabs.map(t => (
            <button key={t.key} onClick={() => setActiveTab(t.key)}
              className={`px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 transition-colors ${
                activeTab === t.key
                  ? 'border-primary text-primary'
                  : 'border-transparent text-ink-faint hover:text-ink-muted hover:border-line'
              }`}>
              {/* Union legal claims (BONU / BOWASEWU) appoint a Lawyer, so the
                  Assessor tab is relabelled "Lawyer" for them. */}
              {t.key === 'assessor' && claim?.unionLegalClaim ? 'Lawyer' : t.label}
            </button>
          ))}
        </nav>
      </div>

      {/* ── Tab content ── */}
      <div className="pb-8">
        {activeTab === 'policy'      && <TabPolicyDetails c={claim} />}
        {activeTab === 'details'     && <TabClaimDetails c={claim} editable={!!isEditable} onRefresh={refetch} />}
        {activeTab === 'assessor'    && <TabAssessor claimId={Number(id)} editable={!!isEditable} />}
        {activeTab === 'attachments' && <TabAttachments claimId={Number(id)} attachments={claim.attachments} editable={canEditClaims} onRefresh={refetch} />}
        {activeTab === 'reserves'    && <TabReserves claimId={Number(id)} claimNumber={claim.claim_number} reserves={claim.reserves} totalReserve={claim.total_reserve} totalPayment={claim.total_payment} balance={claim.balance} editable={!!isEditable} onRefresh={refetch} />}
        {activeTab === 'suppliers'   && <TabSuppliers claimId={Number(id)} quotes={claim.quotes} editable={!!isEditable} onRefresh={refetch} />}
        {activeTab === 'invoice'     && <TabInvoice quotes={claim.quotes} reserves={claim.reserves} />}
        {activeTab === 'reinsurance' && <TabReinsurance claimId={Number(id)} />}
        {activeTab === 'activity'    && <TabActivityLog log={claim.activityLog} />}
        {activeTab === 'complaints'  && <TabComplaints claimId={Number(id)} policyId={claim.policy?.id ?? null} complaints={claim.complaints} editable={!!isEditable} onRefresh={refetch} />}
        {activeTab === 'reviewNotes' && <TabReviewNotes claimId={Number(id)} editable={!!isEditable} />}
        {activeTab === 'sla' && slaCanSee && <ClaimSlaTab claimId={Number(id)} editable={slaCanEdit} />}
        {activeTab === 'decision' && decisionCanSee && <ClaimDecisionTab claimId={Number(id)} />}
        {activeTab === 'purchaseOrders' && poCanSee && <ClaimPurchaseOrdersTab claimId={Number(id)} />}
      </div>

      {/* ── Edit Claim Modal ── */}
      {showEditModal && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 py-4 pt-10 overflow-y-auto" onClick={() => setShowEditModal(false)}>
          <div className="bg-surface rounded-xl shadow-2xl w-full max-w-3xl mx-4 p-5 space-y-2.5 max-h-[85vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
            <h2 className="text-lg font-bold">Edit Claim — {claim.claim_number}</h2>
            <p className="text-xs text-ink-faint">Changes are tracked. Previous values are stored for audit.</p>
            <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
              <div><label className="block text-xs font-medium text-ink-faint mb-1">Claim Type</label>
                <select value={editForm.claim_type} onChange={e => setEditForm({...editForm, claim_type: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                  {(editLookups?.claim_types ?? []).map((t: any) => <option key={t.id} value={t.id}>{t.name}</option>)}
                  {!editLookups?.claim_types?.length && <option value={editForm.claim_type}>{editForm.claim_type}</option>}
                </select></div>
              <div><label className="block text-xs font-medium text-ink-faint mb-1">Claim Sub-Type</label>
                <select value={editForm.claim_sub_type} onChange={e => setEditForm({...editForm, claim_sub_type: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                  <option value="">—</option>
                  {(editLookups?.claim_sub_types ?? []).map((t: any) => <option key={t.id} value={t.name}>{t.name}</option>)}
                </select></div>
              <div><label className="block text-xs font-medium text-ink-faint mb-1">Category</label>
                <input value={editForm.category} onChange={e => setEditForm({...editForm, category: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>

              <div><label className="block text-xs font-medium text-ink-faint mb-1">Incident Date</label>
                <input type="date" value={editForm.incident_date} onChange={e => setEditForm({...editForm, incident_date: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
              <div><label className="block text-xs font-medium text-ink-faint mb-1">Incident Time</label>
                <input value={editForm.incident_time} onChange={e => setEditForm({...editForm, incident_time: e.target.value})} placeholder="HH:MM" className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
              <div><label className="block text-xs font-medium text-ink-faint mb-1">Registered Claim</label>
                <input value={editForm.registered_claim} onChange={e => setEditForm({...editForm, registered_claim: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>

              <div className="col-span-2 md:col-span-3"><label className="block text-xs font-medium text-ink-faint mb-1">Incident Location</label>
                <input value={editForm.incident_location} onChange={e => setEditForm({...editForm, incident_location: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
              <div className="col-span-2 md:col-span-3"><label className="block text-xs font-medium text-ink-faint mb-1">Description</label>
                <textarea value={editForm.incident_description} onChange={e => setEditForm({...editForm, incident_description: e.target.value})} rows={3} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>

              <div><label className="block text-xs font-medium text-ink-faint mb-1">Type of Loss</label>
                <select value={editForm.type_of_loss} onChange={e => setEditForm({...editForm, type_of_loss: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                  <option value="">—</option>
                  {(editLookups?.loss_types ?? []).map((t: any) => <option key={t.id} value={t.id}>{t.name}</option>)}
                </select></div>
              <div><label className="block text-xs font-medium text-ink-faint mb-1">Event Name</label>
                <select value={editForm.event_name} onChange={e => setEditForm({...editForm, event_name: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                  <option value="">—</option>
                  {(editLookups?.event_names ?? []).map((t: any) => <option key={t.id} value={t.name}>{t.name}</option>)}
                </select></div>
              <div><label className="block text-xs font-medium text-ink-faint mb-1">Weather</label>
                <select value={editForm.weather_condition} onChange={e => setEditForm({...editForm, weather_condition: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                  <option value="">—</option>
                  {(editLookups?.weather_conditions ?? []).map((t: any) => <option key={t.id} value={t.id}>{t.name}</option>)}
                </select></div>

              <div><label className="block text-xs font-medium text-ink-faint mb-1">Fault Party</label>
                <select value={editForm.fault_party} onChange={e => setEditForm({...editForm, fault_party: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                  <option value="">—</option>
                  {(editLookups?.fault_parties ?? []).map((t: any) => <option key={t.id} value={t.id}>{t.name}</option>)}
                </select></div>
              <div><label className="block text-xs font-medium text-ink-faint mb-1">Is Motor Claim</label>
                <select value={editForm.is_motor_claim} onChange={e => setEditForm({...editForm, is_motor_claim: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                  <option value="0">No</option><option value="1">Yes</option>
                </select></div>
              <div><label className="block text-xs font-medium text-ink-faint mb-1">Vehicle Plate</label>
                {modalPlateOpts.length > 0 ? (
                  <select value={editForm.vehicle_plate || ''} onChange={e => setEditForm({...editForm, vehicle_plate: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                    <option value="">--</option>
                    {editForm.vehicle_plate && !modalPlateOpts.some(o => o.id === editForm.vehicle_plate) && (
                      <option value={editForm.vehicle_plate}>{editForm.vehicle_plate} (not on term)</option>
                    )}
                    {modalPlateOpts.map(o => <option key={o.id} value={o.id}>{o.name}</option>)}
                  </select>
                ) : (
                  <input value={editForm.vehicle_plate} onChange={e => setEditForm({...editForm, vehicle_plate: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" />
                )}</div>

              <div><label className="block text-xs font-medium text-ink-faint mb-1">Reported By</label>
                <input value={editForm.reported_by} onChange={e => setEditForm({...editForm, reported_by: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
              <div><label className="block text-xs font-medium text-ink-faint mb-1">Reported Date</label>
                <input type="date" value={editForm.reported_date} onChange={e => setEditForm({...editForm, reported_date: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
              <div><label className="block text-xs font-medium text-ink-faint mb-1">Claim Allocated To</label>
                <select value={editForm.claim_allocated_to} onChange={e => setEditForm({...editForm, claim_allocated_to: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                  <option value="">—</option>
                  {(editLookups?.internal_users ?? []).map((u: any) => <option key={u.id} value={u.id}>{u.name}</option>)}
                </select></div>
              <div><label className="block text-xs font-medium text-ink-faint mb-1">Claim Allocated On</label>
                <input type="date" value={editForm.claim_allocated_on} onChange={e => setEditForm({...editForm, claim_allocated_on: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
            </div>

            {/* Flags */}
            <div className="grid grid-cols-2 md:grid-cols-3 gap-2 pt-2 border-t border-line">
              {[
                ['attorney_involved', 'Attorney involved'],
                ['co_attorney_involved', 'Co-Attorney involved'],
                ['dfs_complaint', 'DFS complaint'],
                ['catastrophe_loss', 'Catastrophe loss'],
                ['recovery_involved', 'Recovery involved'],
                ['third_party_insured_elsewhere', '3rd-party insured elsewhere'],
                ['driver_as_insured', 'Driver = insured'],
              ].map(([k, label]) => (
                <label key={k} className="flex items-center gap-2 text-sm cursor-pointer">
                  <input type="checkbox" checked={editForm[k] === '1'} onChange={e => setEditForm({...editForm, [k]: e.target.checked ? '1' : '0'})} className="rounded" />
                  <span>{label}</span>
                </label>
              ))}
            </div>

            <div className="pt-2 border-t border-line"><label className="block text-xs font-medium text-ink-faint mb-1">Reason / Notes</label>
              <textarea value={editForm.reason} onChange={e => setEditForm({...editForm, reason: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" />
            </div>

            {/* ── Type-specific edit sections ─────────────────────────────
                Rendered per policy.form_template so Life / Legal /
                Hospital Cash / Motor / DOMG/COMG operators see the same
                fields they originally entered at create time. Mirrors the
                legacy per-type edit blades (admin.claims.life /
                admin.claims.legal / admin.claims.hospital_cash_edit /
                admin.claims.claim_details).
                See backend ClaimsController::update() for the validator
                that accepts these. */}
            {(() => {
              const ft = claim?.policy?.form_template
              const ctKey = (claim?.claim_type || '').toUpperCase().replace(/[^A-Z0-9]/g, '')

              // Life
              if (ft === 'life') return (
                <div className="pt-3 border-t border-line space-y-2">
                  <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide">Life Claim</div>
                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label className="block text-xs font-medium text-ink-faint mb-1">Date of Death</label>
                      <input type="date" value={editForm.date_of_death || ''}
                        onChange={e => setEditForm({...editForm, date_of_death: e.target.value})}
                        className="w-full px-3 py-2 border border-line rounded-md text-sm" />
                    </div>
                    <div>
                      <label className="block text-xs font-medium text-ink-faint mb-1">Cause of Death</label>
                      <input value={editForm.cause_of_death || ''}
                        onChange={e => setEditForm({...editForm, cause_of_death: e.target.value})}
                        className="w-full px-3 py-2 border border-line rounded-md text-sm" />
                    </div>
                    <div className="col-span-2">
                      <label className="block text-xs font-medium text-ink-faint mb-1">Description</label>
                      <textarea value={editForm.life_description || ''}
                        onChange={e => setEditForm({...editForm, life_description: e.target.value})}
                        rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" />
                    </div>
                  </div>
                </div>
              )

              // Legal
              if (ft === 'legal') return (
                <div className="pt-3 border-t border-line space-y-2">
                  <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide">Legal Claim</div>
                  <div className="grid grid-cols-2 gap-3">
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Legal Firm</label>
                      <input value={editForm.legal_firm || ''} onChange={e => setEditForm({...editForm, legal_firm: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Lawyer Name</label>
                      <input value={editForm.lawyer_name || ''} onChange={e => setEditForm({...editForm, lawyer_name: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Legal Tel</label>
                      <input value={editForm.legal_tel || ''} onChange={e => setEditForm({...editForm, legal_tel: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Legal Email</label>
                      <input type="email" value={editForm.legal_email || ''} onChange={e => setEditForm({...editForm, legal_email: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Member Name</label>
                      <input value={editForm.member_name || ''} onChange={e => setEditForm({...editForm, member_name: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Membership ID</label>
                      <input value={editForm.membership_id || ''} onChange={e => setEditForm({...editForm, membership_id: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Member Contact</label>
                      <input value={editForm.member_contact || ''} onChange={e => setEditForm({...editForm, member_contact: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Member Email</label>
                      <input type="email" value={editForm.member_email || ''} onChange={e => setEditForm({...editForm, member_email: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Loss Reported Date</label>
                      <input type="date" value={editForm.lossreported_date || ''} onChange={e => setEditForm({...editForm, lossreported_date: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Matter Relates To</label>
                      <input value={editForm.matter_relatesto || ''} onChange={e => setEditForm({...editForm, matter_relatesto: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Matter Quantum</label>
                      <input value={editForm.matter_quantum || ''} onChange={e => setEditForm({...editForm, matter_quantum: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Jurisdiction</label>
                      <input value={editForm.jurisdiction || ''} onChange={e => setEditForm({...editForm, jurisdiction: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div className="col-span-2">
                      <label className="block text-xs font-medium text-ink-faint mb-1">Course of Action</label>
                      <textarea value={editForm.course_of_action || ''} onChange={e => setEditForm({...editForm, course_of_action: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" />
                    </div>
                  </div>
                </div>
              )

              // Hospital Cash
              if (ft === 'hospital_cash') return (
                <div className="pt-3 border-t border-line space-y-2">
                  <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide">Hospital Cash Claim</div>
                  <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Patient Name</label>
                      <input value={editForm.patient_name || ''} onChange={e => setEditForm({...editForm, patient_name: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Patient DOB</label>
                      <input type="date" value={editForm.patient_dob || ''} onChange={e => setEditForm({...editForm, patient_dob: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Identity Number</label>
                      <input value={editForm.patient_identity_number || ''} onChange={e => setEditForm({...editForm, patient_identity_number: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Relationship</label>
                      <input value={editForm.relationship || ''} onChange={e => setEditForm({...editForm, relationship: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Hospital Name</label>
                      <input value={editForm.hospital_name || ''} onChange={e => setEditForm({...editForm, hospital_name: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Admitting Doctor</label>
                      <input value={editForm.admitting_doctor || ''} onChange={e => setEditForm({...editForm, admitting_doctor: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Admission Date</label>
                      <input type="date" value={editForm.admission_date || ''} onChange={e => setEditForm({...editForm, admission_date: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Admission Time</label>
                      <input value={editForm.admission_time || ''} onChange={e => setEditForm({...editForm, admission_time: e.target.value})} placeholder="HH:MM" className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Discharge Date</label>
                      <input type="date" value={editForm.discharge_date || ''} onChange={e => setEditForm({...editForm, discharge_date: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Discharge Time</label>
                      <input value={editForm.discharge_time || ''} onChange={e => setEditForm({...editForm, discharge_time: e.target.value})} placeholder="HH:MM" className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Hospitalisation Type</label>
                      <input value={editForm.hospitalisation_type || ''} onChange={e => setEditForm({...editForm, hospitalisation_type: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Injury Date</label>
                      <input type="date" value={editForm.injury_date || ''} onChange={e => setEditForm({...editForm, injury_date: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Medical Scheme</label>
                      <select value={editForm.is_medical_scheme || ''} onChange={e => setEditForm({...editForm, is_medical_scheme: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                        <option value="">—</option><option value="yes">Yes</option><option value="no">No</option>
                      </select></div>
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Medical Scheme Name</label>
                      <input value={editForm.medical_scheme_name || ''} onChange={e => setEditForm({...editForm, medical_scheme_name: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Medical Aid Number</label>
                      <input value={editForm.medical_aid_number || ''} onChange={e => setEditForm({...editForm, medical_aid_number: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div className="col-span-2 md:col-span-3">
                      <label className="block text-xs font-medium text-ink-faint mb-1">Accident Circumstances</label>
                      <textarea value={editForm.accident_circumstances || ''} onChange={e => setEditForm({...editForm, accident_circumstances: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" />
                    </div>
                  </div>
                </div>
              )

              // Motor (vehicle form_template) — accident + driver + passenger
              // + third-party repeaters. Mirrors legacy claim_details.blade
              // which dispatches on claim_type for the Accident flow.
              if (ft === 'vehicle') return (
                <div className="pt-3 border-t border-line space-y-4">
                  <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide">Accident Details</div>
                  <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Date of Accident</label>
                      <input type="date" value={editAccident.date_of_accident || ''} onChange={e => setEditAccident({...editAccident, date_of_accident: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Time</label>
                      <input value={editAccident.time_of_accident || ''} onChange={e => setEditAccident({...editAccident, time_of_accident: e.target.value})} placeholder="HH:MM" className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Place of Accident</label>
                      <input value={editAccident.place_of_accident || ''} onChange={e => setEditAccident({...editAccident, place_of_accident: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Purpose of Trip</label>
                      <input value={editAccident.purpose_of_trip || ''} onChange={e => setEditAccident({...editAccident, purpose_of_trip: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Fault Party</label>
                      <input value={editAccident.fault_party || ''} onChange={e => setEditAccident({...editAccident, fault_party: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div className="flex items-end gap-2">
                      <label className="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" checked={editAccident.third_party === '1'} onChange={e => setEditAccident({...editAccident, third_party: e.target.checked ? '1' : '0'})} className="rounded" />
                        <span>Other party involved</span>
                      </label>
                    </div>
                    <div className="col-span-2 md:col-span-3">
                      <label className="block text-xs font-medium text-ink-faint mb-1">Accident Details</label>
                      <textarea value={editAccident.detail_of_accident || ''} onChange={e => setEditAccident({...editAccident, detail_of_accident: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" />
                    </div>
                  </div>

                  <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide">Driver</div>
                  <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Name</label>
                      <input value={editDriver.name || ''} onChange={e => setEditDriver({...editDriver, name: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">DOB</label>
                      <input type="date" value={editDriver.dob || ''} onChange={e => setEditDriver({...editDriver, dob: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Mobile</label>
                      <input value={editDriver.cellphone || ''} onChange={e => setEditDriver({...editDriver, cellphone: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Address</label>
                      <input value={editDriver.address || ''} onChange={e => setEditDriver({...editDriver, address: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">License</label>
                      <input value={editDriver.license || ''} onChange={e => setEditDriver({...editDriver, license: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <div className="col-span-2 md:col-span-3"><label className="block text-xs font-medium text-ink-faint mb-1">Purpose</label>
                      <input value={editDriver.purpose || ''} onChange={e => setEditDriver({...editDriver, purpose: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                  </div>

                  <div className="flex items-center justify-between">
                    <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide">Passenger Injuries</div>
                    <button type="button" onClick={() => setEditPassengers([...editPassengers, { name: '', address: '', injury: '' }])} className="text-xs text-primary hover:underline">+ Add passenger</button>
                  </div>
                  {editPassengers.length === 0 && <p className="text-xs text-ink-faint">No passenger rows.</p>}
                  {editPassengers.map((p, i) => (
                    <div key={i} className="grid grid-cols-2 md:grid-cols-4 gap-2 p-3 bg-surface-2 rounded-md">
                      <div className="md:col-span-1"><input placeholder="Name" value={p.name} onChange={e => setEditPassengers(editPassengers.map((x, xi) => xi === i ? {...x, name: e.target.value} : x))} className="w-full px-2 py-1 border border-line rounded text-sm" /></div>
                      <div className="md:col-span-2"><input placeholder="Address" value={p.address} onChange={e => setEditPassengers(editPassengers.map((x, xi) => xi === i ? {...x, address: e.target.value} : x))} className="w-full px-2 py-1 border border-line rounded text-sm" /></div>
                      <div className="flex gap-1 md:col-span-1">
                        <input placeholder="Injury" value={p.injury} onChange={e => setEditPassengers(editPassengers.map((x, xi) => xi === i ? {...x, injury: e.target.value} : x))} className="flex-1 px-2 py-1 border border-line rounded text-sm" />
                        <button type="button" onClick={() => setEditPassengers(editPassengers.filter((_, xi) => xi !== i))} className="text-status-danger-fg hover:text-status-danger-fg text-xs px-2" title="Remove">✕</button>
                      </div>
                    </div>
                  ))}

                  <div className="flex items-center justify-between">
                    <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide">Third Parties</div>
                    <button type="button" onClick={() => setEditThirdParties([...editThirdParties, { first_name:'', last_name:'', cellphone:'', address:'', make:'', model:'', registration_no:'', damage_details:'', injured_name:'', relationship:'', hospital_name:'', injured_details:'' }])} className="text-xs text-primary hover:underline">+ Add third party</button>
                  </div>
                  {editThirdParties.length === 0 && <p className="text-xs text-ink-faint">No third-party rows.</p>}
                  {editThirdParties.map((tp, i) => (
                    <div key={i} className="p-3 bg-surface-2 rounded-md space-y-2">
                      <div className="flex justify-end">
                        <button type="button" onClick={() => setEditThirdParties(editThirdParties.filter((_, xi) => xi !== i))} className="text-status-danger-fg hover:text-status-danger-fg text-xs">Remove</button>
                      </div>
                      <div className="grid grid-cols-2 md:grid-cols-3 gap-2">
                        <input placeholder="First Name" value={tp.first_name} onChange={e => setEditThirdParties(editThirdParties.map((x, xi) => xi === i ? {...x, first_name: e.target.value} : x))} className="px-2 py-1 border border-line rounded text-sm" />
                        <input placeholder="Last Name" value={tp.last_name} onChange={e => setEditThirdParties(editThirdParties.map((x, xi) => xi === i ? {...x, last_name: e.target.value} : x))} className="px-2 py-1 border border-line rounded text-sm" />
                        <input placeholder="Mobile" value={tp.cellphone} onChange={e => setEditThirdParties(editThirdParties.map((x, xi) => xi === i ? {...x, cellphone: e.target.value} : x))} className="px-2 py-1 border border-line rounded text-sm" />
                        <input placeholder="Address" value={tp.address} onChange={e => setEditThirdParties(editThirdParties.map((x, xi) => xi === i ? {...x, address: e.target.value} : x))} className="px-2 py-1 border border-line rounded text-sm md:col-span-2" />
                        <input placeholder="Make" value={tp.make} onChange={e => setEditThirdParties(editThirdParties.map((x, xi) => xi === i ? {...x, make: e.target.value} : x))} className="px-2 py-1 border border-line rounded text-sm" />
                        <input placeholder="Model" value={tp.model} onChange={e => setEditThirdParties(editThirdParties.map((x, xi) => xi === i ? {...x, model: e.target.value} : x))} className="px-2 py-1 border border-line rounded text-sm" />
                        <input placeholder="Reg Number" value={tp.registration_no} onChange={e => setEditThirdParties(editThirdParties.map((x, xi) => xi === i ? {...x, registration_no: e.target.value} : x))} className="px-2 py-1 border border-line rounded text-sm" />
                        <input placeholder="Damage Details" value={tp.damage_details} onChange={e => setEditThirdParties(editThirdParties.map((x, xi) => xi === i ? {...x, damage_details: e.target.value} : x))} className="px-2 py-1 border border-line rounded text-sm md:col-span-3" />
                        <input placeholder="Injured Name" value={tp.injured_name} onChange={e => setEditThirdParties(editThirdParties.map((x, xi) => xi === i ? {...x, injured_name: e.target.value} : x))} className="px-2 py-1 border border-line rounded text-sm" />
                        <input placeholder="Relationship" value={tp.relationship} onChange={e => setEditThirdParties(editThirdParties.map((x, xi) => xi === i ? {...x, relationship: e.target.value} : x))} className="px-2 py-1 border border-line rounded text-sm" />
                        <input placeholder="Hospital" value={tp.hospital_name} onChange={e => setEditThirdParties(editThirdParties.map((x, xi) => xi === i ? {...x, hospital_name: e.target.value} : x))} className="px-2 py-1 border border-line rounded text-sm" />
                        <input placeholder="Injury details" value={tp.injured_details} onChange={e => setEditThirdParties(editThirdParties.map((x, xi) => xi === i ? {...x, injured_details: e.target.value} : x))} className="px-2 py-1 border border-line rounded text-sm md:col-span-3" />
                      </div>
                    </div>
                  ))}
                </div>
              )

              // DOMG / COMG — claim-type-driven sub-claim section. Dispatches
              // on claim_type (normalized to compact key) so the right
              // sub-table fields appear. Matches the create-flow dispatch.
              if (ft === 'coverage_based') {
                const SectionHeader: React.FC<{ label: string }> = ({ label }) => (
                  <div className="text-xs font-semibold text-ink-muted uppercase tracking-wide pt-3 border-t border-line">{label}</div>
                )
                if (ctKey === 'BUSINESSINTERRUPTION') return (
                  <>
                    <SectionHeader label="Business Interruption" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Have you previously suffered loss/damage?</label>
                        <select value={editForm.previously_suffered_loss ?? ''} onChange={e => setEditForm({...editForm, previously_suffered_loss: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm"><option value="">—</option><option value="1">Yes</option><option value="0">No</option></select></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Any other party with an interest in the insured property?</label>
                        <input value={editForm.other_party_interest || ''} onChange={e => setEditForm({...editForm, other_party_interest: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Any other insurance covering this loss/damage?</label>
                        <select value={editForm.other_insurance_covering ?? ''} onChange={e => setEditForm({...editForm, other_insurance_covering: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm"><option value="">—</option><option value="1">Yes</option><option value="0">No</option></select></div>
                    </div>
                  </>
                )
                if (ctKey === 'BUSINESSALLRISKS' || ctKey === 'PERSONALALLRISKS' || ctKey === 'ELECTRONICEQUIPMENT' || ctKey === 'ALLRISK') return (
                  <>
                    <SectionHeader label="All Risks Details" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Has the property been stolen or damaged?</label>
                        <select value={editForm.property_stolen_damaged ?? ''} onChange={e => setEditForm({...editForm, property_stolen_damaged: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm"><option value="">—</option><option value="1">Damaged</option><option value="0">Stolen</option></select></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Have you ever before sustained previous loss?</label>
                        <select value={editForm.loss_cause ?? ''} onChange={e => setEditForm({...editForm, loss_cause: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm"><option value="">—</option><option value="1">Loss by theft</option><option value="0">Loss by other cause</option></select></div>
                      {/* V8 #otherCauseInput — only when "other cause" picked */}
                      {String(editForm.loss_cause) === '0' && (
                        <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Other cause (please provide details)</label>
                          <input value={editForm.loss_by_other_cause || ''} onChange={e => setEditForm({...editForm, loss_by_other_cause: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      )}
                      {/* V8 #classStolen — only when property is Stolen */}
                      {String(editForm.property_stolen_damaged) === '0' && (
                        <>
                          <div><label className="block text-xs font-medium text-ink-faint mb-1">Was the property stolen from a car or unlocked premises?</label>
                            <select value={editForm.stolenfromcar_unlockedpremises ?? ''} onChange={e => setEditForm({...editForm, stolenfromcar_unlockedpremises: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm"><option value="">—</option><option value="1">Yes</option><option value="0">No</option></select></div>
                          <div><label className="block text-xs font-medium text-ink-faint mb-1">Has a thorough search been made for the article(s)?</label>
                            <select value={editForm.thorough_search_made_for_article ?? ''} onChange={e => setEditForm({...editForm, thorough_search_made_for_article: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm"><option value="">—</option><option value="1">Yes</option><option value="0">No</option></select></div>
                          <div><label className="block text-xs font-medium text-ink-faint mb-1">Are you the sole owner of the property?</label>
                            <select value={editForm.is_sole_owner_of_property ?? ''} onChange={e => setEditForm({...editForm, is_sole_owner_of_property: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm"><option value="">—</option><option value="1">Yes</option><option value="0">No</option></select></div>
                          {String(editForm.is_sole_owner_of_property) === '0' && (
                            <div><label className="block text-xs font-medium text-ink-faint mb-1">Name of the owner</label>
                              <input value={editForm.sole_owner_of_property || ''} onChange={e => setEditForm({...editForm, sole_owner_of_property: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                          )}
                        </>
                      )}
                    </div>
                  </>
                )
                if (ctKey === 'BURGLARY' || ctKey === 'THEFT' || ctKey === 'BURGLARYTHEFT' || ctKey === 'MONEY') return (
                  <>
                    <SectionHeader label="Burglary / Theft" />
                    <div className="grid grid-cols-2 gap-3">
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Address of Premises</label>
                        <input value={editForm.address_of_premises || ''} onChange={e => setEditForm({...editForm, address_of_premises: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Description of Incident</label>
                        <textarea value={editForm.description_of_incident || ''} onChange={e => setEditForm({...editForm, description_of_incident: e.target.value})} rows={3} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Date/Time Police Advised</label>
                        <input type="datetime-local" value={editForm.date_time_police_advised || ''} onChange={e => setEditForm({...editForm, date_time_police_advised: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Total Value of Contents</label>
                        <input type="number" value={editForm.total_value_contents_of_premises || ''} onChange={e => setEditForm({...editForm, total_value_contents_of_premises: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                  </>
                )
                if (ctKey === 'FIDELITYGUARANTEE') return (
                  <>
                    <SectionHeader label="Fidelity Guarantee" />
                    <div className="grid grid-cols-1 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Defaulting Employees (names & positions)</label>
                        <textarea value={editForm.defaulting_employees_name || ''} onChange={e => setEditForm({...editForm, defaulting_employees_name: e.target.value})} rows={3} className="w-full px-3 py-2 border border-line rounded-md text-sm" placeholder="John Doe — Cashier&#10;Jane Smith — Accountant" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Have the employees been involved in or suspected of any previous loss?</label>
                        <select value={String(editForm.employees_been_involved ?? '')} onChange={e => setEditForm({...editForm, employees_been_involved: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm"><option value="">—</option><option value="1">Yes</option><option value="0">No</option></select></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Full details of circumstances of the loss and how it was discovered</label>
                        <textarea value={editForm.circumstances || ''} onChange={e => setEditForm({...editForm, circumstances: e.target.value})} rows={4} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                  </>
                )
                if (ctKey === 'ERECTIONALLRISK') return (
                  <>
                    <SectionHeader label="Erection All Risk — A. Details of Insured" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Occupation of the Insured</label>
                        <input value={editForm.insured_occupation || ''} onChange={e => setEditForm({...editForm, insured_occupation: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Name of Supervisor Engineer</label>
                        <input value={editForm.supervisor_engineer_name || ''} onChange={e => setEditForm({...editForm, supervisor_engineer_name: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Period of Insurance: From</label>
                        <input type="date" value={editForm.period_from || ''} onChange={e => setEditForm({...editForm, period_from: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Period of Insurance: To</label>
                        <input type="date" value={editForm.period_to || ''} onChange={e => setEditForm({...editForm, period_to: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="B. Particulars of Accident" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Date of Occurrence</label>
                        <input type="date" value={editForm.date_of_occurrence || ''} onChange={e => setEditForm({...editForm, date_of_occurrence: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Time of Occurrence</label>
                        <input type="time" value={editForm.time_of_occurrence || ''} onChange={e => setEditForm({...editForm, time_of_occurrence: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Site where damage occurred</label>
                        <textarea value={editForm.site_of_damage || ''} onChange={e => setEditForm({...editForm, site_of_damage: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Nearest Railway Station</label>
                        <input value={editForm.nearest_railway_station || ''} onChange={e => setEditForm({...editForm, nearest_railway_station: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">a) Contract Works</label>
                        <textarea value={editForm.damage_contract_works || ''} onChange={e => setEditForm({...editForm, damage_contract_works: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">b) Construction Plant &amp; Equipment</label>
                        <textarea value={editForm.damage_plant_equipment || ''} onChange={e => setEditForm({...editForm, damage_plant_equipment: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">c) Property belonging to Third Parties</label>
                        <textarea value={editForm.damage_third_party_property || ''} onChange={e => setEditForm({...editForm, damage_third_party_property: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Cause of Damage</label>
                        <textarea value={editForm.cause_of_damage || ''} onChange={e => setEditForm({...editForm, cause_of_damage: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Is anyone responsible for the damage?</label>
                        <select value={String(editForm.responsible_for_damage ?? '')} onChange={e => setEditForm({...editForm, responsible_for_damage: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      {String(editForm.responsible_for_damage) === '1' && (
                        <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Provide details</label>
                          <textarea value={editForm.responsible_for_damage_details || ''} onChange={e => setEditForm({...editForm, responsible_for_damage_details: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      )}
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Is there any possibility of recovery?</label>
                        <select value={String(editForm.possibility_of_recovery ?? '')} onChange={e => setEditForm({...editForm, possibility_of_recovery: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      {String(editForm.possibility_of_recovery) === '1' && (
                        <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Recovery Details</label>
                          <textarea value={editForm.recovery_details || ''} onChange={e => setEditForm({...editForm, recovery_details: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      )}
                    </div>
                    <SectionHeader label="C. Details of the Damaged Section / Works" />
                    <div className="grid grid-cols-2 gap-3">
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">How did the damage occur?</label>
                        <textarea value={editForm.how_damage_occurred || ''} onChange={e => setEditForm({...editForm, how_damage_occurred: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">What was its probable cause?</label>
                        <textarea value={editForm.probable_cause || ''} onChange={e => setEditForm({...editForm, probable_cause: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Progress of construction at time of damage</label>
                        <textarea value={editForm.progress_of_construction || ''} onChange={e => setEditForm({...editForm, progress_of_construction: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">How will the damaged items be repaired?</label>
                        <textarea value={editForm.how_items_repaired || ''} onChange={e => setEditForm({...editForm, how_items_repaired: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Will alterations / improvements be made during repairs?</label>
                        <select value={String(editForm.alterations_during_repairs ?? '')} onChange={e => setEditForm({...editForm, alterations_during_repairs: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Name of Witness</label>
                        <input value={editForm.witness_name || ''} onChange={e => setEditForm({...editForm, witness_name: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Address of Witness</label>
                        <textarea value={editForm.witness_address || ''} onChange={e => setEditForm({...editForm, witness_address: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Are existing buildings / surrounding properties damaged?</label>
                        <select value={String(editForm.surrounding_properties_damaged ?? '')} onChange={e => setEditForm({...editForm, surrounding_properties_damaged: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Is Third Party Liability involved?</label>
                        <select value={String(editForm.third_party_liability ?? '')} onChange={e => setEditForm({...editForm, third_party_liability: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      {String(editForm.third_party_liability) === '1' && (
                        <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Third Party Liability Details</label>
                          <textarea value={editForm.third_party_liability_details || ''} onChange={e => setEditForm({...editForm, third_party_liability_details: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      )}
                    </div>
                    <SectionHeader label="Estimated Costs for Repair of Damage" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">a) Contract Works (BWP)</label>
                        <input type="number" value={editForm.estimated_cost_contract_works || ''} onChange={e => setEditForm({...editForm, estimated_cost_contract_works: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">b) Construction Plant &amp; Machinery (BWP)</label>
                        <input type="number" value={editForm.estimated_cost_plant_machinery || ''} onChange={e => setEditForm({...editForm, estimated_cost_plant_machinery: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">c) Third Party Property (BWP)</label>
                        <input type="number" value={editForm.estimated_cost_third_party_property || ''} onChange={e => setEditForm({...editForm, estimated_cost_third_party_property: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">d) Owner's Surrounding Property (BWP)</label>
                        <input type="number" value={editForm.estimated_cost_owners_surrounding || ''} onChange={e => setEditForm({...editForm, estimated_cost_owners_surrounding: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="D. Other Insurances" />
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Provide details of other Insurance covering the present loss</label>
                      <textarea value={editForm.other_insurance_details || ''} onChange={e => setEditForm({...editForm, other_insurance_details: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    <SectionHeader label="E. Previous Losses" />
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">Provide details of previous Claims, if any, on the project</label>
                      <textarea value={editForm.previous_losses_details || ''} onChange={e => setEditForm({...editForm, previous_losses_details: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                  </>
                )
                if (ctKey === 'TRAVELINSURANCE') return (
                  <>
                    {/* Travel Insurance — V8 parity. Global Edit Claim
                        modal does NOT render the 25 file inputs; those
                        are handled by the per-section sub-claim Edit
                        flow above (FileRow with "View Uploaded Document"
                        link). Here we only ship the text fields. */}
                    <SectionHeader label="Travel Insurance — Claimant Details" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Title</label>
                        <select value={editForm.title || ''} onChange={e => setEditForm({...editForm, title: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option>
                          <option value="Mr">Mr</option>
                          <option value="Mrs">Mrs</option>
                          <option value="Miss">Miss</option>
                          <option value="Ms">Ms</option>
                          <option value="Other">Other</option>
                        </select></div>
                      {String(editForm.title) === 'Other' && (
                        <div><label className="block text-xs font-medium text-ink-faint mb-1">Other title</label>
                          <input value={editForm.other_title || ''} onChange={e => setEditForm({...editForm, other_title: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      )}
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Surname</label>
                        <input value={editForm.surname || ''} onChange={e => setEditForm({...editForm, surname: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Forename(s)</label>
                        <input value={editForm.forename || ''} onChange={e => setEditForm({...editForm, forename: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Date of Birth</label>
                        <input type="date" value={editForm.dob || ''} onChange={e => setEditForm({...editForm, dob: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Passport No</label>
                        <input value={editForm.passport_no || ''} onChange={e => setEditForm({...editForm, passport_no: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Nationality</label>
                        <input value={editForm.nationality || ''} onChange={e => setEditForm({...editForm, nationality: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Telephone</label>
                        <input value={editForm.telephone || ''} onChange={e => setEditForm({...editForm, telephone: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Post Code</label>
                        <input value={editForm.post_code || ''} onChange={e => setEditForm({...editForm, post_code: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Mobile</label>
                        <input value={editForm.mobile || ''} onChange={e => setEditForm({...editForm, mobile: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Email</label>
                        <input type="email" value={editForm.email || ''} onChange={e => setEditForm({...editForm, email: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Home Address</label>
                        <textarea value={editForm.home_address || ''} onChange={e => setEditForm({...editForm, home_address: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Travel Insurance Policy and Journey Details" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Policy Number</label>
                        <input value={editForm.policy_number || ''} onChange={e => setEditForm({...editForm, policy_number: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Issued by (Insurance Company)</label>
                        <input value={editForm.issued_by || ''} onChange={e => setEditForm({...editForm, issued_by: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Issued on</label>
                        <input value={editForm.issued_on || ''} onChange={e => setEditForm({...editForm, issued_on: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Valid from</label>
                        <input type="date" value={editForm.valid_from || ''} onChange={e => setEditForm({...editForm, valid_from: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Valid to</label>
                        <input type="date" value={editForm.valid_to || ''} onChange={e => setEditForm({...editForm, valid_to: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Bank Details (for Claim Reimbursement)" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Beneficiary</label>
                        <input value={editForm.beneficiary || ''} onChange={e => setEditForm({...editForm, beneficiary: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Bank Name</label>
                        <input value={editForm.bank_name || ''} onChange={e => setEditForm({...editForm, bank_name: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Bank Address</label>
                        <input value={editForm.bank_address || ''} onChange={e => setEditForm({...editForm, bank_address: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Account Number</label>
                        <input value={editForm.account_number || ''} onChange={e => setEditForm({...editForm, account_number: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">IBAN</label>
                        <input value={editForm.iban || ''} onChange={e => setEditForm({...editForm, iban: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">SWIFT Code</label>
                        <input value={editForm.swift_code || ''} onChange={e => setEditForm({...editForm, swift_code: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">BIC Code</label>
                        <input value={editForm.bic_code || ''} onChange={e => setEditForm({...editForm, bic_code: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Do you have any other Insurance Policy?" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Other Insurance Policy</label>
                        <select value={editForm.other_insurance_policy || ''} onChange={e => setEditForm({...editForm, other_insurance_policy: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option>
                          <option value="No">No</option>
                          <option value="Yes">Yes</option>
                        </select></div>
                      {editForm.other_insurance_policy === 'Yes' && (
                        <>
                          <div><label className="block text-xs font-medium text-ink-faint mb-1">Name of the Insurance Company</label>
                            <input value={editForm.name_insurance_company || ''} onChange={e => setEditForm({...editForm, name_insurance_company: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                          <div><label className="block text-xs font-medium text-ink-faint mb-1">Phone Number</label>
                            <input value={editForm.phone_number || ''} onChange={e => setEditForm({...editForm, phone_number: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                          <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Address</label>
                            <textarea value={editForm.address || ''} onChange={e => setEditForm({...editForm, address: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                        </>
                      )}
                    </div>
                    <SectionHeader label="Type of Refund" />
                    <div>
                      <label className="block text-xs font-medium text-ink-faint mb-1">Type of Refund</label>
                      <select value={editForm.type_of_refund || ''} onChange={e => setEditForm({...editForm, type_of_refund: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                        <option value="">Select Type of Refund</option>
                        <option value="Medical Expenses">Medical Expenses</option>
                        <option value="Emergency Dental Care">Emergency Dental Care</option>
                        <option value="Delayed Luggage">Delayed Luggage</option>
                        <option value="Loss of Luggage">Loss of Luggage</option>
                        <option value="Flight Delay">Flight Delay</option>
                        <option value="Delayed Departure">Delayed Departure</option>
                        <option value="Loss of Personal Documents">Loss of Personal Documents</option>
                        <option value="Trip Cancellation">Trip Cancellation</option>
                        <option value="Curtailment">Curtailment</option>
                      </select>
                    </div>
                  </>
                )
                if (ctKey === 'PROFESSIONALINDEMNITY') return (
                  <>
                    {/* Professional Indemnity — V8 parity. Global Edit
                        Claim modal does NOT render the 2 file inputs;
                        those are handled by the per-section sub-claim
                        Edit flow above. Only text fields ship here. */}
                    <SectionHeader label="Professional Indemnity — Insured's Details" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Type of Business</label>
                        <input value={editForm.type_of_business || ''} onChange={e => setEditForm({...editForm, type_of_business: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Contact Person</label>
                        <input value={editForm.contact_person || ''} onChange={e => setEditForm({...editForm, contact_person: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Designation</label>
                        <input value={editForm.designation || ''} onChange={e => setEditForm({...editForm, designation: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">E-mail Address</label>
                        <input type="email" value={editForm.insured_email || ''} onChange={e => setEditForm({...editForm, insured_email: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Cell / Tel Number</label>
                        <input value={editForm.insured_cell_tel || ''} onChange={e => setEditForm({...editForm, insured_cell_tel: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Claimant / Potential Claimant Details" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Claimant Type</label>
                        <select value={editForm.claimant_type || ''} onChange={e => setEditForm({...editForm, claimant_type: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option>
                          <option value="Business">Business</option>
                          <option value="Individual">Individual</option>
                        </select></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Name &amp; Surname</label>
                        <input value={editForm.claimant_name_surname || ''} onChange={e => setEditForm({...editForm, claimant_name_surname: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">E-mail Address</label>
                        <input type="email" value={editForm.claimant_email || ''} onChange={e => setEditForm({...editForm, claimant_email: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Cell / Tel Number</label>
                        <input value={editForm.claimant_cell_tel || ''} onChange={e => setEditForm({...editForm, claimant_cell_tel: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Details of Contract and Claim" />
                    <div className="grid grid-cols-2 gap-3">
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">What was the insured retained/contracted to do?</label>
                        <textarea value={editForm.insured_retained_to_do || ''} onChange={e => setEditForm({...editForm, insured_retained_to_do: e.target.value})} rows={3} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Was there a contract in place?</label>
                        <select value={String(editForm.contract_in_place ?? '')} onChange={e => setEditForm({...editForm, contract_in_place: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      {String(editForm.contract_in_place) === '0' && (
                        <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Provide details</label>
                          <textarea value={editForm.contract_no_details || ''} onChange={e => setEditForm({...editForm, contract_no_details: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      )}
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">When was the work performed?</label>
                        <input type="date" value={editForm.work_performed_date || ''} onChange={e => setEditForm({...editForm, work_performed_date: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Person who performed the work</label>
                        <input value={editForm.person_performed_work || ''} onChange={e => setEditForm({...editForm, person_performed_work: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Circumstances" />
                    <div className="grid grid-cols-2 gap-3">
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Circumstances giving rise to the claim (allegations of negligence)</label>
                        <textarea value={editForm.circumstances || ''} onChange={e => setEditForm({...editForm, circumstances: e.target.value})} rows={4} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">When did the insured first become aware of the claim/circumstance?</label>
                        <input type="date" value={editForm.first_aware_date || ''} onChange={e => setEditForm({...editForm, first_aware_date: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Reason for reporting the incident</label>
                        <textarea value={editForm.reason_for_reporting || ''} onChange={e => setEditForm({...editForm, reason_for_reporting: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Reported for notification purposes only?</label>
                        <select value={String(editForm.notification_purposes_only ?? '')} onChange={e => setEditForm({...editForm, notification_purposes_only: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Received a verbal/written demand for compensation?</label>
                        <select value={String(editForm.verbal_written_demand ?? '')} onChange={e => setEditForm({...editForm, verbal_written_demand: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      {String(editForm.verbal_written_demand) === '1' && (
                        <div><label className="block text-xs font-medium text-ink-faint mb-1">Date demand received</label>
                          <input type="date" value={editForm.demand_received_date || ''} onChange={e => setEditForm({...editForm, demand_received_date: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      )}
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Has the insured been served with a Summons?</label>
                        <select value={String(editForm.served_with_summons ?? '')} onChange={e => setEditForm({...editForm, served_with_summons: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      {String(editForm.served_with_summons) === '1' && (
                        <div><label className="block text-xs font-medium text-ink-faint mb-1">Date Summons served</label>
                          <input type="date" value={editForm.summons_served_date || ''} onChange={e => setEditForm({...editForm, summons_served_date: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      )}
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Has the insured appointed an Attorney/Loss Adjustor?</label>
                        <select value={String(editForm.attorney_appointed ?? '')} onChange={e => setEditForm({...editForm, attorney_appointed: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      {String(editForm.attorney_appointed) === '1' && (
                        <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Attorney details</label>
                          <textarea value={editForm.attorney_details || ''} onChange={e => setEditForm({...editForm, attorney_details: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      )}
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Amount claimed (BWP)</label>
                        <input type="number" value={editForm.amount_claimed || ''} onChange={e => setEditForm({...editForm, amount_claimed: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Insured's Investigation" />
                    <div className="grid grid-cols-2 gap-3">
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Has the insured conducted their own investigation?</label>
                        <select value={String(editForm.own_investigation ?? '')} onChange={e => setEditForm({...editForm, own_investigation: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Insured's views/comments on Liability</label>
                        <textarea value={editForm.views_on_liability || ''} onChange={e => setEditForm({...editForm, views_on_liability: e.target.value})} rows={3} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Insured's views/comments on Amount Claimed</label>
                        <textarea value={editForm.views_on_amount_claimed || ''} onChange={e => setEditForm({...editForm, views_on_amount_claimed: e.target.value})} rows={3} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Additional details to notify insurer</label>
                        <textarea value={editForm.additional_details || ''} onChange={e => setEditForm({...editForm, additional_details: e.target.value})} rows={3} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                  </>
                )
                if (ctKey === 'PLANTALLRISKS') return (
                  <>
                    <SectionHeader label="Plant All Risks — Responsible Person on Site" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Name</label>
                        <input value={editForm.responsible_person_name || ''} onChange={e => setEditForm({...editForm, responsible_person_name: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Phone</label>
                        <input value={editForm.responsible_person_phone || ''} onChange={e => setEditForm({...editForm, responsible_person_phone: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Cellphone</label>
                        <input value={editForm.responsible_person_cellphone || ''} onChange={e => setEditForm({...editForm, responsible_person_cellphone: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Email</label>
                        <input type="email" value={editForm.responsible_person_email || ''} onChange={e => setEditForm({...editForm, responsible_person_email: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Fax</label>
                        <input value={editForm.responsible_person_fax || ''} onChange={e => setEditForm({...editForm, responsible_person_fax: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Site Details" />
                    <div className="grid grid-cols-1 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Site Physical Address</label>
                        <textarea value={editForm.site_physical_address || ''} onChange={e => setEditForm({...editForm, site_physical_address: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Code</label>
                        <input value={editForm.site_code || ''} onChange={e => setEditForm({...editForm, site_code: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Plant Details" />
                    <div className="grid grid-cols-1 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Item of Plant Stolen/Damaged (full description / model / serial number)</label>
                        <textarea value={editForm.item_description || ''} onChange={e => setEditForm({...editForm, item_description: e.target.value})} rows={3} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Item Number on Policy Schedule / Sum Insured</label>
                        <input value={editForm.item_number_sum_insured || ''} onChange={e => setEditForm({...editForm, item_number_sum_insured: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Loss / Damage Details" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Date of Loss / Damage</label>
                        <input type="date" value={editForm.date_of_loss || ''} onChange={e => setEditForm({...editForm, date_of_loss: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Time</label>
                        <input type="time" value={editForm.time_of_loss || ''} onChange={e => setEditForm({...editForm, time_of_loss: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Details of Loss / Damage</label>
                        <textarea value={editForm.details_of_loss || ''} onChange={e => setEditForm({...editForm, details_of_loss: e.target.value})} rows={3} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Cause of Loss / Damage</label>
                        <textarea value={editForm.cause_of_loss || ''} onChange={e => setEditForm({...editForm, cause_of_loss: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Party Responsible — Name</label>
                        <input value={editForm.party_responsible_name || ''} onChange={e => setEditForm({...editForm, party_responsible_name: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Party Responsible — Contact</label>
                        <input value={editForm.party_responsible_contact || ''} onChange={e => setEditForm({...editForm, party_responsible_contact: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Estimated Cost of Repair / Replacement (BWP)</label>
                        <input type="number" value={editForm.estimated_cost || ''} onChange={e => setEditForm({...editForm, estimated_cost: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Is the unit uneconomical to repair / write off?</label>
                        <select value={String(editForm.uneconomical_to_repair ?? '')} onChange={e => setEditForm({...editForm, uneconomical_to_repair: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Is the unit subject to Finance / Hire Purchase?</label>
                        <select value={String(editForm.subject_to_finance ?? '')} onChange={e => setEditForm({...editForm, subject_to_finance: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Was the unit on hire at time of accident / theft?</label>
                        <select value={String(editForm.on_hire_at_time ?? '')} onChange={e => setEditForm({...editForm, on_hire_at_time: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Police Station (Theft claims only)</label>
                        <input value={editForm.police_station || ''} onChange={e => setEditForm({...editForm, police_station: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Reference (Theft claims only)</label>
                        <input value={editForm.police_reference || ''} onChange={e => setEditForm({...editForm, police_reference: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                  </>
                )
                if (ctKey === 'LOCKSANDKEYS' || ctKey === 'KEYLOSS') return (
                  <>
                    {/* Locks & Keys — full graphiteBWV8 key_loss.blade.php form.
                        Global Edit modal does NOT render the file inputs
                        (police_affidavit, quote_1, quote_2); those live in the
                        per-section sub-claim Edit flow above. Text fields only. */}
                    <SectionHeader label="Locks &amp; Keys — Loss Of Key Claim Information" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Financial Interest</label>
                        <input value={editForm.financial_interest || ''} onChange={e => setEditForm({...editForm, financial_interest: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Chassis Number</label>
                        <input value={editForm.chassis_num || ''} onChange={e => setEditForm({...editForm, chassis_num: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Purpose of use</label>
                        <select value={editForm.purpose || ''} onChange={e => setEditForm({...editForm, purpose: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option>
                          <option value="Private">Private</option>
                          <option value="Commercial">Commercial</option>
                          <option value="Public">Public</option>
                        </select></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Is the key lost or damaged or stolen</label>
                        <select value={editForm.key_reason || ''} onChange={e => setEditForm({...editForm, key_reason: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option>
                          <option value="lost">Lost</option>
                          <option value="damaged">Damaged</option>
                          <option value="stolen">Stolen</option>
                        </select></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Replacement Estimate</label>
                        <input value={editForm.estimate || ''} onChange={e => setEditForm({...editForm, estimate: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Date of Loss / Stolen / Damage</label>
                        <input type="date" value={editForm.lossDate || ''} onChange={e => setEditForm({...editForm, lossDate: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Date of claim registered</label>
                        <input type="date" value={editForm.registered_claim || ''} onChange={e => setEditForm({...editForm, registered_claim: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Description</label>
                        <textarea value={editForm.descriptionofLoss || ''} onChange={e => setEditForm({...editForm, descriptionofLoss: e.target.value})} rows={3} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Insured Details" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Name of Insured</label>
                        <input value={editForm.name_of_insured || ''} onChange={e => setEditForm({...editForm, name_of_insured: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Address</label>
                        <input value={editForm.insured_address || ''} onChange={e => setEditForm({...editForm, insured_address: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Occupation</label>
                        <input value={editForm.insured_occupation || ''} onChange={e => setEditForm({...editForm, insured_occupation: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Email</label>
                        <input type="email" value={editForm.insured_email || ''} onChange={e => setEditForm({...editForm, insured_email: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Contact No</label>
                        <input value={editForm.insured_contact_no || ''} onChange={e => setEditForm({...editForm, insured_contact_no: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Vehicle Details" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Registration Number</label>
                        {modalPlateOpts.length > 0 ? (
                          <select value={editForm.vehicle_plate || ''} onChange={e => setEditForm({...editForm, vehicle_plate: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                            <option value="">--</option>
                            {editForm.vehicle_plate && !modalPlateOpts.some(o => o.id === editForm.vehicle_plate) && (
                              <option value={editForm.vehicle_plate}>{editForm.vehicle_plate} (not on term)</option>
                            )}
                            {modalPlateOpts.map(o => <option key={o.id} value={o.id}>{o.name}</option>)}
                          </select>
                        ) : (
                          <input value={editForm.vehicle_plate || ''} onChange={e => setEditForm({...editForm, vehicle_plate: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" />
                        )}</div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Is Imported?</label>
                        <select value={editForm.is_imported || ''} onChange={e => setEditForm({...editForm, is_imported: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option>
                          <option value="Yes">Yes</option>
                          <option value="No">No</option>
                        </select></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Make</label>
                        <input value={editForm.make || ''} onChange={e => setEditForm({...editForm, make: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Manufacturing Year</label>
                        <input value={editForm.year || ''} onChange={e => setEditForm({...editForm, year: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Model</label>
                        <input value={editForm.model || ''} onChange={e => setEditForm({...editForm, model: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Quotation" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Quote 1 — Name of company</label>
                        <input value={editForm.company_1 || ''} onChange={e => setEditForm({...editForm, company_1: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Quote 1 — Amount of quote</label>
                        <input type="number" value={editForm.amount_quote_1 || ''} onChange={e => setEditForm({...editForm, amount_quote_1: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Quote 2 — Name of company</label>
                        <input value={editForm.company_2 || ''} onChange={e => setEditForm({...editForm, company_2: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Quote 2 — Amount of quote</label>
                        <input type="number" value={editForm.amount_quote_2 || ''} onChange={e => setEditForm({...editForm, amount_quote_2: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                  </>
                )
                if (ctKey === 'MACHINERYBREAKDOWN') return (
                  <>
                    <SectionHeader label="Machinery Breakdown — Policy Details" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Insured</label>
                        <input value={editForm.insured || ''} onChange={e => setEditForm({...editForm, insured: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Period of Insurance</label>
                        <input value={editForm.period_of_insurance || ''} onChange={e => setEditForm({...editForm, period_of_insurance: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Sum Insured (BWP)</label>
                        <input type="number" value={editForm.sum_insured || ''} onChange={e => setEditForm({...editForm, sum_insured: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Insured Contact & Business" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Contact Person</label>
                        <input value={editForm.contact_person || ''} onChange={e => setEditForm({...editForm, contact_person: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Designation</label>
                        <input value={editForm.designation || ''} onChange={e => setEditForm({...editForm, designation: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Phone</label>
                        <input value={editForm.phone || ''} onChange={e => setEditForm({...editForm, phone: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Cellphone</label>
                        <input value={editForm.cellphone || ''} onChange={e => setEditForm({...editForm, cellphone: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Email</label>
                        <input type="email" value={editForm.email || ''} onChange={e => setEditForm({...editForm, email: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Years in Operation</label>
                        <input value={editForm.years_in_operation || ''} onChange={e => setEditForm({...editForm, years_in_operation: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Postal / Physical Address</label>
                        <textarea value={editForm.postal_physical_address || ''} onChange={e => setEditForm({...editForm, postal_physical_address: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Nature of Business / Industry</label>
                        <textarea value={editForm.nature_of_business || ''} onChange={e => setEditForm({...editForm, nature_of_business: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Damaged Machinery" />
                    <div className="grid grid-cols-2 gap-3">
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Description of Item per Policy Schedule</label>
                        <textarea value={editForm.item_description || ''} onChange={e => setEditForm({...editForm, item_description: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Make / Model</label>
                        <input value={editForm.make_model || ''} onChange={e => setEditForm({...editForm, make_model: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Serial Number</label>
                        <input value={editForm.serial_number || ''} onChange={e => setEditForm({...editForm, serial_number: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Year of Manufacture</label>
                        <input value={editForm.year_of_manufacture || ''} onChange={e => setEditForm({...editForm, year_of_manufacture: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Date Originally Commissioned</label>
                        <input type="date" value={editForm.date_commissioned || ''} onChange={e => setEditForm({...editForm, date_commissioned: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Current Replacement Value (BWP)</label>
                        <input type="number" value={editForm.current_replacement_value || ''} onChange={e => setEditForm({...editForm, current_replacement_value: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Technical Specs (kW, RPM, voltage, capacity)</label>
                        <textarea value={editForm.technical_specs || ''} onChange={e => setEditForm({...editForm, technical_specs: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Under Manufacturer / AMC Contract at date of loss?</label>
                        <select value={String(editForm.under_amc_contract ?? '')} onChange={e => setEditForm({...editForm, under_amc_contract: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">AMC Contract — Details</label>
                        <textarea value={editForm.amc_contract_details || ''} onChange={e => setEditForm({...editForm, amc_contract_details: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Loss / Damage Details" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Date of Loss</label>
                        <input type="date" value={editForm.date_of_loss || ''} onChange={e => setEditForm({...editForm, date_of_loss: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Time of Loss</label>
                        <input type="time" value={editForm.time_of_loss || ''} onChange={e => setEditForm({...editForm, time_of_loss: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Date Loss Discovered</label>
                        <input type="date" value={editForm.date_loss_discovered || ''} onChange={e => setEditForm({...editForm, date_loss_discovered: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Equipment status at time of loss</label>
                        <input value={editForm.equipment_status || ''} onChange={e => setEditForm({...editForm, equipment_status: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Site / Location of Loss (full physical address or GPS)</label>
                        <textarea value={editForm.site_location || ''} onChange={e => setEditForm({...editForm, site_location: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Cause of Loss</label>
                        <textarea value={editForm.cause_of_loss || ''} onChange={e => setEditForm({...editForm, cause_of_loss: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Description of Damage (which parts, extent of damage)</label>
                        <textarea value={editForm.damage_description || ''} onChange={e => setEditForm({...editForm, damage_description: e.target.value})} rows={3} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Repair, Replacement & Salvage" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Estimated Cost of Repair / Replacement (BWP)</label>
                        <input type="number" value={editForm.estimated_cost || ''} onChange={e => setEditForm({...editForm, estimated_cost: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Proposed Repairer / OEM Service Centre (Name, Address, Contact)</label>
                        <textarea value={editForm.proposed_repairer || ''} onChange={e => setEditForm({...editForm, proposed_repairer: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Salvage Location — where will the damaged item be physically held?</label>
                        <textarea value={editForm.salvage_location || ''} onChange={e => setEditForm({...editForm, salvage_location: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Financier, Recovery & Other Insurance" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Sole owner of the damaged machinery?</label>
                        <select value={String(editForm.sole_owner ?? '')} onChange={e => setEditForm({...editForm, sole_owner: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Subject to bank loan, lease, hire-purchase or notarial bond?</label>
                        <select value={String(editForm.subject_to_finance ?? '')} onChange={e => setEditForm({...editForm, subject_to_finance: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Sole owner — Details</label>
                        <textarea value={editForm.sole_owner_details || ''} onChange={e => setEditForm({...editForm, sole_owner_details: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">If no — co-owner / lessor / hire-purchase financier name</label>
                        <textarea value={editForm.co_owner_financier || ''} onChange={e => setEditForm({...editForm, co_owner_financier: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Finance — Details</label>
                        <textarea value={editForm.finance_details || ''} onChange={e => setEditForm({...editForm, finance_details: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Financier / Bank name and account / agreement reference</label>
                        <textarea value={editForm.financier_bank_reference || ''} onChange={e => setEditForm({...editForm, financier_bank_reference: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Third party responsible for the loss?</label>
                        <select value={String(editForm.third_party_responsible ?? '')} onChange={e => setEditForm({...editForm, third_party_responsible: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Third Party Name & Contact</label>
                        <input value={editForm.third_party_name_contact || ''} onChange={e => setEditForm({...editForm, third_party_name_contact: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Third party responsible — Details</label>
                        <textarea value={editForm.third_party_details || ''} onChange={e => setEditForm({...editForm, third_party_details: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Recovery claim lodged with third party?</label>
                        <select value={String(editForm.recovery_claim_lodged ?? '')} onChange={e => setEditForm({...editForm, recovery_claim_lodged: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Machinery insured under any other policy?</label>
                        <select value={String(editForm.other_insurance ?? '')} onChange={e => setEditForm({...editForm, other_insurance: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Recovery claim — Details</label>
                        <textarea value={editForm.recovery_details || ''} onChange={e => setEditForm({...editForm, recovery_details: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Other insurance — Details</label>
                        <textarea value={editForm.other_insurance_details || ''} onChange={e => setEditForm({...editForm, other_insurance_details: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Other Insurer / Policy Number / Sum Insured</label>
                        <textarea value={editForm.other_insurer_policy || ''} onChange={e => setEditForm({...editForm, other_insurer_policy: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Loss History — incidents in the past 3 years (claimed or not)</label>
                        <textarea value={editForm.loss_history || ''} onChange={e => setEditForm({...editForm, loss_history: e.target.value})} rows={3} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Prevention & Declaration" />
                    <div className="grid grid-cols-2 gap-3">
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Procedural improvements proposed or implemented to prevent recurrence</label>
                        <textarea value={editForm.procedural_improvements || ''} onChange={e => setEditForm({...editForm, procedural_improvements: e.target.value})} rows={3} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Name</label>
                        <input value={editForm.declaration_name || ''} onChange={e => setEditForm({...editForm, declaration_name: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Capacity</label>
                        <input value={editForm.declaration_capacity || ''} onChange={e => setEditForm({...editForm, declaration_capacity: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Date</label>
                        <input type="date" value={editForm.declaration_date || ''} onChange={e => setEditForm({...editForm, declaration_date: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                  </>
                )
                if (ctKey === 'MACHINERYBREAKDOWNLOSSOFPROFIT') return (
                  <>
                    <SectionHeader label="Linked Machinery Breakdown Claim" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Machinery Breakdown Claim Number</label>
                        <input value={editForm.mb_claim_number || ''} onChange={e => setEditForm({...editForm, mb_claim_number: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Date of Breakdown / Damage</label>
                        <input type="date" value={editForm.date_of_breakdown || ''} onChange={e => setEditForm({...editForm, date_of_breakdown: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Status of MB Physical Damage Claim</label>
                        <input value={editForm.mb_physical_claim_status || ''} onChange={e => setEditForm({...editForm, mb_physical_claim_status: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Insured Contact & Business" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Insured (Registered Name)</label>
                        <input value={editForm.insured || ''} onChange={e => setEditForm({...editForm, insured: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Contact Person</label>
                        <input value={editForm.contact_person || ''} onChange={e => setEditForm({...editForm, contact_person: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Designation</label>
                        <input value={editForm.designation || ''} onChange={e => setEditForm({...editForm, designation: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Phone / Cellphone</label>
                        <input value={editForm.phone_cellphone || ''} onChange={e => setEditForm({...editForm, phone_cellphone: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Email</label>
                        <input type="email" value={editForm.email || ''} onChange={e => setEditForm({...editForm, email: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Years in operation</label>
                        <input value={editForm.years_in_operation || ''} onChange={e => setEditForm({...editForm, years_in_operation: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Postal / Physical Address</label>
                        <textarea value={editForm.postal_physical_address || ''} onChange={e => setEditForm({...editForm, postal_physical_address: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Site / Premises affected</label>
                        <textarea value={editForm.site_premises_affected || ''} onChange={e => setEditForm({...editForm, site_premises_affected: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Nature of Business and main products / services</label>
                        <textarea value={editForm.nature_of_business || ''} onChange={e => setEditForm({...editForm, nature_of_business: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Operating Profile (Pre-Loss Baseline)" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Production capacity (units / hour or day)</label>
                        <input value={editForm.production_capacity || ''} onChange={e => setEditForm({...editForm, production_capacity: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Operating hours per day</label>
                        <input value={editForm.operating_hours_per_day || ''} onChange={e => setEditForm({...editForm, operating_hours_per_day: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Number of shifts (1 / 2 / 3)</label>
                        <input value={editForm.number_of_shifts || ''} onChange={e => setEditForm({...editForm, number_of_shifts: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Operating days per week</label>
                        <input value={editForm.operating_days_per_week || ''} onChange={e => setEditForm({...editForm, operating_days_per_week: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Standard turnover prior 12 months (BWP)</label>
                        <input type="number" value={editForm.standard_turnover_prior_12m || ''} onChange={e => setEditForm({...editForm, standard_turnover_prior_12m: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Standard output prior 12 months (units)</label>
                        <input value={editForm.standard_output_prior_12m || ''} onChange={e => setEditForm({...editForm, standard_output_prior_12m: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Is the business seasonal?</label>
                        <select value={String(editForm.is_seasonal ?? '')} onChange={e => setEditForm({...editForm, is_seasonal: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Seasonal — Details</label>
                        <textarea value={editForm.seasonal_details || ''} onChange={e => setEditForm({...editForm, seasonal_details: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Peak months / seasonal pattern</label>
                        <textarea value={editForm.peak_months_pattern || ''} onChange={e => setEditForm({...editForm, peak_months_pattern: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Comparable period turnover — prior year (BWP)</label>
                        <input type="number" value={editForm.comparable_period_turnover || ''} onChange={e => setEditForm({...editForm, comparable_period_turnover: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Comparable period output — prior year (units)</label>
                        <input value={editForm.comparable_period_output || ''} onChange={e => setEditForm({...editForm, comparable_period_output: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Indemnity Period & Downtime" />
                    <div className="grid grid-cols-2 gap-3">
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Damaged item description and item number per Policy Schedule</label>
                        <textarea value={editForm.damaged_item_description || ''} onChange={e => setEditForm({...editForm, damaged_item_description: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Date production halted</label>
                        <input type="date" value={editForm.date_production_halted || ''} onChange={e => setEditForm({...editForm, date_production_halted: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Time excess (waiting period) start and end</label>
                        <input value={editForm.time_excess_start_end || ''} onChange={e => setEditForm({...editForm, time_excess_start_end: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Date production partially resumed</label>
                        <input type="date" value={editForm.date_production_partial_resumed || ''} onChange={e => setEditForm({...editForm, date_production_partial_resumed: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Date production fully resumed (or expected)</label>
                        <input type="date" value={editForm.date_production_full_resumed || ''} onChange={e => setEditForm({...editForm, date_production_full_resumed: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Total full-shutdown days</label>
                        <input value={editForm.total_full_shutdown_days || ''} onChange={e => setEditForm({...editForm, total_full_shutdown_days: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Total reduced-capacity days</label>
                        <input value={editForm.total_reduced_capacity_days || ''} onChange={e => setEditForm({...editForm, total_reduced_capacity_days: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Indemnity period max end-date</label>
                        <input type="date" value={editForm.indemnity_period_max_end_date || ''} onChange={e => setEditForm({...editForm, indemnity_period_max_end_date: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Is the loss continuing?</label>
                        <select value={String(editForm.loss_continuing ?? '')} onChange={e => setEditForm({...editForm, loss_continuing: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Loss continuing — Details</label>
                        <textarea value={editForm.loss_continuing_details || ''} onChange={e => setEditForm({...editForm, loss_continuing_details: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">How the breakdown affected production / turnover (which lines, what %)</label>
                        <textarea value={editForm.production_impact_description || ''} onChange={e => setEditForm({...editForm, production_impact_description: e.target.value})} rows={3} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Loss of Profit — A. Reduction in Turnover / Output" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Standard turnover during indemnity period to date (BWP)</label>
                        <input type="number" value={editForm.standard_turnover_indemnity || ''} onChange={e => setEditForm({...editForm, standard_turnover_indemnity: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Actual turnover during indemnity period to date (BWP)</label>
                        <input type="number" value={editForm.actual_turnover_indemnity || ''} onChange={e => setEditForm({...editForm, actual_turnover_indemnity: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Reduction in turnover (BWP)</label>
                        <input type="number" value={editForm.reduction_in_turnover || ''} onChange={e => setEditForm({...editForm, reduction_in_turnover: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Rate of Gross Profit applied (%)</label>
                        <input value={editForm.gross_profit_rate || ''} onChange={e => setEditForm({...editForm, gross_profit_rate: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Calculated Gross Profit lost (BWP)</label>
                        <input type="number" value={editForm.gross_profit_lost || ''} onChange={e => setEditForm({...editForm, gross_profit_lost: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Loss of Profit — B. Increased Cost of Working" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Outsourcing / sub-contracting (BWP)</label>
                        <input type="number" value={editForm.icw_outsourcing || ''} onChange={e => setEditForm({...editForm, icw_outsourcing: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Hire of replacement / temporary equipment (BWP)</label>
                        <input type="number" value={editForm.icw_equipment_hire || ''} onChange={e => setEditForm({...editForm, icw_equipment_hire: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Express freight / expedited shipping (BWP)</label>
                        <input type="number" value={editForm.icw_express_freight || ''} onChange={e => setEditForm({...editForm, icw_express_freight: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Overtime and additional labour (BWP)</label>
                        <input type="number" value={editForm.icw_overtime_labour || ''} onChange={e => setEditForm({...editForm, icw_overtime_labour: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Temporary premises / utilities (BWP)</label>
                        <input type="number" value={editForm.icw_temporary_premises || ''} onChange={e => setEditForm({...editForm, icw_temporary_premises: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Other ICW (itemise) (BWP)</label>
                        <input value={editForm.icw_other || ''} onChange={e => setEditForm({...editForm, icw_other: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Total ICW (BWP)</label>
                        <input type="number" value={editForm.icw_total || ''} onChange={e => setEditForm({...editForm, icw_total: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Loss of Profit — C. Savings" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Raw materials / consumables not used (BWP)</label>
                        <input type="number" value={editForm.savings_raw_materials || ''} onChange={e => setEditForm({...editForm, savings_raw_materials: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Power / utilities not consumed (BWP)</label>
                        <input type="number" value={editForm.savings_power_utilities || ''} onChange={e => setEditForm({...editForm, savings_power_utilities: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Wages saved (staff stood down) (BWP)</label>
                        <input type="number" value={editForm.savings_wages || ''} onChange={e => setEditForm({...editForm, savings_wages: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Other savings (itemise) (BWP)</label>
                        <input value={editForm.savings_other || ''} onChange={e => setEditForm({...editForm, savings_other: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Total savings (BWP)</label>
                        <input type="number" value={editForm.savings_total || ''} onChange={e => setEditForm({...editForm, savings_total: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Loss of Profit — D. Estimated Claim" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Estimated Gross Loss of Profit (A + B − C) (BWP)</label>
                        <input type="number" value={editForm.gross_loss_of_profit || ''} onChange={e => setEditForm({...editForm, gross_loss_of_profit: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Less Time Excess (BWP)</label>
                        <input type="number" value={editForm.less_time_excess || ''} onChange={e => setEditForm({...editForm, less_time_excess: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Less Self-Insured Retention (BWP)</label>
                        <input type="number" value={editForm.less_self_insured_retention || ''} onChange={e => setEditForm({...editForm, less_self_insured_retention: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Net estimated claim (BWP)</label>
                        <input type="number" value={editForm.net_estimated_claim || ''} onChange={e => setEditForm({...editForm, net_estimated_claim: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Mitigation Actions" />
                    <div className="grid grid-cols-2 gap-3">
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Mitigation steps taken (with dates)</label>
                        <textarea value={editForm.mitigation_steps || ''} onChange={e => setEditForm({...editForm, mitigation_steps: e.target.value})} rows={3} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Alternative production capacity available (group / region)?</label>
                        <select value={String(editForm.alt_production_available ?? '')} onChange={e => setEditForm({...editForm, alt_production_available: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Replacement / hire equipment sourced?</label>
                        <select value={String(editForm.replacement_equipment_sourced ?? '')} onChange={e => setEditForm({...editForm, replacement_equipment_sourced: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Details (location, capacity used, cost)</label>
                        <textarea value={editForm.alt_production_details || ''} onChange={e => setEditForm({...editForm, alt_production_details: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Supplier and rental terms</label>
                        <textarea value={editForm.replacement_supplier_terms || ''} onChange={e => setEditForm({...editForm, replacement_supplier_terms: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Other Insurance & Loss History" />
                    <div className="grid grid-cols-2 gap-3">
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Any other Business Interruption / LOP cover that may respond?</label>
                        <select value={String(editForm.other_bi_cover ?? '')} onChange={e => setEditForm({...editForm, other_bi_cover: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Other BI / LOP cover — Details</label>
                        <textarea value={editForm.other_bi_details || ''} onChange={e => setEditForm({...editForm, other_bi_details: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Other insurer / policy / period / sum insured</label>
                        <textarea value={editForm.other_insurer_policy || ''} onChange={e => setEditForm({...editForm, other_insurer_policy: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Loss history — prior LOP / BI claims in past 24 months</label>
                        <textarea value={editForm.loss_history || ''} onChange={e => setEditForm({...editForm, loss_history: e.target.value})} rows={3} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Declaration" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Name</label>
                        <input value={editForm.declaration_name || ''} onChange={e => setEditForm({...editForm, declaration_name: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Capacity</label>
                        <input value={editForm.declaration_capacity || ''} onChange={e => setEditForm({...editForm, declaration_capacity: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Date</label>
                        <input type="date" value={editForm.declaration_date || ''} onChange={e => setEditForm({...editForm, declaration_date: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                  </>
                )
                if (ctKey === 'DIRECTORSOFFICERSLIABILITY') {
                  // Compact bound checkbox for the "tick all that apply" groups.
                  const DoChk = ({ k, l }: { k: string; l: string }) => (
                    <label className="flex items-center gap-2 text-sm cursor-pointer">
                      <input type="checkbox"
                        checked={!!editForm[k] && editForm[k] !== '0'}
                        onChange={e => setEditForm({ ...editForm, [k]: e.target.checked as any })}
                        className="rounded" /> {l}
                    </label>
                  )
                  const YN = ({ k, l }: { k: string; l: string }) => (
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">{l}</label>
                      <select value={String(editForm[k] ?? '')} onChange={e => setEditForm({...editForm, [k]: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                        <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                      </select></div>
                  )
                  return (
                  <>
                    <SectionHeader label="Type of Notification (tick one or more)" />
                    <div className="grid grid-cols-1 gap-2">
                      <DoChk k="notif_claim" l="Claim — a written demand or proceeding served on an Insured Person" />
                      <DoChk k="notif_circumstance" l="Circumstance — facts that may give rise to a Claim" />
                      <DoChk k="notif_investigation" l="Investigation — regulatory or internal" />
                      <DoChk k="notif_subpoena" l="Subpoena / Witness Summons issued to an Insured Person" />
                    </div>
                    <SectionHeader label="Policy Details" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Period of Insurance</label>
                        <input value={editForm.period_of_insurance || ''} onChange={e => setEditForm({...editForm, period_of_insurance: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Retroactive Date</label>
                        <input type="date" value={editForm.retroactive_date || ''} onChange={e => setEditForm({...editForm, retroactive_date: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Limit — Aggregate (BWP)</label>
                        <input type="number" value={editForm.limit_aggregate || ''} onChange={e => setEditForm({...editForm, limit_aggregate: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Limit — Each Claim (BWP)</label>
                        <input type="number" value={editForm.limit_each_claim || ''} onChange={e => setEditForm({...editForm, limit_each_claim: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Self-Insured Retention / Deductible (BWP)</label>
                        <input type="number" value={editForm.self_insured_retention || ''} onChange={e => setEditForm({...editForm, self_insured_retention: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <div className="mt-2 grid grid-cols-1 gap-2">
                      <label className="block text-xs font-medium text-ink-faint">Policy Cover Sides (tick all applicable)</label>
                      <DoChk k="side_a" l="Side A — direct cover for Insured Persons" />
                      <DoChk k="side_b" l="Side B — reimbursement of the Company" />
                      <DoChk k="side_c" l="Side C — entity cover (Company itself)" />
                      <DoChk k="epl_extension" l="Employment Practices Liability extension" />
                    </div>
                    <SectionHeader label="Insured Entity" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Insured Company (registered name)</label>
                        <input value={editForm.insured_company || ''} onChange={e => setEditForm({...editForm, insured_company: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Company Registration Number</label>
                        <input value={editForm.company_registration_number || ''} onChange={e => setEditForm({...editForm, company_registration_number: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">NBFIRA / Regulator License Number</label>
                        <input value={editForm.regulator_license_number || ''} onChange={e => setEditForm({...editForm, regulator_license_number: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Industry / Sector</label>
                        <input value={editForm.industry_sector || ''} onChange={e => setEditForm({...editForm, industry_sector: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Registered Address</label>
                        <textarea value={editForm.registered_address || ''} onChange={e => setEditForm({...editForm, registered_address: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Company Secretary / Compliance Officer (Name & Contact)</label>
                        <textarea value={editForm.company_secretary_contact || ''} onChange={e => setEditForm({...editForm, company_secretary_contact: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Insured Person(s) Against Whom Claim Is Made" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Person 1 — Name / Director ID</label>
                        <input value={editForm.person1_name_id || ''} onChange={e => setEditForm({...editForm, person1_name_id: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Person 1 — Position / Role</label>
                        <input value={editForm.person1_position || ''} onChange={e => setEditForm({...editForm, person1_position: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Person 1 — Date of Appointment</label>
                        <input type="date" value={editForm.person1_appointment_date || ''} onChange={e => setEditForm({...editForm, person1_appointment_date: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Person 1 — Current or Former</label>
                        <input value={editForm.person1_current_former || ''} onChange={e => setEditForm({...editForm, person1_current_former: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Person 2 — Name / Director ID</label>
                        <input value={editForm.person2_name_id || ''} onChange={e => setEditForm({...editForm, person2_name_id: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Person 2 — Position / Role</label>
                        <input value={editForm.person2_position || ''} onChange={e => setEditForm({...editForm, person2_position: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Person 2 — Date of Appointment</label>
                        <input type="date" value={editForm.person2_appointment_date || ''} onChange={e => setEditForm({...editForm, person2_appointment_date: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Person 2 — Current or Former</label>
                        <input value={editForm.person2_current_former || ''} onChange={e => setEditForm({...editForm, person2_current_former: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Additional Insured Persons</label>
                        <textarea value={editForm.additional_insured_persons || ''} onChange={e => setEditForm({...editForm, additional_insured_persons: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Claim Trigger Dates (claims-made policy)" />
                    <div className="grid grid-cols-3 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Date Wrongful Act Allegedly Occurred</label>
                        <input type="date" value={editForm.date_wrongful_act || ''} onChange={e => setEditForm({...editForm, date_wrongful_act: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Date Claim First Made</label>
                        <input type="date" value={editForm.date_claim_first_made || ''} onChange={e => setEditForm({...editForm, date_claim_first_made: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Date Insured First Became Aware</label>
                        <input type="date" value={editForm.date_insured_first_aware || ''} onChange={e => setEditForm({...editForm, date_insured_first_aware: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Claimant Details — Identity (tick all)" />
                    <div className="grid grid-cols-2 gap-2">
                      <DoChk k="claimant_shareholder" l="Shareholder / investor" />
                      <DoChk k="claimant_regulator" l="Regulator (NBFIRA, BURS, Competition, BSE, other)" />
                      <DoChk k="claimant_liquidator" l="Liquidator / business rescue practitioner" />
                      <DoChk k="claimant_employee" l="Employee or former employee" />
                      <DoChk k="claimant_customer" l="Customer / counterparty" />
                      <DoChk k="claimant_creditor" l="Creditor" />
                      <DoChk k="claimant_government" l="Government / criminal prosecution" />
                      <DoChk k="claimant_other" l="Other" />
                    </div>
                    <div className="grid grid-cols-2 gap-3 mt-2">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Claimant Name(s)</label>
                        <input value={editForm.claimant_names || ''} onChange={e => setEditForm({...editForm, claimant_names: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Claimant's Legal Counsel / Representative</label>
                        <textarea value={editForm.claimant_legal_counsel || ''} onChange={e => setEditForm({...editForm, claimant_legal_counsel: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Form of the Claim (tick all)" />
                    <div className="grid grid-cols-2 gap-2">
                      <DoChk k="form_letter_demand" l="Letter of demand" />
                      <DoChk k="form_summons" l="Summons / Statement of Claim filed" />
                      <DoChk k="form_subpoena" l="Subpoena / witness summons" />
                      <DoChk k="form_regulator_inquiry" l="Regulator inquiry / investigation notice" />
                      <DoChk k="form_criminal_charge" l="Criminal charge" />
                      <DoChk k="form_internal_investigation" l="Internal investigation initiated" />
                      <DoChk k="form_other" l="Other" />
                    </div>
                    <SectionHeader label="Nature of Allegation (tick all)" />
                    <div className="grid grid-cols-2 gap-2">
                      <DoChk k="alleg_fiduciary_breach" l="Breach of fiduciary duty / duty of care" />
                      <DoChk k="alleg_misstatement" l="Misstatement / misrepresentation" />
                      <DoChk k="alleg_insolvent_trading" l="Insolvent / reckless trading" />
                      <DoChk k="alleg_misappropriation" l="Misappropriation / conflict of interest" />
                      <DoChk k="alleg_regulatory_breach" l="Regulatory breach" />
                      <DoChk k="alleg_employment_practices" l="Employment Practices" />
                      <DoChk k="alleg_negligence" l="Negligence / failure of oversight" />
                      <DoChk k="alleg_criminal" l="Criminal allegation" />
                      <DoChk k="alleg_defamation" l="Defamation" />
                      <DoChk k="alleg_other" l="Other" />
                    </div>
                    <div className="grid grid-cols-2 gap-3 mt-2">
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Brief description of the allegation and conduct alleged</label>
                        <textarea value={editForm.allegation_description || ''} onChange={e => setEditForm({...editForm, allegation_description: e.target.value})} rows={4} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Total Quantum / Amount Claimed (BWP)</label>
                        <input type="number" value={editForm.total_quantum_claimed || ''} onChange={e => setEditForm({...editForm, total_quantum_claimed: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Stage of proceedings</label>
                        <input value={editForm.stage_of_proceedings || ''} onChange={e => setEditForm({...editForm, stage_of_proceedings: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Court / Forum</label>
                        <input value={editForm.court_forum || ''} onChange={e => setEditForm({...editForm, court_forum: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Case / Reference Number</label>
                        <input value={editForm.case_reference_number || ''} onChange={e => setEditForm({...editForm, case_reference_number: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Defense & Counsel" />
                    <div className="grid grid-cols-2 gap-3">
                      <YN k="counsel_engaged" l="Has counsel been engaged?" />
                      <YN k="ad_prior_consent" l="Has Alpha Direct's prior consent been obtained?" />
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Counsel engaged — Details</label>
                        <textarea value={editForm.counsel_engaged_details || ''} onChange={e => setEditForm({...editForm, counsel_engaged_details: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Counsel firm and lead attorney</label>
                        <textarea value={editForm.counsel_firm_attorney || ''} onChange={e => setEditForm({...editForm, counsel_firm_attorney: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Counsel firm contact and rate basis</label>
                        <textarea value={editForm.counsel_contact_rate || ''} onChange={e => setEditForm({...editForm, counsel_contact_rate: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Prior consent — Details</label>
                        <textarea value={editForm.ad_prior_consent_details || ''} onChange={e => setEditForm({...editForm, ad_prior_consent_details: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Estimated defense costs to date (BWP)</label>
                        <input type="number" value={editForm.estimated_defense_costs || ''} onChange={e => setEditForm({...editForm, estimated_defense_costs: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Next pending hearing / response deadline</label>
                        <input type="date" value={editForm.next_hearing_deadline || ''} onChange={e => setEditForm({...editForm, next_hearing_deadline: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <YN k="settlement_offer_made" l="Has any settlement offer been made or received?" />
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Settlement offer details</label>
                        <textarea value={editForm.settlement_offer_details || ''} onChange={e => setEditForm({...editForm, settlement_offer_details: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Co-Defendants & Other Insurance" />
                    <div className="grid grid-cols-2 gap-3">
                      <YN k="codefendants_insured_persons" l="Other Insured Persons named as co-defendants?" />
                      <YN k="company_named" l="Is the Insured Company itself named?" />
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Co-defendants — Details</label>
                        <textarea value={editForm.codefendants_details || ''} onChange={e => setEditForm({...editForm, codefendants_details: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Names / Director IDs of co-defendants</label>
                        <textarea value={editForm.codefendant_names || ''} onChange={e => setEditForm({...editForm, codefendant_names: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Company named — Details</label>
                        <textarea value={editForm.company_named_details || ''} onChange={e => setEditForm({...editForm, company_named_details: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <YN k="outside_parties_named" l="Are outside parties (non-Insureds) named?" />
                      <YN k="other_insurance" l="Any other insurance that may respond (PI, EPLI, prior tower D&O)?" />
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Outside parties — Details</label>
                        <textarea value={editForm.outside_parties_details || ''} onChange={e => setEditForm({...editForm, outside_parties_details: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Names of outside parties</label>
                        <textarea value={editForm.outside_party_names || ''} onChange={e => setEditForm({...editForm, outside_party_names: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Other insurance — Details</label>
                        <textarea value={editForm.other_insurance_details || ''} onChange={e => setEditForm({...editForm, other_insurance_details: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Other policy / insurer / period / limit</label>
                        <textarea value={editForm.other_policy_details || ''} onChange={e => setEditForm({...editForm, other_policy_details: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <YN k="previously_notified" l="Previously notified to any insurer?" />
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Previously notified — Details</label>
                        <textarea value={editForm.previously_notified_details || ''} onChange={e => setEditForm({...editForm, previously_notified_details: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Prior notification reference and insurer</label>
                        <textarea value={editForm.prior_notification_reference || ''} onChange={e => setEditForm({...editForm, prior_notification_reference: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Declaration" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Name</label>
                        <input value={editForm.declaration_name || ''} onChange={e => setEditForm({...editForm, declaration_name: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Capacity</label>
                        <input value={editForm.declaration_capacity || ''} onChange={e => setEditForm({...editForm, declaration_capacity: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Date</label>
                        <input type="date" value={editForm.declaration_date || ''} onChange={e => setEditForm({...editForm, declaration_date: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                  </>
                  )
                }
                if (ctKey === 'MARINECARGOONCEOFF') {
                  const MChk = ({ k, l }: { k: string; l: string }) => (
                    <label className="flex items-center gap-2 text-sm cursor-pointer">
                      <input type="checkbox"
                        checked={!!editForm[k] && editForm[k] !== '0'}
                        onChange={e => setEditForm({ ...editForm, [k]: e.target.checked as any })}
                        className="rounded" /> {l}
                    </label>
                  )
                  const YN = ({ k, l }: { k: string; l: string }) => (
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">{l}</label>
                      <select value={String(editForm[k] ?? '')} onChange={e => setEditForm({...editForm, [k]: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                        <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                      </select></div>
                  )
                  const T = ({ k, l, type = 'text' }: { k: string; l: string; type?: string }) => (
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">{l}</label>
                      <input type={type} value={editForm[k] || ''} onChange={e => setEditForm({...editForm, [k]: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                  )
                  const A = ({ k, l, rows = 2 }: { k: string; l: string; rows?: number }) => (
                    <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">{l}</label>
                      <textarea value={editForm[k] || ''} onChange={e => setEditForm({...editForm, [k]: e.target.value})} rows={rows} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                  )
                  return (
                  <>
                    <SectionHeader label="Policy Details" />
                    <div className="grid grid-cols-2 gap-3">
                      <T k="certificate_number" l="Policy / Certificate Number" />
                      <T k="insured" l="Insured" />
                      <T k="period_of_cover" l="Period of Cover (this voyage)" />
                      <T k="insured_value" l="Insured Value (CIF + 10%, BWP)" type="number" />
                      <T k="conditions_of_cover" l="Conditions of Cover (ICC A / B / C)" />
                    </div>
                    <SectionHeader label="Insured Contact & Business" />
                    <div className="grid grid-cols-2 gap-3">
                      <T k="contact_person" l="Contact Person" />
                      <T k="designation" l="Designation" />
                      <T k="phone" l="Phone" />
                      <T k="cellphone" l="Cellphone" />
                      <T k="email" l="Email" type="email" />
                      <A k="postal_physical_address" l="Postal / Physical Address" />
                      <A k="nature_of_business" l="Nature of Business / Industry" />
                    </div>
                    <SectionHeader label="Consignment / Cargo Details" />
                    <div className="grid grid-cols-2 gap-3">
                      <A k="description_of_goods" l="Description of Goods" />
                      <T k="number_type_packages" l="Number and Type of Packages" />
                      <T k="marks_numbers" l="Marks & Numbers" />
                      <T k="gross_weight" l="Gross Weight (kg)" />
                      <T k="net_weight" l="Net Weight (kg)" />
                      <T k="commercial_invoice_number" l="Commercial Invoice Number" />
                      <T k="invoice_value" l="Invoice Value (currency + amount)" />
                      <T k="cif_value" l="CIF Value (BWP)" type="number" />
                      <T k="container_number" l="Container Number (if containerised)" />
                      <T k="seal_numbers" l="Seal Number(s)" />
                    </div>
                    <SectionHeader label="Voyage / Transit Details" />
                    <label className="block text-xs font-medium text-ink-faint">Mode of transport (tick all legs)</label>
                    <div className="grid grid-cols-3 gap-2">
                      <MChk k="mode_sea" l="Sea" />
                      <MChk k="mode_air" l="Air" />
                      <MChk k="mode_road" l="Road" />
                      <MChk k="mode_rail" l="Rail" />
                      <MChk k="mode_multimodal" l="Multimodal" />
                    </div>
                    <div className="grid grid-cols-2 gap-3 mt-2">
                      <A k="multimodal_route_description" l="Multimodal route description (if applicable)" />
                      <A k="origin" l="Origin (City, Country, Port / Airport)" />
                      <A k="destination" l="Destination (City, Country, Final Warehouse Address)" />
                      <T k="vessel_aircraft_truck_reg" l="Vessel / Aircraft / Truck Registration" />
                      <T k="voyage_flight_trip_no" l="Voyage No. / Flight No. / Trip No." />
                      <T k="date_of_departure" l="Date of Sailing / Departure" type="date" />
                      <T k="date_of_arrival" l="Date of Arrival at Destination" type="date" />
                      <T k="bill_of_lading_number" l="Bill of Lading / Waybill Number" />
                      <T k="carrier" l="Carrier" />
                      <A k="freight_forwarder" l="Freight Forwarder / Clearing Agent (Name & Contact)" />
                    </div>
                    <SectionHeader label="Loss / Damage Details" />
                    <div className="grid grid-cols-3 gap-3">
                      <T k="date_of_loss" l="Date of Loss / Damage" type="date" />
                      <T k="date_loss_discovered" l="Date Loss Discovered" type="date" />
                      <T k="date_ad_notified" l="Date Alpha Direct Notified" type="date" />
                    </div>
                    <div className="grid grid-cols-2 gap-3"><A k="place_stage_of_loss" l="Place / Stage at which Loss Occurred" /></div>
                    <label className="block text-xs font-medium text-ink-faint mt-2">Type of Loss (tick all)</label>
                    <div className="grid grid-cols-2 gap-2">
                      <MChk k="loss_shortage" l="Shortage" />
                      <MChk k="loss_pilferage" l="Pilferage / Theft" />
                      <MChk k="loss_non_delivery" l="Non-Delivery" />
                      <MChk k="loss_damage_handling" l="Damage (handling / impact)" />
                      <MChk k="loss_wet_seawater" l="Wet damage / sea water" />
                      <MChk k="loss_freshwater" l="Fresh water damage" />
                      <MChk k="loss_fire_explosion" l="Fire / explosion" />
                      <MChk k="loss_hijacking" l="Hijacking / armed robbery" />
                      <MChk k="loss_sea_perils" l="Sea perils" />
                      <MChk k="loss_other" l="Other" />
                    </div>
                    <div className="grid grid-cols-2 gap-3 mt-2">
                      <A k="loss_description" l="Description of Loss / Damage (which items, extent of loss)" rows={4} />
                      <T k="estimated_value_of_loss" l="Estimated Value of Loss (BWP)" type="number" />
                    </div>
                    <SectionHeader label="Carrier Notice, Survey & Recovery" />
                    <div className="grid grid-cols-2 gap-3">
                      <YN k="notice_of_loss_issued" l="Notice of Loss issued to carrier in writing?" />
                      <YN k="carrier_acknowledged" l="Did the carrier acknowledge or reply?" />
                      <A k="notice_of_loss_details" l="Notice of Loss — Details" />
                      <A k="notice_date_reference" l="Date Notice issued and reference" />
                      <A k="carrier_acknowledged_details" l="Carrier acknowledgement — Details" />
                      <A k="carrier_reply_reference" l="Carrier reply reference / date" />
                      <YN k="joint_survey_held" l="Was a joint survey held with the carrier?" />
                      <YN k="survey_report_attached" l="Survey report attached?" />
                      <A k="joint_survey_details" l="Joint survey — Details" />
                      <A k="surveyor_agent" l="Surveyor / Lloyd's Agent (Name & Contact)" />
                      <A k="survey_report_details" l="Survey report — Details" />
                      <YN k="police_report_attached" l="Police / port / customs report attached?" />
                      <YN k="recovery_claim_lodged" l="Recovery claim lodged with carrier / forwarder?" />
                      <A k="police_report_details" l="Police / customs report — Details" />
                      <T k="police_station_ob" l="Police Station and OB Reference (theft / hijack only)" />
                      <A k="recovery_claim_details" l="Recovery claim — Details" />
                      <A k="carrier_name_address" l="Carrier Name and Address (for AD subrogation)" />
                    </div>
                    <SectionHeader label="Financier, Other Insurance & Loss History" />
                    <div className="grid grid-cols-2 gap-3">
                      <YN k="goods_financed" l="Goods financed under letter of credit or bank loan?" />
                      <YN k="other_insurance" l="Goods insured under any other policy?" />
                      <A k="goods_financed_details" l="Goods financed — Details" />
                      <A k="bank_financier_reference" l="Bank / Financier name and reference" />
                      <A k="other_insurance_details" l="Other insurance — Details" />
                      <A k="other_insurer_policy" l="Other Insurer / Policy Number / Sum Insured" />
                      <A k="loss_history" l="Loss history — prior marine claims in past 24 months" rows={3} />
                      <A k="procedural_improvements" l="Procedural improvements proposed (packing, route, carrier)" rows={3} />
                    </div>
                    <SectionHeader label="Declaration & Subrogation" />
                    <div className="grid grid-cols-2 gap-3">
                      <T k="declaration_name" l="Name" />
                      <T k="declaration_capacity" l="Capacity" />
                      <T k="declaration_date" l="Date" type="date" />
                    </div>
                  </>
                  )
                }
                if (ctKey === 'MARINECARGOOPENCOVER') {
                  const MChk = ({ k, l }: { k: string; l: string }) => (
                    <label className="flex items-center gap-2 text-sm cursor-pointer">
                      <input type="checkbox"
                        checked={!!editForm[k] && editForm[k] !== '0'}
                        onChange={e => setEditForm({ ...editForm, [k]: e.target.checked as any })}
                        className="rounded" /> {l}
                    </label>
                  )
                  const YN = ({ k, l }: { k: string; l: string }) => (
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">{l}</label>
                      <select value={String(editForm[k] ?? '')} onChange={e => setEditForm({...editForm, [k]: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                        <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                      </select></div>
                  )
                  const T = ({ k, l, type = 'text' }: { k: string; l: string; type?: string }) => (
                    <div><label className="block text-xs font-medium text-ink-faint mb-1">{l}</label>
                      <input type={type} value={editForm[k] || ''} onChange={e => setEditForm({...editForm, [k]: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                  )
                  const A = ({ k, l, rows = 2 }: { k: string; l: string; rows?: number }) => (
                    <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">{l}</label>
                      <textarea value={editForm[k] || ''} onChange={e => setEditForm({...editForm, [k]: e.target.value})} rows={rows} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                  )
                  return (
                  <>
                    <SectionHeader label="Policy & Open Cover Details" />
                    <div className="grid grid-cols-2 gap-3">
                      <T k="open_cover_policy_number" l="Open Cover Policy Number" />
                      <T k="insured" l="Insured (Cover Holder)" />
                      <T k="period_of_insurance" l="Period of Insurance" />
                      <T k="annual_aggregate_sum_insured" l="Annual Aggregate Sum Insured (BWP)" type="number" />
                      <T k="certificate_declaration_number" l="Certificate / Declaration Number for this Consignment" />
                      <T k="date_of_declaration" l="Date of Declaration" type="date" />
                      <T k="insured_value_declared" l="Insured Value declared (CIF + 10%)" type="number" />
                    </div>
                    <SectionHeader label="Insured Contact & Business" />
                    <div className="grid grid-cols-2 gap-3">
                      <T k="contact_person" l="Contact Person" />
                      <T k="designation" l="Designation" />
                      <T k="phone" l="Phone" />
                      <T k="cellphone" l="Cellphone" />
                      <T k="email" l="Email" type="email" />
                      <A k="postal_physical_address" l="Postal / Physical Address" />
                      <A k="nature_of_business" l="Nature of Business / Industry" />
                    </div>
                    <SectionHeader label="Consignment / Cargo Details" />
                    <div className="grid grid-cols-2 gap-3">
                      <A k="description_of_goods" l="Description of Goods" />
                      <T k="number_type_packages" l="Number and Type of Packages" />
                      <T k="marks_numbers" l="Marks & Numbers" />
                      <T k="gross_weight" l="Gross Weight (kg)" />
                      <T k="net_weight" l="Net Weight (kg)" />
                      <T k="commercial_invoice_number" l="Commercial Invoice Number" />
                      <T k="invoice_value" l="Invoice Value (currency + amount)" />
                      <T k="cif_value" l="CIF Value (BWP)" type="number" />
                      <T k="container_number" l="Container Number (if containerised)" />
                      <T k="seal_numbers" l="Seal Number(s)" />
                    </div>
                    <SectionHeader label="Voyage / Transit Details" />
                    <label className="block text-xs font-medium text-ink-faint">Mode of transport (tick all legs)</label>
                    <div className="grid grid-cols-3 gap-2">
                      <MChk k="mode_sea" l="Sea" />
                      <MChk k="mode_air" l="Air" />
                      <MChk k="mode_road" l="Road" />
                      <MChk k="mode_rail" l="Rail" />
                      <MChk k="mode_multimodal" l="Multimodal" />
                    </div>
                    <div className="grid grid-cols-2 gap-3 mt-2">
                      <A k="multimodal_route_description" l="Multimodal route description (if applicable)" />
                      <A k="origin" l="Origin (City, Country, Port / Airport)" />
                      <A k="destination" l="Destination (City, Country, Final Warehouse Address)" />
                      <T k="vessel_aircraft_truck_reg" l="Vessel / Aircraft / Truck Registration" />
                      <T k="voyage_flight_trip_no" l="Voyage No. / Flight No. / Trip No." />
                      <T k="date_of_departure" l="Date of Sailing / Departure" type="date" />
                      <T k="date_of_arrival" l="Date of Arrival at Destination" type="date" />
                      <T k="bill_of_lading_number" l="Bill of Lading / Waybill Number" />
                      <T k="carrier" l="Carrier" />
                      <A k="freight_forwarder" l="Freight Forwarder / Clearing Agent (Name & Contact)" />
                    </div>
                    <SectionHeader label="Loss / Damage Details" />
                    <div className="grid grid-cols-2 gap-3">
                      <T k="date_of_loss" l="Date of Loss / Damage" type="date" />
                      <T k="date_loss_discovered" l="Date Loss Discovered" type="date" />
                      <A k="place_stage_of_loss" l="Place / Stage at which Loss Occurred (port, in-transit, customs, warehouse)" />
                    </div>
                    <label className="block text-xs font-medium text-ink-faint mt-2">Type of Loss (tick all)</label>
                    <div className="grid grid-cols-2 gap-2">
                      <MChk k="loss_shortage" l="Shortage" />
                      <MChk k="loss_pilferage" l="Pilferage / Theft" />
                      <MChk k="loss_non_delivery" l="Non-Delivery" />
                      <MChk k="loss_damage_handling" l="Damage (handling / impact)" />
                      <MChk k="loss_wet_seawater" l="Wet damage / sea water" />
                      <MChk k="loss_freshwater" l="Fresh water damage" />
                      <MChk k="loss_fire_explosion" l="Fire / explosion" />
                      <MChk k="loss_hijacking" l="Hijacking / armed robbery" />
                      <MChk k="loss_sea_perils" l="Sea perils" />
                      <MChk k="loss_other" l="Other" />
                    </div>
                    <div className="grid grid-cols-2 gap-3 mt-2">
                      <A k="loss_description" l="Description of Loss / Damage (which items, extent of loss)" rows={4} />
                      <T k="estimated_value_of_loss" l="Estimated Value of Loss (BWP)" type="number" />
                    </div>
                    <SectionHeader label="Carrier Notice, Survey & Recovery" />
                    <div className="grid grid-cols-2 gap-3">
                      <YN k="notice_of_loss_issued" l="Notice of Loss issued to carrier in writing?" />
                      <YN k="carrier_acknowledged" l="Did the carrier acknowledge or reply?" />
                      <A k="notice_of_loss_details" l="Notice of Loss — Details" />
                      <A k="notice_date_reference" l="Date Notice issued and reference" />
                      <A k="carrier_acknowledged_details" l="Carrier acknowledgement — Details" />
                      <A k="carrier_reply_reference" l="Carrier reply reference / date" />
                      <YN k="joint_survey_held" l="Was a joint survey held with the carrier?" />
                      <YN k="survey_report_attached" l="Survey report attached?" />
                      <A k="joint_survey_details" l="Joint survey — Details" />
                      <A k="surveyor_agent" l="Surveyor / Lloyd's Agent (Name & Contact)" />
                      <A k="survey_report_details" l="Survey report — Details" />
                      <YN k="police_report_attached" l="Police / port / customs report attached?" />
                      <YN k="recovery_claim_lodged" l="Recovery claim lodged with carrier / forwarder?" />
                      <A k="police_report_details" l="Police / customs report — Details" />
                      <T k="police_station_ob" l="Police Station and OB Reference (theft / hijack only)" />
                      <A k="recovery_claim_details" l="Recovery claim — Details" />
                      <A k="carrier_name_address" l="Carrier Name and Address (for AD subrogation)" />
                    </div>
                    <SectionHeader label="Financier, Other Insurance & Loss History" />
                    <div className="grid grid-cols-2 gap-3">
                      <YN k="goods_financed" l="Goods financed under letter of credit or bank loan?" />
                      <YN k="other_insurance" l="Goods insured under any other policy?" />
                      <A k="goods_financed_details" l="Goods financed — Details" />
                      <A k="bank_financier_reference" l="Bank / Financier name and reference" />
                      <A k="other_insurance_details" l="Other insurance — Details" />
                      <A k="other_insurer_policy" l="Other Insurer / Policy Number / Sum Insured" />
                      <A k="loss_history" l="Loss History — claims under this Open Cover in past 24 months" rows={3} />
                      <A k="procedural_improvements" l="Procedural improvements proposed (packing, route, carrier)" rows={3} />
                    </div>
                    <SectionHeader label="Declaration & Subrogation" />
                    <div className="grid grid-cols-2 gap-3">
                      <T k="declaration_name" l="Name" />
                      <T k="declaration_capacity" l="Capacity" />
                      <T k="declaration_date" l="Date" type="date" />
                    </div>
                  </>
                  )
                }
                if (ctKey === 'GLASS') return (
                  <>
                    {/* Glass / Windscreen — V8 parity + V2 extensions.
                        Global Edit modal does NOT render the 6 file
                        inputs; those live in the per-section sub-claim
                        Edit flow above. Only text fields ship here. */}
                    <SectionHeader label="Glass / Windscreen — Damage Details" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Date of Damage</label>
                        <input type="date" value={editForm.incident_date || ''} onChange={e => setEditForm({...editForm, incident_date: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Damage Extent</label>
                        <select value={editForm.extent || ''} onChange={e => setEditForm({...editForm, extent: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option>
                          <option value="Cracked">Cracked</option>
                          <option value="Shattered">Shattered</option>
                        </select></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Damage Location</label>
                        <select value={editForm.damage_location || ''} onChange={e => setEditForm({...editForm, damage_location: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option>
                          <option value="windscreen">Windscreen</option>
                          <option value="rear">Rear Glass</option>
                          <option value="side">Side Window</option>
                        </select></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Cause of Damage</label>
                        <textarea value={editForm.cause || ''} onChange={e => setEditForm({...editForm, cause: e.target.value})} rows={3} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Replacement Quotes" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Quote 1 — Company</label>
                        <input value={editForm.company_1 || ''} onChange={e => setEditForm({...editForm, company_1: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Quote 1 — Amount</label>
                        <input type="number" value={editForm.amount_quote_1 || ''} onChange={e => setEditForm({...editForm, amount_quote_1: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Quote 2 — Company</label>
                        <input value={editForm.company_2 || ''} onChange={e => setEditForm({...editForm, company_2: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Quote 2 — Amount</label>
                        <input type="number" value={editForm.amount_quote_2 || ''} onChange={e => setEditForm({...editForm, amount_quote_2: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Damage Photo Descriptions" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Front — Description</label>
                        <textarea value={editForm.front_image_description || ''} onChange={e => setEditForm({...editForm, front_image_description: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Back — Description</label>
                        <textarea value={editForm.back_image_description || ''} onChange={e => setEditForm({...editForm, back_image_description: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Right — Description</label>
                        <textarea value={editForm.right_image_description || ''} onChange={e => setEditForm({...editForm, right_image_description: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Left — Description</label>
                        <textarea value={editForm.left_image_description || ''} onChange={e => setEditForm({...editForm, left_image_description: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                  </>
                )
                if (ctKey === 'MEDICALMALPRACTICE') return (
                  <>
                    {/* Medical Malpractice — V8 parity. Global Edit modal
                        does NOT render the 5 Section-4 file inputs;
                        those live in the per-section sub-claim Edit
                        flow above. Only text fields ship here. */}
                    <SectionHeader label="Medical Malpractice — Section 1: Insured Party Information" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Full Name of Insured</label>
                        <input value={editForm.insured_full_name || ''} onChange={e => setEditForm({...editForm, insured_full_name: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Professional Title / Role</label>
                        <input value={editForm.professional_title_role || ''} onChange={e => setEditForm({...editForm, professional_title_role: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">License or Registration Number</label>
                        <input value={editForm.license_registration_number || ''} onChange={e => setEditForm({...editForm, license_registration_number: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Facility / Practice Name</label>
                        <input value={editForm.facility_practice_name || ''} onChange={e => setEditForm({...editForm, facility_practice_name: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Contact Number</label>
                        <input value={editForm.insured_contact_number || ''} onChange={e => setEditForm({...editForm, insured_contact_number: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Email Address</label>
                        <input type="email" value={editForm.insured_email || ''} onChange={e => setEditForm({...editForm, insured_email: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Address of Practice</label>
                        <textarea value={editForm.address_of_practice || ''} onChange={e => setEditForm({...editForm, address_of_practice: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Section 2: Claimant (Patient) Information" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Full Name</label>
                        <input value={editForm.claimant_full_name || ''} onChange={e => setEditForm({...editForm, claimant_full_name: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Date of Birth</label>
                        <input type="date" value={editForm.claimant_date_of_birth || ''} onChange={e => setEditForm({...editForm, claimant_date_of_birth: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Contact Number</label>
                        <input value={editForm.claimant_contact_number || ''} onChange={e => setEditForm({...editForm, claimant_contact_number: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Mailing Address</label>
                        <textarea value={editForm.claimant_mailing_address || ''} onChange={e => setEditForm({...editForm, claimant_mailing_address: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Section 3: Details of Allegation" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Date of Alleged Incident</label>
                        <input type="date" value={editForm.date_of_alleged_incident || ''} onChange={e => setEditForm({...editForm, date_of_alleged_incident: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Date of Notification of Allegation</label>
                        <input type="date" value={editForm.date_of_notification || ''} onChange={e => setEditForm({...editForm, date_of_notification: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">How were you notified? (letter, legal notice)</label>
                        <input value={editForm.how_notified || ''} onChange={e => setEditForm({...editForm, how_notified: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Nature of Services Provided</label>
                        <textarea value={editForm.nature_of_services_provided || ''} onChange={e => setEditForm({...editForm, nature_of_services_provided: e.target.value})} rows={3} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Detailed Description of Allegation</label>
                        <textarea value={editForm.description_of_allegation || ''} onChange={e => setEditForm({...editForm, description_of_allegation: e.target.value})} rows={4} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                  </>
                )
                if (ctKey === 'CONTRACTORSALLRISKS' || ctKey === 'CONTRACTORSALLRISKSPUBLICLIABILITY' || ctKey === 'CARPL') return (
                  <>
                    <SectionHeader label="Contractors All Risks — Responsible Person" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Name</label>
                        <input value={editForm.responsible_person_name || ''} onChange={e => setEditForm({...editForm, responsible_person_name: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Phone</label>
                        <input value={editForm.responsible_person_phone || ''} onChange={e => setEditForm({...editForm, responsible_person_phone: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Cellphone</label>
                        <input value={editForm.responsible_person_cellphone || ''} onChange={e => setEditForm({...editForm, responsible_person_cellphone: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Email</label>
                        <input type="email" value={editForm.responsible_person_email || ''} onChange={e => setEditForm({...editForm, responsible_person_email: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Fax</label>
                        <input value={editForm.responsible_person_fax || ''} onChange={e => setEditForm({...editForm, responsible_person_fax: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Contract Details" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Parties to the Contract</label>
                        <input value={editForm.parties_to_contract || ''} onChange={e => setEditForm({...editForm, parties_to_contract: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Contract Value (BWP)</label>
                        <input type="number" value={editForm.contract_value || ''} onChange={e => setEditForm({...editForm, contract_value: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Contract Number</label>
                        <input value={editForm.contract_number || ''} onChange={e => setEditForm({...editForm, contract_number: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Code</label>
                        <input value={editForm.code || ''} onChange={e => setEditForm({...editForm, code: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Contract Commencement Date</label>
                        <input type="date" value={editForm.contract_commencement_date || ''} onChange={e => setEditForm({...editForm, contract_commencement_date: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Expected Contract Completion Date</label>
                        <input type="date" value={editForm.expected_contract_completion_date || ''} onChange={e => setEditForm({...editForm, expected_contract_completion_date: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Description of Contract</label>
                        <textarea value={editForm.description_of_contract || ''} onChange={e => setEditForm({...editForm, description_of_contract: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Site Physical Address</label>
                        <textarea value={editForm.site_physical_address || ''} onChange={e => setEditForm({...editForm, site_physical_address: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Insurance Responsibility" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Responsible for arranging Project Insurance (Contract Works)?</label>
                        <select value={String(editForm.responsible_contract_works_claim ?? '')} onChange={e => setEditForm({...editForm, responsible_contract_works_claim: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Responsible for arranging Public Liability Insurance?</label>
                        <select value={String(editForm.responsible_public_liability_claim ?? '')} onChange={e => setEditForm({...editForm, responsible_public_liability_claim: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                    </div>
                    <SectionHeader label="Loss / Damage Details" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Date of Loss / Damage</label>
                        <input type="date" value={editForm.loss_date || ''} onChange={e => setEditForm({...editForm, loss_date: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Time</label>
                        <input type="time" value={editForm.loss_time || ''} onChange={e => setEditForm({...editForm, loss_time: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Details of Loss / Damage</label>
                        <textarea value={editForm.loss_details || ''} onChange={e => setEditForm({...editForm, loss_details: e.target.value})} rows={3} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Cause of Loss / Damage</label>
                        <textarea value={editForm.cause_of_loss || ''} onChange={e => setEditForm({...editForm, cause_of_loss: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Party Responsible — Name</label>
                        <input value={editForm.party_responsible_name || ''} onChange={e => setEditForm({...editForm, party_responsible_name: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Party Responsible — Contact</label>
                        <input value={editForm.party_responsible_contact || ''} onChange={e => setEditForm({...editForm, party_responsible_contact: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Estimated Cost of Repair / Replacement (BWP)</label>
                        <input type="number" value={editForm.estimated_cost_of_repair_replacement || ''} onChange={e => setEditForm({...editForm, estimated_cost_of_repair_replacement: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Police Station (theft only)</label>
                        <input value={editForm.police_station || ''} onChange={e => setEditForm({...editForm, police_station: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Reference (theft only)</label>
                        <input value={editForm.police_reference || ''} onChange={e => setEditForm({...editForm, police_reference: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                  </>
                )
                if (ctKey === 'PROPERTYLOSSDAMAGE' || ctKey === 'PROPERTYDAMAGE'
                    || ctKey === 'BUILDINGSCOMBINED' || ctKey === 'ACCIDENTALDAMAGE'
                    || ctKey === 'HOUSEHOLDERS' || ctKey === 'HOUSEOWNERS'
                    || ctKey === 'HOUSEOWNERBUILDINGS' || ctKey === 'HOUSEHOLDERSCONTENTS') return (
                  <>
                    <SectionHeader label="Property Loss / Damage" />
                    <div className="grid grid-cols-1 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">When was loss/damage discovered?</label>
                        <textarea value={editForm.loss_damage_discovered || ''} onChange={e => setEditForm({...editForm, loss_damage_discovered: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Place where loss/damage occurred</label>
                        <textarea value={editForm.loss_damage_occurred || ''} onChange={e => setEditForm({...editForm, loss_damage_occurred: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Were premises occupied? By whom?</label>
                        <textarea value={editForm.premises_occupied || ''} onChange={e => setEditForm({...editForm, premises_occupied: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">If not occupied, when last occupied?</label>
                        <textarea value={editForm.last_occupied || ''} onChange={e => setEditForm({...editForm, last_occupied: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Purpose of occupation</label>
                        <textarea value={editForm.purpose_of_occupation || ''} onChange={e => setEditForm({...editForm, purpose_of_occupation: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Nature of your interruption</label>
                        <textarea value={editForm.nature_interruption || ''} onChange={e => setEditForm({...editForm, nature_interruption: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Details & estimated amount of loss for each item</label>
                        <textarea value={editForm.loss_for_each_item || ''} onChange={e => setEditForm({...editForm, loss_for_each_item: e.target.value})} rows={3} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Have you previously suffered loss/damage?</label>
                        <textarea value={editForm.previously_suffered_loss || ''} onChange={e => setEditForm({...editForm, previously_suffered_loss: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">If so, give details</label>
                        <textarea value={editForm.give_details || ''} onChange={e => setEditForm({...editForm, give_details: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">If insured, provide name of insurer</label>
                        <textarea value={editForm.name_of_insurer || ''} onChange={e => setEditForm({...editForm, name_of_insurer: e.target.value})} rows={1} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Police reference number, station and date reported</label>
                        <textarea value={editForm.reference_no_station || ''} onChange={e => setEditForm({...editForm, reference_no_station: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Any other party with an interest in the insured property?</label>
                        <textarea value={editForm.interest_insured_property || ''} onChange={e => setEditForm({...editForm, interest_insured_property: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Any other insurance covering this loss/damage?</label>
                        <textarea value={editForm.other_insurance_covering || ''} onChange={e => setEditForm({...editForm, other_insurance_covering: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">If so, give name of insurer</label>
                        <textarea value={editForm.give_name_insurer || ''} onChange={e => setEditForm({...editForm, give_name_insurer: e.target.value})} rows={1} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Estimated total value of all property insured</label>
                        <textarea value={editForm.value_all_property || ''} onChange={e => setEditForm({...editForm, value_all_property: e.target.value})} rows={1} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">When last valued?</label>
                        <textarea value={editForm.when_last_valued || ''} onChange={e => setEditForm({...editForm, when_last_valued: e.target.value})} rows={1} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                  </>
                )
                if (ctKey === 'PUBLICLIABILITY' || ctKey === 'LIABILITY') return (
                  <>
                    <SectionHeader label="Public Liability — Insured's Details" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Name</label>
                        <input value={editForm.insured_name || ''} onChange={e => setEditForm({...editForm, insured_name: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Business or Trading name</label>
                        <input value={editForm.insured_treding_name || ''} onChange={e => setEditForm({...editForm, insured_treding_name: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Postal Address</label>
                        <input value={editForm.insured_postal_address || ''} onChange={e => setEditForm({...editForm, insured_postal_address: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Email address</label>
                        <input type="email" value={editForm.insured_email || ''} onChange={e => setEditForm({...editForm, insured_email: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Telephone no</label>
                        <input value={editForm.insured_telephone_no || ''} onChange={e => setEditForm({...editForm, insured_telephone_no: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Facsimile</label>
                        <input value={editForm.insured_facsimile || ''} onChange={e => setEditForm({...editForm, insured_facsimile: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Mobile no</label>
                        <input value={editForm.insured_mobile_no || ''} onChange={e => setEditForm({...editForm, insured_mobile_no: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Details of the Accident / Incident" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Date</label>
                        <input type="date" value={editForm.accident_date || ''} onChange={e => setEditForm({...editForm, accident_date: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Time</label>
                        <input type="time" value={editForm.accident_time || ''} onChange={e => setEditForm({...editForm, accident_time: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Location of accident / incident</label>
                        <textarea value={editForm.accident_incident || ''} onChange={e => setEditForm({...editForm, accident_incident: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Details of damaged property and/or injuries suffered</label>
                        <textarea value={editForm.accident_injuries || ''} onChange={e => setEditForm({...editForm, accident_injuries: e.target.value})} rows={3} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Have you admitted responsibility / liability?</label>
                        <select value={editForm.accident_liability || ''} onChange={e => setEditForm({...editForm, accident_liability: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="YES">Yes</option><option value="NO">No</option>
                        </select></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Product/service-related claim?</label>
                        <select value={editForm.accident_person || ''} onChange={e => setEditForm({...editForm, accident_person: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="YES">Yes</option><option value="NO">No</option>
                        </select></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Were emergency services contacted?</label>
                        <select value={editForm.accident_contacted || ''} onChange={e => setEditForm({...editForm, accident_contacted: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="YES">Yes</option><option value="NO">No</option>
                        </select></div>
                    </div>
                    <SectionHeader label="Job at which the accident occurred" />
                    <div className="grid grid-cols-2 gap-3">
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Are you the head contractor? If not, who is?</label>
                        <input value={editForm.attach_contractor || ''} onChange={e => setEditForm({...editForm, attach_contractor: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Was anyone other than yourself or employee involved?</label>
                        <input value={editForm.attach_employee || ''} onChange={e => setEditForm({...editForm, attach_employee: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">If so, give names, addresses and state by whom employed</label>
                        <textarea value={editForm.attach_employed || ''} onChange={e => setEditForm({...editForm, attach_employed: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Do you think you or any of your employee(s) was to blame?</label>
                        <input value={editForm.attach_blame || ''} onChange={e => setEditForm({...editForm, attach_blame: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Has any other accident occurred under similar circumstances?</label>
                        <textarea value={editForm.attach_circumstances || ''} onChange={e => setEditForm({...editForm, attach_circumstances: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Was there any damage to property?</label>
                        <input value={editForm.attach_property || ''} onChange={e => setEditForm({...editForm, attach_property: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">If so, please give details</label>
                        <textarea value={editForm.attach_details || ''} onChange={e => setEditForm({...editForm, attach_details: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Name and address of property owner</label>
                        <textarea value={editForm.attach_owner || ''} onChange={e => setEditForm({...editForm, attach_owner: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Damage</label>
                        <textarea value={editForm.attach_damage || ''} onChange={e => setEditForm({...editForm, attach_damage: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Party Making Claim Against You" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Name</label>
                        <input value={editForm.claim_name || ''} onChange={e => setEditForm({...editForm, claim_name: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Telephone</label>
                        <input value={editForm.claim_telephone || ''} onChange={e => setEditForm({...editForm, claim_telephone: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Mobile no</label>
                        <input value={editForm.claim_mobile || ''} onChange={e => setEditForm({...editForm, claim_mobile: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Postal address</label>
                        <input value={editForm.claim_postal || ''} onChange={e => setEditForm({...editForm, claim_postal: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Solicitor's name</label>
                        <input value={editForm.claim_solicitor || ''} onChange={e => setEditForm({...editForm, claim_solicitor: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Witness 1" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Name</label>
                        <input value={editForm.witness1_name || ''} onChange={e => setEditForm({...editForm, witness1_name: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Telephone no</label>
                        <input value={editForm.witness1_telephone || ''} onChange={e => setEditForm({...editForm, witness1_telephone: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Mobile no</label>
                        <input value={editForm.witness1_mobile || ''} onChange={e => setEditForm({...editForm, witness1_mobile: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Postal address</label>
                        <input value={editForm.witness1_postal || ''} onChange={e => setEditForm({...editForm, witness1_postal: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Relationship (employee/family/friend)</label>
                        <input value={editForm.witness1_relationship || ''} onChange={e => setEditForm({...editForm, witness1_relationship: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Witness 2" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Name</label>
                        <input value={editForm.witness2_name || ''} onChange={e => setEditForm({...editForm, witness2_name: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Telephone no</label>
                        <input value={editForm.witness2_telephone || ''} onChange={e => setEditForm({...editForm, witness2_telephone: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Mobile no</label>
                        <input value={editForm.witness2_mobile || ''} onChange={e => setEditForm({...editForm, witness2_mobile: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Postal address</label>
                        <input value={editForm.witness2_postal || ''} onChange={e => setEditForm({...editForm, witness2_postal: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Relationship</label>
                        <input value={editForm.witness2_relationship || ''} onChange={e => setEditForm({...editForm, witness2_relationship: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Was there any damage to property?</label>
                        <input value={editForm.witness2_damage || ''} onChange={e => setEditForm({...editForm, witness2_damage: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                  </>
                )
                if (ctKey === 'WORKERSCOMPENSATION' || ctKey === 'STATEDBENEFITS') return (
                  <>
                    <SectionHeader label={ctKey === 'STATEDBENEFITS' ? 'Stated Benefits — The Injured Person' : 'Workers Compensation — The Injured Person'} />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Name</label>
                        <input value={editForm.injured_name || ''} onChange={e => setEditForm({...editForm, injured_name: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Age</label>
                        <input type="number" value={editForm.injured_age || ''} onChange={e => setEditForm({...editForm, injured_age: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Normal Occupation</label>
                        <input value={editForm.injured_occupation || ''} onChange={e => setEditForm({...editForm, injured_occupation: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Nationality</label>
                        <input value={editForm.injured_nationality || ''} onChange={e => setEditForm({...editForm, injured_nationality: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Status</label>
                        <select value={editForm.injured_status || ''} onChange={e => setEditForm({...editForm, injured_status: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="Married">Married</option><option value="Single">Single</option>
                        </select></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Period of service</label>
                        <input value={editForm.injured_service_period || ''} onChange={e => setEditForm({...editForm, injured_service_period: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Is he/she in your direct employ?</label>
                        <select value={editForm.your_direct_employ ?? ''} onChange={e => setEditForm({...editForm, your_direct_employ: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Address</label>
                        <textarea value={editForm.injured_address || ''} onChange={e => setEditForm({...editForm, injured_address: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      {String(editForm.your_direct_employ) === '0' && (
                        <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">If not, give name and address of Contractor</label>
                          <textarea value={editForm.address_of_contractor || ''} onChange={e => setEditForm({...editForm, address_of_contractor: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      )}
                    </div>
                    <SectionHeader label="The Accident" />
                    <div className="grid grid-cols-3 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Date</label>
                        <input type="date" value={editForm.date || ''} onChange={e => setEditForm({...editForm, date: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Time</label>
                        <input type="time" value={editForm.time || ''} onChange={e => setEditForm({...editForm, time: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Place</label>
                        <input value={editForm.place || ''} onChange={e => setEditForm({...editForm, place: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-3"><label className="block text-xs font-medium text-ink-faint mb-1">How did the accident occur?</label>
                        <input value={editForm.how_accident_occur || ''} onChange={e => setEditForm({...editForm, how_accident_occur: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-3"><label className="block text-xs font-medium text-ink-faint mb-1">When and to whom did he/she first report the accident?</label>
                        <input value={editForm.first_report_accident || ''} onChange={e => setEditForm({...editForm, first_report_accident: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-3"><label className="block text-xs font-medium text-ink-faint mb-1">Probable period of disablement in your opinion</label>
                        <input value={editForm.period_of_disablement || ''} onChange={e => setEditForm({...editForm, period_of_disablement: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                  </>
                )
                if (ctKey === 'DEFECTIVEWORKMANSHIP') return (
                  <>
                    <SectionHeader label="Defective Workmanship — Details of the Accident / Incident" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Location of accident / incident</label>
                        <input value={editForm.location_of_accident || ''} onChange={e => setEditForm({...editForm, location_of_accident: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Accident Date Time</label>
                        <input type="datetime-local" value={editForm.accident_date_time || ''} onChange={e => setEditForm({...editForm, accident_date_time: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Owner's Name</label>
                        <input value={editForm.owners_name || ''} onChange={e => setEditForm({...editForm, owners_name: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Telephone Number</label>
                        <input value={editForm.telephone_number || ''} onChange={e => setEditForm({...editForm, telephone_number: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Mobile Number</label>
                        <input value={editForm.mobile_number || ''} onChange={e => setEditForm({...editForm, mobile_number: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Address</label>
                        <textarea value={editForm.address || ''} onChange={e => setEditForm({...editForm, address: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Claimant's Vehicle" />
                    <div className="grid grid-cols-3 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Make</label>
                        <input value={editForm.make || ''} onChange={e => setEditForm({...editForm, make: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Model</label>
                        <input value={editForm.model || ''} onChange={e => setEditForm({...editForm, model: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Registration</label>
                        <input value={editForm.registration || ''} onChange={e => setEditForm({...editForm, registration: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-3"><label className="block text-xs font-medium text-ink-faint mb-1">Is the vehicle drivable?</label>
                        <select value={String(editForm.vehicle_drivable ?? '')} onChange={e => setEditForm({...editForm, vehicle_drivable: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                    </div>
                    <SectionHeader label="Incident Details" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Was the vehicle handed to the claimant?</label>
                        <select value={String(editForm.vehicle_handed_claimant ?? '')} onChange={e => setEditForm({...editForm, vehicle_handed_claimant: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">When was the vehicle handed?</label>
                        <input type="datetime-local" value={editForm.when_vehicle_handed || ''} onChange={e => setEditForm({...editForm, when_vehicle_handed: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Date and allegations received from claimant</label>
                        <textarea value={editForm.allegations_received || ''} onChange={e => setEditForm({...editForm, allegations_received: e.target.value})} rows={3} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                  </>
                )
                if (ctKey === 'FIRE') return (
                  <>
                    <SectionHeader label="Fire — Details" />
                    <div className="grid grid-cols-2 gap-3">
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Address of premises where fire occurred</label>
                        <textarea value={editForm.address_of_theft_occurred || ''} onChange={e => setEditForm({...editForm, address_of_theft_occurred: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">When was the property last seen?</label>
                        <input value={editForm.property_last_seen || ''} onChange={e => setEditForm({...editForm, property_last_seen: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Date and time of fire</label>
                        <input type="datetime-local" value={editForm.date_time_of_theft || ''} onChange={e => setEditForm({...editForm, date_time_of_theft: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Brief description of incident</label>
                        <textarea value={editForm.brief_description_incident || ''} onChange={e => setEditForm({...editForm, brief_description_incident: e.target.value})} rows={3} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Date/time police advised of loss</label>
                        <input type="datetime-local" value={editForm.date_time_police_advised || ''} onChange={e => setEditForm({...editForm, date_time_police_advised: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Name of police station</label>
                        <input value={editForm.police_station_name || ''} onChange={e => setEditForm({...editForm, police_station_name: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Was anyone at the premises during the loss?</label>
                        <select value={String(editForm.anyone_during_burglary ?? '')} onChange={e => setEditForm({...editForm, anyone_during_burglary: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Days premises unoccupied in past 12 months</label>
                        <input value={editForm.days_premises_unoccupied || ''} onChange={e => setEditForm({...editForm, days_premises_unoccupied: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      {String(editForm.anyone_during_burglary) === '1' && (
                        <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Details in brief</label>
                          <textarea value={editForm.details_during_burglary || ''} onChange={e => setEditForm({...editForm, details_during_burglary: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      )}
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Is premises guarded by a watchman?</label>
                        <select value={String(editForm.premises_guarded_by_watchman ?? '')} onChange={e => setEditForm({...editForm, premises_guarded_by_watchman: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Were all means of access properly secured at time of fire?</label>
                        <select value={String(editForm.premises_properly_secured ?? '')} onChange={e => setEditForm({...editForm, premises_properly_secured: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      {String(editForm.premises_guarded_by_watchman) === '1' && (
                        <>
                          <div><label className="block text-xs font-medium text-ink-faint mb-1">Name of guard</label>
                            <input value={editForm.name_of_guard || ''} onChange={e => setEditForm({...editForm, name_of_guard: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                          <div><label className="block text-xs font-medium text-ink-faint mb-1">Telephone number of guard</label>
                            <input value={editForm.telephone_of_guard || ''} onChange={e => setEditForm({...editForm, telephone_of_guard: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                          <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Where was the guard during the fire?</label>
                            <textarea value={editForm.guard_during_fire || ''} onChange={e => setEditForm({...editForm, guard_during_fire: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                        </>
                      )}
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Name of security agent</label>
                        <input value={editForm.name_of_security_agent || ''} onChange={e => setEditForm({...editForm, name_of_security_agent: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Do you suspect any person?</label>
                        <select value={String(editForm.suspect_any_person ?? '')} onChange={e => setEditForm({...editForm, suspect_any_person: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      {String(editForm.suspect_any_person) === '1' && (
                        <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Give details of suspect person</label>
                          <textarea value={editForm.suspect_person_details || ''} onChange={e => setEditForm({...editForm, suspect_person_details: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      )}
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Total value of buildings (BWP)</label>
                        <input type="number" value={editForm.total_value_premises_buildings || ''} onChange={e => setEditForm({...editForm, total_value_premises_buildings: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Any other insurance against fire on same property?</label>
                        <select value={String(editForm.other_insurance_against_fire ?? '')} onChange={e => setEditForm({...editForm, other_insurance_against_fire: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      {String(editForm.other_insurance_against_fire) === '1' && (
                        <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Details of other insurances against fire</label>
                          <textarea value={editForm.insurance_against_fire_details || ''} onChange={e => setEditForm({...editForm, insurance_against_fire_details: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      )}
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Estimated amount of damaged property (BWP)</label>
                        <input type="number" value={editForm.estimated_amount_of_damaged || ''} onChange={e => setEditForm({...editForm, estimated_amount_of_damaged: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Details of previous losses</label>
                        <textarea value={editForm.details_of_previous_loss || ''} onChange={e => setEditForm({...editForm, details_of_previous_loss: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                  </>
                )
                if (ctKey === 'MOBILEELECTRONICDEVICES' || ctKey === 'OFFICECONTENTS' || ctKey === 'MOBILEANDELECTRONICDEVICES') return (
                  <>
                    <SectionHeader label={ctKey === 'OFFICECONTENTS' ? 'Office Contents — Insured Contact' : 'Mobile / Electronic Devices — Insured Contact'} />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Insured's Name</label>
                        <input value={editForm.insured_name || ''} onChange={e => setEditForm({...editForm, insured_name: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">E-mail Address</label>
                        <input type="email" value={editForm.email_address || ''} onChange={e => setEditForm({...editForm, email_address: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Telephone No</label>
                        <input value={editForm.telephone_no || ''} onChange={e => setEditForm({...editForm, telephone_no: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Address</label>
                        <input value={editForm.address || ''} onChange={e => setEditForm({...editForm, address: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                    <SectionHeader label="Device / Property Loss" />
                    <div className="grid grid-cols-2 gap-3">
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Has the property been stolen or damaged?</label>
                        <select value={String(editForm.property_stolen_damaged ?? '')} onChange={e => setEditForm({...editForm, property_stolen_damaged: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Damaged</option><option value="0">Stolen</option>
                        </select></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Date and time when loss/damage was discovered</label>
                        <input type="datetime-local" value={editForm.date_time_loss_discovered || ''} onChange={e => setEditForm({...editForm, date_time_loss_discovered: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">By whom discovered?</label>
                        <input value={editForm.whom_discovered || ''} onChange={e => setEditForm({...editForm, whom_discovered: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Are you the sole owner of the property?</label>
                        <select value={String(editForm.is_sole_owner_of_property ?? '')} onChange={e => setEditForm({...editForm, is_sole_owner_of_property: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">—</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      {String(editForm.is_sole_owner_of_property) === '0' && (
                        <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Name of the owner</label>
                          <input value={editForm.sole_owner_of_property || ''} onChange={e => setEditForm({...editForm, sole_owner_of_property: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      )}
                    </div>
                  </>
                )
                if (ctKey === 'GOODSINTRANSIT') return (
                  <>
                    <SectionHeader label="Goods In Transit" />
                    <div className="grid grid-cols-2 gap-3">
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Address of premises where loss occurred</label>
                        <textarea value={editForm.address_of_premises_loss || ''} onChange={e => setEditForm({...editForm, address_of_premises_loss: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Details of the carrier / driver</label>
                        <textarea value={editForm.details_of_driver || ''} onChange={e => setEditForm({...editForm, details_of_driver: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Property last seen</label>
                        <input value={editForm.property_last_seen || ''} onChange={e => setEditForm({...editForm, property_last_seen: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Date and time of loss</label>
                        <input type="datetime-local" value={editForm.date_time_of_loss || ''} onChange={e => setEditForm({...editForm, date_time_of_loss: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Brief description of incident</label>
                        <textarea value={editForm.brief_description_incident || ''} onChange={e => setEditForm({...editForm, brief_description_incident: e.target.value})} rows={3} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Date/time police were advised</label>
                        <input type="datetime-local" value={editForm.date_time_police_advised || ''} onChange={e => setEditForm({...editForm, date_time_police_advised: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Police station name</label>
                        <input value={editForm.police_station_name || ''} onChange={e => setEditForm({...editForm, police_station_name: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Witness names</label>
                        <input value={editForm.witnesses_name || ''} onChange={e => setEditForm({...editForm, witnesses_name: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Witness mobile number</label>
                        <input value={editForm.witnesses_mobile_number || ''} onChange={e => setEditForm({...editForm, witnesses_mobile_number: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Total value of loss (BWP)</label>
                        <input type="number" value={editForm.total_value_of_loss || ''} onChange={e => setEditForm({...editForm, total_value_of_loss: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Consignment from</label>
                        <input value={editForm.consignment_from || ''} onChange={e => setEditForm({...editForm, consignment_from: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Consignment transported to</label>
                        <input value={editForm.consignment_transported_to || ''} onChange={e => setEditForm({...editForm, consignment_transported_to: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Vehicle registration number</label>
                        <input value={editForm.vehicle_registration_number || ''} onChange={e => setEditForm({...editForm, vehicle_registration_number: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Carrier contracted?</label>
                        <select value={editForm.is_carrier_contracted || ''} onChange={e => setEditForm({...editForm, is_carrier_contracted: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">--</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Carrier has own GIT insurance?</label>
                        <select value={editForm.carrier_has_own_GIT_ins || ''} onChange={e => setEditForm({...editForm, carrier_has_own_GIT_ins: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">--</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      <div><label className="block text-xs font-medium text-ink-faint mb-1">Other insurance against theft?</label>
                        <select value={editForm.other_insurance_against_theft || ''} onChange={e => setEditForm({...editForm, other_insurance_against_theft: e.target.value})} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                          <option value="">--</option><option value="1">Yes</option><option value="0">No</option>
                        </select></div>
                      {editForm.is_carrier_contracted === '1' && editForm.copy_of_contract && (
                        <div className="col-span-2 text-xs text-ink-faint italic">
                          Existing contract on file: <span className="text-ink-muted">{String(editForm.copy_of_contract).split('/').pop()}</span>
                        </div>
                      )}
                      {editForm.other_insurance_against_theft === '1' && (
                        <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Details of other insurance</label>
                          <textarea value={editForm.insurance_against_theft_details || ''} onChange={e => setEditForm({...editForm, insurance_against_theft_details: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                      )}
                      <div className="col-span-2"><label className="block text-xs font-medium text-ink-faint mb-1">Details of records of previous loss</label>
                        <textarea value={editForm.details_of_previous_loss_records || ''} onChange={e => setEditForm({...editForm, details_of_previous_loss_records: e.target.value})} rows={2} className="w-full px-3 py-2 border border-line rounded-md text-sm" /></div>
                    </div>
                  </>
                )
                return null
              }

              return null
            })()}

            <div className="flex justify-end gap-2 pt-2">
              <button onClick={() => setShowEditModal(false)} className="px-4 py-2 text-sm border border-line rounded-md">Cancel</button>
              <button onClick={handleEdit} disabled={editSaving} className="px-4 py-2 text-sm bg-primary text-white rounded-md disabled:opacity-50">{editSaving ? 'Saving...' : 'Save Changes'}</button>
            </div>
          </div>
        </div>
      )}

      {/* ── Edit History Modal ── */}
      {showEditHistory && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto" onClick={() => setShowEditHistory(false)}>
          <div className="bg-surface rounded-xl shadow-2xl w-full max-w-2xl mx-4 p-5 max-h-[80vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
            <h2 className="text-lg font-bold mb-3">Edit History — {claim.claim_number}</h2>
            {editHistory.length === 0 ? (
              <p className="text-ink-faint text-center py-4">No edits recorded.</p>
            ) : (
              <table className="w-full text-sm divide-y divide-line">
                <thead className="bg-surface-2"><tr>
                  <th className="px-3 py-2 text-left text-xs font-medium text-ink-faint uppercase">Field</th>
                  <th className="px-3 py-2 text-left text-xs font-medium text-ink-faint uppercase">Old Value</th>
                  <th className="px-3 py-2 text-left text-xs font-medium text-ink-faint uppercase">New Value</th>
                  <th className="px-3 py-2 text-left text-xs font-medium text-ink-faint uppercase">Changed By</th>
                  <th className="px-3 py-2 text-left text-xs font-medium text-ink-faint uppercase">Date</th>
                </tr></thead>
                <tbody className="divide-y divide-line">
                  {editHistory.map((h: any, i: number) => (
                    <tr key={i} className="hover:bg-surface-2">
                      <td className="px-3 py-2 font-medium">{h.field?.replace(/_/g, ' ')}</td>
                      <td className="px-3 py-2 text-status-danger-fg line-through">{h.old_value || '-'}</td>
                      <td className="px-3 py-2 text-status-success-fg font-medium">{h.new_value || '-'}</td>
                      <td className="px-3 py-2 text-ink-muted">{h.changed_by_name || `#${h.changed_by}`}</td>
                      <td className="px-3 py-2 text-xs text-ink-faint">{h.created_at?.slice(0, 16).replace('T', ' ')}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
            <div className="flex justify-end mt-3">
              <button onClick={() => setShowEditHistory(false)} className="px-4 py-2 text-sm border border-line rounded-md">Close</button>
            </div>
          </div>
        </div>
      )}

      {/* ── Status Update Modal ── */}
      {showStatusModal && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto" onClick={() => setShowStatusModal(false)}>
          <div className="bg-surface rounded-xl shadow-2xl w-full max-w-md mx-4 p-5 max-h-[85vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
            <h3 className="text-lg font-bold text-ink mb-3">Update Claim Status</h3>
            <p className="text-sm text-ink-faint mb-4">
              Current status: <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium border ${statusColor}`}>{claim.status || 'Unknown'}</span>
            </p>
            {statusError && <div className="mb-4 p-3 bg-status-danger-bg border border-status-danger-fg rounded-lg text-status-danger-fg text-sm">{statusError}</div>}
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-ink-muted mb-1">New Status</label>
                <select value={newStatus}
                  onChange={e => {
                    const next = e.target.value
                    setNewStatus(next)
                    // Keep the current sub-status when staying on 'Open'; otherwise clear it.
                    setSubStatus(next === 'Open' && next === claim.status ? (claim.claim_sub_status ?? '') : '')
                  }}
                  className="w-full px-3 py-2 border border-line rounded-lg text-sm bg-surface">
                  {claim.status
                    ? <option value={claim.status}>{claim.status} (current)</option>
                    : <option value="">Select a status…</option>}
                  {allowedStatuses.filter(s => s !== claim.status).map(s => (
                    <option key={s} value={s}>{s}</option>
                  ))}
                </select>
              </div>
              {newStatus === 'Open' && (
                <div>
                  <label className="block text-sm font-medium text-ink-muted mb-1">Sub Status</label>
                  <select value={subStatus} onChange={e => setSubStatus(e.target.value)}
                    className="w-full px-3 py-2 border border-line rounded-lg text-sm bg-surface">
                    <option value="">Select a sub-status…</option>
                    {OPEN_SUB_STATUSES.map(s => (
                      <option key={s} value={s}>{s}</option>
                    ))}
                  </select>
                </div>
              )}
              {(newStatus === 'Closed' || newStatus === 'Rejected') && (
                <div>
                  <label className="block text-sm font-medium text-ink-muted mb-1">
                    {newStatus === 'Closed' ? 'Closing Note' : 'Rejection Reason'}
                  </label>
                  <textarea value={closedNote} onChange={e => setClosedNote(e.target.value)} rows={3}
                    placeholder={newStatus === 'Closed' ? 'Describe resolution...' : 'Reason for rejection...'}
                    className="w-full px-3 py-2 border border-line rounded-lg text-sm" />
                </div>
              )}
            </div>
            <div className="flex justify-end gap-3 mt-6">
              <button onClick={() => { setShowStatusModal(false); setNewStatus(''); setClosedNote(''); setSubStatus(''); setStatusError('') }}
                className="px-4 py-2 text-ink-muted border border-line rounded-lg hover:bg-surface-2 text-sm">Cancel</button>
              <button onClick={handleStatusUpdate} disabled={!newStatus || (newStatus === 'Open' && !subStatus) || updateStatus.isPending}
                className="px-5 py-2 bg-primary text-white rounded-lg hover:bg-primary disabled:opacity-50 text-sm font-medium">
                {updateStatus.isPending ? 'Updating...' : 'Confirm'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
