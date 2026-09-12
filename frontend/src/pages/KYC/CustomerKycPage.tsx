import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useKycList, useUpdateKycStatus } from '../../hooks/useKyc'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import { canApproveKyc, kycActionErrorMessage, type KycFilters } from '../../api/kyc'
import { fmtDate } from '../../utils/format'
import EmptyState from '../../components/common/EmptyState'

const COMPLIANCE_BADGE: Record<number, { label: string; cls: string }> = {
  1: { label: 'Compliant', cls: 'bg-green-100 text-green-700' },
  2: { label: 'Non-compliant', cls: 'bg-red-100 text-red-700' },
}

const STATUS_BADGE: Record<string, string> = {
  approved:  'bg-green-100 text-green-700',
  pending:   'bg-yellow-100 text-yellow-700',
  rejected:  'bg-red-100 text-red-700',
  active:    'bg-green-100 text-green-700',
  // Re-review states (set when a rejected KYC's docs are re-attached).
  // Keyed lowercase — the lookup runs item.status.toLowerCase().
  recheck:                'bg-orange-100 text-orange-700',
  'recheck(kyc expired)': 'bg-orange-100 text-orange-700',
}

const TIER_BADGE: Record<string, string> = {
  MIS:  'bg-blue-100 text-blue-700',
  DOMG: 'bg-amber-100 text-amber-700',
  COMG: 'bg-purple-100 text-purple-700',
}

function DocIcon({ has, title }: { has: boolean; title: string }) {
  return (
    <span title={title} className={`inline-block w-5 h-5 rounded text-center text-xs leading-5 font-bold mr-0.5 ${has ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-400'}`}>
      {has ? '\u2713' : '\u2717'}
    </span>
  )
}

export default function CustomerKycPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [jumpPage, setJumpPage] = useState('')

  const filters: KycFilters = {
    status:     searchParams.get('status') || undefined,
    compliance: searchParams.get('compliance') ? Number(searchParams.get('compliance')) : undefined,
    search:     searchParams.get('search') || undefined,
    tier:       (searchParams.get('tier') as KycFilters['tier']) || undefined,
    per_page:   25,
    page:       Number(searchParams.get('page') || '1'),
  }

  const { data, isLoading, isFetching, isError } = useKycList(filters)

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
        <h1 className="text-2xl font-bold text-gray-800">Customer KYC</h1>
        <Link
          to="/kyc/sanctioned"
          className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-amber-500 text-white text-sm font-semibold hover:bg-amber-600"
        >
          <span aria-hidden>&#128737;</span> View Sanctioned Customers
        </Link>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap gap-3 items-end">
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Search</label>
          <input
            type="text"
            placeholder="Customer/company name, phone, email, policy number..."
            defaultValue={filters.search}
            onKeyDown={e => { if (e.key === 'Enter') updateFilter('search', (e.target as HTMLInputElement).value) }}
            onBlur={e => updateFilter('search', e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm w-64 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Status</label>
          <select
            value={filters.status ?? ''}
            onChange={e => updateFilter('status', e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500"
          >
            <option value="">All Status</option>
            {/* Option values mirror V8's canonical customer_kyc.status enum
                (see graphiteBWV8 kyc.blade.php). Labels are the friendly
                operator-facing wording the team uses elsewhere. The backend
                filter normalises both V8 casings and V2's lowercase legacy
                writes ("Approve" ↔ "approved", "Unapprove" ↔ "rejected"). */}
            <option value="Unchecked">Unchecked</option>
            <option value="Approve">Approved</option>
            <option value="rejected">Rejected</option>
            <option value="Recheck">Recheck</option>
            <option value="Recheck(KYC Expired)">Recheck(KYC Expired)</option>
            <option value="Renew">Renew</option>
          </select>
        </div>
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Compliance</label>
          <select
            value={filters.compliance ?? ''}
            onChange={e => updateFilter('compliance', e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500"
          >
            <option value="">All</option>
            {/* customer_kyc.compliance integer enum:
                  0 = Pending (KYC Verification Pending — never reviewed)
                  1 = Compliant
                  2 = Non-compliant
                  3 = No ID / No Documents (rarely surfaced) */}
            <option value="0">Pending</option>
            <option value="1">Compliant</option>
            <option value="2">Non-compliant</option>
          </select>
        </div>
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Tier</label>
          <select
            value={filters.tier ?? ''}
            onChange={e => updateFilter('tier', e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500"
          >
            <option value="">All Tiers</option>
            <option value="MIS">MIS</option>
            <option value="DOMG">DOMG</option>
            <option value="COMG">COMG</option>
          </select>
        </div>
      </div>

      {/* Table */}
      <div className="bg-white rounded-lg shadow-sm border overflow-hidden">
        {(isLoading || isFetching) && (
          <div className="absolute inset-0 bg-white/50 z-10 flex items-center justify-center">
            <LoadingSpinner size="md" />
          </div>
        )}
        <div className="overflow-x-auto relative">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-600 uppercase text-xs tracking-wider">
              <tr>
                <th className="px-4 py-3 text-left">Customer Name</th>
                <th className="px-4 py-3 text-left">Tier</th>
                <th className="px-4 py-3 text-left">Phone</th>
                <th className="px-4 py-3 text-left">Email</th>
                <th className="px-4 py-3 text-left">Compliance</th>
                <th className="px-4 py-3 text-left">Status</th>
                <th className="px-4 py-3 text-left">Documents</th>
                <th className="px-4 py-3 text-left">Updated</th>
                <th className="px-4 py-3 text-left">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {isLoading ? (
                <tr><td colSpan={9} className="px-4 py-12 text-center"><LoadingSpinner size="md" /></td></tr>
              ) : isError ? (
                // Without this branch a timeout/500 leaves `data` undefined and the
                // tbody renders nothing — a blank table indistinguishable from an
                // empty result. Surface the failure explicitly instead.
                <tr><td colSpan={9} className="px-4 py-12 text-center text-sm text-red-600">
                  Couldn&rsquo;t load KYC records. The request may have timed out &mdash; please retry.
                </td></tr>
              ) : data?.data.length === 0 ? (
                <tr><td colSpan={9} className="p-0"><EmptyState compact title="No KYC records found" description="Try adjusting your filters or search terms." /></td></tr>
              ) : (
                data?.data.map(item => {
                  const comp = item.compliance != null ? COMPLIANCE_BADGE[item.compliance] : null
                  return (
                    <KycRow key={item.id} item={item} comp={comp} />
                  )
                })
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {meta && meta.last_page > 1 && (
          <div className="flex items-center justify-between px-4 py-3 border-t bg-gray-50 text-sm">
            <span className="text-gray-500">
              Showing {meta.from}\u2013{meta.to} of {meta.total}
            </span>
            <div className="flex items-center gap-1">
              <button disabled={currentPage === 1} onClick={() => goToPage(currentPage - 1)} className="px-2.5 py-1 rounded border text-gray-600 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed">Prev</button>
              {getPageNumbers().map((p, i) =>
                p === '...' ? (
                  <span key={`e${i}`} className="px-1.5 text-gray-400">...</span>
                ) : (
                  <button key={p} onClick={() => goToPage(p as number)} className={`px-2.5 py-1 rounded border text-sm ${p === currentPage ? 'bg-blue-600 text-white border-blue-600' : 'text-gray-600 hover:bg-gray-100'}`}>{p}</button>
                )
              )}
              <button disabled={currentPage === lastPage} onClick={() => goToPage(currentPage + 1)} className="px-2.5 py-1 rounded border text-gray-600 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
              <div className="flex items-center gap-1 ml-3 border-l pl-3">
                <span className="text-gray-500 text-xs">Go to</span>
                <input type="number" min={1} max={lastPage} value={jumpPage} onChange={e => setJumpPage(e.target.value)} onKeyDown={e => { if (e.key === 'Enter') { const p = Number(jumpPage); if (p >= 1 && p <= lastPage) { goToPage(p); setJumpPage('') } } }} className="w-14 px-2 py-1 border rounded text-sm text-center" placeholder="#" />
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}

function KycRow({ item, comp }: { item: any; comp: { label: string; cls: string } | null }) {
  const [showActions, setShowActions] = useState(false)
  const [remark, setRemark] = useState('')
  const updateStatus = useUpdateKycStatus()
  // Inline approve/reject is only offered to holders of `customer-kyc-approve`
  // (KYC Approver role); everyone else gets a plain link to the detail page.
  // The backend enforces the same permission on POST /kyc/{id}/status.
  const canApprove = canApproveKyc()
  const [actionError, setActionError] = useState<string | null>(null)

  async function handleAction(status: 'approved' | 'rejected') {
    setActionError(null)
    try {
      // updateKycStatus is keyed on customer_kyc.id (V8 parity) so the
      // approve/reject hits the exact row the reviewer is looking at,
      // not a sibling row that happens to share the customer_id.
      await updateStatus.mutateAsync({ kycId: item.id, status, remark })
      setShowActions(false)
      setRemark('')
    } catch (e) {
      // A 403 here means the approver gate refused this user; say so rather
      // than swallowing the error and leaving the row unchanged.
      setActionError(kycActionErrorMessage(e))
    }
  }

  // Both flows are keyed on customer_kyc.id (item.id). The DomCom review
  // page resolves the customer_kyc_dom_com sibling from that KYC row via
  // its customer_kyc_id back-link; MIS reads customer_kyc directly.
  const detailHref = item.tier === 'DOMG' || item.tier === 'COMG'
    ? `/kyc/dom-com/${item.id}`
    : `/kyc/${item.id}`

  return (
    <tr className="hover:bg-gray-50 transition">
      <td className="px-4 py-2 font-medium">
        <Link to={detailHref} className="text-blue-600 hover:underline">
          {item.customerName || '\u2014'}
        </Link>
      </td>
      <td className="px-4 py-2">
        <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${TIER_BADGE[item.tier] ?? 'bg-gray-100 text-gray-600'}`}>
          {item.tier}
        </span>
      </td>
      <td className="px-4 py-2">{item.cellphone || '\u2014'}</td>
      <td className="px-4 py-2 truncate max-w-[180px]" title={item.email ?? ''}>{item.email || '\u2014'}</td>
      <td className="px-4 py-2">
        {comp ? (
          <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${comp.cls}`}>{comp.label}</span>
        ) : (
          <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">Pending</span>
        )}
      </td>
      <td className="px-4 py-2">
        {item.status ? (
          <span className={`px-2 py-0.5 rounded-full text-xs font-medium capitalize ${STATUS_BADGE[item.status.toLowerCase()] ?? 'bg-gray-100 text-gray-600'}`}>
            {item.status}
          </span>
        ) : '\u2014'}
      </td>
      <td className="px-4 py-2">
        <DocIcon has={item.hasOmang} title="Omang" />
        <DocIcon has={item.hasPassport} title="Passport" />
        <DocIcon has={item.hasDrivingLicense} title="Driving License" />
        <DocIcon has={item.hasProofResidence} title="Proof of Residence" />
        <DocIcon has={item.hasProofIncome} title="Proof of Income" />
      </td>
      <td className="px-4 py-2 text-gray-500">
        {fmtDate(item.updatedAt)}
      </td>
      <td className="px-4 py-2">
        {showActions ? (
          <div className="flex flex-col gap-1.5 min-w-[160px]">
            <input
              type="text"
              placeholder="Remark (optional)"
              value={remark}
              onChange={e => setRemark(e.target.value)}
              className="px-2 py-1 border rounded text-xs w-full"
            />
            <div className="flex gap-1">
              <button
                onClick={() => handleAction('approved')}
                disabled={updateStatus.isPending}
                className="px-2.5 py-1 bg-green-600 text-white rounded text-xs font-medium hover:bg-green-700 disabled:opacity-50"
              >
                Approve
              </button>
              <button
                onClick={() => handleAction('rejected')}
                disabled={updateStatus.isPending}
                className="px-2.5 py-1 bg-red-600 text-white rounded text-xs font-medium hover:bg-red-700 disabled:opacity-50"
              >
                Reject
              </button>
              <button
                onClick={() => { setShowActions(false); setRemark(''); setActionError(null) }}
                className="px-2 py-1 bg-gray-200 text-gray-600 rounded text-xs hover:bg-gray-300"
              >
                Cancel
              </button>
            </div>
            {actionError && <p className="text-xs text-red-600">{actionError}</p>}
          </div>
        ) : canApprove ? (
          <button
            onClick={() => setShowActions(true)}
            className="px-2.5 py-1 border border-gray-300 rounded text-xs font-medium text-gray-600 hover:bg-gray-100"
          >
            Review
          </button>
        ) : (
          <Link
            to={detailHref}
            className="inline-block px-2.5 py-1 border border-line rounded text-xs font-medium text-ink-muted hover:bg-surface-2"
          >
            View
          </Link>
        )}
      </td>
    </tr>
  )
}
