import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { fetchQuotes, fetchQuoteDetail, rejectQuote, updateQuotePremium, type QuoteFilters, type UpdatePremiumPayload } from '../api/quotes'

export function useQuotes(filters: QuoteFilters = {}) {
  return useQuery({
    queryKey: ['quotes', filters],
    queryFn: () => fetchQuotes(filters),
    placeholderData: (prev) => prev,
    staleTime: 2 * 60 * 1000,
    refetchOnWindowFocus: false,
  })
}

export function useQuoteDetail(id: number) {
  return useQuery({
    queryKey: ['quote', id],
    queryFn: () => fetchQuoteDetail(id),
    staleTime: 5 * 60 * 1000,
    enabled: !!id,
  })
}

export function useRejectQuote() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => rejectQuote(id),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['quotes'] }); qc.invalidateQueries({ queryKey: ['quote'] }) },
  })
}

export function useUpdateQuotePremium() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdatePremiumPayload }) => updateQuotePremium(id, payload),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['quote'] }) },
  })
}
