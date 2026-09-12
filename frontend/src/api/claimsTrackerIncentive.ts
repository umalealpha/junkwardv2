import apiClient from './client'

/**
 * Claims Tracker "Handler Incentive Report" feed — backs the rebuilt
 * Claims → Incentive Report body, a 1:1 replica of the legacy Claims Tracker
 * Incentive Report screen.
 *
 * Consumes the new, purpose-built backend endpoint:
 *   GET /claims-v2/tracker-incentive?month=YYYY-MM
 * which returns a `{ data, gaps }` envelope. Every figure (per-handler totals,
 * approval %, qualify flag, and the two summary buckets) is computed
 * server-side so the frontend only presents.
 *
 * READ-ONLY presentation. The one mutation surface (marking approved
 * suppliers) is NOT part of this feed — it lives in the existing
 * claimsIncentive.ts client (SupplierFlagsPanel), reused as-is.
 *
 * The report is month-scoped: `month` is null until the user picks one, and
 * `availableMonths` drives the month <select> (most-recent first, "YYYY-MM").
 */

/** One handler row within a category (panel-beater / glass). */
export interface TrackerIncentiveHandler {
  handler: string
  /** Claims in this category handled by this handler. */
  total: number
  /** …that used an approved supplier. */
  approved: number
  /** …that did NOT. */
  nonApproved: number
  /** approved ÷ total, as a percentage. */
  pct: number
  /** true when pct meets the category threshold. */
  qualifies: boolean
}

/** One category bucket — panel-beater (motor) or glass. */
export interface TrackerIncentiveBucket {
  /** Qualifying threshold as a whole-number percentage (90 / 80). */
  threshold: number
  /** Human label, e.g. "MIS/DOM Motor Claims" / "Glass Claims". */
  label: string
  /** All claims in this category for the month. */
  totalClaims: number
  /** …that used an approved supplier. */
  approvedCount: number
  /** approvedCount ÷ totalClaims, as a percentage. */
  overallPct: number
  handlers: TrackerIncentiveHandler[]
}

export interface TrackerIncentiveData {
  /** The month the figures are for, "YYYY-MM", or null when none picked. */
  month: string | null
  /** Selectable months, "YYYY-MM", most-recent first. */
  availableMonths: string[]
  panelBeater: TrackerIncentiveBucket
  glass: TrackerIncentiveBucket
}

export interface TrackerIncentiveResponse {
  data: TrackerIncentiveData
  gaps: string[]
}

/**
 * Fetch the handler incentive report for a month. Pass a "YYYY-MM" month, or
 * omit/pass null to let the backend return the shell (availableMonths only)
 * with an empty report until a month is chosen.
 */
export async function fetchClaimsTrackerIncentive(
  month?: string | null,
): Promise<TrackerIncentiveResponse> {
  const params: Record<string, string> = {}
  if (month) params.month = month
  const { data } = await apiClient.get<TrackerIncentiveResponse>(
    '/claims-v2/tracker-incentive',
    { params },
  )
  return data
}
