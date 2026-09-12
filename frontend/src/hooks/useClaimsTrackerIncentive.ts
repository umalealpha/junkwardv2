import { useQuery } from '@tanstack/react-query'
import {
  fetchClaimsTrackerIncentive,
  type TrackerIncentiveResponse,
} from '../api/claimsTrackerIncentive'

/**
 * Claims Tracker "Handler Incentive Report" — the single feed behind the
 * rebuilt Claims → Incentive Report screen (a 1:1 replica of the legacy Claims
 * Tracker Incentive Report).
 *
 * Keyed on the selected month so each month fetches and caches independently.
 * `placeholderData: (prev) => prev` (React Query v5's keepPreviousData) holds
 * the previous month's figures on screen while the next month loads, so
 * switching months doesn't flash an empty report.
 */
export function useClaimsTrackerIncentive(month: string | null, enabled = true) {
  return useQuery<TrackerIncentiveResponse>({
    queryKey: ['claims-tracker-incentive', month],
    queryFn: () => fetchClaimsTrackerIncentive(month),
    enabled,
    placeholderData: (prev) => prev,
    staleTime: 60 * 1000,
    retry: 0,
  })
}
