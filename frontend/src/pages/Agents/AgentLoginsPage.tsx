import { useState, useEffect } from 'react'
import apiClient from '../../api/client'
import { fmtDateTime } from '../../utils/format'
import EmptyState from '../../components/common/EmptyState'

interface AgentLogin {
  agent_id: number
  agent_name: string
  store_id: number | null
  login_date: string
  last_activity: string | null
}

export default function AgentLoginsPage() {
  const [logins, setLogins] = useState<AgentLogin[]>([])
  const [loading, setLoading] = useState(true)
  const [page, setPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [search, setSearch] = useState('')
  const perPage = 25

  useEffect(() => {
    fetchLogins()
  }, [page, perPage, search])

  const fetchLogins = async () => {
    try {
      setLoading(true)
      const response = await apiClient.get('/agent-logins', {
        params: { page, per_page: perPage, search: search || undefined },
      })
      setLogins(response.data.data || [])
      setTotal(response.data.meta?.total || 0)
    } catch (error) {
      console.error('Failed to fetch agent logins:', error)
    } finally {
      setLoading(false)
    }
  }

  const formatDate = (dateStr: string | null) => fmtDateTime(dateStr)

  const lastPage = Math.ceil(total / perPage)

  return (
    <div className="p-6 bg-white rounded-lg shadow">
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-2xl font-bold text-gray-800">Agent Logins</h1>
      </div>

      {/* Search */}
      <div className="mb-4 flex gap-2">
        <input
          type="text"
          placeholder="Search by agent name or email..."
          value={search}
          onChange={e => { setSearch(e.target.value); setPage(1) }}
          className="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
        />
      </div>

      {/* Table */}
      {loading ? (
        <div className="text-center py-8 text-gray-500">Loading...</div>
      ) : logins.length === 0 ? (
        <EmptyState title="No agent logins found" description="Try adjusting your filters or search terms." />
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 border-b">
              <tr>
                <th className="px-4 py-2 text-left text-gray-600 font-medium">Agent ID</th>
                <th className="px-4 py-2 text-left text-gray-600 font-medium">Agent Name</th>
                <th className="px-4 py-2 text-left text-gray-600 font-medium">Store</th>
                <th className="px-4 py-2 text-left text-gray-600 font-medium">Login Date</th>
                <th className="px-4 py-2 text-left text-gray-600 font-medium">Last Activity</th>
              </tr>
            </thead>
            <tbody>
              {logins.map((login, idx) => (
                <tr key={idx} className="border-b hover:bg-gray-50">
                  <td className="px-4 py-2">{login.agent_id}</td>
                  <td className="px-4 py-2">{login.agent_name || '—'}</td>
                  <td className="px-4 py-2">{login.store_id || '—'}</td>
                  <td className="px-4 py-2 text-gray-600 text-xs">{formatDate(login.login_date)}</td>
                  <td className="px-4 py-2 text-gray-600 text-xs">{formatDate(login.last_activity)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Pagination */}
      <div className="mt-4 flex items-center justify-between text-sm">
        <div>
          Showing {logins.length > 0 ? (page - 1) * perPage + 1 : 0} to {Math.min(page * perPage, total)} of {total}
        </div>
        <div className="flex gap-2">
          <button
            onClick={() => setPage(p => Math.max(1, p - 1))}
            disabled={page === 1}
            className="px-3 py-1 border rounded disabled:opacity-50">
            Previous
          </button>
          <span className="px-3 py-1">{page} / {lastPage || 1}</span>
          <button
            onClick={() => setPage(p => p + 1)}
            disabled={page >= lastPage}
            className="px-3 py-1 border rounded disabled:opacity-50">
            Next
          </button>
        </div>
      </div>
    </div>
  )
}
