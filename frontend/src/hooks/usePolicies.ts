import { useQuery } from '@tanstack/react-query'
import {
  fetchPolicies,
  fetchPolicy,
  fetchPolicyVehicles,
  fetchPolicyMembers,
  fetchPolicyCoApplicants,
  fetchPolicyDevices,
  fetchPolicyCoverages,
  fetchPolicyClaims,
  fetchPolicyTransactions,
  fetchPolicyClaimsWaiver,
  fetchPolicyRiskAddresses,
  fetchPolicyKycDocuments,
  fetchPolicyBankingDocuments,
  fetchPolicyLogs,
  fetchPolicyLedger,
  fetchPolicyScheduleTransactions,
  fetchPolicyMati,
  fetchPolicyAttachments,
  fetchPolicyAttachmentList,
  fetchPolicyFileTypes,
  fetchPolicyTerms,
  fetchPolicyActions,
  fetchPolicyReinsurance,
  fetchPolicySpecialistCoverages,
  fetchPolicyHealthSummary,
  fetchPolicyClientHealth,
  type PolicyFilters,
} from '../api/policies'

export function usePolicies(filters: PolicyFilters = {}, enabled = true) {
  return useQuery({
    queryKey: ['policies', filters],
    queryFn: () => fetchPolicies(filters),
    placeholderData: (prev) => prev,
    staleTime: 2 * 60 * 1000,
    refetchOnWindowFocus: false,
    enabled,
  })
}

export function usePolicy(id: number) {
  return useQuery({
    queryKey: ['policy', id],
    queryFn: () => fetchPolicy(id),
    staleTime: 5 * 60 * 1000,
    enabled: !!id,
  })
}

// ── Lazy tab hooks (only fetch when `enabled` is true) ──────────

export function usePolicyVehicles(id: number, enabled: boolean, actionId?: number) {
  return useQuery({
    queryKey: ['policy', id, 'vehicles', actionId ?? 'latest'],
    queryFn: () => fetchPolicyVehicles(id, actionId),
    staleTime: 5 * 60 * 1000,
    enabled: !!id && enabled,
  })
}

export function usePolicyMembers(id: number, enabled: boolean) {
  return useQuery({
    queryKey: ['policy', id, 'members'],
    queryFn: () => fetchPolicyMembers(id),
    staleTime: 5 * 60 * 1000,
    enabled: !!id && enabled,
  })
}

export function usePolicyCoApplicants(id: number, enabled: boolean) {
  return useQuery({
    queryKey: ['policy', id, 'coapplicants'],
    queryFn: () => fetchPolicyCoApplicants(id),
    staleTime: 5 * 60 * 1000,
    enabled: !!id && enabled,
  })
}

export function usePolicyDevices(id: number, enabled: boolean) {
  return useQuery({
    queryKey: ['policy', id, 'devices'],
    queryFn: () => fetchPolicyDevices(id),
    staleTime: 5 * 60 * 1000,
    enabled: !!id && enabled,
  })
}

export function usePolicyCoverages(id: number, enabled: boolean) {
  return useQuery({
    queryKey: ['policy', id, 'coverages'],
    queryFn: () => fetchPolicyCoverages(id),
    staleTime: 5 * 60 * 1000,
    enabled: !!id && enabled,
  })
}

export function usePolicyActions(id: number, enabled: boolean) {
  return useQuery({
    queryKey: ['policy', id, 'actions'],
    queryFn: () => fetchPolicyActions(id),
    staleTime: 5 * 60 * 1000,
    enabled: !!id && enabled,
  })
}

export function usePolicyClaims(id: number, enabled: boolean, page = 1) {
  return useQuery({
    queryKey: ['policy', id, 'claims', page],
    queryFn: () => fetchPolicyClaims(id, page),
    staleTime: 5 * 60 * 1000,
    enabled: !!id && enabled,
  })
}

export function usePolicyTransactions(id: number, enabled: boolean, page = 1) {
  return useQuery({
    queryKey: ['policy', id, 'transactions', page],
    queryFn: () => fetchPolicyTransactions(id, page),
    staleTime: 5 * 60 * 1000,
    enabled: !!id && enabled,
  })
}

export function usePolicyClaimsWaiver(id: number, enabled: boolean) {
  return useQuery({
    queryKey: ['policy', id, 'claimsWaiver'],
    queryFn: () => fetchPolicyClaimsWaiver(id),
    staleTime: 5 * 60 * 1000,
    enabled: !!id && enabled,
  })
}

export function usePolicyRiskAddresses(id: number, enabled: boolean, termId?: number | null, actionId?: number | null) {
  return useQuery({
    queryKey: ['policy', id, 'riskAddresses', termId, actionId],
    queryFn: () => fetchPolicyRiskAddresses(id, termId || undefined, actionId || undefined),
    staleTime: 5 * 60 * 1000,
    enabled: !!id && enabled,
  })
}

/** V8 attachment-table rows for the Attachments tab. One row per
 *  policy_attachments record (each row may carry multiple files). */
export function usePolicyAttachmentList(id: number, enabled: boolean) {
  return useQuery({
    queryKey: ['policy', id, 'attachmentList'],
    queryFn: () => fetchPolicyAttachmentList(id),
    staleTime: 60 * 1000,
    enabled: !!id && enabled,
  })
}

/** Cached document-type lookup for the Attachments tab dropdown. */
export function usePolicyFileTypes(enabled = true) {
  return useQuery({
    queryKey: ['policy', 'fileTypes'],
    queryFn: () => fetchPolicyFileTypes(),
    staleTime: 30 * 60 * 1000,
    enabled,
  })
}

export function usePolicyKycDocuments(id: number, enabled: boolean) {
  return useQuery({
    queryKey: ['policy', id, 'kycDocuments'],
    queryFn: () => fetchPolicyKycDocuments(id),
    staleTime: 5 * 60 * 1000,
    enabled: !!id && enabled,
  })
}

export function usePolicyBankingDocuments(id: number, enabled: boolean) {
  return useQuery({
    queryKey: ['policy', id, 'bankingDocuments'],
    queryFn: () => fetchPolicyBankingDocuments(id),
    staleTime: 5 * 60 * 1000,
    enabled: !!id && enabled,
  })
}

export function usePolicyLogs(id: number, enabled: boolean) {
  return useQuery({
    queryKey: ['policy', id, 'logs'],
    queryFn: () => fetchPolicyLogs(id),
    staleTime: 5 * 60 * 1000,
    enabled: !!id && enabled,
  })
}

export function usePolicyLedger(id: number, enabled: boolean, actionId?: number) {
  return useQuery({
    queryKey: ['policy', id, 'ledger', actionId ?? 'all'],
    queryFn: () => fetchPolicyLedger(id, actionId),
    staleTime: 5 * 60 * 1000,
    enabled: !!id && enabled,
  })
}

export function usePolicyScheduleTransactions(id: number, enabled: boolean) {
  return useQuery({
    queryKey: ['policy', id, 'scheduleTransactions'],
    queryFn: () => fetchPolicyScheduleTransactions(id),
    staleTime: 5 * 60 * 1000,
    enabled: !!id && enabled,
  })
}

export function usePolicyMati(id: number, enabled: boolean) {
  return useQuery({
    queryKey: ['policy', id, 'mati'],
    queryFn: () => fetchPolicyMati(id),
    staleTime: 5 * 60 * 1000,
    enabled: !!id && enabled,
  })
}

export function usePolicyAttachments(id: number, enabled: boolean) {
  return useQuery({
    queryKey: ['policy', id, 'attachments'],
    queryFn: () => fetchPolicyAttachments(id),
    staleTime: 5 * 60 * 1000,
    enabled: !!id && enabled,
  })
}

export function usePolicyTerms(id: number, enabled: boolean) {
  return useQuery({
    queryKey: ['policy', id, 'terms'],
    queryFn: () => fetchPolicyTerms(id),
    staleTime: 5 * 60 * 1000,
    enabled: !!id && enabled,
  })
}

export function usePolicyReinsurance(id: number, enabled: boolean) {
  return useQuery({
    queryKey: ['policy', id, 'reinsurance'],
    queryFn: () => fetchPolicyReinsurance(id),
    staleTime: 5 * 60 * 1000,
    enabled: !!id && enabled,
  })
}

export function usePolicySpecialistCoverages(id: number, enabled: boolean) {
  return useQuery({
    queryKey: ['policy', id, 'specialist-coverages'],
    queryFn: () => fetchPolicySpecialistCoverages(id),
    staleTime: 5 * 60 * 1000,
    enabled: !!id && enabled,
  })
}

export function usePolicyHealthSummary(id: number) {
  return useQuery({
    queryKey: ['policy', id, 'health-summary'],
    queryFn: () => fetchPolicyHealthSummary(id),
    staleTime: 60 * 1000,
    enabled: !!id,
  })
}

// Full Client Health Widget payload (payment-method aware). Use this in
// preference to usePolicyHealthSummary for new UI — the legacy hook is
// kept only for backwards compatibility.
export function usePolicyClientHealth(id: number) {
  return useQuery({
    queryKey: ['policy', id, 'client-health'],
    queryFn: () => fetchPolicyClientHealth(id),
    staleTime: 60 * 1000,
    enabled: !!id,
  })
}
