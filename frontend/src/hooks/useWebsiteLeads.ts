import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  fetchWebsiteLeads,
  updateWebsiteLeadStatus,
  type WebsiteLead,
  type WebsiteLeadFilters,
} from '../api/websiteLeads'

export function useWebsiteLeads(filters: WebsiteLeadFilters = {}) {
  return useQuery({
    queryKey: ['website-leads', filters],
    queryFn: () => fetchWebsiteLeads(filters),
    placeholderData: (prev) => prev,
    staleTime: 2 * 60 * 1000,
    refetchOnWindowFocus: false,
  })
}

export function useUpdateWebsiteLeadStatus() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, status }: { id: number; status: WebsiteLead['status'] }) =>
      updateWebsiteLeadStatus(id, status),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['website-leads'] })
    },
  })
}
