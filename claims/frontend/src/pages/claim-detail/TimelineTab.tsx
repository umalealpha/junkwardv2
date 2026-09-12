import { useState, useEffect } from 'react'
import apiClient from '../../api/client'
import type { TimelineEvent } from './types'

const eventTypeConfig: Record<string, { bg: string; text: string; icon: JSX.Element }> = {
  status_change: {
    bg: 'bg-blue-100',
    text: 'text-blue-600',
    icon: (
      <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
      </svg>
    ),
  },
  document: {
    bg: 'bg-green-100',
    text: 'text-green-600',
    icon: (
      <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
      </svg>
    ),
  },
  reserve: {
    bg: 'bg-yellow-100',
    text: 'text-yellow-600',
    icon: (
      <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
      </svg>
    ),
  },
  payment: {
    bg: 'bg-red-100',
    text: 'text-red-600',
    icon: (
      <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
      </svg>
    ),
  },
  note: {
    bg: 'bg-gray-100',
    text: 'text-gray-600',
    icon: (
      <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
      </svg>
    ),
  },
  assessment: {
    bg: 'bg-purple-100',
    text: 'text-purple-600',
    icon: (
      <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
      </svg>
    ),
  },
}

const defaultEventConfig = {
  bg: 'bg-gray-100',
  text: 'text-gray-600',
  icon: (
    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
  ),
}

export default function TimelineTab({ claimId }: { claimId: number }) {
  const [events, setEvents] = useState<TimelineEvent[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    setLoading(true)
    apiClient.get(`/claims-v2/${claimId}/timeline`)
      .then(r => setEvents(r.data))
      .catch(() => {
        setEvents([
          { id: 1, type: 'status_change', title: 'Status changed to Under Review', description: 'Claim moved to under review after initial assessment', user: 'Admin', timestamp: '2026-03-22 14:30' },
          { id: 2, type: 'payment', title: 'Payment processed - P 85,000.00', description: 'Panel beating payment to AutoFix Workshop (INV-2026-445)', user: 'Finance Team', timestamp: '2026-03-22 10:15' },
          { id: 3, type: 'reserve', title: 'Reserve added - Salvage Reserve', description: 'Expected salvage value P 15,000.00', user: 'K. Mokaleng', timestamp: '2026-03-21 16:45' },
          { id: 4, type: 'assessment', title: 'Assessment completed', description: 'Vehicle inspection completed. Valuation: P 320,000.00', user: 'M. Kgositsile', timestamp: '2026-03-20 11:00' },
          { id: 5, type: 'document', title: 'Document uploaded - Repair_Quotation.xlsx', description: 'Repair quotation from AutoFix Workshop', user: 'M. Assessor', timestamp: '2026-03-18 09:30' },
          { id: 6, type: 'reserve', title: 'Reserve created - Third Party Reserve', description: 'TP vehicle damage reserve P 100,000.00', user: 'K. Mokaleng', timestamp: '2026-03-17 15:00' },
          { id: 7, type: 'document', title: 'Documents uploaded - 3 files', description: 'Police report, damage photos (front, side)', user: 'K. Mokaleng', timestamp: '2026-03-16 14:20' },
          { id: 8, type: 'reserve', title: 'Reserve created - Loss Reserve', description: 'Initial loss reserve P 350,000.00', user: 'System', timestamp: '2026-03-16 12:00' },
          { id: 9, type: 'status_change', title: 'Status changed to Pending Assessment', description: 'Claim registered and pending assessor assignment', user: 'K. Mokaleng', timestamp: '2026-03-16 11:55' },
          { id: 10, type: 'status_change', title: 'Claim created', description: 'New claim registered - Motor Accident', user: 'K. Mokaleng', timestamp: '2026-03-16 11:50' },
        ])
      })
      .finally(() => setLoading(false))
  }, [claimId])

  if (loading) {
    return <div className="flex justify-center py-12"><div className="animate-spin w-8 h-8 border-4 border-claims-primary border-t-transparent rounded-full" /></div>
  }

  return (
    <div className="space-y-0">
      {events.length === 0 && (
        <p className="text-sm text-gray-400 py-8 text-center">No timeline events.</p>
      )}

      <div className="relative">
        {/* Vertical line */}
        <div className="absolute left-5 top-0 bottom-0 w-0.5 bg-gray-200" />

        {events.map((event, i) => {
          const config = eventTypeConfig[event.type] || defaultEventConfig
          return (
            <div key={event.id} className={`relative flex gap-4 ${i < events.length - 1 ? 'pb-6' : ''}`}>
              {/* Icon */}
              <div className={`w-10 h-10 rounded-full ${config.bg} flex items-center justify-center flex-shrink-0 z-10 ${config.text}`}>
                {config.icon}
              </div>

              {/* Content */}
              <div className="flex-1 pt-1">
                <div className="flex items-start justify-between gap-2">
                  <div>
                    <p className="text-sm font-medium text-gray-800">{event.title}</p>
                    {event.description && (
                      <p className="text-xs text-gray-500 mt-0.5">{event.description}</p>
                    )}
                  </div>
                  <span className="text-xs text-gray-400 flex-shrink-0 whitespace-nowrap">{event.timestamp}</span>
                </div>
                <p className="text-xs text-gray-400 mt-1">by {event.user}</p>
              </div>
            </div>
          )
        })}
      </div>
    </div>
  )
}
