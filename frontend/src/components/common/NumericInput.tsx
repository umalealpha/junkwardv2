import type { InputHTMLAttributes } from 'react'
import { formatNumberInput, unformatNumber, toWordsShort } from '../../utils/format'

type Props = Omit<InputHTMLAttributes<HTMLInputElement>, 'value' | 'onChange' | 'type'> & {
  value: string | number | null | undefined
  onChange: (raw: string) => void
  /** Hide the "≈ 1 hundred thousand" magnitude hint below the field. */
  hideHint?: boolean
}

/**
 * Drop-in replacement for <input type="number"> that:
 *  - displays the value with thousand-separators as the user types
 *    (renders as type="text" + inputMode="decimal" so the commas stay visible)
 *  - emits the raw (unformatted) string back through onChange
 *  - renders a short magnitude hint below the field (≈ 5 million / ≈ 1 hundred thousand)
 *
 * The hint uses the African / European "hundred + unit" banding
 * (500,000 = "5 hundred thousand", not "500 thousand"). See
 * utils/format.ts::toWordsShort.
 */
export default function NumericInput({ value, onChange, hideHint, className, ...rest }: Props) {
  const display = formatNumberInput(value ?? '')
  const hint = hideHint ? '' : toWordsShort(value)
  return (
    <div>
      <input
        {...rest}
        type="text"
        inputMode="decimal"
        value={display}
        onChange={e => onChange(unformatNumber(e.target.value))}
        className={className}
      />
      {hint && <p className="text-xs text-gray-500 mt-0.5">{hint}</p>}
    </div>
  )
}
