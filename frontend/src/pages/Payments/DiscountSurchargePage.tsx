import { useState } from 'react'
import apiClient from '../../api/client'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import { fmtPula } from '../../utils/format'

interface DiscountSurchargeEntry {
  id: number
  type: 'discount' | 'surcharge'
  description: string
  amount: number | null
  percentage: number | null
  applied_date: string
}

interface PolicySummary {
  policy_number: string
  customer_name: string
  product: string
}

const TYPE_BADGE: Record<string, { label: string; classes: string }> = {
  discount:   { label: 'Discount',   classes: 'bg-green-100 text-green-700' },
  surcharge:  { label: 'Surcharge',  classes: 'bg-orange-100 text-orange-700' },
}

export default function DiscountSurchargePage() {
  const [policyNumber, setPolicyNumber] = useState('')
  const [policySummary, setPolicySummary] = useState<PolicySummary | null>(null)
  const [entries, setEntries] = useState<DiscountSurchargeEntry[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')

  async function handleLookup() {
    if (!policyNumber.trim()) return
    setLoading(true)
    setError('')
    setPolicySummary(null)
    setEntries([])
    try {
      const res = await apiClient.get('/payments/discounts-surcharges', {
        params: { policy_number: policyNumber.trim() },
      })
      const data = res.data
      setPolicySummary(data.policy ?? null)
      setEntries(data.data ?? data.items ?? [])
    } catch (err: any) {
      const msg = err.response?.data?.message || 'Failed to load discounts/surcharges. Please check the policy number.'
      setError(msg)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="p-6 space-y-4">
      <h1 className="text-2xl font-bold text-gray-800">Discount / Surcharge</h1>

      {/* Policy Number Lookup */}
      <div className="bg-white rounded-lg border border-gray-200 shadow-sm p-6 max-w-lg">
        <label className="block text-sm font-medium text-gray-700 mb-1">Policy Number</label>
        <div className="flex gap-3">
          <input
            type="text"
            value={policyNumber}
            onChange={e => setPolicyNumber(e.target.value)}
            onKeyDown={e => { if (e.key === 'Enter') handleLookup() }}
            placeholder="Enter policy number..."
            className="flex-1 border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-brand-navy/30 focus:outline-none"
          />
          <button
            onClick={handleLookup}
            disabled={!policyNumber.trim() || loading}
            className="px-4 py-2 bg-brand-navy text-white rounded-md text-sm font-medium hover:bg-brand-navy-light disabled:opacity-50 transition flex items-center gap-2"
          >
            {loading && <LoadingSpinner size="sm" />}
            Search
          </button>
        </div>
        {error && (
          <p className="mt-2 text-sm text-red-600">{error}</p>
        )}
      </div>

      {/* Results */}
      {loading && (
        <div className="bg-white rounded-lg border border-gray-200 shadow-sm p-12">
          <LoadingSpinner size="lg" className="mb-3" />
          <p className="text-center text-sm text-gray-400">Loading discounts and surcharges...</p>
        </div>
      )}

      {!loading && policySummary && (
        <>
          {/* Policy Summary */}
          <div className="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
            <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Policy Details</h2>
            <div className="grid grid-cols-3 gap-x-8 text-sm">
              <div>
                <span className="text-gray-500">Policy Number:</span>
                <span className="ml-2 font-mono font-medium text-gray-800">{policySummary.policy_number}</span>
              </div>
              <div>
                <span className="text-gray-500">Customer:</span>
                <span className="ml-2 font-medium text-gray-800">{policySummary.customer_name}</span>
              </div>
              <div>
                <span className="text-gray-500">Product:</span>
                <span className="ml-2 text-gray-800">{policySummary.product}</span>
              </div>
            </div>
          </div>

          {/* Discounts / Surcharges Table */}
          <div className="bg-white rounded-lg border border-gray-200 shadow-sm overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                <tr>
                  <th className="px-4 py-3 text-left">Type</th>
                  <th className="px-4 py-3 text-left">Description</th>
                  <th className="px-4 py-3 text-right">Amount / Percentage</th>
                  <th className="px-4 py-3 text-left">Applied Date</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {entries.map(entry => {
                  const typeKey = String(entry.type ?? '').toLowerCase()
                  const badge = TYPE_BADGE[typeKey] ?? { label: String(entry.type ?? '—'), classes: 'bg-gray-100 text-gray-600' }
                  return (
                    <tr key={entry.id} className="hover:bg-gray-50">
                      <td className="px-4 py-2">
                        <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${badge.classes}`}>
                          {badge.label}
                        </span>
                      </td>
                      <td className="px-4 py-2 text-gray-700">{entry.description || '—'}</td>
                      <td className="px-4 py-2 text-right font-medium text-gray-800">
                        {entry.amount != null
                          ? fmtPula(entry.amount)
                          : entry.percentage != null
                            ? `${Number(entry.percentage).toFixed(2)}%`
                            : '—'}
                      </td>
                      <td className="px-4 py-2 text-gray-500 text-xs">
                        {entry.applied_date
                          ? new Date(entry.applied_date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
                          : '—'}
                      </td>
                    </tr>
                  )
                })}
                {entries.length === 0 && (
                  <tr>
                    <td colSpan={4} className="px-4 py-12 text-center">
                      <svg className="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={1.5}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />
                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 6h.008v.008H6V6z" />
                      </svg>
                      <h3 className="text-base font-medium text-gray-500 mb-1">No discounts or surcharges</h3>
                      <p className="text-sm text-gray-400">This policy has no discounts or surcharges applied.</p>
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </>
      )}
    </div>
  )
}
