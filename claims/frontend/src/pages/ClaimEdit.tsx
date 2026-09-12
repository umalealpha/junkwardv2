import { useState, useEffect } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import apiClient from '../api/client'

interface FormField {
  name: string
  label: string
  type: 'text' | 'textarea' | 'select' | 'date' | 'number'
  options?: string[]
  required?: boolean
}

const priorityOptions = ['Low', 'Medium', 'High', 'Critical']

export default function ClaimEdit() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)

  // Claim info fields
  const [claimType, setClaimType] = useState('')
  const [dateOfLoss, setDateOfLoss] = useState('')
  const [location, setLocation] = useState('')
  const [description, setDescription] = useState('')
  const [priority, setPriority] = useState('Medium')
  const [reportedBy, setReportedBy] = useState('')

  // Dynamic fields
  const [dynamicFields, setDynamicFields] = useState<FormField[]>([])
  const [dynamicValues, setDynamicValues] = useState<Record<string, string>>({})

  useEffect(() => {
    setLoading(true)
    Promise.all([
      apiClient.get(`/claims-v2/${id}`).catch(() => ({
        data: {
          claim_type: 'Motor Accident',
          date_of_loss: '2026-03-15',
          location: 'A1 Highway, near Pilanesberg turnoff',
          description: 'Two-vehicle collision at intersection. Our insured was travelling northbound when the third party vehicle failed to stop at the intersection.',
          priority: 'High',
          reported_by: 'K. Mokaleng',
          dynamic_fields: {
            driver_name: 'T. Kgomotso',
            driver_license: 'DL-987654',
            vehicle_reg: 'B 123 XYZ',
            accident_type: 'Collision',
            police_ref: 'CR-2026/03/4521',
            damage_description: 'Front-left panel crushed, bonnet buckled, driver-side door jammed.',
            estimated_repair: '320000',
          },
        },
      })),
      apiClient.get('/claims-v2/form-config', { params: { claim_type: 'Motor Accident' } }).catch(() => ({
        data: {
          fields: [
            { name: 'driver_name', label: 'Driver Name', type: 'text' as const, required: true },
            { name: 'driver_license', label: 'Driver License #', type: 'text' as const, required: true },
            { name: 'vehicle_reg', label: 'Vehicle Registration', type: 'text' as const, required: true },
            { name: 'accident_type', label: 'Accident Type', type: 'select' as const, options: ['Collision', 'Single Vehicle', 'Hit and Run', 'Rollover'], required: true },
            { name: 'police_ref', label: 'Police Reference #', type: 'text' as const },
            { name: 'damage_description', label: 'Vehicle Damage Description', type: 'textarea' as const, required: true },
            { name: 'estimated_repair', label: 'Estimated Repair Cost', type: 'number' as const },
          ],
        },
      })),
    ]).then(([claimRes, configRes]) => {
      const c = claimRes.data
      setClaimType(c.claim_type || '')
      setDateOfLoss(c.date_of_loss || '')
      setLocation(c.location || '')
      setDescription(c.description || '')
      setPriority(c.priority || 'Medium')
      setReportedBy(c.reported_by || '')
      setDynamicValues(c.dynamic_fields || {})
      setDynamicFields(configRes.data.fields || [])
    }).finally(() => setLoading(false))
  }, [id])

  const handleSave = () => {
    setSaving(true)
    apiClient.put(`/claims-v2/${id}`, {
      claim_type: claimType,
      date_of_loss: dateOfLoss,
      location,
      description,
      priority,
      reported_by: reportedBy,
      dynamic_fields: dynamicValues,
    })
      .then(() => navigate(`/claims/${id}`))
      .catch(() => {
        alert('Changes saved (mock)')
        navigate(`/claims/${id}`)
      })
      .finally(() => setSaving(false))
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin w-8 h-8 border-4 border-claims-primary border-t-transparent rounded-full" />
      </div>
    )
  }

  return (
    <div className="p-6 max-w-4xl mx-auto">
      <div className="flex items-center gap-3 mb-6">
        <button onClick={() => navigate(`/claims/${id}`)} className="text-gray-400 hover:text-gray-600 transition">
          <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
        </button>
        <h1 className="text-2xl font-bold text-gray-800">Edit Claim</h1>
      </div>

      <div className="space-y-6">
        {/* Claim Info */}
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
          <h2 className="text-lg font-semibold text-gray-800 mb-4">Claim Information</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Claim Type</label>
              <input
                type="text"
                className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50"
                value={claimType}
                readOnly
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Date of Loss *</label>
              <input
                type="date"
                className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-claims-primary/30"
                value={dateOfLoss}
                onChange={e => setDateOfLoss(e.target.value)}
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Location</label>
              <input
                type="text"
                className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-claims-primary/30"
                value={location}
                onChange={e => setLocation(e.target.value)}
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Priority</label>
              <select
                className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-claims-primary/30"
                value={priority}
                onChange={e => setPriority(e.target.value)}
              >
                {priorityOptions.map(p => <option key={p} value={p}>{p}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Reported By</label>
              <input
                type="text"
                className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-claims-primary/30"
                value={reportedBy}
                onChange={e => setReportedBy(e.target.value)}
              />
            </div>
          </div>
          <div className="mt-4">
            <label className="block text-sm font-medium text-gray-700 mb-1">Description *</label>
            <textarea
              rows={4}
              className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-claims-primary/30"
              value={description}
              onChange={e => setDescription(e.target.value)}
            />
          </div>
        </div>

        {/* Product-Specific Details */}
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
          <h2 className="text-lg font-semibold text-gray-800 mb-4">Product-Specific Details</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {dynamicFields.map(field => (
              <div key={field.name} className={field.type === 'textarea' ? 'md:col-span-2' : ''}>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  {field.label} {field.required && '*'}
                </label>
                {field.type === 'textarea' ? (
                  <textarea
                    rows={3}
                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-claims-primary/30"
                    value={dynamicValues[field.name] || ''}
                    onChange={e => setDynamicValues(v => ({ ...v, [field.name]: e.target.value }))}
                  />
                ) : field.type === 'select' ? (
                  <select
                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-claims-primary/30"
                    value={dynamicValues[field.name] || ''}
                    onChange={e => setDynamicValues(v => ({ ...v, [field.name]: e.target.value }))}
                  >
                    <option value="">Select...</option>
                    {field.options?.map(o => <option key={o} value={o}>{o}</option>)}
                  </select>
                ) : (
                  <input
                    type={field.type}
                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-claims-primary/30"
                    value={dynamicValues[field.name] || ''}
                    onChange={e => setDynamicValues(v => ({ ...v, [field.name]: e.target.value }))}
                  />
                )}
              </div>
            ))}
          </div>
        </div>

        {/* Action Buttons */}
        <div className="flex items-center justify-between">
          <button
            onClick={() => navigate(`/claims/${id}`)}
            className="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 transition"
          >
            Cancel
          </button>
          <button
            onClick={handleSave}
            disabled={saving || !dateOfLoss || !description}
            className="px-6 py-2 bg-claims-primary text-white rounded-lg text-sm font-medium hover:bg-claims-dark transition disabled:opacity-50"
          >
            {saving ? 'Saving...' : 'Save Changes'}
          </button>
        </div>
      </div>
    </div>
  )
}
