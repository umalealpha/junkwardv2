import { useQuery } from '@tanstack/react-query'
import { fetchBlockList, type BlockListFilters } from '../api/blockList'

export function useBlockList(filters: BlockListFilters = {}) {
  return useQuery({
    queryKey: ['block-list', filters],
    queryFn: () => fetchBlockList(filters),
    placeholderData: (prev) => prev,
    staleTime: 2 * 60 * 1000,
    refetchOnWindowFocus: false,
  })
}
