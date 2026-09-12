import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { getCronReports, getCronConfig, getCronJobDetail, updateCronConfig, addStakeholder, deleteStakeholder, triggerCronJob } from '../api/cronReports'

export function useCronReports() {
  return useQuery({
    queryKey: ['cron-reports'],
    queryFn: getCronReports,
    staleTime: 2 * 60 * 1000,   // 2 min — reports don't change often
    refetchOnWindowFocus: false,
  })
}

export function useCronConfig() {
  return useQuery({
    queryKey: ['cron-config'],
    queryFn: getCronConfig,
    staleTime: 2 * 60 * 1000,
    refetchOnWindowFocus: false,
  })
}

export function useCronJobDetail(key: string) {
  return useQuery({
    queryKey: ['cron-config', key],
    queryFn: () => getCronJobDetail(key),
    staleTime: 60 * 1000,
    enabled: !!key,
  })
}

export function useUpdateCronConfig() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ key, data }: { key: string; data: Parameters<typeof updateCronConfig>[1] }) =>
      updateCronConfig(key, data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['cron-config'] })
    },
  })
}

export function useAddStakeholder(key: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: { email: string; name?: string }) => addStakeholder(key, data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['cron-config'] })
      qc.invalidateQueries({ queryKey: ['cron-config', key] })
    },
  })
}

export function useDeleteStakeholder(key: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deleteStakeholder(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['cron-config'] })
      qc.invalidateQueries({ queryKey: ['cron-config', key] })
    },
  })
}

export function useTriggerCronJob() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (key: string) => triggerCronJob(key),
    onSuccess: () => {
      setTimeout(() => {
        qc.invalidateQueries({ queryKey: ['cron-reports'] })
        qc.invalidateQueries({ queryKey: ['cron-config'] })
      }, 3000)
    },
  })
}
