import { useState, useMemo } from 'react'
import { useCronLogNames, useCronLogTail } from '../../hooks/useCronLogs'
import type { CronLogLine, LogScope } from '../../api/cronLogs'

// ─── Helpers ──────────────────────────────────────────────────────────────────

function formatBytes(n: number): string {
  if (!n) return '0 B'
  if (n < 1024) return `${n} B`
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`
  return `${(n / 1024 / 1024).toFixed(1)} MB`
}

function levelClass(level: string): string {
  switch (level.toLowerCase()) {
    case 'error':
    case 'critical':
    case 'alert':
    case 'emergency':
      return 'text-red-600 bg-red-50 border-red-200'
    case 'warning':
      return 'text-amber-700 bg-amber-50 border-amber-200'
    case 'info':
    case 'notice':
      return 'text-blue-600 bg-blue-50 border-blue-200'
    case 'debug':
      return 'text-gray-500 bg-gray-50 border-gray-200'
    default:
      return 'text-gray-600 bg-gray-50 border-gray-200'
  }
}

// ─── Log line row ─────────────────────────────────────────────────────────────

function LogLineRow({ line }: { line: CronLogLine }) {
  const [open, setOpen] = useState(false)
  const hasLongMessage = line.message.length > 220 || line.message.includes('\n')
  const preview = hasLongMessage ? line.message.slice(0, 220) + '…' : line.message

  return (
    <div className="border-b border-gray-100 last:border-b-0 hover:bg-gray-50 transition">
      <div
        className={`flex items-start gap-2 px-3 py-1.5 ${hasLongMessage ? 'cursor-pointer' : ''}`}
        onClick={hasLongMessage ? () => setOpen(!open) : undefined}
      >
        <span className="text-[11px] font-mono text-gray-400 whitespace-nowrap flex-shrink-0 mt-0.5">
          {line.ts}
        </span>
        <span
          className={`inline-block text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded border flex-shrink-0 ${levelClass(line.level)}`}
          style={{ minWidth: '54px', textAlign: 'center' }}
        >
          {line.level}
        </span>
        <span className="text-[11px] font-mono text-gray-400 flex-shrink-0 mt-0.5">
          {line.channel}
        </span>
        <pre className="text-xs text-gray-800 font-mono whitespace-pre-wrap break-all flex-1 leading-snug mt-0.5">
          {open ? line.message : preview}
        </pre>
        {hasLongMessage && (
          <span className="text-[10px] text-gray-400 flex-shrink-0 mt-1">
            {open ? '▲' : '▼'}
          </span>
        )}
      </div>
    </div>
  )
}

// ─── Main page ────────────────────────────────────────────────────────────────

export default function CronLogsPage() {
  const [scope, setScope]       = useState<LogScope>('all')
  const [cronName, setCronName] = useState<string>('')
  const [level, setLevel]       = useState<'' | 'info' | 'warning' | 'error'>('')
  const [search, setSearch]     = useState<string>('')
  const [lines, setLines]       = useState<number>(500)
  const [autoRefresh, setAutoRefresh] = useState<boolean>(false)

  const { data: names = [], isLoading: namesLoading } = useCronLogNames()

  const params = useMemo(() => ({
    scope,
    cron_name: cronName || undefined,
    level:     (level || undefined) as 'info' | 'warning' | 'error' | undefined,
    search:    search || undefined,
    lines,
  }), [scope, cronName, level, search, lines])

  const { data, isLoading, isError, error, refetch, isFetching } = useCronLogTail(params, {
    refetchMs: autoRefresh ? 30 * 1000 : undefined,
  })

  const logLines = data?.lines ?? []

  return (
    <div className="flex flex-col h-full">
      {/* ── Fixed header ── */}
      <div className="flex-shrink-0 px-6 pt-5 pb-3 bg-white border-b border-gray-200 space-y-3">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-xl font-bold text-gray-900">Application Logs</h1>
            <p className="text-sm text-gray-500 mt-0.5">
              Live tail of backend + cron Laravel logs (shared EFS)
              {data?.file_modified && (
                <span className="ml-2 text-xs text-gray-400">
                  · file modified {new Date(data.file_modified).toLocaleString('en-GB')}
                  {data.file_size != null && ` · ${formatBytes(data.file_size)}`}
                </span>
              )}
            </p>
          </div>
          <div className="flex items-center gap-2">
            <label className="flex items-center gap-2 text-xs text-gray-600 cursor-pointer select-none">
              <input
                type="checkbox"
                checked={autoRefresh}
                onChange={(e) => setAutoRefresh(e.target.checked)}
                className="rounded border-gray-300"
              />
              Auto-refresh (30s)
            </label>
            <button
              onClick={() => refetch()}
              disabled={isFetching}
              className="text-xs bg-brand-navy text-white rounded px-3 py-1.5 hover:bg-brand-navy/90 disabled:opacity-50 transition"
            >
              {isFetching ? 'Refreshing…' : 'Refresh'}
            </button>
          </div>
        </div>

        {/* Scope toggle — All / Cron-only / App-only. Selecting a specific
            cron below implicitly scopes to cron entries, so we disable the
            cron picker when scope=app to avoid contradictory filters. */}
        <div className="flex items-center gap-2">
          <span className="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Scope</span>
          {([
            { v: 'all',  label: 'All' },
            { v: 'app',  label: 'App server' },
            { v: 'cron', label: 'Cron only' },
          ] as const).map((opt) => (
            <button
              key={opt.v}
              type="button"
              onClick={() => {
                setScope(opt.v)
                if (opt.v === 'app') setCronName('')
              }}
              className={
                'text-xs px-3 py-1 rounded border transition ' +
                (scope === opt.v
                  ? 'bg-brand-navy text-white border-brand-navy'
                  : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50')
              }
            >
              {opt.label}
            </button>
          ))}
        </div>

        {/* Filters */}
        <div className="flex flex-wrap items-center gap-3">
          <div className="flex flex-col gap-0.5">
            <label className="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Cron</label>
            <select
              value={cronName}
              onChange={(e) => setCronName(e.target.value)}
              className="text-sm border border-gray-300 rounded px-2 py-1.5 min-w-[240px] focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
              disabled={namesLoading || scope === 'app'}
              title={scope === 'app' ? 'Cron filter disabled while scope = App server' : undefined}
            >
              <option value="">All crons</option>
              {names.map((n) => (
                <option key={n} value={n}>{n}</option>
              ))}
            </select>
          </div>

          <div className="flex flex-col gap-0.5">
            <label className="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Level</label>
            <select
              value={level}
              onChange={(e) => setLevel(e.target.value as '' | 'info' | 'warning' | 'error')}
              className="text-sm border border-gray-300 rounded px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">All levels</option>
              <option value="error">Error</option>
              <option value="warning">Warning</option>
              <option value="info">Info</option>
            </select>
          </div>

          <div className="flex flex-col gap-0.5 flex-1 min-w-[200px] max-w-md">
            <label className="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Search</label>
            <input
              type="search"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Substring in message (e.g. SQLSTATE, policy 153455)"
              className="text-sm border border-gray-300 rounded px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div className="flex flex-col gap-0.5">
            <label className="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Lines</label>
            <select
              value={lines}
              onChange={(e) => setLines(Number(e.target.value))}
              className="text-sm border border-gray-300 rounded px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value={100}>100</option>
              <option value={500}>500</option>
              <option value={1000}>1,000</option>
              <option value={2000}>2,000</option>
              <option value={5000}>5,000</option>
            </select>
          </div>

          {data && (
            <div className="ml-auto text-xs text-gray-500 self-end pb-1">
              {data.returned_count ?? logLines.length} of {data.matched_count ?? logLines.length} matched
              {data.truncated && (
                <span className="ml-2 text-amber-600" title="Only the last 4 MB of the log file was scanned">
                  · scan truncated
                </span>
              )}
            </div>
          )}
        </div>
      </div>

      {/* ── Log body ── */}
      <div className="flex-1 overflow-hidden px-6 pb-4 pt-2">
        <div className="flex flex-col rounded-lg border border-gray-200 bg-white max-h-[calc(100vh-260px)]">
          <div className="overflow-y-auto flex-1">
            {isLoading && (
              <div className="flex items-center justify-center py-20">
                <div className="w-8 h-8 border-2 border-brand-navy border-t-transparent rounded-full animate-spin" />
              </div>
            )}

            {isError && (
              <div className="flex items-center justify-center py-12">
                <div className="text-center max-w-lg px-4">
                  <div className="text-red-500 text-sm font-medium mb-1">Failed to load cron logs</div>
                  <div className="text-gray-500 text-xs whitespace-pre-wrap">
                    {(() => {
                      // Surface the backend's `error` field over axios's generic
                      // "Request failed with status code NNN" — the backend
                      // attaches a human-readable reason (e.g. "Could not reach
                      // cron log endpoint: …") that's far more useful for triage.
                      const err = error as { response?: { data?: { error?: string } }; message?: string } | undefined
                      return err?.response?.data?.error ?? err?.message ?? 'Unknown error'
                    })()}
                  </div>
                </div>
              </div>
            )}

            {!isLoading && !isError && data?.error && (
              <div className="flex items-center justify-center py-12">
                <div className="text-center max-w-lg px-4">
                  <div className="text-red-500 text-sm font-medium mb-1">Cron log endpoint error</div>
                  <div className="text-gray-500 text-xs whitespace-pre-wrap">{data.error}</div>
                </div>
              </div>
            )}

            {!isLoading && !isError && !data?.error && logLines.length === 0 && (
              <div className="flex items-center justify-center py-20 text-gray-400 text-sm">
                {data?.message ?? 'No log lines match the current filters.'}
              </div>
            )}

            {!isLoading && !isError && logLines.length > 0 && (
              <div className="divide-y divide-gray-100">
                {logLines.map((line, i) => (
                  <LogLineRow key={`${line.ts}-${i}`} line={line} />
                ))}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
