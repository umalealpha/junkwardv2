import { useQuery } from '@tanstack/react-query'
import { useIntegrations } from './useIntegrations'
import { getStoredRoles } from '../api/auth'
import {
  fetchNotifOverview,
  fetchNotifByTrigger,
  fetchNotifDailyVolume,
  fetchNotifRecent,
  fetchNotifRecentFailures,
  fetchClaimNotifications,
  type NotifWindow,
} from '../api/claimsNotifications'

/**
 * Roles that may open the Claims Notifications dashboard. Mirrors
 * ClaimNotificationController::allowedRoles() (Admin / Super Admin manager +
 * Claims Manager read).
 */
export const CLAIMS_NOTIF_ROLES = ['Admin', 'admin', 'Super Admin', 'Claims Manager']

/**
 * Whether the `claims_notifications` runtime flag is ON. Read from the
 * (auth-only, cheap, cached) integrations list so nav/tabs can hide the feature
 * entirely while it ships dark.
 */
export function useClaimsNotificationsEnabled(): boolean {
  const { data } = useIntegrations()
  return !!data?.data?.find((i) => i.integration === 'claims_notifications')?.enabled
}

/** True if the current user holds ANY of the given role names. */
function hasAnyRole(roles: string[]): boolean {
  const mine = getStoredRoles()
  return roles.some((r) => mine.includes(r))
}

/** May the user open the Claims Notifications dashboard? */
export function useCanSeeClaimsNotifications(): boolean {
  const enabled = useClaimsNotificationsEnabled()
  return enabled && hasAnyRole(CLAIMS_NOTIF_ROLES)
}

// ── Queries (all window-scoped) ───────────────────────────────────────────────
const COMMON = { staleTime: 30 * 1000, refetchInterval: 60 * 1000, retry: 0 } as const

export function useNotifOverview(window: NotifWindow, enabled = true) {
  return useQuery({
    queryKey: ['claims-notif-overview', window],
    queryFn: () => fetchNotifOverview(window),
    enabled,
    ...COMMON,
  })
}

export function useNotifByTrigger(window: NotifWindow, enabled = true) {
  return useQuery({
    queryKey: ['claims-notif-by-trigger', window],
    queryFn: () => fetchNotifByTrigger(window),
    enabled,
    ...COMMON,
  })
}

export function useNotifDailyVolume(window: NotifWindow, enabled = true) {
  return useQuery({
    queryKey: ['claims-notif-daily-volume', window],
    queryFn: () => fetchNotifDailyVolume(window),
    enabled,
    ...COMMON,
  })
}

export function useNotifRecent(window: NotifWindow, enabled = true) {
  return useQuery({
    queryKey: ['claims-notif-recent', window],
    queryFn: () => fetchNotifRecent(window),
    enabled,
    ...COMMON,
  })
}

export function useNotifRecentFailures(window: NotifWindow, enabled = true) {
  return useQuery({
    queryKey: ['claims-notif-recent-failures', window],
    queryFn: () => fetchNotifRecentFailures(window),
    enabled,
    ...COMMON,
  })
}

/** Per-claim notification history (for a claim detail tab). */
export function useClaimNotifications(claimId: number, enabled = true) {
  return useQuery({
    queryKey: ['claim-notifications', claimId],
    queryFn: () => fetchClaimNotifications(claimId),
    enabled: enabled && !!claimId,
    staleTime: 30 * 1000,
    retry: 0,
  })
}
