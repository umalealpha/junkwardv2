import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import apiClient from '../api/client'

interface DashboardStats {
  pending: number
  approved: number
  paidThisMonth: number
  clawedBack: number
  fraudAlerts: number
  topAgents: { id: number; name: string; earned: number; policies: number }[]
}

function fmtPula(n: number): string {
  return 'P ' + n.toLocaleString('en-BW', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

export default function Dashboard() {
  const [data, setData] = useState<DashboardStats | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    setLoading(true)
    apiClient
      .get('/commission/dashboard')
      .then((r) => setData(r.data))
      .catch(() => setError('Failed to load commission dashboard.'))
      .finally(() => setLoading(false))
  }, [])

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
    return <div className="p-6 text-red-600">{error || 'Failed to load commission dashboard.'}</div>
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
                <th className="px-4 py-3 text-center">Profile</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {data.topAgents.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-4 py-14 text-center">
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
                    <td className="px-4 py-3 text-center">
                      <Link
                        to={`/agent/${agent.id}`}
                        className="px-2.5 py-1 text-xs bg-ace-primary/10 text-ace-primary rounded hover:bg-ace-primary/20 font-medium transition"
                      >
                        View
                      </Link>
                    </td>
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
