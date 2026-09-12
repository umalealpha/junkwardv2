import apiClient from './client'

/**
 * Claims Tracker dashboard feed — backs the rebuilt Claims → Dashboard body,
 * a 1:1 replica of the legacy Claims Tracker home dashboard.
 *
 * Consumes the new, purpose-built backend endpoint:
 *   GET /claims-v2/tracker-dashboard?month=YYYY-MM   (month optional)
 * which returns a single `{ data: {...} }` envelope with every section the
 * tracker dashboard renders (KPIs, registration tiles, SLA breaches, claim-type
 * summary, pipeline, overdue list, handler leaderboard) pre-shaped and
 * pre-sorted server-side.
 *
 * READ-ONLY. Do not mutate. Several numeric fields are intentionally NULLABLE
 * (see below) and must render as an em-dash "—", never as 0:
 *   - pipeline.motor/glass[].onTime | delayed | pct
 *   - leaderboard[].panelBeaterPct | glassPct
 *   - kpis.facClaims  (when kpis.facAvailable === false)
 */

export interface TrackerKpis {
  total: number
  inProgress: number
  overdueStages: number
  delayed: number
  completed: number
  avgDaysLate: number
  totalReserve: number
  totalPaid: number
  majorClaims: number
  /** null when facAvailable === false → render "—". */
  facClaims: number | null
  facAvailable: boolean
}

export interface TrackerRegistrationTiles {
  registered: number
  fnolOpen: number
  unregistered: number
}

export interface TrackerSlaBreach {
  claimId: number
  claimNumber: string
  category: 'M' | 'NM'
  clientName: string
  stageInfo: string
  daysLate: number
  deadline: string
}

export interface TrackerTypeSummaryRow {
  claimType: string
  count: number
  inProgress: number
  completed: number
  reserve: number
  paid: number
}

export interface TrackerTypeTotals {
  count: number
  inProgress: number
  completed: number
  reserve: number
  paid: number
}

export interface TrackerPipelineStage {
  stage: string
  count: number
  /** nullable → render "—", never 0. */
  onTime: number | null
  /** nullable → render "—", never 0. */
  delayed: number | null
  /** nullable → render "—", never 0. */
  pct: number | null
}

export interface TrackerNonMotorByType {
  subType: string
  count: number
}

export interface TrackerNonMotorPipeline {
  inProgress: number
  resolved: number
  breached: number
  byType: TrackerNonMotorByType[]
}

export interface TrackerPipeline {
  motor: TrackerPipelineStage[]
  glass: TrackerPipelineStage[]
  nonMotor: TrackerNonMotorPipeline
}

export interface TrackerStageChip {
  abbr: string
  onTime: boolean
}

export interface TrackerOverdueClaim {
  claimId: number
  claimNumber: string
  category: 'M' | 'NM'
  isMajor: boolean
  isFac: boolean
  client: string
  handler: string
  stage: string
  stageChips: TrackerStageChip[]
  status: string
  daysLate: number
  deadline: string
}

export interface TrackerLeaderboardRow {
  rank: number
  handler: string
  total: number
  completed: number
  onTimeRate: number
  /** nullable → render "—", never 0. */
  panelBeaterPct: number | null
  panelBeaterMet: boolean
  /** nullable → render "—", never 0. */
  glassPct: number | null
  glassMet: boolean
  overallScore: number
  /** true when the handler has < 5 claims → suppress supplier %s as "—". */
  insufficient: boolean
}

export interface ClaimsTrackerDashboard {
  month: string | null
  /** "YYYY-MM", most-recent first. */
  availableMonths: string[]
  kpis: TrackerKpis
  registrationTiles: TrackerRegistrationTiles
  /** already top-12, daysLate desc. */
  slaBreaches: TrackerSlaBreach[]
  typeSummary: TrackerTypeSummaryRow[]
  typeTotals: TrackerTypeTotals
  pipeline: TrackerPipeline
  /** already top-10. */
  overdueClaims: TrackerOverdueClaim[]
  leaderboard: TrackerLeaderboardRow[]
  /** Broker vs Direct mix, month-scoped. Broker = policy has a non-zero agent. */
  channelSplit: { channel: 'Broker' | 'Direct'; count: number }[]
  /** whole-book claims-per-month trend, chronological asc, last 12 months. */
  monthlyVolume: { month: string; count: number }[]
  /** external-ref sync tiles, month-scoped. failed is always 0 today. */
  syncTiles: { synced: number; pending: number; failed: number }
  gaps: string[]
}

/**
 * Fetch the tracker dashboard aggregate. Pass a "YYYY-MM" month to scope every
 * section to that month; omit (or null) for the whole book.
 */
export async function fetchClaimsTrackerDashboard(
  month?: string | null,
): Promise<ClaimsTrackerDashboard> {
  const { data } = await apiClient.get<{ data: ClaimsTrackerDashboard }>(
    '/claims-v2/tracker-dashboard',
    month ? { params: { month } } : undefined,
  )
  return data.data
}
