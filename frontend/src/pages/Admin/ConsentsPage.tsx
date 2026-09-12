import { useSearchParams } from 'react-router-dom'
import { useConsentSummary, useConsents } from '../../hooks/useConsents'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import type { ConsentFilters } from '../../api/consents'
import { fmtDateTime } from '../../utils/format'

// Mirror the AnomalyFindingsPage structure: KPI tiles → filters → table.
// The dashboard answers "is the OTP-gated consent flow firing on every
// retail policy" and "are customers getting through cleanly".

const SCOPE_BADGE: Record<string, string> = {
  retail:      'bg-emerald-100 text-emerald-700',
  domcom:      'bg-blue-100 text-blue-700',
  engineering: 'bg-amber-100 text-amber-700',
  specialist:  'bg-violet-100 text-violet-700',
}

const CHANNEL_BADGE: Record<string, string> = {
  whatsapp: 'bg-green-100 text-green-700',
  sms:      'bg-blue-100 text-blue-700',
  voice:    'bg-amber-100 text-amber-700',
  email:    'bg-slate-100 text-slate-700',
}


export default function ConsentsPage() {
  const [params, setParams] = useSearchParams()

  const filters: ConsentFilters = {
    product_scope: params.get('product_scope') || undefined,
    status:        (params.get('status') as 'active' | 'revoked' | null) || undefined,
    from:          params.get('from') || undefined,
    to:            params.get('to')   || undefined,
    search:        params.get('search') || undefined,
    page:          Number(params.get('page') || '1'),
    per_page:      25,
  }

  const { data: summary } = useConsentSummary()
  const { data, isLoading, isFetching } = useConsents(filters)

  function setFilter(key: string, value: string) {
    const next = new URLSearchParams(params)
    if (value) next.set(key, value); else next.delete(key)
    next.delete('page') // reset paging on filter change
    setParams(next)
  }

  function setPage(p: number) {
    const next = new URLSearchParams(params)
    next.set('page', String(p))
    setParams(next)
  }

  return (
    <div className="px-6 py-6 space-y-6">
      <div className="flex items-baseline justify-between">
        <h1 className="text-2xl font-bold text-slate-900">Consent compliance</h1>
        <p className="text-xs text-slate-500">
          As of {fmtDateTime(summary?.as_of)}
        </p>
      </div>

      {/* KPI tiles */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <Tile label="Consents this week"  value={summary?.consents_week ?? '—'} />
        <Tile label="Consents this month" value={summary?.consents_month ?? '—'} />
        <Tile
          label="Coverage % (linked / consents)"
          value={summary ? `${summary.coverage_pct}%` : '—'}
          tone={summary && summary.coverage_pct < 95 ? 'warn' : 'ok'}
        />
        <Tile
          label="Avg verify → accept (s)"
          value={summary?.avg_verify_to_accept_s ?? '—'}
          tone={summary && summary.avg_verify_to_accept_s < 5 ? 'warn' : 'ok'}
          hint={summary && summary.avg_verify_to_accept_s < 5 ? 'Suspiciously fast — possible bot' : undefined}
        />
        <Tile label="Revoked this month"      value={summary?.revoked_month ?? '—'} />
        <Tile label="Revocation rate"         value={summary ? `${summary.revocation_rate_pct}%` : '—'} />
        <Tile label="WhatsApp / SMS / Voice"  value={
          summary ? `${summary.by_channel?.whatsapp ?? 0}/${summary.by_channel?.sms ?? 0}/${summary.by_channel?.voice ?? 0}` : '—'
        } />
      </div>

      {/* Filters */}
      <div className="rounded-lg ring-1 ring-slate-200 bg-white p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <Select label="Product scope" value={filters.product_scope ?? ''} onChange={(v) => setFilter('product_scope', v)}>
          <option value="">All scopes</option>
          <option value="retail">Retail</option>
          <option value="domcom">Domestic / Commercial</option>
          <option value="engineering">Engineering</option>
          <option value="specialist">Specialist</option>
        </Select>
        <Select label="Status" value={filters.status ?? ''} onChange={(v) => setFilter('status', v)}>
          <option value="">All statuses</option>
          <option value="active">Active</option>
          <option value="revoked">Revoked</option>
        </Select>
        <FilterInput label="From" type="date" value={filters.from ?? ''} onChange={(v) => setFilter('from', v)} />
        <FilterInput label="To"   type="date" value={filters.to ?? ''}   onChange={(v) => setFilter('to', v)} />
        <FilterInput label="Search (cellphone / id / hash)" value={filters.search ?? ''} onChange={(v) => setFilter('search', v)} />
      </div>

      {/* Table */}
      <div className="rounded-lg ring-1 ring-slate-200 bg-white overflow-x-auto">
        {isLoading && <div className="p-6 grid place-items-center"><LoadingSpinner /></div>}
        {!isLoading && (
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                <th className="px-3 py-2">ID</th>
                <th className="px-3 py-2">Mobile</th>
                <th className="px-3 py-2">Scope</th>
                <th className="px-3 py-2">Policy</th>
                <th className="px-3 py-2">Accepted</th>
                <th className="px-3 py-2">Channel</th>
                <th className="px-3 py-2">Verify→Accept (s)</th>
                <th className="px-3 py-2">Status</th>
                <th className="px-3 py-2">Evidence</th>
              </tr>
            </thead>
            <tbody>
              {data?.items.map((r) => (
                <tr key={r.id} className="border-t border-slate-100 hover:bg-slate-50/50">
                  <td className="px-3 py-2 font-mono">#{r.id}</td>
                  <td className="px-3 py-2 font-mono">{r.cellphone_masked}</td>
                  <td className="px-3 py-2">
                    {r.product_scope && (
                      <span className={`inline-flex rounded-full px-2 py-0.5 text-xs ${SCOPE_BADGE[r.product_scope] ?? 'bg-slate-100 text-slate-600'}`}>
                        {r.product_scope}
                      </span>
                    )}
                  </td>
                  <td className="px-3 py-2 font-mono">{r.policy_id ?? '—'}</td>
                  <td className="px-3 py-2 text-slate-600">{fmtDateTime(r.accepted_at)}</td>
                  <td className="px-3 py-2">
                    {r.otp_channel && (
                      <span className={`inline-flex rounded-full px-2 py-0.5 text-xs ${CHANNEL_BADGE[r.otp_channel] ?? 'bg-slate-100 text-slate-600'}`}>
                        {r.otp_channel}
                      </span>
                    )}
                  </td>
                  <td className="px-3 py-2 text-right tabular-nums">
                    {r.sec_verify_to_accept ?? '—'}
                  </td>
                  <td className="px-3 py-2">
                    {r.revoked_at
                      ? <span className="inline-flex rounded-full px-2 py-0.5 text-xs bg-rose-100 text-rose-700">revoked</span>
                      : <span className="inline-flex rounded-full px-2 py-0.5 text-xs bg-emerald-100 text-emerald-700">active</span>}
                  </td>
                  <td className="px-3 py-2 font-mono text-xs text-slate-500" title={r.evidence_hash ?? ''}>
                    {r.evidence_hash ? r.evidence_hash.slice(0, 12) + '…' : '—'}
                  </td>
                </tr>
              ))}
              {data?.items.length === 0 && (
                <tr><td colSpan={9} className="px-3 py-8 text-center text-slate-500">No consents match these filters.</td></tr>
              )}
            </tbody>
          </table>
        )}
      </div>

      {/* Pagination */}
      {data && data.meta.last_page > 1 && (
        <div className="flex items-center justify-between text-sm">
          <span className="text-slate-500">
            Page {data.meta.current_page} of {data.meta.last_page} · {data.meta.total} total
            {isFetching && <span className="ml-2 text-slate-400">refreshing…</span>}
          </span>
          <div className="flex gap-2">
            <button
              onClick={() => setPage(Math.max(1, data.meta.current_page - 1))}
              disabled={data.meta.current_page <= 1}
              className="rounded-md ring-1 ring-slate-300 bg-white px-3 py-1.5 text-sm disabled:opacity-40"
            >
              Previous
            </button>
            <button
              onClick={() => setPage(Math.min(data.meta.last_page, data.meta.current_page + 1))}
              disabled={data.meta.current_page >= data.meta.last_page}
              className="rounded-md ring-1 ring-slate-300 bg-white px-3 py-1.5 text-sm disabled:opacity-40"
            >
              Next
            </button>
          </div>
        </div>
      )}
    </div>
  )
}

function Tile({ label, value, tone, hint }: { label: string; value: string | number; tone?: 'ok' | 'warn'; hint?: string }) {
  return (
    <div className={`rounded-lg ring-1 p-3 ${
      tone === 'warn' ? 'ring-amber-200 bg-amber-50' : 'ring-slate-200 bg-white'
    }`}>
      <div className="text-xs uppercase tracking-wider text-slate-500">{label}</div>
      <div className="mt-1 text-2xl font-bold text-slate-900 tabular-nums">{value}</div>
      {hint && <div className="text-xs text-amber-700 mt-1">{hint}</div>}
    </div>
  )
}

function Select({
  label, value, onChange, children,
}: { label: string; value: string; onChange: (v: string) => void; children: React.ReactNode }) {
  return (
    <label className="block">
      <span className="text-xs font-medium text-slate-600">{label}</span>
      <select
        className="mt-1 w-full rounded-md ring-1 ring-slate-300 bg-white px-2 py-1.5 text-sm"
        value={value}
        onChange={(e) => onChange(e.target.value)}
      >
        {children}
      </select>
    </label>
  )
}

function FilterInput({
  label, type = 'text', value, onChange,
}: { label: string; type?: string; value: string; onChange: (v: string) => void }) {
  return (
    <label className="block">
      <span className="text-xs font-medium text-slate-600">{label}</span>
      <input
        type={type}
        className="mt-1 w-full rounded-md ring-1 ring-slate-300 bg-white px-2 py-1.5 text-sm"
        value={value}
        onChange={(e) => onChange(e.target.value)}
      />
    </label>
  )
}
