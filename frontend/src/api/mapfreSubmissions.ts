import apiClient from './client'

// MAPFRE / MAWDY travel bind console. Backed by MapfreSubmissionsController
// (summary + list + CSV export), reading the mapfre_quote_submissions audit
// table on the V2 ops DB.
//
// A portal travel sale is bound in MAPFRE's book and never becomes a Graphite
// policy, so this is the only screen it appears on.

export interface MapfreSubmissionSummary {
  bound_today: number
  bound_month: number
  failed_month: number
  failure_rate_pct: number
  /** Still 'pending' 30+ minutes on — no response ever came back. */
  stuck_pending: number
  /** Bound upstream but carrying no TRVL number. Needs a human. */
  submitted_unnumbered: number
  as_of: string
}

export interface MapfreSubmissionRow {
  id: number
  reference: string
  status: 'pending' | 'submitted' | 'failed'
  policy_number: string | null
  mapfre_contract_number: string | null
  mapfre_quote_id: string | null
  product_id: string | null
  http_status: number | null
  error: string | null
  submitted_at: string | null
  created_at: string | null

  // Trip facts lifted out of the stored contract body. The payload itself is
  // never returned — it holds the holder's Omang/passport and address.
  holder_name: string | null
  destination: string | null
  /** MAPFRE's own DD/MM/YYYY, passed through as sent. */
  departure_date: string | null
  return_date: string | null
  travellers: number | null

  proposal_reference: string | null
  proposal_signed_at: string | null
  proposal_document: string | null
}

export interface MapfreSubmissionFilters {
  status?: 'pending' | 'submitted' | 'failed'
  from?: string
  to?: string
  search?: string
  page?: number
  per_page?: number
}

interface ListResponse {
  items: MapfreSubmissionRow[]
  meta: { total: number; per_page: number; current_page: number; last_page: number }
}

export async function fetchMapfreSubmissionSummary(): Promise<MapfreSubmissionSummary> {
  const { data } = await apiClient.get<MapfreSubmissionSummary>('/admin/mapfre-submissions/summary')
  return data
}

export async function fetchMapfreSubmissions(
  filters: MapfreSubmissionFilters = {},
): Promise<ListResponse> {
  const { data } = await apiClient.get<ListResponse>('/admin/mapfre-submissions', { params: filters })
  return data
}

/**
 * Download the filtered rows as CSV. Paging is deliberately not forwarded —
 * the export covers the whole filtered set (server-capped), not the page on
 * screen.
 */
export async function exportMapfreSubmissions(filters: MapfreSubmissionFilters = {}): Promise<void> {
  const { status, from, to, search } = filters

  const { data } = await apiClient.get('/admin/mapfre-submissions/export', {
    params: { status, from, to, search },
    responseType: 'blob',
  })

  const url = URL.createObjectURL(data as Blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `mapfre_binds_${new Date().toISOString().slice(0, 10)}.csv`
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(url)
}
