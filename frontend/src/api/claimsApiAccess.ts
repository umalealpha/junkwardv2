import apiClient from './client'

/**
 * Claims "API Access" API client (Claims Tracker -> Graphite port).
 *
 * The Tracker's "API Access Monitor" showed a per-request /api/* access log and
 * an IP whitelist ("Trusted Sources"). Graphite has neither table. Its genuine
 * "who can call the API" source is the Sanctum personal-access-token registry,
 * so this read-only endpoint (GET /claims-v2/api-access, Admin/Super-Admin only)
 * returns token METADATA + usage stats — never the token secret.
 */

export interface ApiAccessStats {
  total_tokens: number
  active_tokens: number
  expired_tokens: number
  used_24h: number
  used_7d: number
  never_used: number
  supports_expiry: boolean
}

export interface ApiAccessToken {
  id: number
  name: string | null
  owner: string | null
  abilities: string[]
  last_used_at: string | null
  expires_at: string | null
  created_at: string | null
  expired: boolean
}

export interface ApiAccessResponse {
  source: string
  available: boolean
  stats: ApiAccessStats | null
  tokens: ApiAccessToken[]
  note: string
}

export async function fetchClaimsApiAccess(limit = 200): Promise<ApiAccessResponse> {
  const { data } = await apiClient.get<ApiAccessResponse>('/claims-v2/api-access', {
    params: { limit },
  })
  return data
}
