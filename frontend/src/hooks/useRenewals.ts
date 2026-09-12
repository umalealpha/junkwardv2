import { useQuery } from '@tanstack/react-query'
import { fetchRenewals, type RenewalFilters } from '../api/renewals'

export function useRenewals(filters: RenewalFilters = {}) {
  return useQuery({
    queryKey: ['renewals', filters],
    queryFn: () => fetchRenewals(filters),
    placeholderData: (prev) => prev,
    staleTime: 2 * 60 * 1000,
    refetchOnWindowFocus: false,
  })
}
