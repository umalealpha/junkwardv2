import { useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import ClaimsChrome from './_chrome/ClaimsChrome'
import { useClaimsTrackerList } from '../../hooks/useClaimsTrackerList'
import {
  fetchAllClaimsTrackerListForExport,
  softDeleteClaim,
  type ClaimsTrackerListParams,
  type ClaimsTrackerListRow,
  type TrackerListSortCol,
} from '../../api/claimsTrackerList'
import { getStoredPermissions } from '../../api/auth'
import { useToast } from '../../components/common/Toast'
import Modal from '../../components/common/Modal'
import { SkeletonTable } from '../../components/common/Skeleton'
import { fmtDate } from '../../utils/format'
import { downloadCsv } from '../../utils/csv'

/**
 * Claims → All Claims — a 1:1 rebuild of the legacy Claims Tracker list screen,
 * fed by GET /claims-v2/tracker-list. Renders inside Graphite's AdminLayout with
 * the shared <ClaimsChrome /> tab strip on top. Canonical brand + design tokens
 * only (no hardcoded hex); emoji labels kept for exact familiarity.
 */

const PER_PAGE_OPTIONS = [15, 25, 50] as const
type PerPage = (typeof PER_PAGE_OPTIONS)[number]

// Keys that count as "active filters" for the filtered/count/export wording.
const FILTER_KEYS: (keyof ClaimsTrackerListParams)[] = [
  'search', 'status', 'decision', 'sync', 'channel', 'claim_type', 'stage', 'month',
]

/** "2026-08" → "Aug 2026". */
function fmtMonthLabel(ym: string): string {
  const m = /^(\d{4})-(\d{2})$/.exec(ym)
  if (!m) return ym
  const d = new Date(Number(m[1]), Number(m[2]) - 1, 1)
  if (isNaN(d.getTime())) return ym
  return d.toLocaleDateString('en-GB', { month: 'short', year: 'numeric' })
}

// ── Status badge (col 8) ─────────────────────────────────────────────────────
type StatusTone = 'danger' | 'info' | 'accent' | 'success'
const STATUS_TONE_CLS: Record<StatusTone, { badge: string; dot: string }> = {
  danger:  { badge: 'bg-status-danger-bg text-status-danger-fg',   dot: 'bg-status-danger-fg' },
  info:    { badge: 'bg-status-info-bg text-status-info-fg',       dot: 'bg-status-info-fg' },
  accent:  { badge: 'bg-status-accent-bg text-status-accent-fg',   dot: 'bg-status-accent-fg' },
  success: { badge: 'bg-status-success-bg text-status-success-fg', dot: 'bg-status-success-fg' },
}
function statusTone(overallStatus: string): StatusTone {
  const s = overallStatus
  if (/Delayed|Overdue/i.test(s)) return 'danger'
  if (/On Time/i.test(s) || s === 'Complete' || s === 'NM: Complete') return 'info'
  if (s === 'In Progress' || s === 'NM: In Progress') return 'accent'
  return 'success'
}

// ── Comment tone → text token (col 9) ────────────────────────────────────────
const COMMENT_TONE_CLS: Record<'danger' | 'warning' | 'success' | 'navy', string> = {
  danger:  'text-status-danger-fg',
  warning: 'text-status-warning-fg',
  success: 'text-status-success-fg',
  navy:    'text-brand-navy',
}

// ── Small chip under the claim number ────────────────────────────────────────
function Chip({ text, tone, title }: { text: string; tone: 'danger' | 'navy' | 'success' | 'warning'; title?: string }) {
  const cls = {
    danger:  'bg-status-danger-bg text-status-danger-fg',
    navy:    'bg-brand-navy/10 text-brand-navy',
    success: 'bg-status-success-bg text-status-success-fg',
    warning: 'bg-status-warning-bg text-status-warning-fg',
  }[tone]
  return (
    <span
      title={title}
      className={`inline-flex w-fit px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide ${cls}`}
    >
      {text}
    </span>
  )
}

// ── Sortable header cell (navy header) ───────────────────────────────────────
function SortableTh({
  label, col, sort, direction, onSort, className = '',
}: {
  label: string
  col: TrackerListSortCol
  sort?: TrackerListSortCol
  direction: 'asc' | 'desc'
  onSort: (col: TrackerListSortCol) => void
  className?: string
}) {
  const active = sort === col
  const caret = !active ? '↕' : direction === 'asc' ? '↑' : '↓'
  return (
    <th className={`px-3 py-2.5 text-left font-semibold whitespace-nowrap ${className}`}>
      <button
        type="button"
        onClick={() => onSort(col)}
        className="inline-flex items-center gap-1 cursor-pointer hover:text-brand-orange transition-colors"
        title={`Sort by ${label}`}
      >
        {label}
        <span className={`text-[10px] ${active ? 'opacity-100' : 'opacity-50'}`}>{caret}</span>
      </button>
    </th>
  )
}

export default function ClaimListPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { toast } = useToast()

  const canDelete = useMemo(
    () => getStoredPermissions().includes('claim-delete'),
    [],
  )

  const [perPage, setPerPage] = useState<PerPage>(15)
  const [params, setParams] = useState<ClaimsTrackerListParams>({
    page: 1,
    per_page: 15,
    sort: 'reportedDate',
    direction: 'desc',
  })
  const [searchInput, setSearchInput] = useState('')
  const [exporting, setExporting] = useState(false)
  const [shareRow, setShareRow] = useState<ClaimsTrackerListRow | null>(null)
  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [deleteRow, setDeleteRow] = useState<ClaimsTrackerListRow | null>(null)
  const [deleteReason, setDeleteReason] = useState('')
  const [deleteErr, setDeleteErr] = useState('')

  // Debounce free-text search (300ms) into params; resets to page 1.
  useEffect(() => {
    const t = setTimeout(() => {
      setParams((prev) => {
        const next = searchInput.trim() || undefined
        if (prev.search === next) return prev
        return { ...prev, search: next, page: 1 }
      })
    }, 300)
    return () => clearTimeout(t)
  }, [searchInput])

  const { data, isLoading, isFetching, error } = useClaimsTrackerList(params)

  const rows = data?.data ?? []
  const meta = data?.meta
  const availableMonths = data?.availableMonths ?? []
  const filterOptions = data?.filterOptions ?? { stages: [], claimTypes: [], nonMotorSubTypes: [] }

  const sort = params.sort
  const direction = params.direction ?? 'desc'

  const isFiltered = useMemo(
    () => FILTER_KEYS.some((k) => params[k] !== undefined && params[k] !== ''),
    [params],
  )

  // Remember the whole-book total (the count when no filters are applied) so the
  // count line can render "{filtered} of {book} claims (filtered)".
  const grandTotalRef = useRef<number | null>(null)
  useEffect(() => {
    if (!isFiltered && meta) grandTotalRef.current = meta.total
  }, [isFiltered, meta])

  const total = meta?.total ?? 0
  const grandTotal = grandTotalRef.current ?? total
  const page = meta?.current_page ?? params.page ?? 1
  const lastPage = meta?.last_page ?? 1
  const per = meta?.per_page ?? perPage
  const from = total === 0 ? 0 : (page - 1) * per + 1
  const to = Math.min(page * per, total)

  const countLine = isFiltered
    ? `${total.toLocaleString()} of ${grandTotal.toLocaleString()} claims (filtered)`
    : `${total.toLocaleString()} claims`

  // ── mutators ───────────────────────────────────────────────────────────────
  /** Single-select filter change: patch the param and reset to page 1. */
  function patch(p: Partial<ClaimsTrackerListParams>) {
    setParams((prev) => ({ ...prev, ...p, page: 1 }))
  }

  function handleSort(col: TrackerListSortCol) {
    setParams((prev) => {
      const sameCol = prev.sort === col
      const nextDir = sameCol && (prev.direction ?? 'desc') === 'desc' ? 'asc' : 'desc'
      return { ...prev, sort: col, direction: nextDir, page: 1 }
    })
  }

  function goToPage(p: number) {
    setParams((prev) => ({ ...prev, page: p }))
  }

  function changePerPage(pp: PerPage) {
    setPerPage(pp)
    setParams((prev) => ({ ...prev, per_page: pp, page: 1 }))
  }

  async function handleExport() {
    setExporting(true)
    try {
      const { page: _p, per_page: _pp, ...exportFilters } = params
      const all = await fetchAllClaimsTrackerListForExport(exportFilters)
      const headers = [
        'Claim #', 'Client', 'Channel', 'Handler', 'Reported', 'Type', 'Sub-Type',
        'Plate', 'Stage', 'Status', 'Days Late', 'Reserve', 'Decision',
        'Major', 'FAC', 'Synced',
      ]
      const csvRows = all.map((r) => [
        r.claimNumber,
        r.clientName,
        r.channel,
        r.handler,
        fmtDate(r.reportedDate, { day: '2-digit', month: 'short', year: 'numeric' }, ''),
        r.claimType,
        r.nonMotorSubType ?? '',
        r.plate ?? '',
        r.currentStage,
        r.overallStatus,
        r.daysLate,
        r.reserve || '',
        r.decision ?? '',
        r.isMajor ? 'Yes' : 'No',
        r.isFac ? 'Yes' : 'No',
        r.synced ? 'Yes' : 'No',
      ])
      const stamp = new Date().toISOString().slice(0, 10)
      downloadCsv(`claims-${stamp}`, headers, csvRows)
    } catch (e) {
      toast.error('Export failed: ' + ((e as Error).message ?? 'unknown error'))
    } finally {
      setExporting(false)
    }
  }

  function handleDelete(row: ClaimsTrackerListRow) {
    setDeleteRow(row)
    setDeleteReason('')
    setDeleteErr('')
  }

  async function confirmDelete() {
    if (!deleteRow) return
    const reason = deleteReason.trim()
    if (reason.length < 3) {
      setDeleteErr('Please enter a reason (at least 3 characters).')
      return
    }
    setDeletingId(deleteRow.claimId)
    try {
      await softDeleteClaim(deleteRow.claimId, reason)
      await queryClient.invalidateQueries({ queryKey: ['claims-tracker-list'] })
      toast.success(`Claim ${deleteRow.claimNumber} deleted.`)
      setDeleteRow(null)
    } catch (e) {
      setDeleteErr('Delete failed: ' + ((e as Error).message ?? 'unknown error'))
    } finally {
      setDeletingId(null)
    }
  }

  // ── styling helpers ──────────────────────────────────────────────────────────
  const selectCls =
    'px-2.5 py-1.5 border border-line rounded-md text-sm bg-surface text-ink cursor-pointer'
  // Sticky Actions column: opaque surface so scrolled rows never bleed through,
  // left border + soft left drop-shadow (rgba in an arbitrary shadow — no hex).
  const stickyTd =
    'sticky right-0 z-10 bg-surface group-hover:bg-surface-2 border-l border-line ' +
    'shadow-[-6px_0_12px_-6px_rgba(2,6,23,0.18)]'
  const actionBtn =
    'inline-flex items-center rounded px-2 py-1 text-[0.7rem] font-semibold cursor-pointer transition-colors'

  return (
    <div>
      <ClaimsChrome />

      {/* A. Title + count */}
      <div className="mb-3">
        <h1 className="font-heading text-xl font-extrabold text-brand-navy">All Claims</h1>
        <p className="text-sm text-ink-muted mt-0.5">{countLine}</p>
      </div>

      {/* B. Filter row */}
      <div className="bg-surface rounded-lg border border-line p-3 mb-3">
        <div className="flex flex-wrap items-center gap-2">
          {/* Search */}
          <div className="relative flex-1 min-w-[220px]">
            <input
              type="text"
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              placeholder="Search claim, client, plate, handler…"
              className="w-full pl-3 pr-8 py-1.5 border border-line rounded-md text-sm bg-surface text-ink"
            />
            {searchInput && (
              <button
                type="button"
                onClick={() => setSearchInput('')}
                aria-label="Clear search"
                className="absolute right-2 top-1/2 -translate-y-1/2 text-ink-faint hover:text-ink text-sm leading-none cursor-pointer"
              >
                ✕
              </button>
            )}
          </div>

          {/* Statuses */}
          <select
            value={params.status ?? ''}
            onChange={(e) => patch({ status: e.target.value || undefined })}
            className={selectCls}
            title="Status"
          >
            <option value="">All Statuses</option>
            <optgroup label="Special Flags">
              <option value="MAJOR">🚨 Major Claims (Reserve &gt; P300K)</option>
              <option value="FAC">📋 FAC Claims (Facultative)</option>
            </optgroup>
            <optgroup label="Quick">
              <option value="Complete">✓ All Completed (any)</option>
            </optgroup>
            <optgroup label="Motor">
              <option value="In Progress">In Progress</option>
              <option value="Overdue">Overdue</option>
              <option value="Delayed">Delayed</option>
              <option value="Complete - On Time">Complete - On Time</option>
              <option value="Complete - Delayed">Complete - Delayed</option>
            </optgroup>
            <optgroup label="Non-Motor">
              <option value="NM: In Progress">NM: In Progress</option>
              <option value="NM: Overdue">NM: Overdue</option>
              <option value="NM: Complete - On Time">NM: Complete - On Time</option>
              <option value="NM: Complete - Delayed">NM: Complete - Delayed</option>
              <option value="NM:">All Non-Motor</option>
            </optgroup>
          </select>

          {/* Decisions */}
          <select
            value={params.decision ?? ''}
            onChange={(e) => patch({ decision: e.target.value || undefined })}
            className={selectCls}
            title="Decision"
          >
            <option value="">All Decisions</option>
            <option value="pending">pending</option>
            <option value="approved">✓ Approved</option>
            <option value="repudiated">✗ Repudiated</option>
            <option value="reversed">↺ Reversed</option>
          </select>

          {/* Sync Status */}
          <select
            value={params.sync ?? ''}
            onChange={(e) => patch({ sync: e.target.value || undefined })}
            className={selectCls}
            title="Sync status"
          >
            <option value="">All Sync Status</option>
            <option value="synced">🔗 Synced</option>
            <option value="pending">⏳ Pending</option>
            <option value="failed">🚨 Sync failed</option>
          </select>

          {/* Channels */}
          <select
            value={params.channel ?? ''}
            onChange={(e) => patch({ channel: e.target.value || undefined })}
            className={selectCls}
            title="Channel"
          >
            <option value="">All Channels</option>
            <option value="Broker">Broker</option>
            <option value="Direct">Direct</option>
          </select>

          {/* Types */}
          <select
            value={params.claim_type ?? ''}
            onChange={(e) => patch({ claim_type: e.target.value || undefined })}
            className={selectCls}
            title="Type"
          >
            <option value="">All Types</option>
            {filterOptions.claimTypes.map((t) => (
              <option key={t} value={t}>{t}</option>
            ))}
            {filterOptions.nonMotorSubTypes.length > 0 && (
              <optgroup label="Non-Motor — by class">
                {filterOptions.nonMotorSubTypes.map((sub) => (
                  <option key={sub} value={`NMSUB:${sub}`}>↳ {sub}</option>
                ))}
              </optgroup>
            )}
          </select>

          {/* Stages */}
          <select
            value={params.stage ?? ''}
            onChange={(e) => patch({ stage: e.target.value || undefined })}
            className={selectCls}
            title="Stage"
          >
            <option value="">All Stages</option>
            {filterOptions.stages.map((s) => (
              <option key={s} value={s}>{s}</option>
            ))}
          </select>

          {/* Months */}
          <select
            value={params.month ?? ''}
            onChange={(e) => patch({ month: e.target.value || undefined })}
            className={selectCls}
            title="Month"
          >
            <option value="">All Months</option>
            {availableMonths.map((m) => (
              <option key={m} value={m}>{fmtMonthLabel(m)}</option>
            ))}
          </select>

          {/* Import / Export */}
          <div className="flex items-center gap-2 ml-auto no-print">
            <button
              type="button"
              onClick={() => navigate('/claims/bulk-import')}
              className="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-semibold bg-surface-2 text-ink border border-line hover:bg-line/40 cursor-pointer"
            >
              ⬆ Import
            </button>
            <button
              type="button"
              onClick={handleExport}
              disabled={exporting || !total}
              className="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-semibold bg-brand-navy text-white border border-brand-navy hover:bg-brand-navy/90 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {exporting ? 'Exporting…' : isFiltered ? `⬇ Export (${total.toLocaleString()})` : '⬇ Export'}
            </button>
          </div>
        </div>
      </div>

      {/* C + D. Table with top & bottom pagination */}
      <div className="bg-surface rounded-lg border border-line overflow-hidden">
        {/* Top pagination */}
        <PaginationBar
          from={from} to={to} total={total} page={page} lastPage={lastPage}
          perPage={perPage} onPage={goToPage} onPerPage={changePerPage} loading={isFetching}
        />

        {isLoading ? (
          <div className="p-4"><SkeletonTable rows={8} cols={12} /></div>
        ) : error ? (
          <div className="p-8 text-center text-status-danger-fg">
            Failed to load claims. {(error as Error).message}
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[1080px] text-sm border-collapse">
              <thead className="bg-brand-navy text-white uppercase text-[0.68rem] tracking-wide">
                <tr>
                  <SortableTh label="Claim #" col="claimNumber" sort={sort} direction={direction} onSort={handleSort} />
                  <SortableTh label="Client" col="clientName" sort={sort} direction={direction} onSort={handleSort} />
                  <SortableTh label="Channel" col="channel" sort={sort} direction={direction} onSort={handleSort} />
                  <SortableTh label="Handler" col="handler" sort={sort} direction={direction} onSort={handleSort} />
                  <SortableTh label="Reported" col="reportedDate" sort={sort} direction={direction} onSort={handleSort} />
                  <SortableTh label="Type" col="claimType" sort={sort} direction={direction} onSort={handleSort} />
                  <SortableTh label="Stage" col="currentStage" sort={sort} direction={direction} onSort={handleSort} />
                  <SortableTh label="Status" col="overallStatus" sort={sort} direction={direction} onSort={handleSort} />
                  <th className="px-3 py-2.5 text-left font-semibold">Comments</th>
                  <SortableTh label="Days Late" col="daysLate" sort={sort} direction={direction} onSort={handleSort} className="text-right" />
                  <SortableTh label="Reserve" col="reserve" sort={sort} direction={direction} onSort={handleSort} className="text-right" />
                  <th className="px-3 py-2.5 text-left font-semibold sticky right-0 z-20 bg-brand-navy border-l border-white/20 no-print">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody>
                {rows.length === 0 ? (
                  <tr>
                    <td colSpan={12} className="px-4 py-12 text-center text-ink-muted">
                      📋 No claims match your filters.
                    </td>
                  </tr>
                ) : (
                  rows.map((r) => {
                    const tone = STATUS_TONE_CLS[statusTone(r.overallStatus)]
                    const isNm = r.overallStatus.startsWith('NM:')
                    const statusText = isNm ? r.overallStatus.replace(/^NM:\s*/, '') : r.overallStatus
                    return (
                      <tr key={r.claimId} className="group border-b border-line hover:bg-surface-2/60">
                        {/* 1. Claim # + chips */}
                        <td className="px-3 py-2 align-top whitespace-nowrap">
                          <button
                            type="button"
                            onClick={() => navigate(`/claims/${r.claimId}`)}
                            className="font-bold text-brand-navy hover:underline cursor-pointer"
                          >
                            {r.claimNumber}
                          </button>
                          {(r.isMajor || r.isFac || r.decision) && (
                            <div className="flex flex-col gap-1 mt-1">
                              {r.isMajor && <Chip text="🚨 MAJOR" tone="danger" title="Reserve > P300,000" />}
                              {r.isFac && <Chip text="📋 FAC" tone="navy" title="Facultative reinsurance" />}
                              {r.decision === 'approved' && <Chip text="✓ APPROVED" tone="success" />}
                              {r.decision === 'repudiated' && <Chip text="✗ REPUDIATED" tone="danger" />}
                              {r.decision === 'reversed' && <Chip text="↺ REVERSED" tone="warning" />}
                            </div>
                          )}
                        </td>

                        {/* 2. Client */}
                        <td className="px-3 py-2 align-top text-ink">{r.clientName || '—'}</td>

                        {/* 3. Channel */}
                        <td className="px-3 py-2 align-top text-ink-muted whitespace-nowrap">{r.channel || '—'}</td>

                        {/* 4. Handler */}
                        <td className="px-3 py-2 align-top text-ink whitespace-nowrap">{r.handler || '—'}</td>

                        {/* 5. Reported */}
                        <td className="px-3 py-2 align-top text-ink-muted whitespace-nowrap">
                          {fmtDate(r.reportedDate, { day: '2-digit', month: 'short', year: 'numeric' })}
                        </td>

                        {/* 6. Type (+ sub-type or plate) */}
                        <td className="px-3 py-2 align-top">
                          <div className="text-ink">{r.claimType || '—'}</div>
                          {r.nonMotorSubType ? (
                            <div className="text-[11px] text-brand-navy/70 mt-0.5">{r.nonMotorSubType}</div>
                          ) : r.plate ? (
                            <div className="text-[11px] text-brand-navy/70 mt-0.5">{r.plate}</div>
                          ) : null}
                        </td>

                        {/* 7. Stage pill + chips */}
                        <td className="px-3 py-2 align-top">
                          <span className="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold bg-brand-orange/15 text-brand-orange whitespace-nowrap">
                            {r.currentStage || '—'}
                          </span>
                          {r.stageChips.some((c) => c.onTime !== null) && (
                            <div className="flex flex-wrap gap-1 mt-1">
                              {r.stageChips.map((c, i) =>
                                c.onTime === null ? null : (
                                  <span
                                    key={`${c.abbr}-${i}`}
                                    className={`inline-flex px-1 py-0.5 rounded text-[10px] font-semibold ${
                                      c.onTime
                                        ? 'bg-status-success-bg text-status-success-fg'
                                        : 'bg-status-danger-bg text-status-danger-fg'
                                    }`}
                                  >
                                    {c.onTime ? '✓' : '✗'}{c.abbr}
                                  </span>
                                ),
                              )}
                            </div>
                          )}
                        </td>

                        {/* 8. Status */}
                        <td className="px-3 py-2 align-top">
                          <span className={`inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap ${tone.badge}`}>
                            <span className={`w-1.5 h-1.5 rounded-full ${tone.dot}`} aria-hidden />
                            {isNm && <span className="text-[9px] opacity-70">NM</span>}
                            {statusText}
                          </span>
                        </td>

                        {/* 9. Comments */}
                        <td className="px-3 py-2 align-top max-w-[160px]">
                          {r.comment ? (
                            <span className={`text-[12px] break-words whitespace-normal ${COMMENT_TONE_CLS[r.comment.tone]}`}>
                              {r.comment.text}
                            </span>
                          ) : (
                            <span className="text-ink-faint">—</span>
                          )}
                        </td>

                        {/* 10. Days Late */}
                        <td className="px-3 py-2 align-top text-right tabular-nums whitespace-nowrap">
                          {r.daysLate > 0 ? (
                            <span className="font-bold text-status-danger-fg">{r.daysLate}</span>
                          ) : (
                            <span className="text-ink-faint">—</span>
                          )}
                        </td>

                        {/* 11. Reserve */}
                        <td className="px-3 py-2 align-top text-right tabular-nums whitespace-nowrap text-ink">
                          {r.reserve ? r.reserve.toLocaleString('en-BW') : <span className="text-ink-faint">—</span>}
                        </td>

                        {/* 12. Actions (sticky right) */}
                        <td className={`px-2 py-2 align-top whitespace-nowrap no-print ${stickyTd}`}>
                          <div className="flex items-center gap-1">
                            <button
                              type="button"
                              onClick={() => navigate(`/claims/${r.claimId}`)}
                              className={`${actionBtn} bg-brand-navy text-white hover:bg-brand-navy/90`}
                              title="View claim"
                            >
                              View
                            </button>
                            <button
                              type="button"
                              onClick={() => navigate(`/claims/${r.claimId}`)}
                              className={`${actionBtn} bg-surface-2 text-ink border border-line hover:bg-line/40`}
                              title="Edit claim"
                            >
                              Edit
                            </button>
                            <button
                              type="button"
                              onClick={() => setShareRow(r)}
                              className={`${actionBtn} bg-surface-2 text-ink border border-line hover:bg-line/40`}
                              title="Share claim"
                            >
                              Share
                            </button>
                            {canDelete && (
                              <button
                                type="button"
                                onClick={() => handleDelete(r)}
                                disabled={deletingId === r.claimId}
                                className={`${actionBtn} bg-status-danger-bg text-status-danger-fg hover:opacity-80 disabled:opacity-50 disabled:cursor-not-allowed`}
                                title="Delete claim"
                              >
                                Del
                              </button>
                            )}
                            <button
                              type="button"
                              onClick={() => navigate(`/claims/${r.claimId}`)}
                              className={`${actionBtn} bg-surface-2 text-ink border border-line hover:bg-line/40`}
                              title="Comments"
                              aria-label="Comments"
                            >
                              💬
                            </button>
                          </div>
                        </td>
                      </tr>
                    )
                  })
                )}
              </tbody>
            </table>
          </div>
        )}

        {/* Bottom pagination (identical) */}
        <PaginationBar
          from={from} to={to} total={total} page={page} lastPage={lastPage}
          perPage={perPage} onPage={goToPage} onPerPage={changePerPage} loading={isFetching}
        />
      </div>

      {/* E. Share modal */}
      {shareRow && <ShareModal row={shareRow} onClose={() => setShareRow(null)} toastCopied={() => toast.success('Copied to clipboard.')} />}
      {deleteRow && (
        <Modal
          open
          onClose={() => setDeleteRow(null)}
          title={`Delete claim ${deleteRow.claimNumber}`}
          size="md"
          footer={
            <div className="flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setDeleteRow(null)}
                className="px-3 py-1.5 text-sm rounded-md border border-line bg-surface text-ink hover:bg-line/40 cursor-pointer"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={confirmDelete}
                disabled={deletingId === deleteRow.claimId}
                className="px-3 py-1.5 text-sm rounded-md bg-status-danger-bg text-status-danger-fg font-semibold hover:opacity-80 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
              >
                {deletingId === deleteRow.claimId ? 'Deleting…' : 'Delete claim'}
              </button>
            </div>
          }
        >
          <div className="space-y-3">
            <p className="text-sm text-ink-muted">
              This hides claim <strong className="text-ink">{deleteRow.claimNumber}</strong> from the list. It is
              reversible and recorded in the audit log with your name and the reason below.
            </p>
            <label className="block text-sm font-medium text-ink">
              Reason for deletion <span className="text-status-danger-fg">*</span>
              <textarea
                value={deleteReason}
                onChange={(e) => { setDeleteReason(e.target.value); setDeleteErr('') }}
                rows={3}
                className="mt-1 w-full px-2.5 py-1.5 border border-line rounded-md text-sm bg-surface text-ink"
                placeholder="e.g. Duplicate of G2026001234"
              />
            </label>
            {deleteErr && <p className="text-sm text-status-danger-fg">{deleteErr}</p>}
          </div>
        </Modal>
      )}
    </div>
  )
}

// ── Pagination bar (top & bottom, identical) ─────────────────────────────────
function pageNumbers(current: number, last: number): (number | '...')[] {
  const pages: (number | '...')[] = []
  if (last <= 7) {
    for (let i = 1; i <= last; i++) pages.push(i)
  } else {
    pages.push(1)
    if (current > 3) pages.push('...')
    for (let i = Math.max(2, current - 1); i <= Math.min(last - 1, current + 1); i++) pages.push(i)
    if (current < last - 2) pages.push('...')
    pages.push(last)
  }
  return pages
}

function PaginationBar({
  from, to, total, page, lastPage, perPage, onPage, onPerPage, loading,
}: {
  from: number
  to: number
  total: number
  page: number
  lastPage: number
  perPage: PerPage
  onPage: (p: number) => void
  onPerPage: (pp: PerPage) => void
  loading?: boolean
}) {
  const pgBtn = 'px-2.5 py-1 rounded border text-sm cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed'
  return (
    <div className="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 border-b border-line bg-surface-2 text-sm no-print">
      <span className="text-ink-muted">
        Showing {from.toLocaleString()}&ndash;{to.toLocaleString()} of {total.toLocaleString()} claims
        {loading && <span className="ml-2 text-ink-faint">·&nbsp;updating…</span>}
      </span>
      <div className="flex items-center gap-1">
        <button
          type="button"
          disabled={page <= 1}
          onClick={() => onPage(page - 1)}
          className={`${pgBtn} border-line text-ink-muted hover:bg-surface`}
          aria-label="Previous page"
        >
          ‹
        </button>
        {pageNumbers(page, lastPage).map((p, i) =>
          p === '...' ? (
            <span key={`e${i}`} className="px-1.5 text-ink-faint">…</span>
          ) : (
            <button
              type="button"
              key={p}
              onClick={() => onPage(p)}
              className={`${pgBtn} ${
                p === page
                  ? 'bg-primary text-primary-contrast border-primary'
                  : 'border-line text-ink-muted hover:bg-surface'
              }`}
            >
              {p}
            </button>
          ),
        )}
        <button
          type="button"
          disabled={page >= lastPage}
          onClick={() => onPage(page + 1)}
          className={`${pgBtn} border-line text-ink-muted hover:bg-surface`}
          aria-label="Next page"
        >
          ›
        </button>
        <label className="flex items-center gap-1 ml-3 border-l border-line pl-3 text-ink-muted">
          Rows:
          <select
            value={perPage}
            onChange={(e) => onPerPage(Number(e.target.value) as PerPage)}
            className="px-2 py-1 border border-line rounded text-sm bg-surface text-ink cursor-pointer"
          >
            {PER_PAGE_OPTIONS.map((n) => (
              <option key={n} value={n}>{n}</option>
            ))}
          </select>
        </label>
      </div>
    </div>
  )
}

// ── Share modal (pure client-side) ───────────────────────────────────────────
function buildShareText(r: ClaimsTrackerListRow): string {
  const lines = [
    `Claim #: ${r.claimNumber}`,
    `Client: ${r.clientName || '—'}`,
    `Channel: ${r.channel || '—'}`,
    `Type: ${r.claimType || '—'}${r.nonMotorSubType ? ` (${r.nonMotorSubType})` : ''}`,
    `Handler: ${r.handler || '—'}`,
    `Status: ${r.overallStatus || '—'}`,
    `Days late: ${r.daysLate > 0 ? r.daysLate : '0'}`,
    `Reserve: ${r.reserve ? 'P ' + r.reserve.toLocaleString('en-BW') : '—'}`,
  ]
  const flags: string[] = []
  if (r.isMajor) flags.push('MAJOR')
  if (r.isFac) flags.push('FAC')
  if (flags.length) lines.push(`Flags: ${flags.join(', ')}`)
  return lines.join('\n')
}

function ShareModal({ row, onClose, toastCopied }: {
  row: ClaimsTrackerListRow
  onClose: () => void
  toastCopied: () => void
}) {
  const text = useMemo(() => buildShareText(row), [row])
  const btn = 'inline-flex items-center justify-center gap-1.5 rounded-md px-3 py-2 text-sm font-semibold cursor-pointer'

  return (
    <Modal open onClose={onClose} title={`Share claim ${row.claimNumber}`} size="md">
      <div className="space-y-3">
        <textarea
          readOnly
          value={text}
          rows={9}
          className="w-full px-3 py-2 border border-line rounded-md text-sm bg-surface-2 text-ink font-mono resize-none"
        />
        <div className="grid grid-cols-3 gap-2">
          <button
            type="button"
            onClick={() => window.open('https://wa.me/?text=' + encodeURIComponent(text))}
            className={`${btn} bg-status-success-bg text-status-success-fg hover:opacity-80`}
          >
            WhatsApp
          </button>
          <button
            type="button"
            onClick={() => { window.location.href = 'mailto:?subject=' + encodeURIComponent(`Claim ${row.claimNumber}`) + '&body=' + encodeURIComponent(text) }}
            className={`${btn} bg-brand-navy text-white hover:bg-brand-navy/90`}
          >
            Email
          </button>
          <button
            type="button"
            onClick={() => { navigator.clipboard.writeText(text).then(toastCopied).catch(() => {}) }}
            className={`${btn} bg-surface-2 text-ink border border-line hover:bg-line/40`}
          >
            Copy
          </button>
        </div>
      </div>
    </Modal>
  )
}
