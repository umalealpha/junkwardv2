import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useMapfreSubmissionSummary, useMapfreSubmissions } from '../../hooks/useMapfreSubmissions'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import { exportMapfreSubmissions, type MapfreSubmissionFilters } from '../../api/mapfreSubmissions'
import { fmtDateTime } from '../../utils/format'

// Read-only console over the MAPFRE / MAWDY bind audit trail. Mirrors the
// /admin/consents layout: KPI tiles → filters → table.
//
// A travel sale made through the Start portal is bound in MAPFRE's book and
// never becomes a Graphite policy, so it shows up on no policy screen, PDF,
// ledger or renewal list. This page is the only place ops can see it.
//
// Nothing here writes, and the stored contract payload never reaches the
// browser — the holder's Omang / passport, address and date of birth stay in
// the audit row (DPA 2024). Only trip facts and the holder's name are shown.

const STATUS_BADGE: Record<string, string> = {
  submitted: 'bg-emerald-100 text-emerald-700',
  failed:    'bg-rose-100 text-rose-700',
  pending:   'bg-amber-100 text-amber-700',
}

export default function MapfreSubmissionsPage() {
  const [params, setParams] = useSearchParams()
  const [exporting, setExporting] = useState(false)
  const [exportError, setExportError] = useState<string | null>(null)

  const filters: MapfreSubmissionFilters = {
    status:   (params.get('status') as MapfreSubmissionFilters['status']) || undefined,
    from:     params.get('from') || undefined,
    to:       params.get('to') || undefined,
    search:   params.get('search') || undefined,
    page:     Number(params.get('page') || '1'),
    per_page: 25,
  }

  const { data: summary } = useMapfreSubmissionSummary()
  const { data, isLoading, isFetching } = useMapfreSubmissions(filters)

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

  async function onExport() {
    setExporting(true)
    setExportError(null)
    try {
      await exportMapfreSubmissions(filters)
    } catch {
      setExportError('Export failed. Try again, or narrow the date range.')
    } finally {
      setExporting(false)
    }
  }

  return (
    <div className="px-6 py-6 space-y-6">
      <div className="flex items-baseline justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">MAPFRE travel binds</h1>
          <p className="mt-1 text-sm text-slate-500">
            Every contract the Start portal bound with MAPFRE. These sales live in MAPFRE&apos;s book —
            they are not Graphite policies, so they appear on no policy screen.{' '}
            <Link to="/admin/integrations" className="text-brand-navy underline">Integration settings</Link>
          </p>
        </div>
        <p className="shrink-0 text-xs text-slate-500">As of {fmtDateTime(summary?.as_of)}</p>
      </div>

      {/* KPI tiles */}
      <div className="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3">
        <Tile label="Bound today"  value={summary?.bound_today ?? '—'} />
        <Tile label="Bound this month" value={summary?.bound_month ?? '—'} />
        <Tile label="Failed this month" value={summary?.failed_month ?? '—'} />
        <Tile
          label="Failure rate"
          value={summary ? `${summary.failure_rate_pct}%` : '—'}
          tone={summary && summary.failure_rate_pct > 10 ? 'warn' : 'ok'}
        />
        <Tile
          label="Stuck pending"
          value={summary?.stuck_pending ?? '—'}
          tone={summary && summary.stuck_pending > 0 ? 'warn' : 'ok'}
          hint={summary && summary.stuck_pending > 0 ? 'No response came back — confirm upstream' : undefined}
        />
        <Tile
          label="Bound, no TRVL number"
          value={summary?.submitted_unnumbered ?? '—'}
          tone={summary && summary.submitted_unnumbered > 0 ? 'warn' : 'ok'}
          hint={summary && summary.submitted_unnumbered > 0 ? 'Sold but unnamed — needs a backfill' : undefined}
        />
      </div>

      {/* Filters */}
      <div className="rounded-lg ring-1 ring-slate-200 bg-surface p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
        <Select label="Status" value={filters.status ?? ''} onChange={(v) => setFilter('status', v)}>
          <option value="">All statuses</option>
          <option value="submitted">Submitted (bound)</option>
          <option value="failed">Failed</option>
          <option value="pending">Pending</option>
        </Select>
        <FilterInput label="From" type="date" value={filters.from ?? ''} onChange={(v) => setFilter('from', v)} />
        <FilterInput label="To"   type="date" value={filters.to ?? ''}   onChange={(v) => setFilter('to', v)} />
        <FilterInput
          label="Search (reference / TRVL / contract / quote)"
          value={filters.search ?? ''}
          onChange={(v) => setFilter('search', v)}
        />
        <div>
          <button
            type="button"
            onClick={onExport}
            disabled={exporting}
            className="w-full rounded-md ring-1 ring-slate-300 bg-surface px-3 py-1.5 text-sm hover:bg-slate-50 disabled:opacity-40"
          >
            {exporting ? 'Exporting…' : 'Export CSV'}
          </button>
          {exportError && <p className="mt-1 text-xs text-rose-600">{exportError}</p>}
        </div>
      </div>

      {/* Table */}
      <div className="rounded-lg ring-1 ring-slate-200 bg-surface overflow-x-auto">
        {isLoading && <div className="p-6 grid place-items-center"><LoadingSpinner /></div>}
        {!isLoading && (
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                <th className="px-3 py-2">Status</th>
                <th className="px-3 py-2">Policy no.</th>
                <th className="px-3 py-2">MAPFRE contract</th>
                <th className="px-3 py-2">Policyholder</th>
                <th className="px-3 py-2">Trip</th>
                <th className="px-3 py-2 text-right">Travellers</th>
                <th className="px-3 py-2">Reference</th>
                <th className="px-3 py-2">Proposal</th>
                <th className="px-3 py-2">Bound</th>
              </tr>
            </thead>
            <tbody>
              {data?.items.map((r) => (
                <tr key={r.id} className="border-t border-slate-100 hover:bg-slate-50/50 align-top">
                  <td className="px-3 py-2">
                    <span className={`inline-flex rounded-full px-2 py-0.5 text-xs ${STATUS_BADGE[r.status] ?? 'bg-slate-100 text-slate-600'}`}>
                      {r.status}
                    </span>
                    {r.error && (
                      <div className="mt-1 max-w-[18rem] text-xs text-rose-600" title={r.error}>
                        {r.http_status ? `${r.http_status} · ` : ''}{r.error}
                      </div>
                    )}
                  </td>
                  <td className="px-3 py-2 font-mono">
                    {r.policy_number ?? (
                      r.status === 'submitted'
                        ? <span className="text-amber-700" title="Bound upstream but no TRVL number was recorded">not issued</span>
                        : '—'
                    )}
                  </td>
                  <td className="px-3 py-2 font-mono text-xs">{r.mapfre_contract_number ?? '—'}</td>
                  <td className="px-3 py-2">{r.holder_name ?? '—'}</td>
                  <td className="px-3 py-2 text-slate-600">
                    {r.destination ?? '—'}
                    {(r.departure_date || r.return_date) && (
                      <div className="text-xs text-slate-400">
                        {r.departure_date ?? '?'} → {r.return_date ?? '?'}
                      </div>
                    )}
                  </td>
                  <td className="px-3 py-2 text-right tabular-nums">{r.travellers ?? '—'}</td>
                  <td className="px-3 py-2 font-mono text-xs text-slate-500" title={r.reference}>
                    {r.reference}
                    {r.mapfre_quote_id && <div className="text-slate-400">quote {r.mapfre_quote_id}</div>}
                  </td>
                  <td className="px-3 py-2 text-xs">
                    {r.proposal_reference
                      ? (
                        r.proposal_document
                          ? <a href={r.proposal_document} target="_blank" rel="noreferrer" className="text-brand-navy underline">{r.proposal_reference}</a>
                          : <span className="font-mono text-slate-600">{r.proposal_reference}</span>
                      )
                      : <span className="text-slate-400">none</span>}
                    {r.proposal_signed_at && (
                      <div className="text-slate-400">signed {fmtDateTime(r.proposal_signed_at)}</div>
                    )}
                  </td>
                  <td className="px-3 py-2 text-slate-600">{fmtDateTime(r.submitted_at ?? r.created_at)}</td>
                </tr>
              ))}
              {data?.items.length === 0 && (
                <tr><td colSpan={9} className="px-3 py-8 text-center text-slate-500">No binds match these filters.</td></tr>
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
              className="rounded-md ring-1 ring-slate-300 bg-surface px-3 py-1.5 text-sm disabled:opacity-40"
            >
              Previous
            </button>
            <button
              onClick={() => setPage(Math.min(data.meta.last_page, data.meta.current_page + 1))}
              disabled={data.meta.current_page >= data.meta.last_page}
              className="rounded-md ring-1 ring-slate-300 bg-surface px-3 py-1.5 text-sm disabled:opacity-40"
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
      tone === 'warn' ? 'ring-amber-200 bg-amber-50' : 'ring-slate-200 bg-surface'
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
        className="mt-1 w-full rounded-md ring-1 ring-slate-300 bg-surface px-2 py-1.5 text-sm"
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
        className="mt-1 w-full rounded-md ring-1 ring-slate-300 bg-surface px-2 py-1.5 text-sm"
        value={value}
        onChange={(e) => onChange(e.target.value)}
      />
    </label>
  )
}
