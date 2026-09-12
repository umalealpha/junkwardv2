import { useState, useEffect, useRef } from 'react'
import { useSearchParams, useNavigate, useLocation } from 'react-router-dom'
import { useHelpDeskTickets, useHelpDeskSummary } from '../../hooks/useHelpDesk'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import { fmtDate } from '../../utils/format'
import type { HelpDeskFilters, HelpDeskStatus, HelpDeskPriority } from '../../api/helpdesk'
import EmptyState from '../../components/common/EmptyState'
import StatusBadge from '../../components/common/StatusBadge'
import { SkeletonTable } from '../../components/common/Skeleton'
import { useToast } from '../../components/common/Toast'
import { openAlphaBridgeWidget, ALPHA_BRIDGE_BOARD_URL } from '../../utils/alphaBridge'

// Status / priority visual language. Each entry carries a dot colour
// (used in the compact KPI strip) and the pill style used when a row in
// the ticket list shows that status / priority.
// `dot` colours drive the compact KPI-strip filter chips (a legit decorative
// signal, kept as-is). `badge` maps each workflow value onto a shared
// StatusBadge semantic status key so the ticket-list pills get token colours +
// AA contrast + a text label. `null` → neutral "Unknown"-style pill.
const STATUS_META: Record<HelpDeskStatus, { label: string; dot: string; badge: string | null }> = {
  new:                 { label: 'New',                dot: 'bg-indigo-500', badge: 'pending' },
  open:                { label: 'Open',               dot: 'bg-blue-500',   badge: 'pending' },
  in_progress:         { label: 'In Progress',        dot: 'bg-amber-500',  badge: 'expired' },
  pending_customer:    { label: 'Pending Customer',   dot: 'bg-purple-500', badge: 'pending' },
  pending_third_party: { label: 'Pending 3rd Party',  dot: 'bg-pink-500',   badge: 'pending' },
  resolved:            { label: 'Resolved',           dot: 'bg-green-500',  badge: 'approved' },
  closed:              { label: 'Closed',             dot: 'bg-gray-400',   badge: 'default' },
  reopened:            { label: 'Reopened',           dot: 'bg-orange-500', badge: 'expired' },
}
const PRIORITY_META: Record<HelpDeskPriority, { label: string; dot: string; badge: string | null }> = {
  critical: { label: 'Critical', dot: 'bg-red-500',     badge: 'rejected' },
  high:     { label: 'High',     dot: 'bg-orange-500',  badge: 'expired' },
  medium:   { label: 'Medium',   dot: 'bg-blue-500',    badge: 'pending' },
  low:      { label: 'Low',      dot: 'bg-gray-400',    badge: 'default' },
}
// Fallback for any status/priority value the API returns that isn't in the maps
// above (legacy/imported rows, NULL, or a future enum value). A single bad row
// must never crash the whole list — render it greyed rather than throw.
const FALLBACK_META = { label: '—', dot: 'bg-gray-300', badge: null }

const PER_PAGE_OPTIONS = [10, 25, 50, 100]
const DEFAULT_PER_PAGE = 10
// 'open' stays in the strip so legacy tickets remain filterable; it sits dim at
// count 0 once they're all migrated through the workflow.
const STATUS_ORDER: HelpDeskStatus[] = ['new', 'open', 'in_progress', 'pending_customer', 'pending_third_party', 'resolved', 'reopened', 'closed']
const PRIORITY_ORDER: HelpDeskPriority[] = ['critical', 'high', 'medium', 'low']

// Show the local-part for ANY email-looking value, so the assignee chips
// don't waste characters on a domain. Backend now resolves emails to
// User.firstName + lastName on assign (see HelpDeskController::assign),
// so most values that hit this helper are already full names and pass
// through unchanged. The email-stripping is a fallback for external or
// unresolved addresses.
function displayName(name: string | null): string {
  if (!name) return 'Unassigned'
  const m = name.match(/^([^@\s]+)@[^@\s]+$/)
  return m ? m[1] : name
}

// ── reusable KPI chip — dot + label + count, single row ───────────
function KpiChip({
  dot, label, count, active, onClick, dim,
}: { dot: string; label: string; count: number; active: boolean; onClick: () => void; dim?: boolean }) {
  return (
    <button
      type="button"
      onClick={onClick}
      title={active ? 'Click to clear this filter' : `Filter by ${label}`}
      aria-pressed={active}
      className={`group inline-flex items-center gap-1.5 px-2 py-1 rounded-md text-xs font-medium border motion-safe:transition w-full justify-between cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-navy focus-visible:ring-offset-1 ${
        active
          ? 'bg-brand-navy text-white border-brand-navy shadow-elev-sm'
          : dim
            ? 'bg-surface text-ink-faint border-line hover:border-line hover:text-ink-muted'
            : 'bg-surface text-ink-muted border-line hover:border-brand-navy hover:text-brand-navy'
      }`}
    >
      <span className="inline-flex items-center gap-1.5 min-w-0">
        <span className={`w-1.5 h-1.5 rounded-full flex-none ${active ? 'bg-white' : dot}`} />
        <span className="truncate">{label}</span>
      </span>
      <span className={`tabular-nums text-[11px] font-semibold ${active ? 'text-white' : dim ? 'text-ink-faint' : 'text-ink-faint'}`}>
        {count}
      </span>
    </button>
  )
}

// ── people-list row — name + count badge ──────────────────────────
function PeopleRow({
  name, count, active, onClick,
}: { name: string; count: number; active: boolean; onClick: () => void }) {
  return (
    <button
      type="button"
      onClick={onClick}
      title={active ? `Click to clear · ${name}` : `Filter by ${name}`}
      aria-pressed={active}
      className={`inline-flex items-center justify-between gap-2 w-full px-2 py-1 rounded-md text-xs font-medium border motion-safe:transition cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-navy focus-visible:ring-offset-1 ${
        active
          ? 'bg-brand-navy text-white border-brand-navy'
          : 'bg-surface text-ink-muted border-line hover:border-brand-navy hover:text-brand-navy'
      }`}
    >
      <span className="truncate">{displayName(name)}</span>
      <span className={`tabular-nums text-[11px] font-semibold px-1.5 rounded ${active ? 'bg-white/20 text-white' : 'bg-surface-2 text-ink-muted'}`}>
        {count}
      </span>
    </button>
  )
}

function Section({ title, children, badge }: { title: string; children: React.ReactNode; badge?: number }) {
  return (
    <div className="min-w-0">
      <div className="flex items-center justify-between mb-1.5">
        <div className="text-[10px] font-semibold tracking-[0.08em] uppercase text-ink-faint">{title}</div>
        {badge !== undefined && badge > 0 && (
          <span className="text-[10px] tabular-nums text-ink-faint">{badge}</span>
        )}
      </div>
      {/* Vertical 1-col stack — keeps Status/Priority columns the same height
          as the people-list columns, eliminating dead space at the bottom. */}
      <div className="space-y-1">{children}</div>
    </div>
  )
}

export default function HelpDeskListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const navigate = useNavigate()
  const location = useLocation()
  const { toast } = useToast()
  const [successMsg, setSuccessMsg] = useState<string | null>(null)

  // New tickets are raised in the Alpha Bridge helpdesk via its widget —
  // this list (and the detail pages) remain read/triage surfaces until the
  // full module cutover.
  const reportIssue = () =>
    openAlphaBridgeWidget().catch(() =>
      toast.error('Could not open the issue reporter — Alpha Bridge is unreachable. Please try again.')
    )

  useEffect(() => {
    const msg = (location.state as any)?.successMessage
    if (msg) {
      setSuccessMsg(msg)
      window.history.replaceState({}, '')
      const t = setTimeout(() => setSuccessMsg(null), 6000)
      return () => clearTimeout(t)
    }
  }, [])

  const filters: HelpDeskFilters = {
    status:        (searchParams.get('status') as HelpDeskStatus) || undefined,
    priority:      (searchParams.get('priority') as HelpDeskPriority) || undefined,
    assignee_name: searchParams.get('assignee_name') || undefined,
    reporter_name: searchParams.get('reporter_name') || undefined,
    mine:          searchParams.get('mine') === '1' || undefined,
    search:        searchParams.get('search') || undefined,
    per_page:      Number(searchParams.get('per_page') || DEFAULT_PER_PAGE),
    page:          Number(searchParams.get('page') || '1'),
  }

  const { data, isLoading, isFetching } = useHelpDeskTickets(filters)
  const { data: summary, isFetching: summaryFetching } = useHelpDeskSummary(filters)

  // ── debounced quick-search ────────────────────────────────────────
  const [searchInput, setSearchInput] = useState(filters.search ?? '')
  const searchDebounceRef = useRef<number | null>(null)
  useEffect(() => {
    if ((filters.search ?? '') !== searchInput) {
      setSearchInput(filters.search ?? '')
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filters.search])

  function onSearchChange(value: string) {
    setSearchInput(value)
    if (searchDebounceRef.current) window.clearTimeout(searchDebounceRef.current)
    searchDebounceRef.current = window.setTimeout(() => {
      const next = new URLSearchParams(searchParams)
      if (value) next.set('search', value)
      else next.delete('search')
      next.delete('page')
      setSearchParams(next, { replace: true })
    }, 300)
  }

  function toggleFilter(key: string, value: string | null) {
    const next = new URLSearchParams(searchParams)
    const current = next.get(key)
    if (value === null || current === value) next.delete(key)
    else next.set(key, value)
    next.delete('page')
    setSearchParams(next)
  }

  function setPerPage(n: number) {
    const next = new URLSearchParams(searchParams)
    if (n === DEFAULT_PER_PAGE) next.delete('per_page')
    else next.set('per_page', String(n))
    next.delete('page')
    setSearchParams(next)
  }

  function clearAllFilters() {
    setSearchParams(new URLSearchParams())
    setSearchInput('')
  }

  function goToPage(page: number) {
    const next = new URLSearchParams(searchParams)
    next.set('page', String(page))
    setSearchParams(next)
  }

  const meta = data?.meta
  const currentPage = meta?.current_page ?? 1
  const lastPage = meta?.last_page ?? 1
  // Active filter chips for the breadcrumb above the table — one chip
  // per active filter, click to remove. Makes the current scope visible
  // without forcing the user back to the KPI strip.
  const activeFilterChips: { key: string; label: string; clear: () => void }[] = []
  if (filters.status)        activeFilterChips.push({ key: 'status',        label: `Status: ${STATUS_META[filters.status]?.label ?? filters.status}`, clear: () => toggleFilter('status', null) })
  if (filters.priority)      activeFilterChips.push({ key: 'priority',      label: `Priority: ${PRIORITY_META[filters.priority]?.label ?? filters.priority}`, clear: () => toggleFilter('priority', null) })
  if (filters.reporter_name) activeFilterChips.push({ key: 'reporter_name', label: `Raised by ${displayName(filters.reporter_name)}`, clear: () => toggleFilter('reporter_name', null) })
  if (filters.assignee_name) activeFilterChips.push({ key: 'assignee_name', label: `Assigned to ${filters.assignee_name === '__unassigned__' ? 'Unassigned' : displayName(filters.assignee_name)}`, clear: () => toggleFilter('assignee_name', null) })
  if (filters.mine)          activeFilterChips.push({ key: 'mine',          label: 'Raised by me', clear: () => { const n = new URLSearchParams(searchParams); n.delete('mine'); n.delete('page'); setSearchParams(n) } })

  return (
    <div className="p-6 space-y-3">
      {/* Header — title + count + report button on a single row. */}
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div className="flex items-baseline gap-2">
          <h1 className="font-heading text-2xl font-bold text-ink">Helpdesk Archive</h1>
          {summary && (
            <span className="text-sm text-ink-faint">
              · <span className="font-semibold tabular-nums text-ink-muted">{summary.total}</span> ticket{summary.total === 1 ? '' : 's'} in scope
            </span>
          )}
        </div>
        <div className="flex items-center gap-2">
          <a
            href={ALPHA_BRIDGE_BOARD_URL}
            className="inline-flex items-center gap-1.5 px-3 py-2 border border-line text-ink-muted hover:text-brand-navy hover:border-brand-navy text-sm font-medium rounded-md transition"
            title="Open the Alpha Bridge helpdesk board"
          >
            Open Alpha Bridge
            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
            </svg>
          </a>
          <button
            onClick={() => { void reportIssue() }}
            className="inline-flex items-center gap-1.5 px-4 py-2 bg-primary text-primary-contrast text-sm font-medium rounded-md hover:opacity-90 transition"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            Report an Issue
          </button>
        </div>
      </div>

      {/* Cutover banner — this module is a read-only archive now. */}
      <div className="flex items-center gap-2 bg-brand-navy/5 border border-brand-navy/20 text-brand-navy text-sm px-4 py-2.5 rounded-md">
        <svg className="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span>
          The helpdesk has moved to{' '}
          <a href={ALPHA_BRIDGE_BOARD_URL} className="font-semibold underline underline-offset-2">Alpha Bridge</a>.
          This is a read-only archive of tickets raised before the move (all migrated to Bridge) — raise and
          track new issues in Bridge or via <span className="font-medium">Report an Issue</span>.
        </span>
      </div>

      {successMsg && (
        <div className="flex items-center gap-2 bg-status-success-bg border border-status-success-fg/30 text-status-success-fg text-sm px-4 py-2 rounded-md">
          <svg className="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
          </svg>
          {successMsg}
        </div>
      )}

      {/* Compact KPI strip — single card, 4 sections, no internal card chrome.
          Half the height of the previous 4-card layout, every chip is still a
          one-click filter. */}
      <div className="relative bg-surface rounded-lg border border-line shadow-sm">
        {summaryFetching && (
          <div className="absolute top-2 right-2 z-10"><LoadingSpinner size="sm" /></div>
        )}
        <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-line">
          <div className="p-3">
            <Section title="Status">
              {STATUS_ORDER.map(s => (
                <KpiChip
                  key={s}
                  dot={STATUS_META[s].dot}
                  label={STATUS_META[s].label}
                  count={summary?.by_status[s] ?? 0}
                  active={filters.status === s}
                  onClick={() => toggleFilter('status', s)}
                  dim={!filters.status && (summary?.by_status[s] ?? 0) === 0}
                />
              ))}
            </Section>
          </div>
          <div className="p-3">
            <Section title="Priority">
              {PRIORITY_ORDER.map(p => (
                <KpiChip
                  key={p}
                  dot={PRIORITY_META[p].dot}
                  label={PRIORITY_META[p].label}
                  count={summary?.by_priority[p] ?? 0}
                  active={filters.priority === p}
                  onClick={() => toggleFilter('priority', p)}
                  dim={!filters.priority && (summary?.by_priority[p] ?? 0) === 0}
                />
              ))}
            </Section>
          </div>
          <div className="p-3">
            <div className="text-[10px] font-semibold tracking-[0.08em] uppercase text-ink-faint mb-1.5">Top Raised By</div>
            {(summary?.by_reporter ?? []).length === 0 ? (
              <div className="text-xs text-ink-faint italic py-1">No tickets in scope</div>
            ) : (
              <div className="space-y-1">
                {(summary?.by_reporter ?? []).slice(0, 5).map(r => (
                  <PeopleRow
                    key={r.name}
                    name={r.name}
                    count={r.count}
                    active={filters.reporter_name === r.name}
                    onClick={() => toggleFilter('reporter_name', r.name)}
                  />
                ))}
              </div>
            )}
          </div>
          <div className="p-3">
            <div className="text-[10px] font-semibold tracking-[0.08em] uppercase text-ink-faint mb-1.5">Top Assigned To</div>
            {(summary?.by_assignee ?? []).length === 0 ? (
              <div className="text-xs text-ink-faint italic py-1">No tickets in scope</div>
            ) : (
              <div className="space-y-1">
                {(summary?.by_assignee ?? []).slice(0, 5).map(a => {
                  const sentinel = a.name ?? '__unassigned__'
                  const displayed = a.name ?? 'Unassigned'
                  return (
                    <PeopleRow
                      key={sentinel}
                      name={displayed}
                      count={a.count}
                      active={filters.assignee_name === sentinel}
                      onClick={() => toggleFilter('assignee_name', sentinel)}
                    />
                  )
                })}
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Action bar — search + scope + per_page + active-filter chips, all on one row. */}
      <div className="bg-surface-2 border border-line rounded-lg px-3 py-2 flex flex-wrap items-center gap-x-3 gap-y-2">
        <div className="relative flex-1 min-w-[240px] max-w-md">
          <svg className="w-4 h-4 absolute left-2.5 top-1/2 -translate-y-1/2 text-ink-faint pointer-events-none" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
          </svg>
          <input
            type="text"
            placeholder="Search ref, title, description, raised by, assigned to…"
            value={searchInput}
            onChange={e => onSearchChange(e.target.value)}
            className="w-full pl-8 pr-7 py-1.5 bg-surface text-ink border border-line rounded-md text-sm focus:ring-1 focus:ring-brand-navy focus:border-brand-navy"
          />
          {searchInput && (
            <button
              type="button"
              onClick={() => onSearchChange('')}
              className="absolute right-2 top-1/2 -translate-y-1/2 text-ink-faint hover:text-ink-muted cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-navy rounded"
              aria-label="Clear search"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          )}
        </div>
        <div className="flex items-center gap-1.5 text-xs text-ink-muted">
          <span className="text-ink-faint">Scope</span>
          <select
            value={filters.mine ? '1' : ''}
            onChange={e => {
              const next = new URLSearchParams(searchParams)
              if (e.target.value) next.set('mine', e.target.value)
              else next.delete('mine')
              next.delete('page')
              setSearchParams(next)
            }}
            className="px-2 py-1 bg-surface text-ink border border-line rounded-md text-xs focus:ring-1 focus:ring-brand-navy"
          >
            <option value="">All</option>
            <option value="1">Raised by me</option>
          </select>
        </div>
        <div className="flex items-center gap-1.5 text-xs text-ink-muted">
          <span className="text-ink-faint">Rows</span>
          <select
            value={filters.per_page}
            onChange={e => setPerPage(Number(e.target.value))}
            className="px-2 py-1 bg-surface text-ink border border-line rounded-md text-xs focus:ring-1 focus:ring-brand-navy"
          >
            {PER_PAGE_OPTIONS.map(n => <option key={n} value={n}>{n}</option>)}
          </select>
        </div>
        {activeFilterChips.length > 0 && (
          <div className="flex items-center gap-1.5 flex-wrap min-w-0">
            <span className="text-[11px] text-ink-faint uppercase tracking-wider">Filters</span>
            {activeFilterChips.map(c => (
              <button
                key={c.key}
                onClick={c.clear}
                className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-brand-navy/10 text-brand-navy text-[11px] font-medium hover:bg-brand-navy/20"
                title="Click to remove this filter"
              >
                {c.label}
                <span className="text-brand-navy/70">✕</span>
              </button>
            ))}
            <button
              onClick={clearAllFilters}
              className="text-[11px] text-ink-faint hover:text-brand-navy underline underline-offset-2"
            >
              Clear all
            </button>
          </div>
        )}
      </div>

      {/* Table */}
      <div className="bg-surface rounded-lg shadow-elev-sm border border-line overflow-hidden">
        <div className="overflow-x-auto relative">
          {isFetching && !isLoading && (
            <div className="absolute top-2 right-2 z-10"><LoadingSpinner size="sm" /></div>
          )}
          {isLoading ? (
            <div className="p-4"><SkeletonTable rows={8} cols={9} /></div>
          ) : data?.data.length === 0 ? (
            <EmptyState compact title="No tickets found" description="Try adjusting your filters or search terms." />
          ) : (
          <table className="w-full text-sm">
            <thead className="bg-surface-2 text-ink-muted uppercase text-xs tracking-wider">
              <tr>
                <th className="px-4 py-3 text-left sticky top-0 bg-surface-2">Ref</th>
                <th className="px-4 py-3 text-left sticky top-0 bg-surface-2">Issue</th>
                <th className="px-4 py-3 text-left sticky top-0 bg-surface-2">Priority</th>
                <th className="px-4 py-3 text-left sticky top-0 bg-surface-2">Raised By</th>
                <th className="px-4 py-3 text-left sticky top-0 bg-surface-2">Assigned To</th>
                <th className="px-4 py-3 text-left sticky top-0 bg-surface-2">Status</th>
                <th className="px-4 py-3 text-center sticky top-0 bg-surface-2">Files</th>
                <th className="px-4 py-3 text-left sticky top-0 bg-surface-2">Opened</th>
                <th className="px-4 py-3 text-left sticky top-0 bg-surface-2">Closed</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {data?.data.map(t => (
                  <tr
                    key={t.id}
                    onClick={() => navigate(`/help-desk/${t.id}`)}
                    className="hover:bg-surface-2 transition cursor-pointer"
                  >
                    <td className="px-4 py-2 font-medium text-brand-navy whitespace-nowrap">{t.ticketRef}</td>
                    {/* Issue title = primary column: don't truncate. */}
                    <td className="px-4 py-2 text-ink" title={t.title || t.excerpt}>{t.title || t.excerpt}</td>
                    <td className="px-4 py-2">
                      <StatusBadge status={(PRIORITY_META[t.priority] ?? FALLBACK_META).badge} label={PRIORITY_META[t.priority]?.label ?? t.priority ?? '—'} />
                    </td>
                    <td className="px-4 py-2 max-w-[180px] truncate text-ink-muted" title={t.reporterName || ''}>
                      {t.reporterName ? displayName(t.reporterName) : '—'}
                    </td>
                    <td className="px-4 py-2 max-w-[200px] truncate text-ink-muted" title={t.assigneeName || 'Unassigned'}>
                      {t.assigneeName
                        ? displayName(t.assigneeName)
                        : <span className="text-ink-faint">Unassigned</span>}
                    </td>
                    <td className="px-4 py-2">
                      <StatusBadge status={(STATUS_META[t.status] ?? FALLBACK_META).badge} label={STATUS_META[t.status]?.label ?? t.status ?? '—'} />
                    </td>
                    <td className="px-4 py-2 text-center text-ink-faint tabular-nums">{t.attachmentCount || '—'}</td>
                    <td className="px-4 py-2 text-ink-muted whitespace-nowrap">{fmtDate(t.createdAt)}</td>
                    <td className="px-4 py-2 text-ink-muted whitespace-nowrap">{t.closedAt ? fmtDate(t.closedAt) : <span className="text-ink-faint">—</span>}</td>
                  </tr>
                ))}
            </tbody>
          </table>
          )}
        </div>

        {meta && meta.total > 0 && (
          <div className="flex items-center justify-between px-4 py-2.5 border-t border-line bg-surface-2 text-xs">
            <span className="text-ink-faint">
              Showing <span className="font-medium tabular-nums text-ink-muted">{meta.from ?? 0}–{meta.to ?? 0}</span> of <span className="font-medium tabular-nums text-ink-muted">{meta.total}</span>
              {meta.total > meta.per_page && (
                <span className="text-ink-faint"> · Page {currentPage} of {lastPage}</span>
              )}
            </span>
            {meta.last_page > 1 && (
              <div className="flex items-center gap-1">
                <button
                  disabled={currentPage === 1}
                  onClick={() => goToPage(1)}
                  className="px-2 py-1 rounded border border-line text-ink-muted hover:bg-surface-2 disabled:opacity-40 disabled:cursor-not-allowed"
                  title="First page"
                >
                  «
                </button>
                <button
                  disabled={currentPage === 1}
                  onClick={() => goToPage(currentPage - 1)}
                  className="px-2 py-1 rounded border border-line text-ink-muted hover:bg-surface-2 disabled:opacity-40 disabled:cursor-not-allowed"
                >
                  Prev
                </button>
                <span className="px-2 text-ink-faint tabular-nums">{currentPage} / {lastPage}</span>
                <button
                  disabled={currentPage === lastPage}
                  onClick={() => goToPage(currentPage + 1)}
                  className="px-2 py-1 rounded border border-line text-ink-muted hover:bg-surface-2 disabled:opacity-40 disabled:cursor-not-allowed"
                >
                  Next
                </button>
                <button
                  disabled={currentPage === lastPage}
                  onClick={() => goToPage(lastPage)}
                  className="px-2 py-1 rounded border border-line text-ink-muted hover:bg-surface-2 disabled:opacity-40 disabled:cursor-not-allowed"
                  title="Last page"
                >
                  »
                </button>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  )
}
