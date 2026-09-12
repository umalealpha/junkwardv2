import apiClient from './client'

export interface Product {
  id: number
  name: string
  slug: string | null
  status: number
  type?: string | null
  hasVehicle?: boolean
  hasMember?: boolean
  isForStart?: boolean
}

export async function fetchProducts(): Promise<Product[]> {
  const { data } = await apiClient.get<{ data: Product[] }>('/products')
  return data.data
}

/**
 * Toggle whether a product appears on start.alphadirect.co.bw. Backend
 * busts both TAG_PRODUCTS and TAG_LOOKUPS so the public catalogue refreshes
 * on the next request.
 */
export async function setProductVisibility(id: number, isForStart: boolean): Promise<{ id: number; name: string; isForStart: number }> {
  const { data } = await apiClient.patch<{ id: number; name: string; isForStart: number }>(
    `/products/${id}/visibility`,
    { isForStart },
  )
  return data
}
