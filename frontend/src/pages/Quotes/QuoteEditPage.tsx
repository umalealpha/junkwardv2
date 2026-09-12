import { useState, useEffect } from 'react'
import { useParams, Link, useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useQuoteDetail } from '../../hooks/useQuotes'
import apiClient from '../../api/client'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import { fmtPula } from '../../utils/format'

function fmtCurrency(v: number | null | undefined) {
  if (v == null) return '—'
  return fmtPula(v)
}

// ─── Vehicle lookup hooks ──────────────────────────────

function useVehicleMakes(isImported: string) {
  return useQuery({
    queryKey: ['vehicle-makes', isImported],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: string[] }>('/vehicle/makes', { params: { is_imported: isImported } })
      return data.data
    },
    staleTime: 10 * 60 * 1000,
  })
}

function useVehicleModels(make: string, isImported: string) {
  return useQuery({
    queryKey: ['vehicle-models', make, isImported],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: string[] }>('/vehicle/models', { params: { make, is_imported: isImported } })
      return data.data
    },
    enabled: !!make,
    staleTime: 10 * 60 * 1000,
  })
}

function useVehicleYears(make: string, model: string) {
  return useQuery({
    queryKey: ['vehicle-years', make, model],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: string[] }>('/vehicle/years', { params: { make, model } })
      return data.data
    },
    enabled: !!make && !!model,
    staleTime: 10 * 60 * 1000,
  })
}

function useVehicleVariants(make: string, model: string, year: string) {
  return useQuery({
    queryKey: ['vehicle-variants', make, model, year],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: string[] }>('/vehicle/variants', { params: { make, model, year } })
      return data.data
    },
    enabled: !!make && !!model && !!year,
    staleTime: 10 * 60 * 1000,
  })
}

// ─── Component ─────────────────────────────────────────

export default function QuoteEditPage() {
  const { id } = useParams<{ id: string }>()
  const quoteId = Number(id)
  const navigate = useNavigate()
  const { data: quote, isLoading, error } = useQuoteDetail(quoteId)

  // Customer fields
  const [firstName, setFirstName] = useState('')
  const [middleName, setMiddleName] = useState('')
  const [lastName, setLastName] = useState('')
  const [email, setEmail] = useState('')
  const [cellphone, setCellphone] = useState('')
  const [gender, setGender] = useState('')
  const [dob, setDob] = useState('')
  const [omang, setOmang] = useState('')
  const [passport, setPassport] = useState('')
  const [maritalStatus, setMaritalStatus] = useState('')
  const [address, setAddress] = useState('')

  // Vehicle fields
  const [isImported, setIsImported] = useState('No')
  const [make, setMake] = useState('')
  const [model, setModel] = useState('')
  const [year, setYear] = useState('')
  const [estimatedValue, setEstimatedValue] = useState('')
  const [variant, setVariant] = useState('')
  const [priorAccidents, setPriorAccidents] = useState('0')

  // Premium calc result
  const [calculatedPremium, setCalculatedPremium] = useState<{
    monthly: number | null; threeInstalment: number | null; annually: number | null; rate: number | null; rateId: number | null
  } | null>(null)
  const [calculating, setCalculating] = useState(false)
  const [calcError, setCalcError] = useState<string | null>(null)
  const [referredToUnderwriting, setReferredToUnderwriting] = useState(false)

  // Discount / Surcharge
  const [adjType, setAdjType] = useState<'Discount' | 'Surcharge'>('Discount')
  const [adjValueType, setAdjValueType] = useState<1 | 2>(1)
  const [adjValue, setAdjValue] = useState('')
  const [adjReason, setAdjReason] = useState('')

  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null)

  // Vehicle lookup queries (interdependent)
  const { data: makes, isLoading: makesLoading } = useVehicleMakes(isImported)
  const { data: models, isLoading: modelsLoading } = useVehicleModels(make, isImported)
  const { data: years, isLoading: yearsLoading } = useVehicleYears(make, model)
  const { data: variants } = useVehicleVariants(make, model, year)

  // Populate form
  useEffect(() => {
    if (!quote) return
    setFirstName(quote.customer.firstName ?? '')
    setMiddleName(quote.customer.middleName ?? '')
    setLastName(quote.customer.lastName ?? '')
    setEmail(quote.customer.email ?? '')
    setCellphone(quote.customer.cellphone ?? '')
    setGender(quote.customer.gender === 'Male' ? '1' : quote.customer.gender === 'Female' ? '0' : '')
    setDob(quote.customer.dob ?? '')
    setOmang(quote.customer.omang ?? '')
    setPassport(quote.customer.passport ?? '')
    setMaritalStatus(quote.customer.maritalStatus ?? '')
    setAddress(quote.customer.address ?? '')
    if (quote.vehicle) {
      setIsImported(quote.vehicle.isImported ? 'Yes' : 'No')
      setMake(quote.vehicle.make ?? '')
      setModel(quote.vehicle.model ?? '')
      setYear(quote.vehicle.year ?? '')
      setEstimatedValue(String(quote.vehicle.estimatedValue ?? ''))
      setVariant(quote.vehicle.variant ?? '')
      setPriorAccidents(String(quote.vehicle.priorAccidents ?? '0'))
    }
  }, [quote])

  if (isLoading) return <div className="p-6 flex justify-center"><LoadingSpinner size="lg" /></div>
  if (error || !quote) return (
    <div className="p-6">
      <div className="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
        <p className="text-red-700 font-medium">Failed to load quote.</p>
        <Link to="/quotes" className="text-blue-600 hover:underline text-sm mt-2 inline-block">Back to Quotes</Link>
      </div>
    </div>
  )

  // ─── Handlers ────────────────────────────────────────

  function handleMakeChange(v: string) {
    setMake(v)
    setModel('')
    setYear('')
    setVariant('')
    setCalculatedPremium(null)
  }

  function handleModelChange(v: string) {
    setModel(v)
    setYear('')
    setVariant('')
    setCalculatedPremium(null)
  }

  function handleYearChange(v: string) {
    setYear(v)
    setVariant('')
    setCalculatedPremium(null)
  }

  function handleImportedChange(v: string) {
    setIsImported(v)
    setMake('')
    setModel('')
    setYear('')
    setVariant('')
    setCalculatedPremium(null)
  }

  async function handleCalculatePremium() {
    if (!make || !year || !dob || !estimatedValue || !gender || !maritalStatus) {
      setCalcError('Please fill in make, year, DOB, estimated value, gender, and marital status before calculating.')
      return
    }
    setCalculating(true)
    setCalcError(null)
    setReferredToUnderwriting(false)
    try {
      const { data } = await apiClient.post<{ success: boolean; data?: any; message?: string; referred_to_underwriting?: boolean }>('/vehicle/calculate-premium', {
        make,
        manufacturing_year: year,
        dob,
        sum_insured: Number(estimatedValue),
        is_imported: isImported,
        marital_status: maritalStatus,
        claim_count: Number(priorAccidents),
        gender: gender === '1' ? 'Male' : 'Female',
      })
      if (data.success) {
        setCalculatedPremium(data.data)
      } else if (data.referred_to_underwriting) {
        setReferredToUnderwriting(true)
      } else {
        setCalcError(data.message || 'Rating engine returned an error.')
      }
    } catch (err: any) {
      const errData = err?.response?.data
      if (errData?.referred_to_underwriting) {
        setReferredToUnderwriting(true)
      } else {
        setCalcError(errData?.message || 'Failed to calculate premium.')
      }
    } finally {
      setCalculating(false)
    }
  }

  async function handleSave(e: React.FormEvent) {
    e.preventDefault()
    setSaving(true)
    setMessage(null)
    try {
      await apiClient.put(`/quotes/${quoteId}/update`, {
        first_name: firstName, middle_name: middleName, last_name: lastName,
        email, cellphone, gender, dob, omang, passport, marital_status: maritalStatus, address,
        make, model, manufacturing_year: year, estimated_value: estimatedValue,
        is_imported: isImported, variant,
      })

      // If new premium was calculated via rating engine, apply the difference
      if (calculatedPremium && calculatedPremium.annually) {
        const diff = calculatedPremium.annually - (quote?.premium.annually ?? 0)
        if (Math.abs(diff) > 0.01) {
          await apiClient.post(`/quotes/${quoteId}/update-premium`, {
            type: diff >= 0 ? 'Surcharge' : 'Discount',
            value_type: 1,
            value: Math.abs(diff),
            reason: 'Re-rated via quote update',
          })
        }
      }

      // If manual discount/surcharge is filled, apply it
      if (adjValue && adjReason) {
        await apiClient.post(`/quotes/${quoteId}/update-premium`, {
          type: adjType,
          value_type: adjValueType,
          value: Number(adjValue),
          reason: adjReason,
        })
      }

      setMessage({ type: 'success', text: 'Quote updated successfully.' })
      setTimeout(() => navigate(`/quotes/${quoteId}`), 1500)
    } catch (err: any) {
      setMessage({ type: 'error', text: err?.response?.data?.message || 'Failed to update quote.' })
    } finally {
      setSaving(false)
    }
  }

  const maritalOptions = [
    { value: '', label: '-- Select --' },
    { value: 'Single', label: 'Single' }, { value: 'Married', label: 'Married' },
    { value: 'Divorced', label: 'Divorced' }, { value: 'Widowed', label: 'Widowed' },
    { value: 'Living Together', label: 'Living Together' }, { value: 'Living Separately', label: 'Living Separately' },
  ]

  return (
    <div className="p-6 space-y-6">
      <div>
        <Link to={`/quotes/${quoteId}`} className="text-sm text-blue-600 hover:underline mb-1 inline-block">&larr; Back to Quote</Link>
        <h1 className="text-2xl font-bold text-gray-800">Update Quote: {quote.quoteCode}</h1>
        <p className="text-sm text-gray-500 mt-1">
          Product: {quote.product.name} | Current Annual Premium: {fmtCurrency(quote.premium.annually)}
        </p>
      </div>

      <form onSubmit={handleSave} className="space-y-6 max-w-5xl">
        {/* Customer Details */}
        <div className="bg-white rounded-lg shadow-sm border p-5">
          <h3 className="text-base font-semibold text-gray-800 mb-4 border-b pb-2">Customer Details</h3>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <Field label="First Name *" value={firstName} onChange={setFirstName} />
            <Field label="Middle Name" value={middleName} onChange={setMiddleName} />
            <Field label="Last Name *" value={lastName} onChange={setLastName} />
            <Field label="Email *" value={email} onChange={setEmail} type="email" />
            <Field label="Cellphone *" value={cellphone} onChange={setCellphone} />
            <SelectField label="Gender *" value={gender} onChange={setGender} options={[{ value: '', label: '-- Select --' }, { value: '1', label: 'Male' }, { value: '0', label: 'Female' }]} />
            <Field label="Date of Birth *" value={dob} onChange={setDob} type="date" />
            <Field label="Omang" value={omang} onChange={setOmang} />
            <Field label="Passport" value={passport} onChange={setPassport} />
            <SelectField label="Marital Status *" value={maritalStatus} onChange={setMaritalStatus} options={maritalOptions} />
            <div className="md:col-span-2">
              <Field label="Address" value={address} onChange={setAddress} />
            </div>
          </div>
        </div>

        {/* Vehicle Details with interdependent dropdowns */}
        {quote.vehicle && (
          <div className="bg-white rounded-lg shadow-sm border p-5">
            <h3 className="text-base font-semibold text-gray-800 mb-4 border-b pb-2">Vehicle Details</h3>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              {/* Imported toggle — resets all other fields */}
              <SelectField label="Japanese Import" value={isImported} onChange={handleImportedChange}
                options={[{ value: 'No', label: 'No' }, { value: 'Yes', label: 'Yes' }]} />

              {/* Make — combobox (dropdown + free text) */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Make *</label>
                <div className="relative">
                  <input list="quote-make-options" value={make} onChange={e => handleMakeChange(e.target.value)}
                    placeholder={makesLoading ? 'Loading makes...' : 'Type or select make'}
                    className="w-full px-3 py-2 border rounded-md text-sm focus:ring-1 focus:ring-blue-500" />
                  <datalist id="quote-make-options">
                    {(makes ?? []).map(m => <option key={m} value={m} />)}
                  </datalist>
                  {makesLoading && <Spinner />}
                </div>
              </div>

              {/* Model — combobox */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Model *</label>
                <div className="relative">
                  <input list="quote-model-options" value={model} onChange={e => handleModelChange(e.target.value)}
                    placeholder={modelsLoading ? 'Loading models...' : (!make ? 'Select make first' : 'Type or select model')}
                    className="w-full px-3 py-2 border rounded-md text-sm focus:ring-1 focus:ring-blue-500" />
                  <datalist id="quote-model-options">
                    {(models ?? []).map(m => <option key={m} value={m} />)}
                  </datalist>
                  {modelsLoading && <Spinner />}
                </div>
              </div>

              {/* Year — combobox */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Year *</label>
                <div className="relative">
                  <input list="quote-year-options" value={year} onChange={e => handleYearChange(e.target.value)}
                    placeholder={yearsLoading ? 'Loading years...' : 'Type or select year'}
                    className="w-full px-3 py-2 border rounded-md text-sm focus:ring-1 focus:ring-blue-500" />
                  <datalist id="quote-year-options">
                    {(years ?? []).map(y => <option key={y} value={String(y)} />)}
                  </datalist>
                  {yearsLoading && <Spinner />}
                </div>
              </div>

              {/* Variant — combobox */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Variant</label>
                <input list="quote-variant-options" value={variant} onChange={e => setVariant(e.target.value)}
                  placeholder={!year ? 'Select year first' : 'Type or select variant'}
                  className="w-full px-3 py-2 border rounded-md text-sm focus:ring-1 focus:ring-blue-500" />
                <datalist id="quote-variant-options">
                  {(variants ?? []).map(v => <option key={v} value={v} />)}
                </datalist>
              </div>

              <Field label="Estimated Value (BWP) *" value={estimatedValue} onChange={v => { setEstimatedValue(v); setCalculatedPremium(null); setReferredToUnderwriting(false) }} type="number" />
              <SelectField label="Prior Accidents" value={priorAccidents} onChange={setPriorAccidents}
                options={[{ value: '0', label: '0' }, { value: '1', label: '1' }, { value: '2', label: '2' }, { value: '3', label: '3' }]} />
            </div>

            {/* Calculate Premium button */}
            <div className="mt-5 pt-4 border-t">
              <div className="flex items-center gap-4">
                <button type="button" onClick={handleCalculatePremium} disabled={calculating}
                  className="px-5 py-2.5 bg-green-600 text-white rounded-md text-sm font-medium hover:bg-green-700 disabled:opacity-50 flex items-center gap-2">
                  {calculating ? (
                    <><div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" /> Calculating...</>
                  ) : (
                    <><svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg> Calculate Premium</>
                  )}
                </button>
                {calcError && <p className="text-sm text-red-600">{calcError}</p>}
              </div>

              {/* Underwriting referral banner — shown when sum insured exceeds P500k */}
              {referredToUnderwriting && (
                <div className="mt-3 bg-amber-50 border border-amber-300 rounded-lg p-4">
                  <div className="flex items-start gap-3">
                    <svg className="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                      <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                    </svg>
                    <div>
                      <p className="text-sm font-semibold text-amber-800">Referred to Underwriting</p>
                      <p className="text-sm text-amber-700 mt-1">
                        The Sum Insured entered exceeds the MIS Motor Comprehensive limit of <strong>P500,000.00</strong>.
                        This quote cannot be processed through the standard quotation flow and must be reviewed manually by the Underwriting team.
                      </p>
                      <p className="text-sm text-amber-700 mt-1">
                        Please contact Underwriting directly to process this case. To use the standard MIS flow, reduce the Sum Insured to P500,000.00 or below.
                      </p>
                    </div>
                  </div>
                </div>
              )}

              {/* Calculated premium result */}
              {calculatedPremium && (
                <div className="mt-4 bg-green-50 border border-green-200 rounded-lg p-4">
                  <h4 className="text-sm font-semibold text-green-800 mb-2">New Premium Calculated</h4>
                  <div className="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                    <div>
                      <span className="text-gray-500 block">Monthly</span>
                      <span className="font-bold text-green-700">{fmtCurrency(calculatedPremium.monthly)}</span>
                    </div>
                    <div>
                      <span className="text-gray-500 block">3-Instalment</span>
                      <span className="font-bold text-green-700">{fmtCurrency(calculatedPremium.threeInstalment)}</span>
                    </div>
                    <div>
                      <span className="text-gray-500 block">Annual</span>
                      <span className="font-bold text-green-700 text-lg">{fmtCurrency(calculatedPremium.annually)}</span>
                    </div>
                    <div>
                      <span className="text-gray-500 block">Rate</span>
                      <span className="font-bold text-green-700">{calculatedPremium.rate ? `${calculatedPremium.rate}%` : '—'}</span>
                    </div>
                  </div>
                  {quote.premium.annually && calculatedPremium.annually && (
                    <p className="text-xs text-gray-500 mt-2">
                      Change: {fmtCurrency(calculatedPremium.annually - (quote.premium.annually ?? 0))}
                      {' '}({calculatedPremium.annually >= (quote.premium.annually ?? 0) ? 'increase' : 'decrease'} from current {fmtCurrency(quote.premium.annually)})
                    </p>
                  )}
                </div>
              )}
            </div>
          </div>
        )}

        {/* Discount / Surcharge */}
        <div className="bg-white rounded-lg shadow-sm border p-5">
          <h3 className="text-base font-semibold text-gray-800 mb-4 border-b pb-2">Discount / Surcharge <span className="text-xs text-gray-400 font-normal">(optional — applied on top of current or re-rated premium)</span></h3>
          <div className="bg-amber-50 border border-amber-200 rounded-md px-3 py-2 text-sm text-amber-700 mb-4">
            Current Annual Premium: <strong>{fmtCurrency(quote?.premium.annually)}</strong>
            {calculatedPremium?.annually && <> | Re-rated: <strong>{fmtCurrency(calculatedPremium.annually)}</strong></>}
          </div>
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <SelectField label="Type" value={adjType} onChange={v => setAdjType(v as 'Discount' | 'Surcharge')}
              options={[{ value: 'Discount', label: 'Discount' }, { value: 'Surcharge', label: 'Surcharge' }]} />
            <SelectField label="Value Type" value={String(adjValueType)} onChange={v => setAdjValueType(Number(v) as 1 | 2)}
              options={[{ value: '1', label: 'Flat Amount (BWP)' }, { value: '2', label: 'Percentage (%)' }]} />
            <Field label="Value" value={adjValue} onChange={setAdjValue} type="number"
              placeholder={adjValueType === 2 ? 'e.g. 10 for 10%' : 'e.g. 500'} />
            <Field label="Reason *" value={adjReason} onChange={setAdjReason} placeholder="Reason for adjustment" />
          </div>
        </div>

        {/* Message & Submit */}
        {message && (
          <div className={`p-3 rounded-md text-sm ${message.type === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'}`}>
            {message.text}
          </div>
        )}

        <div className="flex items-center gap-3">
          <button type="submit" disabled={saving} className="px-6 py-2.5 bg-blue-600 text-white rounded-md font-medium hover:bg-blue-700 disabled:opacity-50 text-sm">
            {saving ? 'Saving...' : 'Update Quote'}
          </button>
          <Link to={`/quotes/${quoteId}`} className="px-6 py-2.5 border border-gray-300 rounded-md text-sm text-gray-600 hover:bg-gray-50">
            Cancel
          </Link>
        </div>
      </form>
    </div>
  )
}

// ─── Reusable components ───────────────────────────────

function Field({ label, value, onChange, type = 'text', placeholder }: {
  label: string; value: string; onChange: (v: string) => void; type?: string; placeholder?: string
}) {
  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
      <input type={type} value={value} onChange={e => onChange(e.target.value)} placeholder={placeholder}
        className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500" />
    </div>
  )
}

function SelectField({ label, value, onChange, options }: {
  label: string; value: string; onChange: (v: string) => void; options: { value: string; label: string }[]
}) {
  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
      <select value={value} onChange={e => onChange(e.target.value)}
        className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
        {options.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
      </select>
    </div>
  )
}

function Spinner() {
  return (
    <div className="absolute right-8 top-1/2 -translate-y-1/2">
      <div className="w-4 h-4 border-2 border-blue-500 border-t-transparent rounded-full animate-spin" />
    </div>
  )
}
