import { useState, useEffect, useCallback } from 'react'
import apiClient from '../../api/client'
import { fmtPula } from '../../utils/format'
import { reportErrorToTeam } from '../../utils/reportError'

interface DashboardStats {
  pending: number
  approved: number
  paidThisMonth: number
  clawedBack: number
  fraudAlerts: number
  topAgents: { id: number; name: string; earned: number; policies: number }[]
}

interface RunResult {
  policy_id: number; policy_number: string; product: string; premium: number
  agent_id: number; matched_rule: string | null; commission: number
  status: string; fail_reasons: any[]
}

export default function CommissionDashboardPage() {
  const [data, setData] = useState<DashboardStats | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  // Run Calculation state
  const [runDays, setRunDays] = useState('30')
  const [runLimit, setRunLimit] = useState('50')
  const [runDryRun, setRunDryRun] = useState(true)
  const [running, setRunning] = useState(false)
  const [runResults, setRunResults] = useState<{ summary: any; results: RunResult[] } | null>(null)
  const [runError, setRunError] = useState('')

  const loadDashboard = useCallback(() => {
    setLoading(true)
    setError('')
    apiClient
      .get('/commission/dashboard')
      .then((r) => {
        const d = r.data.data ?? r.data
        setData({
          pending: d.totalPending ?? d.pending ?? 0,
          approved: d.totalApproved ?? d.approved ?? 0,
          paidThisMonth: d.totalPaidThisMonth ?? d.paidThisMonth ?? 0,
          clawedBack: d.totalClawbackThisMonth ?? d.clawedBack ?? 0,
          fraudAlerts: d.openFraudAlerts ?? d.fraudAlerts ?? 0,
          topAgents: (d.topAgents ?? []).map((a: any) => ({
            id: a.agentId ?? a.id,
            name: a.agentName ?? a.name ?? `Agent #${a.agentId ?? a.id}`,
            earned: a.total ?? a.earned ?? 0,
            policies: a.policies ?? 0,
          })),
        })
      })
      .catch((err: any) => {
        // UAT 2026-05-28: surface the actual error message + report to
        // developers@ instead of a bald red wall. Same pattern as the
        // AuditTrail / UserList / RealPay fixes from 26-May.
        const message = err?.response?.data?.message ?? err?.message ?? 'Failed to load commission dashboard.'
        setError(message)
        reportErrorToTeam({
          error: message,
          stack: err?.stack,
          context: 'CommissionDashboardPage:loadDashboard',
        })
      })
      .finally(() => setLoading(false))
  }, [])

  useEffect(() => { loadDashboard() }, [loadDashboard])

  if (loading) {
    return (
      <div className="p-6 space-y-6">
        <div className="grid grid-cols-2 md:grid-cols-5 gap-4 animate-pulse">
          {[1, 2, 3, 4, 5].map((i) => (
            <div key={i} className="h-24 bg-gray-200 rounded-lg" />
          ))}
        </div>
        <div className="h-72 bg-gray-200 rounded-lg animate-pulse" />
      </div>
    )
  }

  if (error || !data) {
    return (
      <div className="p-6">
        <div className="bg-red-50 border border-red-200 rounded-lg p-6 space-y-3">
          <p className="text-sm font-medium text-red-700">Failed to load commission dashboard.</p>
          {error && <p className="text-xs text-red-600">{error}</p>}
          <button onClick={loadDashboard} className="px-3 py-1.5 bg-red-600 text-white rounded text-sm hover:bg-red-700">Try again</button>
        </div>
      </div>
    )
  }

  const statCards = [
    { label: 'Pending', value: fmtPula(data.pending), bg: 'bg-yellow-50', border: 'border-yellow-200', text: 'text-yellow-700' },
    { label: 'Approved', value: fmtPula(data.approved), bg: 'bg-blue-50', border: 'border-blue-200', text: 'text-blue-700' },
    { label: 'Paid This Month', value: fmtPula(data.paidThisMonth), bg: 'bg-green-50', border: 'border-green-200', text: 'text-green-700' },
    { label: 'Clawed Back', value: fmtPula(data.clawedBack), bg: 'bg-red-50', border: 'border-red-200', text: 'text-red-700' },
    { label: 'Fraud Alerts', value: String(data.fraudAlerts), bg: 'bg-orange-50', border: 'border-orange-200', text: 'text-orange-700' },
  ]

  return (
    <div className="p-6 space-y-6">
      <h1 className="text-2xl font-bold text-gray-800">Commission Dashboard</h1>

      {/* Stat Cards */}
      <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
        {statCards.map((card) => (
          <div key={card.label} className={`rounded-lg border p-4 ${card.bg} ${card.border}`}>
            <p className="text-sm text-gray-500">{card.label}</p>
            <p className={`text-2xl font-bold mt-1 ${card.text}`}>{card.value}</p>
          </div>
        ))}
      </div>

      {/* ── Run Calculation ─────────────────────────── */}
      <div className="bg-white rounded-lg shadow-sm border overflow-hidden">
        <div className="px-5 py-4 border-b flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 className="text-sm font-semibold text-gray-700">Run Commission Calculation</h2>
            <p className="text-xs text-gray-400 mt-0.5">Test rules against policies or apply and create ledger entries</p>
          </div>
          <div className="flex flex-wrap items-center gap-3">
            <div className="flex items-center gap-1">
              <label className="text-xs text-gray-500">Days:</label>
              <input type="number" value={runDays} onChange={e => setRunDays(e.target.value)}
                className="w-16 px-2 py-1 border rounded text-sm" min="1" max="365" />
            </div>
            <div className="flex items-center gap-1">
              <label className="text-xs text-gray-500">Limit:</label>
              <input type="number" value={runLimit} onChange={e => setRunLimit(e.target.value)}
                className="w-16 px-2 py-1 border rounded text-sm" min="1" max="500" />
            </div>
            <label className="flex items-center gap-1.5 text-sm">
              <input type="checkbox" checked={runDryRun} onChange={e => setRunDryRun(e.target.checked)} className="rounded" />
              <span className="text-gray-600">Dry Run</span>
            </label>
            <button disabled={running} onClick={async () => {
              setRunning(true); setRunError(''); setRunResults(null)
              try {
                const r = await apiClient.post('/commission/run', { days: Number(runDays), limit: Number(runLimit), dry_run: runDryRun })
                setRunResults(r.data)
                if (!runDryRun) {
                  // Refresh dashboard stats
                  apiClient.get('/commission/dashboard').then(r2 => {
                    const d = r2.data.data ?? r2.data
                    setData({ pending: d.totalPending ?? d.pending ?? 0, approved: d.totalApproved ?? d.approved ?? 0, paidThisMonth: d.totalPaidThisMonth ?? d.paidThisMonth ?? 0, clawedBack: d.totalClawbackThisMonth ?? d.clawedBack ?? 0, fraudAlerts: d.openFraudAlerts ?? d.fraudAlerts ?? 0, topAgents: (d.topAgents ?? []).map((a: any) => ({ id: a.agentId ?? a.id, name: a.agentName ?? a.name ?? `Agent #${a.agentId}`, earned: a.total ?? a.earned ?? 0, policies: a.policies ?? 0 })) })
                  }).catch(() => {})
                }
              } catch (e: any) { setRunError(e?.response?.data?.message || 'Calculation failed') }
              setRunning(false)
            }} className="px-4 py-1.5 text-sm font-medium rounded bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50">
              {running ? 'Running...' : runDryRun ? 'Test Rules' : 'Run & Apply'}
            </button>
          </div>
        </div>

        {runError && <div className="px-5 py-2 bg-red-50 text-red-700 text-sm border-b">{runError}</div>}

        {runResults && (
          <div className="px-5 py-4 space-y-3">
            {/* Summary */}
            <div className="grid grid-cols-2 md:grid-cols-5 gap-3 text-center">
              {[
                { label: 'Policies Scanned', value: runResults.summary.total, cls: 'text-gray-700' },
                { label: 'Rules Matched', value: runResults.summary.matched, cls: 'text-green-700' },
                { label: 'No Match', value: runResults.summary.no_match, cls: 'text-red-700' },
                { label: 'Total Commission', value: fmtPula(runResults.summary.total_commission), cls: 'text-blue-700' },
                { label: 'Mode', value: runResults.summary.dry_run ? 'DRY RUN' : 'APPLIED', cls: runResults.summary.dry_run ? 'text-amber-600' : 'text-green-600' },
              ].map(s => (
                <div key={s.label} className="bg-gray-50 rounded-lg p-2">
                  <div className="text-[10px] text-gray-400 uppercase">{s.label}</div>
                  <div className={`text-lg font-bold ${s.cls}`}>{s.value}</div>
                </div>
              ))}
            </div>

            {/* Results table */}
            <div className="overflow-x-auto max-h-[400px] overflow-y-auto border rounded">
              <table className="w-full text-xs">
                <thead className="bg-gray-50 text-gray-600 uppercase sticky top-0">
                  <tr>
                    <th className="px-2 py-2 text-left">Policy</th>
                    <th className="px-2 py-2 text-left">Product</th>
                    <th className="px-2 py-2 text-right">Premium</th>
                    <th className="px-2 py-2 text-left">Matched Rule</th>
                    <th className="px-2 py-2 text-right">Commission</th>
                    <th className="px-2 py-2 text-center">Status</th>
                    <th className="px-2 py-2 text-left">Fail Reasons</th>
                  </tr>
                </thead>
                <tbody className="divide-y">
                  {runResults.results.map((r: RunResult) => (
                    <tr key={r.policy_id} className={r.matched_rule ? 'bg-green-50/40' : 'bg-red-50/40'}>
                      <td className="px-2 py-1.5 font-mono">{r.policy_number || r.policy_id}</td>
                      <td className="px-2 py-1.5">{r.product}</td>
                      <td className="px-2 py-1.5 text-right">{fmtPula(r.premium)}</td>
                      <td className="px-2 py-1.5 font-medium text-green-700">{r.matched_rule || '—'}</td>
                      <td className="px-2 py-1.5 text-right font-mono font-medium">{r.commission > 0 ? fmtPula(r.commission) : '—'}</td>
                      <td className="px-2 py-1.5 text-center">
                        <span className={`px-1.5 py-0.5 rounded-full text-[10px] font-medium ${r.status === 'created' ? 'bg-green-100 text-green-700' : r.status === 'would_create' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700'}`}>
                          {r.status === 'created' ? 'Created' : r.status === 'would_create' ? 'Would Create' : 'No Match'}
                        </span>
                      </td>
                      <td className="px-2 py-1.5 text-gray-500 max-w-[200px] truncate" title={r.fail_reasons?.map((f: any) => `${f.rule}: ${f.reasons?.join(', ')}`).join(' | ')}>
                        {r.fail_reasons?.length ? r.fail_reasons.map((f: any) => f.reasons?.join(', ')).join('; ') : ''}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </div>

      {/* Top 5 Agents */}
      <div className="bg-white rounded-lg shadow-sm border overflow-hidden">
        <div className="px-5 py-4 border-b">
          <h2 className="text-sm font-semibold text-gray-700">Top 5 Agents</h2>
          <p className="text-xs text-gray-400 mt-0.5">Highest commission earners this period</p>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-600 uppercase text-xs tracking-wider">
              <tr>
                <th className="px-4 py-3 text-left">#</th>
                <th className="px-4 py-3 text-left">Agent Name</th>
                <th className="px-4 py-3 text-right">Commission Earned</th>
                <th className="px-4 py-3 text-right">Policies</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {data.topAgents.length === 0 ? (
                <tr>
                  <td colSpan={4} className="px-4 py-14 text-center">
                    <div className="flex flex-col items-center gap-3">
                      <div className="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center">
                        <svg className="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                      </div>
                      <p className="text-sm text-gray-400">No agent data available.</p>
                    </div>
                  </td>
                </tr>
              ) : (
                data.topAgents.map((agent, idx) => (
                  <tr key={agent.id} className="hover:bg-gray-50 transition">
                    <td className="px-4 py-3">
                      {idx < 3 ? (
                        <span
                          className={`inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold ${
                            idx === 0
                              ? 'bg-yellow-400 text-yellow-900'
                              : idx === 1
                                ? 'bg-gray-300 text-gray-700'
                                : 'bg-amber-600 text-white'
                          }`}
                        >
                          {idx + 1}
                        </span>
                      ) : (
                        <span className="text-xs font-semibold text-gray-400">{idx + 1}</span>
                      )}
                    </td>
                    <td className="px-4 py-3 font-medium text-gray-800">{agent.name}</td>
                    <td className="px-4 py-3 text-right font-mono text-green-600 font-medium">{fmtPula(agent.earned)}</td>
                    <td className="px-4 py-3 text-right text-gray-600">{agent.policies.toLocaleString()}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  )
}
