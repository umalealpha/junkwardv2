import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { login } from '../../api/auth'

export default function LoginPage() {
  const navigate = useNavigate()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setError('')
    setLoading(true)
    try {
      await login({ email, password, device: 'portal' })
      navigate('/', { replace: true })
    } catch (err: unknown) {
      // UAT 2026-05-26: when the backend returns sso_required:true (the
      // SSO-only policy applies and this user isn't an admin), redirect
      // straight to Microsoft SSO rather than just surfacing an error
      // the user has to act on. Saves a click and tells the user what
      // path to take. See AuthGate (backend) for the policy.
      const response = (err as { response?: { data?: { sso_required?: boolean, message?: string } } })?.response?.data
      if (response?.sso_required) {
        setError(response.message ?? 'Redirecting to Microsoft SSO…')
        // Brief pause so the user sees the message before the redirect
        setTimeout(() => {
          window.location.href = `${import.meta.env.VITE_API_URL}/auth/microsoft?target=react`
        }, 800)
        return
      }
      setError(response?.message ?? 'Login failed. Please check your credentials.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50">
      <div className="bg-white rounded-xl shadow-sm border border-gray-200 w-full max-w-sm p-8 space-y-6">
        <div className="text-center">
          <img src="/logo.png" alt="Alpha Direct" className="h-10 mx-auto mb-3" />
          <p className="text-sm text-gray-500">Sign In To Your Account</p>
        </div>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm text-gray-600 mb-1">Email</label>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              className="w-full border rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 focus:outline-none"
            />
          </div>
          <div>
            <label className="block text-sm text-gray-600 mb-1">Password</label>
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
              className="w-full border rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 focus:outline-none"
            />
          </div>
          {error && <p className="text-red-600 text-sm">{error}</p>}
          <button
            type="submit"
            disabled={loading}
            className="w-full bg-brand-navy text-white rounded-md py-2 text-sm font-medium hover:bg-brand-navy-light disabled:opacity-50 transition"
          >
            {loading ? 'Signing in…' : 'Sign In'}
          </button>
        </form>

        <div className="flex items-center gap-3 my-4">
          <div className="flex-1 h-px bg-gray-200" />
          <span className="text-xs text-gray-400">or</span>
          <div className="flex-1 h-px bg-gray-200" />
        </div>

        <button
          type="button"
          onClick={() => {
            // Redirect to backend SSO with target=react — after Microsoft auth,
            // backend will redirect back to React portal with an SSO token
            window.location.href = `${import.meta.env.VITE_API_URL}/auth/microsoft?target=react`
          }}
          className="w-full flex items-center justify-center gap-2.5 bg-[#2f2f2f] text-white rounded-md py-2.5 text-sm font-medium hover:bg-[#1a1a1a] transition"
        >
          <svg width="18" height="18" viewBox="0 0 21 21"><rect x="1" y="1" width="9" height="9" fill="#f25022"/><rect x="11" y="1" width="9" height="9" fill="#7fba00"/><rect x="1" y="11" width="9" height="9" fill="#00a4ef"/><rect x="11" y="11" width="9" height="9" fill="#ffb900"/></svg>
          Sign in with Microsoft
        </button>
      </div>
    </div>
  )
}
