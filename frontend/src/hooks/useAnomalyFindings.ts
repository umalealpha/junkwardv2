import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  fetchAnomalySummary,
  fetchAnomalyFindings,
  reviewFinding,
  resolveFinding,
  dismissFinding,
  type FindingFilters,
} from '../api/anomalyFindings'

const KEYS = {
  summary:  ['anomaly-findings-summary'] as const,
  findings: (f: FindingFilters) => ['anomaly-findings', f] as const,
}

export function useAnomalySummary() {
  return useQuery({
    queryKey: KEYS.summary,
    queryFn: fetchAnomalySummary,
    staleTime: 60 * 1000,
    refetchOnWindowFocus: false,
  })
}

export function useAnomalyFindings(filters: FindingFilters = {}) {
  return useQuery({
    queryKey: KEYS.findings(filters),
    queryFn: () => fetchAnomalyFindings(filters),
    placeholderData: (prev) => prev,
    staleTime: 60 * 1000,
    refetchOnWindowFocus: false,
  })
}

function invalidate(qc: ReturnType<typeof useQueryClient>) {
  qc.invalidateQueries({ queryKey: ['anomaly-findings'] })
  qc.invalidateQueries({ queryKey: KEYS.summary })
}

export function useReviewFinding() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => reviewFinding(id),
    onSuccess: () => invalidate(qc),
  })
}

export function useResolveFinding() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, notes }: { id: number; notes: string }) => resolveFinding(id, notes),
    onSuccess: () => invalidate(qc),
  })
}

export function useDismissFinding() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, notes }: { id: number; notes: string }) => dismissFinding(id, notes),
    onSuccess: () => invalidate(qc),
  })
}
