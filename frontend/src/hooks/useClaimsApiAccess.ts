import { useQuery } from '@tanstack/react-query'
import { fetchClaimsApiAccess, type ApiAccessResponse } from '../api/claimsApiAccess'

/**
 * Claims → Admin → API Access — read-only Sanctum token registry + usage stats.
 * Admin/Super-Admin only (enforced server-side); a non-admin call returns 403,
 * so we don't retry.
 */
export function useClaimsApiAccess(limit = 200, enabled = true) {
  return useQuery<ApiAccessResponse>({
    queryKey: ['claims-api-access', limit],
    queryFn: () => fetchClaimsApiAccess(limit),
    enabled,
    staleTime: 30 * 1000,
    retry: 0,
  })
}
