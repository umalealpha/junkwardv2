import apiClient from './client'
import type { ListMeta } from './kyc'

export interface ImportActivityFilters { status?: number; search?: string; per_page?: number; page?: number }
export interface ImportActivityItem {
  id: number; uploadedFile: string | null; performFile: string | null
  status: number; statusLabel: string; addedBy: string | null; createdAt: string | null
}
export interface ImportActivityResponse { data: ImportActivityItem[]; meta: ListMeta }

export async function fetchImportActivities(filters: ImportActivityFilters = {}): Promise<ImportActivityResponse> {
  const { data } = await apiClient.get<ImportActivityResponse>('/import-activities', { params: filters })
  return data
}

export interface UploadExcelImportResponse {
  message: string
  id: number
  fileName: string
  remarks: string
}

export async function uploadExcelImport(
  file: File,
  type: 'activation' | 'cancellation'
): Promise<UploadExcelImportResponse> {
  const form = new FormData()
  form.append('file', file)
  form.append('type', type)
  const { data } = await apiClient.post<UploadExcelImportResponse>('/excel-import/upload', form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}
