import apiClient from './client'

/**
 * Inflation rate master — the UW-owned percentages the renewal uplift cron
 * (`policy:inflate-buildings-si --master`) applies to sums insured.
 *
 * Every matcher is nullable and null means "any": one rule can cover a whole
 * product, or a single line of a single section inside a sum-insured band.
 */
export interface InflationRate {
  id: number
  name: string | null
  product_id: number | null
  coverage_id: number | null
  coverage_name: string | null
  sub_coverage_id: number | null
  sub_coverage_name: string | null
  si_from: number | null
  si_to: number | null
  transaction_type: string | null
  effective_from: string | null
  effective_to: string | null
  pct: number
  priority: number
  is_active: boolean
  notes: string | null
  /** How specific the rule is — the higher one wins when several match a line. */
  specificity: number
  /** Server-rendered one-liner, the same text the cron prints. */
  summary: string
  updated_at: string
}

export type InflationRatePayload = {
  name?: string | null
  product_id?: number | null
  coverage_id?: number | null
  coverage_name?: string | null
  sub_coverage_id?: number | null
  sub_coverage_name?: string | null
  si_from?: number | null
  si_to?: number | null
  transaction_type?: string | null
  effective_from?: string | null
  effective_to?: string | null
  pct: number
  priority?: number
  is_active?: boolean
  notes?: string | null
}

export interface InflationRateFilters {
  product_id?: number
  active?: 0 | 1
  search?: string
}

/**
 * One dropdown entry = one NAME, not one coverage-master row.
 *
 * The legacy master repeats the same Description across many rows, so entries
 * are deduped by name server-side. `id` is set only when the name maps to a
 * single master row; when it maps to several, the rule is saved by name and
 * matches all of them (`duplicates` says how many).
 */
export interface CoverageLineOption {
  id: number | null
  ids: number[]
  name: string
  duplicates: number
}

export interface CoverageSectionOption {
  id: number | null
  ids: number[]
  name: string
  duplicates: number
  codes: string[]
  lines: CoverageLineOption[]
}

export interface InflationRateOptions {
  products: { id: number; name: string }[]
  sections: CoverageSectionOption[]
  transaction_types: string[]
}

export interface InflationImpactRow {
  policy_number: string
  product_id: number
  action_id: number
  effective_from: string
  section: string | null
  line: string | null
  si_before: number
  si_after: number
  premium_before: number
  premium_after: number
}

export interface InflationImpact {
  rule: InflationRate
  summary: {
    policies: number
    actions: number
    lines: number
    si_before: number
    si_after: number
    premium_before: number
    premium_after: number
    already_applied: number
  }
  sample: InflationImpactRow[]
  /** Honest caveat from the server — show it, do not swallow it. */
  note: string
}

export interface InflationAppliedRow {
  id: number
  rule_id: number
  rule_name: string | null
  policy_number: string | null
  action_id: number
  line: string | null
  pct: number
  si_before: number
  si_after: number
  premium_before: number
  premium_after: number
  run_marker: string | null
  applied_at: string
}

export async function fetchInflationRates(filters: InflationRateFilters = {}): Promise<InflationRate[]> {
  const { data } = await apiClient.get<{ data: InflationRate[] }>('/inflation-rates', { params: filters })
  return data.data ?? []
}

export async function fetchInflationRateOptions(productId?: number | null): Promise<InflationRateOptions> {
  const { data } = await apiClient.get<InflationRateOptions>('/inflation-rates/options', {
    params: productId ? { product_id: productId } : {},
  })
  return data
}

export async function createInflationRate(payload: InflationRatePayload): Promise<void> {
  await apiClient.post('/inflation-rates', payload)
}

export async function updateInflationRate(id: number, payload: InflationRatePayload): Promise<void> {
  await apiClient.put(`/inflation-rates/${id}`, payload)
}

export async function deleteInflationRate(id: number): Promise<void> {
  await apiClient.delete(`/inflation-rates/${id}`)
}

export async function fetchInflationImpact(id: number, limit = 25): Promise<InflationImpact> {
  const { data } = await apiClient.get<InflationImpact>(`/inflation-rates/${id}/impact`, { params: { limit } })
  return data
}

export async function fetchInflationApplied(params: { rule_id?: number; policy?: string; limit?: number } = {}): Promise<{ data: InflationAppliedRow[]; total: number }> {
  const { data } = await apiClient.get<{ data: InflationAppliedRow[]; total: number }>('/inflation-rates/applied', { params })
  return data
}
