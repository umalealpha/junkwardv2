import apiClient from './client'

// Admin consent compliance dashboard client. Backed by
// ConsentsAdminController (summary + list).

export interface ConsentSummary {
  consents_week: number
  consents_month: number
  linked_policies_month: number
  coverage_pct: number
  avg_verify_to_accept_s: number
  revoked_month: number
  revocation_rate_pct: number
  by_channel: Record<string, number>
  as_of: string
}

export interface ConsentRow {
  id: number
  cellphone_masked: string
  product_scope: string | null
  policy_id: number | null
  accepted_at: string
  otp_channel: string | null
  sec_verify_to_accept: number | null
  revoked_at: string | null
  revoke_reason: string | null
  evidence_hash: string | null
  previous_hash: string | null
  terms_version: string | null
  privacy_version: string | null
  source: string | null
}

export interface ConsentFilters {
  product_scope?: string
  status?: 'active' | 'revoked'
  from?: string
  to?: string
  search?: string
  page?: number
  per_page?: number
}

interface ListResponse {
  items: ConsentRow[]
  meta: { total: number; per_page: number; current_page: number; last_page: number }
}

export async function fetchConsentSummary(): Promise<ConsentSummary> {
  const { data } = await apiClient.get<ConsentSummary>('/admin/consents/summary')
  return data
}

export async function fetchConsents(filters: ConsentFilters = {}): Promise<ListResponse> {
  const { data } = await apiClient.get<ListResponse>('/admin/consents', { params: filters })
  return data
}
