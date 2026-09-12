import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useIntegrations } from './useIntegrations'
import { getStoredRoles } from '../api/auth'
import {
  fetchBackdateSettings,
  saveBackdateSettings,
  fetchBackdateGrants,
  fetchActiveBackdateGrants,
  createBackdateGrant,
  revokeBackdateGrant,
  fetchBackdateEvents,
  fetchBackdateRequests,
  submitBackdateRequest,
  decideBackdateRequest,
  type BackdateSettingsPayload,
  type CreateGrantPayload,
  type SubmitRequestPayload,
} from '../api/claimsBackdate'

/**
 * Roles that MANAGE backdate governance (grants, settings, decide). Mirrors
 * config('claims_backdate.roles.manage') / BackdateGovernanceService::canManage.
 * The admin Backdate Control screen is a PREVIEW surface for these roles — it is
 * visible regardless of the flag so admins can pre-configure.
 */
export const BACKDATE_MANAGE_ROLES = ['Super Admin', 'Admin', 'admin', 'developer']

/** Roles that may REQUEST a backdate window. Mirrors roles.request. */
export const BACKDATE_REQUEST_ROLES = ['Claims Manager']

/** True if the current user holds ANY of the given role names. */
function hasAnyRole(roles: string[]): boolean {
  const mine = getStoredRoles()
  return roles.some((r) => mine.includes(r))
}

/** Whether the `claims_backdate_governance` runtime flag (enforcement) is ON. */
export function useBackdateEnabled(): boolean {
  const { data } = useIntegrations()
  return !!data?.data?.find((i) => i.integration === 'claims_backdate_governance')?.enabled
}

/** May the current user open + manage the Backdate Control screen (preview always)? */
export function useCanManageBackdate(): boolean {
  return hasAnyRole(BACKDATE_MANAGE_ROLES)
}

/** May the current user submit a backdate request? (Only meaningful when the flag is on.) */
export function useCanRequestBackdate(): boolean {
  const enabled = useBackdateEnabled()
  return enabled && (hasAnyRole(BACKDATE_REQUEST_ROLES) || hasAnyRole(BACKDATE_MANAGE_ROLES))
}

// ── Queries ─────────────────────────────────────────────────────────────
export function useBackdateSettings(enabled = true) {
  return useQuery({
    queryKey: ['backdate-settings'],
    queryFn: fetchBackdateSettings,
    enabled,
    staleTime: 30 * 1000,
    retry: 0,
  })
}

export function useBackdateGrants(enabled = true) {
  return useQuery({
    queryKey: ['backdate-grants'],
    queryFn: () => fetchBackdateGrants(),
    enabled,
    staleTime: 20 * 1000,
    retry: 0,
  })
}

export function useActiveBackdateGrants(enabled = true) {
  return useQuery({
    queryKey: ['backdate-grants-active'],
    queryFn: fetchActiveBackdateGrants,
    enabled,
    staleTime: 20 * 1000,
    retry: 0,
  })
}

export function useBackdateEvents(enabled = true) {
  return useQuery({
    queryKey: ['backdate-events'],
    queryFn: () => fetchBackdateEvents(),
    enabled,
    staleTime: 20 * 1000,
    retry: 0,
  })
}

export function useBackdateRequests(opts?: { status?: 'pending' }, enabled = true) {
  return useQuery({
    queryKey: ['backdate-requests', opts?.status ?? 'all'],
    queryFn: () => fetchBackdateRequests(opts),
    enabled,
    staleTime: 15 * 1000,
    retry: 0,
  })
}

// ── Mutations ────────────────────────────────────────────────────────────
export function useSaveBackdateSettings() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: BackdateSettingsPayload) => saveBackdateSettings(payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['backdate-settings'] }),
  })
}

export function useCreateBackdateGrant() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreateGrantPayload) => createBackdateGrant(payload),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['backdate-grants'] })
      qc.invalidateQueries({ queryKey: ['backdate-grants-active'] })
    },
  })
}

export function useRevokeBackdateGrant() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => revokeBackdateGrant(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['backdate-grants'] })
      qc.invalidateQueries({ queryKey: ['backdate-grants-active'] })
    },
  })
}

export function useSubmitBackdateRequest() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: SubmitRequestPayload) => submitBackdateRequest(payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['backdate-requests'] }),
  })
}

export function useDecideBackdateRequest() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, action, note }: { id: number; action: 'approve' | 'deny'; note?: string }) =>
      decideBackdateRequest(id, action, note),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['backdate-requests'] })
      qc.invalidateQueries({ queryKey: ['backdate-grants'] })
      qc.invalidateQueries({ queryKey: ['backdate-grants-active'] })
    },
  })
}
