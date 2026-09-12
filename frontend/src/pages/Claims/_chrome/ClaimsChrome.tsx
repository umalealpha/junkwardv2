import { useEffect, useRef, useState } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { getStoredRoles, getStoredPermissions } from '../../../api/auth'
import { useClaimsFnolEnabled } from '../../../hooks/useFnol'
import { CLAIMS_INCENTIVE_ROLES } from '../../../api/claimsIncentive'
import { CLAIMS_ANALYTICS_ROLES } from '../../../hooks/useClaimsAnalytics'

/**
 * ClaimsChrome — the claims-module top chrome, a 1:1 port of the legacy Claims
 * Tracker shell (action bar + horizontal tab strip + red overdue banner) so
 * users find every feature exactly where the old app put it. Rendered INSIDE
 * Graphite's global AdminLayout (keeps the navy sidebar + global Header — its
 * bell/user/sign-out are reused, not duplicated here). Canonical brand
 * (navy/orange) via design tokens; emoji labels kept for exact familiarity.
 *
 * Include it at the top of each claims screen as it is ported; the active tab
 * is derived from the route, so no per-page wiring is needed.
 */

const ADMIN_ROLES = ['Admin', 'admin', 'Super Admin']

interface Tab {
  emoji: string
  label: string
  path: string
  /** matched by startsWith unless exact (the All-Claims list at /claims) */
  exact?: boolean
}

const TABS: Tab[] = [
  { emoji: '📊', label: 'Dashboard', path: '/claims/dashboard' },
  { emoji: '📋', label: 'All Claims', path: '/claims', exact: true },
  { emoji: '🏆', label: 'Incentive Report', path: '/claims/incentive' },
  { emoji: '📈', label: 'Analytics', path: '/claims/analytics' },
  // How It Works → the maintained Graphite claims Help guide (Pramod 2026-08-07).
  { emoji: '📖', label: 'How It Works', path: '/help/claims' },
  // Policy Library → the Admin-section Policy Wordings page (Pramod 2026-08-07;
  // later to be mapped to the Policy-tab wordings at /wordings).
  { emoji: '📚', label: 'Policy Library', path: '/system/wordings' },
]

const ADMIN_ITEMS: Tab[] = [
  // Audit Log → Graphite's global Audit Trail (superset; includes claims events).
  { emoji: '🔍', label: 'Audit Log', path: '/audit-trail' },
  { emoji: '⚙️', label: 'Master Data', path: '/claims/master-data' },
  { emoji: '🔑', label: 'API Access', path: '/claims/api-access' },
  { emoji: '📨', label: 'Notifications', path: '/claims/notifications' },
  { emoji: '⏱️', label: 'Backdate Control', path: '/claims/backdate-control' },
]

interface ClaimsChromeProps {
  /** Overdue-stage count that drives the red banner. Absent/0 hides it. */
  overdueCount?: number
  /** Called by the Refresh button (typically the page's refetch). */
  onRefresh?: () => void
  refreshing?: boolean
}

export default function ClaimsChrome({ overdueCount = 0, onRefresh, refreshing }: ClaimsChromeProps) {
  const loc = useLocation()
  const nav = useNavigate()
  const roles = getStoredRoles()
  const perms = getStoredPermissions()
  const isAdmin = roles.some((r) => ADMIN_ROLES.includes(r))
  const canCreate = perms.includes('claim-create')
  // Flag-gated cutover: when `claims_fnol` is ON, "+ New Claim" opens the unified
  // tracker-style FNOL create form; when OFF, it opens the legacy create page
  // (unchanged behaviour, current default).
  const fnolOn = useClaimsFnolEnabled()
  const newClaimPath = fnolOn ? '/claims/fnol/new' : '/claims/create'

  const [adminOpen, setAdminOpen] = useState(false)
  const [dismissed, setDismissed] = useState(false)
  const adminRef = useRef<HTMLDivElement>(null)

  // Close the Admin dropdown on outside click.
  useEffect(() => {
    if (!adminOpen) return
    const onDoc = (e: MouseEvent) => {
      if (adminRef.current && !adminRef.current.contains(e.target as Node)) setAdminOpen(false)
    }
    document.addEventListener('mousedown', onDoc)
    return () => document.removeEventListener('mousedown', onDoc)
  }, [adminOpen])

  // A fresh overdue count re-shows a previously dismissed banner.
  useEffect(() => { setDismissed(false) }, [overdueCount])

  const path = loc.pathname
  // Policy Library links to /system/wordings, which is Admin-gated — so it's
  // shown only to admins; non-admin handlers would otherwise hit an access wall.
  // Gate tabs to the SAME access their target page/backend enforce, so a user
  // never sees a tab that then 403s. Incentive + Analytics are role-limited;
  // Policy Library is admin-only (it links to the Admin-section wordings page).
  const hasRole = (rs: string[]) => roles.some((r) => rs.includes(r))
  const visibleTabs = TABS.filter((t) => {
    if (t.path === '/system/wordings') return isAdmin
    if (t.path === '/claims/incentive') return hasRole(CLAIMS_INCENTIVE_ROLES)
    if (t.path === '/claims/analytics') return hasRole(CLAIMS_ANALYTICS_ROLES)
    return true
  })
  const isActive = (t: Tab) => (t.exact ? path === t.path : path === t.path || path.startsWith(t.path + '/'))
  const adminActive = ADMIN_ITEMS.some((i) => isActive(i))

  const tabClass = (active: boolean) =>
    'flex items-center gap-1.5 whitespace-nowrap px-4 py-3 text-[0.82rem] font-semibold tracking-[0.02em] ' +
    'border-b-2 -mb-0.5 transition-colors cursor-pointer ' +
    (active
      ? 'text-brand-navy border-brand-orange'
      : 'text-ink-muted border-transparent hover:text-brand-navy')

  const actionBtn =
    'inline-flex items-center gap-1.5 rounded px-3 py-1.5 text-[0.72rem] font-bold ' +
    'transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed'

  const banner = overdueCount > 0 && !dismissed
  const severe = overdueCount > 3

  return (
    <div className="mb-5">
      {/* Action bar */}
      <div className="flex flex-wrap items-center justify-between gap-2 mb-1">
        <h1 className="font-heading text-xl font-extrabold text-brand-navy">Claims</h1>
        <div className="flex flex-wrap items-center gap-2 no-print">
          {onRefresh && (
            <button
              type="button"
              onClick={onRefresh}
              disabled={refreshing}
              className={actionBtn + ' bg-brand-orange/15 text-brand-orange border border-brand-orange/35 hover:bg-brand-orange/25'}
            >
              <span className={refreshing ? 'inline-block animate-spin' : ''}>↻</span> Refresh
            </button>
          )}
          {isAdmin && (
            <button
              type="button"
              onClick={() => nav('/claims/master-data')}
              className={actionBtn + ' bg-brand-orange/15 text-brand-orange border border-brand-orange/35 hover:bg-brand-orange/25'}
            >
              ⚙️ Master Data
            </button>
          )}
          <button
            type="button"
            onClick={() => window.print()}
            className={actionBtn + ' bg-surface-2 text-ink border border-line hover:bg-line/40'}
          >
            🖨 Print
          </button>
          {canCreate && (
            <button
              type="button"
              onClick={() => nav(newClaimPath)}
              className={actionBtn + ' bg-brand-navy text-white border border-brand-navy hover:bg-brand-navy/90'}
            >
              + New Claim
            </button>
          )}
        </div>
      </div>

      {/* Red / amber overdue banner */}
      {banner && (
        <div
          className={
            'flex items-center justify-between gap-3 rounded-md border px-4 py-2.5 mb-3 no-print ' +
            (severe
              ? 'bg-status-danger-bg border-status-danger-fg/30 text-status-danger-fg'
              : 'bg-status-warning-bg border-status-warning-fg/30 text-status-warning-fg')
          }
          role="alert"
        >
          <span className="text-[0.8rem] font-semibold">
            ⚠ {overdueCount} claim{overdueCount === 1 ? ' has' : 's have'} overdue stages requiring immediate attention
          </span>
          <button
            type="button"
            onClick={() => setDismissed(true)}
            aria-label="Dismiss"
            className="rounded px-2.5 py-0.5 font-bold hover:opacity-70 cursor-pointer"
          >
            ×
          </button>
        </div>
      )}

      {/* Horizontal tab strip */}
      <div className="flex items-stretch border-b-2 border-line overflow-x-auto no-scrollbar no-print">
        {visibleTabs.map((t) => (
          <button key={t.path} type="button" onClick={() => nav(t.path)} className={tabClass(isActive(t))}>
            <span aria-hidden>{t.emoji}</span>
            {t.label}
          </button>
        ))}

        {/* Admin ▾ dropdown (admins only) */}
        {isAdmin && (
          <div ref={adminRef} className="relative">
            <button
              type="button"
              onClick={() => setAdminOpen((o) => !o)}
              className={tabClass(adminActive) + ' select-none'}
            >
              <span aria-hidden>⚙️</span> Admin
              <span className="text-[0.7em]">▾</span>
            </button>
            {adminOpen && (
              <div className="absolute right-0 z-50 mt-0.5 min-w-[210px] rounded-lg border border-line bg-surface shadow-lg py-1">
                {ADMIN_ITEMS.map((i) => (
                  <button
                    key={i.path}
                    type="button"
                    onClick={() => { setAdminOpen(false); nav(i.path) }}
                    className="flex w-full items-center gap-2 px-3.5 py-2 text-left text-[0.85rem] text-ink hover:bg-surface-2 hover:text-brand-navy cursor-pointer"
                  >
                    <span aria-hidden>{i.emoji}</span> {i.label}
                  </button>
                ))}
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  )
}
