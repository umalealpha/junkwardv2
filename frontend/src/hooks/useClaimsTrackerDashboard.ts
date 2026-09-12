import { useQuery } from '@tanstack/react-query'
import {
  fetchClaimsTrackerDashboard,
  type ClaimsTrackerDashboard,
} from '../api/claimsTrackerDashboard'

/**
 * Claims Tracker dashboard aggregate — the single feed behind the rebuilt
 * Claims → Dashboard body (a 1:1 replica of the legacy Claims Tracker home).
 *
 * `month` is part of the query key so switching the month filter fetches (and
 * caches) each month independently. `null` = whole book ("All Months").
 * Same staleTime / refetchInterval / retry conventions as useClaimsDashboard.
 *
 * The page's access gate lives in useClaimsDashboard's `useCanSeeClaimsDashboard`
 * — pass its result in as `enabled` so we never fire for users who can't see
 * the screen.
 */
export function useClaimsTrackerDashboard(month: string | null, enabled = true) {
  return useQuery<ClaimsTrackerDashboard>({
    queryKey: ['claims-tracker-dashboard', month],
    queryFn: () => fetchClaimsTrackerDashboard(month),
    enabled,
    staleTime: 60 * 1000,
    refetchInterval: 60 * 1000,
    retry: 0,
  })
}
