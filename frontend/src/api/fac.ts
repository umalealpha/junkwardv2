import apiClient from './client'
import { downloadFromApi } from '../utils/download'
import type { ListMeta } from './kyc'

// ─── Types ────────────────────────────────────────────────────────────────────

/** The flags that must be visible on the row, not buried in a detail screen. */
export type FacFlag =
  | 'policy_not_in_graphite'
  | 'policy_not_active'
  | 'fx_rate_missing'
  | 'ppw_breached'
  | 'ppw_not_set'
  | 'policy_period_over_treaty_max'
  | 'named_group_exception'

export type FacStatus =
  | 'draft'
  | 'placed'
  | 'awaiting_premium'
  | 'client_paid'
  | 'ready_to_settle'
  | 'settled'
  | 'cancelled'

/** The working cycle, not the record state. Four stages plus one dead end. */
export type FacStage =
  | 'awaiting_signature'
  | 'awaiting_premium'
  | 'ready_to_settle'
  | 'complete'
  | 'cancelled'

export interface FacPlacement {
  id: number
  facReference: string
  facSlipNo: string | null
  financialYear: string | null
  placementType: 'fac' | 'auto_fac'
  placementTypeLabel: string
  policyId: number | null
  policyNumber: string
  insuredName: string | null
  policyType: string | null
  periodFrom: string | null
  periodTo: string | null
  policyStatus: string | null
  riGroupLabel: string | null
  cessionSumInsured: number | null
  /** Full gross premium received on the policy — the basis the ceded share was worked out from. */
  sourcePremium: number | null
  riskPct: number | null
  counterpartyId: number | null
  counterpartyName: string | null
  riskCarrier: string | null
  currency: string
  grossCededPremium: number | null
  commissionPct: number | null
  commissionAmount: number | null
  netCededPremium: number | null
  vatApplicable: boolean
  vatRate: number | null
  grossExclVat: number | null
  /** The VAT carried inside the captured gross. Derived server-side. */
  vatAmount: number | null
  /** NULL for a foreign-currency line with no rate — never silently treated as Pula. */
  payableBwp: number | null
  fxRate: number | null
  fxRateDate: string | null
  fxRateSource: string | null
  slipSignedDate: string | null
  ppwDueDate: string | null
  /** Days from signing by which the client premium must reach us. */
  ppwDays: number | null
  /** The window as the slip words it — "90 days", "Monthly", "Quarterly". */
  ppwTerms: string | null
  /** How the ceded premium is paid. NOT the payment warranty — both can apply. */
  premiumFrequency: string | null
  underwriterName: string | null
  status: FacStatus
  /**
   * Where the placement sits in the cycle, derived server-side from the money
   * events rather than read off `status` — two statuses can describe the same
   * stage of work. This is the field that answers "what is outstanding?".
   */
  stage: FacStage
  stageLabel: string
  /** 1-4 along the ladder; 0 for cancelled, which is off it rather than at the end. */
  stageStep: number
  stageOf: number
  clientPaidAt: string | null
  clientPaidSource: string | null
  settlementDueDate: string | null
  settledAt: string | null
  settlementReference: string | null
  isReversal: boolean
  source: 'manual' | 'import' | 'auto'
  slipGeneratedAt: string | null
  slipSentAt: string | null
  flags: FacFlag[]
}

export interface FacTotals {
  /** Drafts are NOT in here — nothing is owed until the reinsurer signs. */
  payableBwp: number
  /** Placements captured but not yet signed by the reinsurer. */
  draftCount: number
  awaitingPremium: number
  readyToSettle: number
  rateMissing: number
  unmatchedPolicy: number
  ppwBreached: number
  lineCount: number
}

export interface FacListResponse {
  data: FacPlacement[]
  totals: FacTotals
  meta: ListMeta
}

export interface FacFilters {
  search?: string
  status?: string
  placement_type?: 'fac' | 'auto_fac'
  counterparty_id?: number
  financial_year?: string
  currency?: string
  flag?: string
  stage?: FacStage
  sort?: 'oldest' | 'newest'
  page?: number
  per_page?: number
}

export interface FacAttachment {
  id: number
  docType: 'client_pop' | 'ri_payment_advice' | 'fac_slip' | 'other'
  originalName: string
  paymentDate: string | null
  amount: number | null
  note: string | null
  uploadedBy: string | null
  uploadedAt: string | null
}

/**
 * One field an amendment moved, as it was recorded at the time.
 *
 * `from` and `to` arrive already formatted — a record that is re-derived at
 * display time can be re-derived differently later, so the screen shows what was
 * written and does not reformat it. NULL means the field was blank on that side.
 */
export interface FacEventChange {
  field: string
  label: string
  from: string | null
  to: string | null
}

export interface FacEvent {
  id: number
  event: string
  summary: string | null
  /** Who was told. Recorded whether or not the email actually went. */
  notifiedTo: string | null
  sent: boolean
  error: string | null
  actor: string | null
  at: string | null
  /** Field-by-field before/after. Empty for events that are not amendments. */
  changes: FacEventChange[]
}

export interface FacReceipts {
  received: number
  paid: number
  reversed: number
  refunded: number
  lastPaymentAt: string | null
  hasReceipt: boolean
}

export type FacCoverageVerdict =
  | 'policy_not_in_graphite'
  | 'no_fac_required'
  | 'placed_without_requirement'
  | 'fac_required_none_placed'
  | 'fac_under_placed'
  | 'fac_over_placed'
  | 'covered'

export interface FacCoverage {
  policyId: number | null
  policyNumber: string
  requiresFac: boolean
  requiredPremium: number
  requiredSumInsured: number
  placedPremium: number
  placedSumInsured: number
  placedCount: number
  gap: number
  verdict: FacCoverageVerdict
  verdictLabel: string
  policyStatus?: number
}

export interface FacPlacementDetail extends FacPlacement {
  notes: string | null
  attachments: FacAttachment[]
  events: FacEvent[]
  receipts?: FacReceipts
  coverage?: FacCoverage
  participants?: FacParticipants
  /** What is actually insured, itemised as the signed slips itemise it. */
  schedule?: FacSchedule
  /** Treaty conditions this placement trips. Reported, never blocking. */
  mandates?: FacMandateFinding[]
  /** The policy period in months. Null where a date is missing — unknown, not zero. */
  policyMonths?: number | null
}

/**
 * A treaty condition the placement trips, with the document it comes from.
 *
 * `authority` is not decoration. A finding an underwriter cannot trace back to a
 * slip is one they will overrule, so every finding names its source.
 */
export interface FacMandateFinding {
  code: string
  severity: 'info' | 'warn' | 'danger'
  title: string
  detail: string
  authority: string
}

/** The blocks a slip prints its schedule in, in the order the signed slips use. */
export const FAC_SCHEDULE_SECTIONS = [
  { key: 'fire', title: 'FIRE AND ALLIED PERILS' },
  { key: 'business_interruption', title: 'BUSINESS INTERRUPTION' },
] as const

export type FacScheduleSection = typeof FAC_SCHEDULE_SECTIONS[number]['key']

/**
 * One line of the schedule.
 *
 * `amount` NULL is meaningful and is not zero. "Indemnity period – 15 months" is a
 * real line of the business interruption block that states a term rather than a sum
 * insured, and it is excluded from the totals rather than added as nil.
 */
export interface FacScheduleLine {
  id: number
  label: string
  amount: number | null
  sortOrder: number
}

export interface FacScheduleSectionData {
  section: FacScheduleSection
  title: string
  lines: FacScheduleLine[]
  subtotal: number
}

/**
 * What is insured, itemised.
 *
 * `totalLimitsOfIndemnity` is DERIVED from the lines, never stored — the signed
 * slips prove the two are the same figure, so a stored total could only ever drift
 * from the schedule it totals.
 */
export interface FacSchedule {
  sections: FacScheduleSectionData[]
  totalLimitsOfIndemnity: number
  lineCount: number
}

/** One line as the save sends it. `amount` omitted means the line states none. */
export interface FacScheduleInput {
  section: FacScheduleSection
  label: string
  amount?: number | null
}

export interface FacBordereauFilters {
  period_from?: string
  period_to?: string
  financial_year?: string
  counterparty_id?: number
  placement_type?: 'fac' | 'auto_fac'
  currency?: string
}

export interface FacBordereauLine {
  facReference: string
  placementType: string
  slipNo: string | null
  policyNumber: string | null
  insuredName: string | null
  riGroupLabel: string | null
  periodFrom: string | null
  periodTo: string | null
  slipSignedDate: string | null
  riskCarrier: string | null
  riskPct: number | null
  cessionSumInsured: number | null
  currency: string | null
  grossCededPremium: number | null
  premiumExclVat: number | null
  commissionPct: number | null
  commission: number | null
  commissionExclVat: number | null
  netCededPremium: number | null
  isReversal: boolean
}

export interface FacBordereauTotals {
  lineCount: number
  cessionSumInsured: number
  grossCededPremium: number
  premiumExclVat: number
  commission: number
  commissionExclVat: number
  netCededPremium: number
}

/**
 * The cession bordereau.
 *
 * One group per reinsurer PER CURRENCY — a reinsurer written in two currencies gets
 * two sections, because a total across currencies is a total in no currency.
 * Reversals sit outside the groups and are not netted into any subtotal.
 */
export interface FacBordereau {
  header: {
    cedant: string
    statement: string
    periodFrom: string | null
    periodTo: string | null
    financialYear: string | null
    placementType: string | null
    preparedAt: string
    basis: string
  }
  groups: Array<{
    counterpartyId: number | null
    counterpartyName: string
    currency: string
    lines: FacBordereauLine[]
    subtotals: FacBordereauTotals
  }>
  reversals: FacBordereauLine[]
  grandTotal: FacBordereauTotals
  /**
   * Why the bordereau is empty — null whenever it has lines.
   *
   * A nil bordereau and a broken one looked identical, and the module was
   * reported as not recording placements on the strength of one. It was: the
   * period defaults to the current month and is tested on the SIGNING date, so
   * slips signed earlier fall outside it. `withoutSignedDate` is the count no
   * wider window will ever surface — those need the signed slip filed.
   */
  emptyReason: {
    signedOutsidePeriod: number
    withoutSignedDate: number
    drafts: number
    cancelled: number
    signedRange: { from: string | null; to: string | null }
    reasons: string[]
    summary: string
  } | null
}

/**
 * One reinsurer on the slip's acceptance panel.
 *
 * `committed` is the field to read, not `sharePct`. A panel row exists from the
 * moment the slip is generated, but it only means the reinsurer is on risk once a
 * signatory and a date are against it — a slip that has been sent and not signed
 * is not cover.
 */
export interface FacAcceptance {
  id: number
  reinsurerId: number | null
  acceptingCompany: string
  sharePct: number | null
  amount: number | null
  signatoryName: string | null
  acceptedOn: string | null
  committed: boolean
}

/** One placement line on the slip — who the risk was placed with. */
export interface FacParticipantLine {
  id: number
  facReference: string
  counterpartyId: number | null
  counterpartyName: string | null
  riskCarrier: string | null
  riskPct: number | null
  cessionSumInsured: number | null
  grossCededPremium: number | null
  currency: string | null
  status: string
}

/**
 * Who is on a placement, on both bases Reinsurance asked for.
 *
 * `lines` is who we placed it with — present from capture. `acceptances` is who
 * signed — present only once a slip exists, and only meaningful where `committed`.
 * The two are kept apart deliberately: showing one list would imply a placement is
 * covered when nobody has signed.
 */
export interface FacParticipants {
  basis: 'fac' | 'auto_fac'
  slipNo: string | null
  slipId: number | null
  slipVersion: number | null
  slipStatus: string | null
  lines: FacParticipantLine[]
  acceptances: FacAcceptance[]
  totals: {
    lineCount: number
    lineSharePct: number
    acceptanceCount: number
    committedCount: number
    acceptedPct: number
    /** Whether the panel adds up to the placed share. Reported, never enforced. */
    reconciles: boolean
    fullyCommitted: boolean
  }
}

export interface FacSummaryRow {
  placementType: 'fac' | 'auto_fac'
  placementTypeLabel: string
  currency: string
  counterpartyId: number | null
  counterpartyName: string | null
  premiumExclVat: number
  commissionExclVat: number
  payable: number
  payableBwp: number
  openPayable: number
  priorPayable: number
  change: number
  lineCount: number
  rateMissingCount: number
}

export interface FacSummaryResponse {
  rows: FacSummaryRow[]
  totals: {
    premiumExclVat: number
    commissionExclVat: number
    payable: number
    priorPayable: number
    change: number
    lineCount: number
    rateMissing: number
  }
  priorPeriodEnd: string | null
}

export interface FacVarianceResponse {
  periodEnd: string
  available: boolean
  message?: string
  registerPremium: number
  registerCommission: number
  glPremium?: number
  glCommission?: number | null
  glSource?: string | null
  glAsAt?: string | null
  premiumVariance?: number
  commissionVariance?: number | null
  journalToPass?: {
    premium: number
    commission: number | null
    note: string
    /**
     * The five classified lines Finance posts — their SUMMARY sheet's "Entries
     * to Pass", which was kept by hand in Excel because only the two variance
     * figures above were produced here.
     *
     * `available` is false where no ledger commission has been captured: three
     * of the five lines derive from it and a journal missing them would not
     * balance. `control` is their CONTROL CHECK and must be zero.
     */
    lines?:
      | { available: false; message: string }
      | {
          available: true
          vatRate: number
          control: number
          balanced: boolean
          /**
           * The two ends of each movement. Reinsurance check the entry by
           * comparing last month's balance to this month's, so both are
           * reported rather than only the difference.
           */
          balances: {
            receivable: { prior: number; current: number; movement: number }
            payable: { prior: number; current: number; movement: number }
          }
          lines: Array<{
            classification: string
            account: string
            amount: number
            drCr: 'DR' | 'CR'
            signed: number
          }>
        }
  }
}

export interface FacPolicyLookup {
  inGraphite: boolean
  policyId: number | null
  policyNumber: string
  policyActionId?: number | null
  insuredName: string | null
  policyType?: string | null
  productName?: string | null
  periodFrom?: string | null
  periodTo?: string | null
  policyStatus?: string | null
  isActive?: boolean
  premium?: number | null
  message?: string
  receipts?: FacReceipts
  coverage?: FacCoverage
}

export interface FacSlip {
  id: number
  slipNo: string
  version: number
  placementType: 'fac' | 'auto_fac'
  policyNumber: string | null
  insuredName: string | null
  counterpartyId: number | null
  counterpartyName: string | null
  riskCarrier: string | null
  status: 'draft' | 'generated' | 'sent' | 'accepted' | 'superseded'
  generatedAt: string | null
  sentAt: string | null
  sentTo: string | null
  sendError: string | null
  lineCount: number
  hasDocument: boolean
  /** The acceptance panel — who is on the slip and for what proportion. */
  acceptances: FacAcceptance[]
  /** Sum of the panel's shares. Reported so a short panel is visible. */
  acceptedPct: number
  /** How many of the panel have actually signed. Zero means this is not cover. */
  committedCount: number

  // ── Term-sheet wording. Legal terms on the document the reinsurer signs. ──
  descriptionOfRisk: string | null
  territorialScope: string | null
  deductibleText: string | null
  riskCededText: string | null
  basisOfCover: string | null
  /** The placement terms that do not fit one of the named rows above. Printed on
   *  the slip only when set — an empty "Notes" heading on a contractual document
   *  reads as terms omitted rather than terms absent. */
  slipNotes: string | null
  /** True when the policy states a basis. No longer disables the field — an
   *  underwriter may state a different one — but the override is recorded. */
  basisFromPolicy: boolean
  /** What the POLICY says, shown beside the field so an override is a visible
   *  choice rather than an accident. Null where the policy cannot answer. */
  basisPolicyValue: string | null
  /** Which authority stated the stored basis: 'policy' | 'underwriter'. Null on
   *  slips written before the column existed, which are read as 'policy'. */
  basisOfCoverSource: string | null
  /** False once the slip is sent, accepted or superseded — correct it by
   *  generating a new version instead. */
  termsEditable: boolean
}

export interface FacAskResponse {
  available: boolean
  answer: string | null
  model: string | null
  /** How many identifying values were tokenised before anything was sent. */
  redactedFields: number
  error: string | null
}

// ─── Register ─────────────────────────────────────────────────────────────────

export async function fetchFacPlacements(filters: FacFilters = {}): Promise<FacListResponse> {
  const { data } = await apiClient.get<FacListResponse>('/reinsurance/fac', { params: filters })
  return data
}

export async function fetchFacPlacement(id: number): Promise<FacPlacementDetail> {
  const { data } = await apiClient.get<FacPlacementDetail>(`/reinsurance/fac/${id}`)
  return data
}

/** The cession bordereau for a period, grouped by reinsurer. */
export async function fetchFacBordereau(filters: FacBordereauFilters = {}): Promise<FacBordereau> {
  const { data } = await apiClient.get<FacBordereau>('/reinsurance/fac/bordereau', { params: filters })
  return data
}

/**
 * Download the bordereau as a CSV — the form it is actually sent in.
 *
 * Goes through the API client rather than a plain link: the link would resolve
 * against the frontend host and carry no bearer token. See utils/download.
 */
export async function downloadFacBordereauCsv(filters: FacBordereauFilters = {}): Promise<void> {
  await downloadFromApi(
    '/reinsurance/fac/bordereau/csv',
    filters as Record<string, unknown>,
    `fac-cession-bordereau-${filters.period_to ?? 'period'}.csv`,
  )
}

/** Download the flat register extract. Not a bordereau — every column, unsorted. */
export async function downloadFacRegisterExport(filters: FacFilters = {}): Promise<void> {
  await downloadFromApi(
    '/reinsurance/fac/export',
    filters as Record<string, unknown>,
    'fac-register.csv',
  )
}

export async function createFacPlacement(payload: Record<string, unknown>): Promise<FacPlacementDetail> {
  const { data } = await apiClient.post<FacPlacementDetail>('/reinsurance/fac', payload)
  return data
}

export async function updateFacPlacement(id: number, payload: Record<string, unknown>): Promise<FacPlacementDetail> {
  const { data } = await apiClient.put<FacPlacementDetail>(`/reinsurance/fac/${id}`, payload)
  return data
}

export async function syncFacPolicy(id: number): Promise<FacPlacementDetail> {
  const { data } = await apiClient.post<FacPlacementDetail>(`/reinsurance/fac/${id}/sync-policy`)
  return data
}

export async function lookupFacPolicy(policyNumber: string): Promise<FacPolicyLookup> {
  const { data } = await apiClient.get<FacPolicyLookup>('/reinsurance/fac/lookup', {
    params: { policy_number: policyNumber },
  })
  return data
}

// ─── Money legs ───────────────────────────────────────────────────────────────

export async function markFacClientPaid(
  id: number,
  payload: { source?: string; amount?: number; paid_at?: string } = {},
): Promise<FacPlacementDetail> {
  const { data } = await apiClient.post<FacPlacementDetail>(`/reinsurance/fac/${id}/client-paid`, payload)
  return data
}

export async function settleFacPlacement(
  id: number,
  payload: { settlement_reference: string; settled_amount: number; settled_at?: string },
): Promise<FacPlacementDetail> {
  const { data } = await apiClient.post<FacPlacementDetail>(`/reinsurance/fac/${id}/settle`, payload)
  return data
}

export async function cancelFacPlacement(id: number, reason: string): Promise<FacPlacementDetail> {
  const { data } = await apiClient.post<FacPlacementDetail>(`/reinsurance/fac/${id}/cancel`, { reason })
  return data
}

export async function uploadFacAttachment(id: number, form: FormData): Promise<FacPlacementDetail> {
  const { data } = await apiClient.post<FacPlacementDetail>(`/reinsurance/fac/${id}/attachments`, form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}

/**
 * File the signed slip: the document and the signing date together, in one call.
 *
 * Separately, either half can fail and leave the line reading as complete when it
 * is not — a document with no warranty deadline, or a deadline with nothing behind
 * it. This is also what promotes a draft placement into the payable.
 */
export async function uploadFacSignedSlip(id: number, form: FormData): Promise<FacPlacementDetail> {
  const { data } = await apiClient.post<FacPlacementDetail>(`/reinsurance/fac/${id}/signed-slip`, form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}

/**
 * Type the term-sheet wording onto a slip.
 *
 * Deductible, description of risk, territorial scope and the risk-ceded wording
 * are legal terms on the document the reinsurer signs, and until this endpoint
 * existed none of them could be entered — every slip printed the migration
 * default.
 *
 * Basis of cover used to be refused where the policy stated one. It is now
 * accepted and the override is RECORDED instead (Reinsurance, 7 September
 * 2026): a facultative cession can be written on a different basis from the
 * policy underneath it, so the slip may differ — but not quietly.
 */
export async function updateFacSlipTerms(slipId: number, payload: {
  description_of_risk?: string | null
  territorial_scope?: string | null
  deductible_text?: string | null
  risk_ceded_text?: string | null
  basis_of_cover?: string | null
  slip_notes?: string | null
}): Promise<{ message: string; data: FacSlip }> {
  const { data } = await apiClient.put(`/reinsurance/fac/slips/${slipId}/terms`, payload)
  return data
}

export async function downloadFacAttachment(id: number, attId: number): Promise<{ url: string; name: string }> {
  const { data } = await apiClient.get(`/reinsurance/fac/${id}/attachments/${attId}`)
  return data
}

// ─── Summary, variance, close ─────────────────────────────────────────────────

export async function fetchFacSummary(params: { as_at?: string; financial_year?: string } = {}): Promise<FacSummaryResponse> {
  const { data } = await apiClient.get<FacSummaryResponse>('/reinsurance/fac/summary', { params })
  return data
}

export async function fetchFacVariance(params: { period_end: string; financial_year?: string }): Promise<FacVarianceResponse> {
  const { data } = await apiClient.get<FacVarianceResponse>('/reinsurance/fac/variance', { params })
  return data
}

export async function storeFacGl(payload: {
  period_end: string
  gl_premium: number
  gl_commission?: number
  gl_source: string
  gl_as_at: string
}): Promise<{ message: string }> {
  const { data } = await apiClient.post('/reinsurance/fac/period/gl', payload)
  return data
}

export async function closeFacPeriod(payload: { period_end: string; financial_year?: string }) {
  const { data } = await apiClient.post('/reinsurance/fac/period/close', payload)
  return data
}

// ─── Coverage: "is this policy FAC'ed?" ───────────────────────────────────────

export async function fetchFacCoverage(policyNumber: string): Promise<FacCoverage> {
  const { data } = await apiClient.get<FacCoverage>('/reinsurance/fac/coverage', {
    params: { policy_number: policyNumber },
  })
  return data
}

export async function fetchFacCoverageScan(params: { exceptions_only?: boolean; limit?: number } = {}): Promise<{
  data: FacCoverage[]
  summary: {
    policiesRequiringFac: number
    covered: number
    nonePlaced: number
    underPlaced: number
    overPlaced: number
    exposureUnplacedBwp: number
  }
}> {
  const { data } = await apiClient.get('/reinsurance/fac/coverage-scan', { params })
  return data
}

// ─── Slips ────────────────────────────────────────────────────────────────────

export async function fetchFacSlips(params: { status?: string; search?: string; page?: number; per_page?: number } = {}): Promise<{ data: FacSlip[]; meta: ListMeta }> {
  const { data } = await apiClient.get('/reinsurance/fac/slips', { params })
  return data
}

export async function generateFacSlip(payload: { slip_no: string; placement_type?: string }): Promise<{ message: string; data: FacSlip }> {
  const { data } = await apiClient.post('/reinsurance/fac/slips', payload)
  return data
}

export async function sendFacSlip(slipId: number, to?: string): Promise<{ message: string; data: FacSlip }> {
  const { data } = await apiClient.post(`/reinsurance/fac/slips/${slipId}/send`, to ? { to } : {})
  return data
}

export async function downloadFacSlip(slipId: number): Promise<{ url: string; name: string }> {
  const { data } = await apiClient.get(`/reinsurance/fac/slips/${slipId}/download`)
  return data
}

// ─── Assistant ────────────────────────────────────────────────────────────────

export async function askFac(question: string, financialYear?: string): Promise<FacAskResponse> {
  const { data } = await apiClient.post<FacAskResponse>('/reinsurance/fac/ask', {
    question,
    financial_year: financialYear,
  })
  return data
}

// ─── Presentation helpers ─────────────────────────────────────────────────────

export const FAC_STATUS_LABELS: Record<FacStatus, string> = {
  draft: 'Draft',
  placed: 'Placed',
  awaiting_premium: 'Awaiting premium',
  client_paid: 'Client paid',
  ready_to_settle: 'Ready to settle',
  settled: 'Settled',
  cancelled: 'Cancelled',
}

export const FAC_FLAG_LABELS: Record<FacFlag, string> = {
  policy_not_in_graphite: 'Policy not in Graphite',
  policy_not_active: 'Policy not active',
  fx_rate_missing: 'Exchange rate missing',
  ppw_breached: 'Warranty breached',
  ppw_not_set: 'No warranty date',
  policy_period_over_treaty_max: 'Period outside treaty',
  named_group_exception: 'Group FAC exception',
}

/** Red means somebody has to act today; amber means it needs filling in. */
export const FAC_FLAG_TONE: Record<FacFlag, 'red' | 'amber'> = {
  policy_not_in_graphite: 'red',
  policy_not_active: 'red',
  fx_rate_missing: 'amber',
  ppw_breached: 'red',
  ppw_not_set: 'amber',
  // The period puts the risk outside both treaties — red.
  policy_period_over_treaty_max: 'red',
  // A carve-out is information, not a problem. Amber is the quietest tone here.
  named_group_exception: 'amber',
}

export function facMoney(v: number | null | undefined, currency = 'BWP'): string {
  if (v === null || v === undefined) return '—'
  const n = v.toLocaleString('en-BW', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
  return currency ? `${currency} ${n}` : n
}

/**
 * A money cell inside the register's tables.
 *
 * Pula is the base currency, so repeating "BWP" on every row of every money
 * column is 40px of noise per column that carries no information. The code is
 * printed ONLY when the placement is in a foreign currency — which is exactly
 * the row a reader needs to notice.
 */
export function facAmount(v: number | null | undefined, currency = 'BWP'): string {
  if (v === null || v === undefined) return '—'
  const n = v.toLocaleString('en-BW', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
  return currency && currency !== 'BWP' ? `${currency} ${n}` : n
}

export function facPct(v: number | null | undefined): string {
  if (v === null || v === undefined) return '—'
  return `${(v * 100).toFixed(2)}%`
}

/**
 * The cycle, in order, for the register's stage filter. Cancelled is last
 * because it is an outcome, not a step — it never precedes anything.
 */
export const FAC_STAGES: Array<{ key: FacStage; label: string }> = [
  { key: 'awaiting_signature', label: 'Awaiting signature' },
  { key: 'awaiting_premium', label: 'Awaiting client premium' },
  { key: 'ready_to_settle', label: 'Ready to settle' },
  { key: 'complete', label: 'Complete' },
  { key: 'cancelled', label: 'Cancelled' },
]

/**
 * Orange means "a human has to do something here" — which is true of the first
 * two stages and nothing else. Complete is green, cancelled is grey: neither
 * wants attention.
 */
export const FAC_STAGE_TONE: Record<FacStage, 'warn' | 'info' | 'ok' | 'quiet'> = {
  awaiting_signature: 'warn',
  awaiting_premium: 'info',
  ready_to_settle: 'warn',
  complete: 'ok',
  cancelled: 'quiet',
}
