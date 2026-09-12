import type { ClaimData } from './types'
import { formatCurrency } from './types'

export default function OverviewTab({ claim }: { claim: ClaimData }) {
  return (
    <div className="space-y-6">
      {/* Claim Info Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div className="bg-gray-50 rounded-lg p-4">
          <h4 className="text-xs font-semibold text-gray-500 uppercase mb-2">Date of Loss</h4>
          <p className="text-sm font-medium text-gray-800">{claim.date_of_loss}</p>
        </div>
        <div className="bg-gray-50 rounded-lg p-4">
          <h4 className="text-xs font-semibold text-gray-500 uppercase mb-2">Location</h4>
          <p className="text-sm font-medium text-gray-800">{claim.location || '--'}</p>
        </div>
        <div className="bg-gray-50 rounded-lg p-4">
          <h4 className="text-xs font-semibold text-gray-500 uppercase mb-2">Reported By</h4>
          <p className="text-sm font-medium text-gray-800">{claim.reported_by || '--'}</p>
        </div>
        <div className="bg-gray-50 rounded-lg p-4">
          <h4 className="text-xs font-semibold text-gray-500 uppercase mb-2">Assigned To</h4>
          <p className="text-sm font-medium text-gray-800">{claim.assigned_to || 'Unassigned'}</p>
        </div>
        <div className="bg-gray-50 rounded-lg p-4 md:col-span-2">
          <h4 className="text-xs font-semibold text-gray-500 uppercase mb-2">Description</h4>
          <p className="text-sm text-gray-700">{claim.description}</p>
        </div>
      </div>

      {/* Policy Info */}
      <div className="bg-white border border-gray-200 rounded-lg p-5">
        <h3 className="text-sm font-semibold text-gray-700 mb-3">Policy Information</h3>
        <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
          <div>
            <p className="text-xs text-gray-500">Policy #</p>
            <p className="text-sm font-medium text-claims-primary">{claim.policy.policy_number}</p>
          </div>
          <div>
            <p className="text-xs text-gray-500">Product</p>
            <p className="text-sm font-medium text-gray-800">{claim.policy.product}</p>
          </div>
          <div>
            <p className="text-xs text-gray-500">Premium</p>
            <p className="text-sm font-medium text-gray-800">{formatCurrency(claim.policy.premium)}</p>
          </div>
          <div>
            <p className="text-xs text-gray-500">Customer</p>
            <p className="text-sm font-medium text-gray-800">{claim.policy.customer_name}</p>
          </div>
          <div>
            <p className="text-xs text-gray-500">Agent</p>
            <p className="text-sm font-medium text-gray-800">{claim.policy.agent_name}</p>
          </div>
        </div>
      </div>

      {/* Status Timeline */}
      <div className="bg-white border border-gray-200 rounded-lg p-5">
        <h3 className="text-sm font-semibold text-gray-700 mb-4">Status Timeline</h3>
        <div className="relative">
          {claim.status_history.map((entry, i) => (
            <div key={i} className="flex items-start gap-4 mb-4 last:mb-0">
              <div className="flex flex-col items-center">
                <div className={`w-3 h-3 rounded-full flex-shrink-0 ${
                  i === 0 ? 'bg-claims-primary ring-4 ring-claims-primary/20' : 'bg-gray-300'
                }`} />
                {i < claim.status_history.length - 1 && (
                  <div className="w-0.5 h-8 bg-gray-200 mt-1" />
                )}
              </div>
              <div className="flex-1 -mt-0.5">
                <div className="flex items-center gap-2">
                  <span className="text-sm font-medium text-gray-800">{entry.status}</span>
                  <span className="text-xs text-gray-400">{entry.date}</span>
                </div>
                <p className="text-xs text-gray-500">{entry.user}{entry.notes ? ` -- ${entry.notes}` : ''}</p>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}
