import apiClient from './client'

/**
 * Claim-form send button — the handler picks the form and the address, and the
 * claimant is emailed a pre-filled PDF plus a no-password link to complete it
 * online and upload documents.
 *
 * Both endpoints 404 while the `claims_form_dispatch` runtime flag is off, so
 * callers must treat 404 as "feature not switched on", not as an error worth
 * showing the user.
 */

export interface ClaimFormOption {
  id: number
  claim_type: string
  form_title: string
  template_key: string | null
}

export interface ClaimFormOptions {
  claimNumber: string | null
  claimType: string | null
  /** The form we think fits — a SUGGESTION only, always overridable. */
  suggestedFormId: number | null
  /**
   * false when the claim type does not reliably tell us which form to send.
   * `Accident` is the reason this field exists: it is 41% of the claims book
   * and the motor/non-motor signals in the data contradict each other, so the
   * UI must prompt the handler to look rather than click straight through.
   */
  suggestionIsCertain: boolean
  defaultEmail: string | null
  forms: ClaimFormOption[]
  /** How many times a form has already gone out on this claim. */
  alreadySent: number
}

export interface SendClaimFormResult {
  formTitle: string
  sentPdf: boolean
  sentLink: boolean
  linkUrl: string | null
}

export async function getClaimFormOptions(claimId: number): Promise<ClaimFormOptions> {
  const { data } = await apiClient.get(`/claims-v2/${claimId}/claim-form-options`)
  return data.data
}

export async function sendClaimForm(
  claimId: number,
  payload: {
    form_id: number
    to_email: string
    include_pdf: boolean
    include_link: boolean
  },
): Promise<SendClaimFormResult> {
  const { data } = await apiClient.post(`/claims-v2/${claimId}/send-claim-form`, payload)
  return data.data
}
