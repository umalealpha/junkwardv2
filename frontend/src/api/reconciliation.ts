import apiClient from './client'

export interface ReconciliationSummary {
  totalOpen: number; totalResolved: number
  bySeverity: { critical: number; high: number; medium: number; low: number }
  byType: Record<string, number>
  latestRun: { id: number; runType: string; status: string; startedAt: string; completedAt: string | null; policiesChecked: number; anomaliesFound: number } | null
  trend: { date: string; count: number }[]
}

export interface ReconciliationRun {
  id: number; runType: string; status: string; startedAt: string; completedAt: string | null
  policiesChecked: number; anomaliesFound: number; errorMessage: string | null; createdAt: string
}

export interface ReconciliationAnomaly {
  id: number; runId: number; policyId: number; policyNumber: string; customerName: string | null
  anomalyType: string; severity: string; description: string
  expectedAmount: number | null; actualAmount: number | null; difference: number | null
  paymentMethod: string | null; periodFrom: string | null; periodTo: string | null
  status: string; resolvedBy: number | null; resolvedAt: string | null; resolutionNotes: string | null
  createdAt: string
}

export interface AnomalyFilters {
  anomaly_type?: string; severity?: string; status?: string; run_id?: number
  search?: string; per_page?: number; page?: number
}

interface ListMeta { total: number | null; per_page: number; current_page: number; last_page: number | null; has_more?: boolean; from: number | null; to: number | null }

export async function fetchReconciliationSummary(): Promise<ReconciliationSummary> {
  const { data } = await apiClient.get<{ data: ReconciliationSummary }>('/reconciliation/summary')
  return data.data
}

export async function fetchReconciliationRuns(params: { per_page?: number; page?: number } = {}): Promise<{ data: ReconciliationRun[]; meta: ListMeta }> {
  const { data } = await apiClient.get('/reconciliation/runs', { params })
  return data
}

export async function fetchReconciliationAnomalies(filters: AnomalyFilters = {}): Promise<{ data: ReconciliationAnomaly[]; meta: ListMeta }> {
  const { data } = await apiClient.get('/reconciliation/anomalies', { params: filters })
  return data
}

export async function acknowledgeAnomaly(id: number) { return apiClient.post(`/reconciliation/anomalies/${id}/acknowledge`) }
export async function resolveAnomaly(id: number, notes: string) { return apiClient.post(`/reconciliation/anomalies/${id}/resolve`, { resolution_notes: notes }) }
export async function markFalsePositive(id: number) { return apiClient.post(`/reconciliation/anomalies/${id}/false-positive`) }

/** Request async export — returns job_id. Poll exportStatus(), then downloadExport(). */
export async function requestExport(filters: Omit<AnomalyFilters, 'per_page' | 'page'>): Promise<{ job_id: number; status: string }> {
  const { data } = await apiClient.post('/reconciliation/anomalies/export', filters)
  return data
}

export async function exportStatus(jobId: number): Promise<{ status: string; fileName: string; message: string }> {
  const { data } = await apiClient.get(`/reconciliation/export-status/${jobId}`)
  return data
}

export async function downloadExport(jobId: number): Promise<Blob> {
  const { data } = await apiClient.get(`/reconciliation/export-download/${jobId}`, { responseType: 'blob' })
  return data
}

/** @deprecated Use requestExport + exportStatus + downloadExport instead */
export async function exportAnomalies(filters: Omit<AnomalyFilters, 'per_page' | 'page'>): Promise<Blob> {
  const { data } = await apiClient.post('/reconciliation/anomalies/export', filters, { responseType: 'blob' })
  return data
}
