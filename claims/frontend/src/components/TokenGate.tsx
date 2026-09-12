import { useEffect, useState, type ReactNode } from 'react'
import { useSearchParams } from 'react-router-dom'
import apiClient from '../api/client'

export default function TokenGate({ children }: { children: ReactNode }) {
  const [searchParams, setSearchParams] = useSearchParams()
  const [ready, setReady] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    const urlToken = searchParams.get('token')
    if (urlToken) {
      localStorage.setItem('claims_token', urlToken)
      searchParams.delete('token')
      setSearchParams(searchParams, { replace: true })
    }

    const token = localStorage.getItem('claims_token')
    if (!token) {
      setError('No access token. Please open Claims from the Graphite V2 admin portal.')
      return
    }

    apiClient.get('/auth/user').then(r => {
      localStorage.setItem('claims_user', JSON.stringify(r.data))
      setReady(true)
    }).catch(() => {
      localStorage.removeItem('claims_token')
      setError('Session expired. Please reopen Claims from Graphite V2.')
    })
  }, [])

  if (error) return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center">
      <div className="bg-white rounded-xl shadow-lg p-8 max-w-md text-center">
        <div className="w-16 h-16 bg-claims-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg className="w-8 h-8 text-claims-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
        </div>
        <h1 className="text-xl font-bold text-gray-800 mb-2">Claims -- Access Required</h1>
        <p className="text-gray-500 text-sm">{error}</p>
        <a
          href={import.meta.env.VITE_GRAPHITE_URL || 'https://graphite-v2-fe.alphadirect.co.bw'}
          className="inline-block mt-6 px-6 py-2 bg-claims-primary text-white rounded-lg text-sm hover:bg-claims-dark transition"
        >
          Go to Graphite V2
        </a>
      </div>
    </div>
  )

  if (!ready) return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center">
      <div className="animate-spin w-10 h-10 border-4 border-claims-primary border-t-transparent rounded-full" />
    </div>
  )

  return <>{children}</>
}
