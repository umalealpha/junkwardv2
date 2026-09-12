import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useIntegrations } from './useIntegrations'
import { getStoredRoles, getStoredPermissions } from '../api/auth'
import {
  fetchClaimSlaDashboard,
  fetchClaimSlaLeaderboard,
  fetchClaimSla,
  fetchClaimSlaTimeline,
  updateClaimSlaTimeline,
  type ClaimSlaTimelinePayload,
} from '../api/claimsSla'

/**
 * Roles that may see the management SLA dashboard / leaderboard. Mirrors
 * ClaimSlaController::managerRoles().
 */
export const CLAIMS_SLA_MANAGER_ROLES = ['Claims Manager', 'Super Admin', 'Admin']

/**
 * Roles that may read a single claim's timeline/SLA and record stage dates.
 * Mirrors ClaimSlaController::recorderRoles().
 */
export const CLAIMS_SLA_RECORDER_ROLES = ['Claims Team', 'Claims Manager']

/**
 * Whether the `claims_sla` runtime flag is ON. Read from the (auth-only,
 * cheap, cached) integrations list so nav/tabs can hide the feature entirely
 * while it ships dark. Any authenticated user may read the flag state.
 */
export function useClaimsSlaEnabled(): boolean {
  const { data } = useIntegrations()
  return !!data?.data?.find((i) => i.integration === 'claims_sla')?.enabled
}

/** True if the current user holds ANY of the given role names. */
function hasAnyRole(roles: string[]): boolean {
  const mine = getStoredRoles()
  return roles.some((r) => mine.includes(r))
}

/** May the user open the management SLA dashboard? */
export function useCanSeeClaimsSlaDashboard(): boolean {
  const enabled = useClaimsSlaEnabled()
  return enabled && hasAnyRole(CLAIMS_SLA_MANAGER_ROLES)
}

/** May the user see a claim's SLA panel / stage timeline (read)? */
export function useCanSeeClaimSlaPanel(): boolean {
  const enabled = useClaimsSlaEnabled()
  return enabled && hasAnyRole(CLAIMS_SLA_RECORDER_ROLES)
}

/**
 * May the user EDIT the stage timeline? Backend requires a recorder role AND
 * the `claim-edit` permission (PATCH middleware). Everyone else is read-only.
 */
export function canEditClaimSlaTimeline(): boolean {
  return hasAnyRole(CLAIMS_SLA_RECORDER_ROLES) && getStoredPermissions().includes('claim-edit')
}

// ── Queries ─────────────────────────────────────────────────────────────
export function useClaimSlaDashboard(enabled = true) {
  return useQuery({
    queryKey: ['claims-sla-dashboard'],
    queryFn: fetchClaimSlaDashboard,
    enabled,
    staleTime: 60 * 1000,
    refetchInterval: 60 * 1000,
    retry: 0,
  })
}

export function useClaimSlaLeaderboard(enabled = true) {
  return useQuery({
    queryKey: ['claims-sla-leaderboard'],
    queryFn: fetchClaimSlaLeaderboard,
    enabled,
    staleTime: 60 * 1000,
    refetchInterval: 60 * 1000,
    retry: 0,
  })
}

export function useClaimSla(claimId: number, enabled = true) {
  return useQuery({
    queryKey: ['claim-sla', claimId],
    queryFn: () => fetchClaimSla(claimId),
    enabled: enabled && !!claimId,
    staleTime: 30 * 1000,
    retry: 0,
  })
}

export function useClaimSlaTimeline(claimId: number, enabled = true) {
  return useQuery({
    queryKey: ['claim-sla-timeline', claimId],
    queryFn: () => fetchClaimSlaTimeline(claimId),
    enabled: enabled && !!claimId,
    staleTime: 30 * 1000,
    retry: 0,
  })
}

// ── Mutation ────────────────────────────────────────────────────────────
export function useUpdateClaimSlaTimeline(claimId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationKey: ['claim-sla-timeline-update', claimId],
    mutationFn: (payload: ClaimSlaTimelinePayload) => updateClaimSlaTimeline(claimId, payload),
    onSuccess: (res) => {
      // Refresh the timeline + the derived SLA panel; aggregates recompute
      // server-side on next dashboard read.
      qc.setQueryData(['claim-sla-timeline', claimId], res.data)
      qc.invalidateQueries({ queryKey: ['claim-sla', claimId] })
      qc.invalidateQueries({ queryKey: ['claims-sla-dashboard'] })
      qc.invalidateQueries({ queryKey: ['claims-sla-leaderboard'] })
    },
  })
}
