import { useEffect, useState } from 'react'
import apiClient from '../api/client'

/**
 * Underwriting Bottleneck Dashboard (CFO 2026-08-27).
 *
 * Where new business stalls on its way to a live policy. Keyed off the live
 * workflow (policy_actions.status): the real leak is quotes that never convert,
 * not manager approval (only a handful pending). Cloned from the Claims
 * Dashboard shell. Read-only aggregates from /api/v1/uw-bottleneck/dashboard —
 * no customer PII.
 */

interface DashboardData {
  quoteCount: number
  quotePremium: number
  inApprovalCount: number
  inApprovalPremium: number
  inApprovalOldest: string | null
  issuedCount: number
  conversionPct: number
  funnel: Array<{ status: string; label: string; count: number }>
  quoteByAge: Array<{ bucket: string; count: number }>
  byProduct: Array<{ product: string; count: number; premium: number }>
  monthlyTrend: Array<{ month: string; count: number }>
}

const statusColors: Record<string, string> = {
  QUOTE: 'bg-orange-500',
  IN_APPROVAL: 'bg-yellow-500',
  APPROVED: 'bg-blue-500',
  ISSUED: 'bg-green-500',
  LAPSED: 'bg-gray-400',
}

function formatCurrency(amount: number): string {
  return 'P ' + amount.toLocaleString('en-BW', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

function daysSince(date: string | null): number {
  if (!date) return 0
  const d = new Date(date.replace(' ', 'T'))
  if (isNaN(d.getTime())) return 0
  return Math.max(0, Math.floor((Date.now() - d.getTime()) / 86400000))
}

export default function UwBottleneckDashboard() {
  const [data, setData] = useState<DashboardData | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(false)
  const [notEnabled, setNotEnabled] = useState(false)

  useEffect(() => {
    apiClient.get('/uw-bottleneck/dashboard')
      .then(r => { setData(r.data.data); setError(false) })
      // 404 = the `uw_bottleneck` toggle is off (Admin > Integrations); show a
      // clean "not enabled" state rather than a generic load error.
      .catch((e: any) => { if (e?.response?.status === 404) setNotEnabled(true); else setError(true) })
      .finally(() => setLoading(false))
  }, [])

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin w-8 h-8 border-4 border-claims-primary border-t-transparent rounded-full" />
      </div>
    )
  }

  if (notEnabled) {
    return (
      <div className="p-6">
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6 text-sm text-gray-600">
          The Underwriting Bottleneck dashboard is not enabled yet. It will be switched on once approved.
        </div>
      </div>
    )
  }

  if (error || !data) {
    return (
      <div className="p-6">
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6 text-sm text-gray-600">
          Could not load the underwriting bottleneck right now. Please refresh.
        </div>
      </div>
    )
  }

  const maxFunnel = Math.max(...data.funnel.map(f => f.count), 1)
  const maxAge = Math.max(...data.quoteByAge.map(a => a.count), 1)
  const maxTrend = Math.max(...data.monthlyTrend.map(m => m.count), 1)

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-800">Underwriting Bottleneck</h1>
        <p className="text-sm text-gray-500 mt-1">Where new business stalls before it becomes a live policy.</p>
      </div>

      {/* Stat Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
          <p className="text-sm text-gray-500">Quotes not converted</p>
          <p className="text-3xl font-bold text-gray-800 mt-1">{data.quoteCount.toLocaleString()}</p>
          <p className="text-xs text-gray-400 mt-1">{formatCurrency(data.quotePremium)} annual premium</p>
        </div>

        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
          <p className="text-sm text-gray-500">Quote → issue conversion</p>
          <p className="text-3xl font-bold text-gray-800 mt-1">{data.conversionPct}%</p>
          <p className="text-xs text-gray-400 mt-1">{data.issuedCount.toLocaleString()} issued</p>
        </div>

        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
          <p className="text-sm text-gray-500">Waiting on underwriter</p>
          <p className="text-3xl font-bold text-gray-800 mt-1">{data.inApprovalCount.toLocaleString()}</p>
          <p className="text-xs text-gray-400 mt-1">{formatCurrency(data.inApprovalPremium)} · oldest {daysSince(data.inApprovalOldest)}d</p>
        </div>

        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
          <p className="text-sm text-gray-500">Issued policies</p>
          <p className="text-3xl font-bold text-gray-800 mt-1">{data.issuedCount.toLocaleString()}</p>
          <p className="text-xs text-gray-400 mt-1">live new-business actions</p>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Funnel by status */}
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
          <h3 className="text-sm font-semibold text-gray-700 mb-4">New-business funnel</h3>
          <div className="space-y-3">
            {data.funnel.map((f) => (
              <div key={f.status} className="flex items-center gap-3">
                <span className="text-sm text-gray-600 w-44 flex-shrink-0">{f.label}</span>
                <div className="flex-1 bg-gray-100 rounded-full h-6 overflow-hidden">
                  <div
                    className={`h-full rounded-full ${statusColors[f.status] || 'bg-gray-400'} flex items-center justify-end pr-2 transition-all duration-500`}
                    style={{ width: `${Math.max((f.count / maxFunnel) * 100, 8)}%` }}
                  >
                    <span className="text-xs font-bold text-white">{f.count.toLocaleString()}</span>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Age of the un-converted quotes */}
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
          <h3 className="text-sm font-semibold text-gray-700 mb-4">How long quotes have sat un-converted</h3>
          <div className="space-y-3">
            {data.quoteByAge.map((a) => (
              <div key={a.bucket} className="flex items-center gap-3">
                <span className="text-sm text-gray-600 w-24 flex-shrink-0">{a.bucket} days</span>
                <div className="flex-1 bg-gray-100 rounded-full h-6 overflow-hidden">
                  <div
                    className={`h-full rounded-full ${a.bucket === '180+' ? 'bg-red-500' : a.bucket === '91-180' ? 'bg-orange-500' : 'bg-claims-primary/80'} flex items-center justify-end pr-2 transition-all duration-500`}
                    style={{ width: `${Math.max((a.count / maxAge) * 100, a.count > 0 ? 8 : 0)}%` }}
                  >
                    {a.count > 0 && <span className="text-xs font-bold text-white">{a.count.toLocaleString()}</span>}
                  </div>
                </div>
              </div>
            ))}
          </div>
          <p className="text-[11px] text-gray-400 mt-3">Older quotes are the recoverable lost business; recent ones are normal pipeline.</p>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* By product line */}
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
          <h3 className="text-sm font-semibold text-gray-700 mb-4">Un-converted quotes by product</h3>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-xs text-gray-500 border-b border-gray-100">
                  <th className="pb-2 font-medium">Product</th>
                  <th className="pb-2 font-medium text-right">Quotes</th>
                  <th className="pb-2 font-medium text-right">Premium at stake</th>
                </tr>
              </thead>
              <tbody>
                {data.byProduct.map((p) => (
                  <tr key={p.product} className="border-b border-gray-50 last:border-0">
                    <td className="py-2 text-gray-700">{p.product}</td>
                    <td className="py-2 text-right font-semibold text-gray-800">{p.count.toLocaleString()}</td>
                    <td className="py-2 text-right text-gray-600">{formatCurrency(p.premium)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* Quotes raised per month */}
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
          <h3 className="text-sm font-semibold text-gray-700 mb-4">Un-converted quotes by month raised (last 24m)</h3>
          <div className="flex items-end gap-1.5 h-48 overflow-x-auto">
            {data.monthlyTrend.map((m) => (
              <div key={m.month} className="flex-1 min-w-[22px] flex flex-col items-center gap-1">
                <span className="text-[10px] font-semibold text-gray-700">{m.count}</span>
                <div
                  className="w-full bg-claims-primary/80 rounded-t-md transition-all duration-500 hover:bg-claims-primary"
                  style={{ height: `${(m.count / maxTrend) * 100}%`, minHeight: '6px' }}
                />
                <span className="text-[9px] text-gray-500 mt-1 whitespace-nowrap">{m.month.slice(2)}</span>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  )
}
