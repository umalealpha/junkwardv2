import { Children, cloneElement, isValidElement, useId, type ReactNode } from 'react'

interface FormFieldProps {
  label: string
  error?: string
  required?: boolean
  children: ReactNode
  className?: string
}

/**
 * FormField — label + control + error wrapper.
 *
 * The label is associated with its control via useId() (htmlFor/id) so
 * clicking the label focuses the field. When there is exactly one element
 * child (the common case) and it doesn't already carry an id, we inject the
 * generated id onto it. The error message links to the field via
 * aria-describedby, and error state is conveyed by both colour AND text.
 */
export default function FormField({ label, error, required, children, className = '' }: FormFieldProps) {
  const id = useId()
  const errorId = `${id}-error`

  // Associate the label with a single element child (unless it has its own id).
  const only = Children.count(children) === 1 ? Children.only(children) : null
  const control = only && isValidElement(only)
    ? cloneElement(only as any, {
        id: (only.props as any).id ?? id,
        'aria-invalid': error ? true : (only.props as any)['aria-invalid'],
        'aria-describedby': error
          ? [(only.props as any)['aria-describedby'], errorId].filter(Boolean).join(' ')
          : (only.props as any)['aria-describedby'],
      })
    : children

  const htmlFor = only && isValidElement(only) ? ((only.props as any).id ?? id) : undefined

  return (
    <div className={className}>
      <label htmlFor={htmlFor} className="block text-sm font-medium text-ink-muted mb-1">
        {label}
        {required && <span className="text-red-500 ml-0.5">*</span>}
      </label>
      {control}
      {error && <p id={errorId} className="mt-1 text-sm text-red-600">{error}</p>}
    </div>
  )
}
