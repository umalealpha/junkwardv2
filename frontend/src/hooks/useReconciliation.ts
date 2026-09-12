import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { fetchReconciliationSummary, fetchReconciliationRuns, fetchReconciliationAnomalies, acknowledgeAnomaly, resolveAnomaly, markFalsePositive, type AnomalyFilters } from '../api/reconciliation'

export function useReconciliationSummary() {
  return useQuery({ queryKey: ['reconciliation-summary'], queryFn: fetchReconciliationSummary, staleTime: 60 * 1000, refetchOnWindowFocus: false })
}

export function useReconciliationRuns(params: { per_page?: number; page?: number } = {}) {
  return useQuery({ queryKey: ['reconciliation-runs', params], queryFn: () => fetchReconciliationRuns(params), staleTime: 60 * 1000, refetchOnWindowFocus: false })
}

export function useReconciliationAnomalies(filters: AnomalyFilters = {}) {
  return useQuery({ queryKey: ['reconciliation-anomalies', filters], queryFn: () => fetchReconciliationAnomalies(filters), placeholderData: (prev) => prev, staleTime: 60 * 1000, refetchOnWindowFocus: false })
}

export function useAcknowledgeAnomaly() {
  const qc = useQueryClient()
  return useMutation({ mutationFn: (id: number) => acknowledgeAnomaly(id), onSuccess: () => { qc.invalidateQueries({ queryKey: ['reconciliation-anomalies'] }); qc.invalidateQueries({ queryKey: ['reconciliation-summary'] }) } })
}

export function useResolveAnomaly() {
  const qc = useQueryClient()
  return useMutation({ mutationFn: ({ id, notes }: { id: number; notes: string }) => resolveAnomaly(id, notes), onSuccess: () => { qc.invalidateQueries({ queryKey: ['reconciliation-anomalies'] }); qc.invalidateQueries({ queryKey: ['reconciliation-summary'] }) } })
}

export function useMarkFalsePositive() {
  const qc = useQueryClient()
  return useMutation({ mutationFn: (id: number) => markFalsePositive(id), onSuccess: () => { qc.invalidateQueries({ queryKey: ['reconciliation-anomalies'] }); qc.invalidateQueries({ queryKey: ['reconciliation-summary'] }) } })
}
