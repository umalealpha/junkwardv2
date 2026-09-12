import { useQuery } from '@tanstack/react-query'
import { getStoredRoles } from '../api/auth'
import { fetchClaimsDashboard } from '../api/claimsAnalytics'
import { fetchClaims } from '../api/claims'
import { fetchClaimSlaDashboard } from '../api/claimsSla'
import { useClaimsSlaEnabled } from './useClaimsSla'

/**
 * Roles that may see Claims Analytics. Any claims-handling role plus admins.
 */
export const CLAIMS_ANALYTICS_ROLES = ['Claims Manager', 'Claims Team', 'Admin', 'Super Admin']

export function hasAnalyticsRole(): boolean {
  const mine = getStoredRoles()
  return CLAIMS_ANALYTICS_ROLES.some((r) => mine.includes(r))
}

/** Aggregate claims dashboard (by-status / by-type / totals). */
export function useClaimsDashboard(enabled = true) {
  return useQuery({
    queryKey: ['claims-analytics-dashboard'],
    queryFn: fetchClaimsDashboard,
    enabled,
    staleTime: 60 * 1000,
    retry: 0,
  })
}

/** How many claims the monthly-volume / handler charts sample. */
export const ANALYTICS_CLAIMS_SAMPLE = 500

/**
 * A page of recent claims used to derive monthly volume + handler load
 * client-side (the dashboard summary has no per-month or per-handler cut).
 * Returns the list plus the reported total so the UI can flag when it only
 * sampled the most recent N.
 */
export function useClaimsSample(enabled = true) {
  return useQuery({
    queryKey: ['claims-analytics-sample', ANALYTICS_CLAIMS_SAMPLE],
    queryFn: () => fetchClaims({ per_page: ANALYTICS_CLAIMS_SAMPLE, page: 1 }),
    enabled,
    staleTime: 2 * 60 * 1000,
    retry: 0,
  })
}

/**
 * SLA dashboard — powers Pipeline Stage Distribution and On-Time vs Delayed.
 * Only queried when the `claims_sla` flag is on; otherwise those charts
 * degrade to a "no data source" note.
 */
export function useClaimsSlaAnalytics() {
  const slaEnabled = useClaimsSlaEnabled()
  const query = useQuery({
    queryKey: ['claims-analytics-sla'],
    queryFn: fetchClaimSlaDashboard,
    enabled: slaEnabled,
    staleTime: 60 * 1000,
    retry: 0,
  })
  return { slaEnabled, ...query }
}
