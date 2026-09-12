import { useState, useEffect, useCallback } from 'react'
import apiClient from '../../api/client'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import { fmtPula, fmtDate } from '../../utils/format'
import { reportErrorToTeam } from '../../utils/reportError'

interface LedgerEntry {
  id: number
  policy_number: string
  agent_name: string
  entry_type: 'earned' | 'clawback' | 'bonus' | 'adjustment'
  amount: number
  premium: number
  commission_type: string
  status: 'pending' | 'approved' | 'paid' | 'held' | 'cancelled'
  qualifying_date: string | null
  cooling_end: string | null
  kyc_met: boolean
  payments_met: boolean
}

interface LedgerSummary {
  total_earned: number
  total_clawback: number
  total_pending: number
  total_paid: number
}

interface PaginationMeta {
  current_page: number
  last_page: number
  from: number
  to: number
  total: number
}

const ENTRY_TYPE_BADGE: Record<string, string> = {
  earned: 'bg-green-100 text-green-700',
  clawback: 'bg-red-100 text-red-700',
  bonus: 'bg-purple-100 text-purple-700',
  adjustment: 'bg-orange-100 text-orange-700',
}

const STATUS_BADGE: Record<string, string> = {
  pending: 'bg-yellow-100 text-yellow-700',
  approved: 'bg-blue-100 text-blue-700',
  paid: 'bg-green-100 text-green-700',
  held: 'bg-orange-100 text-orange-700',
  cancelled: 'bg-red-100 text-red-700',
}

function titleCase(str: string | null | undefined): string {
  if (!str) return ''
  return str.replace(/\b\w/g, (c) => c.toUpperCase())
}

export default function CommissionLedgerPage() {
  const [entries, setEntries] = useState<LedgerEntry[]>([])
  const [summary, setSummary] = useState<LedgerSummary | null>(null)
  const [meta, setMeta] = useState<PaginationMeta | null>(null)
  const [loading, setLoading] = useState(true)
  const [approving, setApproving] = useState<number | null>(null)
  const [bulkApproving, setBulkApproving] = useState(false)
  const [error, setError] = useState('')
  const [selected, setSelected] = useState<Set<number>>(new Set())

  // Filters
  const [agentSearch, setAgentSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [entryTypeFilter, setEntryTypeFilter] = useState('')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [page, setPage] = useState(1)
  const [jumpPage, setJumpPage] = useState('')

  const loadLedger = useCallback(() => {
    setLoading(true)
    const params: Record<string, any> = { page, per_page: 25 }
    if (agentSearch) params.agent = agentSearch
    if (statusFilter) params.status = statusFilter
    if (entryTypeFilter) params.entry_type = entryTypeFilter
    if (dateFrom) params.date_from = dateFrom
    if (dateTo) params.date_to = dateTo

    apiClient
      .get('/commission/ledger', { params })
      .then((r) => {
        setEntries(r.data.data ?? [])
        setSummary(r.data.summary ?? null)
        setMeta(r.data.meta ?? null)
        setSelected(new Set())
        setError('')
      })
      .catch((err: any) => {
        // UAT 2026-05-28: surface the real error message + report to
        // developers@ instead of a bald "Failed to load…" string.
        // Same pattern as AuditTrail / UserList / RealPay fixes.
        const message = err?.response?.data?.message ?? err?.message ?? 'Failed to load commission ledger.'
        setError(message)
        reportErrorToTeam({
          error: message,
          stack: err?.stack,
          context: 'CommissionLedgerPage:loadLedger',
        })
      })
      .finally(() => setLoading(false))
  }, [page, agentSearch, statusFilter, entryTypeFilter, dateFrom, dateTo])

  useEffect(() => {
    loadLedger()
  }, [loadLedger])

  // Debounced agent search
  const [agentSearchInput, setAgentSearchInput] = useState('')
  useEffect(() => {
    const timer = setTimeout(() => {
      if (agentSearchInput !== agentSearch) {
        setAgentSearch(agentSearchInput)
        setPage(1)
      }
    }, 400)
    return () => clearTimeout(timer)
  }, [agentSearchInput])

  function handleFilterChange(setter: (v: string) => void, value: string) {
    setter(value)
    setPage(1)
  }

  async function handleApprove(id: number) {
    setApproving(id)
    try {
      await apiClient.post(`/commission/ledger/${id}/approve`)
      loadLedger()
    } catch {
      setError('Failed to approve entry.')
    } finally {
      setApproving(null)
    }
  }

  async function handleBulkApprove() {
    if (selected.size === 0) return
    setBulkApproving(true)
    try {
      await apiClient.post('/commission/ledger/bulk-approve', { ids: Array.from(selected) })
      loadLedger()
    } catch {
      setError('Failed to bulk approve entries.')
    } finally {
      setBulkApproving(false)
    }
  }

  function toggleSelect(id: number) {
    const next = new Set(selected)
    if (next.has(id)) next.delete(id)
    else next.add(id)
    setSelected(next)
  }

  function toggleSelectAll() {
    const pendingIds = entries.filter((e) => e.status === 'pending').map((e) => e.id)
    if (pendingIds.every((id) => selected.has(id))) {
      const next = new Set(selected)
      pendingIds.forEach((id) => next.delete(id))
      setSelected(next)
    } else {
      const next = new Set(selected)
      pendingIds.forEach((id) => next.add(id))
      setSelected(next)
    }
  }

  function goToPage(p: number) {
    setPage(p)
  }

  const currentPage = meta?.current_page ?? 1
  const lastPage = meta?.last_page ?? 1

  function getPageNumbers() {
    const pages: (number | '...')[] = []
    if (lastPage <= 7) {
      for (let i = 1; i <= lastPage; i++) pages.push(i)
    } else {
      pages.push(1)
      if (currentPage > 3) pages.push('...')
      for (let i = Math.max(2, currentPage - 1); i <= Math.min(lastPage - 1, currentPage + 1); i++) pages.push(i)
      if (currentPage < lastPage - 2) pages.push('...')
      pages.push(lastPage)
    }
    return pages
  }

  const pendingOnPage = entries.filter((e) => e.status === 'pending')
  const allPendingSelected = pendingOnPage.length > 0 && pendingOnPage.every((e) => selected.has(e.id))

  const summaryCards = summary
    ? [
        { label: 'Total Earned', value: fmtPula(summary.total_earned), bg: 'bg-green-50', border: 'border-green-200', text: 'text-green-700' },
        { label: 'Total Clawback', value: fmtPula(summary.total_clawback), bg: 'bg-red-50', border: 'border-red-200', text: 'text-red-700' },
        { label: 'Total Pending', value: fmtPula(summary.total_pending), bg: 'bg-yellow-50', border: 'border-yellow-200', text: 'text-yellow-700' },
        { label: 'Total Paid', value: fmtPula(summary.total_paid), bg: 'bg-blue-50', border: 'border-blue-200', text: 'text-blue-700' },
      ]
    : []

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-800">Commission Ledger</h1>
        {selected.size > 0 && (
          <button
            onClick={handleBulkApprove}
            disabled={bulkApproving}
            className="px-4 py-2 bg-green-600 text-white rounded-md text-sm font-medium hover:bg-green-700 disabled:opacity-50 transition"
          >
            {bulkApproving ? 'Approving...' : `Approve Selected (${selected.size})`}
          </button>
        )}
      </div>

      {error && (
        <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-2 rounded-md text-sm flex items-center gap-3">
          <span className="flex-1">{error}</span>
          <button onClick={loadLedger} className="px-2.5 py-1 bg-red-600 text-white rounded text-xs font-medium hover:bg-red-700">Try again</button>
          <button onClick={() => setError('')} className="text-red-500 hover:text-red-700">&times;</button>
        </div>
      )}

      {/* Summary Strip */}
      {summary && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          {summaryCards.map((card) => (
            <div key={card.label} className={`rounded-lg border p-4 ${card.bg} ${card.border}`}>
              <p className="text-sm text-gray-500">{card.label}</p>
              <p className={`text-xl font-bold mt-1 ${card.text}`}>{card.value}</p>
            </div>
          ))}
        </div>
      )}

      {/* Filter Bar */}
      <div className="flex flex-wrap gap-3 items-end">
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Agent</label>
          <input
            type="text"
            placeholder="Search agent..."
            value={agentSearchInput}
            onChange={(e) => setAgentSearchInput(e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm w-48 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Status</label>
          <select
            value={statusFilter}
            onChange={(e) => handleFilterChange(setStatusFilter, e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500"
          >
            <option value="">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="paid">Paid</option>
            <option value="held">Held</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Entry Type</label>
          <select
            value={entryTypeFilter}
            onChange={(e) => handleFilterChange(setEntryTypeFilter, e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500"
          >
            <option value="">All Types</option>
            <option value="earned">Earned</option>
            <option value="clawback">Clawback</option>
            <option value="bonus">Bonus</option>
            <option value="adjustment">Adjustment</option>
          </select>
        </div>
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Date From</label>
          <input
            type="date"
            value={dateFrom}
            onChange={(e) => handleFilterChange(setDateFrom, e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500"
          />
        </div>
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Date To</label>
          <input
            type="date"
            value={dateTo}
            onChange={(e) => handleFilterChange(setDateTo, e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500"
          />
        </div>
      </div>

      {/* Table */}
      <div className="bg-white rounded-lg shadow-sm border overflow-hidden relative">
        {loading && (
          <div className="absolute inset-0 bg-white/50 z-10 flex items-center justify-center">
            <LoadingSpinner size="md" />
          </div>
        )}
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-600 uppercase text-xs tracking-wider">
              <tr>
                <th className="px-4 py-3 text-left">
                  <input
                    type="checkbox"
                    checked={allPendingSelected}
                    onChange={toggleSelectAll}
                    className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    title="Select all pending"
                  />
                </th>
                <th className="px-4 py-3 text-left">Policy #</th>
                <th className="px-4 py-3 text-left">Agent</th>
                <th className="px-4 py-3 text-left">Entry Type</th>
                <th className="px-4 py-3 text-right">Amount</th>
                <th className="px-4 py-3 text-right">Premium</th>
                <th className="px-4 py-3 text-left">Commission Type</th>
                <th className="px-4 py-3 text-left">Status</th>
                <th className="px-4 py-3 text-left">Qualifying Date</th>
                <th className="px-4 py-3 text-left">Cooling End</th>
                <th className="px-4 py-3 text-center">KYC</th>
                <th className="px-4 py-3 text-center">Payments</th>
                <th className="px-4 py-3 text-left">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {!loading && entries.length === 0 ? (
                <tr>
                  <td colSpan={13} className="px-4 py-14 text-center">
                    <div className="flex flex-col items-center gap-3">
                      <div className="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center">
                        <svg className="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                      </div>
                      <p className="text-sm text-gray-400">No ledger entries match the current filters.</p>
                    </div>
                  </td>
                </tr>
              ) : (
                entries.map((entry) => (
                  <tr key={entry.id} className="hover:bg-gray-50 transition">
                    <td className="px-4 py-2">
                      {entry.status === 'pending' ? (
                        <input
                          type="checkbox"
                          checked={selected.has(entry.id)}
                          onChange={() => toggleSelect(entry.id)}
                          className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        />
                      ) : (
                        <span className="text-gray-200">-</span>
                      )}
                    </td>
                    <td className="px-4 py-2 font-medium text-blue-600">{entry.policy_number}</td>
                    <td className="px-4 py-2 text-gray-700">{entry.agent_name}</td>
                    <td className="px-4 py-2">
                      <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${ENTRY_TYPE_BADGE[entry.entry_type] ?? 'bg-gray-100 text-gray-600'}`}>
                        {titleCase(entry.entry_type)}
                      </span>
                    </td>
                    <td className={`px-4 py-2 text-right font-mono font-medium ${entry.amount < 0 ? 'text-red-600' : 'text-gray-800'}`}>
                      {fmtPula(entry.amount)}
                    </td>
                    <td className="px-4 py-2 text-right font-mono text-gray-600">
                      {fmtPula(entry.premium)}
                    </td>
                    <td className="px-4 py-2 text-gray-600">{entry.commission_type}</td>
                    <td className="px-4 py-2">
                      {entry.status
                        ? <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_BADGE[entry.status] ?? 'bg-gray-100 text-gray-600'}`}>
                            {titleCase(entry.status)}
                          </span>
                        : <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500 italic">Unknown</span>}
                    </td>
                    <td className="px-4 py-2 text-xs text-gray-500">{fmtDate(entry.qualifying_date)}</td>
                    <td className="px-4 py-2 text-xs text-gray-500">{fmtDate(entry.cooling_end)}</td>
                    <td className="px-4 py-2 text-center">
                      {entry.kyc_met ? (
                        <svg className="w-4 h-4 text-green-500 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                      ) : (
                        <svg className="w-4 h-4 text-red-400 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                      )}
                    </td>
                    <td className="px-4 py-2 text-center">
                      {entry.payments_met ? (
                        <svg className="w-4 h-4 text-green-500 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                      ) : (
                        <svg className="w-4 h-4 text-red-400 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                      )}
                    </td>
                    <td className="px-4 py-2">
                      {entry.status === 'pending' && (
                        <button
                          onClick={() => handleApprove(entry.id)}
                          disabled={approving === entry.id}
                          className="px-2 py-1 text-xs bg-green-50 text-green-600 rounded hover:bg-green-100 disabled:opacity-50"
                        >
                          {approving === entry.id ? 'Approving...' : 'Approve'}
                        </button>
                      )}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {meta && meta.last_page > 1 && (
          <div className="flex items-center justify-between px-4 py-3 border-t bg-gray-50 text-sm">
            <span className="text-gray-500">
              Showing {meta.from}&ndash;{meta.to} of {meta.total}
            </span>
            <div className="flex items-center gap-1">
              <button
                disabled={currentPage === 1}
                onClick={() => goToPage(currentPage - 1)}
                className="px-2.5 py-1 rounded border text-gray-600 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed"
              >
                Prev
              </button>
              {getPageNumbers().map((p, i) =>
                p === '...' ? (
                  <span key={`e${i}`} className="px-1.5 text-gray-400">...</span>
                ) : (
                  <button
                    key={p}
                    onClick={() => goToPage(p as number)}
                    className={`px-2.5 py-1 rounded border text-sm ${
                      p === currentPage ? 'bg-blue-600 text-white border-blue-600' : 'text-gray-600 hover:bg-gray-100'
                    }`}
                  >
                    {p}
                  </button>
                )
              )}
              <button
                disabled={currentPage === lastPage}
                onClick={() => goToPage(currentPage + 1)}
                className="px-2.5 py-1 rounded border text-gray-600 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed"
              >
                Next
              </button>
              <div className="flex items-center gap-1 ml-3 border-l pl-3">
                <span className="text-gray-500 text-xs">Go to</span>
                <input
                  type="number"
                  min={1}
                  max={lastPage}
                  value={jumpPage}
                  onChange={(e) => setJumpPage(e.target.value)}
                  onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                      const p = Number(jumpPage)
                      if (p >= 1 && p <= lastPage) { goToPage(p); setJumpPage('') }
                    }
                  }}
                  className="w-14 px-2 py-1 border rounded text-sm text-center"
                  placeholder="#"
                />
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
