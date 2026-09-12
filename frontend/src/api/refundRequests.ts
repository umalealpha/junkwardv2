import apiClient from './client'

/**
 * Customer Refund Engine — the SOP workflow entity (refund_requests).
 * Distinct from api/refunds.ts (the legacy DPO gateway refund tool):
 * this engine owns the Omni customer-refund lifecycle
 * (intake → review → approve → Omni pays via FNB → posted to policy).
 */

export type RefundArea = 'mis' | 'domestic' | 'commercial'

export type CollectionMethod = 'DPO' | 'RealPay' | 'VCS' | 'PM8' | 'CASH' | 'N-GENIUS'

export type RefundStatus =
  | 'draft' | 'submitted' | 'under_review'
  | 'approved' | 'approval_pending_2' | 'cfo_pending' | 'cfo_approved'
  | 'rejected' | 'escalated'
  | 'handed_off' | 'paid' | 'posted'
  /** Finance already paid the client outside Graphite — terminal, never sent to Omni. */
  | 'settled_manual'

export type RefundDocType = 'bank_statement' | 'bank_confirmation' | 'affidavit' | 'other'

export interface RefundRequestDocument {
  id: number
  refund_request_id: number
  doc_type: RefundDocType
  original_name: string | null
  mime: string | null
  size_bytes: number | null
  uploaded_by: number | null
  created_at: string
}

export interface RefundRequestEvent {
  id: number
  refund_request_id: number
  from_status: string | null
  to_status: string | null
  action: string
  actor_id: number | null
  actor_name: string | null
  note: string | null
  meta: Record<string, unknown> | null
  created_at: string
}

/** Reason-code taxonomy (mirrors RefundRequest model). Return-premium codes
 *  queue a Credit Note for Finance review when the refund is paid. */
export const RETURN_PREMIUM_REASON_CODES: Record<string, string> = {
  cooling_off: 'Cooling-off cancellation',
  cancellation: 'Policy cancellation',
  over_insurance: 'Over-insurance',
  duplicate_cover: 'Duplicate cover',
}
export const PLAIN_REASON_CODES: Record<string, string> = {
  overpayment: 'Overpayment',
  double_debit: 'Double debit',
  goodwill: 'Goodwill / service recovery',
  other: 'Other (cash refund only)',
}

export interface AssignableUser {
  id: number
  name: string | null
  email: string
}

export interface RefundAccountingEntry {
  id: number
  refund_request_id: number
  graphite_ref: string
  policy_id: number | null
  policy_number: string
  customer_id: number | null
  area: RefundArea
  reason_code: string | null
  entry_type: string
  refund_amount: number
  earned_premium: number | null
  unearned_premium: number | null
  effective_date: string | null
  end_date: string | null
  status: 'pending_review' | 'posted' | 'dismissed'
  credit_note_no: string | null
  posted_by: number | null
  posted_at: string | null
  dismissed_by: number | null
  dismissed_at: string | null
  dismiss_reason: string | null
  refund_request?: Pick<RefundRequest, 'id' | 'graphite_ref' | 'status' | 'customer_name' | 'reason' | 'omni_paid_at'>
  created_at: string
}

export interface RefundRequest {
  id: number
  graphite_ref: string
  area: RefundArea
  policy_number: string
  policy_id: number | null
  product_name: string | null
  customer_id: number | null
  customer_name: string | null
  agent_name: string | null
  refund_amount: number
  currency: string
  collection_method: CollectionMethod | null
  reason: string | null
  reason_code: string | null
  bank_name: string | null
  branch_code: string | null
  branch_name: string | null
  account_last4: string | null
  vehicle_not_client: boolean
  bank_account_confirmed: boolean
  after_cutoff: boolean
  ai_greenlight: boolean
  ai_evidence: Record<string, unknown> | null
  fraud_flags: { severity: 'CRITICAL' | 'HIGH' | 'MEDIUM' | 'LOW'; code: string; detail: string }[] | null
  fraud_score: number
  fraud_reviewed_at: string | null
  status: RefundStatus
  created_by: number | null
  submitted_at: string | null
  reviewed_by: number | null
  reviewed_at: string | null
  review_comment: string | null
  approved_by: number | null
  approved_at: string | null
  cfo_approved_by: number | null
  cfo_approved_at: string | null
  rejected_by: number | null
  rejected_at: string | null
  rejected_reason: string | null
  escalated_by: number | null
  escalated_at: string | null
  escalated_reason: string | null
  assigned_to: number | null
  assigned_by: number | null
  assigned_at: string | null
  payment_refund_id: number | null
  omni_status: 'not_sent' | 'sent' | 'paid' | 'failed'
  omni_paid_ref: string | null
  omni_paid_at: string | null
  handed_off_at: string | null
  /** Date Finance paid the client by hand, when supplied. */
  manual_paid_at: string | null
  /** Bank reference for that manual payment, when known. */
  manual_paid_ref: string | null
  /** Who asserted the payment was made outside Graphite. */
  manual_paid_by: number | null
  portal_flag: boolean
  documents?: RefundRequestDocument[]
  events?: RefundRequestEvent[]
  documents_count?: number
  created_at: string
  updated_at: string
  deleted_at?: string | null
}

export interface RefundListParams {
  area?: RefundArea
  status?: RefundStatus | string
  policy_number?: string
  graphite_ref?: string
  customer_name?: string
  amount_min?: number | string
  amount_max?: number | string
  date_from?: string
  date_to?: string
  mine?: boolean
  flagged?: boolean
  /** 'only' = the Deleted tab (soft-deleted rows); 'with' = live + deleted. CFO/Super Admin only server-side. */
  trashed?: 'only' | 'with'
  page?: number
  per_page?: number
}

export interface RefundMetrics {
  areas: RefundArea[]
  status_counts: Record<string, { count: number; value: number }>
  today: { count: number; value: number }
  series: { d: string; c: number; v: number }[]
}

export interface RefundCreatePayload {
  area: RefundArea
  policy_number: string
  refund_amount: number
  product_name?: string
  customer_name?: string
  agent_name?: string
  reason?: string
  reason_code?: string
  collection_method?: CollectionMethod | ''
  bank_name?: string
  branch_code?: string
  branch_name?: string
  account_number?: string
  vehicle_not_client?: boolean
}

// ─── Reads ────────────────────────────────────────────────────────────────────

export async function listRefundRequests(params: RefundListParams = {}) {
  const { data } = await apiClient.get('/refund-requests', { params })
  return data as {
    data: RefundRequest[]
    meta: { current_page: number; last_page: number; per_page: number; total: number }
  }
}

export async function getRefundRequest(id: number) {
  const { data } = await apiClient.get(`/refund-requests/${id}`)
  return data.data as RefundRequest
}

export async function getRefundMetrics() {
  const { data } = await apiClient.get('/refund-requests/summary')
  return data.data as RefundMetrics
}

/** Filterable CSV export — streamed through the API so the Bearer attaches. */
export async function exportRefundRequestsCsv(params: RefundListParams = {}) {
  const res = await apiClient.get('/refund-requests/export', { params, responseType: 'blob' })
  const dispo: string = res.headers['content-disposition'] || ''
  const m = dispo.match(/filename="?([^";]+)"?/)
  const name = m ? m[1] : `refund-requests-${Date.now()}.csv`
  const url = URL.createObjectURL(res.data)
  const a = document.createElement('a')
  a.href = url
  a.download = name
  a.click()
  URL.revokeObjectURL(url)
}

export async function downloadRefundDocument(requestId: number, doc: RefundRequestDocument) {
  const res = await apiClient.get(
    `/refund-requests/${requestId}/documents/${doc.id}/download`,
    { responseType: 'blob' }
  )
  const url = URL.createObjectURL(res.data)
  const a = document.createElement('a')
  a.href = url
  a.download = doc.original_name || `document-${doc.id}`
  a.click()
  URL.revokeObjectURL(url)
}

// ─── Creator writes ───────────────────────────────────────────────────────────

export async function createRefundRequest(payload: RefundCreatePayload) {
  const { data } = await apiClient.post('/refund-requests', payload)
  return data.data as RefundRequest
}

export async function updateRefundRequest(id: number, payload: Partial<RefundCreatePayload>) {
  const { area: _drop, ...rest } = payload
  const { data } = await apiClient.put(`/refund-requests/${id}`, rest)
  return data.data as RefundRequest
}

export async function uploadRefundDocument(id: number, file: File, docType: RefundDocType) {
  const fd = new FormData()
  fd.append('file', file)
  fd.append('doc_type', docType)
  const { data } = await apiClient.post(`/refund-requests/${id}/documents`, fd, {
    headers: { 'Content-Type': 'multipart/form-data' },
    timeout: 60000,
  })
  return data.data as RefundRequestDocument
}

/** Return a rejected refund to draft so the intaker can correct it. */
export async function resetRefundRequestToDraft(id: number) {
  const { data } = await apiClient.post(`/refund-requests/${id}/reset-to-draft`)
  return data.data as RefundRequest
}

export async function submitRefundRequest(id: number) {
  const { data } = await apiClient.post(`/refund-requests/${id}/submit`)
  return data.data as RefundRequest
}

// ─── Reviewer / Administrator / CFO writes ────────────────────────────────────

export async function reviewRefundRequest(id: number, comment = '') {
  const { data } = await apiClient.post(`/refund-requests/${id}/review`, { comment })
  return data.data as RefundRequest
}

export async function approveRefundRequest(id: number, bankAccountConfirmed: boolean, reason: string) {
  const { data } = await apiClient.post(`/refund-requests/${id}/approve`, {
    bank_account_confirmed: bankAccountConfirmed,
    reason,
  })
  return data.data as RefundRequest
}

/** Full bank account for verification — server logs every reveal (Data Protection Act). */
export async function revealAccount(id: number) {
  const { data } = await apiClient.get(`/refund-requests/${id}/account`)
  return data.data as { account_number: string; account_last4: string }
}

export async function rejectRefundRequest(id: number, reason: string, missingDocs: string[] = []) {
  const { data } = await apiClient.post(`/refund-requests/${id}/reject`, {
    reason,
    missing_docs: missingDocs,
  })
  return data.data as RefundRequest
}

export async function escalateRefundRequest(id: number, reason: string) {
  const { data } = await apiClient.post(`/refund-requests/${id}/escalate`, { reason })
  return data.data as RefundRequest
}

export async function cfoApproveRefundRequest(
  id: number, bankAccountConfirmed = false, reason = '', overrideFraud = false
) {
  const { data } = await apiClient.post(`/refund-requests/${id}/cfo-approve`, {
    bank_account_confirmed: bankAccountConfirmed,
    reason,
    override_fraud: overrideFraud,
  })
  return data.data as RefundRequest
}

/** Soft delete a single request (CFO/Super Admin only; refused once money moved). */
export async function deleteRefundRequest(id: number, reason: string) {
  const { data } = await apiClient.delete(`/refund-requests/${id}`, { data: { reason } })
  return data.data as RefundRequest
}

/** Restore a soft-deleted request (CFO/Super Admin only). Reverses a delete. */
export async function restoreRefundRequest(id: number, reason = '') {
  const { data } = await apiClient.post(`/refund-requests/${id}/restore`, { reason })
  return data.data as RefundRequest
}

/**
 * Record that Finance already paid this client OUTSIDE Graphite (manual FNB
 * payment). No money moves — it takes the request out of the payout queue so
 * the client cannot be paid a second time when the Omni link is switched on.
 */
export async function settleRefundManually(
  id: number,
  payload: { reason: string; paid_at?: string | null; paid_ref?: string | null },
) {
  const { data } = await apiClient.post(`/refund-requests/${id}/settle-manually`, {
    reason: payload.reason,
    paid_at: payload.paid_at || null,
    paid_ref: payload.paid_ref || null,
  })
  return data.data as RefundRequest
}

/** Undo a manual-payment record made in error. */
export async function undoManualSettlement(id: number, reason: string) {
  const { data } = await apiClient.post(`/refund-requests/${id}/settle-manually/undo`, { reason })
  return data.data as RefundRequest
}

export async function assignRefundRequest(id: number, userId: number) {
  const { data } = await apiClient.post(`/refund-requests/${id}/assign`, { user_id: userId })
  return data.data as RefundRequest
}

export async function listAssignableUsers(id: number) {
  const { data } = await apiClient.get(`/refund-requests/${id}/assignable-users`)
  return data.data as AssignableUser[]
}

// ─── Finance review-and-post accounting queue ────────────────────────────────

export async function listRefundAccounting(params: { status?: string; policy_number?: string; page?: number; per_page?: number } = {}) {
  const { data } = await apiClient.get('/refund-accounting', { params })
  return data as {
    data: RefundAccountingEntry[]
    meta: { current_page: number; last_page: number; per_page: number; total: number }
  }
}

export async function postRefundAccounting(
  id: number,
  overrides: { earned_premium?: number; unearned_premium?: number; effective_date?: string; end_date?: string } = {}
) {
  const { data } = await apiClient.post(`/refund-accounting/${id}/post`, overrides)
  return data.data as RefundAccountingEntry
}

export async function dismissRefundAccounting(id: number, reason: string) {
  const { data } = await apiClient.post(`/refund-accounting/${id}/dismiss`, { reason })
  return data.data as RefundAccountingEntry
}
