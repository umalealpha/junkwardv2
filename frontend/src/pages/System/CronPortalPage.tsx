import { useState, useEffect, useCallback } from 'react'
import {
  useKernelJobs,
  useKernelStatusSummary,
  useKernelJobDetail,
  useUpdateKernelJob,
  useAddKernelMail,
  useRemoveKernelMail,
  useRunKernelJobNow,
} from '../../hooks/useCronKernel'
import type { KernelJob } from '../../api/cronKernel'

// ─── Helpers ──────────────────────────────────────────────────────────────────

function formatTime(iso: string | null): string {
  if (!iso) return '—'
  const d = new Date(iso)
  if (isNaN(d.getTime())) return iso
  return d.toLocaleString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
  })
}

function durationSeconds(start: string | null, end: string | null): string {
  if (!start || !end) return '—'
  const s = new Date(start).getTime()
  const e = new Date(end).getTime()
  if (isNaN(s) || isNaN(e)) return '—'
  const diff = Math.round((e - s) / 1000)
  if (diff < 60) return `${diff}s`
  const m = Math.floor(diff / 60)
  const sec = diff % 60
  return `${m}m ${sec}s`
}

// ─── Run type badge ────────────────────────────────────────────────────────────

function RunTypeBadge({ type }: { type: KernelJob['run_type'] }) {
  const map: Record<KernelJob['run_type'], { label: string; cls: string }> = {
    Hourly: { label: 'Hourly', cls: 'bg-blue-100 text-blue-700' },
    Daily: { label: 'Daily', cls: 'bg-green-100 text-green-700' },
    weekly_sundays: { label: 'Weekly', cls: 'bg-purple-100 text-purple-700' },
    lastDayOfMonth: { label: 'Month End', cls: 'bg-orange-100 text-orange-700' },
  }
  const { label, cls } = map[type] ?? { label: type, cls: 'bg-gray-100 text-gray-600' }
  return (
    <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${cls}`}>
      {label}
    </span>
  )
}

// ─── Last run indicator ────────────────────────────────────────────────────────

function LastRunIndicator({ job }: { job: KernelJob }) {
  if (job.last_run_start && !job.last_run_end) {
    return (
      <span className="flex items-center gap-1.5 text-xs text-yellow-600">
        <span className="inline-block w-2 h-2 rounded-full bg-yellow-400 animate-pulse" />
        Running...
      </span>
    )
  }
  if (job.last_run_ok && job.last_run_end) {
    return (
      <span className="flex items-center gap-1.5 text-xs text-green-700">
        <span className="inline-block w-2 h-2 rounded-full bg-green-500" />
        <span className="truncate max-w-[120px]" title={formatTime(job.last_run_end)}>
          {formatTime(job.last_run_end)}
        </span>
      </span>
    )
  }
  if (job.last_run_end && !job.last_run_ok) {
    return (
      <span className="flex items-center gap-1.5 text-xs text-red-600">
        <span className="inline-block w-2 h-2 rounded-full bg-red-500" />
        <span className="truncate max-w-[120px]" title={formatTime(job.last_run_end)}>
          {formatTime(job.last_run_end)}
        </span>
      </span>
    )
  }
  return (
    <span className="flex items-center gap-1.5 text-xs text-gray-400">
      <span className="inline-block w-2 h-2 rounded-full bg-gray-300" />
      Never
    </span>
  )
}

// ─── Detail slide-over panel ──────────────────────────────────────────────────

interface DetailPanelProps {
  jobId: number
  onClose: () => void
}

function DetailPanel({ jobId, onClose }: DetailPanelProps) {
  const { data: detail, isLoading } = useKernelJobDetail(jobId)
  const addMailMutation = useAddKernelMail(jobId)
  const removeMailMutation = useRemoveKernelMail(jobId)
  const updateMutation = useUpdateKernelJob()

  const [editingSchedule, setEditingSchedule] = useState(false)
  const [editRunTime, setEditRunTime] = useState('')
  const [editRunType, setEditRunType] = useState<KernelJob['run_type']>('Daily')
  const [newEmail, setNewEmail] = useState('')

  useEffect(() => {
    if (detail) {
      setEditRunTime(detail.run_time ?? '')
      setEditRunType(detail.run_type)
    }
  }, [detail])

  const handleSaveSchedule = useCallback(() => {
    if (!detail) return
    updateMutation.mutate(
      { id: jobId, data: { run_time: editRunTime, run_type: editRunType } },
      { onSuccess: () => setEditingSchedule(false) }
    )
  }, [detail, jobId, editRunTime, editRunType, updateMutation])

  const handleAddEmail = useCallback(() => {
    const trimmed = newEmail.trim()
    if (!trimmed) return
    addMailMutation.mutate(trimmed, { onSuccess: () => setNewEmail('') })
  }, [newEmail, addMailMutation])

  const handleRemoveEmail = useCallback(
    (email: string) => {
      removeMailMutation.mutate(email)
    },
    [removeMailMutation]
  )

  return (
    <div className="fixed inset-0 z-50 flex justify-end" aria-modal="true">
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-black/30 transition-opacity"
        onClick={onClose}
        aria-hidden="true"
      />

      {/* Panel */}
      <div className="relative w-96 bg-white shadow-xl flex flex-col h-full overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between px-4 py-3 border-b border-gray-200 bg-gray-50 flex-shrink-0">
          <h2 className="text-sm font-semibold text-gray-800 truncate pr-2">
            {isLoading ? 'Loading...' : detail?.cron_name ?? `Job #${jobId}`}
          </h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 transition flex-shrink-0"
            aria-label="Close panel"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        {/* Body */}
        <div className="flex-1 overflow-y-auto px-4 py-4 space-y-4">
          {isLoading && (
            <div className="flex items-center justify-center py-12">
              <div className="w-6 h-6 border-2 border-brand-navy border-t-transparent rounded-full animate-spin" />
            </div>
          )}

          {detail && (
            <>
              {/* Schedule section */}
              <section>
                <div className="flex items-center justify-between mb-2">
                  <h3 className="text-xs font-bold text-gray-500 uppercase tracking-wider">Schedule</h3>
                  {!editingSchedule && (
                    <button
                      onClick={() => setEditingSchedule(true)}
                      className="text-xs text-blue-600 hover:text-blue-800 transition"
                    >
                      Edit
                    </button>
                  )}
                </div>

                {editingSchedule ? (
                  <div className="space-y-3 bg-gray-50 rounded-lg p-3 border border-gray-200">
                    <div>
                      <label className="block text-xs font-medium text-gray-600 mb-1">Run Type</label>
                      <select
                        value={editRunType}
                        onChange={(e) => setEditRunType(e.target.value as KernelJob['run_type'])}
                        className="w-full text-sm border border-gray-300 rounded px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      >
                        <option value="Hourly">Hourly</option>
                        <option value="Daily">Daily</option>
                        <option value="weekly_sundays">Weekly (Sundays)</option>
                        <option value="lastDayOfMonth">Last Day of Month</option>
                      </select>
                    </div>
                    <div>
                      <label className="block text-xs font-medium text-gray-600 mb-1">Run Time (HH:MM)</label>
                      <input
                        type="text"
                        value={editRunTime}
                        onChange={(e) => setEditRunTime(e.target.value)}
                        placeholder="e.g. 02:30"
                        className="w-full text-sm border border-gray-300 rounded px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      />
                    </div>
                    <div className="flex gap-2">
                      <button
                        onClick={handleSaveSchedule}
                        disabled={updateMutation.isPending}
                        className="flex-1 text-xs bg-blue-600 text-white rounded px-3 py-1.5 hover:bg-blue-700 disabled:opacity-50 transition"
                      >
                        {updateMutation.isPending ? 'Saving...' : 'Save'}
                      </button>
                      <button
                        onClick={() => setEditingSchedule(false)}
                        className="flex-1 text-xs bg-gray-200 text-gray-700 rounded px-3 py-1.5 hover:bg-gray-300 transition"
                      >
                        Cancel
                      </button>
                    </div>
                  </div>
                ) : (
                  <div className="space-y-2 text-sm">
                    <div className="flex items-center gap-2">
                      <span className="text-gray-500 w-20 flex-shrink-0">Type</span>
                      <RunTypeBadge type={detail.run_type} />
                    </div>
                    <div className="flex items-center gap-2">
                      <span className="text-gray-500 w-20 flex-shrink-0">Time</span>
                      <span className="text-gray-800 font-mono">{detail.run_time ?? '—'}</span>
                    </div>
                    <div className="flex items-center gap-2">
                      <span className="text-gray-500 w-20 flex-shrink-0">Server</span>
                      <span className="text-gray-800">{detail.run_on_server}</span>
                    </div>
                    <div className="flex items-center gap-2">
                      <span className="text-gray-500 w-20 flex-shrink-0">Status</span>
                      {detail.enabled ? (
                        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">
                          Enabled
                        </span>
                      ) : (
                        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-600">
                          Disabled
                        </span>
                      )}
                    </div>
                    <div className="flex items-center gap-2">
                      <span className="text-gray-500 w-20 flex-shrink-0">Last Run</span>
                      <span className="text-gray-800 text-xs">{formatTime(detail.last_run_end)}</span>
                    </div>
                  </div>
                )}
              </section>

              {/* Email recipients */}
              <section>
                <h3 className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">
                  Email Recipients
                </h3>
                <div className="space-y-1.5 mb-3">
                  {detail.emails.length === 0 && (
                    <p className="text-xs text-gray-400 italic">No recipients configured</p>
                  )}
                  {detail.emails.map((email) => (
                    <div
                      key={email}
                      className="flex items-center justify-between text-xs bg-gray-50 rounded px-2 py-1.5 border border-gray-200"
                    >
                      <span className="text-gray-700 truncate">{email}</span>
                      <button
                        onClick={() => handleRemoveEmail(email)}
                        disabled={removeMailMutation.isPending}
                        className="text-red-400 hover:text-red-600 transition ml-2 flex-shrink-0 disabled:opacity-50"
                        aria-label={`Remove ${email}`}
                      >
                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                      </button>
                    </div>
                  ))}
                </div>
                <div className="flex gap-2">
                  <input
                    type="email"
                    value={newEmail}
                    onChange={(e) => setNewEmail(e.target.value)}
                    onKeyDown={(e) => { if (e.key === 'Enter') handleAddEmail() }}
                    placeholder="Add email address"
                    className="flex-1 text-xs border border-gray-300 rounded px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                  <button
                    onClick={handleAddEmail}
                    disabled={addMailMutation.isPending || !newEmail.trim()}
                    className="text-xs bg-blue-600 text-white rounded px-3 py-1.5 hover:bg-blue-700 disabled:opacity-50 transition"
                  >
                    Add
                  </button>
                </div>
              </section>

              {/* Run history */}
              <section>
                <h3 className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">
                  Run History (last 20)
                </h3>
                {detail.run_history.length === 0 ? (
                  <p className="text-xs text-gray-400 italic">No run history available</p>
                ) : (
                  <div className="overflow-x-auto rounded border border-gray-200">
                    <table className="w-full text-xs">
                      <thead className="bg-gray-50 border-b border-gray-200">
                        <tr>
                          <th className="px-2 py-1.5 text-left font-medium text-gray-500">Start</th>
                          <th className="px-2 py-1.5 text-left font-medium text-gray-500">Duration</th>
                          <th className="px-2 py-1.5 text-left font-medium text-gray-500">Processed</th>
                          {/* Heartbeat columns — the cron writes current_step
                              and last_policy_id as it progresses, so a stuck
                              run is visible here instead of just sitting on
                              "Running". error_message is rendered as a
                              tooltip / expanded row when present. */}
                          <th className="px-2 py-1.5 text-left font-medium text-gray-500">Last step</th>
                          <th className="px-2 py-1.5 text-left font-medium text-gray-500">Last policy</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-gray-100">
                        {detail.run_history.slice(0, 20).map((run) => {
                          const stepLabel = (run.current_step || '').toLowerCase()
                          const stepCls =
                            stepLabel === 'completed'
                              ? 'bg-green-100 text-green-700'
                              : stepLabel === 'failed'
                                ? 'bg-red-100 text-red-700'
                                : stepLabel
                                  ? 'bg-yellow-100 text-yellow-800'
                                  : 'text-gray-400'
                          return (
                            <tr key={run.id} className="hover:bg-gray-50 align-top">
                              <td className="px-2 py-1.5 text-gray-700 font-mono whitespace-nowrap">
                                {formatTime(run.start)}
                              </td>
                              <td className="px-2 py-1.5 text-gray-600 whitespace-nowrap">
                                {run.end ? durationSeconds(run.start, run.end) : (
                                  <span className="text-yellow-600 flex items-center gap-1">
                                    <span className="w-1.5 h-1.5 bg-yellow-400 rounded-full animate-pulse inline-block" />
                                    Running
                                  </span>
                                )}
                              </td>
                              <td className="px-2 py-1.5 text-gray-600">
                                {run.processedCount ?? '—'}
                              </td>
                              <td className="px-2 py-1.5">
                                {run.current_step ? (
                                  <span className={`inline-block px-1.5 py-0.5 rounded text-[10px] font-medium ${stepCls}`}>
                                    {run.current_step}
                                  </span>
                                ) : (
                                  <span className="text-gray-400">—</span>
                                )}
                                {run.error_message && (
                                  <div
                                    className="mt-1 text-[10px] text-red-600 max-w-xs truncate"
                                    title={run.error_message}
                                  >
                                    ⚠ {run.error_message}
                                  </div>
                                )}
                              </td>
                              <td className="px-2 py-1.5 text-gray-600 font-mono">
                                {run.last_policy_id ?? '—'}
                              </td>
                            </tr>
                          )
                        })}
                      </tbody>
                    </table>
                  </div>
                )}
              </section>
            </>
          )}
        </div>
      </div>
    </div>
  )
}

// ─── Status summary bar ────────────────────────────────────────────────────────

function StatusSummaryBar() {
  const { data: summary, isLoading } = useKernelStatusSummary()

  if (isLoading) {
    return (
      <div className="flex items-center gap-2 h-8">
        {[...Array(4)].map((_, i) => (
          <div key={i} className="h-7 w-24 bg-gray-200 animate-pulse rounded-full" />
        ))}
      </div>
    )
  }

  if (!summary) return null

  return (
    <div className="flex flex-wrap items-center gap-2">
      <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
        <span className="font-bold">{summary.total}</span> Total
      </span>
      <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
        <span className="w-1.5 h-1.5 bg-green-500 rounded-full inline-block" />
        <span className="font-bold">{summary.enabled}</span> Enabled
      </span>
      <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
        <span className="font-bold">{summary.disabled}</span> Disabled
      </span>
      {summary.currently_running.length > 0 && (
        <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700 border border-blue-200">
          <span className="w-1.5 h-1.5 bg-blue-500 rounded-full inline-block animate-pulse" />
          <span className="font-bold">{summary.currently_running.length}</span> Running now
          {summary.currently_running.length <= 3 && (
            <span className="text-blue-600 ml-0.5">
              ({summary.currently_running.map((r) => r.name).join(', ')})
            </span>
          )}
        </span>
      )}
    </div>
  )
}

// ─── Table row ────────────────────────────────────────────────────────────────

interface TableRowProps {
  job: KernelJob
  isRunning: boolean
  onClick: () => void
}

function JobTableRow({ job, isRunning, onClick }: TableRowProps) {
  const updateMutation = useUpdateKernelJob()
  const runNowMutation = useRunKernelJobNow()

  const handleToggle = useCallback(
    (e: React.MouseEvent) => {
      e.stopPropagation()
      updateMutation.mutate({ id: job.id, data: { status: job.enabled ? 0 : 1 } })
    },
    [job.id, job.enabled, updateMutation]
  )

  const handleRunNow = useCallback(
    (e: React.MouseEvent) => {
      e.stopPropagation()
      if (!window.confirm(`Run "${job.cron_name}" now?\n\nThis triggers the cron immediately and records a manual run in the activity log.`)) {
        return
      }
      runNowMutation.mutate(job.id, {
        onSuccess: (data) => {
          const status = data.success ? 'OK' : `FAILED (exit ${data.exit_code})`
          const out = data.output && data.output !== '(no output)' ? `\n\n— Output —\n${data.output.slice(0, 600)}` : ''
          const err = data.error ? `\n\nError: ${data.error}` : ''
          alert(`${job.cron_name} — ${status}${err}${out}`)
        },
        onError: (err: unknown) => {
          const msg = err instanceof Error ? err.message : 'Unknown error'
          alert(`${job.cron_name} — request failed: ${msg}`)
        },
      })
    },
    [job.id, job.cron_name, runNowMutation]
  )

  const rowCls = job.enabled
    ? isRunning
      ? 'bg-blue-50 hover:bg-blue-100'
      : 'bg-white hover:bg-gray-50'
    : 'bg-gray-50 opacity-60 hover:bg-gray-100'

  return (
    <tr
      onClick={onClick}
      className={`cursor-pointer border-b border-gray-100 transition ${rowCls}`}
    >
      <td className="px-3 py-2.5">
        <div className="flex items-center gap-2">
          {isRunning && (
            <span
              className="w-2 h-2 bg-blue-500 rounded-full animate-pulse flex-shrink-0"
              title="Currently running"
            />
          )}
          <span className="text-sm text-gray-800 font-medium truncate max-w-[220px]" title={job.cron_name}>
            {job.cron_name}
          </span>
        </div>
      </td>
      <td className="px-3 py-2.5 whitespace-nowrap">
        <RunTypeBadge type={job.run_type} />
      </td>
      <td className="px-3 py-2.5 whitespace-nowrap text-xs font-mono text-gray-600">
        {job.run_time ?? '—'}
      </td>
      <td className="px-3 py-2.5 whitespace-nowrap text-xs text-gray-600">
        {job.run_on_server}
      </td>
      <td className="px-3 py-2.5 whitespace-nowrap">
        {job.enabled ? (
          <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">
            On
          </span>
        ) : (
          <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-500">
            Off
          </span>
        )}
      </td>
      <td className="px-3 py-2.5 whitespace-nowrap">
        <LastRunIndicator job={job} />
      </td>
      <td className="px-3 py-2.5">
        <span className="text-xs text-gray-500 truncate max-w-[140px] block" title={job.emails.join(', ')}>
          {job.emails.length === 0
            ? <span className="text-gray-300 italic">none</span>
            : job.emails.length === 1
              ? job.emails[0]
              : `${job.emails[0]} +${job.emails.length - 1}`}
        </span>
      </td>
      <td className="px-3 py-2.5 whitespace-nowrap" onClick={(e) => e.stopPropagation()}>
        <button
          onClick={handleToggle}
          disabled={updateMutation.isPending}
          className={`relative inline-flex h-5 w-9 items-center rounded-full transition-colors disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 ${
            job.enabled ? 'bg-green-500' : 'bg-gray-300'
          }`}
          title={job.enabled ? 'Disable job' : 'Enable job'}
          aria-label={job.enabled ? 'Disable job' : 'Enable job'}
        >
          <span
            className={`inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform ${
              job.enabled ? 'translate-x-4' : 'translate-x-0.5'
            }`}
          />
        </button>
      </td>
      <td className="px-3 py-2.5 whitespace-nowrap" onClick={(e) => e.stopPropagation()}>
        <button
          onClick={handleRunNow}
          disabled={!job.enabled || runNowMutation.isPending}
          title={job.enabled ? 'Trigger this cron immediately' : 'Enable the cron before running it manually'}
          className="text-xs px-2.5 py-1 rounded border border-brand-navy text-brand-navy hover:bg-brand-navy hover:text-white transition disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent disabled:hover:text-brand-navy"
        >
          {runNowMutation.isPending ? 'Running…' : '▶ Run'}
        </button>
      </td>
    </tr>
  )
}

// ─── Pagination bar component ─────────────────────────────────────────────────

const PAGE_SIZE_OPTIONS = [10, 25, 50, 100] as const
type PageSize = (typeof PAGE_SIZE_OPTIONS)[number]

interface PaginationBarProps {
  total: number
  page: number
  pageSize: PageSize
  onPage: (p: number) => void
  onPageSize: (ps: PageSize) => void
}

function PaginationBar({ total, page, pageSize, onPage, onPageSize }: PaginationBarProps) {
  const totalPages = Math.max(1, Math.ceil(total / pageSize))
  const from = total === 0 ? 0 : (page - 1) * pageSize + 1
  const to   = Math.min(page * pageSize, total)

  // Build page window: always show first, last, current ±2, fill with ellipsis
  const pages: (number | '…')[] = []
  const add = (n: number) => { if (!pages.includes(n)) pages.push(n) }
  add(1)
  if (page - 2 > 2) pages.push('…')
  for (let i = Math.max(2, page - 2); i <= Math.min(totalPages - 1, page + 2); i++) add(i)
  if (page + 2 < totalPages - 1) pages.push('…')
  if (totalPages > 1) add(totalPages)

  const btnBase = 'inline-flex items-center justify-center min-w-[32px] h-8 px-2 rounded text-xs font-medium transition'
  const btnActive = 'bg-brand-navy text-white'
  const btnInactive = 'text-gray-600 hover:bg-gray-100 border border-gray-200'
  const btnDisabled = 'text-gray-300 border border-gray-100 cursor-not-allowed'

  return (
    <div className="flex items-center justify-between px-4 py-2.5 border-t border-gray-200 bg-gray-50 rounded-b-lg">
      {/* Left: showing info + rows per page */}
      <div className="flex items-center gap-3">
        <span className="text-xs text-gray-500">
          {total === 0 ? 'No results' : `Showing ${from}–${to} of ${total}`}
        </span>
        <div className="flex items-center gap-1.5">
          <span className="text-xs text-gray-400">Rows:</span>
          <div className="flex items-center rounded border border-gray-200 overflow-hidden bg-white">
            {PAGE_SIZE_OPTIONS.map((ps, idx) => (
              <button
                key={ps}
                onClick={() => { onPageSize(ps); onPage(1) }}
                className={`px-2.5 py-1 text-xs font-medium transition ${
                  pageSize === ps ? 'bg-brand-navy text-white' : 'text-gray-600 hover:bg-gray-100'
                } ${idx > 0 ? 'border-l border-gray-200' : ''}`}
              >
                {ps}
              </button>
            ))}
          </div>
        </div>
      </div>

      {/* Right: page navigation */}
      {totalPages > 1 && (
        <div className="flex items-center gap-1">
          <button
            onClick={() => onPage(page - 1)}
            disabled={page === 1}
            className={`${btnBase} ${page === 1 ? btnDisabled : btnInactive}`}
            aria-label="Previous page"
          >
            ‹ Prev
          </button>

          {pages.map((p, i) =>
            p === '…' ? (
              <span key={`ellipsis-${i}`} className="px-1 text-xs text-gray-400 select-none">…</span>
            ) : (
              <button
                key={p}
                onClick={() => onPage(p as number)}
                className={`${btnBase} ${p === page ? btnActive : btnInactive}`}
                aria-current={p === page ? 'page' : undefined}
              >
                {p}
              </button>
            )
          )}

          <button
            onClick={() => onPage(page + 1)}
            disabled={page === totalPages}
            className={`${btnBase} ${page === totalPages ? btnDisabled : btnInactive}`}
            aria-label="Next page"
          >
            Next ›
          </button>
        </div>
      )}
    </div>
  )
}

// ─── Main page ────────────────────────────────────────────────────────────────

export default function CronPortalPage() {
  const [searchInput, setSearchInput] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')
  const [serverFilter, setServerFilter] = useState<string>('all')
  const [enabledFilter, setEnabledFilter] = useState<'all' | 'enabled' | 'disabled'>('all')
  const [selectedJobId, setSelectedJobId] = useState<number | null>(null)

  // Pagination
  const [pageSize, setPageSize] = useState<PageSize>(10)
  const [currentPage, setCurrentPage] = useState(1)

  // Debounce search 300ms
  useEffect(() => {
    const timer = setTimeout(() => setDebouncedSearch(searchInput), 300)
    return () => clearTimeout(timer)
  }, [searchInput])

  // Reset to page 1 whenever filters change
  useEffect(() => { setCurrentPage(1) }, [debouncedSearch, serverFilter, enabledFilter])

  const queryParams = {
    search: debouncedSearch || undefined,
    server: serverFilter === 'all' ? undefined : serverFilter,
    enabled: enabledFilter === 'all' ? undefined : enabledFilter === 'enabled' ? '1' : '0',
  }

  const { data: jobsData, isLoading: jobsLoading, isError: jobsError } = useKernelJobs(queryParams)
  const { data: summary } = useKernelStatusSummary()

  const allJobs: KernelJob[] = jobsData?.data ?? []
  const runningNames = new Set((summary?.currently_running ?? []).map((r) => r.name))
  const serverList = summary?.by_server?.map((s) => s.run_on_server) ?? []

  // Paginated slice
  const totalJobs   = allJobs.length
  const totalPages  = Math.max(1, Math.ceil(totalJobs / pageSize))
  const safePage    = Math.min(currentPage, totalPages)
  const startIdx    = (safePage - 1) * pageSize
  const jobs        = allJobs.slice(startIdx, startIdx + pageSize)

  const handleSelectJob  = useCallback((id: number) => setSelectedJobId(id), [])
  const handleClosePanel = useCallback(() => setSelectedJobId(null), [])

  return (
    <div className="flex flex-col h-full">
      {/* ── Fixed header ── */}
      <div className="flex-shrink-0 px-6 pt-5 pb-3 bg-white border-b border-gray-200 space-y-3">
        {/* Title row */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-xl font-bold text-gray-900">Cron Portal</h1>
            <p className="text-sm text-gray-500 mt-0.5">
              Kernel job scheduler — manage, monitor and configure scheduled cron jobs
            </p>
          </div>
          <StatusSummaryBar />
        </div>

        {/* Filters row */}
        <div className="flex flex-wrap items-center gap-3">
          {/* Search */}
          <div className="relative flex-1 min-w-[200px] max-w-xs">
            <svg
              className="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
              fill="none" stroke="currentColor" viewBox="0 0 24 24"
            >
              <circle cx="11" cy="11" r="8" strokeWidth="2" />
              <path strokeLinecap="round" strokeWidth="2" d="M21 21l-4.35-4.35" />
            </svg>
            <input
              type="search"
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              placeholder="Search cron name..."
              className="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          {/* Server tabs */}
          <div className="flex items-center rounded-md border border-gray-300 overflow-hidden bg-white text-sm">
            {['all', ...serverList].map((srv) => (
              <button
                key={srv}
                onClick={() => setServerFilter(srv)}
                className={`px-3 py-1.5 transition ${
                  serverFilter === srv ? 'bg-brand-navy text-white font-medium' : 'text-gray-600 hover:bg-gray-100'
                } ${srv !== 'all' && 'border-l border-gray-300'}`}
              >
                {srv === 'all' ? 'All Servers' : srv}
              </button>
            ))}
          </div>

          {/* Enabled filter */}
          <div className="flex items-center rounded-md border border-gray-300 overflow-hidden bg-white text-sm">
            {(['all', 'enabled', 'disabled'] as const).map((f, idx) => (
              <button
                key={f}
                onClick={() => setEnabledFilter(f)}
                className={`px-3 py-1.5 capitalize transition ${
                  enabledFilter === f ? 'bg-brand-navy text-white font-medium' : 'text-gray-600 hover:bg-gray-100'
                } ${idx > 0 && 'border-l border-gray-300'}`}
              >
                {f === 'all' ? 'All' : f.charAt(0).toUpperCase() + f.slice(1)}
              </button>
            ))}
          </div>

          {/* Job count */}
          {!jobsLoading && (
            <span className="text-xs text-gray-500 ml-auto">
              {totalJobs} job{totalJobs !== 1 ? 's' : ''}
            </span>
          )}
        </div>
      </div>

      {/* ── Table + pagination ── */}
      <div className="flex-1 overflow-hidden px-6 pb-4 pt-2">
        <div className="flex flex-col rounded-lg border border-gray-200 bg-white max-h-[calc(100vh-260px)]">

          {/* Scrollable table area */}
          <div className="overflow-y-auto flex-1">
            {jobsLoading && (
              <div className="flex items-center justify-center py-20">
                <div className="w-8 h-8 border-2 border-brand-navy border-t-transparent rounded-full animate-spin" />
              </div>
            )}

            {jobsError && (
              <div className="flex items-center justify-center py-20">
                <div className="text-center">
                  <div className="text-red-500 text-sm font-medium mb-1">Failed to load cron jobs</div>
                  <div className="text-gray-400 text-xs">Check your connection and try again</div>
                </div>
              </div>
            )}

            {!jobsLoading && !jobsError && totalJobs === 0 && (
              <div className="flex items-center justify-center py-20 text-gray-400 text-sm">
                No cron jobs match the current filters
              </div>
            )}

            {!jobsLoading && !jobsError && totalJobs > 0 && (
              <table className="w-full text-sm">
                <thead className="sticky top-0 z-10 bg-gray-50 border-b border-gray-200">
                  <tr>
                    <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
                    <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Type</th>
                    <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Time</th>
                    <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Server</th>
                    <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                    <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Last Run</th>
                    <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Emails</th>
                    <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Toggle</th>
                    <th className="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Run Now</th>
                  </tr>
                </thead>
                <tbody>
                  {jobs.map((job) => (
                    <JobTableRow
                      key={job.id}
                      job={job}
                      isRunning={runningNames.has(job.cron_name)}
                      onClick={() => handleSelectJob(job.id)}
                    />
                  ))}
                </tbody>
              </table>
            )}
          </div>

          {/* Sticky pagination bar */}
          {!jobsLoading && !jobsError && totalJobs > 0 && (
            <PaginationBar
              total={totalJobs}
              page={safePage}
              pageSize={pageSize}
              onPage={setCurrentPage}
              onPageSize={setPageSize}
            />
          )}
        </div>
      </div>

      {/* ── Detail slide-over ── */}
      {selectedJobId !== null && (
        <DetailPanel jobId={selectedJobId} onClose={handleClosePanel} />
      )}
    </div>
  )
}
