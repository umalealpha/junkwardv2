import { useQuery } from '@tanstack/react-query'
import { fetchProducts } from '../api/products'

export function useProducts() {
  return useQuery({
    queryKey: ['products'],
    queryFn: fetchProducts,
    staleTime: 30 * 60 * 1000, // 30 min — products rarely change
    refetchOnWindowFocus: false,
  })
}
