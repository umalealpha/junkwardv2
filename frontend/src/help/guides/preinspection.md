---
lastReviewed: 2026-06-03
---
## Overview

Pre-inspection displays inspection records for vehicles and devices entered into the system. You can search by plate number (vehicles) or IMEI/policy number (devices) and review photos and status. This module is read-only — it reports on inspections already captured but does not capture new inspections from this interface.

## Who uses this

- **Claims teams** — reviewing device inspection photos and status when processing device claims.
- **Underwriting** — checking vehicle inspection photos during underwriting review.
- **Compliance/Risk** — auditing device inspection records and approval status.
- **Admin operators** — searching inspection records for customer inquiries.

## Step-by-step

### Vehicle Pre-inspection Workflow

1. Navigate to **Preinspection > Vehicle** from the sidebar.
2. Enter a **plate number** in the Search field and press Enter or click away to filter.
3. View the paginated table with columns: **ID**, **Plate Number**, **Front** (photo), **Back** (photo), **Left** (photo), **Right** (photo), **Date**.
4. Click any photo thumbnail to open it in a new browser tab.
5. Use **Prev/Next** buttons or click a page number to navigate results (25 per page).
6. Use the **Go to** field to jump directly to a specific page number.

### Device Pre-inspection Workflow

1. Navigate to **Preinspection > Device** from the sidebar.
2. Enter a search term in the Search field (searches across IMEI, policy number, make, or model) and press Enter or click away.
3. View the paginated table with columns: **Policy #**, **IMEI**, **Make**, **Model**, **Value**, **Status**, **Date**.
4. Click a **Policy #** link to jump to that policy record.
5. Review the **Status** badge to see approval state (green for approved/active, yellow for pending, red for rejected, gray for inactive).
6. Use **Prev/Next** buttons or enter a page number in the **Go to** field to navigate (25 per page).

## Field reference

| Field | Meaning | Type | Required |
|-------|---------|------|----------|
| **Plate Number** (Vehicle) | Registration plate for the vehicle being inspected | Text | No; nullable |
| **IMEI** (Device) | International Mobile Equipment Identity for the device | Text | No; nullable |
| **Policy #** (Device) | Associated policy number for the inspected device | Text | No; nullable |
| **Make** (Device) | Device manufacturer (e.g. Apple, Samsung) | Text | No; nullable |
| **Model** (Device) | Device model name (e.g. iPhone 14, Galaxy S22) | Text | No; nullable |
| **Value** (Device) | Device value amount | Text | No; nullable |
| **Status** (Device) | Inspection approval state (approved, pending, rejected, active, inactive) | Badge | No; nullable |
| **Front/Back/Left/Right** (Vehicle) | Photos of vehicle from each angle | Image URL | No; all nullable |
| **Date** | Inspection record created date | ISO timestamp | No; nullable |

## Tips & gotchas

- **Nullable fields**: Every field except ID may be null or missing; the UI renders "—" (em dash) for empty values.
- **Search resets pagination**: When you type in the Search field and filter, the page number resets to 1; your previous position is lost.
- **Photos open in new tab**: Clicking a vehicle photo thumbnail opens the image URL in a new browser tab; there is no lightbox or in-page viewer.
- **Device status badges are case-insensitive**: The status is stored in the database and rendered as-is (e.g. "Approved") but the CSS class assignment uses `.toLowerCase()`, so "Approved" and "approved" both match the green style.
- **Policy number link**: On the Device Pre-inspection page, clicking a policy number searches the Policies module for that number and opens the policies list filtered to that policy.
- **Performance**: Results are cached for 2 minutes and do not refetch on window focus, so if an inspection is added to the database elsewhere, it may not appear in the list until the 2-minute cache expires or you manually search again.
- **Page validation on "Go to"**: You must enter a valid page number (between 1 and the last page) and press Enter; invalid or out-of-range numbers are silently ignored.
- **Pagination limits**: If 7 or fewer pages exist, all are shown; if more, page numbers are abbreviated with ellipsis (...) to show first, current range, and last page.

## Related modules

- [Policies](/help/policies) — click a policy number to view full policy details.
- [Claims](/help/claims) — device inspection status may be reviewed when processing device damage claims.
- [Underwriting](/help/underwriting) — vehicle inspection photos may be reviewed during underwriting.
- [Audit Trail](/help/audit-trail) — inspection records are logged and auditable.
