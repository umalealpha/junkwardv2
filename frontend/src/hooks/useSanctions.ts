import { useQuery } from '@tanstack/react-query'
import { fetchSanctionedCustomers } from '../api/sanctions'

export function useSanctionedCustomers() {
  return useQuery({
    queryKey: ['kyc-sanctioned'],
    queryFn: fetchSanctionedCustomers,
    staleTime: 2 * 60 * 1000,
    refetchOnWindowFocus: false,
  })
}
