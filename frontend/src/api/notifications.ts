import apiClient from './client'

export interface Notification {
  id: number
  type: string
  data: {
    title: string
    message: string
    policy_id?: number
    policy_number?: string
    claim_id?: number
    claim_number?: string
    claim_type?: string
    customer_name?: string
    job_id?: number
    file_name?: string
    [key: string]: any
  }
  action: string | null
  read_at: string | null
  created_at: string
  time_ago: string
}

export async function fetchNotifications(unreadOnly = false): Promise<{ data: Notification[]; unread_count: number }> {
  const params: any = { limit: 30 }
  if (unreadOnly) params.unread_only = 1
  const { data } = await apiClient.get('/notifications', { params })
  return data
}

export async function markNotificationRead(id: number): Promise<void> {
  await apiClient.put(`/notifications/${id}/read`)
}

export async function markNotificationUnread(id: number): Promise<void> {
  await apiClient.put(`/notifications/${id}/unread`)
}

export async function markAllNotificationsRead(): Promise<void> {
  await apiClient.post('/notifications/mark-all-read')
}
