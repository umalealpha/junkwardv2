import { useQuery } from '@tanstack/react-query'
import {
  getAtcSummary, getAtcShipments, getAtcPayments, getAtcClaims, getAtcEvents,
  type AtcListParams,
} from '../api/alphaTransit'

export function useAtcSummary() {
  return useQuery({
    queryKey: ['atc-summary'],
    queryFn: getAtcSummary,
    staleTime: 30 * 1000,
    refetchOnWindowFocus: false,
  })
}

export function useAtcShipments(params: AtcListParams) {
  return useQuery({
    queryKey: ['atc-shipments', params],
    queryFn: () => getAtcShipments(params),
    staleTime: 15 * 1000,
    placeholderData: (prev) => prev, // keep table steady across page flips
  })
}

export function useAtcPayments(params: AtcListParams) {
  return useQuery({
    queryKey: ['atc-payments', params],
    queryFn: () => getAtcPayments(params),
    staleTime: 15 * 1000,
    placeholderData: (prev) => prev,
  })
}

export function useAtcClaims(params: AtcListParams) {
  return useQuery({
    queryKey: ['atc-claims', params],
    queryFn: () => getAtcClaims(params),
    staleTime: 15 * 1000,
    placeholderData: (prev) => prev,
  })
}

export function useAtcEvents(params: AtcListParams) {
  return useQuery({
    queryKey: ['atc-events', params],
    queryFn: () => getAtcEvents(params),
    staleTime: 10 * 1000,
    refetchInterval: 30 * 1000, // live-ish: failed events surface without a manual reload
    placeholderData: (prev) => prev,
  })
}
