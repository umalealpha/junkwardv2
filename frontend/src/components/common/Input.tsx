import { forwardRef, useId, type InputHTMLAttributes } from 'react'

/**
 * Input — tokenized text field for the "Elevated" theme. The label is
 * associated with the control via useId() (htmlFor/id) so clicking the label
 * focuses the field. Error state pairs a red border WITH visible error text
 * (colour is never the sole signal — WCAG 1.4.1), and links the message to
 * the field via aria-describedby + aria-invalid.
 */

interface InputProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'id'> {
  label?: string
  error?: string
  required?: boolean
  /** Wrapper class (label + input + error group). */
  wrapperClassName?: string
}

const Input = forwardRef<HTMLInputElement, InputProps>(function Input(
  { label, error, required, wrapperClassName = '', className = '', ...rest },
  ref,
) {
  const id = useId()
  const errorId = `${id}-error`
  return (
    <div className={wrapperClassName}>
      {label && (
        <label htmlFor={id} className="block text-sm font-medium text-ink-muted mb-1">
          {label}
          {required && <span className="text-red-500 ml-0.5">*</span>}
        </label>
      )}
      <input
        ref={ref}
        id={id}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? errorId : undefined}
        className={`w-full px-3 py-2 text-sm rounded-md bg-surface text-ink placeholder-ink-faint border transition focus:outline-none ${
          error ? 'border-status-danger-fg' : 'border-line'
        } ${className}`}
        {...rest}
      />
      {error && (
        <p id={errorId} className="mt-1 text-sm text-red-600">{error}</p>
      )}
    </div>
  )
})

export default Input
