import apiClient from './client'
import type { ListMeta } from './kyc'

export interface SettlementSummary {
  rows?: number
  policy_not_found?: number
  no_realpay_contract?: number
  contract_stored?: number
  contract_existing?: number
  no_matching_installment?: number
  tx_stored?: number
  tx_existing?: number
  errors?: number
  log_sample?: string[]
}

export interface SettlementImport {
  id: number
  fileName: string
  uploadedBy: string | null
  /** uploaded | previewing | preview_ready | committing | committed | failed */
  status: string
  rowCount: number | null
  previewSummary: SettlementSummary | null
  commitSummary: SettlementSummary | null
  error: string | null
  createdAt: string | null
  updatedAt: string | null
}

export interface SettlementImportListResponse { data: SettlementImport[]; meta: ListMeta }
export interface SettlementFilters { status?: string; search?: string; per_page?: number; page?: number }

export async function fetchSettlementImports(filters: SettlementFilters = {}): Promise<SettlementImportListResponse> {
  const { data } = await apiClient.get<SettlementImportListResponse>('/realpay-settlement/imports', { params: filters })
  return data
}

export async function fetchSettlementImport(id: number): Promise<{ data: SettlementImport }> {
  const { data } = await apiClient.get<{ data: SettlementImport }>(`/realpay-settlement/imports/${id}`)
  return data
}

export async function uploadSettlement(file: File): Promise<{ message: string; data: SettlementImport }> {
  const form = new FormData()
  form.append('file', file)
  const { data } = await apiClient.post<{ message: string; data: SettlementImport }>(
    '/realpay-settlement/upload', form, { headers: { 'Content-Type': 'multipart/form-data' } },
  )
  return data
}

export async function confirmSettlementImport(id: number): Promise<{ message: string; data: SettlementImport }> {
  const { data } = await apiClient.post<{ message: string; data: SettlementImport }>(`/realpay-settlement/imports/${id}/confirm`)
  return data
}
