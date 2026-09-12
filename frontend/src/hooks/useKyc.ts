import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  fetchKycList, fetchEmployerGroupKyc, fetchDuplicateCustomers, fetchDeduplication,
  updateKycStatus, fetchKycDetail, verifyKycDocument, runOpenSanctionsCheck,
  fetchDomComKycList, fetchDomComKycDetail, verifyDomComKycDocument, updateDomComKycStatus,
  type KycFilters, type DuplicateCustomerFilters, type DeduplicationFilters,
} from '../api/kyc'
import { fetchKycAccessReport, type KycAccessReportFilters } from '../api/kycAccessReport'

export function useKycAccessReport(filters: KycAccessReportFilters = {}) {
  return useQuery({
    queryKey: ['kyc-access-report', filters],
    queryFn: () => fetchKycAccessReport(filters),
    placeholderData: (prev) => prev,
    staleTime: 2 * 60 * 1000,
    refetchOnWindowFocus: false,
  })
}

export function useKycList(filters: KycFilters = {}) {
  return useQuery({
    queryKey: ['kyc', filters],
    queryFn: () => fetchKycList(filters),
    placeholderData: (prev) => prev,
    staleTime: 2 * 60 * 1000,
    refetchOnWindowFocus: false,
  })
}

export function useUpdateKycStatus() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ kycId, status, remark }: { kycId: number; status: 'approved' | 'rejected'; remark?: string }) =>
      updateKycStatus(kycId, status, remark),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['kyc'] }) },
  })
}

export function useKycDetail(kycId: number) {
  return useQuery({
    queryKey: ['kyc-detail', kycId],
    queryFn: () => fetchKycDetail(kycId),
    enabled: kycId > 0,
    staleTime: 60 * 1000,
  })
}

export function useVerifyKycDocument() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ kycId, document, status, remark, expiryDate }: { kycId: number; document: string; status: number; remark?: string; expiryDate?: string | null }) =>
      verifyKycDocument(kycId, document, status, remark, expiryDate),
    onSuccess: (_, vars) => { qc.invalidateQueries({ queryKey: ['kyc-detail', vars.kycId] }) },
  })
}

export function useRunOpenSanctionsCheck() {
  const qc = useQueryClient()
  return useMutation({
    // Keyed on customer.id. On success, refresh the KYC detail so the
    // sanctions panel (countries/last-scan/status) reflects the new scan.
    mutationFn: ({ customerId }: { customerId: number; kycId?: number }) =>
      runOpenSanctionsCheck(customerId),
    onSuccess: (_, vars) => {
      if (vars.kycId) qc.invalidateQueries({ queryKey: ['kyc-detail', vars.kycId] })
    },
  })
}

export function useEmployerGroupKyc(filters: { search?: string; per_page?: number; page?: number } = {}) {
  return useQuery({
    queryKey: ['kyc-employer-group', filters],
    queryFn: () => fetchEmployerGroupKyc(filters),
    placeholderData: (prev) => prev,
    staleTime: 2 * 60 * 1000,
    refetchOnWindowFocus: false,
  })
}

export function useDuplicateCustomers(filters: DuplicateCustomerFilters = {}) {
  return useQuery({
    queryKey: ['kyc-duplicates', filters],
    queryFn: () => fetchDuplicateCustomers(filters),
    placeholderData: (prev) => prev,
    staleTime: 2 * 60 * 1000,
    refetchOnWindowFocus: false,
  })
}

export function useDeduplication(filters: DeduplicationFilters = {}) {
  return useQuery({
    queryKey: ['kyc-deduplication', filters],
    queryFn: () => fetchDeduplication(filters),
    placeholderData: (prev) => prev,
    staleTime: 2 * 60 * 1000,
    refetchOnWindowFocus: false,
  })
}

// ─── DOM/COM-tier hooks ─────────────────────────────────────────────

export function useDomComKycList(filters: KycFilters = {}) {
  return useQuery({
    queryKey: ['dom-com-kyc', filters],
    queryFn: () => fetchDomComKycList(filters),
    placeholderData: (prev) => prev,
    staleTime: 2 * 60 * 1000,
    refetchOnWindowFocus: false,
  })
}

export function useDomComKycDetail(kycId: number) {
  return useQuery({
    queryKey: ['dom-com-kyc-detail', kycId],
    queryFn: () => fetchDomComKycDetail(kycId),
    enabled: kycId > 0,
    staleTime: 60 * 1000,
  })
}

export function useVerifyDomComKycDocument() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ kycId, document, status, remark, expiryDate }: {
      kycId: number
      document: string
      status: number
      remark?: string
      expiryDate?: string
    }) => verifyDomComKycDocument(kycId, document, status, remark, expiryDate),
    onSuccess: (_, vars) => { qc.invalidateQueries({ queryKey: ['dom-com-kyc-detail', vars.kycId] }) },
  })
}

export function useUpdateDomComKycStatus() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ kycId, status, remark }: { kycId: number; status: 'approved' | 'rejected'; remark?: string }) =>
      updateDomComKycStatus(kycId, status, remark),
    onSuccess: (_, vars) => {
      qc.invalidateQueries({ queryKey: ['dom-com-kyc'] })
      qc.invalidateQueries({ queryKey: ['dom-com-kyc-detail', vars.kycId] })
    },
  })
}
