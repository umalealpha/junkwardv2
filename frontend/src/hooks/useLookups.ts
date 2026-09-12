import { useQuery } from '@tanstack/react-query'
import {
  fetchPolicyCreateData,
  fetchAgencies,
  fetchAgents,
  fetchAgentsByAgency,
  fetchCitiesByState,
  fetchPlansByProduct,
  fetchCoveragesByProduct,
  fetchCompanies,
} from '../api/lookups'
import { fetchPolicyEditData } from '../api/policyCreate'

export function usePolicyCreateData() {
  return useQuery({
    queryKey: ['lookups', 'policy-create'],
    queryFn: fetchPolicyCreateData,
    staleTime: 30 * 60 * 1000, // 30 min — rarely changes
  })
}

export function useAgencies() {
  return useQuery({
    queryKey: ['lookups', 'agencies'],
    queryFn: fetchAgencies,
    staleTime: 10 * 60 * 1000,
  })
}

export function useAgents() {
  return useQuery({
    queryKey: ['lookups', 'agents'],
    queryFn: fetchAgents,
    staleTime: 10 * 60 * 1000,
  })
}

export function useAgentsByAgency(agencyId: number | null) {
  return useQuery({
    queryKey: ['lookups', 'agents', agencyId],
    queryFn: () => fetchAgentsByAgency(agencyId!),
    enabled: !!agencyId,
    staleTime: 5 * 60 * 1000,
  })
}

export function useCitiesByState(stateId: number | null) {
  return useQuery({
    queryKey: ['lookups', 'cities', stateId],
    queryFn: () => fetchCitiesByState(stateId!),
    enabled: !!stateId,
    staleTime: 30 * 60 * 1000,
  })
}

export function usePlansByProduct(productId: number | null) {
  return useQuery({
    queryKey: ['lookups', 'plans', productId],
    queryFn: () => fetchPlansByProduct(productId!),
    enabled: !!productId,
    staleTime: 30 * 60 * 1000,
  })
}

export function useCoveragesByProduct(productId: number | null) {
  return useQuery({
    queryKey: ['lookups', 'coverages', productId],
    queryFn: () => fetchCoveragesByProduct(productId!),
    enabled: !!productId,
    staleTime: 30 * 60 * 1000,
  })
}

export function useCompanies() {
  return useQuery({
    queryKey: ['lookups', 'companies'],
    queryFn: fetchCompanies,
    staleTime: 10 * 60 * 1000,
  })
}

export function usePolicyEditData(policyId: number | null) {
  return useQuery({
    queryKey: ['policy', 'edit-data', policyId],
    queryFn: () => fetchPolicyEditData(policyId!),
    enabled: !!policyId,
    staleTime: 0,
  })
}
