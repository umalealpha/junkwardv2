import type { ReactNode } from 'react'
import Select from 'react-select'
import { formatNumberInput, unformatNumber, toWordsShort } from '../../../utils/format'

interface InputProps {
  label: string
  value: string | number
  onChange: (v: string) => void
  onBlur?: () => void
  error?: string
  type?: string
  placeholder?: string
  disabled?: boolean
  required?: boolean
  min?: string
  max?: string
}

export function InputField({ label, value, onChange, onBlur, error, type = 'text', placeholder, disabled, required, min, max }: InputProps) {
  const isNumeric = type === 'number'
  // Numeric inputs render as text so thousand-separators stay visible while
  // typing; we keep inputMode='decimal' so mobile keyboards still show digits.
  const displayValue = isNumeric ? formatNumberInput(value ?? '') : (value ?? '')
  const hint = isNumeric ? toWordsShort(value) : ''
  return (
    <div>
      <label className="block text-xs font-medium text-ink-muted mb-1">{label}{required && <span className="text-status-danger-fg ml-0.5">*</span>}</label>
      <input
        type={isNumeric ? 'text' : type}
        inputMode={isNumeric ? 'decimal' : undefined}
        value={displayValue}
        onChange={e => onChange(isNumeric ? unformatNumber(e.target.value) : e.target.value)}
        onBlur={onBlur}
        placeholder={placeholder} disabled={disabled} min={min} max={max}
        aria-invalid={!!error}
        className={`w-full px-3 py-2 text-sm border rounded-md focus:ring-2 focus:ring-brand-navy focus:border-transparent transition ${error ? 'border-status-danger-fg bg-status-danger-bg' : 'border-line'} ${disabled ? 'bg-surface-2' : ''}`} />
      {hint && !error && <p className="text-xs text-ink-muted mt-0.5">{hint}</p>}
      {error && <p className="text-xs text-status-danger-fg mt-0.5 animate-pulse">{error}</p>}
    </div>
  )
}

interface SelectProps {
  label: string
  value: string | number | null
  onChange: (v: string) => void
  options: { id: string | number; name: string }[]
  error?: string
  placeholder?: string
  disabled?: boolean
  required?: boolean
  loading?: boolean
}

export function SelectField({ label, value, onChange, options, error, placeholder = 'Select...', disabled, required, loading }: SelectProps) {
  const selectOptions = options.map(o => ({ value: String(o.id), label: o.name }))
  const selectedOption = selectOptions.find(o => o.value === String(value)) || null

  return (
    <div>
      <label className="block text-xs font-medium text-ink-muted mb-1">{label}{required && <span className="text-status-danger-fg ml-0.5">*</span>}</label>
      <Select
        value={selectedOption}
        onChange={opt => onChange(opt?.value ?? '')}
        options={selectOptions}
        isDisabled={disabled}
        isLoading={loading}
        isClearable
        isSearchable
        placeholder={loading ? 'Loading...' : placeholder}
        menuPortalTarget={typeof document !== 'undefined' ? document.body : undefined}
        styles={{
          control: (base, state) => ({
            ...base,
            minHeight: '38px',
            fontSize: '0.875rem',
            borderColor: error ? 'rgb(var(--danger-fg))' : state.isFocused ? 'rgb(var(--primary))' : 'rgb(var(--border))',
            backgroundColor: error ? 'rgb(var(--danger-bg))' : disabled ? 'rgb(var(--surface-2))' : 'rgb(var(--surface))',
            boxShadow: state.isFocused ? '0 0 0 2px rgb(var(--primary) / 0.25)' : 'none',
            '&:hover': { borderColor: state.isFocused ? 'rgb(var(--primary))' : 'rgb(var(--text-faint))' },
          }),
          menu: base => ({ ...base, fontSize: '0.875rem', zIndex: 50 }),
          menuPortal: base => ({ ...base, zIndex: 9999 }),
          option: (base, state) => ({
            ...base,
            backgroundColor: state.isSelected ? 'rgb(var(--primary))' : state.isFocused ? 'rgb(var(--surface-2))' : 'rgb(var(--surface))',
            color: state.isSelected ? 'rgb(var(--primary-contrast))' : 'rgb(var(--text))',
            fontSize: '0.875rem',
          }),
        }}
      />
      {error && <p className="text-xs text-status-danger-fg mt-0.5">{error}</p>}
    </div>
  )
}

interface CheckboxProps {
  label: string
  checked: boolean
  onChange: (v: boolean) => void
  disabled?: boolean
}

export function CheckboxField({ label, checked, onChange, disabled }: CheckboxProps) {
  return (
    <label className="flex items-center gap-2 cursor-pointer">
      <input type="checkbox" checked={checked} onChange={e => onChange(e.target.checked)} disabled={disabled}
        className="w-4 h-4 text-brand-navy border-line rounded focus:ring-brand-navy" />
      <span className="text-sm text-ink-muted">{label}</span>
    </label>
  )
}

interface RadioGroupProps {
  label: string
  value: string
  onChange: (v: string) => void
  options: { id: string; name: string }[]
  error?: string
  inline?: boolean
}

export function RadioGroup({ label, value, onChange, options, error, inline = true }: RadioGroupProps) {
  return (
    <div>
      <label className="block text-xs font-medium text-ink-muted mb-1">{label}</label>
      <div className={`flex ${inline ? 'gap-4' : 'flex-col gap-1'}`}>
        {options.map(o => (
          <label key={o.id} className="flex items-center gap-1.5 cursor-pointer">
            <input type="radio" value={o.id} checked={value === o.id} onChange={() => onChange(o.id)}
              className="w-4 h-4 text-brand-navy border-line focus:ring-brand-navy" />
            <span className="text-sm text-ink-muted">{o.name}</span>
          </label>
        ))}
      </div>
      {error && <p className="text-xs text-status-danger-fg mt-0.5">{error}</p>}
    </div>
  )
}

interface TextAreaProps {
  label: string
  value: string
  onChange: (v: string) => void
  rows?: number
  placeholder?: string
  error?: string
}

export function TextAreaField({ label, value, onChange, rows = 3, placeholder, error }: TextAreaProps) {
  return (
    <div>
      <label className="block text-xs font-medium text-ink-muted mb-1">{label}</label>
      <textarea value={value} onChange={e => onChange(e.target.value)} rows={rows} placeholder={placeholder}
        aria-invalid={!!error}
        className={`w-full px-3 py-2 text-sm border rounded-md focus:ring-2 focus:ring-brand-navy focus:border-transparent ${error ? 'border-status-danger-fg bg-status-danger-bg' : 'border-line'}`} />
      {error && <p className="text-xs text-status-danger-fg mt-0.5">{error}</p>}
    </div>
  )
}

interface FileUploadProps {
  label: string
  onChange: (f: File | null) => void
  accept?: string
  file?: File | null
}

export function FileUpload({ label, onChange, accept = 'image/*', file }: FileUploadProps) {
  return (
    <div>
      <label className="block text-xs font-medium text-ink-muted mb-1">{label}</label>
      <input type="file" accept={accept} onChange={e => onChange(e.target.files?.[0] ?? null)}
        className="w-full text-sm text-ink-muted file:mr-4 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-brand-navy/10 file:text-brand-navy hover:file:bg-brand-navy/20" />
      {file && <p className="text-xs text-status-success-fg mt-0.5">{file.name}</p>}
    </div>
  )
}

interface SectionProps {
  title: string
  children: ReactNode
  action?: ReactNode
}

export function Section({ title, children, action }: SectionProps) {
  return (
    <div className="bg-surface rounded-lg shadow-sm border p-6 space-y-4">
      <div className="flex items-center justify-between border-b pb-2">
        <h3 className="text-lg font-semibold text-ink">{title}</h3>
        {action}
      </div>
      {children}
    </div>
  )
}

export function TestDataButton({ onClick }: { onClick: () => void }) {
  // Hide in production builds — this is a dev / QA convenience only.
  // Renders null on production so every callsite auto-hides without
  // having to add the check at each one.
  if (import.meta.env.MODE === 'production') {
    return null
  }
  return (
    <button onClick={onClick} type="button"
      className="px-3 py-1 text-xs text-status-warning-fg border border-status-warning-fg rounded hover:bg-status-warning-bg">
      Fill Test Data
    </button>
  )
}
