import apiClient from './client'

/**
 * Claims analytics data client. Read-only, aggregate-only. Composes existing
 * endpoints — the claims-v2 dashboard summary here, plus the claims list
 * (api/claims) and the SLA dashboard (api/claimsSla) in the hook layer.
 *
 * Backed by ClaimsV2Controller::dashboard (GET /claims-v2/dashboard).
 */

export interface ClaimsDashboardStatusRow {
  status: string
  count: number
}

export interface ClaimsDashboardTypeRow {
  claimType: string
  count: number
}

export interface ClaimsDashboard {
  openClaimsCount: number
  totalReserve: number
  totalPayment: number
  balance: number
  byStatus: ClaimsDashboardStatusRow[]
  byType: ClaimsDashboardTypeRow[]
  topClaims: {
    claimId: number
    claimNumber: string | null
    claimType: string | null
    status: string | null
    totalReserve: number
  }[]
}

export async function fetchClaimsDashboard(): Promise<ClaimsDashboard> {
  const { data } = await apiClient.get<{ data: ClaimsDashboard }>('/claims-v2/dashboard')
  return data.data
}
