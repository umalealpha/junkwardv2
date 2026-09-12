import { useState, useEffect } from 'react'
import { useParams, Link } from 'react-router-dom'
import apiClient from '../api/client'

interface AgentSummary {
  agent: {
    id: number
    name: string
    email: string
    phone: string | null
    agency_name: string | null
    status: string
  }
  earnings: {
    all_time: number
    this_month: number
    this_quarter: number
    last_month: number
  }
  status_breakdown: {
    pending: number
    approved: number
    paid: number
    held: number
    clawed_back: number
  }
  performance: {
    active_policies: number
    total_policies: number
    cancellation_rate: number
    kyc_compliance_pct: number
  }
  targets: {
    id: number
    name: string
    target_value: number
    current_value: number
    target_type: string
    period_type: string
    bonus_value: number
    bonus_type: string
    achieved: boolean
  }[]
}

function fmtPula(n: number): string {
  const prefix = n < 0 ? '-' : ''
  return prefix + 'P ' + Math.abs(n).toLocaleString('en-BW', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function pct(n: number): string {
  return n.toFixed(1) + '%'
}

export default function AgentScorecard() {
  const { agentId } = useParams<{ agentId: string }>()
  const [data, setData] = useState<AgentSummary | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    if (!agentId) return
    setLoading(true)
    apiClient
      .get(`/commission/agent/${agentId}/summary`)
      .then((r) => setData(r.data))
      .catch(() => setError('Failed to load agent scorecard.'))
      .finally(() => setLoading(false))
  }, [agentId])

  if (loading) {
    return (
      <div className="p-6 space-y-6 animate-pulse">
        <div className="h-8 w-64 bg-gray-200 rounded" />
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="h-24 bg-gray-200 rounded-lg" />
          ))}
        </div>
        <div className="grid grid-cols-2 gap-4">
          <div className="h-48 bg-gray-200 rounded-lg" />
          <div className="h-48 bg-gray-200 rounded-lg" />
        </div>
      </div>
    )
  }

  if (error || !data) {
    return (
      <div className="p-6">
        <Link to="/" className="text-sm text-ace-primary hover:underline mb-4 inline-block">&larr; Back to Dashboard</Link>
        <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md text-sm mt-2">
          {error || 'Failed to load agent scorecard.'}
        </div>
      </div>
    )
  }

  const { agent, earnings, status_breakdown, performance, targets } = data

  const earningsCards = [
    { label: 'All-Time Earnings', value: fmtPula(earnings.all_time), bg: 'bg-purple-50', border: 'border-purple-200', text: 'text-purple-700' },
    { label: 'This Month', value: fmtPula(earnings.this_month), bg: 'bg-green-50', border: 'border-green-200', text: 'text-green-700' },
    { label: 'This Quarter', value: fmtPula(earnings.this_quarter), bg: 'bg-blue-50', border: 'border-blue-200', text: 'text-blue-700' },
    { label: 'Last Month', value: fmtPula(earnings.last_month), bg: 'bg-gray-50', border: 'border-gray-200', text: 'text-gray-700' },
  ]

  const statusCards = [
    { label: 'Pending', value: fmtPula(status_breakdown.pending), color: 'text-yellow-600' },
    { label: 'Approved', value: fmtPula(status_breakdown.approved), color: 'text-blue-600' },
    { label: 'Paid', value: fmtPula(status_breakdown.paid), color: 'text-green-600' },
    { label: 'Held', value: fmtPula(status_breakdown.held), color: 'text-orange-600' },
    { label: 'Clawed Back', value: fmtPula(status_breakdown.clawed_back), color: 'text-red-600' },
  ]

  return (
    <div className="p-6 space-y-6">
      {/* Breadcrumb */}
      <Link to="/" className="text-sm text-ace-primary hover:underline">&larr; Back to Dashboard</Link>

      {/* Agent Header */}
      <div className="flex items-center gap-4">
        <div className="w-14 h-14 rounded-full bg-ace-primary/10 flex items-center justify-center">
          <span className="text-xl font-bold text-ace-primary">
            {agent.name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase()}
          </span>
        </div>
        <div>
          <h1 className="text-2xl font-bold text-gray-800">{agent.name}</h1>
          <div className="flex items-center gap-3 text-sm text-gray-500 mt-0.5">
            {agent.email && <span>{agent.email}</span>}
            {agent.phone && <span>{agent.phone}</span>}
            {agent.agency_name && (
              <span className="px-2 py-0.5 bg-gray-100 rounded-full text-xs font-medium text-gray-600">
                {agent.agency_name}
              </span>
            )}
            <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${
              agent.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'
            }`}>
              {agent.status === 'active' ? 'Active' : agent.status}
            </span>
          </div>
        </div>
      </div>

      {/* Earnings Cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {earningsCards.map((card) => (
          <div key={card.label} className={`rounded-lg border p-4 ${card.bg} ${card.border}`}>
            <p className="text-sm text-gray-500">{card.label}</p>
            <p className={`text-2xl font-bold mt-1 ${card.text}`}>{card.value}</p>
          </div>
        ))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Status Breakdown */}
        <div className="bg-white rounded-lg shadow-sm border p-5">
          <h2 className="text-sm font-semibold text-gray-700 mb-4">Commission Status Breakdown</h2>
          <div className="space-y-3">
            {statusCards.map((card) => (
              <div key={card.label} className="flex items-center justify-between">
                <span className="text-sm text-gray-600">{card.label}</span>
                <span className={`text-sm font-mono font-medium ${card.color}`}>{card.value}</span>
              </div>
            ))}
          </div>
        </div>

        {/* Performance Metrics */}
        <div className="bg-white rounded-lg shadow-sm border p-5">
          <h2 className="text-sm font-semibold text-gray-700 mb-4">Performance Metrics</h2>
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <span className="text-sm text-gray-600">Active Policies</span>
              <span className="text-sm font-semibold text-gray-800">{performance.active_policies.toLocaleString()}</span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-sm text-gray-600">Total Policies (All Time)</span>
              <span className="text-sm font-semibold text-gray-800">{performance.total_policies.toLocaleString()}</span>
            </div>
            <div>
              <div className="flex items-center justify-between mb-1">
                <span className="text-sm text-gray-600">Cancellation Rate</span>
                <span className={`text-sm font-semibold ${performance.cancellation_rate > 20 ? 'text-red-600' : performance.cancellation_rate > 10 ? 'text-yellow-600' : 'text-green-600'}`}>
                  {pct(performance.cancellation_rate)}
                </span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2">
                <div
                  className={`h-2 rounded-full transition-all ${
                    performance.cancellation_rate > 20 ? 'bg-red-500' : performance.cancellation_rate > 10 ? 'bg-yellow-500' : 'bg-green-500'
                  }`}
                  style={{ width: `${Math.min(performance.cancellation_rate, 100)}%` }}
                />
              </div>
            </div>
            <div>
              <div className="flex items-center justify-between mb-1">
                <span className="text-sm text-gray-600">KYC Compliance</span>
                <span className={`text-sm font-semibold ${performance.kyc_compliance_pct >= 90 ? 'text-green-600' : performance.kyc_compliance_pct >= 70 ? 'text-yellow-600' : 'text-red-600'}`}>
                  {pct(performance.kyc_compliance_pct)}
                </span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2">
                <div
                  className={`h-2 rounded-full transition-all ${
                    performance.kyc_compliance_pct >= 90 ? 'bg-green-500' : performance.kyc_compliance_pct >= 70 ? 'bg-yellow-500' : 'bg-red-500'
                  }`}
                  style={{ width: `${Math.min(performance.kyc_compliance_pct, 100)}%` }}
                />
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Target Achievements */}
      <div className="bg-white rounded-lg shadow-sm border overflow-hidden">
        <div className="px-5 py-4 border-b">
          <h2 className="text-sm font-semibold text-gray-700">Target Achievements</h2>
          <p className="text-xs text-gray-400 mt-0.5">Active commission targets and progress</p>
        </div>
        {targets.length === 0 ? (
          <div className="px-5 py-10 text-center">
            <p className="text-sm text-gray-400">No active targets assigned to this agent.</p>
          </div>
        ) : (
          <div className="divide-y divide-gray-100">
            {targets.map((target) => {
              const progress = target.target_value > 0
                ? Math.min((target.current_value / target.target_value) * 100, 100)
                : 0
              return (
                <div key={target.id} className="px-5 py-4">
                  <div className="flex items-center justify-between mb-2">
                    <div>
                      <span className="text-sm font-medium text-gray-800">{target.name}</span>
                      <span className="ml-2 text-xs text-gray-400 capitalize">{target.period_type}</span>
                    </div>
                    <div className="flex items-center gap-3">
                      <span className="text-xs text-gray-500">
                        Bonus: {target.bonus_type === 'percentage' ? `${target.bonus_value}%` : fmtPula(target.bonus_value)}
                      </span>
                      {target.achieved ? (
                        <span className="px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-xs font-medium">
                          Achieved
                        </span>
                      ) : (
                        <span className="px-2 py-0.5 bg-gray-100 text-gray-500 rounded-full text-xs font-medium">
                          In Progress
                        </span>
                      )}
                    </div>
                  </div>
                  <div className="flex items-center gap-3">
                    <div className="flex-1 bg-gray-200 rounded-full h-2.5">
                      <div
                        className={`h-2.5 rounded-full transition-all ${target.achieved ? 'bg-green-500' : 'bg-ace-primary'}`}
                        style={{ width: `${progress}%` }}
                      />
                    </div>
                    <span className="text-xs font-mono text-gray-600 w-28 text-right">
                      {target.target_type === 'premium_amount'
                        ? `${fmtPula(target.current_value)} / ${fmtPula(target.target_value)}`
                        : `${target.current_value.toLocaleString()} / ${target.target_value.toLocaleString()}`
                      }
                    </span>
                  </div>
                </div>
              )
            })}
          </div>
        )}
      </div>
    </div>
  )
}
