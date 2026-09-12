import apiClient from './client'

/**
 * Claims backdate-governance API client (Claims Tracker -> Graphite port).
 * Backed by BackdateControlController, all under /api/v1 and auth:sanctum.
 *
 * The management surface (settings/grants/events/decide) is a PREVIEW surface:
 * it works for manage-role users regardless of the `claims_backdate_governance`
 * flag, so admins can pre-configure before turning enforcement on. Only the
 * enforcement hook (server-side, on claim stage-date edits), the alerts, and
 * request submission engage when the flag is ON. `settings.enabled` reports the
 * enforcement state so the UI can show a clear preview banner.
 */

// ── Settings ────────────────────────────────────────────────────────────────
export interface BackdateSettings {
  enabled: boolean            // enforcement state (false = preview/dark)
  integration_key: string
  max_days_back: number
  fy_start: string            // YYYY-MM-DD
  today: string               // YYYY-MM-DD
  teams_webhook: string
  alert_recipients: string
  max_days_back_min: number
  max_days_back_max: number
}

export async function fetchBackdateSettings(): Promise<BackdateSettings> {
  const { data } = await apiClient.get<BackdateSettings>('/claims/backdate/settings')
  return data
}

export interface BackdateSettingsPayload {
  max_days_back?: number
  teams_webhook?: string | null
  alert_recipients?: string | null
}

export async function saveBackdateSettings(payload: BackdateSettingsPayload): Promise<{ success: boolean; max_days_back: number }> {
  const { data } = await apiClient.post('/claims/backdate/settings', payload)
  return data
}

// ── Grants ────────────────────────────────────────────────────────────────
export interface BackdateGrant {
  id: number
  target_user_id: string
  target_label: string
  granted_by: string
  reason: string | null
  granted_at: string | null
  expires_at: string | null
  revoked_at: string | null
  revoked_by: string | null
  active: boolean
}

export async function fetchBackdateGrants(limit = 100): Promise<BackdateGrant[]> {
  const { data } = await apiClient.get<{ data: BackdateGrant[] }>('/claims/backdate/grants', { params: { limit } })
  return data.data ?? []
}

export async function fetchActiveBackdateGrants(): Promise<BackdateGrant[]> {
  const { data } = await apiClient.get<{ data: BackdateGrant[] }>('/claims/backdate/grants/active')
  return data.data ?? []
}

export interface CreateGrantPayload {
  target_user_id: string       // user id (string) OR 'ALL_CLAIMS_MANAGERS'
  duration_days?: number       // 1-7
  duration_hours?: number      // 1-168
  reason: string               // >= 10 chars
}

export async function createBackdateGrant(payload: CreateGrantPayload): Promise<{ success: boolean; data: BackdateGrant }> {
  const { data } = await apiClient.post('/claims/backdate/grants', payload)
  return data
}

export async function revokeBackdateGrant(id: number): Promise<{ success: boolean }> {
  const { data } = await apiClient.post(`/claims/backdate/grants/${id}/revoke`)
  return data
}

// ── Events ────────────────────────────────────────────────────────────────
export interface BackdateChange {
  field: string
  old: string
  new: string
}

export interface BackdateEvent {
  id: number
  grant_id: number | null
  claim_id: number
  claim_number: string
  username: string
  user_role: string
  changes: BackdateChange[]
  created_at: string | null
}

export async function fetchBackdateEvents(limit = 50): Promise<BackdateEvent[]> {
  const { data } = await apiClient.get<{ data: BackdateEvent[] }>('/claims/backdate/events', { params: { limit } })
  return data.data ?? []
}

// ── Requests ────────────────────────────────────────────────────────────────
export type BackdateRequestStatus = 'pending' | 'approved' | 'denied' | 'expired'

export interface BackdateRequest {
  id: number
  requester_id: string
  requester_username: string
  requester_name: string
  requester_role: string
  claim_ids: string[]
  claim_numbers: string | null
  reason: string | null
  duration_hours: number
  urgency: 'normal' | 'urgent'
  status: BackdateRequestStatus
  created_at: string | null
  decided_at: string | null
  decided_by: string | null
  decision_note: string | null
  grant_id: number | null
}

export async function fetchBackdateRequests(opts?: { status?: 'pending'; limit?: number }): Promise<BackdateRequest[]> {
  const { data } = await apiClient.get<{ data: BackdateRequest[] }>('/claims/backdate/requests', { params: opts })
  return data.data ?? []
}

export interface SubmitRequestPayload {
  claim_ids: Array<string | number>
  reason: string
  duration_hours: number
  urgency?: 'normal' | 'urgent'
}

export async function submitBackdateRequest(payload: SubmitRequestPayload): Promise<{ success: boolean; data: BackdateRequest }> {
  const { data } = await apiClient.post('/claims/backdate/requests', payload)
  return data
}

export async function decideBackdateRequest(
  id: number,
  action: 'approve' | 'deny',
  note?: string,
): Promise<{ success: boolean; status: string; grant_id?: number }> {
  const { data } = await apiClient.post(`/claims/backdate/requests/${id}/decide`, { action, note })
  return data
}
