import { useEffect, useMemo, useState, type ReactNode } from 'react'
import { useParams, Link } from 'react-router-dom'
import apiClient from '../../api/client'
import { fmtPula, fmtPulaSigned } from '../../utils/format'
import { downloadFromApi } from '../../utils/download'

const MATCH_STATUS_LABEL: Record<string, { label: string; cls: string }> = {
  matched:         { label: 'Matched',          cls: 'bg-green-100 text-green-700' },
  amount_mismatch: { label: 'Amount mismatch',  cls: 'bg-orange-100 text-orange-800' },
  date_mismatch:   { label: 'Date mismatch',    cls: 'bg-yellow-100 text-yellow-800' },
  status_mismatch: { label: 'Status mismatch',  cls: 'bg-yellow-100 text-yellow-800' },
  orphan_dpo:      { label: 'Orphan in DPO',    cls: 'bg-red-100 text-red-700' },
  duplicate:       { label: 'Duplicate match',  cls: 'bg-purple-100 text-purple-700' },
  manual_review:   { label: 'Manual review',    cls: 'bg-gray-200 text-gray-700' },
  pending:         { label: 'Pending',          cls: 'bg-blue-100 text-blue-700' },
}

const FINDING_LABEL: Record<string, string> = {
  none: '—', open: 'Open', accepted: 'Accepted', disputed: 'Disputed', resolved: 'Resolved',
}

export default function SettlementReconciliationDetailPage() {
  const { id } = useParams<{ id: string }>()
  const [run, setRun] = useState<any>(null)
  const [batches, setBatches] = useState<any[]>([])
  const [findings, setFindings] = useState<Record<string, number>>({})
  const [tab, setTab] = useState<'findings' | 'all' | 'batches'>('findings')
  const [matchFilter, setMatchFilter] = useState('all')
  const [findingFilter, setFindingFilter] = useState('open')
  const [providerTypeFilter, setProviderTypeFilter] = useState<string>('all')
  const [batchIdFilter, setBatchIdFilter] = useState<string>('')
  const [downloading, setDownloading] = useState(false)
  const [dateFrom, setDateFrom] = useState<string>('')
  const [dateTo, setDateTo] = useState<string>('')
  const [amountMin, setAmountMin] = useState<string>('')
  const [amountMax, setAmountMax] = useState<string>('')
  const [search, setSearch] = useState<string>('')
  const [searchInput, setSearchInput] = useState<string>('')
  const [sort, setSort] = useState<string>('id')
  const [sortDir, setSortDir] = useState<'asc' | 'desc'>('desc')
  const [perPage, setPerPage] = useState<number>(50)
  const [transactions, setTransactions] = useState<any[]>([])
  const [page, setPage] = useState(1)
  const [pageInput, setPageInput] = useState<string>('1')
  const [meta, setMeta] = useState<any>(null)
  const [loadingTx, setLoadingTx] = useState(false)
  const [busyId, setBusyId] = useState<number | null>(null)

  function loadRun() {
    if (!id) return
    apiClient.get(`/finance/settlement-reconciliation/runs/${id}`).then(r => {
      setRun(r.data?.run)
      setBatches(r.data?.batches ?? [])
      setFindings(r.data?.findings ?? {})
    })
  }

  function loadTx() {
    if (!id) return
    setLoadingTx(true)
    const params: any = { page, per_page: perPage }
    if (tab === 'findings') {
      params.only_findings = 1
      if (findingFilter !== 'all') params.finding_status = findingFilter
    } else if (tab === 'all') {
      if (matchFilter !== 'all') params.match_status = matchFilter
    }
    if (providerTypeFilter !== 'all') params.provider_type = providerTypeFilter
    if (batchIdFilter)      params.batch_id   = batchIdFilter
    if (dateFrom)           params.date_from  = dateFrom
    if (dateTo)             params.date_to    = dateTo
    if (amountMin !== '')   params.amount_min = amountMin
    if (amountMax !== '')   params.amount_max = amountMax
    if (search)             params.search     = search
    if (sort !== 'id') {
      params.sort = sort
      params.dir  = sortDir
    }
    apiClient.get(`/finance/settlement-reconciliation/runs/${id}/transactions`, { params })
      .then(r => { setTransactions(r.data?.data ?? []); setMeta(r.data) })
      .catch(() => { setTransactions([]); setMeta(null) })
      .finally(() => setLoadingTx(false))
  }

  useEffect(loadRun, [id])
  useEffect(loadTx, [id, tab, matchFilter, findingFilter, providerTypeFilter, batchIdFilter,
                     dateFrom, dateTo, amountMin, amountMax, search, sort, sortDir, perPage, page])
  useEffect(() => { setPageInput(String(page)) }, [page])

  function resetFilters() {
    setProviderTypeFilter('all'); setBatchIdFilter(''); setDateFrom(''); setDateTo('')
    setAmountMin(''); setAmountMax(''); setSearch(''); setSearchInput('')
    setSort('id'); setSortDir('desc'); setPage(1)
  }

  async function accept(txId: number, defaultNote = '') {
    const note = window.prompt('Optional note for accepting this finding:', defaultNote)
    if (note === null) return
    setBusyId(txId)
    try {
      await apiClient.post(`/finance/settlement-reconciliation/transactions/${txId}/accept`, { note })
      loadTx(); loadRun()
    } catch (e: any) { alert(e.response?.data?.message || 'Failed') }
    finally { setBusyId(null) }
  }

  async function dispute(txId: number) {
    const note = window.prompt('Reason for disputing this finding (required):')
    if (!note) return
    setBusyId(txId)
    try {
      await apiClient.post(`/finance/settlement-reconciliation/transactions/${txId}/dispute`, { note })
      loadTx(); loadRun()
    } catch (e: any) { alert(e.response?.data?.message || 'Failed') }
    finally { setBusyId(null) }
  }

  async function closeRun() {
    if (!confirm('Close this run? All findings must be accepted or disputed first.')) return
    try {
      await apiClient.post(`/finance/settlement-reconciliation/runs/${id}/close`, { notes: '' })
      loadRun()
    } catch (e: any) { alert(e.response?.data?.message || 'Cannot close — open findings remain.') }
  }

  async function rematchRun() {
    if (!confirm('Re-run the matcher on this run? Accepted findings are preserved; everything else gets re-evaluated. Useful after the matcher has been updated, or after missing local payment records have been added.')) return
    try {
      await apiClient.post(`/finance/settlement-reconciliation/runs/${id}/rematch`)
      // Poll until status flips back to matched
      let attempts = 0
      const maxAttempts = 30
      const poll = async (): Promise<void> => {
        attempts++
        const s = await apiClient.get(`/finance/settlement-reconciliation/runs/${id}`)
        const status = s.data?.run?.status
        if (status === 'matched' || status === 'closed' || status === 'reviewed' || status === 'failed') {
          loadRun(); loadTx()
          return
        }
        if (attempts >= maxAttempts) {
          loadRun(); loadTx()
          return
        }
        await new Promise(res => setTimeout(res, 5000))
        return poll()
      }
      await poll()
    } catch (e: any) {
      alert(e.response?.data?.message || 'Rematch failed')
    }
  }

  const findingCount = useMemo(
    () => Object.entries(findings).filter(([k]) => k !== 'matched').reduce((acc, [, v]: any) => acc + Number(v), 0),
    [findings]
  )

  if (!run) return <div className="p-6 text-gray-500">Loading…</div>

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <Link to="/finance/settlement-reconciliation" className="text-xs text-blue-600 hover:underline">← All runs</Link>
          <h1 className="text-2xl font-bold text-gray-800 mt-1">
            Run #{run.id} <span className="text-sm font-normal text-gray-400">({run.provider.toUpperCase()})</span>
          </h1>
          <p className="text-xs text-gray-500">{run.file_name} · {run.settlement_date_from} → {run.settlement_date_to}</p>
        </div>
        <div className="flex items-center gap-2">
          {/*
            A button, not a link. A root-relative href resolves against the
            FRONTEND host, which has no /api/v1 — the SPA's catch-all answered it
            with "Page Not Found" instead of the file. It also carried no bearer
            token. Going through the API client fixes both.
          */}
          <button
            onClick={async () => {
              setDownloading(true)
              try {
                await downloadFromApi(
                  `/finance/settlement-reconciliation/runs/${run.id}/download-original`,
                  {},
                  run.file_name || `settlement-run-${run.id}.csv`,
                )
              } catch (e: any) {
                alert(e?.message || 'The file could not be downloaded.')
              } finally {
                setDownloading(false)
              }
            }}
            disabled={downloading}
            className="px-3 py-1.5 text-sm rounded-md bg-gray-100 hover:bg-gray-200 disabled:bg-gray-50 disabled:text-gray-400"
          >{downloading ? 'Preparing…' : 'Download original CSV'}</button>
          {run.status !== 'closed' && (
            <button onClick={rematchRun}
              disabled={run.status === 'parsing'}
              className="px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700 disabled:bg-gray-300"
              title="Re-run matcher on existing settlement rows. Accepted findings are kept; everything else is re-evaluated.">
              Rematch
            </button>
          )}
          {run.status !== 'closed' && (
            <button onClick={closeRun}
              disabled={Number(findings.open ?? 0) > 0}
              className="px-3 py-1.5 text-sm rounded-md bg-green-600 text-white hover:bg-green-700 disabled:bg-gray-300">
              Close run
            </button>
          )}
        </div>
      </div>

      {/* Stat tiles */}
      <div className="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
        <Tile label="Status" value={run.status} className="bg-blue-50 text-blue-800" />
        <Tile label="Batches" value={run.batch_count} />
        <Tile label="Transactions" value={run.transaction_count} />
        <Tile label="Matched" value={run.matched_count} className="bg-green-50 text-green-800" />
        <Tile label="Findings" value={run.unmatched_count} className={run.unmatched_count > 0 ? 'bg-red-50 text-red-800' : ''} />
        <Tile label="DPO Total" value={run.total_dpo_amount == null ? '—' : fmtPula(run.total_dpo_amount)} />
        <Tile label="Drift" value={run.drift_total == null ? '—' : fmtPulaSigned(run.drift_total)} className={Math.abs(run.drift_total) > 0.005 ? 'bg-red-50 text-red-800' : ''} />
      </div>

      {/* Heuristic banner: if ≥20% of orphans cluster on the last 1–3 days of
          the run's date range, the RDS clone is likely stale and post-cutoff
          orphans are noise rather than real findings. */}
      {(findings.orphan_dpo as number) > 100 && run.settlement_date_to && (
        <div className="bg-amber-50 border border-amber-200 rounded-md p-3 text-xs text-amber-900 flex items-start gap-2">
          <span className="text-base leading-none">ⓘ</span>
          <div>
            <div className="font-medium">Heads up — many orphans cluster at the end of the period.</div>
            <div className="mt-1">
              If a large share of the {findings.orphan_dpo as number} <span className="font-mono">orphan_dpo</span> findings
              fall on the last day or two of the file's window
              ({run.settlement_date_from} → {run.settlement_date_to}), that's likely the local
              <code className="bg-amber-100 px-1 rounded mx-1">payment_transactions</code>
              data being older than the settlement file (e.g. test-RDS clone cutoff). Use the
              <strong> Date from</strong> filter below to focus on dates the local DB actually covers.
              Hit <strong>Rematch</strong> after the local data is refreshed.
            </div>
          </div>
        </div>
      )}

      {/* Tabs */}
      <div className="border-b border-gray-200 flex gap-4">
        {(['findings', 'all', 'batches'] as const).map(t => (
          <button key={t} onClick={() => { setTab(t); setPage(1) }}
            className={`pb-2 px-1 text-sm font-medium ${tab === t ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-gray-700'}`}>
            {t === 'findings' && `Findings (${findingCount})`}
            {t === 'all' && `All transactions`}
            {t === 'batches' && `Batches (${batches.length})`}
          </button>
        ))}
      </div>

      {/* Tab content */}
      {(tab === 'findings' || tab === 'all') && (
        <>
          {/* Tab-specific status pills */}
          {tab === 'findings' && (
            <div className="flex items-center gap-2 text-xs">
              <span className="font-medium text-gray-500">Status:</span>
              {(['open', 'accepted', 'disputed', 'all'] as const).map(s => (
                <button key={s} onClick={() => { setFindingFilter(s); setPage(1) }}
                  className={`px-2 py-0.5 rounded ${findingFilter === s ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600'}`}>{s}</button>
              ))}
            </div>
          )}
          {tab === 'all' && (
            <div className="flex items-center gap-2 text-xs flex-wrap">
              <span className="font-medium text-gray-500">Match status:</span>
              {['all', 'matched', 'amount_mismatch', 'orphan_dpo', 'duplicate', 'manual_review', 'date_mismatch'].map(s => (
                <button key={s} onClick={() => { setMatchFilter(s); setPage(1) }}
                  className={`px-2 py-0.5 rounded ${matchFilter === s ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600'}`}>{s}</button>
              ))}
            </div>
          )}

          {/* Shared filter bar */}
          <div className="bg-white border border-gray-200 rounded-md p-3 flex flex-wrap items-end gap-3 text-xs">
            <FilterField label="Search">
              <input type="search" value={searchInput} onChange={e => setSearchInput(e.target.value)}
                onKeyDown={e => { if (e.key === 'Enter') { setPage(1); setSearch(searchInput) } }}
                placeholder="Trans ref, ref id, MIS… or policy number"
                className="px-2 py-1 border rounded text-xs w-72" />
            </FilterField>
            <FilterField label="Type">
              <select value={providerTypeFilter} onChange={e => { setProviderTypeFilter(e.target.value); setPage(1) }}
                className="px-2 py-1 border rounded text-xs">
                <option value="all">all</option>
                <option value="Transaction">Transaction</option>
                <option value="Refund">Refund</option>
                <option value="Manual">Manual</option>
                <option value="Other">Other</option>
              </select>
            </FilterField>
            <FilterField label="Batch">
              <select value={batchIdFilter} onChange={e => { setBatchIdFilter(e.target.value); setPage(1) }}
                className="px-2 py-1 border rounded text-xs max-w-[180px]">
                <option value="">all batches</option>
                {batches.map(b => (
                  <option key={b.id} value={b.id}>{b.provider_batch_id} · {b.batch_date}</option>
                ))}
              </select>
            </FilterField>
            <FilterField label="Date from">
              <input type="date" value={dateFrom} onChange={e => { setDateFrom(e.target.value); setPage(1) }}
                className="px-2 py-1 border rounded text-xs" />
            </FilterField>
            <FilterField label="Date to">
              <input type="date" value={dateTo} onChange={e => { setDateTo(e.target.value); setPage(1) }}
                className="px-2 py-1 border rounded text-xs" />
            </FilterField>
            <FilterField label="Amount ≥">
              <input type="number" step="0.01" value={amountMin} onChange={e => { setAmountMin(e.target.value); setPage(1) }}
                className="px-2 py-1 border rounded text-xs w-24" />
            </FilterField>
            <FilterField label="Amount ≤">
              <input type="number" step="0.01" value={amountMax} onChange={e => { setAmountMax(e.target.value); setPage(1) }}
                className="px-2 py-1 border rounded text-xs w-24" />
            </FilterField>
            <FilterField label="Sort">
              <select value={`${sort}:${sortDir}`} onChange={e => {
                const [s, d] = e.target.value.split(':')
                setSort(s); setSortDir(d as 'asc' | 'desc'); setPage(1)
              }} className="px-2 py-1 border rounded text-xs">
                <option value="id:desc">newest first</option>
                <option value="date:desc">date ↓</option>
                <option value="date:asc">date ↑</option>
                <option value="amount:desc">amount ↓ (highest)</option>
                <option value="amount:asc">amount ↑ (lowest)</option>
                <option value="drift:desc">drift ↓ (biggest)</option>
              </select>
            </FilterField>
            <FilterField label="Per page">
              <select value={perPage} onChange={e => { setPerPage(Number(e.target.value)); setPage(1) }}
                className="px-2 py-1 border rounded text-xs">
                {[25, 50, 100, 200].map(n => <option key={n} value={n}>{n}</option>)}
              </select>
            </FilterField>
            <button onClick={resetFilters} className="px-3 py-1 text-xs rounded bg-gray-100 hover:bg-gray-200">
              Reset filters
            </button>
          </div>

          <TxTable rows={transactions} loading={loadingTx} busyId={busyId}
            onAccept={tab === 'findings' ? accept : undefined}
            onDispute={tab === 'findings' ? dispute : undefined}
            showActions={tab === 'findings'} />
          <Pager meta={meta} setPage={setPage} pageInput={pageInput} setPageInput={setPageInput} />
        </>
      )}

      {tab === 'batches' && (
        <div className="bg-white shadow rounded-lg overflow-hidden">
          <table className="min-w-full divide-y divide-gray-200 text-sm">
            <thead className="bg-gray-50"><tr>
              <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Batch ID</th>
              <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
              <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">DPO Total</th>
              <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Local Matched</th>
              <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Drift</th>
              <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Tx</th>
              <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Matched</th>
              <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Findings</th>
            </tr></thead>
            <tbody className="divide-y divide-gray-200">
              {batches.map(b => {
                const driftBad = Math.abs(b.drift_amount) > 0.005
                return (
                  <tr key={b.id} className="hover:bg-gray-50">
                    <td className="px-3 py-2 font-mono text-xs">{b.provider_batch_id}</td>
                    <td className="px-3 py-2 text-xs">{b.batch_date}</td>
                    <td className="px-3 py-2 text-right text-xs font-mono">{b.batch_total_amount == null ? '—' : fmtPula(b.batch_total_amount)}</td>
                    <td className="px-3 py-2 text-right text-xs font-mono">{b.matched_local_total == null ? '—' : fmtPula(b.matched_local_total)}</td>
                    <td className={`px-3 py-2 text-right text-xs font-mono ${driftBad ? 'text-red-700 font-bold' : 'text-gray-400'}`}>
                      {b.drift_amount == null ? '—' : fmtPulaSigned(b.drift_amount)}
                    </td>
                    <td className="px-3 py-2 text-right text-xs">{b.transaction_count}</td>
                    <td className="px-3 py-2 text-right text-xs text-green-700">{b.matched_count}</td>
                    <td className={`px-3 py-2 text-right text-xs font-semibold ${b.unmatched_count > 0 ? 'text-red-700' : 'text-gray-400'}`}>
                      {b.unmatched_count || '—'}
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}

function Tile({ label, value, className = '' }: { label: string; value: any; className?: string }) {
  return (
    <div className={`bg-white border border-gray-200 rounded-md p-3 ${className}`}>
      <div className="text-[10px] uppercase text-gray-500 tracking-wider">{label}</div>
      <div className="text-lg font-semibold mt-1">{value}</div>
    </div>
  )
}

function FilterField({ label, children }: { label: string; children: ReactNode }) {
  return (
    <label className="flex flex-col gap-1">
      <span className="text-[10px] font-medium text-gray-500 uppercase tracking-wider">{label}</span>
      {children}
    </label>
  )
}

function Pager({
  meta, setPage, pageInput, setPageInput,
}: {
  meta: any
  setPage: (n: number) => void
  pageInput: string
  setPageInput: (s: string) => void
}) {
  if (!meta) return null
  function jump() {
    const n = Math.max(1, Math.min(Number(pageInput) || 1, meta.last_page))
    setPage(n)
  }
  return (
    <div className="flex items-center justify-between text-xs text-gray-500 pt-2 flex-wrap gap-2">
      <div>Showing {meta.from ?? 0}–{meta.to ?? 0} of {meta.total ?? 0}</div>
      <div className="flex items-center gap-1">
        <button onClick={() => setPage(1)} disabled={meta.current_page <= 1}
          className="px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 disabled:opacity-40" title="First">«</button>
        <button onClick={() => setPage(meta.current_page - 1)} disabled={meta.current_page <= 1}
          className="px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 disabled:opacity-40">Prev</button>
        <span className="px-2 py-1">Page</span>
        <input
          type="number"
          min={1}
          max={meta.last_page}
          value={pageInput}
          onChange={e => setPageInput(e.target.value)}
          onKeyDown={e => { if (e.key === 'Enter') jump() }}
          onBlur={jump}
          className="w-14 px-1.5 py-0.5 border rounded text-center text-xs"
        />
        <span className="px-1 py-1">/ {meta.last_page}</span>
        <button onClick={() => setPage(meta.current_page + 1)} disabled={meta.current_page >= meta.last_page}
          className="px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 disabled:opacity-40">Next</button>
        <button onClick={() => setPage(meta.last_page)} disabled={meta.current_page >= meta.last_page}
          className="px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 disabled:opacity-40" title="Last">»</button>
      </div>
    </div>
  )
}

function TxTable(props: {
  rows: any[];
  loading: boolean;
  busyId: number | null;
  onAccept?: (id: number, defaultNote?: string) => void;
  onDispute?: (id: number) => void;
  showActions?: boolean;
}) {
  return (
    <div className="bg-white shadow rounded-lg overflow-x-auto">
      <table className="min-w-full divide-y divide-gray-200 text-sm">
        <thead className="bg-gray-50">
          <tr>
            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">DPO Ref</th>
            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Provider Ref</th>
            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tx Date</th>
            <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">DPO Amt</th>
            <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Local Amt</th>
            <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Drift</th>
            <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Match</th>
            <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Finding</th>
            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Local Policy</th>
            {props.showActions && <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>}
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-200">
          {props.loading && <tr><td colSpan={11} className="px-3 py-8 text-center text-gray-400">Loading…</td></tr>}
          {!props.loading && props.rows.length === 0 && (
            <tr><td colSpan={11} className="px-3 py-8 text-center text-gray-400">No transactions match this filter.</td></tr>
          )}
          {props.rows.map(t => {
            const ms = MATCH_STATUS_LABEL[t.match_status] ?? { label: t.match_status, cls: 'bg-gray-100 text-gray-600' }
            const driftBad = Math.abs(t.drift_amount) > 0.005
            return (
              <tr key={t.id} className="hover:bg-gray-50">
                <td className="px-3 py-2 text-xs">{t.provider_type}</td>
                <td className="px-3 py-2 font-mono text-xs">{t.provider_trans_ref ?? '—'}<br /><span className="text-gray-400">{t.provider_ref_id}</span></td>
                <td className="px-3 py-2 font-mono text-xs">{t.provider_external_ref ?? '—'}</td>
                <td className="px-3 py-2 text-xs">{t.transaction_date ?? '—'}</td>
                <td className="px-3 py-2 text-right font-mono text-xs">{t.paid_amount == null ? '—' : fmtPula(t.paid_amount)}</td>
                <td className="px-3 py-2 text-right font-mono text-xs">{t.local_amount == null ? '—' : fmtPula(t.local_amount)}</td>
                <td className={`px-3 py-2 text-right font-mono text-xs ${driftBad ? 'text-red-700 font-bold' : 'text-gray-400'}`}>
                  {driftBad ? fmtPulaSigned(t.drift_amount) : '—'}
                </td>
                <td className="px-3 py-2 text-center">
                  <span className={`inline-block px-2 py-0.5 rounded text-[10px] font-medium ${ms.cls}`}>{ms.label}</span>
                </td>
                <td className="px-3 py-2 text-center text-xs">{FINDING_LABEL[t.finding_status] ?? t.finding_status}</td>
                <td className="px-3 py-2 text-xs">
                  {t.local_policy_id ? (
                    <Link to={`/policies/${t.local_policy_id}`} className="text-blue-600 hover:underline font-mono">
                      {t.local_policy_number ?? `#${t.local_policy_id}`}
                    </Link>
                  ) : '—'}
                </td>
                {props.showActions && (
                  <td className="px-3 py-2 text-right whitespace-nowrap">
                    {t.finding_status === 'open' && t.match_status !== 'matched' && (
                      <>
                        <button onClick={() => props.onAccept?.(t.id)}
                          disabled={props.busyId === t.id}
                          className="text-xs text-green-700 hover:underline mr-2">Accept</button>
                        <button onClick={() => props.onDispute?.(t.id)}
                          disabled={props.busyId === t.id}
                          className="text-xs text-red-700 hover:underline">Dispute</button>
                      </>
                    )}
                    {t.finding_status !== 'open' && t.review_note && (
                      <span className="text-[10px] text-gray-500" title={t.review_note}>📝 noted</span>
                    )}
                  </td>
                )}
              </tr>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}
