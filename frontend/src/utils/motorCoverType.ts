/**
 * Canonical `motor.type_of_cover` tokens.
 *
 * The quotation (v2-quote-sheet blade), the policy schedule and the coverage
 * export all match this column on exact strings. The vehicle grid used to
 * submit the human label ("Third party only"), which stored a value no
 * consumer recognised — the quotation then printed a blank "Type of Cover"
 * cell and treated the vehicle as comprehensive. Keep these values in step
 * with backend `AlphaDirect\Support\MotorCoverType`.
 */
// All FOUR canonical tokens, matching MotorCoverType::LABELS. Third_fire was
// missing here while CANONICAL_LABELS below and the backend both carried it, so
// normalizeMotorCoverType('Third_fire') returned a truthy value with no matching
// <option>: the grid suppressed its '-Select-' placeholder and the browser fell
// back to showing the FIRST option, so a "Third party and fire" vehicle read as
// Comprehensive on screen and saving the row wrote that wider cover back.
export const MOTOR_COVER_TYPES = [
  { value: 'Comprehensive', label: 'Comprehensive' },
  { value: 'third_party_only', label: 'Third party only' },
  { value: 'Third_fire_and_theft', label: 'Third party, fire and theft' },
  { value: 'Third_fire', label: 'Third party and fire' },
] as const

const CANONICAL_LABELS: Record<string, string> = {
  Comprehensive: 'Comprehensive',
  third_party_only: 'Third party only',
  Third_fire_and_theft: 'Third party, fire and theft',
  Third_fire: 'Third party and fire',
}

/** Alias key (lowercase alphanumerics only) => canonical token. */
const ALIASES: Record<string, string> = {
  comprehensive: 'Comprehensive',
  thirdpartyonly: 'third_party_only',
  tponly: 'third_party_only',
  thirdpartyfireandtheft: 'Third_fire_and_theft',
  thirdfireandtheft: 'Third_fire_and_theft',
  thirdpartyfiretheft: 'Third_fire_and_theft',
  thirdpartyandfire: 'Third_fire',
  thirdfire: 'Third_fire',
}

/**
 * Fold a stored/entered value onto its canonical token so rows written before
 * this fix (which hold the label) still select the right option. Unknown
 * values pass through unchanged.
 */
export function normalizeMotorCoverType(value?: string | null): string {
  if (!value) return ''
  const key = value.toLowerCase().replace(/[^a-z0-9]/g, '')
  return ALIASES[key] ?? value
}

/** Display label for a stored value ('' when empty). */
export function motorCoverTypeLabel(value?: string | null): string {
  const canonical = normalizeMotorCoverType(value)
  if (!canonical) return ''
  return CANONICAL_LABELS[canonical] ?? canonical
}
