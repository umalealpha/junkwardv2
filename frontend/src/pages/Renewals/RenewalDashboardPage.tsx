import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import apiClient from '../../api/client'
import EmptyState from '../../components/common/EmptyState'

interface Summary {
  due7days: number; due15days: number; due30days: number; due60days: number
  overdue: number; recentlyRenewed: number; pendingConsent: number
}

interface PipelineItem {
  policyId: number; policyNumber: string; productId: number; productName: string
  section: 'mis' | 'domcom'
  premiumFreq: number; premiumFreqLabel: string
  customerName: string; customerPhone: string; customerEmail: string
  agentName: string; currentPremium: string; newPremium: string | null
  oldPremium: string | null; expiryDate: string; daysToExpiry: number
  isRated: boolean; isRenewed: boolean; needsConsent: boolean
  rateChange: number | null
}

const SECTIONS = [
  { key: 'all', label: 'All Policies', desc: 'Everything' },
  { key: 'mis', label: 'MIS (auto-debit)', desc: 'Re-rate → consent / auto-renew / deactivate' },
  { key: 'domcom', label: 'DomCom / Specialist', desc: 'Report-only (no auto-debit)' },
] as const

const FILTERS = [
  { key: 'all', label: 'All Due (60 days)' },
  { key: 'due_7', label: 'Due 7 Days' },
  { key: 'due_15', label: 'Due 15 Days' },
  { key: 'due_30', label: 'Due 30 Days' },
  { key: 'overdue', label: 'Overdue' },
  { key: 'pending_consent', label: 'Pending Consent' },
  { key: 'renewed', label: 'Renewed' },
]

export default function RenewalDashboardPage() {
  const [summary, setSummary] = useState<Summary | null>(null)
  const [items, setItems] = useState<PipelineItem[]>([])
  const [loading, setLoading] = useState(true)
  const [filter, setFilter] = useState('all')
  const [section, setSection] = useState<'all' | 'mis' | 'domcom'>('all')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [hasMore, setHasMore] = useState(false)
  const [exporting, setExporting] = useState(false)
  const [actingPolicyId, setActingPolicyId] = useState<number | null>(null)

  // Triggers the backend Excel export with the same filter+search the user
  // is looking at on screen. Uses a blob fetch (auth header) instead of a
  // bare window.open — the endpoint is Sanctum-guarded.
  async function exportExcel() {
    setExporting(true)
    try {
      const params: any = { filter, section }
      if (search) params.search = search
      const r = await apiClient.get('/renewals/pipeline/export', { params, responseType: 'blob' })
      const url = URL.createObjectURL(new Blob([r.data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }))
      const a = document.createElement('a')
      a.href = url
      a.download = `renewals-${filter}-${new Date().toISOString().slice(0, 10)}.xlsx`
      document.body.appendChild(a)
      a.click()
      a.remove()
      setTimeout(() => URL.revokeObjectURL(url), 0)
    } catch (e: any) {
      alert('Export failed: ' + (e?.response?.statusText || e.message || 'Unknown'))
    }
    setExporting(false)
  }

  useEffect(() => {
    apiClient.get('/renewals/dashboard').then(r => setSummary(r.data.data.summary)).catch(() => {})
  }, [])

  useEffect(() => {
    setLoading(true)
    const params: any = { filter, section, page, per_page: 25 }
    if (search) params.search = search
    apiClient.get('/renewals/pipeline', { params })
      .then(r => {
        setItems(r.data.data ?? [])
        setHasMore(r.data.meta?.has_more ?? false)
      })
      .catch(() => setItems([]))
      .finally(() => setLoading(false))
  }, [filter, section, search, page])

  async function sendPaymentUrl(policyId: number, policyNumber: string) {
    if (!confirm(`Generate a consent / payment link for ${policyNumber}?`)) return
    setActingPolicyId(policyId)
    try {
      const r = await apiClient.post(`/renewals/${policyId}/send-payment-url`)
      const link = r.data?.link
      if (link) {
        // Copy to clipboard for the agent to forward over WhatsApp/phone.
        try { await navigator.clipboard.writeText(link) } catch {}
        alert(`Payment link ready (copied to clipboard):\n${link}`)
      } else {
        alert('Payment link generated — check Notifications.')
      }
    } catch (e: any) {
      alert(e?.response?.data?.error || 'Failed to generate link')
    }
    setActingPolicyId(null)
  }

  async function autoRenew(policyId: number, policyNumber: string) {
    if (!confirm(`Auto-renew ${policyNumber}? Only allowed when the new premium is ≤ old premium.`)) return
    setActingPolicyId(policyId)
    try {
      await apiClient.post(`/renewals/${policyId}/auto-renew`)
      alert('Policy auto-renewed.')
      // refresh
      setPage(p => p)
    } catch (e: any) {
      alert(e?.response?.data?.error || 'Failed to auto-renew')
    }
    setActingPolicyId(null)
  }

  async function deactivate(policyId: number, policyNumber: string) {
    const reason = prompt(`Deactivate ${policyNumber}? Enter reason (e.g. "renewal payment failed"):`, 'Renewal payment failed')
    if (!reason) return
    setActingPolicyId(policyId)
    try {
      await apiClient.post(`/renewals/${policyId}/deactivate`, { reason })
      alert('Policy deactivated.')
      setPage(p => p)
    } catch (e: any) {
      alert(e?.response?.data?.error || 'Failed to deactivate')
    }
    setActingPolicyId(null)
  }

  const urgencyBadge = (days: number) => {
    if (days < 0) return <span className="px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700 font-bold">OVERDUE {Math.abs(days)}d</span>
    if (days <= 7) return <span className="px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700 font-medium">{days}d</span>
    if (days <= 15) return <span className="px-2 py-0.5 text-xs rounded-full bg-orange-100 text-orange-700 font-medium">{days}d</span>
    if (days <= 30) return <span className="px-2 py-0.5 text-xs rounded-full bg-yellow-100 text-yellow-700 font-medium">{days}d</span>
    return <span className="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600">{days}d</span>
  }

  return (
    <div className="p-6 space-y-5">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-800">Renewal Dashboard</h1>
        <button onClick={exportExcel} disabled={exporting}
          className="inline-flex items-center gap-1.5 px-4 py-2 bg-green-600 text-white text-sm font-medium rounded hover:bg-green-700 disabled:opacity-50"
          title="Download the current filter as Excel — includes policy, customer contact, agent, premium, days-to-expiry">
          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          {exporting ? 'Exporting…' : 'Export to Excel'}
        </button>
      </div>

      {/* Summary Cards */}
      {summary && (
        <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
          {[
            { label: 'Due 7 Days', value: summary.due7days, color: 'bg-red-50 border-red-200 text-red-700', click: 'due_7' },
            { label: 'Due 15 Days', value: summary.due15days, color: 'bg-orange-50 border-orange-200 text-orange-700', click: 'due_15' },
            { label: 'Due 30 Days', value: summary.due30days, color: 'bg-yellow-50 border-yellow-200 text-yellow-700', click: 'due_30' },
            { label: 'Due 60 Days', value: summary.due60days, color: 'bg-blue-50 border-blue-200 text-blue-700', click: 'all' },
            { label: 'Overdue', value: summary.overdue, color: 'bg-red-50 border-red-300 text-red-800', click: 'overdue' },
            { label: 'Pending Consent', value: summary.pendingConsent, color: 'bg-purple-50 border-purple-200 text-purple-700', click: 'pending_consent' },
            { label: 'Renewed (30d)', value: summary.recentlyRenewed, color: 'bg-green-50 border-green-200 text-green-700', click: 'renewed' },
          ].map((card, i) => (
            <button key={i} onClick={() => { setFilter(card.click); setPage(1) }}
              className={`rounded-lg border p-3 text-center cursor-pointer hover:shadow transition ${card.color} ${filter === card.click ? 'ring-2 ring-offset-1 ring-blue-400' : ''}`}>
              <p className="text-2xl font-bold">{card.value}</p>
              <p className="text-xs mt-1">{card.label}</p>
            </button>
          ))}
        </div>
      )}

      {/* Section tabs — MIS vs DomCom. MIS needs the auto-debit workflow
          (consent → payment URL → auto-renew or deactivate). DomCom is
          report-only since those products don't auto-debit. */}
      <div className="flex gap-2 border-b border-gray-200">
        {SECTIONS.map(s => (
          <button key={s.key}
            onClick={() => { setSection(s.key); setPage(1) }}
            className={`px-4 py-2 text-sm font-medium transition border-b-2 -mb-px ${
              section === s.key
                ? 'border-blue-600 text-blue-700'
                : 'border-transparent text-gray-500 hover:text-gray-800'
            }`}
            title={s.desc}
          >
            {s.label}
          </button>
        ))}
      </div>

      {/* Filters + Search */}
      <div className="flex items-center gap-3 flex-wrap">
        <div className="flex gap-1 bg-gray-100 rounded-lg p-1">
          {FILTERS.map(f => (
            <button key={f.key} onClick={() => { setFilter(f.key); setPage(1) }}
              className={`px-3 py-1.5 text-xs rounded-md font-medium transition ${filter === f.key ? 'bg-white shadow text-blue-700' : 'text-gray-500 hover:text-gray-700'}`}>
              {f.label}
            </button>
          ))}
        </div>
        <input value={search} onChange={e => { setSearch(e.target.value); setPage(1) }}
          placeholder="Search policy or customer..."
          className="px-3 py-1.5 border rounded-md text-sm w-56" />
      </div>

      {/* Pipeline Table */}
      <div className="bg-white shadow rounded-lg overflow-hidden">
        <table className="min-w-full divide-y divide-gray-200 text-sm">
          <thead className="bg-gray-50">
            <tr>
              <th className="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Policy</th>
              <th className="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
              <th className="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Agent</th>
              <th className="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
              <th className="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Anniversary</th>
              <th className="px-3 py-2.5 text-right text-xs font-medium text-gray-500 uppercase">Premium</th>
              <th className="px-3 py-2.5 text-right text-xs font-medium text-gray-500 uppercase">New Rate</th>
              <th className="px-3 py-2.5 text-center text-xs font-medium text-gray-500 uppercase">Expiry</th>
              <th className="px-3 py-2.5 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
              <th className="px-3 py-2.5 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {loading && <tr><td colSpan={10} className="px-4 py-8 text-center text-gray-400">Loading...</td></tr>}
            {!loading && items.length === 0 && <tr><td colSpan={10} className="p-0"><EmptyState compact title="No renewals in this view" description="Try adjusting your filters or switching views." /></td></tr>}
            {items.map(item => {
              const isMis = item.section === 'mis'
              const acting = actingPolicyId === item.policyId
              const canAutoRenew = isMis && item.isRated && !item.isRenewed
                && item.newPremium != null && item.oldPremium != null
                && parseFloat(item.newPremium.replace(/,/g, '')) <= parseFloat(item.oldPremium.replace(/,/g, ''))
              return (
              <tr key={item.policyId} className={`hover:bg-gray-50 ${item.daysToExpiry < 0 ? 'bg-red-50/30' : item.needsConsent ? 'bg-purple-50/30' : ''}`}>
                <td className="px-3 py-2.5">
                  <Link to={`/policies/${item.policyId}`} className="text-blue-600 hover:underline font-medium text-xs">{item.policyNumber}</Link>
                  <div className="text-[10px] mt-0.5">
                    <span className={`px-1.5 py-0.5 rounded ${isMis ? 'bg-orange-100 text-orange-700' : 'bg-blue-100 text-blue-700'}`}>
                      {isMis ? 'MIS' : 'DomCom'}
                    </span>
                  </div>
                </td>
                <td className="px-3 py-2.5">
                  <div className="text-sm font-medium text-gray-800">{item.customerName}</div>
                  <div className="text-[10px] text-gray-400">{item.customerPhone} {item.customerEmail ? `| ${item.customerEmail}` : ''}</div>
                </td>
                <td className="px-3 py-2.5 text-xs text-gray-600">{item.agentName || '-'}</td>
                <td className="px-3 py-2.5 text-xs">{item.productName}</td>
                <td className="px-3 py-2.5 text-xs">
                  <span className={`px-2 py-0.5 rounded text-[11px] ${
                    item.premiumFreq === 1 ? 'bg-blue-50 text-blue-700' :
                    item.premiumFreq === 2 ? 'bg-violet-50 text-violet-700' :
                    item.premiumFreq === 3 ? 'bg-emerald-50 text-emerald-700' :
                    'bg-gray-50 text-gray-500'
                  }`}>
                    {item.premiumFreqLabel}
                  </span>
                </td>
                <td className="px-3 py-2.5 text-right text-xs font-medium">P {item.currentPremium}</td>
                <td className="px-3 py-2.5 text-right text-xs">
                  {item.newPremium ? (
                    <div>
                      <span className="font-medium">P {item.newPremium}</span>
                      {item.rateChange !== null && (
                        <span className={`ml-1 text-[10px] ${item.rateChange > 0 ? 'text-red-600' : 'text-green-600'}`}>
                          {item.rateChange > 0 ? '+' : ''}{item.rateChange}%
                        </span>
                      )}
                    </div>
                  ) : <span className="text-gray-300">-</span>}
                </td>
                <td className="px-3 py-2.5 text-center">
                  <div className="text-xs text-gray-600">{item.expiryDate}</div>
                  {urgencyBadge(item.daysToExpiry)}
                </td>
                <td className="px-3 py-2.5 text-center">
                  {item.isRenewed ? (
                    <span className="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-700 font-medium">Renewed</span>
                  ) : item.needsConsent ? (
                    <span className="px-2 py-0.5 text-xs rounded-full bg-purple-100 text-purple-700 font-medium">Needs Consent</span>
                  ) : item.isRated ? (
                    <span className="px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-700 font-medium">Rated</span>
                  ) : (
                    <span className="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-500">Pending</span>
                  )}
                </td>
                <td className="px-3 py-2.5 text-center">
                  {isMis ? (
                    <div className="flex items-center justify-center gap-1">
                      {item.needsConsent && (
                        <button disabled={acting}
                          onClick={() => sendPaymentUrl(item.policyId, item.policyNumber)}
                          title="Generate + copy payment/consent URL to send customer"
                          className="px-2 py-0.5 text-[10px] bg-purple-600 text-white rounded hover:bg-purple-700 disabled:opacity-50">
                          Send Link
                        </button>
                      )}
                      {canAutoRenew && (
                        <button disabled={acting}
                          onClick={() => autoRenew(item.policyId, item.policyNumber)}
                          title="New premium ≤ current — eligible for auto-renew"
                          className="px-2 py-0.5 text-[10px] bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-50">
                          Renew
                        </button>
                      )}
                      <button disabled={acting}
                        onClick={() => deactivate(item.policyId, item.policyNumber)}
                        title="Payment failed — deactivate policy"
                        className="px-2 py-0.5 text-[10px] bg-red-600 text-white rounded hover:bg-red-700 disabled:opacity-50">
                        Deactivate
                      </button>
                    </div>
                  ) : (
                    <span className="text-[10px] text-gray-400 italic">report-only</span>
                  )}
                </td>
              </tr>
              )
            })}
          </tbody>
        </table>
      </div>

      {/* Pagination */}
      <div className="flex justify-between items-center">
        <button disabled={page <= 1} onClick={() => setPage(p => p - 1)}
          className="px-3 py-1.5 text-sm border rounded-md disabled:opacity-30 hover:bg-gray-50">Previous</button>
        <span className="text-sm text-gray-500">Page {page}</span>
        <button disabled={!hasMore} onClick={() => setPage(p => p + 1)}
          className="px-3 py-1.5 text-sm border rounded-md disabled:opacity-30 hover:bg-gray-50">Next</button>
      </div>
    </div>
  )
}
