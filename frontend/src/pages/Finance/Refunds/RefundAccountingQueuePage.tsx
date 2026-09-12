import { useState } from 'react'
import { Link } from 'react-router-dom'
import {
  useDismissRefundAccounting,
  usePostRefundAccounting,
  useRefundAccountingList,
} from '../../../hooks/useRefundRequests'
import type { RefundAccountingEntry } from '../../../api/refundRequests'
import { fmtDate, fmtPula } from '../../../utils/format'
import { AREA_LABEL, apiErrorMessage, useRefundAccess } from './refundShared'

const TABS = [
  { key: 'pending_review', label: 'Pending review' },
  { key: 'posted', label: 'Posted' },
  { key: 'dismissed', label: 'Dismissed' },
] as const

/**
 * Finance review-and-post queue (CFO decision 2026-07-26): return-premium
 * refunds that have been PAID prepare a Credit Note entry here. Finance
 * reviews the split/dates and posts — raising the Credit Note + ledger +
 * sub-ledger reversal — or dismisses with a reason. Nothing auto-posts.
 */
export default function RefundAccountingQueuePage() {
  const access = useRefundAccess()
  const [tab, setTab] = useState<string>('pending_review')
  const [page, setPage] = useState(1)
  const list = useRefundAccountingList({ status: tab, page })
  const postMut = usePostRefundAccounting()
  const dismissMut = useDismissRefundAccounting()

  const [active, setActive] = useState<RefundAccountingEntry | null>(null)
  const [mode, setMode] = useState<'post' | 'dismiss' | null>(null)
  const [earned, setEarned] = useState('')
  const [unearned, setUnearned] = useState('')
  const [effective, setEffective] = useState('')
  const [end, setEnd] = useState('')
  const [reason, setReason] = useState('')
  const [error, setError] = useState<string | null>(null)

  if (!access.canPostAccounting) {
    return (
      <div className="p-6">
        <p className="text-sm text-ink-muted">
          You don't have the refund-accounting-post permission — this queue is for Finance.
        </p>
      </div>
    )
  }

  function openPost(e: RefundAccountingEntry) {
    setActive(e); setMode('post'); setError(null)
    setEarned(String(e.earned_premium ?? e.refund_amount))
    setUnearned(String(e.unearned_premium ?? 0))
    setEffective(e.effective_date?.slice(0, 10) ?? '')
    setEnd(e.end_date?.slice(0, 10) ?? '')
  }

  async function confirm() {
    if (!active || !mode) return
    setError(null)
    try {
      if (mode === 'post') {
        await postMut.mutateAsync({
          id: active.id,
          overrides: {
            earned_premium: parseFloat(earned || '0'),
            unearned_premium: parseFloat(unearned || '0'),
            ...(effective ? { effective_date: effective } : {}),
            ...(end ? { end_date: end } : {}),
          },
        })
      } else {
        await dismissMut.mutateAsync({ id: active.id, reason: reason.trim() })
      }
      setActive(null); setMode(null); setReason('')
    } catch (e) {
      setError(apiErrorMessage(e))
    }
  }

  const rows = list.data?.data ?? []
  const meta = list.data?.meta
  const busy = postMut.isPending || dismissMut.isPending
  const total = (parseFloat(earned || '0') || 0) + (parseFloat(unearned || '0') || 0)
  const overCap = active ? total > active.refund_amount + 0.05 : false

  return (
    <div className="p-6 space-y-4">
      <div>
        <Link to="/finance/refund-engine" className="text-sm text-brand-navy hover:underline">← Customer Refunds</Link>
        <h1 className="text-2xl font-bold text-ink mt-1">Refund Accounting — Finance Queue</h1>
        <p className="text-sm text-ink-muted mt-1">
          Paid return-premium refunds prepare a Credit Note entry here. Review the earned/unearned split and
          post — written premium reduces and the numbers tie. Nothing posts without you.
        </p>
      </div>

      <div className="flex gap-1 border-b border-line">
        {TABS.map(t => (
          <button key={t.key} onClick={() => { setTab(t.key); setPage(1) }}
            className={`px-4 py-2 text-sm border-b-2 -mb-px ${tab === t.key
              ? 'border-brand-orange text-ink font-medium'
              : 'border-transparent text-ink-muted hover:text-ink'}`}>
            {t.label}
          </button>
        ))}
      </div>

      <div className="bg-surface rounded-lg shadow-sm overflow-x-auto">
        <table className="min-w-full text-sm">
          <thead>
            <tr className="border-b bg-surface-2 text-left text-xs uppercase tracking-wide text-ink-muted">
              <th className="px-4 py-3">Ref</th>
              <th className="px-4 py-3">Area</th>
              <th className="px-4 py-3">Policy</th>
              <th className="px-4 py-3">Reason</th>
              <th className="px-4 py-3 text-right">Refunded</th>
              <th className="px-4 py-3 text-right">Prepared CN</th>
              <th className="px-4 py-3">{tab === 'posted' ? 'Credit Note' : tab === 'dismissed' ? 'Dismissed' : 'Paid'}</th>
              {tab === 'pending_review' && <th className="px-4 py-3 text-right">Actions</th>}
            </tr>
          </thead>
          <tbody>
            {list.isLoading && <tr><td colSpan={8} className="px-4 py-8 text-center text-ink-faint">Loading…</td></tr>}
            {!list.isLoading && rows.length === 0 && (
              <tr><td colSpan={8} className="px-4 py-8 text-center text-ink-faint">Nothing here.</td></tr>
            )}
            {rows.map(e => (
              <tr key={e.id} className="border-b last:border-0 hover:bg-surface-2">
                <td className="px-4 py-2.5">
                  <Link to={`/finance/refund-engine/${e.refund_request_id}`} className="text-brand-navy font-medium hover:underline">
                    {e.graphite_ref}
                  </Link>
                </td>
                <td className="px-4 py-2.5 text-ink-muted">{AREA_LABEL[e.area]}</td>
                <td className="px-4 py-2.5">{e.policy_number}</td>
                <td className="px-4 py-2.5 text-ink-muted">{e.reason_code?.replace(/_/g, ' ') || '—'}</td>
                <td className="px-4 py-2.5 text-right font-medium">{fmtPula(e.refund_amount)}</td>
                <td className="px-4 py-2.5 text-right">{fmtPula((e.earned_premium ?? 0) + (e.unearned_premium ?? 0))}</td>
                <td className="px-4 py-2.5 text-ink-muted">
                  {tab === 'posted' ? (e.credit_note_no || '—')
                    : tab === 'dismissed' ? (e.dismiss_reason || '—')
                    : fmtDate(e.refund_request?.omni_paid_at ?? e.created_at)}
                </td>
                {tab === 'pending_review' && (
                  <td className="px-4 py-2.5 text-right whitespace-nowrap">
                    <button onClick={() => openPost(e)} disabled={busy}
                      className="px-2.5 py-1.5 text-xs rounded bg-brand-navy text-white hover:opacity-90 disabled:opacity-50">
                      Review &amp; Post
                    </button>
                    <button onClick={() => { setActive(e); setMode('dismiss'); setError(null) }} disabled={busy}
                      className="ml-2 px-2.5 py-1.5 text-xs rounded border border-red-300 text-red-700 hover:bg-red-50 disabled:opacity-50">
                      Dismiss
                    </button>
                  </td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {meta && meta.last_page > 1 && (
        <div className="flex items-center justify-between text-sm text-ink-muted">
          <span>Page {meta.current_page} of {meta.last_page} · {meta.total} entries</span>
          <div className="flex gap-2">
            <button disabled={meta.current_page <= 1} onClick={() => setPage(p => p - 1)}
              className="px-3 py-1.5 rounded border border-line disabled:opacity-40">Previous</button>
            <button disabled={meta.current_page >= meta.last_page} onClick={() => setPage(p => p + 1)}
              className="px-3 py-1.5 rounded border border-line disabled:opacity-40">Next</button>
          </div>
        </div>
      )}

      {active && mode && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <div className="bg-surface rounded-lg shadow-lg w-full max-w-md p-5 space-y-4">
            {mode === 'post' ? (
              <>
                <h3 className="text-lg font-semibold text-ink">Post Credit Note — {active.graphite_ref}</h3>
                <p className="text-sm text-ink-muted">
                  Raises CR credit note + policy ledger + sub-ledger reversal for {active.policy_number}.
                  Refunded: <b>{fmtPula(active.refund_amount)}</b> (the CN cannot exceed this).
                </p>
                <div className="grid grid-cols-2 gap-3">
                  <label className="text-xs text-ink-muted">Earned portion
                    <input type="number" min="0" step="0.01" value={earned} onChange={e => setEarned(e.target.value)}
                      className="mt-1 w-full border border-line rounded px-2 py-1.5 text-sm text-ink" />
                  </label>
                  <label className="text-xs text-ink-muted">Unearned portion
                    <input type="number" min="0" step="0.01" value={unearned} onChange={e => setUnearned(e.target.value)}
                      className="mt-1 w-full border border-line rounded px-2 py-1.5 text-sm text-ink" />
                  </label>
                  <label className="text-xs text-ink-muted">Effective date
                    <input type="date" value={effective} onChange={e => setEffective(e.target.value)}
                      className="mt-1 w-full border border-line rounded px-2 py-1.5 text-sm text-ink" />
                  </label>
                  <label className="text-xs text-ink-muted">End date
                    <input type="date" value={end} onChange={e => setEnd(e.target.value)}
                      className="mt-1 w-full border border-line rounded px-2 py-1.5 text-sm text-ink" />
                  </label>
                </div>
                <div className="text-sm text-ink">
                  Total reversal: <b>{fmtPula(total)}</b>
                  {overCap && <span className="text-red-600 ml-2">exceeds the refunded amount</span>}
                </div>
              </>
            ) : (
              <>
                <h3 className="text-lg font-semibold text-ink">Dismiss entry — {active.graphite_ref}</h3>
                <p className="text-sm text-ink-muted">No Credit Note will be raised. A reason is required and audited.</p>
                <textarea value={reason} onChange={e => setReason(e.target.value)} rows={3}
                  placeholder="Why is no premium reversal needed?"
                  className="w-full border border-line rounded px-3 py-2 text-sm" />
              </>
            )}

            {error && <div className="rounded border border-red-200 bg-red-50 text-red-700 text-sm px-3 py-2">{error}</div>}

            <div className="flex justify-end gap-2">
              <button onClick={() => { setActive(null); setMode(null); setError(null) }}
                className="px-3 py-2 text-sm rounded border border-line text-ink">Cancel</button>
              <button
                disabled={busy || (mode === 'post' ? (total <= 0 || overCap) : !reason.trim())}
                onClick={confirm}
                className={`px-3 py-2 text-sm rounded text-white disabled:opacity-40 ${mode === 'post' ? 'bg-green-600' : 'bg-red-600'}`}>
                {mode === 'post' ? (busy ? 'Posting…' : 'Post Credit Note') : (busy ? 'Dismissing…' : 'Dismiss')}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
