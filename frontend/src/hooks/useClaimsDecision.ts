import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useIntegrations } from './useIntegrations'
import { getStoredRoles, getStoredPermissions } from '../api/auth'
import {
  fetchClaimDecision,
  decideClaim,
  reverseClaimDecision,
  type DecidePayload,
} from '../api/claimsDecision'

/** Admin roles that may PREVIEW the workflow even while the flag is OFF. */
export const CLAIMS_DECISION_ADMIN_ROLES = ['Admin', 'Super Admin']

/** Roles that may reverse a decision (admins always may). */
export const CLAIMS_DECISION_REVERSE_ROLES = ['Claims Manager', 'Admin', 'Super Admin']

/**
 * Roles that may see/read the decision panel when the flag is ON. Mirrors
 * ClaimDecisionController::readRoles().
 */
export const CLAIMS_DECISION_READ_ROLES = ['Claims Team', 'Claims Manager', 'Admin', 'Super Admin']

/** Permission a non-admin decider must hold to approve/repudiate. */
const ACT_PERMISSION = 'claim-edit'

/**
 * Whether the `claims_decision_workflow` runtime flag is ON. Read from the
 * (auth-only, cheap, cached) integrations list so tabs can hide the feature
 * while it ships dark.
 */
export function useClaimsDecisionEnabled(): boolean {
  const { data } = useIntegrations()
  return !!data?.data?.find((i) => i.integration === 'claims_decision_workflow')?.enabled
}

function hasAnyRole(roles: string[]): boolean {
  const mine = getStoredRoles()
  return roles.some((r) => mine.includes(r))
}

function isAdmin(): boolean {
  return hasAnyRole(CLAIMS_DECISION_ADMIN_ROLES)
}

/**
 * May the current user SEE the decision panel?
 *   - Admin / Super Admin — always (preview), flag on or off.
 *   - Claims Team / Manager — only when the flag is ON.
 */
export function useCanSeeClaimDecisionPanel(): boolean {
  const enabled = useClaimsDecisionEnabled()
  if (isAdmin()) return true
  return enabled && hasAnyRole(CLAIMS_DECISION_READ_ROLES)
}

/** True when the user is previewing the feature while the flag is OFF. */
export function useClaimDecisionPreview(): boolean {
  const enabled = useClaimsDecisionEnabled()
  return isAdmin() && !enabled
}

/**
 * May the user APPROVE / REPUDIATE? Admins always (preview); everyone else
 * needs a claims role + the claim-edit permission AND the flag ON.
 */
export function useCanDecideClaim(): boolean {
  const enabled = useClaimsDecisionEnabled()
  if (isAdmin()) return true
  return enabled
    && hasAnyRole(CLAIMS_DECISION_READ_ROLES)
    && getStoredPermissions().includes(ACT_PERMISSION)
}

/** May the user REVERSE a decision? Admin / Super Admin / Claims Manager. */
export function useCanReverseClaimDecision(): boolean {
  const enabled = useClaimsDecisionEnabled()
  if (isAdmin()) return true
  return enabled && hasAnyRole(CLAIMS_DECISION_REVERSE_ROLES)
}

// ── Query ─────────────────────────────────────────────────────────────────
export function useClaimDecision(claimId: number, enabled = true) {
  return useQuery({
    queryKey: ['claim-decision', claimId],
    queryFn: () => fetchClaimDecision(claimId),
    enabled: enabled && !!claimId,
    staleTime: 30 * 1000,
    retry: 0,
  })
}

// ── Mutations ───────────────────────────────────────────────────────────────
export function useDecideClaim(claimId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationKey: ['claim-decide', claimId],
    mutationFn: (payload: DecidePayload) => decideClaim(claimId, payload),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['claim-decision', claimId] })
    },
  })
}

export function useReverseClaimDecision(claimId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationKey: ['claim-decision-reverse', claimId],
    mutationFn: (note?: string | null) => reverseClaimDecision(claimId, note),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['claim-decision', claimId] })
    },
  })
}
