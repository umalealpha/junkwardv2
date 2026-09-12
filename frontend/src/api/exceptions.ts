import apiClient from './client'

// ── Reconciliation Exceptions API ─────────────────────────────────────────────
// Backend: routes/api_v1.php → finance/exceptions/* (ExceptionsController)

export type FlagCode = 'A' | 'B' | 'C' | 'D' | 'STATEMENT_REFLECTION'
// 'DOMG' | 'COMG' for RealPay-mandate exceptions; statement-reflection exceptions
// carry a product name (any string). Keep the literals for autocomplete, allow strings.
export type Product = 'DOMG' | 'COMG' | (string & {})
export type Severity = 'low' | 'medium' | 'high' | 'critical'
export type ExStatus = 'open' | 'reviewing' | 'accepted' | 'disputed' | 'resolved'

export interface ExceptionRun {
  id: number
  source: string
  period_label: string | null
  run_date: string
  status: 'generating' | 'ready' | 'reviewing' | 'closed' | 'failed'
  exception_count: number
  open_count: number
  totals: Record<string, any> | null
  created_at?: string
}

export interface ExceptionRow {
  id: number
  product: Product
  flag_code: FlagCode
  flag_label: string
  policy_number: string | null
  customer_id: number | null
  contract_number: string | null
  severity: Severity
  graphite_value: number | null
  realpay_value: number | null
  variance: number | null
  status: ExStatus
  reviewed_at: string | null
  comment_count: number
}

export interface ExceptionComment {
  id: number
  user_id: number | null
  user_name: string | null
  comment: string
  created_at: string
}

export interface ExceptionSummary {
  run: ExceptionRun | null
  byFlagProduct: Array<{ flag_code: FlagCode; product: Product; n: number }>
  byStatus: Record<string, number>
  bySeverity: Record<string, number>
  trend: Array<{ run_date: string; exception_count: number; open_count: number }>
}

export interface ListParams {
  run_id?: number
  product?: string
  flag?: string
  status?: string
  severity?: string
  search?: string
  sort?: string
  dir?: 'asc' | 'desc'
  page?: number
  per_page?: number
}

export async function listRuns(limit = 12): Promise<ExceptionRun[]> {
  const r = await apiClient.get('/finance/exceptions/runs', { params: { limit } })
  return r.data?.data ?? []
}

export async function getSummary(runId?: number): Promise<ExceptionSummary> {
  const r = await apiClient.get('/finance/exceptions/summary', { params: runId ? { run_id: runId } : {} })
  return r.data
}

export async function listExceptions(params: ListParams = {}) {
  const r = await apiClient.get('/finance/exceptions', { params })
  return r.data // Laravel paginator: { data, current_page, last_page, total, ... }
}

export async function getException(id: number) {
  const r = await apiClient.get(`/finance/exceptions/${id}`)
  return r.data as { exception: any; run: ExceptionRun; comments: ExceptionComment[] }
}

export async function addExceptionComment(id: number, comment: string) {
  const r = await apiClient.post(`/finance/exceptions/${id}/comments`, { comment })
  return r.data
}

export async function updateExceptionStatus(id: number, status: ExStatus, comment?: string) {
  const r = await apiClient.post(`/finance/exceptions/${id}/status`, { status, comment })
  return r.data
}

export async function generateExceptions() {
  const r = await apiClient.post('/finance/exceptions/generate', {})
  return r.data
}
