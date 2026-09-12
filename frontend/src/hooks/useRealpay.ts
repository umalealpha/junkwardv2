import { useQuery } from '@tanstack/react-query'
import {
  fetchRealpayBanks,
  fetchRealpayBranches,
  fetchPolicyRealpayContracts,
  fetchPolicyRealpayInstallments,
  fetchPolicyTransactionLogs,
} from '../api/realpay'

const FIVE_MIN = 5 * 60 * 1000

/** All supported RealPay banks. Cached for 30 minutes — bank list rarely changes. */
export function useRealpayBanks(enabled: boolean) {
  return useQuery({
    queryKey: ['realpay', 'banks'],
    queryFn: fetchRealpayBanks,
    staleTime: 30 * 60 * 1000,
    enabled,
  })
}

/** Branches for the selected bank. Disabled until a bankId is picked. */
export function useRealpayBranches(bankId: number | null) {
  return useQuery({
    queryKey: ['realpay', 'branches', bankId],
    queryFn: () => fetchRealpayBranches(bankId as number),
    staleTime: FIVE_MIN,
    enabled: !!bankId,
  })
}

export function usePolicyRealpayContracts(policyId: number, enabled: boolean) {
  return useQuery({
    queryKey: ['policy', policyId, 'realpay', 'contracts'],
    queryFn: () => fetchPolicyRealpayContracts(policyId),
    staleTime: FIVE_MIN,
    enabled: !!policyId && enabled,
  })
}

export function usePolicyRealpayInstallments(policyId: number, enabled: boolean) {
  return useQuery({
    queryKey: ['policy', policyId, 'realpay', 'installments'],
    queryFn: () => fetchPolicyRealpayInstallments(policyId),
    staleTime: FIVE_MIN,
    enabled: !!policyId && enabled,
  })
}

export function usePolicyTransactionLogs(policyId: number, enabled: boolean) {
  return useQuery({
    queryKey: ['policy', policyId, 'transaction-logs'],
    queryFn: () => fetchPolicyTransactionLogs(policyId),
    staleTime: FIVE_MIN,
    enabled: !!policyId && enabled,
  })
}
