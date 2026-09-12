# Claims Document Generator — `claims-docs/` (rides alongside `brain/`)

Static, self-contained generator for the Claims Department's 7 standard correspondence
forms (Agreement of Loss, Cash in Lieu, Ex Gratia, Form of Release, Refund Memo, Towing
Authorisation, Claim Flagging Form). Requested by Claims (Kelebogile / Wangu) to sit
under **Claims** in the Operations Portal, like the Underwriting Document Generator.

## What it is / why it can't affect Graphite or the brain
- **One file:** `claims-docs/index.html` — self-contained static HTML (logo embedded as
  a data URI, no external assets, no build step, no server, no database).
- **Adds only new files** under `claims-docs/`. Zero existing files changed. It cannot
  affect Graphite, the backend, or `brain/`.
- The user fills the fields in the browser and prints -> PDF. Every form is verified to
  print as **A4, one page**.
- Deep-link a specific form: `index.html?type=aol|cil|exg|release|refund|towing|flag`.

## Where it goes in the UI (Pramod)
Surface it as a new item under **Claims** in the Operations Portal nav (e.g. "Claim
Forms"), below "All Claims" / "Create Claim". Serve it as a static route or in an
iframe/webview — it needs nothing from the backend.

## Wording status (legal documents — please note)
- **Agreement of Loss** and **Cash in Lieu** are faithful to the Claims Word templates.
- The other 5 use standard wording and are flagged in-page "confirm against the Claims
  Word file" — Claims to verify exact wording before any document is issued to a client.

Deploy alongside the brain in the same push. Nothing here depends on the brain, and the
brain does not depend on this.
