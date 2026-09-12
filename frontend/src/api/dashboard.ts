import apiClient from './client'

export interface ProductCount {
  product_id: number
  total: number
  active: number
  inactive: number
  cancelled: number
}

export interface DashboardStats {
  policies: {
    total: number
    active: number
    inactive: number
    cancelled: number
    expired: number
  }
  claims: {
    total: number
    approved: number
    rejected: number
    pending: number
    closed: number
    reopen: number
    /** Claims with NULL/empty status or a value outside the 5-state set —
     *  surfaced so the cards reconcile against `total`. */
    unknown: number
  }
  products: {
    commercial: ProductCount
    domestic: ProductCount
    instant: ProductCount
  }
  recent_policies: Array<{
    id: number
    policyNumber: string
    status: number
    created_at: string
    firstName: string
    lastName: string
    product_name: string
  }>
}

export async function fetchDashboardStats(): Promise<DashboardStats> {
  const { data } = await apiClient.get<DashboardStats>('/dashboard/stats')
  return data
}

// ─── Sales Performance ─────────────────────────────────────────────────────

export interface ProductBreakdown {
  product: string
  count: number
  premium: number
}

export interface PerformanceEntry {
  id: number
  name: string
  policies: number
  active: number
  cancelled: number
  premium: number
  products: ProductBreakdown[]
}

export interface SalesGraphPoint {
  period: string
  period_start?: string
  policies: number
  active: number
  cancelled: number
  premium: number
}

export interface SalesPerformanceData {
  fyStart: string
  view: 'month' | 'week'
  productId: string
  topAgents: PerformanceEntry[]
  bottomAgents: PerformanceEntry[]
  topStores: PerformanceEntry[]
  bottomStores: PerformanceEntry[]
  salesGraph: SalesGraphPoint[]
  products: Array<{ id: number; name: string }>
}

export async function fetchSalesPerformance(
  view: 'month' | 'week' = 'month',
  productId: string = 'all',
  period: 'fy' | 'this_month' | 'last_month' = 'fy'
): Promise<SalesPerformanceData> {
  const { data } = await apiClient.get<SalesPerformanceData>('/dashboard/sales-performance', {
    params: { view, product_id: productId, period },
  })
  return data
}
