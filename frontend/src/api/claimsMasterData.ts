import apiClient from './client'

// ─── Read-through tile types ─────────────────────────────────────────────

export interface ClaimHandler {
  id: number
  firstName: string | null
  lastName: string | null
  name: string
  email: string | null
  isActive: boolean
}

export interface Broker {
  id: number
  name: string
  isActive: boolean
}

export interface Reinsurer {
  id: number
  companyName: string | null
  email: string | null
  cellphone: string | null
}

export interface ReinsurerTreatyFallback {
  id: number
  treatyName: string | null
  treatyNumber: string | null
  status: number | null
}

export interface MasterSupplier {
  id: number
  name: string
  type: string | null
  email: string | null
  phone: string | null
  location: string | null
  isApprovedPanelBeater: boolean | null
  isApprovedGlassSupplier: boolean | null
}

export interface SystemUser {
  id: number
  name: string
  email: string | null
  isActive: boolean
  roles: string[]
}

export interface ClaimTypeAlias {
  alias: string
  canonical: string
  isMotor: boolean
}

export interface ClaimTypeMap {
  aliases: ClaimTypeAlias[]
  motorTypes: string[]
  nonMotorTypes: string[]
  canonicalTypes: string[]
}

export interface MasterDataSummary {
  handlers: number
  assessorsMotor: number
  assessorsNonMotor: number
  panelBeaters: number
  glassSuppliers: number
  reinsurers: number
  brokers: number
  systemUsers: number
  config: Record<string, number>
  flagsAvailable: boolean
}

// ─── claims_config store types ───────────────────────────────────────────

export interface ClaimsConfigCategory {
  category: string
  label: string
  count: number
}

export interface ClaimsConfigEntry {
  id: number
  category: string
  label: string
  value: string | null
  sort_order: number
  is_active: boolean
}

export type ClaimsConfigPayload = {
  label: string
  value?: string | null
  sort_order?: number
  is_active?: boolean
}

// ─── Read-through tile calls ─────────────────────────────────────────────

export async function fetchMasterDataSummary(): Promise<MasterDataSummary> {
  const { data } = await apiClient.get<{ data: MasterDataSummary }>('/claims-masterdata/summary')
  return data.data
}

export async function fetchClaimHandlers(search?: string): Promise<ClaimHandler[]> {
  const { data } = await apiClient.get<{ data: ClaimHandler[] }>('/claims-masterdata/handlers', { params: { search: search || undefined } })
  return data.data
}

export async function fetchBrokers(search?: string): Promise<Broker[]> {
  const { data } = await apiClient.get<{ data: Broker[] }>('/claims-masterdata/brokers', { params: { search: search || undefined } })
  return data.data
}

export interface ReinsurersResponse {
  data: Reinsurer[]
  meta: { total: number; usingFallback: boolean; fallbackTreaties: ReinsurerTreatyFallback[] }
}
export async function fetchReinsurers(search?: string): Promise<ReinsurersResponse> {
  const { data } = await apiClient.get<ReinsurersResponse>('/claims-masterdata/reinsurers', { params: { search: search || undefined } })
  return data
}

export interface MasterSuppliersResponse {
  data: MasterSupplier[]
  meta: { current_page: number; per_page: number; has_more: boolean; flagsAvailable: boolean; category: string }
}
export async function fetchMasterSuppliers(params: { type: 'panel_beater' | 'glass'; search?: string; page?: number; per_page?: number }): Promise<MasterSuppliersResponse> {
  const { data } = await apiClient.get<MasterSuppliersResponse>('/claims-masterdata/suppliers', {
    params: { type: params.type, search: params.search || undefined, page: params.page, per_page: params.per_page ?? 25 },
  })
  return data
}

export async function toggleSupplierApproval(
  id: number,
  flags: { is_approved_panel_beater?: boolean; is_approved_glass_supplier?: boolean },
): Promise<void> {
  await apiClient.patch(`/claims-masterdata/suppliers/${id}/approval`, flags)
}

export interface SystemUsersResponse {
  data: SystemUser[]
  meta: { current_page: number; per_page: number; has_more: boolean }
}
export async function fetchSystemUsers(params: { search?: string; page?: number; per_page?: number } = {}): Promise<SystemUsersResponse> {
  const { data } = await apiClient.get<SystemUsersResponse>('/claims-masterdata/system-users', {
    params: { search: params.search || undefined, page: params.page, per_page: params.per_page ?? 25 },
  })
  return data
}

export async function fetchClaimTypeMap(): Promise<ClaimTypeMap> {
  const { data } = await apiClient.get<{ data: ClaimTypeMap }>('/claims-masterdata/claim-type-map')
  return data.data
}

// ─── claims_config CRUD calls ────────────────────────────────────────────

export async function fetchConfigCategories(): Promise<ClaimsConfigCategory[]> {
  const { data } = await apiClient.get<{ data: ClaimsConfigCategory[] }>('/claims-config')
  return data.data
}

export async function fetchConfigEntries(category: string, search?: string): Promise<ClaimsConfigEntry[]> {
  const { data } = await apiClient.get<{ data: ClaimsConfigEntry[] }>(`/claims-config/${category}`, { params: { search: search || undefined } })
  return data.data
}

export async function createConfigEntry(category: string, payload: ClaimsConfigPayload): Promise<void> {
  await apiClient.post(`/claims-config/${category}`, payload)
}

export async function updateConfigEntry(category: string, id: number, payload: ClaimsConfigPayload): Promise<void> {
  await apiClient.put(`/claims-config/${category}/${id}`, payload)
}

export async function deleteConfigEntry(category: string, id: number): Promise<void> {
  await apiClient.delete(`/claims-config/${category}/${id}`)
}
