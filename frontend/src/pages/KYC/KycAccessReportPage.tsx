import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useKycAccessReport } from '../../hooks/useKyc'
import { exportKycAccessReport, type KycAccessReportFilters } from '../../api/kycAccessReport'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import Pagination from '../../components/common/Pagination'

// Status → badge colour. Mirrors the four current_status values the
// backend SQL emits (see KycAccessReportController::getKycAccessUsers).
const STATUS_BADGE: Record<string, string> = {
  'Active': 'bg-green-100 text-green-700',
  'No Active Role': 'bg-amber-100 text-amber-700',
  'Activity Only (No Permission)': 'bg-blue-100 text-blue-700',
  'No KYC Permission': 'bg-gray-100 text-gray-500',
}

// Default range = last 1 year up to today. A narrower default window keeps
// the audits scan small; users can widen the range via the date filters.
function isoDate(d: Date): string {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}
const TODAY = new Date()
const DEFAULT_TO = isoDate(TODAY)
const DEFAULT_FROM = isoDate(new Date(TODAY.getFullYear() - 1, TODAY.getMonth(), TODAY.getDate()))

const PER_PAGE = 10

export default function KycAccessReportPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [isExporting, setIsExporting] = useState(false)

  const fromDate = searchParams.get('from_date') || DEFAULT_FROM
  const toDate = searchParams.get('to_date') || DEFAULT_TO

  const filters: KycAccessReportFilters = {
    from_date: fromDate,
    to_date: toDate,
    search: searchParams.get('search') || undefined,
    per_page: PER_PAGE,
    page: Number(searchParams.get('page') || '1'),
  }

  const { data, isLoading, isFetching } = useKycAccessReport(filters)

  function updateFilter(key: string, value: string) {
    const next = new URLSearchParams(searchParams)
    if (value) next.set(key, value)
    else next.delete(key)
    next.delete('page')
    setSearchParams(next)
  }

  function goToPage(page: number) {
    const next = new URLSearchParams(searchParams)
    next.set('page', String(page))
    setSearchParams(next)
  }

  function resetFilters() {
    const next = new URLSearchParams(searchParams)
    next.set('from_date', DEFAULT_FROM)
    next.set('to_date', DEFAULT_TO)
    next.delete('search')
    next.delete('page')
    setSearchParams(next)
  }

  async function handleExport() {
    if (isExporting) return
    setIsExporting(true)
    try {
      await exportKycAccessReport({ from_date: fromDate, to_date: toDate })
    } catch (err) {
      console.error('KYC access report export failed', err)
      alert('Export failed. Please try again.')
    } finally {
      setIsExporting(false)
    }
  }

  const meta = data?.meta
  const rows = data?.data ?? []

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-800">KYC Access Audit Report</h1>
        <button
          onClick={handleExport}
          disabled={isExporting}
          className="flex items-center gap-1.5 px-3 py-1.5 text-sm border border-green-300 text-green-700 rounded-md hover:bg-green-50 disabled:opacity-40 transition"
          title="Export current filter to Excel"
        >
          {isExporting ? (
            <>
              <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              Exporting…
            </>
          ) : (
            <>
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
              Export Excel
            </>
          )}
        </button>
      </div>

      {/* Info banner — mirrors the V8 report description. */}
      <div className="flex items-start gap-2 bg-blue-50 border border-blue-200 text-blue-800 text-sm rounded-md px-4 py-3">
        <svg className="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        <p>
          This report shows all users who currently hold KYC verification permissions
          (<code className="px-1 bg-blue-100 rounded">customer-kyc-edit</code>,
          {' '}<code className="px-1 bg-blue-100 rounded">customer-kyc-list</code>,
          {' '}<code className="px-1 bg-blue-100 rounded">kyc-compliance-edit</code>, etc.) and any user who
          has modified KYC records within the selected date range. Default range is the last 1 year.
        </p>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap gap-3 items-end">
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">From Date</label>
          <input
            type="date"
            value={fromDate}
            onChange={e => updateFilter('from_date', e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">To Date</label>
          <input
            type="date"
            value={toDate}
            onChange={e => updateFilter('to_date', e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Search</label>
          <input
            type="text"
            placeholder="Name, email, role, permission…"
            defaultValue={filters.search}
            onKeyDown={e => { if (e.key === 'Enter') updateFilter('search', (e.target as HTMLInputElement).value) }}
            onBlur={e => updateFilter('search', e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm w-64 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
        <button
          onClick={resetFilters}
          className="px-3 py-1.5 text-sm border border-gray-300 text-gray-600 rounded-md hover:bg-gray-50 transition"
          title="Reset to the last 1 year"
        >
          Reset (Last 1 Year)
        </button>
      </div>

      {/* Table */}
      <div className="bg-white rounded-lg shadow-sm border overflow-hidden relative">
        {(isLoading || isFetching) && (
          <div className="absolute inset-0 bg-white/50 z-10 flex items-center justify-center">
            <LoadingSpinner size="md" />
          </div>
        )}
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-600 uppercase text-xs tracking-wider">
              <tr>
                <th className="px-4 py-3 text-left">User ID</th>
                <th className="px-4 py-3 text-left">Full Name</th>
                <th className="px-4 py-3 text-left">Email</th>
                <th className="px-4 py-3 text-left">Roles</th>
                <th className="px-4 py-3 text-left">KYC Permissions</th>
                <th className="px-4 py-3 text-left">Current Status</th>
                <th className="px-4 py-3 text-right">Total KYC Actions</th>
                <th className="px-4 py-3 text-left">First Action</th>
                <th className="px-4 py-3 text-left">Last Action</th>
                <th className="px-4 py-3 text-left">User Created</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {isLoading ? (
                <tr><td colSpan={10} className="px-4 py-12 text-center"><LoadingSpinner size="md" /></td></tr>
              ) : rows.length === 0 ? (
                <tr>
                  <td colSpan={10} className="px-4 py-14 text-center text-gray-400 text-sm">
                    No users match the current filters.
                  </td>
                </tr>
              ) : (
                rows.map(row => (
                  <tr key={row.id} className="hover:bg-gray-50 transition align-top">
                    <td className="px-4 py-2 text-gray-700">{row.id}</td>
                    <td className="px-4 py-2 font-medium text-gray-800 whitespace-nowrap">{row.full_name}</td>
                    <td className="px-4 py-2 text-gray-600">{row.email}</td>
                    <td className="px-4 py-2 text-gray-600 text-xs">{row.roles}</td>
                    <td className="px-4 py-2 text-gray-600 text-xs">{row.kyc_permissions}</td>
                    <td className="px-4 py-2">
                      <span className={`px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap ${STATUS_BADGE[row.current_status] ?? 'bg-gray-100 text-gray-600'}`}>
                        {row.current_status}
                      </span>
                    </td>
                    <td className="px-4 py-2 text-right text-gray-700">{row.total_kyc_actions}</td>
                    <td className="px-4 py-2 text-gray-500 text-xs whitespace-nowrap">{row.first_action}</td>
                    <td className="px-4 py-2 text-gray-500 text-xs whitespace-nowrap">{row.last_action}</td>
                    <td className="px-4 py-2 text-gray-500 text-xs whitespace-nowrap">{row.user_created}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {meta && (
          <Pagination
            currentPage={meta.current_page}
            lastPage={meta.last_page}
            total={meta.total}
            from={meta.from}
            to={meta.to}
            onPageChange={goToPage}
          />
        )}
      </div>
    </div>
  )
}
