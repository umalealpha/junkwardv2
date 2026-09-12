import apiClient from './client'

export interface LookupItem {
  id: string | number
  name: string
}

export interface PolicyCreateData {
  products: LookupItem[]
  premium_frequencies: LookupItem[]
  source_of_income: LookupItem[]
  entity_types: LookupItem[]
  genders: LookupItem[]
  marital_statuses: LookupItem[]
  currently_insured: LookupItem[]
  hear_about_alpha: LookupItem[]
  states: LookupItem[]
  construction_types: LookupItem[]
}

export interface CoverageMaster {
  id: number
  s_CoverageName: string
  s_CoverageCode: string
  s_CoverageGroupCode: string
  s_UsageType: string
  has_risk_address: number
  n_DisplaySequence: number
}

export interface Plan {
  id: number
  product_id: number
  name: string
  sum_assured?: number
  premium?: number
}

export async function fetchPolicyCreateData(): Promise<PolicyCreateData> {
  const { data } = await apiClient.get<{ data: PolicyCreateData }>('/lookups/policy-create')
  return data.data
}

export async function fetchAgencies(): Promise<LookupItem[]> {
  const { data } = await apiClient.get<{ data: LookupItem[] }>('/lookups/agencies')
  return data.data
}

export async function fetchAgentsByAgency(agencyId: number): Promise<LookupItem[]> {
  const { data } = await apiClient.get<{ data: LookupItem[] }>(`/lookups/agencies/${agencyId}/agents`)
  return data.data
}

/** All agents who have written at least one policy — backs the policy list "Filter By Agent" dropdown. */
export async function fetchAgents(): Promise<LookupItem[]> {
  const { data } = await apiClient.get<{ data: LookupItem[] }>('/lookups/agents')
  return data.data
}

export async function fetchCitiesByState(stateId: number): Promise<LookupItem[]> {
  const { data } = await apiClient.get<{ data: LookupItem[] }>(`/lookups/states/${stateId}/cities`)
  return data.data
}

export async function fetchPlansByProduct(productId: number): Promise<Plan[]> {
  const { data } = await apiClient.get<{ data: Plan[] }>(`/lookups/products/${productId}/plans`)
  return data.data
}

export async function fetchCoveragesByProduct(productId: number): Promise<CoverageMaster[]> {
  const { data } = await apiClient.get<{ data: CoverageMaster[] }>(`/lookups/products/${productId}/coverages`)
  return data.data
}

export async function fetchCompanies(): Promise<LookupItem[]> {
  const { data } = await apiClient.get<{ data: LookupItem[] }>('/lookups/companies')
  return data.data
}

export async function fetchSubCompanies(companyId: number): Promise<LookupItem[]> {
  const { data } = await apiClient.get<{ data: LookupItem[] }>(`/lookups/companies/${companyId}/sub`)
  return data.data
}
