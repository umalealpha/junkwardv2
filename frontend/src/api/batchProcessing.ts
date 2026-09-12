import apiClient from './client'
import type { ListMeta } from './groupPolicies'

export interface BatchReportFilters { remarks?: string; search?: string; per_page?: number; page?: number }
export interface BatchReportItem {
  id: number; fileName: string | null; filePath: string | null; uploadedBy: string | null
  status: string | null; remarks: string | null; reportFile: string | null; createdAt: string | null
}
export interface BatchReportResponse { data: BatchReportItem[]; meta: ListMeta }

export async function fetchBatchReport(filters: BatchReportFilters = {}): Promise<BatchReportResponse> {
  const { data } = await apiClient.get<BatchReportResponse>('/batch-report', { params: filters })
  return data
}
