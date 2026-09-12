import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import {
  useApproveRefundRequest,
  useAssignableUsers,
  useAssignRefundRequest,
  useCfoApproveRefundRequest,
  useEscalateRefundRequest,
  useRefundRequest,
  useRejectRefundRequest,
  useResetRefundToDraft,
  useReviewRefundRequest,
  useSettleRefundManually,
  useSubmitRefundRequest,
  useUndoManualSettlement,
  useUploadRefundDocument,
} from '../../../hooks/useRefundRequests'
import { downloadRefundDocument, revealAccount, type RefundDocType, type RefundRequest } from '../../../api/refundRequests'
import { fmtDateTime, fmtPula } from '../../../utils/format'
import { AREA_LABEL, STATUS_LABEL, STATUS_PILL, apiErrorMessage, useRefundAccess } from './refundShared'

/** Reviewer-routing dropdown — approvers/owners reassign to anyone in the
 *  area (CFO 2026-07-26). Options load on demand from assignable-users. */
function AssignControl({ requestId, assignedTo, busy, onAssign }: {
  requestId: number
  assignedTo: number | null
  busy: boolean
  onAssign: (userId: number) => void
}) {
  const [open, setOpen] = useState(false)
  const users = useAssignableUsers(requestId, open)
  const current = users.data?.find(u => u.id === assignedTo)
  return (
    <span className="inline-flex items-center gap-2">
      {!open && (
        <button onClick={() => setOpen(true)} disabled={busy}
          className="px-3 py-2 text-sm rounded border border-line text-ink hover:bg-surface-2 disabled:opacity-50">
          {assignedTo ? `Assigned${current ? `: ${current.name || current.email}` : ''} — reassign` : 'Assign reviewer'}
        </button>
      )}
      {open && (
        <select
          autoFocus
          defaultValue=""
          disabled={users.isLoading || busy}
          onChange={e => { const v = Number(e.target.value); if (v) { onAssign(v); setOpen(false) } }}
          onBlur={() => setOpen(false)}
          className="border border-line rounded px-2 py-2 text-sm max-w-[230px]"
        >
          <option value="">{users.isLoading ? 'Loading…' : 'Assign to…'}</option>
          {(users.data ?? []).map(u => (
            <option key={u.id} value={u.id}>{u.name || u.email}</option>
          ))}
        </select>
      )}
    </span>
  )
}

const DOC_LABEL: Record<string, string> = {
  bank_statement: 'Bank statement',
  bank_confirmation: 'Bank confirmation',
  affidavit: 'Affidavit',
  other: 'Other',
}

/**
 * Customer Refund Engine — request detail: documents, SOP workflow actions
 * (per role + status) and the full audit timeline. All gating here is UX only;
 * the backend enforces permissions, area and separation-of-duties.
 */
export default function RefundRequestDetailPage() {
  const { id } = useParams()
  const requestId = Number(id)
  const access = useRefundAccess()
  const q = useRefundRequest(requestId)

  const submit = useSubmitRefundRequest()
  const review = useReviewRefundRequest()
  const approve = useApproveRefundRequest()
  const reject = useRejectRefundRequest()
  const escalate = useEscalateRefundRequest()
  const cfoApprove = useCfoApproveRefundRequest()
  const upload = useUploadRefundDocument()
  const assign = useAssignRefundRequest()
  const resetToDraft = useResetRefundToDraft()
  const settleManual = useSettleRefundManually()
  const undoManual = useUndoManualSettlement()

  const [error, setError] = useState<string | null>(null)
  const [modal, setModal] = useState<'reject' | 'escalate' | 'approve' | 'cfo' | 'review' | 'manualPaid' | 'undoManualPaid' | null>(null)
  const [modalReason, setModalReason] = useState('')
  const [bankConfirmed, setBankConfirmed] = useState(false)
  const [manualPaidAt, setManualPaidAt] = useState('')
  const [manualPaidRef, setManualPaidRef] = useState('')
  const [docFile, setDocFile] = useState<File | null>(null)
  const [docType, setDocType] = useState<RefundDocType>('other')
  const [revealedAccount, setRevealedAccount] = useState<string | null>(null)
  const [revealing, setRevealing] = useState(false)

  if (q.isLoading) return <div className="p-6 text-ink-faint">Loading…</div>
  if (q.isError || !q.data) {
    return (
      <div className="p-6">
        <p className="text-sm text-red-600">{apiErrorMessage(q.error)}</p>
        <Link to="/finance/refund-engine" className="text-sm text-brand-navy hover:underline">← Customer Refunds</Link>
      </div>
    )
  }
  const r: RefundRequest = q.data
  const busy = submit.isPending || review.isPending || approve.isPending
    || reject.isPending || escalate.isPending || cfoApprove.isPending || resetToDraft.isPending
    || settleManual.isPending || undoManual.isPending

  async function run(action: () => Promise<unknown>, close = true) {
    setError(null)
    try {
      await action()
      if (close) { setModal(null); setModalReason(''); setBankConfirmed(false); setManualPaidAt(''); setManualPaidRef('') }
    } catch (e) {
      setError(apiErrorMessage(e))
      // Re-read the request before showing the error. A rejected action usually
      // means the row moved underneath us (a double-click, or someone else
      // acting first), and without this the page kept its stale copy — Phatsimo
      // saw a "Draft" pill next to "Cannot submit a submitted request", which
      // reads like a system fault rather than "this is already submitted".
      q.refetch()
    }
  }

  async function revealAccountNow() {
    setError(null)
    setRevealing(true)
    try {
      const res = await revealAccount(requestId)
      setRevealedAccount(res.account_number)
    } catch (e) {
      setError(apiErrorMessage(e))
    } finally {
      setRevealing(false)
    }
  }

  const canAssignNow  = access.canApprove && ['submitted', 'under_review'].includes(r.status)
  const canSubmitNow  = access.canSubmit && (r.status === 'draft' || r.status === 'rejected')
  // A rejected refund almost always needs a data fix, so the intaker gets both
  // an Edit route and an explicit Reset to Draft (Finance ask 2026-08-18).
  const canEditNow    = access.canCreate && ['draft', 'rejected'].includes(r.status)
  const canResetNow   = access.canCreate && r.status === 'rejected'
  const canReviewNow  = access.canReview && r.status === 'submitted'
  const canDecideNow  = access.canReview && ['submitted', 'under_review'].includes(r.status)
  // Approvers may also escalate — an Administrator blocked by a CRITICAL
  // fraud flag at approval sends the case to the CFO themselves.
  const canEscalateNow = (access.canReview || access.canApprove) && ['submitted', 'under_review'].includes(r.status)
  const hasCritical   = (r.fraud_flags ?? []).some(f => f.severity === 'CRITICAL')
  const canApproveNow = access.canApprove && ['under_review', 'escalated', 'approval_pending_2'].includes(r.status)
  // The CFO clears anything held. A deputy holding only refund-escalation-clear
  // clears ordinary escalations — never the >P50k gate, and never one the engine
  // has flagged CRITICAL, because clearing that IS the fraud override
  // (CFO 2026-09-02). UX only; the service enforces both limits.
  const canFullCfo    = access.canCfoApprove && ['cfo_pending', 'escalated'].includes(r.status)
  const canClearEscOnly = !access.canCfoApprove && access.canClearEscalation
    && r.status === 'escalated' && !hasCritical
  const canCfoNow     = canFullCfo || canClearEscOnly
  // A deputy looking at a CRITICAL-flagged escalation needs to know why there is
  // no button, rather than assuming the screen is broken.
  const escBlockedByFraud = !access.canCfoApprove && access.canClearEscalation
    && r.status === 'escalated' && hasCritical
  const canUploadNow  = access.canCreate && ['draft', 'rejected', 'submitted', 'under_review'].includes(r.status)
  // Finance closes a refund it already paid by hand at the bank. Only from a
  // state that has reached a decision, and only while our own money leg has not
  // touched it — the backend enforces both.
  const canMarkManualPaid = access.canPostAccounting && r.omni_status === 'not_sent'
    && ['approved', 'cfo_approved', 'approval_pending_2', 'escalated', 'cfo_pending'].includes(r.status)
  const canUndoManualPaid = access.canPostAccounting && r.status === 'settled_manual'

  const fieldRows: [string, string][] = [
    ['Area', AREA_LABEL[r.area]],
    ['Policy', r.policy_number],
    ['Product', r.product_name || '—'],
    ['Customer', r.customer_name || '—'],
    ['Agent', r.agent_name || '—'],
    ['Amount', fmtPula(r.refund_amount)],
    ['Collected via', r.collection_method || '—'],
    ['Reason', r.reason || '—'],
    ['Bank', r.bank_name || '—'],
    ['Branch name', r.branch_name || '—'],
    ['Branch code', r.branch_code || '—'],
    ['Vehicle not client', r.vehicle_not_client ? 'Yes — affidavit required' : 'No'],
    ['AI green light', r.ai_greenlight ? 'Yes' : 'No'],
    ['Omni status', r.omni_status + (r.omni_paid_ref ? ` · ${r.omni_paid_ref}` : '')],
    ...(r.status === 'settled_manual' || r.manual_paid_at || r.manual_paid_ref
      ? ([['Paid outside Graphite',
          [r.manual_paid_at ? `on ${r.manual_paid_at}` : 'date not supplied',
           r.manual_paid_ref ? `ref ${r.manual_paid_ref}` : null,
           r.manual_paid_by ? `recorded by user ${r.manual_paid_by}` : 'no recorded actor',
          ].filter(Boolean).join(' · ')]] as [string, string][])
      : []),
  ]

  return (
    <div className="p-6 max-w-5xl space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <Link to="/finance/refund-engine" className="text-sm text-brand-navy hover:underline">← Customer Refunds</Link>
          <div className="flex items-center gap-3 mt-1">
            <h1 className="text-2xl font-bold text-ink">{r.graphite_ref}</h1>
            <span className={`inline-block px-2.5 py-1 rounded-full text-xs font-medium ${STATUS_PILL[r.status]}`}>
              {STATUS_LABEL[r.status]}
            </span>
            {r.after_cutoff && (
              <span className="text-[10px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-800">
                submitted after 15:00 — next business day
              </span>
            )}
          </div>
        </div>

        {/* Workflow actions */}
        <div className="flex flex-wrap items-center gap-2">
          {canEditNow && (
            <Link to={`/finance/refund-engine/${r.id}/edit`}
              className="px-3 py-2 text-sm rounded border border-line text-ink hover:bg-surface-2">
              Edit details
            </Link>
          )}
          {canResetNow && (
            <button onClick={() => run(() => resetToDraft.mutateAsync(r.id))} disabled={busy}
              className="px-3 py-2 text-sm rounded border border-line text-ink hover:bg-surface-2 disabled:opacity-50"
              title="Return this refund to draft so it can be corrected and resubmitted">
              Reset to Draft
            </button>
          )}
          {canSubmitNow && (
            <button onClick={() => run(() => submit.mutateAsync(r.id))} disabled={busy}
              className="px-3 py-2 text-sm rounded bg-brand-navy text-white hover:opacity-90 disabled:opacity-50">
              {r.status === 'rejected' ? 'Resubmit' : 'Submit for Review'}
            </button>
          )}
          {canReviewNow && (
            <button onClick={() => { setModal('review'); setModalReason(''); setError(null) }} disabled={busy}
              className="px-3 py-2 text-sm rounded bg-indigo-600 text-white hover:opacity-90 disabled:opacity-50">
              Mark Reviewed
            </button>
          )}
          {canApproveNow && (
            <button onClick={() => { setModal('approve'); setError(null) }} disabled={busy}
              className="px-3 py-2 text-sm rounded bg-green-600 text-white hover:opacity-90 disabled:opacity-50">
              {r.status === 'approval_pending_2' ? 'Approve (2nd approver)' : 'Approve'}
            </button>
          )}
          {canCfoNow && (
            <button onClick={() => { setModal('cfo'); setError(null) }} disabled={busy}
              className="px-3 py-2 text-sm rounded bg-green-700 text-white hover:opacity-90 disabled:opacity-50">
              {canClearEscOnly ? 'Clear Escalation'
                : r.status === 'escalated' ? 'CFO Clear (override)' : 'CFO Approve (>P50k)'}
            </button>
          )}
          {canMarkManualPaid && (
            <button onClick={() => { setModal('manualPaid'); setError(null) }} disabled={busy}
              className="px-3 py-2 text-sm rounded border border-emerald-300 text-emerald-800 hover:bg-emerald-50 disabled:opacity-50">
              Already paid outside Graphite
            </button>
          )}
          {canUndoManualPaid && (
            <button onClick={() => { setModal('undoManualPaid'); setError(null) }} disabled={busy}
              className="px-3 py-2 text-sm rounded border border-line text-ink hover:bg-surface-2 disabled:opacity-50">
              Undo manual payment record
            </button>
          )}
          {escBlockedByFraud && (
            <span className="px-3 py-2 text-sm rounded bg-red-50 border border-red-300 text-red-700">
              Fraud-flagged — only the CFO can clear this one
            </span>
          )}
          {(canDecideNow || (access.canReview && r.status === 'cfo_pending')
            || (access.canApprove && r.status === 'approval_pending_2')) && (
            <button onClick={() => { setModal('reject'); setError(null) }} disabled={busy}
              className="px-3 py-2 text-sm rounded border border-red-300 text-red-700 hover:bg-red-50 disabled:opacity-50">
              Reject
            </button>
          )}
          {canEscalateNow && (
            <button onClick={() => { setModal('escalate'); setError(null) }} disabled={busy}
              className="px-3 py-2 text-sm rounded border border-orange-300 text-orange-700 hover:bg-orange-50 disabled:opacity-50">
              Escalate
            </button>
          )}
          {canAssignNow && <AssignControl requestId={r.id} assignedTo={r.assigned_to} busy={busy}
            onAssign={(userId) => run(() => assign.mutateAsync({ id: r.id, userId }), false)} />}
        </div>
      </div>

      {error && !modal && (
        <div className="rounded border border-red-200 bg-red-50 text-red-700 text-sm px-4 py-3">{error}</div>
      )}

      {r.status === 'rejected' && r.rejected_reason && (
        <div className="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm">
          <div className="font-semibold text-red-700">Rejected — fix and resubmit</div>
          <div className="text-red-700 mt-1">{r.rejected_reason}</div>
        </div>
      )}
      {r.review_comment && (
        <div className="rounded border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm">
          <div className="font-semibold text-indigo-800">Reviewer comment</div>
          <div className="text-indigo-900 mt-1 whitespace-pre-wrap">{r.review_comment}</div>
        </div>
      )}
      {r.status === 'escalated' && r.escalated_reason && (
        <div className="rounded border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-800">
          <span className="font-semibold">Escalated:</span> {r.escalated_reason}
        </div>
      )}

      {/* Fraud signals — stamped by the native fraud engine on submit and
          re-checked at approval. CRITICAL blocks approval; only the CFO clears it. */}
      {(r.fraud_flags ?? []).length > 0 && (
        <div className={`rounded border px-4 py-3 text-sm ${hasCritical ? 'border-red-300 bg-red-50' : 'border-amber-300 bg-amber-50'}`}>
          <div className={`font-semibold ${hasCritical ? 'text-red-700' : 'text-amber-800'}`}>
            {hasCritical ? '⛔ Fraud alerts — approval blocked, CFO clearance required' : '⚠ Fraud signals — review before approving'}
            <span className="ml-2 font-normal text-xs">
              score {r.fraud_score}{r.fraud_reviewed_at ? ` · scanned ${fmtDateTime(r.fraud_reviewed_at)}` : ''}
            </span>
          </div>
          <ul className="mt-2 space-y-1">
            {(r.fraud_flags ?? []).map((f, i) => (
              <li key={i} className="flex gap-2">
                <span className={`shrink-0 px-1.5 rounded text-[10px] font-bold self-start mt-0.5 ${
                  f.severity === 'CRITICAL' ? 'bg-red-600 text-white'
                  : f.severity === 'HIGH' ? 'bg-orange-500 text-white'
                  : f.severity === 'MEDIUM' ? 'bg-amber-400 text-black'
                  : 'bg-surface-2 text-ink-muted'}`}>
                  {f.severity}
                </span>
                <span className="text-ink">{f.detail}</span>
              </li>
            ))}
          </ul>
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {/* Request details */}
        <div className="lg:col-span-2 bg-surface rounded-lg shadow-sm p-5">
          <h2 className="text-sm font-semibold text-ink mb-3">Request</h2>
          <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
            {fieldRows.map(([k, v]) => (
              <div key={k} className="flex justify-between gap-3 border-b border-line/60 py-1.5">
                <dt className="text-ink-muted">{k}</dt>
                <dd className="text-ink text-right font-medium">{v}</dd>
              </div>
            ))}
            <div className="flex justify-between gap-3 border-b border-line/60 py-1.5">
              <dt className="text-ink-muted">Account</dt>
              <dd className="text-ink text-right font-medium">
                {revealedAccount ? (
                  <span className="inline-flex flex-col items-end">
                    <span>{revealedAccount}</span>
                    <span className="text-[10px] font-normal text-ink-faint">viewing is logged</span>
                  </span>
                ) : (
                  <span className="inline-flex items-center gap-2">
                    <span>{r.account_last4 ? `•••• ${r.account_last4}` : '—'}</span>
                    {r.account_last4 && (
                      <button onClick={revealAccountNow} disabled={revealing}
                        className="text-xs font-normal text-brand-navy hover:underline disabled:opacity-50">
                        {revealing ? 'Revealing…' : 'Reveal full account number'}
                      </button>
                    )}
                  </span>
                )}
              </dd>
            </div>
          </dl>

          {/* Documents */}
          <h2 className="text-sm font-semibold text-ink mt-6 mb-3">Documents</h2>
          {(r.documents ?? []).length === 0 && (
            <p className="text-sm text-ink-faint">No documents uploaded yet.</p>
          )}
          <ul className="space-y-2">
            {(r.documents ?? []).map(d => (
              <li key={d.id} className="flex items-center justify-between rounded border border-line px-3 py-2 text-sm">
                <div>
                  <span className="font-medium text-ink">{DOC_LABEL[d.doc_type] ?? d.doc_type}</span>
                  <span className="text-ink-muted ml-2">{d.original_name}</span>
                </div>
                <button onClick={() => downloadRefundDocument(r.id, d)}
                  className="text-brand-navy hover:underline">Download</button>
              </li>
            ))}
          </ul>
          {canUploadNow && (
            <div className="mt-3 flex flex-wrap items-center gap-2">
              <select value={docType} onChange={e => setDocType(e.target.value as RefundDocType)}
                className="border border-line rounded px-2 py-1.5 text-sm">
                <option value="bank_statement">Bank statement</option>
                <option value="bank_confirmation">Bank confirmation</option>
                <option value="affidavit">Affidavit</option>
                <option value="other">Other</option>
              </select>
              <input type="file" accept=".pdf,.jpg,.jpeg,.png"
                onChange={e => setDocFile(e.target.files?.[0] ?? null)} className="text-sm" />
              <button
                disabled={!docFile || upload.isPending}
                onClick={() => docFile && run(async () => {
                  await upload.mutateAsync({ id: r.id, file: docFile, docType })
                  setDocFile(null)
                }, false)}
                className="px-3 py-1.5 text-sm rounded bg-primary text-white disabled:opacity-40">
                {upload.isPending ? 'Uploading…' : 'Upload'}
              </button>
            </div>
          )}
        </div>

        {/* Audit timeline */}
        <div className="bg-surface rounded-lg shadow-sm p-5">
          <h2 className="text-sm font-semibold text-ink mb-3">Audit Trail</h2>
          <ol className="space-y-3">
            {(r.events ?? []).map(ev => (
              <li key={ev.id} className="text-sm border-l-2 border-brand-orange pl-3">
                <div className="font-medium text-ink">
                  {ev.action.replace(/_/g, ' ')}
                  {ev.to_status && ev.to_status !== ev.from_status && (
                    <span className="text-ink-muted font-normal"> → {STATUS_LABEL[ev.to_status as keyof typeof STATUS_LABEL] ?? ev.to_status}</span>
                  )}
                </div>
                {ev.note && <div className="text-ink-muted">{ev.note}</div>}
                <div className="text-xs text-ink-faint mt-0.5">
                  {ev.actor_name || 'system'} · {fmtDateTime(ev.created_at)}
                </div>
              </li>
            ))}
            {(r.events ?? []).length === 0 && <li className="text-sm text-ink-faint">No events yet.</li>}
          </ol>
        </div>
      </div>

      {/* Modals */}
      {modal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <div className="bg-surface rounded-lg shadow-lg w-full max-w-md p-5 space-y-4">
            {modal === 'approve' && (
              <>
                <h3 className="text-lg font-semibold text-ink">Approve {r.graphite_ref}</h3>
                <p className="text-sm text-ink-muted">
                  Final approval arms the money leg: this request is handed to Omni (Finance queue → FNB EFT)
                  once the integration is enabled{r.refund_amount > 50000
                    ? ' — this refund is above P50,000 and will additionally require CFO approval'
                    : ''}.
                </p>
                <label className="flex items-start gap-2 text-sm text-ink">
                  <input type="checkbox" checked={bankConfirmed}
                    onChange={e => setBankConfirmed(e.target.checked)} className="mt-0.5 rounded" />
                  I have confirmed the client's bank account ({r.bank_name || 'bank'} •••• {r.account_last4 || '????'})
                  against the uploaded bank proof (SOP — recorded in the audit trail).
                </label>
                <div>
                  <label className="block text-sm text-ink mb-1">Reason for approval *</label>
                  <textarea value={modalReason} onChange={e => setModalReason(e.target.value)} rows={3}
                    placeholder="Why is this refund approved? (recorded in the audit trail)"
                    className="w-full border border-line rounded px-3 py-2 text-sm" />
                </div>
              </>
            )}
            {modal === 'cfo' && (
              <>
                <h3 className="text-lg font-semibold text-ink">
                  {r.status === 'escalated' ? `CFO clearance — ${r.graphite_ref}` : `CFO approval — ${r.graphite_ref}`}
                </h3>
                {canClearEscOnly && (
                  <p className="text-xs text-ink-muted bg-surface-2 border border-line rounded px-2 py-1.5">
                    You are clearing an escalation. This does not clear the over-P50,000 gate and cannot override
                    fraud alerts — those stay with the CFO.
                  </p>
                )}
                {hasCritical && (
                  <p className="text-sm rounded border border-red-300 bg-red-50 text-red-700 px-3 py-2">
                    This request carries CRITICAL fraud flags (score {r.fraud_score}). Clearing it is the
                    documented fraud override — recorded with your name in the audit trail.
                  </p>
                )}
                <p className="text-sm text-ink-muted">
                  Clearing arms the money leg: the request goes to Omni (Finance queue → FNB EFT) once the
                  integration is enabled.
                </p>
                {!r.bank_account_confirmed && (
                  <label className="flex items-start gap-2 text-sm text-ink">
                    <input type="checkbox" checked={bankConfirmed}
                      onChange={e => setBankConfirmed(e.target.checked)} className="mt-0.5 rounded" />
                    I have confirmed the client's bank account ({r.bank_name || 'bank'} •••• {r.account_last4 || '????'})
                    against the uploaded bank proof (SOP — this escalated request skipped the administrator
                    confirmation, so it is required here).
                  </label>
                )}
              </>
            )}
            {modal === 'review' && (
              <>
                <h3 className="text-lg font-semibold text-ink">Mark {r.graphite_ref} reviewed</h3>
                <p className="text-sm text-ink-muted">
                  Confirms you have checked the request and its documents. It then goes to an approver.
                </p>
                <div>
                  <label className="block text-xs font-medium uppercase tracking-wide text-ink-muted mb-1">
                    Review comment (optional)
                  </label>
                  <textarea value={modalReason} onChange={e => setModalReason(e.target.value)} rows={4}
                    placeholder="What you checked, and anything the approver should know…"
                    className="w-full border border-line rounded px-3 py-2 text-sm" />
                  <p className="text-xs text-ink-faint mt-1">
                    Shown to the approver and kept in the audit trail. Never put card or account numbers here.
                  </p>
                </div>
              </>
            )}
            {modal === 'reject' && (
              <>
                <h3 className="text-lg font-semibold text-ink">Reject {r.graphite_ref}</h3>
                <p className="text-sm text-ink-muted">
                  The request returns to its creator with your reason; they can fix and resubmit.
                </p>
                <textarea value={modalReason} onChange={e => setModalReason(e.target.value)} rows={3}
                  placeholder="Reason + missing documents…"
                  className="w-full border border-line rounded px-3 py-2 text-sm" />
              </>
            )}
            {modal === 'escalate' && (
              <>
                <h3 className="text-lg font-semibold text-ink">Escalate {r.graphite_ref}</h3>
                <textarea value={modalReason} onChange={e => setModalReason(e.target.value)} rows={3}
                  placeholder="Why does this need a senior decision?"
                  className="w-full border border-line rounded px-3 py-2 text-sm" />
              </>
            )}

            {error && <div className="rounded border border-red-200 bg-red-50 text-red-700 text-sm px-3 py-2">{error}</div>}

            {modal === 'manualPaid' && (
              <>
                <h3 className="text-lg font-semibold text-ink">{r.graphite_ref} — already paid outside Graphite</h3>
                <p className="text-sm text-ink-muted">
                  Use this when Finance has <strong>already refunded this client at the bank</strong>, outside the system.
                  It records that fact and takes the request out of the payout queue, so the client cannot be paid a
                  second time when the refund link is switched on. <strong>No money moves.</strong>
                </p>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label className="block text-xs font-medium uppercase tracking-wide text-ink-muted mb-1">
                      Date paid (optional)
                    </label>
                    <input type="date" value={manualPaidAt} max={new Date().toISOString().slice(0, 10)}
                      onChange={e => setManualPaidAt(e.target.value)}
                      className="w-full border border-line rounded px-3 py-2 text-sm" />
                    <p className="text-xs text-ink-faint mt-1">Lets Finance tie this to the bank statement later.</p>
                  </div>
                  <div>
                    <label className="block text-xs font-medium uppercase tracking-wide text-ink-muted mb-1">
                      Bank reference (optional)
                    </label>
                    <input type="text" value={manualPaidRef} maxLength={120}
                      onChange={e => setManualPaidRef(e.target.value)}
                      placeholder="FNB payment reference…"
                      className="w-full border border-line rounded px-3 py-2 text-sm" />
                  </div>
                </div>
                <div>
                  <label className="block text-xs font-medium uppercase tracking-wide text-ink-muted mb-1">
                    How was this verified? *
                  </label>
                  <textarea value={modalReason} onChange={e => setModalReason(e.target.value)} rows={3}
                    placeholder="Who paid it and how you confirmed it — e.g. paid manually at FNB while the integration was off, confirmed against the bank statement…"
                    className="w-full border border-line rounded px-3 py-2 text-sm" />
                  <p className="text-xs text-ink-faint mt-1">
                    At least 10 characters. This is the only record that the client was already refunded, so it is kept
                    in the audit trail. Never put card or account numbers here.
                  </p>
                </div>
              </>
            )}
            {modal === 'undoManualPaid' && (
              <>
                <h3 className="text-lg font-semibold text-ink">Undo the manual payment record on {r.graphite_ref}</h3>
                <p className="text-sm text-ink-muted">
                  Removes the &ldquo;paid outside Graphite&rdquo; record and returns the request to where it was before,
                  so a mistake never leaves a genuine refund unpaid. If the previous state cannot be established it
                  returns to <strong>Reviewed — awaiting approval</strong>, which means it must be approved again
                  before any payout.
                </p>
                <textarea value={modalReason} onChange={e => setModalReason(e.target.value)} rows={3}
                  placeholder="Why this record is being removed…"
                  className="w-full border border-line rounded px-3 py-2 text-sm" />
                <p className="text-xs text-ink-faint">At least 10 characters.</p>
              </>
            )}
            <div className="flex justify-end gap-2">
              <button onClick={() => { setModal(null); setError(null) }}
                className="px-3 py-2 text-sm rounded border border-line text-ink">Cancel</button>
              {modal === 'approve' && (
                <button disabled={!bankConfirmed || !modalReason.trim() || busy}
                  onClick={() => run(() => approve.mutateAsync({ id: r.id, bankAccountConfirmed: bankConfirmed, reason: modalReason.trim() }))}
                  className="px-3 py-2 text-sm rounded bg-green-600 text-white disabled:opacity-40">
                  Confirm Approval
                </button>
              )}
              {modal === 'review' && (
                <button disabled={busy}
                  onClick={() => run(() => review.mutateAsync({ id: r.id, comment: modalReason.trim() }))}
                  className="px-3 py-2 text-sm rounded bg-indigo-600 text-white disabled:opacity-40">
                  Mark Reviewed
                </button>
              )}
              {modal === 'reject' && (
                <button disabled={!modalReason.trim() || busy}
                  onClick={() => run(() => reject.mutateAsync({ id: r.id, reason: modalReason.trim() }))}
                  className="px-3 py-2 text-sm rounded bg-red-600 text-white disabled:opacity-40">
                  Reject
                </button>
              )}
              {modal === 'escalate' && (
                <button disabled={!modalReason.trim() || busy}
                  onClick={() => run(() => escalate.mutateAsync({ id: r.id, reason: modalReason.trim() }))}
                  className="px-3 py-2 text-sm rounded bg-orange-600 text-white disabled:opacity-40">
                  Escalate
                </button>
              )}
              {modal === 'manualPaid' && (
                <button disabled={busy || modalReason.trim().length < 10}
                  onClick={() => run(() => settleManual.mutateAsync({
                    id: r.id, reason: modalReason.trim(),
                    paid_at: manualPaidAt || null, paid_ref: manualPaidRef.trim() || null,
                  }))}
                  className="px-3 py-2 text-sm rounded bg-emerald-700 text-white disabled:opacity-40">
                  Record as already paid
                </button>
              )}
              {modal === 'undoManualPaid' && (
                <button disabled={busy || modalReason.trim().length < 10}
                  onClick={() => run(() => undoManual.mutateAsync({ id: r.id, reason: modalReason.trim() }))}
                  className="px-3 py-2 text-sm rounded bg-brand-navy text-white disabled:opacity-40">
                  Undo record
                </button>
              )}
              {modal === 'cfo' && (
                <button disabled={busy || (!r.bank_account_confirmed && !bankConfirmed)}
                  onClick={() => run(() => cfoApprove.mutateAsync({ id: r.id, bankAccountConfirmed: bankConfirmed, reason: modalReason.trim(), overrideFraud: hasCritical }))}
                  className="px-3 py-2 text-sm rounded bg-green-700 text-white disabled:opacity-40">
                  {hasCritical ? 'Clear & Override' : 'Approve'}
                </button>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
