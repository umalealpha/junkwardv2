import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { fetchCancelRequests, approveCancelRequest, declineCancelRequest, type CancelRequestFilters } from '../api/cancelRequests'

export function useCancelRequests(filters: CancelRequestFilters = {}) {
  return useQuery({
    queryKey: ['cancelRequests', filters],
    queryFn: () => fetchCancelRequests(filters),
    placeholderData: (prev) => prev,
    staleTime: 2 * 60 * 1000,
    refetchOnWindowFocus: false,
  })
}

export function useApproveCancelRequest() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => approveCancelRequest(id),
    onSuccess: () => {
      // Approval cancels the policy server-side, so refresh the request list
      // AND any policy list/detail views so the new (cancelled) status shows
      // without a hard reload.
      queryClient.invalidateQueries({ queryKey: ['cancelRequests'] })
      queryClient.invalidateQueries({ queryKey: ['policy'] })
      queryClient.invalidateQueries({ queryKey: ['policies'] })
    },
  })
}

export function useDeclineCancelRequest() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => declineCancelRequest(id),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['cancelRequests'] }) },
  })
}
