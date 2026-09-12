import { useState } from 'react'
import apiClient from '../../api/client'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import { fmtPula } from '../../utils/format'

interface PolicyInfo {
  id: number
  policy_number: string
  customer_name: string
  product: string
  premium: number
}

export default function OfflinePaymentPage() {
  // Lookup state
  const [policyNumber, setPolicyNumber] = useState('')
  const [policyInfo, setPolicyInfo] = useState<PolicyInfo | null>(null)
  const [lookupLoading, setLookupLoading] = useState(false)
  const [lookupError, setLookupError] = useState('')

  // Form state
  const [amount, setAmount] = useState('')
  const [paymentDate, setPaymentDate] = useState('')
  const [receiptNumber, setReceiptNumber] = useState('')
  const [notes, setNotes] = useState('')

  // Submission state
  const [submitting, setSubmitting] = useState(false)
  const [alert, setAlert] = useState<{ type: 'success' | 'error'; message: string } | null>(null)

  async function handleLookup() {
    if (!policyNumber.trim()) return
    setLookupLoading(true)
    setLookupError('')
    setPolicyInfo(null)
    setAlert(null)
    try {
      const res = await apiClient.get(`/policies/lookup`, { params: { policy_number: policyNumber.trim() } })
      setPolicyInfo(res.data.data ?? res.data)
    } catch (err: any) {
      const msg = err.response?.data?.message || 'Policy not found. Please check the policy number.'
      setLookupError(msg)
    } finally {
      setLookupLoading(false)
    }
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!policyInfo) return
    setSubmitting(true)
    setAlert(null)
    try {
      await apiClient.post(`/payments/${policyInfo.id}/offline`, {
        amount: parseFloat(amount),
        payment_date: paymentDate,
        receipt_number: receiptNumber,
        notes,
      })
      setAlert({ type: 'success', message: 'Offline payment recorded successfully.' })
      // Reset form
      setAmount('')
      setPaymentDate('')
      setReceiptNumber('')
      setNotes('')
    } catch (err: any) {
      const msg = err.response?.data?.message || 'Failed to record offline payment. Please try again.'
      setAlert({ type: 'error', message: msg })
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="p-6 space-y-4">
      <h1 className="text-2xl font-bold text-gray-800">Offline Payment</h1>

      {/* Alert */}
      {alert && (
        <div className={`rounded-lg border px-4 py-3 text-sm ${
          alert.type === 'success'
            ? 'bg-green-50 border-green-200 text-green-800'
            : 'bg-red-50 border-red-200 text-red-800'
        }`}>
          <div className="flex items-center gap-2">
            {alert.type === 'success' ? (
              <svg className="w-5 h-5 text-green-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            ) : (
              <svg className="w-5 h-5 text-red-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            )}
            <span>{alert.message}</span>
            <button onClick={() => setAlert(null)} className="ml-auto text-gray-400 hover:text-gray-600">
              <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
      )}

      {/* Policy Number Lookup */}
      <div className="bg-white rounded-lg border border-gray-200 shadow-sm p-6 max-w-2xl">
        <label className="block text-sm font-medium text-gray-700 mb-1">Policy Number</label>
        <div className="flex gap-3">
          <input
            type="text"
            value={policyNumber}
            onChange={e => setPolicyNumber(e.target.value)}
            onKeyDown={e => { if (e.key === 'Enter') handleLookup() }}
            placeholder="Enter policy number..."
            className="flex-1 border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-brand-navy/30 focus:outline-none"
          />
          <button
            onClick={handleLookup}
            disabled={!policyNumber.trim() || lookupLoading}
            className="px-4 py-2 bg-brand-navy text-white rounded-md text-sm font-medium hover:bg-brand-navy-light disabled:opacity-50 transition flex items-center gap-2"
          >
            {lookupLoading && <LoadingSpinner size="sm" />}
            Lookup
          </button>
        </div>
        {lookupError && (
          <p className="mt-2 text-sm text-red-600">{lookupError}</p>
        )}
      </div>

      {/* Policy Info + Payment Form */}
      {policyInfo && (
        <div className="bg-white rounded-lg border border-gray-200 shadow-sm p-6 max-w-2xl">
          {/* Policy Summary */}
          <div className="mb-6 pb-5 border-b border-gray-100">
            <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Policy Details</h2>
            <div className="grid grid-cols-2 gap-x-8 gap-y-2 text-sm">
              <div>
                <span className="text-gray-500">Policy Number:</span>
                <span className="ml-2 font-mono font-medium text-gray-800">{policyInfo.policy_number}</span>
              </div>
              <div>
                <span className="text-gray-500">Customer:</span>
                <span className="ml-2 font-medium text-gray-800">{policyInfo.customer_name}</span>
              </div>
              <div>
                <span className="text-gray-500">Product:</span>
                <span className="ml-2 text-gray-800">{policyInfo.product}</span>
              </div>
              <div>
                <span className="text-gray-500">Premium:</span>
                <span className="ml-2 font-medium text-gray-800">
                  {fmtPula(policyInfo.premium)}
                </span>
              </div>
            </div>
          </div>

          {/* Payment Form */}
          <form onSubmit={handleSubmit} className="space-y-4">
            <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wider">Record Payment</h2>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Amount (BWP)</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  required
                  value={amount}
                  onChange={e => setAmount(e.target.value)}
                  placeholder="0.00"
                  className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-brand-navy/30 focus:outline-none"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Payment Date</label>
                <input
                  type="date"
                  required
                  value={paymentDate}
                  onChange={e => setPaymentDate(e.target.value)}
                  className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-brand-navy/30 focus:outline-none"
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Receipt Number</label>
              <input
                type="text"
                required
                value={receiptNumber}
                onChange={e => setReceiptNumber(e.target.value)}
                placeholder="Enter receipt number..."
                className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-brand-navy/30 focus:outline-none"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Notes</label>
              <textarea
                value={notes}
                onChange={e => setNotes(e.target.value)}
                placeholder="Optional notes about this payment..."
                rows={3}
                className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-brand-navy/30 focus:outline-none resize-none"
              />
            </div>

            <div className="flex justify-end pt-2">
              <button
                type="submit"
                disabled={submitting}
                className="px-6 py-2 bg-brand-navy text-white rounded-md text-sm font-medium hover:bg-brand-navy-light disabled:opacity-50 transition flex items-center gap-2"
              >
                {submitting && <LoadingSpinner size="sm" />}
                {submitting ? 'Recording...' : 'Record Payment'}
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  )
}
