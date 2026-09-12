import apiClient from './client'

/**
 * PO-in-Graphite Phase 2 — the Omni purchase orders raised for a claim.
 *
 * Everything here is RENDERED LIVE from Omni via our server-side proxy and
 * never written into Graphite: Omni owns the accounting truth, Graphite shows
 * it. The proxy holds the Omni key (it never reaches this code) and caches
 * successful lookups for ~60s.
 *
 * 404 means the `omni_po` flag is off (or the claim is unknown) — feature
 * absent, not an error worth showing.
 */

export interface OmniPurchaseOrder {
  uuid: string | null
  po_number: string | null
  /** Omni's live status — e.g. Approved / Pending / Cancelled / Rejected. */
  status: string | null
  total: string | null
  currency: string | null
  date: string | null
  supplier_name: string | null
  /** Opens the PO detail in Omni — same tab; the shared Microsoft tenant
      lands the user already signed in. */
  deep_link: string | null
}

export interface ClaimPurchaseOrdersResponse {
  enabled: boolean
  /** false = Omni unreachable / proxy not configured — render the honest
      "temporarily unavailable" state, never a stale or guessed one. */
  available: boolean
  claim_ref: string | null
  purchase_orders: OmniPurchaseOrder[]
}

export async function fetchClaimPurchaseOrders(claimId: number): Promise<ClaimPurchaseOrdersResponse> {
  const res = await apiClient.get(`/claims-v2/${claimId}/purchase-orders`)
  return res.data
}
