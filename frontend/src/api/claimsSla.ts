import apiClient from './client'

/**
 * Claims SLA + stage-timeline API client (Claims Tracker -> Graphite migration,
 * Phase 1). Backed by ClaimSlaController on the backend, all under /api/v1 and
 * auth:sanctum.
 *
 * The whole feature is gated behind the runtime `claims_sla` integration toggle
 * (Admin > Integrations, default OFF). While OFF every endpoint here 404s — the
 * calling UI checks the flag first (useClaimsSlaEnabled) and treats a 404 as
 * "feature off" so nothing crashes if the flag is flipped mid-session.
 *
 * READ-ONLY over live claim data except the timeline PATCH, which writes only to
 * the additive claim_tracker_workflow row (+ an audit row per field).
 */

// ── Status vocabulary ────────────────────────────────────────────────────
// met       — stage completed on/before its due date
// missed    — stage completed AFTER its due date (retrospective breach)
// breached  — stage still open and now past its due date
// due_soon  — stage open, within the amber warning window
// on_track  — stage open, comfortably before due
export type ClaimSlaStatus = 'on_track' | 'due_soon' | 'breached' | 'met' | 'missed'

/** Presentation metadata for a status chip — token classes + human label. */
export const CLAIM_SLA_STATUS_META: Record<ClaimSlaStatus, { label: string; cls: string }> = {
  met:      { label: 'Met',       cls: 'bg-status-success-bg text-status-success-fg' },
  on_track: { label: 'On track',  cls: 'bg-status-success-bg text-status-success-fg' },
  due_soon: { label: 'Due soon',  cls: 'bg-status-warning-bg text-status-warning-fg' },
  missed:   { label: 'Missed',    cls: 'bg-status-danger-bg text-status-danger-fg' },
  breached: { label: 'Breached',  cls: 'bg-status-danger-bg text-status-danger-fg' },
}

// ── Dashboard ──────────────────────────────────────────────────────────────
export interface ClaimSlaByStatus {
  on_track: number
  due_soon: number
  breached: number
  met: number
  missed: number
}

export interface ClaimSlaByClassRow {
  class: string
  label: string
  total: number
  breached: number
  completed: number
}

export interface ClaimSlaByStageRow {
  stage: string
  label: string
  on_track: number
  due_soon: number
  breached: number
  met: number
  missed: number
}

export interface ClaimSlaDashboard {
  summary: {
    total_claims: number
    breached: number
    by_status: ClaimSlaByStatus
  }
  by_class: ClaimSlaByClassRow[]
  by_stage: ClaimSlaByStageRow[]
  generated_at: string
}

export async function fetchClaimSlaDashboard(): Promise<ClaimSlaDashboard> {
  const { data } = await apiClient.get<{ data: ClaimSlaDashboard }>('/claims/sla/dashboard')
  return data.data
}

// ── Handler leaderboard ──────────────────────────────────────────────────
export interface ClaimSlaLeaderboardRow {
  handler_id: number | null
  handler_name: string
  total: number
  breached: number
  completed: number
  on_time: number
  on_time_pct: number
}

export async function fetchClaimSlaLeaderboard(): Promise<ClaimSlaLeaderboardRow[]> {
  const { data } = await apiClient.get<{ data: ClaimSlaLeaderboardRow[] }>('/claims/sla/leaderboard')
  return data.data ?? []
}

// ── Per-claim SLA evaluation ───────────────────────────────────────────────
export interface ClaimSlaStage {
  key: string
  label: string
  due_working_days: number
  due_date: string
  completed_date: string | null
  status: ClaimSlaStatus
  breached: boolean
  variance_working_days: number
}

export interface ClaimSlaEval {
  claim_id: number
  class: string
  class_label: string
  sub_type: string | null
  customer_type: string | null
  start_date: string
  total_working_days: number
  overall_due_date: string
  overall_status: ClaimSlaStatus
  breached: boolean
  completed: boolean
  stages: ClaimSlaStage[]
}

export async function fetchClaimSla(claimId: number): Promise<ClaimSlaEval> {
  const { data } = await apiClient.get<{ data: ClaimSlaEval }>(`/claims-v2/${claimId}/sla`)
  return data.data
}

// ── Stage timeline (read + edit) ───────────────────────────────────────────
export type ClaimStageFieldKind = 'date' | 'text' | 'comment' | 'select'

export interface ClaimStageField {
  key: string
  label: string
  kind: ClaimStageFieldKind
  /** For kind 'select' — static option list. Dynamic lists (e.g. assessors) are
   *  supplied by the rendering component; the current value is always preserved. */
  options?: string[]
}

export interface ClaimStageGroup {
  title: string
  fields: ClaimStageField[]
}

/**
 * Editable workflow fields grouped by stage. Mirrors
 * ClaimStageTimelineService::EDITABLE_FIELDS / DATE_FIELDS exactly — keep in
 * sync if the backend list changes. `kind` decides the input type and the
 * server-side max length (date | text ≤255 | comment ≤2000).
 */
export const CLAIM_STAGE_GROUPS: ClaimStageGroup[] = [
  {
    title: 'Classification',
    fields: [
      { key: 'customer_type', label: 'Customer type', kind: 'text' },
      { key: 'customer_type_other', label: 'Customer type (other)', kind: 'text' },
      { key: 'non_motor_sub_type', label: 'Non-motor sub-type', kind: 'text' },
    ],
  },
  {
    title: 'Stage 1 — Assessor allotment & file upload',
    fields: [
      { key: 'claim_docs_received', label: 'Claim docs received (SLA start)', kind: 'date' },
      { key: 'assessor_allotment_date', label: 'Assessor allotment date', kind: 'date' },
      // Dropdown of master assessors (claims-team v6). Options are loaded live by
      // ClaimSlaTab; any existing free-text value is preserved as a selectable option.
      { key: 'assessor_name', label: 'Assessor name', kind: 'select' },
      { key: 'file_uploaded_to_gt', label: 'File uploaded to GT', kind: 'date' },
      { key: 'gt_number', label: 'GT number', kind: 'text' },
      { key: 'distance', label: 'Distance', kind: 'text' },
      { key: 'stage1_comment', label: 'Stage 1 comment', kind: 'comment' },
    ],
  },
  {
    title: 'Stage 2 — Physical assessment',
    fields: [
      { key: 'panel_beater_name', label: 'Panel beater name', kind: 'text' },
      { key: 'panel_beater_other', label: 'Panel beater (other)', kind: 'text' },
      { key: 'physical_assessment', label: 'Physical assessment date', kind: 'date' },
      { key: 'physical_assessment_comment', label: 'Physical assessment comment', kind: 'comment' },
    ],
  },
  {
    title: 'Stage 3 — Quote request',
    fields: [
      { key: 'quote_request_date', label: 'Quote request date', kind: 'date' },
      { key: 'quote_request_comment', label: 'Quote request comment', kind: 'comment' },
      { key: 'under_warranty', label: 'Under warranty', kind: 'text' },
    ],
  },
  {
    title: 'Stage 4 — Quote finalisation / assessment report',
    fields: [
      { key: 'quote_finalisation', label: 'Quote finalisation date', kind: 'date' },
      { key: 'assessment_report_date', label: 'Assessment report date', kind: 'date' },
      { key: 'assessment_report_comment', label: 'Assessment report comment', kind: 'comment' },
    ],
  },
  {
    title: 'Stage 5 — Purchase order',
    fields: [
      { key: 'po_generation_date', label: 'PO generation date', kind: 'date' },
      { key: 'po_issue', label: 'PO issue', kind: 'text' },
      { key: 'po_issue_other', label: 'PO issue (other)', kind: 'text' },
      { key: 'po_issue_date', label: 'PO issue date', kind: 'date' },
    ],
  },
  {
    title: 'Stage 6 — Parts & job completion',
    fields: [
      { key: 'parts_eta', label: 'Parts ETA', kind: 'date' },
      { key: 'parts_delivery_date', label: 'Parts delivery date', kind: 'date' },
      { key: 'confirmation_date', label: 'Confirmation date', kind: 'date' },
      { key: 'mismatch_reported', label: 'Mismatch reported', kind: 'text' },
      { key: 'replacement_date', label: 'Replacement date', kind: 'date' },
      { key: 'job_end_date', label: 'Job end date', kind: 'date' },
      { key: 'job_end_status', label: 'Job end status', kind: 'text' },
    ],
  },
  {
    title: 'Non-motor / glass specifics',
    fields: [
      { key: 'non_motor_assessor', label: 'Non-motor assessor', kind: 'text' },
      { key: 'non_motor_assessor_other', label: 'Non-motor assessor (other)', kind: 'text' },
      { key: 'glass_supplier', label: 'Glass supplier', kind: 'text' },
      { key: 'glass_supplier_other', label: 'Glass supplier (other)', kind: 'text' },
    ],
  },
]

/** Flat list of every editable field key (order = display order). */
export const CLAIM_STAGE_FIELD_KEYS: string[] = CLAIM_STAGE_GROUPS.flatMap(g => g.fields.map(f => f.key))

export type ClaimStageFields = Record<string, string | null>

export interface ClaimStageTimeline {
  claim_id: number
  claim_number: string | null
  claim_type: string | null
  exists: boolean
  external_ref: string | null
  fields: ClaimStageFields
  updated_at: string | null
}

export async function fetchClaimSlaTimeline(claimId: number): Promise<ClaimStageTimeline> {
  const { data } = await apiClient.get<{ data: ClaimStageTimeline }>(`/claims-v2/${claimId}/sla-timeline`)
  return data.data
}

export type ClaimSlaTimelinePayload = Record<string, string | null>

export interface ClaimSlaTimelineUpdateResult {
  data: ClaimStageTimeline
  changed: number
  message: string
}

/** PATCH a subset of stage fields. Send only the fields the user changed. */
export async function updateClaimSlaTimeline(
  claimId: number,
  payload: ClaimSlaTimelinePayload,
): Promise<ClaimSlaTimelineUpdateResult> {
  const { data } = await apiClient.patch<ClaimSlaTimelineUpdateResult>(
    `/claims-v2/${claimId}/sla-timeline`,
    payload,
  )
  return data
}
