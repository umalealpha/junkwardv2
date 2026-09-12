import { useQuery } from '@tanstack/react-query'
import { fetchDashboardStats } from '../api/dashboard'

export function useDashboardStats() {
  return useQuery({
    queryKey: ['dashboard', 'stats'],
    queryFn: fetchDashboardStats,
    // Stats are Redis-cached on server for 5 min; refresh every 3 min on client
    staleTime: 3 * 60 * 1000,
    refetchInterval: 3 * 60 * 1000,
    refetchOnWindowFocus: false,
  })
}
