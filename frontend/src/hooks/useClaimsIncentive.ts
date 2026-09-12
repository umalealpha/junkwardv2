import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useIntegrations } from './useIntegrations'
import { getStoredRoles } from '../api/auth'
import {
  fetchIncentiveReport,
  searchSuppliers,
  markSupplierIncentiveFlags,
  CLAIMS_INCENTIVE_FLAG,
  CLAIMS_INCENTIVE_ROLES,
  type IncentiveFlagsPayload,
} from '../api/claimsIncentive'

/**
 * Whether the `claims_incentive_report` runtime flag is ON. Read from the
 * (auth-only, cheap, cached) integrations list so nav/pages can hide the
 * feature entirely while it ships dark.
 */
export function useClaimsIncentiveEnabled(): boolean {
  const { data } = useIntegrations()
  return !!data?.data?.find((i) => i.integration === CLAIMS_INCENTIVE_FLAG)?.enabled
}

/** True if the current user holds any incentive-report role. */
export function hasIncentiveRole(): boolean {
  const mine = getStoredRoles()
  return CLAIMS_INCENTIVE_ROLES.some((r) => mine.includes(r))
}

export function useIncentiveReport(dateFrom: string, dateTo: string, enabled = true) {
  return useQuery({
    queryKey: ['claims-incentive-report', dateFrom, dateTo],
    queryFn: () => fetchIncentiveReport(dateFrom, dateTo),
    enabled: enabled && !!dateFrom && !!dateTo,
    staleTime: 60 * 1000,
    retry: 0,
  })
}

export function useSupplierSearch(term: string, enabled = true) {
  return useQuery({
    queryKey: ['incentive-supplier-search', term],
    queryFn: () => searchSuppliers(term),
    // Only fire once the operator has typed something — avoids listing every
    // supplier on first paint.
    enabled: enabled && term.trim().length >= 2,
    staleTime: 30 * 1000,
    retry: 0,
  })
}

export function useMarkSupplierIncentiveFlags() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, flags }: { id: number; flags: IncentiveFlagsPayload }) =>
      markSupplierIncentiveFlags(id, flags),
    onSuccess: () => {
      // Marking a supplier changes the approved-of-routed numerator, so the
      // report should recompute on next view.
      qc.invalidateQueries({ queryKey: ['claims-incentive-report'] })
    },
  })
}
