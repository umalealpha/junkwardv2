import { useState, useEffect, useCallback, useRef } from 'react'
import { useSearchParams, useNavigate } from 'react-router-dom'
import apiClient from '../../api/client'
import { SkeletonTable } from '../../components/common/Skeleton'
import EmptyState from '../../components/common/EmptyState'
import StatusBadge from '../../components/common/StatusBadge'
import Pagination from '../../components/common/Pagination'
import { fmtPula, maskId } from '../../utils/format'


interface Customer {
  id: number
  firstName: string
  lastName: string
  cellphone: string
  email: string
  idNumber: string | null
  isBlocked: boolean
  blockReason?: string | null
  kycStatus: 'compliant' | 'pending' | 'non_compliant' | string | null
  amlStatus: 'cleared' | 'checked' | 'not_checked' | string | null
  amlLastChecked?: string | null
  activePolicies: number
  totalPremium: number
  createdAt: string
}

interface Meta {
  total: number
  per_page: number
  current_page: number
  last_page: number
}

interface CustomersResponse {
  data: Customer[]
  meta: Meta
}

const STATUS_FILTER_OPTIONS = [
  { value: '', label: 'All Customers' },
  { value: 'kyc_compliant', label: 'KYC Compliant' },
  { value: 'kyc_pending', label: 'KYC Pending' },
  { value: 'kyc_non_compliant', label: 'KYC Non-Compliant' },
  { value: 'blocked', label: 'Blocked' },
  { value: 'aml_flagged', label: 'AML Flagged' },
]

// Map KYC / AML domain values onto the shared StatusBadge's semantic status
// keys (which drive token colours + AA contrast) while keeping the
// human-readable label. `status: null` renders the neutral "Unknown" pill.
const KYC_BADGE: Record<string, { label: string; status: string | null }> = {
  compliant:     { label: 'Compliant',     status: 'approved' },   // success/green
  pending:       { label: 'Pending',       status: 'pending' },    // accent
  non_compliant: { label: 'Non-Compliant', status: 'rejected' },   // danger/red
}

const AML_BADGE: Record<string, { label: string; status: string | null }> = {
  cleared:     { label: 'Cleared',     status: 'approved' },        // success/green
  checked:     { label: 'Checked',     status: 'pending' },         // accent
  not_checked: { label: 'Not Checked', status: null },              // neutral
}

export default function CustomerListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const navigate = useNavigate()

  const [customers, setCustomers] = useState<Customer[]>([])
  const [meta, setMeta] = useState<Meta | null>(null)
  const [loading, setLoading] = useState(true)
  const [fetching, setFetching] = useState(false)
  const [blockingId, setBlockingId] = useState<number | null>(null)

  // Add-customer modal (quick-create: name + contact only)
  const emptyAddForm = { firstName: '', middleName: '', lastName: '', email: '', cellphone: '' }
  const [showAdd, setShowAdd] = useState(false)
  const [addForm, setAddForm] = useState(emptyAddForm)
  const [addSaving, setAddSaving] = useState(false)
  const [addError, setAddError] = useState<string | null>(null)

  const search = searchParams.get('search') || ''
  // Controlled input state — kept in sync with URL so navigating back restores the value
  const [searchValue, setSearchValue] = useState(search)
  const searchDebounceRef = useRef<ReturnType<typeof setTimeout>>()
  const status = searchParams.get('status') || ''
  const page = Number(searchParams.get('page') || '1')

  const fetchCustomers = useCallback(async () => {
    setFetching(true)
    try {
      const params: Record<string, string | number> = { per_page: 25, page }
      if (search) params.search = search
      if (status) params.status = status
      const res = await apiClient.get<CustomersResponse>('/customers', { params })
      setCustomers(res.data.data)
      setMeta(res.data.meta)
    } catch {
      setCustomers([])
      setMeta(null)
    } finally {
      setLoading(false)
      setFetching(false)
    }
  }, [search, status, page])

  useEffect(() => {
    fetchCustomers()
  }, [fetchCustomers])

  // Keep the input in sync when the URL search param changes externally
  // (e.g. browser back/forward, or clearing a filter from elsewhere).
  useEffect(() => {
    setSearchValue(search)
  }, [search])

  function updateFilter(key: string, value: string) {
    const next = new URLSearchParams(searchParams)
    if (value) next.set(key, value)
    else next.delete(key)
    next.delete('page')
    setSearchParams(next)
  }

  function goToPage(p: number) {
    const next = new URLSearchParams(searchParams)
    next.set('page', String(p))
    setSearchParams(next)
  }

  async function toggleBlock(customer: Customer) {
    if (blockingId) return
    const action = customer.isBlocked ? 'unblock' : 'block'
    let reason = ''
    if (!customer.isBlocked) {
      reason = window.prompt('Enter block reason:') || ''
      if (!reason) return
    }
    setBlockingId(customer.id)
    try {
      await apiClient.post(`/customers/${customer.id}/${action}`, { reason })
      setCustomers((prev) =>
        prev.map((c) =>
          c.id === customer.id
            ? { ...c, isBlocked: !c.isBlocked, blockReason: customer.isBlocked ? null : reason }
            : c
        )
      )
    } catch {
      // silently fail — the UI stays unchanged
    } finally {
      setBlockingId(null)
    }
  }

  function openAdd() {
    setAddForm(emptyAddForm)
    setAddError(null)
    setShowAdd(true)
  }

  async function submitAdd(e: React.FormEvent) {
    e.preventDefault()
    if (addSaving) return
    if (!addForm.firstName.trim() || !addForm.lastName.trim()) {
      setAddError('First name and last name are required.')
      return
    }
    setAddSaving(true)
    setAddError(null)
    try {
      const payload: Record<string, string> = {
        firstName: addForm.firstName.trim(),
        lastName: addForm.lastName.trim(),
      }
      if (addForm.middleName.trim()) payload.middleName = addForm.middleName.trim()
      if (addForm.email.trim()) payload.email = addForm.email.trim()
      if (addForm.cellphone.trim()) payload.cellphone = addForm.cellphone.trim()
      const res = await apiClient.post<{ data: { id: number } }>('/customers', payload)
      setShowAdd(false)
      // Jump straight to the new customer so the user can add KYC / a policy.
      navigate(`/customers/${res.data.data.id}`)
    } catch (err: any) {
      const d = err?.response?.data ?? {}
      const detail = d.errors ? Object.values(d.errors).flat().join(' · ') : ''
      setAddError([d.message || 'Failed to add customer.', detail].filter(Boolean).join(' — '))
    } finally {
      setAddSaving(false)
    }
  }

  return (
    <div className="p-6 space-y-4">
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <h1 className="font-heading text-2xl font-bold text-ink">Customers</h1>
          {meta && (
            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-surface-2 text-ink-muted tabular-nums">
              {meta.total.toLocaleString()}
            </span>
          )}
        </div>
        <button
          onClick={openAdd}
          className="inline-flex items-center gap-1.5 rounded-md bg-brand-navy px-3 py-1.5 text-sm font-medium text-white hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-brand-navy/30"
        >
          <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
          Add Customer
        </button>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap gap-3">
        <input
          type="text"
          placeholder="Search by name, email, phone, ID..."
          value={searchValue}
          onChange={(e) => {
            const v = e.target.value
            setSearchValue(v)
            clearTimeout(searchDebounceRef.current)
            searchDebounceRef.current = setTimeout(() => updateFilter('search', v), 500)
          }}
          onKeyDown={(e) => {
            if (e.key === 'Enter') {
              clearTimeout(searchDebounceRef.current)
              updateFilter('search', (e.target as HTMLInputElement).value)
            }
          }}
          className="border border-line rounded-md px-3 py-1.5 text-sm w-72 bg-surface text-ink focus:ring-2 focus:ring-brand-navy/30 focus:outline-none"
        />
        <select
          value={status}
          onChange={(e) => updateFilter('status', e.target.value)}
          className="border border-line rounded-md px-3 py-1.5 text-sm bg-surface text-ink focus:ring-2 focus:ring-brand-navy/30 focus:outline-none"
        >
          {STATUS_FILTER_OPTIONS.map((o) => (
            <option key={o.value} value={o.value}>{o.label}</option>
          ))}
        </select>
      </div>

      {/* Table */}
      {loading ? (
        <SkeletonTable rows={8} cols={10} />
      ) : (
        <>
          {fetching && (
            <div className="bg-surface rounded-lg border border-line shadow-elev-sm px-4 py-3 flex items-center gap-2">
              <span className="text-sm text-ink-muted">Updating results…</span>
            </div>
          )}

          <div className={`bg-surface rounded-lg border border-line shadow-elev-sm overflow-x-auto transition-opacity ${fetching ? 'opacity-50 pointer-events-none' : ''}`}>
            <table className="w-full text-sm table-sticky-header">
              <thead className="bg-surface-2 text-ink-muted uppercase text-xs tracking-wide">
                <tr>
                  <th className="px-4 py-2 text-left sticky top-0 bg-surface-2">Name</th>
                  <th className="px-4 py-2 text-left sticky top-0 bg-surface-2">ID Number</th>
                  <th className="px-4 py-2 text-left sticky top-0 bg-surface-2">Phone</th>
                  <th className="px-4 py-2 text-left sticky top-0 bg-surface-2">Email</th>
                  <th className="px-4 py-2 text-left sticky top-0 bg-surface-2">KYC Status</th>
                  <th className="px-4 py-2 text-left sticky top-0 bg-surface-2">AML Status</th>
                  <th className="px-4 py-2 text-right sticky top-0 bg-surface-2">Policies</th>
                  <th className="px-4 py-2 text-right sticky top-0 bg-surface-2">Premium</th>
                  <th className="px-4 py-2 text-center sticky top-0 bg-surface-2">Blocked</th>
                  <th className="px-4 py-2 text-left sticky top-0 bg-surface-2">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {customers.map((c) => {
                  // UAT 2026-05-26: kycStatus / amlStatus may be null when the
                  // customer has never been screened. Surface as "Not set" /
                  // "Not screened" rather than an invisible blank badge — the
                  // shared StatusBadge renders these via semantic status keys.
                  const kyc = c.kycStatus
                    ? (KYC_BADGE[c.kycStatus] ?? { label: c.kycStatus, status: null })
                    : { label: 'Not set', status: null }
                  const aml = c.amlStatus
                    ? (AML_BADGE[c.amlStatus] ?? { label: c.amlStatus, status: null })
                    : { label: 'Not screened', status: null }
                  return (
                    <tr key={c.id} className="hover:bg-surface-2">
                      {/* Name = primary column: don't truncate. */}
                      <td className="px-4 py-2">
                        <button
                          onClick={() => navigate(`/customers/${c.id}`)}
                          className="text-brand-navy hover:underline font-medium text-left"
                        >
                          {c.firstName} {c.lastName}
                        </button>
                      </td>
                      {/* DPA: Omang masked in list view — full value only on the DPO-gated detail page */}
                      <td className="px-4 py-2 font-mono text-ink-muted whitespace-nowrap" title="ID hidden — view on customer detail">{maskId(c.idNumber)}</td>
                      <td className="px-4 py-2 text-ink-muted whitespace-nowrap">{c.cellphone || '—'}</td>
                      <td className="px-4 py-2 text-ink-muted truncate max-w-[180px]" title={c.email}>{c.email || '—'}</td>
                      <td className="px-4 py-2">
                        <StatusBadge status={kyc.status} label={kyc.label} />
                      </td>
                      <td className="px-4 py-2">
                        <StatusBadge status={aml.status} label={aml.label} />
                      </td>
                      <td className="px-4 py-2 text-right tabular-nums text-ink">{c.activePolicies}</td>
                      <td className="px-4 py-2 text-right tabular-nums text-ink">{fmtPula(c.totalPremium)}</td>
                      <td className="px-4 py-2 text-center">
                        {c.isBlocked ? (
                          <span title={c.blockReason || 'Blocked'} className="inline-flex items-center">
                            <svg className="w-4 h-4 text-status-danger-fg" fill="currentColor" viewBox="0 0 20 20">
                              <path fillRule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clipRule="evenodd" />
                            </svg>
                          </span>
                        ) : (
                          <span className="text-ink-faint">—</span>
                        )}
                      </td>
                      <td className="px-4 py-2">
                        <div className="flex items-center gap-2">
                          <button
                            onClick={() => navigate(`/customers/${c.id}`)}
                            className="text-xs text-brand-navy hover:opacity-80 font-medium"
                          >
                            View
                          </button>
                          <button
                            onClick={() => toggleBlock(c)}
                            disabled={blockingId === c.id}
                            className={`text-xs font-medium ${
                              c.isBlocked
                                ? 'text-status-success-fg hover:opacity-80'
                                : 'text-status-danger-fg hover:opacity-80'
                            } disabled:opacity-50`}
                          >
                            {blockingId === c.id ? '...' : c.isBlocked ? 'Unblock' : 'Block'}
                          </button>
                        </div>
                      </td>
                    </tr>
                  )
                })}
                {customers.length === 0 && (
                  <tr>
                    <td colSpan={10} className="p-0">
                      <EmptyState
                        title="No customers found"
                        description="Try adjusting your search or filter criteria."
                        icon={
                          <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={1.5} aria-hidden="true">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128H5.228A2 2 0 013 17.16v-.088c0-2.264 1.713-4.126 3.933-4.309a10.726 10.726 0 016.134 0c.668.055 1.308.18 1.933.36M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                          </svg>
                        }
                      />
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>

          {/* Pagination — shared component */}
          {meta && (
            <Pagination
              currentPage={meta.current_page}
              lastPage={meta.last_page}
              total={meta.total}
              from={meta.total === 0 ? 0 : (meta.current_page - 1) * meta.per_page + 1}
              to={Math.min(meta.current_page * meta.per_page, meta.total)}
              onPageChange={goToPage}
            />
          )}
        </>
      )}

      {/* Add-customer modal */}
      {showAdd && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
          onClick={() => !addSaving && setShowAdd(false)}
        >
          <div
            className="w-full max-w-lg rounded-lg bg-surface shadow-elev-lg border border-line"
            onClick={(e) => e.stopPropagation()}
          >
            <form onSubmit={submitAdd}>
              <div className="flex items-center justify-between border-b border-line px-5 py-3">
                <h2 className="font-heading text-lg font-semibold text-ink">Add Customer</h2>
                <button
                  type="button"
                  onClick={() => setShowAdd(false)}
                  disabled={addSaving}
                  className="text-ink-muted hover:text-ink text-xl leading-none disabled:opacity-50"
                  aria-label="Close"
                >
                  &times;
                </button>
              </div>

              <div className="px-5 py-4 space-y-3">
                {addError && (
                  <div className="rounded-md border border-status-danger-fg/30 bg-status-danger-bg px-3 py-2 text-sm text-status-danger-fg">
                    {addError}
                  </div>
                )}
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-xs font-medium text-ink-muted mb-1">First Name <span className="text-status-danger-fg">*</span></label>
                    <input
                      value={addForm.firstName}
                      onChange={(e) => setAddForm({ ...addForm, firstName: e.target.value })}
                      className="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:ring-2 focus:ring-brand-navy/30 focus:outline-none"
                      placeholder="Enter first name"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-medium text-ink-muted mb-1">Middle Name</label>
                    <input
                      value={addForm.middleName}
                      onChange={(e) => setAddForm({ ...addForm, middleName: e.target.value })}
                      className="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:ring-2 focus:ring-brand-navy/30 focus:outline-none"
                      placeholder="Enter middle name"
                    />
                  </div>
                  <div className="col-span-2">
                    <label className="block text-xs font-medium text-ink-muted mb-1">Last Name <span className="text-status-danger-fg">*</span></label>
                    <input
                      value={addForm.lastName}
                      onChange={(e) => setAddForm({ ...addForm, lastName: e.target.value })}
                      className="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:ring-2 focus:ring-brand-navy/30 focus:outline-none"
                      placeholder="Enter last name"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-medium text-ink-muted mb-1">Email</label>
                    <input
                      type="email"
                      value={addForm.email}
                      onChange={(e) => setAddForm({ ...addForm, email: e.target.value })}
                      className="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:ring-2 focus:ring-brand-navy/30 focus:outline-none"
                      placeholder="Enter email"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-medium text-ink-muted mb-1">Phone</label>
                    <input
                      value={addForm.cellphone}
                      onChange={(e) => setAddForm({ ...addForm, cellphone: e.target.value })}
                      className="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:ring-2 focus:ring-brand-navy/30 focus:outline-none"
                      placeholder="Enter phone number"
                    />
                  </div>
                </div>
                <p className="text-xs text-ink-faint">
                  ID / KYC details are captured later on the customer's profile.
                </p>
              </div>

              <div className="flex justify-end gap-2 border-t border-line px-5 py-3">
                <button
                  type="button"
                  onClick={() => setShowAdd(false)}
                  disabled={addSaving}
                  className="rounded-md border border-line bg-surface px-3 py-1.5 text-sm text-ink-muted hover:bg-surface-2 disabled:opacity-50"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={addSaving}
                  className="rounded-md bg-brand-navy px-4 py-1.5 text-sm font-medium text-white hover:opacity-90 disabled:opacity-50"
                >
                  {addSaving ? 'Adding…' : 'Add Customer'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}
