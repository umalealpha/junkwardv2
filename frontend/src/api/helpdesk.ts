import apiClient from './client'
import type { ListMeta } from './groupPolicies'

/**
 * 2026-07 cutover: the helpdesk lives in Alpha Bridge now. All historical
 * Graphite tickets were migrated there; these native pages remain as a
 * READ-ONLY archive. This flag gates every write control (create, edit,
 * assign, status, comment, attachments) on the list/detail pages — flip to
 * false only if the cutover has to be rolled back. The backend module and
 * its routes are intentionally untouched.
 */
export const HELPDESK_ARCHIVED = true

// 'new' is the entry state for newly raised tickets. 'open' is kept only for
// legacy tickets created before the status workflow change. 'pending_customer'
// and 'pending_third_party' pause the SLA clock.
export type HelpDeskStatus =
  | 'new' | 'open' | 'in_progress'
  | 'pending_customer' | 'pending_third_party'
  | 'resolved' | 'closed' | 'reopened'

// Map 1:1 onto Alpha Bridge's Priority / TicketType (upper-cased on hand-off).
export type HelpDeskPriority = 'low' | 'medium' | 'high' | 'critical'
export type HelpDeskType = 'bug' | 'feature' | 'improvement' | 'task'

export const PRIORITY_OPTIONS: { value: HelpDeskPriority; label: string }[] = [
  { value: 'low', label: 'Low' },
  { value: 'medium', label: 'Medium' },
  { value: 'high', label: 'High' },
  { value: 'critical', label: 'Critical' },
]
export const TYPE_OPTIONS: { value: HelpDeskType; label: string }[] = [
  { value: 'bug', label: 'Bug' },
  { value: 'feature', label: 'Feature' },
  { value: 'improvement', label: 'Improvement' },
  { value: 'task', label: 'Task' },
]

export interface HelpDeskTicketSummary {
  id: number
  ticketRef: string
  title: string | null
  excerpt: string
  priority: HelpDeskPriority
  type: HelpDeskType
  reporterName: string | null
  assigneeId: number | null
  assigneeName: string | null
  status: HelpDeskStatus
  attachmentCount: number
  createdAt: string | null
  closedAt: string | null
}

export interface HelpDeskAttachment {
  slot: string
  index: number
  originalName: string
  mime: string | null
  size: number | null
}

export interface HelpDeskTicketDetail {
  id: number
  ticketRef: string
  title: string | null
  description: string
  priority: HelpDeskPriority
  type: HelpDeskType
  source: string
  externalRef: string | null
  reporterId: number
  reporterName: string | null
  reporterEmail: string | null
  assigneeId: number | null
  assigneeName: string | null
  status: HelpDeskStatus
  relatedTicketId: number | null
  relatedTicketRef: string | null
  relatedChildren: { id: number; ticketRef: string; status: HelpDeskStatus }[]
  canReopen: boolean
  closingSummary: string | null
  openedAt: string | null
  closedAt: string | null
  attachments: HelpDeskAttachment[]
  createdAt: string | null
  updatedAt: string | null
}

export interface HelpDeskFilters {
  status?: HelpDeskStatus
  priority?: HelpDeskPriority
  assignee_id?: number
  assignee_name?: string
  reporter_name?: string
  mine?: boolean
  search?: string
  per_page?: number
  page?: number
}

export interface HelpDeskListResponse { data: HelpDeskTicketSummary[]; meta: ListMeta }

export interface HelpDeskSummary {
  total: number
  by_status: Record<HelpDeskStatus, number>
  by_priority: Record<HelpDeskPriority, number>
  by_reporter: { name: string; count: number }[]
  by_assignee: { name: string | null; count: number }[]
}

export async function fetchHelpDeskTickets(filters: HelpDeskFilters = {}): Promise<HelpDeskListResponse> {
  const { data } = await apiClient.get<HelpDeskListResponse>('/help-desk-tickets', { params: filters })
  return data
}

export async function fetchHelpDeskSummary(filters: HelpDeskFilters = {}): Promise<HelpDeskSummary> {
  const { page, per_page, ...rest } = filters
  const { data } = await apiClient.get<{ data: HelpDeskSummary }>('/help-desk-tickets/summary', { params: rest })
  return data.data
}

export async function fetchHelpDeskTicket(id: number): Promise<HelpDeskTicketDetail> {
  const { data } = await apiClient.get<{ data: HelpDeskTicketDetail }>(`/help-desk-tickets/${id}`)
  return data.data
}

export interface CreateHelpDeskTicketPayload {
  title: string
  description: string
  priority?: HelpDeskPriority
  type?: HelpDeskType
  assignee_email?: string | null
  screenshot1?: File | null
  screenshot2?: File | null
  screenshot3?: File | null
  attachment?: File | null
}

export async function createHelpDeskTicket(payload: CreateHelpDeskTicketPayload): Promise<{ message: string; data: HelpDeskTicketDetail }> {
  const fd = new FormData()
  fd.append('title', payload.title)
  fd.append('description', payload.description)
  if (payload.priority) fd.append('priority', payload.priority)
  if (payload.type) fd.append('type', payload.type)
  if (payload.assignee_email) fd.append('assignee_email', payload.assignee_email)
  if (payload.screenshot1) fd.append('screenshot_1', payload.screenshot1)
  if (payload.screenshot2) fd.append('screenshot_2', payload.screenshot2)
  if (payload.screenshot3) fd.append('screenshot_3', payload.screenshot3)
  if (payload.attachment) fd.append('attachment', payload.attachment)

  const { data } = await apiClient.post('/help-desk-tickets', fd, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}

export async function assignHelpDeskTicket(id: number, assigneeEmail: string | null): Promise<HelpDeskTicketDetail> {
  const { data } = await apiClient.post<{ data: HelpDeskTicketDetail }>(`/help-desk-tickets/${id}/assign`, {
    assignee_email: assigneeEmail,
  })
  return data.data
}

export async function updateHelpDeskStatus(id: number, status: HelpDeskStatus): Promise<HelpDeskTicketDetail> {
  const { data } = await apiClient.post<{ data: HelpDeskTicketDetail }>(`/help-desk-tickets/${id}/status`, { status })
  return data.data
}

/**
 * Reopen a closed ticket. Allowed for the reporter (within the server-side
 * window) or an agent/admin; a short reason is required. All enforced server-side.
 */
export async function reopenHelpDeskTicket(id: number, reason: string): Promise<HelpDeskTicketDetail> {
  const { data } = await apiClient.post<{ data: HelpDeskTicketDetail }>(`/help-desk-tickets/${id}/reopen`, { reason })
  return data.data
}

/** Clone a ticket into a new, linked ("related") ticket. Returns the new ticket. */
export async function cloneHelpDeskTicket(id: number): Promise<HelpDeskTicketDetail> {
  const { data } = await apiClient.post<{ data: HelpDeskTicketDetail }>(`/help-desk-tickets/${id}/clone`)
  return data.data
}

/** Close a ticket — requires a ≤50-char summary and a proof screenshot. */
export async function closeHelpDeskTicket(id: number, summary: string, screenshot: File): Promise<HelpDeskTicketDetail> {
  const fd = new FormData()
  fd.append('closing_summary', summary)
  fd.append('closing_screenshot', screenshot)
  const { data } = await apiClient.post<{ data: HelpDeskTicketDetail }>(`/help-desk-tickets/${id}/close`, fd, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data.data
}

// ── Edit an existing ticket ───────────────────────────────────────────
export interface UpdateHelpDeskTicketPayload {
  title: string
  description: string
  priority?: HelpDeskPriority
  type?: HelpDeskType
}

export async function updateHelpDeskTicket(id: number, payload: UpdateHelpDeskTicketPayload): Promise<HelpDeskTicketDetail> {
  const { data } = await apiClient.post<{ data: HelpDeskTicketDetail }>(`/help-desk-tickets/${id}/update`, payload)
  return data.data
}

/** Add a screenshot/file to an existing ticket. `slot` is a display label. */
export async function addHelpDeskAttachment(id: number, file: File, slot = 'attachment'): Promise<HelpDeskTicketDetail> {
  const fd = new FormData()
  fd.append('file', file)
  fd.append('slot', slot)
  const { data } = await apiClient.post<{ data: HelpDeskTicketDetail }>(`/help-desk-tickets/${id}/attachments`, fd, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data.data
}

/** Delete an attachment by its index. */
export async function deleteHelpDeskAttachment(id: number, index: number): Promise<HelpDeskTicketDetail> {
  const { data } = await apiClient.delete<{ data: HelpDeskTicketDetail }>(`/help-desk-tickets/${id}/attachments/${index}`)
  return data.data
}

/** Download an attachment (carries the Bearer token via apiClient). */
export async function downloadHelpDeskAttachment(id: number, att: HelpDeskAttachment): Promise<void> {
  const res = await apiClient.get(`/help-desk-tickets/${id}/attachments/${att.index}`, {
    responseType: 'blob',
  })
  const url = window.URL.createObjectURL(res.data as Blob)
  const a = document.createElement('a')
  a.href = url
  a.download = att.originalName || 'attachment'
  document.body.appendChild(a)
  a.click()
  a.remove()
  window.URL.revokeObjectURL(url)
}

/** Fetch an attachment as a displayable object URL — for in-app image viewing
 *  (the endpoint is auth-gated, so an <img src> can't hit it directly). */
export async function fetchHelpDeskAttachmentObjectURL(id: number, att: HelpDeskAttachment): Promise<string> {
  const res = await apiClient.get(`/help-desk-tickets/${id}/attachments/${att.index}`, {
    responseType: 'blob',
  })
  return window.URL.createObjectURL(res.data as Blob)
}

// ── Audit trail ────────────────────────────────────────────────────────
export interface HelpDeskAuditEntry {
  id: number
  event: string
  description: string
  actor: string | null
  createdAt: string | null
}

export async function fetchHelpDeskAudit(id: number): Promise<HelpDeskAuditEntry[]> {
  const { data } = await apiClient.get<{ data: HelpDeskAuditEntry[] }>(`/help-desk-tickets/${id}/audit`)
  return data.data
}

// ── Discussion comments ────────────────────────────────────────────────
// User-entered collaboration notes, kept separate from the audit trail.
export interface HelpDeskComment {
  id: number
  userId: number | null
  userName: string | null
  comment: string
  createdAt: string | null
}

/** Discussion comments for a ticket, chronological (oldest first). */
export async function fetchHelpDeskComments(id: number): Promise<HelpDeskComment[]> {
  const { data } = await apiClient.get<{ data: HelpDeskComment[] }>(`/help-desk-tickets/${id}/comments`)
  return data.data
}

/** Add a comment. The author + timestamp are captured server-side from the session. */
export async function addHelpDeskComment(id: number, comment: string): Promise<HelpDeskComment> {
  const { data } = await apiClient.post<{ data: HelpDeskComment }>(`/help-desk-tickets/${id}/comments`, { comment })
  return data.data
}

// ── SLA ────────────────────────────────────────────────────────────────
export type SlaRagStatus = 'green' | 'amber' | 'red' | 'met' | 'met_late'

export interface HelpDeskSlaLeg {
  dueAt: string | null
  metAt: string | null
  breached: boolean
  targetMinutes: number
  consumedMinutes: number
  consumedPct: number
  remainingMinutes: number | null
  status: SlaRagStatus
}

export interface HelpDeskSlaInfo {
  paused: boolean
  priority: string
  response: HelpDeskSlaLeg
  resolution: HelpDeskSlaLeg
}

/** Live SLA panel data for a ticket. Returns null when the ticket has no SLA. */
export async function fetchHelpDeskSla(id: number): Promise<HelpDeskSlaInfo | null> {
  const { data } = await apiClient.get<{ data: HelpDeskSlaInfo | null }>(`/help-desk-tickets/${id}/sla`)
  return data.data
}

// ── SLA dashboard (management) ──────────────────────────────────────────
export interface SlaDashboard {
  summary: {
    total: number
    within_sla: number
    response_breached: number
    resolution_breached: number
    open_breaches: number
    closed_breaches: number
    compliance_pct: number | null
  }
  aging: Record<string, number>
  priority: Record<string, { open: number; breached: number; resolved: number }>
  assignees: {
    assignee: string
    total: number
    avg_response_min: number | null
    avg_resolution_min: number | null
    breaches: number
    compliance_pct: number | null
  }[]
  heatmap: {
    id: number
    ticketRef: string
    priority: string
    assignee: string
    status: HelpDeskStatus
    flags: string[]
    severity: number
  }[]
  generated_at: string
}

export async function fetchSlaDashboard(): Promise<SlaDashboard> {
  const { data } = await apiClient.get<{ data: SlaDashboard }>('/help-desk/sla/dashboard')
  return data.data
}

export type SlaReportType = 'compliance' | 'breach' | 'assignee' | 'monthly'

/** Download an SLA report (xlsx/csv). Streams via apiClient so the Bearer token is sent. */
export async function downloadSlaReport(type: SlaReportType, format: 'xlsx' | 'csv' = 'xlsx'): Promise<void> {
  const res = await apiClient.get(`/help-desk/sla/reports/${type}`, {
    params: { format },
    responseType: 'blob',
  })
  const url = window.URL.createObjectURL(res.data as Blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `${type}-sla-report.${format}`
  document.body.appendChild(a)
  a.click()
  a.remove()
  window.URL.revokeObjectURL(url)
}

export const ASSIGNEE_SUGGESTIONS = ['developers@theriskco.com']
