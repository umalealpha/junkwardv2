import { useState } from 'react'

export default function ChangeCustomerPolicyPage() {
  const [policyId, setPolicyId] = useState('')
  const [customerId, setCustomerId] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null)

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!policyId || !customerId) {
      setMessage({ type: 'error', text: 'Please select both a policy and a customer.' })
      return
    }

    setSubmitting(true)
    setMessage(null)

    // Placeholder: API endpoint not yet built
    setTimeout(() => {
      setSubmitting(false)
      setMessage({ type: 'success', text: `Policy ${policyId} would be reassigned to customer ${customerId}. (API not yet connected)` })
    }, 1000)
  }

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-800">Change Customer in Policy</h1>
      </div>

      <div className="bg-white rounded-lg shadow-sm border p-6 max-w-xl">
        <p className="text-sm text-gray-500 mb-6">
          Transfer a policy from one customer to another. Select the policy and the new customer below.
        </p>

        <form onSubmit={handleSubmit} className="space-y-5">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Select Policy</label>
            <input
              type="text"
              placeholder="Enter policy number or ID..."
              value={policyId}
              onChange={e => setPolicyId(e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
            />
            <p className="text-xs text-gray-400 mt-1">Type the policy number to search.</p>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Select Customer</label>
            <input
              type="text"
              placeholder="Enter customer name, phone, or ID..."
              value={customerId}
              onChange={e => setCustomerId(e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
            />
            <p className="text-xs text-gray-400 mt-1">Type the new customer's name or ID to search.</p>
          </div>

          {message && (
            <div className={`px-4 py-3 rounded-md text-sm ${message.type === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'}`}>
              {message.text}
            </div>
          )}

          <button
            type="submit"
            disabled={submitting}
            className="px-6 py-2.5 bg-blue-600 text-white rounded-md font-medium hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {submitting ? 'Processing...' : 'Change Customer'}
          </button>
        </form>
      </div>
    </div>
  )
}
