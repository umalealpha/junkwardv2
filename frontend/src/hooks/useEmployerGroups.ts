import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  deleteEmployerGroup,
  fetchADGroupPolicies,
  fetchEmployerGroup,
  fetchEmployerGroups,
  sendHrCredentials,
  sendOnboardingEmail,
  updateEmployerGroup,
  type ADGroupPolicyFilters,
  type EmployerGroupFilters,
  type UpdateEmployerGroupPayload,
} from '../api/employerGroups'

export function useEmployerGroups(filters: EmployerGroupFilters = {}) {
  return useQuery({
    queryKey: ['employerGroups', filters],
    queryFn: () => fetchEmployerGroups(filters),
    placeholderData: (prev) => prev,
    staleTime: 2 * 60 * 1000,
    refetchOnWindowFocus: false,
  })
}

export function useADGroupPolicies(filters: ADGroupPolicyFilters = {}) {
  return useQuery({
    queryKey: ['adGroupPolicies', filters],
    queryFn: () => fetchADGroupPolicies(filters),
    placeholderData: (prev) => prev,
    staleTime: 2 * 60 * 1000,
    refetchOnWindowFocus: false,
  })
}

export function useEmployerGroup(id: number) {
  return useQuery({
    queryKey: ['employerGroups', 'detail', id],
    queryFn: () => fetchEmployerGroup(id),
    enabled: Number.isFinite(id) && id > 0,
    staleTime: 2 * 60 * 1000,
    refetchOnWindowFocus: false,
  })
}

function useInvalidateEmployerGroups() {
  const qc = useQueryClient()
  return (id?: number) => {
    qc.invalidateQueries({ queryKey: ['employerGroups'] })
    if (id) qc.invalidateQueries({ queryKey: ['employerGroups', 'detail', id] })
  }
}

export function useUpdateEmployerGroup(id: number) {
  const invalidate = useInvalidateEmployerGroups()
  return useMutation({
    mutationFn: (payload: UpdateEmployerGroupPayload) => updateEmployerGroup(id, payload),
    onSuccess: () => invalidate(id),
  })
}

export function useDeleteEmployerGroup() {
  const invalidate = useInvalidateEmployerGroups()
  return useMutation({
    mutationFn: (id: number) => deleteEmployerGroup(id),
    onSuccess: () => invalidate(),
  })
}

export function useSendHrCredentials(id: number) {
  const invalidate = useInvalidateEmployerGroups()
  return useMutation({
    mutationFn: () => sendHrCredentials(id),
    onSuccess: () => invalidate(id),
  })
}

export function useSendOnboardingEmail(id: number) {
  const invalidate = useInvalidateEmployerGroups()
  return useMutation({
    mutationFn: () => sendOnboardingEmail(id),
    onSuccess: () => invalidate(id),
  })
}
