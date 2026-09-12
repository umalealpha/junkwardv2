import { useState } from 'react'
import {
  useClaimDecision,
  useDecideClaim,
  useReverseClaimDecision,
  useCanDecideClaim,
  useCanReverseClaimDecision,
  useClaimDecisionPreview,
} from '../../hooks/useClaimsDecision'
import type { ClaimDecision } from '../../api/claimsDecision'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import EmptyState from '../../components/common/EmptyState'
import { useToast } from '../../components/common/Toast'
import { fmtDateTime } from '../../utils/format'

/** Status chip for a decision (approved / repudiated / reversed). */
function DecisionChip({ d }: { d: ClaimDecision }) {
  if (d.reversed_at) {
    return <span className="inline-block px-2 py-0.5 rounded-full text-[11px] font-medium bg-surface-2 text-ink-muted border border-line">Reversed</span>
  }
  const cls = d.decision_status === 'approved'
    ? 'bg-status-success-bg text-status-success-fg'
    : 'bg-status-danger-bg text-status-danger-fg'
  return <span className={`inline-block px-2 py-0.5 rounded-full text-[11px] font-medium ${cls}`}>{d.decision_status === 'approved' ? 'Approved' : 'Repudiated'}</span>
}

interface Props {
  claimId: number
}

export default function ClaimDecisionTab({ claimId }: Props) {
  const { toast } = useToast()
  const q = useClaimDecision(claimId)
  const decide = useDecideClaim(claimId)
  const reverse = useReverseClaimDecision(claimId)

  const canDecide = useCanDecideClaim()
  const canReverse = useCanReverseClaimDecision()
  const isPreview = useClaimDecisionPreview()

  const [note, setNote] = useState('')
  const [noteError, setNoteError] = useState('')
  const [showReverse, setShowReverse] = useState(false)
  const [reverseNote, setReverseNote] = useState('')

  // ── Loading / off / error states ──────────────────────────────────────────
  const status = (q.error as any)?.response?.status
  if (status === 404) {
    return <div className="py-6"><EmptyState compact title="Claims decision workflow is not enabled" description="This module is currently turned off." /></div>
  }
  if (status === 403) {
    return <div className="py-6"><EmptyState compact title="No access" description="You do not have permission to view claim decisions." /></div>
  }
  if (q.isLoading) {
    return <div className="py-10 flex justify-center"><LoadingSpinner /></div>
  }
  if (q.isError && !q.data) {
    return (
      <div className="py-6 text-center">
        <p className="text-status-danger-fg text-sm">Could not load the claim decision.</p>
        <button onClick={() => q.refetch()} className="mt-3 text-sm text-primary underline">Try again</button>
      </div>
    )
  }

  const state = q.data
  const current = state?.current ?? null
  const history = state?.history ?? []

  async function submit(decision: 'approved' | 'repudiated') {
    setNoteError('')
    if (decision === 'repudiated' && !note.trim()) {
      setNoteError('A note is required when repudiating a claim.')
      return
    }
    try {
      await decide.mutateAsync({ status: decision, note: note.trim() || null })
      toast.success(decision === 'approved' ? 'Claim approved.' : 'Claim repudiated.')
      setNote('')
    } catch (err: any) {
      const resp = err?.response
      if (resp?.status === 422 && resp.data?.errors?.note) {
        setNoteError(Array.isArray(resp.data.errors.note) ? resp.data.errors.note[0] : String(resp.data.errors.note))
      } else {
        toast.error(resp?.data?.message || 'Failed to record the decision.')
      }
    }
  }

  async function submitReverse() {
    try {
      await reverse.mutateAsync(reverseNote.trim() || null)
      toast.success('Decision reversed. The claim can be re-decided.')
      setShowReverse(false)
      setReverseNote('')
    } catch (err: any) {
      toast.error(err?.response?.data?.message || 'Failed to reverse the decision.')
    }
  }

  return (
    <div className="space-y-5 pt-4">
      {isPreview && (
        <div className="flex items-start gap-2 rounded-lg border border-status-warning-fg/40 bg-status-warning-bg px-4 py-2.5 text-xs text-status-warning-fg">
          <span aria-hidden>👁</span>
          <span>Admin preview — this workflow is turned OFF for the wider team. Decisions you record here are real data writes, but the feature stays hidden from non-admins until enabled in Admin &gt; Integrations.</span>
        </div>
      )}

      {/* ── Current decision ── */}
      <section className="bg-surface rounded-xl border border-line shadow-sm overflow-hidden">
        <div className="bg-surface-2 px-5 py-3 border-b border-line flex items-center justify-between gap-2 flex-wrap">
          <h2 className="text-sm font-semibold text-ink-muted uppercase tracking-wide">Decision</h2>
          {current && <DecisionChip d={current} />}
        </div>

        {current ? (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3 px-5 py-4 text-sm">
            <div><span className="text-ink-faint">Outcome:</span> <span className="font-medium text-ink capitalize">{current.decision_status}</span></div>
            <div><span className="text-ink-faint">Decided by:</span> <span className="font-medium text-ink">{current.decided_by_name || '—'}{current.decided_by_role ? ` (${current.decided_by_role})` : ''}</span></div>
            <div><span className="text-ink-faint">Decision date:</span> <span className="font-medium text-ink">{current.decision_date ? fmtDateTime(current.decision_date) : '—'}</span></div>
            <div className="md:col-span-2"><span className="text-ink-faint">Note:</span> <span className="font-medium text-ink whitespace-pre-wrap break-words">{current.decision_note || '—'}</span></div>
          </div>
        ) : (
          <div className="px-5 py-4">
            <p className="text-sm text-ink-muted">No decision has been recorded for this claim yet.</p>
          </div>
        )}

        {/* Reverse action (Admin / Claims Manager) — only when there is an active decision. */}
        {current && canReverse && (
          <div className="px-5 pb-4">
            {!showReverse ? (
              <button
                onClick={() => setShowReverse(true)}
                className="px-3 py-1.5 text-xs font-medium rounded-md border border-status-warning-fg text-status-warning-fg hover:bg-status-warning-bg transition"
              >
                Reverse decision
              </button>
            ) : (
              <div className="space-y-2 max-w-lg">
                <label className="block text-[11px] font-medium text-ink-muted">Reversal note (optional)</label>
                <textarea
                  value={reverseNote}
                  rows={2}
                  maxLength={2000}
                  onChange={(e) => setReverseNote(e.target.value)}
                  className="w-full px-2.5 py-2 text-sm rounded-md bg-surface text-ink border border-line focus:outline-none focus:ring-1 focus:ring-primary"
                  placeholder="Why is this decision being reversed?"
                />
                <div className="flex items-center gap-2">
                  <button
                    onClick={submitReverse}
                    disabled={reverse.isPending}
                    className="px-3 py-1.5 text-xs font-semibold rounded-md bg-status-warning-fg text-white hover:opacity-90 transition disabled:opacity-50"
                  >
                    {reverse.isPending ? 'Reversing…' : 'Confirm reversal'}
                  </button>
                  <button
                    onClick={() => { setShowReverse(false); setReverseNote('') }}
                    disabled={reverse.isPending}
                    className="px-3 py-1.5 text-xs font-medium rounded-md border border-line text-ink-muted hover:bg-surface-2 transition disabled:opacity-50"
                  >
                    Cancel
                  </button>
                </div>
                <p className="text-[11px] text-ink-faint">The original decision is preserved for audit; the claim can then be re-decided.</p>
              </div>
            )}
          </div>
        )}
      </section>

      {/* ── Record a decision ── (only when none active + the user may decide) */}
      {!current && canDecide && (
        <section className="bg-surface rounded-xl border border-line shadow-sm overflow-hidden">
          <div className="bg-surface-2 px-5 py-3 border-b border-line">
            <h2 className="text-sm font-semibold text-ink-muted uppercase tracking-wide">Record a decision</h2>
          </div>
          <div className="p-5 space-y-3 max-w-lg">
            <div>
              <label htmlFor="decision-note" className="block text-[11px] font-medium text-ink-muted mb-1">
                Note <span className="text-ink-faint">(required to repudiate)</span>
              </label>
              <textarea
                id="decision-note"
                value={note}
                rows={3}
                maxLength={2000}
                onChange={(e) => setNote(e.target.value)}
                className={`w-full px-2.5 py-2 text-sm rounded-md bg-surface text-ink border ${noteError ? 'border-status-danger-fg' : 'border-line'} focus:outline-none focus:ring-1 focus:ring-primary`}
                placeholder="Reason / supporting note for the decision"
              />
              {noteError && <p className="text-[11px] text-status-danger-fg mt-0.5">{noteError}</p>}
            </div>
            <div className="flex items-center gap-2">
              <button
                onClick={() => submit('approved')}
                disabled={decide.isPending}
                className="px-4 py-2 text-sm font-semibold rounded-md bg-status-success-fg text-white hover:opacity-90 transition disabled:opacity-50"
              >
                {decide.isPending ? 'Saving…' : 'Approve'}
              </button>
              <button
                onClick={() => submit('repudiated')}
                disabled={decide.isPending}
                className="px-4 py-2 text-sm font-semibold rounded-md bg-status-danger-fg text-white hover:opacity-90 transition disabled:opacity-50"
              >
                {decide.isPending ? 'Saving…' : 'Repudiate'}
              </button>
            </div>
            <p className="text-[11px] text-ink-faint">
              The decision date is stamped by the server. This is recorded separately from the claim's status — it does not change the claim status.
            </p>
          </div>
        </section>
      )}

      {/* ── Decision history ── */}
      {history.length > 0 && (
        <section className="bg-surface rounded-xl border border-line shadow-sm overflow-hidden">
          <div className="bg-surface-2 px-5 py-3 border-b border-line">
            <h2 className="text-sm font-semibold text-ink-muted uppercase tracking-wide">History</h2>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="text-xs text-ink-muted uppercase">
                <tr>
                  <th className="text-left px-5 py-2">Outcome</th>
                  <th className="text-left py-2">Decided by</th>
                  <th className="text-left py-2">Date</th>
                  <th className="text-left py-2">Note</th>
                  <th className="text-left px-5 py-2">Reversal</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {history.map((d) => (
                  <tr key={d.id}>
                    <td className="px-5 py-2"><DecisionChip d={d} /></td>
                    <td className="py-2 text-ink">{d.decided_by_name || '—'}{d.decided_by_role ? <span className="text-ink-faint"> ({d.decided_by_role})</span> : null}</td>
                    <td className="py-2 text-ink-muted whitespace-nowrap">{d.decision_date ? fmtDateTime(d.decision_date) : '—'}</td>
                    <td className="py-2 text-ink-muted max-w-xs truncate" title={d.decision_note ?? ''}>{d.decision_note || '—'}</td>
                    <td className="px-5 py-2 text-ink-muted">
                      {d.reversed_at
                        ? <span title={d.reversal_note ?? ''}>{fmtDateTime(d.reversed_at)}{d.reversed_by_name ? ` · ${d.reversed_by_name}` : ''}</span>
                        : <span className="text-ink-faint">—</span>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      )}
    </div>
  )
}
