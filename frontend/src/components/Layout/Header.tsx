import { useState, useRef, useEffect, type RefObject } from 'react'
import { useNavigate, useLocation } from 'react-router-dom'
import { logout, getStoredUser } from '../../api/auth'
import { IS_TEST_ENV } from '../EnvironmentBanner'
import { useMyProfile } from '../../hooks/useMyProfile'
import NotificationBell from '../common/NotificationBell'
import ThemeToggle from '../common/ThemeToggle'
import WhatsNew from '../ReleaseNotes/WhatsNew'
import { getHelpModuleForPath } from '../../help/resolve'
import { getPageMeta } from './pageMeta'

interface HeaderProps {
  sidebarCollapsed: boolean
  /** Open the mobile drawer (below md). */
  onMobileMenuOpen?: () => void
  /** Drawer open state — drives the hamburger's aria-expanded. */
  mobileMenuOpen?: boolean
  /** Ref forwarded to the hamburger so the drawer can return focus to it. */
  hamburgerRef?: RefObject<HTMLButtonElement>
}

export default function Header({
  sidebarCollapsed,
  onMobileMenuOpen,
  mobileMenuOpen = false,
  hamburgerRef,
}: HeaderProps) {
  const navigate = useNavigate()
  const location = useLocation()
  // Contextual help: the guide that matches the page the user is currently on
  // (undefined on pages with no guide, e.g. the dashboard or the Help pages).
  const helpEntry = getHelpModuleForPath(location.pathname)
  // Top-bar breadcrumb + page title, derived from the sidebar menu (mirrors
  // the Reporting portal for consistency).
  const meta = getPageMeta(location.pathname)
  const user = getStoredUser()
  // Avatar URL is on the live profile; cached + shared with the profile page
  // query so this doesn't add a second network call.
  const { data: profile } = useMyProfile()
  const avatarUrl = profile?.avatar_url ?? null
  const [dropdownOpen, setDropdownOpen] = useState(false)
  const dropdownRef = useRef<HTMLDivElement>(null)
  const userMenuButtonRef = useRef<HTMLButtonElement>(null)

  useEffect(() => {
    function handleClickOutside(e: MouseEvent) {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target as Node)) {
        setDropdownOpen(false)
      }
    }
    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [])

  // Escape closes the user menu and returns focus to its trigger (WCAG 2.1.2).
  useEffect(() => {
    if (!dropdownOpen) return
    function onKey(e: KeyboardEvent) {
      if (e.key === 'Escape') {
        setDropdownOpen(false)
        userMenuButtonRef.current?.focus()
      }
    }
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [dropdownOpen])

  async function handleLogout() {
    try { await logout() } catch { /* ignore */ }
    navigate('/login', { replace: true })
  }

  return (
    <header
      className={`fixed ${IS_TEST_ENV ? 'top-10' : 'top-0'} right-0 left-0 z-sticky h-14 bg-surface border-b border-line flex items-center justify-between px-4 md:px-6 transition-all duration-300 overflow-visible ${
        sidebarCollapsed ? 'md:left-[var(--sidebar-w-collapsed)]' : 'md:left-[var(--sidebar-w)]'
      }`}
    >
      {/* Left: hamburger (mobile only) + breadcrumb + page title */}
      <div className="flex items-center gap-2 min-w-0">
        {/* Hamburger — opens the off-canvas drawer. Visible below md only; the
            fixed sidebar column takes over at md+. 44×44 tap target. */}
        <button
          ref={hamburgerRef}
          type="button"
          onClick={onMobileMenuOpen}
          aria-label="Open navigation menu"
          aria-controls="app-sidebar"
          aria-expanded={mobileMenuOpen}
          className="md:hidden flex items-center justify-center w-11 h-11 -ml-2 rounded-lg text-ink-muted hover:text-ink hover:bg-surface-2 transition shrink-0"
        >
          <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
        <div className="min-w-0">
        {meta.crumbs.length > 1 && (
          <nav className="hidden sm:flex items-center gap-1 text-[11px] text-ink-muted mb-0.5">
            {meta.crumbs.map((c, i) => (
              <span key={i} className="flex items-center gap-1">
                {i > 0 && (
                  <svg className="w-3 h-3 opacity-50 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                  </svg>
                )}
                {c.to && i < meta.crumbs.length - 1 ? (
                  <button onClick={() => navigate(c.to as string)} className="hover:text-brand-orange transition-colors">{c.label}</button>
                ) : (
                  <span className={i === meta.crumbs.length - 1 ? 'text-brand-navy dark:text-ink font-medium' : ''}>{c.label}</span>
                )}
              </span>
            ))}
          </nav>
        )}
        <h1 className="text-sm font-bold text-brand-navy dark:text-ink leading-tight truncate">{meta.title}</h1>
        </div>
      </div>

      {/* Right: actions */}
      <div className="flex items-center gap-3 shrink-0">
      {/* Create DOM/COM Policy — scope made explicit per UAT 2026-05-26
          (Arjun B2): the "+" button used to be labelled "Create New Policy"
          which led users to expect Instant products (Motor Comp, Mobile,
          Hospital Cashback, etc.) here. They actually live elsewhere. */}
      <button
        onClick={() => navigate('/policies/create')}
        title="Create DOM/COM Policy (Domestic / Commercial / Engineering / Liability / Marine)"
        aria-label="Create DOM/COM policy"
        className="flex items-center justify-center w-8 h-8 rounded-full bg-green-600 text-white hover:bg-green-700 transition shadow-sm"
      >
        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M12 4v16m8-8H4" />
        </svg>
      </button>

      {/* Contextual help — links to the guide for the current module.
          Hidden on pages that have no guide. */}
      {helpEntry && (
        <button
          onClick={() => navigate(`/help/${helpEntry.key}`)}
          title={`Help: ${helpEntry.title}`}
          aria-label={`Help for ${helpEntry.title}`}
          className="flex items-center justify-center w-8 h-8 rounded-full border border-brand-navy/30 text-brand-navy hover:bg-brand-navy/5 transition"
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
        </button>
      )}

      {/* What's New — on-login release-notes panel only (no top-bar icon;
          release notes also surface in the notification bell below). */}
      <WhatsNew />

      {/* Light / dark / system theme toggle */}
      <ThemeToggle />

      {/* Notification Bell */}
      <NotificationBell />

      {/* AI Assistant moved to the right-edge ActionRail (see AdminLayout) */}

      {/* User dropdown */}
      <div className="relative" ref={dropdownRef}>
        <button
          ref={userMenuButtonRef}
          onClick={() => setDropdownOpen(!dropdownOpen)}
          aria-label="User menu"
          aria-haspopup="menu"
          aria-expanded={dropdownOpen}
          className="flex items-center gap-2 text-sm text-ink-muted hover:text-ink transition"
        >
          {avatarUrl ? (
            <img
              src={avatarUrl}
              alt={`Avatar for ${user?.name ?? 'user'}`}
              className="w-8 h-8 rounded-full object-cover border border-line"
            />
          ) : (
            <div className="w-8 h-8 rounded-full bg-brand-navy/10 text-brand-navy flex items-center justify-center font-semibold text-xs">
              {user?.name?.charAt(0)?.toUpperCase() ?? 'U'}
            </div>
          )}
          <span className="hidden md:inline font-medium">{user?.name ?? 'User'}</span>
          <svg className="w-4 h-4 text-ink-faint" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
          </svg>
        </button>

        {dropdownOpen && (
          <div role="menu" className="absolute right-0 mt-2 w-52 bg-surface rounded-lg shadow-elev-md border border-line py-1 z-drawer">
            <div className="px-4 py-2 border-b border-line">
              <p className="text-sm font-medium text-ink">{user?.name}</p>
              <p className="text-xs text-ink-muted">{user?.email}</p>
              {user?.role && <p className="text-xs text-brand-navy dark:text-ink-muted mt-0.5">{user.role}</p>}
            </div>
            <button
              onClick={() => { setDropdownOpen(false); navigate('/profile') }}
              className="w-full text-left px-4 py-2 text-sm text-ink-muted hover:bg-surface-2 transition flex items-center gap-2 cursor-pointer focus:outline-none focus-visible:bg-surface-2"
            >
              <svg className="w-4 h-4 text-ink-faint" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              View profile
            </button>
            <div className="border-t border-line" />
            <button
              onClick={handleLogout}
              className="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition flex items-center gap-2 cursor-pointer focus:outline-none focus-visible:bg-red-50"
            >
              <svg className="w-4 h-4 text-red-400" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
              </svg>
              Sign Out
            </button>
          </div>
        )}
      </div>
      </div>
    </header>
  )
}
