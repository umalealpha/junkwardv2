import apiClient from './client'

/**
 * Claims Incentive Report API client (Claims Tracker -> Graphite, Phase 3).
 *
 * Backed by ClaimsIncentiveReportController on the backend. The whole feature
 * is gated behind the runtime `claims_incentive_report` integration toggle
 * (Admin > Integrations, default OFF). While OFF every endpoint here returns
 * 403 — the calling UI reads the flag first (useClaimsIncentiveEnabled) and
 * treats a 403 as "feature off" so nothing crashes if the flag flips
 * mid-session.
 *
 * Aggregate-only, read-only over existing claims / claim_quotes / suppliers
 * data (no customer PII). The one write is markSupplierIncentiveFlags, which
 * only sets the two approved-supplier boolean flags.
 */

/** Runtime flag slug — mirrors ClaimsIncentiveReportController::FLAG. */
export const CLAIMS_INCENTIVE_FLAG = 'claims_incentive_report'

/** Roles allowed to see the report + mark suppliers (route-level guard). */
export const CLAIMS_INCENTIVE_ROLES = ['Claims Manager', 'Admin', 'Super Admin']

/** Incentive criteria per category — the target approved-of-routed %. */
export const INCENTIVE_CRITERIA = {
  panelBeater: 90, // MOTOR claims routed to APPROVED panel-beaters
  glass: 80,       // GLASS claims routed to APPROVED glass suppliers
} as const

/** One category bucket — mirrors ClaimsIncentiveReport::emptyBucket(). */
export interface IncentiveBucket {
  total: number
  routed: number
  notRouted: number
  routedToApproved: number
  routedToNonApproved: number
  /** routedToApproved ÷ routed, 2dp (the headline incentive KPI). */
  approvedPctOfRouted: number
  /** routed ÷ total, 2dp. */
  routedPctOfTotal: number
}

export interface IncentiveReport {
  dateFrom: string
  dateTo: string
  panelBeater: IncentiveBucket
  glass: IncentiveBucket
}

export async function fetchIncentiveReport(
  dateFrom?: string,
  dateTo?: string,
): Promise<IncentiveReport> {
  const params: Record<string, string> = {}
  if (dateFrom) params.date_from = dateFrom
  if (dateTo) params.date_to = dateTo
  const { data } = await apiClient.get<{ data: IncentiveReport }>(
    '/claims-v2/reports/incentive',
    { params },
  )
  return data.data
}

// ── Approved-supplier flags ────────────────────────────────────────────────
// The suppliers list endpoint does NOT currently return these flags, so the
// incentive-flags panel offers explicit "mark / remove" actions rather than a
// stateful toggle. A small backend addition (surface the two flags in
// SupplierController::index) would let the UI show current state.

export interface SupplierLite {
  id: number
  name: string
  type: string | null
  email?: string | null
  location?: string | null
}

export interface SupplierPage {
  data: SupplierLite[]
  hasMore: boolean
}

/** Search suppliers by name/email (reuses GET /suppliers). */
export async function searchSuppliers(search: string, page = 1): Promise<SupplierPage> {
  const params: Record<string, string | number> = { page, per_page: 15 }
  if (search) params.search = search
  const { data } = await apiClient.get<{ data: SupplierLite[]; meta?: { has_more?: boolean } }>(
    '/suppliers',
    { params },
  )
  return { data: data.data ?? [], hasMore: !!data.meta?.has_more }
}

export interface IncentiveFlagsPayload {
  is_approved_panel_beater?: boolean
  is_approved_glass_supplier?: boolean
}

export interface IncentiveFlagsResult {
  id: number
  is_approved_panel_beater: boolean
  is_approved_glass_supplier: boolean
}

/** PATCH /suppliers/{id}/incentive-flags — sets only the supplied flags. */
export async function markSupplierIncentiveFlags(
  id: number,
  flags: IncentiveFlagsPayload,
): Promise<IncentiveFlagsResult> {
  const { data } = await apiClient.patch<{ message: string; data: IncentiveFlagsResult }>(
    `/suppliers/${id}/incentive-flags`,
    flags,
  )
  return data.data
}
