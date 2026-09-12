import { useState, useEffect, useCallback } from 'react'
import apiClient from '../../api/client'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import { fmtPula } from '../../utils/format'

interface ScheduleEntry {
  id: number
  policy_number: string
  amount: number
  frequency: string
  next_payment_date: string
  status: string
}

const STATUS_BADGE: Record<string, { label: string; classes: string }> = {
  active:    { label: 'Active',    classes: 'bg-green-100 text-green-700' },
  paused:    { label: 'Paused',    classes: 'bg-yellow-100 text-yellow-700' },
  cancelled: { label: 'Cancelled', classes: 'bg-red-100 text-red-700' },
  completed: { label: 'Completed', classes: 'bg-gray-100 text-gray-600' },
}

export default function CancelSchedulePage() {
  const [entries, setEntries] = useState<ScheduleEntry[]>([])
  const [loading, setLoading] = useState(true)
  const [fetching, setFetching] = useState(false)
  const [search, setSearch] = useState('')
  const [cancellingId, setCancellingId] = useState<number | null>(null)
  const [alert, setAlert] = useState<{ type: 'success' | 'error'; message: string } | null>(null)

  const [searchInput, setSearchInput] = useState('')

  const fetchSchedules = useCallback(async () => {
    setFetching(true)
    try {
      const params: Record<string, string> = {}
      if (search) params.search = search
      const res = await apiClient.get('/payments/schedule', { params })
      setEntries(res.data.data ?? res.data ?? [])
    } catch {
      setEntries([])
    } finally {
      setLoading(false)
      setFetching(false)
    }
  }, [search])

  useEffect(() => {
    fetchSchedules()
  }, [fetchSchedules])

  // Debounced search
  useEffect(() => {
    const timer = setTimeout(() => {
      setSearch(searchInput)
    }, 400)
    return () => clearTimeout(timer)
  }, [searchInput])

  async function handleCancel(entry: ScheduleEntry) {
    const confirmed = window.confirm(
      `Are you sure you want to cancel the payment schedule for policy ${entry.policy_number}?\n\nAmount: P ${Number(entry.amount).toFixed(2)}\nFrequency: ${entry.frequency}`
    )
    if (!confirmed) return

    setCancellingId(entry.id)
    setAlert(null)
    try {
      await apiClient.post(`/payments/schedule/${entry.id}/cancel`)
      setEntries(prev =>
        prev.map(e => e.id === entry.id ? { ...e, status: 'cancelled' } : e)
      )
      setAlert({ type: 'success', message: `Schedule for policy ${entry.policy_number} cancelled successfully.` })
    } catch (err: any) {
      const msg = err.response?.data?.message || 'Failed to cancel schedule. Please try again.'
      setAlert({ type: 'error', message: msg })
    } finally {
      setCancellingId(null)
    }
  }

  return (
    <div className="p-6 space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-800">Cancel Schedule Transactions</h1>
      </div>

      {/* Alert */}
      {alert && (
        <div className={`rounded-lg border px-4 py-3 text-sm ${
          alert.type === 'success'
            ? 'bg-green-50 border-green-200 text-green-800'
            : 'bg-red-50 border-red-200 text-red-800'
        }`}>
          <div className="flex items-center gap-2">
            {alert.type === 'success' ? (
              <svg className="w-5 h-5 text-green-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            ) : (
              <svg className="w-5 h-5 text-red-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            )}
            <span>{alert.message}</span>
            <button onClick={() => setAlert(null)} className="ml-auto text-gray-400 hover:text-gray-600">
              <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
      )}

      {/* Search */}
      <div className="flex flex-wrap gap-3 items-end">
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Search</label>
          <input
            type="text"
            placeholder="Policy number..."
            value={searchInput}
            onChange={e => setSearchInput(e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm w-64 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
      </div>

      {/* Table */}
      {loading ? (
        <div className="bg-white rounded-lg border border-gray-200 shadow-sm p-12">
          <LoadingSpinner size="lg" className="mb-3" />
          <p className="text-center text-sm text-gray-400">Loading payment schedules...</p>
        </div>
      ) : (
        <>
          {fetching && (
            <div className="bg-white rounded-lg border border-blue-200 shadow-sm px-4 py-3 flex items-center gap-2">
              <LoadingSpinner size="sm" />
              <span className="text-sm text-blue-600">Updating results...</span>
            </div>
          )}

          <div className={`bg-white rounded-lg border border-gray-200 shadow-sm overflow-x-auto transition-opacity ${fetching ? 'opacity-50 pointer-events-none' : ''}`}>
            <table className="w-full text-sm">
              <thead className="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                <tr>
                  <th className="px-4 py-3 text-left">Policy #</th>
                  <th className="px-4 py-3 text-right">Amount</th>
                  <th className="px-4 py-3 text-left">Frequency</th>
                  <th className="px-4 py-3 text-left">Next Payment</th>
                  <th className="px-4 py-3 text-left">Status</th>
                  <th className="px-4 py-3 text-center">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {entries.map(entry => {
                  const statusKey = String(entry.status ?? '').toLowerCase()
                  const badge = STATUS_BADGE[statusKey] ?? { label: String(entry.status ?? '—'), classes: 'bg-gray-100 text-gray-600' }
                  const isCancelled = statusKey === 'cancelled'
                  const isCompleted = statusKey === 'completed'
                  return (
                    <tr key={entry.id} className="hover:bg-gray-50">
                      <td className="px-4 py-2 font-mono text-gray-700">{entry.policy_number}</td>
                      <td className="px-4 py-2 text-right font-medium text-gray-800">
                        {fmtPula(entry.amount)}
                      </td>
                      <td className="px-4 py-2 text-gray-600 capitalize">{entry.frequency}</td>
                      <td className="px-4 py-2 text-gray-500 text-xs">
                        {entry.next_payment_date
                          ? new Date(entry.next_payment_date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
                          : '—'}
                      </td>
                      <td className="px-4 py-2">
                        <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${badge.classes}`}>
                          {badge.label}
                        </span>
                      </td>
                      <td className="px-4 py-2 text-center">
                        {!isCancelled && !isCompleted ? (
                          <button
                            onClick={() => handleCancel(entry)}
                            disabled={cancellingId === entry.id}
                            className="text-xs font-medium text-red-600 hover:text-red-800 disabled:opacity-50 transition"
                          >
                            {cancellingId === entry.id ? 'Cancelling...' : 'Cancel'}
                          </button>
                        ) : (
                          <span className="text-xs text-gray-400">—</span>
                        )}
                      </td>
                    </tr>
                  )
                })}
                {entries.length === 0 && (
                  <tr>
                    <td colSpan={6} className="px-4 py-12 text-center">
                      <svg className="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={1.5}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                      </svg>
                      <h3 className="text-base font-medium text-gray-500 mb-1">No payment schedules found</h3>
                      <p className="text-sm text-gray-400">Try adjusting your search criteria.</p>
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
