import apiClient from './client'

export interface CreatePolicyPayload {
  // Policy
  product_id: number
  plan_id: number
  agency_id: number
  agent_id: number | null
  premium_freq: string
  term_start_date: string
  expiry_date: string
  gfs_policy_no?: string
  /** Binder (cover-in-principle) date, stored on customer_profile.binder_date.
   *  Must be <= term_start_date. */
  binder_date?: string

  // Customer
  entity_type: 'Individual' | 'Organisation'
  company_id?: number
  first_name?: string
  middle_name?: string
  last_name?: string
  email?: string
  cellphone?: string
  gender?: string
  dob?: string
  marital_status?: string
  omang?: string
  passport?: string
  state?: number
  city?: number
  post_address?: string
  source_of_income?: string
  employment?: Record<string, string>

  // KYC questions
  currently_insured?: string
  current_insurer_detail?: string
  hear_about_alpha?: string
  decline_proposal?: boolean
  refused_policy?: boolean
  cancel_policy?: boolean
  business_note?: string

  // COMG-specific
  firm_member?: boolean
  books?: boolean
  date?: string
}

export interface CreatePolicyResponse {
  data: {
    policy_id: number
    policy_number: string
    term_id: number
    action_id: number
    customer_id: number
    status: string
  }
  message: string
}

export interface RiskAddressPayload {
  term_id: number
  action_id: number
  address_name: string
  physical_address: string
  lat?: number
  lng?: number
  risk_state: number
  risk_city: number
  extension?: string
  occupation?: string
  town_class?: string
  risk_class?: string
  iso_rcv?: number
  year_built?: string
  area?: string
  structure_type?: string
  const_type: string
  distance_to_water?: string
  distance_to_fire?: string
  distance_to_hydrant?: string
  usage?: string
  occupancy_type?: string
  central_fire?: boolean
  central_burglar?: boolean
  gated_community?: boolean
  automatic?: boolean
  company_id?: number
}

export interface CoveragePayload {
  risk_address_id: number
  action_id: number
  term_id: number
  coverage_id: number
  coverage_value: number
  rate?: number
  calculated_value?: number
  type_string?: string
  type_value?: number
  entity_type?: string
  // Workers Compensation specific fields
  ratefactor_type?: string
  ratefactor_value?: string
  details?: Array<{
    description?: string
    value?: number
    type_id?: number
    coverage_id?: number
    coverage_value?: number
    rate?: number
    calculated_value?: number
    ratefactor_type?: string
    ratefactor_value?: string
  }>
  // Extensions (non-motor)
  extensions?: Array<{
    extentions_id: number
    s_ScreenName?: string
    type?: string
    extention_type?: string
    extention_coverage_value?: number | string
    extention_text_value?: string
    extention_limit_id?: number | null
    extention_excess_min_value?: number | string
    extention_excess_max_value?: number | string
    extention_discount_surcharge?: string
    extention_discount_surcharge_type?: string
    extention_discount_surcharge_value?: number | string
    extention_calculated_value?: number | string
  }>
  // Specified Items (miscellaneous)
  specified_items?: Array<{
    specified_coverage_id?: number | null
    name?: string
    sum_insured?: number | string
    rate?: number | string
    calculated_value?: number | string
  }>
  // Excesses
  excesses?: Array<{
    excesses?: string
    min_percent?: number | string
    min_amt?: number | string
  }>
  // Fidelity Guarantee (policy_coverages_data)
  fidelity_data?: Array<{
    id?: number
    cover_type?: string
    cover_area?: string
    name_and_position?: string
    designation?: string
    length_of_service?: string
    amount_to_be_guaranteed?: number | string
    premium?: number | string
    deleted_at?: string | null
  }>
  // Notes
  notes?: string
  // Public Liability retroactive date
  retroactive_date?: string
}

export interface PolicyEditData {
  policy: {
    id: number
    policy_number: string
    product_id: number
    plan_id: number
    agency_id: number
    agent_id: number | null
    premium_freq: string
    term_start_date: string
    expiry_date: string
    gfs_policy_no: string | null
    status: number
    is_draft?: boolean
    note?: string | null
    premium?: string | null
    annual_premium?: string | null
    first_premium?: string | null
    sum_assured?: string | null
    billing_start_date?: string | null
    activated_date?: string | null
  }
  customer: {
    id: number | null
    entity_type: 'Individual' | 'Organisation'
    company_id: number | null
    first_name: string
    middle_name: string
    last_name: string
    email: string
    cellphone: string
    gender: string
    dob: string
    marital_status: string
    omang: string
    passport: string
    state: number | null
    city: number | null
    post_address: string
    source_of_income: string | null
    employment: Record<string, string>
    currently_insured: string
    current_insurer_detail?: string
    hear_about_alpha: string
    decline_proposal: boolean
    refused_policy: boolean
    cancel_policy: boolean
    business_note: string
    firm_member: boolean
    books: boolean
    date: string
  }
  term_id: number | null
  action_id: number | null
  risk_addresses: any[]
  coverages: any[]
  beneficiaries?: any[]
}

export interface UpdatePolicyPayload extends Partial<CreatePolicyPayload> {}

// ── API calls ──────────────────────────────────────────────

export async function createPolicy(payload: CreatePolicyPayload): Promise<CreatePolicyResponse> {
  const { data } = await apiClient.post<CreatePolicyResponse>('/policies', payload)
  return data
}

export async function addRiskAddress(policyId: number, payload: RiskAddressPayload) {
  const { data } = await apiClient.post(`/policies/${policyId}/risk-addresses`, payload)
  return data
}

export async function updateRiskAddress(policyId: number, riskAddressId: number, payload: Partial<RiskAddressPayload>) {
  const { data } = await apiClient.put(`/policies/${policyId}/risk-addresses/${riskAddressId}`, payload)
  return data
}

export async function deleteRiskAddress(policyId: number, riskAddressId: number) {
  const { data } = await apiClient.delete(`/policies/${policyId}/risk-addresses/${riskAddressId}`)
  return data
}

export async function reinstateRiskAddress(policyId: number, riskAddressId: number) {
  const { data } = await apiClient.post(`/policies/${policyId}/risk-addresses/${riskAddressId}/reinstate`)
  return data
}

export async function getRiskAddressesWithCoverages(policyId: number) {
  const { data } = await apiClient.get(`/policies/${policyId}/risk-addresses-with-coverages`)
  return data.data
}

export async function addCoverage(policyId: number, payload: CoveragePayload) {
  const { data } = await apiClient.post(`/policies/${policyId}/coverages`, payload)
  return data
}

export async function updateCoverage(policyId: number, coverageId: number, payload: Partial<CoveragePayload>) {
  const { data } = await apiClient.put(`/policies/${policyId}/coverages/${coverageId}`, payload)
  return data
}

export async function deleteCoverage(policyId: number, coverageId: number) {
  const { data } = await apiClient.delete(`/policies/${policyId}/coverages/${coverageId}`)
  return data
}

export async function reinstateCoverage(policyId: number, coverageId: number) {
  const { data } = await apiClient.post(`/policies/${policyId}/coverages/${coverageId}/reinstate`)
  return data
}

export async function fetchPolicyEditData(policyId: number, actionId?: number | null): Promise<PolicyEditData> {
  const params = actionId ? { action_id: actionId } : undefined
  const { data } = await apiClient.get<{ data: PolicyEditData }>(`/policies/${policyId}/edit-data`, { params })
  return data.data
}

export async function updatePolicy(policyId: number, payload: UpdatePolicyPayload) {
  const { data } = await apiClient.put(`/policies/${policyId}`, payload)
  return data
}

// ── Excel template download (triggers browser file download) ──────────────
export type ExcelImportType = 'risk-address' | 'coverages' | 'specified-items' | 'beneficiaries'

/**
 * Download an Excel template for the given policy and import type.
 * Uses fetch directly so we can handle the binary response as a Blob.
 */
export async function downloadExcelTemplate(policyId: number, type: ExcelImportType): Promise<void> {
  // Use apiClient directly so the request goes through the auth interceptor
  const response = await apiClient.get(`/policies/${policyId}/excel-template/${type}`, {
    responseType: 'blob',
    headers: { Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' },
  })

  const blob = response.data as Blob
  const disposition = response.headers['content-disposition'] ?? ''
  const match = (disposition as string).match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/)
  const filename = match ? match[1].replace(/['"]/g, '') : `policy_${policyId}_${type}_template.xlsx`

  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = filename
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(link.href)
}

/**
 * Upload an Excel file and import data into the policy.
 *
 * @param actionId Optional — the action currently being viewed/edited (e.g.
 *   from the page URL). When supplied, the backend imports into THAT action
 *   instead of whatever is currently "latest" for the policy, so importing
 *   while viewing an older action doesn't silently attach rows to a newer one.
 */
export async function importExcelData(
  policyId: number,
  type: ExcelImportType,
  file: File,
  actionId?: number | null,
): Promise<{ message: string }> {
  const formData = new FormData()
  formData.append('file', file)
  if (actionId) {
    formData.append('action_id', String(actionId))
  }

  const { data } = await apiClient.post<{ message: string }>(
    `/policies/${policyId}/excel-import/${type}`,
    formData,
    { headers: { 'Content-Type': 'multipart/form-data' } },
  )
  return data
}

// ── Risk Address Dropdowns (States, Cities, Construction Types) ─────────────

export interface State {
  id: number
  name: string
}

export interface City {
  id: number
  name: string
}

export interface ConstructionType {
  id: string
  name: string
}

export async function getPolicyCreateLookups() {
  const { data } = await apiClient.get<{ data: { states: State[]; construction_types: ConstructionType[] } }>('/lookups/policy-create')
  return data.data
}

export async function getCitiesByState(stateId: number): Promise<City[]> {
  const { data } = await apiClient.get<{ data: City[] }>(`/lookups/states/${stateId}/cities`)
  return data.data || []
}

export async function getConstructionTypes(): Promise<ConstructionType[]> {
  const lookups = await getPolicyCreateLookups()
  return lookups.construction_types || []
}
