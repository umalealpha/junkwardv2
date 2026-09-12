import { useQuery } from '@tanstack/react-query'
import { fetchImportActivities, type ImportActivityFilters } from '../api/excelImports'

export function useImportActivities(filters: ImportActivityFilters = {}) {
  return useQuery({
    queryKey: ['import-activities', filters],
    queryFn: () => fetchImportActivities(filters),
    placeholderData: (prev) => prev,
    staleTime: 2 * 60 * 1000,
    refetchOnWindowFocus: false,
  })
}
