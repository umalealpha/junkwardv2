import { useNavigate, useLocation } from 'react-router-dom'

export default function StickyCreatePolicy() {
  const navigate = useNavigate()
  const location = useLocation()

  // Don't show on create page itself or login
  if (location.pathname === '/policies/create' || location.pathname === '/login') return null

  const isInnerPage = location.pathname.includes('/') && location.pathname !== '/'

  // On inner pages: compact "+" icon. On main pages: full button
  if (isInnerPage && location.pathname.split('/').length > 2) {
    return (
      <button
        onClick={() => navigate('/policies/create')}
        title="Create New Policy"
        className="fixed bottom-6 right-6 z-40 w-12 h-12 rounded-full bg-gradient-to-br from-blue-600 to-indigo-700 text-white shadow-lg hover:shadow-xl hover:scale-105 transition-all flex items-center justify-center group"
      >
        <svg className="w-6 h-6 group-hover:rotate-90 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M12 4v16m8-8H4" />
        </svg>
      </button>
    )
  }

  return (
    <button
      onClick={() => navigate('/policies/create')}
      className="fixed bottom-6 right-6 z-40 flex items-center gap-2 px-5 py-3 rounded-full bg-gradient-to-r from-blue-600 to-indigo-700 text-white text-sm font-semibold shadow-lg hover:shadow-xl hover:scale-105 transition-all"
    >
      <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
      </svg>
      Create Policy
    </button>
  )
}
