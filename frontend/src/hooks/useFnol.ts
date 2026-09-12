import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useIntegrations } from './useIntegrations'
import { getStoredRoles } from '../api/auth'
import {
  fetchFnols,
  fetchFnol,
  createFnol,
  updateFnol,
  convertFnol,
  closeFnol,
  type FnolFilters,
  type CreateFnolPayload,
  type UpdateFnolPayload,
} from '../api/fnol'

/**
 * Roles that may open the FNOL (First Notification of Loss) intake surface.
 * Mirrors the claims-handling role set used by the other Claims screens
 * (create / analytics) — claims team, claims managers, and admins.
 */
export const CLAIMS_FNOL_ROLES = ['Claims Team', 'Claims Manager', 'Admin', 'admin', 'Super Admin']

/**
 * Whether the `claims_fnol` runtime flag is ON. Read from the (auth-only,
 * cheap, cached) integrations list so nav / routes can hide the feature
 * entirely while it ships dark. Any authenticated user may read the flag state.
 */
export function useClaimsFnolEnabled(): boolean {
  const { data } = useIntegrations()
  return !!data?.data?.find((i) => i.integration === 'claims_fnol')?.enabled
}

/** True if the current user holds ANY of the given role names. */
function hasAnyRole(roles: string[]): boolean {
  const mine = getStoredRoles()
  return roles.some((r) => mine.includes(r))
}

/** May the user open the FNOL intake surface (flag ON + claims role)? */
export function useCanSeeClaimsFnol(): boolean {
  const enabled = useClaimsFnolEnabled()
  return enabled && hasAnyRole(CLAIMS_FNOL_ROLES)
}

// ── Queries ─────────────────────────────────────────────────────────────
export function useFnols(filters: FnolFilters, enabled = true) {
  return useQuery({
    queryKey: ['fnols', filters],
    queryFn: () => fetchFnols(filters),
    enabled,
    staleTime: 30 * 1000,
    retry: 0,
  })
}

export function useFnol(id: number, enabled = true) {
  return useQuery({
    queryKey: ['fnol', id],
    queryFn: () => fetchFnol(id),
    enabled: enabled && !!id,
    staleTime: 30 * 1000,
    retry: 0,
  })
}

// ── Mutations ───────────────────────────────────────────────────────────
export function useCreateFnol() {
  const qc = useQueryClient()
  return useMutation({
    mutationKey: ['fnol-create'],
    mutationFn: (payload: CreateFnolPayload) => createFnol(payload),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['fnols'] })
    },
  })
}

export function useUpdateFnol(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationKey: ['fnol-update', id],
    mutationFn: (payload: UpdateFnolPayload) => updateFnol(id, payload),
    onSuccess: (fnol) => {
      qc.setQueryData(['fnol', id], fnol)
      qc.invalidateQueries({ queryKey: ['fnols'] })
    },
  })
}

export function useConvertFnol(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationKey: ['fnol-convert', id],
    mutationFn: () => convertFnol(id),
    onSuccess: (res) => {
      qc.setQueryData(['fnol', id], res.fnol)
      qc.invalidateQueries({ queryKey: ['fnols'] })
      // A new claim now exists — let the claims list pick it up.
      qc.invalidateQueries({ queryKey: ['claims'] })
    },
  })
}

export function useCloseFnol(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationKey: ['fnol-close', id],
    mutationFn: (reason?: string) => closeFnol(id, reason),
    onSuccess: (fnol) => {
      qc.setQueryData(['fnol', id], fnol)
      qc.invalidateQueries({ queryKey: ['fnols'] })
    },
  })
}
