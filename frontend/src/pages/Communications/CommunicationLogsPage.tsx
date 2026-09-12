import { useState, useEffect } from 'react'
import apiClient from '../../api/client'
import EmptyState from '../../components/common/EmptyState'

interface LogEntry {
  id: number
  userId: number | null
  userName: string | null
  type: string
  channel: string
  status: string
  reason: string | null
  data: Record<string, any>
  createdAt: string
}

const STATUS_BADGE: Record<string, string> = {
  sent: 'bg-green-100 text-green-700',
  dispatched: 'bg-blue-100 text-blue-700',
  failed: 'bg-red-100 text-red-700',
  skipped: 'bg-gray-100 text-gray-500',
}

const CHANNEL_BADGE: Record<string, string> = {
  in_app: 'bg-blue-50 text-blue-600',
  email: 'bg-green-50 text-green-600',
  sms: 'bg-yellow-50 text-yellow-700',
  whatsapp: 'bg-emerald-50 text-emerald-700',
}

export default function CommunicationLogsPage() {
  const [logs, setLogs] = useState<LogEntry[]>([])
  const [loading, setLoading] = useState(true)
  const [filters, setFilters] = useState({ channel: '', status: '', type: '' })
  const [page, setPage] = useState(1)
  const [hasMore, setHasMore] = useState(false)

  const load = (p = 1) => {
    setLoading(true)
    const params: any = { page: p, per_page: 50 }
    if (filters.channel) params.channel = filters.channel
    if (filters.status) params.status = filters.status
    if (filters.type) params.type = filters.type

    apiClient.get('/communications/logs', { params })
      .then(r => {
        setLogs(r.data.data ?? [])
        setHasMore(r.data.meta?.has_more ?? false)
        setPage(p)
      })
      .catch(() => {})
      .finally(() => setLoading(false))
  }

  useEffect(() => { load(1) }, [filters])

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Notification Delivery Logs</h1>
          <p className="text-sm text-gray-500 mt-1">Track every notification dispatched by the system — email, SMS, WhatsApp, in-app.</p>
        </div>
        <button onClick={() => load(page)} disabled={loading}
          className="px-4 py-2 text-sm bg-gray-100 hover:bg-gray-200 rounded-lg border disabled:opacity-50">
          {loading ? 'Loading...' : 'Refresh'}
        </button>
      </div>

      {/* Filters */}
      <div className="flex gap-3 flex-wrap">
        <select value={filters.channel} onChange={e => setFilters({...filters, channel: e.target.value})}
          className="px-3 py-1.5 border rounded-md text-sm">
          <option value="">All Channels</option>
          <option value="in_app">In-App</option>
          <option value="email">Email</option>
          <option value="sms">SMS</option>
          <option value="whatsapp">WhatsApp</option>
        </select>
        <select value={filters.status} onChange={e => setFilters({...filters, status: e.target.value})}
          className="px-3 py-1.5 border rounded-md text-sm">
          <option value="">All Statuses</option>
          <option value="sent">Sent</option>
          <option value="dispatched">Dispatched</option>
          <option value="failed">Failed</option>
          <option value="skipped">Skipped</option>
        </select>
        <input value={filters.type} onChange={e => setFilters({...filters, type: e.target.value})}
          placeholder="Filter by type..." className="px-3 py-1.5 border rounded-md text-sm w-48" />
      </div>

      <div className="bg-white shadow rounded-lg overflow-hidden">
        <table className="min-w-full divide-y divide-gray-200 text-sm">
          <thead className="bg-gray-50">
            <tr>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Channel</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Message</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-200">
            {logs.length === 0 && !loading && (
              <tr><td colSpan={7} className="p-0"><EmptyState compact title="No logs found" description="Try adjusting your filters." /></td></tr>
            )}
            {logs.map(l => (
              <tr key={l.id} className="hover:bg-gray-50">
                <td className="px-4 py-2.5 font-mono text-xs text-gray-500">#{l.id}</td>
                <td className="px-4 py-2.5 font-mono text-xs">{l.type}</td>
                <td className="px-4 py-2.5">
                  <span className={`px-2 py-0.5 text-xs rounded-full font-medium ${CHANNEL_BADGE[l.channel] ?? 'bg-gray-100'}`}>{l.channel}</span>
                </td>
                <td className="px-4 py-2.5 text-gray-600">{l.userName ?? (l.userId ? `#${l.userId}` : '-')}</td>
                <td className="px-4 py-2.5">
                  <span className={`px-2 py-0.5 text-xs rounded-full font-medium ${STATUS_BADGE[l.status] ?? 'bg-gray-100'}`}>{l.status}</span>
                  {l.reason && <span className="ml-1 text-[10px] text-gray-400" title={l.reason}>{l.reason.slice(0, 40)}</span>}
                </td>
                <td className="px-4 py-2.5 text-xs text-gray-500 max-w-[200px] truncate">{l.data?.message ?? l.data?.title ?? '-'}</td>
                <td className="px-4 py-2.5 text-xs text-gray-400">{l.createdAt?.replace('T', ' ').slice(0, 19) ?? '-'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Pagination */}
      <div className="flex justify-between items-center">
        <button disabled={page <= 1} onClick={() => load(page - 1)}
          className="px-3 py-1.5 text-sm border rounded-md disabled:opacity-30 hover:bg-gray-50">Previous</button>
        <span className="text-sm text-gray-500">Page {page}</span>
        <button disabled={!hasMore} onClick={() => load(page + 1)}
          className="px-3 py-1.5 text-sm border rounded-md disabled:opacity-30 hover:bg-gray-50">Next</button>
      </div>
    </div>
  )
}
