import apiClient from './client'

export interface StateRow {
  id: number
  name: string
  country_id: number
  cities_count: number | null
}

export interface CityRow {
  id: number
  name: string
  state_id: number
  state_name: string | null
}

export type StatePayload = { name: string }
export type CityPayload = { name: string; state_id: number }

export interface ListResponse<T> {
  data: T[]
  meta: { current_page: number; per_page: number; has_more: boolean }
}

export interface StateListFilters { search?: string; page?: number; per_page?: number }
export interface CityListFilters { state_id: number; search?: string; page?: number; per_page?: number }

// ─── States / Provinces (Botswana; country_id fixed server-side) ───────────
export async function fetchStates(filters: StateListFilters = {}): Promise<ListResponse<StateRow>> {
  const { data } = await apiClient.get<ListResponse<StateRow>>('/master/states', { params: filters })
  return data
}

export async function createState(payload: StatePayload): Promise<void> {
  await apiClient.post('/master/states', payload)
}

export async function updateState(id: number, payload: StatePayload): Promise<void> {
  await apiClient.put(`/master/states/${id}`, payload)
}

// ─── Cities (child of a State; list always scoped by state_id) ─────────────
export async function fetchCities(filters: CityListFilters): Promise<ListResponse<CityRow>> {
  const { data } = await apiClient.get<ListResponse<CityRow>>('/master/cities', { params: filters })
  return data
}

export async function createCity(payload: CityPayload): Promise<void> {
  await apiClient.post('/master/cities', payload)
}

export async function updateCity(id: number, payload: CityPayload): Promise<void> {
  await apiClient.put(`/master/cities/${id}`, payload)
}
