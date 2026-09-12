import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import {
  useClaimsFnolEnabled,
  useFnol,
  useUpdateFnol,
  useConvertFnol,
  useCloseFnol,
  CLAIMS_FNOL_ROLES,
} from '../../hooks/useFnol'
import { getStoredRoles } from '../../api/auth'
import type { UpdateFnolPayload } from '../../api/fnol'
import EmptyState from '../../components/common/EmptyState'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import Button from '../../components/common/Button'
import { useToast } from '../../components/common/Toast'
import { useConfirm } from '../../components/common/ConfirmDialog'
import { fmtDate, fmtDateTime, fmtPula } from '../../utils/format'
import FnolStatusBadge from './FnolStatusBadge'
import OutstandingDocsInput from './OutstandingDocsInput'

function Row({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex flex-col sm:flex-row sm:items-baseline gap-0.5 sm:gap-3 py-2 border-b border-line last:border-b-0">
      <div className="w-40 shrink-0 text-[11px] uppercase tracking-wide text-ink-faint">{label}</div>
      <div className="text-sm text-ink">{children}</div>
    </div>
  )
}

export default function FnolDetailPage() {
  const navigate = useNavigate()
  const { id } = useParams<{ id: string }>()
  const fnolId = Number(id)
  const { toast } = useToast()
  const confirm = useConfirm()

  const enabled = useClaimsFnolEnabled()
  const hasRole = getStoredRoles().some((r) => CLAIMS_FNOL_ROLES.includes(r))
  const canQuery = enabled && hasRole

  const { data: fnol, isLoading, isError, error, refetch } = useFnol(fnolId, canQuery)
  const updateFnol = useUpdateFnol(fnolId)
  const convertFnol = useConvertFnol(fnolId)
  const closeFnol = useCloseFnol(fnolId)

  const [editing, setEditing] = useState(false)
  const [draft, setDraft] = useState<UpdateFnolPayload>({})
  const [closing, setClosing] = useState(false)
  const [closeReason, setCloseReason] = useState('')

  // Seed the edit draft whenever we enter edit mode / the record refreshes.
  useEffect(() => {
    if (fnol && editing) {
      setDraft({
        claimant_name: fnol.claimant_name,
        description: fnol.description,
        policy_number: fnol.policy_number,
        claim_type: fnol.claim_type,
        loss_date: fnol.loss_date,
        contact_phone: fnol.contact_phone,
        contact_email: fnol.contact_email,
        estimate_amount: fnol.estimate_amount,
        outstanding_docs: fnol.outstanding_docs ?? [],
      })
    }
  }, [editing, fnol])

  const status = (error as { response?: { status?: number } } | undefined)?.response?.status
  const featureOff = !enabled || status === 404

  if (featureOff) {
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
  if (!hasRole || status === 403) {
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
  if (isLoading) {
    return <div className="p-12 flex justify-center"><LoadingSpinner size="lg" /></div>
  }
  if (isError || !fnol) {
    return (
      <div className="p-8 text-center">
        <p className="text-status-danger-fg font-medium">Could not load this FNOL.</p>
        <button onClick={() => refetch()} className="mt-4 text-sm text-primary underline">Try again</button>
      </div>
    )
  }

  const isOpen = fnol.status === 'open'
  const inputCls = 'w-full px-3 py-2 border border-line rounded-md text-sm bg-surface text-ink'
  const labelCls = 'block text-sm font-medium text-ink mb-1'

  function patchDraft(p: Partial<UpdateFnolPayload>) {
    setDraft((prev) => ({ ...prev, ...p }))
  }

  async function handleSave() {
    try {
      await updateFnol.mutateAsync(draft)
      toast.success('FNOL updated')
      setEditing(false)
    } catch (err) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
      toast.error(msg || 'Could not save changes.')
    }
  }

  async function handleConvert() {
    const ok = await confirm({
      title: 'Convert to claim',
      message: 'Create a full claim from this FNOL? The FNOL will be marked converted.',
      confirmText: 'Convert',
    })
    if (!ok) return
    try {
      const res = await convertFnol.mutateAsync()
      toast.success(`Claim ${res.claim_number} created`)
      navigate(`/claims/${res.claim_id}`)
    } catch (err) {
      // 422 → the FNOL lacks a valid policy or loss_date; surface the message.
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
      toast.error(msg || 'Could not convert this FNOL to a claim.')
    }
  }

  async function handleClose() {
    try {
      await closeFnol.mutateAsync(closeReason.trim() || undefined)
      toast.success('FNOL closed')
      setClosing(false)
      setCloseReason('')
    } catch (err) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
      toast.error(msg || 'Could not close this FNOL.')
    }
  }

  return (
    <div className="max-w-3xl space-y-4">
      {/* Header */}
      <div className="flex items-start justify-between flex-wrap gap-3">
        <div>
          <button onClick={() => navigate('/claims/fnol')} className="text-xs text-primary underline mb-1">← Back to FNOL list</button>
          <div className="flex items-center gap-3">
            <h1 className="font-heading text-2xl font-bold text-ink">{fnol.fnol_number}</h1>
            <FnolStatusBadge status={fnol.status} />
          </div>
          <p className="text-xs text-ink-muted mt-0.5">Recorded {fmtDateTime(fnol.created_at)}</p>
        </div>
        {isOpen && !editing && (
          <div className="flex items-center gap-2">
            <Button variant="secondary" onClick={() => setEditing(true)}>Edit</Button>
            <Button variant="secondary" onClick={() => setClosing((v) => !v)}>Close</Button>
            <Button onClick={handleConvert} loading={convertFnol.isPending}>Convert to claim</Button>
          </div>
        )}
      </div>

      {/* Converted / closed banner */}
      {fnol.status === 'converted' && (
        <div className="rounded-lg border border-status-success-fg/20 bg-status-success-bg text-status-success-fg px-4 py-3 text-sm flex items-center justify-between gap-3 flex-wrap">
          <span>This FNOL has been converted to a claim.</span>
          {fnol.converted_claim_id && (
            <button onClick={() => navigate(`/claims/${fnol.converted_claim_id}`)} className="underline font-medium">
              View claim
            </button>
          )}
        </div>
      )}
      {fnol.status === 'closed' && (
        <div className="rounded-lg border border-line bg-surface-2 text-ink-muted px-4 py-3 text-sm">
          This FNOL has been closed.
        </div>
      )}

      {/* Close reason panel */}
      {closing && isOpen && (
        <div className="rounded-lg border border-line bg-surface p-4 space-y-3">
          <label className={labelCls}>Reason for closing <span className="text-ink-faint font-normal">(optional)</span></label>
          <textarea value={closeReason} onChange={(e) => setCloseReason(e.target.value)} rows={2} className={inputCls} placeholder="e.g. Duplicate report / claimant withdrew" />
          <div className="flex items-center gap-2">
            <Button variant="danger" onClick={handleClose} loading={closeFnol.isPending}>Confirm close</Button>
            <Button variant="ghost" onClick={() => { setClosing(false); setCloseReason('') }}>Cancel</Button>
          </div>
        </div>
      )}

      {/* Reminder status — compact strip; this is minor metadata, not a headline KPI */}
      <div className="bg-surface rounded-lg border border-line px-4 py-2.5 flex flex-wrap items-center gap-x-6 gap-y-1 text-sm">
        <div>
          <span className="text-[11px] uppercase tracking-wide text-ink-muted">Reminders sent</span>
          <span className="ml-2 font-semibold tabular-nums text-ink">{fnol.reminder_count}</span>
        </div>
        <div className="hidden sm:block h-4 w-px bg-line" aria-hidden="true" />
        <div>
          <span className="text-[11px] uppercase tracking-wide text-ink-muted">Last reminder</span>
          <span className="ml-2 font-medium text-ink">{fnol.last_reminder_at ? fmtDateTime(fnol.last_reminder_at) : '—'}</span>
        </div>
      </div>

      {/* Body — view or edit */}
      {editing ? (
        <div className="bg-surface rounded-lg border border-line p-5 space-y-4">
          <div>
            <label className={labelCls}>Claimant name <span className="text-status-danger-fg">*</span></label>
            <input type="text" value={draft.claimant_name ?? ''} onChange={(e) => patchDraft({ claimant_name: e.target.value })} className={inputCls} />
          </div>
          <div>
            <label className={labelCls}>Description <span className="text-status-danger-fg">*</span></label>
            <textarea value={draft.description ?? ''} onChange={(e) => patchDraft({ description: e.target.value })} rows={4} className={inputCls} />
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className={labelCls}>Policy number</label>
              <input type="text" value={draft.policy_number ?? ''} onChange={(e) => patchDraft({ policy_number: e.target.value || null })} className={inputCls} />
            </div>
            <div>
              <label className={labelCls}>Claim type</label>
              <input type="text" value={draft.claim_type ?? ''} onChange={(e) => patchDraft({ claim_type: e.target.value || null })} className={inputCls} />
            </div>
            <div>
              <label className={labelCls}>Loss date</label>
              <input type="date" value={draft.loss_date ?? ''} onChange={(e) => patchDraft({ loss_date: e.target.value || null })} className={inputCls} />
            </div>
            <div>
              <label className={labelCls}>Estimate amount (BWP)</label>
              <input
                type="text"
                inputMode="decimal"
                value={draft.estimate_amount ?? ''}
                onChange={(e) => {
                  const v = e.target.value.trim()
                  patchDraft({ estimate_amount: v === '' ? null : Number(v.replace(/,/g, '')) })
                }}
                className={inputCls}
              />
            </div>
            <div>
              <label className={labelCls}>Contact phone</label>
              <input type="tel" value={draft.contact_phone ?? ''} onChange={(e) => patchDraft({ contact_phone: e.target.value || null })} className={inputCls} />
            </div>
            <div>
              <label className={labelCls}>Contact email</label>
              <input type="email" value={draft.contact_email ?? ''} onChange={(e) => patchDraft({ contact_email: e.target.value || null })} className={inputCls} />
            </div>
          </div>
          <div>
            <label className={labelCls}>Outstanding documents</label>
            <OutstandingDocsInput value={draft.outstanding_docs ?? []} onChange={(next) => patchDraft({ outstanding_docs: next })} />
          </div>
          <div className="flex items-center gap-2 pt-2 border-t border-line">
            <Button onClick={handleSave} loading={updateFnol.isPending} disabled={!draft.claimant_name?.trim() || !draft.description?.trim()}>Save changes</Button>
            <Button variant="ghost" onClick={() => setEditing(false)}>Cancel</Button>
          </div>
        </div>
      ) : (
        <>
          <div className="bg-surface rounded-lg border border-line p-5">
            <Row label="Claimant">{fnol.claimant_name}</Row>
            <Row label="Description"><span className="whitespace-pre-wrap">{fnol.description}</span></Row>
            <Row label="Policy">
              {fnol.policy_id ? (
                <button onClick={() => navigate(`/policies/${fnol.policy_id}`)} className="text-brand-navy hover:underline">
                  {fnol.policy_number || `#${fnol.policy_id}`}
                </button>
              ) : (fnol.policy_number || <span className="text-ink-faint">Not linked yet</span>)}
            </Row>
            <Row label="Claim type">{fnol.claim_type || <span className="text-ink-faint">—</span>}</Row>
            <Row label="Loss date">{fmtDate(fnol.loss_date)}</Row>
            <Row label="Estimate">{fnol.estimate_amount != null ? fmtPula(fnol.estimate_amount) : <span className="text-ink-faint">—</span>}</Row>
            <Row label="Contact phone">{fnol.contact_phone || <span className="text-ink-faint">—</span>}</Row>
            <Row label="Contact email">{fnol.contact_email || <span className="text-ink-faint">—</span>}</Row>
            <Row label="Last updated">{fmtDateTime(fnol.updated_at)}</Row>
          </div>

          <div className="bg-surface rounded-lg border border-line p-5">
            <h2 className="text-sm font-semibold text-ink-muted uppercase tracking-wide mb-3">Outstanding Documents</h2>
            <OutstandingDocsInput value={fnol.outstanding_docs ?? []} onChange={() => {}} disabled />
          </div>
        </>
      )}
    </div>
  )
}
