import { useState, useEffect } from 'react'
import { useParams, Link } from 'react-router-dom'
import apiClient from '../../api/client'
import { fmtPula } from '../../utils/format'

export default function AgentDetailPage() {
  const { id } = useParams<{ id: string }>()
  const [data, setData] = useState<any>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    if (!id) return
    apiClient.get(`/agents/${id}`)
      .then(r => setData(r.data.data))
      .catch(() => {})
      .finally(() => setLoading(false))
  }, [id])

  if (loading) return <div className="p-6"><div className="animate-pulse space-y-3">{[1,2,3].map(i => <div key={i} className="h-12 bg-gray-200 rounded" />)}</div></div>
  if (!data) return <div className="p-6 text-red-600">Agent not found.</div>

  const s = data.stats
  const statCards = [
    { label: 'Active Policies', value: s.activePolicies, color: 'bg-blue-50 text-blue-700' },
    { label: 'Total Policies', value: s.totalPolicies, color: 'bg-gray-50 text-gray-700' },
    { label: 'Cancelled', value: s.cancelledPolicies, color: 'bg-red-50 text-red-700' },
    { label: 'Retention Rate', value: `${s.retentionRate}%`, color: 'bg-green-50 text-green-700' },
    { label: 'Commission Earned', value: fmtPula(s.commissionEarned), color: 'bg-emerald-50 text-emerald-700' },
    { label: 'Commission Paid', value: fmtPula(s.commissionPaid), color: 'bg-purple-50 text-purple-700' },
  ]

  return (
    <div className="p-6 space-y-5">
      <div className="flex items-center gap-3">
        <Link to="/agents" className="text-blue-600 hover:underline text-sm">&larr; Agents</Link>
        <h1 className="text-2xl font-bold text-gray-800">{data.name}</h1>
        {data.agencyName && <span className="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm font-medium">{data.agencyName}</span>}
      </div>

      {/* Profile */}
      <div className="bg-white shadow rounded-lg p-5 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
        <div><span className="text-gray-500 text-xs">Email</span><p className="font-medium">{data.email || '-'}</p></div>
        <div><span className="text-gray-500 text-xs">Phone</span><p className="font-medium">{data.cellphone || '-'}</p></div>
        <div><span className="text-gray-500 text-xs">DOB</span><p className="font-medium">{data.dob || '-'}</p></div>
        <div><span className="text-gray-500 text-xs">Omang</span><p className="font-medium">{data.omang || '-'}</p></div>
        <div><span className="text-gray-500 text-xs">Account Type</span><p className="font-medium">{data.accountType || '-'}</p></div>
        <div><span className="text-gray-500 text-xs">Joined</span><p className="font-medium">{data.createdAt?.split('T')[0] ?? '-'}</p></div>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        {statCards.map((c, i) => (
          <div key={i} className={`rounded-lg p-4 text-center ${c.color}`}>
            <p className="text-2xl font-bold">{c.value}</p>
            <p className="text-xs mt-1">{c.label}</p>
          </div>
        ))}
      </div>
    </div>
  )
}
