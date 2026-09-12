import apiClient from './client'

export interface ProductBreakdown {
  total: number; active: number; inactive: number; cancelled: number
}

export interface FinanceDashboardData {
  dashboardType: string
  dataDate?: string
  computedAt?: string
  products: { commercial: ProductBreakdown; domestic: ProductBreakdown; instant: ProductBreakdown }
  premiums: {
    totalActive: number; totalPremium: number; avgPremium: number
    commercialPremium: number; domesticPremium: number; instantPremium: number
  }
  collections: {
    thisWeek: number; lastWeek: number; thisMonth: number; lastMonth: number
    thisWeekCount: number; lastWeekCount: number; thisMonthCount: number; lastMonthCount: number
  }
  collectionsByMethod: { paymentMethod: string; count: number; total: string }[]
  failedPayments: { totalFailed: number; totalFailedAmount: number; failedThisWeek: number }
  policyMovement: {
    activatedThisWeek: number; activatedLastWeek: number
    activatedThisMonth: number; activatedLastMonth: number
    cancelledThisWeek: number; cancelledThisMonth: number
  }
  reconciliation: { openAnomalies: number; lastRunAt: string | null; lastRunFound: number } | null
  collectNow: {
    successToday: number
    failedToday: number
    successThisMonth: number
    failedThisMonth: number
    collectedToday: number
    collectedThisMonth: number
    dpoSuccessMonth: number
    realpaySuccessMonth: number
  } | null
}

export async function fetchFinanceDashboard(): Promise<FinanceDashboardData> {
  const { data } = await apiClient.get<FinanceDashboardData>('/dashboard/finance')
  return data
}
