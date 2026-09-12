---
lastReviewed: 2026-06-03
---
## Overview

The Operational Anomalies module detects and tracks policy-level irregularities across the business — duplicates, premium mismatches, cancellation status inconsistencies, lapses, and payment failures. CSRs and underwriters review findings, mark them as resolved (with proof of the fix), or dismiss them (explaining why they're false positives) for audit.

## Who uses this

- **Claims & Underwriting teams** — primary users; review anomalies involving their policies, resolve issues
- **Finance / Compliance** — audit findings, verify dismissals, track resolution patterns
- **Operations** — triage engine-flagged anomalies, manage batch investigations

## Step-by-step

### View all findings

1. Open **Anomaly Findings** (from main nav).
2. You see a summary box showing count of Open, Reviewing, Resolved, and Dismissed findings.
3. Below is a table of recent detections, with columns: Detected date, Type, Branch, Customer, Product, Device key (plate/IMEI), Policy count, Policy numbers, Status, and Actions.
4. Pagination shows current page and total count; use **← Prev** / **Next →** buttons or enter a page number in the **Jump** field and press Enter.

### Filter findings

On the filter bar above the table:

1. **Anomaly type** — select from: Duplicate active policies, Cancelled but collecting, Premium amount mismatch, Large claim filed, Expired but active, Zero collections today, Payment failure spike, Lapse spike. Default is all types.
2. **Branch** — Motor, Cellphone / electronic, Other non-motor, or all.
3. **Status** — Open, Reviewing, Resolved, Dismissed, or all.
4. **Search** — enter customer name, product code, plate, IMEI, or policy number (press Enter to apply).
5. **From** and **To** date fields — narrows to detections within that date range.
6. If any filter is active, a **Clear filters** link appears; click to reset to defaults.

### Review a finding (move to "Reviewing" status)

1. In the table, find an **Open** finding.
2. Click the **Review** button (blue).
3. The finding's status changes to **Reviewing** and the button disappears from that row.

### Resolve a finding

1. On a finding with status **Open** or **Reviewing**, click **Resolve** (green button).
2. A modal opens showing the finding's customer, product, device key, and policy numbers.
3. In the **Notes** field, describe how the underlying issue was fixed (e.g., "Duplicate policy MIS-12345 cancelled" or "Premium on MIS-67890 adjusted from 5000 to 4500").
4. Notes must be at least 5 characters. The **Resolve** button is disabled until valid.
5. Click **Resolve** to save. The finding status changes to **Resolved** and notes appear in the Actions column on hover.

### Dismiss a finding

1. On a finding with status **Open** or **Reviewing**, click **Dismiss** (gray button).
2. A modal opens with the same finding details.
3. In the **Notes** field, explain why this is not a real issue (e.g., "False positive: intentional duplicate for fraud testing" or "Policy lapse is expected; customer requested pause").
4. Notes must be at least 5 characters.
5. Click **Dismiss** to save. The finding status changes to **Dismissed** and notes appear in the Actions column on hover.

## Field reference

| Field | Meaning | Required |
|-------|---------|----------|
| Detected | Date and time the anomaly engine flagged this finding | System-generated |
| Type | Anomaly category (duplicate policies, premium mismatch, lapse spike, etc.) | Yes |
| Branch | Insurance line of business: Motor, Cellphone, or Non-motor | No |
| Customer | Customer name and ID for the account with the anomaly | No |
| Product | Product code or name affected | No |
| Device key | License plate (motor) or IMEI (cellphone); null for other anomalies | No |
| # pol. | Count of policies involved in the anomaly | Yes |
| Policies | Comma-separated list of policy numbers | No |
| Status | Open, Reviewing, Resolved, or Dismissed | System-generated |
| Notes (Resolve/Dismiss modal) | Description of resolution or reason for dismissal | Yes, min 5 characters |

## Tips & gotchas

- **Status flow:** Open → (optional) Reviewing → Resolved *or* Dismissed. Once Resolved or Dismissed, the finding is locked; no further action buttons appear.
- **Review button:** Clicking Review changes status to Reviewing but does not require notes. Use it to flag that you are investigating; resolve or dismiss later.
- **Notes validation:** Both Resolve and Dismiss modals require a 5+ character note. The submit button is disabled until this is met; no automatic trimming of whitespace for length.
- **Device key:** Only populated for anomalies involving vehicles (plate) or devices with serial numbers (IMEI). Most non-motor anomalies will show "—".
- **Search scope:** The Search field matches customer name, product, plate, IMEI, or policy number. It is case-insensitive and requires pressing Enter; changes to other filters auto-apply without Enter.
- **Date range:** "From" and "To" filters apply to the detection date (when the engine flagged the finding), not the issue's occurrence date.
- **Pagination:** Results are 25 per page. Jump field accepts numbers 1 to the last page; invalid entries are ignored.
- **Summary tiles refresh:** Open, Reviewing, Resolved, and Dismissed counts are cached for 60 seconds; manual filter changes do not immediately refresh the counts (but the table does).
- **Stale data after action:** When you resolve or dismiss a finding, all finding lists and the summary are refreshed to reflect the change.
- **No bulk actions:** Findings must be actioned one at a time; there is no select-all or batch resolve/dismiss.

## Related modules

- [Policies](/help/policies)
- [Claims](/help/claims)
- [Audit Trail](/help/audit-trail)
- [Compliance](/help/compliance)
- [Underwriting](/help/underwriting)
