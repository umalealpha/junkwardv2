import apiClient from './client'
import type { ListMeta } from './kyc'

// ─── Types ────────────────────────────────────────────────────────────────────

export interface ReinsuranceTypeItem {
  id: number
  typeCode: string
  typeName: string
  typeDescription: string | null
  status: number
  createdAt: string | null
}
export interface ReinsuranceTypeResponse { data: ReinsuranceTypeItem[]; meta: ListMeta }

export async function fetchReinsuranceTypes(filters: { search?: string; per_page?: number; page?: number } = {}): Promise<ReinsuranceTypeResponse> {
  const { data } = await apiClient.get<ReinsuranceTypeResponse>('/reinsurance/types', { params: filters })
  return data
}
export async function fetchReinsuranceType(id: number): Promise<ReinsuranceTypeItem> {
  const { data } = await apiClient.get<ReinsuranceTypeItem>(`/reinsurance/types/${id}`)
  return data
}
export async function createReinsuranceType(payload: { type_code: string; type_name: string; type_description: string; status?: number }): Promise<{ message: string; data: { id: number } }> {
  const { data } = await apiClient.post('/reinsurance/types', payload)
  return data
}
export async function updateReinsuranceType(id: number, payload: { type_code: string; type_name: string; type_description: string; status?: number }): Promise<{ message: string }> {
  const { data } = await apiClient.put(`/reinsurance/types/${id}`, payload)
  return data
}
export async function deleteReinsuranceType(id: number): Promise<{ message: string }> {
  const { data } = await apiClient.delete(`/reinsurance/types/${id}`)
  return data
}

// ─── Formulas ─────────────────────────────────────────────────────────────────

export interface ReinsuranceFormulaItem {
  id: number
  formulaCode: string
  formulaName: string
  productId: number | null
  reinsuranceTypeId: number | null
  typeId: number | null
  sFormulaType: string | null
  reinsuranceType: string | null
  status: number
  groupId: number | null
  operator: string | null
  vehicleType: string | null
  siAllocation: string | null
  percentage: string | null
  dateFrom: string | null
  dateTo: string | null
  createdAt: string | null
}
export interface ReinsuranceFormulaResponse { data: ReinsuranceFormulaItem[]; meta: ListMeta }

export async function fetchReinsuranceFormulas(filters: { search?: string; per_page?: number; page?: number } = {}): Promise<ReinsuranceFormulaResponse> {
  const { data } = await apiClient.get<ReinsuranceFormulaResponse>('/reinsurance/formulas', { params: filters })
  return data
}
export async function fetchReinsuranceFormula(id: number): Promise<ReinsuranceFormulaItem> {
  const { data } = await apiClient.get<ReinsuranceFormulaItem>(`/reinsurance/formulas/${id}`)
  return data
}
export async function createReinsuranceFormula(payload: Record<string, unknown>): Promise<{ message: string; data: { id: number } }> {
  const { data } = await apiClient.post('/reinsurance/formulas', payload)
  return data
}
export async function updateReinsuranceFormula(id: number, payload: Record<string, unknown>): Promise<{ message: string }> {
  const { data } = await apiClient.put(`/reinsurance/formulas/${id}`, payload)
  return data
}
export async function deleteReinsuranceFormula(id: number): Promise<{ message: string }> {
  const { data } = await apiClient.delete(`/reinsurance/formulas/${id}`)
  return data
}

// ─── Coverage Groups ──────────────────────────────────────────────────────────

export interface GroupCoverageRow {
  id?: number
  coverage_id: number | string
  coverage_name: string
  si_premium: number | null
  ri_limit: number | null
  limit_value: string | null
  is_parent?: boolean
  is_selected?: boolean
}
export interface CoverageGroupItem {
  id: number
  groupCode: string | null
  groupName: string | null
  productId: number | null
  status: number | null
  createdAt: string | null
  coverages?: GroupCoverageRow[]
}
export interface CoverageGroupResponse { data: CoverageGroupItem[]; meta: ListMeta }

export interface ProductCoverageRow {
  coverage_id: number
  name: string
  code: string
  usage_type: string
  is_dynamic?: boolean
  is_hardcoded?: boolean
  children?: ProductCoverageRow[]
}
export interface CoverageGroupPayload {
  group_code: string
  group_name: string
  product_id?: number | null
  status?: number
  coverages?: GroupCoverageRow[]
}

export async function fetchReinsuranceCoverageGroups(filters: { search?: string; per_page?: number; page?: number } = {}): Promise<CoverageGroupResponse> {
  const { data } = await apiClient.get<CoverageGroupResponse>('/reinsurance/coverage-groups', { params: filters })
  return data
}
export async function fetchReinsuranceCoverageGroup(id: number): Promise<CoverageGroupItem> {
  const { data } = await apiClient.get<CoverageGroupItem>(`/reinsurance/coverage-groups/${id}`)
  return data
}
export async function fetchProductCoverages(productId: number): Promise<{ data: ProductCoverageRow[] }> {
  const { data } = await apiClient.get<{ data: ProductCoverageRow[] }>(`/reinsurance/products/${productId}/coverages`)
  return data
}
export async function createReinsuranceCoverageGroup(payload: CoverageGroupPayload): Promise<{ message: string; data: { id: number } }> {
  const { data } = await apiClient.post('/reinsurance/coverage-groups', payload)
  return data
}
export async function updateReinsuranceCoverageGroup(id: number, payload: CoverageGroupPayload): Promise<{ message: string }> {
  const { data } = await apiClient.put(`/reinsurance/coverage-groups/${id}`, payload)
  return data
}
export async function deleteReinsuranceCoverageGroup(id: number): Promise<{ message: string }> {
  const { data } = await apiClient.delete(`/reinsurance/coverage-groups/${id}`)
  return data
}

// ─── Treaties ─────────────────────────────────────────────────────────────────

export interface TreatyItem {
  id: number
  treatyName: string | null
  treatyNumber: string | null
  effectiveFrom: string | null
  effectiveTo: string | null
  provisionalCommission: number | null
  proportionalShare: number | null
  cashLossAdvise: number | null
  eventLimit: number | null
  exclusions: string | null
  status: number | null
  createdAt: string | null
}
export interface TreatyResponse { data: TreatyItem[]; meta: ListMeta }

export async function fetchReinsuranceTreaties(filters: { search?: string; per_page?: number; page?: number } = {}): Promise<TreatyResponse> {
  const { data } = await apiClient.get<TreatyResponse>('/reinsurance/treaties', { params: filters })
  return data
}
export async function fetchReinsuranceTreaty(id: number): Promise<TreatyItem> {
  const { data } = await apiClient.get<TreatyItem>(`/reinsurance/treaties/${id}`)
  return data
}
export async function createReinsuranceTreaty(payload: Record<string, unknown>): Promise<{ message: string; data: { id: number } }> {
  const { data } = await apiClient.post('/reinsurance/treaties', payload)
  return data
}
export async function updateReinsuranceTreaty(id: number, payload: Record<string, unknown>): Promise<{ message: string }> {
  const { data } = await apiClient.put(`/reinsurance/treaties/${id}`, payload)
  return data
}
export async function deleteReinsuranceTreaty(id: number): Promise<{ message: string }> {
  const { data } = await apiClient.delete(`/reinsurance/treaties/${id}`)
  return data
}

// ─── Treaty Rollover ──────────────────────────────────────────────────────────
// Clones an existing treaty + its treaty_details rows into a new period.
// Mirrors `php artisan treaty:rollover` — same service on the backend.

export interface TreatyRolloverPayload {
  name?: string
  number?: string
  effective_from?: string  // YYYY-MM-DD
  effective_to?: string    // YYYY-MM-DD
  dry_run?: boolean
  force?: boolean
}

export interface TreatyRolloverResult {
  source_id: number
  source_name: string
  new_treaty_id: number | null
  new_name: string
  new_number: string
  effective_from: string
  effective_to: string
  cloned_details: number
  dry_run: boolean
  log: string[]
}

export async function rolloverReinsuranceTreaty(
  id: number,
  payload: TreatyRolloverPayload,
): Promise<{ message: string; data: TreatyRolloverResult }> {
  const { data } = await apiClient.post(`/reinsurance/treaties/${id}/rollover`, payload)
  return data
}

// ─── Form Lookups ─────────────────────────────────────────────────────────────

export interface FormLookups {
  types: { id: number; type_name: string }[]
  products: { id: number; name: string }[]
  formulas: { id: number; formula_name: string }[]
  groups: { id: number; group_name: string }[]
  formulaTypes: { id: number; name: string }[]
  /** lookup_data reinsurance_formula_key — 35 Motor, 36 Non-Motor. Drives whether
   *  the cession calc splits a class per coverage detail (per vehicle) or aggregates. */
  formulaKeys: { id: number; name: string }[]
}

export async function fetchReinsuranceFormLookups(): Promise<FormLookups> {
  const { data } = await apiClient.get<FormLookups>('/reinsurance/form-lookups')
  return data
}
