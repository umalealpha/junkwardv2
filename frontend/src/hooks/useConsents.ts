import { useQuery } from '@tanstack/react-query'
import { fetchConsentSummary, fetchConsents, type ConsentFilters } from '../api/consents'

export function useConsentSummary() {
  return useQuery({
    queryKey: ['consents', 'summary'],
    queryFn: fetchConsentSummary,
    staleTime: 60_000, // 1 min — KPIs don't need realtime
  })
}

export function useConsents(filters: ConsentFilters) {
  return useQuery({
    queryKey: ['consents', 'list', filters],
    queryFn: () => fetchConsents(filters),
    staleTime: 30_000,
    placeholderData: (prev) => prev,
  })
}
