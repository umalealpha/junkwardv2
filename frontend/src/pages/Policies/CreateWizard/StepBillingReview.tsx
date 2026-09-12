// `FileUpload` is only referenced inside the commented-out KYC Documents
// section below; restore it in this import line when un-commenting.
import { InputField, SelectField, /* FileUpload, */ Section, TestDataButton } from './FormField'
import { generateTestBilling } from './testData'
import { formatCurrency } from './helpers'
import type { BillingForm, KycDocuments, PolicyFormData, SavedRiskAddress, CoverageForm, MemberForm, BeneficiaryForm, VehicleForm, DeviceForm } from './types'

interface Props {
  billing: BillingForm
  setBilling: (v: BillingForm) => void
  kycDocs: KycDocuments
  setKycDocs: (v: KycDocuments) => void
  form: PolicyFormData
  savedAddresses: SavedRiskAddress[]
  savedCoverages: CoverageForm[]
  members: MemberForm[]
  beneficiaries: BeneficiaryForm[]
  vehicle: VehicleForm
  device: DeviceForm
  lookups: any
  banks: any[]
  bankBranches: any[]
  banksLoading?: boolean
  branchesLoading?: boolean
  showKyc: boolean
  errors: Record<string, string>
}

export default function StepBillingReview({ billing, setBilling, kycDocs, setKycDocs, form, savedAddresses, savedCoverages, members, beneficiaries, lookups, bankBranches, branchesLoading, showKyc, errors }: Props) {
  const update = (key: keyof BillingForm, value: any) => setBilling({ ...billing, [key]: value })
  // Keep these props destructured so the parent's prop wiring still
  // type-checks; the inline KYC Documents section that consumed them is
  // commented out below. Voids silence "declared but never read".
  void kycDocs; void setKycDocs; void showKyc
  // const updateDoc = (key: keyof KycDocuments, file: File | null) => setKycDocs({ ...kycDocs, [key]: file })
  const showBanking = billing.billing_method && ['DPO', 'RealPay'].includes(billing.billing_method)

  const totalPremium = savedCoverages.reduce((sum, c) => sum + parseFloat(c.calculated_value || '0'), 0)

  return (
    <div className="space-y-6">
      {/* ─── Billing/Payment Details ──────────────────────────── */}
      <Section title="Billing & Payment" action={<TestDataButton onClick={() => setBilling(generateTestBilling())} />}>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <SelectField label="Billing Method" value={billing.billing_method} required
            onChange={v => update('billing_method', v)}
            options={lookups?.billing_methods ?? []} error={errors.billing_method} />
          <InputField label="Billing Start Date" type="date" value={billing.billing_start_date}
            onChange={v => update('billing_start_date', v)} />
          {billing.billing_method === 'Orange' && (
            <InputField label="Orange/Myzaka Cell" value={billing.billing_cell}
              onChange={v => update('billing_cell', v)} placeholder="7XXXXXXX" />
          )}
        </div>

        {showBanking && (
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 p-3 bg-surface-2 rounded-md">
            <SelectField label="Bank" value={billing.bank_id}
              onChange={v => { setBilling({ ...billing, bank_id: v ? parseInt(v) : null, branch_id: null }) }}
              options={lookups?.banks ?? []} />
            <SelectField label="Branch" value={billing.branch_id}
              onChange={v => update('branch_id', v ? parseInt(v) : null)}
              options={bankBranches ?? []} loading={branchesLoading} />
            <InputField label="Account Number" value={billing.account_number}
              onChange={v => update('account_number', v)} />
            <SelectField label="Account Type" value={billing.account_type}
              onChange={v => update('account_type', v)}
              options={lookups?.account_types ?? []} />
          </div>
        )}
      </Section>

      {/* ─── KYC Documents ──────────────────────────────────────
          Commented out per ops request 2026-06-02 — per-doc uploads now
          live on the policy detail page's KYC Documents tab (V8-style
          grid). Restore by un-commenting if the wizard ever needs a
          single-shot KYC upload again. */}
      {/*
      {showKyc && (
        <Section title="KYC Documents">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <FileUpload label="Driving License" file={kycDocs.driving_license} onChange={f => updateDoc('driving_license', f)} />
            <FileUpload label="Omang Copy" file={kycDocs.omang_doc} onChange={f => updateDoc('omang_doc', f)} />
            <FileUpload label="Proof of Residence" file={kycDocs.proof_of_residence} onChange={f => updateDoc('proof_of_residence', f)} />
            <FileUpload label="Proof of Income" file={kycDocs.proof_of_income} onChange={f => updateDoc('proof_of_income', f)} />
            <FileUpload label="Passport Copy" file={kycDocs.passport_doc} onChange={f => updateDoc('passport_doc', f)} />
          </div>
        </Section>
      )}
      */}

      {/* ─── Review Summary ──────────────────────────────────── */}
      <Section title="Policy Summary">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {/* Policy Info */}
          <div>
            <h4 className="text-sm font-semibold text-ink-muted mb-2">Policy</h4>
            <dl className="text-sm space-y-1">
              <div className="flex justify-between"><dt className="text-ink-muted">Product</dt><dd className="font-medium">{lookups?.products?.find((p: any) => p.id === form.product_id)?.name || '-'}</dd></div>
              <div className="flex justify-between"><dt className="text-ink-muted">Frequency</dt><dd className="font-medium">{lookups?.premium_frequencies?.find((f: any) => f.id === form.premium_freq)?.name || '-'}</dd></div>
              <div className="flex justify-between"><dt className="text-ink-muted">Start</dt><dd className="font-medium">{form.term_start_date}</dd></div>
              <div className="flex justify-between"><dt className="text-ink-muted">Expiry</dt><dd className="font-medium">{form.expiry_date}</dd></div>
              <div className="flex justify-between"><dt className="text-ink-muted">Binder Date</dt><dd className="font-medium">{form.binder_date}</dd></div>
              <div className="flex justify-between"><dt className="text-ink-muted">GFS No</dt><dd className="font-medium">{form.gfs_policy_no || '-'}</dd></div>
            </dl>
          </div>

          {/* Customer Info */}
          <div>
            <h4 className="text-sm font-semibold text-ink-muted mb-2">Customer</h4>
            <dl className="text-sm space-y-1">
              <div className="flex justify-between"><dt className="text-ink-muted">Name</dt><dd className="font-medium">{form.first_name} {form.middle_name} {form.last_name}</dd></div>
              <div className="flex justify-between"><dt className="text-ink-muted">Omang</dt><dd className="font-medium">{form.omang || '-'}</dd></div>
              <div className="flex justify-between"><dt className="text-ink-muted">Phone</dt><dd className="font-medium">{form.cellphone}</dd></div>
              <div className="flex justify-between"><dt className="text-ink-muted">Email</dt><dd className="font-medium">{form.email}</dd></div>
              <div className="flex justify-between"><dt className="text-ink-muted">Gender</dt><dd className="font-medium">{form.gender}</dd></div>
              <div className="flex justify-between"><dt className="text-ink-muted">DOB</dt><dd className="font-medium">{form.dob}</dd></div>
            </dl>
          </div>
        </div>

        {/* Counts Summary */}
        <div className="grid grid-cols-2 md:grid-cols-5 gap-3 mt-4">
          <div className="p-3 bg-status-info-bg rounded-md text-center">
            <p className="text-2xl font-bold text-primary">{savedAddresses.length}</p>
            <p className="text-xs text-primary">Risk Addresses</p>
          </div>
          <div className="p-3 bg-status-success-bg rounded-md text-center">
            <p className="text-2xl font-bold text-status-success-fg">{savedCoverages.length}</p>
            <p className="text-xs text-status-success-fg">Coverages</p>
          </div>
          <div className="p-3 bg-status-accent-bg rounded-md text-center">
            <p className="text-2xl font-bold text-status-accent-fg">{members.length}</p>
            <p className="text-xs text-status-accent-fg">Members</p>
          </div>
          <div className="p-3 bg-status-warning-bg rounded-md text-center">
            <p className="text-2xl font-bold text-status-warning-fg">{beneficiaries.length}</p>
            <p className="text-xs text-status-warning-fg">Beneficiaries</p>
          </div>
          <div className="p-3 bg-status-info-bg rounded-md text-center">
            <p className="text-2xl font-bold text-primary">{formatCurrency(totalPremium)}</p>
            <p className="text-xs text-primary">Total Premium</p>
          </div>
        </div>
      </Section>
    </div>
  )
}
