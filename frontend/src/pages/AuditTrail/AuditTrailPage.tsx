import { useState, useEffect, useCallback } from 'react'
import { useSearchParams } from 'react-router-dom'
import apiClient from '../../api/client'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import DualScrollTable from '../../components/common/DualScrollTable'
import EmptyState from '../../components/common/EmptyState'
import { reportErrorToTeam } from '../../utils/reportError'

interface AuditEntry {
  id: number
  date: string
  user: string
  causer_id: number | null
  action: string
  subject_type: string
  subject_id: number | null
  details: string
  ip_address: string | null
}

interface Meta {
  total: number
  per_page: number
  current_page: number
  last_page: number
}

interface AuditResponse {
  data: AuditEntry[]
  meta: Meta
  filters: {
    users: { id: number; name: string }[]
    log_names: string[]
  }
}

const ACTION_BADGE: Record<string, string> = {
  created: 'bg-green-100 text-green-700',
  updated: 'bg-blue-100 text-blue-700',
  deleted: 'bg-red-100 text-red-700',
  login:   'bg-purple-100 text-purple-700',
  export:  'bg-yellow-100 text-yellow-700',
}

export default function AuditTrailPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [jumpPage, setJumpPage] = useState('')

  const [entries, setEntries] = useState<AuditEntry[]>([])
  const [meta, setMeta] = useState<Meta | null>(null)
  const [loading, setLoading] = useState(true)
  const [fetching, setFetching] = useState(false)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [userOptions, setUserOptions] = useState<{ id: number; name: string }[]>([])
  const [logNameOptions, setLogNameOptions] = useState<string[]>([])

  const search = searchParams.get('search') || ''
  const dateFrom = searchParams.get('date_from') || ''
  const dateTo = searchParams.get('date_to') || ''
  const userId = searchParams.get('user') || ''
  const actionType = searchParams.get('action') || ''
  const page = Number(searchParams.get('page') || '1')

  const fetchAuditTrail = useCallback(async () => {
    setFetching(true)
    setLoadError(null)
    try {
      const params: Record<string, string | number> = { per_page: 50, page }
      if (search) params.search = search
      if (dateFrom) params.date_from = dateFrom
      if (dateTo) params.date_to = dateTo
      if (userId) params.user = userId
      if (actionType) params.action = actionType
      const res = await apiClient.get<AuditResponse>('/audit-trail', { params })
      setEntries(res.data.data)
      setMeta(res.data.meta)
      if (res.data.filters) {
        if (res.data.filters.users) setUserOptions(res.data.filters.users)
        if (res.data.filters.log_names) setLogNameOptions(res.data.filters.log_names)
      }
    } catch (err: any) {
      // UAT 2026-05-26 (Prathap BUG-019 / BUG-020): silent catch{} was hiding
      // backend failures behind an empty results table — operators couldn't
      // tell if the audit trail was empty or the API was down. Surface the
      // error to the user AND report to developers@ so silent failures don't
      // go uninvestigated.
      const message = err?.response?.data?.message ?? err?.message ?? 'Failed to load audit trail.'
      setEntries([])
      setMeta(null)
      setLoadError(message)
      reportErrorToTeam({
        error: message,
        stack: err?.stack,
        context: 'AuditTrailPage:fetchAuditTrail',
      })
    } finally {
      setLoading(false)
      setFetching(false)
    }
  }, [search, dateFrom, dateTo, userId, actionType, page])

  useEffect(() => {
    fetchAuditTrail()
  }, [fetchAuditTrail])

  function updateFilter(key: string, value: string) {
    const next = new URLSearchParams(searchParams)
    if (value) next.set(key, value)
    else next.delete(key)
    next.delete('page')
    setSearchParams(next)
  }

  function goToPage(p: number) {
    const next = new URLSearchParams(searchParams)
    next.set('page', String(p))
    setSearchParams(next)
  }

  const currentPage = meta?.current_page ?? 1
  const lastPage = meta?.last_page ?? 1

  function getPageNumbers(): (number | '...')[] {
    if (lastPage <= 7) return Array.from({ length: lastPage }, (_, i) => i + 1)
    const pages: (number | '...')[] = []
    const addPage = (p: number) => { if (!pages.includes(p)) pages.push(p) }
    addPage(1)
    if (currentPage > 3) pages.push('...')
    for (let i = Math.max(2, currentPage - 1); i <= Math.min(lastPage - 1, currentPage + 1); i++) addPage(i)
    if (currentPage < lastPage - 2) pages.push('...')
    addPage(lastPage)
    return pages
  }

  function formatDate(dateStr: string | null): string {
    if (!dateStr) return '—'
    const d = new Date(dateStr)
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) +
      ' ' + d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
  }

  /** Turn "App\\Policy" into "Policy" */
  function shortModel(subjectType: string): string {
    if (!subjectType) return '—'
    const parts = subjectType.split('\\')
    return parts[parts.length - 1]
  }

  return (
    <div className="p-6 space-y-4">
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <h1 className="text-2xl font-bold text-gray-800">Audit Trail</h1>
          {meta && (
            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
              {meta.total.toLocaleString()}
            </span>
          )}
        </div>
      </div>

      {/* Filters */}
      <div className="bg-white rounded-lg border border-gray-200 p-4">
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
          <div>
            <label className="block text-xs font-medium text-gray-500 mb-1">Search</label>
            <input
              type="text"
              placeholder="Search description..."
              defaultValue={search}
              onKeyDown={(e) => {
                if (e.key === 'Enter') updateFilter('search', (e.target as HTMLInputElement).value)
              }}
              className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none"
            />
          </div>
          <div>
            <label className="block text-xs font-medium text-gray-500 mb-1">Date From</label>
            <input
              type="date"
              value={dateFrom}
              onChange={(e) => updateFilter('date_from', e.target.value)}
              className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none"
            />
          </div>
          <div>
            <label className="block text-xs font-medium text-gray-500 mb-1">Date To</label>
            <input
              type="date"
              value={dateTo}
              onChange={(e) => updateFilter('date_to', e.target.value)}
              className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none"
            />
          </div>
          <div>
            <label className="block text-xs font-medium text-gray-500 mb-1">User</label>
            <select
              value={userId}
              onChange={(e) => updateFilter('user', e.target.value)}
              className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none bg-white"
            >
              <option value="">All Users</option>
              {userOptions.map((u) => (
                <option key={u.id} value={u.id}>{u.name}</option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-xs font-medium text-gray-500 mb-1">Action Type</label>
            <select
              value={actionType}
              onChange={(e) => updateFilter('action', e.target.value)}
              className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none bg-white"
            >
              <option value="">All Actions</option>
              {logNameOptions.map((ln) => (
                <option key={ln} value={ln}>{ln}</option>
              ))}
            </select>
          </div>
        </div>
      </div>

      {/* Table */}
      {loading ? (
        <div className="bg-white rounded-lg border border-gray-200 shadow-sm p-12">
          <LoadingSpinner size="lg" className="mb-3" />
          <p className="text-center text-sm text-gray-400">Loading audit trail...</p>
        </div>
      ) : loadError ? (
        <div className="bg-red-50 border border-red-200 rounded-lg p-6 space-y-3">
          <p className="text-sm font-medium text-red-700">Failed to load audit trail.</p>
          <p className="text-xs text-red-600">{loadError}</p>
          <button onClick={fetchAuditTrail} className="px-3 py-1.5 bg-red-600 text-white rounded text-sm hover:bg-red-700">Try again</button>
        </div>
      ) : (
        <>
          {fetching && (
            <div className="bg-white rounded-lg border border-blue-200 shadow-sm px-4 py-3 flex items-center gap-2">
              <LoadingSpinner size="sm" />
              <span className="text-sm text-blue-600">Updating results...</span>
            </div>
          )}

          <div className={`bg-white rounded-lg border border-gray-200 shadow-sm transition-opacity ${fetching ? 'opacity-50 pointer-events-none' : ''}`}>
            <DualScrollTable>
              <table className="min-w-full text-sm">
                <thead className="bg-gray-50 text-gray-500 uppercase text-xs">
                  <tr>
                    <th className="px-4 py-2 text-left whitespace-nowrap">Date</th>
                    <th className="px-4 py-2 text-left">User</th>
                    <th className="px-4 py-2 text-left">Action</th>
                    <th className="px-4 py-2 text-left">Subject</th>
                    <th className="px-4 py-2 text-left">Record ID</th>
                    <th className="px-4 py-2 text-left">Details</th>
                    <th className="px-4 py-2 text-left">IP Address</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                  {entries.map((e) => {
                    const badgeClass = ACTION_BADGE[e.action.toLowerCase()] ?? 'bg-gray-100 text-gray-600'
                    return (
                      <tr key={e.id} className="hover:bg-gray-50">
                        <td className="px-4 py-2 text-gray-500 whitespace-nowrap">{formatDate(e.date)}</td>
                        <td className="px-4 py-2 text-gray-800 font-medium">{e.user || '—'}</td>
                        <td className="px-4 py-2">
                          <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${badgeClass}`}>
                            {e.action}
                          </span>
                        </td>
                        <td className="px-4 py-2 text-gray-600">{shortModel(e.subject_type)}</td>
                        <td className="px-4 py-2 text-gray-500 font-mono">{e.subject_id ?? '—'}</td>
                        <td className="px-4 py-2 text-gray-600 max-w-[300px] truncate" title={e.details}>{e.details || '—'}</td>
                        <td className="px-4 py-2 text-gray-400 font-mono text-xs">{e.ip_address || '—'}</td>
                      </tr>
                    )
                  })}
                  {entries.length === 0 && (
                    <tr>
                      <td colSpan={7} className="p-0">
                        <EmptyState title="No audit entries found" description="Try adjusting your filters or search criteria." />
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </DualScrollTable>
          </div>

          {/* Pagination */}
          {meta && meta.last_page > 1 && (
            <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-gray-500">
              <span>
                Page {currentPage} of {lastPage.toLocaleString()} ({meta.total.toLocaleString()} total)
              </span>
              <div className="flex items-center gap-1">
                <button
                  disabled={currentPage === 1}
                  onClick={() => goToPage(currentPage - 1)}
                  className="px-2.5 py-1 border rounded disabled:opacity-40 hover:bg-gray-50 text-xs"
                >
                  Prev
                </button>
                {getPageNumbers().map((p, i) =>
                  p === '...' ? (
                    <span key={`e${i}`} className="px-1.5 text-gray-400">...</span>
                  ) : (
                    <button
                      key={p}
                      onClick={() => goToPage(p)}
                      className={`px-2.5 py-1 border rounded text-xs ${
                        p === currentPage
                          ? 'bg-brand-navy text-white border-brand-navy'
                          : 'hover:bg-gray-50'
                      }`}
                    >
                      {p}
                    </button>
                  )
                )}
                <button
                  disabled={currentPage === lastPage}
                  onClick={() => goToPage(currentPage + 1)}
                  className="px-2.5 py-1 border rounded disabled:opacity-40 hover:bg-gray-50 text-xs"
                >
                  Next
                </button>
                <span className="ml-3 text-xs text-gray-400">Go to</span>
                <input
                  type="text"
                  value={jumpPage}
                  onChange={(e) => setJumpPage(e.target.value.replace(/\D/g, ''))}
                  onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                      const pg = Number(jumpPage)
                      if (pg >= 1 && pg <= lastPage) {
                        goToPage(pg)
                        setJumpPage('')
                      }
                    }
                  }}
                  placeholder={String(currentPage)}
                  className="w-14 border rounded px-2 py-1 text-xs text-center focus:ring-1 focus:ring-brand-navy/30 focus:outline-none"
                />
              </div>
            </div>
          )}
        </>
      )}
    </div>
  )
}
