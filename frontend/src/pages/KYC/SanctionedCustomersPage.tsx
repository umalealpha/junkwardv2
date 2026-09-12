import { Link } from 'react-router-dom'
import { useSanctionedCustomers } from '../../hooks/useSanctions'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import type { SanctionedCustomer } from '../../api/sanctions'
import EmptyState from '../../components/common/EmptyState'

// React port of the Blade admin.sanctioned_customers page. Same columns,
// same badge logic: customers flagged by the AML screening cron.

function scoreCls(score: number): string {
  if (score > 0.7) return 'bg-red-100 text-red-700'
  if (score > 0.5) return 'bg-amber-100 text-amber-700'
  return 'bg-gray-100 text-gray-600'
}

function statusCls(status: string | null): string {
  const s = (status ?? '').toLowerCase()
  if (s === 'rejected') return 'bg-red-100 text-red-700'
  if (s === 'review') return 'bg-amber-100 text-amber-700'
  return 'bg-green-100 text-green-700'
}

function TargetBadge({ target }: { target: SanctionedCustomer['target'] }) {
  if (target === 'true' || target === true) {
    return <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">True</span>
  }
  if (target === 'false' || target === false) {
    return <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">False</span>
  }
  return <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">{target ?? 'N/A'}</span>
}

export default function SanctionedCustomersPage() {
  const { data, isLoading } = useSanctionedCustomers()
  const rows = data?.data ?? []

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <span className="text-amber-500">&#128737;</span> Sanctioned Customers
        </h1>
        <Link
          to="/kyc"
          className="px-3 py-1.5 border border-gray-300 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-100"
        >
          &larr; Back to KYC
        </Link>
      </div>

      <div className="rounded-md bg-blue-50 text-blue-800 ring-1 ring-blue-200 px-4 py-3 text-sm">
        This page shows all customers who have been flagged as sanctioned by the AML screening.
      </div>

      <div className="bg-white rounded-lg shadow-sm border overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-600 uppercase text-xs tracking-wider">
              <tr>
                <th className="px-4 py-3 text-left">Customer Name</th>
                <th className="px-4 py-3 text-left">Sanctioned in Countries</th>
                <th className="px-4 py-3 text-left">Program IDs</th>
                <th className="px-4 py-3 text-left">Max Score</th>
                <th className="px-4 py-3 text-left">Target</th>
                <th className="px-4 py-3 text-left">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {isLoading ? (
                <tr><td colSpan={6} className="px-4 py-12 text-center"><LoadingSpinner size="md" /></td></tr>
              ) : rows.length === 0 ? (
                <tr><td colSpan={6} className="p-0"><EmptyState compact title="No sanctioned customers found" description="Try adjusting your filters or search terms." /></td></tr>
              ) : (
                rows.map(c => (
                  <tr key={c.customerId} className="hover:bg-gray-50 transition">
                    <td className="px-4 py-2 font-medium">
                      {c.kycId ? (
                        <Link to={`/kyc/${c.kycId}`} className="text-blue-600 hover:underline">
                          {c.customerName}
                        </Link>
                      ) : (
                        <div>
                          <strong>{c.customerName}</strong>
                          <span className="block text-xs text-gray-400">No KYC data available</span>
                        </div>
                      )}
                    </td>
                    <td className="px-4 py-2">
                      {c.countries.length > 0 ? (
                        <div className="flex flex-wrap gap-1">
                          {c.countries.map((country, i) => (
                            <span key={i} className="px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700">{country}</span>
                          ))}
                        </div>
                      ) : <span className="text-gray-400">No data available</span>}
                    </td>
                    <td className="px-4 py-2">
                      {c.programIds.length > 0 ? (
                        <div className="flex flex-wrap gap-1">
                          {c.programIds.map((pid, i) => (
                            <span key={i} className="px-2 py-0.5 rounded text-xs font-medium bg-sky-100 text-sky-700">{pid}</span>
                          ))}
                        </div>
                      ) : <span className="text-gray-400">No data available</span>}
                    </td>
                    <td className="px-4 py-2">
                      <span className={`px-2 py-0.5 rounded-full text-xs font-semibold ${scoreCls(c.maxScore)}`}>
                        {c.maxScore.toFixed(3)}
                      </span>
                    </td>
                    <td className="px-4 py-2"><TargetBadge target={c.target} /></td>
                    <td className="px-4 py-2">
                      <span className={`px-2 py-0.5 rounded-full text-xs font-medium capitalize ${statusCls(c.status)}`}>
                        {c.status ?? '—'}
                      </span>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {rows.length > 0 && (
        <div className="rounded-md bg-red-50 text-red-800 ring-2 ring-red-300 px-4 py-3 flex items-center gap-2 text-sm font-semibold">
          <span className="text-lg">&#128737;</span>
          Total Sanctioned Customers:
          <span className="ml-1 px-2.5 py-0.5 rounded bg-white text-red-600 ring-1 ring-red-300 text-base">{rows.length}</span>
        </div>
      )}
    </div>
  )
}
