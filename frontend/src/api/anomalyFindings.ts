import apiClient from './client'

// ── types ────────────────────────────────────────────────────────────────────

export interface AnomalySummary {
  totalOpen: number
  byType: Record<string, number>
  byStatus: Record<string, number>
}

export interface AnomalyFinding {
  id: number
  alertKey: string
  anomalyType: string
  branch: string | null            // 'motor' | 'cellphone' | 'non-motor' | null
  customerId: number | null
  customerName: string | null
  productId: number | null
  productName: string | null
  deviceKey: string | null         // plate / IMEI / null
  policyCount: number
  policyNumbers: string | null
  status: 'open' | 'reviewing' | 'resolved' | 'dismissed'
  reviewedBy: number | null
  reviewedAt: string | null
  reviewNote: string | null
  detectedAt: string
}

export interface FindingFilters {
  anomaly_type?: string
  branch?: string
  status?: string
  search?: string
  date_from?: string
  date_to?: string
  per_page?: number
  page?: number
}

interface ListResponse {
  data: AnomalyFinding[]
  currentPage: number
  lastPage: number
  perPage: number
  total: number
}

// ── api ──────────────────────────────────────────────────────────────────────

export async function fetchAnomalySummary(): Promise<AnomalySummary> {
  const { data } = await apiClient.get<AnomalySummary>('/anomalies/summary')
  return data
}

export async function fetchAnomalyFindings(filters: FindingFilters = {}): Promise<ListResponse> {
  const { data } = await apiClient.get<ListResponse>('/anomalies/findings', { params: filters })
  return data
}

export async function reviewFinding(id: number) {
  return apiClient.post(`/anomalies/findings/${id}/review`)
}

export async function resolveFinding(id: number, notes: string) {
  return apiClient.post(`/anomalies/findings/${id}/resolve`, { notes })
}

export async function dismissFinding(id: number, notes: string) {
  return apiClient.post(`/anomalies/findings/${id}/dismiss`, { notes })
}
