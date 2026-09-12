import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  fetchVehicleInspections, fetchDeviceInspections,
  fetchVehicleInspection, approveVehicleInspection,
  fetchDeviceInspection, approveDeviceInspection,
  type VehicleInspectionFilters, type DeviceInspectionFilters,
  type VehicleApprovePayload, type DeviceApprovePayload,
} from '../api/preinspection'

export function useVehicleInspections(filters: VehicleInspectionFilters = {}) {
  return useQuery({
    queryKey: ['vehicle-inspections', filters],
    queryFn: () => fetchVehicleInspections(filters),
    placeholderData: (prev) => prev,
    staleTime: 2 * 60 * 1000,
    refetchOnWindowFocus: false,
  })
}

export function useDeviceInspections(filters: DeviceInspectionFilters = {}) {
  return useQuery({
    queryKey: ['device-inspections', filters],
    queryFn: () => fetchDeviceInspections(filters),
    placeholderData: (prev) => prev,
    staleTime: 2 * 60 * 1000,
    refetchOnWindowFocus: false,
  })
}

// ── Detail + approve ──────────────────────────────────────────────

export function useVehicleInspection(id: number) {
  return useQuery({
    queryKey: ['vehicle-inspection', id],
    queryFn: () => fetchVehicleInspection(id),
    enabled: Number.isFinite(id) && id > 0,
    refetchOnWindowFocus: false,
  })
}

export function useApproveVehicleInspection(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: VehicleApprovePayload) => approveVehicleInspection(id, payload),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['vehicle-inspection', id] })
      qc.invalidateQueries({ queryKey: ['vehicle-inspections'] })
    },
  })
}

export function useDeviceInspection(id: number) {
  return useQuery({
    queryKey: ['device-inspection', id],
    queryFn: () => fetchDeviceInspection(id),
    enabled: Number.isFinite(id) && id > 0,
    refetchOnWindowFocus: false,
  })
}

export function useApproveDeviceInspection(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: DeviceApprovePayload) => approveDeviceInspection(id, payload),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['device-inspection', id] })
      qc.invalidateQueries({ queryKey: ['device-inspections'] })
    },
  })
}
