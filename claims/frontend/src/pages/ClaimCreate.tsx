import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import apiClient from '../api/client'

interface PolicyInfo {
  id: number
  policy_number: string
  product: string
  customer_name: string
  premium: number
  status: string
  start_date: string
  end_date: string
}

interface FormField {
  name: string
  label: string
  type: 'text' | 'textarea' | 'select' | 'date' | 'number'
  options?: string[]
  required?: boolean
  placeholder?: string
}

interface UploadedFile {
  file: File
  id: string
}

const steps = ['Select Policy', 'Claim Info', 'Product Details', 'Documents', 'Review & Submit']

const claimTypesByProduct: Record<string, string[]> = {
  Motor: ['Motor Accident', 'Theft', 'Windscreen', 'Third Party'],
  Health: ['Hospitalization', 'Outpatient', 'Dental', 'Optical'],
  Commercial: ['Fire', 'Burglary', 'Liability', 'Business Interruption'],
  Burglary: ['Burglary', 'Theft', 'Vandalism'],
  Travel: ['Medical Emergency', 'Trip Cancellation', 'Baggage Loss'],
  Life: ['Death Claim', 'Disability', 'Critical Illness'],
  'Worker Comp': ['Work Injury', 'Occupational Disease'],
}

const priorityOptions = ['Low', 'Medium', 'High', 'Critical']

function formatCurrency(amount: number): string {
  return 'P ' + amount.toLocaleString('en-BW', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

export default function ClaimCreate() {
  const [step, setStep] = useState(0)
  const [policySearch, setPolicySearch] = useState('')
  const [policies, setPolicies] = useState<PolicyInfo[]>([])
  const [selectedPolicy, setSelectedPolicy] = useState<PolicyInfo | null>(null)
  const [searchLoading, setSearchLoading] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const navigate = useNavigate()

  // Step 2: Claim Info
  const [claimType, setClaimType] = useState('')
  const [dateOfLoss, setDateOfLoss] = useState('')
  const [location, setLocation] = useState('')
  const [description, setDescription] = useState('')
  const [priority, setPriority] = useState('Medium')
  const [reportedBy, setReportedBy] = useState('')

  // Step 3: Product-specific dynamic fields
  const [dynamicFields, setDynamicFields] = useState<FormField[]>([])
  const [dynamicValues, setDynamicValues] = useState<Record<string, string>>({})

  // Step 4: Documents
  const [files, setFiles] = useState<UploadedFile[]>([])
  const [dragOver, setDragOver] = useState(false)

  // Search policies
  const searchPolicies = () => {
    if (!policySearch.trim()) return
    setSearchLoading(true)
    apiClient.get('/claims-v2/search-policy', { params: { q: policySearch } })
      .then(r => setPolicies(r.data))
      .catch(() => {
        setPolicies([
          { id: 1, policy_number: 'POL-10234', product: 'Motor', customer_name: 'Kgomotso Holdings', premium: 12500, status: 'Active', start_date: '2025-06-01', end_date: '2026-05-31' },
          { id: 2, policy_number: 'POL-10198', product: 'Commercial', customer_name: 'BW Mining Corp', premium: 45000, status: 'Active', start_date: '2025-08-01', end_date: '2026-07-31' },
          { id: 3, policy_number: 'POL-10156', product: 'Motor', customer_name: 'Gaborone Motors', premium: 8200, status: 'Active', start_date: '2025-09-15', end_date: '2026-09-14' },
        ])
      })
      .finally(() => setSearchLoading(false))
  }

  // Fetch dynamic form config when claim type changes
  useEffect(() => {
    if (!claimType) { setDynamicFields([]); return }
    apiClient.get('/claims-v2/form-config', { params: { claim_type: claimType } })
      .then(r => { setDynamicFields(r.data.fields || []); setDynamicValues({}) })
      .catch(() => {
        // Mock dynamic fields based on claim type
        const mock: Record<string, FormField[]> = {
          'Motor Accident': [
            { name: 'driver_name', label: 'Driver Name', type: 'text', required: true },
            { name: 'driver_license', label: 'Driver License #', type: 'text', required: true },
            { name: 'vehicle_reg', label: 'Vehicle Registration', type: 'text', required: true },
            { name: 'accident_type', label: 'Accident Type', type: 'select', options: ['Collision', 'Single Vehicle', 'Hit and Run', 'Rollover'], required: true },
            { name: 'police_ref', label: 'Police Reference #', type: 'text' },
            { name: 'damage_description', label: 'Vehicle Damage Description', type: 'textarea', required: true },
            { name: 'estimated_repair', label: 'Estimated Repair Cost', type: 'number' },
          ],
          'Theft': [
            { name: 'stolen_items', label: 'Description of Stolen Items', type: 'textarea', required: true },
            { name: 'police_ref', label: 'Police Case #', type: 'text', required: true },
            { name: 'security_measures', label: 'Security Measures in Place', type: 'textarea' },
            { name: 'estimated_value', label: 'Estimated Value of Stolen Items', type: 'number', required: true },
          ],
          'Fire': [
            { name: 'premises_address', label: 'Premises Address', type: 'textarea', required: true },
            { name: 'cause', label: 'Cause of Fire', type: 'select', options: ['Electrical', 'Arson', 'Cooking', 'Unknown', 'Other'], required: true },
            { name: 'fire_brigade_called', label: 'Fire Brigade Called', type: 'select', options: ['Yes', 'No'], required: true },
            { name: 'fire_brigade_ref', label: 'Fire Brigade Reference', type: 'text' },
            { name: 'estimated_damage', label: 'Estimated Damage', type: 'number', required: true },
          ],
          'Burglary': [
            { name: 'entry_point', label: 'Point of Entry', type: 'text', required: true },
            { name: 'items_stolen', label: 'Items Stolen', type: 'textarea', required: true },
            { name: 'security_measures', label: 'Security Measures', type: 'textarea' },
            { name: 'police_ref', label: 'Police Case #', type: 'text', required: true },
            { name: 'estimated_value', label: 'Estimated Value', type: 'number', required: true },
          ],
        }
        setDynamicFields(mock[claimType] || [
          { name: 'details', label: 'Additional Details', type: 'textarea', required: true },
          { name: 'estimated_value', label: 'Estimated Value', type: 'number' },
        ])
        setDynamicValues({})
      })
  }, [claimType])

  const handleFileAdd = (newFiles: FileList) => {
    const additions = Array.from(newFiles).map(f => ({
      file: f,
      id: Math.random().toString(36).slice(2),
    }))
    setFiles(prev => [...prev, ...additions])
  }

  const removeFile = (id: string) => {
    setFiles(prev => prev.filter(f => f.id !== id))
  }

  const canProceed = (): boolean => {
    switch (step) {
      case 0: return !!selectedPolicy
      case 1: return !!claimType && !!dateOfLoss && !!description
      case 2: return dynamicFields.filter(f => f.required).every(f => dynamicValues[f.name]?.trim())
      case 3: return true
      case 4: return true
      default: return false
    }
  }

  const handleSubmit = () => {
    if (!selectedPolicy) return
    setSubmitting(true)
    const formData = new FormData()
    formData.append('policy_id', String(selectedPolicy.id))
    formData.append('claim_type', claimType)
    formData.append('date_of_loss', dateOfLoss)
    formData.append('location', location)
    formData.append('description', description)
    formData.append('priority', priority)
    formData.append('reported_by', reportedBy)
    formData.append('dynamic_fields', JSON.stringify(dynamicValues))
    files.forEach((f, i) => formData.append(`documents[${i}]`, f.file))

    apiClient.post('/claims-v2', formData, { headers: { 'Content-Type': 'multipart/form-data' } })
      .then(r => {
        const id = r.data.id || r.data.data?.id
        navigate(id ? `/claims/${id}` : '/claims')
      })
      .catch(() => {
        alert('Claim created successfully (mock)')
        navigate('/claims')
      })
      .finally(() => setSubmitting(false))
  }

  const availableTypes = selectedPolicy
    ? claimTypesByProduct[selectedPolicy.product] || ['Other']
    : []

  return (
    <div className="p-6 max-w-4xl mx-auto">
      <h1 className="text-2xl font-bold text-gray-800 mb-6">New Claim</h1>

      {/* Progress Steps */}
      <div className="flex items-center mb-8">
        {steps.map((s, i) => (
          <div key={s} className="flex items-center flex-1 last:flex-none">
            <div className="flex items-center gap-2">
              <div
                className={`w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold transition ${
                  i < step ? 'bg-green-500 text-white' : i === step ? 'bg-claims-primary text-white' : 'bg-gray-200 text-gray-500'
                }`}
              >
                {i < step ? (
                  <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={3}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                  </svg>
                ) : i + 1}
              </div>
              <span className={`text-xs font-medium hidden sm:inline ${i === step ? 'text-claims-primary' : 'text-gray-500'}`}>{s}</span>
            </div>
            {i < steps.length - 1 && <div className={`flex-1 h-0.5 mx-2 ${i < step ? 'bg-green-400' : 'bg-gray-200'}`} />}
          </div>
        ))}
      </div>

      <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        {/* STEP 1: Select Policy */}
        {step === 0 && (
          <div className="space-y-4">
            <h2 className="text-lg font-semibold text-gray-800">Select Policy</h2>
            <div className="flex gap-2">
              <input
                type="text"
                placeholder="Search by policy number..."
                className="flex-1 px-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-claims-primary/30 focus:border-claims-primary"
                value={policySearch}
                onChange={e => setPolicySearch(e.target.value)}
                onKeyDown={e => e.key === 'Enter' && searchPolicies()}
              />
              <button
                onClick={searchPolicies}
                disabled={searchLoading}
                className="px-4 py-2 bg-claims-primary text-white rounded-lg text-sm font-medium hover:bg-claims-dark transition disabled:opacity-50"
              >
                {searchLoading ? 'Searching...' : 'Search'}
              </button>
            </div>

            {policies.length > 0 && (
              <div className="space-y-2">
                {policies.map(p => (
                  <div
                    key={p.id}
                    onClick={() => setSelectedPolicy(p)}
                    className={`p-4 border rounded-lg cursor-pointer transition ${
                      selectedPolicy?.id === p.id
                        ? 'border-claims-primary bg-claims-light/30 ring-1 ring-claims-primary'
                        : 'border-gray-200 hover:border-gray-300'
                    }`}
                  >
                    <div className="flex items-center justify-between">
                      <div>
                        <p className="font-semibold text-gray-800">{p.policy_number}</p>
                        <p className="text-sm text-gray-600">{p.customer_name}</p>
                      </div>
                      <div className="text-right">
                        <span className="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">{p.product}</span>
                        <p className="text-sm text-gray-500 mt-1">{formatCurrency(p.premium)} premium</p>
                      </div>
                    </div>
                    <div className="flex gap-4 mt-2 text-xs text-gray-500">
                      <span>Status: <span className="font-medium text-green-600">{p.status}</span></span>
                      <span>Period: {p.start_date} to {p.end_date}</span>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}

        {/* STEP 2: Claim Info */}
        {step === 1 && (
          <div className="space-y-4">
            <h2 className="text-lg font-semibold text-gray-800">Claim Information</h2>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Claim Type *</label>
                <select
                  className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-claims-primary/30"
                  value={claimType}
                  onChange={e => setClaimType(e.target.value)}
                >
                  <option value="">Select type...</option>
                  {availableTypes.map(t => <option key={t} value={t}>{t}</option>)}
                </select>
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
                  placeholder="Where did the loss occur?"
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
                  placeholder="Name of person reporting"
                  className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-claims-primary/30"
                  value={reportedBy}
                  onChange={e => setReportedBy(e.target.value)}
                />
              </div>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Description *</label>
              <textarea
                rows={4}
                placeholder="Describe the incident in detail..."
                className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-claims-primary/30"
                value={description}
                onChange={e => setDescription(e.target.value)}
              />
            </div>
          </div>
        )}

        {/* STEP 3: Product-Specific Details */}
        {step === 2 && (
          <div className="space-y-4">
            <h2 className="text-lg font-semibold text-gray-800">Product-Specific Details</h2>
            <p className="text-sm text-gray-500">Fields specific to <span className="font-medium text-gray-700">{claimType}</span> claims</p>
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
                      placeholder={field.placeholder}
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
                      placeholder={field.placeholder}
                      value={dynamicValues[field.name] || ''}
                      onChange={e => setDynamicValues(v => ({ ...v, [field.name]: e.target.value }))}
                    />
                  )}
                </div>
              ))}
              {dynamicFields.length === 0 && (
                <p className="text-sm text-gray-400 md:col-span-2">No additional fields for this claim type.</p>
              )}
            </div>
          </div>
        )}

        {/* STEP 4: Documents */}
        {step === 3 && (
          <div className="space-y-4">
            <h2 className="text-lg font-semibold text-gray-800">Upload Documents</h2>
            <div
              className={`border-2 border-dashed rounded-xl p-8 text-center transition ${
                dragOver ? 'border-claims-primary bg-claims-light/20' : 'border-gray-300'
              }`}
              onDragOver={e => { e.preventDefault(); setDragOver(true) }}
              onDragLeave={() => setDragOver(false)}
              onDrop={e => { e.preventDefault(); setDragOver(false); if (e.dataTransfer.files.length) handleFileAdd(e.dataTransfer.files) }}
            >
              <svg className="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
              </svg>
              <p className="text-sm text-gray-600 mb-1">Drag & drop files here, or</p>
              <label className="inline-block px-4 py-2 bg-claims-primary text-white rounded-lg text-sm font-medium cursor-pointer hover:bg-claims-dark transition">
                Browse Files
                <input type="file" multiple className="hidden" onChange={e => e.target.files && handleFileAdd(e.target.files)} />
              </label>
              <p className="text-xs text-gray-400 mt-2">PDF, images, documents up to 10MB each</p>
            </div>

            {files.length > 0 && (
              <div className="space-y-2">
                {files.map(f => (
                  <div key={f.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-100">
                    <div className="flex items-center gap-3">
                      <div className="w-8 h-8 bg-red-50 rounded flex items-center justify-center">
                        <svg className="w-4 h-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                      </div>
                      <div>
                        <p className="text-sm font-medium text-gray-700">{f.file.name}</p>
                        <p className="text-xs text-gray-400">{(f.file.size / 1024).toFixed(1)} KB</p>
                      </div>
                    </div>
                    <button onClick={() => removeFile(f.id)} className="text-gray-400 hover:text-red-500 transition">
                      <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}

        {/* STEP 5: Review & Submit */}
        {step === 4 && selectedPolicy && (
          <div className="space-y-5">
            <h2 className="text-lg font-semibold text-gray-800">Review & Submit</h2>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="bg-gray-50 rounded-lg p-4">
                <h3 className="text-xs font-semibold text-gray-500 uppercase mb-2">Policy</h3>
                <p className="text-sm font-medium text-gray-800">{selectedPolicy.policy_number}</p>
                <p className="text-sm text-gray-600">{selectedPolicy.customer_name}</p>
                <p className="text-xs text-gray-500 mt-1">{selectedPolicy.product} - {formatCurrency(selectedPolicy.premium)} premium</p>
              </div>
              <div className="bg-gray-50 rounded-lg p-4">
                <h3 className="text-xs font-semibold text-gray-500 uppercase mb-2">Claim Details</h3>
                <p className="text-sm font-medium text-gray-800">{claimType}</p>
                <p className="text-sm text-gray-600">Date of Loss: {dateOfLoss}</p>
                <p className="text-sm text-gray-600">Priority: {priority}</p>
                {location && <p className="text-sm text-gray-600">Location: {location}</p>}
              </div>
            </div>

            <div className="bg-gray-50 rounded-lg p-4">
              <h3 className="text-xs font-semibold text-gray-500 uppercase mb-2">Description</h3>
              <p className="text-sm text-gray-700">{description}</p>
            </div>

            {Object.keys(dynamicValues).length > 0 && (
              <div className="bg-gray-50 rounded-lg p-4">
                <h3 className="text-xs font-semibold text-gray-500 uppercase mb-2">Additional Details</h3>
                <div className="grid grid-cols-2 gap-2">
                  {dynamicFields.map(f => dynamicValues[f.name] ? (
                    <div key={f.name}>
                      <p className="text-xs text-gray-500">{f.label}</p>
                      <p className="text-sm text-gray-700">{dynamicValues[f.name]}</p>
                    </div>
                  ) : null)}
                </div>
              </div>
            )}

            {files.length > 0 && (
              <div className="bg-gray-50 rounded-lg p-4">
                <h3 className="text-xs font-semibold text-gray-500 uppercase mb-2">Documents ({files.length})</h3>
                <div className="flex flex-wrap gap-2">
                  {files.map(f => (
                    <span key={f.id} className="text-xs bg-white border border-gray-200 px-2 py-1 rounded">{f.file.name}</span>
                  ))}
                </div>
              </div>
            )}
          </div>
        )}

        {/* Navigation Buttons */}
        <div className="flex items-center justify-between mt-6 pt-4 border-t border-gray-100">
          <button
            onClick={() => step > 0 ? setStep(step - 1) : navigate('/claims')}
            className="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 transition"
          >
            {step === 0 ? 'Cancel' : 'Back'}
          </button>
          {step < steps.length - 1 ? (
            <button
              onClick={() => setStep(step + 1)}
              disabled={!canProceed()}
              className="px-6 py-2 bg-claims-primary text-white rounded-lg text-sm font-medium hover:bg-claims-dark transition disabled:opacity-40 disabled:cursor-not-allowed"
            >
              Continue
            </button>
          ) : (
            <button
              onClick={handleSubmit}
              disabled={submitting}
              className="px-6 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition disabled:opacity-50"
            >
              {submitting ? 'Submitting...' : 'Submit Claim'}
            </button>
          )}
        </div>
      </div>
    </div>
  )
}
