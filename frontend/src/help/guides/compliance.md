---
lastReviewed: 2026-06-03
---
## Overview

The Compliance module tracks two critical regulatory requirements: customer complaints for TCF (Treating Customers Fairly) compliance, and opt-in consent records for ECTA/DPA/NBFIRA data protection laws. Use this module to log customer grievances, review consent journeys (OTP verification to acceptance), and monitor coverage gaps.

## Who uses this

- **Complaints Register**: Customer service, operations, compliance officers (anyone who receives a customer complaint must log it here).
- **Consent Compliance**: Compliance team, data protection officer, operations managers monitoring customer consent capture flows and revocation trends.

## Step-by-step

### Logging a Complaint

1. Open **Compliance** → **Complaints Register** from the sidebar.
2. Click **+ Log Complaint** (top right).
3. Fill the modal form:
   - **Policy ID** (optional): Link to the affected policy.
   - **Claim ID** (optional): Link to a claim if the complaint arose from claims handling.
   - **Complaint Of** (required): Brief category or subject (e.g., "Claim denial", "Premium increase", "Service delay").
   - **Details** (required): Full description of the complaint.
4. Click **Save**. The complaint is recorded with the logged-in user's name and current timestamp.
5. Scroll the complaint register table to find the newly logged entry.

### Reviewing Complaints

1. Open **Compliance** → **Complaints Register**.
2. View all logged complaints in the table (sorted by date, newest first).
3. Table columns show complaint ID, linked policy, category, details (truncated), who logged it, and the date.
4. Use **Previous** / **Next** buttons to page through records (25 per page).

### Monitoring Consent Compliance

1. Open **Compliance** (or **Admin** → **Consent Compliance**) from the sidebar. 
2. Review the **KPI tiles** at the top:
   - **Consents this week** / **Consents this month**: Volume of consent captures.
   - **Coverage %**: Percentage of linked policies that have active consents (target ≥ 95%).
   - **Avg verify → accept (s)**: Average seconds between OTP verification and final acceptance (watch for <5s, which flags possible bot activity).
   - **Revoked this month** / **Revocation rate %**: Track consent withdrawals and churn.
   - **WhatsApp / SMS / Voice**: Breakdown of OTP delivery channels used.
3. Use the **Filters** row to narrow results:
   - **Product scope**: Retail, Domestic/Commercial, Engineering, or Specialist.
   - **Status**: Active or Revoked.
   - **From** / **To**: Date range (consent acceptance date).
   - **Search**: Find by masked cellphone, customer ID, or evidence hash.
4. Review the **Consents table** to inspect individual records:
   - **ID**: Consent record number.
   - **Mobile**: Masked phone number.
   - **Scope**: Product segment.
   - **Policy**: Linked policy ID (if any).
   - **Accepted**: Date and time consent was accepted.
   - **Channel**: OTP method (WhatsApp, SMS, Voice, Email).
   - **Verify→Accept (s)**: Time delta in seconds.
   - **Status**: Active (green) or Revoked (red).
   - **Evidence**: Truncated hash of the consent evidence (hover to see full).
5. Click **Previous** / **Next** to paginate (25 records per page).

## Field reference

| Field | Meaning | Required | Notes |
|-------|---------|----------|-------|
| **Complaints Page** | | | |
| Policy ID | Link to affected policy number | No | Numeric; optional if complaint is general |
| Claim ID | Link to specific claim | No | Numeric; used for claims-related complaints |
| Complaint Of | Category or brief subject | Yes | Free text; e.g., "Premium error", "Claim dispute" |
| Details | Full complaint description | Yes | Free text; accepts up to ~2000 chars in textarea |
| **Consents Page** | | | |
| Product scope | Retail, Domcom, Engineering, or Specialist | No | Filter only; set at policy level |
| Status | Active or Revoked | No | Filter only; revoked = customer withdrew consent |
| From / To | Date range for acceptance | No | ISO format (YYYY-MM-DD); applied to accepted_at |
| Search | Cellphone, customer ID, or hash | No | Partial matching; masks full phone for privacy |
| Mobile (table) | Customer phone number | Auto | Masked for display (e.g., "27712***456") |
| Accepted (table) | When consent was accepted | Auto | Datetime; captured at OTP confirmation |
| Channel (table) | OTP delivery method | Auto | WhatsApp, SMS, Voice, or Email |
| Verify→Accept (s) | Seconds from OTP send to acceptance | Auto | Numeric; <5s may indicate bot or local-network abuse |
| Evidence (table) | Hash of consent payload | Auto | Truncated to 12 chars; click/hover for full hash |

## Tips & gotchas

### Complaints Register

- **Validation**: The Save button is disabled if "Complaint Of" is empty. Ensure the category is filled before clicking Save.
- **Linked records**: If you link a Policy ID or Claim ID that does not exist, the API will not validate—the numbers are stored as-is. Verify IDs exist in Policies or Claims before logging.
- **Audit trail**: Each complaint is recorded with the creator's name and exact timestamp. This is immutable for compliance.
- **Pagination**: The register shows 25 complaints per page. If you logged a complaint and don't see it immediately, check you're on page 1 or refresh the page.

### Consent Compliance

- **Coverage %**: This KPI shows (`linked_policies_with_active_consent` / `total_consents_month`). A low % means customers are granting consent but not linking it to policies, or many consents are revoked. Investigate with your data team.
- **Bot detection via Verify→Accept timing**: Any consent row showing <5 seconds will display a **"Suspiciously fast — possible bot"** warning in the tile. This does not auto-block the consent; it flags it for manual review.
- **Revoked status**: Once a consent is revoked, it cannot be "un-revoked" in the UI. A new consent capture is required if the customer re-agrees.
- **Filter resets pagination**: Changing any filter automatically resets the page to 1 to avoid "no results" confusion.
- **Search scope**: The search bar looks across cellphone_masked, customer ID, and evidence_hash. Partial matches work (e.g., searching "2771" finds all Botswana mobiles starting +267 71...).
- **Channel field is null for some rows**: If a consent was migrated or imported, the otp_channel may be null. The table displays "—" for these.
- **Revoke reason not visible in UI**: The backend tracks `revoke_reason`, but the admin UI only shows "revoked" / "active" status. Details on *why* a customer revoked are in the database; request a report if needed.
- **Terms/Privacy versions in backend**: The ConsentsPage stores `terms_version` and `privacy_version` in the database but does not expose them in the table. If you need to audit consent to a specific privacy policy version, query the backend or ask the data team.

### General

- **No edit/delete from UI**: Both Complaints and Consents are append-only in the admin UI. If a mistake is made, contact the backend team to correct the database directly.
- **Time zones**: All timestamps are stored in UTC. Times displayed in the UI reflect your browser's local timezone.
- **Rate limiting**: Logging many complaints in rapid succession may hit API rate limits. Log in batches if bulk-importing complaint records.

## Related modules

- [Customer KYC](/help/customer-kyc) — KYC compliance rules for customer data capture.
- [Audit Trail](/help/audit-trail) — Immutable log of all system changes, including compliance records.
- [Policies](/help/policies) — Link policies to complaints and consents.
- [Claims](/help/claims) — Link claims to complaint records.
- [Customers](/help/customers) — Customer 360 view, including consent history.
