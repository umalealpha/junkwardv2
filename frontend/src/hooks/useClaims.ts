import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  fetchClaims,
  fetchClaim,
  fetchClaimCreateData,
  createClaim,
  updateClaimStatus,
  fetchClaimReviewNotes,
  createClaimReviewNote,
  type ClaimFilters,
  type CreateClaimPayload,
  type CreateClaimReviewNotePayload,
} from '../api/claims'

export function useClaims(filters: ClaimFilters) {
  return useQuery({
    queryKey: ['claims', filters],
    queryFn: () => fetchClaims(filters),
    staleTime: 2 * 60 * 1000,
  })
}

export function useClaim(id: number) {
  return useQuery({
    queryKey: ['claims', id],
    queryFn: () => fetchClaim(id),
    staleTime: 5 * 60 * 1000,
    enabled: !!id,
    // UAT 2026-05-26 (Arjun): claim detail showed "infinite spinner"
    // on certain Domestic claims. The global default retries once, so
    // axios's 30s timeout × 2 attempts kept users staring at the
    // spinner for ~60s before the error path engaged. Surface failures
    // immediately on the detail page so the error UI (with retry
    // button) appears within a single timeout window.
    retry: 0,
  })
}

export function useClaimCreateData() {
  return useQuery({
    queryKey: ['claims', 'create-data'],
    queryFn: fetchClaimCreateData,
    staleTime: 30 * 60 * 1000,
  })
}

export function useCreateClaim() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: CreateClaimPayload) => createClaim(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['claims'] })
    },
  })
}

export function useClaimReviewNotes(claimId: number) {
  return useQuery({
    queryKey: ['claims', claimId, 'review-notes'],
    queryFn: () => fetchClaimReviewNotes(claimId),
    enabled: !!claimId,
    staleTime: 60 * 1000,
  })
}

export function useCreateClaimReviewNote(claimId: number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: CreateClaimReviewNotePayload) => createClaimReviewNote(claimId, payload),
    onSuccess: () => {
      // Prefix-invalidate the claim detail so BOTH the dedicated Review Notes
      // query (['claims', id, 'review-notes']) and the claim detail itself
      // (['claims', id]) refetch — the latter carries activityLog, so the new
      // "Review note logged" entry shows in the Activity Log tab without a
      // manual page refresh.
      queryClient.invalidateQueries({ queryKey: ['claims', claimId] })
    },
  })
}

export function useUpdateClaimStatus() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, status, closedNote, subStatus }: { id: number; status: string; closedNote?: string; subStatus?: string }) =>
      updateClaimStatus(id, status, closedNote, subStatus),
    onSuccess: (_data, variables) => {
      queryClient.invalidateQueries({ queryKey: ['claims'] })
      queryClient.invalidateQueries({ queryKey: ['claims', variables.id] })
    },
  })
}
