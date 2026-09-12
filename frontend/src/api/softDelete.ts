import apiClient from './client'

export interface SoftDeletePayload {
  table_name: string
  record_id: number
}

export interface SoftDeleteResponse {
  message: string
  data: { table_name: string; record_id: number }
}

// Super Admin only. Soft-deletes a single record from the given table by ID
// (the backend stamps the table's deleted_at column instead of removing the row).
export async function softDeleteRecord(payload: SoftDeletePayload): Promise<SoftDeleteResponse> {
  const { data } = await apiClient.post<SoftDeleteResponse>('/admin/soft-delete', payload)
  return data
}
