import apiClient from './client'

export interface ClaimListItem {
  id: number
  claim_number: string
  claim_type: string
  status: string
  registered_claim?: string
  created_at: string
  /** graphiteBWV8 parity: resolves claims.created_by → "firstName lastName",
   *  falls back to 'N/A'. Rendered as the "Claim Handler" column. */
  claim_handler?: string
  // ── Additive "All Claims" badge / filter signals (see ClaimsController::index) ──
  /** Net reserve total (active coverages). Drives the MAJOR badge tooltip. */
  total_reserve?: number
  /** true when total_reserve > P300,000. Drives the MAJOR badge. */
  is_major?: boolean
  /** true when the claim has a voided payment. Drives the REVERSED badge. */
  has_reversal?: boolean
  /** 'Broker' (policy carries an agent) or 'Direct'. */
  channel?: 'Broker' | 'Direct'
  /** true = linked to Claims Tracker (external_ref set); null when the tracker
   *  columns are absent on this schema. Drives the SYNCED badge. */
  synced?: boolean | null
  /** Origin tag (e.g. 'claims-tracker', 'admin', 'mobile'); null if unavailable. */
  source?: string | null
  policy?: {
    id: number
    policy_number: string
    product_name?: string
  }
  customer?: {
    id: number
    name: string
    company_name?: string | null
    is_company?: boolean
    cellphone?: string
  }
}

export interface ClaimCoverage {
  // Per-row id from claim_reserves_coverages — surfaced so the FE can hit
  // the void endpoint with the exact row to reverse.
  id?: number
  coverageId: number
  coverageName: string
  reserveAmt: number
  paymentAmt: number
  balance: number
  subrogationReserve?: number
  subrogationPayment?: number
  salvageReserve?: number
  salvagePayment?: number
  // 0=active, 1=original was voided, 2=this is the reversal entry.
  isPaymentVoided?: number
  // True when this coverage/claimed item was flagged as a write-off
  // (Loss Reserve + "Claim Expense"). Drives the WRITE-OFF badge.
  writeOff?: boolean
}

export interface ClaimReserve {
  id: number
  date: string
  transactionType: string | number
  transactionTypeName?: string
  transactionSubType?: number | null
  transactionSubTypeName?: string | null
  payee?: string
  payeeName?: string | null
  address?: string | null
  invoiceNo?: string
  invoiceDate?: string | null
  invoiceDueDate?: string | null
  memo?: string
  description?: string
  creditNote?: number
  includeVat?: boolean
  createdAt: string
  insertedBy?: number | null
  insertedByName?: string | null
  plusAmount?: number
  minusAmount?: number
  runningBalance?: number
  isVoided?: boolean
  // True on the original payment row that has been voided (is_payment_voided=1).
  // The FE uses this to paint the row yellow and show the Void Info button.
  wasVoided?: boolean
  canVoid?: boolean
  voidCrcId?: number | null
  // True when any coverage in this reserve entry was flagged as a write-off.
  // Drives the row-level WRITE-OFF badge in the reserve history table.
  hasWriteOff?: boolean
  coverages: ClaimCoverage[]
}

export interface ClaimAssessment {
  id: number
  assessorName?: string
  assessmentReport?: string
  quotationsParts?: string
  valuation?: number
  notes?: string
}

export interface ClaimQuote {
  id: number
  supplierId?: number
  total?: number
  status?: string
  selectReason?: string
  invoiceNotes?: string
  claimFile?: string
  invoice?: string
  createdAt?: string
}

export interface ClaimAttachment {
  id: number
  name: string
  documentTypeName?: string
  type?: string
  files: { name: string; url: string }[]
  createdAt?: string
}

export interface ClaimActivity {
  id: number
  description: string
  userName?: string
  createdAt: string
}

export interface ClaimComplaint {
  id: number
  complaintOf?: string
  complaintDetails?: string
  addedBy?: string
  createdAt?: string
  // Regulatory register fields (snake_case — returned raw from /complaints)
  complainant_name?: string
  complainant_id_type?: string
  complainant_omang?: string
  complainant_passport?: string
  complainant_phone?: string
  complainant_email?: string
  complainant_postal_address?: string
  date_filed?: string
  reference_number?: string
  nature?: string
  handler_user_id?: number
  handler_name?: string
  escalation_level?: string
  status?: string
  rejection_reason?: string
  resolution?: string
  closed_at?: string
  claim_number?: string
  policyNumber?: string
  added_by_name?: string
  complaint_of?: string
  complaint_details?: string
}

export interface ClaimDetail extends ClaimListItem {
  category?: string
  created_by?: number
  created_by_name?: string
  closed_note?: string
  updated_at?: string
  allocated_to?: string
  allocated_on?: string
  date_of_loss?: string
  location?: string
  claim_sub_type?: string
  type_of_loss?: string
  claim_reported_by?: string
  is_motor_claim?: boolean
  vehicle_plate?: string
  paid_amount?: number
  reserve_amount?: number
  driver_as_insured?: boolean | string
  attorney_involved?: boolean | string
  claim_sub_status?: string
  total_reserve?: number
  total_payment?: number
  balance?: number
  documents?: { label: string; url: string }[]
  policy?: {
    id: number
    policy_number: string
    product_id?: number | null
    product_name?: string
    // Authoritative switch for which detail sections to render. Values
    // mirror legacy claimTypesByPolicy(): 'life', 'vehicle', 'legal',
    // 'cellphone', 'tyre', 'hospital_cash', 'coverage_based'.
    form_template?: 'life' | 'vehicle' | 'legal' | 'cellphone' | 'tyre' | 'hospital_cash' | 'coverage_based' | null
    status?: number
    premium?: number
    agent_name?: string
  }
  // Policy action term the claim was registered against — rendered as the
  // "Action Term: …" badge in the detail header (mirrors graphiteBWV8).
  policy_action?: {
    id: number
    transaction_type?: string
    status?: string
    effective_from?: string | null
    effective_to?: string | null
    // True when the linked term has been soft-deleted — the badge/row is
    // marked "Deleted" so the operator knows the term no longer exists.
    deleted?: boolean
  } | null
  customer?: {
    id: number
    name: string
    company_name?: string | null
    is_company?: boolean
    individual_name?: string
    email?: string
    cellphone?: string
  }
  attachments?: ClaimAttachment[]
  reserves?: ClaimReserve[]
  assessment?: ClaimAssessment | null
  quotes?: ClaimQuote[]
  thirdParties?: any[]
  vehicleDetails?: Record<string, any> | null
  // MIS claim-type payloads. Non-null only when the corresponding
  // form_template was used at creation (life / legal / hospital_cash).
  lifeDetails?: Record<string, any> | null
  legalDetails?: Record<string, any> | null
  // Union legal claim (BONU / BOWASEWU) — its own dedicated form record. When
  // present, the detail page shows the union claim's fields instead of the
  // generic claim_legal (Legal Firm / Lawyer …) block.
  unionLegalClaim?: Record<string, any> | null
  /** Union member's premium status for the claim month (Unions › Payments). */
  unionPaymentStatus?: {
    period: string; paid: boolean; amount: number | null; paid_on: string | null
    previous_period: string; previous_paid: boolean; proofs: number; basis: 'matter_arose_date' | 'filed_date'
  } | null
  hospitalCashDetails?: Record<string, any> | null
  // Motor sub-tables.
  accidentDriver?: Record<string, any> | null
  accidentPassengers?: Record<string, any>[]
  // DOMG/COMG claim-type-specific payload: whichever legacy sub-table
  // matches claim_type (business_interruption / burglary / fidelity_guarantee
  // / property_loss_damage / public_liability / workers_compensation /
  // goods_in_transit / all_risk_and_electronic_equipment / fire / etc.).
  // NULL for MIS and motor claim types.
  subClaimData?: Record<string, any> | null
  activityLog?: ClaimActivity[]
  complaints?: ClaimComplaint[]
  // MotoLink (Scans.ai) assessment mirrored onto the claim by the push
  // endpoint. Null until the first assessment syncs.
  motolink?: {
    assessment_id?: string | null
    status?: string | null
    final_cost?: number | string | null
    make?: string | null
    model?: string | null
    registration?: string | null
    vin?: string | null
    total_loss?: boolean
    write_off_alert?: boolean
    synced_at?: string | null
  } | null
}

/**
 * Goods In Transit (GIT) sub-claim payload — column-for-column mirror of
 * the `goods_in_transit_claim` table. Keys match graphiteBWV8's
 * `NewClaimController::storeGoodsInTransit` so the backend sub_claim_data
 * passthrough lands them directly. The `copy_of_contract` file itself is
 * sent via FormData, not on this object — the path returned by S3 is
 * persisted to the same column server-side.
 */
export interface GoodsInTransitSubClaim {
  address_of_premises_loss: string
  details_of_driver: string
  property_last_seen: string
  date_time_of_loss: string
  brief_description_incident: string
  date_time_police_advised: string
  police_station_name: string
  witnesses_name: string
  witnesses_mobile_number: string
  total_value_of_loss: string
  consignment_transported_to: string
  consignment_from: string
  vehicle_registration_number: string
  is_carrier_contracted: '0' | '1'
  carrier_has_own_GIT_ins: '0' | '1'
  other_insurance_against_theft: '0' | '1'
  insurance_against_theft_details?: string
  details_of_previous_loss_records: string
  copy_of_contract?: string
}

export interface ClaimFilters {
  status?: string
  claim_type?: string
  search?: string
  per_page?: number
  page?: number
  date_from?: string
  date_to?: string
  // ── Additive "All Claims" parity filters (all optional) ──
  /** YYYY-MM convenience filter over created_at. */
  month?: string
  /** 'broker' | 'direct' — matched against the policy's agent. */
  channel?: 'broker' | 'direct'
  /** claims.source origin tag. */
  source?: string
  /** 1 = linked to Claims Tracker (external_ref set), 0 = not. */
  synced?: 0 | 1
  /** 1 = has a voided payment, 0 = none. */
  reversed?: 0 | 1
  /** 1 = net reserve > P300,000. */
  major?: 0 | 1
  /** Sortable column whitelist (backend-enforced). */
  sort?: 'claim_number' | 'created_at' | 'status' | 'claim_type' | 'id'
  direction?: 'asc' | 'desc'
}

export interface ClaimListResponse {
  data: ClaimListItem[]
  meta: {
    total: number
    per_page: number
    current_page: number
    last_page: number
  }
}

export interface ClaimTypeOption {
  id: string
  name: string
  code: string
}

export interface ClaimCreateData {
  claim_types: ClaimTypeOption[]
  event_names: { id: number; name: string }[]
  reported_by: { id: number; name: string }[]
  loss_types: { id: number; name: string }[]
  claim_sub_types: { id: number; name: string }[]
  /** Lookup::where('key','transaction_sub_type') — drives the reserve form */
  transaction_sub_types: { id: number; name: string }[]
  /** Active internal users — drives the "Claim Allocated To" dropdown and the
   *  write-off notification recipient picker (email included for the latter). */
  internal_users: { id: number; name: string; email?: string | null }[]
  /** Lookup::where('key','cause_of_death') — used for Life / ADI claim form */
  cause_of_death_options: { id: number; name: string }[]
  weather_conditions: { id: string; name: string }[]
  fault_parties: { id: string; name: string }[]
  statuses: { id: string; name: string }[]
  /** DOM/COM-only Type of Loss (Property/Liability per V8 main.blade) */
  loss_types_dom_com?: { id: string; name: string }[]
  /** Hardcoded attorney firm lists for the DOM/COM Classification block */
  attorneys_primary?: { id: string; name: string }[]
  attorneys_co?: { id: string; name: string }[]
  /**
   * Reported by Broker/Agent dropdown — union of active users (typed "Agent")
   * and rows from the `agencies` table (typed "Agency"). Mirrors V8's
   * NewClaimController $agents_options.
   */
  agents_options?: { id: string; name: string; type: string }[]
}

export interface CreateClaimPayload {
  policy_id: number
  claim_type: string
  category?: string
  registered_claim?: string
  incident_date?: string
  incident_time?: string
  incident_location?: string
  incident_description?: string
  reported_by?: string
  reported_date?: string
}

// ── Fetchers ──────────────────────────────────────────────

export async function fetchClaims(filters: ClaimFilters = {}): Promise<ClaimListResponse> {
  const { data } = await apiClient.get<ClaimListResponse>('/claims', { params: filters })
  return data
}

/**
 * Fetch ALL claims matching the current filters (across pages) for CSV export.
 * Walks the paginated endpoint at the max page size, bounded by `cap` rows so
 * a filter-less export can never runaway. Ignores the caller's page/per_page.
 */
export async function fetchAllClaimsForExport(
  filters: ClaimFilters = {},
  cap = 5000,
): Promise<ClaimListItem[]> {
  const perPage = 100
  const rows: ClaimListItem[] = []
  let page = 1
  let lastPage = 1
  do {
    const { data } = await apiClient.get<ClaimListResponse>('/claims', {
      params: { ...filters, page, per_page: perPage },
    })
    rows.push(...data.data)
    lastPage = data.meta?.last_page ?? 1
    page += 1
  } while (page <= lastPage && rows.length < cap)
  return rows.slice(0, cap)
}

export async function fetchClaim(id: number): Promise<ClaimDetail> {
  // The claim detail endpoint aggregates ~30 sub-queries (reserves, attachments,
  // activity log, claim-type sub-tables, etc.). On a remote dev DB the cumulative
  // round-trip latency comfortably exceeds the apiClient's 30s default, so we
  // give this endpoint a 90s ceiling. Production latency is well under 5s.
  const { data } = await apiClient.get<{ data: ClaimDetail }>(`/claims/${id}`, { timeout: 90_000 })
  return data.data
}

export async function fetchClaimCreateData(): Promise<ClaimCreateData> {
  const { data } = await apiClient.get<{ data: ClaimCreateData }>('/claims/create-data')
  return data.data
}

export async function fetchClaimPolicyCoverages(policyId: number) {
  const { data } = await apiClient.get<{ data: any[] }>(`/claims/policy/${policyId}/coverages`)
  return data.data
}

export interface PolicyActionOption {
  id: number
  transactionType: string
  status: string
  effectiveFrom?: string | null
  effectiveTo?: string | null
}

/** Selectable policy action terms for a claim's Classification edit dropdown. */
export async function fetchClaimPolicyActions(claimId: number): Promise<PolicyActionOption[]> {
  const { data } = await apiClient.get<{ data: PolicyActionOption[] }>(`/claims/${claimId}/policy-actions`)
  return data.data
}

export async function createClaim(payload: CreateClaimPayload | FormData) {
  const isFormData = payload instanceof FormData
  const { data } = await apiClient.post('/claims', payload, isFormData ? {
    headers: { 'Content-Type': 'multipart/form-data' },
  } : undefined)
  return data
}

export async function updateClaimStatus(id: number, status: string, closedNote?: string, subStatus?: string) {
  const { data } = await apiClient.patch(`/claims/${id}/status`, {
    status,
    closed_note: closedNote,
    // Only sent when moving into 'Open'; backend requires + validates it then.
    ...(subStatus !== undefined ? { claim_sub_status: subStatus } : {}),
  })
  return data
}

// ── Complaints ─────────────────────────────────────────────

/**
 * Log a complaint against a claim. Mirrors graphiteBWV8's
 * /admin/claims/storeComplaint/{id} endpoint — one shared
 * `claim_complaint_log` table; `added_by` is set server-side from the
 * authenticated user, `created_at` becomes the "Added Date" column.
 */
export interface ComplaintPayload {
  policy_id?: number | null
  claim_id: number
  complainant_name: string
  complainant_id_type?: string | null
  complainant_omang?: string | null
  complainant_passport?: string | null
  complainant_phone?: string | null
  complainant_email?: string | null
  complainant_postal_address?: string | null
  date_filed: string
  reference_number?: string | null
  complaint_of?: string | null
  nature: string
  complaint_details: string
  handler_user_id?: number | null
  handler_name?: string | null
  escalation_level?: string | null
  status: string
  rejection_reason?: string | null
  resolution?: string | null
  closed_at?: string | null
}

export interface ComplaintLookups {
  id_type: string[]
  nature: string[]
  status: string[]
  escalation_level: string[]
}

export async function createClaimComplaint(payload: ComplaintPayload) {
  const { data } = await apiClient.post('/complaints', payload)
  return data
}

export async function updateClaimComplaint(id: number, payload: ComplaintPayload) {
  const { data } = await apiClient.put(`/complaints/${id}`, payload)
  return data
}

/** Register dropdown vocab (draft — aligned to the regulator template at go-live). */
export async function fetchComplaintLookups(): Promise<ComplaintLookups> {
  const { data } = await apiClient.get('/complaints/lookups')
  return data.data
}

/** Prefill complainant/handler/reference from the claim's customer (internal PII path). */
export async function fetchComplaintPrefill(claimId: number): Promise<Record<string, any>> {
  const { data } = await apiClient.get('/complaints/prefill', { params: { claim_id: claimId } })
  return data.data
}

/** Full register rows for one claim (all regulatory fields), used by the claim tab. */
export async function fetchClaimComplaints(claimId: number): Promise<ClaimComplaint[]> {
  const { data } = await apiClient.get('/complaints', { params: { claim_id: claimId, per_page: 200 } })
  return (data.data ?? []) as ClaimComplaint[]
}

export async function fetchComplaintDocuments(complaintId: number): Promise<any[]> {
  const { data } = await apiClient.get(`/complaints/${complaintId}/documents`)
  return (data.data ?? []) as any[]
}

export async function uploadComplaintDocument(complaintId: number, files: File[], name?: string) {
  const fd = new FormData()
  files.forEach(f => fd.append('files[]', f))
  if (name) fd.append('name', name)
  const { data } = await apiClient.post(`/complaints/${complaintId}/documents`, fd, {
    // The axios instance defaults to application/json; must override for
    // multipart or the files arrive as strings ("files.0 must be a file").
    headers: { 'Content-Type': 'multipart/form-data' },
    timeout: 5 * 60 * 1000,
  })
  return data
}

export async function deleteComplaintDocument(complaintId: number, docId: number) {
  const { data } = await apiClient.delete(`/complaints/${complaintId}/documents/${docId}`)
  return data
}

/** Download the quarterly Complaints Register (xlsx/csv) as a browser file. */
export async function exportComplaintsRegister(params: Record<string, any>) {
  const res = await apiClient.get('/complaints/export', { params, responseType: 'blob' })
  const cd = (res.headers['content-disposition'] as string) || ''
  const m = cd.match(/filename="?([^"]+)"?/)
  const filename = m ? m[1] : 'complaints-register.xlsx'
  const url = URL.createObjectURL(res.data)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  a.click()
  URL.revokeObjectURL(url)
}

// ── Review Notes ───────────────────────────────────────────

export interface ClaimReviewNoteRecipient {
  name: string
  email: string
  /** ISO timestamp the notification email was sent; null = not (yet) emailed. */
  notifiedAt?: string | null
}

/**
 * A parsed @mention on a review note (claims_mentions feature). `resolved`
 * is false when the handle didn't map to a Graphite user. The array is always
 * present in the API response but empty when the feature is off or the note
 * carried no mentions, so the UI degrades to plain notes automatically.
 */
export interface ClaimReviewNoteMention {
  handle: string
  userId: number | null
  resolved: boolean
}

export interface ClaimReviewNote {
  id: number
  title?: string | null
  note: string
  /** Author display name (falls back to the raw user id for legacy rows). */
  createdBy?: string | null
  createdAt?: string | null
  recipients: ClaimReviewNoteRecipient[]
  files: { name: string; url: string | null }[]
  /** Parsed @mentions for highlight/notify — empty when claims_mentions is off. */
  mentions?: ClaimReviewNoteMention[]
}

export async function fetchClaimReviewNotes(claimId: number): Promise<ClaimReviewNote[]> {
  const { data } = await apiClient.get<{ data: ClaimReviewNote[] }>(`/claims/${claimId}/review-notes`)
  return data.data ?? []
}

export interface CreateClaimReviewNotePayload {
  title?: string
  note: string
  /** Picked internal users to notify (in-app + email). */
  recipientIds?: number[]
  /** Optional free-text / external email recipients. */
  recipientEmails?: string[]
  files?: File[]
  /** Comment priority (claims_comment_status feature); drives @mention reminders. */
  priority?: string
}

/**
 * Log a review note against a claim. Always multipart so attachments ride
 * along on the same request (mirrors uploadClaimAttachment). The note body and
 * recipients are echoed to the tagged users by email server-side; `added_by`
 * is taken from the authenticated user.
 */
export async function createClaimReviewNote(claimId: number, payload: CreateClaimReviewNotePayload) {
  const fd = new FormData()
  fd.append('note', payload.note)
  if (payload.title) fd.append('title', payload.title)
  if (payload.priority) fd.append('priority', payload.priority)
  ;(payload.recipientIds ?? []).forEach(id => fd.append('recipient_ids[]', String(id)))
  ;(payload.recipientEmails ?? []).forEach(e => fd.append('recipient_emails[]', e))
  ;(payload.files ?? []).forEach(f => fd.append('files[]', f))
  const { data } = await apiClient.post(`/claims/${claimId}/review-notes`, fd, {
    headers: { 'Content-Type': 'multipart/form-data' },
    // Attachment uploads can exceed the 30s axios default on prod's network —
    // match the 5-min ceiling used by uploadClaimAttachment.
    timeout: 5 * 60 * 1000,
  })
  return data
}

// ── Comment status + priority (claims_comment_status feature) ──
export interface ClaimCommentPriority { value: string; label: string; threshold_minutes: number | null }
export interface ClaimCommentStatusOptions {
  statuses: string[]
  awaitingSubReasons: string[]
  subReasonStatus: string
  priorities: ClaimCommentPriority[]
}
export interface ClaimCommentStatusData {
  commentStatus: string | null
  commentSubReason: string | null
  updatedBy: string | null
  updatedAt: string | null
}
/** GET the claim's comment status + the option lists (statuses/sub-reasons/priorities). Additive — never throws the tab. */
export async function getClaimCommentStatus(claimId: number): Promise<{ data: ClaimCommentStatusData; options: ClaimCommentStatusOptions }> {
  const { data } = await apiClient.get(`/claims/${claimId}/comment-status`)
  return data
}
/** Set/clear the claim's comment status + sub-reason (permission:claim-edit). */
export async function setClaimCommentStatus(claimId: number, payload: { comment_status: string | null; comment_sub_reason: string | null }) {
  const { data } = await apiClient.put(`/claims/${claimId}/comment-status`, payload)
  return data
}

// ── Attachments ────────────────────────────────────────────

export interface AttachmentRowPayload {
  name: string
  documentTypeName?: string
  type?: string
  files: File[]
}

/**
 * Upload one attachment "row" (matches the legacy blade's per-row submission:
 * one Name + Doc Type label + dropdown key + N files). The TabAttachments
 * form submits each row sequentially so the inline V8-style "Add New
 * Attachment" form ends up creating one claim_attachments record per row.
 */
export async function uploadClaimAttachment(claimId: number, row: AttachmentRowPayload) {
  const fd = new FormData()
  row.files.forEach(f => fd.append('files[]', f))
  if (row.name) fd.append('name', row.name)
  if (row.type) fd.append('type', row.type)
  if (row.documentTypeName) fd.append('document_type_name', row.documentTypeName)
  const { data } = await apiClient.post(
    `/claims-v2/${claimId}/documents`,
    fd,
    {
      headers: { 'Content-Type': 'multipart/form-data' },
      // Multipart uploads of multiple files exceed the 30s axios default
      // on prod's network. Without this override the XHR aborts client-
      // side and surfaces as a (canceled) request with a generic
      // "Upload failed" alert — only on prod, because staging's LAN is
      // fast enough that 5 files finish under 30s.
      timeout: 5 * 60 * 1000,
    },
  )
  return data
}

export async function deleteClaimAttachment(claimId: number, docId: number) {
  const { data } = await apiClient.delete(`/claims-v2/${claimId}/documents/${docId}`)
  return data
}

// ── Closing Documents ──────────────────────────────────────

export interface ClosingDocuments {
  document1?: string | null
  document1Url?: string | null
  document2?: string | null
  document2Url?: string | null
  document3?: string | null
  document3Url?: string | null
  closedNote?: string | null
}

export async function fetchClosingDocuments(claimId: number): Promise<ClosingDocuments> {
  const { data } = await apiClient.get<{ data: ClosingDocuments }>(`/claims-v2/${claimId}/closing-documents`)
  return data.data
}

export interface ClosingDocumentsPayload {
  document1?: File | null
  document2?: File | null
  document3?: File | null
  closedNote?: string
}

export async function uploadClaimClosingDocuments(claimId: number, payload: ClosingDocumentsPayload): Promise<ClosingDocuments> {
  const fd = new FormData()
  if (payload.document1) fd.append('document_1', payload.document1)
  if (payload.document2) fd.append('document_2', payload.document2)
  if (payload.document3) fd.append('document_3', payload.document3)
  if (payload.closedNote !== undefined) fd.append('closed_note', payload.closedNote)
  const { data } = await apiClient.post<{ data: ClosingDocuments; message: string }>(
    `/claims-v2/${claimId}/closing-documents`,
    fd,
    {
      headers: { 'Content-Type': 'multipart/form-data' },
      // Same 5-min override as uploadClaimAttachment: a large closing
      // "full report" PDF exceeds the 30s axios default on prod's network,
      // so the XHR aborts client-side and surfaces as a generic "Upload
      // failed" (GRA-0107). The backend now accepts up to 40 MB; match the
      // client timeout so large closing documents actually complete.
      timeout: 5 * 60 * 1000,
    },
  )
  return data.data
}

// ── Reserves / Payments ────────────────────────────────────

export interface ReserveLookupItem { id: number; name: string }
export interface ReservePayee { id: number; name: string; address?: string | null }

export interface VoidPaymentInfo {
  id: number
  claimReservesCoveragesId: number
  claimId: number
  paymentVoidById: number | null
  paymentVoidByName: string | null
  paymentVoidDate: string | null
  amount: number
  reasonForVoid: string
  claimNumber: string | null
}

export async function fetchReserveTransactionTypes(opts: { excludeInitial?: boolean } = {}): Promise<ReserveLookupItem[]> {
  const { data } = await apiClient.get<{ data: ReserveLookupItem[] }>('/claims-v2/lookups/reserve-types', {
    params: { exclude_initial: opts.excludeInitial === false ? 0 : 1 },
  })
  return data.data ?? []
}

export async function fetchReserveSubTypes(forType: number): Promise<ReserveLookupItem[]> {
  const { data } = await apiClient.get<{ data: ReserveLookupItem[] }>('/claims-v2/lookups/reserve-sub-types', {
    params: { for_type: forType },
  })
  return data.data ?? []
}

export async function fetchReservePayees(): Promise<ReservePayee[]> {
  const { data } = await apiClient.get<{ data: ReservePayee[] }>('/claims-v2/lookups/payees')
  return data.data ?? []
}

export async function fetchVoidPaymentInfo(claimId: number, crcId: number): Promise<VoidPaymentInfo> {
  const { data } = await apiClient.get<{ data: VoidPaymentInfo }>(`/claims-v2/${claimId}/reserves/${crcId}/void-info`)
  return data.data
}

export interface CreateReservePayload {
  transaction_type: number
  transaction_sub_type?: number | string | null
  date?: string
  payee?: string | number | null
  address?: string | null
  invoice_no?: string | null
  invoice_date?: string | null
  invoice_due_date?: string | null
  memo?: string | null
  description?: string | null
  credit_note?: boolean
  include_vat?: boolean
  product_id?: number | null
  coverages: Array<{ coverage_id: number; coverage_name: string; amount: number; write_off?: boolean }>
  // Recipient address(es) for the write-off notification email. One or many;
  // when omitted the backend falls back to the configured department mailboxes.
  write_off_emails?: string[]
}

export async function createReserve(claimId: number, payload: CreateReservePayload) {
  const { data } = await apiClient.post(`/claims-v2/${claimId}/reserves`, payload)
  return data
}

export async function voidReservePayment(claimId: number, crcId: number, reason: string) {
  const { data } = await apiClient.post(`/claims-v2/${claimId}/reserves/${crcId}/void`, {
    reason_for_void: reason,
  })
  return data
}

export interface ReserveCoverageRow {
  // Unique per-row id within a coverages response. Needed because motor
  // extensions repeat the same coverageId across every vehicle, so the row
  // key must be this, not source+policyCoverageId+coverageId.
  rowUid?: number
  policyCoverageId: number
  coverageId: number
  coverageName: string
  coverageCode?: string | null
  coverageLimit: number | null
  riskAddressId: number | null
  riskAddressName: string | null
  // 'specialist' = schedule item held as JSON on a specialist coverage table
  // (Plant All Risk insured_items); 'prior-term' = orphaned reserve from an
  // earlier policy action that still has an outstanding balance.
  source: 'base' | 'extension' | 'specified' | 'specialist' | 'prior-term' | 'union'
  reserveAmt: number
  paymentAmt: number
  balance: number
  // True when this claimed item has already been written off on a prior
  // reserve. A claimed item can only be written off once, so the Write Off
  // checkbox is disabled for these rows.
  writtenOff?: boolean
}

export interface ReserveCoveragesResponse {
  isGrouped: boolean
  productId: number | null
  policyActionId: number | null
  rows: ReserveCoverageRow[]
  groups: Record<string, ReserveCoverageRow[]> | null
}

export async function fetchReserveCoverages(claimId: number): Promise<ReserveCoveragesResponse> {
  const { data } = await apiClient.get<{ data: ReserveCoveragesResponse }>(`/claims-v2/${claimId}/reserves/coverages`)
  return data.data
}
