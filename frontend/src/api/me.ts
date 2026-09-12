import apiClient from './client'

export interface MyProfile {
  id: number
  name: string
  firstName: string | null
  lastName: string | null
  email: string
  avatar_path: string | null
  avatar_url: string | null
  active: boolean
  roles: string[]
  role: string | null
  permissions: string[]
  account: {
    created_at: string | null
    last_login_at: string | null
    password_changed_at: string | null
    is_first_login: boolean
  }
  manager: { id: number; name: string; email: string } | null
  uw_rules: {
    role_id: number
    role_name: string
    rule_group: number | null
    rule_group_code: string | null
    rule_group_desc: string | null
  }[]
  stats: {
    tickets_raised: number
    tickets_open: number
    notifications_unread: number
    pending_approvals: number
  }
}

export async function fetchMyProfile(): Promise<MyProfile> {
  const { data } = await apiClient.get<{ data: MyProfile }>('/me/profile')
  return data.data
}

export async function uploadMyAvatar(image: Blob): Promise<{ avatar_path: string; avatar_url: string | null }> {
  const fd = new FormData()
  fd.append('image', image, 'avatar.jpg')
  const { data } = await apiClient.post<{ message: string; data: { avatar_path: string; avatar_url: string | null } }>(
    '/me/avatar', fd, { headers: { 'Content-Type': 'multipart/form-data' } },
  )
  return data.data
}

export async function deleteMyAvatar(): Promise<void> {
  await apiClient.delete('/me/avatar')
}
