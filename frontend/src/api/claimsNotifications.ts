import apiClient from './client'

/**
 * Claims Notifications API client (Claims Tracker -> Graphite migration).
 * Backed by ClaimNotificationController, all under /api/v1 and auth:sanctum.
 *
 * The whole feature is gated behind the runtime `claims_notifications`
 * integration toggle (Admin > Integrations, default OFF). While OFF every
 * endpoint here 404s — the calling UI checks the flag first
 * (useClaimsNotificationsEnabled) and treats a 404 as "feature off" so nothing
 * crashes if the flag is flipped mid-session.
 *
 * READ-ONLY. This feature only logs notifications Graphite already sends and
 * shows them here — it never initiates a send. The `recipient` field is always
 * a MASKED destination (never raw contact PII) — masked server-side at write.
 */

export type NotifWindow = 'today' | '7d' | '30d' | '90d'

export const NOTIF_WINDOWS: { key: NotifWindow; label: string }[] = [
  { key: 'today', label: 'Today' },
  { key: '7d', label: '7 days' },
  { key: '30d', label: '30 days' },
  { key: '90d', label: '90 days' },
]

// ── Overview ────────────────────────────────────────────────────────────────
export type NotifMode = 'disabled' | 'pilot' | 'live'

export interface NotifOverview {
  window: NotifWindow
  mode: NotifMode
  enabled: boolean
  stats: {
    total: number
    delivered: number
    failed: number
    suppressed: number
    pending: number
    cost_units: number
  }
  by_channel: { sms: number; email: number }
  generated_at: string
}

export async function fetchNotifOverview(window: NotifWindow): Promise<NotifOverview> {
  const { data } = await apiClient.get<{ data: NotifOverview }>('/claims/notifications/overview', {
    params: { window },
  })
  return data.data
}

// ── By trigger ────────────────────────────────────────────────────────────────
export interface NotifByTriggerRow {
  trigger_key: string
  total: number
  delivered: number
  failed: number
  suppressed: number
  cost_units: number
}

export async function fetchNotifByTrigger(window: NotifWindow): Promise<NotifByTriggerRow[]> {
  const { data } = await apiClient.get<{ data: NotifByTriggerRow[] }>('/claims/notifications/by-trigger', {
    params: { window },
  })
  return data.data ?? []
}

// ── Daily volume ────────────────────────────────────────────────────────────
export interface NotifDailyVolumeRow {
  day: string
  total: number
  sms: number
  email: number
  failed: number
}

export async function fetchNotifDailyVolume(window: NotifWindow): Promise<NotifDailyVolumeRow[]> {
  const { data } = await apiClient.get<{ data: NotifDailyVolumeRow[] }>('/claims/notifications/daily-volume', {
    params: { window },
  })
  return data.data ?? []
}

// ── Send row (recent sends / recent failures / per-claim) ─────────────────────
export interface NotifRow {
  id: number
  claim_id: number | null
  claim_number: string | null
  channel: string
  provider: string | null
  trigger_key: string
  recipient: string | null // masked
  template_key: string | null
  provider_msg_id: string | null
  status: string
  reason: string | null
  cost_units: number | null
  delivered_at: string | null
  created_at: string | null
}

export async function fetchNotifRecent(window: NotifWindow): Promise<NotifRow[]> {
  const { data } = await apiClient.get<{ data: NotifRow[] }>('/claims/notifications/recent', {
    params: { window },
  })
  return data.data ?? []
}

export async function fetchNotifRecentFailures(window: NotifWindow): Promise<NotifRow[]> {
  const { data } = await apiClient.get<{ data: NotifRow[] }>('/claims/notifications/recent-failures', {
    params: { window },
  })
  return data.data ?? []
}

export async function fetchClaimNotifications(claimId: number): Promise<NotifRow[]> {
  const { data } = await apiClient.get<{ data: NotifRow[] }>(`/claims-v2/${claimId}/notifications`)
  return data.data ?? []
}

// ── Status presentation ───────────────────────────────────────────────────────
export type NotifStatusTone = 'good' | 'bad' | 'warn' | 'muted'

/**
 * Map a (normalised) status to a semantic tone + label for chips/text.
 * Falls back to muted for any unrecognised provider status.
 */
export function statusMeta(status: string): { label: string; tone: NotifStatusTone } {
  switch (status.toLowerCase()) {
    case 'delivered':
      return { label: 'Delivered', tone: 'good' }
    case 'sent':
      return { label: 'Sent', tone: 'good' }
    case 'pending':
      return { label: 'Pending', tone: 'warn' }
    case 'suppressed':
      return { label: 'Suppressed', tone: 'muted' }
    case 'skipped':
      return { label: 'Skipped', tone: 'muted' }
    case 'failed':
    case 'error':
    case 'curl_error':
    case 'undeliverable':
    case 'rejected':
      return { label: 'Failed', tone: 'bad' }
    default:
      return { label: status, tone: 'muted' }
  }
}
