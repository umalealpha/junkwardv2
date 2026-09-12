import apiClient from './client'

/**
 * Claims Tracker "All Claims" list feed — backs the rebuilt Claims → All Claims
 * body, a 1:1 replica of the legacy Claims Tracker list screen.
 *
 * Consumes the new, purpose-built backend endpoint:
 *   GET /claims-v2/tracker-list?<filters>
 * which returns a paginated `{ data, meta, availableMonths, filterOptions, gaps }`
 * envelope with every row pre-shaped and pre-sorted server-side (stage chips,
 * comment tone, decision, flags, etc.) so the frontend only presents.
 *
 * READ-ONLY presentation. The one mutation exposed here is the guarded
 * soft-delete (see softDeleteClaim), which removes a claim from the list.
 *
 * Several fields are intentionally NULLABLE and must render as an em-dash "—":
 *   - nonMotorSubType, plate, comment, decision
 *   - reserve may be 0 → render "—" (never a bare 0)
 */

/** Tone drives the Comments-column text colour (mapped to design tokens). */
export type CommentTone = 'danger' | 'warning' | 'success' | 'navy'

export type ClaimDecision = 'approved' | 'repudiated' | 'reversed'

export interface TrackerListStageChip {
  abbr: string
  /** true = on-time (green ✓), false = late (red ✗), null = omit the chip. */
  onTime: boolean | null
}

export interface TrackerListComment {
  text: string
  tone: CommentTone
}

export interface ClaimsTrackerListRow {
  claimId: number
  claimNumber: string
  clientName: string
  channel: string
  handler: string
  reportedDate: string
  claimType: string
  /** nullable → render on a 2nd line under Type, else fall back to plate. */
  nonMotorSubType: string | null
  /** nullable → shown under Type only when there's no nonMotorSubType. */
  plate: string | null
  currentStage: string
  stageChips: TrackerListStageChip[]
  overallStatus: string
  category: 'M' | 'NM'
  daysLate: number
  /** 0 / null → render "—", never a bare 0. */
  reserve: number
  /** null → render "—". */
  comment: TrackerListComment | null
  /** null → no decision badge. */
  decision: ClaimDecision | null
  isMajor: boolean
  isFac: boolean
  synced: boolean
  syncFailed: boolean
}

export interface ClaimsTrackerListMeta {
  total: number
  per_page: number
  current_page: number
  last_page: number
}

export interface ClaimsTrackerListFilterOptions {
  stages: string[]
  claimTypes: string[]
  nonMotorSubTypes: string[]
}

export interface ClaimsTrackerListResponse {
  data: ClaimsTrackerListRow[]
  meta: ClaimsTrackerListMeta
  /** "YYYY-MM", most-recent first. */
  availableMonths: string[]
  filterOptions: ClaimsTrackerListFilterOptions
  gaps: string[]
}

/** Sortable columns — value passed straight through as the `sort` param. */
export type TrackerListSortCol =
  | 'claimNumber'
  | 'clientName'
  | 'channel'
  | 'handler'
  | 'reportedDate'
  | 'claimType'
  | 'currentStage'
  | 'overallStatus'
  | 'daysLate'
  | 'reserve'

export interface ClaimsTrackerListParams {
  search?: string
  status?: string
  decision?: string
  sync?: string
  channel?: string
  claim_type?: string
  stage?: string
  month?: string
  sort?: TrackerListSortCol
  direction?: 'asc' | 'desc'
  page?: number
  /** 15 | 25 | 50 (default 15). */
  per_page?: number
}

/** Strip undefined / empty-string params so we send a clean query string. */
function cleanParams(params: ClaimsTrackerListParams): Record<string, string | number> {
  const out: Record<string, string | number> = {}
  for (const [k, v] of Object.entries(params)) {
    if (v === undefined || v === null || v === '') continue
    out[k] = v as string | number
  }
  return out
}

/**
 * Fetch one page of the tracker list. Pass any subset of filters/sort/paging;
 * empties are dropped so the backend applies its own defaults.
 */
export async function fetchClaimsTrackerList(
  params: ClaimsTrackerListParams = {},
): Promise<ClaimsTrackerListResponse> {
  const { data } = await apiClient.get<ClaimsTrackerListResponse>(
    '/claims-v2/tracker-list',
    { params: cleanParams(params) },
  )
  return data
}

/**
 * Fetch ALL rows matching the current filters (across pages) for CSV export.
 * Walks the paginated endpoint at the max allowed page size (50 — the backend
 * only accepts 15|25|50), bounded by `cap` rows so a filter-less export can
 * never runaway. Ignores the caller's page/per_page.
 */
export async function fetchAllClaimsTrackerListForExport(
  params: ClaimsTrackerListParams = {},
  cap = 5000,
): Promise<ClaimsTrackerListRow[]> {
  const perPage = 50
  const rows: ClaimsTrackerListRow[] = []
  let page = 1
  let lastPage = 1
  do {
    const res = await fetchClaimsTrackerList({ ...params, page, per_page: perPage })
    rows.push(...res.data)
    lastPage = res.meta?.last_page ?? 1
    page += 1
  } while (page <= lastPage && rows.length < cap)
  return rows.slice(0, cap)
}

/**
 * Guarded soft-delete — removes a claim from the list. RBAC is enforced both
 * client-side (button visibility) and server-side. The endpoint is being built
 * in parallel; this just wires the call.
 */
export async function softDeleteClaim(claimId: number, reason: string): Promise<void> {
  await apiClient.post(`/claims-v2/${claimId}/soft-delete`, { reason })
}
