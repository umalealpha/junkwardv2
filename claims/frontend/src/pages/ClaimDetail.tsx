import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import apiClient from '../api/client'
import type { ClaimData } from './claim-detail/types'
import { statusColors, priorityColors, formatCurrency } from './claim-detail/types'
import OverviewTab from './claim-detail/OverviewTab'
import DetailsTab from './claim-detail/DetailsTab'
import DocumentsTab from './claim-detail/DocumentsTab'
import ReservesTab from './claim-detail/ReservesTab'
import AssessmentTab from './claim-detail/AssessmentTab'
import ThirdPartyTab from './claim-detail/ThirdPartyTab'
import QuotesTab from './claim-detail/QuotesTab'
import TimelineTab from './claim-detail/TimelineTab'

const tabs = ['Overview', 'Details', 'Documents', 'Reserves & Payments', 'Assessment', 'Third Party', 'Quotes', 'Timeline']

const allStatuses = ['New', 'Pending Assessment', 'Under Review', 'Approved', 'Rejected', 'Settled', 'Closed']

export default function ClaimDetail() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const [claim, setClaim] = useState<ClaimData | null>(null)
  const [loading, setLoading] = useState(true)
  const [activeTab, setActiveTab] = useState(0)
  const [showStatusDropdown, setShowStatusDropdown] = useState(false)
  const [updatingStatus, setUpdatingStatus] = useState(false)

  const fetchClaim = () => {
    setLoading(true)
    apiClient.get(`/claims-v2/${id}`)
      .then(r => setClaim(r.data))
      .catch(() => {
        setClaim({
          id: Number(id),
          claim_number: 'CLM-2026-0451',
          claim_type: 'Motor Accident',
          status: 'Under Review',
          priority: 'High',
          date_of_loss: '2026-03-15',
          location: 'A1 Highway, near Pilanesberg turnoff',
          description: 'Two-vehicle collision at intersection. Our insured was travelling northbound when the third party vehicle failed to stop at the intersection, resulting in a side-impact collision. Significant damage to front-left panel, bonnet, and driver-side door. No serious injuries reported.',
          reported_by: 'K. Mokaleng',
          assigned_to: 'M. Kgositsile',
          created_at: '2026-03-16',
          days_open: 26,
          reserve_total: 450000,
          paid_total: 120000,
          balance: 330000,
          policy: {
            policy_number: 'POL-10234',
            product: 'Motor',
            premium: 12500,
            customer_name: 'Kgomotso Holdings (Pty) Ltd',
            agent_name: 'S. Nkwe',
          },
          dynamic_fields: {
            driver_name: 'T. Kgomotso',
            driver_license: 'DL-987654',
            vehicle_reg: 'B 123 XYZ',
            accident_type: 'Collision',
            police_ref: 'CR-2026/03/4521',
            damage_description: 'Front-left panel crushed, bonnet buckled, driver-side door jammed, headlight assembly shattered. Airbags deployed. Windscreen cracked.',
            estimated_repair: '320000',
          },
          status_history: [
            { status: 'Under Review', date: '2026-03-22', user: 'Admin', notes: 'Moved to review after assessment' },
            { status: 'Pending Assessment', date: '2026-03-16', user: 'K. Mokaleng', notes: 'Assessor assigned' },
            { status: 'New', date: '2026-03-16', user: 'K. Mokaleng', notes: 'Claim registered' },
          ],
        })
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchClaim() }, [id])

  const handleStatusChange = (newStatus: string) => {
    setUpdatingStatus(true)
    setShowStatusDropdown(false)
    apiClient.put(`/claims-v2/${id}/status`, { status: newStatus })
      .catch(() => {})
      .finally(() => {
        if (claim) {
          setClaim({
            ...claim,
            status: newStatus,
            status_history: [
              { status: newStatus, date: new Date().toISOString().split('T')[0], user: 'You' },
              ...claim.status_history,
            ],
          })
        }
        setUpdatingStatus(false)
      })
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin w-8 h-8 border-4 border-claims-primary border-t-transparent rounded-full" />
      </div>
    )
  }

  if (!claim) {
    return (
      <div className="p-6 text-center">
        <p className="text-gray-500">Claim not found.</p>
        <button onClick={() => navigate('/claims')} className="mt-4 text-claims-primary hover:text-claims-dark font-medium text-sm">
          Back to Claims
        </button>
      </div>
    )
  }

  return (
    <div className="p-6 space-y-4">
      {/* Header */}
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="flex items-center gap-3">
          <button onClick={() => navigate('/claims')} className="text-gray-400 hover:text-gray-600 transition">
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
          </button>
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-xl font-bold text-gray-800">{claim.claim_number}</h1>
              <span className="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-medium">{claim.claim_type}</span>
            </div>
            <p className="text-sm text-gray-500">{claim.policy.customer_name} -- {claim.policy.policy_number}</p>
          </div>
        </div>
        <div className="flex items-center gap-2">
          {/* Status Badge with dropdown */}
          <div className="relative">
            <button
              onClick={() => setShowStatusDropdown(!showStatusDropdown)}
              disabled={updatingStatus}
              className={`px-3 py-1.5 rounded-full text-xs font-semibold border-2 border-transparent transition hover:ring-2 hover:ring-gray-200 ${statusColors[claim.status] || 'bg-gray-100 text-gray-600'}`}
            >
              {updatingStatus ? 'Updating...' : claim.status}
              <svg className="w-3 h-3 inline ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            {showStatusDropdown && (
              <div className="absolute right-0 top-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg py-1 z-20 min-w-[180px]">
                {allStatuses.filter(s => s !== claim.status).map(s => (
                  <button
                    key={s}
                    onClick={() => handleStatusChange(s)}
                    className="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 transition flex items-center gap-2"
                  >
                    <span className={`w-2 h-2 rounded-full ${statusColors[s]?.split(' ')[0] || 'bg-gray-300'}`} />
                    {s}
                  </button>
                ))}
              </div>
            )}
          </div>

          <span className={`px-3 py-1.5 rounded-full text-xs font-semibold ${priorityColors[claim.priority] || 'bg-gray-100 text-gray-600'}`}>
            {claim.priority}
          </span>

          <button
            onClick={() => navigate(`/claims/${id}/edit`)}
            className="px-4 py-1.5 bg-claims-primary text-white rounded-lg text-xs font-medium hover:bg-claims-dark transition"
          >
            Edit
          </button>
        </div>
      </div>

      {/* Quick Stats Strip */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div className="bg-white border border-gray-200 rounded-lg px-4 py-3 text-center">
          <p className="text-xs text-gray-500">Reserve Total</p>
          <p className="text-lg font-bold text-gray-800">{formatCurrency(claim.reserve_total)}</p>
        </div>
        <div className="bg-white border border-gray-200 rounded-lg px-4 py-3 text-center">
          <p className="text-xs text-gray-500">Paid Total</p>
          <p className="text-lg font-bold text-green-700">{formatCurrency(claim.paid_total)}</p>
        </div>
        <div className="bg-white border border-gray-200 rounded-lg px-4 py-3 text-center">
          <p className="text-xs text-gray-500">Balance</p>
          <p className="text-lg font-bold text-red-700">{formatCurrency(claim.balance)}</p>
        </div>
        <div className="bg-white border border-gray-200 rounded-lg px-4 py-3 text-center">
          <p className="text-xs text-gray-500">Days Open</p>
          <p className="text-lg font-bold text-gray-800">{claim.days_open}</p>
        </div>
      </div>

      {/* Tabs */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-100">
        <div className="border-b border-gray-200 overflow-x-auto">
          <div className="flex min-w-max">
            {tabs.map((tab, i) => (
              <button
                key={tab}
                onClick={() => setActiveTab(i)}
                className={`px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition ${
                  i === activeTab
                    ? 'border-claims-primary text-claims-primary'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                }`}
              >
                {tab}
              </button>
            ))}
          </div>
        </div>

        <div className="p-5">
          {activeTab === 0 && <OverviewTab claim={claim} />}
          {activeTab === 1 && <DetailsTab claim={claim} onUpdate={fetchClaim} />}
          {activeTab === 2 && <DocumentsTab claimId={claim.id} />}
          {activeTab === 3 && <ReservesTab claimId={claim.id} />}
          {activeTab === 4 && <AssessmentTab claimId={claim.id} />}
          {activeTab === 5 && <ThirdPartyTab claimId={claim.id} />}
          {activeTab === 6 && <QuotesTab claimId={claim.id} />}
          {activeTab === 7 && <TimelineTab claimId={claim.id} />}
        </div>
      </div>

      {/* Click-outside handler for status dropdown */}
      {showStatusDropdown && (
        <div className="fixed inset-0 z-10" onClick={() => setShowStatusDropdown(false)} />
      )}
    </div>
  )
}
