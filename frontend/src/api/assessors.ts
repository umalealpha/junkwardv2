import apiClient from './client'

export interface Assessor {
  id: number
  name: string
  email: string | null
  phone: string | null
  company: string | null
  category: 'motor' | 'non_motor' | 'both' | null
  is_active: boolean
  notes: string | null
}

export type AssessorPayload = {
  name: string
  email?: string | null
  phone?: string | null
  company?: string | null
  category?: 'motor' | 'non_motor' | 'both' | ''
  is_active?: boolean
  notes?: string | null
}

export interface AssessorListFilters {
  search?: string
  category?: 'motor' | 'non_motor'
  active?: 0 | 1
  page?: number
  per_page?: number
}

export interface AssessorListResponse {
  data: Assessor[]
  meta: { current_page: number; per_page: number; has_more: boolean }
}

export async function fetchAssessors(filters: AssessorListFilters = {}): Promise<AssessorListResponse> {
  const { data } = await apiClient.get<AssessorListResponse>('/assessors', { params: filters })
  return data
}

export async function createAssessor(payload: AssessorPayload): Promise<void> {
  await apiClient.post('/assessors', payload)
}

export async function updateAssessor(id: number, payload: AssessorPayload): Promise<void> {
  await apiClient.put(`/assessors/${id}`, payload)
}

export async function deleteAssessor(id: number): Promise<void> {
  await apiClient.delete(`/assessors/${id}`)
}
