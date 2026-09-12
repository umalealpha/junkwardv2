import { useQuery } from '@tanstack/react-query'
import { fetchGroupPolicies, type GroupPolicyFilters } from '../api/groupPolicies'

export function useGroupPolicies(filters: GroupPolicyFilters = {}) {
  return useQuery({
    queryKey: ['groupPolicies', filters],
    queryFn: () => fetchGroupPolicies(filters),
    placeholderData: (prev) => prev,
    staleTime: 2 * 60 * 1000,
    refetchOnWindowFocus: false,
  })
}
