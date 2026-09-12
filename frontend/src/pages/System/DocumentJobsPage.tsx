import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import apiClient from '../../api/client'
import { getStoredRoles } from '../../api/auth'
import EmptyState from '../../components/common/EmptyState'

// Restart-queue control is Admin / Super Admin only — mirrors the backend
// SystemDiagnosticsController::isAdmin() gate (hasRole('admin') ||
// hasRole('Super Admin')). FE check is for UI affordance only; the backend
// re-checks and returns 403 regardless.
const ADMIN_ROLES = ['admin', 'super admin', 'superadmin']
function isAdminUser(): boolean {
  return getStoredRoles().some(r => ADMIN_ROLES.includes(String(r).trim().toLowerCase()))
}

interface DocJob {
  id: number
  policy_id: number
  policy_number: string
  action_id: number | null
  status: string
  file_name: string | null
  message: string | null
  requested_by: string
  created_at: string
  time_ago: string
  error_log_id: number | null
  is_stuck?: boolean
}

interface DocStats {
  total: number
  queued: number
  processing: number
  completed: number
  failed: number
  cancelled: number
  stuck: number
}

const STATUS_OPTIONS: { value: string; label: string }[] = [
  { value: '',                                    label: 'All statuses' },
  { value: 'queued,queued_long',                  label: 'Queued' },
  { value: 'processing',                          label: 'Processing' },
  { value: 'completed',                           label: 'Completed' },
  { value: 'failed',                              label: 'Failed' },
  { value: 'cancelled',                           label: 'Cancelled' },
]

export default function DocumentJobsPage() {
  const [jobs, setJobs] = useState<DocJob[]>([])
  const [stats, setStats] = useState<DocStats | null>(null)
  const [loading, setLoading] = useState(true)
  const [statusFilter, setStatusFilter] = useState('')
  const [searchInput, setSearchInput] = useState('')
  const [search, setSearch] = useState('')
  const [limit, setLimit] = useState(100)
  const [restarting, setRestarting] = useState(false)
  const canRestart = isAdminUser()

  const loadJobs = async () => {
    try {
      const params: Record<string, string | number> = { limit }
      if (statusFilter) params.status = statusFilter
      if (search) params.search = search
      const { data } = await apiClient.get('/document-jobs', { params })
      setJobs(data.data ?? [])
      setStats(data.stats ?? null)
    } catch { setJobs([]); setStats(null) }
    setLoading(false)
  }

  useEffect(() => {
    loadJobs()
    const interval = setInterval(loadJobs, 30000) // refresh every 30s
    return () => clearInterval(interval)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [statusFilter, search, limit])

  // Quick-filter helper: click a stats card → filter the list by that status.
  const quickFilter = (status: string) => {
    setStatusFilter(status); setLoading(true)
  }

  // Inline error-log modal. Opening /admin/api-error-log/{id} in a new tab
  // hit the backend web login because the React app sits on a different
  // origin and the session cookie didn't carry. Fetch the row via the new
  // Sanctum-authed /document-jobs/error-log/{id} endpoint and show it in
  // place instead.
  const [errorLog, setErrorLog] = useState<any | null>(null)
  const [errorLogLoading, setErrorLogLoading] = useState(false)
  const openErrorLog = async (errorLogId: number) => {
    setErrorLogLoading(true); setErrorLog({ id: errorLogId })
    try {
      const { data } = await apiClient.get(`/document-jobs/error-log/${errorLogId}`)
      setErrorLog(data?.data ?? { id: errorLogId, error: 'No data' })
    } catch (e: any) {
      setErrorLog({ id: errorLogId, error: e?.response?.data?.error || 'Fetch failed' })
    }
    setErrorLogLoading(false)
  }

  const statusBadge = (status: string) => {
    switch (status) {
      case 'completed': return <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Completed</span>
      case 'processing': return <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700 animate-pulse">Processing</span>
      case 'queued': return <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">Queued</span>
      case 'queued_long': return <span title="Large policy — processed by minutely cron (5-15 min)" className="px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Queued (large)</span>
      case 'failed': return <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Failed</span>
      case 'cancelled': return <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Cancelled</span>
      default: return <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">{status}</span>
    }
  }

  // Restart queue workers + clear stuck scheduler locks. Recovery for
  // "Queued (large)" jobs stalled because the minutely cron's schedule
  // lock is wedged (the worker/cron run in separate ECS services we have
  // no shell access to — this triggers the same artisan commands over the
  // shared Redis from the backend container).
  const restartQueue = async () => {
    if (!confirm(
      'Restart workers, clear stuck scheduler locks, and generate the pending PDFs now?\n\n' +
      'This drains the stuck "Queued" jobs directly on the server — no cron needed. ' +
      'It is safe and does not delete any jobs. Large policies can take a few minutes; ' +
      'if it appears to time out, just wait and click Refresh — generation continues in the background.'
    )) return
    setRestarting(true)
    try {
      // Direct PDF generation can run for minutes on big policies — give the
      // request a generous timeout. Even if it still times out, the server
      // keeps generating (ignore_user_abort) and Refresh will show results.
      const { data } = await apiClient.post('/system/restart-queue', {}, { timeout: 180000 })
      const lines = Object.entries(data?.results ?? {}).map(
        ([cmd, r]: [string, any]) => `${r?.success ? '✅' : '❌'} ${cmd}${r?.error ? ` — ${r.error}` : ''}`
      )
      const drain = (data?.results ?? {})['pdf:process-pending']?.output
      alert(
        (data?.success ? 'Done — workers restarted, locks cleared, pending PDFs processed.' : 'Completed with errors:') +
        '\n\n' + lines.join('\n') +
        (drain ? `\n\nProcessor output:\n${drain}` : '') +
        '\n\nClick Refresh to see updated statuses.'
      )
      loadJobs()
    } catch (e: any) {
      if (e?.response?.status === 403) {
        alert('You do not have permission to run this (Admin / Super Admin only).')
      } else if (e?.code === 'ECONNABORTED' || !e?.response) {
        // Timed out / connection dropped — server is still generating thanks to
        // ignore_user_abort. Don't present it as a hard failure.
        alert('Still working… large policies take a few minutes. The server is generating the PDFs in the background — wait a moment, then click Refresh to see them complete.')
      } else {
        alert(e?.response?.data?.error || 'Restart failed.')
      }
      loadJobs()
    }
    setRestarting(false)
  }

  const retryJob = async (jobId: number) => {
    if (!confirm(`Retry job #${jobId}? A new PDF generation will be queued.`)) return
    try {
      const { data } = await apiClient.post(`/document-jobs/${jobId}/retry`)
      loadJobs()
      alert(data.message || 'Retry queued.')
    } catch (e: any) { alert(e?.response?.data?.error || 'Retry failed') }
  }

  const downloadPdf = async (job: DocJob) => {
    try {
      const r = await apiClient.get(`/policies/${job.policy_id}/download-quote-pdf/${job.id}`, { responseType: 'blob' })
      const url = URL.createObjectURL(r.data)
      const a = document.createElement('a')
      a.href = url
      a.download = job.file_name || `policy_${job.policy_id}_quote_sheet.pdf`
      document.body.appendChild(a)
      a.click()
      document.body.removeChild(a)
      URL.revokeObjectURL(url)
    } catch { alert('Download failed') }
  }

  return (
    <div className="p-6 max-w-7xl mx-auto">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Document Jobs</h1>
          <p className="text-sm text-gray-500 mt-1">Track PDF generation progress — auto-refreshes every 30s</p>
        </div>
        <div className="flex items-center gap-2">
          {canRestart && (
            <button onClick={restartQueue} disabled={restarting}
              title="Restart queue workers & clear stuck scheduler locks — unsticks 'Queued (large)' jobs waiting for the cron"
              className="px-4 py-2 text-sm bg-orange-600 hover:bg-orange-700 text-white rounded-lg disabled:opacity-50 inline-flex items-center gap-2">
              {restarting && <span className="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin" />}
              {restarting ? 'Restarting…' : 'Restart Queue & Cron'}
            </button>
          )}
          <button onClick={loadJobs} disabled={loading}
            className="px-4 py-2 text-sm bg-gray-100 hover:bg-gray-200 rounded-lg border disabled:opacity-50">
            {loading ? 'Loading...' : 'Refresh'}
          </button>
        </div>
      </div>

      {/* Stats cards — click to filter the list below. Stuck card only
          renders when there are genuinely stuck rows so it doesn't add
          visual noise on a healthy queue. */}
      {stats && (
        <div className="grid grid-cols-2 md:grid-cols-6 gap-3 mb-4">
          <button onClick={() => quickFilter('')} className={`text-left p-3 rounded-xl border transition ${!statusFilter ? 'bg-gray-100 border-gray-300' : 'bg-white hover:bg-gray-50'}`}>
            <div className="text-xs text-gray-500 uppercase">Total</div>
            <div className="text-xl font-bold text-gray-900 mt-0.5">{stats.total}</div>
          </button>
          <button onClick={() => quickFilter('queued,queued_long')} className={`text-left p-3 rounded-xl border transition ${statusFilter === 'queued,queued_long' ? 'bg-amber-100 border-amber-300' : 'bg-white hover:bg-amber-50'}`}>
            <div className="text-xs text-amber-600 uppercase">Queued</div>
            <div className="text-xl font-bold text-amber-700 mt-0.5">{stats.queued}</div>
          </button>
          <button onClick={() => quickFilter('processing')} className={`text-left p-3 rounded-xl border transition ${statusFilter === 'processing' ? 'bg-blue-100 border-blue-300' : 'bg-white hover:bg-blue-50'}`}>
            <div className="text-xs text-blue-600 uppercase">Processing</div>
            <div className="text-xl font-bold text-blue-700 mt-0.5">{stats.processing}</div>
          </button>
          <button onClick={() => quickFilter('completed')} className={`text-left p-3 rounded-xl border transition ${statusFilter === 'completed' ? 'bg-green-100 border-green-300' : 'bg-white hover:bg-green-50'}`}>
            <div className="text-xs text-green-600 uppercase">Completed</div>
            <div className="text-xl font-bold text-green-700 mt-0.5">{stats.completed}</div>
          </button>
          <button onClick={() => quickFilter('failed')} className={`text-left p-3 rounded-xl border transition ${statusFilter === 'failed' ? 'bg-red-100 border-red-300' : 'bg-white hover:bg-red-50'}`}>
            <div className="text-xs text-red-600 uppercase">Failed</div>
            <div className="text-xl font-bold text-red-700 mt-0.5">{stats.failed}</div>
          </button>
          {stats.stuck > 0 ? (
            <button onClick={() => quickFilter('queued,queued_long,processing')} className="text-left p-3 rounded-xl border bg-orange-50 border-orange-300 hover:bg-orange-100 transition">
              <div className="text-xs text-orange-700 uppercase">⚠ Stuck &gt;30min</div>
              <div className="text-xl font-bold text-orange-800 mt-0.5">{stats.stuck}</div>
            </button>
          ) : (
            <button onClick={() => quickFilter('cancelled')} className={`text-left p-3 rounded-xl border transition ${statusFilter === 'cancelled' ? 'bg-gray-200 border-gray-400' : 'bg-white hover:bg-gray-50'}`}>
              <div className="text-xs text-gray-500 uppercase">Cancelled</div>
              <div className="text-xl font-bold text-gray-700 mt-0.5">{stats.cancelled}</div>
            </button>
          )}
        </div>
      )}

      {/* Filter bar */}
      <div className="flex flex-wrap items-end gap-3 mb-4">
        <div>
          <label className="block text-xs text-gray-500 uppercase mb-1">Status</label>
          <select value={statusFilter} onChange={e => setStatusFilter(e.target.value)}
            className="px-3 py-2 border rounded-lg text-sm bg-white min-w-[160px]">
            {STATUS_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
          </select>
        </div>
        <form
          onSubmit={e => { e.preventDefault(); setSearch(searchInput.trim()) }}
          className="flex items-end gap-2">
          <div>
            <label className="block text-xs text-gray-500 uppercase mb-1">Policy #</label>
            <input value={searchInput} onChange={e => setSearchInput(e.target.value)}
              placeholder="COMG2024130199"
              className="px-3 py-2 border rounded-lg text-sm w-64" />
          </div>
          <button type="submit" className="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">Search</button>
          {search && (
            <button type="button" onClick={() => { setSearchInput(''); setSearch('') }}
              className="px-3 py-2 text-sm border rounded-lg hover:bg-gray-50">Clear</button>
          )}
        </form>
        <div className="ml-auto">
          <label className="block text-xs text-gray-500 uppercase mb-1">Show</label>
          <select value={limit} onChange={e => setLimit(Number(e.target.value))}
            className="px-3 py-2 border rounded-lg text-sm bg-white">
            <option value={50}>50 rows</option>
            <option value={100}>100 rows</option>
            <option value={250}>250 rows</option>
            <option value={500}>500 rows</option>
          </select>
        </div>
      </div>

      {/* Error-log inline modal */}
      {errorLog && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-start justify-center p-4 pt-10 overflow-y-auto" onClick={() => setErrorLog(null)}>
          <div className="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[85vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
            <div className="flex items-center justify-between px-5 py-3 border-b bg-gray-50 sticky top-0">
              <div>
                <h3 className="text-sm font-semibold text-gray-700">API Error Log #{errorLog.id}</h3>
                {errorLog.created_at && <p className="text-[10px] text-gray-500 mt-0.5">{new Date(errorLog.created_at).toLocaleString()}</p>}
              </div>
              <button onClick={() => setErrorLog(null)} className="text-gray-400 hover:text-gray-600 text-xl leading-none">✕</button>
            </div>
            <div className="px-5 py-4 space-y-3 text-sm">
              {errorLogLoading && <p className="text-gray-500 italic">Loading…</p>}
              {errorLog.error && !errorLogLoading && (
                <p className="text-red-600 bg-red-50 border border-red-200 rounded p-3">{errorLog.error}</p>
              )}
              {!errorLog.error && !errorLogLoading && (
                <>
                  {(errorLog.method || errorLog.url) && (
                    <div className="font-mono text-xs bg-gray-50 border rounded p-2">
                      <span className="font-semibold">{errorLog.method || ''}</span> {errorLog.url || ''}
                      {errorLog.status_code && <span className="ml-2 px-2 py-0.5 bg-red-100 text-red-700 rounded">{errorLog.status_code}</span>}
                    </div>
                  )}
                  {errorLog.error_message && (
                    <div>
                      <h4 className="text-[10px] uppercase text-gray-500 font-semibold mb-1">Message</h4>
                      <pre className="text-xs bg-red-50 border border-red-200 rounded p-3 whitespace-pre-wrap">{errorLog.error_message}</pre>
                    </div>
                  )}
                  {errorLog.error_data && (
                    <div>
                      <h4 className="text-[10px] uppercase text-gray-500 font-semibold mb-1">Stack / Detail</h4>
                      <pre className="text-[10px] bg-gray-50 border rounded p-3 whitespace-pre-wrap overflow-x-auto">{typeof errorLog.error_data === 'string' ? errorLog.error_data : JSON.stringify(errorLog.error_data, null, 2)}</pre>
                    </div>
                  )}
                  {errorLog.request_payload && (
                    <div>
                      <h4 className="text-[10px] uppercase text-gray-500 font-semibold mb-1">Request Payload</h4>
                      <pre className="text-[10px] bg-gray-50 border rounded p-3 whitespace-pre-wrap overflow-x-auto">{typeof errorLog.request_payload === 'string' ? errorLog.request_payload : JSON.stringify(errorLog.request_payload, null, 2)}</pre>
                    </div>
                  )}
                </>
              )}
            </div>
          </div>
        </div>
      )}

      <div className="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table className="w-full text-sm">
          <thead>
            <tr className="text-left text-xs text-gray-500 border-b bg-gray-50">
              <th className="px-4 py-3">Job ID</th>
              <th className="px-4 py-3">Policy</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3">File</th>
              <th className="px-4 py-3">Message</th>
              <th className="px-4 py-3">Requested</th>
              <th className="px-4 py-3">Actions</th>
            </tr>
          </thead>
          <tbody>
            {jobs.length === 0 && !loading && (
              <tr><td colSpan={7} className="p-0"><EmptyState compact title="No document jobs found" description="Try adjusting your filters." /></td></tr>
            )}
            {jobs.map(job => (
              <tr key={job.id} className={`border-b hover:bg-gray-50 ${job.is_stuck ? 'bg-orange-50/40' : ''}`} title={job.is_stuck ? 'Stuck — non-terminal status for 30+ minutes' : undefined}>
                <td className="px-4 py-3 font-mono text-xs text-gray-600">
                  #{job.id}
                  {job.is_stuck && <span className="ml-1 text-orange-600" title="Stuck &gt;30min">⚠</span>}
                </td>
                <td className="px-4 py-3">
                  <Link to={`/policies/${job.policy_id}`} className="text-blue-600 hover:underline font-medium">
                    {job.policy_number || `Policy #${job.policy_id}`}
                  </Link>
                </td>
                <td className="px-4 py-3">{statusBadge(job.status)}</td>
                <td className="px-4 py-3 text-xs text-gray-500 max-w-[200px] truncate">{job.file_name || '—'}</td>
                <td className="px-4 py-3 text-xs text-gray-500 max-w-[200px] truncate">{job.message || '—'}</td>
                <td className="px-4 py-3 text-xs text-gray-400">{job.time_ago}</td>
                <td className="px-4 py-3">
                  {job.status === 'completed' && job.file_name && (
                    <div className="flex items-center gap-2">
                      <button onClick={() => downloadPdf(job)} title="Download"
                        className="p-1.5 text-green-600 hover:bg-green-50 rounded transition">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                      </button>
                      <button onClick={async () => {
                        try {
                          const r = await apiClient.get(`/policies/${job.policy_id}/download-quote-pdf/${job.id}`, { responseType: 'blob' })
                          window.open(URL.createObjectURL(r.data), '_blank')
                        } catch { alert('View failed') }
                      }} title="View"
                        className="p-1.5 text-blue-600 hover:bg-blue-50 rounded transition">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                      </button>
                    </div>
                  )}
                  {job.status === 'failed' && (
                    <div className="flex items-center gap-3">
                      {job.error_log_id ? (
                        <button
                          type="button"
                          onClick={() => openErrorLog(job.error_log_id as number)}
                          title="Show matching api_error_log entry inline — no session redirect"
                          className="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 hover:underline">
                          <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                          </svg>
                          View error log
                        </button>
                      ) : (
                        <span className="text-xs text-red-500">Failed</span>
                      )}
                      <button onClick={() => retryJob(job.id)} title="Re-queue a new PDF generation with the same policy/action"
                        className="text-xs text-blue-600 hover:underline">Retry</button>
                    </div>
                  )}
                  {(job.status === 'queued' || job.status === 'queued_long' || job.status === 'processing') && (
                    <div className="flex items-center gap-3">
                      <span className="inline-flex items-center gap-1 text-xs text-blue-600">
                        <span className="w-3 h-3 border-2 border-blue-500 border-t-transparent rounded-full animate-spin" />
                        {job.status === 'queued_long' ? 'Waiting for cron (5-15 min)' : 'In progress'}
                      </span>
                      <button
                        onClick={async () => {
                          if (!confirm(`Cancel job #${job.id}?`)) return
                          try {
                            await apiClient.post(`/document-jobs/${job.id}/cancel`)
                            loadJobs()
                          } catch (e: any) { alert(e?.response?.data?.error || 'Cancel failed') }
                        }}
                        className="text-xs text-red-500 hover:underline">
                        Cancel
                      </button>
                    </div>
                  )}
                  {job.status === 'cancelled' && (
                    <div className="flex items-center gap-3">
                      <span className="text-xs text-gray-500">Cancelled</span>
                      <button onClick={() => retryJob(job.id)}
                        className="text-xs text-blue-600 hover:underline">Retry</button>
                    </div>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
