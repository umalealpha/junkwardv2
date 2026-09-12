import { fmtPula } from '../../../utils/format'

/**
 * Calculate expiry date from start date + premium frequency.
 */
export function calcExpiry(startDate: string, freq: string): string {
  if (!startDate) return ''
  const d = new Date(startDate)
  switch (freq) {
    case '1': // Monthly
      d.setMonth(d.getMonth() + 1)
      d.setDate(d.getDate() - 1)
      break
    case '3': // Annual
      d.setFullYear(d.getFullYear() + 1)
      d.setDate(d.getDate() - 1)
      break
    case '5': // Quarterly
      d.setMonth(d.getMonth() + 3)
      d.setDate(d.getDate() - 1)
      break
    case '6': // Manual
      return ''
    default:
      return ''
  }
  return d.toISOString().split('T')[0]
}

/**
 * Validate email format.
 */
export function isValidEmail(email: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
}

/**
 * Validate Botswana Omang (9 digits).
 */
export function isValidOmang(omang: string): boolean {
  return /^\d{9,10}$/.test(omang)
}

/**
 * Validate Botswana cellphone (starts with 7, 8 digits).
 */
export function isValidPhone(phone: string): boolean {
  return /^7\d{7}$/.test(phone)
}

/**
 * Format currency (BWP).
 *
 * Negative values render as "(Credit P 288.04)" rather than "P -288.04"
 * — UAT 2026-05-26 finding (Arjun M6, Prathap BUG-005). Finance prefers
 * accounting-style credit notation and the literal minus sign was being
 * misread as a data quality bug rather than an intentional credit.
 */
export function formatCurrency(amount: number | string): string {
  return fmtPula(amount)
}

/**
 * Check if product is COMG (Commercial).
 */
export function isComgProduct(productId: number | null): boolean {
  return productId !== null && [7, 16, 17].includes(productId)
}

/**
 * Check if product is specifically "Commercial Insurance" (ids 7 & 17 —
 * the products table has a duplicate row). Narrower than isComgProduct,
 * which also covers Engineering (16). Used to relax the Omang/Passport
 * requirement for Individual policy holders on Commercial Insurance only.
 */
export function isCommercialInsuranceProduct(productId: number | null): boolean {
  return productId !== null && [7, 17].includes(productId)
}

/**
 * Check if product is DOMG (Domestic).
 */
export function isDomgProduct(productId: number | null): boolean {
  return productId !== null && [8, 18, 19].includes(productId)
}

/**
 * Check if product is DOM/COM (has risk addresses).
 */
export function isDomComProduct(productId: number | null): boolean {
  return isComgProduct(productId) || isDomgProduct(productId)
}

/**
 * Specialist single-site products — Engineering (16/17), Specialist (18),
 * Commercial Liabilities (20), Marine (22), Guarantee (23), Miscellaneous (24).
 * Same grouping used for the one-coverage-per-risk-address rule and the
 * Policy Detail page's specialist-coverage tab.
 */
export function isSpecialistProduct(productId: number | null): boolean {
  return productId !== null && [16, 17, 18, 20, 22, 23, 24].includes(productId)
}

/**
 * ANNUAL premium frequency id (policies.premium_freq). Mirrors the
 * `premium_frequencies` lookup in LookupController::policyCreateData.
 */
export const ANNUAL_FREQ = '3'

/**
 * Annual-term-only products — Commercial Liabilities (20), Guarantee (23),
 * Miscellaneous (24). Per UW all three are COMG/company lines with an
 * Annual term only, so no Monthly/Quarterly/Manual frequency.
 *
 * Narrower than isSpecialistProduct, which also covers Engineering
 * (16/17), Specialist (18) and Marine (22) — those still allow
 * non-annual terms.
 *
 * Mirrors backend Support\CompanyOnlyProducts::includes().
 */
export function isCompanyOnlyProduct(productId: number | null): boolean {
  return productId !== null && [20, 23, 24].includes(productId)
}

/**
 * Organisation-only products — Guarantee (23) and Miscellaneous (24).
 * On these the holder is always an Organisation (no Individual option) and
 * no individual customer details are captured — the operator picks the
 * Organisation/Company and nothing else.
 *
 * Commercial Liabilities (20) is deliberately EXCLUDED: per UW
 * (2026-08-26) it may be issued to an Individual OR an Organisation, so
 * both Entity Type options are offered. Its Annual-only term still comes
 * from isCompanyOnlyProduct above.
 *
 * Mirrors backend Support\CompanyOnlyProducts::entityLocked().
 */
export function isOrganisationOnlyProduct(productId: number | null): boolean {
  return productId !== null && [23, 24].includes(productId)
}
