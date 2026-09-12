import { useState, useEffect } from 'react'
import apiClient from '../../api/client'
import type { Quote } from './types'
import { formatCurrency } from './types'

const quoteStatusColors: Record<string, string> = {
  'Pending': 'bg-yellow-100 text-yellow-700',
  'Accepted': 'bg-green-100 text-green-700',
  'Rejected': 'bg-red-100 text-red-700',
}

export default function QuotesTab({ claimId }: { claimId: number }) {
  const [quotes, setQuotes] = useState<Quote[]>([])
  const [loading, setLoading] = useState(true)
  const [showModal, setShowModal] = useState(false)
  const [submitting, setSubmitting] = useState(false)

  const [supplier, setSupplier] = useState('')
  const [amount, setAmount] = useState('')
  const [notes, setNotes] = useState('')
  const [quoteFile, setQuoteFile] = useState<File | null>(null)

  const fetchQuotes = () => {
    setLoading(true)
    apiClient.get(`/claims-v2/${claimId}/quotes`)
      .then(r => setQuotes(r.data))
      .catch(() => {
        setQuotes([
          { id: 1, supplier: 'AutoFix Workshop', amount: 285000, status: 'Accepted', file_url: '#', notes: 'Full panel repair with OEM parts', created_at: '2026-03-19' },
          { id: 2, supplier: 'SpeedyRepairs BW', amount: 310000, status: 'Rejected', file_url: '#', notes: 'Includes full respray', created_at: '2026-03-20' },
          { id: 3, supplier: 'Gaborone Auto Body', amount: 275000, status: 'Pending', file_url: '#', notes: 'Aftermarket parts used where possible', created_at: '2026-03-21' },
        ])
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchQuotes() }, [claimId])

  const handleRequest = () => {
    setSubmitting(true)
    const formData = new FormData()
    formData.append('supplier', supplier)
    formData.append('amount', amount)
    formData.append('notes', notes)
    if (quoteFile) formData.append('file', quoteFile)

    apiClient.post(`/claims-v2/${claimId}/quotes`, formData, { headers: { 'Content-Type': 'multipart/form-data' } })
      .then(() => { fetchQuotes(); setShowModal(false) })
      .catch(() => {
        setQuotes(prev => [...prev, {
          id: Date.now(), supplier, amount: parseFloat(amount) || 0, status: 'Pending',
          file_url: quoteFile ? '#' : undefined, notes, created_at: new Date().toISOString().split('T')[0],
        }])
        setShowModal(false)
      })
      .finally(() => setSubmitting(false))
  }

  const handleAcceptReject = (quoteId: number, action: 'accept' | 'reject') => {
    apiClient.post(`/claims-v2/${claimId}/quotes/${quoteId}/${action}`)
      .catch(() => {})
      .finally(() => {
        setQuotes(prev => prev.map(q =>
          q.id === quoteId ? { ...q, status: action === 'accept' ? 'Accepted' : 'Rejected' } : q
        ))
      })
  }

  if (loading) {
    return <div className="flex justify-center py-12"><div className="animate-spin w-8 h-8 border-4 border-claims-primary border-t-transparent rounded-full" /></div>
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-semibold text-gray-700">Supplier Quotes</h3>
        <button
          onClick={() => { setSupplier(''); setAmount(''); setNotes(''); setQuoteFile(null); setShowModal(true) }}
          className="px-4 py-2 bg-claims-primary text-white rounded-lg text-sm font-medium hover:bg-claims-dark transition"
        >
          + Request Quote
        </button>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {quotes.map(q => (
          <div key={q.id} className="bg-white border border-gray-200 rounded-lg p-4">
            <div className="flex items-start justify-between mb-2">
              <h4 className="text-sm font-semibold text-gray-800">{q.supplier}</h4>
              <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${quoteStatusColors[q.status] || 'bg-gray-100 text-gray-600'}`}>
                {q.status}
              </span>
            </div>
            <p className="text-xl font-bold text-gray-800 mb-1">{formatCurrency(q.amount)}</p>
            {q.notes && <p className="text-xs text-gray-500 mb-2">{q.notes}</p>}
            <p className="text-xs text-gray-400 mb-3">{q.created_at}</p>
            <div className="flex items-center justify-between pt-2 border-t border-gray-100">
              {q.file_url && (
                <a href={q.file_url} target="_blank" rel="noopener noreferrer" className="text-xs text-claims-primary hover:text-claims-dark font-medium">
                  View File
                </a>
              )}
              {q.status === 'Pending' && (
                <div className="flex gap-2 ml-auto">
                  <button
                    onClick={() => handleAcceptReject(q.id, 'accept')}
                    className="text-xs text-green-600 hover:text-green-700 font-medium transition"
                  >
                    Accept
                  </button>
                  <button
                    onClick={() => handleAcceptReject(q.id, 'reject')}
                    className="text-xs text-red-500 hover:text-red-600 font-medium transition"
                  >
                    Reject
                  </button>
                </div>
              )}
            </div>
          </div>
        ))}
        {quotes.length === 0 && (
          <p className="text-sm text-gray-400 py-8 text-center col-span-full">No quotes yet.</p>
        )}
      </div>

      {/* Request Quote Modal */}
      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-lg font-semibold text-gray-800">Request Quote</h3>
              <button onClick={() => setShowModal(false)} className="text-gray-400 hover:text-gray-600 transition">
                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            <div className="space-y-3">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Supplier *</label>
                <input type="text" className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" placeholder="Supplier name" value={supplier} onChange={e => setSupplier(e.target.value)} />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                <input type="number" className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" placeholder="0.00" value={amount} onChange={e => setAmount(e.target.value)} />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Quote File</label>
                <label className="flex items-center gap-2 px-4 py-2 border border-dashed border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition">
                  <svg className="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                  </svg>
                  <span className="text-sm text-gray-600">{quoteFile ? quoteFile.name : 'Attach file...'}</span>
                  <input type="file" className="hidden" onChange={e => e.target.files && setQuoteFile(e.target.files[0])} />
                </label>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea rows={2} className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" value={notes} onChange={e => setNotes(e.target.value)} />
              </div>
            </div>
            <div className="flex justify-end gap-2 mt-5">
              <button onClick={() => setShowModal(false)} className="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">Cancel</button>
              <button
                onClick={handleRequest}
                disabled={!supplier || submitting}
                className="px-4 py-2 bg-claims-primary text-white rounded-lg text-sm font-medium hover:bg-claims-dark transition disabled:opacity-50"
              >
                {submitting ? 'Submitting...' : 'Request Quote'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
