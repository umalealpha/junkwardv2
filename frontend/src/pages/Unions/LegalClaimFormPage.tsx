import LegalClaimFormBody from './LegalClaimFormBody'

// Generic fallback legal claim form — used for any union without a dedicated
// page. Title derives from the union name (e.g. "{Union} Legal Claim Form").
export default function LegalClaimFormPage() {
  return <LegalClaimFormBody />
}
