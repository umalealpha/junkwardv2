import { useQuery } from '@tanstack/react-query'
import {
  fetchMapfreSubmissionSummary,
  fetchMapfreSubmissions,
  type MapfreSubmissionFilters,
} from '../api/mapfreSubmissions'

export function useMapfreSubmissionSummary() {
  return useQuery({
    queryKey: ['mapfreSubmissions', 'summary'],
    queryFn: fetchMapfreSubmissionSummary,
    staleTime: 60_000, // 1 min — KPIs don't need realtime
  })
}

export function useMapfreSubmissions(filters: MapfreSubmissionFilters) {
  return useQuery({
    queryKey: ['mapfreSubmissions', 'list', filters],
    queryFn: () => fetchMapfreSubmissions(filters),
    staleTime: 30_000,
    placeholderData: (prev) => prev,
  })
}
