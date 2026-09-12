import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  fetchReinsuranceTypes,
  fetchReinsuranceType,
  createReinsuranceType,
  updateReinsuranceType,
  deleteReinsuranceType,
  fetchReinsuranceFormulas,
  fetchReinsuranceFormula,
  createReinsuranceFormula,
  updateReinsuranceFormula,
  deleteReinsuranceFormula,
  fetchReinsuranceCoverageGroups,
  fetchReinsuranceCoverageGroup,
  fetchProductCoverages,
  createReinsuranceCoverageGroup,
  updateReinsuranceCoverageGroup,
  deleteReinsuranceCoverageGroup,
  fetchReinsuranceTreaties,
  fetchReinsuranceTreaty,
  createReinsuranceTreaty,
  updateReinsuranceTreaty,
  deleteReinsuranceTreaty,
  rolloverReinsuranceTreaty,
  fetchReinsuranceFormLookups,
} from '../api/reinsurance'
import type { TreatyRolloverPayload } from '../api/reinsurance'

// ─── Reinsurance Types ────────────────────────────────────────────────────────

export function useReinsuranceTypes(filters: { search?: string; per_page?: number; page?: number } = {}) {
  return useQuery({
    queryKey: ['reinsurance-types', filters],
    queryFn: () => fetchReinsuranceTypes(filters),
    placeholderData: (prev) => prev,
    staleTime: 2 * 60 * 1000,
    refetchOnWindowFocus: false,
  })
}

export function useReinsuranceType(id: number | null) {
  return useQuery({
    queryKey: ['reinsurance-type', id],
    queryFn: () => fetchReinsuranceType(id!),
    enabled: id != null,
    staleTime: 0,
  })
}

export function useCreateReinsuranceType() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: createReinsuranceType,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['reinsurance-types'] }),
  })
}

export function useUpdateReinsuranceType() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Parameters<typeof updateReinsuranceType>[1] }) =>
      updateReinsuranceType(id, payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['reinsurance-types'] }),
  })
}

export function useDeleteReinsuranceType() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: deleteReinsuranceType,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['reinsurance-types'] }),
  })
}

// ─── Reinsurance Formulas ─────────────────────────────────────────────────────

export function useReinsuranceFormulas(filters: { search?: string; per_page?: number; page?: number } = {}) {
  return useQuery({
    queryKey: ['reinsurance-formulas', filters],
    queryFn: () => fetchReinsuranceFormulas(filters),
    placeholderData: (prev) => prev,
    staleTime: 2 * 60 * 1000,
    refetchOnWindowFocus: false,
  })
}

export function useReinsuranceFormula(id: number | null) {
  return useQuery({
    queryKey: ['reinsurance-formula', id],
    queryFn: () => fetchReinsuranceFormula(id!),
    enabled: id != null,
    staleTime: 0,
  })
}

export function useCreateReinsuranceFormula() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: createReinsuranceFormula,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['reinsurance-formulas'] }),
  })
}

export function useUpdateReinsuranceFormula() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Record<string, unknown> }) =>
      updateReinsuranceFormula(id, payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['reinsurance-formulas'] }),
  })
}

export function useDeleteReinsuranceFormula() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: deleteReinsuranceFormula,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['reinsurance-formulas'] }),
  })
}

// ─── Coverage Groups ──────────────────────────────────────────────────────────

export function useReinsuranceCoverageGroups(filters: { search?: string; per_page?: number; page?: number } = {}) {
  return useQuery({
    queryKey: ['reinsurance-coverage-groups', filters],
    queryFn: () => fetchReinsuranceCoverageGroups(filters),
    placeholderData: (prev) => prev,
    staleTime: 2 * 60 * 1000,
    refetchOnWindowFocus: false,
  })
}

export function useReinsuranceCoverageGroup(id: number | null) {
  return useQuery({
    queryKey: ['reinsurance-coverage-group', id],
    queryFn: () => fetchReinsuranceCoverageGroup(id!),
    enabled: id != null,
    staleTime: 0,
  })
}

// Group changes also alter the group dropdown on the Formula screen, which is fed
// by the form-lookups query — invalidate both or the new group stays hidden for
// staleTime (5 min) with no refetch on focus.
const GROUP_KEYS = [['reinsurance-coverage-groups'], ['reinsurance-form-lookups']]

function invalidateGroupQueries(qc: ReturnType<typeof useQueryClient>) {
  GROUP_KEYS.forEach(queryKey => qc.invalidateQueries({ queryKey }))
}

export function useCreateReinsuranceCoverageGroup() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: createReinsuranceCoverageGroup,
    onSuccess: () => invalidateGroupQueries(qc),
  })
}

export function useUpdateReinsuranceCoverageGroup() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Parameters<typeof updateReinsuranceCoverageGroup>[1] }) =>
      updateReinsuranceCoverageGroup(id, payload),
    onSuccess: () => invalidateGroupQueries(qc),
  })
}

export function useDeleteReinsuranceCoverageGroup() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: deleteReinsuranceCoverageGroup,
    onSuccess: () => invalidateGroupQueries(qc),
  })
}

export function useProductCoverages(productId: number | null) {
  return useQuery({
    queryKey: ['product-coverages', productId],
    queryFn: () => fetchProductCoverages(productId!),
    enabled: productId != null,
    staleTime: 5 * 60 * 1000,
    refetchOnWindowFocus: false,
  })
}

// ─── Treaties ─────────────────────────────────────────────────────────────────

export function useReinsuranceTreaties(filters: { search?: string; per_page?: number; page?: number } = {}) {
  return useQuery({
    queryKey: ['reinsurance-treaties', filters],
    queryFn: () => fetchReinsuranceTreaties(filters),
    placeholderData: (prev) => prev,
    staleTime: 2 * 60 * 1000,
    refetchOnWindowFocus: false,
  })
}

export function useReinsuranceTreaty(id: number | null) {
  return useQuery({
    queryKey: ['reinsurance-treaty', id],
    queryFn: () => fetchReinsuranceTreaty(id!),
    enabled: id != null,
    staleTime: 0,
  })
}

export function useCreateReinsuranceTreaty() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: createReinsuranceTreaty,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['reinsurance-treaties'] }),
  })
}

export function useUpdateReinsuranceTreaty() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Record<string, unknown> }) =>
      updateReinsuranceTreaty(id, payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['reinsurance-treaties'] }),
  })
}

export function useDeleteReinsuranceTreaty() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: deleteReinsuranceTreaty,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['reinsurance-treaties'] }),
  })
}

/**
 * Rollover (clone) an existing treaty into a new period. When dry_run=true
 * the backend rolls back the transaction, so cache invalidation is skipped
 * — the caller shows the log but the list doesn't need to refresh.
 */
export function useRolloverReinsuranceTreaty() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: TreatyRolloverPayload }) =>
      rolloverReinsuranceTreaty(id, payload),
    onSuccess: (res) => {
      if (!res.data.dry_run) {
        qc.invalidateQueries({ queryKey: ['reinsurance-treaties'] })
      }
    },
  })
}

// ─── Form Lookups ─────────────────────────────────────────────────────────────

export function useReinsuranceFormLookups() {
  return useQuery({
    queryKey: ['reinsurance-form-lookups'],
    queryFn: fetchReinsuranceFormLookups,
    staleTime: 5 * 60 * 1000,
    refetchOnWindowFocus: false,
  })
}
