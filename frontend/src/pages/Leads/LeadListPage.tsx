import { Fragment, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useUpdateWebsiteLeadStatus, useWebsiteLeads } from '../../hooks/useWebsiteLeads'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import EmptyState from '../../components/common/EmptyState'
import apiClient from '../../api/client'
import type { WebsiteLead, WebsiteLeadFilters } from '../../api/websiteLeads'
import { fmtDate } from '../../utils/format'

interface LeadFileRef {
  file: string
  original: string
}

function extractFiles(details: Record<string, unknown> | null): LeadFileRef[] {
  if (!details) return []
  const files: LeadFileRef[] = []
  const push = (v: unknown) => {
    if (v && typeof v === 'object' && typeof (v as LeadFileRef).file === 'string') {
      files.push({ file: (v as LeadFileRef).file, original: String((v as LeadFileRef).original ?? (v as LeadFileRef).file) })
    }
  }
  push(details.resume_file)
  if (Array.isArray(details.credential_files)) details.credential_files.forEach(push)
  return files
}

async function openLeadFile(ref: LeadFileRef) {
  const { data } = await apiClient.get(`/website-leads/files/${ref.file}`, { responseType: 'blob' })
  const url = URL.createObjectURL(data)
  const a = document.createElement('a')
  a.href = url
  a.download = ref.original
  a.click()
  setTimeout(() => URL.revokeObjectURL(url), 10_000)
}

const TYPE_BADGE: Record<string, string> = {
  quotation:  'bg-blue-100 text-blue-700',
  contact:    'bg-purple-100 text-purple-700',
  claim:      'bg-red-100 text-red-700',
  career:     'bg-teal-100 text-teal-700',
  newsletter: 'bg-gray-100 text-gray-600',
}

const STATUS_BADGE: Record<string, string> = {
  new:         'bg-green-100 text-green-700',
  in_progress: 'bg-yellow-100 text-yellow-700',
  resolved:    'bg-gray-100 text-gray-600',
  spam:        'bg-red-100 text-red-700',
}

const STATUS_OPTIONS: WebsiteLead['status'][] = ['new', 'in_progress', 'resolved', 'spam']

export default function LeadListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [expandedId, setExpandedId] = useState<number | null>(null)

  const filters: WebsiteLeadFilters = {
    type:     searchParams.get('type') || undefined,
    status:   searchParams.get('status') || undefined,
    search:   searchParams.get('search') || undefined,
    per_page: 25,
    page:     Number(searchParams.get('page') || '1'),
  }

  const { data, isLoading, isFetching } = useWebsiteLeads(filters)
  const updateStatus = useUpdateWebsiteLeadStatus()

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

  const meta = data?.meta
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
        <div>
          <h1 className="text-2xl font-bold text-ink">Website Leads</h1>
          <p className="text-sm text-ink-faint mt-0.5">
            Quotation enquiries, contact messages, claims, career applications and newsletter
            sign-ups submitted from the marketing website
          </p>
        </div>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap gap-3 items-end">
        <div>
          <label className="block text-xs font-medium text-ink-faint mb-1">Search</label>
          <input
            type="text"
            placeholder="Name, email, phone, product..."
            defaultValue={filters.search}
            onKeyDown={e => { if (e.key === 'Enter') updateFilter('search', (e.target as HTMLInputElement).value) }}
            onBlur={e => updateFilter('search', e.target.value)}
            className="px-3 py-1.5 border border-line rounded-md text-sm w-64 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
        <div>
          <label className="block text-xs font-medium text-ink-faint mb-1">Type</label>
          <select
            value={filters.type ?? ''}
            onChange={e => updateFilter('type', e.target.value)}
            className="px-3 py-1.5 border border-line rounded-md text-sm focus:ring-1 focus:ring-blue-500"
          >
            <option value="">All Types</option>
            <option value="quotation">Quotation</option>
            <option value="contact">Contact</option>
            <option value="claim">Claim</option>
            <option value="career">Career</option>
            <option value="newsletter">Newsletter</option>
          </select>
        </div>
        <div>
          <label className="block text-xs font-medium text-ink-faint mb-1">Status</label>
          <select
            value={filters.status ?? ''}
            onChange={e => updateFilter('status', e.target.value)}
            className="px-3 py-1.5 border border-line rounded-md text-sm focus:ring-1 focus:ring-blue-500"
          >
            <option value="">All Status</option>
            <option value="new">New</option>
            <option value="in_progress">In Progress</option>
            <option value="resolved">Resolved</option>
            <option value="spam">Spam</option>
          </select>
        </div>
      </div>

      {/* Table */}
      <div className="bg-surface rounded-lg shadow-sm border border-line overflow-hidden">
        {(isLoading || isFetching) && (
          <div className="absolute inset-0 bg-white/50 z-10 flex items-center justify-center">
            <LoadingSpinner size="md" />
          </div>
        )}
        <div className="overflow-x-auto relative">
          <table className="w-full text-sm table-sticky-header">
            <thead className="bg-surface-2 text-ink-muted uppercase text-xs tracking-wider">
              <tr>
                <th className="px-4 py-2 text-left">Type</th>
                <th className="px-4 py-2 text-left">Name</th>
                <th className="px-4 py-2 text-left">Email / Phone</th>
                <th className="px-4 py-2 text-left">Product</th>
                <th className="px-4 py-2 text-left">Status</th>
                <th className="px-4 py-2 text-left">Submitted</th>
                <th className="px-4 py-2 text-left">Details</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {isLoading ? (
                <tr><td colSpan={7} className="px-4 py-12 text-center"><LoadingSpinner size="md" /></td></tr>
              ) : data?.data.length === 0 ? (
                <tr><td colSpan={7} className="p-0"><EmptyState compact title="No website leads found" description="Try adjusting your filters. New website submissions appear here automatically." /></td></tr>
              ) : (
                data?.data.map(lead => (
                  <Fragment key={lead.id}>
                    <tr className={`hover:bg-surface-2 transition ${['resolved', 'spam'].includes(lead.status) ? 'opacity-60' : ''}`}>
                      <td className="px-4 py-2">
                        <span className={`px-2 py-0.5 rounded-full text-xs font-medium capitalize ${TYPE_BADGE[lead.type] ?? 'bg-gray-100 text-gray-600'}`}>
                          {lead.type}
                        </span>
                      </td>
                      <td className="px-4 py-2 truncate max-w-[180px]" title={lead.full_name ?? ''}>{lead.full_name || '—'}</td>
                      <td className="px-4 py-2">
                        <div className="truncate max-w-[220px]" title={lead.email ?? ''}>{lead.email || '—'}</div>
                        {lead.phone && <div className="text-xs text-ink-faint">{lead.phone}</div>}
                      </td>
                      <td className="px-4 py-2 truncate max-w-[200px]" title={lead.product ?? ''}>{lead.product || '—'}</td>
                      <td className="px-4 py-2">
                        <select
                          value={lead.status}
                          disabled={updateStatus.isPending}
                          onChange={e => updateStatus.mutate({ id: lead.id, status: e.target.value as WebsiteLead['status'] })}
                          className={`px-2 py-0.5 rounded-full text-xs font-medium border-0 cursor-pointer ${STATUS_BADGE[lead.status] ?? 'bg-gray-100 text-gray-600'}`}
                        >
                          {STATUS_OPTIONS.map(s => (
                            <option key={s} value={s}>{s.replace('_', ' ')}</option>
                          ))}
                        </select>
                      </td>
                      <td className="px-4 py-2 text-ink-faint whitespace-nowrap">{fmtDate(lead.submitted_at)}</td>
                      <td className="px-4 py-2">
                        {(lead.message || lead.details) ? (
                          <button
                            onClick={() => setExpandedId(expandedId === lead.id ? null : lead.id)}
                            className="text-blue-600 hover:underline text-xs"
                          >
                            {expandedId === lead.id ? 'Hide' : 'View'}
                          </button>
                        ) : '—'}
                      </td>
                    </tr>
                    {expandedId === lead.id && (
                      <tr className="bg-surface-2">
                        <td colSpan={7} className="px-4 py-3">
                          {lead.message && (
                            <p className="text-sm text-ink mb-2 whitespace-pre-wrap">{lead.message}</p>
                          )}
                          {extractFiles(lead.details).length > 0 && (
                            <div className="flex flex-wrap gap-2 mb-3">
                              {extractFiles(lead.details).map(ref => (
                                <button
                                  key={ref.file}
                                  onClick={() => void openLeadFile(ref)}
                                  className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded border border-line bg-surface text-sm text-blue-600 hover:bg-surface-2"
                                >
                                  📎 {ref.original}
                                </button>
                              ))}
                            </div>
                          )}
                          {lead.details && (
                            <pre className="text-xs text-ink-muted bg-surface border border-line rounded p-3 overflow-x-auto">
                              {JSON.stringify(lead.details, null, 2)}
                            </pre>
                          )}
                          {lead.source && (
                            <p className="text-xs text-ink-faint mt-2">Source: {lead.source}</p>
                          )}
                        </td>
                      </tr>
                    )}
                  </Fragment>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {meta && meta.last_page > 1 && (
          <div className="flex items-center justify-between px-4 py-3 border-t border-line bg-surface-2 text-sm">
            <span className="text-ink-faint">
              Page {meta.current_page} of {meta.last_page} · {meta.total} total
            </span>
            <div className="flex items-center gap-1">
              <button
                disabled={currentPage === 1}
                onClick={() => goToPage(currentPage - 1)}
                className="px-2.5 py-1 rounded border border-line text-ink-muted hover:bg-surface-2 disabled:opacity-40 disabled:cursor-not-allowed"
              >
                Prev
              </button>
              {getPageNumbers().map((p, i) =>
                p === '...' ? (
                  <span key={`e${i}`} className="px-1.5 text-ink-faint">...</span>
                ) : (
                  <button
                    key={p}
                    onClick={() => goToPage(p as number)}
                    className={`px-2.5 py-1 rounded border border-line text-sm ${
                      p === currentPage ? 'bg-blue-600 text-white border-blue-600' : 'text-ink-muted hover:bg-surface-2'
                    }`}
                  >
                    {p}
                  </button>
                )
              )}
              <button
                disabled={currentPage === lastPage}
                onClick={() => goToPage(currentPage + 1)}
                className="px-2.5 py-1 rounded border border-line text-ink-muted hover:bg-surface-2 disabled:opacity-40 disabled:cursor-not-allowed"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
