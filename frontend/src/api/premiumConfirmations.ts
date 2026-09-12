import apiClient from './client'

/**
 * Premium Confirmation Tracker.
 *
 * `overdue` and `hoursOpen` are computed SERVER-side and read straight off the
 * response — deliberately not re-derived here. Two places deriving the same
 * figure is how a screen ends up disagreeing with the report it is meant to
 * summarise.
 *
 * 404 means the `premium_confirmation` flag is off, not an error worth showing.
 */

export interface PremiumConfirmationRow {
  id: number
  claim_id: number
  claim_number: string | null
  claim_type: string | null
  status: 'auto_released' | 'pending_finance' | 'confirmed' | 'queried'
  light: 'green' | 'amber' | 'red' | null
  premium_status: string | null
  balance: string | number | null
  premium: string | number | null
  premiums_outstanding: number
  unpaid_from: string | null
  last_payment_date: string | null
  /** Three or more premiums outstanding — settlement waits for management. */
  settlement_hold: boolean | number
  raised_at: string | null
  due_at: string | null
  released_at: string | null
  released_by: string | null
  finance_comment: string | null
  collection_drafted_at: string | null
  collection_sent_at: string | null
  hoursOpen: number | null
  overdue: boolean
  /** The breach fell on a weekend — the clock is calendar hours by decision. */
  breachedOnRest: boolean
}

export interface PremiumConfirmationList {
  data: PremiumConfirmationRow[]
  meta: { total: number; per_page: number; current_page: number; last_page: number }
  summary: {
    open: number
    overdue: number
    autoReleased: number
    onHold: number
    slaHours: number
  }
}

export async function getPremiumConfirmations(params: {
  status?: string
  light?: 'green' | 'amber' | 'red'
  overdue?: boolean
  page?: number
  per_page?: 15 | 25 | 50
} = {}): Promise<PremiumConfirmationList> {
  const { data } = await apiClient.get('/claims-v2/premium-confirmations', { params })
  return data
}

export async function recordPremiumDecision(
  id: number,
  decision: 'confirmed' | 'queried',
  comment: string,
): Promise<{ status: string }> {
  const { data } = await apiClient.post(`/claims-v2/premium-confirmations/${id}/record`, {
    decision,
    comment,
  })
  return data.data
}

export async function draftCollectionLetter(
  id: number,
): Promise<{ audience: string; subject: string; body: string }> {
  const { data } = await apiClient.post(`/claims-v2/premium-confirmations/${id}/draft-collection`)
  return data.data
}
