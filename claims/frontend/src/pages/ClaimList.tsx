import { useEffect, useState, useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import apiClient from '../api/client'

interface Claim {
  id: number
  claim_number: string
  policy_number: string
  customer_name: string
  claim_type: string
  status: string
  priority: string
  reserve: number
  paid: number
  balance: number
  date_of_loss: string
  created_at: string
  product: string
}

const statusOptions = ['New', 'Pending Assessment', 'Under Review', 'Approved', 'Rejected', 'Settled', 'Closed']
const priorityOptions = ['Low', 'Medium', 'High', 'Critical']

const statusColors: Record<string, string> = {
  'New': 'bg-blue-100 text-blue-700',
  'Pending Assessment': 'bg-yellow-100 text-yellow-700',
  'Under Review': 'bg-orange-100 text-orange-700',
  'Approved': 'bg-green-100 text-green-700',
  'Rejected': 'bg-red-100 text-red-700',
  'Settled': 'bg-emerald-100 text-emerald-700',
  'Closed': 'bg-gray-100 text-gray-600',
}

const priorityColors: Record<string, string> = {
  'Low': 'bg-gray-100 text-gray-600',
  'Medium': 'bg-blue-100 text-blue-700',
  'High': 'bg-orange-100 text-orange-700',
  'Critical': 'bg-red-100 text-red-700',
}

const kanbanColumns = ['New', 'Pending Assessment', 'Approved', 'Rejected', 'Closed']
const kanbanColumnColors: Record<string, string> = {
  'New': 'border-blue-400',
  'Pending Assessment': 'border-yellow-400',
  'Approved': 'border-green-400',
  'Rejected': 'border-red-400',
  'Closed': 'border-gray-400',
}

function formatCurrency(amount: number): string {
  return 'P ' + amount.toLocaleString('en-BW', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

export default function ClaimList() {
  const [claims, setClaims] = useState<Claim[]>([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState<string[]>([])
  const [typeFilter, setTypeFilter] = useState('')
  const [priorityFilter, setPriorityFilter] = useState('')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [view, setView] = useState<'table' | 'kanban'>('table')
  const [page, setPage] = useState(1)
  const [totalPages, setTotalPages] = useState(1)
  const navigate = useNavigate()

  const fetchClaims = useCallback(() => {
    setLoading(true)
    const params: Record<string, string> = { page: String(page) }
    if (search) params.search = search
    if (statusFilter.length) params.status = statusFilter.join(',')
    if (typeFilter) params.claim_type = typeFilter
    if (priorityFilter) params.priority = priorityFilter
    if (dateFrom) params.date_from = dateFrom
    if (dateTo) params.date_to = dateTo

    apiClient.get('/claims-v2', { params })
      .then(r => {
        setClaims(r.data.data || r.data)
        setTotalPages(r.data.last_page || 1)
      })
      .catch(() => {
        // Mock data for dev
        setClaims([
          { id: 1, claim_number: 'CLM-2026-0451', policy_number: 'POL-10234', customer_name: 'Kgomotso Holdings', claim_type: 'Motor Accident', status: 'Under Review', priority: 'High', reserve: 450000, paid: 120000, balance: 330000, date_of_loss: '2026-03-15', created_at: '2026-03-16', product: 'Motor' },
          { id: 2, claim_number: 'CLM-2026-0447', policy_number: 'POL-10198', customer_name: 'BW Mining Corp', claim_type: 'Fire', status: 'Approved', priority: 'Critical', reserve: 380000, paid: 0, balance: 380000, date_of_loss: '2026-03-10', created_at: '2026-03-11', product: 'Commercial' },
          { id: 3, claim_number: 'CLM-2026-0443', policy_number: 'POL-10156', customer_name: 'Gaborone Motors', claim_type: 'Theft', status: 'Pending Assessment', priority: 'Medium', reserve: 275000, paid: 50000, balance: 225000, date_of_loss: '2026-03-05', created_at: '2026-03-06', product: 'Motor' },
          { id: 4, claim_number: 'CLM-2026-0440', policy_number: 'POL-10289', customer_name: 'TechBW Solutions', claim_type: 'Burglary', status: 'New', priority: 'High', reserve: 195000, paid: 0, balance: 195000, date_of_loss: '2026-03-01', created_at: '2026-03-02', product: 'Burglary' },
          { id: 5, claim_number: 'CLM-2026-0435', policy_number: 'POL-10312', customer_name: 'Safari Lodges Ltd', claim_type: 'Natural Disaster', status: 'Under Review', priority: 'Medium', reserve: 160000, paid: 30000, balance: 130000, date_of_loss: '2026-02-25', created_at: '2026-02-26', product: 'Commercial' },
          { id: 6, claim_number: 'CLM-2026-0430', policy_number: 'POL-10345', customer_name: 'J. Mokaleng', claim_type: 'Motor Accident', status: 'Closed', priority: 'Low', reserve: 85000, paid: 85000, balance: 0, date_of_loss: '2026-02-20', created_at: '2026-02-21', product: 'Motor' },
          { id: 7, claim_number: 'CLM-2026-0425', policy_number: 'POL-10367', customer_name: 'Francistown Hardware', claim_type: 'Liability', status: 'Rejected', priority: 'Low', reserve: 0, paid: 0, balance: 0, date_of_loss: '2026-02-18', created_at: '2026-02-19', product: 'Commercial' },
          { id: 8, claim_number: 'CLM-2026-0420', policy_number: 'POL-10390', customer_name: 'M. Kgosimore', claim_type: 'Health', status: 'Approved', priority: 'Medium', reserve: 55000, paid: 55000, balance: 0, date_of_loss: '2026-02-15', created_at: '2026-02-16', product: 'Health' },
        ])
        setTotalPages(3)
      })
      .finally(() => setLoading(false))
  }, [page, search, statusFilter, typeFilter, priorityFilter, dateFrom, dateTo])

  useEffect(() => {
    fetchClaims()
  }, [fetchClaims])

  const toggleStatus = (s: string) => {
    setStatusFilter(prev =>
      prev.includes(s) ? prev.filter(x => x !== s) : [...prev, s]
    )
    setPage(1)
  }

  const filtered = claims.filter(c => {
    if (search) {
      const q = search.toLowerCase()
      if (!c.claim_number.toLowerCase().includes(q) && !c.policy_number.toLowerCase().includes(q) && !c.customer_name.toLowerCase().includes(q)) return false
    }
    if (statusFilter.length && !statusFilter.includes(c.status)) return false
    if (typeFilter && c.claim_type !== typeFilter) return false
    if (priorityFilter && c.priority !== priorityFilter) return false
    return true
  })

  const claimTypes = [...new Set(claims.map(c => c.claim_type))]

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-800">All Claims</h1>
        <button
          onClick={() => navigate('/claims/new')}
          className="px-4 py-2 bg-claims-primary text-white rounded-lg text-sm font-medium hover:bg-claims-dark transition"
        >
          + New Claim
        </button>
      </div>

      {/* Search & Filters */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-4 space-y-3">
        <div className="flex flex-wrap items-center gap-3">
          <div className="flex-1 min-w-[240px]">
            <div className="relative">
              <svg className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
              <input
                type="text"
                placeholder="Search claim #, policy #, customer..."
                className="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-claims-primary/30 focus:border-claims-primary"
                value={search}
                onChange={e => { setSearch(e.target.value); setPage(1) }}
              />
            </div>
          </div>
          <select
            className="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-claims-primary/30"
            value={typeFilter}
            onChange={e => { setTypeFilter(e.target.value); setPage(1) }}
          >
            <option value="">All Types</option>
            {claimTypes.map(t => <option key={t} value={t}>{t}</option>)}
          </select>
          <select
            className="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-claims-primary/30"
            value={priorityFilter}
            onChange={e => { setPriorityFilter(e.target.value); setPage(1) }}
          >
            <option value="">All Priorities</option>
            {priorityOptions.map(p => <option key={p} value={p}>{p}</option>)}
          </select>
          <input type="date" className="px-3 py-2 border border-gray-200 rounded-lg text-sm" value={dateFrom} onChange={e => setDateFrom(e.target.value)} />
          <input type="date" className="px-3 py-2 border border-gray-200 rounded-lg text-sm" value={dateTo} onChange={e => setDateTo(e.target.value)} />
          <div className="flex bg-gray-100 rounded-lg p-0.5">
            <button
              onClick={() => setView('table')}
              className={`px-3 py-1.5 rounded-md text-xs font-medium transition ${view === 'table' ? 'bg-white shadow-sm text-gray-800' : 'text-gray-500'}`}
            >
              Table
            </button>
            <button
              onClick={() => setView('kanban')}
              className={`px-3 py-1.5 rounded-md text-xs font-medium transition ${view === 'kanban' ? 'bg-white shadow-sm text-gray-800' : 'text-gray-500'}`}
            >
              Kanban
            </button>
          </div>
        </div>

        {/* Status Filter Badges */}
        <div className="flex flex-wrap gap-2">
          {statusOptions.map(s => (
            <button
              key={s}
              onClick={() => toggleStatus(s)}
              className={`px-3 py-1 rounded-full text-xs font-medium border transition ${
                statusFilter.includes(s)
                  ? 'bg-claims-primary text-white border-claims-primary'
                  : 'bg-white text-gray-600 border-gray-200 hover:border-claims-primary/40'
              }`}
            >
              {s}
            </button>
          ))}
        </div>
      </div>

      {loading ? (
        <div className="flex items-center justify-center h-32">
          <div className="animate-spin w-8 h-8 border-4 border-claims-primary border-t-transparent rounded-full" />
        </div>
      ) : view === 'table' ? (
        /* TABLE VIEW */
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-xs text-gray-500 bg-gray-50 border-b border-gray-100">
                  <th className="px-4 py-3 font-medium">Claim #</th>
                  <th className="px-4 py-3 font-medium">Policy #</th>
                  <th className="px-4 py-3 font-medium">Customer</th>
                  <th className="px-4 py-3 font-medium">Type</th>
                  <th className="px-4 py-3 font-medium">Status</th>
                  <th className="px-4 py-3 font-medium">Priority</th>
                  <th className="px-4 py-3 font-medium text-right">Reserve</th>
                  <th className="px-4 py-3 font-medium text-right">Paid</th>
                  <th className="px-4 py-3 font-medium text-right">Balance</th>
                  <th className="px-4 py-3 font-medium">Date of Loss</th>
                  <th className="px-4 py-3 font-medium">Created</th>
                </tr>
              </thead>
              <tbody>
                {filtered.map(claim => (
                  <tr
                    key={claim.id}
                    onClick={() => navigate(`/claims/${claim.id}`)}
                    className="border-b border-gray-50 hover:bg-gray-50 cursor-pointer transition"
                  >
                    <td className="px-4 py-3 font-medium text-claims-primary">{claim.claim_number}</td>
                    <td className="px-4 py-3 text-gray-600">{claim.policy_number}</td>
                    <td className="px-4 py-3 text-gray-700 font-medium">{claim.customer_name}</td>
                    <td className="px-4 py-3 text-gray-600">{claim.claim_type}</td>
                    <td className="px-4 py-3">
                      <span className={`inline-block px-2.5 py-0.5 rounded-full text-xs font-medium ${statusColors[claim.status] || 'bg-gray-100 text-gray-600'}`}>
                        {claim.status}
                      </span>
                    </td>
                    <td className="px-4 py-3">
                      <span className={`inline-block px-2.5 py-0.5 rounded-full text-xs font-medium ${priorityColors[claim.priority] || 'bg-gray-100 text-gray-600'}`}>
                        {claim.priority}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-right font-medium">{formatCurrency(claim.reserve)}</td>
                    <td className="px-4 py-3 text-right text-gray-600">{formatCurrency(claim.paid)}</td>
                    <td className="px-4 py-3 text-right font-medium text-gray-800">{formatCurrency(claim.balance)}</td>
                    <td className="px-4 py-3 text-gray-600">{claim.date_of_loss}</td>
                    <td className="px-4 py-3 text-gray-500">{claim.created_at}</td>
                  </tr>
                ))}
                {filtered.length === 0 && (
                  <tr>
                    <td colSpan={11} className="px-4 py-8 text-center text-gray-400">No claims found</td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          <div className="flex items-center justify-between px-4 py-3 border-t border-gray-100 bg-gray-50">
            <span className="text-sm text-gray-500">Page {page} of {totalPages}</span>
            <div className="flex gap-2">
              <button
                onClick={() => setPage(Math.max(1, page - 1))}
                disabled={page === 1}
                className="px-3 py-1.5 border border-gray-200 rounded-lg text-sm disabled:opacity-40 hover:bg-white transition"
              >
                Previous
              </button>
              <button
                onClick={() => setPage(Math.min(totalPages, page + 1))}
                disabled={page === totalPages}
                className="px-3 py-1.5 border border-gray-200 rounded-lg text-sm disabled:opacity-40 hover:bg-white transition"
              >
                Next
              </button>
            </div>
          </div>
        </div>
      ) : (
        /* KANBAN VIEW */
        <div className="flex gap-4 overflow-x-auto pb-4">
          {kanbanColumns.map(col => {
            const colClaims = filtered.filter(c => c.status === col || (col === 'Closed' && (c.status === 'Closed' || c.status === 'Settled')))
            return (
              <div key={col} className={`flex-shrink-0 w-72 bg-gray-50 rounded-xl border-t-4 ${kanbanColumnColors[col] || 'border-gray-400'}`}>
                <div className="px-4 py-3 border-b border-gray-200">
                  <div className="flex items-center justify-between">
                    <h3 className="text-sm font-semibold text-gray-700">{col}</h3>
                    <span className="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full">{colClaims.length}</span>
                  </div>
                </div>
                <div className="p-3 space-y-3 max-h-[calc(100vh-320px)] overflow-y-auto">
                  {colClaims.map(claim => (
                    <div
                      key={claim.id}
                      onClick={() => navigate(`/claims/${claim.id}`)}
                      className="bg-white rounded-lg shadow-sm border border-gray-100 p-3 cursor-pointer hover:shadow-md transition"
                    >
                      <div className="flex items-center justify-between mb-2">
                        <span className="text-xs font-semibold text-claims-primary">{claim.claim_number}</span>
                        <span className={`px-2 py-0.5 rounded-full text-[10px] font-medium ${priorityColors[claim.priority]}`}>
                          {claim.priority}
                        </span>
                      </div>
                      <p className="text-sm font-medium text-gray-800 mb-1">{claim.customer_name}</p>
                      <p className="text-xs text-gray-500 mb-2">{claim.claim_type}</p>
                      <div className="flex items-center justify-between text-xs">
                        <span className="text-gray-500">{claim.date_of_loss}</span>
                        <span className="font-semibold text-gray-700">{formatCurrency(claim.reserve)}</span>
                      </div>
                    </div>
                  ))}
                  {colClaims.length === 0 && (
                    <p className="text-xs text-gray-400 text-center py-4">No claims</p>
                  )}
                </div>
              </div>
            )
          })}
        </div>
      )}
    </div>
  )
}
