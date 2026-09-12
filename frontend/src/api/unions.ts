import apiClient from './client'

// ─── Types ──────────────────────────────────────────────────────────────────

export type UnionListRow = {
  id: number
  union_name: string
  union_code: string | null
  policy_id: number | null
  policy_number: string | null
  product_id: number
  /** Legal Insurance product this union is mapped to (product_plans.id). */
  plan_id: number | null
  plan_name: string | null
  /** Derived from the mapped plan's price — read-only from the UI's point of view. */
  monthly_premium: number
  effective_date: string | null
  expiry_date: string | null
  status: number
  total_members: number
  active_members: number
  inactive_members: number
  total_monthly_premium: number
}

export type UnionStats = {
  total_members: number
  active_members: number
  inactive_members: number
  monthly_premium: number
  total_monthly_premium: number
  total_claims: number
  outstanding_claims: number
}

export type UnionDetail = {
  id: number
  union_name: string
  union_code: string | null
  description: string | null
  monthly_premium: number
  product_id: number
  plan_id: number | null
  plan_name: string | null
  policy_number: string | null
  effective_date: string | null
  expiry_date: string | null
  contact_person: string | null
  contact_number: string | null
  email: string | null
  address: string | null
  status: number
  policy: {
    id: number
    policy_number: string | null
    product_id: number
    premium: number
    premium_freq: string | null
    status: number
  } | null
  stats: UnionStats
}

export type UnionMember = {
  id: number
  union_id: number
  policy_id: number | null
  customer_id: number | null
  id_number: string
  member_name: string
  member_type: string | null
  date_of_birth: string | null
  gender: number | null
  contact_number: string | null
  email: string | null
  nationality: string | null
  status: number
}

export type ImportPreviewRow = {
  row: number
  id_number: string
  name: string
  type: string
  status: 'valid' | 'duplicate' | 'error'
  messages: string[]
}

export type ImportResult = {
  committed: boolean
  summary: {
    total: number
    valid: number
    imported: number
    failed: number
    duplicates: number
    validation_errors: number
  }
  preview: ImportPreviewRow[]
}

export type Paginated<T> = {
  data: T[]
  current_page: number
  last_page: number
  from: number
  to: number
  total: number
}

export type MemberFilters = { search?: string; status?: string; page?: number; per_page?: number }

/** A Legal Insurance product, as served by GET /lookups/products/4/plans. */
export type LegalProduct = {
  id: number
  product_id: number
  name: string
  sum_assured?: string | number | null
  billing?: string | null
  /** All-in price (VAT included) — the number shown to operators. */
  premium: number
  premium_inc_vat?: number
  premium_ex_vat?: number
}

export type UnionPayload = {
  union_name: string
  union_code: string
  description?: string | null
  product_id?: number
  policy_number?: string | null
  /**
   * The Legal Insurance product the union is registered under. The backend
   * derives monthly_premium from it, which is why that field is optional here —
   * it is only sent by legacy callers that have no plan to map.
   */
  plan_id?: number | null
  monthly_premium?: number
  effective_date?: string | null
  expiry_date?: string | null
  contact_person?: string | null
  contact_number?: string | null
  email?: string | null
  address?: string | null
  status?: number
}

export type MemberPayload = {
  id_number: string
  member_name: string
  member_type: string
  date_of_birth?: string | null
  gender?: string | null
  contact_number: string
  email?: string | null
  nationality: string
  status?: number
}

// ─── Legal Insurance products ───────────────────────────────────────────────

/** Product 4 = Legal Insurance; its plans are the products a union maps to. */
export const LEGAL_PRODUCT_ID = 4

export const fetchLegalProducts = () =>
  apiClient
    .get(`/lookups/products/${LEGAL_PRODUCT_ID}/plans`)
    .then(r => (r.data.data ?? r.data.plans ?? []) as LegalProduct[])

// ─── Unions ─────────────────────────────────────────────────────────────────

export const fetchUnions =(params: { search?: string; status?: string } = {}) =>
  apiClient.get('/unions', { params }).then(r => r.data.data as UnionListRow[])

export const fetchUnion = (id: number | string) =>
  apiClient.get(`/unions/${id}`).then(r => r.data.data as UnionDetail)

export const createUnion = (payload: UnionPayload) =>
  apiClient.post('/unions', payload).then(r => r.data)

export const updateUnion = (id: number, payload: Partial<UnionPayload>) =>
  apiClient.put(`/unions/${id}`, payload).then(r => r.data)

export const setUnionStatus = (id: number, status: number) =>
  apiClient.patch(`/unions/${id}/status`, { status }).then(r => r.data)

export const deleteUnion = (id: number) =>
  apiClient.delete(`/unions/${id}`).then(r => r.data)

// ─── Members ────────────────────────────────────────────────────────────────

export const fetchMembers = (unionId: number | string, filters: MemberFilters = {}) =>
  apiClient.get(`/unions/${unionId}/members`, { params: filters }).then(r => r.data as Paginated<UnionMember>)

export const createMember = (unionId: number | string, payload: MemberPayload) =>
  apiClient.post(`/unions/${unionId}/members`, payload).then(r => r.data)

export const updateMember = (unionId: number | string, memberId: number, payload: Partial<MemberPayload>) =>
  apiClient.put(`/unions/${unionId}/members/${memberId}`, payload).then(r => r.data)

export const removeMember = (unionId: number | string, memberId: number) =>
  apiClient.delete(`/unions/${unionId}/members/${memberId}`).then(r => r.data)

// ─── Excel: template / export / import ────────────────────────────────────────

/** Trigger a browser download of a blob returned by an authed apiClient GET. */
async function downloadBlob(url: string, fallbackName: string) {
  const res = await apiClient.get(url, { responseType: 'blob' })
  const disp = res.headers['content-disposition'] as string | undefined
  const match = disp?.match(/filename="?([^"]+)"?/)
  const name = match?.[1] || fallbackName
  const href = URL.createObjectURL(res.data as Blob)
  const a = document.createElement('a')
  a.href = href
  a.download = name
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(href)
}

export const downloadMemberTemplate = (unionId: number | string) =>
  downloadBlob(`/unions/${unionId}/members/template`, 'union_members_template.xlsx')

export const exportMembersFile = (unionId: number | string) =>
  downloadBlob(`/unions/${unionId}/members/export`, 'union_members.xlsx')

export const importMembers = (unionId: number | string, file: File, commit: boolean) => {
  const fd = new FormData()
  fd.append('file', file)
  fd.append('commit', commit ? '1' : '0')
  // Multipart header is required: apiClient defaults to application/json, and
  // axios v1 serialises FormData to JSON when the content type says JSON — the
  // File is dropped and the backend 422s with "The file field is required".
  //
  // The 30s apiClient default is a sane guard for ordinary calls but far too
  // short here: a full union roster is thousands of rows, each inserting a
  // customer + profile + member + policy link. Blowing the client timeout
  // aborts the request while the server is still mid-import ("The upload timed
  // out. Try a smaller file."), so allow 10 minutes to match the server's.
  return apiClient
    .post(`/unions/${unionId}/members/import`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
      timeout: 10 * 60 * 1000,
    })
    .then(r => r.data as ImportResult)
}

// ─── Legal claims (BONU claim form filed against a member) ────────────────────

/** "Who does the matter relate to?" — matches the BONU form's four options. */
export const MATTER_RELATES_TO = ['Main Member', 'Spouse/Life Partner', 'Child', 'Parents'] as const
/** Type of matter — the BONU form's three options. */
export const MATTER_TYPES = ['Civil', 'Criminal', 'Labor'] as const

/** One row of the page-3 documentation checklist. */
export type DocChecklistItem = { key: string; label: string }
/** Documentation checklist, grouped exactly as the BONU form page 3. */
export const DOCUMENTATION_CATALOG: { group: string; items: DocChecklistItem[] }[] = [
  {
    group: 'General',
    items: [
      { key: 'gen_member_id', label: "Copy of the Member's Omang or Passport" },
      { key: 'gen_spouse_id', label: "Spouse/Life Partner: Omang/Passport first page + proof of marriage/relationship" },
      { key: 'gen_child_birth_cert', label: "Child under 18: Birth Certificate" },
      { key: 'gen_child_18_21_id', label: 'Child 18–21: Birth Certificate or Omang/Passport' },
      { key: 'gen_child_school_proof', label: 'Child 18–21: proof of full-time school / tertiary study' },
      { key: 'gen_child_dependence', label: 'Child 18–21: proof of financial dependence' },
    ],
  },
  {
    group: 'Criminal matters',
    items: [
      { key: 'crim_charge_sheet', label: 'Copy of the charge sheet and annexures' },
      { key: 'crim_no_conviction', label: "Member's statement confirming no relevant prior convictions" },
    ],
  },
  {
    group: 'Civil matters',
    items: [
      { key: 'civ_demand_summons', label: 'Copy of the demand or summons' },
      { key: 'civ_accident_report', label: 'Copy of the Road Accident Report' },
      { key: 'civ_relevant_dates', label: "Member's statement of relevant dates" },
      { key: 'civ_vehicle_reg', label: 'Vehicle registration certificate (proof of ownership)' },
      { key: 'civ_repair_quotes', label: 'Copy of repair quotations' },
      { key: 'civ_agreement', label: 'Agreement / correspondence giving rise to the dispute' },
      { key: 'civ_member_statement', label: 'Copy of statement taken from the Member' },
    ],
  },
  {
    group: 'Labor matters',
    items: [
      { key: 'lab_member_statement', label: 'Copy of statement taken from the Member' },
      { key: 'lab_dispute_proof', label: 'Proof of when the dispute arose (dismissal/retrenchment/appointment letter)' },
      { key: 'lab_unfair_practice', label: 'Details of unfair labor practice not referred to above' },
    ],
  },
]

/** Per-item checklist state captured on the form. */
export type DocChecklistState = Record<string, { enclosed: boolean; forwarded: boolean }>

export type LegalClaimPayload = {
  region?: string
  claim_type?: string
  matter_relates_to: string
  child_financially_dependent?: boolean
  dependent_omang_passport?: string
  dependent_dob?: string
  matter_type: string
  matter_arose_date?: string
  proposed_course_of_action?: string
  declaration_signed?: boolean
  signatory_name?: string
  signed_date?: string
  documentation_checklist?: DocChecklistState
}

/** Route to the dedicated per-union legal claim form (BONU / BOWASEWU), else the generic form. */
export function legalClaimPath(
  unionCode: string | null | undefined,
  unionId: number | string,
  memberId: number | string,
): string {
  const base = `/unions/${unionId}/members/${memberId}/legal-claim`
  switch ((unionCode || '').toUpperCase()) {
    case 'BONU': return `${base}/bonu`
    case 'BOWASEWU': return `${base}/bowasewu`
    default: return base
  }
}

export type UnionLegalClaim = {
  id: number
  union_id: number
  union_member_id: number
  claim_id: number | null
  claim_number: string | null
  form_code: string | null
  form_name: string | null
  policy_number: string | null
  insured_name: string | null
  region: string | null
  omang_passport: string | null
  cellphone: string | null
  email: string | null
  claim_type: string | null
  matter_relates_to: string | null
  matter_type: string | null
  matter_arose_date: string | null
  proposed_course_of_action: string | null
  status: string
  created_at: string | null
}

export const fetchLegalClaims = (unionId: number | string, filters: MemberFilters = {}) =>
  apiClient.get(`/unions/${unionId}/legal-claims`, { params: filters }).then(r => r.data as Paginated<UnionLegalClaim>)

export const fetchLegalClaim = (unionId: number | string, claimId: number | string) =>
  apiClient.get(`/unions/${unionId}/legal-claims/${claimId}`).then(r => r.data.data)

/**
 * File a legal claim against a member. `fd` is a FormData carrying the scalar
 * fields, a JSON `documentation_checklist`, a JSON `document_labels` map, and
 * any enclosed files under `documents[<itemKey>]`. Multipart header + longer
 * timeout for the same reasons as importMembers.
 */
export const createLegalClaim = (unionId: number | string, memberId: number | string, fd: FormData) =>
  apiClient
    .post(`/unions/${unionId}/members/${memberId}/legal-claims`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
      timeout: 5 * 60 * 1000,
    })
    .then(r => r.data)

/** Edit a union legal claim's own fields (used from the claim detail page). */
export const updateUnionLegalClaim = (
  unionId: number | string,
  legalClaimId: number | string,
  payload: Partial<LegalClaimPayload>,
) => apiClient.put(`/unions/${unionId}/legal-claims/${legalClaimId}`, payload).then(r => r.data)

// ─── Monthly premium collection: payment list + proof of payment ─────────────
// (BONU brief 2026-09-08.) Reads need view_unions; imports/uploads need
// manage_union_payments.

export type PaymentMemberRow = {
  member_id: number
  id_number: string
  member_name: string
  member_type: string | null
  contact_number: string | null
  paid: boolean
  amount: number | null
  paid_on: string | null
  reference: string | null
  source: 'import' | 'manual' | null
}

export type PaymentSummary = {
  active_members: number
  paid: number
  unpaid: number
  collected: number
  expected: number
  monthly_premium: number
  proofs: number
}

export type UnmatchedPayment = {
  id_number: string
  member_name: string | null
  amount: number | null
  paid_on: string | null
  reference: string | null
}

export type PaymentListResponse = Paginated<PaymentMemberRow> & {
  period: string
  summary: PaymentSummary
  unmatched: UnmatchedPayment[]
}

export type PaymentPeriod = { period: string; paid: number; proofs: number }

export type PaymentImportRow = {
  row: number
  id_number: string
  name: string
  amount: number | null
  paid_on: string | null
  status: 'valid' | 'unmatched' | 'duplicate' | 'failed'
  messages: string[]
}

export type PaymentImportResult = {
  committed: boolean
  period: string
  summary: { total: number; valid: number; unmatched: number; duplicates: number; failed: number; imported: number; validation_errors: number }
  preview: PaymentImportRow[]
}

export type PaymentProof = {
  id: number
  period: string
  original_name: string
  url: string | null
  mime: string | null
  size: number | null
  amount: number | null
  note: string | null
  uploaded_by_name: string | null
  created_at: string
}

export type PaymentFilters = { period: string; search?: string; status?: 'paid' | 'unpaid' | ''; page?: number; per_page?: number }

export const fetchUnionPayments = (unionId: number | string, f: PaymentFilters) =>
  apiClient.get(`/unions/${unionId}/payments`, { params: f }).then(r => r.data as PaymentListResponse)

export const fetchPaymentPeriods = (unionId: number | string) =>
  apiClient.get(`/unions/${unionId}/payments/periods`).then(r => r.data.data as PaymentPeriod[])

export const downloadPaymentTemplate = (unionId: number | string) =>
  downloadBlob(`/unions/${unionId}/payments/template`, 'union_payment_list_template.xlsx')

export const importPaymentList = (unionId: number | string, period: string, file: File, commit: boolean) => {
  const fd = new FormData()
  fd.append('file', file)
  fd.append('period', period)
  fd.append('commit', commit ? '1' : '0')
  return apiClient
    .post(`/unions/${unionId}/payments/import`, fd, { headers: { 'Content-Type': 'multipart/form-data' }, timeout: 5 * 60 * 1000 })
    .then(r => r.data as PaymentImportResult)
}

export const setMemberPayment = (
  unionId: number | string,
  memberId: number,
  payload: { period: string; paid: boolean; amount?: number | null; paid_on?: string | null; reference?: string | null },
) => apiClient.put(`/unions/${unionId}/payments/members/${memberId}`, payload).then(r => r.data as { message: string; summary: PaymentSummary })

export const fetchPaymentProofs = (unionId: number | string, period?: string) =>
  apiClient.get(`/unions/${unionId}/payments/proofs`, { params: period ? { period } : {} }).then(r => r.data.data as PaymentProof[])

export const uploadPaymentProof = (unionId: number | string, period: string, file: File, amount?: string, note?: string) => {
  const fd = new FormData()
  fd.append('file', file)
  fd.append('period', period)
  if (amount) fd.append('amount', amount)
  if (note) fd.append('note', note)
  return apiClient
    .post(`/unions/${unionId}/payments/proofs`, fd, { headers: { 'Content-Type': 'multipart/form-data' }, timeout: 5 * 60 * 1000 })
    .then(r => r.data as { message: string; data: PaymentProof })
}

export const deletePaymentProof = (unionId: number | string, proofId: number) =>
  apiClient.delete(`/unions/${unionId}/payments/proofs/${proofId}`).then(r => r.data)

/** Frontend-side gate mirroring the manage_union_payments route middleware. */
export function canManageUnionPayments(): boolean {
  try {
    const perms = JSON.parse(localStorage.getItem('user_permissions') || '[]') as string[]
    const roles = JSON.parse(localStorage.getItem('user_roles') || '[]') as string[]
    if (perms.includes('manage_union_payments')) return true
    return roles.some(r => /super admin/i.test(String(r)))
  } catch {
    return false
  }
}
