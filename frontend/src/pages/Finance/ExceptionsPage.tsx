import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  ResponsiveContainer, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend,
  PieChart, Pie, Cell, LineChart, Line,
} from 'recharts'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import {
  listRuns, getSummary, listExceptions, generateExceptions,
  type ExceptionRun, type ExceptionRow, type ExceptionSummary,
} from '../../api/exceptions'

const NAVY = '#0D1B2A'
const ORANGE = '#F4A623'
const FLAGS: { code: string; label: string }[] = [
  { code: 'A', label: 'No mandate' },
  { code: 'B', label: 'Orphan mandate' },
  { code: 'C', label: 'Amount mismatch' },
  { code: 'D', label: 'Cancelled/claimed' },
  // Payment -> ledger -> statement reflection tie-out (source 'payment_reflection').
  { code: 'STATEMENT_REFLECTION', label: 'Statement reflection' },
]
const SEVERITY_CLS: Record<string, string> = {
  critical: 'bg-red-600 text-white',
  high: 'bg-orange-500 text-white',
  medium: 'bg-amber-300 text-amber-900',
  low: 'bg-gray-200 text-gray-700',
}
const STATUS_CLS: Record<string, string> = {
  open: 'bg-red-100 text-red-700',
  reviewing: 'bg-blue-100 text-blue-700',
  accepted: 'bg-green-100 text-green-700',
  disputed: 'bg-orange-100 text-orange-700',
  resolved: 'bg-gray-100 text-gray-600',
}
const STATUS_PIE: Record<string, string> = {
  open: '#dc2626', reviewing: '#2563eb', accepted: '#16a34a', disputed: '#ea580c', resolved: '#9ca3af',
}

function money(v: number | null): string {
  if (v === null || v === undefined) return '—'
  return new Intl.NumberFormat('en-BW', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v)
}

function Kpi({ label, value, accent }: { label: string; value: number | string; accent: string }) {
  return (
    <div className="rounded-xl p-4 shadow-sm border" style={{ borderColor: '#e5e7eb' }}>
      <div className="text-3xl font-bold" style={{ color: accent }}>{value}</div>
      <div className="text-xs font-medium text-gray-500 mt-1 uppercase tracking-wide">{label}</div>
    </div>
  )
}

export default function ExceptionsPage() {
  const [runs, setRuns] = useState<ExceptionRun[]>([])
  const [runId, setRunId] = useState<number | undefined>(undefined)
  const [summary, setSummary] = useState<ExceptionSummary | null>(null)
  const [rows, setRows] = useState<ExceptionRow[]>([])
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [loading, setLoading] = useState(true)
  const [tableLoading, setTableLoading] = useState(false)
  const [generating, setGenerating] = useState(false)
  const [filters, setFilters] = useState({ product: 'all', flag: 'all', status: 'all', severity: 'all', search: '' })

  // initial: runs + latest summary
  useEffect(() => {
    (async () => {
      try {
        const rs = await listRuns()
        setRuns(rs)
        const latest = rs[0]?.id
        setRunId(latest)
        const sum = await getSummary(latest)
        setSummary(sum)
      } finally { setLoading(false) }
    })()
  }, [])

  // reload summary when run changes
  useEffect(() => {
    if (runId === undefined) return
    getSummary(runId).then(setSummary).catch(() => {})
  }, [runId])

  // reload table when run / filters / page change
  useEffect(() => {
    if (runId === undefined && runs.length) return
    setTableLoading(true)
    listExceptions({ run_id: runId, ...filters, page, per_page: 25 })
      .then((res) => {
        setRows(res.data ?? [])
        setLastPage(res.last_page ?? 1)
        setTotal(res.total ?? 0)
      })
      .catch(() => setRows([]))
      .finally(() => setTableLoading(false))
  }, [runId, filters, page])

  async function onGenerate() {
    setGenerating(true)
    try {
      await generateExceptions()
      alert('Reconciliation started. It runs in the background — refresh in ~1 minute to see the new run.')
    } finally { setGenerating(false) }
  }

  // ── chart data ──
  const barData = useMemo(() => {
    const map: Record<string, any> = {}
    FLAGS.forEach(f => { map[f.code] = { flag: `${f.code} · ${f.label}`, DOMG: 0, COMG: 0 } })
    ;(summary?.byFlagProduct ?? []).forEach(r => {
      if (map[r.flag_code]) map[r.flag_code][r.product] = Number(r.n)
    })
    return FLAGS.map(f => map[f.code])
  }, [summary])

  const statusData = useMemo(
    () => Object.entries(summary?.byStatus ?? {}).map(([name, value]) => ({ name, value: Number(value) })),
    [summary],
  )
  const trendData = useMemo(
    () => (summary?.trend ?? []).map(t => ({
      date: t.run_date?.slice(5) ?? '', total: Number(t.exception_count), open: Number(t.open_count),
    })),
    [summary],
  )

  const totalEx = summary?.run?.exception_count ?? 0
  const openEx = summary?.run?.open_count ?? 0
  const criticalEx = Number(summary?.bySeverity?.critical ?? 0)
  const domgTotal = (summary?.byFlagProduct ?? []).filter(r => r.product === 'DOMG').reduce((a, r) => a + Number(r.n), 0)
  const comgTotal = (summary?.byFlagProduct ?? []).filter(r => r.product === 'COMG').reduce((a, r) => a + Number(r.n), 0)

  if (loading) {
    return <div className="p-6 flex items-center justify-center min-h-[400px]"><LoadingSpinner size="lg" /></div>
  }

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="rounded-xl px-6 py-5 flex items-center justify-between" style={{ background: NAVY }}>
        <div>
          <h1 className="text-2xl font-bold text-white">Reconciliation Exceptions</h1>
          <p className="text-sm mt-0.5" style={{ color: '#9aa6b2' }}>
            DOMG &amp; COMG policies vs RealPay mandates — generated weekly, reviewed by Finance
          </p>
        </div>
        <div className="flex items-center gap-3">
          <select
            value={runId ?? ''}
            onChange={(e) => { setPage(1); setRunId(Number(e.target.value)) }}
            className="rounded-md text-sm px-3 py-2 border-0"
          >
            {runs.map(r => (
              <option key={r.id} value={r.id}>
                {r.period_label || r.run_date} · {r.exception_count} ({r.status})
              </option>
            ))}
          </select>
          <button
            onClick={onGenerate}
            disabled={generating}
            className="px-4 py-2 rounded-md text-sm font-semibold disabled:opacity-50"
            style={{ background: ORANGE, color: NAVY }}
          >
            {generating ? 'Starting…' : 'Generate now'}
          </button>
        </div>
      </div>

      {/* KPI cards */}
      <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
        <Kpi label="Total exceptions" value={totalEx} accent={NAVY} />
        <Kpi label="Open" value={openEx} accent="#dc2626" />
        <Kpi label="Critical" value={criticalEx} accent="#b91c1c" />
        <Kpi label="DOMG" value={domgTotal} accent={NAVY} />
        <Kpi label="COMG" value={comgTotal} accent={ORANGE} />
      </div>

      {/* Charts */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div className="bg-white rounded-xl shadow-sm border p-4 lg:col-span-2">
          <h2 className="text-sm font-semibold text-gray-700 mb-3">Exceptions by flag &amp; product</h2>
          <ResponsiveContainer width="100%" height={260}>
            <BarChart data={barData} barGap={4}>
              <CartesianGrid strokeDasharray="3 3" stroke="#eef2f6" />
              <XAxis dataKey="flag" tick={{ fontSize: 11 }} />
              <YAxis tick={{ fontSize: 11 }} allowDecimals={false} />
              <Tooltip />
              <Legend />
              <Bar dataKey="DOMG" fill={NAVY} radius={[4, 4, 0, 0]} />
              <Bar dataKey="COMG" fill={ORANGE} radius={[4, 4, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>
        <div className="bg-white rounded-xl shadow-sm border p-4">
          <h2 className="text-sm font-semibold text-gray-700 mb-3">Review status</h2>
          <ResponsiveContainer width="100%" height={260}>
            <PieChart>
              <Pie data={statusData} dataKey="value" nameKey="name" innerRadius={50} outerRadius={85} paddingAngle={2}>
                {statusData.map((d) => <Cell key={d.name} fill={STATUS_PIE[d.name] ?? '#cbd5e1'} />)}
              </Pie>
              <Tooltip />
              <Legend />
            </PieChart>
          </ResponsiveContainer>
        </div>
      </div>

      {trendData.length > 1 && (
        <div className="bg-white rounded-xl shadow-sm border p-4">
          <h2 className="text-sm font-semibold text-gray-700 mb-3">Trend across runs</h2>
          <ResponsiveContainer width="100%" height={200}>
            <LineChart data={trendData}>
              <CartesianGrid strokeDasharray="3 3" stroke="#eef2f6" />
              <XAxis dataKey="date" tick={{ fontSize: 11 }} />
              <YAxis tick={{ fontSize: 11 }} allowDecimals={false} />
              <Tooltip />
              <Legend />
              <Line type="monotone" dataKey="total" name="Total" stroke={NAVY} strokeWidth={2} dot={{ r: 3 }} />
              <Line type="monotone" dataKey="open" name="Open" stroke={ORANGE} strokeWidth={2} dot={{ r: 3 }} />
            </LineChart>
          </ResponsiveContainer>
        </div>
      )}

      {/* Filters */}
      <div className="bg-white rounded-xl shadow-sm border p-4 flex flex-wrap gap-3 items-end">
        {[
          { key: 'product', label: 'Product', opts: ['all', 'DOMG', 'COMG'] },
          { key: 'flag', label: 'Flag', opts: ['all', 'A', 'B', 'C', 'D', 'STATEMENT_REFLECTION'] },
          { key: 'status', label: 'Status', opts: ['all', 'open', 'reviewing', 'accepted', 'disputed', 'resolved'] },
          { key: 'severity', label: 'Severity', opts: ['all', 'critical', 'high', 'medium', 'low'] },
        ].map(f => (
          <div key={f.key}>
            <label className="block text-xs text-gray-500 mb-1">{f.label}</label>
            <select
              value={(filters as any)[f.key]}
              onChange={(e) => { setPage(1); setFilters(s => ({ ...s, [f.key]: e.target.value })) }}
              className="rounded-md border-gray-300 text-sm px-2 py-1.5"
            >
              {f.opts.map(o => <option key={o} value={o}>{o}</option>)}
            </select>
          </div>
        ))}
        <div className="flex-1 min-w-[180px]">
          <label className="block text-xs text-gray-500 mb-1">Search policy / contract</label>
          <input
            value={filters.search}
            onChange={(e) => setFilters(s => ({ ...s, search: e.target.value }))}
            onKeyDown={(e) => { if (e.key === 'Enter') setPage(1) }}
            placeholder="POL number…"
            className="w-full rounded-md border-gray-300 text-sm px-2 py-1.5"
          />
        </div>
      </div>

      {/* Table */}
      <div className="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div className="px-4 py-3 border-b flex items-center justify-between">
          <span className="text-sm font-semibold text-gray-700">{total} exception{total === 1 ? '' : 's'}</span>
        </div>
        {tableLoading ? (
          <div className="py-10 flex justify-center"><LoadingSpinner size="sm" /></div>
        ) : rows.length === 0 ? (
          <div className="py-10 text-center text-sm text-gray-400">No exceptions match these filters.</div>
        ) : (
          <table className="min-w-full text-sm">
            <thead className="bg-gray-50 text-gray-500 text-xs uppercase">
              <tr>
                <th className="text-left px-4 py-2">Product</th>
                <th className="text-left px-4 py-2">Flag</th>
                <th className="text-left px-4 py-2">Policy</th>
                <th className="text-right px-4 py-2">Graphite</th>
                <th className="text-right px-4 py-2">RealPay</th>
                <th className="text-right px-4 py-2">Variance</th>
                <th className="text-left px-4 py-2">Severity</th>
                <th className="text-left px-4 py-2">Status</th>
                <th className="text-center px-4 py-2">💬</th>
                <th className="px-4 py-2"></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {rows.map(r => (
                <tr key={r.id} className="hover:bg-gray-50">
                  <td className="px-4 py-2">
                    <span className={`px-2 py-0.5 rounded text-xs font-semibold ${r.product === 'DOMG' ? 'bg-slate-200 text-slate-800' : 'bg-orange-100 text-orange-800'}`}>{r.product}</span>
                  </td>
                  <td className="px-4 py-2"><span title={r.flag_label} className="font-mono font-semibold">{r.flag_code}</span> <span className="text-gray-500 text-xs">{r.flag_label}</span></td>
                  <td className="px-4 py-2 font-mono text-xs">{r.policy_number ?? '—'}</td>
                  <td className="px-4 py-2 text-right">{money(r.graphite_value)}</td>
                  <td className="px-4 py-2 text-right">{money(r.realpay_value)}</td>
                  <td className={`px-4 py-2 text-right font-medium ${r.variance && Math.abs(r.variance) > 0 ? 'text-red-600' : ''}`}>{money(r.variance)}</td>
                  <td className="px-4 py-2"><span className={`px-2 py-0.5 rounded text-xs font-semibold ${SEVERITY_CLS[r.severity]}`}>{r.severity}</span></td>
                  <td className="px-4 py-2"><span className={`px-2 py-0.5 rounded text-xs font-semibold ${STATUS_CLS[r.status]}`}>{r.status}</span></td>
                  <td className="px-4 py-2 text-center text-gray-500">{r.comment_count || ''}</td>
                  <td className="px-4 py-2 text-right">
                    <Link to={`/finance/exceptions/${r.id}`} className="text-blue-600 hover:underline text-xs font-medium">Review →</Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
        {lastPage > 1 && (
          <div className="px-4 py-3 border-t flex items-center justify-between text-sm">
            <button disabled={page <= 1} onClick={() => setPage(p => p - 1)} className="px-3 py-1 rounded border disabled:opacity-40">Prev</button>
            <span className="text-gray-500">Page {page} of {lastPage}</span>
            <button disabled={page >= lastPage} onClick={() => setPage(p => p + 1)} className="px-3 py-1 rounded border disabled:opacity-40">Next</button>
          </div>
        )}
      </div>
    </div>
  )
}
