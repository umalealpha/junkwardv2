import apiClient from './client'
import type { ListMeta } from './kyc'

export interface VehicleInspectionFilters { search?: string; policy_number?: string; vehicle_plate?: string; per_page?: number; page?: number }
export interface VehicleInspectionItem {
  id: number; policyId: number | null; policyNumber: string | null; customerName: string | null
  vehiclePlate: string; make: string | null; model: string | null; year: string | null
  frontImage: string | null; backImage: string | null
  leftImage: string | null; rightImage: string | null
  status: number; statusLabel: string; createdAt: string | null
}
export interface VehicleInspectionResponse { data: VehicleInspectionItem[]; meta: ListMeta }

export async function fetchVehicleInspections(filters: VehicleInspectionFilters = {}): Promise<VehicleInspectionResponse> {
  const { data } = await apiClient.get<VehicleInspectionResponse>('/preinspection/vehicles', { params: filters })
  return data
}

// ── Detail + approve ──────────────────────────────────────────────
export interface InspectionImage { url: string | null; status: number | null; remark: string | null }

export interface VehicleInspectionDetail {
  id: number; policyId: number | null; policyNumber: string | null; customerName: string | null
  vehiclePlate: string; make: string | null; model: string | null; year: string | null
  status: number; statusLabel: string; compliance: number | null
  remark: string | null; reason: string | null; performedBy: string | null
  createdAt: string | null; updatedAt: string | null
  images: {
    front: InspectionImage; back: InspectionImage; left: InspectionImage
    right: InspectionImage; registration: InspectionImage; invoice: InspectionImage
  }
}

export interface VehicleApprovePayload {
  front_status?: number; back_status?: number; left_status?: number; right_status?: number
  vehicle_registration_status?: number; vehicle_invoice_status?: number
  front_image_remark?: string; back_image_remark?: string; left_image_remark?: string
  right_image_remark?: string; registration_image_remark?: string; vehicle_invoice_remark?: string
  remark?: string
}

export interface ApproveResult { message: string; status: number; statusLabel: string }

export async function fetchVehicleInspection(id: number): Promise<VehicleInspectionDetail> {
  const { data } = await apiClient.get<{ data: VehicleInspectionDetail }>(`/preinspection/vehicles/${id}`)
  return data.data
}

export async function approveVehicleInspection(id: number, payload: VehicleApprovePayload): Promise<ApproveResult> {
  const { data } = await apiClient.post<ApproveResult>(`/preinspection/vehicles/${id}/approve`, payload)
  return data
}

export interface DeviceInspectionFilters { search?: string; per_page?: number; page?: number }
export interface DeviceInspectionItem {
  id: number; policyId: number | null; policyNumber: string | null; customerName: string | null
  imei: string | null; make: string | null; model: string | null; deviceType: string | null
  deviceValue: string | number | null; deviceStatus: number | null; deviceStatusLabel: string; createdAt: string | null
}
export interface DeviceInspectionResponse { data: DeviceInspectionItem[]; meta: ListMeta }

export async function fetchDeviceInspections(filters: DeviceInspectionFilters = {}): Promise<DeviceInspectionResponse> {
  const { data } = await apiClient.get<DeviceInspectionResponse>('/preinspection/devices', { params: filters })
  return data
}

// ── Detail + approve ──────────────────────────────────────────────
export interface DeviceInspectionDetail {
  id: number; policyId: number | null; policyNumber: string | null; customerName: string | null
  imei: string | null; make: string | null; model: string | null; deviceType: string | null
  deviceValue: string | number | null; status: number | null; statusLabel: string
  remark: string | null; reason: string | null; createdAt: string | null; updatedAt: string | null
  images: {
    front: InspectionImage; back: InspectionImage; left: InspectionImage
    right: InspectionImage; top: InspectionImage; bottom: InspectionImage
  }
}

export interface DeviceApprovePayload {
  cell_phone_front_status?: number; cell_phone_back_status?: number; cell_phone_left_status?: number
  cell_phone_right_status?: number; cell_phone_top_status?: number; cell_phone_bottom_status?: number
  cell_phone_front_image_remark?: string; cell_phone_back_image_remark?: string
  cell_phone_left_image_remark?: string; cell_phone_right_image_remark?: string
  cell_phone_top_remark?: string; cell_phone_bottom_remark?: string
  remark?: string
}

export async function fetchDeviceInspection(id: number): Promise<DeviceInspectionDetail> {
  const { data } = await apiClient.get<{ data: DeviceInspectionDetail }>(`/preinspection/devices/${id}`)
  return data.data
}

export async function approveDeviceInspection(id: number, payload: DeviceApprovePayload): Promise<ApproveResult> {
  const { data } = await apiClient.post<ApproveResult>(`/preinspection/devices/${id}/approve`, payload)
  return data
}
