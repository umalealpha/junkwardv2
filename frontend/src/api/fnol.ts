import apiClient from './client'

/**
 * FNOL (First Notification of Loss) API client.
 *
 * FNOL is a lightweight claim-intake surface: a handler records a reported
 * loss immediately — even before full documents or a valid policy exist —
 * Graphite chases the missing documents by email, and once complete the FNOL
 * is converted into a full claim. This gives incomplete / awaiting-docs claims
 * a home in Graphite.
 *
 * The whole feature is gated behind the runtime `claims_fnol` integration
 * toggle (Admin > Integrations, default OFF). While OFF the calling UI hides
 * the nav + routes (see useClaimsFnol) and the backend endpoints 404 — treated
 * as "feature off" so nothing crashes if the flag is flipped mid-session.
 *
 * Contract (base apiClient already prefixes /api/v1):
 *   GET  claims/fnol?status=&search=&page=&per_page=  -> { data: Fnol[], meta }
 *   POST claims/fnol                                  -> { data: Fnol }
 *   GET  claims/fnol/{id}                             -> { data: Fnol }
 *   PUT  claims/fnol/{id}                             -> { data: Fnol }
 *   POST claims/fnol/{id}/convert                     -> { data: { fnol, claim_id, claim_number } }
 *   POST claims/fnol/{id}/close  { reason? }          -> { data: Fnol }
 */

export type FnolStatus = 'open' | 'converting' | 'converted' | 'closed'

export interface Fnol {
  id: number
  fnol_number: string
  claimant_name: string
  policy_number: string | null
  policy_id: number | null
  claim_type: string | null
  loss_date: string | null
  description: string
  contact_phone: string | null
  contact_email: string | null
  estimate_amount: number | null
  outstanding_docs: string[]
  status: FnolStatus
  reminder_count: number
  last_reminder_at: string | null
  converted_claim_id: number | null
  // ── Claims-Tracker "New Claim" Basic-Information fields (additive; populated
  //    by the unified tracker-style FNOL create form, gated by `claims_fnol`) ──
  channel?: string | null
  broker_name?: string | null
  claims_handler?: string | null
  plate_number?: string | null
  reserve_amount?: number | null
  claim_paid_amount?: number | null
  customer_type?: string | null
  customer_type_other?: string | null
  non_motor_sub_type?: string | null
  assessor?: string | null
  assessor_other?: string | null
  glass_supplier?: string | null
  glass_supplier_other?: string | null
  reinsurer?: string | null
  is_fac?: boolean
  comment_status?: string | null
  comment_sub_reason?: string | null
  /** Allocated Date — carried onto claims.claim_allocated_on at convert (claims-team v6). */
  claim_allocated_on?: string | null
  // Tracker stage-timeline captured at intake; applied to claim_tracker_workflow
  // on convert. Keys mirror ClaimStageTimelineService::EDITABLE_FIELDS.
  stage_data?: Record<string, string | null> | null
  created_at: string
  updated_at: string
}

export interface FnolFilters {
  status?: FnolStatus | ''
  search?: string
  page?: number
  per_page?: number
}

export interface FnolListResponse {
  data: Fnol[]
  meta: {
    total: number
    per_page: number
    current_page: number
    last_page: number
  }
}

/** Fields accepted by POST claims/fnol. Only claimant_name + description are required. */
export interface CreateFnolPayload {
  claimant_name: string
  description: string
  policy_number?: string | null
  policy_id?: number | null
  claim_type?: string | null
  loss_date?: string | null
  reported_date?: string | null
  claim_allocated_on?: string | null
  contact_phone?: string | null
  contact_email?: string | null
  estimate_amount?: number | null
  outstanding_docs?: string[]
  // ── Claims-Tracker "New Claim" Basic-Information fields (all optional;
  //    backend accepts them additively — omitting them is fully supported) ──
  channel?: string | null
  broker_name?: string | null
  claims_handler?: string | null
  plate_number?: string | null
  reserve_amount?: number | null
  claim_paid_amount?: number | null
  customer_type?: string | null
  customer_type_other?: string | null
  non_motor_sub_type?: string | null
  assessor?: string | null
  assessor_other?: string | null
  glass_supplier?: string | null
  glass_supplier_other?: string | null
  reinsurer?: string | null
  is_fac?: boolean
  comment_status?: string | null
  comment_sub_reason?: string | null
  /** Tracker stage-timeline map (keys = ClaimStageTimelineService EDITABLE_FIELDS). */
  stage_data?: Record<string, string | null> | null
}

/** Fields accepted by PUT claims/fnol/{id}. All optional (partial update). */
export type UpdateFnolPayload = Partial<CreateFnolPayload>

export interface ConvertFnolResult {
  fnol: Fnol
  claim_id: number
  claim_number: string
}

// ── Fetchers ────────────────────────────────────────────────────────────────

export async function fetchFnols(filters: FnolFilters = {}): Promise<FnolListResponse> {
  const { data } = await apiClient.get<FnolListResponse>('/claims/fnol', { params: filters })
  return data
}

export async function fetchFnol(id: number): Promise<Fnol> {
  const { data } = await apiClient.get<{ data: Fnol }>(`/claims/fnol/${id}`)
  return data.data
}

export async function createFnol(payload: CreateFnolPayload): Promise<Fnol> {
  const { data } = await apiClient.post<{ data: Fnol }>('/claims/fnol', payload)
  return data.data
}

export async function updateFnol(id: number, payload: UpdateFnolPayload): Promise<Fnol> {
  const { data } = await apiClient.put<{ data: Fnol }>(`/claims/fnol/${id}`, payload)
  return data.data
}

/**
 * Convert an FNOL into a full claim. May return 422 { message } if the FNOL
 * lacks a valid policy or loss_date — the caller surfaces that message to the
 * user (the error's response.data.message).
 */
export async function convertFnol(id: number): Promise<ConvertFnolResult> {
  const { data } = await apiClient.post<{ data: ConvertFnolResult }>(`/claims/fnol/${id}/convert`)
  return data.data
}

export async function closeFnol(id: number, reason?: string): Promise<Fnol> {
  const { data } = await apiClient.post<{ data: Fnol }>(`/claims/fnol/${id}/close`, reason ? { reason } : {})
  return data.data
}

// ── Status presentation ───────────────────────────────────────────────────────
export type FnolStatusTone = 'open' | 'converted' | 'closed'

/** Map an FNOL status to a chip label + token-based classes (light/dark safe). */
export function fnolStatusMeta(status: FnolStatus): { label: string; cls: string } {
  switch (status) {
    case 'open':
      return { label: 'Open', cls: 'bg-status-accent-bg text-status-accent-fg' }
    case 'converting':
      return { label: 'Converting…', cls: 'bg-status-warning-bg text-status-warning-fg' }
    case 'converted':
      return { label: 'Converted', cls: 'bg-status-success-bg text-status-success-fg' }
    case 'closed':
      return { label: 'Closed', cls: 'bg-surface-2 text-ink-muted' }
    default:
      return { label: status, cls: 'bg-surface-2 text-ink-muted' }
  }
}
