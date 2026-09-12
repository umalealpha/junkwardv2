import { InputField, SelectField, Section, TestDataButton } from './FormField'
import { generateTestBeneficiary } from './testData'
import type { BeneficiaryForm } from './types'
import { INITIAL_BENEFICIARY } from './types'

interface Props {
  beneficiaries: BeneficiaryForm[]
  setBeneficiaries: (v: BeneficiaryForm[]) => void
  lookups: any
  errors: Record<string, string>
}

export default function StepBeneficiaries({ beneficiaries, setBeneficiaries, lookups }: Props) {
  const addBeneficiary = () => setBeneficiaries([...beneficiaries, { ...INITIAL_BENEFICIARY }])
  const removeBeneficiary = (i: number) => setBeneficiaries(beneficiaries.filter((_, idx) => idx !== i))
  const updateBeneficiary = (i: number, key: keyof BeneficiaryForm, value: any) => {
    const updated = [...beneficiaries]
    updated[i] = { ...updated[i], [key]: value }
    setBeneficiaries(updated)
  }

  const totalPercent = beneficiaries.reduce((sum, b) => sum + (b.payment_percent || 0), 0)
  const percentValid = totalPercent === 100 || beneficiaries.length === 0

  return (
    <div className="space-y-6">
      <Section title="Beneficiaries" action={
        <div className="flex gap-2">
          <TestDataButton onClick={() => setBeneficiaries([...beneficiaries, generateTestBeneficiary()])} />
          <button onClick={addBeneficiary} className="px-3 py-1 text-xs bg-primary text-white rounded hover:bg-primary">+ Add Beneficiary</button>
        </div>
      }>
        {beneficiaries.length > 0 && (
          <div className={`text-sm font-medium px-3 py-1.5 rounded ${percentValid ? 'bg-status-success-bg text-status-success-fg' : 'bg-status-danger-bg text-status-danger-fg'}`}>
            Total Payment Allocation: {totalPercent}% {!percentValid && '(must equal 100%)'}
          </div>
        )}

        {beneficiaries.length === 0 ? (
          <p className="text-sm text-ink-muted text-center py-4">No beneficiaries added yet. Click "Add Beneficiary" to add.</p>
        ) : (
          <div className="space-y-4">
            {beneficiaries.map((b, i) => (
              <div key={i} className="p-4 border rounded-md bg-surface-2">
                <div className="flex justify-between items-center mb-3">
                  <h4 className="text-sm font-medium text-ink-muted">Beneficiary {i + 1}</h4>
                  <button onClick={() => removeBeneficiary(i)} className="text-xs text-status-danger-fg hover:text-status-danger-fg">Remove</button>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
                  <SelectField label="Relation" value={b.relation}
                    onChange={v => updateBeneficiary(i, 'relation', v)}
                    options={lookups?.beneficiary_relations ?? []} />
                  <InputField label="Omang" value={b.omang}
                    onChange={v => updateBeneficiary(i, 'omang', v)} placeholder="9 digits" />
                  <InputField label="Passport" value={b.passport}
                    onChange={v => updateBeneficiary(i, 'passport', v)} />
                  <InputField label="Payment %" type="number" value={b.payment_percent}
                    onChange={v => updateBeneficiary(i, 'payment_percent', parseInt(v) || 0)} />
                </div>
                <div className="grid grid-cols-1 md:grid-cols-4 gap-3 mt-3">
                  <InputField label="First Name" value={b.first_name}
                    onChange={v => updateBeneficiary(i, 'first_name', v)} />
                  <InputField label="Last Name" value={b.last_name}
                    onChange={v => updateBeneficiary(i, 'last_name', v)} />
                  <InputField label="Date of Birth" type="date" value={b.dob}
                    onChange={v => updateBeneficiary(i, 'dob', v)} />
                  <SelectField label="Gender" value={b.gender}
                    onChange={v => updateBeneficiary(i, 'gender', v)}
                    options={lookups?.genders ?? []} />
                </div>
              </div>
            ))}
          </div>
        )}
      </Section>
    </div>
  )
}
