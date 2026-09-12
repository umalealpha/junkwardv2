import { useCallback, useEffect, useState } from 'react'
import { useToast } from '../../components/common/Toast'
import Modal from '../../components/common/Modal'
import {
  getPremiumConfirmations,
  recordPremiumDecision,
  draftCollectionLetter,
  type PremiumConfirmationList,
  type PremiumConfirmationRow,
} from '../../api/premiumConfirmations'

/**
 * Premium Confirmation Tracker.
 *
 * The question this screen exists to answer, in the CFO's words: is Finance
 * releasing premium confirmations within 24 hours? So the overdue count leads,
 * and every open row shows how long it has been waiting.
 *
 * Paid-up confirmations release themselves and appear here already closed —
 * they are shown, not hidden, because "how many never needed a person" is the
 * measure of whether the automation is working.
 *
 * Renders a plain not-enabled state while the flag is off.
 */
export default function PremiumConfirmationTrackerPage() {
  const { toast } = useToast()

  const [list, setList] = useState<PremiumConfirmationList | null>(null)
  const [loading, setLoading] = useState(true)
  const [enabled, setEnabled] = useState(true)
  const [filter, setFilter] = useState<'all' | 'open' | 'overdue' | 'hold'>('open')

  const [acting, setActing] = useState<PremiumConfirmationRow | null>(null)
  const [comment, setComment] = useState('')
  const [saving, setSaving] = useState(false)
  const [draft, setDraft] = useState<{ audience: string; subject: string; body: string } | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const params: Parameters<typeof getPremiumConfirmations>[0] = { per_page: 50 }
      if (filter === 'open') params.status = 'pending_finance'
      if (filter === 'overdue') params.overdue = true
      setList(await getPremiumConfirmations(params))
      setEnabled(true)
    } catch (err: any) {
      if (err?.response?.status === 404) setEnabled(false)
      else toast.error('Could not load the tracker.')
    } finally {
      setLoading(false)
    }
  }, [filter, toast])

  useEffect(() => { void load() }, [load])

  async function save(decision: 'confirmed' | 'queried') {
    if (!acting) return
    if (decision === 'queried' && !comment.trim()) {
      toast.error('Please say what needs checking.')
      return
    }
    setSaving(true)
    try {
      await recordPremiumDecision(acting.id, decision, comment.trim())
      toast.success(decision === 'confirmed' ? 'Premium confirmed.' : 'Sent back with your query.')
      setActing(null)
      setComment('')
      void load()
    } catch (err: any) {
      toast.error(err?.response?.data?.message || 'Could not save it.')
    } finally {
      setSaving(false)
    }
  }

  async function makeDraft(row: PremiumConfirmationRow) {
    try {
      setDraft(await draftCollectionLetter(row.id))
    } catch {
      toast.error('Could not draft the letter.')
    }
  }

  if (!enabled) {
    return (
      <div className="p-6">
        <h1 className="text-lg font-semibold text-ink">Premium Confirmations</h1>
        <p className="mt-2 text-sm text-ink-muted">
          This is built but not switched on yet. An administrator can enable it under
          Admin &gt; Integrations.
        </p>
      </div>
    )
  }

  const rows = (list?.data ?? []).filter(r => (filter === 'hold' ? !!r.settlement_hold : true))
  const s = list?.summary

  return (
    <div className="p-6 space-y-5">
      <div>
        <h1 className="text-lg font-semibold text-ink">Premium Confirmations</h1>
        <p className="mt-1 text-sm text-ink-muted">
          Raised automatically when a claim is registered. A policy that is paid up releases
          itself; anything outstanding comes to a person. Nothing here declines a claim.
        </p>
      </div>

      {/* The overdue number leads, because that is the question this page answers. */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        <Tile label={`Overdue (past ${s?.slaHours ?? 24}h)`} value={s?.overdue ?? 0} tone={(s?.overdue ?? 0) > 0 ? 'bad' : 'good'} />
        <Tile label="Waiting on Finance" value={s?.open ?? 0} />
        <Tile label="Released with no person" value={s?.autoReleased ?? 0} tone="good" />
        <Tile label="Held for management" value={s?.onHold ?? 0} tone={(s?.onHold ?? 0) > 0 ? 'warn' : undefined} />
      </div>

      <div className="flex flex-wrap gap-2">
        {(['open', 'overdue', 'hold', 'all'] as const).map(f => (
          <button
            key={f}
            onClick={() => setFilter(f)}
            className={`px-3 py-1.5 text-xs font-medium rounded-md border transition ${
              filter === f
                ? 'bg-primary text-primary-contrast border-primary'
                : 'border-line text-ink-muted hover:bg-surface-2'
            }`}
          >
            {f === 'open' ? 'Waiting' : f === 'overdue' ? 'Overdue' : f === 'hold' ? 'On hold' : 'Everything'}
          </button>
        ))}
      </div>

      <div className="bg-surface border border-line rounded-xl overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-surface-2 text-ink-muted">
              <tr>
                {['Claim', 'Premium status', 'Balance', 'Unpaid from', 'Waiting', 'State', ''].map(h => (
                  <th key={h} className="text-left font-medium px-4 py-2.5 whitespace-nowrap">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {loading && (
                <tr><td colSpan={7} className="px-4 py-8 text-center text-ink-muted">Loading…</td></tr>
              )}
              {!loading && rows.length === 0 && (
                <tr><td colSpan={7} className="px-4 py-8 text-center text-ink-muted">Nothing here.</td></tr>
              )}
              {!loading && rows.map(r => (
                <tr key={r.id} className="border-t border-line align-top">
                  <td className="px-4 py-2.5 whitespace-nowrap">
                    <span className="font-medium text-ink">{r.claim_number || r.claim_id}</span>
                    <span className="block text-[11px] text-ink-faint">{r.claim_type}</span>
                  </td>
                  <td className="px-4 py-2.5">
                    <Light light={r.light} />
                    <span className="ml-2">{r.premium_status || '—'}</span>
                    {!!r.settlement_hold && (
                      <span className="block mt-1 text-[11px] text-amber-700">
                        {r.premiums_outstanding} premiums outstanding — settlement held for management
                      </span>
                    )}
                  </td>
                  <td className="px-4 py-2.5 whitespace-nowrap">
                    {r.balance != null ? `P ${Number(r.balance).toLocaleString(undefined, { minimumFractionDigits: 2 })}` : '—'}
                  </td>
                  <td className="px-4 py-2.5 whitespace-nowrap">{r.unpaid_from || '—'}</td>
                  <td className="px-4 py-2.5 whitespace-nowrap">
                    {r.hoursOpen != null ? `${r.hoursOpen}h` : '—'}
                    {r.overdue && (
                      <span className="ml-1.5 text-[11px] font-medium text-red-700">
                        overdue{r.breachedOnRest ? ' (weekend)' : ''}
                      </span>
                    )}
                  </td>
                  <td className="px-4 py-2.5 whitespace-nowrap">
                    <State status={r.status} />
                    {r.released_by && <span className="block text-[11px] text-ink-faint">{r.released_by}</span>}
                  </td>
                  <td className="px-4 py-2.5 whitespace-nowrap text-right">
                    {r.status === 'pending_finance' || r.status === 'queried' ? (
                      <div className="flex gap-1.5 justify-end">
                        <button
                          onClick={() => { setActing(r); setComment('') }}
                          className="px-2.5 py-1 text-xs font-medium rounded-md border border-line text-ink-muted hover:bg-surface-2"
                        >
                          Sign off
                        </button>
                        {r.light === 'red' && (
                          <button
                            onClick={() => void makeDraft(r)}
                            className="px-2.5 py-1 text-xs font-medium rounded-md border border-line text-ink-muted hover:bg-surface-2"
                          >
                            Draft chase
                          </button>
                        )}
                      </div>
                    ) : null}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      <Modal
        open={!!acting}
        onClose={() => setActing(null)}
        title="Premium confirmation"
        size="md"
        footer={
          <div className="flex justify-end gap-2">
            <button onClick={() => setActing(null)} className="px-3 py-1.5 text-xs font-medium rounded-md border border-line text-ink-muted hover:bg-surface-2">
              Cancel
            </button>
            <button onClick={() => void save('queried')} disabled={saving} className="px-3 py-1.5 text-xs font-medium rounded-md border border-line text-ink-muted hover:bg-surface-2 disabled:opacity-50">
              Send back with a query
            </button>
            <button onClick={() => void save('confirmed')} disabled={saving} className="px-3 py-1.5 text-xs font-semibold rounded-md bg-primary text-primary-contrast hover:opacity-90 disabled:opacity-50">
              {saving ? 'Saving…' : 'Confirm the premium'}
            </button>
          </div>
        }
      >
        {acting && (
          <div className="space-y-3 text-sm">
            <p className="text-ink-muted">
              Claim <span className="font-medium text-ink">{acting.claim_number}</span> ·{' '}
              {acting.premium_status} · balance{' '}
              <span className="font-medium text-ink">
                P {Number(acting.balance ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}
              </span>
            </p>
            <p className="text-[12px] rounded-md px-3 py-2 bg-surface-2 text-ink-muted">
              Before signing off: check the transaction log for a payment that has not reached the
              statement, trace anything missing on the paygate, and look for money sitting in
              unidentified income or on the broker board. If the money is in but not posted, fix the
              posting rather than reporting arrears.
            </p>
            <div>
              <label htmlFor="pc-comment" className="block text-[11px] font-medium text-ink-muted mb-1">
                Comment {acting.light !== 'green' && <span className="text-ink-faint">(required if you are querying it)</span>}
              </label>
              <textarea
                id="pc-comment"
                rows={3}
                value={comment}
                maxLength={2000}
                onChange={e => setComment(e.target.value)}
                className="w-full px-2.5 py-2 text-sm rounded-md bg-surface text-ink border border-line focus:outline-none focus:ring-1 focus:ring-primary"
              />
            </div>
          </div>
        )}
      </Modal>

      <Modal
        open={!!draft}
        onClose={() => setDraft(null)}
        title={`Draft chase — to the ${draft?.audience ?? ''}`}
        size="lg"
        footer={
          <div className="flex justify-end gap-2">
            <button onClick={() => setDraft(null)} className="px-3 py-1.5 text-xs font-medium rounded-md border border-line text-ink-muted hover:bg-surface-2">
              Close
            </button>
            <button
              onClick={() => { void navigator.clipboard.writeText(draft?.body ?? ''); toast.success('Copied.') }}
              className="px-3 py-1.5 text-xs font-semibold rounded-md bg-primary text-primary-contrast hover:opacity-90"
            >
              Copy it
            </button>
          </div>
        }
      >
        <div className="space-y-3 text-sm">
          <p className="text-ink-muted">
            Nothing has been sent. Read it, change what you want, and send it yourself.
          </p>
          <p><span className="text-ink-faint">Subject:</span> <span className="font-medium text-ink">{draft?.subject}</span></p>
          <pre className="whitespace-pre-wrap text-[13px] bg-surface-2 rounded-md p-3 text-ink">{draft?.body}</pre>
        </div>
      </Modal>
    </div>
  )
}

function Tile({ label, value, tone }: { label: string; value: number; tone?: 'good' | 'bad' | 'warn' }) {
  const colour =
    tone === 'bad' ? 'text-red-700' : tone === 'good' ? 'text-emerald-700' : tone === 'warn' ? 'text-amber-700' : 'text-ink'
  return (
    <div className="bg-surface border border-line rounded-xl px-4 py-3">
      <p className="text-[11px] uppercase tracking-wide text-ink-faint">{label}</p>
      <p className={`mt-1 text-2xl font-semibold ${colour}`}>{value}</p>
    </div>
  )
}

function Light({ light }: { light: string | null }) {
  const map: Record<string, string> = {
    green: 'bg-emerald-500',
    amber: 'bg-amber-500',
    red: 'bg-red-500',
  }
  return <span className={`inline-block w-2 h-2 rounded-full align-middle ${map[light ?? ''] ?? 'bg-slate-300'}`} />
}

function State({ status }: { status: PremiumConfirmationRow['status'] }) {
  const label: Record<string, string> = {
    auto_released: 'Released automatically',
    pending_finance: 'Waiting on Finance',
    confirmed: 'Confirmed',
    queried: 'Queried',
  }
  return <span className="text-ink">{label[status] ?? status}</span>
}
