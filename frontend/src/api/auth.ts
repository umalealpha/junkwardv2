import apiClient from './client'

export interface LoginPayload {
  email: string
  password: string
  device?: string
}

export interface AuthUser {
  id: number
  name: string
  email: string
  role: string | null
  roles?: string[]
  permissions?: string[]
}

export interface LoginResponse {
  token: string
  expires_at: string
  user: AuthUser
}

export async function login(payload: LoginPayload): Promise<LoginResponse> {
  const { data } = await apiClient.post<LoginResponse>('/auth/login', payload)
  localStorage.setItem('sanctum_token', data.token)
  localStorage.setItem('user', JSON.stringify(data.user))
  if (data.user.permissions) {
    localStorage.setItem('user_permissions', JSON.stringify(data.user.permissions))
  }
  if (data.user.roles) {
    localStorage.setItem('user_roles', JSON.stringify(data.user.roles))
  }
  // Cache lookups from login response — no more separate API calls needed
  if ((data as any).lookups) {
    localStorage.setItem('cached_lookups', JSON.stringify((data as any).lookups))
  }
  return data
}

/** Get cached lookups from login response */
export function getCachedLookups(): Record<string, any[]> | null {
  const raw = localStorage.getItem('cached_lookups')
  return raw ? JSON.parse(raw) : null
}

export function logout(): void {
  // Clear local state immediately — don't wait for API (can be slow)
  localStorage.removeItem('sanctum_token')
  localStorage.removeItem('user')
  localStorage.removeItem('user_permissions')
  localStorage.removeItem('user_roles')
  localStorage.removeItem('cached_lookups')
  // Fire-and-forget server logout
  apiClient.post('/auth/logout').catch(() => {})
}

export function getStoredPermissions(): string[] {
  const raw = localStorage.getItem('user_permissions')
  return raw ? JSON.parse(raw) : []
}

export function getStoredRoles(): string[] {
  const raw = localStorage.getItem('user_roles')
  return raw ? JSON.parse(raw) : []
}

export function getStoredUser(): AuthUser | null {
  const raw = localStorage.getItem('user')
  return raw ? JSON.parse(raw) : null
}

export function isAuthenticated(): boolean {
  return !!localStorage.getItem('sanctum_token')
}

// ── Policy cancel / reinstate authorisation (mirrors backend AuthGate) ──────
// Cancellation is product-scoped: Motor Comprehensive (product 3) vs the rest
// of the instant book [1,2,4,5,9]. Reinstatement uses `policy_reinstate`.
// Managers/Admins bypass. Fail-closed: no matching permission => no button
// (no permissive "empty perms = allow" fallback). The backend is the real gate;
// these only control button visibility.
const CANCEL_INSTANT_PRODUCT_IDS = [1, 2, 4, 5, 9]
const CANCEL_MOTOR_COMP_PRODUCT_ID = 3

export function isAdminOrManager(): boolean {
  return getStoredRoles().some((r) => /admin|manager/i.test(String(r)))
}

export function canReinstate(): boolean {
  return isAdminOrManager() || getStoredPermissions().includes('policy_reinstate')
}

export function canCancelProduct(productId: number): boolean {
  if (isAdminOrManager()) return true
  const perms = getStoredPermissions()
  if (productId === CANCEL_MOTOR_COMP_PRODUCT_ID) return perms.includes('policy_cancel_motor_comp')
  if (CANCEL_INSTANT_PRODUCT_IDS.includes(productId)) return perms.includes('policy_cancel_instant')
  return false
}

// Payment reversal / refund — sensitive money-movement. ONLY Super Admin
// bypasses (NOT the general admin/manager bypass); everyone else needs the
// `payment_reversal` permission. Mirrors backend AuthGate::canReversePayment.
export function canReversePayment(): boolean {
  const isSuperAdmin = getStoredRoles().some((r) => /super\s*admin/i.test(String(r)))
  return isSuperAdmin || getStoredPermissions().includes('payment_reversal')
}
