import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import apiClient from '../api/client'

interface DashboardData {
  open_claims: number
  total_reserves_outstanding: number
  avg_settlement_days: number
  claims_this_month: number
  claims_by_status: Record<string, number>
  claims_by_type: Record<string, number>
  top_claims: Array<{
    id: number
    claim_number: string
    policy_number: string
    customer_name: string
    reserve_amount: number
    status: string
  }>
  monthly_trend: Array<{ month: string; count: number }>
}

const statusColors: Record<string, string> = {
  'New': 'bg-blue-500',
  'Pending Assessment': 'bg-yellow-500',
  'Under Review': 'bg-orange-500',
  'Approved': 'bg-green-500',
  'Rejected': 'bg-red-500',
  'Closed': 'bg-gray-500',
  'Settled': 'bg-emerald-500',
}

function formatCurrency(amount: number): string {
  return 'P ' + amount.toLocaleString('en-BW', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

export default function Dashboard() {
  const [data, setData] = useState<DashboardData | null>(null)
  const [loading, setLoading] = useState(true)
  const navigate = useNavigate()

  useEffect(() => {
    apiClient.get('/claims-v2/dashboard')
      .then(r => setData(r.data))
      .catch(() => {
        // Use mock data for development
        setData({
          open_claims: 47,
          total_reserves_outstanding: 2450000,
          avg_settlement_days: 23,
          claims_this_month: 12,
          claims_by_status: {
            'New': 8,
            'Pending Assessment': 15,
            'Under Review': 10,
            'Approved': 7,
            'Rejected': 3,
            'Closed': 4,
          },
          claims_by_type: {
            'Motor Accident': 18,
            'Theft': 8,
            'Fire': 5,
            'Natural Disaster': 3,
            'Liability': 7,
            'Health': 6,
          },
          top_claims: [
            { id: 1, claim_number: 'CLM-2026-0451', policy_number: 'POL-10234', customer_name: 'Kgomotso Holdings', reserve_amount: 450000, status: 'Under Review' },
            { id: 2, claim_number: 'CLM-2026-0447', policy_number: 'POL-10198', customer_name: 'BW Mining Corp', reserve_amount: 380000, status: 'Approved' },
            { id: 3, claim_number: 'CLM-2026-0443', policy_number: 'POL-10156', customer_name: 'Gaborone Motors', reserve_amount: 275000, status: 'Pending Assessment' },
            { id: 4, claim_number: 'CLM-2026-0440', policy_number: 'POL-10289', customer_name: 'TechBW Solutions', reserve_amount: 195000, status: 'New' },
            { id: 5, claim_number: 'CLM-2026-0435', policy_number: 'POL-10312', customer_name: 'Safari Lodges Ltd', reserve_amount: 160000, status: 'Under Review' },
          ],
          monthly_trend: [
            { month: 'Nov', count: 14 },
            { month: 'Dec', count: 18 },
            { month: 'Jan', count: 22 },
            { month: 'Feb', count: 16 },
            { month: 'Mar', count: 19 },
            { month: 'Apr', count: 12 },
          ],
        })
      })
      .finally(() => setLoading(false))
  }, [])

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin w-8 h-8 border-4 border-claims-primary border-t-transparent rounded-full" />
      </div>
    )
  }

  if (!data) return null

  const maxStatusCount = Math.max(...Object.values(data.claims_by_status), 1)
  const maxTrendCount = Math.max(...data.monthly_trend.map(m => m.count), 1)

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-800">Claims Dashboard</h1>
        <button
          onClick={() => navigate('/claims/new')}
          className="px-4 py-2 bg-claims-primary text-white rounded-lg text-sm font-medium hover:bg-claims-dark transition"
        >
          + New Claim
        </button>
      </div>

      {/* Stat Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-500">Open Claims</p>
              <p className="text-3xl font-bold text-gray-800 mt-1">{data.open_claims}</p>
            </div>
            <div className="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center">
              <svg className="w-6 h-6 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-500">Reserves Outstanding</p>
              <p className="text-3xl font-bold text-gray-800 mt-1">{formatCurrency(data.total_reserves_outstanding)}</p>
            </div>
            <div className="w-12 h-12 bg-red-50 rounded-xl flex items-center justify-center">
              <svg className="w-6 h-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-500">Avg Settlement Days</p>
              <p className="text-3xl font-bold text-gray-800 mt-1">{data.avg_settlement_days}</p>
            </div>
            <div className="w-12 h-12 bg-amber-50 rounded-xl flex items-center justify-center">
              <svg className="w-6 h-6 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-500">Claims This Month</p>
              <p className="text-3xl font-bold text-gray-800 mt-1">{data.claims_this_month}</p>
            </div>
            <div className="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center">
              <svg className="w-6 h-6 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Claims by Status */}
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
          <h3 className="text-sm font-semibold text-gray-700 mb-4">Claims by Status</h3>
          <div className="space-y-3">
            {Object.entries(data.claims_by_status).map(([status, count]) => (
              <div key={status} className="flex items-center gap-3">
                <span className="text-sm text-gray-600 w-36 flex-shrink-0">{status}</span>
                <div className="flex-1 bg-gray-100 rounded-full h-6 overflow-hidden">
                  <div
                    className={`h-full rounded-full ${statusColors[status] || 'bg-gray-400'} flex items-center justify-end pr-2 transition-all duration-500`}
                    style={{ width: `${Math.max((count / maxStatusCount) * 100, 8)}%` }}
                  >
                    <span className="text-xs font-bold text-white">{count}</span>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Claims by Type */}
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
          <h3 className="text-sm font-semibold text-gray-700 mb-4">Claims by Type</h3>
          <div className="space-y-3">
            {Object.entries(data.claims_by_type).map(([type, count]) => (
              <div key={type} className="flex items-center justify-between py-1.5 border-b border-gray-50 last:border-0">
                <span className="text-sm text-gray-700">{type}</span>
                <span className="text-sm font-semibold text-gray-800 bg-gray-100 px-3 py-0.5 rounded-full">{count}</span>
              </div>
            ))}
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Top 5 Highest Value Claims */}
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
          <h3 className="text-sm font-semibold text-gray-700 mb-4">Top 5 Highest Value Claims</h3>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-xs text-gray-500 border-b border-gray-100">
                  <th className="pb-2 font-medium">Claim #</th>
                  <th className="pb-2 font-medium">Policy</th>
                  <th className="pb-2 font-medium text-right">Amount</th>
                  <th className="pb-2 font-medium">Status</th>
                </tr>
              </thead>
              <tbody>
                {data.top_claims.map((claim) => (
                  <tr
                    key={claim.id}
                    onClick={() => navigate(`/claims/${claim.id}`)}
                    className="border-b border-gray-50 last:border-0 hover:bg-gray-50 cursor-pointer transition"
                  >
                    <td className="py-2 font-medium text-claims-primary">{claim.claim_number}</td>
                    <td className="py-2 text-gray-600">{claim.policy_number}</td>
                    <td className="py-2 text-right font-medium text-gray-800">{formatCurrency(claim.reserve_amount)}</td>
                    <td className="py-2">
                      <span className={`inline-block px-2 py-0.5 rounded-full text-xs font-medium text-white ${statusColors[claim.status] || 'bg-gray-400'}`}>
                        {claim.status}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* Monthly Trend */}
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
          <h3 className="text-sm font-semibold text-gray-700 mb-4">Monthly Trend</h3>
          <div className="flex items-end gap-3 h-48">
            {data.monthly_trend.map((month) => (
              <div key={month.month} className="flex-1 flex flex-col items-center gap-1">
                <span className="text-xs font-semibold text-gray-700">{month.count}</span>
                <div
                  className="w-full bg-claims-primary/80 rounded-t-md transition-all duration-500 hover:bg-claims-primary"
                  style={{ height: `${(month.count / maxTrendCount) * 100}%`, minHeight: '8px' }}
                />
                <span className="text-xs text-gray-500 mt-1">{month.month}</span>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  )
}
