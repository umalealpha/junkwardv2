import { useState, useEffect, useCallback } from 'react'
import apiClient from '../../api/client'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import Modal from '../../components/common/Modal'
import { fmtDate } from '../../utils/format'
import { reportErrorToTeam } from '../../utils/reportError'

interface FraudAlert {
  id: number
  created_at: string
  agent_name: string
  alert_type: string
  severity: 'low' | 'medium' | 'high' | 'critical'
  policy_number: string
  details: string
  details_json: Record<string, any> | null
  status: 'open' | 'reviewing' | 'resolved' | 'dismissed'
  resolution_note: string | null
}

interface PaginationMeta {
  current_page: number
  last_page: number
  from: number
  to: number
  total: number
}

const SEVERITY_BADGE: Record<string, string> = {
  low: 'bg-gray-100 text-gray-600',
  medium: 'bg-yellow-100 text-yellow-700',
  high: 'bg-orange-100 text-orange-700',
  critical: 'bg-red-100 text-red-700',
}

const STATUS_BADGE: Record<string, string> = {
  open: 'bg-red-100 text-red-700',
  reviewing: 'bg-blue-100 text-blue-700',
  resolved: 'bg-green-100 text-green-700',
  dismissed: 'bg-gray-100 text-gray-500',
}

const ALERT_TYPE_BADGE: Record<string, string> = {
  rapid_policy_creation: 'bg-purple-100 text-purple-700',
  self_referral: 'bg-pink-100 text-pink-700',
  unusual_cancellation: 'bg-orange-100 text-orange-700',
  duplicate_customer: 'bg-cyan-100 text-cyan-700',
  premium_manipulation: 'bg-red-100 text-red-700',
  ghost_policy: 'bg-amber-100 text-amber-700',
  churning: 'bg-rose-100 text-rose-700',
}

const ALERT_TYPES = [
  'rapid_policy_creation',
  'self_referral',
  'unusual_cancellation',
  'duplicate_customer',
  'premium_manipulation',
  'ghost_policy',
  'churning',
]

function titleCase(str: string): string {
  return str
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase())
}

export default function CommissionFraudPage() {
  const [alerts, setAlerts] = useState<FraudAlert[]>([])
  const [meta, setMeta] = useState<PaginationMeta | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  // Filters
  const [statusFilter, setStatusFilter] = useState('')
  const [severityFilter, setSeverityFilter] = useState('')
  const [alertTypeFilter, setAlertTypeFilter] = useState('')
  const [agentSearchInput, setAgentSearchInput] = useState('')
  const [agentSearch, setAgentSearch] = useState('')
  const [page, setPage] = useState(1)
  const [jumpPage, setJumpPage] = useState('')

  // Review modal
  const [reviewAlert, setReviewAlert] = useState<FraudAlert | null>(null)
  const [reviewStatus, setReviewStatus] = useState('')
  const [reviewNote, setReviewNote] = useState('')
  const [reviewSaving, setReviewSaving] = useState(false)

  const loadAlerts = useCallback(() => {
    setLoading(true)
    const params: Record<string, any> = { page, per_page: 25 }
    if (statusFilter) params.status = statusFilter
    if (severityFilter) params.severity = severityFilter
    if (alertTypeFilter) params.alert_type = alertTypeFilter
    if (agentSearch) params.agent = agentSearch

    setError('')
    apiClient
      .get('/commission/fraud-alerts', { params })
      .then((r) => {
        setAlerts(r.data.data ?? [])
        setMeta(r.data.meta ?? null)
      })
      .catch((err: any) => {
        const message = err?.response?.data?.message ?? err?.message ?? 'Failed to load fraud alerts.'
        setError(message)
        reportErrorToTeam({ error: message, stack: err?.stack, context: 'CommissionFraudPage:loadAlerts' })
      })
      .finally(() => setLoading(false))
  }, [page, statusFilter, severityFilter, alertTypeFilter, agentSearch])

  useEffect(() => {
    loadAlerts()
  }, [loadAlerts])

  // Debounced agent search
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

  function openReview(alert: FraudAlert) {
    setReviewAlert(alert)
    setReviewStatus(alert.status)
    setReviewNote(alert.resolution_note ?? '')
  }

  async function handleReviewSave() {
    if (!reviewAlert) return
    setReviewSaving(true)
    try {
      await apiClient.post(`/commission/fraud-alerts/${reviewAlert.id}/review`, {
        status: reviewStatus,
        resolution_note: reviewNote,
      })
      setReviewAlert(null)
      setReviewNote('')
      setReviewStatus('')
      loadAlerts()
    } catch {
      setError('Failed to save review.')
    } finally {
      setReviewSaving(false)
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

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-800">Commission Fraud Alerts</h1>
      </div>

      {error && (
        <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-2 rounded-md text-sm flex items-center gap-3">
          <span className="flex-1">{error}</span>
          <button onClick={loadAlerts} className="px-2.5 py-1 bg-red-600 text-white rounded text-xs font-medium hover:bg-red-700">Try again</button>
          <button onClick={() => setError('')} className="text-red-500 hover:text-red-700">&times;</button>
        </div>
      )}

      {/* Review Modal */}
      <Modal
        open={reviewAlert !== null}
        onClose={() => setReviewAlert(null)}
        title={`Review Alert #${reviewAlert?.id ?? ''}`}
        footer={
          <>
            <button
              onClick={() => setReviewAlert(null)}
              className="px-4 py-2 text-sm border rounded-md text-gray-600 hover:bg-gray-50"
            >
              Cancel
            </button>
            <button
              onClick={handleReviewSave}
              disabled={reviewSaving}
              className="px-4 py-2 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {reviewSaving ? 'Saving...' : 'Save Review'}
            </button>
          </>
        }
      >
        {reviewAlert && (
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div>
                <p className="text-xs text-gray-500">Agent</p>
                <p className="font-medium text-gray-800">{reviewAlert.agent_name}</p>
              </div>
              <div>
                <p className="text-xs text-gray-500">Policy</p>
                <p className="font-medium text-blue-600">{reviewAlert.policy_number}</p>
              </div>
              <div>
                <p className="text-xs text-gray-500">Alert Type</p>
                <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${ALERT_TYPE_BADGE[reviewAlert.alert_type] ?? 'bg-gray-100 text-gray-600'}`}>
                  {titleCase(reviewAlert.alert_type)}
                </span>
              </div>
              <div>
                <p className="text-xs text-gray-500">Severity</p>
                <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${SEVERITY_BADGE[reviewAlert.severity]}`}>
                  {titleCase(reviewAlert.severity)}
                </span>
              </div>
              <div>
                <p className="text-xs text-gray-500">Date</p>
                <p className="text-gray-700">{fmtDate(reviewAlert.created_at)}</p>
              </div>
            </div>

            <div>
              <p className="text-xs text-gray-500 mb-1">Details</p>
              <p className="text-sm text-gray-700 bg-gray-50 rounded-md p-3 border border-gray-200">
                {reviewAlert.details}
              </p>
            </div>

            {reviewAlert.details_json && (
              <div>
                <p className="text-xs text-gray-500 mb-1">Details (JSON)</p>
                <pre className="text-xs text-gray-600 bg-gray-50 rounded-md p-3 border border-gray-200 overflow-x-auto max-h-40">
                  {JSON.stringify(reviewAlert.details_json, null, 2)}
                </pre>
              </div>
            )}

            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Status</label>
              <select
                value={reviewStatus}
                onChange={(e) => setReviewStatus(e.target.value)}
                className="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="open">Open</option>
                <option value="reviewing">Reviewing</option>
                <option value="resolved">Resolved</option>
                <option value="dismissed">Dismissed</option>
              </select>
            </div>

            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Resolution Note</label>
              <textarea
                value={reviewNote}
                onChange={(e) => setReviewNote(e.target.value)}
                rows={3}
                className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                placeholder="Enter resolution notes..."
              />
            </div>
          </div>
        )}
      </Modal>

      {/* Filter Bar */}
      <div className="flex flex-wrap gap-3 items-end">
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Status</label>
          <select
            value={statusFilter}
            onChange={(e) => handleFilterChange(setStatusFilter, e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500"
          >
            <option value="">All Statuses</option>
            <option value="open">Open</option>
            <option value="reviewing">Reviewing</option>
            <option value="resolved">Resolved</option>
            <option value="dismissed">Dismissed</option>
          </select>
        </div>
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Severity</label>
          <select
            value={severityFilter}
            onChange={(e) => handleFilterChange(setSeverityFilter, e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500"
          >
            <option value="">All Severities</option>
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
            <option value="critical">Critical</option>
          </select>
        </div>
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Alert Type</label>
          <select
            value={alertTypeFilter}
            onChange={(e) => handleFilterChange(setAlertTypeFilter, e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500"
          >
            <option value="">All Types</option>
            {ALERT_TYPES.map((t) => (
              <option key={t} value={t}>{titleCase(t)}</option>
            ))}
          </select>
        </div>
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
                <th className="px-4 py-3 text-left">Date</th>
                <th className="px-4 py-3 text-left">Agent</th>
                <th className="px-4 py-3 text-left">Alert Type</th>
                <th className="px-4 py-3 text-left">Severity</th>
                <th className="px-4 py-3 text-left">Policy #</th>
                <th className="px-4 py-3 text-left">Details</th>
                <th className="px-4 py-3 text-left">Status</th>
                <th className="px-4 py-3 text-left">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {!loading && alerts.length === 0 ? (
                <tr>
                  <td colSpan={8} className="px-4 py-14 text-center">
                    <div className="flex flex-col items-center gap-3">
                      <div className="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center">
                        <svg className="w-8 h-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                      </div>
                      <p className="text-base font-semibold text-gray-700">No Fraud Alerts</p>
                      <p className="text-sm text-gray-400">No alerts match the current filters.</p>
                    </div>
                  </td>
                </tr>
              ) : (
                alerts.map((alert) => (
                  <tr key={alert.id} className="hover:bg-gray-50 transition">
                    <td className="px-4 py-2 text-xs text-gray-500">{fmtDate(alert.created_at)}</td>
                    <td className="px-4 py-2 text-gray-700 font-medium">{alert.agent_name}</td>
                    <td className="px-4 py-2">
                      <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${ALERT_TYPE_BADGE[alert.alert_type] ?? 'bg-gray-100 text-gray-600'}`}>
                        {titleCase(alert.alert_type)}
                      </span>
                    </td>
                    <td className="px-4 py-2">
                      <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${SEVERITY_BADGE[alert.severity]}`}>
                        {titleCase(alert.severity)}
                      </span>
                    </td>
                    <td className="px-4 py-2 font-medium text-blue-600">{alert.policy_number}</td>
                    <td className="px-4 py-2 truncate max-w-[200px] text-gray-600" title={alert.details}>
                      {alert.details}
                    </td>
                    <td className="px-4 py-2">
                      <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_BADGE[alert.status] ?? 'bg-gray-100 text-gray-600'}`}>
                        {titleCase(alert.status)}
                      </span>
                    </td>
                    <td className="px-4 py-2">
                      <button
                        onClick={() => openReview(alert)}
                        className="px-2 py-1 text-xs bg-blue-50 text-blue-600 rounded hover:bg-blue-100"
                      >
                        Review
                      </button>
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
