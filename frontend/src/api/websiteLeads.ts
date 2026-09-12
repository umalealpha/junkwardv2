import apiClient from './client'

export interface WebsiteLead {
  id: number
  type: 'quotation' | 'contact' | 'claim' | 'career' | 'newsletter'
  full_name: string | null
  email: string | null
  phone: string | null
  product: string | null
  message: string | null
  details: Record<string, unknown> | null
  status: 'new' | 'in_progress' | 'resolved' | 'spam'
  source: string | null
  submitted_at: string | null
}

export interface WebsiteLeadFilters {
  type?: string
  status?: string
  search?: string
  per_page?: number
  page?: number
}

export interface WebsiteLeadListResponse {
  data: WebsiteLead[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export async function fetchWebsiteLeads(
  filters: WebsiteLeadFilters = {},
): Promise<WebsiteLeadListResponse> {
  const { data } = await apiClient.get<WebsiteLeadListResponse>('/website-leads', {
    params: filters,
  })
  return data
}

export async function updateWebsiteLeadStatus(
  id: number,
  status: WebsiteLead['status'],
): Promise<void> {
  await apiClient.patch(`/website-leads/${id}/status`, { status })
}
