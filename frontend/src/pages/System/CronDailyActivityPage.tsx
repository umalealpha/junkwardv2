import { useState, useEffect, useCallback } from 'react'
import { getDailyActivity } from '../../api/cronKernel'
import type { DailyActivityRow, DailyActivityMeta } from '../../api/cronKernel'
import apiClient from '../../api/client'

// ─── Helpers ──────────────────────────────────────────────────────────────────

function today(): string {
  return new Date().toISOString().slice(0, 10)
}

function fmtTime(iso: string | null): string {
  if (!iso) return '—'
  const d = new Date(iso)
  if (isNaN(d.getTime())) return iso
  return d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false })
}

function fmtDuration(sec: number | null): string {
  if (sec === null) return '—'
  if (sec < 60) return `${sec}s`
  const m = Math.floor(sec / 60)
  const s = sec % 60
  if (m < 60) return `${m}m ${s}s`
  const h = Math.floor(m / 60)
  const rm = m % 60
  return `${h}h ${rm}m`
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function CronDailyActivityPage() {
  const [date, setDate]     = useState<string>(today())
  const [rows, setRows]     = useState<DailyActivityRow[]>([])
  const [meta, setMeta]     = useState<DailyActivityMeta | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError]   = useState<string | null>(null)
  const [downloading, setDownloading] = useState(false)
  const [search, setSearch] = useState('')

  const load = useCallback(async (d: string) => {
    setLoading(true)
    setError(null)
    try {
      const result = await getDailyActivity(d)
      setRows(result.data)
      setMeta(result.meta)
    } catch (e: any) {
      setError(e?.response?.data?.message ?? 'Failed to load data')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => { load(date) }, [date, load])

  const filtered = rows.filter(r =>
    !search || r.name.toLowerCase().includes(search.toLowerCase())
  )

  async function handleDownload() {
    setDownloading(true)
    try {
      const res = await apiClient.get('/cron-kernel/daily-activity/download', {
        params: { date },
        responseType: 'blob',
      })
      const url = URL.createObjectURL(new Blob([res.data], { type: 'text/csv' }))
      const a   = document.createElement('a')
      a.href    = url
      a.download = `cron_activity_${date}.csv`
      a.click()
      URL.revokeObjectURL(url)
    } catch {
      alert('Download failed')
    } finally {
      setDownloading(false)
    }
  }

  return (
    <div className="p-6 max-w-7xl mx-auto">
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Daily Cron Activity</h1>
          <p className="text-sm text-gray-500 mt-1">
            View all cron runs for a selected day. A summary email is sent to kkatolkar@alphadirect.co.bw every night at 23:55.
          </p>
        </div>
        <div className="flex items-center gap-3">
          <input
            type="date"
            value={date}
            max={today()}
            onChange={e => setDate(e.target.value)}
            className="border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          <button
            onClick={handleDownload}
            disabled={downloading || rows.length === 0}
            className="flex items-center gap-2 px-4 py-2 bg-green-600 text-white text-sm rounded-md hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {downloading ? (
              <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
              </svg>
            ) : (
              <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
              </svg>
            )}
            Download CSV
          </button>
        </div>
      </div>

      {/* Summary Cards */}
      {meta && (
        <div className="grid grid-cols-3 gap-4 mb-6">
          <div className="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
            <div className="text-3xl font-bold text-blue-600">{meta.total}</div>
            <div className="text-sm text-gray-500 mt-1">Total Runs</div>
          </div>
          <div className="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
            <div className="text-3xl font-bold text-green-600">{meta.completed}</div>
            <div className="text-sm text-gray-500 mt-1">Completed</div>
          </div>
          <div className="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
            <div className={`text-3xl font-bold ${meta.running > 0 ? 'text-red-500' : 'text-gray-400'}`}>
              {meta.running}
            </div>
            <div className="text-sm text-gray-500 mt-1">
              {meta.running > 0 ? 'Running / Incomplete' : 'None Incomplete'}
            </div>
          </div>
        </div>
      )}

      {/* Search */}
      <div className="mb-4">
        <input
          type="text"
          placeholder="Filter by cron name…"
          value={search}
          onChange={e => setSearch(e.target.value)}
          className="w-full max-w-sm border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
      </div>

      {/* Table */}
      <div className="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
        {loading ? (
          <div className="flex items-center justify-center py-20 text-gray-400">
            <svg className="animate-spin h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24">
              <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
              <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
            </svg>
            Loading…
          </div>
        ) : error ? (
          <div className="py-10 text-center text-red-500">{error}</div>
        ) : filtered.length === 0 ? (
          <div className="py-16 text-center text-gray-400">
            {search ? 'No matching cron runs.' : `No cron runs recorded for ${date}.`}
          </div>
        ) : (
          <table className="min-w-full divide-y divide-gray-200 text-sm">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide text-xs">#</th>
                <th className="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide text-xs">Cron Name</th>
                <th className="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide text-xs">Started</th>
                <th className="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide text-xs">Ended</th>
                <th className="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide text-xs">Duration</th>
                <th className="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide text-xs">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100 bg-white">
              {filtered.map((row, idx) => (
                <tr key={row.id} className="hover:bg-gray-50">
                  <td className="px-4 py-3 text-gray-400">{idx + 1}</td>
                  <td className="px-4 py-3 font-mono text-gray-800 text-xs">{row.name}</td>
                  <td className="px-4 py-3 text-gray-600">{fmtTime(row.start)}</td>
                  <td className="px-4 py-3 text-gray-600">{fmtTime(row.end)}</td>
                  <td className="px-4 py-3 text-gray-600">{fmtDuration(row.duration_sec)}</td>
                  <td className="px-4 py-3">
                    {row.completed ? (
                      <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                        <svg className="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                        </svg>
                        Completed
                      </span>
                    ) : (
                      <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-600">
                        <svg className="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        No End Time
                      </span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {!loading && !error && filtered.length > 0 && (
        <p className="text-xs text-gray-400 mt-3">
          Showing {filtered.length} of {rows.length} cron runs for {date}
          {search && ` matching "${search}"`}.
        </p>
      )}
    </div>
  )
}
