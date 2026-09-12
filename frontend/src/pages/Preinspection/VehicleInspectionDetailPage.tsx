import { useState, useEffect } from 'react'
import { useParams, Link, useNavigate } from 'react-router-dom'
import { useVehicleInspection, useApproveVehicleInspection } from '../../hooks/usePreinspection'
import type { VehicleApprovePayload } from '../../api/preinspection'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import ImageReviewCard from './ImageReviewCard'

type CardKey = 'front' | 'back' | 'left' | 'right' | 'registration' | 'invoice'

interface CardConfig {
  key: CardKey
  title: string
  statusField: keyof VehicleApprovePayload
  remarkField: keyof VehicleApprovePayload
}

const CARDS: CardConfig[] = [
  { key: 'front',        title: 'Front',             statusField: 'front_status',                remarkField: 'front_image_remark' },
  { key: 'back',         title: 'Back',              statusField: 'back_status',                 remarkField: 'back_image_remark' },
  { key: 'left',         title: 'Left',              statusField: 'left_status',                 remarkField: 'left_image_remark' },
  { key: 'right',        title: 'Right',             statusField: 'right_status',                remarkField: 'right_image_remark' },
  { key: 'registration', title: 'Registration Book', statusField: 'vehicle_registration_status', remarkField: 'registration_image_remark' },
  { key: 'invoice',      title: 'Vehicle Invoice',   statusField: 'vehicle_invoice_status',      remarkField: 'vehicle_invoice_remark' },
]

type Decision = { status: number | null; remark: string }
type DecisionMap = Record<CardKey, Decision>

const EMPTY: DecisionMap = {
  front: { status: null, remark: '' }, back: { status: null, remark: '' },
  left: { status: null, remark: '' }, right: { status: null, remark: '' },
  registration: { status: null, remark: '' }, invoice: { status: null, remark: '' },
}

const STATUS_BADGE: Record<string, string> = {
  Approved: 'bg-green-100 text-green-700',
  Unapproved: 'bg-red-100 text-red-700',
  Recheck: 'bg-orange-100 text-orange-700',
  Pending: 'bg-yellow-100 text-yellow-700',
}

export default function VehicleInspectionDetailPage() {
  const { id } = useParams<{ id: string }>()
  const inspectionId = Number(id)
  const navigate = useNavigate()

  const { data, isLoading, isError } = useVehicleInspection(inspectionId)
  const approve = useApproveVehicleInspection(inspectionId)

  const [decisions, setDecisions] = useState<DecisionMap>(EMPTY)
  const [remark, setRemark] = useState('')
  const [toast, setToast] = useState<{ type: 'success' | 'error'; msg: string } | null>(null)

  // Seed decisions from existing data once loaded
  useEffect(() => {
    if (!data) return
    const next = { ...EMPTY }
    for (const c of CARDS) {
      const img = data.images[c.key]
      next[c.key] = { status: img.status, remark: img.remark ?? '' }
    }
    setDecisions(next)
    setRemark(data.remark ?? '')
  }, [data])

  function setStatus(key: CardKey, status: number) {
    setDecisions(prev => ({ ...prev, [key]: { ...prev[key], status } }))
  }
  function setRemarkFor(key: CardKey, value: string) {
    setDecisions(prev => ({ ...prev, [key]: { ...prev[key], remark: value } }))
  }

  function handleSave() {
    const payload: VehicleApprovePayload = { remark }
    for (const c of CARDS) {
      const d = decisions[c.key]
      ;(payload[c.statusField] as number) = d.status === 1 ? 1 : 0
      ;(payload[c.remarkField] as string) = d.remark ?? ''
    }
    approve.mutate(payload, {
      onSuccess: (res) => {
        setToast({ type: 'success', msg: res.message })
        setTimeout(() => navigate('/preinspection/vehicle'), 1200)
      },
      onError: (err: any) => {
        setToast({ type: 'error', msg: err?.response?.data?.message || 'Failed to save' })
      },
    })
  }

  if (isLoading) {
    return <div className="p-12 flex justify-center"><LoadingSpinner size="lg" /></div>
  }
  if (isError || !data) {
    return (
      <div className="p-6">
        <p className="text-red-600">Failed to load vehicle inspection.</p>
        <Link to="/preinspection/vehicle" className="text-blue-600 hover:underline text-sm">← Back to list</Link>
      </div>
    )
  }

  return (
    <div className="p-6 space-y-4 max-w-5xl">
      <div className="flex items-center justify-between">
        <div>
          <Link to="/preinspection/vehicle" className="text-sm text-blue-600 hover:underline">← Back to vehicle inspections</Link>
          <h1 className="text-2xl font-bold text-gray-800 mt-1">Vehicle Inspection · {data.vehiclePlate || '—'}</h1>
        </div>
        <span className={`px-2.5 py-1 rounded-full text-xs font-medium ${STATUS_BADGE[data.statusLabel] ?? 'bg-gray-100 text-gray-600'}`}>
          {data.statusLabel}
        </span>
      </div>

      {/* Summary */}
      <div className="bg-white rounded-lg border shadow-sm p-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
        <div><div className="text-xs text-gray-500">Policy #</div><div className="font-medium text-gray-800">{data.policyNumber || '—'}</div></div>
        <div><div className="text-xs text-gray-500">Customer</div><div className="font-medium text-gray-800">{data.customerName || '—'}</div></div>
        <div><div className="text-xs text-gray-500">Vehicle</div><div className="font-medium text-gray-800">{[data.make, data.model, data.year].filter(Boolean).join(' ') || '—'}</div></div>
        <div><div className="text-xs text-gray-500">Inspected by</div><div className="font-medium text-gray-800">{data.performedBy || '—'}</div></div>
      </div>

      {data.reason && (
        <div className="bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm text-amber-800">
          <span className="font-medium">Last reason:</span> <span>{data.reason}</span>
        </div>
      )}

      {/* Image review grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        {CARDS.map(c => (
          <ImageReviewCard
            key={c.key}
            title={c.title}
            url={data.images[c.key].url}
            status={decisions[c.key].status}
            remark={decisions[c.key].remark}
            onStatusChange={s => setStatus(c.key, s)}
            onRemarkChange={r => setRemarkFor(c.key, r)}
            disabled={approve.isPending}
          />
        ))}
      </div>

      {/* Overall remark */}
      <div className="bg-white rounded-lg border shadow-sm p-4 space-y-2">
        <label className="block text-sm font-medium text-gray-700">Overall remark</label>
        <textarea
          value={remark}
          onChange={e => setRemark(e.target.value)}
          rows={2}
          disabled={approve.isPending}
          className="w-full px-3 py-2 border border-gray-300 rounded text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
        />
        <p className="text-xs text-gray-500">
          When every image is approved the policy is activated (if a successful payment exists) and the customer is notified.
          Any rejection notifies the customer and agent of the reasons.
        </p>
      </div>

      {toast && (
        <div className={`rounded-lg px-4 py-2 text-sm ${toast.type === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'}`}>
          {toast.msg}
        </div>
      )}

      <div className="flex gap-3">
        <button
          onClick={handleSave}
          disabled={approve.isPending}
          className="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2"
        >
          {approve.isPending && <LoadingSpinner size="sm" />}
          Save decision
        </button>
        <Link to="/preinspection/vehicle" className="px-4 py-2 border rounded-md text-sm text-gray-600 hover:bg-gray-50">Cancel</Link>
      </div>
    </div>
  )
}
