import apiClient from './client'

export interface Lawyer {
  id: number
  name: string
  email: string | null
  phone: string | null
  company: string | null
  category: 'motor' | 'non_motor' | 'both' | null
  is_active: boolean
  notes: string | null
}

export type LawyerPayload = {
  name: string
  email?: string | null
  phone?: string | null
  company?: string | null
  category?: 'motor' | 'non_motor' | 'both' | ''
  is_active?: boolean
  notes?: string | null
}

export interface LawyerListFilters {
  search?: string
  category?: 'motor' | 'non_motor'
  active?: 0 | 1
  page?: number
  per_page?: number
}

export interface LawyerListResponse {
  data: Lawyer[]
  meta: { current_page: number; per_page: number; has_more: boolean }
}

export async function fetchLawyers(filters: LawyerListFilters = {}): Promise<LawyerListResponse> {
  const { data } = await apiClient.get<LawyerListResponse>('/lawyers', { params: filters })
  return data
}

export async function createLawyer(payload: LawyerPayload): Promise<void> {
  await apiClient.post('/lawyers', payload)
}

export async function updateLawyer(id: number, payload: LawyerPayload): Promise<void> {
  await apiClient.put(`/lawyers/${id}`, payload)
}

export async function deleteLawyer(id: number): Promise<void> {
  await apiClient.delete(`/lawyers/${id}`)
}
