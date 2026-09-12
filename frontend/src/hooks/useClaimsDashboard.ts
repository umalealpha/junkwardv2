import { useQuery } from '@tanstack/react-query'
import { getStoredPermissions, getStoredRoles } from '../api/auth'
import { fetchClaimsDashboard } from '../api/claimsDashboard'

/**
 * Roles allowed to open the Claims → Dashboard screen. Any claims role plus
 * the finance / underwriting / exec read roles that had the tracker's home
 * dashboard in their CORE_PAGES. This is CORE claims nav (not the dark
 * `claims_sla` flag) — visibility is role/permission based, never a toggle.
 *
 * A user also qualifies if they hold the `claim-list` permission (i.e. they
 * can already reach the claims list), so the dashboard tracks claim-list
 * access without needing every role enumerated here.
 */
export const CLAIMS_DASHBOARD_ROLES = [
  'Claims Team',
  'Claims Manager',
  'Admin',
  'admin',
  'Super Admin',
  'Finance',
  'Finance Claims Viewer',
  'Underwriting',
  'CXO',
  'CEO',
  'CFO',
]

/** True if the current user holds ANY of the given role names. */
function hasAnyRole(roles: string[]): boolean {
  const mine = getStoredRoles()
  return roles.some((r) => mine.includes(r))
}

/** May the user open the Claims Dashboard (role OR claim-list permission)? */
export function useCanSeeClaimsDashboard(): boolean {
  return hasAnyRole(CLAIMS_DASHBOARD_ROLES) || getStoredPermissions().includes('claim-list')
}

/** Whole-book claim aggregates (counts, reserve/paid totals, top claims). */
export function useClaimsDashboard(enabled = true) {
  return useQuery({
    queryKey: ['claims-dashboard'],
    queryFn: fetchClaimsDashboard,
    enabled,
    staleTime: 60 * 1000,
    refetchInterval: 60 * 1000,
    retry: 0,
  })
}
