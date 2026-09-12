import { useQuery } from '@tanstack/react-query'
import { useIntegrations } from './useIntegrations'
import { getStoredRoles } from '../api/auth'
import { fetchClaimPurchaseOrders } from '../api/claimPurchaseOrders'

/**
 * PO-in-Graphite Phase 2 gating + query. Mirrors the useClaimsSla pattern:
 * the tab hides entirely while the `omni_po` flag is off, so the feature
 * ships dark and is armed from Admin > Integrations without a deploy.
 */

/** Mirrors the route middleware on claims-v2/{id}/purchase-orders. */
export const CLAIM_PO_VIEWER_ROLES = [
  'Claims Team', 'Claim Handler', 'Claim Processor', 'Claims Manager',
  'Finance', 'Manager', 'Admin', 'Super Admin',
]

export function useOmniPoEnabled(): boolean {
  const { data } = useIntegrations()
  return !!data?.data?.find((i) => i.integration === 'omni_po')?.enabled
}

export function useCanSeeClaimPurchaseOrders(): boolean {
  const enabled = useOmniPoEnabled()
  const mine = getStoredRoles()
  return enabled && CLAIM_PO_VIEWER_ROLES.some((r) => mine.includes(r))
}

export function useClaimPurchaseOrders(claimId: number, enabled = true) {
  return useQuery({
    queryKey: ['claim-purchase-orders', claimId],
    queryFn: () => fetchClaimPurchaseOrders(claimId),
    enabled: enabled && Number.isFinite(claimId) && claimId > 0,
    // The server already caches ~60s; matching here avoids hammering the
    // proxy on tab switches without holding PO status any longer than spec.
    staleTime: 60 * 1000,
    retry: false,
  })
}
