import { useQuery } from '@tanstack/react-query'
import {
  fetchClaimsTrackerList,
  type ClaimsTrackerListParams,
  type ClaimsTrackerListResponse,
} from '../api/claimsTrackerList'

/**
 * Claims Tracker "All Claims" list — the single feed behind the rebuilt
 * Claims → All Claims screen (a 1:1 replica of the legacy Claims Tracker list).
 *
 * The full param object is part of the query key so each filter/sort/page
 * combination fetches and caches independently. `placeholderData: (prev) => prev`
 * (React Query v5's keepPreviousData) holds the previous page's rows on screen
 * while the next set loads, so paging/filtering doesn't flash an empty table.
 */
export function useClaimsTrackerList(params: ClaimsTrackerListParams, enabled = true) {
  return useQuery<ClaimsTrackerListResponse>({
    queryKey: ['claims-tracker-list', params],
    queryFn: () => fetchClaimsTrackerList(params),
    enabled,
    placeholderData: (prev) => prev,
    staleTime: 30 * 1000,
    retry: 0,
  })
}
