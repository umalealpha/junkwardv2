---
lastReviewed: 2026-06-03
---
## Overview

The Reconciliation module detects and tracks financial mismatches across policies and payments. It runs automated checks against your policy database, identifies inconsistencies (e.g., premium mismatches, unpaid invoices, payment gaps), and provides a dashboard to monitor, acknowledge, and resolve anomalies. All anomalies are categorized by severity and type for easy prioritization.

## Who uses this

- **Finance/Accounting teams** — Review and resolve high-priority anomalies; download reports.
- **Compliance officers** — Monitor resolved issues and audit trails; ensure financial accuracy.
- **CSR/Operations supervisors** — Acknowledge issues and track resolution status.

## Step-by-step

### View the reconciliation dashboard

1. Navigate to **Reconciliation** in the main menu.
2. The **Reconciliation Dashboard** displays:
   - **Finance Reports panel** — Auto-generated Excel reports (Written Premium, Ageing, Anomaly) with download links and last-run timestamps.
   - **Severity cards** — Counts of Critical, High, Medium, and Low anomalies.
   - **Overview stats** — Total open vs. resolved anomalies.
   - **Latest Run info** — Run number, status (completed/running/failed), policies checked, and anomalies found.
   - **Open Anomalies by Type** — Clickable breakdown of anomaly types with counts.
3. If no anomalies exist, an "All Clear" banner appears showing total resolved issues and the last check count.

### View and filter anomalies

1. Click **View All Anomalies** (top-right of dashboard) or click any anomaly type in the breakdown list.
2. The **Reconciliation Anomalies** page opens with a searchable, filterable table of all detected issues.
3. Filter by:
   - **Search** — Policy number, customer name, or description (Enter or blur to apply).
   - **Type** — premium_mismatch, partial_payment, unpaid_invoice, balance_accumulating, payment_gap, cancelled_but_collecting.
   - **Severity** — Critical, High, Medium, Low.
   - **Status** — Open, Acknowledged, Resolved, False Positive.
4. Results show 25 anomalies per page. Use **Prev/Next** buttons or the numbered page selector to navigate. Enter a page number in the "Go to" field to jump.

### Acknowledge an anomaly

1. On the Anomalies table, locate an **Open** status anomaly row.
2. Click the **Ack** button (blue, far-right Actions column).
3. The anomaly status changes to **Acknowledged** and refreshes the dashboard totals.

### Resolve an anomaly

1. On the Anomalies table, click the **Resolve** button (green) for any Open or Acknowledged anomaly.
2. A modal dialog appears titled "Resolve Anomaly #[ID]".
3. Enter resolution notes in the textarea (required — must have at least one character).
4. Click **Resolve** to save.
5. The anomaly status changes to **Resolved**, and dashboard counters update. Your resolution notes are stored with the record.

### Mark an anomaly as false positive

1. On the Anomalies table, click the **FP** button (gray) for any Open or Acknowledged anomaly.
2. The anomaly status changes to **False Positive** and is removed from active counts.

### Export anomalies as CSV

1. On the Anomalies page, use the filter controls to narrow results (e.g., by Status, Severity, or Type).
2. Click the **Export CSV** button (top-right).
3. The button enters a loading state ("Exporting…") while the backend generates the file (1–3 seconds typical).
4. A CSV file named `reconciliation_anomalies_YYYY-MM-DD.csv` downloads automatically.
5. The export respects all active filters — only anomalies matching the current search/type/severity/status are included.

## Field reference

| Field | Meaning | Required |
|-------|---------|----------|
| **Policy #** | Link to the policy; click to open in Policies module. Shows severity icon. | Yes |
| **Description** | Plain-text summary of the anomaly (e.g., "Premium mismatch: expected P2,500, received P2,000"). | Yes |
| **Expected** | Amount the system calculated should be (e.g., invoice total, expected balance). Formatted as BWP currency. | Conditional |
| **Actual** | Amount actually recorded in the system. Formatted as BWP currency. | Conditional |
| **Diff** | Expected minus Actual. Red if negative (shortfall), green if positive (overage). | Conditional |
| **Status** | Badge: Open (red), Acknowledged (blue), Resolved (green), False Positive (gray). Determines which action buttons appear. | Yes |
| **Last Payment** | Most recent payment date for the policy; highlighted in red if older than 90 days. | Conditional |
| **Date** | Anomaly detection date (when the check flagged it). | Yes |
| **Action Buttons** | Ack (Open only), Resolve (Open/Acknowledged), FP (Open/Acknowledged). Disabled after status changes. | N/A |

## Tips & gotchas

- **Status-dependent actions** — Ack, Resolve, and FP buttons only appear for Open or Acknowledged anomalies. Once Resolved or marked False Positive, no further actions are available (immutable states).
- **Resolution notes are required** — You cannot submit the Resolve modal without entering at least one character in the notes field. This ensures audit compliance.
- **Export filtering** — The CSV export respects all current filters. If you want all anomalies, clear all filters before exporting.
- **Export polling** — The export process polls the backend up to 60 times (every 1.5 seconds, max ~90 seconds). If it times out, try again with fewer filters or a smaller result set.
- **Dashboard data is cached** — The summary and anomalies are cached for 60 seconds. If you resolve an anomaly and the dashboard doesn't update immediately, wait 1 minute or refresh the page.
- **Finance Reports** — Three auto-generated reports appear on the dashboard (Written Premium, Ageing, Anomaly). They are generated by a Python cron engine and appear as downloadable Excel files. Status dots show: green (ok), red (error), blue animate-pulse (running), gray (never run). Hover over a report to see the last-run timestamp and file size.
- **Severity levels** — Critical (red triangle icon) is highest priority; High (orange triangle), Medium (yellow circle), Low (blue circle). The anomaly type and amounts help determine severity automatically.
- **Last payment aging** — If a payment was made more than 90 days ago, the date appears in red. This is a visual signal for follow-up.
- **Search by policy/customer** — The Search field accepts partial matches on policy number, customer name, or description. No wildcard syntax needed.
- **Page context** — The pagination footer shows "Showing X–Y of Z" when a total count is available, or just the current page number if the backend is streaming results. The "Go to page" field only appears when a total count is known.
- **All Clear state** — If totalOpen is 0, the dashboard shows a green banner instead of severity cards and stats. This banner displays the total resolved count and (if available) how many policies were checked in the latest run.

## Related modules

- [Policies](/help/policies)
- [Payments](/help/payments)
- [Accounting](/help/accounting)
- [Batch Processing](/help/batch-processing)
- [Excel Imports](/help/excel-imports)
- [Audit Trail](/help/audit-trail)
- [Cron Portal](/help/cron-portal)
