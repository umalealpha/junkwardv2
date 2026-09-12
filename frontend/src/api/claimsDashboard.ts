import apiClient from './client'

/**
 * Claims dashboard aggregate feed — backs the Claims → Dashboard screen.
 * Consumes the existing, unmodified ClaimsV2Controller::dashboard endpoint
 * (GET /claims-v2/dashboard). READ-ONLY; no new backend.
 *
 * The endpoint returns whole-book aggregates computed server-side:
 *   - openClaimsCount           claims whose status != 'Closed'
 *   - totalReserve/totalPayment net reserve + payment across all coverages
 *                               (voided originals + reversals excluded)
 *   - byStatus                  count per claim status
 *   - byType                    count per claim_type
 *   - topClaims                 the 5 highest-reserve claims
 *
 * NOTE (documented gap): the endpoint does NOT expose a type×status cross-tab
 * nor per-type reserve/payment, so the claim-type summary can only show the
 * per-type COUNT authoritatively. Per-type in-progress/completed/reserve/paid
 * would need a backend group-by (see the delivery report).
 */

export interface ClaimsDashboardStatusRow {
  status: string | null
  count: number
}

export interface ClaimsDashboardTypeRow {
  claimType: string | null
  count: number
}

export interface ClaimsDashboardTopClaim {
  claimId: number
  claimNumber: string | null
  claimType: string | null
  status: string | null
  totalReserve: number
}

export interface ClaimsDashboard {
  openClaimsCount: number
  totalReserve: number
  totalPayment: number
  balance: number
  byStatus: ClaimsDashboardStatusRow[]
  byType: ClaimsDashboardTypeRow[]
  topClaims: ClaimsDashboardTopClaim[]
}

export async function fetchClaimsDashboard(): Promise<ClaimsDashboard> {
  const { data } = await apiClient.get<{ data: ClaimsDashboard }>('/claims-v2/dashboard')
  return data.data
}
