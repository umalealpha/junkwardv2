import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  fetchSettlementImports, confirmSettlementImport,
  type SettlementFilters,
} from '../api/realpaySettlement'

export function useSettlementImports(filters: SettlementFilters = {}) {
  return useQuery({
    queryKey: ['realpay-settlement-imports', filters],
    queryFn: () => fetchSettlementImports(filters),
    placeholderData: (prev) => prev,
    // Preview/commit run as background jobs — poll so the status + summary
    // update in place without a manual refresh.
    refetchInterval: 5000,
    refetchOnWindowFocus: false,
  })
}

export function useConfirmSettlementImport() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => confirmSettlementImport(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['realpay-settlement-imports'] }),
  })
}
