import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  getKernelJobs, getKernelStatusSummary, getKernelJobDetail,
  updateKernelJob, addKernelMail, removeKernelMail, runKernelJobNow,
} from '../api/cronKernel'

export function useKernelJobs(params?: { search?: string; server?: string; enabled?: string }) {
  return useQuery({
    queryKey: ['cron-kernel', params],
    queryFn: () => getKernelJobs(params),
    staleTime: 30 * 1000,
    refetchOnWindowFocus: false,
  })
}

export function useKernelStatusSummary() {
  return useQuery({
    queryKey: ['cron-kernel-summary'],
    queryFn: getKernelStatusSummary,
    staleTime: 30 * 1000,
    refetchInterval: 60 * 1000, // auto-refresh every 60s
  })
}

export function useKernelJobDetail(id: number | null) {
  return useQuery({
    queryKey: ['cron-kernel-detail', id],
    queryFn: () => getKernelJobDetail(id!),
    enabled: id !== null,
    staleTime: 30 * 1000,
  })
}

export function useUpdateKernelJob() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: Parameters<typeof updateKernelJob>[1] }) =>
      updateKernelJob(id, data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['cron-kernel'] })
    },
  })
}

export function useAddKernelMail(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (email: string) => addKernelMail(id, email),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['cron-kernel'] })
      qc.invalidateQueries({ queryKey: ['cron-kernel-detail', id] })
    },
  })
}

export function useRemoveKernelMail(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (email: string) => removeKernelMail(id, email),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['cron-kernel'] })
      qc.invalidateQueries({ queryKey: ['cron-kernel-detail', id] })
    },
  })
}

export function useRunKernelJobNow() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => runKernelJobNow(id),
    onSuccess: (_data, id) => {
      // Refresh the kernel list (last-run timestamps) + the detail view.
      qc.invalidateQueries({ queryKey: ['cron-kernel'] })
      qc.invalidateQueries({ queryKey: ['cron-kernel-detail', id] })
      qc.invalidateQueries({ queryKey: ['cron-kernel-summary'] })
    },
  })
}
