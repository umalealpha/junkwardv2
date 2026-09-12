import apiClient from './client'
import type { ListMeta } from './kyc'

// KYC Access Audit Report — port of graphiteBWV8 admin/kycAccessReport.
// Lists users holding KYC verification permissions (via role or directly)
// plus anyone who modified KYC records in the selected date range.

export interface KycAccessReportFilters {
  from_date?: string
  to_date?: string
  search?: string
  per_page?: number
  page?: number
}

export interface KycAccessReportItem {
  id: number
  full_name: string
  email: string
  roles: string
  kyc_permissions: string
  /** 'Active' | 'No Active Role' | 'Activity Only (No Permission)' | 'No KYC Permission' */
  current_status: string
  total_kyc_actions: number
  /** Pre-formatted display strings ('d-m-Y H:i' / 'd-m-Y') or '--'. */
  first_action: string
  last_action: string
  user_created: string
}

export interface KycAccessReportResponse {
  data: KycAccessReportItem[]
  meta: ListMeta
}

export async function fetchKycAccessReport(
  filters: KycAccessReportFilters = {},
): Promise<KycAccessReportResponse> {
  const { data } = await apiClient.get<KycAccessReportResponse>('/kyc/access-report', {
    params: filters,
    // The report aggregates across users/roles/permissions + a multi-year
    // audit window; allow well beyond the 30s default so a heavier query
    // (e.g. a 5-year range on a large audits table) isn't client-cancelled.
    timeout: 120_000,
  })
  return data
}

/**
 * Download the report as .xlsx with the current date filter, triggering a
 * browser save. Routes through apiClient so the Bearer token is attached.
 */
export async function exportKycAccessReport(
  filters: Pick<KycAccessReportFilters, 'from_date' | 'to_date'> = {},
): Promise<void> {
  const response = await apiClient.get('/kyc/access-report/export', {
    params: filters,
    responseType: 'blob',
    headers: { Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' },
    timeout: 120_000,
  })

  const blob = response.data as Blob
  const disposition = (response.headers['content-disposition'] ?? '') as string
  const match = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/)
  const filename = match ? match[1].replace(/['"]/g, '') : 'KycAccessReport.xlsx'

  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = filename
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(link.href)
}
