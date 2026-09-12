import { useState, useRef } from 'react'
import { useSearchParams, Link } from 'react-router-dom'
import { usePolicies } from '../../hooks/usePolicies'
import { useProducts } from '../../hooks/useProducts'
import { useAgencies, useAgents } from '../../hooks/useLookups'
import ProgressBar from '../../components/common/ProgressBar'
import EmptyState from '../../components/common/EmptyState'
import StatusBadge from '../../components/common/StatusBadge'
import Pagination from '../../components/common/Pagination'
import { SkeletonTable } from '../../components/common/Skeleton'
import { fmtPula, fmtDate } from '../../utils/format'
import type { PolicyFilters } from '../../api/policies'

// UAT 2026-06-03 (Satyajeet): coverage-based products that use the V2 Edit
// wizard. MIS retail products (ACD / HCB / Legal / Mobile&Electronic / TP
// Car) don't have a working V2 edit flow yet — keep the "Continue" draft
// link gated to wizard products so it can't link into the broken DomCom
// wizard. Mirrors PolicyDetailPage + PolicyCreatePage. In practice MIS
// retail policies are always created in ISSUED state through the public
// payment flow, so this is defensive — but keeps the codebase consistent.
const COVERAGE_PRODUCT_IDS = [7, 8, 16, 17, 18, 20, 22, 23, 24]

// Status options are product-aware. DOMG/COMG policies track state via
// policy_actions.status (QUOTE / IN_APPROVAL / APPROVED / ISSUED / REJECTED)
// plus is_draft and the legacy status=2 (cancelled). Everything else just
// uses the top-level status int.
// The option `value` shape encodes which backend filter param to send:
//   "status:1"    → ?status=1
//   "action:ISSUED" → ?action_status=ISSUED
//   "draft:1"     → ?draft=1
const STATUS_OPTIONS_DEFAULT = [
  { value: '',          label: 'All Statuses' },
  { value: 'status:1',  label: 'Active' },
  { value: 'status:0',  label: 'In-Active' },
  { value: 'status:2',  label: 'Cancelled' },
  { value: 'status:3',  label: 'Expired' },
]
const STATUS_OPTIONS_DOMCOM = [
  { value: '',                 label: 'All Statuses' },
  { value: 'action:ISSUED',    label: 'Issued' },
  { value: 'action:QUOTE',     label: 'In Quote' },
  { value: 'draft:1',          label: 'Draft' },
  { value: 'status:2',         label: 'Cancelled' },
]

// Mirrors the legacy Graphite "Filter By Payment Method" dropdown exactly
// (values matched case-insensitively against customer_banking.billing /
// payment_transactions.paymentMethod on the backend).
const PAYMENT_METHOD_OPTIONS = [
  { value: '',            label: 'All Payment Methods' },
  { value: 'DPO',         label: 'DPO' },
  { value: 'RealPay',     label: 'RealPay' },
  { value: 'VCS',         label: 'VCS' },
  { value: 'CASH',        label: 'Cash' },
  { value: 'orangeMoney', label: 'Orange Money' },
  { value: 'N-Genius',    label: 'N-Genius' },
]

// All DomCom product IDs to exclude from default "instant" view
const COMMERCIAL_ID = 7
const DOMESTIC_ID = 8
const DOMCOM_IDS = [7, 8, 16,17,18,20,22,23,24] // Commercial, Domestic, Engineering, Specialist, Guarantee, Miscellaneous

export default function PolicyListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [searchValue, setSearchValue] = useState(searchParams.get('search') || '')
  const searchDebounceRef = useRef<ReturnType<typeof setTimeout>>()

  // Handle special "instant" product filter — exclude commercial & domestic
  const productParam = searchParams.get('product_id')
  const isInstantFilter = productParam === 'instant'

  const draftParam = searchParams.get('draft')

  const agencyParam = searchParams.get('agency_id')
  const agentParam = searchParams.get('agent_id')
  const paymentMethodParam = searchParams.get('payment_method')

  const filters: PolicyFilters = {
    status:         searchParams.get('status') ? Number(searchParams.get('status')) as PolicyFilters['status'] : undefined,
    draft:          draftParam ? Number(draftParam) as PolicyFilters['draft'] : undefined,
    product_id:     productParam && !isInstantFilter ? Number(productParam) : undefined,
    agency_id:      agencyParam ? Number(agencyParam) : undefined,
    agent_id:       agentParam ? Number(agentParam) : undefined,
    payment_method: paymentMethodParam || undefined,
    action_status:  searchParams.get('action_status') || undefined,
    search:         searchParams.get('search') || undefined,
    per_page:       25,
    page:           Number(searchParams.get('page') || '1'),
  }

  // Are we currently scoped to a DomCom product? Drives which status
  // options the dropdown shows. Also 'instant' = explicitly non-DomCom.
  const isDomComScope = productParam && !isInstantFilter && DOMCOM_IDS.includes(Number(productParam))
  const statusOptions = isDomComScope ? STATUS_OPTIONS_DOMCOM : STATUS_OPTIONS_DEFAULT

  // The dropdown stores its state as a single string "filter:value" since
  // the three backend params (status/draft/action_status) are mutually
  // exclusive from the user's POV. Map to/from URL search params.
  const statusDropdownValue = (() => {
    if (searchParams.get('action_status')) return `action:${searchParams.get('action_status')}`
    if (searchParams.get('draft') === '1')  return 'draft:1'
    if (searchParams.get('status'))         return `status:${searchParams.get('status')}`
    return ''
  })()
  function setStatusDropdown(value: string) {
    const next = new URLSearchParams(searchParams)
    // Clear all three filter params, then set the one the user picked.
    next.delete('status'); next.delete('draft'); next.delete('action_status')
    if (value) {
      const [kind, val] = value.split(':')
      if (kind === 'action') next.set('action_status', val)
      else if (kind === 'draft') next.set('draft', val)
      else if (kind === 'status') next.set('status', val)
    }
    next.delete('page')
    setSearchParams(next)
  }

  // Exclude commercial & domestic by default (unless a specific product is selected or searching)
  const shouldExcludeDomCom = !filters.product_id && !filters.search
  const queryFilters = shouldExcludeDomCom
    ? { ...filters, exclude_products: DOMCOM_IDS.join(',') } as PolicyFilters & { exclude_products: string }
    : filters

  const { data, isLoading, isFetching } = usePolicies(queryFilters, true)
  const { data: products } = useProducts()
  const { data: agencies } = useAgencies()
  const { data: agents } = useAgents()

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

  // Active product label for header
  const activeProductLabel = isInstantFilter
    ? 'Instant'
    : productParam
      ? products?.find(p => p.id === Number(productParam))?.name ?? ''
      : ''

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="font-heading text-2xl font-bold text-ink">
          {draftParam === '1' ? 'Draft Policies' : `Policies${activeProductLabel ? ` — ${activeProductLabel}` : ''}`}
        </h1>
        <Link to="/policies/create"
          className="px-4 py-2 bg-primary text-primary-contrast rounded-md hover:opacity-90 text-sm font-medium flex items-center gap-2 shadow-elev-sm">
          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          Create Policy
        </Link>
      </div>

      {/* Filters — product first, then status, then search. Status options
          swap to DomCom-specific labels (Issued / In Quote / Draft /
          Cancelled) when a DOMG/COMG product is selected, since those
          policies track state via policy_actions rather than the top-level
          status int. Picking a different filter clears the others (the
          three backend params are mutually exclusive from the operator's
          POV). */}
      <div className="flex flex-wrap gap-3">
        <select
          value={productParam || ''}
          onChange={(e) => updateFilter('product_id', e.target.value)}
          className="border border-line rounded-md px-3 py-1.5 text-sm focus:ring-2 focus:ring-brand-navy/30 focus:outline-none"
        >
          <option value="">All Products</option>
          <option value="instant">Instant (All except Commercial/Domestic)</option>
          <option value={String(COMMERCIAL_ID)}>Commercial (COMG)</option>
          <option value={String(DOMESTIC_ID)}>Domestic (DOMG)</option>
          {products?.filter(p => p.id !== COMMERCIAL_ID && p.id !== DOMESTIC_ID).map((p) => (
            <option key={p.id} value={p.id}>{p.name}</option>
          ))}
        </select>
        <select
          value={statusDropdownValue}
          onChange={(e) => setStatusDropdown(e.target.value)}
          className="border border-line rounded-md px-3 py-1.5 text-sm focus:ring-2 focus:ring-brand-navy/30 focus:outline-none"
        >
          {statusOptions.map((o) => (
            <option key={o.value} value={o.value}>{o.label}</option>
          ))}
        </select>
        <select
          value={agencyParam || ''}
          onChange={(e) => updateFilter('agency_id', e.target.value)}
          className="border border-line rounded-md px-3 py-1.5 text-sm focus:ring-2 focus:ring-brand-navy/30 focus:outline-none"
        >
          <option value="">All Agencies</option>
          {agencies?.map((a) => (
            <option key={a.id} value={a.id}>{a.name}</option>
          ))}
        </select>
        <select
          value={agentParam || ''}
          onChange={(e) => updateFilter('agent_id', e.target.value)}
          className="border border-line rounded-md px-3 py-1.5 text-sm focus:ring-2 focus:ring-brand-navy/30 focus:outline-none"
        >
          <option value="">All Agents</option>
          {agents?.map((a) => (
            <option key={a.id} value={a.id}>{a.name}</option>
          ))}
        </select>
        <select
          value={paymentMethodParam || ''}
          onChange={(e) => updateFilter('payment_method', e.target.value)}
          className="border border-line rounded-md px-3 py-1.5 text-sm focus:ring-2 focus:ring-brand-navy/30 focus:outline-none"
        >
          {PAYMENT_METHOD_OPTIONS.map((o) => (
            <option key={o.value} value={o.value}>{o.label}</option>
          ))}
        </select>
        <input
          type="text"
          placeholder="Search by policy #, customer, phone or agency..."
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
          className="border border-line rounded-md px-3 py-1.5 text-sm focus:ring-2 focus:ring-brand-navy/30 focus:outline-none flex-1 min-w-[240px] max-w-md"
        />
      </div>

      {/* Table */}
      {isLoading ? (
        <div className="space-y-3">
          <ProgressBar isLoading={isLoading} label="Loading policies" className="max-w-sm mx-auto" />
          <SkeletonTable rows={8} cols={7} />
        </div>
      ) : (
        <>
          {isFetching && (
            <div className="bg-surface rounded-lg border border-line shadow-elev-sm px-4 py-3">
              <ProgressBar isLoading label="Updating results" className="max-w-xs" />
            </div>
          )}

          <div className={`bg-surface rounded-lg border border-line shadow-elev-sm overflow-x-auto transition-opacity ${isFetching ? 'opacity-50 pointer-events-none' : ''}`}>
            <table className="w-full text-sm table-sticky-header">
              <thead className="bg-surface-2 text-ink-muted uppercase text-xs tracking-wide">
                <tr>
                  <th className="px-4 py-2 text-left sticky top-0 bg-surface-2">Policy #</th>
                  <th className="px-4 py-2 text-left sticky top-0 bg-surface-2">Customer</th>
                  <th className="px-4 py-2 text-left sticky top-0 bg-surface-2">Product</th>
                  <th className="px-4 py-2 text-right sticky top-0 bg-surface-2">Premium</th>
                  <th className="px-4 py-2 text-left sticky top-0 bg-surface-2">Status</th>
                  <th className="px-4 py-2 text-left sticky top-0 bg-surface-2">Created</th>
                  <th className="px-4 py-2 text-left sticky top-0 bg-surface-2"></th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {data?.data.map((policy) => (
                  <tr key={policy.id} className="hover:bg-surface-2">
                    <td className="px-4 py-2 font-mono text-brand-navy whitespace-nowrap">
                      <Link to={`/policies/${policy.id}`} title={policy.policyNumber}>{policy.policyNumber}</Link>
                    </td>
                    {/* Customer = primary column: don't truncate the name. */}
                    <td className="px-4 py-2 text-ink" title={policy.customer?.fullName ?? ''}>{policy.customer?.fullName ?? '—'}</td>
                    <td className="px-4 py-2 text-ink-muted truncate max-w-[150px]" title={policy.product?.name ?? ''}>{policy.product?.name ?? '—'}</td>
                    <td className="px-4 py-2 text-right tabular-nums text-ink">
                      {policy.premium != null
                        ? fmtPula(policy.premium)
                        : '—'}
                    </td>
                    <td className="px-4 py-2">
                      <div className="flex flex-col items-start gap-0.5">
                        {policy.is_draft ? (
                          <StatusBadge status="pending" label="Draft" />
                        ) : (
                          <StatusBadge status={policy.statusLabel} label={policy.statusLabel === 'in-active' ? 'In-Active' : undefined} />
                        )}
                        {policy.actionStatus && (
                          <StatusBadge status={policy.actionStatus} label={policy.actionStatus.replace('_', ' ')} />
                        )}
                      </div>
                    </td>
                    <td className="px-4 py-2 text-ink-muted whitespace-nowrap">
                      {fmtDate(policy.createdAt)}
                    </td>
                    <td className="px-4 py-2">
                      {policy.is_draft && COVERAGE_PRODUCT_IDS.includes(policy.product?.id ?? 0) && (
                        <Link to={`/policies/${policy.id}/edit`} className="text-xs text-brand-navy hover:opacity-80 font-medium">
                          Continue
                        </Link>
                      )}
                    </td>
                  </tr>
                ))}
                {data?.data.length === 0 && (
                  <tr>
                    <td colSpan={7} className="p-0"><EmptyState compact title="No policies found" description="Try adjusting your filters or search terms." /></td>
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
              from={meta.from ?? null}
              to={meta.to ?? null}
              onPageChange={goToPage}
            />
          )}
        </>
      )}
    </div>
  )
}
