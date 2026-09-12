import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import {
  getException, addExceptionComment, updateExceptionStatus,
  type ExceptionComment, type ExStatus,
} from '../../api/exceptions'

const NAVY = '#0D1B2A'
const STATUS_CLS: Record<string, string> = {
  open: 'bg-red-100 text-red-700',
  reviewing: 'bg-blue-100 text-blue-700',
  accepted: 'bg-green-100 text-green-700',
  disputed: 'bg-orange-100 text-orange-700',
  resolved: 'bg-gray-100 text-gray-600',
}
const SEVERITY_CLS: Record<string, string> = {
  critical: 'bg-red-600 text-white', high: 'bg-orange-500 text-white',
  medium: 'bg-amber-300 text-amber-900', low: 'bg-gray-200 text-gray-700',
}
// Allowed next states from the review workflow
const ACTIONS: { status: ExStatus; label: string; cls: string }[] = [
  { status: 'reviewing', label: 'Mark reviewing', cls: 'bg-blue-600 text-white' },
  { status: 'accepted', label: 'Accept', cls: 'bg-green-600 text-white' },
  { status: 'disputed', label: 'Dispute', cls: 'bg-orange-600 text-white' },
  { status: 'resolved', label: 'Resolve', cls: 'bg-gray-700 text-white' },
  { status: 'open', label: 'Reopen', cls: 'bg-red-600 text-white' },
]

function money(v: any): string {
  if (v === null || v === undefined || v === '') return '—'
  const n = Number(v)
  if (Number.isNaN(n)) return String(v)
  return new Intl.NumberFormat('en-BW', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n)
}

function fmtTime(s: string): string {
  try { return new Date(s).toLocaleString('en-BW') } catch { return s }
}

export default function ExceptionDetailPage() {
  const { id } = useParams<{ id: string }>()
  const exId = Number(id)
  const [ex, setEx] = useState<any>(null)
  const [run, setRun] = useState<any>(null)
  const [comments, setComments] = useState<ExceptionComment[]>([])
  const [loading, setLoading] = useState(true)
  const [comment, setComment] = useState('')
  const [busy, setBusy] = useState(false)

  async function load() {
    const data = await getException(exId)
    setEx(data.exception); setRun(data.run); setComments(data.comments ?? [])
  }
  useEffect(() => { load().finally(() => setLoading(false)) }, [exId])

  async function postComment() {
    if (!comment.trim()) return
    setBusy(true)
    try { await addExceptionComment(exId, comment.trim()); setComment(''); await load() }
    finally { setBusy(false) }
  }

  async function setStatus(status: ExStatus) {
    let note: string | undefined
    if (status === 'disputed') {
      const n = window.prompt('Reason for disputing this exception (required):')
      if (!n) return
      note = n
    }
    setBusy(true)
    try { await updateExceptionStatus(exId, status, note); await load() }
    finally { setBusy(false) }
  }

  if (loading) return <div className="p-6 flex items-center justify-center min-h-[400px]"><LoadingSpinner size="lg" /></div>
  if (!ex) return <div className="p-6"><p className="text-gray-500">Exception not found.</p><Link to="/finance/exceptions" className="text-blue-600">← Back</Link></div>

  const detail = ex.detail || {}

  return (
    <div className="p-6 space-y-5 max-w-4xl">
      <Link to="/finance/exceptions" className="text-sm text-blue-600 hover:underline">← Back to Exceptions</Link>

      {/* Header */}
      <div className="rounded-xl px-6 py-5" style={{ background: NAVY }}>
        <div className="flex items-center gap-3">
          <span className="font-mono text-2xl font-bold" style={{ color: '#F4A623' }}>{ex.flag_code}</span>
          <div>
            <h1 className="text-lg font-bold text-white">{ex.flag_label}</h1>
            <p className="text-sm" style={{ color: '#9aa6b2' }}>
              {ex.product} · Policy <span className="font-mono">{ex.policy_number ?? '—'}</span>
              {run?.period_label ? ` · ${run.period_label}` : ''}
            </p>
          </div>
          <div className="ml-auto flex items-center gap-2">
            <span className={`px-2.5 py-1 rounded text-xs font-semibold ${SEVERITY_CLS[ex.severity]}`}>{ex.severity}</span>
            <span className={`px-2.5 py-1 rounded text-xs font-semibold ${STATUS_CLS[ex.status]}`}>{ex.status}</span>
          </div>
        </div>
      </div>

      {/* Facts */}
      <div className="bg-white rounded-xl border shadow-sm p-5 grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
        <Fact label="Product" value={ex.product} />
        <Fact label="Policy number" value={ex.policy_number} mono />
        <Fact label="Customer ID" value={ex.customer_id} mono />
        <Fact label="RealPay contract" value={ex.contract_number} mono />
        <Fact label="Graphite monthly" value={money(ex.graphite_value)} />
        <Fact label="RealPay monthly" value={money(ex.realpay_value)} />
        <Fact label="Variance" value={money(ex.variance)} />
        {Object.entries(detail).map(([k, v]) => (
          <Fact key={k} label={k.replace(/_/g, ' ')} value={typeof v === 'object' ? JSON.stringify(v) : String(v)} />
        ))}
      </div>

      {/* Status workflow */}
      <div className="bg-white rounded-xl border shadow-sm p-5">
        <h2 className="text-sm font-semibold text-gray-700 mb-3">Change status</h2>
        <div className="flex flex-wrap gap-2">
          {ACTIONS.filter(a => a.status !== ex.status).map(a => (
            <button key={a.status} disabled={busy} onClick={() => setStatus(a.status)}
              className={`px-3 py-1.5 rounded text-xs font-semibold disabled:opacity-50 ${a.cls}`}>
              {a.label}
            </button>
          ))}
        </div>
        {ex.reviewed_at && <p className="text-xs text-gray-400 mt-3">Last reviewed {fmtTime(ex.reviewed_at)}</p>}
      </div>

      {/* Comments */}
      <div className="bg-white rounded-xl border shadow-sm p-5">
        <h2 className="text-sm font-semibold text-gray-700 mb-3">Finance review comments</h2>
        <div className="space-y-3 mb-4">
          {comments.length === 0 && <p className="text-sm text-gray-400">No comments yet. Add the first review note below.</p>}
          {comments.map(c => (
            <div key={c.id} className="border-l-2 pl-3" style={{ borderColor: '#F4A623' }}>
              <div className="flex items-baseline gap-2">
                <span className="text-sm font-semibold text-gray-800">{c.user_name || `User #${c.user_id ?? '?'}`}</span>
                <span className="text-xs text-gray-400">{fmtTime(c.created_at)}</span>
              </div>
              <p className="text-sm text-gray-700 whitespace-pre-wrap">{c.comment}</p>
            </div>
          ))}
        </div>
        <textarea
          value={comment} onChange={(e) => setComment(e.target.value)}
          placeholder="Add a review comment…" rows={3}
          className="w-full rounded-md border-gray-300 text-sm p-2"
        />
        <div className="mt-2 flex justify-end">
          <button onClick={postComment} disabled={busy || !comment.trim()}
            className="px-4 py-2 rounded-md text-sm font-semibold text-white disabled:opacity-50" style={{ background: NAVY }}>
            {busy ? 'Saving…' : 'Add comment'}
          </button>
        </div>
      </div>
    </div>
  )
}

function Fact({ label, value, mono }: { label: string; value: any; mono?: boolean }) {
  return (
    <div>
      <div className="text-xs text-gray-400 uppercase tracking-wide">{label}</div>
      <div className={`text-gray-800 ${mono ? 'font-mono text-sm' : ''}`}>{value ?? '—'}</div>
    </div>
  )
}
