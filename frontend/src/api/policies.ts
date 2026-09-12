import apiClient from './client'

export interface PolicyFilters {
  status?: 0 | 1 | 2 | 3
  draft?: 0 | 1
  product_id?: number
  exclude_products?: string
  agent_id?: number
  agency_id?: number
  payment_method?: string
  search?: string
  per_page?: number
  page?: number
  // DomCom-only — latest policy_action.status. One of:
  //   QUOTE / IN_APPROVAL / APPROVED / ISSUED / REJECTED
  action_status?: string
}

export interface PolicyMeta {
  total: number
  per_page: number
  current_page: number
  last_page: number
  from: number | null
  to: number | null
}

export interface PolicyListResponse {
  data: Policy[]
  meta: PolicyMeta
  links: {
    first: string
    last: string
    prev: string | null
    next: string | null
  }
}

export interface PolicyCustomer {
  id: number
  firstName: string
  middleName?: string
  lastName: string
  fullName: string
  email: string
  cellphone: string
}

export interface VehicleDocument {
  label: string
  url: string
  status?: string | number | null
}

export interface PolicyVehicle {
  id: number
  vehiclePlate: string
  chassisNo?: string
  odometer?: string
  purpose?: string
  condition?: string
  make: string
  model: string
  engineNo?: string
  seats?: number
  cylinders?: number
  year?: string
  colour?: string
  bodyType?: string
  fuelType?: string
  transmission?: string
  estimatedValue?: string
  registeredOwner?: string
  registrationNumber?: string
  antiTheftDevice?: string
  trackerDevice?: string
  trackerDeviceType?: string
  isImported: boolean
  claimCount?: string | number | null
  riskAddressName?: string | null
  riskAddressPhysical?: string | null
  approvalStatus?: string | null
  approvalComment?: string | null
  status?: number
  inspectionStatus?: string
  documents?: VehicleDocument[]
  /** Keyed inspection photos (CloudFront URLs, null when not uploaded) */
  images?: {
    front?: string | null
    back?: string | null
    right?: string | null
    left?: string | null
    registration?: string | null
    valuation?: string | null
  }
}

export interface PolicyMember {
  id: number
  relation: string
  firstName: string
  middleName?: string
  lastName: string
  dob?: string
  gender: string
  omang?: string
  passport?: string
  cellphone?: string
  email?: string
  payment?: number | string | null
  createdAt?: string
}

export interface PolicyBeneficiary {
  id: number
  relation: string
  firstName: string
  middleName?: string
  lastName: string
  dob?: string
  gender: string
  omang?: string
  passport?: string
  cellphone?: string
  email?: string
  payment?: number | string | null
  createdAt?: string
}

export interface PolicyDevice {
  id: number
  deviceType: string
  imei: string
  make: string
  model: string
  value?: string
  status?: number
  /** Pre-inspection photos (CloudFront URLs, null when not uploaded) */
  images?: {
    front?: string | null
    back?: string | null
    left?: string | null
    right?: string | null
    top?: string | null
    bottom?: string | null
    invoice?: string | null
  }
}

export interface PolicyAction {
  id: number; transactionType: string; status: string; policyQuoteNo?: string
  premium?: string; effectiveFrom?: string; effectiveTo?: string
  transactionReason?: string; note?: string; transactionDate?: string; createdAt?: string
  // Premium frequency AS AT THIS ACTION. `currentFrequencyId` is the raw
  // policy_actions.current_frequency_id stamp (null = this transaction did not
  // change the frequency); `frequencyId`/`frequencyLabel` are backend-resolved,
  // carrying the last stamped value forward along the action timeline and
  // falling back to policies.premium_freq.
  currentFrequencyId?: number | null
  frequencyId?: number | null
  frequencyLabel?: string | null
}

export interface DomComCoverageDetail {
  id: number; coverageValue?: string; rate?: string; calculatedValue?: string
  proRatePremium?: string; discountSurcharge?: string; coverageValueString?: string
}

export interface DomComCoverage {
  id: number; coverageId: number; coverageName: string; screenName?: string
  groupName?: string; coverageCode?: string; masterRate?: string
  riskAddressId?: number; riskAddressName?: string; riskAddress?: string
  rowType?: string; status?: string; details: DomComCoverageDetail[]
}

export interface PolicyCoverage {
  id: number
  vehicleId?: number
  main?: string
  value?: string
  discount?: string
  type?: string
}

export interface PolicyClaim {
  id: number
  claimNumber: string
  claimType: string
  status: string
  createdAt: string
}

export interface PolicyTransaction {
  id: number
  policyNumber?: string
  referenceNumber?: string
  paymentMethod?: string
  amount?: string
  paymentDate?: string
  settlementDate?: string
  installmentsPaid?: string | number | null
  note?: string
  status?: string
  cashRecipient?: string
  addedBy?: string
  isReversed?: boolean
  isRefunded?: boolean
}

export interface PolicyRiskAddress {
  id: number
  addressName?: string
  address?: string
  physical_address?: string
  risk_state?: number | string
  risk_city?: number | string
  state?: string
  city?: string
  zipCode?: string
  extension?: string
  occupation?: string
  town_class?: string
  risk_class?: string
  iso_rcv?: string | number
  year_built?: string
  area?: string
  structure_type?: string
  const_type?: string
  distance_to_water?: string
  distance_to_fire?: string
  distance_to_hydrant?: string
  usage?: string
  occupancy_type?: string
  central_fire?: boolean
  central_burglar?: boolean
  gated_community?: boolean
  automatic?: string
  company_id?: number
  lat?: string | number
  lng?: string | number
}

export interface PolicyKyc {
  compliance?: number
  complianceLabel?: string
  omang: boolean
  omangBack?: boolean
  passport: boolean
  passportBack?: boolean
  drivingLicense: boolean
  drivingLicenseBack?: boolean
  proofOfResidence: boolean
  proofOfIncome: boolean
  debitAuthorizationForm?: boolean
  bankStatement?: boolean
  vehicleRegistration?: boolean
}

export interface PolicyProfile {
  address?: string
  omang?: string
  dob?: string
  passport?: string
  maritalStatus?: string
  drivingLicense?: string
  licenseValidTill?: string
  city?: string
  state?: string
  // Raw FK ids for the Customer-tab edit form (State/District + City selects).
  cityId?: number
  stateId?: number
  gender?: string
  nationality?: string
  occupation?: string
  occupationLevel?: string
  country?: string
  incomeBracket?: string
  sourceOfIncome?: string
  sourceOfIncomeKey?: string
  sourceOfIncomeRaw?: string
  taxIdNumber?: string
  employerName?: string
  employeeNo?: string
  employerPhone?: string
  salaryPayDate?: string
  // Prominent/Influential Person (PEP) declarations.
  isPep?: boolean
  pepType?: string
  isPepRelated?: boolean
  pepRelationship?: string
  pepRelationshipSpecify?: string
  // High-risk country (AML watch-list) — customer's country is on the
  // Compliance > High Risk Countries list.
  isHighRiskCountry?: boolean
  highRiskCountryName?: string
}

export interface PolicyBanking {
  bankName?: string
  accountHolder?: string
  accountNumber?: string
  accountType?: string
  branchCode?: string
  billing?: string
  billingCell?: string
  cardType?: string
  cardHolderName?: string
  subscriptionId?: string
  orangeMoney?: string
  myzaka?: string
}

export interface KycDocument {
  field: string
  label: string
  hasFile: boolean
  url: string | null
  status: string | null
}

export interface KycDocumentsResponse {
  tier?: 'DOMG' | 'COMG' | 'MIS'
  compliance: number | null
  complianceLabel: string
  isMotor?: boolean
  documents: KycDocument[]
}

export interface Policy {
  id: number
  policyNumber: string
  status: number
  is_draft: boolean
  statusLabel: 'active' | 'in-active' | 'cancelled' | 'expired'
  premium: number | null
  firstPremium?: number | null
  sumAssured: number | null
  // POLICY-level frequency (policies.premium_freq). The frequency in force on a
  // single transaction is per-action - see PolicyAction.frequencyLabel.
  premiumFreq?: number | null
  premiumFreqLabel?: string
  vat?: number | null
  startDate: string | null
  endDate: string | null
  billingStartDate?: string | null
  policyActivatedDate?: string | null
  createdAt: string
  updatedAt?: string
  quoteNumber?: string | null
  activationCode?: string | null
  note?: string | null
  isBundled?: boolean
  bundledDiscountRate?: number | null
  bundledDiscountAmount?: number | null
  isReinstate?: boolean
  hasVehicle?: boolean
  hasMember?: boolean

  // Additional fields
  leadSource?: string | null
  policyType?: string | null
  agencyName?: string | null
  storeName?: string | null
  cancelledDate?: string | null
  cancelledBy?: string | null
  complianceLabel?: string | null
  billingType?: string | null
  policyCreatedBy?: string | null
  actionStatus?: string | null   // DomCom lifecycle: QUOTE | IN_APPROVAL | APPROVED | ISSUED | REJECTED | LAPSED

  // Relationships (loaded on main show)
  customer?: PolicyCustomer
  product?: { id: number; name: string; slug?: string; type?: string; hasVehicle?: boolean; hasMember?: boolean }
  plan?: { id: number; name: string; sumAssured?: number; premium?: number }
  agent?: { id: number; name: string; email: string }
  kyc?: PolicyKyc
  profile?: PolicyProfile
  banking?: PolicyBanking | null

  // These are NO LONGER loaded on main show — fetched lazily per tab
  vehicle?: PolicyVehicle | null
  vehicles?: PolicyVehicle[]
  members?: PolicyMember[]
  beneficiaries?: PolicyBeneficiary[]
  devices?: PolicyDevice[]
  coverages?: PolicyCoverage[]
  claims?: PolicyClaim[]
  transactions?: PolicyTransaction[]
  riskAddresses?: PolicyRiskAddress[]
}

// ── Core fetchers ──────────────────────────────────────────────

export async function fetchPolicies(filters: PolicyFilters = {}): Promise<PolicyListResponse> {
  const { data } = await apiClient.get<PolicyListResponse>('/policies', { params: filters })
  return data
}

export async function fetchPolicy(id: number): Promise<Policy> {
  const { data } = await apiClient.get<{ data: Policy }>(`/policies/${id}`)
  return data.data
}

// ── Lazy tab fetchers ──────────────────────────────────────────

export async function fetchPolicyVehicles(id: number, actionId?: number): Promise<{ data: PolicyVehicle[]; pendingApprovals: any[] }> {
  // action_id scopes the list to one policy action — vehicle rows are
  // replicated per action, so without it the backend defaults to the latest.
  const { data } = await apiClient.get<{ data: PolicyVehicle[]; pendingApprovals: any[] }>(`/policies/${id}/vehicles`, {
    params: actionId ? { action_id: actionId } : {},
  })
  return data
}

export async function fetchPolicyMembers(id: number): Promise<{ members: PolicyMember[]; beneficiaries: PolicyBeneficiary[] }> {
  const { data } = await apiClient.get<{ data: { members: PolicyMember[]; beneficiaries: PolicyBeneficiary[] } }>(`/policies/${id}/members`)
  return data.data
}

/** Hospital Cashback (product_id=9) co-applicant — distinct from the generic PolicyMember above; premium recalculates on every add/edit/remove. */
export interface PolicyCoApplicant {
  id: number
  relation: string
  first_name: string
  middle_name?: string | null
  last_name: string
  dob?: string | null
  gender: number | string | null
  omang?: string | null
  passport?: string | null
}

export async function fetchPolicyCoApplicants(id: number): Promise<{ data: PolicyCoApplicant[]; premium: number | string }> {
  const { data } = await apiClient.get<{ data: PolicyCoApplicant[]; premium: number | string }>(`/policies/${id}/coapplicants`)
  return data
}

export async function fetchPolicyDevices(id: number): Promise<PolicyDevice[]> {
  const { data } = await apiClient.get<{ data: PolicyDevice[] }>(`/policies/${id}/devices`)
  return data.data
}

export async function fetchPolicyCoverages(id: number, actionId?: number): Promise<{ type: string; data: any[]; riskAddresses?: any[] }> {
  const params: any = {}
  if (actionId) params.action_id = actionId
  const { data } = await apiClient.get(`/policies/${id}/coverages`, { params })
  return data as any
}

// `policyFrequency*` is the policy-level frequency derived from the LAST change
// on the action timeline (see PolicyAction::latestFrequency) — what the policies
// table should read, as opposed to whichever edit last touched the column.
export type PolicyActionsResponse = {
  current: PolicyAction | null
  history: PolicyAction[]
  hasFinancialActivity: boolean
  policyFrequencyId?: number | null
  policyFrequencyLabel?: string | null
}

// Frequency codes as stored in policies.premium_freq and
// policy_actions.current_frequency_id. Mirrors PolicyAction::frequencyLabel().
export const PREMIUM_FREQ_OPTIONS: { id: number; label: string }[] = [
  { id: 1, label: 'Monthly' },
  { id: 2, label: 'Three Installments' },
  { id: 3, label: 'Annual' },
  { id: 4, label: 'Semiannual' },
  { id: 5, label: 'Quarterly' },
  { id: 6, label: 'Manual Input' },
]

// Super-Admin-only: set the frequency on ONE action. `applyToPolicy` also writes
// policies.premium_freq. No other action row is touched. Backend:
// PolicyController::updateActionFrequency.
export async function updateActionFrequency(
  policyId: number,
  actionId: number,
  frequencyId: number,
  applyToPolicy: boolean,
): Promise<{ message: string; frequencyId: number; frequencyLabel: string; appliedToPolicy: boolean; policyFrequencyId: number }> {
  const { data } = await apiClient.patch(
    `/policies/${policyId}/actions/${actionId}/frequency`,
    { frequency_id: frequencyId, apply_to_policy: applyToPolicy },
  )
  return data
}

export async function fetchPolicyActions(id: number): Promise<PolicyActionsResponse> {
  const { data } = await apiClient.get<PolicyActionsResponse>(`/policies/${id}/actions`)
  return data
}

// Motor Traders Ext/Int — single row per policy_coverage with ~32 fields each.
// Backend lives at PolicyCreateController::{get,save}MotorTraders{External,Internal}.
// Null `data` is a legitimate response (coverage has no row yet); callers
// should treat that as "show empty form".
export type MotorTradersRow = Record<string, string | number | null> | null

export async function fetchMotorTradersExternal(policyId: number, coverageId: number): Promise<MotorTradersRow> {
  const { data } = await apiClient.get<{ data: MotorTradersRow }>(`/policies/${policyId}/coverages/${coverageId}/motor-traders-external`)
  return data?.data ?? null
}

export async function saveMotorTradersExternal(policyId: number, coverageId: number, payload: Record<string, any>): Promise<MotorTradersRow> {
  const { data } = await apiClient.put<{ data: MotorTradersRow }>(`/policies/${policyId}/coverages/${coverageId}/motor-traders-external`, payload)
  return data?.data ?? null
}

export async function fetchMotorTradersInternal(policyId: number, coverageId: number): Promise<MotorTradersRow> {
  const { data } = await apiClient.get<{ data: MotorTradersRow }>(`/policies/${policyId}/coverages/${coverageId}/motor-traders-internal`)
  return data?.data ?? null
}

export async function saveMotorTradersInternal(policyId: number, coverageId: number, payload: Record<string, any>): Promise<MotorTradersRow> {
  const { data } = await apiClient.put<{ data: MotorTradersRow }>(`/policies/${policyId}/coverages/${coverageId}/motor-traders-internal`, payload)
  return data?.data ?? null
}

export async function fetchPolicyClaims(id: number, page = 1) {
  const { data } = await apiClient.get(`/policies/${id}/claims`, { params: { page } })
  return data
}

export async function fetchPolicyTransactions(id: number, page = 1) {
  const { data } = await apiClient.get(`/policies/${id}/transactions`, { params: { page } })
  return data
}

export async function fetchPolicyRiskAddresses(id: number, termId?: number, actionId?: number): Promise<PolicyRiskAddress[]> {
  let url = `/policies/${id}/risk-addresses`
  const params = new URLSearchParams()
  if (termId) params.append('term_id', String(termId))
  if (actionId) params.append('action_id', String(actionId))
  if (params.toString()) url += `?${params.toString()}`
  
  const { data } = await apiClient.get<{ data: PolicyRiskAddress[] }>(url)
  return data.data
}

export async function fetchPolicyKycDocuments(id: number): Promise<KycDocumentsResponse> {
  const { data } = await apiClient.get<{ data: KycDocumentsResponse }>(`/policies/${id}/kyc-documents`)
  return data.data
}

// ── Banking documents (Bank Statement + Debit Authorization Form) ──
// Live on the Banking Details tab, stored on customer_banking.
export interface BankingDocument {
  field: string
  label: string
  hasFile: boolean
  url: string | null
  status: number // 0=pending, 1=approved, 2=rejected
  remark: string | null
}

export interface BankingDocumentsResponse {
  documents: BankingDocument[]
}

export async function fetchPolicyBankingDocuments(id: number): Promise<BankingDocumentsResponse> {
  const { data } = await apiClient.get<{ data: BankingDocumentsResponse }>(`/policies/${id}/banking-documents`)
  return data.data
}

// No Claims Declaration — MIS policies only, one document per policy.
// (Type/function names keep the pre-rename `ClaimsWaiver` spelling.)
export type ClaimsWaiverStatus = 'PENDING' | 'APPROVED' | 'REJECTED'

export interface ClaimsWaiverDocument {
  hasFile: boolean
  url: string | null
  name: string | null
  uploadedAt: string | null
  // Approval state. A document with no approval row (uploaded before approval
  // existed) reads as PENDING — the backend never reports it as approved.
  status: ClaimsWaiverStatus
  uploadedBy: number | null
  uploadedByName: string | null
  approvedBy: number | null
  approvedByName: string | null
  approvedAt: string | null
  rejectionReason: string | null
  // What the caller may do. Both are re-checked on every write by the backend;
  // these only decide which controls are rendered.
  mayUpload: boolean
  mayApprove: boolean
  // Name of the role that grants approval, and who currently holds it, so the
  // tab can say who to chase. Unbounded — any number of people may hold it.
  approverRole: string
  approvers: Array<{ id: number; name: string | null }>
  // Set-up gap (role missing / unassigned / table not migrated). Only sent to
  // users who could act on it.
  warning: string | null
  // Every upload and every decision, newest first. Kept after the document is
  // replaced or deleted, so the sign-off record cannot be erased. Optional
  // only for an API that predates it.
  history?: ClaimsWaiverHistoryEntry[]
  // OTP e-signature evidence for the CURRENT document. Null for a manually
  // uploaded scan. Optional only for an API that predates it.
  signature?: ClaimsWaiverSignature | null
  // What the "Generate via OTP" modal needs before it sends anything.
  otpSign?: ClaimsWaiverOtpMeta
}

/** How the current declaration was signed, when it was generated via OTP. */
export interface ClaimsWaiverSignature {
  method: 'OTP'
  cellphoneMasked: string
  signedAt: string | null
}

export interface ClaimsWaiverOtpMeta {
  available: boolean
  hasCellphone: boolean
  cellphoneMasked: string | null
  validitySeconds: number
  cooldownSeconds: number
}

/** One line of the approve/reject trail. */
export interface ClaimsWaiverHistoryEntry {
  id: number
  action: 'UPLOADED' | 'APPROVED' | 'REJECTED'
  status: ClaimsWaiverStatus
  fileName: string | null
  byId: number | null
  byName: string | null
  at: string | null
  reason: string | null
}

export async function fetchPolicyClaimsWaiver(id: number): Promise<ClaimsWaiverDocument> {
  const { data } = await apiClient.get<{ data: ClaimsWaiverDocument }>(`/policies/${id}/claims-waiver`)
  return data.data
}

/**
 * Approve or reject the No Claims Declaration. Approver-role holders only —
 * the backend returns 403 for everyone else, admins included.
 */
export async function decidePolicyClaimsWaiver(
  id: number,
  opts: { reject?: boolean; reason?: string } = {},
): Promise<{ message: string }> {
  const { data } = await apiClient.post<{ message: string }>(`/policies/${id}/claims-waiver/approve`, {
    reject: opts.reject ? 1 : 0,
    reason: opts.reason ?? '',
  })
  return data
}

// ── New lazy tab fetchers ──────────────────────────────────────

export interface SmsEmailLog {
  id: number
  type?: string
  recipient?: string
  message?: string
  htmlContent?: string
  messageId?: string
  hook?: string
  status?: string
  createdAt?: string
}

export interface ActivityLog {
  id: number
  activityBy?: string
  ipAddress?: string
  doneFrom?: string
  activityTag?: string
  url?: string
  oldValues?: unknown
  newValues?: unknown
  activityDone?: string
}

export interface PolicyLogsResponse {
  smsEmailLogs: SmsEmailLog[]
  activityLogs: ActivityLog[]
}

export interface LedgerAccountEntry {
  id: number
  accountingDate?: string
  transType?: string
  transRef?: string
  /** Credit Note rows only: the invoice number the note reverses. */
  creditedInvoiceNo?: string | null
  /** Credit Note rows only: "<invoice no>_<CR no>", as printed on the statement. */
  creditNoteRef?: string | null
  customerName?: string
  origTrans?: string
  unallocated?: string
  debit?: string
  credit?: string
  balance?: string
  systemDate?: string
}

export interface LedgerReceivableEntry {
  id: number
  accountingDate?: string
  transType?: string
  transSubType?: string
  transRef?: string
  effDate?: string
  debit?: string
  credit?: string
  balance?: string
}

export interface LedgerInvoiceEntry {
  id: number
  invoiceDate?: string
  invoiceNo?: string
  premium?: string
  otherCharges?: string
  dueAmount?: string
  balance?: string
  pmtsAdjust?: string
  invoiceAmount?: string
  dueDate?: string
  status?: string
  /** The credit note raised against this invoice, when there is one. */
  creditNote?: {
    id: number
    no?: string
    date?: string
    earned?: string | null
    unearned?: string | null
    fileUrl?: string | null
  } | null
}

/** One posted credit note, for the Ledger > Credit Notes tab. */
export interface LedgerCreditNoteEntry {
  id: number
  /** Credit note number (CRxxxxxx). */
  no?: string | null
  /** The invoice this note reverses. */
  invoiceId?: number | null
  invoiceNo?: string | null
  /** Posting date — the date the Statement of Account prints the note under. */
  date?: string | null
  /** When the note was actually raised (audit stamp). */
  createdAt?: string | null
  /** The credited period. */
  periodFrom?: string | null
  periodTo?: string | null
  noOfDays?: number | null
  earned?: string | null
  unearned?: string | null
  /** Value posted to the ledger for this note. */
  amount?: string | null
  fileUrl?: string | null
}

export interface LedgerSubEntry {
  id: number
  systemDate?: string
  transType?: string
  transRef?: string
  accountName?: string
  debit?: string
  credit?: string
}

export interface LedgerResponse {
  totalDues: string
  accountView: LedgerAccountEntry[]
  receivableView: LedgerReceivableEntry[]
  invoicing: LedgerInvoiceEntry[]
  creditNotes: LedgerCreditNoteEntry[]
  subLedger: LedgerSubEntry[]
  // subLedger is capped server-side (a few policies have 100k+ rows). These let
  // the UI show "showing latest N of M" instead of silently truncating.
  subLedgerTotal?: number
  subLedgerCapped?: boolean
}

export interface ScheduleTransaction {
  id: number
  installment?: number
  amount?: string | number | null
  billingDate?: string
  status?: string
  statusCode?: number
  retryCount?: number
  reason?: string
  paymentMethod?: string
  email?: string
  createdAt?: string
}

// ─── Mati / MetaMap verification (V8 "Mati Verification" tab parity) ─────────
export interface MatiDocument {
  type?: string | null
  fullName?: string | null
  dateOfBirth?: string | null
  firstName?: string | null
  surname?: string | null
  sex?: string | null
  documentNumber?: string | null
  expirationDate?: string | null
  issueCountry?: string | null
  nationality?: string | null
}
export interface MatiLocation {
  country?: string | null
  region?: string | null
  city?: string | null
  zip?: string | null
}
export interface MatiDevice {
  deviceType?: string | null
  os?: string | null
  browser?: string | null
  ip?: string | null
}
export interface MatiImages {
  passportUrl?: string | null
  omangUrl?: string | null
  omangBackUrl?: string | null
  drivingLicenseUrl?: string | null
  proofResidenceUrl?: string | null
  selfieUrl?: string | null
}
export interface MatiVerification {
  id: number
  matiId?: string | null
  identityId?: string | null
  verificationId?: string | null
  status?: string | null
  hasFetchData?: boolean
  documents?: MatiDocument[]
  location?: MatiLocation | null
  device?: MatiDevice | null
  images?: MatiImages
  createdAt?: string | null
  updatedAt?: string | null
  // ── Deprecated flat fields (kept optional for the legacy Customer-tab card;
  //    the structured fields above are the source of truth on the Mati tab). ──
  eventName?: string | null
  identityStatus?: string | null
  documentType?: string | null
  fullName?: string | null
  dateOfBirth?: string | null
  documentNumber?: string | null
  country?: string | null
}

export interface PolicyAttachment {
  id: string | number
  name?: string
  type?: string
  category?: 'attachment' | 'policy_document' | 'kyc'
  url?: string
  description?: string
  createdAt?: string
}

// ─── V8 Attachment-tab row (one row per policy_attachments record) ─────────
export interface PolicyAttachmentFile {
  name: string
  url: string
  extension: string
  kind: 'image' | 'pdf' | 'word' | 'excel' | 'other'
}
export interface PolicyAttachmentRow {
  id: number
  name: string
  type: string
  files: PolicyAttachmentFile[]
  createdAt?: string
  updatedAt?: string
}

export async function fetchPolicyAttachmentList(id: number): Promise<PolicyAttachmentRow[]> {
  const { data } = await apiClient.get<{ data: PolicyAttachmentRow[] }>(`/policies/${id}/attachment-list`)
  return data.data
}

export interface FileTypeOption { id: string; name: string }
export async function fetchPolicyFileTypes(): Promise<FileTypeOption[]> {
  const { data } = await apiClient.get<{ data: FileTypeOption[] }>(`/policies/lookups/file-types`)
  return data.data
}

export interface PolicyTerm {
  id: number
  termNumber?: string | number
  startDate?: string
  endDate?: string
  premium?: string | number
  status?: string | number
  createdAt?: string
}

export async function fetchPolicyLogs(id: number): Promise<PolicyLogsResponse> {
  const { data } = await apiClient.get<{ data: PolicyLogsResponse }>(`/policies/${id}/logs`)
  return data.data
}

/**
 * Download the full (unpaginated) Activity Log for a policy as .xlsx,
 * triggering a browser save. Unlike the on-screen tab (capped at 200 rows),
 * the export streams the entire history — needed for audit/investigation.
 */
export async function exportPolicyActivityLog(id: number, policyNumber?: string): Promise<void> {
  const response = await apiClient.get(`/policies/${id}/logs/export`, {
    responseType: 'blob',
    headers: { Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' },
    timeout: 120_000,
  })

  const blob = response.data as Blob
  const disposition = (response.headers['content-disposition'] ?? '') as string
  const match = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/)
  const filename = match ? match[1].replace(/['"]/g, '') : `PolicyActivityLog_${policyNumber ?? id}.xlsx`

  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = filename
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(link.href)
}

export async function fetchPolicyLedger(id: number, actionId?: number): Promise<LedgerResponse> {
  const url = actionId ? `/policies/${id}/ledger?action_id=${actionId}` : `/policies/${id}/ledger`
  const { data } = await apiClient.get<{ data: LedgerResponse }>(url)
  return data.data
}

export async function fetchPolicyScheduleTransactions(id: number): Promise<ScheduleTransaction[]> {
  const { data } = await apiClient.get<{ data: ScheduleTransaction[] }>(`/policies/${id}/schedule-transactions`)
  return data.data
}

/**
 * GRA-0194 — cancel the LIVE DPO mandate for a migrated-but-active policy
 * (DPO→RealPay double-debit fix). Stops DPO deducting; does NOT cancel the
 * policy. Gated server-side by permission:policy-suspend_payment_dpo.
 */
export async function cancelPolicyDpoContract(id: number): Promise<{ success: boolean; message: string }> {
  const { data } = await apiClient.post<{ success: boolean; message: string }>(`/policies/${id}/cancel-dpo-contract`)
  return data
}

export async function fetchPolicyMati(id: number): Promise<MatiVerification | null> {
  const { data } = await apiClient.get<{ data: MatiVerification | null }>(`/policies/${id}/mati`)
  return data.data
}

/** Send the MetaMap (Mati) KYC verification link to the customer (email/sms). */
export async function sendMatiVerificationLink(id: number, type: 'email' | 'sms'): Promise<{ message: string }> {
  const { data } = await apiClient.post<{ message: string }>(`/policies/${id}/mati/verification-link/${type}`)
  return data
}

/** Pull the latest verification data from the Mati API for the existing IDs. */
export async function fetchMatiData(id: number): Promise<{ message: string }> {
  const { data } = await apiClient.post<{ message: string }>(`/policies/${id}/mati/fetch`)
  return data
}

/** Set/replace the customer's Mati identity_id + verification_id. */
export async function updateMatiData(id: number, identityId: string, verificationId: string): Promise<{ message: string }> {
  const { data } = await apiClient.post<{ message: string }>(`/policies/${id}/mati/update`, {
    identity_id: identityId,
    verification_id: verificationId,
  })
  return data
}

export async function fetchPolicyAttachments(id: number): Promise<PolicyAttachment[]> {
  const { data } = await apiClient.get<{ data: PolicyAttachment[] }>(`/policies/${id}/attachments`)
  return data.data
}

export async function fetchPolicyTerms(id: number): Promise<PolicyTerm[]> {
  const { data } = await apiClient.get<{ data: PolicyTerm[] }>(`/policies/${id}/terms`)
  return data.data
}

// ─── Reinsurance ────────────────────────────────────────────────────────────

export interface PolicyReinsuranceRecord {
  riskAddress: string | null
  groupCode: string | null
  formulaName: string | null
  totalSumInsured: string
  totalPremium: string
  netRetention: string
  netRetentionSI: string
  quotaShare: string
  quotaShareSI: string
  surplus: string
  surplusSI: string
  facultative: string
  facultativeSI: string
  facPlacement: string
  facPlacementSI: string
}

export async function fetchPolicyReinsurance(id: number): Promise<PolicyReinsuranceRecord[]> {
  const { data } = await apiClient.get<{ data: PolicyReinsuranceRecord[] }>(`/policies/${id}/reinsurance`)
  return data.data
}

// ─── Specialist / Engineering / Marine coverages ─────────────────────────────

export interface SpecialistCoverageGroup {
  label: string
  table: string
  rows: Record<string, any>[]
}

export async function fetchPolicySpecialistCoverages(id: number): Promise<SpecialistCoverageGroup[]> {
  const { data } = await apiClient.get<{ data: SpecialistCoverageGroup[] }>(`/policies/${id}/specialist-coverages`)
  return data.data
}

export async function fetchSpecialistCoverageByType(
  policyId: number,
  type: string,
  filters?: { policy_coverage_id?: number },
): Promise<{ data: Record<string, any>[]; label: string }> {
  const { data } = await apiClient.get<{ data: Record<string, any>[]; label: string }>(
    `/policies/${policyId}/specialist-coverages/${type}`,
    { params: filters },
  )
  return data
}

export async function createSpecialistCoverage(
  policyId: number,
  type: string,
  payload: Record<string, any>,
): Promise<{ message: string; id: number }> {
  const { data } = await apiClient.post<{ message: string; id: number }>(
    `/policies/${policyId}/specialist-coverages/${type}`,
    payload,
  )
  return data
}

export async function updateSpecialistCoverage(
  policyId: number,
  type: string,
  recordId: number,
  payload: Record<string, any>,
): Promise<{ message: string }> {
  const { data } = await apiClient.put<{ message: string }>(
    `/policies/${policyId}/specialist-coverages/${type}/${recordId}`,
    payload,
  )
  return data
}

export async function deleteSpecialistCoverage(
  policyId: number,
  type: string,
  recordId: number,
): Promise<{ message: string }> {
  const { data } = await apiClient.delete<{ message: string }>(
    `/policies/${policyId}/specialist-coverages/${type}/${recordId}`,
  )
  return data
}


// ─── Collect Now (Pay Now) ────────────────────────────────────────────────────

export interface CollectNowResult {
  success: boolean
  method: 'DPO' | 'REALPAY' | 'NONE'
  reference: string | null
  message: string
  /** Present only when specific outstanding premiums were collected. */
  premiums_collected?: number
  total_amount?: number
  schedule_ids?: number[]
}

export interface CollectNowEvent {
  id: number
  payment_method: 'DPO' | 'REALPAY'
  amount: number | string
  status: 'pending' | 'success' | 'failed'
  gateway_reference: string | null
  failure_reason: string | null
  created_at: string
  triggered_by_name: string | null
  /** How many outstanding premiums the debit covered (null on legacy rows). */
  premium_count?: number | null
}

export interface CollectNowHistoryResponse {
  data: CollectNowEvent[]
  total: number
}

/** One outstanding premium installment, selectable in the Collect Now tab. */
export interface OutstandingPremium {
  id: number
  installment: number | null
  amount: number
  billingDate: string | null
  status: string
  statusCode: number
  retryCount: number | null
  reason: string | null
  paymentMethod: string | null
  /** false = a future installment, i.e. collecting early. */
  isDue: boolean
}

export interface CollectNowOutstandingResponse {
  policy: {
    id: number
    policyNumber: string
    premium: number
    isMis: boolean
    /** Multi-premium selection is MIS-only. */
    selectionSupported: boolean
  }
  data: OutstandingPremium[]
  totals: { count: number; amount: number; due: number }
}

/** What the confirmation dialog sends when specific premiums were ticked. */
export interface CollectNowSelection {
  schedule_ids: number[]
  /** The total the operator saw. The server refuses if its own sum differs. */
  expected_amount: number
  /** Explicit acknowledgement — the server rejects the request without it. */
  confirmed: true
}

/**
 * Trigger immediate payment collection for a policy.
 *
 * With no `selection` this debits the single policy premium (original
 * behaviour). With a selection it debits the SUM of the ticked outstanding
 * premiums as one charge and settles each installment.
 */
export async function triggerCollectNow(
  policyId: number,
  selection?: CollectNowSelection,
): Promise<CollectNowResult> {
  const { data } = await apiClient.post<CollectNowResult>(
    `/policies/${policyId}/collect-now`,
    selection,
  )
  return data
}

/** Outstanding premium installments available to collect for a policy. */
export async function fetchCollectNowOutstanding(
  policyId: number,
): Promise<CollectNowOutstandingResponse> {
  const { data } = await apiClient.get<CollectNowOutstandingResponse>(
    `/policies/${policyId}/collect-now/outstanding`,
  )
  return data
}

/** Fetch the collection event history for a policy. */
export async function fetchCollectNowHistory(policyId: number): Promise<CollectNowHistoryResponse> {
  const { data } = await apiClient.get<CollectNowHistoryResponse>(`/collect-now/history/${policyId}`)
  return data
}

// ─── Policy wording documents (old edit page Documents tab parity) ─────

export interface WordingDocument { id: number; name: string; url: string }

/**
 * Wording documents for the policy's product + plan (plan-specific rows from
 * the documents table win, falling back to product-wide, plus global docs).
 */
export async function fetchPolicyWordingDocs(policyId: number): Promise<WordingDocument[]> {
  const { data } = await apiClient.get<{ data: WordingDocument[] }>(`/policies/${policyId}/wording-documents`)
  return data.data
}

// ─── Assign Agent tab (old policy edit page parity) ────────────────────

export interface AssignAgentOption { id: number; name: string }
export interface AssignAgentData {
  agents: AssignAgentOption[]
  stores: AssignAgentOption[]
  agentId: number | null
  storeId: number | null
}

/** Agents + stores lists plus the policy's current assignment. */
export async function fetchAssignAgentData(policyId: number): Promise<AssignAgentData> {
  const { data } = await apiClient.get<{ data: AssignAgentData }>(`/policies/${policyId}/assign-agent`)
  return data.data
}

/** Update the policy's assigned agent and store. */
export async function updateAssignAgent(policyId: number, payload: { agent_id: number | null; store_id: number | null }) {
  const { data } = await apiClient.post(`/policies/${policyId}/assign-agent`, payload)
  return data
}

// ─── Endorse / Renew / Reinstate ───────────────────────────────────────
// Each of these creates a new policy_actions row (transaction_type ENDORSE
// / RENEW / REINSTATE) on top of the existing issued policy. After the
// call the UI should send the user into the edit wizard to adjust
// coverages → rate → submit → approve → issue. Backend handles the
// period defaults: endorse carries the current end date; renew adds a
// year from the current end; reinstate defaults to today + 1 year.

export interface PolicyActionPayload {
  effective_from?: string       // YYYY-MM-DD
  effective_to?: string         // YYYY-MM-DD (renew/reinstate only)
  transaction_reason?: string
  note?: string
}

export interface ReinstatePayload extends PolicyActionPayload {
  reinstate_type: 'reinstate_fresh' | 'Reinstate_arrears'
}

export interface PolicyActionResult {
  message?: string
  data?: unknown
}

export async function endorsePolicy(
  policyId: number,
  payload: PolicyActionPayload = {},
): Promise<PolicyActionResult> {
  const { data } = await apiClient.post<PolicyActionResult>(`/policies/${policyId}/endorse`, payload)
  return data
}

export async function renewPolicyCreate(
  policyId: number,
  payload: PolicyActionPayload = {},
): Promise<PolicyActionResult> {
  const { data } = await apiClient.post<PolicyActionResult>(`/policies/${policyId}/renew-create`, payload)
  return data
}

export async function reinstatePolicyCreate(
  policyId: number,
  payload: ReinstatePayload,
): Promise<PolicyActionResult> {
  const { data } = await apiClient.post<PolicyActionResult>(`/policies/${policyId}/reinstate-create`, payload)
  return data
}

export async function generateRenewalLink(
  policyId: number,
): Promise<{ url: string; expires_at?: string }> {
  const { data } = await apiClient.post<{ url: string; expires_at?: string }>(`/policies/${policyId}/renewal-link`)
  return data
}

// Legacy v1 type — preserved for any caller still on the narrow endpoint.
// The widget now consumes ClientHealth (the richer payload) below.
export interface PolicyHealthSummary {
  balanceOwing: {
    realpayUnpaid: number
    ledgerNet: number
  }
  daysInArrears: number
  collectionStatus: {
    key: 'active' | 'failed' | 'inactive' | 'none' | 'cancelled' | 'in_arrears'
    label: string
  }
  activeClaims: {
    count: number
  }
}

export async function fetchPolicyHealthSummary(id: number): Promise<PolicyHealthSummary> {
  const { data } = await apiClient.get<{ data: PolicyHealthSummary }>(`/policies/${id}/health-summary`)
  return data.data
}

// Full Client Health Widget payload — payment-method aware. Backed by
// ClientHealthService::summarise on the backend.
export type CollectionStatusKey = 'active' | 'failed' | 'in_arrears' | 'cancelled' | 'none'
export type BannerSeverity      = 'critical' | 'warning' | 'info' | 'success'
export type PaymentBucket       = 'RealPay' | 'DPO' | 'Manual'

export interface PolicyClientHealth {
  paymentMethod: {
    method: string          // raw label: RealPay / DPO / VCS / CASH / Manual / etc.
    bucket: PaymentBucket   // normalised — drives FE rendering
    source: 'contract' | 'banking' | 'transaction' | 'default'
  }
  balanceOwing: {
    headline: number          // worst-of-two value rendered as the big number
    realpayUnpaid: number | null  // null when not on RealPay
    ledgerNet: number         // always present (universal fallback)
    source: 'realpay' | 'ledger'
  }
  daysInArrears: number
  collectionStatus: {
    key: CollectionStatusKey
    label: string
  }
  activeClaims: {
    count: number
    totalReserve: number
    totalPayment: number
  }
  banner: {
    severity: BannerSeverity
    message: string
  }
}

export async function fetchPolicyClientHealth(id: number): Promise<PolicyClientHealth> {
  const { data } = await apiClient.get<{ data: PolicyClientHealth }>(`/policies/${id}/client-health`)
  return data.data
}

/**
 * OTP e-signature for the No Claims Declaration. `send` SMSes a code to the
 * customer's registered number (read server-side — the client never supplies
 * it); `verify` checks the code the agent keyed in and, on success, generates
 * the signed PDF as the current declaration (pending approval).
 */
export async function sendClaimsWaiverOtp(
  id: number,
): Promise<{ message: string; data: { cellphoneMasked: string; expiresAt: string; validitySeconds: number } }> {
  const { data } = await apiClient.post(`/policies/${id}/claims-waiver/otp/send`)
  return data
}

export async function verifyClaimsWaiverOtp(
  id: number,
  code: string,
): Promise<{ message: string; data: { url: string | null; name: string } }> {
  const { data } = await apiClient.post(`/policies/${id}/claims-waiver/otp/verify`, { code })
  return data
}
