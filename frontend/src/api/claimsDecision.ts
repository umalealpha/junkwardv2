import apiClient from './client'

/**
 * Claims decision-workflow API client (approve / repudiate / reverse) —
 * Claims Tracker -> Graphite migration. Backed by ClaimDecisionController, all
 * under /api/v1 and auth:sanctum.
 *
 * Gated behind the runtime `claims_decision_workflow` integration toggle
 * (Admin > Integrations, default OFF). While OFF the endpoints 404 for
 * non-admins; Admin / Super Admin can preview them regardless. The calling UI
 * checks visibility first (useCanSeeClaimDecisionPanel) and treats a 404 as
 * "feature off".
 *
 * This layer is SEPARATE from Graphite's claim status/sub-status workflow —
 * recording a decision never changes the claim's status.
 */

export type ClaimDecisionStatus = 'approved' | 'repudiated'

export interface ClaimDecision {
  id: number
  claim_id: number
  decision_status: ClaimDecisionStatus
  decided_by: number | null
  decided_by_name: string | null
  decided_by_role: string | null
  decision_date: string | null
  decision_note: string | null
  reversed_at: string | null
  reversed_by: number | null
  reversed_by_name: string | null
  reversal_note: string | null
  created_at: string | null
}

export interface ClaimDecisionState {
  claim_id: number
  current: ClaimDecision | null
  history: ClaimDecision[]
  can: {
    decide: boolean
    reverse: boolean
    preview: boolean
  }
}

export async function fetchClaimDecision(claimId: number): Promise<ClaimDecisionState> {
  const { data } = await apiClient.get<{ data: ClaimDecisionState }>(`/claims-v2/${claimId}/decision`)
  return data.data
}

export interface DecidePayload {
  status: ClaimDecisionStatus
  note?: string | null
}

export async function decideClaim(claimId: number, payload: DecidePayload): Promise<ClaimDecision> {
  const { data } = await apiClient.post<{ data: ClaimDecision }>(`/claims-v2/${claimId}/decision`, payload)
  return data.data
}

export async function reverseClaimDecision(claimId: number, note?: string | null): Promise<ClaimDecision> {
  const { data } = await apiClient.post<{ data: ClaimDecision }>(`/claims-v2/${claimId}/decision/reverse`, { note })
  return data.data
}
