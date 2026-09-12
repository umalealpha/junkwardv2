import { useState, useEffect, useRef, Suspense } from 'react'
import { Outlet, Navigate, useLocation } from 'react-router-dom'
import { isAuthenticated, getStoredPermissions, getStoredRoles, getStoredUser } from '../../api/auth'
import apiClient from '../../api/client'
import Sidebar from './Sidebar'
import Header from './Header'
import ActionRail from '../common/ActionRail'
import LoadingSpinner from '../common/LoadingSpinner'
import { initAlphaBridgeWidget } from '../../utils/alphaBridge'
import { IS_TEST_ENV } from '../EnvironmentBanner'
// Create policy button moved to Header

export default function AdminLayout() {
  const location = useLocation()
  const [collapsed, setCollapsed] = useState(false)
  // Mobile drawer (below Tailwind `md`). The sidebar is an off-canvas drawer on
  // phones; this owns its open/close state so both the Header (hamburger) and
  // the Sidebar (backdrop/Escape/nav-click) can drive it. Ignored at md+ where
  // the sidebar is a fixed column.
  const [mobileOpen, setMobileOpen] = useState(false)
  // The hamburger lives in the Header; keep a ref here so the Sidebar can
  // return focus to it when the drawer closes (WCAG 2.4.3 focus management).
  const hamburgerRef = useRef<HTMLButtonElement>(null)
  const [permissions, setPermissions] = useState<string[]>([])
  const [roles, setRoles] = useState<string[]>([])

  // Close the drawer on navigation — a nav-item click changes the route, so
  // this covers "close on nav-item click" for every menu entry in one place.
  useEffect(() => {
    setMobileOpen(false)
  }, [location.pathname])

  // Preload the Alpha Bridge "Report an Issue" widget so the first click on
  // any entry point opens instantly. Failures are silent here — the entry
  // points retry on click and surface an error toast if Bridge is down.
  useEffect(() => {
    initAlphaBridgeWidget().catch(() => {})
  }, [])

  useEffect(() => {
    // Load cached permissions immediately (fast)
    setPermissions(getStoredPermissions())
    const storedRoles = getStoredRoles()
    if (storedRoles.length > 0) {
      setRoles(storedRoles)
    } else {
      const user = getStoredUser()
      if (user?.role) setRoles([user.role])
    }

    // Refresh permissions from server in background (catches permission changes without re-login)
    apiClient.get('/auth/user').then(r => {
      const perms = r.data?.permissions
      const userRoles = r.data?.roles
      if (perms && Array.isArray(perms)) {
        localStorage.setItem('user_permissions', JSON.stringify(perms))
        setPermissions(perms)
      }
      if (userRoles && Array.isArray(userRoles)) {
        localStorage.setItem('user_roles', JSON.stringify(userRoles))
        setRoles(userRoles)
      }
      // Refresh the lookups cache too (products/plans/states/agencies/...).
      // It was previously written only at login and never refreshed, so a
      // stale cache produced empty City/province and "No options" plan
      // dropdowns until the user re-logged in. Now it self-heals on every load.
      const lookups = r.data?.lookups
      if (lookups && typeof lookups === 'object') {
        localStorage.setItem('cached_lookups', JSON.stringify(lookups))
      }
    }).catch(() => {}) // Fail silently — stale cache still works
  }, [])

  if (!isAuthenticated()) {
    return <Navigate to="/login" replace />
  }

  return (
    <div className="min-h-screen bg-app">
      <Sidebar
        collapsed={collapsed}
        onToggle={() => setCollapsed(!collapsed)}
        permissions={permissions}
        roles={roles}
        mobileOpen={mobileOpen}
        onMobileClose={() => setMobileOpen(false)}
        returnFocusRef={hamburgerRef}
      />
      <Header
        sidebarCollapsed={collapsed}
        onMobileMenuOpen={() => setMobileOpen(true)}
        mobileMenuOpen={mobileOpen}
        hamburgerRef={hamburgerRef}
      />
      {/* Content offset only reserves space for the sidebar at md+; below md the
          drawer overlays the content and the margin resets to 0. */}
      <main
        className={`${IS_TEST_ENV ? 'pt-24' : 'pt-14'} transition-all duration-300 ml-0 ${
          collapsed ? 'md:ml-[var(--sidebar-w-collapsed)]' : 'md:ml-[var(--sidebar-w)]'
        }`}
      >
        {/* Suspense boundary lives INSIDE the layout so lazy pages suspend
            here — only the content area shows the loader while the sidebar +
            header stay mounted (no full-shell flash on navigation). Keyed by
            route so the page-enter animation replays on each navigation. */}
        <Suspense fallback={<div className="flex items-center justify-center py-24"><LoadingSpinner size="lg" /></div>}>
          <div key={location.pathname} className="page-enter">
            <Outlet />
          </div>
        </Suspense>
      </main>
      <ActionRail />
    </div>
  )
}
