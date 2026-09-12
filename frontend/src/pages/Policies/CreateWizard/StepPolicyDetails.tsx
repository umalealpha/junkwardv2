import { useState, useEffect, useRef } from 'react'
import { InputField, SelectField, TextAreaField, Section, TestDataButton } from './FormField'
import SearchableSelect from '../../../components/common/SearchableSelect'
import { useToast } from '../../../components/common/Toast'
import { useConfirm } from '../../../components/common/ConfirmDialog'
import apiClient from '../../../api/client'
import { calcExpiry, isComgProduct, isCommercialInsuranceProduct, isSpecialistProduct, isCompanyOnlyProduct, isOrganisationOnlyProduct, ANNUAL_FREQ } from './helpers'
import { generateTestPolicy } from './testData'
import { MIN_START_DATE } from './types'
import type { PolicyFormData } from './types'

interface Props {
  form: PolicyFormData
  setForm: (fn: (prev: PolicyFormData) => PolicyFormData) => void
  errors: Record<string, string>
  setErrors: (fn: (prev: Record<string, string>) => Record<string, string>) => void
  lookups: any
  agencies: any[]
  agents: any[]
  cities: any[]
  plans: any[]
  companies: any[]
  agenciesLoading?: boolean
  agentsLoading?: boolean
  citiesLoading?: boolean
  plansLoading?: boolean
  isEditMode?: boolean
  // True only when the policy is a NEW BUSINESS quote (or being created).
  // Unlocks the Expiry (end) date so the term can be set freely; otherwise
  // expiry stays auto-calculated from the premium frequency.
  datesEditable?: boolean
}

export default function StepPolicyDetails({ form, setForm, errors, setErrors, lookups, agencies, agents, cities, plans, companies, agentsLoading, citiesLoading, plansLoading, isEditMode, datesEditable }: Props) {
  const isComg = isComgProduct(form.product_id)
  // Commercial Insurance individuals don't need Omang/Passport (per UW).
  const omangOptional = isCommercialInsuranceProduct(form.product_id)
  // Commercial Liabilities (20) / Guarantee (23) / Miscellaneous (24):
  // Annual-term only.
  const isCompanyOnly = isCompanyOnlyProduct(form.product_id)
  // Guarantee (23) / Miscellaneous (24) only: Organisation-held, no
  // individual customer capture. Commercial Liabilities (20) allows BOTH
  // Individual and Organisation holders (UW 2026-08-26), so it is annual-only
  // but not entity-locked.
  const isOrgOnly = isOrganisationOnlyProduct(form.product_id)

  // Keep the form in the only shape these products allow. Covers BOTH entry
  // points: picking the product on create, and loading an existing policy in
  // edit mode (where entity_type / premium_freq come from the API and may
  // carry a legacy Individual / Monthly value).
  useEffect(() => {
    if (!isCompanyOnly) return
    const freqWrong = form.premium_freq !== ANNUAL_FREQ
    const entityWrong = isOrgOnly && form.entity_type !== 'Organisation'
    if (!entityWrong && !freqWrong) return
    setForm(p => ({
      ...p,
      // Only force the holder on the entity-locked products — Commercial
      // Liabilities keeps whatever the operator picked.
      entity_type: entityWrong ? 'Organisation' : p.entity_type,
      premium_freq: ANNUAL_FREQ,
      // Only re-derive the term when the frequency itself was wrong — an
      // existing annual policy keeps the expiry it was issued with.
      expiry_date: freqWrong ? calcExpiry(p.term_start_date, ANNUAL_FREQ) : p.expiry_date,
    }))
    setErrors(p => ({ ...p, entity_type: '', premium_freq: '' }))
  }, [isCompanyOnly, isOrgOnly, form.entity_type, form.premium_freq])

  const REQUIRED_FIELDS: Record<string, string> = {
    product_id: 'Product is required',
    plan_id: 'Plan is required',
    premium_freq: 'Premium frequency is required',
    term_start_date: 'Start date is required',
    entity_type: 'Entity type is required',
    first_name: 'First name is required',
    last_name: 'Last name is required',
    cellphone: 'Phone is required',
    gender: 'Gender is required',
    dob: 'Date of birth is required',
    marital_status: 'Marital status is required',
    state: 'Province is required',
    city: 'City is required',
    post_address: 'Post address is required',
    source_of_income: 'Source of income is required',
  }

  const update = (key: keyof PolicyFormData, value: any) => {
    setForm(p => ({ ...p, [key]: value }))
    // When entity_type changes, clear email error (email is only required for Individual)
    if (key === 'entity_type') {
      setErrors(p => ({ ...p, [key]: '', email: '' }))
    } else {
      setErrors(p => ({ ...p, [key]: '' }))
    }
  }

  // Real-time validation on blur
  const validateField = (key: string, value: any) => {
    const v = String(value ?? '').trim()

    // Required check
    if (REQUIRED_FIELDS[key] && !v) {
      setErrors(p => ({ ...p, [key]: REQUIRED_FIELDS[key] })); return
    }

    // First Name / Last Name: letters only, max 16
    if ((key === 'first_name' || key === 'last_name') && v) {
      if (!/^[a-zA-Z\s'-]+$/.test(v)) { setErrors(p => ({ ...p, [key]: 'Letters only' })); return }
      if (v.length > 16) { setErrors(p => ({ ...p, [key]: 'Max 16 characters' })); return }
    }

    // Cellphone: exactly 8 digits, must start with 7
    if (key === 'cellphone' && v) {
      if (!/^7\d{7}$/.test(v)) { setErrors(p => ({ ...p, cellphone: 'Must be 8 digits starting with 7' })); return }
    }

    // Email: optional but must be valid if provided
    if (key === 'email' && v) {
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) { setErrors(p => ({ ...p, email: 'Invalid email format' })); return }
    }

    // Omang: 9 digits, 5th digit must be 1 or 2
    if (key === 'omang' && v) {
      if (!/^\d{9}$/.test(v)) { setErrors(p => ({ ...p, omang: '9 digits required' })); return }
      if (v[4] !== '1' && v[4] !== '2') { setErrors(p => ({ ...p, omang: '5th digit must be 1 or 2' })); return }
    }

    // Passport: alphanumeric, max 16
    if (key === 'passport' && v) {
      if (!/^[a-zA-Z0-9]+$/.test(v)) { setErrors(p => ({ ...p, passport: 'Alphanumeric only' })); return }
      if (v.length > 16) { setErrors(p => ({ ...p, passport: 'Max 16 characters' })); return }
    }

    // DOB: must be 18-100 years old
    if (key === 'dob' && v) {
      const age = Math.floor((Date.now() - new Date(v).getTime()) / (365.25 * 24 * 60 * 60 * 1000))
      if (age < 18) { setErrors(p => ({ ...p, dob: 'Must be at least 18 years old' })); return }
      if (age > 100) { setErrors(p => ({ ...p, dob: 'Age cannot exceed 100 years' })); return }
    }

    // Start Date: allow any date (including past dates for backdated endorsements)
    // No validation check needed — let backend handle any date constraints

    // Binder Date: optional field, no validation needed

    // Post Address: max 80
    if (key === 'post_address' && v && v.length > 80) {
      setErrors(p => ({ ...p, post_address: 'Max 80 characters' })); return
    }

    // Omang or Passport required only for Individual policy holders.
    // Organisation (Commercial) policies don't need either — they're held
    // by a company, not a natural person.
    if (!omangOptional && form.entity_type === 'Individual' && (key === 'omang' || key === 'passport') && !v) {
      const otherField = key === 'omang' ? form.passport : form.omang
      if (!otherField) {
        const fieldKey = key === 'omang' ? 'omang' : 'passport'
        setErrors(p => ({ ...p, [fieldKey]: 'Either Omang or Passport is required' })); return
      }
    }

    // Company required for Organisation
    if (key === 'company_id' && form.entity_type === 'Organisation' && !v) {
      setErrors(p => ({ ...p, company_id: 'Company is required for Organisation' })); return
    }

    // If we reach here, validation passed — clear any previous error for this field and related fields
    if (key === 'omang' || key === 'passport') {
      setErrors(p => ({ ...p, omang: '', passport: '' }))
    } else {
      setErrors(p => ({ ...p, [key]: '' }))
    }
  }

  const updateEmployment = (key: string, value: string) => {
    setForm(p => ({ ...p, employment: { ...p.employment, [key]: value } }))
  }

  return (
    <div className="space-y-6">
      {/* ─── Product & Policy Section ─────────────────────────── */}
      <Section title="Product & Policy" action={<TestDataButton onClick={() => {
        const td = generateTestPolicy()
        setForm(() => td)
      }} />}>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          {/* Product field: Read-only in edit mode to prevent changing already-issued policies,
              EXCEPT for the specialist single-site products (Engineering 16/17, Specialist 18,
              Commercial Liabilities 20, Marine 22) where switching between specialist product
              types is permitted. Once a policy is issued, its product type is otherwise immutable.
              Using isDisabled prop on react-select prevents all interaction.

              UAT 2026-05-26 (Arjun H1): "Commercial Insurance" and "Domestic Insurance"
              were appearing twice in the dropdown. Likely a duplicate row in the products
              table or a join multiplier in the lookup. Dedupe by id at point of consumption
              so a stray duplicate row in the DB doesn't surface in the UI.
              Real fix-later: clean the products table. */}
          <SelectField label="Product" value={form.product_id} required
            disabled={isEditMode && !isSpecialistProduct(form.product_id)}
            onChange={v => {
              const pid = v ? parseInt(v) : null
              setForm(p => ({ ...p, product_id: pid, plan_id: null }))
              setErrors(p => ({ ...p, product_id: '' }))
            }}
            options={Array.from(
              new Map<string, any>((lookups?.products ?? []).map((p: any) => [String(p.id ?? p.value), p])).values()
            ) as any}
            error={errors.product_id} />

          {/* Plan field: Editable in both create and edit modes.
              Operators can update the plan/coverage selection after policy issuance. */}
          <SelectField label="Plan" value={form.plan_id} required
            onChange={v => update('plan_id', v ? parseInt(v) : null)}
            options={plans ?? []} loading={plansLoading} error={errors.plan_id} />


          {/* Entity Type. Locked to Organisation on the organisation-only
              products (Guarantee 23 / Miscellaneous 24) — these are COMG
              company lines with no individual holder, so the Individual option
              is not offered at all rather than offered and rejected on save.
              Commercial Liabilities (20) is NOT locked: per UW (2026-08-26) it
              takes an Individual or an Organisation, so both options show. */}
          <div>
            <label className="block text-xs font-medium text-ink-muted mb-2">Entity Type <span className="text-status-danger-fg">*</span></label>
            {isOrgOnly ? (
              <div className="flex items-center gap-1.5 py-2 px-3 bg-surface-2 rounded-lg text-sm font-medium text-ink-muted border">
                <span>🏢</span> Organisation
                <span className="ml-auto text-xs font-normal">Company only</span>
              </div>
            ) : (
              <div className="flex gap-1 bg-surface-2 rounded-lg p-0.5">
                {[{ id: 'Individual', label: 'Individual', icon: '👤' }, { id: 'Organisation', label: 'Organisation', icon: '🏢' }].map(opt => (
                  <button key={opt.id} type="button"
                    onClick={() => update('entity_type', opt.id)}
                    className={`flex-1 py-2 text-sm font-medium rounded-md transition flex items-center justify-center gap-1.5 ${
                      form.entity_type === opt.id
                        ? 'bg-primary text-white shadow'
                        : 'text-ink-muted hover:text-ink-muted'
                    }`}>
                    <span>{opt.icon}</span> {opt.label}
                  </button>
                ))}
              </div>
            )}
          </div>
        </div>

        {form.entity_type === 'Organisation' && (
          <CompanySelector form={form} update={update} errors={errors} companies={companies} lookups={lookups} />
        )}

        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <SearchableSelect label="Agency" value={form.agency_id ?? ''} required
            onChange={v => {
              setForm(p => ({ ...p, agency_id: v ? parseInt(v) : null, agent_id: null }))
              setErrors(p => ({ ...p, agency_id: '' }))
            }}
            fetchOptions={async (search) => {
              const resp = await apiClient.get('/lookups/agencies', { params: { search } })
              return (resp.data.data ?? []).map((a: any) => ({ value: String(a.id), label: a.name }))
            }}
            options={(agencies ?? []).map((a: any) => ({ value: String(a.id ?? a.value), label: a.name ?? a.label }))}
            placeholder="Search agency..."
            error={errors.agency_id} />

          <SelectField label="Agent" value={form.agent_id}
            onChange={v => update('agent_id', v ? parseInt(v) : null)}
            options={agents ?? []} loading={agentsLoading} error={errors.agent_id} />

          <SelectField label="UW Status" value={form.uw_app_status ?? ''}
            onChange={v => update('uw_app_status' as any, v)}
            options={lookups?.uw_statuses ?? []} />

          {/* Company-only products are Annual-term only — show ANNUAL alone
              rather than the full Monthly/Annual/Quarterly/Manual list. */}
          <SelectField label="Premium Frequency" value={form.premium_freq} required
            onChange={v => {
              setForm(p => ({
                ...p,
                premium_freq: v,
                expiry_date: calcExpiry(p.term_start_date, v),
              }))
              setErrors(p => ({ ...p, premium_freq: '' }))
            }}
            options={isCompanyOnly
              ? (lookups?.premium_frequencies ?? []).filter((f: any) => String(f.id ?? f.value) === ANNUAL_FREQ)
              : (lookups?.premium_frequencies ?? [])}
            error={errors.premium_freq} />
        </div>

        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <InputField label="Start Date" type="date" value={form.term_start_date} required
            min={MIN_START_DATE}
            onChange={v => {
              setForm(p => ({
                ...p,
                term_start_date: v,
                expiry_date: calcExpiry(v, p.premium_freq),
              }))
              setErrors(p => ({ ...p, term_start_date: '' }))
            }} onBlur={() => validateField('term_start_date', form.term_start_date)} error={errors.term_start_date} />

          <InputField label="Expiry Date" type="date" value={form.expiry_date}
            onChange={v => update('expiry_date', v)}
            disabled={!datesEditable && form.premium_freq !== '6'} error={errors.expiry_date} />

          <InputField label="Binder Date" type="date" value={form.binder_date}
            onChange={v => update('binder_date', v)} />

          <InputField label="GFS Policy No" value={form.gfs_policy_no}
            onChange={v => update('gfs_policy_no', v)} />
        </div>

        {/* COMG Date removed per requirement */}
      </Section>

      {/* ─── Customer Details ─────────────────────────────────── */}
      {/* Individual customer capture. Not rendered on the organisation-only
          products (23 / 24) — the holder is the Organisation picked above, so
          there are no individual details to collect. Commercial Liabilities
          (20) keeps this section, since it can be held by an Individual. */}
      {!isOrgOnly && (<>
        <Section title="Customer Details">
          {/* UAT 2026-05-28 (Muskan B4 partial): operators reported the
              asterisk was missing on the Omang ID field even though it's
              required. The accurate rule is "either Omang or Passport" —
              so we mark BOTH fields with the visual asterisk (the parent
              validator at validateStep1 enforces the either/or logic; the
              asterisk on InputField is purely visual). Helper text is also
              promoted from a thin blue note to a more visible amber pill
              so operators can't miss the either/or rule. */}
          {form.entity_type === 'Individual' && !omangOptional && (
            <div className="mb-3 inline-flex items-center gap-2 px-3 py-1.5 bg-status-warning-bg border border-status-warning-fg rounded-md">
              <span className="text-status-warning-fg font-bold">!</span>
              <p className="text-xs text-status-warning-fg font-medium">Either <b>Omang ID</b> or <b>Passport</b> is required — fill one of the two.</p>
            </div>
          )}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <InputField label="Omang ID" value={form.omang} required={form.entity_type === 'Individual' && !omangOptional}
              onChange={v => update('omang', v)} onBlur={() => validateField('omang', form.omang)} error={errors.omang} placeholder="9 digits" />
            <InputField label="Passport" value={form.passport} required={form.entity_type === 'Individual' && !omangOptional}
              onChange={v => update('passport', v)} onBlur={() => validateField('passport', form.passport)} error={errors.passport} placeholder="Alphanumeric, max 16" />
            <InputField label="Date of Birth" type="date" value={form.dob} required
              onChange={v => update('dob', v)} onBlur={() => validateField('dob', form.dob)} error={errors.dob} />
          </div>

          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <InputField label="First Name" value={form.first_name} required
              onChange={v => update('first_name', v)} onBlur={() => validateField('first_name', form.first_name)} error={errors.first_name} />
            <InputField label="Middle Name" value={form.middle_name}
              onChange={v => update('middle_name', v)} />
            <InputField label="Last Name" value={form.last_name} required
              onChange={v => update('last_name', v)} onBlur={() => validateField('last_name', form.last_name)} error={errors.last_name} />
            <div>
              <label className="block text-xs font-medium text-ink-muted mb-2">Gender <span className="text-status-danger-fg">*</span></label>
              <div className="flex gap-1 bg-surface-2 rounded-lg p-0.5">
                {[{ id: 'Male', label: 'Male' }, { id: 'Female', label: 'Female' }].map(opt => (
                  <button key={opt.id} type="button"
                    onClick={() => update('gender', opt.id)}
                    className={`flex-1 py-2 text-sm font-medium rounded-md transition ${
                      form.gender === opt.id
                        ? 'bg-primary text-white shadow'
                        : 'text-ink-muted hover:text-ink-muted'
                    }`}>
                    {opt.label}
                  </button>
                ))}
              </div>
              {errors.gender && <p className="text-xs text-status-danger-fg mt-0.5">{errors.gender}</p>}
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <InputField label="Cellphone" value={form.cellphone} required
              onChange={v => update('cellphone', v)} onBlur={() => validateField('cellphone', form.cellphone)} error={errors.cellphone} placeholder="7XXXXXXX" />
            <InputField label="Email" type="email" value={form.email}
              onChange={v => update('email', v)} onBlur={() => validateField('email', form.email)} error={errors.email} />
            <SelectField label="Marital Status" value={form.marital_status} required
              onChange={v => update('marital_status', v)}
              options={lookups?.marital_statuses ?? []} error={errors.marital_status} />
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <SelectField label="Province" value={form.state} required
              onChange={v => {
                setForm(p => ({ ...p, state: v ? parseInt(v) : null, city: null }))
                setErrors(p => ({ ...p, state: '' }))
              }}
              options={lookups?.states ?? []} error={errors.state} />
            <SelectField label="City" value={form.city} required
              onChange={v => update('city', v ? parseInt(v) : null)}
              options={cities ?? []} loading={citiesLoading} error={errors.city} />
            <InputField label="Post Address" value={form.post_address} required
              onChange={v => update('post_address', v)} onBlur={() => validateField('post_address', form.post_address)} error={errors.post_address} placeholder="Max 80 characters" />
          </div>
        </Section>
      </>)}

      {/* ─── Source of Income ───────────────────────────────────
          Title was "Source of Income & KYC" — the inline KYC docs
          upload UI lives commented out in PolicyCreatePage (see
          "KYC Documents — commented out"), so drop "& KYC" from
          the heading until it's restored. */}
      <Section title={isOrgOnly ? 'Underwriting Questions' : 'Source of Income'}>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {/* Personal income / employment capture — individual holders only.
              Organisation-only products (23 / 24) capture no customer
              details, so these fields are not rendered. */}
          {!isOrgOnly && (<>
            <SelectField label="Source of Income" value={form.source_of_income} required
              onChange={v => update('source_of_income', v)}
              options={lookups?.source_of_income ?? []} error={errors.source_of_income} />

            {form.source_of_income === 'employment' && (
              <>
                <InputField label="Where are you employed?" value={form.employment['where_are_you_employed_?'] || ''}
                  onChange={v => updateEmployment('where_are_you_employed_?', v)} />
                <InputField label="Monthly Salary" type="number" value={form.employment['what_is_your_monthly_salary_?'] || ''}
                  onChange={v => updateEmployment('what_is_your_monthly_salary_?', v)} />
              </>
            )}
            {form.source_of_income === 'bussiness' && (
              <>
                <InputField label="Business Name" value={form.employment['business_name'] || ''}
                  onChange={v => updateEmployment('business_name', v)} />
                <InputField label="Business Address" value={form.employment['business_address'] || ''}
                  onChange={v => updateEmployment('business_address', v)} />
                <InputField label="Monthly Income" type="number" value={form.employment['monthly_income'] || ''}
                  onChange={v => updateEmployment('monthly_income', v)} />
              </>
            )}
            {form.source_of_income === 'pensioner_retired' && (
              <InputField label="Monthly Pension" type="number" value={form.employment['monthly_pension'] || ''}
                onChange={v => updateEmployment('monthly_pension', v)} />
            )}
            {form.source_of_income === 'inheritance' && (
              <InputField label="Source of Funds" value={form.employment['source_of_funds'] || ''}
                onChange={v => updateEmployment('source_of_funds', v)} />
            )}
            {form.source_of_income === 'gifts' && (
              <>
                <InputField label="Gifter Name" value={form.employment['gifter_name'] || ''}
                  onChange={v => updateEmployment('gifter_name', v)} />
                <InputField label="Amount Gifted" type="number" value={form.employment['amount_gifted'] || ''}
                  onChange={v => updateEmployment('amount_gifted', v)} />
              </>
            )}
            {form.source_of_income === 'investments' && (
              <>
                <InputField label="Amount Invested" type="number" value={form.employment['amount_invested'] || ''}
                  onChange={v => updateEmployment('amount_invested', v)} />
                <InputField label="Monthly Earnings" type="number" value={form.employment['monthly_earnings'] || ''}
                  onChange={v => updateEmployment('monthly_earnings', v)} />
              </>
            )}
          </>)}
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <SelectField label="Are you currently insured?" value={form.currently_insured}
            onChange={v => update('currently_insured', v)}
            options={lookups?.currently_insured ?? []} />
          {/* Current Insurer Details: Only show when user is currently insured.
              Capture the policy number or details of existing insurance to assess overlap and claims history. */}
          {form.currently_insured && form.currently_insured !== 'Not Insured' && String(form.currently_insured) !== '510' && (
            <InputField label="Current Insurer Details" value={form.current_insurer_detail ?? ''}
              onChange={v => update('current_insurer_detail' as any, v)}
              placeholder="Policy number or details" />
          )}
          <SelectField label="How did you hear about Alpha Direct?" value={form.hear_about_alpha}
            onChange={v => update('hear_about_alpha', v)}
            options={lookups?.hear_about_alpha ?? []} />
        </div>

        {/* Insurance History Questions */}
        <div className="bg-surface-2 rounded-lg p-4 space-y-3 border">
          <h4 className="text-sm font-semibold text-ink-muted">Has any insurer ever:</h4>
          <YesNoToggle label="(a) Declined any proposal?" value={form.decline_proposal} onChange={v => update('decline_proposal', v)} />
          <YesNoToggle label="(b) Refused to renew any policy?" value={form.refused_policy} onChange={v => update('refused_policy', v)} />
          <YesNoToggle label="(c) Cancelled any policy?" value={form.cancel_policy} onChange={v => update('cancel_policy', v)} />
        </div>

        {/* COMG-specific questions */}
        {isComg && (
          <div className="bg-status-info-bg rounded-lg p-4 space-y-3 border border-primary">
            <h4 className="text-sm font-semibold text-primary">Commercial Questions</h4>
            <YesNoToggle label="Have you or any member of your firm ever made a compromise with creditors or been declared insolvent?"
              value={form.firm_member} onChange={v => update('firm_member', v)} />
            <YesNoToggle label="Do you keep a complete set of books showing a true and accurate record of business transacted?"
              value={form.books} onChange={v => update('books', v)} />
            <InputField label="Date Business Established" type="date" value={form.date}
              onChange={v => update('date', v)} />
          </div>
        )}
      </Section>

      {/* ─── Notes ────────────────────────────────────────────── */}
      <Section title="Notes">
        <TextAreaField label="Policy Notes" value={form.note} rows={3}
          onChange={v => update('note', v)} placeholder="Add any additional notes about this policy..." />
        <TextAreaField label="Business Note" value={form.business_note} rows={2}
          onChange={v => update('business_note', v)} placeholder="Internal business notes..." />
      </Section>
    </div>
  )
}

// ── Yes/No Toggle ───────────────────────────────────────────

function YesNoToggle({ label, value, onChange }: { label: string; value: boolean; onChange: (v: boolean) => void }) {
  return (
    <div className="flex items-center justify-between gap-4">
      <span className="text-sm text-ink-muted flex-1">{label}</span>
      <div className="flex gap-0.5 bg-surface-2 rounded-full p-0.5 flex-shrink-0">
        <button type="button" onClick={() => onChange(false)}
          className={`px-3 py-1 text-xs font-medium rounded-full transition ${!value ? 'bg-status-success-fg text-white shadow' : 'text-ink-muted'}`}>
          NO
        </button>
        <button type="button" onClick={() => onChange(true)}
          className={`px-3 py-1 text-xs font-medium rounded-full transition ${value ? 'bg-status-danger-fg text-white shadow' : 'text-ink-muted'}`}>
          YES
        </button>
      </div>
    </div>
  )
}

// ── Company Selector with Create Modal ──────────────────────

function CompanySelector({ form, update, errors, companies, lookups }: any) {
  const { toast } = useToast()
  const [showModal, setShowModal] = useState(false)
  const [saving, setSaving] = useState(false)
  const [companyCities, setCompanyCities] = useState<any[]>([])
  const [citiesLoading, setCitiesLoading] = useState(false)
  const [selectedCompanyDetails, setSelectedCompanyDetails] = useState<any>(null)
  const [detailsConfirmed, setDetailsConfirmed] = useState(false)
  const detailsLoadedFor = useRef<number | null>(null)

  // Load company details when selected
  async function loadCompanyDetails(companyId: number) {
    try {
      const resp = await apiClient.get(`/lookups/companies/${companyId}/details`)
      setSelectedCompanyDetails(resp.data.data)
    } catch {
      // Details endpoint unreachable — still show the card (with whatever
      // name the picker knows) so the Edit button is never missing.
      const found = (companies ?? []).find((c: any) => c.id === companyId || c.value === String(companyId))
      setSelectedCompanyDetails({ id: companyId, name: found?.name ?? found?.label ?? '' })
    }
  }

  async function onCompanySelected(companyId: number | null) {
    update('company_id', companyId)
    setDetailsConfirmed(false)
    // Claim the id so the hydrate effect below does not re-fetch and
    // auto-confirm what the user just picked by hand.
    detailsLoadedFor.current = companyId
    if (!companyId) { setSelectedCompanyDetails(null); return }
    await loadCompanyDetails(companyId)
  }

  // Edit mode: the company arrives already selected from the saved policy, so
  // onCompanySelected never fires and the details card — the only "Edit
  // Company" path on this screen — stayed hidden. Hydrate it on load (and on
  // any company_id change that did not come from the picker) so UW can edit
  // company data here instead of going to Company Master.
  useEffect(() => {
    const id = form.company_id ? Number(form.company_id) : null
    if (!id) { detailsLoadedFor.current = null; return }
    if (detailsLoadedFor.current === id) return
    detailsLoadedFor.current = id
    // Saved policy data — show it settled, not as an unconfirmed warning.
    // The UW clicks Edit on the card to change it.
    loadCompanyDetails(id).then(() => setDetailsConfirmed(true))
    /* eslint-disable-next-line react-hooks/exhaustive-deps */
  }, [form.company_id])
  const [companyForm, setCompanyForm] = useState({
    name: '', VAT_registration_number: '', company_registration_number: '',
    head_office_physical_address: '', postal_address: '', city: '', state: '',
    pincode: '', status: '1', contact_person_first_name: '', contact_person_last_name: '',
    contact_person_number: '', primary_email: '', secondary_email: '', broker_email: '',
  })

  // Load cities when province changes
  async function onProvinceChange(stateId: string) {
    cf('state', stateId)
    cf('city', '')
    if (!stateId) { setCompanyCities([]); return }
    setCitiesLoading(true)
    try {
      const resp = await apiClient.get(`/lookups/states/${stateId}/cities`)
      setCompanyCities(resp.data.data ?? [])
    } catch { setCompanyCities([]) }
    setCitiesLoading(false)
  }

  const [companyErrors, setCompanyErrors] = useState<Record<string, string>>({})

  function validateCompany(): Record<string, string> {
    const e: Record<string, string> = {}
    const c = companyForm
    if (!c.name) e.name = 'Required'
    else if (c.name.length > 40) e.name = 'Max 40 characters'
    else if (!/^[a-zA-Z0-9\s().,&'-]+$/.test(c.name)) e.name = 'Alphanumeric only'
    if (!c.VAT_registration_number) e.VAT_registration_number = 'Required'
    else if (c.VAT_registration_number.length > 15) e.VAT_registration_number = 'Max 15 characters'
    else if (!/^[a-zA-Z0-9]+$/.test(c.VAT_registration_number)) e.VAT_registration_number = 'Alphanumeric only'
    if (!c.company_registration_number) e.company_registration_number = 'Required'
    else if (c.company_registration_number.length > 15) e.company_registration_number = 'Max 15 characters'
    if (!c.head_office_physical_address) e.head_office_physical_address = 'Required'
    if (!c.postal_address) e.postal_address = 'Required'
    if (!c.state) e.state = 'Required'
    if (!c.city) e.city = 'Required'
    if (!c.contact_person_first_name) e.contact_person_first_name = 'Required'
    if (!c.contact_person_last_name) e.contact_person_last_name = 'Required'
    if (!c.contact_person_number) e.contact_person_number = 'Required'
    else if (!/^7\d{7}$/.test(c.contact_person_number)) e.contact_person_number = '8 digits starting with 7'
    if (!c.primary_email) e.primary_email = 'Required'
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(c.primary_email)) e.primary_email = 'Invalid email'
    setCompanyErrors(e)
    return e
  }

  async function handleCreate() {
    const errs = validateCompany()
    if (Object.keys(errs).length > 0) {
      // Surface the first missing field so the user sees why the button
      // appears to "do nothing" — e.g. when City is required but the
      // SelectField error message scrolled off-screen in the modal.
      const fieldName = Object.keys(errs)[0]
      toast.warning(`${fieldName.replace(/_/g, ' ')}: ${errs[fieldName]}`)
      return
    }
    setSaving(true)
    try {
      const resp = await apiClient.post('/companies', companyForm)
      update('company_id', resp.data.data.id)
      setShowModal(false)
    } catch (e: any) {
      // Laravel 422 wraps field errors under `errors`. Surface them so
      // backend-side rejections (e.g. duplicate VAT, schema mismatch) are
      // visible instead of a generic "Failed to create company".
      const data = e.response?.data
      const fieldErrs = data?.errors
        ? Object.values(data.errors).flat().join('\n')
        : null
      toast.error(fieldErrs || data?.message || 'Failed to create company')
    }
    setSaving(false)
  }

  const cf = (k: string, v: string) => setCompanyForm(p => ({ ...p, [k]: v }))

  // Picker options, with the selected company's live name forced in. Covers
  // both a rename saved below and a company that never made it into the
  // cached lookup list (which is capped) — without this the field can render
  // blank even though a company is selected.
  const companyOptions = (() => {
    const base = (companies ?? []).map((c: any) => ({ value: String(c.id ?? c.value), label: c.name ?? c.label }))
    const id = form.company_id ? String(form.company_id) : ''
    const name = selectedCompanyDetails?.name
    if (!id || !name) return base
    const i = base.findIndex((o: any) => o.value === id)
    if (i === -1) return [{ value: id, label: name }, ...base]
    if (base[i].label === name) return base
    const copy = [...base]
    copy[i] = { value: id, label: name }
    return copy
  })()

  return (
    <>
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
        <SearchableSelect
          // Remount when the selected company's name changes so a rename saved
          // on the card below is reflected here too — the picker seeds its
          // option list once and would otherwise keep showing the old name.
          key={`company-${form.company_id ?? ''}-${selectedCompanyDetails?.name ?? ''}`}
          label="Company" value={form.company_id ?? ''}
          onChange={v => onCompanySelected(v ? parseInt(v) : null)}
          fetchOptions={async (search) => {
            const resp = await apiClient.get('/lookups/companies', { params: { search } })
            return (resp.data.data ?? []).map((c: any) => ({ value: String(c.id), label: c.name }))
          }}
          options={companyOptions}
          placeholder="Search company..." error={errors.company_id} />
        <button type="button" onClick={() => setShowModal(true)} title="Create new company"
          className="w-9 h-9 flex items-center justify-center rounded-full border border-line text-ink-muted hover:bg-status-info-bg hover:text-primary hover:border-primary transition mt-5">
          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
        </button>
      </div>

      {/* Company Details Card — editable, then confirm */}
      {selectedCompanyDetails && form.company_id && (
        <CompanyDetailsCard
          details={selectedCompanyDetails}
          confirmed={detailsConfirmed}
          onConfirm={() => setDetailsConfirmed(true)}
          onEdit={() => setDetailsConfirmed(false)}
          onSave={async (updated) => {
            // Errors are deliberately NOT caught here — the card keeps the
            // editor open (and the typed values) when the save is rejected.
            await apiClient.put(`/companies/${form.company_id}`, updated)
            await loadCompanyDetails(Number(form.company_id))
            setDetailsConfirmed(true)
            toast.success('Company details updated')
          }}
        />
      )}

      {/* Sub Companies — table + inline add/edit, mirrors V1 SubAdd Livewire */}
      {form.company_id && (
        <SubCompaniesSection parentId={form.company_id} parentName={selectedCompanyDetails?.name} lookups={lookups} />
      )}

      {showModal && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto" onClick={(e) => { if (e.target === e.currentTarget) setShowModal(false) }}>
          <div className="bg-surface rounded-lg shadow-xl w-full max-w-2xl max-h-[85vh] overflow-y-auto p-5 m-4">
            <div className="flex justify-between items-center mb-3 border-b pb-3">
              <h3 className="text-lg font-semibold">Create New Company</h3>
              <button onClick={() => setShowModal(false)} className="text-ink-faint hover:text-ink-muted text-xl">&times;</button>
            </div>

            <div className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <InputField label="Company Name" value={companyForm.name} required onChange={v => { cf('name', v); setCompanyErrors(p => ({...p, name: ''})) }} error={companyErrors.name} placeholder="Max 40 characters" />
                <InputField label="VAT Registration No" value={companyForm.VAT_registration_number} required onChange={v => { cf('VAT_registration_number', v); setCompanyErrors(p => ({...p, VAT_registration_number: ''})) }} error={companyErrors.VAT_registration_number} placeholder="Max 15, alphanumeric" />
                <InputField label="Company Registration No" value={companyForm.company_registration_number} required onChange={v => { cf('company_registration_number', v); setCompanyErrors(p => ({...p, company_registration_number: ''})) }} error={companyErrors.company_registration_number} placeholder="Max 15, alphanumeric" />
                <InputField label="Physical Address" value={companyForm.head_office_physical_address} required onChange={v => cf('head_office_physical_address', v)} error={companyErrors.head_office_physical_address} />
                <InputField label="Postal Address" value={companyForm.postal_address} required onChange={v => cf('postal_address', v)} error={companyErrors.postal_address} />
                <SelectField label="Province" value={companyForm.state} required
                  onChange={v => { onProvinceChange(v); setCompanyErrors(p => ({ ...p, state: '' })) }}
                  options={lookups?.states ?? []}
                  error={companyErrors.state} />
                <SelectField label="City" value={companyForm.city} required
                  onChange={v => { cf('city', v); setCompanyErrors(p => ({ ...p, city: '' })) }}
                  options={companyCities} loading={citiesLoading}
                  error={companyErrors.city} />
                <InputField label="Postal Code" value={companyForm.pincode} onChange={v => cf('pincode', v)} />
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-2">Status</label>
                  <div className="flex gap-1 bg-surface-2 rounded-lg p-0.5">
                    {[{ id: '1', label: 'Active' }, { id: '0', label: 'Inactive' }].map(opt => (
                      <button key={opt.id} type="button"
                        onClick={() => cf('status', opt.id)}
                        className={`flex-1 py-1.5 text-sm font-medium rounded-md transition ${
                          companyForm.status === opt.id
                            ? opt.id === '1' ? 'bg-status-success-fg text-white shadow' : 'bg-status-danger-fg text-white shadow'
                            : 'text-ink-muted hover:text-ink-muted'
                        }`}>
                        {opt.label}
                      </button>
                    ))}
                  </div>
                </div>
              </div>

              <h4 className="font-medium text-ink-muted border-b pb-1">Contact Person</h4>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <InputField label="First Name" value={companyForm.contact_person_first_name} required onChange={v => cf('contact_person_first_name', v)} error={companyErrors.contact_person_first_name} />
                <InputField label="Last Name" value={companyForm.contact_person_last_name} required onChange={v => cf('contact_person_last_name', v)} error={companyErrors.contact_person_last_name} />
                <InputField label="Phone" value={companyForm.contact_person_number} required onChange={v => cf('contact_person_number', v)} error={companyErrors.contact_person_number} placeholder="8 digits starting with 7" />
                <InputField label="Primary Email" value={companyForm.primary_email} required onChange={v => cf('primary_email', v)} type="email" error={companyErrors.primary_email} />
                <InputField label="Secondary Email" value={companyForm.secondary_email} onChange={v => cf('secondary_email', v)} type="email" />
                <InputField label="Broker Email" value={companyForm.broker_email} onChange={v => cf('broker_email', v)} type="email" />
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t">
                <button onClick={() => setShowModal(false)} className="px-4 py-2 text-sm text-ink-muted border rounded-md hover:bg-surface-2">Cancel</button>
                <button onClick={handleCreate} disabled={saving}
                  className="px-4 py-2 text-sm bg-primary text-white rounded-md hover:bg-primary disabled:opacity-50">
                  {saving ? 'Creating...' : 'Create Company'}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </>
  )
}

// ── Editable Company Details Card ──────────────────────────

// One list drives both the read view and the edit grid, so a field can never
// be shown-but-not-editable (or edited but invisible). Keys match what
// LookupController::updateCompany accepts.
const COMPANY_CARD_FIELDS: { key: string; label: string; type?: string }[] = [
  { key: 'name',                         label: 'Company Name' },
  { key: 'VAT_registration_number',      label: 'VAT No' },
  { key: 'company_registration_number',  label: 'Registration No' },
  { key: 'head_office_physical_address', label: 'Physical Address' },
  { key: 'postal_address',               label: 'Postal Address' },
  { key: 'primary_email',                label: 'Email', type: 'email' },
  { key: 'contact_person_number',        label: 'Phone', type: 'tel' },
]

// Mirrors the server rules in updateCompany() so the user sees the problem on
// the field instead of a bare 422 toast. Only the name is mandatory — legacy
// companies routinely have blanks and must stay editable one field at a time.
function validateCompanyCard(f: any): Record<string, string> {
  const e: Record<string, string> = {}
  const v = (k: string) => String(f?.[k] ?? '').trim()
  if (!v('name')) e.name = 'Company name is required'
  else if (v('name').length > 255) e.name = 'Max 255 characters'
  if (v('VAT_registration_number').length > 50) e.VAT_registration_number = 'Max 50 characters'
  if (v('company_registration_number').length > 50) e.company_registration_number = 'Max 50 characters'
  if (v('head_office_physical_address').length > 500) e.head_office_physical_address = 'Max 500 characters'
  if (v('postal_address').length > 500) e.postal_address = 'Max 500 characters'
  if (v('primary_email') && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v('primary_email'))) e.primary_email = 'Invalid email'
  if (v('contact_person_number') && !/^[0-9+][0-9\s()-]{5,19}$/.test(v('contact_person_number'))) e.contact_person_number = '6-20 digits'
  return e
}

function CompanyDetailsCard({ details, confirmed, onConfirm, onEdit, onSave }: {
  details: any; confirmed: boolean; onConfirm: () => void; onEdit: () => void
  onSave: (updated: any) => Promise<void>
}) {
  const { toast } = useToast()
  const [editing, setEditing] = useState(false)
  const [form, setForm] = useState<any>({ ...details })
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [saving, setSaving] = useState(false)
  const u = (k: string, v: string) => {
    setForm((p: any) => ({ ...p, [k]: v }))
    setFieldErrors(p => ({ ...p, [k]: '' }))
  }

  function startEditing() {
    setForm({ ...details })
    setFieldErrors({})
    setEditing(true)
    onEdit()
  }

  async function handleSave() {
    const errs = validateCompanyCard(form)
    setFieldErrors(errs)
    if (Object.keys(errs).length > 0) {
      toast.warning(errs[Object.keys(errs)[0]])
      return
    }
    // Send only the fields this card owns — anything else in `details`
    // (id, status, city/state ids) is not ours to rewrite.
    const payload: Record<string, string> = {}
    COMPANY_CARD_FIELDS.forEach(f => { payload[f.key] = String(form[f.key] ?? '').trim() })

    setSaving(true)
    try {
      await onSave(payload)
      setEditing(false)
    } catch (e: any) {
      // Keep the editor open with the typed values so nothing is lost.
      const data = e?.response?.data
      const fromServer: Record<string, string> = {}
      if (data?.errors) {
        Object.entries(data.errors).forEach(([k, msgs]: any) => { fromServer[k] = Array.isArray(msgs) ? msgs[0] : String(msgs) })
        setFieldErrors(fromServer)
      }
      toast.error(Object.values(fromServer)[0] || data?.message || 'Failed to update company')
    } finally {
      setSaving(false)
    }
  }

  if (editing) {
    return (
      <div className="rounded-lg border border-primary bg-status-info-bg p-4 mt-3 space-y-3">
        <div className="flex items-center justify-between">
          <h4 className="text-sm font-semibold text-primary">Edit Company Details</h4>
          <button type="button" onClick={() => setEditing(false)} className="text-xs text-ink-muted hover:text-ink">Cancel</button>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
          {COMPANY_CARD_FIELDS.map(f => (
            <div key={f.key}>
              <label className="block text-[11px] text-ink-muted mb-0.5">
                {f.label}{f.key === 'name' && <span className="text-status-danger-fg"> *</span>}
              </label>
              <input type={f.type ?? 'text'} value={form[f.key] ?? ''} onChange={e => u(f.key, e.target.value)}
                className={`w-full px-2 py-1.5 text-xs border rounded focus:ring-1 focus:ring-primary ${fieldErrors[f.key] ? 'border-status-danger-fg' : 'border-line'}`} />
              {fieldErrors[f.key] && <p className="text-[10px] text-status-danger-fg mt-0.5">{fieldErrors[f.key]}</p>}
            </div>
          ))}
        </div>
        <div className="flex justify-end gap-2">
          <button type="button" onClick={() => setEditing(false)} disabled={saving}
            className="px-3 py-1.5 text-xs border border-line rounded hover:bg-surface-2 disabled:opacity-50">
            Cancel
          </button>
          <button type="button" onClick={handleSave} disabled={saving}
            className="px-3 py-1.5 text-xs bg-primary text-white rounded hover:opacity-90 disabled:opacity-50">
            {saving ? 'Saving...' : 'Save Company Details'}
          </button>
        </div>
      </div>
    )
  }

  return (
    <div className={`rounded-lg border p-4 mt-3 ${confirmed ? 'bg-status-success-bg border-status-success-fg' : 'bg-status-warning-bg border-status-warning-fg'}`}>
      <div className="flex items-center justify-between mb-2 gap-2 flex-wrap">
        <h4 className="text-sm font-semibold text-ink-muted">Company Details</h4>
        <div className="flex items-center gap-2">
          <button type="button" onClick={startEditing}
            className="inline-flex items-center gap-1 px-3 py-1 text-xs font-medium border border-primary text-primary rounded hover:bg-primary hover:text-white transition">
            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
            Edit Company Details
          </button>
          {!confirmed ? (
            <button type="button" onClick={onConfirm}
              className="px-3 py-1 text-xs bg-status-success-fg text-white rounded-full hover:opacity-90">
              Confirm
            </button>
          ) : (
            <span className="text-xs text-status-success-fg font-medium flex items-center gap-1">
              <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>
              Confirmed
            </span>
          )}
        </div>
      </div>
      {/* Every field is listed even when empty — a blank has to be visible
          before anyone will fill it in. */}
      <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2 text-xs text-ink-muted">
        {COMPANY_CARD_FIELDS.map(f => (
          <div key={f.key}>
            <span className="text-ink-faint">{f.label}:</span>{' '}
            {details?.[f.key] ? details[f.key] : <span className="text-ink-faint italic">—</span>}
          </div>
        ))}
      </div>
    </div>
  )
}

// ── Sub Companies — V1 SubAdd Livewire ported (list + add/edit/delete) ──

const BLANK_SUB = { name: '', VAT_registration_number: '', company_registration_number: '', head_office_physical_address: '', postal_address: '', state: '', city: '', pincode: '', contact_person: '', contact_person_number: '', primary_email: '', secondary_email: '', broker_email: '', status: 1 }

function SubCompaniesSection({ parentId, parentName, lookups }: { parentId: number; parentName?: string; lookups: any }) {
  const { toast } = useToast()
  const confirm = useConfirm()
  const [rows, setRows] = useState<any[]>([])
  const [open, setOpen] = useState(false)
  const [form, setForm] = useState<any>(BLANK_SUB)
  const [editId, setEditId] = useState<number | null>(null)
  const [saving, setSaving] = useState(false)
  const [cities, setCities] = useState<any[]>([])

  const load = async () => {
    const { data } = await apiClient.get('/master/companies', { params: { parent_id: parentId, per_page: 100 } })
    setRows(data.data ?? [])
  }
  useEffect(() => { if (parentId) load() /* eslint-disable-next-line react-hooks/exhaustive-deps */ }, [parentId])

  const u = (k: string, v: any) => setForm((p: any) => ({ ...p, [k]: v }))
  const reset = () => { setForm(BLANK_SUB); setEditId(null); setOpen(false); setCities([]) }

  const onState = async (v: string) => {
    u('state', v); u('city', '')
    if (!v) return setCities([])
    try {
      const { data } = await apiClient.get(`/lookups/states/${v}/cities`)
      setCities(data.data ?? [])
    } catch { setCities([]) }
  }

  const onEdit = async (row: any) => {
    setEditId(row.id); setForm({ ...BLANK_SUB, ...row, status: row.status ? 1 : 0 }); setOpen(true)
    if (row.state) {
      try {
        const { data } = await apiClient.get(`/lookups/states/${row.state}/cities`)
        setCities(data.data ?? [])
      } catch { setCities([]) }
    }
  }

  const onSave = async () => {
    if (!form.name) { toast.warning('Sub Company Name is required'); return }
    setSaving(true)
    try {
      const payload = { ...form, parent_id: parentId }
      if (editId) await apiClient.put(`/master/companies/${editId}`, payload)
      else        await apiClient.post('/master/companies', payload)
      await load(); reset()
    } catch (e: any) {
      toast.error(e.response?.data?.message || 'Failed to save')
    } finally { setSaving(false) }
  }

  const onDelete = async (id: number, name: string) => {
    if (!(await confirm({ message: `Delete sub company "${name}"?`, danger: true, confirmText: 'Delete' }))) return
    try { await apiClient.delete(`/master/companies/${id}`); await load() }
    catch (e: any) { toast.error(e.response?.data?.message || 'Failed to delete') }
  }

  return (
    <div className="rounded-lg border border-status-warning-fg bg-status-warning-bg p-4 mt-3">
      <div className="flex items-center justify-between mb-2">
        <h4 className="text-sm font-semibold text-ink-muted">Sub Companies{parentName ? ` for ${parentName}` : ''}</h4>
        {!open && (
          <button type="button" onClick={() => { reset(); setOpen(true) }}
            className="px-3 py-1 text-xs bg-primary text-white rounded hover:bg-primary">+ Add Sub Company</button>
        )}
      </div>

      {rows.length > 0 ? (
        <div className="overflow-x-auto">
          <table className="w-full text-xs">
            <thead className="text-ink-muted">
              <tr className="border-b">
                <th className="text-left py-1 px-2">#</th>
                <th className="text-left py-1 px-2">Name</th>
                <th className="text-left py-1 px-2">VAT</th>
                <th className="text-left py-1 px-2">Reg</th>
                <th className="text-left py-1 px-2">Status</th>
                <th className="text-right py-1 px-2">Action</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r, i) => (
                <tr key={r.id} className="border-b border-status-warning-fg">
                  <td className="py-1 px-2">{i + 1}</td>
                  <td className="py-1 px-2">{r.name}</td>
                  <td className="py-1 px-2">{r.VAT_registration_number || '—'}</td>
                  <td className="py-1 px-2">{r.company_registration_number || '—'}</td>
                  <td className="py-1 px-2">
                    <span className={r.status ? 'text-status-success-fg' : 'text-status-danger-fg'}>{r.status ? 'Active' : 'Inactive'}</span>
                  </td>
                  <td className="py-1 px-2 text-right">
                    <button onClick={() => onEdit(r)} className="text-primary hover:underline mr-3">Edit</button>
                    <button onClick={() => onDelete(r.id, r.name)} className="text-status-danger-fg hover:underline">Delete</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <p className="text-xs text-ink-muted italic">Sub Companies Details Not Present.</p>
      )}

      {open && (
        <div className="mt-3 pt-3 border-t border-status-warning-fg">
          <div className="flex items-center justify-between mb-2">
            <h5 className="text-sm font-semibold text-primary">{editId ? 'Edit' : 'Add'} Sub Company</h5>
            <button type="button" onClick={reset} className="text-xs text-ink-muted hover:text-ink-muted">Cancel</button>
          </div>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-2">
            {[
              ['name', 'Sub Company Name', true],
              ['VAT_registration_number', 'VAT Reg No', true],
              ['company_registration_number', 'Company Reg No', true],
              ['head_office_physical_address', 'Head Office Address', true],
              ['postal_address', 'Postal Address', true],
            ].map(([k, l, req]) => (
              <div key={k as string}>
                <label className="block text-[10px] text-ink-muted mb-0.5">{l}{req ? ' *' : ''}</label>
                <input value={form[k as string] || ''} onChange={e => u(k as string, e.target.value)}
                  className="w-full px-2 py-1 text-xs border rounded focus:ring-1 focus:ring-primary" />
              </div>
            ))}
            <div>
              <label className="block text-[10px] text-ink-muted mb-0.5">Province *</label>
              <select value={form.state || ''} onChange={e => onState(e.target.value)}
                className="w-full px-2 py-1 text-xs border rounded focus:ring-1 focus:ring-primary">
                <option value="">- Select -</option>
                {(lookups?.states ?? []).map((s: any) => (
                  <option key={s.id ?? s.value} value={s.id ?? s.value}>{s.name ?? s.label}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-[10px] text-ink-muted mb-0.5">City *</label>
              <select value={form.city || ''} onChange={e => u('city', e.target.value)}
                className="w-full px-2 py-1 text-xs border rounded focus:ring-1 focus:ring-primary">
                <option value="">- Select -</option>
                {cities.map((c: any) => (
                  <option key={c.id ?? c.value} value={c.id ?? c.value}>{c.name ?? c.label}</option>
                ))}
              </select>
            </div>
            {[
              ['pincode', 'Pincode', true],
              ['contact_person', 'Contact Person', true],
              ['contact_person_number', 'Contact Number', true],
              ['primary_email', 'Primary Email', true],
              ['secondary_email', 'Secondary Email', true],
              ['broker_email', 'Brokers Email', true],
            ].map(([k, l, req]) => (
              <div key={k as string}>
                <label className="block text-[10px] text-ink-muted mb-0.5">{l}{req ? ' *' : ''}</label>
                <input value={form[k as string] || ''} onChange={e => u(k as string, e.target.value)}
                  className="w-full px-2 py-1 text-xs border rounded focus:ring-1 focus:ring-primary" />
              </div>
            ))}
            <div>
              <label className="block text-[10px] text-ink-muted mb-0.5">Status</label>
              <div className="flex gap-1 bg-surface-2 rounded p-0.5">
                {[{ v: 1, l: 'Active' }, { v: 0, l: 'Inactive' }].map(o => (
                  <button key={o.v} type="button" onClick={() => u('status', o.v)}
                    className={`flex-1 py-1 text-xs font-medium rounded ${
                      form.status === o.v
                        ? o.v === 1 ? 'bg-status-success-fg text-white' : 'bg-status-danger-fg text-white'
                        : 'text-ink-muted'
                    }`}>{o.l}</button>
                ))}
              </div>
            </div>
          </div>
          <div className="flex justify-end mt-3">
            <button type="button" onClick={onSave} disabled={saving}
              className="px-4 py-1.5 text-xs bg-primary text-white rounded hover:bg-primary disabled:opacity-50">
              {saving ? 'Saving...' : editId ? 'Update' : 'Save'}
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
