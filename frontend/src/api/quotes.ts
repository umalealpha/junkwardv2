import apiClient from './client'

export interface QuoteFilters {
  status?: string
  agent_id?: number
  search?: string
  per_page?: number
  page?: number
}

export interface QuoteItem {
  id: number
  quoteCode: string
  status: string | null
  statusLabel: string | null
  productId: number | null
  planId: number | null
  policyNumber: string | null
  policyStatus: number | null
  customerName: string | null
  customerPhone: string | null
  customerEmail: string | null
  agentName: string | null
  createdAt: string | null
  updatedAt: string | null
}

export interface QuoteMeta {
  total: number
  per_page: number
  current_page: number
  last_page: number
  from: number | null
  to: number | null
}

export interface QuoteListResponse {
  data: QuoteItem[]
  meta: QuoteMeta
}

export async function fetchQuotes(filters: QuoteFilters = {}): Promise<QuoteListResponse> {
  const { data } = await apiClient.get<QuoteListResponse>('/quotes', { params: filters })
  return data
}

// ─── Quote Detail ────────────────────────────────────

export interface QuoteCustomer {
  id: number | null; firstName: string | null; middleName: string | null; lastName: string | null
  fullName: string | null; email: string | null; cellphone: string | null
  gender: string | null; dob: string | null; maritalStatus: string | null
  omang: string | null; passport: string | null; address: string | null
}

export interface QuoteVehicle {
  make: string | null; model: string | null; year: string | null
  estimatedValue: number | null; isImported: boolean; priorAccidents: number | null
  variant: string | null
}

export interface QuotePremium {
  monthly: number | null; threeInstalment: number | null; annually: number | null
  rate: number | null; ratingsId: number | null; frequency: string | null
  discountSurcharge: number | null; percentDiscount: number | null
}

export interface QuotePremiumHistory {
  id: number; oldValue: number | null; newValue: number | null
  discountSurcharge: number | null; reason: string | null
  addedBy: string | null; createdAt: string | null
}

export interface QuoteDetail {
  id: number; quoteCode: string; status: number; statusLabel: string
  quoteType: string | null; expiryDate: string | null
  createdAt: string | null; updatedAt: string | null
  customer: QuoteCustomer; product: { id: number | null; name: string | null; plan: string | null }
  vehicle: QuoteVehicle | null; premium: QuotePremium
  agentName: string | null; storeName: string | null
  policy: { id: number; policyNumber: string; status: number } | null
  premiumHistory: QuotePremiumHistory[]
}

export async function fetchQuoteDetail(id: number): Promise<QuoteDetail> {
  const { data } = await apiClient.get<{ data: QuoteDetail }>(`/quotes/${id}`)
  return data.data
}

export async function fetchQuoteHistory(id: number): Promise<QuotePremiumHistory[]> {
  const { data } = await apiClient.get<{ data: QuotePremiumHistory[] }>(`/quotes/${id}/history`)
  return data.data
}

export async function rejectQuote(id: number) {
  return apiClient.post(`/quotes/${id}/reject`)
}

export interface UpdatePremiumPayload {
  type: 'Discount' | 'Surcharge'; value_type: 1 | 2; value: number; reason: string
}

export async function updateQuotePremium(id: number, payload: UpdatePremiumPayload) {
  const { data } = await apiClient.post(`/quotes/${id}/update-premium`, payload)
  return data
}

export async function exportQuotePdf(id: number): Promise<{ url: string; path: string; filename: string }> {
  const { data } = await apiClient.post<{ data: { url: string; path: string; filename: string } }>(`/quotes/${id}/export`)
  return data.data
}

export async function markQuoteUsed(id: number) {
  return apiClient.post(`/quotes/${id}/mark-used`)
}
