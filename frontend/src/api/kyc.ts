import apiClient from './client'
import { getStoredPermissions } from './auth'

/**
 * Permission that gates approving / rejecting customer KYC — the overall
 * decision and the per-document verdicts, MIS and DOM/COM alike. Held only by
 * the `KYC Approver` role (three named people, 2026-09-10). The backend
 * enforces it on POST /kyc/{id}/status, /kyc/{id}/verify-document and the
 * dom-com-kyc twins; the pages use this to hide the buttons from everyone
 * else so viewers are not shown controls that would 403.
 */
export const KYC_APPROVE_PERMISSION = 'customer-kyc-approve'

export function canApproveKyc(): boolean {
  return getStoredPermissions().includes(KYC_APPROVE_PERMISSION)
}

/**
 * Human-readable message for a failed approve / reject / verify call. A 403
 * means the backend gate refused the user (stale tab from before the role
 * change, or the role was revoked) — say so plainly instead of failing silently.
 */
export function kycActionErrorMessage(e: unknown): string {
  const err = e as { response?: { status?: number; data?: { message?: string } }; message?: string } | undefined
  if (err?.response?.status === 403) {
    return 'You do not have permission to approve or reject KYC. Only members of the KYC Approver role can do this.'
  }
  return err?.response?.data?.message || err?.message || 'The KYC decision could not be saved.'
}

export interface ListMeta { total: number; per_page: number; current_page: number; last_page: number; from: number | null; to: number | null }

export interface KycFilters { status?: string; compliance?: number; search?: string; tier?: 'MIS' | 'DOMG' | 'COMG'; per_page?: number; page?: number }
export type KycListTier = 'MIS' | 'DOMG' | 'COMG'
export interface KycItem {
  id: number; customerId: number; customerName: string | null
  /** Resolved per row from the customer's active policies. COMG rows
   *  show the company name (when entity_type='Organisation'), DOMG/COMG
   *  rows route to the DomCom review page. */
  tier: KycListTier
  cellphone: string | null; email: string | null
  compliance: number | null; status: string | null; remark: string | null
  hasOmang: boolean; hasOmangBack: boolean; hasPassport: boolean
  hasDrivingLicense: boolean; hasProofResidence: boolean; hasProofIncome: boolean
  updatedAt: string | null
}
export interface KycResponse { data: KycItem[]; meta: ListMeta }

export async function fetchKycList(filters: KycFilters = {}): Promise<KycResponse> {
  const { data } = await apiClient.get<KycResponse>('/kyc', { params: filters })
  return data
}

export async function updateKycStatus(kycId: number, status: 'approved' | 'rejected', remark?: string): Promise<void> {
  // {kycId} = customer_kyc.id (V8 parity). See CustomerKycController::detail
  // for why this is keyed on the KYC row, not customer.id.
  await apiClient.post(`/kyc/${kycId}/status`, { status, remark })
}

// KYC Detail
export interface KycDocument {
  key: string; label: string; uploaded: boolean; url: string | null
  status: number; remark: string | null // 0=pending, 1=approved, 2=rejected
  /** Name of the underlying expiry column on customer_kyc (null if the
   *  doc doesn't carry an expiry — render the input only when set). */
  expiryField: string | null
  /** Current expiry value (ISO date or null). */
  expiryDate: string | null
}
export interface KycActivityLog {
  id: number; action: string; status: string; compliance: string | null
  description: string; reason: string | null; performedBy: string; performedAt: string
  /** V8-parity audit fields (null when the kyc_activity_log row didn't
   *  capture them OR the column doesn't yet exist in the schema). */
  ipAddress?: string | null
  tag?: string | null
  /** Snapshot of the customer_kyc row BEFORE the change captured by this
   *  log entry (sourced from kyc_activity_log.old_data_json). The "new"
   *  state is implicit in the description text — V8 never stored it. */
  oldValues?: Record<string, unknown> | null
}
export type KycTier = 'MIS' | 'DOM_COM' | 'BOTH' | 'NONE'

/** AML/sanctions screening summary for the KYC review page. Mirrors the
 *  V8 viewData.blade sanctions panel (countries, last scan, status). */
export interface KycSanctions {
  /** "Country (Agency)" labels derived from the latest AML datasets. */
  countries: string[]
  programIds: string[]
  maxScore: number
  sanctioned: boolean
  /** Timestamp of the latest AML scan, or null if never screened. */
  lastScan: string | null
  target: string | boolean | null
}

export interface KycDetail {
  customer: { id: number; name: string; firstName: string; lastName: string; cellphone: string; email: string; isBlocked: boolean; blockReason: string | null; address: string | null; dob: string | null; gender: string | null }
  kyc: { id: number; omangNumber: string | null; passportNumber: string | null; omangExpiry: string | null; passportExpiry: string | null; licenseExpiry: string | null; compliance: number | null; status: string | null; remark: string | null; performedBy: string | null; approvedDate: string | null; createdAt: string; updatedAt: string }
  documents: KycDocument[]
  policies: { id: number; policyNumber: string; product_id: number; status: number; policyActivatedDate: string | null; premium: string }[]
  activityLog: KycActivityLog[]
  sanctions: KycSanctions
  /** Which KYC flow the customer falls into. DOM_COM-only customers
   *  should be redirected to /kyc/dom-com/:customerId. */
  tier: KycTier
}

export async function fetchKycDetail(kycId: number): Promise<KycDetail> {
  const { data } = await apiClient.get<KycDetail>(`/kyc/${kycId}/detail`)
  return data
}

export interface RunSanctionsResult {
  success: boolean
  message: string
  maxScore: number
  status: string
  datasetsCount: number
  sanctioned: boolean
  kycCaseId: number
}

// Keyed on customer.id (NOT customer_kyc.id) — AML cases are per-customer.
export async function runOpenSanctionsCheck(customerId: number): Promise<RunSanctionsResult> {
  const { data } = await apiClient.post<RunSanctionsResult>(`/kyc/${customerId}/run-opensanctions`)
  return data
}

export async function verifyKycDocument(kycId: number, document: string, status: number, remark?: string, expiryDate?: string | null): Promise<void> {
  const body: Record<string, unknown> = { document, status, remark }
  if (expiryDate !== undefined) body.expiry_date = expiryDate
  await apiClient.post(`/kyc/${kycId}/verify-document`, body)
}

export interface EmployerGroupKycItem {
  id: number; employerGroupId: string; name: string; industry: string | null
  contactEmail: string | null; contactPhone: string | null; status: string | null
  noOfEmployees: number | null; createdAt: string | null; updatedAt: string | null
}
export interface EmployerGroupKycResponse { data: EmployerGroupKycItem[]; meta: ListMeta }

export async function fetchEmployerGroupKyc(filters: { search?: string; per_page?: number; page?: number } = {}): Promise<EmployerGroupKycResponse> {
  const { data } = await apiClient.get<EmployerGroupKycResponse>('/kyc/employer-group', { params: filters })
  return data
}

export interface DuplicateCustomerFilters { status?: string; duplicate_type?: string; search?: string; per_page?: number; page?: number }
export interface DuplicateCustomerItem {
  id: number; customerId: number; customerName: string | null; cellphone: string | null; email: string | null
  omangNumber: string | null; passportNumber: string | null; duplicateType: string | null
  duplicateReason: string | null; status: string | null; createdAt: string | null
}
export interface DuplicateCustomerResponse { data: DuplicateCustomerItem[]; meta: ListMeta }

export async function fetchDuplicateCustomers(filters: DuplicateCustomerFilters = {}): Promise<DuplicateCustomerResponse> {
  const { data } = await apiClient.get<DuplicateCustomerResponse>('/kyc/duplicates', { params: filters })
  return data
}

export interface DeduplicationFilters { status?: string; search?: string; per_page?: number; page?: number }
export interface DeduplicationItem {
  id: number; customerId: number; customerName: string | null; email: string | null; cellphone: string | null
  omangNumber: string | null; passportNumber: string | null; bankAccountNumber: string | null
  documentUploadStatus: string | null; verificationStatus: string | null; status: string | null
  isSuspended: boolean; createdAt: string | null
}
export interface DeduplicationResponse { data: DeduplicationItem[]; meta: ListMeta }

export async function fetchDeduplication(filters: DeduplicationFilters = {}): Promise<DeduplicationResponse> {
  const { data } = await apiClient.get<DeduplicationResponse>('/kyc/deduplication', { params: filters })
  return data
}

// ─── DOM/COM-tier KYC (products 7, 8, 16-19, 20, 22) ─────────────────
// Mirrors the MIS endpoints above, but the response model layers two
// underlying tables (customer_kyc + customer_kyc_dom_com) and includes
// the V8 BizSure sparse-document list (extraDocs).

export interface DomComKycListItem {
  id: number
  customerId: number
  customerName: string | null
  cellphone: string | null
  email: string | null
  compliance: number | null
  status: string | null
  remark: string | null
  hasKycForm: boolean
  hasCertificateIncorporation: boolean
  hasDirectorsIdFront: boolean
  hasShareholdersIdFront: boolean
  updatedAt: string | null
}
export interface DomComKycListResponse { data: DomComKycListItem[]; meta: ListMeta }

export async function fetchDomComKycList(filters: KycFilters = {}): Promise<DomComKycListResponse> {
  const { data } = await apiClient.get<DomComKycListResponse>('/dom-com-kyc', { params: filters })
  return data
}

export interface DomComKycDocument {
  key: string
  label: string
  table: 'kyc' | 'kyc_dom_com'
  uploaded: boolean
  url: string | null
  status: number   // 0=pending, 1=approved, 2=rejected
  remark: string | null
  /** Backend column that stores this doc's expiry (null when the doc
   *  doesn't have one). FE uses presence-of-`expiryField` to gate the
   *  date input on the card. */
  expiryField: string | null
  /** Current expiry value in YYYY-MM-DD, or null. */
  expiryDate: string | null
}

export interface DomComKycExtraDoc {
  id: number
  policyId: number | null
  docType: string
  docIndex: number | null
  url: string | null
  createdAt: string | null
}

export type DomComPolicyTier = 'DOMESTIC' | 'COMMERCIAL' | 'MIXED' | 'UNKNOWN'

export interface DomComKycDetail {
  customer: {
    id: number; name: string; firstName: string; lastName: string
    cellphone: string; email: string
    isBlocked: boolean; blockReason: string | null
    address: string | null; dob: string | null; gender: string | null
  }
  kyc: {
    id: number | null; omangNumber: string | null; passportNumber: string | null
    compliance: number | null; status: string | null; remark: string | null
    performedBy: string | null; approvedDate: string | null
    createdAt: string | null; updatedAt: string | null
  }
  kycDomCom: {
    id: number; customerKycId: number | null
    compliance: number | null; status: string | null; remark: string | null
    reason: string | null; approvedDate: string | null
    directorsIdExpiry: string | null; directorsPassportExpiry: string | null
    shareholdersIdExpiry: string | null; shareholdersPassportExpiry: string | null
  } | null
  documents: DomComKycDocument[]
  extraDocs: DomComKycExtraDoc[]
  policies: { id: number; policyNumber: string; product_id: number; productName: string | null; status: number; policyActivatedDate: string | null; premium: string }[]
  activityLog: KycActivityLog[]
  /** Whether the customer's DOM/COM policies are Domestic (individual
   *  KYC docs), Commercial (corporate KYC docs), or both. */
  policyTier: DomComPolicyTier
}

// All DOM/COM detail endpoints are keyed on customer_kyc.id (the KYC row
// PK), NOT customer.id — the server resolves the customer_kyc_dom_com
// sibling from that KYC row via its customer_kyc_id back-link.
export async function fetchDomComKycDetail(kycId: number): Promise<DomComKycDetail> {
  const { data } = await apiClient.get<DomComKycDetail>(`/dom-com-kyc/${kycId}/detail`)
  return data
}

export async function verifyDomComKycDocument(
  kycId: number,
  document: string,
  status: number,
  remark?: string,
  /** YYYY-MM-DD or empty string to clear. Ignored server-side for docs
   *  that don't have an expiry column. */
  expiryDate?: string,
): Promise<void> {
  await apiClient.post(`/dom-com-kyc/${kycId}/verify-document`, {
    document,
    status,
    remark,
    expiry_date: expiryDate,
  })
}

export async function updateDomComKycStatus(kycId: number, status: 'approved' | 'rejected', remark?: string): Promise<void> {
  await apiClient.post(`/dom-com-kyc/${kycId}/status`, { status, remark })
}
