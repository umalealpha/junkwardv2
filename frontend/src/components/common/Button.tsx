import { forwardRef, type ButtonHTMLAttributes, type ReactNode } from 'react'

/**
 * Button — the "Elevated" theme's canonical action control.
 *
 * Variants: primary (brand navy → lightens in dark), secondary (surface +
 * border), danger (status tokens), ghost (transparent). Sizes sm / md.
 * `loading` shows a spinner and disables the button. Inherits the global
 * focus-visible ring (see src/index.css) — no per-button ring needed.
 */

type Variant = 'primary' | 'secondary' | 'danger' | 'ghost'
type Size = 'sm' | 'md'

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant
  size?: Size
  loading?: boolean
  children: ReactNode
}

const VARIANT: Record<Variant, string> = {
  primary: 'bg-primary text-primary-contrast hover:opacity-90',
  secondary: 'bg-surface border border-line text-ink hover:bg-surface-2',
  danger: 'bg-status-danger-fg text-white hover:opacity-90',
  ghost: 'bg-transparent text-ink hover:bg-surface-2',
}

const SIZE: Record<Size, string> = {
  sm: 'px-3 py-1.5 text-xs',
  md: 'px-4 py-2 text-sm',
}

const Button = forwardRef<HTMLButtonElement, ButtonProps>(function Button(
  { variant = 'primary', size = 'md', loading = false, disabled, className = '', children, ...rest },
  ref,
) {
  const isDisabled = disabled || loading
  return (
    <button
      ref={ref}
      disabled={isDisabled}
      aria-busy={loading || undefined}
      className={`inline-flex items-center justify-center gap-2 font-medium rounded-md transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed ${VARIANT[variant]} ${SIZE[size]} ${className}`}
      {...rest}
    >
      {loading && (
        <svg className="w-4 h-4 animate-spin shrink-0" fill="none" viewBox="0 0 24 24" aria-hidden="true">
          <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
          <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
      )}
      {children}
    </button>
  )
})

export default Button
