import { useQuery } from '@tanstack/react-query'
import { getCronLogNames, getCronLogTail, type CronLogTailParams } from '../api/cronLogs'

export function useCronLogNames() {
  return useQuery({
    queryKey: ['cron-log-names'],
    queryFn: getCronLogNames,
    staleTime: 5 * 60 * 1000,
    refetchOnWindowFocus: false,
  })
}

export function useCronLogTail(params: CronLogTailParams, opts?: { enabled?: boolean; refetchMs?: number }) {
  return useQuery({
    queryKey: ['cron-log-tail', params],
    queryFn: () => getCronLogTail(params),
    enabled: opts?.enabled ?? true,
    staleTime: 5 * 1000,
    refetchInterval: opts?.refetchMs ?? false,
    refetchOnWindowFocus: false,
  })
}
