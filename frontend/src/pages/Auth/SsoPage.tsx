import { useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import apiClient from '../../api/client'
import LoadingSpinner from '../../components/common/LoadingSpinner'

/**
 * SSO Landing Page
 *
 * URL: /sso?token=XXXX
 *
 * Flow:
 * 1. User clicks "React Portal" in Graphite backend
 * 2. Backend generates a one-time token and redirects to /sso?token=XXXX
 * 3. This page exchanges the token for a Sanctum API token
 * 4. Stores the token in localStorage and redirects to dashboard
 */
export default function SsoPage() {
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const token = searchParams.get('token')

    if (!token) {
      setError('No SSO token provided')
      return
    }

    exchangeToken(token)
  }, []) // eslint-disable-line react-hooks/exhaustive-deps

  async function exchangeToken(ssoToken: string) {
    try {
      const res = await apiClient.post('/auth/sso/exchange', { token: ssoToken })
      const { token, user, lookups } = res.data

      // Store auth data — same format as normal login
      localStorage.setItem('sanctum_token', token)
      localStorage.setItem('user', JSON.stringify(user))

      if (user.roles) {
        localStorage.setItem('roles', JSON.stringify(user.roles))
        localStorage.setItem('user_roles', JSON.stringify(user.roles))
      }
      if (user.permissions) {
        localStorage.setItem('permissions', JSON.stringify(user.permissions))
        localStorage.setItem('user_permissions', JSON.stringify(user.permissions))
      }
      if (lookups) localStorage.setItem('cached_lookups', JSON.stringify(lookups))

      // Redirect to dashboard
      navigate('/', { replace: true })
    } catch (err: any) {
      const msg = err?.response?.data?.error ?? 'SSO token exchange failed'
      setError(msg)
    }
  }

  if (error) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="bg-white rounded-2xl shadow-lg p-8 max-w-md text-center">
          <div className="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
            <svg className="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </div>
          <h2 className="text-lg font-bold text-gray-900 mb-2">SSO Failed</h2>
          <p className="text-sm text-gray-500 mb-6">{error}</p>
          <a
            href="/login"
            className="inline-block px-6 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-xl hover:bg-blue-700 transition"
          >
            Go to Login
          </a>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50">
      <div className="text-center">
        <LoadingSpinner size="lg" />
        <p className="mt-4 text-sm text-gray-500 font-medium">Signing you in...</p>
      </div>
    </div>
  )
}
