import { useQuery } from '@tanstack/react-query'
import { fetchBatchReport, type BatchReportFilters } from '../api/batchProcessing'

export function useBatchReport(filters: BatchReportFilters = {}) {
  return useQuery({
    queryKey: ['batchReport', filters],
    queryFn: () => fetchBatchReport(filters),
    placeholderData: (prev) => prev,
    staleTime: 2 * 60 * 1000,
    refetchOnWindowFocus: false,
  })
}
