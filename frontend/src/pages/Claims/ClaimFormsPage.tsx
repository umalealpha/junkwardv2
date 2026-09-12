// Claim Forms — the Claims Document Generator (the 7 standard claims
// correspondence forms: Agreement of Loss, Cash in Lieu, Ex Gratia, Form of
// Release, Refund Memo, Towing Authorisation, Claim Flagging). The generator is
// a self-contained static microsite served from /claims-forms/index.html
// (frontend/public) and embedded here in an iframe — it needs nothing from the
// backend and stores no data (fill in the browser → print to PDF, A4/1 page).
// Access is gated by the `claims_docs` integration flag + Claims roles in the
// sidebar; the page itself is inert client-side.
export default function ClaimFormsPage() {
  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="font-heading text-2xl font-bold text-ink">Claim Forms</h1>
          <p className="mt-1 text-sm text-gray-500">
            Generate the standard claims correspondence forms, then print to PDF (A4, one page).
          </p>
        </div>
        <a
          href="/claims-forms/index.html"
          target="_blank"
          rel="noopener noreferrer"
          className="text-sm font-medium text-primary hover:underline"
        >
          Open in new tab ↗
        </a>
      </div>

      <p className="rounded-md border border-line bg-status-warning-bg px-3 py-2 text-xs text-status-warning-fg">
        Agreement of Loss and Cash in Lieu match the Claims Word templates. The other five use
        standard wording — confirm against the Claims Word file before issuing any document to a client.
      </p>

      <div className="overflow-hidden rounded-lg border border-line bg-white">
        <iframe
          title="Claims Document Generator"
          src="/claims-forms/index.html"
          className="h-[80vh] w-full border-0"
        />
      </div>
    </div>
  )
}
